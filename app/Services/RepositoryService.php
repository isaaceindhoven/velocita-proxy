<?php

namespace App\Services;

use App\Models\Repository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RepositoryService
{
    /**
     * @param \App\Models\Repository $repo
     * @param \Closure $dataCallback
     */
    public function createRootPackagesFile(Repository $repo, \Closure $dataCallback = null)
    {
        Log::debug('Writing repository packages.json', ['repo' => $repo->name]);

        // TODO Make optional based on goal (composer plugin vs. standalone)
        $includePreferredMirror = false;

        $rootJson = [
            // TODO: hardcoded right now - best practices around notify-batch, how to deal with
            //       when sourcing multiple origins?
            'notify-batch'       => 'https://packagist.org/downloads/',

            'providers-lazy-url' => '/%%package%%.json',
        ];
        if ($includePreferredMirror) {
            $rootJson['mirrors'] = [
                [
                    'dist-url'  => url(sprintf('/dist/%s/%%package%%/%%reference%%', 'github')),
                    'preferred' => true,
                ]
            ];
        }
        $rootJson = json_encode($rootJson);

        // Write packages.json for future caching
        $storage = Storage::disk('local');
        $storage->put(sprintf('repo/%s/packages.json', $repo->name), $rootJson);

        if ($dataCallback) {
            $dataCallback($rootJson);
        }
    }
}
