<?php
/**
 * Plugin Name: Charitable PDF Receipt Sender
 * Description: Generates and emails a PDF receipt when a Charitable donation is completed.
 * Version: 0.1.0
 * Author: Ebrahim
 * Requires PHP: 7.4
 * Text Domain: charitable-pdf-receipt-sender
 */

if (! defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/src/Plugin.php';

CPRS\Plugin::bootstrap(__FILE__);
