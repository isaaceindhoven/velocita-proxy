<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProviderInclude extends Model {
	/**
	 * @var string
	 */
	protected $table = 'provider_includes';

	/**
	 * @return BelongsTo
	 */
	public function repository(): BelongsTo
	{
		return $this->belongsTo(Repository::class);
	}

	/**
	 * @return HasMany
	 */
	public function providers(): HasMany
	{
		return $this->hasMany(Provider::class);
	}
}
