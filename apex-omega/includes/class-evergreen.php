<?php
namespace ApexOmega\Core;

if (!defined('ABSPATH')) exit;

/**
 * رشد مداوم و طبیعی — هر 5 دقیقه کمی بازدید/امتیاز اضافه می‌کند
 * تا سقف 1.5M واقعی بماند، بصورت اصولی و روزانه برای تمام پست‌ها
 */
class Evergreen {
    const OPTION = 'apex_omega_evergreen';

    public static function get_settings(): array {
        $defaults = [
            'enabled' => false, // حرفه‌ای: پیش‌فرض خاموش (کاربر فعال کند) — ولی rate استاندارد 0.035٪
            'rate_percent' => 0.035, // حرفه‌ای: 0.035٪ هر 5دقیقه → ~8.5٪ روزانه (طبیعی جهانی)
            'max_views' => 1500000, // سقف اعتبار 1.5M — استاندارد
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
            'max_views' => isset($data['max_views']) ? max(100000, min(2000000, (int)$data['max_views'])) : $cur['max_views'],
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
        // برای جلوگیری از فشار، فقط 30 پست در هر تیک (چرخشی)
        global $wpdb;
        $blog = get_current_blog_id();
        // 1.9.4 JOB-03: transient به option پایدار — transient فقط کش، option منبع حقیقت (اگر flush شد offset صفر نمی‌شود)
        $offset = (int) get_option("apex_evergreen_offset_{$blog}", 0);
        $offset_transient = get_transient("apex_evergreen_offset_{$blog}");
        if ($offset_transient !== false) $offset = (int)$offset_transient; // کش تازه‌تر باشد
        $batch = 32; // حرفه‌ای: 32/تیک → 9216 روزانه
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p WHERE p.post_type='post' AND p.post_status='publish' ORDER BY p.ID ASC LIMIT %d OFFSET %d",
            $batch, $offset
        ));
        if (empty($ids)) {
            // دور کامل شد → از اول
            $offset = 0;
            $ids = $wpdb->get_col($wpdb->prepare(
                "SELECT p.ID FROM {$wpdb->posts} p WHERE p.post_type='post' AND p.post_status='publish' ORDER BY p.ID ASC LIMIT %d OFFSET %d",
                $batch, $offset
            ));
        }
        // offset بعدی
        $new_offset = $offset + $batch;
        set_transient("apex_evergreen_offset_{$blog}", $new_offset, HOUR_IN_SECONDS);
        update_option("apex_evergreen_offset_{$blog}", $new_offset, false); // پایدار

        if (empty($ids)) return;
        $exec = apex_omega_generate_uuid();
        $user_id = 0; // سیستم
        foreach ($ids as $pid) {
            $pid = (int)$pid;
            $cur_views = Adapter_Views::get_views($pid);
            if ($cur_views >= $max) continue;
            if ($cur_views < 100) $cur_views = 100; // حداقل
            $tier = get_post_meta($pid, TierManager::META_TIER, true);
            $mult = isset($tier_multipliers[$tier]) ? (float)$tier_multipliers[$tier] : (float)($tier_multipliers[''] ?? 1.0);
            $rate = $base_rate * $mult;
            // سقف تیره: اگر تیره دارد، از سقف تیره فراتر نرو (دقیق‌تر از سقف کلی)
            $tier_cap = null;
            if ($tier) {
                $tiers = TierManager::get_tiers();
                foreach ($tiers as $tt) if ($tt['id'] === $tier) { $tier_cap = (int)$tt['views_max']; break; }
            }
            $effective_max = $tier_cap ? min($max, $tier_cap) : $max;
            if ($cur_views >= $effective_max) continue;
            // رشد تصادفی ±30% حول rate
            $jitter = 0.7 + (mt_rand(0,60)/100); // 0.7 - 1.3
            $inc = max(1, (int) round($cur_views * $rate / 100 * $jitter));
            // حرفه‌ای: برای <10K سقف 10-18 بازدید هر تیک (طبیعی، نه جهشی)
            if ($cur_views < 10000) {
                $inc = min($inc, mt_rand(10, 18));
            }
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
