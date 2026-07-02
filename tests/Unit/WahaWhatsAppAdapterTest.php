<?php

declare(strict_types=1);

use Emissary\AgentResponse;
use Emissary\Channel;
use Emissary\Channels\ConfigChannelCredentialStore;
use Emissary\Channels\WahaWhatsAppAdapter;
use Emissary\Contracts\ChannelCredentialStore;
use Illuminate\Http\Request;
use Orchestra\Testbench\TestCase;

uses(TestCase::class);

uses()->beforeEach(function (): void {
    app()->bind(ChannelCredentialStore::class, ConfigChannelCredentialStore::class);

    config()->set('emissary.channels.whatsapp', [
        'backend' => 'waha',
        'waha_api_url' => 'http://localhost:3000',
        'waha_api_key' => 'test-api-key',
        'waha_session' => 'default',
        'waha_hmac_key' => '',
        'waha_version' => 'free',
    ]);
});

test('parse extracts text and sender from WAHA payload', function (): void {
    $adapter = app()->make(WahaWhatsAppAdapter::class);

    $payload = [
        'event' => 'message',
        'session' => 'default',
        'payload' => [
            'from' => '12345678901@c.us',
            'body' => 'Hello from WAHA',
            'fromMe' => false,
            'hasMedia' => false,
        ],
    ];

    $request = Request::create('/', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], json_encode($payload));

    $message = $adapter->parse($request);

    expect($message->channel)->toBe(Channel::WhatsApp);
    expect($message->conversationRef)->toBe('12345678901@c.us');
    expect($message->text)->toBe('Hello from WAHA');
});

test('parse extracts media URL from WAHA payload', function (): void {
    $adapter = app()->make(WahaWhatsAppAdapter::class);

    $payload = [
        'event' => 'message',
        'session' => 'default',
        'payload' => [
            'from' => '12345678901@c.us',
            'body' => 'Check this image',
            'fromMe' => false,
            'hasMedia' => true,
            'media' => [
                'url' => 'http://localhost:3000/api/files/image.jpg',
                'mimetype' => 'image/jpeg',
                'filename' => 'image.jpg',
            ],
        ],
    ];

    $request = Request::create('/', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], json_encode($payload));

    $message = $adapter->parse($request);

    expect($message->mediaUrl)->toBe('http://localhost:3000/api/files/image.jpg');
});

test('parse skips fromMe messages', function (): void {
    $adapter = app()->make(WahaWhatsAppAdapter::class);

    $payload = [
        'event' => 'message',
        'session' => 'default',
        'payload' => [
            'from' => '12345678901@c.us',
            'body' => 'Outbound echo',
            'fromMe' => true,
            'hasMedia' => false,
        ],
    ];

    $request = Request::create('/', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], json_encode($payload));

    $message = $adapter->parse($request);

    expect($message->text)->toBe('');
});

test('verify returns true when HMAC key is not configured', function (): void {
    config()->set('emissary.channels.whatsapp.waha_hmac_key', '');

    $adapter = app()->make(WahaWhatsAppAdapter::class);

    $request = Request::create('/', 'POST', [], [], [], [], 'test-body');

    expect($adapter->verify($request))->toBeTrue();
});

test('verify returns true for valid HMAC-SHA512 signature', function (): void {
    $secret = 'my-secret-key';
    config()->set('emissary.channels.whatsapp.waha_hmac_key', $secret);

    $payload = '{"event":"message","session":"default","engine":"WEBJS"}';
    $expected = hash_hmac('sha512', $payload, $secret);

    $adapter = app()->make(WahaWhatsAppAdapter::class);

    $request = Request::create('/', 'POST', [], [], [], [
        'HTTP_X-Webhook-Hmac' => $expected,
        'HTTP_X-Webhook-Hmac-Algorithm' => 'sha512',
    ], $payload);

    expect($adapter->verify($request))->toBeTrue();
});

test('verify returns false for invalid HMAC signature', function (): void {
    config()->set('emissary.channels.whatsapp.waha_hmac_key', 'my-secret-key');

    $adapter = app()->make(WahaWhatsAppAdapter::class);

    $request = Request::create('/', 'POST', [], [], [], [
        'HTTP_X-Webhook-Hmac' => 'invalidhash',
        'HTTP_X-Webhook-Hmac-Algorithm' => 'sha512',
    ], 'test-body');

    expect($adapter->verify($request))->toBeFalse();
});

test('verify returns false for missing HMAC header when key is configured', function (): void {
    config()->set('emissary.channels.whatsapp.waha_hmac_key', 'my-secret-key');

    $adapter = app()->make(WahaWhatsAppAdapter::class);

    $request = Request::create('/', 'POST', [], [], [], [], 'test-body');

    expect($adapter->verify($request))->toBeFalse();
});

test('formatResponse maps toolCalls to WAHA buttons', function (): void {
    $adapter = app()->make(WahaWhatsAppAdapter::class);

    $response = new AgentResponse(
        content: 'Choose an option:',
        toolCalls: [
            ['name' => 'order', 'args_summary' => 'Option 1', 'duration_ms' => 10, 'success' => true],
            ['name' => 'cancel', 'args_summary' => 'Option 2', 'duration_ms' => 10, 'success' => true],
        ],
    );

    $outbound = $adapter->formatResponse($response);

    expect($outbound->text)->toBe('Choose an option:');
    expect($outbound->channelExtras)->toBeArray();
    expect($outbound->channelExtras['buttons'])->toHaveCount(2);
    expect($outbound->channelExtras['buttons'][0]['buttonId'])->toBe('opt0');
    expect($outbound->channelExtras['buttons'][0]['text'])->toBe('Option 1');
});

test('formatResponse returns plain OutboundMessage when no toolCalls', function (): void {
    $adapter = app()->make(WahaWhatsAppAdapter::class);

    $response = AgentResponse::fromContent('Plain text reply');

    $outbound = $adapter->formatResponse($response);

    expect($outbound->text)->toBe('Plain text reply');
    expect($outbound->channelExtras)->toBeNull();
});

// Send method is tested via integration/FakeChannelAdapter
// The send method makes real HTTP calls which we replace with fakes
