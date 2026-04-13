# AGENTS.md

## Repo Overview

This repo contains small standalone WooCommerce plugins.

Current plugins:

- `kt-auto-apply-url-coupon` - plugin to auto-apply coupon when link is provided with specified param(such as ?coupon=MYCOUPON)
- `kt-post-purchase-upsell` - email sequence for post purchase upsells, includes dashboard and per-product config, and a beautiful coupon box in post-purchase email and post-purchase page
- `kt-review-images` - add images upload support to reviews and restructure reviews into masonry layout
- `kt-conditional-phone-and-admin-subject` - require billing phone only for physical carts, append order total to admin new-order email subjects (with physical marker for physical orders), and expose localized JSON strings in KT Plugins menu


## Working Style

- Deploy plugins using store admin plugin via wp_cli

## Verification Expectations

When you change logic:

1. Read the affected plugin fully enough to understand the flow before editing.
2. Check current API usage against up-to-date docs with Context7.
3. If a runnable site exists, verify the change in-browser with Chrome DevTools MCP.

## Documentation Expectations

- Update `README.md` and AGENTS.md when plugin behavior, setup, or settings change in a way a human operator should know about.
- Keep docs concrete. Prefer actual menu names, option names, URL examples, and flows over generic advice.
- Do not document infrastructure that does not exist in the repo.
