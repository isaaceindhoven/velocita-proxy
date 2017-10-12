<?php

namespace App\Services;

use App\Composer\DistReference;
use App\Models\Repository;
use Composer\Semver\VersionParser;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Support\Facades\Log;

class DistService
{
	const DOWNLOAD_CHUNK_SIZE = 8 * 1024;

	public function createDistCache(DistReference $distRef, string $distURL, \Closure $dataCallback = null)
	{
		$repo = $distRef->repository;
		$packageName = sprintf('%s/%s', $distRef->namespace, $distRef->package);

		Log::debug('Creating dist cache', ['repo' => $repo->name, 'package' => $packageName, 'version' => $distRef->version, 'ref' => $distRef->reference, 'type' => $distRef->type]);

		// Construct local path
		$distLocalPath = storage_path(sprintf('app/repo/%s/dist/%s/%s-%s.%s', $repo->name, $packageName, $distRef->version, $distRef->reference, $distRef->type));
		$distLocalDir = dirname($distLocalPath);
		if (!is_dir($distLocalDir)) {
			mkdir($distLocalDir, 0750, true);
		}

		// Start the download and invoke the data callback on each chunk
		$client = new HttpClient();

		$requestConfig = $this->getGuzzleConfig($distURL);
		$response = $client->request('GET', $distURL, $requestConfig);
		$body = $response->getBody();

		// TODO: write to temporary file and rename afterwards

		$fpOut = fopen($distLocalPath, 'wb');
		try {
			while (!$body->eof()) {
				// Read data from origin
				$data = $body->read(self::DOWNLOAD_CHUNK_SIZE);

				// Write data to local cache
				fwrite($fpOut, $data);

				// Invoke data callback
				if ($dataCallback) {
					$dataCallback($data);
				}
			}
		} finally {
			fclose($fpOut);
		}
	}

	/**
	 * @return string The distribution's URL
	 */
	public function findDistURL(DistReference $distRef): string
	{
		$repo = $distRef->repository;

		// Locate the provider file
		$packageName = sprintf('%s/%s', $distRef->namespace, $distRef->package);
		$providerPath = storage_path(sprintf('app/repo/%s/pack/%s.json', $repo->name, $packageName));
		if (!is_readable($providerPath)) {
			throw new \Exception('Missing provider file');
		}

		// Parse provider data
		$providerData = json_decode(file_get_contents($providerPath));

		// Find data for the requested package
		if (!isset($providerData->packages->{$packageName})) {
			throw new \Exception('Missing package in provider file');
		}
		$packageData = $providerData->packages->{$packageName};

		// Iterate over available versions and select one with a matching version property
		$matchingVersion = null;
		$versionParser = new VersionParser();
		foreach ($packageData as $versionKey => $versionData) {
			// Obtain normalized version
			if (isset($versionData->version_normalized)) {
				$currentVersion = $versionData->version_normalized;
			} else if (isset($versionData->version)) {
				$currentVersion = $versionParser->normalize($versionData->version);
			} else {
				Log::warning("No version information available for $packageName at key $versionKey");
				continue;
			}

			// Is this a match?
			if ($currentVersion === $distRef->version) {
				$matchingVersion = $versionData;
				break;
			}
		}
		if ($matchingVersion === null) {
			throw new \Exception('Unknown package version');
		}

		// Confirm that dist info matches requested reference and type
		$distData = $matchingVersion->dist;
		if (($distData->reference !== $distRef->reference) || ($distData->type !== $distRef->type)) {
			throw new \Exception('Found version does not match reference and/or type');
		}

		return $distData->url;
	}

	/**
	 * Creates a request configuration for the provided URL.
	 *
	 * @param string $url The URL to return request configuration for
	 *
	 * @return array Request configuration for Guzzle
	 */
	protected function getGuzzleConfig(string $url): array
	{
		$config = ['stream' => true];

		// Add GitHub API token if applicable
		if (starts_with($url, 'https://api.github.com/')) {
			$githubUsername = config('repositories.github.api.username');
			$githubToken = config('repositories.github.api.token');
			if (($githubUsername !== null) && ($githubToken !== null)) {
				$config['auth'] = [$githubUsername, $githubToken];
			}
		}

		return $config;
	}
}
