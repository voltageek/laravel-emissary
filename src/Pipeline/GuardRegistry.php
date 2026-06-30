<?php

declare(strict_types=1);

namespace Emissary\Pipeline;

use Emissary\Contracts\AgentGuard;
use Emissary\Events\GuardDecision;
use Emissary\GuardResult;
use Emissary\InboundMessage;
use Illuminate\Contracts\Auth\Authenticatable;

class GuardRegistry
{
    /** @var AgentGuard[] */
    private array $guards = [];
    private string $turnId = '';
    private string $conversationId = '';

    public function setTurnContext(string $turnId, string $conversationId): void
    {
        $this->turnId = $turnId;
        $this->conversationId = $conversationId;
    }

    public function register(AgentGuard $guard): void
    {
        $this->guards[] = $guard;
    }

    public function checkBeforeIntent(InboundMessage $message, ?Authenticatable $user, mixed $tenant): GuardResult
    {
        return $this->evaluate(
            'beforeIntent',
            fn (AgentGuard $guard) => $guard->beforeIntent($message, $user, $tenant),
        );
    }

    public function checkBeforeExecution(string $intent, ?Authenticatable $user, mixed $tenant): GuardResult
    {
        return $this->evaluate(
            'beforeExecution',
            fn (AgentGuard $guard) => $guard->beforeExecution($intent, $user, $tenant),
            intent: $intent,
        );
    }

    public function checkBeforeTool(string $toolName, array $arguments, ?Authenticatable $user, mixed $tenant): GuardResult
    {
        return $this->evaluate(
            'beforeTool',
            fn (AgentGuard $guard) => $guard->beforeTool($toolName, $arguments, $user, $tenant),
            toolName: $toolName,
        );
    }

    private function evaluate(
        string $checkpoint,
        callable $call,
        ?string $intent = null,
        ?string $toolName = null,
    ): GuardResult {
        foreach ($this->guards as $guard) {
            $result = $call($guard);

            $traceAllows = config('emissary.observability.trace_guard_allows', false);

            if (! $result->allowed || $traceAllows) {
                event(new GuardDecision(
                    turnId: $this->turnId,
                    conversationId: $this->conversationId,
                    checkpoint: $checkpoint,
                    guard: $guard->getName(),
                    allowed: $result->allowed,
                    toolName: $toolName,
                    errorCode: $result->errorCode,
                    userMessage: $result->userMessage,
                ));
            }

            if (! $result->allowed) {
                if ($result->errorCode === null) {
                    return GuardResult::deny(
                        $result->userMessage ?? 'Access denied.',
                        \Emissary\AgentError::GUARD_DENIED,
                    );
                }

                return $result;
            }
        }

        return GuardResult::allow();
    }
}
