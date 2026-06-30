<?php

declare(strict_types=1);

namespace Emissary\Channels;

use Emissary\AgentResponse;
use Emissary\Channel;
use Emissary\Contracts\ChannelAdapter;
use Emissary\Contracts\ChannelCredentialStore;
use Emissary\InboundMessage;
use Emissary\OutboundMessage;
use GuzzleHttp\Client;
use Illuminate\Http\Request;

class TelegramAdapter implements ChannelAdapter
{
    public function __construct(
        private ChannelCredentialStore $credentialStore,
    ) {}

    public function parse(Request $request): InboundMessage
    {
        $body = $request->all();
        $message = $body['message'] ?? [];
        $from = $message['from'] ?? [];
        $chat = $message['chat'] ?? [];

        $text = $message['text'] ?? '';

        $mediaUrl = null;
        if (isset($message['photo'])) {
            $photo = end($message['photo']);
            $mediaUrl = $photo['file_id'] ?? null;
        }

        return new InboundMessage(
            conversationRef: (string) ($chat['id'] ?? $from['id'] ?? 'unknown'),
            channel: Channel::Telegram,
            text: $text,
            mediaUrl: $mediaUrl,
        );
    }

    public function verify(Request $request): bool
    {
        $secretHeader = $request->header('X-Telegram-Bot-Api-Secret-Token');

        if ($secretHeader === null || $secretHeader === '') {
            return false;
        }

        $creds = $this->credentialStore->resolve(Channel::Telegram);

        if ($creds === null || $creds->verifySecret === '') {
            return false;
        }

        return hash_equals($creds->verifySecret, $secretHeader);
    }

    public function formatResponse(AgentResponse $response): OutboundMessage
    {
        return new OutboundMessage(text: $response->content);
    }

    public function send(string $channelRef, OutboundMessage $message): void
    {
        $creds = $this->credentialStore->resolve(Channel::Telegram);

        if ($creds === null || $creds->accessToken === null) {
            return;
        }

        $client = new Client();

        try {
            $client->post("https://api.telegram.org/bot{$creds->accessToken}/sendMessage", [
                'json' => [
                    'chat_id' => $channelRef,
                    'text' => $message->text,
                ],
            ]);
        } catch (\Throwable) {
            // fail silently
        }
    }
}
