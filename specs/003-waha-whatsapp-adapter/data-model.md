# WAHA WhatsApp Adapter — Data Model

> No new database tables. Changes are limited to config, code, and an error constant.

## Schema Impact: None

This feature does not introduce new migrations. All WAHA credentials are either:
- **Config-backed**: Read from `config/emissary.php` + `.env` via `ConfigChannelCredentialStore` (single-app/default path)
- **DB-backed**: Stored in the existing `channel_configs` table via `EncryptedChannelCredentialStore` (multi-tenant path)

The `channel_configs` table already has a `credentials` JSON column (encrypted) that can accommodate WAHA-specific fields without schema changes.

## Entity Changes

### AgentError (constant addition)

| Constant | Value | Message Key |
|---|---|---|
| `CHANNEL_DELIVERY_FAILED` | `'channel.delivery_failed'` | `error_messages.channel.delivery_failed` |

No migration needed — `AgentError` is a static final class, not a database entity.

### WahaSessionState (new enum, no persistence)

```
WahaSessionState
├── Stopped     ('STOPPED')
├── Starting    ('STARTING')
├── ScanQrCode  ('SCAN_QR_CODE')
├── Working     ('WORKING')
└── Failed      ('FAILED')
```

Transitions:
```
STOPPED → STARTING (on start/restart)
STARTING → SCAN_QR_CODE (QR code available) or WORKING (already authenticated)
SCAN_QR_CODE → WORKING (after QR scan)
WORKING → STOPPED (on stop)
Any → FAILED (on unrecoverable error)
```

This is a PHP enum, not persisted. State is queried live from WAHA's session API.

### ChannelCredentials.extra (DTO extension, no migration)

The existing `extra` field (`?array`) carries WAHA-specific data:
```php
$extra = [
    'waha_api_url' => 'http://localhost:3000',
    'waha_session' => 'default',
    'waha_version' => 'free',
];
```

When stored in `channel_configs.credentials`, these are encrypted at rest by `EncryptedChannelCredentialStore` (same as all channel credentials).

## Config Key Map

| Config Key | Env Variable | Default | Sensitivity |
|---|---|---|---|
| `channels.whatsapp.backend` | — | `'waha'` | Public |
| `channels.whatsapp.waha_api_url` | `WAHA_API_URL` | `'http://localhost:3000'` | Config |
| `channels.whatsapp.waha_api_key` | `WAHA_API_KEY` | `null` | Secret |
| `channels.whatsapp.waha_session` | `WAHA_SESSION` | `'default'` | Config |
| `channels.whatsapp.waha_hmac_key` | `WAHA_HMAC_KEY` | `null` | Secret |
| `channels.whatsapp.waha_version` | `WAHA_VERSION` | `'free'` | Config |
| `error_messages.channel.delivery_failed` | — | `'I couldn\'t deliver that message. Please try again.'` | Public |

## Existing Tables Used (No Changes)

| Table | How It's Used |
|---|---|
| `channel_configs` | Stores WAHA credentials per tenant in `EncryptedChannelCredentialStore` mode. `credentials` JSON column holds API key, session name, URL, version. Encrypted at rest. |
| `agent_events` | `error_code` column stores `CHANNEL_DELIVERY_FAILED` when send fails (FR25). No schema change — column is `VARCHAR` and accepts any error code. |
