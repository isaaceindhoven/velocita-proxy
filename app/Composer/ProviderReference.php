<?php

namespace App\Composer;

use App\Models\ProviderInclude;
use App\Models\Repository;

class ProviderReference
{
	/** @var Repository */
	public $repository;

	/** @var ProviderInclude */
	public $providerInclude;

	/** @var string */
	public $namespace;

	/** @var string */
	public $package;

	/** @var string */
	public $sha256;

	public function getName(): string
	{
		return sprintf('%s/%s', $this->namespace, $this->package);
	}
}
