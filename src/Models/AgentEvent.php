<?php

declare(strict_types=1);

namespace Emissary\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AgentEvent extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'turn_id',
        'conversation_id',
        'tenant_id',
        'kind',
        'model',
        'input_tokens',
        'output_tokens',
        'latency_ms',
        'intent',
        'checkpoint',
        'guard',
        'tool_name',
        'result',
        'error_code',
        'error',
        'payload',
        'conversation_message_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'latency_ms' => 'integer',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function toolInvocations(): HasMany
    {
        return $this->hasMany(ToolInvocation::class, 'agent_event_id');
    }

    public function llmPayload(): HasOne
    {
        return $this->hasOne(LlmPayload::class, 'agent_event_id');
    }
}
