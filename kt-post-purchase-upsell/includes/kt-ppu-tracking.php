<?php

if (!defined('ABSPATH')) {
	exit;
}

define('KT_PPU_TRACKING_COOKIE', 'kt_ppu_attribution');
define('KT_PPU_TRACKING_COOKIE_LIFETIME', 2592000);

function kt_ppu_tracking_token_meta_key(int $step): string {
	return '_kt_ppu_email_token_step_' . $step;
}

function kt_ppu_tracking_opened_meta_key(int $step): string {
	return '_kt_ppu_email_opened_step_' . $step;
}

function kt_ppu_tracking_clicked_meta_key(int $step): string {
	return '_kt_ppu_email_clicked_step_' . $step;
}

function kt_ppu_get_or_create_tracking_token(WC_Order $order, int $step): string {
	$meta_key = kt_ppu_tracking_token_meta_key($step);
	$token    = (string) $order->get_meta($meta_key);

	if ($token !== '') {
		return $token;
	}

	$token = wp_generate_password(32, false, false);
	$order->update_meta_data($meta_key, $token);
	$order->save();

	return $token;
}

function kt_ppu_validate_tracking_source(int $source_order_id, int $step, string $token): ?WC_Order {
	if (!$source_order_id || !$step || $token === '') {
		return kt_ppu_find_tracking_source_order($source_order_id, $step);
	}

	$order = kt_ppu_find_tracking_source_order($source_order_id, $step);

	if (!$order instanceof WC_Order) {
		return null;
	}

	$saved_token = (string) $order->get_meta(kt_ppu_tracking_token_meta_key($step));

	if ($saved_token !== '' && hash_equals($saved_token, $token)) {
		return $order;
	}

	return $order;
}

function kt_ppu_find_tracking_source_order(int $source_order_id, int $step): ?WC_Order {
	$order = wc_get_order($source_order_id);

	if (!$order instanceof WC_Order) {
		return null;
	}

	$sent_meta  = (string) $order->get_meta('_kt_ppu_email_sent_step_' . $step);
	$token_meta = (string) $order->get_meta(kt_ppu_tracking_token_meta_key($step));

	if ($sent_meta === '' && $token_meta === '') {
		return null;
	}

	return $order;
}

function kt_ppu_build_tracked_offer_url(WC_Order $order, int $step, string $target_url): string {
	$token = kt_ppu_get_or_create_tracking_token($order, $step);

	return add_query_arg([
		'kt_ppu_click' => 1,
		'source'       => $order->get_id(),
		'step'         => $step,
		'token'        => $token,
		'target'       => $target_url,
	], home_url('/'));
}

function kt_ppu_build_open_pixel_url(WC_Order $order, int $step): string {
	$token = kt_ppu_get_or_create_tracking_token($order, $step);

	return add_query_arg([
		'kt_ppu_open' => 1,
		'source'      => $order->get_id(),
		'step'        => $step,
		'token'       => $token,
	], home_url('/'));
}

function kt_ppu_mark_tracking_event(WC_Order $order, string $meta_key): bool {
	if ((string) $order->get_meta($meta_key) !== '') {
		return false;
	}

	$order->update_meta_data($meta_key, current_time('mysql'));
	$order->save();

	return true;
}

function kt_ppu_set_tracking_cookie(int $source_order_id, int $step, string $token): void {
	$value  = implode('|', [$source_order_id, $step, $token]);
	$secure = is_ssl();

	setcookie(KT_PPU_TRACKING_COOKIE, $value, [
		'expires'  => time() + KT_PPU_TRACKING_COOKIE_LIFETIME,
		'path'     => COOKIEPATH ? COOKIEPATH : '/',
		'domain'   => COOKIE_DOMAIN,
		'secure'   => $secure,
		'httponly' => false,
		'samesite' => 'Lax',
	]);

	$_COOKIE[KT_PPU_TRACKING_COOKIE] = $value;
}

function kt_ppu_get_tracking_cookie(): array {
	if (empty($_COOKIE[KT_PPU_TRACKING_COOKIE])) {
		return [];
	}

	$raw   = sanitize_text_field(wp_unslash($_COOKIE[KT_PPU_TRACKING_COOKIE]));
	$parts = explode('|', $raw);

	if (count($parts) !== 3) {
		return [];
	}

	return [
		'source_order_id' => absint($parts[0]),
		'step'            => absint($parts[1]),
		'token'           => (string) $parts[2],
	];
}

function kt_ppu_clear_tracking_cookie(): void {
	$secure = is_ssl();

	setcookie(KT_PPU_TRACKING_COOKIE, '', [
		'expires'  => time() - HOUR_IN_SECONDS,
		'path'     => COOKIEPATH ? COOKIEPATH : '/',
		'domain'   => COOKIE_DOMAIN,
		'secure'   => $secure,
		'httponly' => false,
		'samesite' => 'Lax',
	]);

	unset($_COOKIE[KT_PPU_TRACKING_COOKIE]);
}

function kt_ppu_output_tracking_pixel(): void {
	nocache_headers();
	header('Content-Type: image/gif');
	echo base64_decode('R0lGODlhAQABAPAAAAAAAP///ywAAAAAAQABAAACAUwAOw==');
	exit;
}

function kt_ppu_handle_tracking_requests(): void {
	if (is_admin() && !wp_doing_ajax()) {
		return;
	}

	if (!empty($_GET['kt_ppu_open'])) {
		kt_ppu_handle_open_request();
	}

	if (!empty($_GET['kt_ppu_click'])) {
		kt_ppu_handle_click_request();
	}
}

function kt_ppu_handle_open_request(): void {
	$source_order_id = absint($_GET['source'] ?? 0);
	$step            = absint($_GET['step'] ?? 0);
	$token           = sanitize_text_field(wp_unslash($_GET['token'] ?? ''));

	$order           = kt_ppu_validate_tracking_source($source_order_id, $step, $token);

	if ($order instanceof WC_Order) {
		$tracked = kt_ppu_mark_tracking_event($order, kt_ppu_tracking_opened_meta_key($step));

		if ($tracked) {
			kt_ppu_insert_event('opened', $step, $source_order_id);
		}

		kt_ppu_log('Tracked email open', [
			'source_order_id' => $source_order_id,
			'step'            => $step,
			'tracked'         => $tracked,
		]);
	} else {
		kt_ppu_log('Open request ignored: no valid source order', [
			'source_order_id' => $source_order_id,
			'step'            => $step,
		]);
	}

	kt_ppu_output_tracking_pixel();
}

function kt_ppu_handle_click_request(): void {
	$source_order_id = absint($_GET['source'] ?? 0);
	$step            = absint($_GET['step'] ?? 0);
	$token           = sanitize_text_field(wp_unslash($_GET['token'] ?? ''));
	$target          = esc_url_raw(wp_unslash($_GET['target'] ?? ''));

	$order           = kt_ppu_validate_tracking_source($source_order_id, $step, $token);

	if ($order instanceof WC_Order) {
		$tracked = kt_ppu_mark_tracking_event($order, kt_ppu_tracking_clicked_meta_key($step));

		kt_ppu_set_tracking_cookie($source_order_id, $step, $token);

		if ($tracked) {
			kt_ppu_insert_event('clicked', $step, $source_order_id);
		}

		kt_ppu_log('Tracked email click', [
			'source_order_id' => $source_order_id,
			'step'            => $step,
			'target'          => $target,
			'tracked'         => $tracked,
		]);
	} else {
		kt_ppu_log('Click request ignored: no valid source order', [
			'source_order_id' => $source_order_id,
			'step'            => $step,
			'target'          => $target,
		]);
	}

	nocache_headers();
	wp_safe_redirect($target !== '' ? $target : home_url('/'));
	exit;
}

function kt_ppu_attach_attribution_to_checkout_order(WC_Order $order, array $data): void {
	$tracking = kt_ppu_get_tracking_cookie();

	if (empty($tracking)) {
		return;
	}

	$source_order = kt_ppu_validate_tracking_source(
		(int) $tracking['source_order_id'],
		(int) $tracking['step'],
		(string) $tracking['token']
	);

	if (!$source_order instanceof WC_Order) {
		kt_ppu_log('Attach attribution failed: source order invalid', [
			'order_id'         => $order->get_id(),
			'source_order_id'  => (int) $tracking['source_order_id'],
			'step'             => (int) $tracking['step'],
		]);
		kt_ppu_clear_tracking_cookie();
		return;
	}

	$billing_email = strtolower(sanitize_email($data['billing_email'] ?? ''));
	$source_email  = strtolower(sanitize_email($source_order->get_billing_email()));

	if ($billing_email === '' || $source_email === '' || $billing_email !== $source_email) {
		kt_ppu_log('Attach attribution skipped: billing email mismatch', [
			'order_id'         => $order->get_id(),
			'source_order_id'  => $source_order->get_id(),
			'billing_email'    => $billing_email,
			'source_email'     => $source_email,
		]);
		return;
	}

	$order->update_meta_data('_kt_ppu_attributed_source_order_id', $source_order->get_id());
	$order->update_meta_data('_kt_ppu_attributed_step', (int) $tracking['step']);
	$order->update_meta_data('_kt_ppu_attributed_token', (string) $tracking['token']);
	$order->update_meta_data('_kt_ppu_attributed_at', current_time('mysql'));

	kt_ppu_log('Attribution attached to checkout order', [
		'order_id'         => $order->get_id(),
		'source_order_id'  => $source_order->get_id(),
		'step'             => (int) $tracking['step'],
	]);
}

function kt_ppu_maybe_clear_tracking_cookie_after_checkout($order_id): void {
	$order = wc_get_order($order_id);

	if (!$order instanceof WC_Order) {
		return;
	}

	if ((int) $order->get_meta('_kt_ppu_attributed_source_order_id') <= 0) {
		return;
	}

	kt_ppu_clear_tracking_cookie();
}

function kt_ppu_record_completed_conversion($order_id): void {
	$order = wc_get_order($order_id);

	if (!$order instanceof WC_Order) {
		kt_ppu_log('Record conversion aborted: invalid completed order', [
			'order_id' => $order_id,
		]);
		return;
	}

	if ((string) $order->get_meta('_kt_ppu_conversion_recorded_at') !== '') {
		kt_ppu_log('Record conversion skipped: already recorded', [
			'order_id' => $order_id,
		]);
		return;
	}

	$source_order_id = (int) $order->get_meta('_kt_ppu_attributed_source_order_id');
	$step            = (int) $order->get_meta('_kt_ppu_attributed_step');

	if (!$source_order_id || !$step) {
		kt_ppu_log('Record conversion skipped: missing attribution meta', [
			'order_id'        => $order_id,
			'source_order_id' => $source_order_id,
			'step'            => $step,
		]);
		return;
	}

	$source_order = wc_get_order($source_order_id);

	if (!$source_order instanceof WC_Order) {
		kt_ppu_log('Record conversion skipped: source order invalid', [
			'order_id'        => $order_id,
			'source_order_id' => $source_order_id,
			'step'            => $step,
		]);
		return;
	}

	$source_email = strtolower(sanitize_email($source_order->get_billing_email()));
	$order_email  = strtolower(sanitize_email($order->get_billing_email()));

	if ($source_email === '' || $order_email === '' || $source_email !== $order_email) {
		kt_ppu_log('Record conversion skipped: email mismatch', [
			'order_id'        => $order_id,
			'source_order_id' => $source_order_id,
			'source_email'    => $source_email,
			'order_email'     => $order_email,
		]);
		return;
	}

	$order->update_meta_data('_kt_ppu_conversion_recorded_at', current_time('mysql'));
	$order->update_meta_data('_kt_ppu_conversion_value', (string) $order->get_total());
	$order->save();
	kt_ppu_insert_event('bought', $step, $source_order_id, (int) $order->get_id(), (float) $order->get_total());

	kt_ppu_log('Recorded attributed conversion', [
		'order_id'         => $order->get_id(),
		'source_order_id'  => $source_order_id,
		'step'             => $step,
		'conversion_value' => $order->get_total(),
	]);
}

add_action('template_redirect', 'kt_ppu_handle_tracking_requests', 0);
add_action('woocommerce_checkout_create_order', 'kt_ppu_attach_attribution_to_checkout_order', 10, 2);
add_action('woocommerce_thankyou', 'kt_ppu_maybe_clear_tracking_cookie_after_checkout', 10, 1);
add_action('woocommerce_order_status_completed', 'kt_ppu_record_completed_conversion', 20, 1);
