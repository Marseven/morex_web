<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'avatar',
        'theme',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class)->orderBy('order_index');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class)->orderBy('order_index');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class)->orderByDesc('date');
    }

    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class);
    }

    public function debts(): HasMany
    {
        return $this->hasMany(Debt::class);
    }

    public function recurringTransactions(): HasMany
    {
        return $this->hasMany(RecurringTransaction::class);
    }

    public function getTotalBalanceAttribute(): int
    {
        return $this->accounts()->sum('balance');
    }
}
