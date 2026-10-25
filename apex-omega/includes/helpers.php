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

if (!function_exists('apex_omega_encrypt')) {
    function apex_omega_encrypt(string $plaintext): string {
        if ($plaintext === '') return '';
        $key_raw = (defined('AUTH_KEY') ? AUTH_KEY : '') . (defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : '') . (defined('LOGGED_IN_SALT') ? LOGGED_IN_SALT : '');
        if (strlen($key_raw) < 16) $key_raw = $key_raw . 'apex-omega-fallback-salt-2024';
        $key = hash('sha256', $key_raw, true);
        if (function_exists('openssl_encrypt')) {
            $iv = openssl_random_pseudo_bytes(16);
            $enc = openssl_encrypt($plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
            if ($enc !== false) return 'enc1:' . base64_encode($iv . $enc);
        }
        // امن: اگر OpenSSL نیست، ذخیره نکن — plaintext هرگز (P0 SEC-05 1.9.3)
        error_log('[APEX Ω] encrypt: OpenSSL unavailable — API key not saved securely');
        return '';
    }
}
if (!function_exists('apex_omega_decrypt')) {
    function apex_omega_decrypt(string $ciphertext): string {
        if ($ciphertext === '') return '';
        if (strpos($ciphertext, 'enc1:') === 0) {
            $raw = base64_decode(substr($ciphertext, 5), true);
            if ($raw === false) return '';
            $key_raw = (defined('AUTH_KEY') ? AUTH_KEY : '') . (defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : '') . (defined('LOGGED_IN_SALT') ? LOGGED_IN_SALT : '');
            if (strlen($key_raw) < 16) $key_raw = $key_raw . 'apex-omega-fallback-salt-2024';
            $key = hash('sha256', $key_raw, true);
            $iv = substr($raw, 0, 16);
            $enc = substr($raw, 16);
            if (function_exists('openssl_decrypt')) {
                $dec = openssl_decrypt($enc, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
                if ($dec !== false) return $dec;
            }
            return '';
        }
        if (strpos($ciphertext, 'b64:') === 0) {
            $d = base64_decode(substr($ciphertext, 4), true);
            return $d !== false ? $d : '';
        }
        // legacy plain text — دیگر ذخیره نمی‌شود، فقط برای سازگاری خواندن قدیمی
        return $ciphertext;
    }
}
if (!function_exists('apex_omega_lock_ttl')) {
    function apex_omega_lock_ttl(int $count, int $min = 30, int $max = 600, int $per_item = 2): int {
        $ttl = $count * $per_item + 15;
        if ($ttl < $min) $ttl = $min;
        if ($ttl > $max) $ttl = $max;
        return $ttl;
    }
}

if (!function_exists('apex_omega_never_decrease_enabled')) {
    function apex_omega_never_decrease_enabled(): bool {
        $s = get_option('apex_omega_settings', []);
        // پیش‌فرض فعال برای جلوگیری از کاهش ناخواسته پس از آپدیت
        if (!is_array($s)) return true;
        if (!array_key_exists('never_decrease_views', $s)) return true;
        return !empty($s['never_decrease_views']);
    }
}
if (!function_exists('apex_omega_guard_views')) {
    /**
     * محافظ کاهش — اگر never_decrease فعال باشد و مقدار جدید کمتر از قدیمی باشد، قدیمی را نگه دار
     * اما برای شکستن یکنواختی (مثل 600k تکراری) یک افزایش ریز 0.8–2.8٪ بر اساس ID اضافه می‌کند
     * برای automated sources فقط؛ manual با $allow_decrease=true فراخوانی شود
     */
    function apex_omega_guard_views(int $old, int $new, bool $allow_decrease = false, ?int $post_id = null): int {
        if ($allow_decrease) return $new;
        if (!apex_omega_never_decrease_enabled()) return $new;
        if ($new >= $old) return $new;
        // به جای نگه داشتن مقدار یکسان 600k برای همه، یک تنوع صعودی کوچک اضافه کن — هنوز هرگز کم نمی‌شود
        $pidHash = $post_id ? ((($post_id * 9301 + 49297) % 233280) / 233280) : (mt_rand(0,1000)/1000);
        $inc = (int) round($old * (0.008 + $pidHash * 0.022)); // 0.8% تا 3% — برای 600k => 4.8k–18k
        $inc = max(1200, $inc); // حداقل 1.2k برای دیده شدن تفاوت
        return $old + $inc;
    }
}
if (!function_exists('apex_omega_guard_stats')) {
    function apex_omega_guard_stats(array $old_stats, array $new_vals, bool $allow_decrease = false, ?int $post_id = null): array {
        if ($allow_decrease || !apex_omega_never_decrease_enabled()) return $new_vals;
        if (isset($new_vals['casts']) && $new_vals['casts'] < (int)($old_stats['casts'] ?? 0)) $new_vals['casts'] = (int)$old_stats['casts'];
        if (isset($new_vals['avg']) && $new_vals['avg'] < (float)($old_stats['avg'] ?? 0)) $new_vals['avg'] = (float)$old_stats['avg'];
        if (isset($new_vals['ratings']) && $new_vals['ratings'] < (float)($old_stats['ratings'] ?? 0)) $new_vals['ratings'] = (float)$old_stats['ratings'];
        if (isset($new_vals['views']) && $new_vals['views'] < (int)($old_stats['views'] ?? 0)) {
            $pidHash = $post_id ? ((($post_id * 9301 + 49297) % 233280) / 233280) : (mt_rand(0,1000)/1000);
            $inc = (int) round((int)$old_stats['views'] * (0.008 + $pidHash * 0.022));
            $inc = max(1200, $inc);
            $new_vals['views'] = (int)$old_stats['views'] + $inc;
        }
        return $new_vals;
    }
}

