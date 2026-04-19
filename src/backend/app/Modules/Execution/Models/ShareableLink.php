<?php

namespace App\Modules\Execution\Models;

use App\Models\Merchant;
use App\Modules\Partnership\Models\Partnership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * GAP 10 — Link-Based Token Flow
 * A short 8-char code that creates a shareable landing URL for a partnership.
 */
class ShareableLink extends Model
{
    protected $fillable = [
        'code',
        'partnership_id',
        'created_by_merchant_id',
        'click_count',
    ];

    protected $casts = [
        'click_count' => 'integer',
    ];

    public function partnership(): BelongsTo
    {
        return $this->belongsTo(Partnership::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'created_by_merchant_id');
    }
}
