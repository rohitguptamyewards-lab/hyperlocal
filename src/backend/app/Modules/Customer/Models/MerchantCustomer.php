<?php

namespace App\Modules\Customer\Models;

use App\Models\Merchant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantCustomer extends Model
{
    protected $fillable = [
        'merchant_id', 'name', 'phone', 'email', 'source', 'tags',
    ];

    protected $casts = [
        'tags' => 'array',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
