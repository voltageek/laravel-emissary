# WAHA WhatsApp Adapter — Quickstart & Verification

> How to verify the WAHA adapter implementation is correct.

## Prerequisites

- Project dependencies installed (`composer install`)
- Test database configured (SQLite in-memory for unit tests)
- No live WAHA instance needed — all tests use fakes

## Verification Checklist

### 1. Meta Adapter Rename (Regression)

```bash
composer test -- --filter="MetaWhatsAppAdapter"
```

**Expected**: All 4 original WhatsApp adapter tests pass with updated class name. Parse, verify, handshake, send all work exactly as before.

### 2. WAHA Adapter Parsing

```bash
composer test -- --filter="WahaWhatsAppAdapterTest"
```

**Expected tests pass**:
- `parse extracts text and sender from WAHA payload` — `payload.from` → `conversationRef`, `payload.body` → `text`
- `parse handles media webhook path` — `payload.hasMedia` + `payload.media.url` → `mediaUrl`
- `parse skips fromMe messages` — `payload.fromMe === true` → early return, no pipeline entry
- `verify returns true for valid HMAC-SHA512 signature` — uses WAHA test vector
- `verify returns false for invalid HMAC signature`
- `verify passes when waha_hmac_key is not configured`
- `send posts to sendText` — correct URL, headers, JSON body
- `send posts to sendImage for image media`
- `send posts to sendFile for document media`
- `formatResponse maps quickReplies to WAHA buttons` — `channelExtras` contains button array
- `send returns error response on API failure` — `AgentResponse::fromError(CHANNEL_DELIVERY_FAILED)`

### 3. WAHA Client

```bash
composer test -- --filter="WahaClientTest"
```

**Expected tests pass**:
- `createSession sends correct request`
- `startSession sends correct request with webhook config`
- `stopSession sends stop request`
- `getStatus returns correct WahaSessionState`
- `getQrCode returns raw QR string`
- `deleteSession sends delete request`
- `listSessions returns session array`

### 4. WAHA Session State

```bash
composer test -- --filter="WahaSessionStateTest"
```

**Expected tests pass**:
- `fromApiResponse maps STOPPED`
- `fromApiResponse maps STARTING`
- `fromApiResponse maps SCAN_QR_CODE`
- `fromApiResponse maps WORKING`
- `fromApiResponse maps FAILED`

### 5. WAHA Commands

```bash
composer test -- --filter="WahaCommandTest"
```

**Expected tests pass**:
- `session:start creates and starts session, displays QR when needed`
- `session:start forces default session in free mode`
- `session:status shows current state`
- `session:stop sends stop request`
- `session:restart cycles session`
- `session:qr displays raw QR code`
- `session:list shows all sessions`
- `session:delete warns if WORKING`

### 6. Fake Channel Adapter (WAHA Factory)

```bash
composer test -- --filter="FakeChannelAdapter.*waha"
```

**Expected**: `FakeChannelAdapter::waha()` creates a WhatsApp-flavored fake with WAHA-specific conversation ref format (`12345678901@c.us`) and the correct `Channel::WhatsApp`.

### 7. AgentError Constant

```bash
composer test -- --filter="AgentError.*CHANNEL_DELIVERY_FAILED"
```

**Expected**: `AgentError::CHANNEL_DELIVERY_FAILED` exists and equals `'channel.delivery_failed'`. Config includes a default message under `error_messages.channel.delivery_failed`.

### 8. Config Compilation

```bash
php -r "require 'vendor/autoload.php'; echo json_encode(config('emissary.channels.whatsapp'), JSON_PRETTY_PRINT);"
```

**Expected**: Config output includes all WAHA keys (`waha_api_url`, `waha_api_key`, `waha_session`, `waha_hmac_key`, `waha_version`, `backend`) alongside Meta keys. Default `backend` is `'waha'`.

### 9. Full Suite (No Regressions)

```bash
composer test
```

**Expected**: Full test suite green. No test failures from the rename of `WhatsAppAdapter` → `MetaWhatsAppAdapter`.

### 10. Doc Lint

```bash
php scripts/docslint.php
```

**Expected**: No documentation errors. All code examples in docs are syntactically valid PHP 8.3+.

## End-to-End Test Script (Manual)

For a manual smoke test with a real WAHA instance:

```bash
# 1. Start WAHA
docker run -d --name waha -p 3000:3000 devlikeapro/waha

# 2. Configure env
export WAHA_API_URL=http://localhost:3000
export WAHA_API_KEY=my-secret-key

# 3. Start session (creates + starts + configures webhook)
php artisan emissary:waha:session:start
# → Shows QR code if SCAN_QR_CODE state
# → Scan with WhatsApp mobile app
# → Transitions to WORKING

# 4. Check status
php artisan emissary:waha:session:status
# → WORKING

# 5. Send test message
php artisan emissary:channel:test whatsapp
# → Confirms connectivity + sends test message

# 6. Verify
# → Check your WhatsApp for the test message
# → Send a reply → webhook fires → pipeline responds
```
