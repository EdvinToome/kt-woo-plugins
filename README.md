# KT Woo Plugins

Small WooCommerce-focused WordPress plugins for KT projects.

This repo currently contains two standalone plugins:

- `kt-auto-apply-url-coupon`
- `kt-post-purchase-upsell`

Each plugin is self-contained and can be copied into `wp-content/plugins/` on a WordPress site.

## Plugins

### `kt-auto-apply-url-coupon`

Captures a coupon code from a URL query parameter, keeps it in cookie/session storage, and applies it automatically on checkout.

What it does:

- Reads a configurable query parameter such as `?coupon=PRINT5`
- Stores the pending coupon for up to 72 hours
- Applies the coupon automatically on checkout
- Shows a badge on product pages when a valid coupon is present
- Clears coupon state after checkout or when the cart is emptied

Admin settings:

- `Settings > KT Auto Apply URL Coupon`
- Configurable query parameter name
- Configurable badge title
- Configurable badge message using `{coupon}` placeholder

Main files:

- [kt-auto-apply-url-coupon.php](/Users/edvintoome/Work/kt-woo-plugins/kt-auto-apply-url-coupon/kt-auto-apply-url-coupon.php)
- [class-kt-auto-apply-url-coupon-settings.php](/Users/edvintoome/Work/kt-woo-plugins/kt-auto-apply-url-coupon/includes/class-kt-auto-apply-url-coupon-settings.php)

### `kt-post-purchase-upsell`

Sends a three-step delayed upsell email sequence after a WooCommerce order is completed, based on configured trigger products.

What it does:

- Watches completed WooCommerce orders
- Matches purchased products against configured trigger products
- Schedules up to 3 delayed emails through Action Scheduler
- Builds email content from one JSON settings field
- Unschedules pending emails when orders are cancelled, refunded, or failed
- Includes admin test URLs for step-by-step email testing

Admin settings:

- `Settings > KT Upsell Emails`
- One JSON configuration field with defaults

Main file:

- [kt-post-purchase-upsell.php](/Users/edvintoome/Work/kt-woo-plugins/kt-post-purchase-upsell/kt-post-purchase-upsell.php)

## Requirements

- WordPress
- WooCommerce

Additional requirement for `kt-post-purchase-upsell`:

- Action Scheduler available in the site environment

## Install

1. Copy the plugin directory you want into `wp-content/plugins/`.
2. Activate it in WordPress admin.
3. Configure it under `Settings`.

## Development Notes

- This repo does not currently include a local WordPress environment, Docker setup, or automated test harness.
- Verification is expected to happen against a real WordPress + WooCommerce site.
- Keep each plugin isolated. Avoid cross-plugin abstractions unless there is a real shared need.
- Prefer direct WordPress and WooCommerce APIs over adding infrastructure.

## Manual Testing

### `kt-auto-apply-url-coupon`

1. Open a product page with a valid coupon query parameter.
2. Confirm the badge appears.
3. Add a product to cart and proceed to checkout.
4. Confirm the coupon is applied automatically.
5. Complete an order or empty the cart and confirm coupon state is cleared.

### `kt-post-purchase-upsell`

1. Configure trigger products and email content in the JSON settings page.
2. Place an order containing a trigger product.
3. Mark the order as `completed`.
4. Confirm scheduled actions are created for all enabled steps.
5. Run the admin test URLs if needed.
6. Cancel, refund, or fail an order and confirm pending actions are removed.

## Repo Structure

```text
kt-woo-plugins/
├── kt-auto-apply-url-coupon/
│   ├── includes/
│   └── kt-auto-apply-url-coupon.php
└── kt-post-purchase-upsell/
    └── kt-post-purchase-upsell.php
```
