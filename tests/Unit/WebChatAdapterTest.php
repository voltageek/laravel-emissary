<?php

declare(strict_types=1);

use Emissary\AgentResponse;
use Emissary\Channels\WebChatAdapter;
use Emissary\Channel;
use Emissary\Channels\ConfigChannelCredentialStore;
use Emissary\Contracts\ChannelCredentialStore;
use Illuminate\Http\Request;
use Orchestra\Testbench\TestCase;

uses(TestCase::class);

uses()->beforeEach(function (): void {
    app()->bind(ChannelCredentialStore::class, ConfigChannelCredentialStore::class);
});

test('parse extracts text from web request', function (): void {
    $adapter = app()->make(WebChatAdapter::class);

    $request = new Request(['text' => 'Hello from Web']);

    $message = $adapter->parse($request);

    expect($message->channel)->toBe(Channel::Web);
    expect($message->text)->toBe('Hello from Web');
});

test('formatResponse adds quick replies when confirmation required', function (): void {
    $adapter = app()->make(WebChatAdapter::class);

    $response = new AgentResponse(
        content: 'Confirm order?',
        confirmationRequired: true,
    );

    $outbound = $adapter->formatResponse($response);

    expect($outbound->text)->toBe('Confirm order?');
    expect($outbound->quickReplies)->toBe(['Yes', 'No']);
});

test('formatResponse returns no quick replies for normal response', function (): void {
    $adapter = app()->make(WebChatAdapter::class);

    $response = AgentResponse::fromContent('Done.');
    $outbound = $adapter->formatResponse($response);

    expect($outbound->quickReplies)->toBeNull();
});
