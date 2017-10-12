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

		$rootJson = [
			// TODO: are these necessary?
			// TODO: configure
			'notify-batch'       => 'https://packagist.org/downloads/',
			'search'             => 'https://packagist.org' . $repo->search_pattern,

			'providers-url'      => $repo->providers_pattern,
			'providers-lazy-url' => sprintf('/repo/%s/pack/%%package%%.json', $repo->name),
			'mirrors' => [
				[
					'dist-url'  => url(sprintf('/repo/%s/dist/%%package%%/%%version%%-%%reference%%.%%type%%', $repo->name)),
					'preferred' => true,
				]
			],
		];
		$rootJson = json_encode($rootJson);

		$storage = Storage::disk('local');
		$storage->put(sprintf('repo/%s/packages.json', $repo->name), $rootJson);

		if ($dataCallback) {
			$dataCallback($rootJson);
		}
	}
}
