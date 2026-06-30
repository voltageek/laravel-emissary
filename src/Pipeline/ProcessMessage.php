<?php

declare(strict_types=1);

namespace Emissary\Pipeline;

use Emissary\AgentError;
use Emissary\AgentResponse;
use Emissary\Events\TurnCompleted;
use Emissary\Models\Conversation;
use Emissary\Models\ConversationMessage;

class ProcessMessage
{
    private string $turnId;
    private string $conversationId;

    public function __construct(
        private IntentRouter $intentRouter,
        private ModelSelector $modelSelector,
        private GuardRegistry $guardRegistry,
        private ToolRegistry $toolRegistry,
        private TaskAgent $taskAgent,
        private ConversationMemory $memory,
        private DatabaseConfirmationGate $confirmationGate,
    ) {}

    public function handle(
        Conversation $conversation,
        string $userMessage,
        ?string $mediaUrl = null,
        $user = null,
        $tenant = null,
        string $turnId = '',
    ): AgentResponse {
        $this->turnId = $turnId;
        $this->conversationId = $conversation->id;

        $this->intentRouter->setTurnContext($this->turnId, $this->conversationId);
        $this->guardRegistry->setTurnContext($this->turnId, $this->conversationId);
        $this->confirmationGate->setTurnContext($this->turnId, $this->conversationId);
        $this->taskAgent->setTurnContext($this->turnId, $this->conversationId);

        $response = $this->process($conversation, $userMessage, $mediaUrl, $user, $tenant);

        $this->emitTurnCompleted($response);

        return $response;
    }

    private function process(
        Conversation $conversation,
        string $userMessage,
        ?string $mediaUrl,
        $user,
        $tenant,
    ): AgentResponse {
        // Confirmation fast-path
        $pendingAction = $conversation->pending_action;

        if ($pendingAction !== null) {
            $lower = mb_strtolower(trim($userMessage));

            if (in_array($lower, ['yes', 'confirm', 'ok', 'proceed', 'y'], true)) {
                if ($this->confirmationGate->isExpired($conversation)) {
                    $conversation->update(['pending_action' => null]);

                    event(new \Emissary\Events\ConfirmationGateTransitioned(
                        turnId: $this->turnId,
                        conversationId: $this->conversationId,
                        transition: 'expire',
                        toolName: $pendingAction['tool_name'] ?? null,
                    ));

                    return AgentResponse::fromContent(
                        config('emissary.error_messages.guard.denied', 'This confirmation has expired.'),
                    );
                }

                $action = $this->confirmationGate->execute($conversation);

                $toolName = $action['tool_name'] ?? '';
                $fields = $action['fields'] ?? [];

                $guardResult = $this->guardRegistry->checkBeforeTool(
                    $toolName, $fields, $user, $tenant,
                );

                if (! $guardResult->allowed) {
                    return AgentResponse::fromError(
                        $guardResult->errorCode ?? AgentError::GUARD_DENIED,
                        $guardResult->userMessage ?? 'Denied.',
                    );
                }

                try {
                    $this->toolRegistry->setTurnContext($this->turnId, $this->conversationId);
                    $result = $this->toolRegistry->execute($toolName, $fields);
                } catch (\Throwable $e) {
                    return AgentResponse::fromError(
                        AgentError::TOOL_EXECUTION_FAILED,
                        $e->getMessage(),
                    );
                }

                $content = is_string($result) ? $result : json_encode($result);

                return AgentResponse::fromContent($content);
            }

            if (in_array($lower, ['no', 'cancel', 'n'], true)) {
                $this->confirmationGate->cancel($conversation);

                return AgentResponse::fromContent('Action cancelled.');
            }
        }

        // Intent classification
        try {
            $intentResult = $this->intentRouter->classify($userMessage);
        } catch (\Throwable $e) {
            return AgentResponse::fromError(AgentError::LLM_ERROR, $e->getMessage());
        }

        $slug = $intentResult->slug;
        $confidence = $intentResult->confidence;
        $threshold = config('emissary.intent_confidence_threshold', 0.4);

        if ($confidence < $threshold || $slug === 'unknown') {
            $message = config('emissary.error_messages.intent.low_confidence',
                'I\'m not sure I understood that — could you rephrase?');

            return new AgentResponse(content: $message);
        }

        // Guard checkpoint 2: beforeExecution
        $guardResult = $this->guardRegistry->checkBeforeExecution($slug, $user, $tenant);

        if (! $guardResult->allowed) {
            return AgentResponse::fromError(
                $guardResult->errorCode ?? AgentError::GUARD_DENIED,
                $guardResult->userMessage ?? 'Access denied.',
            );
        }

        $model = $this->modelSelector->select($intentResult, $mediaUrl !== null);
        $toolNames = $this->toolRegistry->resolveToolsForIntent($slug, $tenant);

        return $this->taskAgent->run($conversation, $model, $userMessage, $mediaUrl, $toolNames, $user);
    }

    private function emitTurnCompleted(AgentResponse $response): void
    {
        $outcome = match (true) {
            $response->errorCode !== null => 'error',
            $response->confirmationRequired => 'confirmation_proposed',
            default => 'success',
        };

        event(new TurnCompleted(
            turnId: $this->turnId,
            conversationId: $this->conversationId,
            outcome: $outcome,
            intent: $response->intent,
        ));
    }
}
