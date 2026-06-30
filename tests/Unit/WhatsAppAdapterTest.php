<?php

declare(strict_types=1);

use Emissary\Channels\WhatsAppAdapter;
use Emissary\Channel;
use Emissary\Channels\ConfigChannelCredentialStore;
use Emissary\Contracts\ChannelCredentialStore;
use Illuminate\Http\Request;
use Orchestra\Testbench\TestCase;

uses(TestCase::class);

uses()->beforeEach(function (): void {
    app()->bind(ChannelCredentialStore::class, ConfigChannelCredentialStore::class);

    config()->set('emissary.channels.whatsapp', [
        'app_secret' => 'test-secret',
        'access_token' => 'test-token',
        'phone_number_id' => '12345',
        'verify_token' => 'verify-me',
    ]);
});

test('parse extracts text and sender from WhatsApp payload', function (): void {
    $adapter = app()->make(WhatsAppAdapter::class);

    $payload = [
        'entry' => [[
            'changes' => [[
                'value' => [
                    'messages' => [[
                        'from' => '15551234567',
                        'text' => ['body' => 'Hello from WhatsApp'],
                    ]],
                ],
            ]],
        ]],
    ];

    $request = Request::create('/', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], json_encode($payload));

    $message = $adapter->parse($request);

    expect($message->channel)->toBe(Channel::WhatsApp);
    expect($message->conversationRef)->toBe('15551234567');
    expect($message->text)->toBe('Hello from WhatsApp');
});

test('verify returns true for valid HMAC signature', function (): void {
    $secret = 'test-secret';
    config()->set('emissary.channels.whatsapp.app_secret', $secret);

    $payload = 'test-body';
    $signature = 'sha256=' . hash_hmac('sha256', $payload, $secret);

    $adapter = app()->make(WhatsAppAdapter::class);

    $request = Request::create('/', 'POST', [], [], [], [
        'HTTP_X-Hub-Signature-256' => $signature,
    ], $payload);

    expect($adapter->verify($request))->toBeTrue();
});

test('verify returns false for invalid HMAC signature', function (): void {
    config()->set('emissary.channels.whatsapp.app_secret', 'test-secret');

    $adapter = app()->make(WhatsAppAdapter::class);

    $request = Request::create('/', 'POST', [], [], [], [
        'HTTP_X-Hub-Signature-256' => 'sha256=invalidhash',
    ], 'test-body');

    expect($adapter->verify($request))->toBeFalse();
});

test('handshake echoes hub.challenge for valid verify token', function (): void {
    config()->set('emissary.channels.whatsapp.verify_token', 'verify-me');

    $adapter = app()->make(WhatsAppAdapter::class);

    $request = Request::create('/whatsapp?hub_mode=subscribe&hub_verify_token=verify-me&hub_challenge=abc123', 'GET');

    $result = $adapter->handshake($request);

    expect($result)->toBe('abc123');
});

test('handshake returns null for wrong verify token', function (): void {
    config()->set('emissary.channels.whatsapp.verify_token', 'verify-me');

    $adapter = app()->make(WhatsAppAdapter::class);

    $request = Request::create('/whatsapp?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=abc', 'GET');

    expect($adapter->handshake($request))->toBeNull();
});
