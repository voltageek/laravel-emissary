<?php

declare(strict_types=1);

namespace Emissary\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LlmPayload extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'agent_event_id',
        'turn_id',
        'request_messages',
        'tools_sent',
        'response',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'request_messages' => 'array',
            'tools_sent' => 'array',
            'response' => 'array',
        ];
    }

    public function agentEvent(): BelongsTo
    {
        return $this->belongsTo(AgentEvent::class, 'agent_event_id');
    }
}
