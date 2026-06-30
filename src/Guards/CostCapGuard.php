<?php

declare(strict_types=1);

namespace Emissary\Guards;

use Emissary\AgentError;
use Emissary\Contracts\AgentGuard;
use Emissary\GuardResult;
use Emissary\InboundMessage;
use Emissary\Models\CostLedger;
use Emissary\Models\Conversation;
use Illuminate\Contracts\Auth\Authenticatable;

class CostCapGuard implements AgentGuard
{
    public function getName(): string
    {
        return 'cost-cap';
    }

    public function beforeIntent(InboundMessage $message, ?Authenticatable $user, mixed $tenant): GuardResult
    {
        $cap = config('emissary.cost_alerts.per_conversation_max_usd', 0.10);

        $conversation = Conversation::where('channel', $message->channel->value)
            ->where('channel_ref', $message->conversationRef)
            ->first();

        if ($conversation === null) {
            return GuardResult::allow();
        }

        $totalCost = CostLedger::where('conversation_id', $conversation->id)
            ->sum('cost_usd');

        if ((float) $totalCost >= $cap) {
            return GuardResult::deny(
                'You\'ve hit the usage limit for this conversation. Please start a new one.',
                AgentError::COST_LIMIT_EXCEEDED,
            );
        }

        return GuardResult::allow();
    }

    public function beforeExecution(string $intent, ?Authenticatable $user, mixed $tenant): GuardResult
    {
        return GuardResult::allow();
    }

    public function beforeTool(string $toolName, array $arguments, ?Authenticatable $user, mixed $tenant): GuardResult
    {
        return GuardResult::allow();
    }
}
