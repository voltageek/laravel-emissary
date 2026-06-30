<?php

declare(strict_types=1);

namespace Emissary\Pipeline;

use Emissary\AgentError;
use Emissary\Events\AgentCallCompleted;
use Emissary\IntentResult;
use Emissary\Testing\FakeLlmClient;
use RuntimeException;

class IntentRouter
{
    private array $intents = [
        'smalltalk_or_other',
        'confirm_action',
        'cancel_action',
        'start_onboarding',
        'verify_identity',
    ];

    private array $classificationHints = [];
    private string $turnId = '';
    private string $conversationId = '';

    public function setTurnContext(string $turnId, string $conversationId): void
    {
        $this->turnId = $turnId;
        $this->conversationId = $conversationId;
    }

    public function registerIntents(array $intents): void
    {
        $this->intents = array_unique(array_merge($this->intents, $intents));
    }

    public function registerClassificationHints(array $hints): void
    {
        foreach ($hints as $slug => $description) {
            $this->classificationHints[$slug] = $description;
        }
    }

    public function classify(string $userMessage): IntentResult
    {
        try {
            $llmClient = app()->make(FakeLlmClient::class);

            $result = $llmClient->classify($userMessage);
        } catch (\Throwable $e) {
            event(new AgentCallCompleted(
                turnId: $this->turnId,
                conversationId: $this->conversationId,
                model: config('emissary.default_model'),
                callType: 'intent',
                inputTokens: 0,
                outputTokens: 0,
                latencyMs: 0,
                errorCode: AgentError::LLM_ERROR,
                error: $e->getMessage(),
            ));

            throw $e;
        }

        $slug = $result->slug;
        $confidence = $result->confidence;

        if (! in_array($slug, $this->intents, true)) {
            $slug = 'unknown';
            $confidence = 0.0;
        }

        $threshold = config('emissary.intent_confidence_threshold', 0.4);

        if ($confidence < $threshold) {
            $slug = 'unknown';
            $confidence = 0.0;
        }

        event(new AgentCallCompleted(
            turnId: $this->turnId,
            conversationId: $this->conversationId,
            model: config('emissary.default_model'),
            callType: 'intent',
            inputTokens: 0,
            outputTokens: 0,
            latencyMs: 0,
            intent: $slug,
        ));

        return new IntentResult(slug: $slug, confidence: $confidence);
    }
}
