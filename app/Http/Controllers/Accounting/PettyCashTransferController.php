<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\ChartAccount;
use App\Models\Accounting\AccountTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PettyCashTransferController extends Controller
{
    public function create()
    {
        $mainAccount = ChartAccount::firstOrCreate(
            ['code' => '1100'],
            [
                'name' => 'Main Account (Cash)',
                'type' => 'asset',
                'subtype' => 'cash',
                'opening_balance' => 0,
                'current_balance' => 0,
                'is_active' => true,
            ]
        );
        
        $pettyFunds = \App\Models\Accounting\PettyCashFund::with('chartAccount')->where('is_active', true)->get();

        return view('accounting.petty_cash_transfer', compact('mainAccount', 'pettyFunds'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'petty_cash_fund_id' => 'required|exists:petty_cash_funds,id',
            'direction' => 'required|in:to_petty_cash,to_main_account',
            'amount' => 'required|numeric|min:0.01',
            'transfer_date' => 'required|date',
            'description' => 'nullable|string|max:255',
        ]);

        $mainAccount = ChartAccount::where('code', '1100')->first();
        if (!$mainAccount) {
            return back()->withErrors(['error' => 'Main account missing.']);
        }

        $amount = (float) $request->amount;

        DB::transaction(function () use ($request, $mainAccount, $amount) {
            $fund = \App\Models\Accounting\PettyCashFund::lockForUpdate()->findOrFail($request->petty_cash_fund_id);
            $pettyCashAccount = $fund->chartAccount;

            $isToPettyCash = $request->direction === 'to_petty_cash';

            $fromAccount = $isToPettyCash ? $mainAccount : $pettyCashAccount;
            $toAccount = $isToPettyCash ? $pettyCashAccount : $mainAccount;

            // 1. Transaction out of fromAccount
            AccountTransaction::create([
                'account_id' => $fromAccount->id,
                'related_account_id' => $toAccount->id,
                'transaction_date' => $request->transfer_date,
                'type' => 'transfer_out',
                'direction' => 'out',
                'amount' => $amount,
                'payment_method' => 'cash',
                'description' => $request->description ?: 'Transfer to ' . $toAccount->name,
                'source_type' => 'petty_cash_transfer',
                'source_id' => $fund->id,
            ]);

            // 2. Transaction into toAccount
            AccountTransaction::create([
                'account_id' => $toAccount->id,
                'related_account_id' => $fromAccount->id,
                'transaction_date' => $request->transfer_date,
                'type' => 'transfer_in',
                'direction' => 'in',
                'amount' => $amount,
                'payment_method' => 'cash',
                'description' => $request->description ?: 'Transfer from ' . $fromAccount->name,
                'source_type' => 'petty_cash_transfer',
                'source_id' => $fund->id,
            ]);

            // 3. Update balances
            $fromAccount->decrement('current_balance', $amount);
            $toAccount->increment('current_balance', $amount);
            
            // 4. Update Petty Cash Fund balance directly
            if ($isToPettyCash) {
                $fund->increment('current_balance', $amount);
            } else {
                $fund->decrement('current_balance', $amount);
            }
        });

        return redirect()->route('accounting.petty-cash-transfer.create')
            ->with('success', 'Transfer completed successfully.');
    }
}
