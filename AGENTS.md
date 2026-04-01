# AGENTS.md

Instructions for coding agents working in `/Users/edvintoome/Work/kt-woo-plugins`.

## Repo Overview

This repo contains small standalone WooCommerce plugins.

Current plugins:

- `kt-auto-apply-url-coupon`
- `kt-post-purchase-upsell`

Treat each plugin as an independent deployable unit. Keep changes local to the plugin you are editing unless the task explicitly asks for repo-wide restructuring.

## Working Style

- Use Context7 to find relevant up-to-date docs when debugging, creating code, or verifying WordPress, WooCommerce, or other library usage.
- Use Chrome DevTools MCP and Playwright MCP to verify UI or browser-facing behavior when a runnable site is available.
- If a local WordPress site is not available, say so clearly instead of claiming full verification.
- Prefer direct, readable code over generic abstractions.
- Do not add backwards compatibility or fallback logic unless explicitly requested.
- Do not add defensive handling for unlikely alternate shapes just in case.
- Remove dead or unused code when you find it.
- Keep helpers small and files reasonably short. If a file grows too much, split it.
- If something is ambiguous and the repo does not answer it, ask instead of guessing.

## Code Style Rules

- Prefer readability to cleverness.
- Keep assignments direct.
- Avoid over-guarded normalization helpers.
- Use existing WordPress and WooCommerce conventions instead of inventing new layers.
- Prefer small settings pages or simple config structures over framework-style architecture.
- Preserve the plugin’s current behavior unless the task explicitly changes it.

Bad:

```php
const resolveNationalityLabel = (code?: string) => {
if (!code) return '';
const trimmed = code.trim();
const normalized = normalizeNationalityCode(trimmed);
return (
nationalityLookup.get(trimmed) ||
(normalized ? nationalityLookup.get(normalized) : undefined) ||
normalized ||
trimmed
);
};
```

Good:

```php
const resolveNationalityLabel = (code: string) =>
nationalityLookup.get(code) || code;
```

## Plugin-Specific Guidance

### `kt-auto-apply-url-coupon`

- Main behavior lives in [kt-auto-apply-url-coupon.php](/Users/edvintoome/Work/kt-woo-plugins/kt-auto-apply-url-coupon/kt-auto-apply-url-coupon.php).
- Admin settings live in [class-kt-auto-apply-url-coupon-settings.php](/Users/edvintoome/Work/kt-woo-plugins/kt-auto-apply-url-coupon/includes/class-kt-auto-apply-url-coupon-settings.php).
- Preserve the coupon lifecycle: capture, persist, apply on checkout, clear on completion/cart-empty.
- Keep default settings aligned with current production behavior unless explicitly changed.

### `kt-post-purchase-upsell`

- Main behavior lives in [kt-post-purchase-upsell.php](/Users/edvintoome/Work/kt-woo-plugins/kt-post-purchase-upsell/kt-post-purchase-upsell.php).
- Configuration is intentionally centralized in one JSON option.
- Preserve existing scheduling and unscheduling behavior unless the task explicitly changes business logic.
- When adjusting config shape, keep defaults and the rendered settings JSON in sync.

## Verification Expectations

When you change logic:

1. Read the affected plugin fully enough to understand the flow before editing.
2. Check current API usage against up-to-date docs with Context7.
3. If a runnable site exists, verify the change in-browser with Chrome DevTools MCP or Playwright MCP.
4. If CLI tools such as `php` are unavailable, say that explicitly in the final note.

## Documentation Expectations

- Update `README.md` when plugin behavior, setup, or settings change in a way a human operator should know about.
- Keep docs concrete. Prefer actual menu names, option names, URL examples, and flows over generic advice.
- Do not document infrastructure that does not exist in the repo.
