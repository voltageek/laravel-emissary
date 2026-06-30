<?php

declare(strict_types=1);

namespace Emissary\Guards;

use Emissary\AgentError;
use Emissary\Contracts\AgentGuard;
use Emissary\GuardResult;
use Emissary\InboundMessage;
use Illuminate\Contracts\Auth\Authenticatable;

class JailbreakDetectionGuard implements AgentGuard
{
    private array $patterns = [
        '/ignore\s+(all\s+)?(previous|prior|above)\s+(instructions|prompts|directives)/i',
        '/you\s+are\s+now\s+(a\s+)?(DAN|jailbroken|unrestricted)/i',
        '/pretend\s+(you\s+are|to\s+be)/i',
        '/\[system\]|\[SYSTEM\]|<<SYSTEM>>|<system>/',
        '/developer\s+mode/i',
        '/override\s+(system|safety|security)/i',
    ];

    public function getName(): string
    {
        return 'jailbreak-detection';
    }

    public function beforeIntent(InboundMessage $message, ?Authenticatable $user, mixed $tenant): GuardResult
    {
        if (! config('emissary.security.jailbreak.enabled', true)) {
            return GuardResult::allow();
        }

        foreach ($this->patterns as $pattern) {
            if (preg_match($pattern, $message->text)) {
                return GuardResult::deny(
                    'I can\'t help with that.',
                    AgentError::SECURITY_JAILBREAK,
                );
            }
        }

        $modelClassifier = config('emissary.security.jailbreak.model');

        if ($modelClassifier !== null) {
            // Future: model-based classifier check
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
