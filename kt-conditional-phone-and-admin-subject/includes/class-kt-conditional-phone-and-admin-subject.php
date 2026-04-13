<?php

if (!defined("ABSPATH")) {
    exit();
}

final class KT_Conditional_Phone_And_Admin_Subject
{
    private static ?KT_Conditional_Phone_And_Admin_Subject $instance = null;

    public static function get_instance(): KT_Conditional_Phone_And_Admin_Subject
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        add_filter("woocommerce_billing_fields", [$this, "set_phone_requirement"], 10000);
        add_filter("woocommerce_checkout_fields", [$this, "set_checkout_phone_requirement"], 10000);
        add_filter("woocommerce_get_country_locale", [$this, "set_country_locale_phone_requirement"], 10000);
        add_action("woocommerce_store_api_checkout_update_order_meta", [
            $this,
            "validate_phone_for_store_api_checkout",
        ], 10000);
        add_action("wp_footer", [$this, "render_required_marker_script"], 10000);
        add_filter("woocommerce_email_subject_new_order", [
            $this,
            "filter_admin_new_order_subject",
        ], 20, 2);
    }

    public function set_phone_requirement(array $address_fields): array
    {
        $required = WC()->cart->needs_shipping();
        $address_fields["billing_phone"]["required"] = $required;
        $address_fields["billing_phone"]["validate"] = ["phone"];
        $address_fields["billing_phone"]["class"] = ["form-row-wide"];

        return $address_fields;
    }

    public function set_checkout_phone_requirement(array $fields): array
    {
        $required = WC()->cart->needs_shipping();
        $fields["billing"]["billing_phone"]["required"] = $required;
        $fields["billing"]["billing_phone"]["validate"] = ["phone"];
        $fields["billing"]["billing_phone"]["class"] = ["form-row-wide"];

        return $fields;
    }

    public function set_country_locale_phone_requirement(array $locale): array
    {
        $required = WC()->cart->needs_shipping();

        foreach ($locale as $country_code => $country_locale) {
            if (!isset($country_locale["phone"])) {
                continue;
            }

            $locale[$country_code]["phone"]["required"] = $required;
        }

        return $locale;
    }

    public function filter_admin_new_order_subject(
        string $subject,
        WC_Order $order,
    ): string
    {
        $total = $this->format_order_total($order);
        $separator = $this->subject_separator();
        $domain = $this->store_domain();
        $rest = $this->subject_without_store_prefix($subject);

        if ($this->order_has_physical_items($order)) {
            $physical_marker = KT_Conditional_Phone_And_Admin_Subject_Settings::get("admin_new_order_physical_marker");
            return "[" . $domain . "] " . strtoupper($physical_marker) . $separator . $total . $separator . $rest;
        }

        return "[" . $domain . "] " . $total . $separator . $rest;
    }

    public function validate_phone_for_store_api_checkout(WC_Order $order): void
    {
        if (!$order->needs_shipping_address()) {
            return;
        }

        if ($order->get_billing_phone() !== "") {
            return;
        }

        throw new Exception($this->required_phone_error_message());
    }

    public function render_required_marker_script(): void
    {
        if (!function_exists("is_checkout") || !is_checkout()) {
            return;
        }

        if (!WC()->cart->needs_shipping()) {
            return;
        }
        ?>
        <script>
            (function () {
                function updatePhoneFieldLabel() {
                    var field = document.querySelector("#billing_phone_field");
                    if (!field) {
                        return;
                    }

                    field.classList.add("validate-required");
                    var label = field.querySelector("label");
                    if (!label) {
                        return;
                    }

                    var optional = label.querySelector(".optional");
                    if (optional) {
                        optional.remove();
                    }

                    label.querySelectorAll("abbr.required, span.required").forEach(function (node) {
                        node.remove();
                    });

                    label.childNodes.forEach(function (node) {
                        if (node.nodeType !== Node.TEXT_NODE) {
                            return;
                        }

                        node.textContent = node.textContent.replace(/\s*\*+\s*$/u, "");
                    });

                    var abbr = document.createElement("span");
                    abbr.className = "required";
                    abbr.setAttribute("aria-hidden", "true");
                    abbr.textContent = "*";
                    label.appendChild(document.createTextNode(" "));
                    label.appendChild(abbr);
                }

                updatePhoneFieldLabel();
                document.body.addEventListener("updated_checkout", updatePhoneFieldLabel);
            })();
        </script>
        <?php
    }

    private function order_has_physical_items(WC_Order $order): bool
    {
        return $order->needs_shipping_address();
    }

    private function format_order_total(WC_Order $order): string
    {
        return html_entity_decode(
            wp_strip_all_tags(
                wc_price((float) $order->get_total(), [
                    "currency" => $order->get_currency(),
                ]),
            ),
        );
    }

    private function required_phone_error_message(): string
    {
        return KT_Conditional_Phone_And_Admin_Subject_Settings::get("phone_required_error");
    }

    private function subject_separator(): string
    {
        return " " . KT_Conditional_Phone_And_Admin_Subject_Settings::get("admin_subject_separator") . " ";
    }

    private function store_domain(): string
    {
        return (string) wp_parse_url(home_url(), PHP_URL_HOST);
    }

    private function subject_without_store_prefix(string $subject): string
    {
        return (string) preg_replace("/^\\[[^\\]]+\\]\\s*:?\s*/u", "", $subject);
    }
}
