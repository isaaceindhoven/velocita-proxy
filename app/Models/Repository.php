<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Repository extends Model
{
	/**
	 * @var string
	 */
	protected $table = 'repositories';

	/**
	 * @return HasMany
	 */
	public function providerIncludes(): HasMany
	{
		return $this->hasMany(ProviderInclude::class);
	}

	/**
	 * @return HasMany
	 */
	public function providers(): HasMany
	{
		return $this->hasMany(Provider::class);
	}
}
