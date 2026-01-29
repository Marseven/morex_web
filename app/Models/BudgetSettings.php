<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetSettings extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'preferred_start_day',
        'tolerance_start_day',
        'tolerance_end_day',
        'salary_category_id',
        'salary_account_id',
        'auto_detect_salary',
    ];

    protected $casts = [
        'preferred_start_day' => 'integer',
        'tolerance_start_day' => 'integer',
        'tolerance_end_day' => 'integer',
        'auto_detect_salary' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function salaryCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'salary_category_id');
    }

    public function salaryAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'salary_account_id');
    }

    /**
     * Vérifie si une date est dans la fenêtre de tolérance pour le salaire
     */
    public function isInSalaryWindow(\DateTime $date): bool
    {
        $day = (int) $date->format('d');
        return $day >= $this->tolerance_start_day && $day <= $this->tolerance_end_day;
    }

    /**
     * Calcule la date de début théorique du prochain cycle
     */
    public function getNextCycleStartDate(\DateTime $fromDate = null): \DateTime
    {
        $from = $fromDate ?? now();
        $day = $this->preferred_start_day;

        // Si on est après le jour préféré, c'est le mois prochain
        if ((int) $from->format('d') >= $day) {
            $nextMonth = (clone $from)->modify('first day of next month');
            return $nextMonth->setDate(
                (int) $nextMonth->format('Y'),
                (int) $nextMonth->format('m'),
                min($day, (int) $nextMonth->format('t')) // Gérer les mois courts
            );
        }

        // Sinon c'est ce mois-ci
        return $from->setDate(
            (int) $from->format('Y'),
            (int) $from->format('m'),
            min($day, (int) $from->format('t'))
        );
    }
}
