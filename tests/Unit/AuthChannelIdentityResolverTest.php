<?php

declare(strict_types=1);

use Emissary\AuthChannelIdentityResolver;
use Emissary\Channel;
use Emissary\InboundMessage;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\User;
use Orchestra\Testbench\TestCase;

uses(TestCase::class);

test('returns null for WhatsApp channel', function (): void {
    $resolver = new AuthChannelIdentityResolver();

    $result = $resolver->resolveUser(new InboundMessage(
        conversationRef: 'wa_123',
        channel: Channel::WhatsApp,
        text: 'Hello',
    ));

    expect($result)->toBeNull();
});

test('returns null for Telegram channel', function (): void {
    $resolver = new AuthChannelIdentityResolver();

    $result = $resolver->resolveUser(new InboundMessage(
        conversationRef: 'tg_abc',
        channel: Channel::Telegram,
        text: 'Hi',
    ));

    expect($result)->toBeNull();
});

test('returns authenticated user for Web channel when logged in', function (): void {
    $user = new User();
    $user->id = 1;

    auth()->login($user);

    $resolver = new AuthChannelIdentityResolver();

    $result = $resolver->resolveUser(new InboundMessage(
        conversationRef: 'web_1',
        channel: Channel::Web,
        text: 'Hi',
    ));

    expect($result)->not->toBeNull();
    expect($result->getAuthIdentifier())->toBe(1);
});

test('returns null for Web channel when not logged in', function (): void {
    auth()->logout();

    $resolver = new AuthChannelIdentityResolver();

    $result = $resolver->resolveUser(new InboundMessage(
        conversationRef: 'web_1',
        channel: Channel::Web,
        text: 'Hi',
    ));

    expect($result)->toBeNull();
});
