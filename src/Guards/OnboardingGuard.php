<?php

declare(strict_types=1);

namespace Emissary\Guards;

use Emissary\AgentError;
use Emissary\Contracts\AgentGuard;
use Emissary\GuardResult;
use Emissary\InboundMessage;
use Emissary\Models\Conversation;
use Illuminate\Contracts\Auth\Authenticatable;

class OnboardingGuard implements AgentGuard
{
    private ?Conversation $conversation = null;

    public function setConversation(Conversation $conversation): void
    {
        $this->conversation = $conversation;
    }

    public function getName(): string
    {
        return 'onboarding';
    }

    public function beforeIntent(InboundMessage $message, ?Authenticatable $user, mixed $tenant): GuardResult
    {
        return GuardResult::allow();
    }

    public function beforeExecution(string $intent, ?Authenticatable $user, mixed $tenant): GuardResult
    {
        if (! config('emissary.onboarding.enabled', false)) {
            return GuardResult::allow();
        }

        if ($this->conversation === null) {
            return GuardResult::allow();
        }

        $state = $this->conversation->onboarding_state;

        if (in_array($state, ['complete', 'skipped'], true)) {
            return GuardResult::allow();
        }

        $gatedIntents = config('emissary.onboarding.gated_intents', ['*']);

        if (in_array('*', $gatedIntents, true) || in_array($intent, $gatedIntents, true)) {
            return GuardResult::deny(
                'Let\'s get you set up before we do that — just say "start".',
                AgentError::ONBOARDING_REQUIRED,
            );
        }

        return GuardResult::allow();
    }

    public function beforeTool(string $toolName, array $arguments, ?Authenticatable $user, mixed $tenant): GuardResult
    {
        return GuardResult::allow();
    }
}
