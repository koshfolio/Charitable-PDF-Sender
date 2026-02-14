<?php

namespace CPRS\Utils;

class Options
{
    public const KEY = 'cprs_settings';

    public static function defaults(): array
    {
        return [
            'enabled' => 1,
            'logging' => 0,
            'resend_if_sent' => 0,
            'sender_email' => '',
            'sender_name' => '',
            'reply_to' => '',
            'bcc_email' => '',
            'email_subject' => 'Your donation receipt #{receipt_number}',
            'email_body' => "Hello {donor_name},\n\nThank you for your donation of {amount}.\nReceipt #{receipt_number}",
            'template_id' => 0,
            'field_map' => self::defaultFieldMap(),
        ];
    }

    public static function get(): array
    {
        $saved = get_option(self::KEY, []);
        $merged = wp_parse_args(is_array($saved) ? $saved : [], self::defaults());
        $merged['field_map'] = wp_parse_args($merged['field_map'] ?? [], self::defaultFieldMap());

        return $merged;
    }

    public static function defaultFieldMap(): array
    {
        return [
            'receipt_number' => '',
            'donor_name' => '',
            'donation_date' => '',
            'campaign_name' => '',
            'donation_description' => '',
            'amount' => '',
            'sub_total' => '',
            'payment_method' => '',
            'donation_purpose' => '',
            'transaction_id' => '',
            'total_donation' => '',
        ];
    }
}
