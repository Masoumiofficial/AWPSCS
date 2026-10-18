<?php
namespace ApexOmega\Core;

if (!defined('ABSPATH')) exit;

/**
 * نگهبان خودکار پست‌های تازه — هر چند دقیقه سایت را چک می‌کند
 * پست‌های <10K بازدید (مخصوصاً تازه‌واردها از اسکریپت) را شارژ می‌کند تا سایت ضایع نباشد
 * خواننده تازه‌کار: 500-10K یک‌بار، خواننده ویژه: همین شارژ اولیه + Evergreen صعودی هر تیک
 */
class AutoWatch {
    const OPTION = 'apex_omega_autowatch';

    public static function get_settings(): array {
        $defaults = [
            'enabled' => true, // پیش‌فرض روشن — درخواست کاربر
            'threshold' => 10000, // زیر این = تازه، نیاز به شارژ
            'min_views' => 500,
            'max_views' => 10000,
            'batch' => 20, // هر تیک چند پست
            'only_recent_days' => 60, // فقط پست‌های 60 روز اخیر (اسکریپت تازه) — 0=همه
            'cooldown_hours' => 24, // هر پست حداکثر هر 24ساعت یک‌بار شارژ شود
        ];
        $saved = get_option(self::OPTION, []);
        if (!is_array($saved)) $saved = [];
        $out = wp_parse_args($saved, $defaults);
        $out['enabled'] = !empty($out['enabled']);
        $out['threshold'] = max(1000, min(50000, (int)$out['threshold']));
        $out['min_views'] = max(100, min(10000, (int)$out['min_views']));
        $out['max_views'] = max($out['min_views']+100, min(50000, (int)$out['max_views']));
        $out['batch'] = max(5, min(50, (int)$out['batch']));
        $out['only_recent_days'] = max(0, min(365, (int)$out['only_recent_days']));
        $out['cooldown_hours'] = max(1, min(168, (int)$out['cooldown_hours']));
        return $out;
    }

    public static function save_settings(array $data): void {
        $cur = self::get_settings();
        $new = [
            'enabled' => isset($data['enabled']) ? !empty($data['enabled']) : $cur['enabled'],
            'threshold' => isset($data['threshold']) ? (int)$data['threshold'] : $cur['threshold'],
            'min_views' => isset($data['min_views']) ? (int)$data['min_views'] : $cur['min_views'],
            'max_views' => isset($data['max_views']) ? (int)$data['max_views'] : $cur['max_views'],
            'batch' => isset($data['batch']) ? (int)$data['batch'] : $cur['batch'],
            'only_recent_days' => isset($data['only_recent_days']) ? (int)$data['only_recent_days'] : $cur['only_recent_days'],
            'cooldown_hours' => isset($data['cooldown_hours']) ? (int)$data['cooldown_hours'] : $cur['cooldown_hours'],
        ];
        update_option(self::OPTION, $new, false);
        Scheduler::ensure_cron();
    }

    public static function is_enabled(): bool {
        return !empty(self::get_settings()['enabled']);
    }

    // تولید شارژ اولیه همبسته: بازدید↔رای↔میانگین — بر اساس وزن خواننده
    public static function generate_initial(int $weight, int $min_views, int $max_views): array {
        $weight = max(0, min(100, $weight));
        // بازدید: خطی 500-10K با jitter
        $span = $max_views - $min_views;
        $base = $min_views + ($weight / 100) * $span;
        $jitter = mt_rand(-300, 300);
        $views = (int) round($base + $jitter);
        $views = max($min_views, min($max_views, $views));
        // casts: حدود views/120 تا views/80 + وزن
        $casts_min = max(8, (int) round($views / 140));
        $casts_max = max($casts_min+5, (int) round($views / 85));
        // برای خواننده ویژه، casts کمی بیشتر
        if ($weight >= 75) $casts_max = (int) round($casts_max * 1.25);
        $casts = mt_rand($casts_min, $casts_max);
        $casts = max(10, min(150, $casts));
        // avg: 3.4 پایه + 0.6 بر اساس وزن + jitter، برای ویژه تا 4.8
        $avg_base = 3.4 + ($weight / 100) * 1.4; // 3.4..4.8
        if ($weight >= 85) $avg_base += 0.15; // ویژه کمی بالاتر
        $avg_jitter = mt_rand(-15, 10) / 100; // -0.15..+0.10
        $avg = round($avg_base + $avg_jitter, 1);
        $avg = max(3.3, min(5.0, $avg));
        // ratings سازگار
        $best = (int) get_option('kksr_stars', 5); if ($best <1) $best=5;
        $ratings = round($avg * $casts / $best * 5, 2);
        return ['views'=>$views,'casts'=>$casts,'avg'=>$avg,'ratings'=>$ratings,'best'=>$best];
    }

    // تیک هر 5 دقیقه — پیدا کردن پست‌های کم‌بازدید و شارژ
    public static function tick(): void {
        if (!self::is_enabled()) return;
        $s = self::get_settings();
        $threshold = (int)$s['threshold'];
        $min_views = (int)$s['min_views'];
        $max_views = (int)$s['max_views'];
        $batch = (int)$s['batch'];
        $recent_days = (int)$s['only_recent_days'];
        $cooldown = (int)$s['cooldown_hours'] * HOUR_IN_SECONDS;

        global $wpdb;
        // پیدا کردن پست‌های کم‌بازدید — با LEFT JOIN برای پست‌های بدون meta views
        $where_recent = '';
        if ($recent_days > 0) {
            $cutoff = gmdate('Y-m-d H:i:s', time() - $recent_days * DAY_IN_SECONDS);
            $where_recent = $wpdb->prepare(" AND p.post_date_gmt >= %s ", $cutoff);
        }

        // کوئری اصلی: پست‌های منتشرشده که views < threshold یا ندارند
        // از NOT EXISTS برای cooldown استفاده می‌کنیم
        $sql = "
            SELECT p.ID, p.post_title, p.post_date_gmt, COALESCE(CAST(pm.meta_value AS UNSIGNED), 0) as cur_views
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key='views'
            WHERE p.post_type='post' AND p.post_status='publish'
            AND (pm.meta_value IS NULL OR CAST(pm.meta_value AS UNSIGNED) < %d)
            {$where_recent}
            AND NOT EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm2 
                WHERE pm2.post_id = p.ID 
                AND pm2.meta_key = '_apex_autowatch_last'
                AND pm2.meta_value >= %s
            )
            ORDER BY p.post_date_gmt DESC, p.ID DESC
            LIMIT %d
        ";
        $cooldown_cutoff = gmdate('Y-m-d H:i:s', time() - $cooldown);
        $rows = $wpdb->get_results($wpdb->prepare($sql, $threshold, $cooldown_cutoff, $batch), ARRAY_A);
        if (empty($rows)) return;

        $exec = apex_omega_generate_uuid();
        $user_id = 0; // سیستم
        $done = 0;
        foreach ($rows as $r) {
            $pid = (int)$r['ID'];
            $cur_views = (int)$r['cur_views'];
            if ($cur_views >= $threshold) continue;

            // وزن خواننده — اولویت اصلی
            $weight = ArtistManager::get_weight_for_post($pid);
            $gen = self::generate_initial($weight, $min_views, $max_views);

            // برای خواننده ویژه (>=75) اجازه بده کمی بالاتر از max_views برود تا با Evergreen هماهنگ باشد
            // اما برای تازه‌کار capped به max_views
            $target_views = $gen['views'];
            if ($weight >= 85) {
                // ویژه: اگر تازه باشد ولی خواننده ویژه، 20% بیشتر
                $target_views = min((int)($max_views * 1.6), $target_views + mt_rand(2000,5000));
                $target_views = max($target_views, $gen['views']);
            }

            $stats = Adapter_KKSR::get_stats($pid);
            $detect = Adapter_KKSR::detect();
            $best = $detect['best'];

            // اگر قبلاً مدیریت شده و میانگین بالایی دارد، حفظ کن
            // اما برای پست تازه، avg پایین را شارژ کن
            $new_avg = $gen['avg'];
            $new_casts = $gen['casts'];
            // اگر پست قبلاً casts دارد و بیشتر است، نگه دار
            if ($stats['casts'] > $new_casts) {
                $new_casts = $stats['casts'];
                // avg را کمی بهبود بده برای ویژه
                if ($weight >= 70 && $stats['avg'] < 4.5) $new_avg = max($new_avg, round($stats['avg'] + 0.1,1));
                else $new_avg = max($new_avg, $stats['avg']);
            }

            global $wpdb;
            $wpdb->query('START TRANSACTION');
            try {
                update_post_meta($pid, Adapter_Views::META_KEY, $target_views);
                // kksr
                $new_ratings = $new_avg * $new_casts / $best * 5;
                update_post_meta($pid, $detect['meta_casts'], $new_casts);
                update_post_meta($pid, $detect['meta_ratings'], $new_ratings);
                update_post_meta($pid, $detect['meta_avg'], $new_avg);
                // مارک برای cooldown و تاریخچه
                update_post_meta($pid, '_apex_autowatch_last', current_time('mysql', true));
                update_post_meta($pid, '_apex_autowatch_count', (int)get_post_meta($pid, '_apex_autowatch_count', true) + 1);
                update_post_meta($pid, '_apex_autowatch_weight', $weight);
                // برای Evergreen: تیره را ست کن تا ضریب درست بخورد
                $tier_hint = ArtistManager::weight_to_tier_hint($weight);
                // اگر قبلاً تیره دارد، نگه دار؛ وگرنه ست کن
                $cur_tier = get_post_meta($pid, TierManager::META_TIER, true);
                if (!$cur_tier) update_post_meta($pid, TierManager::META_TIER, $tier_hint);
                $wpdb->query('COMMIT');
            } catch (\Throwable $e) {
                $wpdb->query('ROLLBACK');
                continue;
            }
            // تاریخچه — منبع autowatch
            History::log($pid, 'views', $cur_views, $target_views, 'autowatch', $user_id, $exec, null, ['weight'=>$weight,'tier_hint'=>$tier_hint ?? '']);
            History::log($pid, 'kksr_combined', wp_json_encode(['casts'=>$stats['casts'],'avg'=>$stats['avg']], JSON_UNESCAPED_UNICODE), wp_json_encode(['casts'=>$new_casts,'avg'=>$new_avg], JSON_UNESCAPED_UNICODE), 'autowatch', $user_id, $exec, null, ['weight'=>$weight]);
            do_action('apex_omega_views_updated', $pid, $cur_views, $target_views, 'autowatch', $exec);
            $done++;
        }
        if ($done > 0) {
            update_option('apex_omega_autowatch_last_run', ['at'=>current_time('mysql', true),'done'=>$done,'exec'=>$exec], false);
            error_log("[APEX Ω] AutoWatch: {$done} posts boosted (threshold {$threshold})");
        }
    }
}
