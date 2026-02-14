<?php

namespace CPRS\Services;

use CPRS\Plugin;
use CPRS\Utils\Options;

class DiagnosticsService
{
    public function run(bool $attemptMail = false): array
    {
        $settings = Options::get();
        $pdf = new PdfService();
        $autoload = Plugin::pluginDir() . 'vendor/autoload.php';
        $templatePath = $pdf->resolveTemplatePath((int) ($settings['template_id'] ?? 0));

        $results = [
            'timestamp' => wp_date('c'),
            'enabled' => ! empty($settings['enabled']),
            'template_id' => (int) ($settings['template_id'] ?? 0),
            'template_exists' => $templatePath !== '',
            'resolved_template_path' => $templatePath,
            'vendor_autoload_path' => $autoload,
            'vendor_autoload_exists' => file_exists($autoload),
            'test_handler_reached' => true,
            'wp_mail_attempted' => false,
            'wp_mail_result' => false,
        ];

        try {
            $pdf->loadPdfDependencies();
            $results['class_exists_FPDM'] = class_exists('FPDM');
            $results['class_exists_FPDF'] = class_exists('FPDF');
            $results['class_exists_FPDI'] = class_exists('FPDI') || class_exists('setasign\\Fpdi\\Fpdi');

            $bytes = $pdf->generate([
                'receipt_number' => 'DIAG-001',
                'donor_name' => 'Diagnostic User',
                'donation_date' => wp_date('Y-m-d'),
                'campaign_name' => 'Diagnostic Campaign',
                'amount' => '$1.00',
                'sub_total' => '$1.00',
                'transaction_id' => 'diag_txn',
                'total_donation' => '$1.00',
            ], $settings);

            $results['pdf_generation_succeeded'] = true;
            $results['pdf_byte_size'] = strlen($bytes);
            $results['exception'] = '';

            if ($attemptMail) {
                $email = new EmailService();
                $target = wp_get_current_user()->user_email ?: get_option('admin_email');
                $mailResult = $email->send($target, ['donor_name' => 'Diagnostic User', 'amount' => '$1.00', 'donation_date' => wp_date('Y-m-d'), 'campaign_name' => 'Diagnostic Campaign', 'transaction_id' => 'diag_txn', 'receipt_number' => 'DIAG-001'], $bytes, 'diagnostic.pdf', $settings);
                $results['wp_mail_attempted'] = true;
                $results['wp_mail_result'] = (bool) $mailResult;
            }
        } catch (\Throwable $e) {
            $results['pdf_generation_succeeded'] = false;
            $results['pdf_byte_size'] = 0;
            $results['exception'] = $e->getMessage();
            $results['wp_mail_attempted'] = false;
            $results['wp_mail_result'] = false;
        }

        return $results;
    }
}
