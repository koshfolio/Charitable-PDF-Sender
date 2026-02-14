<?php

namespace CPRS\Services;

use CPRS\Utils\Guards;
use CPRS\Utils\Options;

class CharitableBridge
{
    private PdfService $pdf;
    private EmailService $email;

    public function __construct(PdfService $pdf, EmailService $email)
    {
        $this->pdf = $pdf;
        $this->email = $email;
    }

    public function register(): void
    {
        add_action('charitable_donation_status_changed', [$this, 'onStatusChanged'], 10, 3);
    }

    public function onStatusChanged($donationId, $oldStatus, $newStatus): void
    {
        if (! Guards::isEnabled()) {
            return;
        }

        if (! in_array((string) $newStatus, ['charitable-completed', 'completed'], true)) {
            return;
        }

        $settings = Options::get();
        if (empty($settings['resend_if_sent']) && $this->getMeta((int) $donationId, 'receipt_sent')) {
            return;
        }

        try {
            $data = $this->buildDonationData((int) $donationId);
            $templatePath = $this->pdf->resolveTemplatePath((int) ($settings['template_id'] ?? 0));
            if (! $templatePath) {
                return;
            }

            $bytes = $this->pdf->generate($data, $settings);
            $sent = $this->email->send((string) ($data['donor_email'] ?? ''), $data, $bytes, 'receipt-' . $data['receipt_number'] . '.pdf', $settings);

            if ($sent) {
                $this->updateMeta((int) $donationId, 'receipt_sent', 1);
            }
        } catch (\Throwable $e) {
            return;
        }
    }

    private function buildDonationData(int $donationId): array
    {
        $data = [
            'receipt_number' => (string) $donationId,
            'donor_name' => '',
            'donor_email' => '',
            'donation_date' => get_the_date('Y-m-d', $donationId) ?: wp_date('Y-m-d'),
            'campaign_name' => '',
            'donation_description' => '',
            'amount' => '',
            'sub_total' => '',
            'payment_method' => '',
            'donation_purpose' => '',
            'transaction_id' => '',
            'total_donation' => '',
        ];

        if (function_exists('charitable_get_donation')) {
            $donation = charitable_get_donation($donationId);
            if ($donation) {
                $data['receipt_number'] = method_exists($donation, 'get_donation_key') ? (string) $donation->get_donation_key() : $data['receipt_number'];
                $data['donor_name'] = method_exists($donation, 'get_donor_name') ? (string) $donation->get_donor_name() : '';
                $data['donor_email'] = method_exists($donation, 'get_donor_email') ? (string) $donation->get_donor_email() : '';
                $amount = method_exists($donation, 'get_total_donation_amount') ? (float) $donation->get_total_donation_amount() : 0;
                $formatted = function_exists('charitable_format_money') ? charitable_format_money($amount) : (string) $amount;
                $data['amount'] = $formatted;
                $data['sub_total'] = $formatted;
                $data['total_donation'] = $formatted;
                $data['campaign_name'] = method_exists($donation, 'get_campaign_title') ? (string) $donation->get_campaign_title() : '';
                $data['transaction_id'] = method_exists($donation, 'get_gateway_transaction_id') ? (string) $donation->get_gateway_transaction_id() : '';
                $data['payment_method'] = method_exists($donation, 'get_gateway_label') ? (string) $donation->get_gateway_label() : '';
                $data['donation_description'] = method_exists($donation, 'get_donation_summary') ? (string) $donation->get_donation_summary() : '';
                $data['donation_date'] = method_exists($donation, 'get_date') ? (string) $donation->get_date('Y-m-d') : $data['donation_date'];
            }
        }

        return $data;
    }

    private function getMeta(int $donationId, string $key)
    {
        return get_post_meta($donationId, $key, true);
    }

    private function updateMeta(int $donationId, string $key, $value): void
    {
        update_post_meta($donationId, $key, $value);
    }
}
