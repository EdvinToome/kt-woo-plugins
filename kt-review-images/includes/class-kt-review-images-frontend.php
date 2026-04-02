<?php

if (!defined('ABSPATH')) {
	exit;
}

final class KT_Review_Images_Frontend
{
	const COMMENT_IMAGE_META_KEY = '_kt_review_image_id';
	const IMAGE_FIELD_NAME = 'kt_review_image';
	const NONCE_FIELD_NAME = 'kt_review_image_nonce';
	const NONCE_ACTION = 'kt_review_image_upload';
	const VERSION = '1.0.0';
	const LOG_SOURCE = 'kt-review-images';
	const DEBUG_LOG = true;

	private static $instance = null;

	public static function get_instance(): self
	{
		if (self::$instance === null) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct()
	{
		add_filter('woocommerce_product_tabs', [$this, 'filter_product_tabs'], 98);
		add_filter('woocommerce_locate_template', [$this, 'filter_woocommerce_template'], 10, 3);
		add_filter('preprocess_comment', [$this, 'validate_review_image']);
		add_action('comment_post', [$this, 'save_review_image'], 10, 3);
		add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
		add_action('template_redirect', [$this, 'log_product_page_boot']);
	}

	private function log(string $message, array $context = []): void
	{
		if (!self::DEBUG_LOG) {
			return;
		}

		if (function_exists('wc_get_logger')) {
			wc_get_logger()->debug($message . (!empty($context) ? ' | ' . wp_json_encode($context) : ''), [
				'source' => self::LOG_SOURCE,
			]);
			return;
		}

		error_log('[KT Review Images] ' . $message . (!empty($context) ? ' | ' . wp_json_encode($context) : ''));
	}

	public function debug_log(string $message, array $context = []): void
	{
		$this->log($message, $context);
	}

	public function log_product_page_boot(): void
	{
		if (!function_exists('is_product') || !is_product()) {
			return;
		}

		$this->log('Product page boot', [
			'product_id' => get_the_ID(),
			'url' => home_url(add_query_arg([])),
		]);
	}

	public function filter_product_tabs(array $tabs): array
	{
		if (!isset($tabs['reviews'])) {
			$this->log('Reviews tab missing');
			return $tabs;
		}

		$tabs['reviews']['callback'] = [$this, 'render_reviews_tab'];

		$this->log('Reviews tab callback replaced', [
			'product_id' => get_the_ID(),
		]);

		return $tabs;
	}

	public function render_reviews_tab(): void
	{
		$this->log('Rendering custom reviews tab', [
			'product_id' => get_the_ID(),
		]);

		include plugin_dir_path(__DIR__) . 'templates/single-product-reviews.php';
	}

	public function filter_woocommerce_template(string $template, string $template_name, string $template_path): string
	{
		if (!is_singular('product')) {
			return $template;
		}

		if ($template_name !== 'single-product-reviews.php') {
			return $template;
		}

		$custom_template = plugin_dir_path(__DIR__) . 'templates/single-product-reviews.php';

		$this->log('WooCommerce template override hit', [
			'template_name' => $template_name,
			'template_path' => $template_path,
			'original_template' => $template,
			'custom_template' => $custom_template,
		]);

		return $custom_template;
	}

	public function enqueue_assets(): void
	{
		if (!function_exists('is_product') || !is_product()) {
			return;
		}

		wp_enqueue_style(
			'kt-review-images',
			plugins_url('../assets/kt-review-images.css', __FILE__),
			[],
			self::VERSION
		);

		wp_enqueue_script(
			'kt-review-images',
			plugins_url('../assets/kt-review-images.js', __FILE__),
			[],
			self::VERSION,
			true
		);

		wp_localize_script('kt-review-images', 'ktReviewImagesConfig', [
			'tabRatingAriaPrefix' => KT_Review_Images_Settings::get('tab_rating_aria_prefix'),
		]);

		$this->log('Assets enqueued', [
			'product_id' => get_the_ID(),
		]);
	}

	public function validate_review_image(array $commentdata): array
	{
		$post_id = isset($commentdata['comment_post_ID']) ? (int) $commentdata['comment_post_ID'] : 0;

		if ($post_id < 1 || get_post_type($post_id) !== 'product') {
			return $commentdata;
		}

		if (empty($_FILES[self::IMAGE_FIELD_NAME])) {
			return $commentdata;
		}

		$file = $_FILES[self::IMAGE_FIELD_NAME];

		if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) {
			return $commentdata;
		}

		if (
			empty($_POST[self::NONCE_FIELD_NAME]) ||
			!wp_verify_nonce(
				sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD_NAME])),
				self::NONCE_ACTION
			)
		) {
			$this->log('Review image nonce failed during validation', [
				'post_id' => $post_id,
			]);
			wp_die(esc_html__('Security check failed. Please try again.', 'kt-review-images'));
		}

		if ((int) $file['error'] !== UPLOAD_ERR_OK) {
			$this->log('Review image upload validation failed', [
				'post_id' => $post_id,
				'error' => (int) $file['error'],
			]);
			wp_die(esc_html__('Image upload failed. Please try again.', 'kt-review-images'));
		}

		$file_info = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);

		if (empty($file_info['type']) || strpos($file_info['type'], 'image/') !== 0) {
			$this->log('Review image rejected by mime validation', [
				'post_id' => $post_id,
				'file_name' => $file['name'],
				'detected_type' => $file_info['type'] ?? '',
			]);
			wp_die(esc_html__('Please upload an image file for the review photo.', 'kt-review-images'));
		}

		$this->log('Review image validated', [
			'post_id' => $post_id,
			'file_name' => $file['name'],
		]);

		return $commentdata;
	}

	public function save_review_image(int $comment_id, $comment_approved, array $commentdata): void
	{
		$post_id = isset($commentdata['comment_post_ID']) ? (int) $commentdata['comment_post_ID'] : 0;

		if ($post_id < 1 || get_post_type($post_id) !== 'product') {
			return;
		}

		if (empty($_FILES[self::IMAGE_FIELD_NAME])) {
			return;
		}

		$file = $_FILES[self::IMAGE_FIELD_NAME];

		if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) {
			return;
		}

		if (
			empty($_POST[self::NONCE_FIELD_NAME]) ||
			!wp_verify_nonce(
				sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD_NAME])),
				self::NONCE_ACTION
			)
		) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$attachment_id = media_handle_upload(
			self::IMAGE_FIELD_NAME,
			$post_id,
			[],
			['test_form' => false]
		);

		if (is_wp_error($attachment_id)) {
			$this->log('Review image upload failed', [
				'post_id' => $post_id,
				'comment_id' => $comment_id,
				'error' => $attachment_id->get_error_message(),
			]);
			return;
		}

		update_comment_meta($comment_id, self::COMMENT_IMAGE_META_KEY, (int) $attachment_id);

		$this->log('Review image saved', [
			'post_id' => $post_id,
			'comment_id' => $comment_id,
			'attachment_id' => (int) $attachment_id,
		]);
	}

	public function get_reviews(int $product_id): array
	{
		$reviews = get_comments([
			'post_id' => $product_id,
			'status' => 'approve',
			'type' => 'review',
			'parent' => 0,
			'orderby' => 'comment_date_gmt',
			'order' => 'DESC',
		]);

		usort($reviews, function (WP_Comment $left, WP_Comment $right): int {
			$left_has_image = $this->get_review_image_id($left->comment_ID) > 0;
			$right_has_image = $this->get_review_image_id($right->comment_ID) > 0;

			if ($left_has_image !== $right_has_image) {
				return $left_has_image ? -1 : 1;
			}

			return strcmp($right->comment_date_gmt, $left->comment_date_gmt);
		});

		return $reviews;
	}

	public function get_review_image_id(int $comment_id): int
	{
		return (int) get_comment_meta($comment_id, self::COMMENT_IMAGE_META_KEY, true);
	}

	public function get_review_image_html(int $comment_id, string $size, array $attrs = []): string
	{
		$image_id = $this->get_review_image_id($comment_id);

		if ($image_id < 1) {
			return '';
		}

		return wp_get_attachment_image($image_id, $size, false, $attrs) ?: '';
	}

	public function get_review_image_url(int $comment_id, string $size = 'full'): string
	{
		$image_id = $this->get_review_image_id($comment_id);

		if ($image_id < 1) {
			return '';
		}

		return (string) wp_get_attachment_image_url($image_id, $size);
	}

	public function get_review_rating(WP_Comment $review): int
	{
		return (int) get_comment_meta($review->comment_ID, 'rating', true);
	}

	public function get_review_date(WP_Comment $review): string
	{
		return mysql2date(get_option('date_format'), $review->comment_date);
	}

	public function get_verified_label(WP_Comment $review): string
	{
		if (
			!function_exists('wc_review_is_from_verified_owner') ||
			get_option('woocommerce_review_rating_verification_label') !== 'yes' ||
			!wc_review_is_from_verified_owner($review->comment_ID)
		) {
			return '';
		}

		return esc_html__('Verified owner', 'woocommerce');
	}

	public function get_comment_form_args(): array
	{
		$commenter = wp_get_current_commenter();
		$require_name_email = get_option('require_name_email');
		$rating_required = get_option('woocommerce_review_rating_required') === 'yes';
		$strings = KT_Review_Images_Settings::all();
		$rating_field = '';
		$comment_field_label = $strings['comment_label'];
		$name_label = $strings['name_label'];
		$email_label = $strings['email_label'];
		$photo_label = $strings['photo_label'];
		$optional_label = $strings['optional_label'];

		if (function_exists('wc_review_ratings_enabled') && wc_review_ratings_enabled()) {
			$rating_field = sprintf(
				'<p class="comment-form-rating"><label for="rating">%1$s %2$s</label><select name="rating" id="rating" %3$s>
					<option value="">%4$s</option>
					<option value="5">%5$s</option>
					<option value="4">%6$s</option>
					<option value="3">%7$s</option>
					<option value="2">%8$s</option>
					<option value="1">%9$s</option>
				</select></p>',
				esc_html($strings['rating_label']),
				$rating_required ? '<span class="required">*</span>' : '',
				$rating_required ? 'required' : '',
				esc_html($strings['choose_rating_label']),
				esc_html($strings['rating_5_label']),
				esc_html($strings['rating_4_label']),
				esc_html($strings['rating_3_label']),
				esc_html($strings['rating_2_label']),
				esc_html($strings['rating_1_label'])
			);
		}

		$fields = [
			'author' => sprintf(
				'<p class="comment-form-author"><label for="author">%1$s %2$s</label><input id="author" name="author" type="text" value="%3$s" size="30" %4$s /></p>',
				esc_html($name_label),
				$require_name_email ? '<span class="required">*</span>' : '',
				esc_attr($commenter['comment_author']),
				$require_name_email ? 'required' : ''
			),
			'email' => sprintf(
				'<p class="comment-form-email"><label for="email">%1$s %2$s</label><input id="email" name="email" type="email" value="%3$s" size="30" %4$s /></p>',
				esc_html($email_label),
				$require_name_email ? '<span class="required">*</span>' : '',
				esc_attr($commenter['comment_author_email']),
				$require_name_email ? 'required' : ''
			),
		];

		$comment_field = sprintf(
			'%1$s
			<p class="comment-form-comment">
				<label for="comment">%2$s <span class="required">*</span></label>
				<textarea id="comment" name="comment" cols="45" rows="7" required></textarea>
			</p>
			<div class="kt-review-images__upload-field">
				<label for="%3$s">%4$s <span>%5$s</span></label>
				<input id="%3$s" name="%3$s" type="file" accept="image/*" />
				<p class="kt-review-images__upload-help">%6$s</p>
			</div>
			%7$s',
			$rating_field,
			esc_html($comment_field_label),
			esc_attr(self::IMAGE_FIELD_NAME),
			esc_html($photo_label),
			esc_html($optional_label),
			esc_html($strings['upload_help']),
			wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD_NAME, true, false)
		);

		return [
			'title_reply' => esc_html($strings['title_reply']),
			'title_reply_to' => esc_html($strings['title_reply_to']),
			'title_reply_before' => '<h3 id="reply-title" class="kt-review-images__form-title comment-reply-title">',
			'title_reply_after' => '</h3>',
			'class_form' => 'comment-form kt-review-images__form',
			'class_submit' => 'submit kt-review-images__submit',
			'label_submit' => esc_html($strings['label_submit']),
			'logged_in_as' => '',
			'comment_notes_before' => '<p class="comment-notes kt-review-images__comment-notes">' .
				esc_html($strings['comment_notes_before']) .
				'</p>',
			'comment_notes_after' => '',
			'fields' => $fields,
			'comment_field' => $comment_field,
		];
	}

	public function render_review_card(WP_Comment $review): string
	{
		$rating = $this->get_review_rating($review);
		$image_id = $this->get_review_image_id($review->comment_ID);
		$image_html = $image_id > 0
			? wp_get_attachment_image(
				$image_id,
				'large',
				false,
				[
					'class' => 'kt-review-images__card-image',
					'loading' => 'lazy',
				]
			)
			: '';
		$image_url = $image_id > 0 ? $this->get_review_image_url($review->comment_ID) : '';
		$verified_label = $this->get_verified_label($review);

		ob_start();
		?>
		<li class="kt-review-images__card<?php echo $image_id > 0 ? ' has-image' : ''; ?>" id="kt-review-<?php echo (int) $review->comment_ID; ?>">
			<?php if ($image_html && $image_url) : ?>
				<button
					type="button"
					class="kt-review-images__image-button"
					data-kt-review-lightbox="<?php echo esc_url($image_url); ?>"
					data-kt-review-author="<?php echo esc_attr($review->comment_author); ?>"
				>
					<?php echo $image_html; ?>
				</button>
			<?php endif; ?>

			<div class="kt-review-images__card-body">
				<div class="kt-review-images__card-head">
					<div>
						<p class="kt-review-images__author">
							<?php echo esc_html($review->comment_author); ?>
							<?php if ($verified_label) : ?>
								<span class="kt-review-images__verified"><?php echo esc_html($verified_label); ?></span>
							<?php endif; ?>
						</p>
						<p class="kt-review-images__date"><?php echo esc_html($this->get_review_date($review)); ?></p>
					</div>
					<?php if ($rating > 0) : ?>
						<div class="kt-review-images__stars"><?php echo wp_kses_post(wc_get_rating_html($rating)); ?></div>
					<?php endif; ?>
				</div>

				<div class="kt-review-images__content">
					<?php echo wpautop(wp_kses_post($review->comment_content)); ?>
				</div>
			</div>
		</li>
		<?php

		return (string) ob_get_clean();
	}
}
