<?php
/**
 * Plugin Name: KT Auto Apply URL Coupon
 * Description: Stores a coupon from a configurable URL query param and applies it automatically on checkout. Also shows a small badge on product pages when the coupon is detected.
 * Version: 1.2.0
 * Author: Edvin Toome
 */

if (!defined("ABSPATH")) {
    exit();
}

require_once __DIR__ .
    "/includes/class-kt-auto-apply-url-coupon-settings.php";

final class KT_Auto_Apply_URL_Coupon
{
    const COOKIE_PENDING = "kt_pending_coupon";
    const COOKIE_CONSUMED = "kt_consumed_coupon";
    const SESSION_PENDING = "kt_pending_coupon";
    const SESSION_CONSUMED = "kt_consumed_coupon";
    const COOKIE_LIFETIME = 259200; // 72 hours
    const DEBUG_LOG = false;

    public function __construct()
    {
        if (is_admin()) {
            new KT_Auto_Apply_URL_Coupon_Settings();
        }

        add_action("init", [$this, "capture_coupon_from_url"], 1);
        add_action("woocommerce_init", [$this, "sync_cookie_to_wc_session"]);
        add_action(
            "template_redirect",
            [$this, "maybe_apply_coupon_on_checkout"],
            20,
        );
        add_action(
            "woocommerce_before_checkout_form",
            [$this, "maybe_apply_coupon_on_checkout"],
            1,
        );
        add_action(
            "woocommerce_checkout_update_order_review",
            [$this, "maybe_apply_coupon_on_checkout_ajax"],
            1,
        );
        add_action(
            "woocommerce_single_product_summary",
            [$this, "render_coupon_badge_on_product_page"],
            28,
        );
        add_action("wp_head", [$this, "output_coupon_badge_styles"]);
        add_action("woocommerce_cart_emptied", [
            $this,
            "maybe_clear_consumed_if_cart_empty",
        ]);
        add_action("woocommerce_thankyou", [$this, "maybe_clear_after_order"]);
    }

    public function capture_coupon_from_url()
    {
        if (is_admin() && !wp_doing_ajax()) {
            return;
        }

        $query_param = $this->get_query_param();

        if (empty($_GET[$query_param])) {
            return;
        }

        $coupon_code = wc_format_coupon_code(wp_unslash($_GET[$query_param]));
        $coupon_code = sanitize_text_field($coupon_code);

        if ($coupon_code === "") {
            return;
        }

        $this->log("Captured coupon from URL: " . $coupon_code);

        $this->set_pending_coupon($coupon_code);
        $this->clear_consumed_coupon();
    }

    public function sync_cookie_to_wc_session()
    {
        if (!function_exists("WC") || !WC()->session) {
            return;
        }

        $session_pending = WC()->session->get(self::SESSION_PENDING);
        $cookie_pending = $this->get_cookie(self::COOKIE_PENDING);

        if (empty($session_pending) && !empty($cookie_pending)) {
            WC()->session->set(
                self::SESSION_PENDING,
                wc_format_coupon_code($cookie_pending),
            );
            $this->log(
                "Synced pending coupon from cookie to session: " .
                    $cookie_pending,
            );
        }

        $session_consumed = WC()->session->get(self::SESSION_CONSUMED);
        $cookie_consumed = $this->get_cookie(self::COOKIE_CONSUMED);

        if (empty($session_consumed) && !empty($cookie_consumed)) {
            WC()->session->set(
                self::SESSION_CONSUMED,
                wc_format_coupon_code($cookie_consumed),
            );
        }
    }

    public function maybe_apply_coupon_on_checkout()
    {
        if (!function_exists("WC") || !WC()->cart) {
            return;
        }

        if (!function_exists("is_checkout") || !is_checkout()) {
            return;
        }

        $this->apply_pending_coupon();
    }

    public function maybe_apply_coupon_on_checkout_ajax()
    {
        if (!function_exists("WC") || !WC()->cart) {
            return;
        }

        $this->apply_pending_coupon();
    }

    private function apply_pending_coupon()
    {
        $pending_coupon = $this->get_pending_coupon();
        if (!$pending_coupon) {
            return;
        }

        $consumed_coupon = $this->get_consumed_coupon();
        if (
            $consumed_coupon &&
            wc_format_coupon_code($consumed_coupon) ===
                wc_format_coupon_code($pending_coupon)
        ) {
            $this->log("Coupon already consumed, skipping: " . $pending_coupon);
            return;
        }

        if (WC()->cart->is_empty()) {
            $this->log(
                "Cart empty on checkout, coupon remains pending: " .
                    $pending_coupon,
            );
            return;
        }

        if (WC()->cart->has_discount($pending_coupon)) {
            $this->mark_coupon_consumed($pending_coupon);
            $this->clear_pending_coupon();
            $this->log(
                "Coupon already present in cart, marked consumed: " .
                    $pending_coupon,
            );
            return;
        }

        $coupon = new WC_Coupon($pending_coupon);
        if (!$coupon || !$coupon->get_id()) {
            $this->log("Invalid coupon, clearing pending: " . $pending_coupon);
            $this->clear_pending_coupon();
            return;
        }

        static $applying = false;
        if ($applying) {
            return;
        }
        $applying = true;

        wc_clear_notices();

        $applied = WC()->cart->apply_coupon($pending_coupon);

        if ($applied) {
            $this->mark_coupon_consumed($pending_coupon);
            $this->clear_pending_coupon();
            WC()->cart->calculate_totals();
            wc_clear_notices();
            $this->log(
                "Coupon applied successfully on checkout: " . $pending_coupon,
            );
            $applying = false;
            return;
        }

        $error_notices = wc_get_notices("error");
        $should_clear = false;

        if (!empty($error_notices)) {
            foreach ($error_notices as $notice) {
                $message = isset($notice["notice"])
                    ? wp_strip_all_tags($notice["notice"])
                    : "";

                if (
                    stripos($message, "does not exist") !== false ||
                    stripos($message, "not valid") !== false ||
                    stripos($message, "expired") !== false ||
                    stripos($message, "usage limit") !== false ||
                    stripos($message, "not yours") !== false
                ) {
                    $should_clear = true;
                    break;
                }
            }
        }

        if ($should_clear) {
            $this->clear_pending_coupon();
            $this->log("Coupon hard-failed, cleared: " . $pending_coupon);
        }

        wc_clear_notices();
        $applying = false;
    }

    public function render_coupon_badge_on_product_page()
    {
        if (!is_product()) {
            return;
        }

        $coupon_code = $this->get_pending_coupon();
        $query_param = $this->get_query_param();

        if (!$coupon_code && !empty($_GET[$query_param])) {
            $coupon_code = wc_format_coupon_code(wp_unslash($_GET[$query_param]));
            $coupon_code = sanitize_text_field($coupon_code);
        }

        if (!$coupon_code) {
            return;
        }

        $coupon = new WC_Coupon($coupon_code);
        if (!$coupon || !$coupon->get_id()) {
            return;
        }

        echo '<div class="kt-auto-coupon-badge">';
        echo "<strong>" . esc_html($this->get_badge_title()) . "</strong>";
        echo "<span>" .
            esc_html($this->get_badge_message($coupon_code)) .
            "</span>";
        echo "</div>";
    }

    public function output_coupon_badge_styles()
    {
        if (!is_product()) {
            return;
        }

        echo '<style>
		.kt-auto-coupon-badge{
			margin-top:0px;
			margin-bottom: 5px;
			padding:8px 12px;
			border:1px solid #56B0F2;
			background:#e4f3fd;
			color:#0a73c2;
			border-radius:8px;
			font-size:14px;
			line-height:1.4;
			display:inline-flex;
			flex-direction:column;
			gap:2px;
		}
		.kt-auto-coupon-badge strong{
			font-weight:700;
			color:#0a73c2;
		}
		.kt-auto-coupon-badge span{
			color:#0a73c2;
			font-size:13px;
		}
		</style>';
    }

    public function maybe_clear_after_order()
    {
        $this->clear_pending_coupon();
        $this->clear_consumed_coupon();
    }

    public function maybe_clear_consumed_if_cart_empty()
    {
        $this->clear_pending_coupon();
        $this->clear_consumed_coupon();
    }

    private function get_query_param()
    {
        $settings = KT_Auto_Apply_URL_Coupon_Settings::get();

        return $settings["query_param"];
    }

    private function get_badge_title()
    {
        $settings = KT_Auto_Apply_URL_Coupon_Settings::get();

        return $settings["badge_title"];
    }

    private function get_badge_message($coupon_code)
    {
        $settings = KT_Auto_Apply_URL_Coupon_Settings::get();

        return str_replace(
            "{coupon}",
            strtoupper($coupon_code),
            $settings["badge_message"],
        );
    }

    private function set_pending_coupon($coupon_code)
    {
        $coupon_code = wc_format_coupon_code($coupon_code);

        if (function_exists("WC") && WC()->session) {
            WC()->session->set(self::SESSION_PENDING, $coupon_code);
        }

        $this->set_cookie(
            self::COOKIE_PENDING,
            $coupon_code,
            time() + self::COOKIE_LIFETIME,
        );
    }

    private function get_pending_coupon()
    {
        if (function_exists("WC") && WC()->session) {
            $session_value = WC()->session->get(self::SESSION_PENDING);
            if (!empty($session_value)) {
                return wc_format_coupon_code($session_value);
            }
        }

        $cookie_value = $this->get_cookie(self::COOKIE_PENDING);
        if (!empty($cookie_value)) {
            return wc_format_coupon_code($cookie_value);
        }

        return "";
    }

    private function clear_pending_coupon()
    {
        if (function_exists("WC") && WC()->session) {
            WC()->session->__unset(self::SESSION_PENDING);
        }

        $this->delete_cookie(self::COOKIE_PENDING);
    }

    private function mark_coupon_consumed($coupon_code)
    {
        $coupon_code = wc_format_coupon_code($coupon_code);

        if (function_exists("WC") && WC()->session) {
            WC()->session->set(self::SESSION_CONSUMED, $coupon_code);
        }

        $this->set_cookie(
            self::COOKIE_CONSUMED,
            $coupon_code,
            time() + self::COOKIE_LIFETIME,
        );
    }

    private function get_consumed_coupon()
    {
        if (function_exists("WC") && WC()->session) {
            $session_value = WC()->session->get(self::SESSION_CONSUMED);
            if (!empty($session_value)) {
                return wc_format_coupon_code($session_value);
            }
        }

        $cookie_value = $this->get_cookie(self::COOKIE_CONSUMED);
        if (!empty($cookie_value)) {
            return wc_format_coupon_code($cookie_value);
        }

        return "";
    }

    private function clear_consumed_coupon()
    {
        if (function_exists("WC") && WC()->session) {
            WC()->session->__unset(self::SESSION_CONSUMED);
        }

        $this->delete_cookie(self::COOKIE_CONSUMED);
    }

    private function get_cookie($name)
    {
        if (!isset($_COOKIE[$name])) {
            return "";
        }

        return sanitize_text_field(wp_unslash($_COOKIE[$name]));
    }

    private function set_cookie($name, $value, $expires)
    {
        $secure = is_ssl();

        setcookie($name, $value, [
            "expires" => $expires,
            "path" => COOKIEPATH ? COOKIEPATH : "/",
            "domain" => COOKIE_DOMAIN,
            "secure" => $secure,
            "httponly" => false,
            "samesite" => "Lax",
        ]);

        $_COOKIE[$name] = $value;
    }

    private function delete_cookie($name)
    {
        $secure = is_ssl();

        setcookie($name, "", [
            "expires" => time() - HOUR_IN_SECONDS,
            "path" => COOKIEPATH ? COOKIEPATH : "/",
            "domain" => COOKIE_DOMAIN,
            "secure" => $secure,
            "httponly" => false,
            "samesite" => "Lax",
        ]);

        unset($_COOKIE[$name]);
    }

    private function log($message)
    {
        if (!self::DEBUG_LOG) {
            return;
        }

        if (function_exists("wc_get_logger")) {
            wc_get_logger()->debug($message, [
                "source" => "kt-auto-apply-url-coupon",
            ]);
        }
    }
}

new KT_Auto_Apply_URL_Coupon();
