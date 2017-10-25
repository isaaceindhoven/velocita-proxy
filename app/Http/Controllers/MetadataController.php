<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\Response;

class MetadataController extends Controller
{
    /**
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getEndpoints(): Response
    {
        // TODO: process types correctly

        $repos = [];
        foreach (config('repositories') as $repoName => $repoConfig) {
            $repos[$repoName] = [
                'remoteURL' => $repoConfig['url'],
                'path'      => sprintf('/repo/%s', $repoName),
            ];
        }

        $dists = [];
        foreach (config('dist') as $distName => $distConfig) {
            $options = $distConfig['options'];
            $dists[$distName] = [
                'remoteURL' => $options['baseURL'],
                'path'      => sprintf('/dist/%s', $distName),
            ];
        }

        return response()->json([
            'repositories' => $repos,
            'distributionChannels' => $dists,
        ]);
    }
}
