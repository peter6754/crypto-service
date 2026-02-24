<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CryptoTransaction extends Model
{
    const TYPE_DEPOSIT = 'deposit';

    const TYPE_WITHDRAW = 'withdraw';

    const TYPE_PAYMENT = 'payment';

    const TYPE_COMMISSION = 'commission';

    const TYPE_REFUND = 'refund';

    const STATUS_PENDING = 'pending';

    const STATUS_COMPLETED = 'completed';

    const STATUS_FAILED = 'failed';

    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'wallet_id',
        'type',
        'currency',
        'amount',
        'balance_before',
        'balance_after',
        'tx_hash',
        'status',
        'external_id',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'balance_before' => 'decimal:8',
        'balance_after' => 'decimal:8',
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(CryptoWallet::class);
    }

    public function canBeCompleted(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
