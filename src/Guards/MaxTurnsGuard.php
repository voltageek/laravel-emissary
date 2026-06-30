<?php

declare(strict_types=1);

namespace Emissary\Guards;

use Emissary\AgentError;
use Emissary\Contracts\AgentGuard;
use Emissary\GuardResult;
use Emissary\InboundMessage;
use Emissary\Models\Conversation;
use Illuminate\Contracts\Auth\Authenticatable;

class MaxTurnsGuard implements AgentGuard
{
    public function getName(): string
    {
        return 'max-turns';
    }

    public function beforeIntent(InboundMessage $message, ?Authenticatable $user, mixed $tenant): GuardResult
    {
        $maxTurns = config('emissary.max_conversation_turns', 24);

        $conversation = Conversation::where('channel', $message->channel->value)
            ->where('channel_ref', $message->conversationRef)
            ->first();

        if ($conversation === null) {
            return GuardResult::allow();
        }

        $turnCount = \Emissary\Models\AgentEvent::where('conversation_id', $conversation->id)
            ->where('kind', 'turn')
            ->count();

        if ($turnCount >= $maxTurns) {
            return GuardResult::deny(
                'We\'ve reached the limit for this conversation. Start a new one?',
                AgentError::CONVERSATION_MAX_TURNS,
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
