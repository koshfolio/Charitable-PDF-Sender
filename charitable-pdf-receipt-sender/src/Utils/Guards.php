<?php

namespace CPRS\Utils;

class Guards
{
    public static function isCharitableActive(): bool
    {
        return function_exists('charitable_get_donation') || post_type_exists('donation');
    }

    public static function isEnabled(): bool
    {
        $settings = Options::get();

        return ! empty($settings['enabled']);
    }
}
