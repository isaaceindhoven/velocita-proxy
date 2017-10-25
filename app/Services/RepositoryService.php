<?php

namespace App\Services;

use App\Models\Repository;

class RepositoryService
{
    /**
     * @param \App\Models\Repository $repo
     * @param string $relativeURL
     *
     * @return string
     */
    protected function getAbsoluteRepositoryURL(Repository $repo, string $relativeURL): string
    {
        $baseURL = config("repositories.{$repo->name}.url");
        return $baseURL . $relativeURL;
    }

    /**
     * @param \App\Models\Repository $repo
     *
     * @return array Structure for repository information
     */
    public function getPackagesStructure(Repository $repo): array
    {
        return [
            'notify'       => $this->getAbsoluteRepositoryURL($repo, $repo->notify),
            'notify-batch' => $this->getAbsoluteRepositoryURL($repo, $repo->notify_batch),

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
            'notify'       => $this->getAbsoluteRepositoryURL($repo, $repo->notify),
            'notify-batch' => $this->getAbsoluteRepositoryURL($repo, $repo->notify_batch),

            'providers-lazy-url' => '/%package%.json',
        ];
    }
}
