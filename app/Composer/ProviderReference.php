<?php

namespace App\Composer;

class ProviderReference
{
	/** @var App\Models\Repository */
	public $repository;

	/** @var App\Models\ProviderInclude */
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
