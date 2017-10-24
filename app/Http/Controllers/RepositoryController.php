<?php

namespace App\Http\Controllers;

use App\Models\Repository;
use App\Services\ProviderService;
use App\Services\RepositoryService;
use Symfony\Component\HttpFoundation\Response;

class RepositoryController extends Controller
{
	/** @var \App\Services\ProviderService */
	protected $providerService;

	/** @var \App\Services\RepositoryService */
	protected $repositoryService;

	public function __construct(ProviderService $providerService, RepositoryService $repositoryService)
	{
		$this->providerService = $providerService;
		$this->repositoryService = $repositoryService;
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
	 * @return \Symfony\Component\HttpFoundation\Response
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
