<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Debt extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'initial_amount',
        'current_amount',
        'due_date',
        'description',
        'contact_name',
        'contact_phone',
        'status',
        'color',
        'account_id',
    ];

    protected function casts(): array
    {
        return [
            'initial_amount' => 'integer',
            'current_amount' => 'integer',
            'due_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(DebtPayment::class);
    }

    public function getProgressPercentageAttribute(): float
    {
        if ($this->initial_amount === 0) {
            return 100;
        }
        $paid = $this->initial_amount - $this->current_amount;
        return min(100, ($paid / $this->initial_amount) * 100);
    }

    public function getRemainingAmountAttribute(): int
    {
        return max(0, $this->current_amount);
    }

    public function getPaidAmountAttribute(): int
    {
        return $this->initial_amount - $this->current_amount;
    }

    public function getDaysUntilDueAttribute(): ?int
    {
        if (!$this->due_date) {
            return null;
        }
        return now()->diffInDays($this->due_date, false);
    }

    public function getIsOverdueAttribute(): bool
    {
        if (!$this->due_date) {
            return false;
        }
        return $this->due_date->isPast() && $this->status === 'active';
    }

    public function addPayment(int $amount, string $accountId, ?string $date = null): DebtPayment
    {
        $paymentDate = $date ?? now()->toDateString();

        // Create a transaction to reflect the financial impact
        // debt (je dois) -> paying = expense from my account
        // credit (on me doit) -> receiving payment = income to my account
        $transactionType = $this->type === 'debt' ? 'expense' : 'income';
        $beneficiary = $this->contact_name ?? $this->name;
        $description = $this->type === 'debt'
            ? "Remboursement: {$this->name}"
            : "Recu: {$this->name}";

        // Atomicité : la transaction, le paiement et la mise à jour de la dette
        // doivent réussir ou échouer ensemble (évite les incohérences financières).
        return DB::transaction(function () use ($amount, $accountId, $transactionType, $beneficiary, $description, $paymentDate) {
            $transaction = new Transaction([
                'amount' => $amount,
                'type' => $transactionType,
                'account_id' => $accountId,
                'beneficiary' => $beneficiary,
                'description' => $description,
                'date' => $paymentDate,
            ]);
            $transaction->user_id = $this->user_id;
            $transaction->save();

            // Create the debt payment record
            $payment = new DebtPayment([
                'debt_id' => $this->id,
                'account_id' => $accountId,
                'transaction_id' => $transaction->id,
                'amount' => $amount,
                'date' => $paymentDate,
                'description' => $description,
            ]);
            $payment->user_id = $this->user_id;
            $payment->save();

            // Update debt amount
            $this->current_amount = max(0, $this->current_amount - $amount);
            if ($this->current_amount <= 0) {
                $this->status = 'paid';
            }
            $this->save();

            return $payment;
        });
    }
}
