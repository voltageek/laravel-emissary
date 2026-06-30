<?php

declare(strict_types=1);

namespace Emissary\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'channel',
        'channel_ref',
        'status',
        'onboarding_state',
        'pending_action',
        'summary',
    ];

    protected function casts(): array
    {
        return [
            'pending_action' => 'array',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(AgentEvent::class);
    }

    public function toolInvocations(): HasMany
    {
        return $this->hasMany(ToolInvocation::class);
    }

    public function costLedgers(): HasMany
    {
        return $this->hasMany(CostLedger::class);
    }
}
