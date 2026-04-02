<?php

if (!defined('ABSPATH')) {
	exit;
}

final class KT_Review_Images_Settings
{
	const OPTION_NAME = 'kt_review_images_strings_json';
	const SETTINGS_GROUP = 'kt_review_images_settings_group';
	const MENU_SLUG = 'kt-review-images-settings';
	const COPY_ACTION = 'kt_review_images_copy_reviews';
	const COPY_NONCE_ACTION = 'kt_review_images_copy_reviews';

	private static $instance = null;

	public static function get_instance(): self
	{
		if (self::$instance === null) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public static function all(): array
	{
		$json = get_option(self::OPTION_NAME, '');

		if (!is_string($json) || trim($json) === '') {
			return self::defaults();
		}

		$values = json_decode($json, true);

		if (!is_array($values)) {
			return self::defaults();
		}

		return array_replace(self::defaults(), $values);
	}

	public static function get(string $key): string
	{
		$values = self::all();
		return $values[$key];
	}

	public static function defaults(): array
	{
		return [
			'empty_reviews' => __('There are no reviews yet.', 'woocommerce'),
			'review_restricted' => __('Only logged in customers who have purchased this product may leave a review.', 'woocommerce'),
			'lightbox_label' => 'Arvustuse pildi eelvaade',
			'close_label' => __('Close'),
			'comment_label' => __('Your review', 'woocommerce'),
			'name_label' => __('Name', 'woocommerce'),
			'email_label' => __('Email', 'woocommerce'),
			'photo_label' => 'Lisa foto',
			'optional_label' => 'Valikuline',
			'rating_label' => __('Your rating', 'woocommerce'),
			'choose_rating_label' => 'Vali hinnang',
			'rating_5_label' => '5 tärni',
			'rating_4_label' => '4 tärni',
			'rating_3_label' => '3 tärni',
			'rating_2_label' => '2 tärni',
			'rating_1_label' => '1 tärn',
			'upload_help' => 'Sobib telefoni- või ekraanipilt lapse tehtud tööst.',
			'title_reply' => __('Add a review', 'woocommerce'),
			'title_reply_to' => __('Leave a Reply to %s'),
			'label_submit' => __('Submit', 'woocommerce'),
			'comment_notes_before' => 'Sinu e-postiaadressi ei avaldata. Nõutavad väljad on tähistatud *-ga.',
			'tab_rating_aria_prefix' => 'Hinnatud',
		];
	}

	private function __construct()
	{
		add_action('admin_menu', [$this, 'register_menu']);
		add_action('admin_init', [$this, 'register_settings']);
		add_action('admin_post_' . self::COPY_ACTION, [$this, 'handle_copy_reviews']);
	}

	public function register_menu(): void
	{
		$this->register_parent_menu();

		add_submenu_page(
			'kt-plugins',
			'KT Review Images',
			'Review Images',
			'manage_options',
			self::MENU_SLUG,
			[$this, 'render_settings_page']
		);
	}

	public function register_settings(): void
	{
		register_setting(self::SETTINGS_GROUP, self::OPTION_NAME, [
			'type' => 'string',
			'sanitize_callback' => [$this, 'sanitize_settings_json'],
			'default' => '',
		]);
	}

	public function sanitize_settings_json($value): string
	{
		if (!is_string($value)) {
			add_settings_error(self::OPTION_NAME, 'invalid_json', 'Settings JSON must be a string.');
			return (string) get_option(self::OPTION_NAME, '');
		}

		$value = trim($value);

		if ($value === '') {
			add_settings_error(self::OPTION_NAME, 'json_saved', 'Settings cleared. Defaults will be used.', 'updated');
			return '';
		}

		$decoded = json_decode($value, true);

		if (!is_array($decoded)) {
			add_settings_error(self::OPTION_NAME, 'invalid_json', 'Invalid JSON: ' . json_last_error_msg());
			return (string) get_option(self::OPTION_NAME, '');
		}

		$clean = [];

		foreach (array_keys(self::defaults()) as $key) {
			if (!isset($decoded[$key])) {
				continue;
			}

			$clean[$key] = sanitize_textarea_field((string) $decoded[$key]);
		}

		add_settings_error(self::OPTION_NAME, 'json_saved', 'Settings saved.', 'updated');

		return wp_json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	}

	public function render_settings_page(): void
	{
		if (!current_user_can('manage_options')) {
			return;
		}

		$current = get_option(self::OPTION_NAME, '');

		if (trim((string) $current) === '') {
			$current = wp_json_encode(
				self::defaults(),
				JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			);
		}
		?>
		<div class="wrap">
			<h1>KT Review Images</h1>
			<p>Paste the storefront string settings here as one JSON object.</p>

			<?php settings_errors(self::OPTION_NAME); ?>
			<?php $this->render_copy_reviews_notice(); ?>

			<form method="post" action="options.php">
				<?php settings_fields(self::SETTINGS_GROUP); ?>

				<textarea
					name="<?php echo esc_attr(self::OPTION_NAME); ?>"
					rows="28"
					style="width:100%; font-family:monospace; font-size:13px;"
				><?php echo esc_textarea($current); ?></textarea>

				<?php submit_button('Save JSON Settings'); ?>
			</form>

			<hr>

			<h2>Copy reviews between products</h2>
			<p>Duplicate approved and pending product reviews from one product to another, including ratings, replies, and review images.</p>

			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr(self::COPY_ACTION); ?>" />
				<?php wp_nonce_field(self::COPY_NONCE_ACTION); ?>

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="kt-review-images-source-product">Source product ID</label>
							</th>
							<td>
								<input
									id="kt-review-images-source-product"
									name="source_product_id"
									type="number"
									min="1"
									required
									class="small-text"
								/>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="kt-review-images-target-product">Target product ID</label>
							</th>
							<td>
								<input
									id="kt-review-images-target-product"
									name="target_product_id"
									type="number"
									min="1"
									required
									class="small-text"
								/>
							</td>
						</tr>
					</tbody>
				</table>

				<?php submit_button('Copy Reviews', 'secondary'); ?>
			</form>
		</div>
		<?php
	}

	public function handle_copy_reviews(): void
	{
		if (!current_user_can('manage_options')) {
			wp_die('You do not have permission to do this.');
		}

		check_admin_referer(self::COPY_NONCE_ACTION);

		$source_product_id = isset($_POST['source_product_id']) ? (int) $_POST['source_product_id'] : 0;
		$target_product_id = isset($_POST['target_product_id']) ? (int) $_POST['target_product_id'] : 0;

		if ($source_product_id < 1 || $target_product_id < 1) {
			$this->redirect_with_copy_notice('invalid_ids');
		}

		if ($source_product_id === $target_product_id) {
			$this->redirect_with_copy_notice('same_product');
		}

		if (get_post_type($source_product_id) !== 'product' || get_post_type($target_product_id) !== 'product') {
			$this->redirect_with_copy_notice('invalid_products');
		}

		$copied_reviews = $this->copy_product_reviews($source_product_id, $target_product_id);

		$this->redirect_with_copy_notice('copied', [
			'copied_reviews' => $copied_reviews,
			'source_product_id' => $source_product_id,
			'target_product_id' => $target_product_id,
		]);
	}

	private function register_parent_menu(): void
	{
		global $menu;

		foreach ($menu as $item) {
			if (($item[2] ?? '') === 'kt-plugins') {
				return;
			}
		}

		add_menu_page(
			'KT Plugins',
			'KT Plugins',
			'manage_options',
			'kt-plugins',
			[$this, 'render_settings_page'],
			'dashicons-admin-plugins',
			58
		);
	}

	private function copy_product_reviews(int $source_product_id, int $target_product_id): int
	{
		$comments = get_comments([
			'post_id' => $source_product_id,
			'status' => 'all',
			'orderby' => 'comment_date_gmt',
			'order' => 'ASC',
		]);

		$reviews = array_values(array_filter($comments, function (WP_Comment $comment): bool {
			if ($comment->comment_approved === 'spam' || $comment->comment_approved === 'trash') {
				return false;
			}

			return $comment->comment_type === 'review' || $comment->comment_type === '';
		}));

		if ($reviews === []) {
			$this->refresh_target_product_reviews($target_product_id);
			return 0;
		}

		$children_by_parent = [];

		foreach ($reviews as $review) {
			$children_by_parent[(int) $review->comment_parent][] = $review;
		}

		$copied_reviews = 0;
		$this->copy_review_branch($children_by_parent, 0, 0, $target_product_id, $copied_reviews);
		$this->refresh_target_product_reviews($target_product_id);

		return $copied_reviews;
	}

	private function copy_review_branch(array $children_by_parent, int $source_parent_id, int $target_parent_id, int $target_product_id, int &$copied_reviews): void
	{
		$children = $children_by_parent[$source_parent_id] ?? [];

		foreach ($children as $review) {
			$new_comment_id = wp_insert_comment([
				'comment_post_ID' => $target_product_id,
				'comment_author' => $review->comment_author,
				'comment_author_email' => $review->comment_author_email,
				'comment_author_url' => $review->comment_author_url,
				'comment_author_IP' => $review->comment_author_IP,
				'comment_date' => $review->comment_date,
				'comment_date_gmt' => $review->comment_date_gmt,
				'comment_content' => $review->comment_content,
				'comment_karma' => $review->comment_karma,
				'comment_approved' => $review->comment_approved,
				'comment_agent' => $review->comment_agent,
				'comment_type' => $review->comment_type,
				'comment_parent' => $target_parent_id,
				'user_id' => $review->user_id,
			]);

			if (!$new_comment_id) {
				continue;
			}

			$copied_reviews++;
			$this->copy_review_meta($review->comment_ID, $new_comment_id);
			$this->copy_review_branch($children_by_parent, (int) $review->comment_ID, (int) $new_comment_id, $target_product_id, $copied_reviews);
		}
	}

	private function copy_review_meta(int $source_comment_id, int $target_comment_id): void
	{
		$meta = get_comment_meta($source_comment_id);

		foreach ($meta as $meta_key => $values) {
			foreach ($values as $value) {
				add_comment_meta($target_comment_id, $meta_key, maybe_unserialize($value));
			}
		}
	}

	private function refresh_target_product_reviews(int $product_id): void
	{
		$approved_reviews = get_comments([
			'post_id' => $product_id,
			'status' => 'approve',
			'orderby' => 'comment_date_gmt',
			'order' => 'ASC',
		]);

		$ratings = [];
		$review_count = 0;

		foreach ($approved_reviews as $review) {
			if ($review->comment_type !== 'review' && $review->comment_type !== '') {
				continue;
			}

			if ((int) $review->comment_parent === 0) {
				$review_count++;
			}

			$rating = (int) get_comment_meta($review->comment_ID, 'rating', true);

			if ($rating > 0) {
				$ratings[] = $rating;
			}
		}

		$rating_count = array_count_values($ratings);
		$average_rating = $ratings === [] ? 0 : array_sum($ratings) / count($ratings);

		update_post_meta($product_id, '_wc_review_count', $review_count);
		update_post_meta($product_id, '_wc_rating_count', $rating_count);
		update_post_meta($product_id, '_wc_average_rating', wc_format_decimal($average_rating, 2));

		wp_update_comment_count($product_id);
		clean_post_cache($product_id);
		wc_delete_product_transients($product_id);
	}

	private function redirect_with_copy_notice(string $status, array $args = []): void
	{
		$query = array_merge([
			'page' => self::MENU_SLUG,
			'kt_review_copy_status' => $status,
		], $args);

		wp_safe_redirect(add_query_arg($query, admin_url('admin.php')));
		exit;
	}

	private function render_copy_reviews_notice(): void
	{
		$status = isset($_GET['kt_review_copy_status']) ? sanitize_key(wp_unslash($_GET['kt_review_copy_status'])) : '';

		if ($status === '') {
			return;
		}

		if ($status === 'copied') {
			$copied_reviews = isset($_GET['copied_reviews']) ? (int) $_GET['copied_reviews'] : 0;
			$source_product_id = isset($_GET['source_product_id']) ? (int) $_GET['source_product_id'] : 0;
			$target_product_id = isset($_GET['target_product_id']) ? (int) $_GET['target_product_id'] : 0;
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					echo esc_html(
						sprintf(
							'Copied %d reviews from product %d to product %d.',
							$copied_reviews,
							$source_product_id,
							$target_product_id
						)
					);
					?>
				</p>
			</div>
			<?php
			return;
		}

		$messages = [
			'invalid_ids' => 'Enter valid source and target product IDs.',
			'same_product' => 'Source and target product must be different.',
			'invalid_products' => 'Both source and target must be WooCommerce products.',
		];

		if (!isset($messages[$status])) {
			return;
		}
		?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo esc_html($messages[$status]); ?></p>
		</div>
		<?php
	}
}
