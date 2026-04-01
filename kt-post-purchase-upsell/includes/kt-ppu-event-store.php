<?php

if (!defined('ABSPATH')) {
	exit;
}

define('KT_PPU_DB_VERSION', '1.0.1');
define('KT_PPU_DB_VERSION_OPTION', 'kt_ppu_db_version');

function kt_ppu_event_table_name(): string {
	global $wpdb;

	return $wpdb->prefix . 'kt_ppu_events';
}

function kt_ppu_install_event_table(): void {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table_name      = kt_ppu_event_table_name();
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE {$table_name} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		created_at datetime NOT NULL,
		event_type varchar(20) NOT NULL,
		step tinyint(3) unsigned NOT NULL,
		source_order_id bigint(20) unsigned NOT NULL,
		target_order_id bigint(20) unsigned NULL,
		value decimal(18,2) NULL,
		PRIMARY KEY  (id),
		KEY created_at (created_at),
		KEY event_type_step_created_at (event_type, step, created_at),
		KEY source_event_lookup (source_order_id, event_type, step),
		KEY target_order_lookup (target_order_id, event_type)
	) {$charset_collate};";

	dbDelta($sql);

	$has_old_unique_index = $wpdb->get_var(
		$wpdb->prepare(
			"SHOW INDEX FROM {$table_name} WHERE Key_name = %s",
			'target_event_unique'
		)
	);

	if ($has_old_unique_index) {
		$wpdb->query("ALTER TABLE {$table_name} DROP INDEX target_event_unique");
	}

	update_option(KT_PPU_DB_VERSION_OPTION, KT_PPU_DB_VERSION, false);
}

function kt_ppu_maybe_install_event_table(): void {
	$installed_version = get_option(KT_PPU_DB_VERSION_OPTION, '');

	if ($installed_version === KT_PPU_DB_VERSION) {
		return;
	}

	kt_ppu_install_event_table();
}

function kt_ppu_insert_event(
	string $event_type,
	int $step,
	int $source_order_id,
	?int $target_order_id = null,
	?float $value = null
): bool {
	global $wpdb;

	if ($step < 1 || $step > 3) {
		kt_ppu_log('Skipped event insert due to invalid step', [
			'event_type'      => $event_type,
			'step'            => $step,
			'source_order_id' => $source_order_id,
		]);
		return false;
	}

	$inserted = $wpdb->insert(
		kt_ppu_event_table_name(),
		[
			'created_at'      => current_time('mysql'),
			'event_type'      => $event_type,
			'step'            => $step,
			'source_order_id' => $source_order_id,
			'target_order_id' => $target_order_id,
			'value'           => $value,
		],
		[
			'%s',
			'%s',
			'%d',
			'%d',
			is_null($target_order_id) ? null : '%d',
			is_null($value) ? null : '%f',
		]
	);

	if ($inserted !== false) {
		return true;
	}

	if (stripos($wpdb->last_error, 'Duplicate entry') !== false) {
		return false;
	}

	kt_ppu_log('Failed to insert tracking event', [
		'event_type'      => $event_type,
		'step'            => $step,
		'source_order_id' => $source_order_id,
		'target_order_id' => $target_order_id,
		'value'           => $value,
		'db_error'        => $wpdb->last_error,
	]);

	return false;
}

function kt_ppu_dashboard_event_rows(string $start_datetime): array {
	global $wpdb;

	$table_name = kt_ppu_event_table_name();
	$sql        = $wpdb->prepare(
		"SELECT step, event_type, COUNT(*) AS total_events, SUM(COALESCE(value, 0)) AS total_value
		FROM {$table_name}
		WHERE created_at >= %s
		GROUP BY step, event_type",
		$start_datetime
	);

	$rows = $wpdb->get_results($sql, ARRAY_A);

	return is_array($rows) ? $rows : [];
}
