<?php

namespace App\Modules\IntegrationHub\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Merchant;

/**
 * One row per merchant per external provider.
 * config is encrypted JSON — use IntegrationResolverService to decrypt and instantiate adapters.
 *
 * Owner module: IntegrationHub
 * Tables owned: merchant_integrations
 * DO NOT read config directly — always go through IntegrationResolverService.
 */
class MerchantIntegration extends Model
{
    protected $fillable = [
        'merchant_id',
        'provider',
        'config',
        'is_loyalty_source',
        'is_active',
    ];

    protected $casts = [
        'config'           => 'encrypted:array',
        'is_loyalty_source' => 'boolean',
        'is_active'        => 'boolean',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
