<?php
namespace ApexOmega\Core;

if (!defined('ABSPATH')) exit;

/**
 * رشد مداوم و طبیعی — هر 5 دقیقه کمی بازدید/امتیاز اضافه می‌کند
 * تا سقف 10M واقعی بماند، بصورت اصولی و روزانه برای تمام پست‌ها
 */
class Evergreen {
    const OPTION = 'apex_omega_evergreen';

    public static function get_settings(): array {
        $defaults = [
            'enabled' => false, // حرفه‌ای: پیش‌فرض خاموش (کاربر فعال کند) — ولی rate استاندارد 0.035٪
            'rate_percent' => 0.035, // حرفه‌ای: 0.035٪ هر 5دقیقه → ~8.5٪ روزانه (طبیعی جهانی)
            'max_views' => 10000000, // سقف اعتبار 10M — استاندارد (قبلا 1.5M)
            'interval' => 300,
        ];
        $saved = get_option(self::OPTION, []);
        if (!is_array($saved)) $saved = [];
        return wp_parse_args($saved, $defaults);
    }

    public static function save_settings(array $data): void {
        $cur = self::get_settings();
        $new = [
            'enabled' => !empty($data['enabled']),
            'rate_percent' => isset($data['rate_percent']) ? max(0.01, min(0.3, (float)$data['rate_percent'])) : $cur['rate_percent'],
            'max_views' => isset($data['max_views']) ? max(100000, min(15000000, (int)$data['max_views'])) : $cur['max_views'],
            'interval' => 300,
        ];
        update_option(self::OPTION, $new, false);
        // کرون تیک قبلاً هر 5 دقیقه هست؛ فقط تنظیم مجدد اگر لازم بود
        Scheduler::ensure_cron();
    }

    public static function is_enabled(): bool {
        $s = self::get_settings();
        return !empty($s['enabled']);
    }

    // اجرا روی هر تیک 5دقیقه‌ای — با ریتم تیره‌محور (Tier-aware)
    public static function tick(): void {
        if (!self::is_enabled()) return;
        $s = self::get_settings();
        $base_rate = (float)$s['rate_percent'];
        $max = (int)$s['max_views'];
        // ضریب تیره: الماس سریع‌تر، تازه کندتر — طبیعی و قابل کنترل
        $tier_multipliers = apply_filters('apex_omega_evergreen_tier_multipliers', [
            'tier_1' => 1.30, // الماس 30٪ سریع‌تر (حرفه‌ای تعدیل شده)
            'tier_2' => 1.18,
            'tier_3' => 1.08,
            'tier_4' => 1.00, // مرجع
            'tier_5' => 0.88,
            'tier_6' => 0.75,
            'tier_7' => 0.60, // تازه کندتر — طبیعی
            '' => 0.92,
        ]);
        // 2.0-beta PERF-02: cursor pagination (ID > last_id) به‌جای OFFSET — برای 10K+ بدون کندی
        global $wpdb;
        $blog = get_current_blog_id();
        $lastId = (int) get_option("apex_evergreen_last_id_{$blog}", 0);
        // سازگاری با نسخه قبلی offset
        if (!$lastId) {
            $oldOffset = (int) get_option("apex_evergreen_offset_{$blog}", 0);
            if ($oldOffset) {
                // تخمین lastId از offset (برای مهاجرت یک‌باره)
                $lastId = (int) $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type='post' AND post_status='publish' ORDER BY ID ASC LIMIT 1 OFFSET %d", $oldOffset));
                $lastId = $lastId ?: 0;
            }
        }
        $batch = 32; // 9216 روزانه
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p WHERE p.post_type='post' AND p.post_status='publish' AND p.ID > %d ORDER BY p.ID ASC LIMIT %d",
            $lastId, $batch
        ));
        if (empty($ids)) {
            // دور کامل شد → از اول
            $lastId = 0;
            $ids = $wpdb->get_col($wpdb->prepare(
                "SELECT p.ID FROM {$wpdb->posts} p WHERE p.post_type='post' AND p.post_status='publish' AND p.ID > %d ORDER BY p.ID ASC LIMIT %d",
                $lastId, $batch
            ));
        }
        // ذخیره cursor جدید
        if (!empty($ids)) $lastId = max($ids);
        update_option("apex_evergreen_last_id_{$blog}", $lastId, false);
        set_transient("apex_evergreen_offset_{$blog}", $lastId, HOUR_IN_SECONDS); // سازگاری

        if (empty($ids)) return;
        $exec = apex_omega_generate_uuid();
        $user_id = 0; // سیستم
        foreach ($ids as $pid) {
            $pid = (int)$pid;
            $cur_views = Adapter_Views::get_views($pid);
            if ($cur_views >= $max) continue;
            if ($cur_views < 100) $cur_views = 100; // حداقل
                // 2.0-alpha: Simulator S-curve (جایگزین فرمول خطی قدیمی)
            $tier_cap = null;
            if ($tier) {
                $tiers = TierManager::get_tiers();
                foreach ($tiers as $tt) if ($tt['id'] === $tier) { $tier_cap = (int)$tt['views_max']; break; }
            }
            $effective_max = $tier_cap ? min($max, $tier_cap) : $max;
            if ($cur_views >= $effective_max) continue;

            // پروفایل آرتیست از جدول profiles (اگر نباشد fallback به growth پیش‌فرض)
            $profile = ['growth' => $base_rate, 'decay' => 0.002];
            $ageDays = 0;
            // سن پست: از post_date_gmt
            $postDate = get_post_field('post_date_gmt', $pid);
            if ($postDate) $ageDays = max(0, (int) ((time() - strtotime($postDate)) / DAY_IN_SECONDS));
            // تلاش برای یافتن پروفایل آرتیست
            try {
                global $wpdb;
                $artistTerm = null;
                // از ArtistManager وزن بگیر و پروفایل را پیدا کن
                $weight = \ApexOmega\Core\ArtistManager::get_weight_for_post($pid);
                // وزن 50 => growth 0.035, وزن 90 => 0.05 (پویا)
                $profile['growth'] = 0.02 + ($weight / 100) * 0.03;
            } catch (\Throwable $e) {}

            $inc = Simulator::next_delta($cur_views, $tier ?? '', $profile, $ageDays, $base_rate);
            $new_views = $cur_views + $inc;
            if ($new_views > $effective_max) $new_views = $effective_max;
            // امتیاز هم کمی رشد کند
            $stats = Adapter_KKSR::get_stats($pid);
            $new_casts = $stats['casts'];
            $new_avg = $stats['avg'];
            if ($stats['casts'] > 0) {
                $cast_inc = max(0, (int) round($inc / 800)); // هر 800 بازدید ≈ 1 رأی جدید
                if ($cast_inc > 0) {
                    $new_casts = $stats['casts'] + $cast_inc;
                    if ($new_avg < 5.0) {
                        $new_avg = min(5.0, round($new_avg + (mt_rand(0,2)/100), 1));
                    }
                }
            }
            // نوشتن اتمیک
            $detect = Adapter_KKSR::detect();
            $best = $detect['best'];
            $new_ratings = $new_avg * $new_casts / $best * 5;
            global $wpdb;
            $wpdb->query('START TRANSACTION');
            try {
                update_post_meta($pid, Adapter_Views::META_KEY, $new_views);
                update_post_meta($pid, $detect['meta_casts'], $new_casts);
                update_post_meta($pid, $detect['meta_ratings'], $new_ratings);
                update_post_meta($pid, $detect['meta_avg'], $new_avg);
                update_post_meta($pid, '_apex_evergreen_last', current_time('mysql', true));
                $wpdb->query('COMMIT');
                // 2.0-alpha: ثبت timeseries
                try { Simulator::record($pid, 'views', (float)$new_views, 'evergreen', '5m'); } catch (\Throwable $e) {}
            } catch (\Throwable $e) {
                $wpdb->query('ROLLBACK');
                continue;
            }
            // لاگ سبک (هر 12 تیک ≈ هر ساعت)
            $tick_count = (int) get_option('apex_evergreen_tick_count_' . get_current_blog_id(), 0);
            if ($tick_count % 12 === 0) {
                History::log($pid, 'views', $cur_views, $new_views, 'evergreen', $user_id, $exec, null, ['rate'=>$rate,'inc'=>$inc,'tier'=>$tier,'mult'=>$mult]);
            }
        }
        $tc = (int) get_option('apex_evergreen_tick_count_' . get_current_blog_id(), 0);
        update_option('apex_evergreen_tick_count_' . get_current_blog_id(), $tc+1, false);
    }
}
