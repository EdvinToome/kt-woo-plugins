<?php
/**
 * Plugin Name: KT Conditional Phone & Admin Subject
 * Description: Requires billing phone only for physical carts and appends physical + total info to admin new-order email subjects.
 * Version: 1.0.0
 * Author: Edvin Toome
 */

if (!defined("ABSPATH")) {
    exit();
}

add_action("plugins_loaded", function (): void {
    if (!class_exists("WooCommerce")) {
        return;
    }

    require_once __DIR__ . "/includes/class-kt-conditional-phone-and-admin-subject-settings.php";
    require_once __DIR__ . "/includes/class-kt-conditional-phone-and-admin-subject.php";

    if (is_admin()) {
        KT_Conditional_Phone_And_Admin_Subject_Settings::get_instance();
    }

    KT_Conditional_Phone_And_Admin_Subject::get_instance();
});
