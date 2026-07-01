---
extends: _layouts.master
title: Onboarding & Consent
description: Configure first-contact flows, guest accounts, and consent gates.
---

@section('body')
<div class="tldr-box">
    <h4>TL;DR</h4>
    <pre><code class="language-php">// config/emissary.php
'onboarding' => [
    'mode' => 'hybrid',
    'require_consent' => true,
    'guest_creation' => true,
],</code></pre>
</div>

## Quick Start

Emissary supports three onboarding modes:

| Mode | Behavior |
|---|---|
| `channel_first` | User connects via channel → guest identity → full access after consent |
| `auth_first` | User must be authenticated in your Laravel app → identity linked to channel |
| `hybrid` | Both paths supported — guest or authenticated |

### Consent Gate

When `require_consent` is enabled, new users see a welcome message with data usage information. The agent won't process messages until consent is given.

### Guest Accounts

When `guest_creation` is enabled, unauthenticated users get a temporary identity linked to their channel. Guests can be upgraded to full accounts later.

<details class="deep-dive">
    <summary>Deep Dive</summary>
    <div class="deep-dive-content">

### Configuration

```php
'onboarding' => [
    'mode' => 'hybrid',
    'require_consent' => true,
    'consent_message' => 'Welcome! I record conversations to improve responses. Continue?',
    'guest_creation' => true,
    'guest_ttl_days' => 30,
],
```

### Identity Resolution

The `ChannelIdentityResolver` determines who's messaging:
- `AuthChannelIdentityResolver` — Matches by authenticated user
- `GuestCreatingChannelIdentityResolver` — Creates guest for new channel contacts

### Artisan Commands

```bash
php artisan emissary:onboarding:status {userId}      # Check onboarding state
php artisan emissary:onboarding:reset {userId}        # Reset onboarding for a user
```

### Events

The `UserOnboardingTransitioned` event fires when a user's onboarding state changes (guest → consented → linked).

    </div>
</details>
@endsection
