<?php

declare(strict_types=1);

namespace Emissary\Listeners;

use Emissary\Events\AgentCallCompleted;
use Emissary\Models\LlmPayload;

class CaptureLlmPayload
{
    private array $buffer = [];

    public function handle(AgentCallCompleted $event): void
    {
        if (! config('emissary.observability.capture_llm_payloads', false)) {
            return;
        }

        $key = $event->turnId . ':' . $event->model . ':' . $event->callType;

        if (isset($this->buffer[$key])) {
            $data = $this->buffer[$key];

            LlmPayload::create([
                'agent_event_id' => '',
                'turn_id' => $event->turnId,
                'request_messages' => $data['messages'] ?? [],
                'tools_sent' => $data['tools'] ?? null,
                'response' => $data['response'] ?? [],
                'created_at' => now(),
            ]);

            unset($this->buffer[$key]);
        }
    }

    public function capture(string $turnId, string $model, array $messages, array $tools, array $response): void
    {
        $key = $turnId . ':' . $model . ':agent';

        $this->buffer[$key] = [
            'messages' => $messages,
            'tools' => $tools,
            'response' => $response,
        ];
    }
}
