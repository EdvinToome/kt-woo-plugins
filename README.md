# KT Woo Plugins

Small WooCommerce-focused WordPress plugins for KT projects.

This repo currently contains four standalone plugins:

- `kt-auto-apply-url-coupon`
- `kt-post-purchase-upsell`
- `kt-review-images`
- `kt-conditional-phone-and-admin-subject`

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

- `KT Plugins > Auto Apply URL Coupon`
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
- Shows a printed-product coupon box on the thank you page for qualifying orders
- Shows the same printed-product coupon box in WooCommerce customer processing and completed emails
- Schedules up to 3 delayed emails through Action Scheduler
- Tracks sent, opened, clicked, bought, and purchase value metrics
- Stores tracking events in a dedicated WordPress database table for fast dashboard reads
- Builds delayed emails and the thank you/native-email coupon box from one JSON settings field
- Unschedules pending emails when orders are cancelled, refunded, or failed
- Includes admin test URLs for step-by-step email testing

Admin settings:

- `KT Plugins > Upsell Emails`
- Built-in tracking dashboard with `day / week / month / year` filters
- One JSON configuration field with defaults for coupon settings, trigger products, delayed email content, and the thank you/native-email coupon box

Tracking note:

- Dashboard stats are based on new tracking events written after deployment.
- Existing historical order meta is not backfilled automatically.

Main file:

- [kt-post-purchase-upsell.php](/Users/edvintoome/Work/kt-woo-plugins/kt-post-purchase-upsell/kt-post-purchase-upsell.php)

### `kt-review-images`

Replaces the default WooCommerce single-product review section with a custom review block that supports one optional image per review and shows image reviews first.

What it does:

- Replaces the product review section on WooCommerce single product pages
- Adds one optional image upload field to the review form
- Adds image selection on the WordPress admin review edit screen
- Saves the uploaded review image as a WordPress attachment linked through comment meta
- Sorts reviews with images above text-only reviews
- Keeps older reviews without images visible in the same layout
- Matches the review section styling to the current Kontrolltoo storefront palette
- Lets you edit the storefront Estonian review strings from a settings page
- Includes a one-off admin tool to copy reviews from one product to another

Main files:

- [kt-review-images.php](/Users/edvintoome/Work/kt-woo-plugins/kt-review-images/kt-review-images.php)
- [class-kt-review-images-settings.php](/Users/edvintoome/Work/kt-woo-plugins/kt-review-images/includes/class-kt-review-images-settings.php)
- [class-kt-review-images-frontend.php](/Users/edvintoome/Work/kt-woo-plugins/kt-review-images/includes/class-kt-review-images-frontend.php)
- [single-product-reviews.php](/Users/edvintoome/Work/kt-woo-plugins/kt-review-images/templates/single-product-reviews.php)

Admin settings:

- `KT Plugins > Review Images`
- One JSON field for the storefront review strings, so the same config can be copied between sites
- Defaults reuse WooCommerce/core strings where they already exist, with custom plugin-only strings added on top
- One review copy tool with source and target product IDs

### `kt-conditional-phone-and-admin-subject`

Makes WooCommerce checkout phone requirement conditional and updates only admin new-order email subjects.

What it does:

- Sets `billing_phone` as required when the cart has physical products (`needs_shipping() === true`)
- Sets `billing_phone` as optional when the cart is virtual-only
- Appends the order total to every admin `New order` email subject
- Appends `Physical` to admin `New order` email subject when the order includes physical products
- Does not change customer email subjects

Admin settings:

- `KT Plugins > Conditional Phone`
- One JSON settings field: `kt_conditional_phone_admin_subject_strings_json`
- JSON keys:
  - `phone_required_error`
  - `admin_new_order_physical_marker`
  - `admin_subject_separator`

Main files:

- [kt-conditional-phone-and-admin-subject.php](/Users/edvintoome/Work/kt-woo-plugins/kt-conditional-phone-and-admin-subject/kt-conditional-phone-and-admin-subject.php)
- [class-kt-conditional-phone-and-admin-subject.php](/Users/edvintoome/Work/kt-woo-plugins/kt-conditional-phone-and-admin-subject/includes/class-kt-conditional-phone-and-admin-subject.php)
- [class-kt-conditional-phone-and-admin-subject-settings.php](/Users/edvintoome/Work/kt-woo-plugins/kt-conditional-phone-and-admin-subject/includes/class-kt-conditional-phone-and-admin-subject-settings.php)

## Requirements

- WordPress
- WooCommerce

Additional requirement for `kt-post-purchase-upsell`:

- Action Scheduler available in the site environment

## Install

1. Copy the plugin directory you want into `wp-content/plugins/`.
2. Activate it in WordPress admin.
3. Configure it under `KT Plugins`.

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
3. Confirm the thank you page shows the coupon box and links to the matching `printed_url` or the shared printed category URL when multiple trigger products were bought.
4. Confirm the same coupon box appears in the WooCommerce customer processing or completed email.
5. Mark the order as `completed`.
6. Confirm scheduled actions are created for all enabled steps.
7. Open a received upsell email and confirm opens are tracked.
8. Click the upsell CTA and confirm clicks are tracked.
9. Complete a follow-up purchase from the same browser session and confirm bought count and revenue update.
10. Run the admin test URLs if needed.
11. Cancel, refund, or fail an order and confirm pending actions are removed.

### `kt-review-images`

1. Copy `kt-review-images` into `wp-content/plugins/` and activate it.
2. Open a WooCommerce product page with existing reviews.
3. Confirm the old review list is replaced with the custom review section.
4. Submit a review without an image and confirm it appears in the review cards below image reviews.
5. Submit a review with one image and confirm the image appears in the gallery and in the review card.
6. Click a review image and confirm the lightbox opens and closes correctly.
7. Open a product review from `Comments` in WordPress admin, select an image in the `Review image` box, save, and confirm it appears on the storefront.
8. Open `KT Plugins > Review Images`, change one of the Estonian strings, save, and confirm it updates on the storefront.
9. Open `KT Plugins > Review Images`, copy reviews from one product ID to another, and confirm the target product shows the duplicated reviews, ratings, and review images.
10. Check the review section on desktop and mobile widths.

### `kt-conditional-phone-and-admin-subject`

1. Activate `kt-conditional-phone-and-admin-subject`.
2. Add one physical product to cart and open checkout.
3. Confirm `Phone` is required and rendered with the same required marker (`*`) as other mandatory fields.
4. Place the order and confirm admin new-order email subject includes `Physical` and the order total.
5. Add only virtual products to cart and open checkout.
6. Confirm `Phone` is optional.
7. Place the order and confirm admin new-order email subject does not include the physical marker.
8. Confirm customer email subject remains unchanged for both order types.

## Repo Structure

```text
kt-woo-plugins/
├── kt-auto-apply-url-coupon/
│   ├── includes/
│   └── kt-auto-apply-url-coupon.php
├── kt-review-images/
│   ├── assets/
│   ├── includes/
│   ├── templates/
│   └── kt-review-images.php
├── kt-conditional-phone-and-admin-subject/
│   ├── includes/
│   └── kt-conditional-phone-and-admin-subject.php
└── kt-post-purchase-upsell/
    ├── includes/
    └── kt-post-purchase-upsell.php
```
