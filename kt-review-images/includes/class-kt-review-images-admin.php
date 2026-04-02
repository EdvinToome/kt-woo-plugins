<?php

if (!defined('ABSPATH')) {
	exit;
}

final class KT_Review_Images_Admin
{
	const META_BOX_ID = 'kt-review-images-meta-box';
	const NONCE_FIELD_NAME = 'kt_review_images_admin_nonce';
	const NONCE_ACTION = 'kt_review_images_admin_save';

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
		add_action('add_meta_boxes_comment', [$this, 'register_comment_meta_box']);
		add_action('edit_comment', [$this, 'save_comment_meta_box']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
	}

	public function register_comment_meta_box(WP_Comment $comment): void
	{
		if (!$this->is_product_review($comment)) {
			return;
		}

		add_meta_box(
			self::META_BOX_ID,
			esc_html__('Review image', 'kt-review-images'),
			[$this, 'render_comment_meta_box'],
			'comment',
			'normal',
			'high'
		);
	}

	public function enqueue_assets(string $hook_suffix): void
	{
		if ($hook_suffix !== 'comment.php') {
			return;
		}

		if (!isset($_GET['action']) || $_GET['action'] !== 'editcomment') {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_script(
			'kt-review-images-admin',
			plugins_url('../assets/kt-review-images-admin.js', __FILE__),
			['jquery'],
			KT_Review_Images_Frontend::VERSION,
			true
		);
	}

	public function render_comment_meta_box(WP_Comment $comment): void
	{
		$image_id = (int) get_comment_meta($comment->comment_ID, KT_Review_Images_Frontend::COMMENT_IMAGE_META_KEY, true);
		$image_url = $image_id > 0 ? wp_get_attachment_image_url($image_id, 'medium') : '';
		?>
		<div class="kt-review-images-admin">
			<?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD_NAME); ?>
			<input
				type="hidden"
				name="<?php echo esc_attr(KT_Review_Images_Frontend::COMMENT_IMAGE_META_KEY); ?>"
				value="<?php echo esc_attr((string) $image_id); ?>"
				data-kt-review-image-id
			/>

			<div data-kt-review-image-preview-wrap<?php echo $image_url ? '' : ' hidden'; ?>>
				<img
					src="<?php echo esc_url($image_url ?: ''); ?>"
					alt=""
					data-kt-review-image-preview
					style="display:block;width:100%;height:auto;border:1px solid #d0d7e2;border-radius:12px;margin-bottom:12px;"
				/>
			</div>

			<p><?php esc_html_e('Add or replace the image shown with this product review on the storefront.', 'kt-review-images'); ?></p>

			<p>
				<button type="button" class="button button-secondary" data-kt-review-image-select>
					<?php echo $image_id > 0 ? esc_html__('Replace image', 'kt-review-images') : esc_html__('Select image', 'kt-review-images'); ?>
				</button>
				<button
					type="button"
					class="button-link-delete"
					data-kt-review-image-remove
					<?php echo $image_id > 0 ? '' : 'hidden'; ?>
				>
					<?php esc_html_e('Remove image', 'kt-review-images'); ?>
				</button>
			</p>
		</div>
		<?php
	}

	public function save_comment_meta_box(int $comment_id): void
	{
		if (!current_user_can('edit_comment', $comment_id)) {
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

		$comment = get_comment($comment_id);

		if (!$comment instanceof WP_Comment || !$this->is_product_review($comment)) {
			return;
		}

		$image_id = isset($_POST[KT_Review_Images_Frontend::COMMENT_IMAGE_META_KEY])
			? (int) wp_unslash($_POST[KT_Review_Images_Frontend::COMMENT_IMAGE_META_KEY])
			: 0;

		if ($image_id > 0) {
			update_comment_meta($comment_id, KT_Review_Images_Frontend::COMMENT_IMAGE_META_KEY, $image_id);
			return;
		}

		delete_comment_meta($comment_id, KT_Review_Images_Frontend::COMMENT_IMAGE_META_KEY);
	}

	private function is_product_review(WP_Comment $comment): bool
	{
		$post_id = (int) $comment->comment_post_ID;

		if ($post_id < 1 || get_post_type($post_id) !== 'product') {
			return false;
		}

		return $comment->comment_type === 'review' || $comment->comment_type === '';
	}
}
