<?php

namespace App\Modules\WhatsAppCredit\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Denormalized WhatsApp credit balance per merchant.
 * One row per merchant. Updated atomically in locked DB transactions.
 *
 * Owner module: WhatsAppCredit
 * Table owned: merchant_whatsapp_balance
 *
 * DO NOT read this table outside a locked transaction when making credit decisions.
 * Use WhatsAppCreditService::deduct() which handles locking internally.
 */
class MerchantWhatsAppBalance extends Model
{
    protected $table = 'merchant_whatsapp_balance';

    public $timestamps = false; // only updated_at, managed manually

    protected $fillable = [
        'merchant_id',
        'balance',
        'low_balance_alerted',
    ];

    protected function casts(): array
    {
        return [
            'balance'             => 'integer',
            'low_balance_alerted' => 'boolean',
            'updated_at'          => 'datetime',
        ];
    }
}
