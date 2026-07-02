<?php

declare(strict_types=1);

namespace Emissary;

final class AgentError
{
    public const GUARD_DENIED = 'guard.denied';
    public const AUTH_UNAUTHENTICATED = 'auth.unauthenticated';
    public const AUTH_UNAUTHORIZED = 'auth.unauthorized';
    public const INTENT_LOW_CONFIDENCE = 'intent.low_confidence';
    public const INTENT_UNKNOWN = 'intent.unknown';
    public const TOOL_EXECUTION_FAILED = 'tool.execution_failed';
    public const TOOL_INVALID_ARGUMENTS = 'tool.invalid_arguments';
    public const TOOL_MAX_ROUNDS = 'agent.max_rounds';
    public const LLM_TIMEOUT = 'llm.timeout';
    public const LLM_RATE_LIMITED = 'llm.rate_limited';
    public const LLM_ERROR = 'llm.error';
    public const SECURITY_JAILBREAK = 'security.jailbreak';
    public const COST_LIMIT_EXCEEDED = 'cost.limit_exceeded';
    public const ONBOARDING_REQUIRED = 'onboarding.required';
    public const CONVERSATION_MAX_TURNS = 'conversation.max_turns';
    public const CHANNEL_DELIVERY_FAILED = 'channel.delivery_failed';
}
