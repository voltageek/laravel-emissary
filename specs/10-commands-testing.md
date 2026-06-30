# Commands & Testing

> Artisan commands + the Pest Testing toolkit.

---

## Artisan Commands

| Command | Purpose |
|---|---|
| `agent:report [--conversation=] [--since=] [--model=]` | Prints an aggregate summary: turns, p50/p95 latency, total tokens & cost, error rate by code, top tools by call count / failure rate, and guard-denial counts. Reads `agent_events` + `tool_invocations` + `cost_ledgers`. |
| `agent:replay <turn_id> [--re-run]` | Prints the ordered trace for one turn — guards → intent → (LLM call ↔ tool invocation ↔ span)… → reply — reconstructed from `agent_events`, `tool_invocations`, `ConversationMessage` (and `llm_payloads` / `agent_spans` if captured). `--re-run` re-sends each captured `request_messages` + `tools_sent` to the same model and diffs the response, for "did the model behave differently?" reproduction (requires `observability.capture_llm_payloads`; LLM non-determinism is expected and shown as a diff). |
| `agent:prune` | Deletes rows older than their TTL across `conversation_messages`, `agent_events`, `tool_invocations`, `llm_payloads`, and `agent_spans`. Intended for a scheduled runner. |
| `agent:channels:list` | Lists configured (static) or provisioned (dynamic) channels with status — used to confirm what's live. |
| `agent:webhook:url <channel>` | Prints the absolute webhook URL to paste into the provider's console (uses `APP_URL` + `webhook_path`). |
| `agent:set-telegram-webhook` | Calls Telegram's `setWebhook` with the app URL + `TELEGRAM_SECRET_TOKEN`. Run once after bot creation. |
| `agent:channel:test <channel> [--tenant=]` | Round-trip health check: resolves credentials via the store, confirms the webhook is reachable, and sends a test outbound message. |
| `agent:channel:add <channel> [--tenant=]` | Interactively provisions a new channel's credentials into the DB-backed store (`EncryptedChannelCredentialStore` only). |
| `agent:onboarding:status <channel_ref>` | Prints the onboarding state + profile/consent for a conversation's sender (for support/debugging). |
| `agent:onboarding:reset <channel_ref>` | Resets `onboarding_state` to `new` and clears the `UserOnboarding` record, so the flow re-runs (for testing or re-consent). |
| `agent:fixture:capture <turn_id> [--name=]` | Exports a captured turn (messages, `llm_payloads`, `tool_invocations`, events) to `tests/Fixtures/Agent/<name>.json` for use as a Pest regression dataset (see Testing → Replay-as-fixture). Requires `observability.capture_llm_payloads`. |

---

## Testing

The agent loop is non-deterministic and talks to external services (LLM, channel APIs), so the library ships its own **Pest-first test toolkit** in a `Testing\` namespace (importable by host apps, mirroring `Illuminate\Testing`). Nothing in a test hits the network or a live model.

### Test doubles

```php
// FakeLlmClient — deterministic, scripted responses; records every call.
$llm = FakeLlmClient::make()
    ->onIntent(new IntentResult('place_order', 0.92))
    ->onAgent(ToolCall::make('placeOrder', ['product_id' => 42, 'quantity' => 3]))
    ->thenText('Order placed.');

// FakeChannelAdapter — in-process webhook→reply, no HTTP.
$channel = FakeChannelAdapter::whatsapp();

// Clock fake — for expiry, rate-limit windows, retention pruning, onboarding timeout.
$clock = Clock::fake('2026-06-29 10:00:00');
$clock->advance(900); // 15 min → confirmation now expired
```

`FakeLlmClient` exposes the recorded calls (model, messages, tools sent) for assertion; `FakeChannelAdapter` records outbound messages and never performs real sends. The `Clock` fake is injected wherever the pipeline reads time.

### `AgentTestCase` (Pest)

Boots a minimal container with the fakes bound and provides fluent send/confirm/assert helpers:

```php
it('runs a write tool via the confirmation fast-path', function () {
    send(whatsapp(), 'Order 3 units of product 42')
        ->assertReply('Place an order for 3x product 42?')
        ->assertConfirmationProposed('placeOrder');

    confirm()->assertReply('Order #123 placed.')
        ->assertToolCalled('placeOrder', fn (ToolInvocationCompleted $t) =>
            $t->triggeredVia === 'confirmation_fastpath'
        );
});

it('denies when onboarding is incomplete', function () {
    send(whatsapp(), 'cancel my order')
        ->assertGuardDenied(OnboardingGuard::class)
        ->assertTurnOutcome('guard_denied');
});
```

Assertion set: `assertReply`, `assertReplyCount`, `assertToolCalled`, `assertIntentClassified`, `assertGuardDenied`, `assertConfirmationProposed`, `assertTurnOutcome`, `assertEvent(...)`, `assertOnboardingState(...)`.

### Replay-as-fixture

Captured production turns become regression tests, reusing the v2.3 replay infra:

1. A turn misbehaves in prod → debug with `agent:replay <turn_id>`.
2. Freeze it: `agent:fixture:capture <turn_id> --name=place_order_overflow` writes `tests/Fixtures/Agent/place_order_overflow.json` (messages, `llm_payloads`, `tool_invocations`, events).
3. Replay against `FakeLlmClient` and assert the outcome:

```php
it('regresses: place_order overflow is blocked', fn ($fixture) => {
    replay($fixture)->assertTurnOutcome('guard_denied');
})->dataset('agent/fixtures', fn () => glob(__DIR__.'/Fixtures/Agent/*.json'));
```

By default a fixture replays against its **captured** model responses (deterministic); live re-run semantics mirror `agent:replay --re-run`. Note fixtures bind to model id and `consent_version` — a model migration may require re-freezing.

### Coverage by layer

| Layer | Scope | How |
|---|---|---|
| Unit | `ToolScanner` (type inference → JSON schema), JSON-schema argument validation, `GuardResult`, DTOs, `AgentError` | plain Pest, no boot |
| Component | `ToolRegistry` (merge, escape-hatch precedence), `GuardRegistry` (registration order, first-deny short-circuit), `IntentRouter` + `ModelSelector`, `ConfirmationGate`, `ConversationMemory` wrapping | fakes |
| Pipeline | `ProcessMessage::handle` end-to-end — intent→agent→tool→reply, guard denials, low-confidence fallback, confirmation two-turn, onboarding | `AgentTestCase` + `FakeChannelAdapter` |
| Plugin | provider's tools resolve to the expected schema, guards fire at the right checkpoint, confirmation templates register | `AgentTestCase` helpers against a real provider |

### What is never tested live
LLM inference, channel webhook/Send APIs, and the wall clock. The fakes replace all three; `agent:channel:test` remains the only live verification and is a smoke check, not a test.
