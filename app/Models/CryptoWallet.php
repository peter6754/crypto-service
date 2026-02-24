<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CryptoWallet extends Model
{
    protected $fillable = [
        'user_id',
        'currency',
        'balance',
        'locked_balance',
    ];

    protected $casts = [
        'balance' => 'decimal:8',
        'locked_balance' => 'decimal:8',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CryptoTransaction::class, 'wallet_id');
    }

    public function getAvailableBalanceAttribute(): float
    {
        return round((float) $this->balance - (float) $this->locked_balance, 8);
    }

    public function hasEnoughBalance(float $amount): bool
    {
        $available = $this->available_balance;
        $epsilon = 1e-8;

        return ($available - $amount) >= -$epsilon;
    }
}
