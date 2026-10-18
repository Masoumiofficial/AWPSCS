<?php
namespace ApexOmega\Core;

if (!defined('ABSPATH')) exit;

/**
 * مدیریت دسته‌بندی هوشمند (Tier) - توزیع خودکار بازدید/امتیاز
 * ۷ دسته پیش‌فرض per درخواست کاربر
 */
class TierManager {
    const OPTION_TIERS = 'apex_omega_tiers';
    const OPTION_LAST_DISTRIBUTION = 'apex_omega_last_distribution';
    const META_TIER = '_apex_tier';

    /**
     * دسته‌های پیش‌فرض - دقیقاً طبق درخواست کاربر
     */
    public static function default_tiers(): array {
        return [
            [
                'id' => 'tier_1',
                'label' => 'پرطرفدار ویژه — الماس',
                'icon' => '💎',
                'color' => '#7c3aed',
                'views_min' => 1000000,
                'views_max' => 10000000,
                'casts_min' => 2500,
                'casts_max' => 15000,
                'avg_min' => 4.7,
                'avg_max' => 5.0,
                'desc' => 'ابرآهنگ‌ها، صفحه اول سایت',
            ],
            [
                'id' => 'tier_2',
                'label' => 'پرطرفدار',
                'icon' => '🔥',
                'color' => '#ef4444',
                'views_min' => 500000,
                'views_max' => 900000,
                'casts_min' => 1200,
                'casts_max' => 6000,
                'avg_min' => 4.5,
                'avg_max' => 4.9,
                'desc' => 'ترند هفته',
            ],
            [
                'id' => 'tier_3',
                'label' => 'محبوب',
                'icon' => '⭐',
                'color' => '#f59e0b',
                'views_min' => 100000,
                'views_max' => 500000,
                'casts_min' => 400,
                'casts_max' => 2800,
                'avg_min' => 4.2,
                'avg_max' => 4.7,
                'desc' => 'پلی‌لیست پیشنهادی',
            ],
            [
                'id' => 'tier_4',
                'label' => 'متوسط رو به بالا',
                'icon' => '📈',
                'color' => '#10b981',
                'views_min' => 50000,
                'views_max' => 100000,
                'casts_min' => 180,
                'casts_max' => 900,
                'avg_min' => 4.0,
                'avg_max' => 4.5,
                'desc' => 'شنیده‌شده',
            ],
            [
                'id' => 'tier_5',
                'label' => 'متوسط',
                'icon' => '🎵',
                'color' => '#06b6d4',
                'views_min' => 20000,
                'views_max' => 50000,
                'casts_min' => 80,
                'casts_max' => 500,
                'avg_min' => 3.8,
                'avg_max' => 4.3,
                'desc' => 'در حال رشد',
            ],
            [
                'id' => 'tier_6',
                'label' => 'در حال رشد',
                'icon' => '🌱',
                'color' => '#84cc16',
                'views_min' => 5000,
                'views_max' => 20000,
                'casts_min' => 30,
                'casts_max' => 220,
                'avg_min' => 3.6,
                'avg_max' => 4.1,
                'desc' => 'کشف جدید',
            ],
            [
                'id' => 'tier_7',
                'label' => 'تازه منتشر شده',
                'icon' => '🆕',
                'color' => '#6b7280',
                'views_min' => 1000,
                'views_max' => 5000,
                'casts_min' => 10,
                'casts_max' => 80,
                'avg_min' => 3.4,
                'avg_max' => 4.0,
                'desc' => 'نیاز به دیده شدن',
            ],
        ];
    }

    public static function get_tiers(): array {
        $saved = get_option(self::OPTION_TIERS, null);
        if (is_array($saved) && !empty($saved)) return $saved;
        return self::default_tiers();
    }

    public static function save_tiers(array $tiers): void {
        update_option(self::OPTION_TIERS, $tiers, false);
    }

    public static function reset_tiers(): void {
        delete_option(self::OPTION_TIERS);
    }

    /**
     * تولید مقدار تصادفی منطقی برای یک دسته
     */
    public static function generate_values(array $tier, int $best = 5): array {
        $views = mt_rand((int)$tier['views_min'], (int)$tier['views_max']);
        $casts = mt_rand((int)$tier['casts_min'], (int)$tier['casts_max']);
        // میانگین با وزن: اگر بازدید بالاتر، avg متمایل به max
        $range = (float)$tier['avg_max'] - (float)$tier['avg_min'];
        // jitter 30% bias to high for popular tiers
        $bias = 0;
        if (strpos($tier['id'], 'tier_1') !== false || strpos($tier['id'], 'tier_2') !== false) $bias = 0.25;
        $rand = mt_rand(0, 1000) / 1000;
        $rand = min(1, max(0, $rand + $bias * (mt_rand(0,100)/100)));
        $avg = round((float)$tier['avg_min'] + $range * $rand, 1);
        $avg = min(max($avg, (float)$tier['avg_min']), (float)$tier['avg_max']);
        $avg = min($avg, $best);

        // محاسبه ratings سازگار
        $ratings = $avg * $casts / $best * 5;

        return [
            'views' => $views,
            'casts' => $casts,
            'avg' => $avg,
            'ratings' => round($ratings, 2),
            'best' => $best,
        ];
    }

    /**
     * دریافت پست‌های واجد شرایط
     */
    public static function get_eligible_post_ids(array $filters = []): array {
        $post_type = $filters['post_type'] ?? 'any';
        $category = $filters['category'] ?? '';
        $limit = $filters['limit'] ?? 5000;

        if ($post_type === 'any') {
            $post_types = get_post_types(['public'=>true], 'names');
            unset($post_types['attachment']);
        } else {
            $post_types = [$post_type];
        }

        $args = [
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => (int)$limit,
            'fields' => 'ids',
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ];
        if ($category && $category !== 'all') {
            $args['cat'] = (int)$category;
            // پشتیبانی دسته سفارشی
            if (!is_numeric($category)) {
                $args['tax_query'] = [[
                    'taxonomy' => 'category',
                    'field' => 'slug',
                    'terms' => sanitize_text_field($category),
                ]];
                unset($args['cat']);
            }
        }

        $q = new \WP_Query($args);
        return $q->posts ?: [];
    }

    /**
     * پیش‌نمایش توزیع: بدون نوشتن در DB، فقط انتخاب و تولید مقدار
     */
    public static function preview_distribution(array $counts_per_tier, array $filters = []): array {
        $tiers = self::get_tiers();
        $tiers_by_id = [];
        foreach ($tiers as $t) $tiers_by_id[$t['id']] = $t;

        $best = (int) get_option('kksr_stars', 5);
        if ($best < 1) $best = 5;

        // جمع کل درخواستی
        $total_requested = array_sum(array_map('intval', $counts_per_tier));
        $eligible_ids = self::get_eligible_post_ids($filters);
        $total_available = count($eligible_ids);

        if ($total_requested > $total_available) {
            return [
                'success' => false,
                'message' => sprintf('تعداد درخواستی (%s) از کل آثار منتشرشده (%s) بیشتر است. تعداد دسته‌ها را کم کنید یا آثار بیشتری منتشر کنید.', number_format_i18n($total_requested), number_format_i18n($total_available)),
                'total_requested' => $total_requested,
                'total_available' => $total_available,
            ];
        }

        // تصادفی‌سازی منصفانه: shuffle - یا آرتیست‌محور
        $artist_aware = !empty($filters['artist_aware']);
        if ($artist_aware) {
            // مرتب‌سازی بر اساس وزن آرتیست (پرطرفدارها اول)
            $weights = [];
            foreach ($eligible_ids as $pid) {
                $weights[$pid] = \ApexOmega\Core\ArtistManager::get_weight_for_post($pid);
            }
            usort($eligible_ids, function($a,$b) use ($weights){
                $wa = $weights[$a] ?? 50; $wb = $weights[$b] ?? 50;
                if ($wa === $wb) return 0;
                return ($wb <=> $wa); // نزولی
            });
        } else {
            shuffle($eligible_ids);
        }
        $cursor = 0;
        $preview = [];
        $summary = [];

        foreach ($tiers_by_id as $tier_id => $tier) {
            $count = isset($counts_per_tier[$tier_id]) ? (int)$counts_per_tier[$tier_id] : 0;
            if ($count <= 0) continue;

            $slice = array_slice($eligible_ids, $cursor, $count);
            $cursor += $count;

            $tier_preview = [];
            $sum_views = 0; $sum_casts = 0; $sum_avg = 0;
            foreach ($slice as $pid) {
                $post = get_post($pid);
                $vals = self::generate_values($tier, $best);
                $sum_views += $vals['views'];
                $sum_casts += $vals['casts'];
                $sum_avg += $vals['avg'];
                $tier_preview[] = [
                    'post_id' => $pid,
                    'post_title' => $post ? $post->post_title : "ID $pid",
                    'post_url' => get_edit_post_link($pid),
                    'views' => $vals['views'],
                    'casts' => $vals['casts'],
                    'avg' => $vals['avg'],
                    'ratings' => $vals['ratings'],
                ];
            }

            $summary[$tier_id] = [
                'label' => $tier['label'],
                'icon' => $tier['icon'],
                'color' => $tier['color'],
                'count' => $count,
                'avg_views' => $count ? round($sum_views / $count) : 0,
                'total_views' => $sum_views,
                'avg_casts' => $count ? round($sum_casts / $count) : 0,
                'avg_rating' => $count ? round($sum_avg / $count, 2) : 0,
            ];
            $preview[$tier_id] = $tier_preview;
        }

        return [
            'success' => true,
            'total_requested' => $total_requested,
            'total_available' => $total_available,
            'remaining' => $total_available - $total_requested,
            'preview' => $preview,
            'summary' => $summary,
            'tiers' => $tiers_by_id,
        ];
    }

    /**
     * اجرای واقعی توزیع
     */
    public static function apply_distribution(array $counts_per_tier, array $filters = [], string $mode = 'overwrite'): array {
        $preview = self::preview_distribution($counts_per_tier, $filters);
        if (empty($preview['success'])) return $preview;

        $best = (int) get_option('kksr_stars', 5);
        $exec = apex_omega_generate_uuid();
        $user_id = get_current_user_id();
        $applied = 0;
        $errors = [];

        foreach ($preview['preview'] as $tier_id => $items) {
            foreach ($items as $item) {
                $pid = (int)$item['post_id'];
                if (!get_post($pid)) { $errors[] = "پست $pid یافت نشد"; continue; }

                // قفل
                $lock = "apex_tier_{$pid}";
                if (!Database::acquire_lock($lock, 10)) { $errors[] = "پست $pid قفل است"; continue; }

                $old_views = Adapter_Views::get_views($pid);
                $old_kksr = Adapter_KKSR::get_stats($pid);

                // نوشتن
                $ok1 = Adapter_Views::set_views($pid, (int)$item['views'], 'tier_distribution', $exec);
                $ok2 = Adapter_KKSR::set_stats($pid, (int)$item['casts'], (float)$item['ratings'], (float)$item['avg'], 'tier_distribution', $exec);

                if ($ok1 && $ok2) {
                    update_post_meta($pid, self::META_TIER, $tier_id);
                    update_post_meta($pid, '_apex_tier_applied_at', current_time('mysql', true));
                    // تاریخچه
                    History::log($pid, 'views', $old_views, $item['views'], 'tier_distribution', $user_id, $exec, null, ['tier'=>$tier_id]);
                    History::log($pid, 'kksr_combined', wp_json_encode(['casts'=>$old_kksr['casts'],'avg'=>$old_kksr['avg']], JSON_UNESCAPED_UNICODE), wp_json_encode(['casts'=>$item['casts'],'avg'=>$item['avg']], JSON_UNESCAPED_UNICODE), 'tier_distribution', $user_id, $exec, null, ['tier'=>$tier_id, 'best'=>$best]);
                    $applied++;
                } else {
                    $errors[] = "خطا در پست $pid";
                }
                Database::release_lock($lock);
            }
        }

        // ذخیره آخرین توزیع
        update_option(self::OPTION_LAST_DISTRIBUTION, [
            'counts' => $counts_per_tier,
            'filters' => $filters,
            'applied' => $applied,
            'exec' => $exec,
            'at' => current_time('mysql', true),
        ], false);

        // اگر حالت خودکار فعال است، زمان بعدی را تنظیم کن
        $auto = get_option('apex_omega_auto_tier', []);
        if (!empty($auto['enabled'])) {
            self::schedule_auto();
        }

        return [
            'success' => true,
            'applied' => $applied,
            'errors' => $errors,
            'execution_id' => $exec,
            'summary' => $preview['summary'],
        ];
    }

    public static function schedule_auto(): void {
        if (!wp_next_scheduled('apex_omega_auto_tier_tick')) {
            wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', 'apex_omega_auto_tier_tick');
        }
    }
    public static function unschedule_auto(): void {
        wp_clear_scheduled_hook('apex_omega_auto_tier_tick');
    }

    // توزیع هوشمند پیش‌فرض بر اساس هرم: تعداد آثار کم در صدر، زیاد در قاعده
    public static function suggested_pyramid(int $total): array {
        // درصد هر دسته از کل
        $percents = [
            'tier_1' => 0.02, // 2% الماس
            'tier_2' => 0.04, // 4% پرطرفدار
            'tier_3' => 0.10, // 10% محبوب
            'tier_4' => 0.14, // 14%
            'tier_5' => 0.20, // 20%
            'tier_6' => 0.25, // 25%
            'tier_7' => 0.25, // 25% تازه
        ];
        $out = [];
        $sum = 0;
        foreach ($percents as $k=>$p) {
            $c = (int) round($total * $p);
            $out[$k] = $c;
            $sum += $c;
        }
        // تنظیم باقی‌مانده به دسته آخر
        $diff = $total - $sum;
        if ($diff !== 0) $out['tier_7'] += $diff;
        return $out;
    }
}
