<?php
namespace ApexOmega\Core;

if (!defined('ABSPATH')) exit;

/**
 * رشد مداوم و طبیعی — هر ۱ ساعت (ریتم هرساعته 2026-10-21)
 * تا سقف 1.5M، اولویت بازدید/امتیاز سپس کامنت، افزایش تدریجی هرگز کاهش نیابد
 * fresh 500-10000 با rating همبسته + 3-10 کامنت، high-view مقیاس million ~50/day
 */
class Evergreen {
    const OPTION = 'apex_omega_evergreen';

    public static function get_settings(): array {
        $defaults = [
            'enabled' => true, // 2.0 hourly: پیش‌فرض روشن — ریتم هرساعته
            'rate_percent' => 0.035, // حفظ برای سازگاری — اکنون هرساعته تفسیر می‌شود
            'max_views' => 1500000,
            'interval' => 3600, // 1h
        ];
        $saved = get_option(self::OPTION, []);
        if (!is_array($saved)) $saved = [];
        return wp_parse_args($saved, $defaults);
    }

    public static function save_settings(array $data): void {
        $cur = self::get_settings();
        $new = [
            'enabled' => !empty($data['enabled']),
            'rate_percent' => isset($data['rate_percent']) ? max(0.005, min(0.3, (float)$data['rate_percent'])) : $cur['rate_percent'],
            'max_views' => isset($data['max_views']) ? max(100000, min(2000000, (int)$data['max_views'])) : $cur['max_views'],
            'interval' => 3600,
        ];
        update_option(self::OPTION, $new, false);
        Scheduler::ensure_cron();
    }

    public static function is_enabled(): bool {
        $s = self::get_settings();
        return !empty($s['enabled']);
    }

    // اجرا روی هر تیک هرساعته — با منطق افزایش تدریجی هرگز کاهشی
    public static function tick(): void {
        if (!self::is_enabled()) return;
        // hourly gate: ensure only once per hour even if scheduler still 5m
        $last = (int) get_option('apex_evergreen_last_tick_ts', 0);
        if ($last && (time() - $last < 3600)) {
            // allow verbose/manual via direct call? Scheduler fallback already checks 6h; for hourly gate we respect
            // but if called from CLI manual, bypass: check backtrace or explicit flag?
            // We'll respect gate for cron; manual calls should set bypass via update_option before call
            return;
        }
        update_option('apex_evergreen_last_tick_ts', time(), false);

        $s = self::get_settings();
        $base_rate = (float)$s['rate_percent'];
        $max = (int)$s['max_views'];

        $tier_multipliers = apply_filters('apex_omega_evergreen_tier_multipliers', [
            'tier_1' => 1.30,
            'tier_2' => 1.18,
            'tier_3' => 1.08,
            'tier_4' => 1.00,
            'tier_5' => 0.88,
            'tier_6' => 0.75,
            'tier_7' => 0.60,
            '' => 0.92,
        ]);
        global $wpdb;
        $blog = get_current_blog_id();
        $lastId = (int) get_option("apex_evergreen_last_id_{$blog}", 0);
        if (!$lastId) {
            $oldOffset = (int) get_option("apex_evergreen_offset_{$blog}", 0);
            if ($oldOffset) {
                $lastId = (int) $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type='post' AND post_status='publish' ORDER BY ID ASC LIMIT 1 OFFSET %d", $oldOffset));
                $lastId = $lastId ?: 0;
            }
        }
        // hourly batch: 40-60 to cover ~900 posts in 24h (40*24=960). Use 55 for safety.
        $batch = 55;
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p WHERE p.post_type='post' AND p.post_status='publish' AND p.ID > %d ORDER BY p.ID ASC LIMIT %d",
            $lastId, $batch
        ));
        if (empty($ids)) {
            $lastId = 0;
            $ids = $wpdb->get_col($wpdb->prepare(
                "SELECT p.ID FROM {$wpdb->posts} p WHERE p.post_type='post' AND p.post_status='publish' AND p.ID > %d ORDER BY p.ID ASC LIMIT %d",
                $lastId, $batch
            ));
        }
        if (!empty($ids)) $lastId = max($ids);
        update_option("apex_evergreen_last_id_{$blog}", $lastId, false);
        set_transient("apex_evergreen_offset_{$blog}", $lastId, HOUR_IN_SECONDS);

        if (empty($ids)) return;
        $exec = apex_omega_generate_uuid();
        $user_id = 0;

        // tier divisor map for rating correlation
        $divisor_map = [
            'tier_1' => 380,
            'tier_2' => 320,
            'tier_3' => 280,
            'tier_4' => 220,
            'tier_5' => 180,
            'tier_6' => 150,
            'tier_7' => 120,
            '' => 200,
        ];

        foreach ($ids as $pid) {
            $pid = (int)$pid;
            $cur_views = Adapter_Views::get_views($pid);
            if ($cur_views >= $max) continue;
            if ($cur_views < 0) $cur_views = 0;

            $tier = get_post_meta($pid, TierManager::META_TIER, true);
            if (!$tier) $tier = '';
            $tiers = TierManager::get_tiers();
            $tier_cap = null;
            $tier_def = null;
            foreach ($tiers as $tt) if ($tt['id'] === $tier) { $tier_cap = (int)$tt['views_max']; $tier_def = $tt; break; }
            // if no tier assigned, infer tier by current views
            if (!$tier_def) {
                foreach ($tiers as $tt) {
                    if ($cur_views >= (int)$tt['views_min'] && $cur_views <= (int)$tt['views_max']) { $tier_def = $tt; $tier = $tt['id']; $tier_cap = (int)$tt['views_max']; break; }
                }
                if (!$tier_cap) $tier_cap = $max;
            }
            $effective_max = $tier_cap ? min($max, $tier_cap) : $max;
            // اگر هدف زمان‌بندی داریم (آهنگ جدید)، تا اون هدف برو، نه لزوما سقف دسته
            $target_views_meta = (int) get_post_meta($pid, '_apex_target_views', true);
            if ($target_views_meta > 0) $effective_max = min($effective_max, $target_views_meta);
            if ($cur_views >= $effective_max) continue;

            $mult = $tier_multipliers[$tier] ?? $tier_multipliers[''] ?? 1.0;
            // weight for artist
            $weight = 50;
            try { $weight = \ApexOmega\Core\ArtistManager::get_weight_for_post($pid); } catch (\Throwable $e) {}
            $weight_factor = 0.8 + ($weight / 100) * 0.4; // 0.8-1.2

            // post age
            $ageDays = 0;
            $postDate = get_post_field('post_date_gmt', $pid);
            if ($postDate) $ageDays = max(0, (int)((time() - strtotime($postDate)) / DAY_IN_SECONDS));

            // calculate delta — hourly absolute scaling (never decrease, gradual)
            if ($cur_views < 500) {
                // fresh seeding 500-10000 per spec — one-time jump
                $new_views = mt_rand(500, 10000);
                // ensure correlation: if weight high, bias higher
                if ($weight >= 75) $new_views = mt_rand(2000, 10000);
                elseif ($weight >= 50) $new_views = mt_rand(800, 6000);
                // never below 500
                $inc = $new_views - $cur_views;
            } else {
                // hourly absolute delta table
                if ($cur_views < 10000) {
                    $base_delta = mt_rand(6, 18); // 144-432/day
                } elseif ($cur_views < 50000) {
                    $base_delta = mt_rand(4, 10);
                } elseif ($cur_views < 200000) {
                    $base_delta = mt_rand(3, 8);
                } elseif ($cur_views < 500000) {
                    $base_delta = mt_rand(2, 6);
                } else { // 500k+
                    $base_delta = mt_rand(1, 4); // million ~24-96/day → ~50 avg
                }
                // apply tier mult + weight + age decay (older slightly slower)
                // آهنگ جدید سریع‌تر رشد کنه تا فیک نباشه: هفته اول 2.2x، ماه اول 1.6x، بعد عادی
            $age_factor = 1.0;
            if ($ageDays < 7) $age_factor = 2.2;
            elseif ($ageDays < 14) $age_factor = 1.9;
            elseif ($ageDays < 30) $age_factor = 1.6;
            elseif ($ageDays < 90) $age_factor = 1.25;
            else $age_factor = max(0.7, 1 - ($ageDays / 365) * 0.3);
                $season = Simulator::seasonality_factor(time());
                // combine: base_delta * mult * weight_factor * age_factor * season (0.6-1.3)
                $season_norm = max(0.85, min(1.2, $season));
                $inc = (int) round($base_delta * $mult * $weight_factor * $age_factor * $season_norm);
                $inc = max(1, $inc);
                // clamp for fresh to avoid exploding beyond tier cap in one hour
                if ($cur_views < 10000) $inc = min($inc, 25);
                // S-curve cap near tier ceiling
                if ($tier_cap && $tier_cap > 0) {
                    $remaining = $tier_cap - $cur_views;
                    if ($remaining < $tier_cap * 0.08) {
                        $inc = (int) round($inc * ($remaining / ($tier_cap * 0.08)));
                        $inc = max(1, $inc);
                    }
                }
            }

            $new_views = $cur_views + $inc;
            if ($new_views > $effective_max) $new_views = $effective_max;
            // monotonic guard: never decrease (already inc >=1, but double-check)
            if ($new_views < $cur_views) $new_views = $cur_views + 1;
            // minimal drift for already high: ensure at least +1 if not at cap
            if ($new_views === $cur_views && $cur_views < $effective_max) $new_views = $cur_views + 1;

            // rating correlation — gradual increase never decrease
            $stats = Adapter_KKSR::get_stats($pid);
            $old_casts = (int)($stats['casts'] ?? 0);
            $old_avg = (float)($stats['avg'] ?? 0);
            $new_casts = $old_casts;
            $new_avg = $old_avg;

            // expected casts correlation via divisor
            $div = $divisor_map[$tier] ?? 200;
            $target_casts = (int) round($new_views / $div);
            // tier casts bounds
            if ($tier_def) {
                $target_casts = max((int)$tier_def['casts_min'], min((int)$tier_def['casts_max'], $target_casts));
            }
            if ($target_casts > $old_casts) {
                $need_cast = $target_casts - $old_casts;
                $max_inc_cast = ($cur_views >= 500000) ? 1 : 2;
                if ($cur_views < 10000) $max_inc_cast = 2;
                // gradual: at most 1-2 per hour
                $inc_cast = min($need_cast, mt_rand(0, $max_inc_cast));
                // 30% chance even if 0 to ensure progress for high need
                if ($inc_cast === 0 && $need_cast > 0 && mt_rand(1,100) <= 30) $inc_cast = 1;
                // also ensure fresh seeding gets at least 3-10 comments correlation? rating casts also should seed
                if ($cur_views < 500 && $old_casts < 3) {
                    $inc_cast = max($inc_cast, mt_rand(3, 6));
                }
                $new_casts = $old_casts + $inc_cast;
                // never exceed target
                if ($new_casts > $target_casts) $new_casts = $target_casts;
            }
            // avg correlation — lerp within tier range based on new_views position
            if ($tier_def) {
                $avg_min = (float)$tier_def['avg_min'];
                $avg_max = (float)$tier_def['avg_max'];
                $view_range = max(1, (int)$tier_def['views_max'] - (int)$tier_def['views_min']);
                $pos = ($new_views - (int)$tier_def['views_min']) / $view_range; // 0-1
                $pos = max(0, min(1, $pos));
                $target_avg = $avg_min + $pos * ($avg_max - $avg_min);
                // weight bias
                if ($weight >= 80) $target_avg += 0.05;
                $target_avg = round($target_avg, 1);
                $target_avg = max($avg_min, min($avg_max, $target_avg));
                if ($target_avg > $old_avg) {
                    // gradual: at most +0.1 per hour
                    $diff = $target_avg - $old_avg;
                    if ($diff > 0.1) $new_avg = round($old_avg + 0.1, 1);
                    else $new_avg = $target_avg;
                } else {
                    $new_avg = $old_avg; // never decrease
                }
                $new_avg = max($old_avg, $new_avg);
                $new_avg = min(5.0, $new_avg);
            } else {
                // no tier def: gentle increase
                if ($old_avg < 5.0 && $new_casts > $old_casts) {
                    $new_avg = min(5.0, round($old_avg + (mt_rand(0,5)/100), 1));
                    if ($new_avg < $old_avg) $new_avg = $old_avg;
                }
            }

            // compute ratings
            $detect = Adapter_KKSR::detect();
            $best = (int)($detect['best'] ?? 5); if ($best <1) $best=5;
            $new_ratings = $best >0 ? round($new_avg * $new_casts / $best * 5, 2) : (float)($stats['ratings'] ?? 0);
            // ensure ratings never decrease if casts/avg increased
            if ($new_ratings < (float)($stats['ratings'] ?? 0)) $new_ratings = (float)($stats['ratings'] ?? 0);

            // write atomically
            $wpdb->query('START TRANSACTION');
            try {
                update_post_meta($pid, Adapter_Views::META_KEY, $new_views);
                update_post_meta($pid, $detect['meta_casts'], $new_casts);
                update_post_meta($pid, $detect['meta_ratings'], $new_ratings);
                update_post_meta($pid, $detect['meta_avg'], $new_avg);
                update_post_meta($pid, '_apex_evergreen_last', current_time('mysql', true));
                // for fresh seeding, also set managed views marker
                if ($cur_views < 500) {
                    update_post_meta($pid, '_apex_managed_views', $new_views);
                }
                $wpdb->query('COMMIT');
                try { Simulator::record($pid, 'views', (float)$new_views, 'evergreen', '1h'); } catch (\Throwable $e) {}
            } catch (\Throwable $e) {
                $wpdb->query('ROLLBACK');
                continue;
            }
            // light history — once per hour tick all processed are logged via batch? Keep hourly log for trace
            // only log every 2nd hour to avoid spam but ensure views priority logged
            $hour_mod = (int) gmdate('G');
            if ($hour_mod % 2 === 0) {
                History::log($pid, 'views', $cur_views, $new_views, 'evergreen', $user_id, $exec, null, ['inc'=>$inc,'tier'=>$tier,'mult'=>$mult,'weight'=>$weight]);
                if ($new_casts !== $old_casts) {
                    History::log($pid, 'kksr_combined', wp_json_encode(['casts'=>$old_casts,'avg'=>$old_avg], JSON_UNESCAPED_UNICODE), wp_json_encode(['casts'=>$new_casts,'avg'=>$new_avg], JSON_UNESCAPED_UNICODE), 'evergreen', $user_id, $exec, null, ['tier'=>$tier]);
                }
            }
        }
        // tick count
        $tc = (int) get_option('apex_evergreen_tick_count_' . get_current_blog_id(), 0);
        update_option('apex_evergreen_tick_count_' . get_current_blog_id(), $tc+1, false);
    }
}
