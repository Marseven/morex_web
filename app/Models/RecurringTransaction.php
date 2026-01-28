<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecurringTransaction extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'account_id',
        'category_id',
        'type',
        'amount',
        'beneficiary',
        'description',
        'frequency',
        'day_of_month',
        'start_date',
        'end_date',
        'last_generated_date',
        'next_due_date',
        'remaining_occurrences',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'day_of_month' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'last_generated_date' => 'date',
            'next_due_date' => 'date',
            'remaining_occurrences' => 'integer',
            'is_active' => 'boolean',
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount, 0, ',', ' ') . ' FCFA';
    }

    public function getFrequencyLabelAttribute(): string
    {
        return match ($this->frequency) {
            'daily' => 'Quotidien',
            'weekly' => 'Hebdomadaire',
            'biweekly' => 'Bi-hebdomadaire',
            'monthly' => 'Mensuel',
            'quarterly' => 'Trimestriel',
            'yearly' => 'Annuel',
            default => $this->frequency,
        };
    }

    public function getIsDueAttribute(): bool
    {
        return $this->is_active && $this->next_due_date <= now()->toDateString();
    }

    public function getDaysUntilDueAttribute(): ?int
    {
        if (!$this->is_active) {
            return null;
        }

        return now()->startOfDay()->diffInDays($this->next_due_date, false);
    }

    /**
     * Generate a transaction from this recurring transaction
     */
    public function generateTransaction(): Transaction
    {
        return Transaction::create([
            'user_id' => $this->user_id,
            'account_id' => $this->account_id,
            'category_id' => $this->category_id,
            'type' => $this->type,
            'amount' => $this->amount,
            'beneficiary' => $this->beneficiary,
            'description' => $this->description ?? "Transaction récurrente: {$this->frequency_label}",
            'date' => $this->next_due_date,
        ]);
    }

    /**
     * Calculate and update the next due date
     */
    public function updateNextDueDate(): void
    {
        $this->last_generated_date = $this->next_due_date;

        $nextDate = match ($this->frequency) {
            'daily' => $this->next_due_date->addDay(),
            'weekly' => $this->next_due_date->addWeek(),
            'biweekly' => $this->next_due_date->addWeeks(2),
            'monthly' => $this->calculateNextMonthlyDate(),
            'quarterly' => $this->next_due_date->addMonths(3),
            'yearly' => $this->next_due_date->addYear(),
            default => $this->next_due_date->addMonth(),
        };

        $this->next_due_date = $nextDate;

        // Decrement remaining occurrences if set
        if ($this->remaining_occurrences !== null) {
            $this->remaining_occurrences--;
            if ($this->remaining_occurrences <= 0) {
                $this->is_active = false;
            }
        }

        // Check if end_date is reached
        if ($this->end_date && $this->next_due_date > $this->end_date) {
            $this->is_active = false;
        }

        $this->save();
    }

    /**
     * Calculate the next monthly due date, respecting day_of_month
     */
    private function calculateNextMonthlyDate(): \Carbon\Carbon
    {
        $nextMonth = $this->next_due_date->copy()->addMonth();

        if ($this->day_of_month) {
            $targetDay = min($this->day_of_month, $nextMonth->daysInMonth);
            $nextMonth->setDay($targetDay);
        }

        return $nextMonth;
    }

    /**
     * Scope for active recurring transactions
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for due recurring transactions
     */
    public function scopeDue($query)
    {
        return $query->active()->where('next_due_date', '<=', now()->toDateString());
    }
}
