<?php

declare(strict_types=1);

use Emissary\AgentError;
use Emissary\Contracts\AgentGuard;
use Emissary\GuardResult;
use Emissary\InboundMessage;
use Emissary\Channel;
use Emissary\Pipeline\GuardRegistry;
use Illuminate\Contracts\Auth\Authenticatable;
use Orchestra\Testbench\TestCase;

uses(TestCase::class);

test('checkBeforeIntent returns allow when all guards pass', function (): void {
    $registry = new GuardRegistry();
    $registry->register(new class implements AgentGuard {
        public function getName(): string { return 'test-guard'; }
        public function beforeIntent(InboundMessage $message, ?Authenticatable $user, mixed $tenant): GuardResult {
            return GuardResult::allow();
        }
        public function beforeExecution(string $intent, ?Authenticatable $user, mixed $tenant): GuardResult {
            return GuardResult::allow();
        }
        public function beforeTool(string $toolName, array $arguments, ?Authenticatable $user, mixed $tenant): GuardResult {
            return GuardResult::allow();
        }
    });

    $result = $registry->checkBeforeIntent(
        new InboundMessage('ref', Channel::Web, 'Hi'), null, null,
    );

    expect($result->allowed)->toBeTrue();
});

test('checkBeforeIntent short-circuits on first deny', function (): void {
    $registry = new GuardRegistry();
    $registry->setTurnContext('turn-1', 'conv-1');

    $registry->register(new class implements AgentGuard {
        public function getName(): string { return 'deny-first'; }
        public function beforeIntent(InboundMessage $message, ?Authenticatable $user, mixed $tenant): GuardResult {
            return GuardResult::deny('Blocked by first guard', AgentError::GUARD_DENIED);
        }
        public function beforeExecution(string $intent, ?Authenticatable $user, mixed $tenant): GuardResult {
            return GuardResult::allow();
        }
        public function beforeTool(string $toolName, array $arguments, ?Authenticatable $user, mixed $tenant): GuardResult {
            return GuardResult::allow();
        }
    });

    $secondCalled = false;
    $registry->register(new class($secondCalled) implements AgentGuard {
        public function __construct(private bool &$called) {}
        public function getName(): string { return 'second-guard'; }
        public function beforeIntent(InboundMessage $message, ?Authenticatable $user, mixed $tenant): GuardResult {
            $this->called = true;
            return GuardResult::allow();
        }
        public function beforeExecution(string $intent, ?Authenticatable $user, mixed $tenant): GuardResult {
            return GuardResult::allow();
        }
        public function beforeTool(string $toolName, array $arguments, ?Authenticatable $user, mixed $tenant): GuardResult {
            return GuardResult::allow();
        }
    });

    $result = $registry->checkBeforeIntent(
        new InboundMessage('ref', Channel::Web, 'Hi'), null, null,
    );

    expect($result->allowed)->toBeFalse();
    expect($result->userMessage)->toBe('Blocked by first guard');
    expect($secondCalled)->toBeFalse();
});

test('deny defaults error code to GUARD_DENIED when omitted', function (): void {
    $registry = new GuardRegistry();
    $registry->setTurnContext('turn-1', 'conv-1');

    $registry->register(new class implements AgentGuard {
        public function getName(): string { return 'no-code-guard'; }
        public function beforeIntent(InboundMessage $message, ?Authenticatable $user, mixed $tenant): GuardResult {
            return GuardResult::deny('No access');
        }
        public function beforeExecution(string $intent, ?Authenticatable $user, mixed $tenant): GuardResult {
            return GuardResult::allow();
        }
        public function beforeTool(string $toolName, array $arguments, ?Authenticatable $user, mixed $tenant): GuardResult {
            return GuardResult::allow();
        }
    });

    $result = $registry->checkBeforeIntent(
        new InboundMessage('ref', Channel::Web, 'Hi'), null, null,
    );

    expect($result->allowed)->toBeFalse();
    expect($result->errorCode)->toBe(AgentError::GUARD_DENIED);
});
