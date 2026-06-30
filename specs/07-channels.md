# Channel Onboarding

> Getting a channel live: credentials, webhooks, per-channel setup.

---

## Channel Onboarding

Getting a channel live is a hybrid story: single-app installs configure once via `config/agent.php` + `.env`; multi-tenant apps provision dynamically via a DB-backed `ChannelCredentialStore`. Both paths feed the same `ChannelAdapter` seam, so the pipeline is unaware of where credentials came from.

### Credential matrix

| Channel | Credentials (`ChannelCredentials`) | External setup |
|---|---|---|
| WhatsApp | `accessToken`, `senderId` (phone_number_id), `verifySecret` (app secret), `handshakeToken` (hub.verify_token) | Meta Business app → WhatsApp Business Account → subscribe webhook to `messages` |
| Telegram | `accessToken` (bot token), `verifySecret` (X-Telegram-Bot-Api-Secret-Token) | BotFather → create bot → `agent:set-telegram-webhook` |
| Web | `verifySecret` (CSRF key) | none external — drop JS widget + Blade include |

### Webhook route registration contract
The package service provider auto-registers the webhook routes under a configurable prefix (default `webhooks`):

```
POST /webhooks/whatsapp   → verify() (HMAC) → parse()
GET  /webhooks/whatsapp   → registration handshake (echo hub.challenge)
POST /webhooks/telegram   → verify() (secret header) → parse()
POST /webhooks/web        → verify() (CSRF/session) → parse()
```

`config('agent.webhook_path', 'webhooks')` controls the prefix; set `APP_URL` so setup commands can print the absolute URL. The controller resolves the adapter from `channels.<channel>.adapter` and credentials via `ChannelCredentialStore`.

### Setup procedure

**WhatsApp**
1. Create a Meta Business app + WhatsApp Business Account; add a System User access token.
2. Note the phone_number_id, app secret, and choose a verify token string.
3. Put them in `.env` (static) or provision via `agent:channel:add whatsapp` (dynamic).
4. Run `agent:webhook:url whatsapp` → paste the URL into Meta's webhook subscription; on save Meta performs the GET handshake (the controller echoes `hub.challenge`).
5. Subscribe the webhook to the `messages` field.
6. `agent:channel:test whatsapp` — sends a test outbound message to confirm the round trip.

**Telegram**
1. Create a bot via BotFather; copy the bot token.
2. Choose a secret-token string for the `X-Telegram-Bot-Api-Secret-Token` header.
3. Put them in `.env` (static) or provision via `agent:channel:add telegram` (dynamic).
4. `agent:set-telegram-webhook` — calls Telegram's `setWebhook` with the app URL + secret token.
5. `agent:channel:test telegram`.

**Web**
1. Publish the widget assets (`vendor:publish --tag=agent-web-widget`).
2. `@include('agent::widget')` in your layout, or drop the JS snippet.
3. No external credentials; CSRF/session is handled by the adapter.

### Chat→user identity linking
For WhatsApp/Telegram authorisation against Laravel users, run the linking flow (see `ChannelIdentityResolver`): a logged-in user requests a code, sends `VERIFY <code>` from the channel, the built-in `verify_identity` intent binds the `channel_ref` to their account. Until linked, chat-channel `$user` is `null`.

### Credential security
**Channel credentials are secrets and are encrypted at rest** — `EncryptedChannelCredentialStore` encrypts the `ChannelConfig.credentials` column (Laravel's encrypter). This is the opposite default to conversation `content` (unencrypted, because transcripts are operational data, not authenticators). Treat tokens with full secret hygiene: never log them, never expose in client responses, rotate via `agent:channel:add` on compromise.
