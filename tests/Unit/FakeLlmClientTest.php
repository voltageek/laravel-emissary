<?php

declare(strict_types=1);

use Emissary\IntentResult;
use Emissary\Testing\FakeLlmClient;
use Emissary\Testing\ToolCall;

test('make creates a new FakeLlmClient', function (): void {
    $client = FakeLlmClient::make();

    expect($client)->toBeInstanceOf(FakeLlmClient::class);
});

test('onIntent queues intent classification response', function (): void {
    $intent = new IntentResult(slug: 'place_order', confidence: 0.92);
    $client = FakeLlmClient::make()
        ->onIntent($intent);

    $result = $client->classify('Order 3 widgets');

    expect($result->slug)->toBe('place_order');
    expect($result->confidence)->toBe(0.92);
});

test('onAgent queues tool call response', function (): void {
    $client = FakeLlmClient::make()
        ->onIntent(new IntentResult('place_order', 0.92))
        ->onAgent(ToolCall::make('placeOrder', ['product_id' => 42, 'quantity' => 3]));

    $client->classify('Order 3 widgets');

    $result = $client->chat('test-model', []);

    expect($result['choices'][0]['message']['content'])->toBeNull();
    expect($result['choices'][0]['message']['tool_calls'][0]['function']['name'])
        ->toBe('placeOrder');
});

test('onAgent queues text response', function (): void {
    $client = FakeLlmClient::make()
        ->onIntent(new IntentResult('greeting', 0.95))
        ->onAgent('Hello! How can I help?');

    $client->classify('Hi');
    $result = $client->chat('test-model', []);

    expect($result['choices'][0]['message']['content'])->toBe('Hello! How can I help?');
});

test('thenText is alias for onAgent with string', function (): void {
    $client = FakeLlmClient::make()
        ->onIntent(new IntentResult('greeting', 0.95))
        ->thenText('Done.');

    $client->classify('Hi');
    $result = $client->chat('test-model', []);

    expect($result['choices'][0]['message']['content'])->toBe('Done.');
});

test('thenToolCall is alias for onAgent with ToolCall', function (): void {
    $client = FakeLlmClient::make()
        ->onIntent(new IntentResult('lookup', 0.88))
        ->thenToolCall(ToolCall::make('search', ['query' => 'widgets']));

    $client->classify('Search widgets');

    $result = $client->chat('test-model', []);

    expect($result['choices'][0]['message']['tool_calls'][0]['function']['name'])
        ->toBe('search');
});

test('calls records all invocations', function (): void {
    $client = FakeLlmClient::make()
        ->onIntent(new IntentResult('greeting', 0.95))
        ->onAgent('Reply');

    $client->classify('Hi');
    $client->chat('model', []);

    expect($client->calls())->toHaveCount(2);
});

test('assertCalled passes when count matches', function (): void {
    $client = FakeLlmClient::make()
        ->onIntent(new IntentResult('greeting', 0.95))
        ->onAgent('Reply');

    $client->classify('Hi');
    $client->chat('model', []);

    $client->assertCalled(2);

    expect(true)->toBeTrue();
});

test('assertCalled throws when count mismatches', function (): void {
    $client = FakeLlmClient::make()
        ->onIntent(new IntentResult('greeting', 0.95));

    $client->classify('Hi');

    $client->assertCalled(5);
})->throws(\RuntimeException::class, 'Expected 5 LLM calls, got 1.');

test('empty script throws when no intent response', function (): void {
    $client = FakeLlmClient::make();

    $client->classify('Hello');
})->throws(\RuntimeException::class, 'no intent response scripted');

test('empty script throws when no agent response', function (): void {
    $client = FakeLlmClient::make()
        ->onIntent(new IntentResult('greeting', 0.95));

    $client->classify('Hi');
    $client->chat('model', []);
})->throws(\RuntimeException::class, 'no agent response scripted');
