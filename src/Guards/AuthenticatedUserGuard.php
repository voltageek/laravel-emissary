<?php

declare(strict_types=1);

namespace Emissary\Guards;

use Emissary\AgentError;
use Emissary\Contracts\AgentGuard;
use Emissary\GuardResult;
use Emissary\InboundMessage;
use Illuminate\Contracts\Auth\Authenticatable;

class AuthenticatedUserGuard implements AgentGuard
{
    public function getName(): string
    {
        return 'authenticated-user';
    }

    public function beforeIntent(InboundMessage $message, ?Authenticatable $user, mixed $tenant): GuardResult
    {
        return GuardResult::allow();
    }

    public function beforeExecution(string $intent, ?Authenticatable $user, mixed $tenant): GuardResult
    {
        $requireAuthIntents = config('emissary.require_auth_intents', []);

        if (in_array($intent, $requireAuthIntents, true) && $user === null) {
            return GuardResult::deny(
                'You need to be logged in to do that.',
                AgentError::AUTH_UNAUTHENTICATED,
            );
        }

        return GuardResult::allow();
    }

    public function beforeTool(string $toolName, array $arguments, ?Authenticatable $user, mixed $tenant): GuardResult
    {
        return GuardResult::allow();
    }
}
