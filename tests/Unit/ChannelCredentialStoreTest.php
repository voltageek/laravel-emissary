<?php

declare(strict_types=1);

use Emissary\Channel;
use Emissary\Channels\ConfigChannelCredentialStore;
use Orchestra\Testbench\TestCase;

uses(TestCase::class);

test('resolves WhatsApp credentials from config', function (): void {
    config()->set('emissary.channels.whatsapp', [
        'app_secret' => 'test-secret',
        'access_token' => 'test-token',
        'phone_number_id' => '12345',
        'verify_token' => 'verify-me',
    ]);

    $store = new ConfigChannelCredentialStore();
    $creds = $store->resolve(Channel::WhatsApp);

    expect($creds)->not->toBeNull();
    expect($creds->verifySecret)->toBe('test-secret');
    expect($creds->accessToken)->toBe('test-token');
    expect($creds->senderId)->toBe('12345');
    expect($creds->handshakeToken)->toBe('verify-me');
});

test('resolves Telegram credentials from config', function (): void {
    config()->set('emissary.channels.telegram', [
        'secret_token' => 'tg-secret',
        'bot_token' => 'tg-bot-token',
    ]);

    $store = new ConfigChannelCredentialStore();
    $creds = $store->resolve(Channel::Telegram);

    expect($creds)->not->toBeNull();
    expect($creds->verifySecret)->toBe('tg-secret');
    expect($creds->accessToken)->toBe('tg-bot-token');
});

test('returns null for unconfigured channel', function (): void {
    $store = new ConfigChannelCredentialStore();
    config()->set('emissary.channels.whatsapp', null);

    $creds = $store->resolve(Channel::WhatsApp);

    expect($creds)->toBeNull();
});
