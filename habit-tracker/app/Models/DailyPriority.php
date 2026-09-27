<?php

namespace App\Models;

use App\Casts\DateString;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyPriority extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'date', 'position', 'text', 'completed'];

    protected function casts(): array
    {
        return [
            'date' => DateString::class,
            'position' => 'integer',
            'completed' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
