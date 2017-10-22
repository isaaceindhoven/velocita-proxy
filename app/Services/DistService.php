<?php

namespace App\Services;

use App\Composer\DistReference;
use App\Models\Repository;
use Composer\Semver\VersionParser;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DistService
{
    const DOWNLOAD_CHUNK_SIZE = 8 * 1024;

    public function createDistCache(DistReference $distRef, string $distURL, \Closure $dataCallback = null)
    {
        $repo = $distRef->repository;
        $packageName = sprintf('%s/%s', $distRef->namespace, $distRef->package);

        Log::debug('Creating dist cache', ['repo' => $repo->name, 'package' => $packageName, 'version' => $distRef->version, 'ref' => $distRef->reference, 'type' => $distRef->type]);

        // Start the download and invoke the data callback on each chunk
        $client = new HttpClient();

        $requestConfig = $this->getGuzzleConfig($distURL);
        $response = $client->request('GET', $distURL, $requestConfig);
        $body = $response->getBody();

        $storage = Storage::disk('local');
        $tmpOut = tmpfile();
        try {
            while (!$body->eof()) {
                // Stream data to tmpfile
                $data = $body->read(self::DOWNLOAD_CHUNK_SIZE);
                fwrite($tmpOut, $data);

                // Invoke data callback
                if ($dataCallback) {
                    $dataCallback($data);
                }
            }

            // Stream tmp contents to target file
            rewind($tmpOut);
            $targetPath = sprintf('repo/%s/dist/%s/%s-%s.%s', $repo->name, $packageName, $distRef->version, $distRef->reference, $distRef->type);
            $storage->putStream($targetPath, $tmpOut);
        } finally {
            fclose($tmpOut);
        }
    }

    /**
     * @return string The distribution's URL
     */
    public function findDistURL(DistReference $distRef): string
    {
        $repo = $distRef->repository;

        // Load the provider file
        $packageName = sprintf('%s/%s', $distRef->namespace, $distRef->package);
        $storage = Storage::disk('local');
        $providerData = json_decode($storage->read(sprintf('repo/%s/pack/%s.json', $repo->name, $packageName)));

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

        // Add GitHub API token if configured and applicable
        if (starts_with($url, 'https://api.github.com/')) {
            $githubUsername = config('repositories.github.api.username');
            $githubToken = config('repositories.github.api.token');

            if (($githubUsername !== null) && ($githubToken !== null)) {
                Log::debug('Performing authenticated GitHub API request');
                $config['auth'] = [$githubUsername, $githubToken];
            }
        }

        return $config;
    }
}
