<?php

declare(strict_types=1);

namespace Emissary\Testing;

use Emissary\AgentResponse;
use Emissary\Channel;
use Emissary\Contracts\ChannelAdapter;
use Emissary\InboundMessage;
use Emissary\OutboundMessage;
use Illuminate\Http\Request;

class FakeChannelAdapter implements ChannelAdapter
{
    private array $outboundMessages = [];
    private ?string $lastError = null;

    public function __construct(
        private Channel $channel,
        private ?string $conversationRef = null,
    ) {}

    public static function whatsapp(?string $conversationRef = null): self
    {
        return new self(channel: Channel::WhatsApp, conversationRef: $conversationRef ?? 'wa_test_user');
    }

    public static function waha(?string $conversationRef = null): self
    {
        return new self(channel: Channel::WhatsApp, conversationRef: $conversationRef ?? '12345678901@c.us');
    }

    public static function telegram(?string $conversationRef = null): self
    {
        return new self(channel: Channel::Telegram, conversationRef: $conversationRef ?? 'tg_test_user');
    }

    public static function web(?string $conversationRef = null): self
    {
        return new self(channel: Channel::Web, conversationRef: $conversationRef ?? 'web_test_user');
    }

    public function parse(Request $request): InboundMessage
    {
        $text = $request->input('text', '');

        return new InboundMessage(
            conversationRef: $this->conversationRef,
            channel: $this->channel,
            text: $text,
        );
    }

    public function verify(Request $request): bool
    {
        return true;
    }

    public function formatResponse(AgentResponse $response): OutboundMessage
    {
        return new OutboundMessage(text: $response->content);
    }

    public function send(string $channelRef, OutboundMessage $message): void
    {
        $this->outboundMessages[] = $message;
    }

    public function assertSent(string $expected): void
    {
        foreach ($this->outboundMessages as $msg) {
            if ($msg->text === $expected) {
                return;
            }
        }

        throw new \RuntimeException("Expected message [{$expected}] was not sent.");
    }

    public function sendCount(): int
    {
        return count($this->outboundMessages);
    }

    public function lastOutbound(): ?OutboundMessage
    {
        return $this->outboundMessages[count($this->outboundMessages) - 1] ?? null;
    }

    public function outboundMessages(): array
    {
        return $this->outboundMessages;
    }
}
