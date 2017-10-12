<?php

namespace App\Console\Commands;

use App\Composer\ProviderReference;
use App\Models\ProviderInclude;
use App\Models\Repository;
use App\Services\ProviderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MirrorSynchronize extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'mirror:synchronize';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Synchronize all repositories';

	/**
	 * @var \App\Services\ProviderService
	 */
	protected $providerService;

	public function __construct(ProviderService $providerService)
	{
		parent::__construct();

		$this->providerService = $providerService;
	}

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function handle()
	{
		$this->synchronizeRepositories();
	}

	protected function synchronizeRepositories()
	{
		$baseSourceDir = storage_path('app/mirrors');

		$repositories = config('repositories.mirrors');
		foreach ($repositories as $repoName => $repoConfig) {
			Log::info('Synchronizing repository', ['repo' => $repoName]);

			// Create source dir
			$repoSourceDir = sprintf('%s/%s', $baseSourceDir, $repoName);
			if (!is_dir($repoSourceDir)) {
				mkdir($repoSourceDir, 0750, true);
			}

			$repoURL = config("repositories.mirrors.$repoName.url");
			$packagesFile = 'packages.json';
			$packagesURL = sprintf('%s/%s', $repoURL, $packagesFile);

			$packagesData = file_get_contents($packagesURL);
			file_put_contents(sprintf('%s/%s', $repoSourceDir, $packagesFile), $packagesData);
			$packagesData = json_decode($packagesData);

			// Sync repo model
			$repo = Repository::where('name', $repoName)->first();
			if ($repo === null) {
				$repo = new Repository();
				$repo->name = $repoName;
			}
			$repo->providers_pattern = $packagesData->{'providers-url'};
			$repo->search_pattern = $packagesData->search;
			$repo->save();

			// TODO: interpret packages key

			// Keep track of includes to update and delete
			$deleteIncludePaths = [];
			$patternsSeen = [];
			$updateProviderReferences = [];
			foreach ($packagesData->{'provider-includes'} as $includePattern => $includeData) {
				$needsUpdate = false;
				$patternsSeen[] = $includePattern;

				// Sync include model
				$providerInclude = $repo->providerIncludes()->where('pattern', $includePattern)->first();
				if ($providerInclude === null) {
					$providerInclude = new ProviderInclude();
					$providerInclude->repository()->associate($repo);
					$providerInclude->pattern = $includePattern;

					$needsUpdate = true;
				}

				// Check if SHA256 was updated
				$sha256 = $includeData->sha256;
				if ($providerInclude->sha256 !== $sha256) {
					// Mark old file for deletion
					if ($providerInclude->exists) {
						$includePath = str_replace('%hash%', $providerInclude->sha256, $includePattern);
						$oldIncludeFile = basename($includePath);
						$deleteIncludePaths[] = sprintf('%s/%s', $repoSourceDir, $oldIncludeFile);
					}

					$providerInclude->sha256 = $sha256;
					$needsUpdate = true;
				}

				// Check if we have a local copy of this file
				$includePath = str_replace('%hash%', $sha256, $includePattern);
				$includeFile = basename($includePath);
				$includeSourcePath = sprintf('%s/%s', $repoSourceDir, $includeFile);
				if (!is_readable($includeSourcePath)) {
					$needsUpdate = true;
				}

				// Proceed with next include if we don't need an update
				if (!$needsUpdate) {
					continue;
				}

				// Download include file
				Log::debug('Downloading provider include', ['repo' => $repo->name, 'file' => $includeFile]);
				$includeData = file_get_contents(sprintf('%s/%s', $repoURL, $includePath));

				// Validate include data
				$includeDataSHA256 = hash('sha256', $includeData);
				if ($includeDataSHA256 !== $sha256) {
					throw new \Exception('Provider include data corrupted (hash mismatch)');
				}

				// Store include file
				file_put_contents($includeSourcePath, $includeData);

				// Store model
				$repo->providerIncludes()->save($providerInclude);

				// Iterate over known providers, check for SHA256 updates and register providers if changed
				$providerList = json_decode($includeData)->providers;
				foreach ($providerInclude->providers as $provider) {
					if (!isset($providerList->{$provider->name})) {
						continue;
					}
					$providerData = $providerList->{$provider->name};
					if ($providerData->sha256 !== $provider->sha256) {
						$providerRef = new ProviderReference();
						$providerRef->repository = $repo;
						$providerRef->providerInclude = $providerInclude;
						$providerRef->namespace = $provider->namespace;
						$providerRef->package = $provider->package;
						$providerRef->sha256 = $providerData->sha256;

						$updateProviderReferences[] = $providerRef;
					}
				}
			}

			// Delete old provider include files
			foreach ($deleteIncludePaths as $deletePath) {
				if (file_exists($deletePath)) {
					Log::debug("Deleting old include file", ['repo' => $repo->name, 'file' => basename($deletePath)]);
					unlink($deletePath);
				}
			}

			// Delete includes we've not seen
			$deleteIncludes = ProviderInclude::whereNotIn('pattern', $patternsSeen)->get();
			foreach ($deleteIncludes as $providerInclude) {
				DB::transaction(function () use ($providerInclude) {
					// TODO: don't delete
					$providerInclude->providers()->delete();
					$providerInclude->delete();
				});
			}

			// Update provider caches
			foreach ($updateProviderReferences as $providerRef) {
				$this->providerService->createProviderCache($providerRef);
			}

			// Write packages.json
			$this->writePackagesJson($repo);
		}
    }

	protected function writePackagesJson(Repository $repo)
	{
		Log::debug('Writing repository packages.json', ['repo' => $repo->name]);

		$repoDir = sprintf('%s/repo/%s', public_path(), $repo->name);
		$rootJsonPath = $repoDir . '/packages.json';
		$rootJson = [
			// TODO: are these necessary?
			// TODO: configure
			'notify-batch'       => 'https://packagist.org/downloads/',
			'search'             => 'https://packagist.org' . $repo->search_pattern,

			'providers-url'      => $repo->providers_pattern,
			'providers-lazy-url' => sprintf('/repo/%s/pack/%%package%%.json', $repo->name),
			'mirrors' => [
				[
					'dist-url'  => url(sprintf('/repo/%s/dist/%%package%%/%%version%%-%%reference%%.%%type%%', $repo->name)),
					'preferred' => true,
				]
			],
		];
		if (!is_dir($repoDir)) {
			mkdir($repoDir, 0750, true);
		}
		file_put_contents($rootJsonPath, json_encode($rootJson));
	}
}
