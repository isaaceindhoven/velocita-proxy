<?php

namespace App\Http\Controllers;

use App\Models\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RepositoryController extends Controller
{
    public function package($repoName, $namespace, $package)
	{
		// Find the repository
		$repo = Repository::where('name', $repoName)
			->first();
		if ($repo === null) {
			return response('Repository not found', 404);
		}

		// Try to find a registered provider
		$providerName = sprintf('%s/%s', $namespace, $package);
		$provider = $repo->providers()
			->where('name', $providerName)
			->first();

		// TODO: support existing providers (race condition? broken logic?)
		if ($provider !== null) {
			throw new \Exception('unexpected existing provider');
		}

		// Walk through provider includes to find provider hash
		$providerSHA256 = null;
		foreach ($repo->providerIncludes as $providerInclude) {
			$includeFile = str_replace('%hash%', $providerInclude->sha256, $providerInclude->pattern);
			$includeFile = basename($includeFile);

			$includePath = sprintf('%s/app/mirrors/%s/%s', storage_path(), $repo->name, $includeFile);
			$provider = $this->findProviderInProviderInclude($includePath, $providerName);
			
			if ($provider !== null) {
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

		// Store provider file in our public repo
		$providerLocalPath = sprintf('%s/repo/%s%s', public_path(), $repo->name, $providerPath);
		$providerLocalDir = dirname($providerLocalPath);
		if (!is_dir($providerLocalDir)) {
			mkdir($providerLocalDir, 0750, true);
		}
		file_put_contents($providerLocalPath, $providerData);

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
