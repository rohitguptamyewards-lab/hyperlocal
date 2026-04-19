<?php

namespace App\Modules\Customer\Models;

use App\Models\Merchant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerUpload extends Model
{
    protected $fillable = [
        'merchant_id', 'file_name', 'total_rows',
        'imported_count', 'failed_count', 'status', 'errors_json',
    ];

    protected $casts = [
        'total_rows'     => 'integer',
        'imported_count' => 'integer',
        'failed_count'   => 'integer',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
