<?php

declare(strict_types=1);

namespace Emissary\Testing;

class FixtureReplayer
{
    public static function replay(string $fixturePath): array
    {
        if (! file_exists($fixturePath)) {
            throw new \RuntimeException("Fixture not found: {$fixturePath}");
        }

        $data = json_decode(file_get_contents($fixturePath), true);

        if ($data === null) {
            throw new \RuntimeException("Invalid JSON in fixture: {$fixturePath}");
        }

        $llmClient = FakeLlmClient::make();

        foreach ($data['payloads'] ?? [] as $payload) {
            $requestMessages = $payload['request_messages'] ?? [];
            $response = $payload['response'] ?? [];
            $choices = $response['choices'] ?? [];

            foreach ($choices as $choice) {
                $content = $choice['message']['content'] ?? null;
                $toolCalls = $choice['message']['tool_calls'] ?? null;

                if ($toolCalls !== null) {
                    foreach ($toolCalls as $tc) {
                        $function = $tc['function'] ?? [];
                        $name = $function['name'] ?? 'unknown';
                        $args = json_decode($function['arguments'] ?? '{}', true) ?? [];

                        $llmClient->onAgent(ToolCall::make($name, $args));
                    }
                } elseif ($content !== null) {
                    $llmClient->onAgent($content);
                }
            }
        }

        return $data;
    }
}
