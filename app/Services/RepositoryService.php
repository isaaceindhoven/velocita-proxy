<?php

namespace App\Services;

use App\Models\Repository;

class RepositoryService
{
    /**
     * @param \App\Models\Repository $repo
     *
     * @return array Structure for repository information
     */
    public function getPackagesStructure(Repository $repo): array
    {
        return [
            // TODO: Use stored notify-batch
            'notify-batch' => 'https://packagist.org/downloads/',

            'providers-lazy-url' => sprintf('/repo/%s/%%package%%.json', $repo->name),
            'mirrors' => [
                'dist-url'  => url(sprintf('/dist/%s/%%package%%/%%reference%%', 'github')),
                'preferred' => true,
            ],
        ];
    }

    /**
     * @param \App\Models\Repository $repo
     *
     * @return array Structure for Velocita-compatible repository information
     */
    public function getPackagesVelocitaStructure(Repository $repo): array
    {
        return [
            'providers-lazy-url' => '/%package%.json',
        ];
    }
}
