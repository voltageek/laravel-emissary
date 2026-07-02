<?php

declare(strict_types=1);

namespace Emissary\Channels;

use Emissary\AgentResponse;
use Emissary\AgentError;
use Emissary\Channel;
use Emissary\ChannelCredentials;
use Emissary\Contracts\ChannelAdapter;
use Emissary\Contracts\ChannelCredentialStore;
use Emissary\InboundMessage;
use Emissary\OutboundMessage;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WahaWhatsAppAdapter implements ChannelAdapter
{
    public function __construct(
        private ChannelCredentialStore $credentialStore,
    ) {}

    public function parse(Request $request): InboundMessage
    {
        $body = $request->all();

        $payload = $body['payload'] ?? [];

        if (($payload['fromMe'] ?? false) === true) {
            return new InboundMessage(
                conversationRef: '',
                channel: Channel::WhatsApp,
                text: '',
            );
        }

        $from = $payload['from'] ?? 'unknown';
        $text = $payload['body'] ?? '';
        $mediaUrl = null;

        if (($payload['hasMedia'] ?? false) === true) {
            $media = $payload['media'] ?? [];
            $mediaUrl = $media['url'] ?? null;
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
        $creds = $this->credentialStore->resolve(Channel::WhatsApp);

        if ($creds === null) {
            return false;
        }

        $hmacKey = $creds->verifySecret;

        if ($hmacKey === '' || $hmacKey === null) {
            return true;
        }

        $signature = $request->header('X-Webhook-Hmac', '');
        $algorithm = $request->header('X-Webhook-Hmac-Algorithm', 'sha512');

        if ($signature === '') {
            return false;
        }

        if ($algorithm !== 'sha512') {
            return false;
        }

        $payload = $request->getContent();
        $expected = hash_hmac('sha512', $payload, $hmacKey);

        return hash_equals($expected, $signature);
    }

    public function formatResponse(AgentResponse $response): OutboundMessage
    {
        $channelExtras = null;

        if ($response->toolCalls !== null) {
            $quickReplies = [];
            foreach ($response->toolCalls as $call) {
                $summary = $call['args_summary'] ?? null;
                if ($summary !== null) {
                    $quickReplies[] = $summary;
                }
            }
            if ($quickReplies !== []) {
                $buttons = array_map(fn (string $text, int $i): array => [
                    'buttonId' => "opt{$i}",
                    'text' => $text,
                ], $quickReplies, array_keys($quickReplies));

                $channelExtras = ['buttons' => $buttons];
            }
        }

        return new OutboundMessage(
            text: $response->content,
            channelExtras: $channelExtras,
        );
    }

    public function send(string $channelRef, OutboundMessage $message): void
    {
        $creds = $this->credentialStore->resolve(Channel::WhatsApp);

        if ($creds === null) {
            return;
        }

        $apiUrl = $creds->extra['waha_api_url'] ?? 'http://localhost:3000';
        $apiKey = $creds->accessToken ?? '';
        $session = $creds->extra['waha_session'] ?? 'default';

        if ($apiKey === '') {
            return;
        }

        $client = new Client([
            'base_uri' => rtrim($apiUrl, '/') . '/',
            'headers' => [
                'X-Api-Key' => $apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);

        try {
            if ($message->mediaUrl !== null) {
                $this->sendMedia($client, $session, $channelRef, $message);
            } else {
                $this->sendText($client, $session, $channelRef, $message);
            }
        } catch (GuzzleException $e) {
            Log::error('WAHA send failed', [
                'channel_ref' => $channelRef,
                'error' => $e->getMessage(),
                'status_code' => $e->getCode(),
            ]);
        }
    }

    public function sendReturnsError(string $channelRef, OutboundMessage $message): AgentResponse
    {
        $creds = $this->credentialStore->resolve(Channel::WhatsApp);

        if ($creds === null) {
            return AgentResponse::fromError(
                AgentError::CHANNEL_DELIVERY_FAILED,
                config('emissary.error_messages.channel.delivery_failed', 'I couldn\'t deliver that message. Please try again.'),
            );
        }

        $apiUrl = $creds->extra['waha_api_url'] ?? 'http://localhost:3000';
        $apiKey = $creds->accessToken ?? '';
        $session = $creds->extra['waha_session'] ?? 'default';

        if ($apiKey === '') {
            return AgentResponse::fromError(
                AgentError::CHANNEL_DELIVERY_FAILED,
                config('emissary.error_messages.channel.delivery_failed', 'I couldn\'t deliver that message. Please try again.'),
            );
        }

        $client = new Client([
            'base_uri' => rtrim($apiUrl, '/') . '/',
            'headers' => [
                'X-Api-Key' => $apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);

        try {
            if ($message->mediaUrl !== null) {
                $this->sendMedia($client, $session, $channelRef, $message);
            } else {
                $this->sendText($client, $session, $channelRef, $message);
            }

            return AgentResponse::fromContent($message->text);
        } catch (GuzzleException $e) {
            Log::error('WAHA send failed', [
                'channel_ref' => $channelRef,
                'error' => $e->getMessage(),
                'status_code' => $e->getCode(),
            ]);

            return AgentResponse::fromError(
                AgentError::CHANNEL_DELIVERY_FAILED,
                config('emissary.error_messages.channel.delivery_failed', 'I couldn\'t deliver that message. Please try again.'),
            );
        }
    }

    private function sendText(Client $client, string $session, string $channelRef, OutboundMessage $message): void
    {
        $body = [
            'session' => $session,
            'chatId' => $channelRef,
            'text' => $message->text,
        ];

        if ($message->channelExtras !== null && isset($message->channelExtras['buttons'])) {
            $body['buttons'] = $message->channelExtras['buttons'];
        }

        $client->post('api/sendText', ['json' => $body]);
    }

    private function sendMedia(Client $client, string $session, string $channelRef, OutboundMessage $message): void
    {
        $mediaUrl = $message->mediaUrl;
        $mimeType = $this->detectMimeType($mediaUrl);

        $body = [
            'session' => $session,
            'chatId' => $channelRef,
        ];

        if (str_starts_with($mimeType, 'image/')) {
            $body['url'] = $mediaUrl;
            $client->post('api/sendImage', ['json' => $body]);
        } elseif ($mimeType === 'application/pdf' || str_starts_with($mimeType, 'application/')) {
            $body['url'] = $mediaUrl;
            $client->post('api/sendFile', ['json' => $body]);
        } elseif (str_starts_with($mimeType, 'audio/')) {
            $body['url'] = $mediaUrl;
            $client->post('api/sendVoice', ['json' => $body]);
        } else {
            $this->sendText($client, $session, $channelRef, $message);
        }
    }

    private function detectMimeType(string $url): string
    {
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            'ogg', 'oga' => 'audio/ogg',
            'mp3' => 'audio/mpeg',
            default => 'application/octet-stream',
        };
    }
}
