<?php

declare(strict_types=1);

use Emissary\Channels\TelegramAdapter;
use Emissary\Channel;
use Emissary\Channels\ConfigChannelCredentialStore;
use Emissary\Contracts\ChannelCredentialStore;
use Illuminate\Http\Request;
use Orchestra\Testbench\TestCase;

uses(TestCase::class);

uses()->beforeEach(function (): void {
    app()->bind(ChannelCredentialStore::class, ConfigChannelCredentialStore::class);

    config()->set('emissary.channels.telegram', [
        'secret_token' => 'tg-secret-token',
        'bot_token' => '123456:abc-bot-token',
    ]);
});

test('parse extracts text and chat ID from Telegram payload', function (): void {
    $adapter = app()->make(TelegramAdapter::class);

    $payload = [
        'message' => [
            'from' => ['id' => 12345, 'first_name' => 'Test'],
            'chat' => ['id' => 12345],
            'text' => 'Hello from Telegram',
        ],
    ];

    $request = Request::create('/', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], json_encode($payload));

    $message = $adapter->parse($request);

    expect($message->channel)->toBe(Channel::Telegram);
    expect($message->conversationRef)->toBe('12345');
    expect($message->text)->toBe('Hello from Telegram');
});

test('verify returns true for valid secret header', function (): void {
    $adapter = app()->make(TelegramAdapter::class);

    $request = Request::create('/', 'POST', [], [], [], [
        'HTTP_X-Telegram-Bot-Api-Secret-Token' => 'tg-secret-token',
    ]);

    expect($adapter->verify($request))->toBeTrue();
});

test('verify returns false for invalid secret header', function (): void {
    $adapter = app()->make(TelegramAdapter::class);

    $request = Request::create('/', 'POST', [], [], [], [
        'HTTP_X-Telegram-Bot-Api-Secret-Token' => 'wrong-token',
    ]);

    expect($adapter->verify($request))->toBeFalse();
});

test('verify returns false when header is missing', function (): void {
    $adapter = app()->make(TelegramAdapter::class);

    expect($adapter->verify(new Request()))->toBeFalse();
});
