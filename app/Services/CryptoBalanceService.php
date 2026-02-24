<?php

namespace App\Services;

use App\Models\CryptoTransaction;
use App\Models\CryptoWallet;
use Exception;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Log;

class CryptoBalanceService
{
    public function __construct(
        private readonly DatabaseManager $db
    ) {}

    private function add(float $a, float $b, int $scale = 8): float
    {
        return round($a + $b, $scale);
    }

    private function sub(float $a, float $b, int $scale = 8): float
    {
        return round($a - $b, $scale);
    }

    private function comp(float $a, float $b): int
    {
        $epsilon = 1e-8;
        if (abs($a - $b) < $epsilon) {
            return 0;
        }

        return ($a < $b) ? -1 : 1;
    }

    public function getWallet(int $userId, string $currency = 'BTC'): CryptoWallet
    {
        return CryptoWallet::query()
            ->firstOrCreate(
                ['user_id' => $userId, 'currency' => $currency],
                ['balance' => 0, 'locked_balance' => 0]
            );
    }

    public function deposit(
        int $userId,
        string $currency,
        float $amount,
        ?string $externalId = null,
        ?string $txHash = null,
        ?array $meta = null
    ): CryptoTransaction {
        if ($amount <= 0) {
            throw new Exception('Amount must be greater than zero');
        }

        // Проверка идемпотентности - предотвращение дабл спенд
        if ($externalId) {
            $existing = CryptoTransaction::query()
                ->where('external_id', $externalId)
                ->where('status', '!=', CryptoTransaction::STATUS_FAILED)
                ->first();

            if ($existing) {
                Log::warning('Duplicate deposit attempt', [
                    'external_id' => $externalId,
                    'existing_tx_id' => $existing->id,
                ]);

                return $existing;
            }
        }

        return $this->db->transaction(function () use ($userId, $currency, $amount, $externalId, $txHash, $meta) {
            $wallet = $this->getWallet($userId, $currency);
            $balanceBefore = $wallet->balance;

            $wallet->balance = $this->add((float) $wallet->balance, $amount);
            $wallet->save();

            $transaction = CryptoTransaction::query()->create([
                'user_id' => $userId,
                'wallet_id' => $wallet->id,
                'type' => CryptoTransaction::TYPE_DEPOSIT,
                'currency' => $currency,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $wallet->balance,
                'tx_hash' => $txHash,
                'status' => CryptoTransaction::STATUS_COMPLETED,
                'external_id' => $externalId,
                'meta' => $meta,
            ]);

            Log::info('Deposit completed', [
                'tx_id' => $transaction->id,
                'user_id' => $userId,
                'amount' => $amount,
                'currency' => $currency,
            ]);

            return $transaction;
        });
    }

    public function withdraw(
        int $userId,
        string $currency,
        float $amount,
        string $type = CryptoTransaction::TYPE_WITHDRAW,
        ?string $externalId = null,
        ?array $meta = null
    ): CryptoTransaction {
        if ($amount <= 0) {
            throw new Exception('Amount must be greater than zero');
        }

        // Проверка идемпотентности
        if ($externalId) {
            $existing = CryptoTransaction::query()
                ->where('external_id', $externalId)
                ->where('status', '!=', CryptoTransaction::STATUS_FAILED)
                ->first();

            if ($existing) {
                Log::warning('Duplicate withdraw attempt', [
                    'external_id' => $externalId,
                    'existing_tx_id' => $existing->id,
                ]);

                return $existing;
            }
        }

        return $this->db->transaction(function () use ($userId, $currency, $amount, $type, $externalId, $meta) {
            $wallet = $this->getWallet($userId, $currency);

            // Проверка достаточности средств
            if (! $wallet->hasEnoughBalance($amount)) {
                throw new Exception('Insufficient funds');
            }

            $balanceBefore = $wallet->balance;

            // Обновление баланса
            $wallet->balance = $this->sub((float) $wallet->balance, $amount);
            $wallet->save();

            // Создание записи о транзакции
            $transaction = CryptoTransaction::query()->create([
                'user_id' => $userId,
                'wallet_id' => $wallet->id,
                'type' => $type,
                'currency' => $currency,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $wallet->balance,
                'status' => CryptoTransaction::STATUS_COMPLETED,
                'external_id' => $externalId,
                'meta' => $meta,
            ]);

            Log::info('Withdraw completed', [
                'tx_id' => $transaction->id,
                'user_id' => $userId,
                'amount' => $amount,
                'currency' => $currency,
                'type' => $type,
            ]);

            return $transaction;
        });
    }

    public function chargeCommission(
        int $userId,
        string $currency,
        float $amount,
        ?string $externalId = null,
        ?array $meta = null
    ): CryptoTransaction {
        return $this->withdraw(
            $userId,
            $currency,
            $amount,
            CryptoTransaction::TYPE_COMMISSION,
            $externalId,
            $meta
        );
    }

    public function createPendingWithdraw(
        int $userId,
        string $currency,
        float $amount,
        ?string $externalId = null,
        ?array $meta = null
    ): CryptoTransaction {
        if ($amount <= 0) {
            throw new Exception('Amount must be greater than zero');
        }

        if ($externalId) {
            $existing = CryptoTransaction::query()
                ->where('external_id', $externalId)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        return $this->db->transaction(function () use ($userId, $currency, $amount, $externalId, $meta) {
            $wallet = $this->getWallet($userId, $currency);

            // Проверка достаточности средств
            if (! $wallet->hasEnoughBalance($amount)) {
                throw new Exception('Insufficient funds');
            }

            // Блокировка средств
            $wallet->locked_balance = $this->add((float) $wallet->locked_balance, $amount);
            $wallet->save();

            $transaction = CryptoTransaction::query()->create([
                'user_id' => $userId,
                'wallet_id' => $wallet->id,
                'type' => CryptoTransaction::TYPE_WITHDRAW,
                'currency' => $currency,
                'amount' => $amount,
                'balance_before' => $wallet->balance,
                'balance_after' => $wallet->balance,
                'status' => CryptoTransaction::STATUS_PENDING,
                'external_id' => $externalId,
                'meta' => $meta,
            ]);

            Log::info('Pending withdraw created', [
                'tx_id' => $transaction->id,
                'user_id' => $userId,
                'amount' => $amount,
                'currency' => $currency,
            ]);

            return $transaction;
        });
    }

    public function confirmPendingWithdraw(int $transactionId): CryptoTransaction
    {
        return $this->db->transaction(function () use ($transactionId) {
            $transaction = CryptoTransaction::query()
                ->lockForUpdate()
                ->findOrFail($transactionId);

            if ($transaction->status !== CryptoTransaction::STATUS_PENDING) {
                throw new Exception('Transaction is not pending');
            }

            $wallet = $transaction->wallet;
            $wallet->balance = $this->sub((float) $wallet->balance, $transaction->amount);
            $wallet->locked_balance = $this->sub((float) $wallet->locked_balance, $transaction->amount);
            $wallet->save();

            $transaction->balance_after = $wallet->balance;
            $transaction->status = CryptoTransaction::STATUS_COMPLETED;
            $transaction->save();

            Log::info('Pending withdraw confirmed', [
                'tx_id' => $transaction->id,
                'user_id' => $transaction->user_id,
            ]);

            return $transaction;
        });
    }

    public function cancelPendingWithdraw(int $transactionId): CryptoTransaction
    {
        return $this->db->transaction(function () use ($transactionId) {
            $transaction = CryptoTransaction::query()
                ->lockForUpdate()
                ->findOrFail($transactionId);

            if ($transaction->status !== CryptoTransaction::STATUS_PENDING) {
                throw new Exception('Transaction is not pending');
            }

            $wallet = $transaction->wallet;
            $wallet->locked_balance = $this->sub((float) $wallet->locked_balance, $transaction->amount);
            $wallet->save();

            $transaction->status = CryptoTransaction::STATUS_CANCELLED;
            $transaction->save();

            Log::info('Pending withdraw cancelled', [
                'tx_id' => $transaction->id,
                'user_id' => $transaction->user_id,
            ]);

            return $transaction;
        });
    }

    public function refund(
        int $userId,
        string $currency,
        float $amount,
        ?string $externalId = null,
        ?array $meta = null
    ): CryptoTransaction {
        if ($amount <= 0) {
            throw new Exception('Amount must be greater than zero');
        }

        if ($externalId) {
            $existing = CryptoTransaction::query()
                ->where('external_id', $externalId)
                ->where('status', '!=', CryptoTransaction::STATUS_FAILED)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        return $this->db->transaction(function () use ($userId, $currency, $amount, $externalId, $meta) {
            $wallet = $this->getWallet($userId, $currency);
            $balanceBefore = $wallet->balance;

            $wallet->balance = $this->add((float) $wallet->balance, $amount);
            $wallet->save();

            $transaction = CryptoTransaction::query()->create([
                'user_id' => $userId,
                'wallet_id' => $wallet->id,
                'type' => CryptoTransaction::TYPE_REFUND,
                'currency' => $currency,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $wallet->balance,
                'status' => CryptoTransaction::STATUS_COMPLETED,
                'external_id' => $externalId,
                'meta' => $meta,
            ]);

            Log::info('Refund completed', [
                'tx_id' => $transaction->id,
                'user_id' => $userId,
                'amount' => $amount,
                'currency' => $currency,
            ]);

            return $transaction;
        });
    }
}
