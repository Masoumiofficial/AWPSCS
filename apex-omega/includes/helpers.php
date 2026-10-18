<?php
if (!defined('ABSPATH')) exit;

if (!function_exists('apex_omega_generate_uuid')) {
    function apex_omega_generate_uuid(): string {
        try {
            $data = random_bytes(16);
            $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
            $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
            return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
        } catch (Exception $e) {
            return uniqid('apex_', true) . '-' . wp_generate_password(12, false);
        }
    }
}

if (!function_exists('apex_omega_now_mysql')) {
    function apex_omega_now_mysql(): string {
        return current_time('mysql', true); // GMT
    }
}

if (!function_exists('apex_omega_user_can_manage')) {
    function apex_omega_user_can_manage(int $user_id = 0): bool {
        if (!$user_id) $user_id = get_current_user_id();
        if (!$user_id) return false;
        // قابلیت اختصاصی + بازگشت به manage_options برای سازگاری
        if (user_can($user_id, 'apex_manage_statistics')) return true;
        return user_can($user_id, 'manage_options');
    }
}

if (!function_exists('apex_omega_sanitize_int')) {
    function apex_omega_sanitize_int($val): int {
        if (is_string($val)) $val = trim($val);
        if ($val === '' || $val === null) return 0;
        return (int) $val;
    }
}

if (!function_exists('apex_omega_format_number_fa')) {
    function apex_omega_format_number_fa($num): string {
        return number_format_i18n((float) $num);
    }
}
