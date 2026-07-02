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
use GuzzleHttp\Client;
use Illuminate\Http\Request;

class MetaWhatsAppAdapter implements ChannelAdapter
{
    public function __construct(
        private ChannelCredentialStore $credentialStore,
    ) {}

    public function parse(Request $request): InboundMessage
    {
        $body = $request->all();

        $entry = $body['entry'][0] ?? [];
        $change = $entry['changes'][0] ?? [];
        $value = $change['value'] ?? [];
        $messages = $value['messages'] ?? [];
        $message = $messages[0] ?? [];

        $text = $message['text']['body'] ?? '';
        $from = $value['messages'][0]['from'] ?? $message['from'] ?? 'unknown';

        $mediaUrl = null;
        $media = $message['image'] ?? $message['audio'] ?? $message['document'] ?? null;

        if ($media !== null && isset($media['id'])) {
            $mediaUrl = $media['id'];
        }

        return new InboundMessage(
            conversationRef: (string) $from,
            channel: Channel::WhatsApp,
            text: $text,
            mediaUrl: $mediaUrl,
        );
    }

    public function verify(Request $request): bool
    {
        $signature = $request->header('X-Hub-Signature-256', '');

        if ($signature === '') {
            return false;
        }

        $creds = $this->credentialStore->resolve(Channel::WhatsApp);

        if ($creds === null || $creds->verifySecret === '') {
            return false;
        }

        $payload = $request->getContent();
        $expected = 'sha256=' . hash_hmac('sha256', $payload, $creds->verifySecret);

        return hash_equals($expected, $signature);
    }

    public function handshake(Request $request): ?string
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode !== 'subscribe' || $challenge === null) {
            return null;
        }

        $creds = $this->credentialStore->resolve(Channel::WhatsApp);

        if ($creds === null || $creds->handshakeToken !== $token) {
            return null;
        }

        return $challenge;
    }

    public function formatResponse(AgentResponse $response): OutboundMessage
    {
        return new OutboundMessage(text: $response->content);
    }

    public function send(string $channelRef, OutboundMessage $message): void
    {
        $creds = $this->credentialStore->resolve(Channel::WhatsApp);

        if ($creds === null || $creds->accessToken === null) {
            return;
        }

        $client = new Client();

        try {
            $client->post("https://graph.facebook.com/v18.0/{$creds->senderId}/messages", [
                'headers' => [
                    'Authorization' => "Bearer {$creds->accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'messaging_product' => 'whatsapp',
                    'to' => $channelRef,
                    'type' => 'text',
                    'text' => ['body' => $message->text],
                ],
            ]);
        } catch (\Throwable) {
        }
    }
}
