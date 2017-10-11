<?php

namespace App\Http\Controllers;

use App\Composer\DistReference;
use App\Models\Provider;
use App\Models\Repository;
use App\Services\DistService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RepositoryController extends Controller
{
	/** @var App\Services\DistService */
	protected $distService;

	public function __construct(DistService $distService)
	{
		$this->distService = $distService;
	}

	/**
	 * @return Symfony\Component\HttpFoundation\Response
	 */
	public function downloadDist(string $repoName, string $namespace, string $package, string $version, string $reference, string $type): Response
	{
		// Find the repository
		$repo = Repository::where('name', $repoName)->firstOrFail();

		// Create reference from arguments
		$distRef = new DistReference();
		$distRef->repository = $repo;
		$distRef->namespace = $namespace;
		$distRef->package = $package;
		$distRef->version = $version;
		$distRef->reference = $reference;
		$distRef->type = $type;

		// Determine dist URL
		$distURL = $this->distService->findDistURL($distRef);
		if ($distURL === null) {
			return response('Unable to find distribution location', 404);
		}

		// Create cache and immediately stream output
		return response()
			->stream(function () use ($distRef, $distURL) {
				$this->distService->createDistCache($distRef, $distURL, function (string $data) {
					echo $data;
				});
			}, 200, [
				'Content-Type' => 'application/zip',
			]);
	}

	public function downloadPackage(string $repoName, string $namespace, string $package)
	{
		// Find the repository
		$repo = Repository::where('name', $repoName)
			->first();
		if ($repo === null) {
			return response('Repository not found', 404);
		}

		// Walk through provider includes to find provider hash
		$providerName = sprintf('%s/%s', $namespace, $package);
		$parentInclude = null;
		$providerSHA256 = null;
		foreach ($repo->providerIncludes as $providerInclude) {
			$includeFile = str_replace('%hash%', $providerInclude->sha256, $providerInclude->pattern);
			$includeFile = basename($includeFile);

			$includePath = sprintf('%s/app/mirrors/%s/%s', storage_path(), $repo->name, $includeFile);
			$provider = $this->findProviderInProviderInclude($includePath, $providerName);

			if ($provider !== null) {
				$parentInclude = $providerInclude;
				$providerSHA256 = $provider->sha256;
				break;
			}
		}

		// Handle provider not found
		if ($providerSHA256 === null) {
			return response('Provider not found', 404);
		}

		// Download provider file
		$providerPath = str_replace(['%package%', '%hash%'], [$providerName, $providerSHA256], $repo->providers_pattern);
		$remoteProviderURL = config("repositories.mirrors.{$repo->name}.url") . $providerPath;
		$providerData = file_get_contents($remoteProviderURL);

		// Validate contents
		$providerDataSHA256 = hash('sha256', $providerData);
		if ($providerDataSHA256 !== $providerSHA256) {
			return response('Provider data corrupted', 502);
		}

		// Store provider file in our public repo
		$providerLocalPath = sprintf('%s/repo/%s/pack/%s.json', public_path(), $repo->name, $providerName);
		$providerLocalDir = dirname($providerLocalPath);
		if (!is_dir($providerLocalDir)) {
			mkdir($providerLocalDir, 0750, true);
		}
		file_put_contents($providerLocalPath, $providerData);

		// Register provider model
		$provider = $repo->providers()
			->where('name', $providerName)
			->first();
		if ($provider === null) {
			$provider = new Provider();
			$provider->name = $providerName;
			$provider->repository()->associate($repo);
		}
		$provider->providerInclude()->associate($parentInclude);
		$provider->sha256 = $providerSHA256;
		$provider->save();

		// Output provider data - next time, the request will be served directly through the file we just created
		return response($providerData, 200)
			->header('Content-Type', 'application/json');
	}

	protected function findProviderInProviderInclude($path, $provider)
	{
		$list = json_decode(file_get_contents($path));
		$providers = $list->providers;
		if (!isset($providers->{$provider})) {
			return null;
		}
		return $providers->{$provider};
	}
}
