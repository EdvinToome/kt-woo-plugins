<?php
/**
 * Plugin Name: KT Post-Purchase Upsell Emails
 * Description: Sends 3 delayed post-purchase upsell emails for specific WooCommerce products after order completion, configured from one JSON settings field.
 * Version: 2.0.0
 * Author: Edvin Toome
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * -------------------------------------------------------
 * CONSTANTS
 * -------------------------------------------------------
 */
define('KT_PPU_OPTION_JSON', 'kt_ppu_settings_json');
define('KT_PPU_LOG_SOURCE', 'kt-ppu');
define('KT_PPU_ACTION_HOOK', 'kt_ppu_send_scheduled_email');
define('KT_PPU_ACTION_GROUP', 'kt_post_purchase_upsell');

/**
 * -------------------------------------------------------
 * LOGGER
 * -------------------------------------------------------
 */
function kt_ppu_log($message, array $context = []): void {
	$line = $message;

	if (!empty($context)) {
		$line .= ' | ' . wp_json_encode($context);
	}

	if (function_exists('wc_get_logger')) {
		wc_get_logger()->debug($line, ['source' => KT_PPU_LOG_SOURCE]);
	} else {
		error_log('[KT PPU] ' . $line);
	}
}

/**
 * -------------------------------------------------------
 * DEFAULT CONFIG
 * -------------------------------------------------------
 */
function kt_ppu_default_config(): array {
	return [
		'from_name' => get_bloginfo('name'),
		'from_email' => get_option('admin_email'),
		'logo_url' => '',

		'colors' => [
			'outer_bg' => '#f7f7f7',
			'card_bg' => '#ffffff',
			'promo_bg' => '#e4f3fd',
			'promo_border' => '#56B0F2',
			'heading' => '#3c3c3c',
			'text' => '#4a5568',
			'muted' => '#6b7280',
			'button_bg' => '#56B0F2',
			'button_text' => '#ffffff',
			'coupon_bg' => '#ffffff',
			'coupon_border' => '#56B0F2',
			'coupon_text' => '#0a73c2',
		],

		'coupon_code' => 'PRINT5',
		'coupon_discount_text' => '5 €',
		'printed_category_url' => '',

		'delays' => [
			'step_1' => 60,
			'step_2' => 86400,
			'step_3' => 259200,
		],

		'subjects' => [
			'step_1' => 'Kas soovid ka trükitud versiooni?',
			'step_2' => 'Meeldetuletus: trükitud versioon sooduskoodiga',
			'step_3' => 'Viimane meeldetuletus trükitud versiooni soodustusele',
		],

		'headings' => [
			'step_1_single' => 'Kas soovid sellest komplektist ka trükitud versiooni?',
			'step_1_multi' => 'Kas soovid oma ostetud materjale ka trükitud kujul?',
			'step_2' => 'Meeldetuletus trükitud versiooni pakkumisest',
			'step_3' => 'Viimane võimalus kasutada sooduskoodi',
		],

		'texts' => [
			'greeting_prefix' => 'Tere',
			'intro_step_1' => 'Aitäh tellimuse eest!',
			'intro_step_2' => 'Tuletame meelde, et saad tellida ka trükitud versiooni.',
			'intro_step_3' => 'See on viimane meeldetuletus sinu trükitud versiooni pakkumise kohta.',
			'single_description' => 'Sul on digitaalne komplekt juba olemas – nüüd saad tellida ka sama materjali trükitud kujul. Kasuta allolevat sooduskoodi ja saad trükitud komplektilt {discount} alla.',
			'multi_description' => 'Ostsid mitu digitaalset toodet. Vaata trükitud materjalide valikut ja kasuta allolevat sooduskoodi, et saada trükitud toodetelt {discount} soodustust.',
			'small_note' => 'Kasuta koodi, et saada trükitud toodetelt soodustust.',
			'footer_note' => 'Aitäh, et kasutasid meie teenuseid!',
		],

		'buttons' => [
			'single' => 'Vaata trükitud komplekti',
			'multi' => 'Vaata trükitud materjale',
		],

		'trigger_products' => [],
	];
}

function kt_ppu_array_replace_recursive_distinct(array $base, array $replacements): array {
	foreach ($replacements as $key => $value) {
		if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
			$base[$key] = kt_ppu_array_replace_recursive_distinct($base[$key], $value);
		} else {
			$base[$key] = $value;
		}
	}
	return $base;
}

function kt_ppu_config(): array {
	$defaults = kt_ppu_default_config();
	$json     = get_option(KT_PPU_OPTION_JSON, '');

	if (!is_string($json) || trim($json) === '') {
		return $defaults;
	}

	$decoded = json_decode($json, true);

	if (!is_array($decoded)) {
		kt_ppu_log('Invalid settings JSON, using defaults');
		return $defaults;
	}

	return kt_ppu_array_replace_recursive_distinct($defaults, $decoded);
}

/**
 * -------------------------------------------------------
 * SETTINGS PAGE
 * -------------------------------------------------------
 */
add_action('admin_menu', function () {
	add_options_page(
		'KT Upsell Emails',
		'KT Upsell Emails',
		'manage_options',
		'kt-ppu-settings',
		'kt_ppu_render_settings_page'
	);
});

add_action('admin_init', function () {
	register_setting('kt_ppu_settings_group', KT_PPU_OPTION_JSON, [
		'type' => 'string',
		'sanitize_callback' => 'kt_ppu_sanitize_settings_json',
		'default' => '',
	]);
});

function kt_ppu_sanitize_settings_json($value): string {
	if (!is_string($value)) {
		add_settings_error(KT_PPU_OPTION_JSON, 'invalid_json', 'Settings JSON must be a string.');
		return get_option(KT_PPU_OPTION_JSON, '');
	}

	$value = trim($value);

	if ($value === '') {
		add_settings_error(KT_PPU_OPTION_JSON, 'json_saved', 'Settings cleared. Defaults will be used.', 'updated');
		return '';
	}

	$decoded = json_decode($value, true);

	if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
		add_settings_error(KT_PPU_OPTION_JSON, 'invalid_json', 'Invalid JSON: ' . json_last_error_msg());
		return get_option(KT_PPU_OPTION_JSON, '');
	}

	add_settings_error(KT_PPU_OPTION_JSON, 'json_saved', 'Settings saved.', 'updated');

	return wp_json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function kt_ppu_render_settings_page(): void {
	if (!current_user_can('manage_options')) {
		return;
	}

	$current = get_option(KT_PPU_OPTION_JSON, '');

	if (trim((string) $current) === '') {
		$current = wp_json_encode(
			kt_ppu_default_config(),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);
	}
	?>
	<div class="wrap">
		<h1>KT Post-Purchase Upsell Emails</h1>
		<p>Paste all plugin settings here as one JSON object.</p>

		<?php settings_errors(KT_PPU_OPTION_JSON); ?>

		<form method="post" action="options.php">
			<?php settings_fields('kt_ppu_settings_group'); ?>

			<textarea
				name="<?php echo esc_attr(KT_PPU_OPTION_JSON); ?>"
				rows="36"
				style="width:100%; font-family:monospace; font-size:13px;"
			><?php echo esc_textarea($current); ?></textarea>

			<?php submit_button('Save JSON Settings'); ?>
		</form>

		<hr>
		<h2>Useful test URLs</h2>
		<p>While logged in as admin:</p>
		<pre><?php echo esc_html(admin_url('?kt_ppu_test_order=123&kt_ppu_test_step=1')); ?></pre>
		<pre><?php echo esc_html(admin_url('?kt_ppu_test_order=123&kt_ppu_test_step=2')); ?></pre>
		<pre><?php echo esc_html(admin_url('?kt_ppu_test_order=123&kt_ppu_test_step=3')); ?></pre>
		<pre><?php echo esc_html(admin_url('?kt_ppu_reset_order=123')); ?></pre>
	</div>
	<?php
}

/**
 * -------------------------------------------------------
 * INIT / DEBUG
 * -------------------------------------------------------
 */
kt_ppu_log('Plugin file loaded');

add_action('init', function () {
	kt_ppu_log('Init fired', [
		'action_scheduler_single_exists' => function_exists('as_schedule_single_action'),
		'action_scheduler_has_exists'    => function_exists('as_has_scheduled_action'),
		'action_scheduler_uns_exists'    => function_exists('as_unschedule_all_actions'),
	]);
}, 20);

add_action('action_scheduler_init', function () {
	kt_ppu_log('Action Scheduler initialized');
});

add_action('woocommerce_order_status_changed', function ($order_id, $from, $to) {
	kt_ppu_log('Order status changed', [
		'order_id' => $order_id,
		'from'     => $from,
		'to'       => $to,
	]);
}, 10, 3);

/**
 * -------------------------------------------------------
 * HELPERS
 * -------------------------------------------------------
 */
function kt_ppu_cfg(array $config, string $path, $default = null) {
	$parts = explode('.', $path);
	$value = $config;

	foreach ($parts as $part) {
		if (!is_array($value) || !array_key_exists($part, $value)) {
			return $default;
		}
		$value = $value[$part];
	}

	return $value;
}

function kt_ppu_get_matching_products(WC_Order $order): array {
	$config  = kt_ppu_config();
	$map     = $config['trigger_products'] ?? [];
	$matches = [];

	kt_ppu_log('Checking order items for matches', [
		'order_id' => $order->get_id(),
		'items'    => count($order->get_items()),
	]);

	foreach ($order->get_items() as $item_id => $item) {
		$product_id   = (int) $item->get_product_id();
		$variation_id = (int) $item->get_variation_id();
		$name         = $item->get_name();

		kt_ppu_log('Inspecting order item', [
			'order_id'     => $order->get_id(),
			'item_id'      => $item_id,
			'product_id'   => $product_id,
			'variation_id' => $variation_id,
			'name'         => $name,
		]);

		$key = (string) $product_id;

		if (isset($map[$key]) && is_array($map[$key])) {
			$matches[$key] = $map[$key];

			kt_ppu_log('Matched trigger product', [
				'order_id'   => $order->get_id(),
				'product_id' => $product_id,
				'name'       => $map[$key]['name'] ?? '',
			]);
		}
	}

	kt_ppu_log('Finished matching products', [
		'order_id'    => $order->get_id(),
		'match_count' => count($matches),
		'matched_ids' => array_keys($matches),
	]);

	return $matches;
}

function kt_ppu_build_offer_data(WC_Order $order): ?array {
	$config  = kt_ppu_config();
	$matches = kt_ppu_get_matching_products($order);

	if (empty($matches)) {
		kt_ppu_log('No matching products found, no offer built', [
			'order_id' => $order->get_id(),
		]);
		return null;
	}

	$discount           = (string) ($config['coupon_discount_text'] ?? '');
	$single_description = str_replace('{discount}', $discount, (string) kt_ppu_cfg($config, 'texts.single_description', ''));
	$multi_description  = str_replace('{discount}', $discount, (string) kt_ppu_cfg($config, 'texts.multi_description', ''));

	if (count($matches) > 1) {
		return [
			'title'       => (string) kt_ppu_cfg($config, 'headings.step_1_multi', 'Kas soovid oma ostetud materjale ka trükitud kujul?'),
			'description' => $multi_description,
			'button_text' => (string) kt_ppu_cfg($config, 'buttons.multi', 'Vaata trükitud materjale'),
			'url'         => (string) ($config['printed_category_url'] ?? ''),
			'coupon_code' => (string) ($config['coupon_code'] ?? ''),
		];
	}

	$single = reset($matches);

	kt_ppu_log('Building single-product offer', [
		'order_id' => $order->get_id(),
		'url'      => $single['printed_url'] ?? '',
	]);

	return [
		'title'       => (string) kt_ppu_cfg($config, 'headings.step_1_single', 'Kas soovid sellest komplektist ka trükitud versiooni?'),
		'description' => $single_description,
		'button_text' => (string) kt_ppu_cfg($config, 'buttons.single', 'Vaata trükitud komplekti'),
		'url'         => (string) ($single['printed_url'] ?? ''),
		'coupon_code' => (string) ($config['coupon_code'] ?? ''),
	];
}

function kt_ppu_get_email_subject(int $step, WC_Order $order): string {
	$config = kt_ppu_config();
	return (string) kt_ppu_cfg($config, 'subjects.step_' . $step, 'Trükitud versiooni pakkumine');
}

function kt_ppu_get_email_heading(int $step, array $offer): string {
	$config = kt_ppu_config();

	if ($step === 1) {
		return (string) ($offer['title'] ?? '');
	}
	if ($step === 2) {
		return (string) kt_ppu_cfg($config, 'headings.step_2', $offer['title'] ?? '');
	}
	if ($step === 3) {
		return (string) kt_ppu_cfg($config, 'headings.step_3', $offer['title'] ?? '');
	}

	return (string) ($offer['title'] ?? '');
}

function kt_ppu_build_email_body(WC_Order $order, int $step, array $offer): string {
	$config        = kt_ppu_config();
	$colors        = $config['colors'] ?? [];
	$texts         = $config['texts'] ?? [];
	$images        = $config['images'] ?? [];
	$customer_name = trim((string) $order->get_billing_first_name());
	$greeting_base = (string) ($texts['greeting_prefix'] ?? 'Tere');

	$greeting = $customer_name ? $greeting_base . ', ' . esc_html($customer_name) . '!' : $greeting_base . '!';

	if ($step === 1) {
		$intro = (string) ($texts['intro_step_1'] ?? 'Aitäh tellimuse eest!');
	} elseif ($step === 2) {
		$intro = (string) ($texts['intro_step_2'] ?? 'Tuletame meelde, et saad tellida ka trükitud versiooni.');
	} else {
		$intro = (string) ($texts['intro_step_3'] ?? 'See on viimane meeldetuletus sinu trükitud versiooni pakkumise kohta.');
	}

	$promo_bg      = esc_attr($colors['promo_bg'] ?? '#e4f3fd');
	$promo_border  = esc_attr($colors['promo_border'] ?? '#56B0F2');
	$heading_color = esc_attr($colors['heading'] ?? '#3c3c3c');
	$text_color    = esc_attr($colors['text'] ?? '#4a5568');
	$muted_color   = esc_attr($colors['muted'] ?? '#6b7280');
	$button_bg     = esc_attr($colors['button_bg'] ?? '#56B0F2');
	$button_text   = esc_attr($colors['button_text'] ?? '#ffffff');
	$coupon_bg     = esc_attr($colors['coupon_bg'] ?? '#ffffff');
	$coupon_border = esc_attr($colors['coupon_border'] ?? '#56B0F2');
	$coupon_text   = esc_attr($colors['coupon_text'] ?? '#0a73c2');
	$small_note    = (string) ($texts['small_note'] ?? 'Kasuta koodi, et saada trükitud toodetelt soodustust.');

	$image_url = '';
	if (!empty($images['step_' . $step]) && is_string($images['step_' . $step])) {
		$image_url = $images['step_' . $step];
	}

	$image_html = '';
	if ($image_url !== '') {
		$image_html = '
			<div style="margin:0 0 18px 0;">
				<img
					src="' . esc_url($image_url) . '"
					alt="Trükitud õppematerjalide komplekt"
					style="display:block;width:100%;max-width:420px;height:auto;margin:0 auto;border:0;border-radius:14px;"
				/>
			</div>';
	}

	return '
		<p>' . $greeting . '</p>
		<p>' . esc_html($intro) . '</p>

		' . $image_html . '
		<div style="margin:24px 0;padding:28px 22px;border:1px solid ' . $promo_border . ';background:' . $promo_bg . ';border-radius:16px;text-align:center;">

			<h2 style="margin:0 0 14px;font-size:24px;line-height:1.35;color:' . $heading_color . ';">' . esc_html((string) ($offer['title'] ?? '')) . '</h2>

			<p style="margin:0 0 18px;color:' . $text_color . ';font-size:15px;line-height:1.7;">
				' . esc_html((string) ($offer['description'] ?? '')) . '
			</p>

			<div style="margin:0 0 20px;">
				<div style="display:inline-block;padding:14px 18px;background:' . $coupon_bg . ';border:2px dashed ' . $coupon_border . ';border-radius:10px;font-size:22px;font-weight:700;letter-spacing:1px;color:' . $coupon_text . ';">
					' . esc_html((string) ($offer['coupon_code'] ?? '')) . '
				</div>
			</div>

			<table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" style="margin:0 auto 14px auto;">
				<tr>
					<td align="center" bgcolor="' . $button_bg . '" style="border-radius:8px;">
						<a href="' . esc_url((string) ($offer['url'] ?? '')) . '" style="display:inline-block;background:' . $button_bg . ';color:' . $button_text . ';text-decoration:none;padding:14px 24px;border-radius:8px;font-weight:600;font-size:15px;line-height:18px;mso-line-height-rule:exactly;">' . esc_html((string) ($offer['button_text'] ?? '')) . '</a>
					</td>
				</tr>
			</table>

			<p style="margin:0;font-size:13px;line-height:1.5;color:' . $muted_color . ';">
				' . esc_html($small_note) . '
			</p>
		</div>
	';
}

function kt_ppu_wrap_wc_email(string $heading, string $content): string {
	$config      = kt_ppu_config();
	$colors      = $config['colors'] ?? [];
	$outer_bg    = esc_attr($colors['outer_bg'] ?? '#f7f7f7');
	$card_bg     = esc_attr($colors['card_bg'] ?? '#ffffff');
	$heading_col = esc_attr($colors['heading'] ?? '#3c3c3c');
	$muted_col   = esc_attr($colors['muted'] ?? '#6b7280');
	$logo_url    = trim((string) ($config['logo_url'] ?? ''));
	$footer_note = (string) kt_ppu_cfg($config, 'texts.footer_note', 'Aitäh, et kasutasid meie teenuseid!');

	$logo_html = '';
	if ($logo_url !== '') {
		$logo_html = '
					<tr>
						<td align="center" style="padding:36px 36px 12px 36px;">
							<img src="' . esc_url($logo_url) . '" alt="" style="max-width:200px;width:100%;height:auto;border:0;display:block;margin:0 auto;" />
						</td>
					</tr>';
	}

	return '
<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title>' . esc_html($heading) . '</title>
</head>
<body bgcolor="' . $outer_bg . '" style="margin:0; padding:0; background-color:' . $outer_bg . ';">
	<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" bgcolor="' . $outer_bg . '" style="background-color:' . $outer_bg . '; margin:0; padding:0; width:100%;">
		<tr>
			<td align="center" valign="top" style="padding:40px 20px;">
				<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="600" style="width:100%; max-width:600px; background-color:' . $card_bg . '; border-radius:0; overflow:hidden;">
					' . $logo_html . '
					<tr>
						<td style="padding:0 36px 10px 36px; font-family:Arial,sans-serif; font-size:34px; line-height:1.3; font-weight:700; color:' . $heading_col . '; background-color:' . $card_bg . ';">
							' . esc_html($heading) . '
						</td>
					</tr>
					<tr>
						<td style="padding:0 36px 40px 36px; font-family:Arial,sans-serif; font-size:16px; line-height:1.7; color:' . $heading_col . '; background-color:' . $card_bg . ';">
							' . $content . '
						</td>
					</tr>
					<tr>
						<td style="padding:0 36px 36px 36px; font-family:Arial,sans-serif; font-size:14px; line-height:1.5; color:' . $muted_col . '; background-color:' . $card_bg . ';">
							' . esc_html($footer_note) . '
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>';
}

function kt_ppu_get_delay_for_step(int $step): int {
	$config = kt_ppu_config();
	$key    = 'step_' . $step;
	$delay  = (int) kt_ppu_cfg($config, 'delays.' . $key, 0);

	return max(0, $delay);
}

/**
 * -------------------------------------------------------
 * SEND EMAIL
 * -------------------------------------------------------
 */
function kt_ppu_send_email(WC_Order $order, int $step): void {
	kt_ppu_log('Preparing to send email', [
		'order_id' => $order->get_id(),
		'step'     => $step,
		'status'   => $order->get_status(),
	]);

	$offer = kt_ppu_build_offer_data($order);

	if (!$offer) {
		kt_ppu_log('Email aborted: no offer data', [
			'order_id' => $order->get_id(),
			'step'     => $step,
		]);
		return;
	}

	$to      = $order->get_billing_email();
	$subject = kt_ppu_get_email_subject($step, $order);
	$heading = kt_ppu_get_email_heading($step, $offer);
	$body    = kt_ppu_build_email_body($order, $step, $offer);
	$html    = kt_ppu_wrap_wc_email($heading, $body);

	$config = kt_ppu_config();

	$from_filter = function () use ($config) {
		return (string) ($config['from_email'] ?? '');
	};

	$from_name_filter = function () use ($config) {
		return (string) ($config['from_name'] ?? '');
	};

	$content_type_filter = function () {
		return 'text/html';
	};

	add_filter('wp_mail_from', $from_filter);
	add_filter('wp_mail_from_name', $from_name_filter);
	add_filter('wp_mail_content_type', $content_type_filter);

	kt_ppu_log('Calling wp_mail', [
		'order_id' => $order->get_id(),
		'step'     => $step,
		'to'       => $to,
		'subject'  => $subject,
	]);

	$result = wp_mail($to, $subject, $html);

	kt_ppu_log('wp_mail finished', [
		'order_id' => $order->get_id(),
		'step'     => $step,
		'result'   => (bool) $result,
	]);

	remove_filter('wp_mail_from', $from_filter);
	remove_filter('wp_mail_from_name', $from_name_filter);
	remove_filter('wp_mail_content_type', $content_type_filter);
}

/**
 * -------------------------------------------------------
 * SCHEDULING
 * -------------------------------------------------------
 */
add_action('woocommerce_order_status_completed', function ($order_id) {
	kt_ppu_log('Completed hook fired', [
		'order_id' => $order_id,
	]);

	$order = wc_get_order($order_id);

	if (!$order instanceof WC_Order) {
		kt_ppu_log('Completed hook aborted: invalid order object', [
			'order_id' => $order_id,
		]);
		return;
	}

	$offer = kt_ppu_build_offer_data($order);
	if (!$offer) {
		kt_ppu_log('Completed hook aborted: no matching products / no offer', [
			'order_id' => $order_id,
		]);
		return;
	}

	if (!function_exists('as_has_scheduled_action') || !function_exists('as_schedule_single_action')) {
		kt_ppu_log('Completed hook aborted: Action Scheduler functions missing', [
			'order_id' => $order_id,
			'as_has_scheduled_action' => function_exists('as_has_scheduled_action'),
			'as_schedule_single_action' => function_exists('as_schedule_single_action'),
		]);
		return;
	}

	$args1 = ['order_id' => (int) $order_id, 'step' => 1];
	$args2 = ['order_id' => (int) $order_id, 'step' => 2];
	$args3 = ['order_id' => (int) $order_id, 'step' => 3];

	$already_1 = as_has_scheduled_action(KT_PPU_ACTION_HOOK, $args1, KT_PPU_ACTION_GROUP);
	$already_2 = as_has_scheduled_action(KT_PPU_ACTION_HOOK, $args2, KT_PPU_ACTION_GROUP);
	$already_3 = as_has_scheduled_action(KT_PPU_ACTION_HOOK, $args3, KT_PPU_ACTION_GROUP);

	kt_ppu_log('Existing scheduled action check', [
		'order_id'     => $order_id,
		'step1_exists' => $already_1,
		'step2_exists' => $already_2,
		'step3_exists' => $already_3,
	]);

	if (!$already_1) {
		$action_id_1 = as_schedule_single_action(
			time() + kt_ppu_get_delay_for_step(1),
			KT_PPU_ACTION_HOOK,
			$args1,
			KT_PPU_ACTION_GROUP,
			false
		);

		kt_ppu_log('Step 1 schedule attempt finished', [
			'order_id'  => $order_id,
			'action_id' => $action_id_1,
			'args'      => $args1,
		]);
	}

	if (!$already_2) {
		$action_id_2 = as_schedule_single_action(
			time() + kt_ppu_get_delay_for_step(2),
			KT_PPU_ACTION_HOOK,
			$args2,
			KT_PPU_ACTION_GROUP,
			false
		);

		kt_ppu_log('Step 2 schedule attempt finished', [
			'order_id'  => $order_id,
			'action_id' => $action_id_2,
			'args'      => $args2,
		]);
	}

	if (!$already_3) {
		$action_id_3 = as_schedule_single_action(
			time() + kt_ppu_get_delay_for_step(3),
			KT_PPU_ACTION_HOOK,
			$args3,
			KT_PPU_ACTION_GROUP,
			false
		);

		kt_ppu_log('Step 3 schedule attempt finished', [
			'order_id'  => $order_id,
			'action_id' => $action_id_3,
			'args'      => $args3,
		]);
	}
}, 10, 1);

/**
 * -------------------------------------------------------
 * SCHEDULED CALLBACK
 * -------------------------------------------------------
 */
add_action(KT_PPU_ACTION_HOOK, function ($order_id, $step) {
	kt_ppu_log('Scheduled callback fired', [
		'order_id' => $order_id,
		'step'     => $step,
	]);

	$order = wc_get_order($order_id);

	if (!$order instanceof WC_Order) {
		kt_ppu_log('Scheduled callback aborted: invalid order', [
			'order_id' => $order_id,
			'step'     => $step,
		]);
		return;
	}

	if (!$order->has_status('completed')) {
		kt_ppu_log('Scheduled callback aborted: order not completed', [
			'order_id' => $order_id,
			'step'     => $step,
			'status'   => $order->get_status(),
		]);
		return;
	}

	if (!kt_ppu_build_offer_data($order)) {
		kt_ppu_log('Scheduled callback aborted: no offer data', [
			'order_id' => $order_id,
			'step'     => $step,
		]);
		return;
	}

	$meta_key = '_kt_ppu_email_sent_step_' . absint($step);

	if ($order->get_meta($meta_key)) {
		kt_ppu_log('Scheduled callback aborted: step already sent', [
			'order_id' => $order_id,
			'step'     => $step,
			'meta_key' => $meta_key,
			'meta_val' => $order->get_meta($meta_key),
		]);
		return;
	}

	kt_ppu_send_email($order, (int) $step);

	$order->update_meta_data($meta_key, current_time('mysql'));
	$order->save();

	kt_ppu_log('Scheduled callback finished, meta saved', [
		'order_id' => $order_id,
		'step'     => $step,
		'meta_key' => $meta_key,
	]);
}, 10, 2);

/**
 * -------------------------------------------------------
 * UNSCHEDULE ON BAD STATUSES
 * -------------------------------------------------------
 */
function kt_ppu_unschedule_order_emails($order_id): void {
	kt_ppu_log('Unschedule requested', [
		'order_id' => $order_id,
	]);

	if (!function_exists('as_unschedule_all_actions')) {
		kt_ppu_log('Unschedule aborted: Action Scheduler unschedule function missing', [
			'order_id' => $order_id,
		]);
		return;
	}

	as_unschedule_all_actions(KT_PPU_ACTION_HOOK, [
		'order_id' => (int) $order_id,
		'step'     => 1,
	], KT_PPU_ACTION_GROUP);

	as_unschedule_all_actions(KT_PPU_ACTION_HOOK, [
		'order_id' => (int) $order_id,
		'step'     => 2,
	], KT_PPU_ACTION_GROUP);

	as_unschedule_all_actions(KT_PPU_ACTION_HOOK, [
		'order_id' => (int) $order_id,
		'step'     => 3,
	], KT_PPU_ACTION_GROUP);

	kt_ppu_log('Unschedule completed', [
		'order_id' => $order_id,
	]);
}

add_action('woocommerce_order_status_cancelled', 'kt_ppu_unschedule_order_emails');
add_action('woocommerce_order_status_refunded', 'kt_ppu_unschedule_order_emails');
add_action('woocommerce_order_status_failed', 'kt_ppu_unschedule_order_emails');

/**
 * -------------------------------------------------------
 * MANUAL TEST EMAIL
 * -------------------------------------------------------
 */
add_action('admin_init', function () {
	if (!current_user_can('manage_woocommerce')) {
		return;
	}

	if (empty($_GET['kt_ppu_test_order']) || empty($_GET['kt_ppu_test_step'])) {
		return;
	}

	$order_id = absint($_GET['kt_ppu_test_order']);
	$step     = absint($_GET['kt_ppu_test_step']);

	if (!$order_id || !$step) {
		return;
	}

	$order = wc_get_order($order_id);

	if (!$order instanceof WC_Order) {
		wp_die('Invalid order');
	}

	kt_ppu_log('Manual test email trigger', [
		'order_id' => $order_id,
		'step'     => $step,
	]);

	kt_ppu_send_email($order, $step);

	wp_die('Test email sent for order #' . $order_id . ', step ' . $step);
});

/**
 * -------------------------------------------------------
 * RESET SENT META FLAGS
 * -------------------------------------------------------
 */
add_action('admin_init', function () {
	if (!current_user_can('manage_woocommerce')) {
		return;
	}

	if (empty($_GET['kt_ppu_reset_order'])) {
		return;
	}

	$order_id = absint($_GET['kt_ppu_reset_order']);
	$order    = wc_get_order($order_id);

	if (!$order instanceof WC_Order) {
		wp_die('Invalid order');
	}

	$order->delete_meta_data('_kt_ppu_email_sent_step_1');
	$order->delete_meta_data('_kt_ppu_email_sent_step_2');
	$order->delete_meta_data('_kt_ppu_email_sent_step_3');
	$order->save();

	kt_ppu_log('Reset sent flags', [
		'order_id' => $order_id,
	]);

	wp_die('Reset email sent flags for order #' . $order_id);
});

/**
 * -------------------------------------------------------
 * EXTRA DEBUG
 * -------------------------------------------------------
 */
add_action('action_scheduler_failed_execution', function ($action_id, $exception) {
	kt_ppu_log('Action Scheduler failed execution', [
		'action_id' => $action_id,
		'error'     => is_object($exception) ? $exception->getMessage() : 'unknown',
	]);
}, 10, 2);

add_action('wp_mail_failed', function ($wp_error) {
	kt_ppu_log('wp_mail_failed', [
		'message' => $wp_error->get_error_message(),
		'data'    => $wp_error->get_error_data(),
	]);
});
