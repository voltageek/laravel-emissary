<?php

declare(strict_types=1);

use Carbon\Carbon;
use Emissary\Channel;
use Emissary\ChannelCredentials;
use Emissary\InboundMessage;
use Emissary\IntentResult;
use Emissary\OutboundMessage;

test('InboundMessage stores constructor args as readonly properties', function (): void {
    $receivedAt = Carbon::parse('2026-06-30 10:00:00');
    $msg = new InboundMessage(
        conversationRef: 'wa_12345',
        channel: Channel::WhatsApp,
        text: 'Hello',
        mediaUrl: 'https://example.com/img.jpg',
        receivedAt: $receivedAt,
    );

    expect($msg->conversationRef)->toBe('wa_12345');
    expect($msg->channel)->toBe(Channel::WhatsApp);
    expect($msg->text)->toBe('Hello');
    expect($msg->mediaUrl)->toBe('https://example.com/img.jpg');
    expect($msg->receivedAt)->toBe($receivedAt);
});

test('InboundMessage defaults mediaUrl to null and receivedAt to now', function (): void {
    $msg = new InboundMessage(
        conversationRef: 'tg_abc',
        channel: Channel::Telegram,
        text: 'Hi',
    );

    expect($msg->mediaUrl)->toBeNull();
    expect($msg->receivedAt)->toBeInstanceOf(Carbon::class);
});

test('OutboundMessage stores constructor args with defaults', function (): void {
    $msg = new OutboundMessage(text: 'Reply');

    expect($msg->text)->toBe('Reply');
    expect($msg->mediaUrl)->toBeNull();
    expect($msg->quickReplies)->toBeNull();
    expect($msg->channelExtras)->toBeNull();
});

test('OutboundMessage with all fields', function (): void {
    $msg = new OutboundMessage(
        text: 'Choose',
        mediaUrl: 'https://example.com/pic.jpg',
        quickReplies: ['Yes', 'No'],
        channelExtras: ['buttons' => []],
    );

    expect($msg->text)->toBe('Choose');
    expect($msg->quickReplies)->toBe(['Yes', 'No']);
    expect($msg->channelExtras)->toBe(['buttons' => []]);
});

test('IntentResult stores slug and confidence', function (): void {
    $result = new IntentResult(slug: 'place_order', confidence: 0.92);

    expect($result->slug)->toBe('place_order');
    expect($result->confidence)->toBe(0.92);
});

test('ChannelCredentials stores all fields', function (): void {
    $creds = new ChannelCredentials(
        verifySecret: 'secret123',
        accessToken: 'token_abc',
        senderId: 'sender_1',
        handshakeToken: 'hs_token',
        extra: ['key' => 'value'],
    );

    expect($creds->verifySecret)->toBe('secret123');
    expect($creds->accessToken)->toBe('token_abc');
    expect($creds->senderId)->toBe('sender_1');
    expect($creds->handshakeToken)->toBe('hs_token');
    expect($creds->extra)->toBe(['key' => 'value']);
});

test('ChannelCredentials defaults optional fields to null', function (): void {
    $creds = new ChannelCredentials(verifySecret: 'secret');

    expect($creds->accessToken)->toBeNull();
    expect($creds->senderId)->toBeNull();
    expect($creds->handshakeToken)->toBeNull();
    expect($creds->extra)->toBeNull();
});

test('Channel enum has three cases', function (): void {
    expect(Channel::cases())->toHaveCount(3);
    expect(Channel::WhatsApp->value)->toBe('whatsapp');
    expect(Channel::Telegram->value)->toBe('telegram');
    expect(Channel::Web->value)->toBe('web');
});
