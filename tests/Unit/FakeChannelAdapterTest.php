<?php

declare(strict_types=1);

use Emissary\AgentResponse;
use Emissary\Channel;
use Emissary\OutboundMessage;
use Emissary\Testing\FakeChannelAdapter;
use Illuminate\Http\Request;

test('whatsapp factory creates adapter with WhatsApp channel', function (): void {
    $adapter = FakeChannelAdapter::whatsapp('wa_12345');

    $message = $adapter->parse(new Request(query: ['text' => 'Hello']));

    expect($message->channel)->toBe(Channel::WhatsApp);
    expect($message->conversationRef)->toBe('wa_12345');
    expect($message->text)->toBe('Hello');
});

test('telegram factory creates adapter with Telegram channel', function (): void {
    $adapter = FakeChannelAdapter::telegram('tg_abc');

    $message = $adapter->parse(new Request(query: ['text' => 'Hi']));

    expect($message->channel)->toBe(Channel::Telegram);
    expect($message->conversationRef)->toBe('tg_abc');
});

test('web factory creates adapter with Web channel', function (): void {
    $adapter = FakeChannelAdapter::web();

    $message = $adapter->parse(new Request(query: ['text' => 'Hi']));

    expect($message->channel)->toBe(Channel::Web);
    expect($message->conversationRef)->toBe('web_test_user');
});

test('verify always returns true', function (): void {
    $adapter = FakeChannelAdapter::whatsapp();

    expect($adapter->verify(new Request()))->toBeTrue();
});

test('formatResponse wraps AgentResponse in OutboundMessage', function (): void {
    $adapter = FakeChannelAdapter::whatsapp();
    $response = AgentResponse::fromContent('Order placed.');

    $outbound = $adapter->formatResponse($response);

    expect($outbound)->toBeInstanceOf(OutboundMessage::class);
    expect($outbound->text)->toBe('Order placed.');
});

test('send records outbound messages', function (): void {
    $adapter = FakeChannelAdapter::whatsapp();

    $adapter->send('wa_12345', new OutboundMessage(text: 'Message 1'));
    $adapter->send('wa_12345', new OutboundMessage(text: 'Message 2'));

    expect($adapter->sendCount())->toBe(2);
});

test('assertSent passes when message was sent', function (): void {
    $adapter = FakeChannelAdapter::whatsapp();

    $adapter->send('wa_12345', new OutboundMessage(text: 'Hello there'));

    $adapter->assertSent('Hello there');

    expect(true)->toBeTrue();
});

test('assertSent throws when message was not sent', function (): void {
    $adapter = FakeChannelAdapter::whatsapp();

    $adapter->send('wa_12345', new OutboundMessage(text: 'Stuff'));

    $adapter->assertSent('Missing');
})->throws(\RuntimeException::class, 'Expected message [Missing] was not sent.');

test('lastOutbound returns most recent message', function (): void {
    $adapter = FakeChannelAdapter::whatsapp();

    $adapter->send('wa_12345', new OutboundMessage(text: 'First'));
    $adapter->send('wa_12345', new OutboundMessage(text: 'Last'));

    expect($adapter->lastOutbound()->text)->toBe('Last');
});

test('lastOutbound returns null when no messages sent', function (): void {
    $adapter = FakeChannelAdapter::whatsapp();

    expect($adapter->lastOutbound())->toBeNull();
});

test('outboundMessages returns all sent messages', function (): void {
    $adapter = FakeChannelAdapter::whatsapp();

    $adapter->send('wa_12345', new OutboundMessage(text: 'One'));
    $adapter->send('wa_12345', new OutboundMessage(text: 'Two'));

    expect($adapter->outboundMessages())->toHaveCount(2);
});
