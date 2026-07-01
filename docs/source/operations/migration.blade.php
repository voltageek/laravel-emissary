---
extends: _layouts.master
title: Migration Guide
description: Upgrade between Emissary versions — breaking changes, new features, and migration steps.
---

@section('body')
<div class="tldr-box">
    <h4>TL;DR</h4>
    <p>Emissary follows semantic versioning. MAJOR releases include breaking changes. MINOR releases add features. PATCH releases fix bugs.</p>
</div>

## Quick Start

### Upgrading

```bash
composer update voltageek/laravel-emissary
php artisan vendor:publish --tag=emissary-config --force
php artisan migrate
```

The `--force` flag on config publish ensures you see any new or changed config keys.

## Version History

### 2.6 → Current

**New**: Pest test toolkit (`Emissary\Testing\`), replay-as-fixture.

**Breaking**: None. Backward compatible.

**Migration**: No action needed. Install `pestphp/pest` in dev dependencies if using the test toolkit.

### 2.5 → 2.6

**New**: User onboarding (hybrid mode, configurable guest creation, consent gate).

**Breaking**: None. Opt-in feature — enable via `onboarding.enabled`.

**Migration**: Run `php artisan vendor:publish --tag=emissary-config --force` to get the new `onboarding` config block. Run `php artisan migrate` for onboarding tables.

### 2.4 → 2.5

**New**: Channel onboarding (`ChannelCredentialStore`, setup commands, identity linking).

**Breaking**: `ChannelCredentials` constructor signature changed (added `channel` property).

**Migration**: Update any custom credential resolution that constructs `ChannelCredentials` directly. Use `ChannelCredentialStore` instead.

### 2.3 → 2.4

**New**: Turn-traceable observability (`turn_id`, typed events, replay capture).

**Breaking**: Event classes renamed with `Completed` suffix.

**Migration**: Update event listener class references.

### 2.2 → 2.3

**New**: Security model (webhook verify, schema validation, jailbreak guard, cost cap).

**Breaking**: `webhook_verify` now fails closed (default on). Previously failed open.

**Migration**: Set `security.require_webhook_verify` to `false` only if you have a specific reason.

### 2.1 → 2.2

**New**: Attribute-driven tools (`#[Tool]`, `ToolScanner`).

**Breaking**: `getToolHandlers()` removed. Tools now use `#[Tool]` attribute.

**Migration**: Replace `getToolHandlers()` with `#[Tool]` attribute on methods. Example:

```php
// Before (2.1)
public function getToolHandlers(): array
{
    return ['recordSale' => [$this, 'recordSale']];
}

// After (2.2+)
#[Tool(name: 'record_sale', description: 'Record a sale')]
public function recordSale(float $amount, string $item): string
{
    // ...
}
```

### 2.0 → 2.1

**New**: Merged handler types; first-class guards; optional tenancy; error taxonomy.

**Breaking**: Tool handler method signatures changed. Guard interface introduced.

**Migration**: Require full review. See `specs/11-decisions.md` for the rationale behind each change.

## General Guidance

1. **Always publish config** on upgrade: `php artisan vendor:publish --tag=emissary-config --force`
2. **Run migrations**: `php artisan migrate`
3. **Check event listeners**: Event class renames are the most common breaking change
4. **Review security config**: Security defaults may tighten between versions
5. **Test in staging**: Run your test suite against the new version before production deploy
@endsection
