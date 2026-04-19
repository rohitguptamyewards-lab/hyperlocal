<?php

namespace App\Modules\Partnership\Models;

use App\Modules\Partnership\Constants\PartnershipStatus;
use App\Modules\Partnership\Constants\ScopeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class Partnership extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'merchant_id', 'name', 'scope_type', 'offer_structure', 'status',
        'template_id', 'agreement_id', 'start_at', 'end_at',
        'paused_at', 'paused_reason', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'scope_type' => 'integer',
        'status'     => 'integer',
        'start_at'   => 'datetime',
        'end_at'     => 'datetime',
        'paused_at'  => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Partnership $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function participants(): HasMany
    {
        return $this->hasMany(PartnershipParticipant::class);
    }

    public function terms(): HasOne
    {
        return $this->hasOne(PartnershipTerms::class);
    }

    public function rules(): HasOne
    {
        return $this->hasOne(PartnershipRules::class);
    }

    public function agreements(): HasMany
    {
        return $this->hasMany(PartnershipAgreement::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeForMerchant(Builder $query, int $merchantId): Builder
    {
        // Include all partnerships where this merchant is a participant (proposer OR acceptor)
        return $query->whereHas('participants', fn ($q) => $q->where('merchant_id', $merchantId));
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', PartnershipStatus::LIVE);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            PartnershipStatus::LIVE,
            PartnershipStatus::PAUSED,
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function statusLabel(): string
    {
        return PartnershipStatus::label($this->status);
    }

    public function isBrandLevel(): bool
    {
        return $this->scope_type === ScopeType::BRAND;
    }

    public function canTransitionTo(int $newStatus): bool
    {
        return PartnershipStatus::canTransition($this->status, $newStatus);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, PartnershipStatus::EDITABLE, true);
    }
}
