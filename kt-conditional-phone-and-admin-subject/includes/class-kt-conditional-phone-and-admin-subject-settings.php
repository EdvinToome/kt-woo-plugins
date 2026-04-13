<?php

if (!defined("ABSPATH")) {
    exit();
}

final class KT_Conditional_Phone_And_Admin_Subject_Settings
{
    const PARENT_SLUG = "kt-plugins";
    const MENU_SLUG = "kt-conditional-phone-admin-subject";
    const SETTINGS_GROUP = "kt_conditional_phone_admin_subject_settings_group";
    const OPTION_NAME = "kt_conditional_phone_admin_subject_strings_json";

    private static ?KT_Conditional_Phone_And_Admin_Subject_Settings $instance = null;

    public static function get_instance(): KT_Conditional_Phone_And_Admin_Subject_Settings
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function defaults(): array
    {
        return [
            "phone_required_error" => "Maksja telefon on füüsilise tellimuse puhul kohustuslik.",
            "admin_new_order_physical_marker" => "Physical",
            "admin_subject_separator" => "|",
        ];
    }

    public static function all(): array
    {
        $json = get_option(self::OPTION_NAME, "");

        if (!is_string($json) || trim($json) === "") {
            return self::defaults();
        }

        $parsed = json_decode($json, true);

        if (!is_array($parsed)) {
            return self::defaults();
        }

        return array_replace(self::defaults(), $parsed);
    }

    public static function get(string $key): string
    {
        $values = self::all();

        return (string) $values[$key];
    }

    private function __construct()
    {
        add_action("admin_menu", [$this, "register_menu"]);
        add_action("admin_init", [$this, "register_settings"]);
    }

    public function register_menu(): void
    {
        $this->register_parent_menu();

        add_submenu_page(
            self::PARENT_SLUG,
            "KT Conditional Phone & Admin Subject",
            "Conditional Phone",
            "manage_options",
            self::MENU_SLUG,
            [$this, "render_page"],
        );
    }

    public function register_settings(): void
    {
        register_setting(self::SETTINGS_GROUP, self::OPTION_NAME, [
            "type" => "string",
            "sanitize_callback" => [$this, "sanitize_settings_json"],
            "default" => "",
        ]);
    }

    public function sanitize_settings_json($value): string
    {
        if (!is_string($value)) {
            add_settings_error(self::OPTION_NAME, "invalid_json_type", "Settings JSON must be a string.");
            return (string) get_option(self::OPTION_NAME, "");
        }

        $value = trim($value);

        if ($value === "") {
            add_settings_error(self::OPTION_NAME, "json_cleared", "Settings cleared. Defaults will be used.", "updated");
            return "";
        }

        $parsed = json_decode($value, true);

        if (!is_array($parsed)) {
            add_settings_error(self::OPTION_NAME, "invalid_json", "Invalid JSON: " . json_last_error_msg());
            return (string) get_option(self::OPTION_NAME, "");
        }

        $clean = [];

        foreach (array_keys(self::defaults()) as $key) {
            if (!isset($parsed[$key])) {
                continue;
            }

            $clean[$key] = sanitize_text_field((string) $parsed[$key]);
        }

        add_settings_error(self::OPTION_NAME, "json_saved", "Settings saved.", "updated");

        return wp_json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function render_page(): void
    {
        if (!current_user_can("manage_options")) {
            return;
        }

        $current = get_option(self::OPTION_NAME, "");

        if (trim((string) $current) === "") {
            $current = wp_json_encode(
                self::defaults(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
        }
        ?>
        <div class="wrap">
            <h1>KT Conditional Phone &amp; Admin Subject</h1>
            <p>Paste localized strings as one JSON object.</p>
            <?php settings_errors(self::OPTION_NAME); ?>

            <form method="post" action="options.php">
                <?php settings_fields(self::SETTINGS_GROUP); ?>

                <textarea
                    name="<?php echo esc_attr(self::OPTION_NAME); ?>"
                    rows="18"
                    style="width:100%; font-family:monospace; font-size:13px;"
                ><?php echo esc_textarea($current); ?></textarea>

                <?php submit_button("Save JSON Settings"); ?>
            </form>
        </div>
        <?php
    }

    private function register_parent_menu(): void
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
            [$this, "render_page"],
            "dashicons-admin-plugins",
            58,
        );
    }
}
