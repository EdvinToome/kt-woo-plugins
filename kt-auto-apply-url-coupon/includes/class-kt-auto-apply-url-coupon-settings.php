<?php

if (!defined("ABSPATH")) {
    exit();
}

final class KT_Auto_Apply_URL_Coupon_Settings
{
    const PARENT_SLUG = "kt-plugins";
    const OPTION_KEY = "kt_auto_apply_url_coupon_settings";
    const SETTINGS_GROUP = "kt_auto_apply_url_coupon_settings_group";
    const PAGE_SLUG = "kt-auto-apply-url-coupon";

    public function __construct()
    {
        add_action("admin_menu", [$this, "register_page"]);
        add_action("admin_init", [$this, "register_settings"]);
    }

    public static function get()
    {
        $settings = get_option(self::OPTION_KEY, []);

        if (!is_array($settings)) {
            return self::defaults();
        }

        return wp_parse_args($settings, self::defaults());
    }

    public static function defaults()
    {
        return [
            "query_param" => "coupon",
            "badge_title" => "5 € soodustust + tasuta kohaletoimetamine",
            "badge_message" => "Kood {coupon} rakendub kassas automaatselt",
        ];
    }

    public function register_page()
    {
        $this->register_parent_page();

        add_submenu_page(
            self::PARENT_SLUG,
            "KT Auto Apply URL Coupon",
            "Auto Apply URL Coupon",
            "manage_options",
            self::PAGE_SLUG,
            [$this, "render_page"],
        );
    }

    private function register_parent_page()
    {
        global $menu;

        foreach ($menu as $item) {
            if (($item[2] ?? "") === self::PARENT_SLUG) {
                return;
            }
        }

        add_menu_page(
            "KT Plugins",
            "KT Plugins",
            "manage_options",
            self::PARENT_SLUG,
            [$this, "render_parent_page"],
            "dashicons-admin-plugins",
            58,
        );
    }

    public function render_parent_page()
    {
        if (!current_user_can("manage_options")) {
            return;
        }
        ?>
		<div class="wrap">
			<h1>KT Plugins</h1>
			<p>Open one of the plugin settings pages:</p>
			<ul>
				<li><a href="<?php echo esc_url(admin_url("admin.php?page=" . self::PAGE_SLUG)); ?>">Auto Apply URL Coupon</a></li>
				<?php if (function_exists("kt_ppu_render_settings_page")): ?>
					<li><a href="<?php echo esc_url(admin_url("admin.php?page=kt-ppu-settings")); ?>">Upsell Emails</a></li>
				<?php endif; ?>
			</ul>
		</div>
		<?php
    }

    public function register_settings()
    {
        register_setting(self::SETTINGS_GROUP, self::OPTION_KEY, [
            "type" => "array",
            "sanitize_callback" => [$this, "sanitize_settings"],
            "default" => self::defaults(),
        ]);
    }

    public function sanitize_settings($input)
    {
        $defaults = self::defaults();
        $settings = is_array($input) ? $input : [];
        $query_param = sanitize_key($settings["query_param"] ?? "");

        return [
            "query_param" => $query_param ?: $defaults["query_param"],
            "badge_title" => sanitize_text_field(
                $settings["badge_title"] ?? $defaults["badge_title"],
            ),
            "badge_message" => sanitize_text_field(
                $settings["badge_message"] ?? $defaults["badge_message"],
            ),
        ];
    }

    public function render_page()
    {
        if (!current_user_can("manage_options")) {
            return;
        }

        $settings = self::get();
        ?>
		<div class="wrap">
			<h1>KT Auto Apply URL Coupon</h1>
			<p>Configure the coupon query param and the product badge copy.</p>
			<?php settings_errors(); ?>

			<form method="post" action="options.php">
				<?php settings_fields(self::SETTINGS_GROUP); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="kt-auto-apply-url-coupon-query-param">Query param</label>
						</th>
						<td>
							<input
								id="kt-auto-apply-url-coupon-query-param"
								name="<?php echo esc_attr(
         self::OPTION_KEY,
     ); ?>[query_param]"
								type="text"
								class="regular-text"
								value="<?php echo esc_attr($settings["query_param"]); ?>"
							>
							<p class="description">Example: <code>?<?php echo esc_html(
           $settings["query_param"],
       ); ?>=SPRING</code></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="kt-auto-apply-url-coupon-badge-title">Badge title</label>
						</th>
						<td>
							<input
								id="kt-auto-apply-url-coupon-badge-title"
								name="<?php echo esc_attr(
         self::OPTION_KEY,
     ); ?>[badge_title]"
								type="text"
								class="regular-text"
								value="<?php echo esc_attr($settings["badge_title"]); ?>"
							>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="kt-auto-apply-url-coupon-badge-message">Badge message</label>
						</th>
						<td>
							<input
								id="kt-auto-apply-url-coupon-badge-message"
								name="<?php echo esc_attr(
         self::OPTION_KEY,
     ); ?>[badge_message]"
								type="text"
								class="regular-text"
								value="<?php echo esc_attr($settings["badge_message"]); ?>"
							>
							<p class="description">Use <code>{coupon}</code> where the coupon code should appear.</p>
						</td>
					</tr>
				</table>

				<?php submit_button("Save Settings"); ?>
			</form>
		</div>
		<?php
    }
}
