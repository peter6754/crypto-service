<?php

namespace App\Http\Controllers;

use App\Services\CryptoBalanceService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CryptoBalanceController extends Controller
{
    public function __construct(
        private readonly CryptoBalanceService $balanceService
    ) {}

    public function balance(Request $request): JsonResponse
    {
        $request->validate([
            'currency' => 'sometimes|string|size:3',
        ]);

        $currency = $request->get('currency', 'BTC');
        $wallet = $this->balanceService->getWallet(
            Auth::id(),
            strtoupper($currency)
        );

        return response()->json([
            'currency' => $wallet->currency,
            'balance' => $wallet->balance,
            'locked_balance' => $wallet->locked_balance,
            'available_balance' => $wallet->available_balance,
        ]);
    }

    public function deposit(Request $request): JsonResponse
    {
        $request->validate([
            'currency' => 'required|string|size:3',
            'amount' => 'required|numeric|min:0.00000001',
            'external_id' => 'sometimes|string|max:255',
            'tx_hash' => 'sometimes|string|max:255',
        ]);

        try {
            $transaction = $this->balanceService->deposit(
                Auth::id(),
                strtoupper($request->currency),
                $request->amount,
                $request->external_id,
                $request->tx_hash
            );

            return response()->json([
                'success' => true,
                'transaction' => [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'amount' => $transaction->amount,
                    'currency' => $transaction->currency,
                    'status' => $transaction->status,
                    'balance_before' => $transaction->balance_before,
                    'balance_after' => $transaction->balance_after,
                ],
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function withdraw(Request $request): JsonResponse
    {
        $request->validate([
            'currency' => 'required|string|size:3',
            'amount' => 'required|numeric|min:0.00000001',
            'external_id' => 'sometimes|string|max:255',
        ]);

        try {
            $transaction = $this->balanceService->withdraw(
                Auth::id(),
                strtoupper($request->currency),
                $request->amount,
                externalId: $request->external_id
            );

            return response()->json([
                'success' => true,
                'transaction' => [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'amount' => $transaction->amount,
                    'currency' => $transaction->currency,
                    'status' => $transaction->status,
                    'balance_before' => $transaction->balance_before,
                    'balance_after' => $transaction->balance_after,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function createPendingWithdraw(Request $request): JsonResponse
    {
        $request->validate([
            'currency' => 'required|string|size:3',
            'amount' => 'required|numeric|min:0.00000001',
            'external_id' => 'sometimes|string|max:255',
        ]);

        try {
            $transaction = $this->balanceService->createPendingWithdraw(
                Auth::id(),
                strtoupper($request->currency),
                $request->amount,
                $request->external_id
            );

            return response()->json([
                'success' => true,
                'transaction' => [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'amount' => $transaction->amount,
                    'currency' => $transaction->currency,
                    'status' => $transaction->status,
                ],
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function confirmWithdraw(Request $request): JsonResponse
    {
        $request->validate([
            'transaction_id' => 'required|integer',
        ]);

        try {
            $transaction = $this->balanceService->confirmPendingWithdraw(
                $request->transaction_id
            );

            return response()->json([
                'success' => true,
                'transaction' => [
                    'id' => $transaction->id,
                    'status' => $transaction->status,
                    'balance_after' => $transaction->balance_after,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function cancelWithdraw(Request $request): JsonResponse
    {
        $request->validate([
            'transaction_id' => 'required|integer',
        ]);

        try {
            $transaction = $this->balanceService->cancelPendingWithdraw(
                $request->transaction_id
            );

            return response()->json([
                'success' => true,
                'transaction' => [
                    'id' => $transaction->id,
                    'status' => $transaction->status,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function transactions(Request $request): JsonResponse
    {
        $request->validate([
            'currency' => 'sometimes|string|size:3',
            'type' => 'sometimes|string|in:deposit,withdraw,payment,commission,refund',
            'status' => 'sometimes|string|in:pending,completed,failed,cancelled',
            'limit' => 'sometimes|integer|min:1|max:100',
        ]);

        $query = Auth::user()->cryptoTransactions();

        if ($request->has('currency')) {
            $query->where('currency', strtoupper($request->currency));
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $transactions = $query
            ->orderBy('created_at', 'desc')
            ->limit($request->get('limit', 50))
            ->get();

        return response()->json([
            'transactions' => $transactions->map(fn ($t) => [
                'id' => $t->id,
                'type' => $t->type,
                'amount' => $t->amount,
                'currency' => $t->currency,
                'status' => $t->status,
                'tx_hash' => $t->tx_hash,
                'created_at' => $t->created_at,
            ]),
        ]);
    }
}
