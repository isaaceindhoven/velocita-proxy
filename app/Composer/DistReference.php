<?php

namespace App\Composer;

class DistReference
{
	/** @var App\Models\Repository */
	public $repository;

	/** @var string */
	public $namespace;

	/** @var string */
	public $package;

	/** @var string */
	public $version;

	/** @var string */
	public $reference;

	/** @var string */
	public $type;
}
