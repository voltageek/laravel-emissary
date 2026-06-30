# User Onboarding

> First-contact journey, modes, consent, guest creation.

---

## User Onboarding

User onboarding is the first-contact journey for an end-user: welcome, optional profile collection, and consent. It is **opt-in** (`onboarding.enabled` defaults false) and built from existing primitives — a built-in intent, a guard, a resolver, and a couple of tools — so apps that already have their own signup ignore it entirely.

### Modes (`onboarding.mode`)

| Mode | Chat-channel identity | When to use |
|---|---|---|
| `web_centric` | `AuthChannelIdentityResolver` — `$user` is `null` until the sender links an existing account via `verify_identity`. **No guest creation.** | The web app is primary; chat is a secondary channel for known users. |
| `channel_first` | `GuestCreatingChannelIdentityResolver` — a **guest `User`** is created on first chat contact and upgraded after onboarding. | The bot is the product (WhatsApp/Telegram-led). |
| `hybrid` *(default when enabled)* | Guest creation **on** for channel-first senders; web users still link existing accounts via `verify_identity`. | Both shapes coexist. |

**Channel-first guest creation is configurable** — it is bound only when `mode` is `channel_first` or `hybrid`. `web_centric` never writes guest rows and never binds the guest-creating resolver.

### First-contact flow

1. **Detect.** On inbound, if `Conversation.onboarding_state == 'new'` and `onboarding.enabled`, `ProcessMessage` routes to the built-in `start_onboarding` intent instead of normal classification; state becomes `onboarding`.
2. **Welcome.** The `start_onboarding` intent delivers `onboarding.welcome_message` and explains the agent's capabilities (sourced from registered intents).
3. **Collect profile.** The agent gathers `onboarding.fields` (e.g. `name`, `email`) conversationally, persisting each via a built-in `update_profile` tool into `UserOnboarding.profile`. Optional `onboarding.field_map` mirrors collected values onto the `User` model.
4. **Consent.** If `onboarding.require_consent`, the agent presents `onboarding.consent_text` and captures acceptance via a built-in `accept_consent` confirmation gate, stamping `consent_at` + `consent_version`.
5. **Complete.** State → `complete`; `User.onboarded_at = now()` (guest upgraded). The `OnboardingGuard` stops gating them.

### Channel-first guest creation
When enabled by mode, `GuestCreatingChannelIdentityResolver` runs on first chat contact: no existing `ChannelIdentityLink` → create a `User` row (`onboarded_at = null` = guest) + `ChannelIdentityLink` → return it. The onboarding flow then collects profile/consent and upgrades the guest. Web-channel users always come from the session and never trigger guest creation.

### Consent as a control
`OnboardingGuard` (`beforeExecution`) blocks `onboarding.gated_intents` (default `['*']`) until `onboarding_state == complete` (or `skipped`). With `require_consent = true`, completion additionally requires a recorded `consent_at`. This makes consent an enforceable precondition to tool-bearing intents — relevant where processing chat for a third-party LLM needs explicit agreement (see Security). Disabling `require_consent` is the host's legal call and is documented as such.

### Web users vs channel-first users
- **Web user** (already authenticated) → onboarding can be `skipped` automatically, or run if you still want channel consent. No guest is created.
- **Channel-first user** → guest created → onboarding runs → upgraded to full.

### Acceptance criteria (EARS)
- **WHEN** `onboarding.enabled` is false **THE SYSTEM SHALL** set new conversations to `onboarding_state = skipped` and register no `OnboardingGuard` behaviour (no gating).
- **WHEN** an inbound message arrives on a conversation with `onboarding_state = new` **AND** `onboarding.enabled` is true **THE SYSTEM SHALL** route to the `start_onboarding` intent (bypassing normal classification) and set `onboarding_state = onboarding`.
- **IF** `onboarding.mode` is `web_centric` **THE SYSTEM SHALL NOT** create guest `User` rows; chat-channel `$user` remains `null` until `verify_identity` links an account.
- **WHEN** `GuestCreatingChannelIdentityResolver` resolves a chat-channel message with no existing `ChannelIdentityLink` **THE SYSTEM SHALL** create a `User` (`onboarded_at = null`) + link, emit `UserOnboardingTransitioned(transition: guest_created)`, and return that user.
- **WHEN** `OnboardingGuard` evaluates a gated intent on a conversation whose `onboarding_state` is neither `complete` nor `skipped` **THE SYSTEM SHALL** deny with `AgentError::ONBOARDING_REQUIRED`.
- **IF** `onboarding.require_consent` is true **WHEN** onboarding completes **THE SYSTEM SHALL** require a non-null `consent_at` matching the current `consent_version`; otherwise completion is blocked.
- **WHEN** onboarding completes **THE SYSTEM SHALL** set `onboarding_state = complete`, `User.onboarded_at = now()`, `UserOnboarding.completed_at = now()`, and emit `UserOnboardingTransitioned(transition: completed)`.
- **WHEN** `consent_version` is bumped in config **THE SYSTEM SHALL** treat previously-onboarded users as needing re-consent (their next gated intent is blocked until the new version is accepted).
