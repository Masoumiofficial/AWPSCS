<?php
namespace ApexOmega\Core;

if (!defined('ABSPATH')) exit;

/**
 * Simulator 2.0-alpha — S-curve + seasonality + decay
 * نسخه آلفا: فقط منطق ریاضی، بدون وابستگی به queue
 */
class Simulator {
    const VERSION = '2.0.0-alpha';

    /**
     * محاسبه ضریب فصلی
     */
    public static function seasonality_factor(int $timestamp = 0): float {
        if (!$timestamp) $timestamp = time();
        $dayOfYear = (int) gmdate('z', $timestamp); // 0-365
        $hour = (int) gmdate('G', $timestamp);
        $w = (int) gmdate('w', $timestamp); // 0=یکشنبه

        $season = 1 + 0.15 * sin(2 * M_PI * $dayOfYear / 365);
        $weekend = ($w === 5 || $w === 6) ? 0.08 : -0.04; // جمعه/شنبه پیک
        $evening = ($hour >= 19 && $hour <= 22) ? 0.10 : 0;

        return max(0.6, $season + $weekend + $evening);
    }

    /**
     * منحنی هنرمند: decay نمایی
     */
    public static function artist_factor(array $profile, int $ageDays): float {
        $growth = (float) ($profile['growth'] ?? 0.035);
        $decay = (float) ($profile['decay'] ?? 0.002);
        return $growth * exp(-$ageDays * $decay / 10);
    }

    /**
     * یک گام شبیه‌سازی
     *
     * @param int $curViews
     * @param string $tierId
     * @param array $profile ['growth'=>0.035,'decay'=>0.002]
     * @param int $ageDays
     * @param float $baseRate  نرخ پایه evergreen (0.035)
     * @param int|null $timestamp
     * @return int delta
     */
    public static function next_delta(int $curViews, string $tierId, array $profile, int $ageDays, float $baseRate = 0.035, ?int $timestamp = null): int {
        if ($curViews < 100) $curViews = 100;

        $tierMults = apply_filters('apex_omega_evergreen_tier_multipliers', [
            'tier_1' => 1.30, 'tier_2' => 1.18, 'tier_3' => 1.08,
            'tier_4' => 1.00, 'tier_5' => 0.88, 'tier_6' => 0.75, 'tier_7' => 0.60, '' => 0.92,
        ]);
        $mult = $tierMults[$tierId] ?? $tierMults[''] ?? 1.0;
        $season = self::seasonality_factor($timestamp ?? time());
        $artist = self::artist_factor($profile, $ageDays);

        // فرمول نهایی آلفا: base = cur * (rate * mult * season) + artist bonus
        // برای جلوگیری از انفجار، artist factor نرمال می‌شود به 0.8-1.2
        $artistNorm = 0.8 + min(0.4, max(0, $artist * 100));
        $rate = $baseRate * $mult * $season * $artistNorm;

        $base = $curViews * $rate / 100;
        // نویز گاوسی ساده: ±12%
        $noise = $base * (mt_rand(-12, 12) / 100);
        $delta = (int) round($base + $noise);

        // clamp برای تازه‌ها
        if ($curViews < 10000) $delta = min($delta, mt_rand(10, 18));

        // S-curve cap: هرچه به tier_cap نزدیک‌تر، رشد کندتر
        $tiers = TierManager::get_tiers();
        foreach ($tiers as $tt) if ($tt['id'] === $tierId) { $cap = (int) $tt['views_max']; break; }
        if (isset($cap) && $cap > 0) {
            $remaining = $cap - $curViews;
            if ($remaining < $cap * 0.1) $delta = (int) round($delta * ($remaining / ($cap * 0.1)));
        }

        return max(1, $delta);
    }

    /**
     * پیش‌نمایش 30 روزه بدون نوشتن در DB
     *
     * @param array $counts ['tier_1'=>18,...]
     * @param array $filters
     * @param int $days 7|30
     * @return array ['execution_id'=>uuid,'series'=>[...],'confidence'=>float]
     */
    public static function preview_30d(array $counts, array $filters = [], int $days = 30): array {
        $preview = TierManager::preview_distribution($counts, $filters);
        if (empty($preview['success'])) return $preview;

        $series = [];
        $exec = apex_omega_generate_uuid();

        // میانگین اولیه هر تیره
        $tierAvgs = [];
        foreach ($preview['summary'] as $tid => $s) {
            $tierAvgs[$tid] = (int) $s['avg_views'];
        }

        // شبیه‌سازی روز به روز
        for ($d = 0; $d <= $days; $d++) {
            $dayData = ['day' => $d];
            $totalCapped = 0;
            foreach ($tierAvgs as $tid => $avg) {
                // هر روز ≈ 288 تیک 5m → delta روزانه ≈ avg * 0.035% * 288 * mult ≈ 10%
                // برای سادگی: delta روزانه = avg * 0.08 * tierMult
                $profile = ['growth' => 0.035, 'decay' => 0.002];
                $age = 15 + $d; // فرض سن 15 روز + d
                $delta = self::next_delta($avg, $tid, $profile, $age);
                // روزانه: 288 برابر (تقریب)
                $avg += $delta * 20; // 20 تیک موثر روزانه (نه 288 برای نمایش)
                $tiers = TierManager::get_tiers();
                foreach ($tiers as $tt) if ($tt['id'] === $tid) { $cap = (int) $tt['views_max']; break; }
                if (isset($cap) && $avg > $cap) { $avg = $cap; $totalCapped++; }
                $tierAvgs[$tid] = $avg;
                $dayData[$tid . '_avg'] = $avg;
            }
            $dayData['capped'] = $totalCapped;
            // فقط روزهای کلیدی ذخیره برای سبکی
            if (in_array($d, [0, 1, 3, 7, 14, 21, 30], true)) {
                $series[] = $dayData;
            }
        }

        // confidence بر اساس داده واقعی: اگر هیچ profile نداریم 0.5، اگر داریم 0.8
        global $wpdb;
        $hasProfiles = (int) $wpdb->get_var("SELECT COUNT(*) FROM " . Database::table_profiles());
        $confidence = $hasProfiles > 10 ? 0.82 : 0.55;

        return [
            'success' => true,
            'execution_id' => $exec,
            'preview' => $preview,
            'series' => $series,
            'confidence' => $confidence,
            'days' => $days,
            'tier_avgs_final' => $tierAvgs,
        ];
    }

    /**
     * ثبت نمونه timeseries (هر تیک)
     */
    public static function record(int $postId, string $metric, float $value, string $source = 'evergreen', string $granularity = '5m'): void {
        global $wpdb;
        $table = Database::table_timeseries();
        $now = current_time('mysql');
        $nowGmt = current_time('mysql', true);
        $wpdb->insert($table, [
            'post_id' => $postId,
            'metric' => $metric,
            'value' => $value,
            'sampled_at' => $now,
            'sampled_at_gmt' => $nowGmt,
            'source' => $source,
            'granularity' => $granularity,
        ]);
    }
}
