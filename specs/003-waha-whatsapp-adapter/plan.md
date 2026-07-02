# WAHA WhatsApp Adapter — Implementation Plan

> Plan version: 1.0 · Spec: `specs/003-waha-whatsapp-adapter/spec.md` · Date: 2026-07-01

## Summary

Replace the default WhatsApp adapter from Meta's WhatsApp Business API to WAHA (WhatsApp HTTP API). The existing Meta adapter is preserved as `MetaWhatsAppAdapter`. A new `WahaWhatsAppAdapter` + `WahaClient` + 6 Artisan commands provide full WhatsApp functionality without requiring a Meta Business account. This is a focused channel-level change — the pipeline, guards, tools, and observability layers are untouched.

Total estimated changes: ~20 new/modified files.

## Constitution Check

- [x] **Principle 1 — Spec-Driven**: All class names, method signatures, and config keys from `spec.md` used verbatim. `WahaWhatsAppAdapter` implements `ChannelAdapter` exactly per `specs/02-contracts.md`.
- [x] **Principle 2 — Security by Default**: HMAC-SHA512 webhook verification enforced when `waha_hmac_key` is configured; `fromMe` echo filtering; `CHANNEL_DELIVERY_FAILED` error on send failure; API keys encrypted via `EncryptedChannelCredentialStore`.
- [x] **Principle 3 — Testability First**: All tests use `FakeChannelAdapter::waha()` + HTTP test double for WAHA API. No live WAHA instance required.
- [x] **Principle 4 — Observability by Design**: Existing `TurnCompleted` event emitted with `turn_id` on send failures (FR25). No new events needed — send failures flow through existing pipeline error path.
- [x] **Principle 5 — Single Tool Surface**: No change — tools remain defined via `#[Tool]` attribute.
- [x] **Principle 6 — Guards as First-Class Primitives**: No change — adapter is below the guard layer.
- [x] **Principle 7 — Config-Driven Behavior**: Backend selection (`waha`/`meta`) and all WAHA credentials via config + env. `waha_version` controls free/plus mode.
- [x] **Principle 8 — Channel Agnosticism**: `formatResponse()` maps channel-agnostic `OutboundMessage.quickReplies` to WAHA buttons. Tool handlers unchanged.
- [x] **Principle 9 — Explicit Error Taxonomy**: New `AgentError::CHANNEL_DELIVERY_FAILED` constant with configurable message.
- [x] **Principle 10 — PHP 8.3+ Conventions**: All new code uses readonly classes, named arguments, match expressions, constructor property promotion. Pest-first testing.

---

## Phase 1 — WAHA Adapter (Single Phase)

**Goal**: WhatsApp channel works via WAHA by default. Meta adapter preserved as opt-in alternative. Full session lifecycle commands available. All tests green.

**Milestone**: `FakeChannelAdapter::waha()` → `send('hello')` → `parse()` returns correct `InboundMessage` → `send()` reaches correct WAHA endpoint → `assertReply()`.

### 1.1 — Rename Existing Adapter

Preserve existing Meta WhatsApp integration under a new name.

| # | File | Action | Spec Ref |
|---|---|---|---|
| 1 | `src/Channels/MetaWhatsAppAdapter.php` | Rename from `WhatsAppAdapter.php`; class renamed to `MetaWhatsAppAdapter`; no behavioral changes | FR2 |
| 2 | `src/Channels/WhatsAppAdapter.php` | Delete (replaced by #1 and #10) | FR2 |
| 3 | `tests/Unit/MetaWhatsAppAdapterTest.php` | Rename from `WhatsAppAdapterTest.php`; update imports to `MetaWhatsAppAdapter`; all test logic unchanged | FR2 |
| 4 | `tests/Unit/WhatsAppAdapterTest.php` | Delete (replaced by #3) | FR2 |

### 1.2 — Configuration Changes

Add WAHA credential keys and backend selection. Preserve Meta keys.

| # | File | Action | Spec Ref |
|---|---|---|---|
| 5 | `config/emissary.php` | Add `backend` key (`'waha'`); add 5 WAHA keys: `waha_api_url`, `waha_api_key`, `waha_session`, `waha_hmac_key`, `waha_version`; change default `adapter` to `WahaWhatsAppAdapter::class`; preserve Meta keys; add `channel.delivery_failed` error message | FR3, FR4, FR28 |
| 6 | `src/EmissaryServiceProvider.php` | Update binding: resolve `ChannelAdapter` for WhatsApp based on `channels.whatsapp.backend` config (binds `WahaWhatsAppAdapter` for `'waha'`, `MetaWhatsAppAdapter` for `'meta'`) | FR3 |

### 1.3 — Error Taxonomy

| # | File | Action | Spec Ref |
|---|---|---|---|
| 7 | `src/AgentError.php` | Add `const CHANNEL_DELIVERY_FAILED = 'channel.delivery_failed';` | FR28 |

### 1.4 — WAHA Session State Enum

| # | File | Action | Spec Ref |
|---|---|---|---|
| 8 | `src/WahaSessionState.php` | New enum: `case Stopped`, `case Starting`, `case ScanQrCode`, `case Working`, `case Failed`; static `fromApiResponse(string $status): self` factory method | FR11 |

### 1.5 — WAHA HTTP Client

Thin wrapper around WAHA's REST API for session management. Used by Artisan commands.

| # | File | Action | Spec Ref |
|---|---|---|---|
| 9 | `src/Waha/WahaClient.php` | New class. Constructor: `(string $apiUrl, string $apiKey)`. Methods: `createSession(string $name, ?string $webhookUrl, ?string $hmacKey): array`, `startSession(string $name, ?string $webhookUrl, ?string $hmacKey): array`, `stopSession(string $name): array`, `restartSession(string $name, ?string $webhookUrl, ?string $hmacKey): array`, `getStatus(string $name): WahaSessionState`, `getQrCode(string $name, string $format = 'raw'): ?string`, `deleteSession(string $name): array`, `listSessions(): array`, `getScreenshot(string $session): string`, `getMe(string $session): array` | FR10–FR17 |

### 1.6 — WAHA WhatsApp Adapter

The core replacement. Implements `ChannelAdapter` for WAHA backend.

| # | File | Action | Spec Ref |
|---|---|---|---|
| 10 | `src/Channels/WahaWhatsAppAdapter.php` | New class implementing `ChannelAdapter`. Constructor: `(ChannelCredentialStore $credentialStore)`. Methods: `parse(Request $request): InboundMessage` — extracts `payload.from`, `payload.body`, `payload.media` from WAHA webhook JSON; skips `fromMe: true` messages. `verify(Request $request): bool` — validates HMAC-SHA512 on `X-Webhook-Hmac` header using `waha_hmac_key`; passes when key not configured. `formatResponse(AgentResponse $response): OutboundMessage` — maps `quickReplies` to WAHA button format in `channelExtras`. `send(string $channelRef, OutboundMessage $message): void` — dispatches to `sendText`/`sendImage`/`sendFile`/`sendVoice` based on media presence; returns `AgentResponse::fromError(CHANNEL_DELIVERY_FAILED)` on failure | FR1, FR5–FR7, FR22–FR27 |

### 1.7 — Webhook Updates

| # | File | Action | Spec Ref |
|---|---|---|---|
| 11 | `src/Http/WebhookController.php` | Update `whatsapp()` method: resolve adapter dynamically; return HTTP 405 for GET when backend is `'waha'`; drop handshake call for WAHA | FR8, FR9 |
| 12 | `routes/webhooks.php` | No structural change needed (already `Route::match(['GET', 'POST'], ...)`); adapter handles routing internally | — |

### 1.8 — Session Management Commands

6 new Artisan commands for WAHA session lifecycle.

| # | File | Action | Spec Ref |
|---|---|---|---|
| 13 | `src/Commands/EmissaryWahaSessionStart.php` | New command `emissary:waha:session:start {session?}`. Reads WAHA config, resolves session name (forces `'default'` in free mode), creates+starts session via `WahaClient`, configures webhook URL + HMAC, polls status, displays QR code if `SCAN_QR_CODE` | FR10, FR17 |
| 14 | `src/Commands/EmissaryWahaSessionStatus.php` | New command `emissary:waha:session:status {session?}`. Prints state and QR code when applicable | FR11 |
| 15 | `src/Commands/EmissaryWahaSessionStop.php` | New command `emissary:waha:session:stop {session?}`. Stops session via `WahaClient` | FR12 |
| 16 | `src/Commands/EmissaryWahaSessionRestart.php` | New command `emissary:waha:session:restart {session?}`. Stops then starts | FR13 |
| 17 | `src/Commands/EmissaryWahaSessionQr.php` | New command `emissary:waha:session:qr {session?}`. Fetches and displays raw QR code | FR14 |
| 18 | `src/Commands/EmissaryWahaSessionList.php` | New command `emissary:waha:session:list`. Lists all sessions via `WahaClient` | FR15 |
| 19 | `src/Commands/EmissaryWahaSessionDelete.php` | New command `emissary:waha:session:delete {session}`. Deletes session; warns if `WORKING` | FR16 |

### 1.9 — Update Existing Commands

| # | File | Action | Spec Ref |
|---|---|---|---|
| 20 | `src/Commands/EmissaryChannelTest.php` | Add WAHA path: when `backend = 'waha'`, check WAHA API connectivity + session status before sending test message | FR20 |
| 21 | `src/Commands/EmissaryChannelAdd.php` | Add `--waha-session` and `--waha-api-key` options; prompt for WAHA credentials in interactive mode; store in `EncryptedChannelCredentialStore` | FR21 |

### 1.10 — Testing

| # | File | Action | Spec Ref |
|---|---|---|---|
| 22 | `src/Testing/FakeChannelAdapter.php` | Add static factory `FakeChannelAdapter::waha(?string $conversationRef = null): self`. Creates fake with `Channel::WhatsApp`, default `conversationRef` `'12345678901@c.us'`. Records sent messages with WAHA-specific payload format for assertions | FR1 |
| 23 | `tests/Unit/WahaWhatsAppAdapterTest.php` | New tests: parse (text message, media message, fromMe skip), verify (valid HMAC passes, invalid fails, no key passes), send (text endpoint, image endpoint, file endpoint), formatResponse (quickReplies → button format), send failure returns error response | FR1, FR5–FR7, FR22–FR27 |
| 24 | `tests/Unit/WahaClientTest.php` | New tests: session CRUD, status mapping, QR code retrieval, list sessions, screenshot, me endpoint. Uses HTTP fake/mock for WAHA API responses | FR10–FR17 |
| 25 | `tests/Unit/WahaSessionStateTest.php` | New tests: `fromApiResponse` maps all WAHA status strings to correct enum cases, unknown status throws or returns a default | FR11 |
| 26 | `tests/Unit/WahaCommandTest.php` | New tests: each WAHA session command produces expected output and exits with correct code. Tests free-mode session name forcing, plus mode multi-session | FR10–FR17 |

### 1.11 — Documentation

| # | File | Action | Spec Ref |
|---|---|---|---|
| 27 | `docs/source/guides/channels/whatsapp.blade.php` | Rewrite: WAHA as default setup (Docker + env vars + `session:start` + QR scan). Tabbed view for WAHA vs Meta. Preserve Meta setup as secondary tab | — |
| 28 | `docs/source/reference/config.blade.php` | Add WAHA config keys; mark Meta keys as legacy | — |
| 29 | `docs/source/reference/commands.blade.php` | Add 7 WAHA session commands | — |
| 30 | `docs/source/reference/api/dtos.blade.php` | Add `AgentError::CHANNEL_DELIVERY_FAILED` constant | — |
| 31 | `docs/source/reference/api/inheritance.blade.php` | Add `WahaWhatsAppAdapter` and `MetaWhatsAppAdapter` to diagram | — |

---

## Dependency Graph

```
1.1 (Rename Meta adapter)
  │
  ▼
1.2–1.3 (Config + Error constant)
  │
  ├────────────────────────────────────┐
  ▼                                    ▼
1.4 (WahaSessionState)          1.5 (WahaClient)
  │                                    │
  └────────────┬───────────────────────┘
               ▼
         1.6 (WahaWhatsAppAdapter)
               │
               ├──────────────────────┐
               ▼                      ▼
         1.7 (Webhook updates)   1.8 (Session commands)
               │                      │
               │              1.9 (Existing command updates)
               │                      │
               └──────────┬───────────┘
                          ▼
                    1.10 (Tests)
                          │
                          ▼
                    1.11 (Docs)
```

1.8 (Session commands) and 1.9 (Existing command updates) can be developed in parallel after 1.5 (WahaClient) completes.

## Risks & Mitigations

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| WAHA API payload format changes | M | H | `parse()` tests use real WAHA payload examples from docs; `FakeChannelAdapter::waha()` captures exact payload shapes |
| WAHA free mode session limit breaks multi-tenant | L | M | FR17 enforces at command level; config validation warns; documentation clear on free vs plus |
| Meta adapter rename breaks existing user configs | L | H | `backend` config key provides explicit migration path; Service provider resolves correct adapter; Meta users set `backend = 'meta'` once |
| HMAC-SHA512 verification mismatch with WAHA | M | M | Use WAHA's documented test vector (`my-secret-key` example from docs) as test fixture |
| Webhook echo (fromMe) filtering edge cases | L | L | `fromMe === true` always skipped; test covers both boolean true and missing field |
| `ChannelCredentials.extra` contract change breaks downstream | L | M | `extra` is `?array` already in DTO; only WAHA adapter reads it; Meta adapter ignores it |

## Definition of Done

1. `MetaWhatsAppAdapter` passed all original `WhatsAppAdapterTest` cases (renamed).
2. `WahaWhatsAppAdapter` implements `ChannelAdapter` with all four methods; Pest tests pass.
3. `FakeChannelAdapter::waha()` factory creates a WhatsApp-flavored fake with WAHA payload format.
4. 7 WAHA session Artisan commands registered and functional.
5. `AgentError::CHANNEL_DELIVERY_FAILED` constant exists with configurable message.
6. `composer test -- --filter="(Waha|MetaWhatsApp)"` is green.
7. `composer test` full suite is green (no regressions from rename).
8. Documentation pages updated with WAHA setup and command references.
9. `php scripts/docslint.php` passes.

## File Count Summary

| Category | New | Modified | Deleted | Total |
|---|---|---|---|---|
| Source (src/) | 9 | 4 | 1 | 14 |
| Config | 0 | 1 | 0 | 1 |
| Tests | 4 | 2 | 1 | 7 |
| Docs | 0 | 5 | 0 | 5 |
| **Total** | **13** | **12** | **2** | **27** |

## Verification Commands

```bash
# Adapter tests
composer test -- --filter="WahaWhatsAppAdapter"

# Session client tests
composer test -- --filter="WahaClient"

# Session state tests
composer test -- --filter="WahaSessionState"

# Command tests
composer test -- --filter="WahaCommand"

# Meta adapter (regression)
composer test -- --filter="MetaWhatsAppAdapter"

# Full suite (must be green)
composer test

# Documentation lint
php scripts/docslint.php
```
