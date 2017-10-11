<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Provider extends Model
{
	/**
	 * @return BelongsTo
	 */
	public function repository(): BelongsTo
	{
		return $this->belongsTo(Repository::class);
	}

	/**
	 * @return BelongsTo
	 */
	public function providerInclude(): BelongsTo
	{
		return $this->belongsTo(ProviderInclude::class, 'provider_include_id');
	}
}
