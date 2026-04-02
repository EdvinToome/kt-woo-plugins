<?php
/**
 * Plugin Name: KT Review Images
 * Description: Adds optional image uploads to WooCommerce product reviews and replaces the product review section with an image-first layout.
 * Version: 1.1.0
 * Author: Edvin Toome
 */

if (!defined("ABSPATH")) {
    exit();
}

add_action("plugins_loaded", function (): void {
    if (!class_exists("WooCommerce")) {
        return;
    }

    require_once __DIR__ . "/includes/class-kt-review-images-settings.php";
    require_once __DIR__ . "/includes/class-kt-review-images-frontend.php";
    require_once __DIR__ . "/includes/class-kt-review-images-admin.php";

    KT_Review_Images_Settings::get_instance();
    KT_Review_Images_Frontend::get_instance();
    KT_Review_Images_Admin::get_instance();
});
