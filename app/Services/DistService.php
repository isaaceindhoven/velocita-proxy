<?php

namespace App\Services;

use App\Models\Repository;
use Composer\Semver\VersionParser;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\ResponseInterface;

class DistService
{
    const DOWNLOAD_CHUNK_SIZE = 8 * 1024;

    public function createDistCache(string $site, string $path, \Closure $dataCallback = null)
    {
        Log::debug('Creating dist cache', ['site' => $site, 'path' => $path]);

        // Perform request based on type
        switch (config("dist.$site.type")) {
            case 'github':
                $response = $this->performGitHubRequest($site, $path);
                break;
            default:
                throw new \Exception(sprintf('Unsupported site: %s', $site));
        }

        // Start the download and invoke the data callback on each chunk
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
            $targetPath = sprintf('dist/%s/%s', $site, $path);
            $storage->putStream($targetPath, $tmpOut);
        } finally {
            fclose($tmpOut);
        }
    }

    protected function performGitHubRequest(string $site, string $path): ResponseInterface
    {
        $baseURL = config("dist.$site.options.baseURL");
        $distURL = $baseURL . $path;

        $requestConfig = $this->getDefaultGuzzleConfig();

        $githubUsername = config("dist.$site.options.username");
        $githubToken = config("dist.$site.options.token");

        if (($githubUsername !== null) && ($githubToken !== null)) {
            Log::debug('Performing authenticated GitHub API request');
            $requestConfig['auth'] = [$githubUsername, $githubToken];
        }

        $client = new HttpClient();
        return $client->request('GET', $distURL, $requestConfig);
    }

    protected function getDefaultGuzzleConfig(): array
    {
        return [
            'stream' => true,
        ];
    }
}
