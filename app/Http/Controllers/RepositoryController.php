<?php

namespace App\Http\Controllers;

use App\Models\Provider;
use App\Models\Repository;
use Composer\Semver\VersionParser;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RepositoryController extends Controller
{
	public function downloadDist(string $repoName, string $namespace, string $package, string $version, string $reference, string $type)
	{
		// Find the repository
		$repo = Repository::where('name', $repoName)
			->first();
		if ($repo === null) {
			return response('Repository not found', 404);
		}

		// Locate the provider file
		$packageName = sprintf('%s/%s', $namespace, $package);
		$providerPath = sprintf('%s/repo/%s/%s.json', public_path(), $repo->name, $packageName);
		if (!is_readable($providerPath)) {
			return response('Unknown provider', 404);
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
			if ($currentVersion === $version) {
				$matchingVersion = $versionData;
				break;
			}
		}
		if ($matchingVersion === null) {
			return response('Unknown version', 404);
		}

		// Confirm that dist info matches requested reference and type
		$distData = $matchingVersion->dist;
		if (($distData->reference !== $reference) || ($distData->type !== $type)) {
			return response('Unknown dist for this version', 404);
		}

		// Prepare download
		$distURL = $distData->url;
		$distLocalPath = sprintf('%s/repo/%s/dist/%s/%s/%s.%s', public_path(), $repo->name, $packageName, $version, $reference, $type);
		$distLocalDir = dirname($distLocalPath);
		if (!is_dir($distLocalDir)) {
			mkdir($distLocalDir, 0750, true);
		}

		// Download dist and stream to output immediately
		$storeAndOutput = function() use ($distURL, $distLocalPath) {
			$client = new HttpClient();
			$response = $client->request('GET', $distURL, ['stream' => true]);
			$body = $response->getBody();

			// TODO: write to temporary file and rename afterwards

			$fpOut = fopen($distLocalPath, 'wb');
			try {
				while (!$body->eof()) {
					$data = $body->read(8192);
					fwrite($fpOut, $data);
					echo $data;
				}
			} finally {
				fclose($fpOut);
			}
		};
		return response()
			->stream($storeAndOutput, 200, [
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
		$providerLocalPath = sprintf('%s/repo/%s/%s/%s.json', public_path(), $repo->name, $namespace, $package);
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

	private function findProviderInProviderInclude($path, $provider)
	{
		$list = json_decode(file_get_contents($path));
		$providers = $list->providers;
		if (!isset($providers->{$provider})) {
			return null;
		}
		return $providers->{$provider};
	}
}
