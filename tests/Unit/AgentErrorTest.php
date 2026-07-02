<?php

declare(strict_types=1);

use Emissary\AgentError;

test('all 16 AgentError constants are defined with correct values', function (): void {
    expect(AgentError::GUARD_DENIED)->toBe('guard.denied');
    expect(AgentError::AUTH_UNAUTHENTICATED)->toBe('auth.unauthenticated');
    expect(AgentError::AUTH_UNAUTHORIZED)->toBe('auth.unauthorized');
    expect(AgentError::INTENT_LOW_CONFIDENCE)->toBe('intent.low_confidence');
    expect(AgentError::INTENT_UNKNOWN)->toBe('intent.unknown');
    expect(AgentError::TOOL_EXECUTION_FAILED)->toBe('tool.execution_failed');
    expect(AgentError::TOOL_INVALID_ARGUMENTS)->toBe('tool.invalid_arguments');
    expect(AgentError::TOOL_MAX_ROUNDS)->toBe('agent.max_rounds');
    expect(AgentError::LLM_TIMEOUT)->toBe('llm.timeout');
    expect(AgentError::LLM_RATE_LIMITED)->toBe('llm.rate_limited');
    expect(AgentError::LLM_ERROR)->toBe('llm.error');
    expect(AgentError::SECURITY_JAILBREAK)->toBe('security.jailbreak');
    expect(AgentError::COST_LIMIT_EXCEEDED)->toBe('cost.limit_exceeded');
    expect(AgentError::ONBOARDING_REQUIRED)->toBe('onboarding.required');
    expect(AgentError::CONVERSATION_MAX_TURNS)->toBe('conversation.max_turns');
    expect(AgentError::CHANNEL_DELIVERY_FAILED)->toBe('channel.delivery_failed');
});
