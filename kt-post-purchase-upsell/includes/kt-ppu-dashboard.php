<?php

if (!defined('ABSPATH')) {
	exit;
}

function kt_ppu_dashboard_ranges(): array {
	return [
		'day'   => 'Last day',
		'week'  => 'Last week',
		'month' => 'Last month',
		'year'  => 'Last year',
	];
}

function kt_ppu_dashboard_range(): string {
	$range = sanitize_key($_GET['kt_ppu_range'] ?? 'month');

	return array_key_exists($range, kt_ppu_dashboard_ranges()) ? $range : 'month';
}

function kt_ppu_dashboard_start_timestamp(string $range): int {
	$now = current_time('timestamp');

	if ($range === 'day') {
		return $now - DAY_IN_SECONDS;
	}

	if ($range === 'week') {
		return $now - WEEK_IN_SECONDS;
	}

	if ($range === 'year') {
		return $now - YEAR_IN_SECONDS;
	}

	return $now - MONTH_IN_SECONDS;
}

function kt_ppu_dashboard_start_datetime(string $range): string {
	return wp_date('Y-m-d H:i:s', kt_ppu_dashboard_start_timestamp($range), wp_timezone());
}

function kt_ppu_dashboard_stats(string $range): array {
	$rows            = kt_ppu_dashboard_event_rows(kt_ppu_dashboard_start_datetime($range));
	$stats           = [
		1 => [
			'label'   => 'Email 1',
			'sent'    => 0,
			'opened'  => 0,
			'clicked' => 0,
			'bought'  => 0,
			'revenue' => 0.0,
		],
		2 => [
			'label'   => 'Email 2',
			'sent'    => 0,
			'opened'  => 0,
			'clicked' => 0,
			'bought'  => 0,
			'revenue' => 0.0,
		],
		3 => [
			'label'   => 'Email 3',
			'sent'    => 0,
			'opened'  => 0,
			'clicked' => 0,
			'bought'  => 0,
			'revenue' => 0.0,
		],
	];

	foreach ($rows as $row) {
		$step       = (int) ($row['step'] ?? 0);
		$event_type = (string) ($row['event_type'] ?? '');

		if ($step < 1 || $step > 3 || !isset($stats[$step])) {
			continue;
		}

		if ($event_type === 'bought') {
			$stats[$step]['bought'] = (int) ($row['total_events'] ?? 0);
			$stats[$step]['revenue'] = (float) ($row['total_value'] ?? 0);
		}

		if ($event_type === 'sent' || $event_type === 'opened' || $event_type === 'clicked') {
			$stats[$step][$event_type] = (int) ($row['total_events'] ?? 0);
		}
	}

	$stats['all'] = [
		'label'   => 'All',
		'sent'    => 0,
		'opened'  => 0,
		'clicked' => 0,
		'bought'  => 0,
		'revenue' => 0.0,
	];

	for ($step = 1; $step <= 3; $step++) {
		$stats['all']['sent'] += $stats[$step]['sent'];
		$stats['all']['opened'] += $stats[$step]['opened'];
		$stats['all']['clicked'] += $stats[$step]['clicked'];
		$stats['all']['bought'] += $stats[$step]['bought'];
		$stats['all']['revenue'] += $stats[$step]['revenue'];
	}

	return $stats;
}

function kt_ppu_render_dashboard(): void {
	$range     = kt_ppu_dashboard_range();
	$ranges    = kt_ppu_dashboard_ranges();
	$stats     = kt_ppu_dashboard_stats($range);
	$base_url  = add_query_arg(['page' => 'kt-ppu-settings'], admin_url('options-general.php'));
	?>
	<h2>Tracking Dashboard</h2>
	<p>Attributed purchases are counted when a customer clicks a tracked upsell email link and then places a checkout order in the same browser before the tracking cookie expires.</p>

	<p>
		<?php foreach ($ranges as $key => $label) : ?>
			<?php if ($key === $range) : ?>
				<strong><?php echo esc_html($label); ?></strong>
			<?php else : ?>
				<a href="<?php echo esc_url(add_query_arg(['kt_ppu_range' => $key], $base_url)); ?>"><?php echo esc_html($label); ?></a>
			<?php endif; ?>
			&nbsp;
		<?php endforeach; ?>
	</p>

	<table class="widefat striped" style="max-width:720px; margin:0 0 24px 0;">
		<thead>
			<tr>
				<th>Email</th>
				<th>Sent</th>
				<th>Opened</th>
				<th>Clicked</th>
				<th>Bought</th>
				<th>Purchase value</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ([1, 2, 3, 'all'] as $key) : ?>
				<tr>
					<td>
						<?php if ($key === 'all') : ?>
							<strong><?php echo esc_html($stats[$key]['label']); ?></strong>
						<?php else : ?>
							<?php echo esc_html($stats[$key]['label']); ?>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html((string) $stats[$key]['sent']); ?></td>
					<td><?php echo esc_html((string) $stats[$key]['opened']); ?></td>
					<td><?php echo esc_html((string) $stats[$key]['clicked']); ?></td>
					<td><?php echo esc_html((string) $stats[$key]['bought']); ?></td>
					<td><?php echo wp_kses_post(html_entity_decode(wc_price($stats[$key]['revenue']))); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}
