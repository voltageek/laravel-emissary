<?php

declare(strict_types=1);

namespace Emissary\Channels;

use Emissary\AgentResponse;
use Emissary\Channel;
use Emissary\ChannelCredentials;
use Emissary\Contracts\ChannelAdapter;
use Emissary\Contracts\ChannelCredentialStore;
use Emissary\InboundMessage;
use Emissary\OutboundMessage;
use Illuminate\Http\Request;

class WebChatAdapter implements ChannelAdapter
{
    public function __construct(
        private ChannelCredentialStore $credentialStore,
    ) {}

    public function parse(Request $request): InboundMessage
    {
        $text = $request->input('text', $request->input('message', ''));
        $conversationRef = $request->input('conversation_ref', 'web_' . session()->getId());

        return new InboundMessage(
            conversationRef: $conversationRef,
            channel: Channel::Web,
            text: $text,
        );
    }

    public function verify(Request $request): bool
    {
        if (! config('emissary.security.require_webhook_verify', true)) {
            return true;
        }

        return csrf_token() === $request->input('_token', $request->header('X-CSRF-TOKEN', ''));
    }

    public function formatResponse(AgentResponse $response): OutboundMessage
    {
        return new OutboundMessage(
            text: $response->content,
            quickReplies: $response->confirmationRequired ? ['Yes', 'No'] : null,
        );
    }

    public function send(string $channelRef, OutboundMessage $message): void
    {
        // Web responses are returned synchronously via HTTP response.
        // Storage for polling/message history can be added later.
    }
}
