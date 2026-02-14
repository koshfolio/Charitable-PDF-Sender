<?php

namespace CPRS\Services;

class EmailService
{
    public function send(string $to, array $data, string $pdfBytes, string $filename, array $settings): bool
    {
        if (! is_email($to)) {
            return false;
        }

        $subject = $this->replacePlaceholders((string) ($settings['email_subject'] ?? ''), $data);
        $body = $this->replacePlaceholders((string) ($settings['email_body'] ?? ''), $data);

        $headers = [];
        $senderEmail = sanitize_email((string) ($settings['sender_email'] ?? ''));
        $senderName = sanitize_text_field((string) ($settings['sender_name'] ?? ''));

        if (! empty($settings['reply_to']) && is_email($settings['reply_to'])) {
            $headers[] = 'Reply-To: ' . sanitize_email((string) $settings['reply_to']);
        }
        if (! empty($settings['bcc_email']) && is_email($settings['bcc_email'])) {
            $headers[] = 'Bcc: ' . sanitize_email((string) $settings['bcc_email']);
        }

        $isHtml = $body !== wp_strip_all_tags($body);
        $headers[] = 'Content-Type: ' . ($isHtml ? 'text/html; charset=UTF-8' : 'text/plain; charset=UTF-8');

        $tmpFile = wp_tempnam($filename);
        if (! $tmpFile) {
            return false;
        }

        file_put_contents($tmpFile, $pdfBytes);

        $fromFilter = static function () use ($senderEmail) {
            return $senderEmail;
        };
        $nameFilter = static function () use ($senderName) {
            return $senderName;
        };

        if ($senderEmail) {
            add_filter('wp_mail_from', $fromFilter);
        }
        if ($senderName) {
            add_filter('wp_mail_from_name', $nameFilter);
        }

        try {
            $sent = wp_mail($to, $subject, $body, $headers, [$tmpFile]);
        } finally {
            if ($senderEmail) {
                remove_filter('wp_mail_from', $fromFilter);
            }
            if ($senderName) {
                remove_filter('wp_mail_from_name', $nameFilter);
            }
            @unlink($tmpFile);
        }

        return (bool) $sent;
    }

    private function replacePlaceholders(string $text, array $data): string
    {
        $pairs = [
            '{donor_name}' => (string) ($data['donor_name'] ?? ''),
            '{campaign_name}' => (string) ($data['campaign_name'] ?? ''),
            '{amount}' => (string) ($data['amount'] ?? ''),
            '{donation_date}' => (string) ($data['donation_date'] ?? ''),
            '{transaction_id}' => (string) ($data['transaction_id'] ?? ''),
            '{receipt_number}' => (string) ($data['receipt_number'] ?? ''),
        ];

        return strtr($text, $pairs);
    }
}
