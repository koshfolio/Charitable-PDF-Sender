<?php

namespace CPRS\Admin;

use CPRS\Services\DiagnosticsService;
use CPRS\Services\EmailService;
use CPRS\Services\PdfService;
use CPRS\Utils\Options;

class Actions
{
    private DiagnosticsService $diagnostics;
    private PdfService $pdf;
    private EmailService $email;

    public function __construct(DiagnosticsService $diagnostics, PdfService $pdf, EmailService $email)
    {
        $this->diagnostics = $diagnostics;
        $this->pdf = $pdf;
        $this->email = $email;
    }

    public function register(): void
    {
        add_action('admin_post_cprs_send_test_email', [$this, 'handleTestEmail']);
        add_action('admin_post_cprs_run_diagnostics', [$this, 'handleDiagnostics']);
    }

    public function handleTestEmail(): void
    {
        $this->assertAdminAction('cprs_send_test_email');
        $to = sanitize_email($_POST['test_email'] ?? '');
        $settings = Options::get();

        $dummy = [
            'receipt_number' => 'TEST-' . wp_generate_password(6, false),
            'donor_name' => 'Test Donor',
            'donation_date' => wp_date('Y-m-d'),
            'campaign_name' => 'Test Campaign',
            'amount' => '$50.00',
            'transaction_id' => 'txn_test_123',
            'total_donation' => '$50.00',
        ];

        try {
            $bytes = $this->pdf->generate($dummy, $settings);
            $result = $this->email->send($to, $dummy, $bytes, 'test-receipt.pdf', $settings);
            $status = $result ? 'success' : 'error';
            $message = $result ? 'Test email sent.' : 'wp_mail returned false.';
        } catch (\Throwable $e) {
            $status = 'error';
            $message = $e->getMessage();
        }

        $this->redirectWithNotice($status, $message);
    }

    public function handleDiagnostics(): void
    {
        $this->assertAdminAction('cprs_run_diagnostics');

        $results = $this->diagnostics->run(true);
        update_option('cprs_last_diagnostics', $results, false);

        $this->redirectWithNotice('success', 'Diagnostics completed.');
    }

    private function assertAdminAction(string $nonce): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer($nonce);
    }

    private function redirectWithNotice(string $status, string $message): void
    {
        $url = add_query_arg([
            'page' => 'cprs-settings',
            'tab' => 'testing',
            'cprs_status' => rawurlencode($status),
            'cprs_message' => rawurlencode($message),
        ], admin_url('options-general.php'));

        wp_safe_redirect($url);
        exit;
    }
}
