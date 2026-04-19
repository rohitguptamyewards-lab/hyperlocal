<?php

namespace App\Modules\WhatsAppCredit\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Immutable append-only WhatsApp credit ledger.
 * Every credit movement (allocation, consumption, reversal) is one row.
 *
 * Owner module: WhatsAppCredit
 * Table owned: whatsapp_credit_ledger
 *
 * NEVER call ->update() or ->save() on existing rows.
 * Use WhatsAppCreditService methods for all writes.
 */
class WhatsAppCreditLedger extends Model
{
    protected $table = 'whatsapp_credit_ledger';

    public $timestamps = false; // only created_at

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'merchant_id',
        'entry_type',
        'credits_delta',
        'balance_after',
        'reference_type',
        'reference_id',
        'note',
        'allocated_by',
    ];

    protected function casts(): array
    {
        return [
            'credits_delta' => 'integer',
            'balance_after' => 'integer',
            'created_at'    => 'datetime',
        ];
    }
}
