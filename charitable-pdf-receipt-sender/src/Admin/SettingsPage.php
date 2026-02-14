<?php

namespace CPRS\Admin;

use CPRS\Plugin;
use CPRS\Utils\Options;

class SettingsPage
{
    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenu']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function addMenu(): void
    {
        add_options_page(
            __('PDF Receipts', 'charitable-pdf-receipt-sender'),
            __('PDF Receipts', 'charitable-pdf-receipt-sender'),
            'manage_options',
            'cprs-settings',
            [$this, 'render']
        );
    }

    public function registerSettings(): void
    {
        register_setting(Options::KEY, Options::KEY, [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize'],
            'default' => Options::defaults(),
        ]);
    }

    public function enqueueAssets(string $hook): void
    {
        if ($hook !== 'settings_page_cprs-settings') {
            return;
        }

        wp_enqueue_style('cprs-admin', Plugin::pluginUrl() . 'assets/admin.css', [], '0.1.0');
        wp_enqueue_media();
    }

    public function sanitize(array $input): array
    {
        $defaults = Options::defaults();
        $output = wp_parse_args($input, $defaults);

        foreach (['enabled', 'logging', 'resend_if_sent'] as $flag) {
            $output[$flag] = empty($output[$flag]) ? 0 : 1;
        }

        foreach (['sender_email', 'reply_to', 'bcc_email'] as $emailField) {
            $output[$emailField] = sanitize_email((string) ($output[$emailField] ?? ''));
        }

        $output['sender_name'] = sanitize_text_field((string) ($output['sender_name'] ?? ''));
        $output['email_subject'] = sanitize_text_field((string) ($output['email_subject'] ?? ''));
        $output['email_body'] = wp_kses_post((string) ($output['email_body'] ?? ''));
        $output['template_id'] = absint($output['template_id'] ?? 0);

        $map = [];
        foreach (Options::defaultFieldMap() as $key => $value) {
            $map[$key] = sanitize_text_field((string) ($output['field_map'][$key] ?? ''));
        }
        $output['field_map'] = $map;

        return $output;
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $settings = Options::get();
        $tab = sanitize_key($_GET['tab'] ?? 'general');
        $tabs = ['general' => 'General', 'email' => 'Email', 'template' => 'PDF Template', 'mapping' => 'Field Mapping', 'testing' => 'Testing'];

        echo '<div class="wrap cprs-wrap"><h1>Charitable PDF Receipt Sender</h1>';

        if (! empty($_GET['cprs_message'])) {
            $class = (($_GET['cprs_status'] ?? '') === 'success') ? 'notice notice-success' : 'notice notice-error';
            echo '<div class="' . esc_attr($class) . '"><p>' . esc_html(rawurldecode((string) $_GET['cprs_message'])) . '</p></div>';
        }
        echo '<nav class="nav-tab-wrapper">';
        foreach ($tabs as $slug => $label) {
            $active = $tab === $slug ? ' nav-tab-active' : '';
            $url = esc_url(add_query_arg(['page' => 'cprs-settings', 'tab' => $slug], admin_url('options-general.php')));
            echo '<a class="nav-tab' . esc_attr($active) . '" href="' . $url . '">' . esc_html($label) . '</a>';
        }
        echo '</nav>';

        if ($tab === 'testing') {
            $this->renderTestingTab();
            echo '</div>';
            return;
        }

        echo '<form method="post" action="options.php">';
        settings_fields(Options::KEY);
        echo '<input type="hidden" name="' . esc_attr(Options::KEY) . '[template_id]" id="cprs_template_id" value="' . esc_attr((string) $settings['template_id']) . '">';

        if ($tab === 'general') {
            $this->renderCheckbox('enabled', 'Enable plugin', $settings);
            $this->renderCheckbox('logging', 'Enable logging', $settings);
            $this->renderCheckbox('resend_if_sent', 'Resend even if already sent', $settings);
        } elseif ($tab === 'email') {
            $this->renderText('sender_email', 'Sender email', $settings);
            $this->renderText('sender_name', 'Sender name', $settings);
            $this->renderText('reply_to', 'Reply-To', $settings);
            $this->renderText('bcc_email', 'Bcc', $settings);
            $this->renderText('email_subject', 'Email subject', $settings);
            echo '<p><label>Email body</label><textarea name="' . esc_attr(Options::KEY) . '[email_body]" rows="8" class="large-text">' . esc_textarea((string) $settings['email_body']) . '</textarea></p>';
        } elseif ($tab === 'template') {
            $name = $settings['template_id'] ? basename((string) get_attached_file((int) $settings['template_id'])) : 'No template selected';
            echo '<p><strong>Selected:</strong> ' . esc_html($name) . '</p>';
            echo '<button type="button" class="button" id="cprs_upload_template">Upload / Replace template</button>';
            echo '<script>jQuery(function($){$("#cprs_upload_template").on("click",function(e){e.preventDefault();const frame=wp.media({title:"Select PDF Template",button:{text:"Use Template"},multiple:false,library:{type:"application/pdf"}});frame.on("select",function(){const a=frame.state().get("selection").first().toJSON();$("#cprs_template_id").val(a.id);location.reload();});frame.open();});});</script>';
        } elseif ($tab === 'mapping') {
            foreach (Options::defaultFieldMap() as $key => $unused) {
                $value = $settings['field_map'][$key] ?? '';
                echo '<p><label><strong>' . esc_html($key) . '</strong></label><br><input class="regular-text" name="' . esc_attr(Options::KEY) . '[field_map][' . esc_attr($key) . ']" value="' . esc_attr($value) . '"></p>';
            }
        }

        submit_button();
        echo '</form></div>';
    }

    private function renderTestingTab(): void
    {
        $diag = get_option('cprs_last_diagnostics', []);
        echo '<h2>Send Test Email</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('cprs_send_test_email');
        echo '<input type="hidden" name="action" value="cprs_send_test_email">';
        echo '<p><input name="test_email" class="regular-text" value="' . esc_attr((string) wp_get_current_user()->user_email) . '"></p>';
        submit_button('Send Test Email');
        echo '</form>';

        echo '<h2>Diagnostics</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('cprs_run_diagnostics');
        echo '<input type="hidden" name="action" value="cprs_run_diagnostics">';
        submit_button('Run Diagnostics', 'secondary');
        echo '</form>';

        if (! empty($diag)) {
            echo '<h3>Last Diagnostics (' . esc_html($diag['timestamp'] ?? '') . ')</h3><pre>' . esc_html(wp_json_encode($diag, JSON_PRETTY_PRINT)) . '</pre>';
        }
    }

    private function renderCheckbox(string $key, string $label, array $settings): void
    {
        $checked = ! empty($settings[$key]) ? 'checked' : '';
        echo '<p><label><input type="checkbox" name="' . esc_attr(Options::KEY) . '[' . esc_attr($key) . ']" value="1" ' . $checked . '> ' . esc_html($label) . '</label></p>';
    }

    private function renderText(string $key, string $label, array $settings): void
    {
        echo '<p><label>' . esc_html($label) . '</label><br><input class="regular-text" name="' . esc_attr(Options::KEY) . '[' . esc_attr($key) . ']" value="' . esc_attr((string) $settings[$key]) . '"></p>';
    }
}
