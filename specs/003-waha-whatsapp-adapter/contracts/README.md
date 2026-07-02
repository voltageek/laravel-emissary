# WAHA WhatsApp Adapter — Contracts

> No new interfaces. All new classes implement existing contracts.

## Interfaces Consumed

| Interface | Implemented By | Location |
|---|---|---|
| `Emissary\Contracts\ChannelAdapter` | `WahaWhatsAppAdapter`, `MetaWhatsAppAdapter` | `src/Contracts/ChannelAdapter.php` |
| `Emissary\Contracts\ChannelCredentialStore` | _Injected into adapters_ | `src/Contracts/ChannelCredentialStore.php` |

## Interface Method Summary (ChannelAdapter)

No changes to the interface. Both adapters implement the same four methods:

```php
interface ChannelAdapter
{
    public function parse(Request $request): InboundMessage;
    public function verify(Request $request): bool;
    public function formatResponse(AgentResponse $response): OutboundMessage;
    public function send(string $channelRef, OutboundMessage $message): void;
}
```

### Behavioral Notes Per Backend

| Method | WAHA | Meta |
|---|---|---|
| `parse()` | Reads `payload.from`, `payload.body`, `payload.media`; skips `fromMe: true` | Reads `entry[0].changes[0].value.messages[0]` |
| `verify()` | Validates `X-Webhook-Hmac` (HMAC-SHA512); passes when key unset | Validates `X-Hub-Signature-256` (HMAC-SHA256); fails when secret missing |
| `formatResponse()` | Maps `quickReplies` to WAHA button format | Maps `quickReplies` to Meta interactive format |
| `send()` | POSTs to `{waha_url}/api/sendText` (or media endpoints) | POSTs to `graph.facebook.com/v18.0/{phone_number_id}/messages` |

## DTO Usage

| DTO | Field | WAHA Usage | Meta Usage |
|---|---|---|---|
| `ChannelCredentials` | `verifySecret` | `waha_hmac_key` | `app_secret` |
| | `accessToken` | `waha_api_key` | `access_token` |
| | `senderId` | `null` (unused) | `phone_number_id` |
| | `handshakeToken` | `null` (no GET handshake) | `verify_token` |
| | `extra` | `['waha_api_url' => ..., 'waha_session' => ..., 'waha_version' => ...]` | `null` (unused) |

## New Classes (No Interface Changes)

| Class | Type | Purpose |
|---|---|---|
| `WahaWhatsAppAdapter` | Adapter | Implements `ChannelAdapter` for WAHA |
| `MetaWhatsAppAdapter` | Adapter | Renamed existing `WhatsAppAdapter` |
| `WahaClient` | HTTP Client | Wraps WAHA session management API |
| `WahaSessionState` | Enum | WAHA session states |

## Error Taxonomy Addition

```php
// Add to AgentError:
const CHANNEL_DELIVERY_FAILED = 'channel.delivery_failed';
```
