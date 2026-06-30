<?php

declare(strict_types=1);

namespace Emissary\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ToolInvocation extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'turn_id',
        'conversation_id',
        'tenant_id',
        'tool_name',
        'arguments',
        'result_summary',
        'duration_ms',
        'success',
        'validation_error',
        'triggered_via',
        'agent_event_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'arguments' => 'array',
            'success' => 'boolean',
            'duration_ms' => 'integer',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function agentEvent(): BelongsTo
    {
        return $this->belongsTo(AgentEvent::class, 'agent_event_id');
    }
}
