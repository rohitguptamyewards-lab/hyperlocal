<?php

namespace App\Modules\Webhook\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Stores HMAC-SHA256 signing keys for inbound webhook sources.
 *
 * Secrets are stored encrypted via Laravel's encrypt() / decrypt().
 * Each source (e.g. 'ewrds', 'internal') can have multiple key IDs to
 * support key rotation without downtime.
 *
 * Owner module: Webhook
 * Table owned: webhook_signing_keys
 */
class WebhookSigningKey extends Model
{
    protected $table = 'webhook_signing_keys';

    protected $fillable = [
        'source',
        'key_id',
        'secret',
        'is_active',
        'expires_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Return the decrypted secret value.
     */
    public function getDecryptedSecret(): string
    {
        return decrypt($this->secret);
    }
}
