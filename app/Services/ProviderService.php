<?php

namespace App\Services;

use App\Composer\ProviderReference;
use App\Models\Provider;
use App\Models\ProviderInclude;
use App\Models\Repository;
use Illuminate\Support\Facades\Log;

class ProviderService
{
	/**
	 * @param \App\Composer\ProviderReference $providerRef
	 * @param \Closure $dataCallback
	 */
	public function createProviderCache(ProviderReference $providerRef, \Closure $dataCallback = null)
	{
		$repo = $providerRef->repository;
		$providerName = $providerRef->getName();

		Log::debug("Creating provider cache", ['repo' => $repo->id, 'provider' => $providerName, 'sha256' => $providerRef->sha256]);

		// Download provider file
		$providerPath = str_replace(['%package%', '%hash%'], [$providerName, $providerRef->sha256], $repo->providers_pattern);
		$remoteProviderURL = config("repositories.mirrors.{$repo->name}.url") . $providerPath;
		$providerData = file_get_contents($remoteProviderURL);

		// Validate contents
		$providerDataSHA256 = hash('sha256', $providerData);
		if ($providerDataSHA256 !== $providerRef->sha256) {
			throw new \Exception('Provider data corrupted');
		}

		// Store provider file in our public repo
		$providerLocalPath = public_path(sprintf('repo/%s/pack/%s.json', $repo->name, $providerName));
		$providerLocalDir = dirname($providerLocalPath);
		if (!is_dir($providerLocalDir)) {
			mkdir($providerLocalDir, 0750, true);
		}
		file_put_contents($providerLocalPath, $providerData);

		// Register provider model
		$provider = $repo->providers()
			->where('namespace', $providerRef->namespace)
			->where('package', $providerRef->package)
			->first();
		if ($provider === null) {
			$provider = new Provider();
			$provider->namespace = $providerRef->namespace;
			$provider->package = $providerRef->package;
			$provider->repository()->associate($repo);
		}
		$provider->providerInclude()->associate($providerRef->providerInclude);
		$provider->sha256 = $providerRef->sha256;
		$provider->save();

		// Invoke data callback
		if ($dataCallback) {
			$dataCallback(file_get_contents($providerLocalPath));
		}
	}

	/**
	 * @return \App\Composer\ProviderReference|null
	 */
	protected function findProviderInProviderInclude(Repository $repo, ProviderInclude $providerInclude, string $namespace, string $package)
	{
		// Construct path to local provider include file
		$includeFile = str_replace('%hash%', $providerInclude->sha256, $providerInclude->pattern);
		$includeFile = basename($includeFile);
		$includePath = storage_path(sprintf('app/mirrors/%s/%s', $repo->name, $includeFile));

		// Find provider entry
		$list = json_decode(file_get_contents($includePath));
		$providers = $list->providers;
		$providerName = sprintf('%s/%s', $namespace, $package);
		if (!isset($providers->{$providerName})) {
			return null;
		}

		// Return provider reference
		$ref = new ProviderReference();
		$ref->repository = $repo;
		$ref->providerInclude = $providerInclude;
		$ref->namespace = $namespace;
		$ref->package = $package;
		$ref->sha256 = $providers->{$providerName}->sha256;
		return $ref;
	}

	/**
	 * @return \App\Composer\ProviderReference|null
	 */
	public function findProviderInRepository(Repository $repo, string $namespace, string $package)
	{
		foreach ($repo->providerIncludes as $providerInclude) {
			$providerRef = $this->findProviderInProviderInclude($repo, $providerInclude, $namespace, $package);
			if ($providerRef !== null) {
				return $providerRef;
			}
		}

		// Found nothing
		return null;
	}
}
