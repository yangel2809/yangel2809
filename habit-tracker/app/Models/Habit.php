<?php

namespace App\Models;

use App\Casts\DateString;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Habit extends Model
{
    use HasFactory;

    public const DAILY = 'daily';
    public const WEEKLY = 'weekly';

    protected $fillable = [
        'name', 'frequency_type', 'weekly_target', 'color', 'start_date', 'sort_order', 'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => DateString::class,
            'archived_at' => 'datetime',
            'weekly_target' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(HabitLog::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function isWeekly(): bool
    {
        return $this->frequency_type === self::WEEKLY;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function startDate(): string
    {
        return $this->start_date;
    }

    public function frequencyLabel(): string
    {
        return $this->isWeekly() ? "{$this->weekly_target}× por semana" : 'Diario';
    }
}
