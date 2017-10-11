<?php

namespace App\Console\Commands;

use App\Models\Provider;
use App\Models\ProviderInclude;
use App\Models\Repository;
use Illuminate\Console\Command;

class MirrorSynchronize extends Command {
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
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
		$this->synchronizeRepositories();
	}

	private function synchronizeRepositories() {
		$baseSourceDir = storage_path() . '/app/mirrors';

		$repositories = config('repositories.mirrors');
		foreach ($repositories as $repoName => $repoConfig) {
			$this->info("Synchronizing repository: $repoName");

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
			$updatedProviderIncludes = [];
			$deleteIncludePaths = [];
			$patternsSeen = [];
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
					$includePath = str_replace('%hash%', $providerInclude->sha256, $includePattern);
					$oldIncludeFile = basename($includePath);
					$deleteIncludePaths[] = sprintf('%s/%s', $repoSourceDir, $oldIncludeFile);

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

				if ($needsUpdate) {
					// Download include file
					$this->line("Downloading provider include: $includeFile");
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
					$updatedProviderIncludes[] = $providerInclude;
				}
			}

			// Delete old provider include files
			foreach ($deleteIncludePaths as $deletePath) {
				$deleteFile = basename($deletePath);
				$this->line("Deleting old provider include: $deleteFile");
				unlink($deletePath);
			}

			// Delete includes we've not seen
			ProviderInclude::whereNotIn('pattern', $patternsSeen)
				->delete();

			// TODO: process includes to update

			// Write packages.json
			$this->writePackagesJson($repo);
		}
    }

	private function writePackagesJson(Repository $repo)
	{
		$this->info("Writing root {$repo->name}/packages.json");

		$repoDir = sprintf('%s/repo/%s', public_path(), $repo->name);
		$rootJsonPath = $repoDir . '/packages.json';
		$rootJson = [
			// TODO: are these necessary?
			'notify-batch'       => 'https://packagist.org/downloads/',
			'search'             => 'https://packagist.org' . $repo->search_pattern,

			'providers-url'      => $repo->providers_pattern,
			'providers-lazy-url' => sprintf('/repo/%s/%%package%%.json', $repo->name),
			'mirrors' => [
				[
					'dist-url'  => url(sprintf('/repo/%s/dist/%%package%%/%%version%%/%%reference%%.%%type%%', $repo->name)),
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
