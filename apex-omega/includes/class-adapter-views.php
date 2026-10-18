<?php
namespace ApexOmega\Core;

if (!defined('ABSPATH')) exit;

class Adapter_Views {
    const META_KEY = 'views';

    /**
     * کشف: آیا فیلد views وجود دارد؟ چه پست‌هایی دارند؟
     */
    public static function detect(): array {
        global $wpdb;
        $key = self::META_KEY;
        $count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s", $key));
        $sample = $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s ORDER BY post_id DESC LIMIT 1", $key));
        $has_non_numeric = false;
        if ($count > 0) {
            $bad = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value NOT REGEXP '^[0-9]+$'", $key));
            $has_non_numeric = $bad > 0;
        }
        return [
            'exists' => $count > 0,
            'total_posts_with_views' => $count,
            'sample_value' => $sample,
            'has_non_numeric' => $has_non_numeric,
            'meta_key' => $key,
            'strategy' => 'direct', // direct: نوشتن مستقیم روی views، زیرا قالب فقط همین را می‌خواند
            'limitation' => 'قالب فقط views را می‌خواند؛ تفکیک بازدید واقعی و مدیریتی در همین فیلد انجام می‌شود و تاریخچه در جدول اختصاصی APEX نگهداری خواهد شد.',
        ];
    }

    public static function get_views(int $post_id): int {
        $v = get_post_meta($post_id, self::META_KEY, true);
        if ($v === '' || $v === null) return 0;
        if (!is_numeric($v)) return 0;
        return max(0, (int) $v);
    }

    public static function set_views(int $post_id, int $views, string $source = 'manual', ?string $execution_id = null): bool {
        $views = max(0, $views);
        $old = self::get_views($post_id);

        global $wpdb;
        $wpdb->query('START TRANSACTION');
        try {
            update_post_meta($post_id, self::META_KEY, $views);
            // snapshot واقعی اگر اولین بار است
            $real_snapshot = get_post_meta($post_id, '_apex_real_views', true);
            if ($real_snapshot === '') {
                update_post_meta($post_id, '_apex_real_views', $old);
                update_post_meta($post_id, '_apex_real_views_captured_at', current_time('mysql', true));
            }
            update_post_meta($post_id, '_apex_managed_views', $views);
            update_post_meta($post_id, '_apex_views_last_source', $source);
            if ($execution_id) {
                update_post_meta($post_id, '_apex_views_last_exec', $execution_id);
            }

            do_action('apex_omega_views_updated', $post_id, $old, $views, $source, $execution_id);
            $wpdb->query('COMMIT');
            return true;
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            error_log('[APEX Ω] set_views failed: ' . $e->getMessage());
            return false;
        }
    }

    public static function get_real_snapshot(int $post_id): ?int {
        $v = get_post_meta($post_id, '_apex_real_views', true);
        if ($v === '') return null;
        return (int) $v;
    }
}
