<?php

declare(strict_types=1);

namespace Emissary\Guards;

use Emissary\AgentError;
use Emissary\Contracts\AgentGuard;
use Emissary\GuardResult;
use Emissary\InboundMessage;
use Emissary\Models\Conversation;
use Illuminate\Contracts\Auth\Authenticatable;

class RateLimitGuard implements AgentGuard
{
    public function getName(): string
    {
        return 'rate-limit';
    }

    public function beforeIntent(InboundMessage $message, ?Authenticatable $user, mixed $tenant): GuardResult
    {
        $perMinute = config('emissary.rate_limit.per_minute', 10);
        $windowSeconds = 60;

        $recentCount = \Emissary\Models\ConversationMessage::whereHas('conversation', function ($q) use ($message): void {
            $q->where('channel', $message->channel->value)
                ->where('channel_ref', $message->conversationRef);
        })
            ->where('role', 'user')
            ->where('created_at', '>=', now()->subSeconds($windowSeconds))
            ->count();

        if ($recentCount >= $perMinute) {
            return GuardResult::deny(
                'You\'re sending messages too quickly. Please wait a moment.',
                AgentError::GUARD_DENIED,
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
