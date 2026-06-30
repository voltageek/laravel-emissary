<?php

declare(strict_types=1);

namespace Emissary\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentSpan extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'turn_id',
        'conversation_id',
        'stage',
        'duration_ms',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'duration_ms' => 'integer',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
