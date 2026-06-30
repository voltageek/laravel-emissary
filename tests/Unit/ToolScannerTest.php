<?php

declare(strict_types=1);

use Emissary\Attributes\Tool;
use Emissary\Contracts\AgentToolProvider;
use Emissary\Pipeline\ToolScanner;

test('scanner maps string parameter to JSON string type', function (): void {
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

    $scanner = new ToolScanner();
    $result = $scanner->scan($provider);

    expect($result)->toHaveKey('testTool');
    expect($result['testTool']['definition']['name'])->toBe('testTool');
    expect($result['testTool']['definition']['parameters']['properties']['name']['type'])->toBe('string');
});

test('scanner maps int parameter to JSON integer type', function (): void {
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

        #[Tool(description: 'Count items')]
        public function countItems(int $count): int { return $count; }
    };

    $scanner = new ToolScanner();
    $result = $scanner->scan($provider);

    expect($result['countItems']['definition']['parameters']['properties']['count']['type'])->toBe('integer');
});

test('scanner marks nullable parameter as not required', function (): void {
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

        #[Tool(description: 'Optional tool')]
        public function optionalTool(string $name, ?string $email = null): string { return $name; }
    };

    $scanner = new ToolScanner();
    $result = $scanner->scan($provider);

    $required = $result['optionalTool']['definition']['parameters']['required'];
    expect($required)->toContain('name');
    expect($required)->not->toContain('email');
});

test('scanner throws on mismatched params key', function (): void {
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

        #[Tool(description: 'Test', params: ['typo_param' => ['description' => 'wrong']])]
        public function testTool(string $name): string { return $name; }
    };

    $scanner = new ToolScanner();
    $scanner->scan($provider);
})->throws(RuntimeException::class, 'params key \'typo_param\' does not match any parameter');
