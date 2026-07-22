<?php

namespace App\Models;

use App\Enums\CrmActivityType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmActivity extends Model
{
    /** @use HasFactory<\Database\Factories\CrmActivityFactory> */
    use HasFactory;

    protected $fillable = [
        'contact_id',
        'opportunity_id',
        'task_id',
        'user_id',
        'type',
        'subject',
        'body',
        'happened_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => CrmActivityType::class,
            'happened_at' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
