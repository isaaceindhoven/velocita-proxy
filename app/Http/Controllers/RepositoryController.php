<?php

namespace App\Http\Controllers;

use App\Composer\DistReference;
use App\Models\Repository;
use App\Services\DistService;
use App\Services\ProviderService;
use App\Services\RepositoryService;
use Symfony\Component\HttpFoundation\Response;

class RepositoryController extends Controller
{
	/** @var \App\Services\DistService */
	protected $distService;

	/** @var \App\Services\ProviderService */
	protected $providerService;

	/** @var \App\Services\RepositoryService */
	protected $repositoryService;

	public function __construct(DistService $distService, ProviderService $providerService, RepositoryService $repositoryService)
	{
		$this->distService = $distService;
		$this->providerService = $providerService;
		$this->repositoryService = $repositoryService;
	}

	/**
	 * @return \Symfony\Component\HttpFoundation\Response
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

		// Obtain dist URL
		// TODO Handle proper exceptions from this call
		$distURL = $this->distService->findDistURL($distRef);

		// Create cache and immediately stream output
		return response()
			->stream(function () use ($distRef, $distURL) {
				$this->distService->createDistCache($distRef, $distURL, function (string $data) {
					echo $data;
				});
			}, 200, ['Content-Type' => 'application/zip']);
	}

	/**
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function downloadPackage(string $repoName, string $namespace, string $package): Response
	{
		// Find the repository
		$repo = Repository::where('name', $repoName)->firstOrFail();

		// Find provider reference
		$providerRef = $this->providerService->findProviderInRepository($repo, $namespace, $package);
		if (!$providerRef) {
			return response('Provider not found', 404);
		}

		// Create cache and immediately return contents
		return response()
			->stream(function () use ($providerRef) {
				$this->providerService->createProviderCache($providerRef, function (string $data) {
					echo $data;
				});
			}, 200, ['Content-Type' => 'application/json']);
	}

	/**
	 * @return Symfony\Component\HttpFoundation\Response
	 */
	public function rootPackages(string $repoName): Response
	{
		$repo = Repository::where('name', $repoName)->firstOrFail();
		return response()
			->stream(function () use ($repo) {
				$this->repositoryService->createRootPackagesFile($repo, function (string $data) {
					echo $data;
				});
			}, 200, ['Content-Type' => 'application/json']);
	}
}
