<?php

declare(strict_types=1);

namespace Emissary\Pipeline;

use Emissary\AgentError;
use Emissary\Contracts\AgentToolProvider;
use Emissary\Events\ToolInvocationCompleted;
use RuntimeException;

class ToolRegistry
{
    private array $tools = [];
    private array $baseDefinitions = [];
    private array $baseIntentConfig = [];
    private string $turnId = '';
    private string $conversationId = '';

    public function __construct(
        private ToolScanner $scanner,
    ) {}

    public function setTurnContext(string $turnId, string $conversationId): void
    {
        $this->turnId = $turnId;
        $this->conversationId = $conversationId;
    }

    public function register(string $name, callable $handler): void
    {
        $this->tools[$name] = $handler;
    }

    public function registerProvider(AgentToolProvider $provider): void
    {
        $definitions = $this->scanner->scan($provider);

        foreach ($definitions as $name => $entry) {
            $this->tools[$name] = $entry['handler'];
            $this->baseDefinitions[$name] = $entry['definition'];
        }

        $intentConfig = $provider->getIntentConfig();

        foreach ($intentConfig as $intent => $config) {
            $this->baseIntentConfig[$intent] = $config;
        }
    }

    public function resolveToolsForIntent(string $intent, mixed $tenant): array
    {
        $toolNames = $this->baseIntentConfig[$intent]['tools'] ?? [];
        $tools = [];

        foreach ($toolNames as $name) {
            $tools[] = $name;
        }

        $baseIntents = config('emissary.intents', []);
        $baseToolNames = $baseIntents[$intent]['tools'] ?? [];

        foreach ($baseToolNames as $name) {
            if (! in_array($name, $tools, true)) {
                $tools[] = $name;
            }
        }

        // Auto-register tools whose intents list matches
        foreach ($this->baseDefinitions as $name => $def) {
            $toolIntents = $def['intents'] ?? [];

            if (in_array($intent, $toolIntents, true) && ! in_array($name, $tools, true)) {
                $tools[] = $name;
            }
        }

        return $tools;
    }

    public function execute(string $name, array $arguments): mixed
    {
        $startTime = hrtime(true);

        if (! isset($this->tools[$name])) {
            $event = new ToolInvocationCompleted(
                turnId: $this->turnId,
                conversationId: $this->conversationId,
                toolName: $name,
                arguments: $arguments,
                success: false,
                validationError: "Tool '{$name}' not found",
                triggeredVia: 'agent_loop',
            );
            event($event);

            throw new RuntimeException("Tool '{$name}' not registered.");
        }

        $definition = $this->baseDefinitions[$name] ?? null;

        if ($definition !== null) {
            $error = $this->validateArguments($arguments, $definition);

            if ($error !== null) {
                $event = new ToolInvocationCompleted(
                    turnId: $this->turnId,
                    conversationId: $this->conversationId,
                    toolName: $name,
                    arguments: $arguments,
                    success: false,
                    validationError: $error,
                    triggeredVia: 'agent_loop',
                );
                event($event);

                throw new RuntimeException(
                    "[Tool '{$name}'] " . AgentError::TOOL_INVALID_ARGUMENTS . ': ' . $error,
                );
            }
        }

        try {
            $result = call_user_func($this->tools[$name], ...$arguments);
        } catch (\Throwable $e) {
            $event = new ToolInvocationCompleted(
                turnId: $this->turnId,
                conversationId: $this->conversationId,
                toolName: $name,
                arguments: $arguments,
                success: false,
                durationMs: (int) ((hrtime(true) - $startTime) / 1_000_000),
                triggeredVia: 'agent_loop',
            );
            event($event);

            throw $e;
        }

        $durationMs = (int) ((hrtime(true) - $startTime) / 1_000_000);
        $summary = is_string($result) ? $result : ($result['message'] ?? ($result['result_summary'] ?? null));

        $event = new ToolInvocationCompleted(
            turnId: $this->turnId,
            conversationId: $this->conversationId,
            toolName: $name,
            arguments: $arguments,
            resultSummary: is_string($summary) ? $summary : json_encode($summary),
            durationMs: $durationMs,
            success: true,
            triggeredVia: 'agent_loop',
        );
        event($event);

        return $result;
    }

    private function validateArguments(array $arguments, array $definition): ?string
    {
        $params = $definition['parameters']['properties'] ?? [];
        $required = $definition['parameters']['required'] ?? [];

        foreach ($arguments as $key => $value) {
            if (! isset($params[$key])) {
                return "Unknown parameter: '{$key}'";
            }
        }

        foreach ($required as $key) {
            if (! array_key_exists($key, $arguments)) {
                return "Missing required parameter: '{$key}'";
            }
        }

        foreach ($arguments as $key => $value) {
            $paramDef = $params[$key] ?? null;

            if ($paramDef === null) {
                continue;
            }

            $expectedType = $paramDef['type'] ?? 'string';

            if ($expectedType === 'integer' && ! is_int($value)) {
                return "Parameter '{$key}' expected integer, got " . gettype($value);
            }

            if ($expectedType === 'number' && ! is_float($value) && ! is_int($value)) {
                return "Parameter '{$key}' expected number, got " . gettype($value);
            }

            if ($expectedType === 'boolean' && ! is_bool($value)) {
                return "Parameter '{$key}' expected boolean, got " . gettype($value);
            }

            if (isset($paramDef['enum']) && ! in_array($value, $paramDef['enum'], true)) {
                return "Parameter '{$key}' value '{$value}' not in allowed values: " . implode(', ', $paramDef['enum']);
            }
        }

        return null;
    }

    public function getToolDefinitions(array $toolNames): array
    {
        $defs = [];

        foreach ($toolNames as $name) {
            if (isset($this->baseDefinitions[$name])) {
                $def = $this->baseDefinitions[$name];
                $defs[] = [
                    'type' => 'function',
                    'function' => [
                        'name' => $def['name'],
                        'description' => $def['description'],
                        'parameters' => $def['parameters'],
                    ],
                ];
            }
        }

        return $defs;
    }

    public function getAllDefinitions(): array
    {
        return $this->baseDefinitions;
    }

    public function getMergedIntentConfig(): array
    {
        $base = config('emissary.intents', []);

        return array_merge($base, $this->baseIntentConfig);
    }

    public function requiresConfirmation(string $toolName): bool
    {
        return $this->baseDefinitions[$toolName]['requires_confirmation'] ?? false;
    }

    public function getConfirmationTemplate(string $toolName): ?string
    {
        return $this->baseDefinitions[$toolName]['confirmation_template'] ?? null;
    }
}
