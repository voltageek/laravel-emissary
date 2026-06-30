<?php

declare(strict_types=1);

namespace Emissary\Testing;

use Emissary\IntentResult;
use RuntimeException;

class FakeLlmClient
{
    private array $script = [];
    private array $calls = [];
    private int $position = 0;

    private function __construct() {}

    public static function make(): self
    {
        return new self();
    }

    public function onIntent(IntentResult $result): self
    {
        $this->script[] = ['type' => 'intent', 'result' => $result];

        return $this;
    }

    public function onAgent(ToolCall|string $response): self
    {
        if ($response instanceof ToolCall) {
            $this->script[] = ['type' => 'tool_call', 'response' => $response];
        } else {
            $this->script[] = ['type' => 'text', 'response' => $response];
        }

        return $this;
    }

    public function thenText(string $text): self
    {
        return $this->onAgent($text);
    }

    public function thenToolCall(ToolCall $toolCall): self
    {
        return $this->onAgent($toolCall);
    }

    public function classify(string $userMessage): IntentResult
    {
        $entry = $this->script[$this->position] ?? null;
        $this->position++;

        if ($entry && $entry['type'] === 'intent') {
            $this->calls[] = ['call' => 'classify', 'message' => $userMessage, 'result' => $entry['result']];

            return $entry['result'];
        }

        throw new RuntimeException('FakeLlmClient: no intent response scripted.');
    }

    public function chat(string $model, array $messages, array $tools = []): array
    {
        $entry = $this->script[$this->position] ?? null;
        $this->position++;

        if (! $entry) {
            throw new RuntimeException('FakeLlmClient: no agent response scripted.');
        }

        $this->calls[] = [
            'call' => 'chat',
            'model' => $model,
            'messages' => $messages,
            'tools' => $tools,
            'response' => $entry['response'],
        ];

        if ($entry['type'] === 'tool_call') {
            $tc = $entry['response'];

            return [
                'choices' => [
                    [
                        'message' => [
                            'content' => null,
                            'tool_calls' => [
                                [
                                    'id' => 'call_fake_001',
                                    'function' => [
                                        'name' => $tc->name,
                                        'arguments' => json_encode($tc->arguments),
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ];
        }

        return [
            'choices' => [
                [
                    'message' => [
                        'content' => $entry['response'],
                    ],
                ],
            ],
        ];
    }

    public function calls(): array
    {
        return $this->calls;
    }

    public function assertCalled(int $expected): void
    {
        $actual = count($this->calls);

        if ($actual !== $expected) {
            throw new RuntimeException("Expected {$expected} LLM calls, got {$actual}.");
        }
    }
}
