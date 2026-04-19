<?php

namespace App\Modules\Partnership\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PartnershipAgreement extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'partnership_id', 'merchant_id', 'version',
        'file_path', 'accepted_by', 'accepted_at', 'ip_address',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (PartnershipAgreement $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function partnership(): BelongsTo
    {
        return $this->belongsTo(Partnership::class);
    }

    public function isAccepted(): bool
    {
        return !is_null($this->accepted_at);
    }
}
