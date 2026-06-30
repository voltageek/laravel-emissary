<?php

declare(strict_types=1);

use Emissary\IntentResult;
use Emissary\Pipeline\ModelSelector;
use Orchestra\Testbench\TestCase;

uses(TestCase::class);

test('selects vision model when media present', function (): void {
    config()->set('emissary.vision_model', 'test-vision-model');

    $selector = new ModelSelector();
    $result = $selector->select(new IntentResult('describe_image', 0.9), hasMedia: true);

    expect($result)->toBe('test-vision-model');
});

test('selects complex model for complex intents', function (): void {
    config()->set('emissary.complex_intents', ['query_financials']);
    config()->set('emissary.complex_model', 'test-complex-model');

    $selector = new ModelSelector();
    $result = $selector->select(new IntentResult('query_financials', 0.95));

    expect($result)->toBe('test-complex-model');
});

test('selects complex model when confidence below escalation threshold', function (): void {
    config()->set('emissary.confidence_escalation_threshold', 0.5);
    config()->set('emissary.complex_model', 'test-complex-model');

    $selector = new ModelSelector();
    $result = $selector->select(new IntentResult('place_order', 0.3));

    expect($result)->toBe('test-complex-model');
});

test('selects default model for standard confident intent', function (): void {
    config()->set('emissary.complex_intents', []);
    config()->set('emissary.confidence_escalation_threshold', 0.5);
    config()->set('emissary.default_model', 'test-default-model');

    $selector = new ModelSelector();
    $result = $selector->select(new IntentResult('greeting', 0.92));

    expect($result)->toBe('test-default-model');
});
