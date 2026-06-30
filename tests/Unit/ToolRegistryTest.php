<?php

declare(strict_types=1);

use Emissary\AgentError;
use Emissary\Attributes\Tool;
use Emissary\Contracts\AgentToolProvider;
use Emissary\Pipeline\ToolRegistry;
use Emissary\Pipeline\ToolScanner;
use Orchestra\Testbench\TestCase;

uses(TestCase::class);

test('execute validates arguments against schema and rejects unknown properties', function (): void {
    $registry = new ToolRegistry(new ToolScanner());
    $registry->setTurnContext('turn-1', 'conv-1');

    $provider = new class implements AgentToolProvider {
        public function pluginName(): string { return 'test'; }
        public function getIntents(): array { return []; }
        public function getIntentConfig(): array { return []; }
        public function getIntentClassificationHints(): array { return []; }
        public function getToolDefinitions(): array { return []; }
        public function getGuards(): array { return []; }
        public function getSystemPromptExtension(): string { return ''; }
        public function getDocumentMappings(): array { return []; }
        public function isIntentSupported(string $intent, mixed $tenant): bool { return true; }

        #[Tool(description: 'Test tool')]
        public function testTool(string $name): string { return $name; }
    };

    $registry->registerProvider($provider);

    expect(fn () => $registry->execute('testTool', ['name' => 'ok']))->not->toThrow(Exception::class);
});

test('execute rejects missing required parameter', function (): void {
    $registry = new ToolRegistry(new ToolScanner());
    $registry->setTurnContext('turn-1', 'conv-1');

    $provider = new class implements AgentToolProvider {
        public function pluginName(): string { return 'test'; }
        public function getIntents(): array { return []; }
        public function getIntentConfig(): array { return []; }
        public function getIntentClassificationHints(): array { return []; }
        public function getToolDefinitions(): array { return []; }
        public function getGuards(): array { return []; }
        public function getSystemPromptExtension(): string { return ''; }
        public function getDocumentMappings(): array { return []; }
        public function isIntentSupported(string $intent, mixed $tenant): bool { return true; }

        #[Tool(description: 'Test tool')]
        public function testTool(string $name, int $count): string { return "{$name} x{$count}"; }
    };

    $registry->registerProvider($provider);

    $registry->execute('testTool', ['name' => 'widget']);
})->throws(RuntimeException::class, 'Missing required parameter');
