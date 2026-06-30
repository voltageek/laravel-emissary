<?php

declare(strict_types=1);

use Emissary\Channel;
use Emissary\InboundMessage;
use Emissary\NullTenancyResolver;

test('resolve always returns null regardless of message', function (): void {
    $resolver = new NullTenancyResolver();

    $result = $resolver->resolve(new InboundMessage(
        conversationRef: 'wa_123',
        channel: Channel::WhatsApp,
        text: 'Hello',
    ));

    expect($result)->toBeNull();
});

test('resolve returns null for web channel too', function (): void {
    $resolver = new NullTenancyResolver();

    $result = $resolver->resolve(new InboundMessage(
        conversationRef: 'web_1',
        channel: Channel::Web,
        text: 'Hi',
    ));

    expect($result)->toBeNull();
});

test('activate is a no-op', function (): void {
    $resolver = new NullTenancyResolver();

    $resolver->activate('some-tenant');

    expect(true)->toBeTrue();
});
