<?php

if (!defined("ABSPATH")) {
    exit();
}

if (!function_exists("wc_get_product")) {
    return;
}

$product = wc_get_product(get_the_ID());

if (!$product instanceof WC_Product) {
    return;
}

$strings = KT_Review_Images_Settings::all();
$frontend = KT_Review_Images_Frontend::get_instance();
$reviews = $frontend->get_reviews($product->get_id());
$review_count = (int) $product->get_review_count();
$average_rating = (float) $product->get_average_rating();

if (
    get_option("woocommerce_review_rating_verification_required") === "yes" &&
    !wc_customer_bought_product("", get_current_user_id(), $product->get_id())
) {
    $can_review = false;
} else {
    $can_review = true;
}

$frontend->debug_log("Custom reviews template rendered", [
    "product_id" => $product->get_id(),
    "review_count" => count($reviews),
    "average_rating" => $average_rating,
]);
?>
<div
    id="reviews"
    class="woocommerce-Reviews kt-review-images"
    data-kt-review-average-rating="<?php echo esc_attr((string) $average_rating); ?>"
    data-kt-review-count="<?php echo esc_attr((string) $review_count); ?>"
>
	<div class="kt-review-images__shell">
		<section class="kt-review-images__list-section">

			<?php if (!empty($reviews)): ?>
				<ol class="kt-review-images__list">
					<?php foreach ($reviews as $review): ?>
						<?php echo $frontend->render_review_card($review); ?>
					<?php endforeach; ?>
				</ol>
			<?php else: ?>
				<p class="kt-review-images__empty"><?php echo esc_html(
        $strings["empty_reviews"],
    ); ?></p>
			<?php endif; ?>
		</section>

		<div id="review_form_wrapper" class="kt-review-images__form-wrap">
			<?php if ($can_review): ?>
				<?php
    ob_start();
    comment_form($frontend->get_comment_form_args());
    $form_html = (string) ob_get_clean();
    $form_html = preg_replace(
        "/<form\b/",
        '<form enctype="multipart/form-data"',
        $form_html,
        1,
    );
    echo $form_html;
    ?>
			<?php else: ?>
				<p class="kt-review-images__notice">
					<?php echo esc_html($strings["review_restricted"]); ?>
				</p>
			<?php endif; ?>
		</div>
	</div>

	<div class="kt-review-images__lightbox" hidden>
		<div class="kt-review-images__lightbox-backdrop" data-kt-review-close></div>
		<div class="kt-review-images__lightbox-dialog" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr(
      $strings["lightbox_label"],
  ); ?>">
			<button type="button" class="kt-review-images__lightbox-close" data-kt-review-close aria-label="<?php echo esc_attr(
       $strings["close_label"],
   ); ?>">
				&times;
			</button>
			<img src="" alt="" class="kt-review-images__lightbox-image" />
		</div>
	</div>
</div>
