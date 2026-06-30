<?php

declare(strict_types=1);

use Emissary\AgentResponse;

test('fromContent creates response with content', function (): void {
    $response = AgentResponse::fromContent('Order placed.');

    expect($response->content)->toBe('Order placed.');
    expect($response->intent)->toBeNull();
    expect($response->errorCode)->toBeNull();
    expect($response->confirmationRequired)->toBeFalse();
});

test('fromError creates response with error code and message', function (): void {
    $response = AgentResponse::fromError('auth.unauthorized', 'Permission denied.');

    expect($response->content)->toBe('Permission denied.');
    expect($response->errorCode)->toBe('auth.unauthorized');
    expect($response->confirmationRequired)->toBeFalse();
});

test('toOutbound returns OutboundMessage with matching text', function (): void {
    $response = AgentResponse::fromContent('Hello world');
    $outbound = $response->toOutbound();

    expect($outbound->text)->toBe('Hello world');
});
