<?php

namespace CPRS;

use CPRS\Admin\Actions;
use CPRS\Admin\SettingsPage;
use CPRS\Services\CharitableBridge;
use CPRS\Services\DiagnosticsService;
use CPRS\Services\EmailService;
use CPRS\Services\PdfService;
use CPRS\Utils\Guards;
use CPRS\Utils\Options;

class Plugin
{
    private static string $baseFile;

    public static function bootstrap(string $baseFile): void
    {
        self::$baseFile = $baseFile;
        self::registerAutoloader();

        add_action('plugins_loaded', [self::class, 'init']);
    }

    public static function init(): void
    {
        if (is_admin()) {
            $settingsPage = new SettingsPage();
            $settingsPage->register();

            $actions = new Actions(new DiagnosticsService(), new PdfService(), new EmailService());
            $actions->register();

            add_action('admin_notices', [self::class, 'renderCharitableNotice']);
        }

        if (! Guards::isCharitableActive()) {
            return;
        }

        $bridge = new CharitableBridge(new PdfService(), new EmailService());
        $bridge->register();
    }

    public static function baseFile(): string
    {
        return self::$baseFile;
    }

    public static function pluginDir(): string
    {
        return plugin_dir_path(self::$baseFile);
    }

    public static function pluginUrl(): string
    {
        return plugin_dir_url(self::$baseFile);
    }

    public static function renderCharitableNotice(): void
    {
        if (! current_user_can('manage_options') || Guards::isCharitableActive()) {
            return;
        }

        echo '<div class="notice notice-warning"><p>';
        echo esc_html__('Charitable PDF Receipt Sender requires the Charitable plugin to be active.', 'charitable-pdf-receipt-sender');
        echo '</p></div>';
    }

    private static function registerAutoloader(): void
    {
        spl_autoload_register(static function (string $class): void {
            if (strpos($class, 'CPRS\\') !== 0) {
                return;
            }

            $relative = str_replace(['CPRS\\', '\\'], ['', '/'], $class);
            $path = __DIR__ . '/' . $relative . '.php';

            if (file_exists($path)) {
                require_once $path;
            }
        });
    }
}
