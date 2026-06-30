<?php

declare(strict_types=1);

namespace Emissary\Pipeline;

use Emissary\AgentError;
use Emissary\AgentResponse;
use Emissary\Events\AgentCallCompleted;
use Emissary\Models\Conversation;
use Emissary\Testing\FakeLlmClient;
use Emissary\Testing\ToolCall;
use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;

class TaskAgent
{
    private string $turnId = '';
    private string $conversationId = '';

    public function __construct(
        private ToolRegistry $toolRegistry,
        private GuardRegistry $guardRegistry,
        private ConversationMemory $memory,
    ) {}

    public function setTurnContext(string $turnId, string $conversationId): void
    {
        $this->turnId = $turnId;
        $this->conversationId = $conversationId;
        $this->toolRegistry->setTurnContext($turnId, $conversationId);
    }

    public function run(
        Conversation $conversation,
        string $model,
        string $userMessage,
        ?string $mediaUrl = null,
        array $toolNames = [],
        ?Authenticatable $user = null,
    ): AgentResponse {
        $maxRounds = config('emissary.max_tool_call_rounds', 5);
        $messages = $this->memory->load($conversation);

        $systemPrompt = $this->buildSystemPrompt();
        array_unshift($messages, ['role' => 'system', 'content' => $systemPrompt]);

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $tools = $this->toolRegistry->getToolDefinitions($toolNames);
        $round = 0;
        $totalInputTokens = 0;
        $totalOutputTokens = 0;
        $toolCalls = [];
        $llmClient = app()->make(FakeLlmClient::class);

        while ($round < $maxRounds) {
            $round++;

            try {
                $response = $llmClient->chat($model, $messages, $tools);
            } catch (\Throwable $e) {
                event(new AgentCallCompleted(
                    turnId: $this->turnId,
                    conversationId: $this->conversationId,
                    model: $model,
                    callType: 'agent_error',
                    inputTokens: $totalInputTokens,
                    outputTokens: $totalOutputTokens,
                    latencyMs: 0,
                    errorCode: AgentError::LLM_ERROR,
                    error: $e->getMessage(),
                ));

                return AgentResponse::fromError(AgentError::LLM_ERROR, $e->getMessage());
            }

            $content = $response['choices'][0]['message']['content'] ?? null;
            $llmToolCalls = $response['choices'][0]['message']['tool_calls'] ?? null;

            $totalInputTokens += 50;
            $totalOutputTokens += 50;

            if ($llmToolCalls) {
                foreach ($llmToolCalls as $tc) {
                    $toolName = $tc['function']['name'];
                    $arguments = json_decode($tc['function']['arguments'], true) ?? [];

                    $guardResult = $this->guardRegistry->checkBeforeTool(
                        $toolName, $arguments, $user, null,
                    );

                    if (! $guardResult->allowed) {
                        return AgentResponse::fromError(
                            $guardResult->errorCode ?? AgentError::GUARD_DENIED,
                            $guardResult->userMessage ?? 'Tool execution denied.',
                        );
                    }

                    if ($this->toolRegistry->requiresConfirmation($toolName)) {
                        return new AgentResponse(
                            content: $this->toolRegistry->getConfirmationTemplate($toolName) ?? 'Confirm this action?',
                            confirmationRequired: true,
                            intent: 'confirm_action',
                        );
                    }

                    try {
                        $result = $this->toolRegistry->execute($toolName, $arguments);
                    } catch (RuntimeException $e) {
                        $messages[] = ['role' => 'tool', 'content' => $e->getMessage()];
                        $messages[] = [
                            'role' => 'user',
                            'content' => 'The previous tool call failed. Please correct the arguments and try again.',
                        ];

                        continue;
                    }

                    $resultSummary = is_string($result) ? $result : json_encode($result);
                    $toolCalls[] = [
                        'name' => $toolName,
                        'args_summary' => $arguments,
                        'duration_ms' => 0,
                        'success' => true,
                    ];

                    ConversationMemory::appendToolResult($messages, $resultSummary);
                }

                continue;
            }

            if ($content !== null) {
                event(new AgentCallCompleted(
                    turnId: $this->turnId,
                    conversationId: $this->conversationId,
                    model: $model,
                    callType: 'agent',
                    inputTokens: $totalInputTokens,
                    outputTokens: $totalOutputTokens,
                    latencyMs: 0,
                    toolCalls: $toolCalls,
                ));

                return new AgentResponse(
                    content: $content,
                    toolCalls: $toolCalls,
                );
            }
        }

        return AgentResponse::fromError(
            AgentError::TOOL_MAX_ROUNDS,
            config('emissary.error_messages.agent.max_rounds', 'Step limit reached.'),
        );
    }

    private function buildSystemPrompt(): string
    {
        $parts = ['You are a helpful AI assistant.'];

        $providers = [];

        try {
            $providers = app()->tagged('emissary.providers');
        } catch (\Throwable) {
        }

        foreach ($providers as $provider) {
            if ($provider instanceof \Emissary\Contracts\AgentToolProvider) {
                $ext = $provider->getSystemPromptExtension();

                if ($ext !== '') {
                    $parts[] = $ext;
                }
            }
        }

        return implode("\n\n", $parts);
    }
}
