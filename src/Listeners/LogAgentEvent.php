<?php

declare(strict_types=1);

namespace Emissary\Listeners;

use Emissary\Events\AgentCallCompleted;
use Emissary\Events\ConfirmationGateTransitioned;
use Emissary\Events\GuardDecision;
use Emissary\Events\TurnCompleted;
use Emissary\Events\UserOnboardingTransitioned;
use Emissary\Models\AgentEvent;

class LogAgentEvent
{
    public function handle(
        AgentCallCompleted|GuardDecision|ConfirmationGateTransitioned|TurnCompleted|UserOnboardingTransitioned $event,
    ): void {
        $data = [
            'turn_id' => $event->turnId,
            'conversation_id' => $event->conversationId,
            'created_at' => now(),
        ];

        if ($event instanceof AgentCallCompleted) {
            $data['kind'] = 'llm_call';
            $data['model'] = $event->model;
            $data['input_tokens'] = $event->inputTokens;
            $data['output_tokens'] = $event->outputTokens;
            $data['latency_ms'] = $event->latencyMs;
            $data['intent'] = $event->intent;
            $data['error_code'] = $event->errorCode;
            $data['error'] = $event->error;
            $data['payload'] = ['tool_calls' => $event->toolCalls];
            $data['conversation_message_id'] = $event->conversationMessageId;
        } elseif ($event instanceof GuardDecision) {
            $data['kind'] = 'guard';
            $data['checkpoint'] = $event->checkpoint;
            $data['guard'] = $event->guard;
            $data['result'] = $event->allowed ? 'allow' : 'deny';
            $data['error_code'] = $event->errorCode;
            $data['payload'] = ['user_message' => $event->userMessage];
            $data['tool_name'] = $event->toolName;
        } elseif ($event instanceof ConfirmationGateTransitioned) {
            $data['kind'] = 'gate';
            $data['result'] = $event->transition;
            $data['tool_name'] = $event->toolName;
            $data['payload'] = ['fields' => $event->fields];
        } elseif ($event instanceof TurnCompleted) {
            $data['kind'] = 'turn';
            $data['result'] = $event->outcome;
            $data['intent'] = $event->intent;
            $data['latency_ms'] = $event->totalLatencyMs;
            $data['input_tokens'] = $event->totalInputTokens;
            $data['output_tokens'] = $event->totalOutputTokens;
            $data['error_code'] = $event->errorCode;
            $data['payload'] = [
                'models' => $event->models,
                'tool_count' => $event->toolCount,
            ];
        } elseif ($event instanceof UserOnboardingTransitioned) {
            $data['kind'] = 'onboarding';
            $data['result'] = $event->transition;
            $data['payload'] = [
                'user_id' => $event->userId,
                'profile' => $event->profile,
                'consent_version' => $event->consentVersion,
            ];
        }

        AgentEvent::create($data);
    }
}
