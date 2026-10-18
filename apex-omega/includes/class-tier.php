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
     * دریافت پست‌های واجد شرایط — با paging برای >5000 و فیلتر 7 روزه
     */
    public static function get_eligible_post_ids(array $filters = []): array {
        $post_type = $filters['post_type'] ?? 'any';
        $category = $filters['category'] ?? '';
        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 5000;
        $exclude_recent_days = isset($filters['exclude_recent_days']) ? (int)$filters['exclude_recent_days'] : 0;

        if ($post_type === 'any') {
            $post_types = get_post_types(['public'=>true], 'names');
            unset($post_types['attachment']);
        } else {
            $post_types = [$post_type];
        }

        $base_args = [
            'post_type' => $post_types,
            'post_status' => 'publish',
            'fields' => 'ids',
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ];
        if ($category && $category !== 'all') {
            $base_args['cat'] = (int)$category;
            if (!is_numeric($category)) {
                $base_args['tax_query'] = [[
                    'taxonomy' => 'category',
                    'field' => 'slug',
                    'terms' => sanitize_text_field($category),
                ]];
                unset($base_args['cat']);
            }
        }
        // فیلتر 7 روزه: فقط پست‌هایی که _apex_tier_applied_at قدیمی‌تر از N روز است یا خالی است
        if ($exclude_recent_days > 0) {
            $cutoff = gmdate('Y-m-d H:i:s', time() - $exclude_recent_days * DAY_IN_SECONDS);
            $base_args['meta_query'] = [
                'relation' => 'OR',
                ['key'=>'_apex_tier_applied_at','compare'=>'NOT EXISTS'],
                ['key'=>'_apex_tier_applied_at','value'=>$cutoff,'compare'=>'<','type'=>'DATETIME'],
            ];
        }

        // Paging: WP_Query with posts_per_page max 2000 per batch to avoid memory
        if ($limit <= 5000) {
            $args = $base_args;
            $args['posts_per_page'] = (int)$limit;
            $q = new \WP_Query($args);
            return $q->posts ?: [];
        }
        // large limit: batch
        $collected = [];
        $batch = 1000;
        $pages = (int) ceil($limit / $batch);
        for ($p=1; $p <= $pages; $p++) {
            $args = $base_args;
            $args['posts_per_page'] = $batch;
            $args['paged'] = $p;
            $q = new \WP_Query($args);
            if (empty($q->posts)) break;
            $collected = array_merge($collected, $q->posts);
            if (count($collected) >= $limit) break;
            if (count($q->posts) < $batch) break;
        }
        return array_slice($collected, 0, $limit);
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
     * اجرای واقعی توزیع — با اسنپ‌شات، قفل هوشمند و تراکنش یکپارچه
     */
    public static function apply_distribution(array $counts_per_tier, array $filters = [], string $mode = 'overwrite'): array {
        $preview = self::preview_distribution($counts_per_tier, $filters);
        if (empty($preview['success'])) return $preview;

        $best = (int) get_option('kksr_stars', 5);
        if ($best < 1) $best = 5;
        $exec = apex_omega_generate_uuid();
        $user_id = get_current_user_id();
        $total_req = (int) $preview['total_requested'];
        $ttl = apex_omega_lock_ttl($total_req, 30, 600, 2);

        // master lock for whole distribution
        $master_lock = 'apex_tier_master';
        if (!Database::acquire_lock($master_lock, $ttl)) {
            return ['success'=>false,'message'=>'توزیع دیگری در حال اجراست. لطفاً '. $ttl .' ثانیه صبر کنید.','ttl'=>$ttl];
        }

        // Prepare snapshot: collect old values for all selected posts before any write
        $snapshot_rows = [];
        foreach ($preview['preview'] as $tier_id => $items) {
            foreach ($items as $item) {
                $pid = (int)$item['post_id'];
                $old_views = Adapter_Views::get_views($pid);
                $old_kksr = Adapter_KKSR::get_stats($pid);
                $snapshot_rows[] = [
                    'post_id' => $pid,
                    'post_title' => $item['post_title'] ?? (string)$pid,
                    'tier' => $tier_id,
                    'old_views' => $old_views,
                    'new_views' => $item['views'],
                    'old_casts' => $old_kksr['casts'],
                    'old_avg' => $old_kksr['avg'],
                    'old_ratings' => $old_kksr['ratings'],
                    'best' => $best,
                    'new_casts' => $item['casts'],
                    'new_avg' => $item['avg'],
                    'new_ratings' => $item['ratings'],
                ];
            }
        }

        $applied = 0;
        $errors = [];
        $details = [];

        foreach ($preview['preview'] as $tier_id => $items) {
            foreach ($items as $item) {
                $pid = (int)$item['post_id'];
                if (!get_post($pid)) { $errors[] = "پست $pid یافت نشد"; continue; }

                // قفل per-post با TTL هوشمند
                $lock = "apex_tier_{$pid}";
                if (!Database::acquire_lock($lock, max(15, min(120, $ttl)))) { $errors[] = "پست $pid قفل است"; continue; }

                $old_views = Adapter_Views::get_views($pid);
                $old_kksr = Adapter_KKSR::get_stats($pid);
                $detect = Adapter_KKSR::detect();

                // Single transaction per post: views + kksr + tier meta atomically
                global $wpdb;
                $wpdb->query('START TRANSACTION');
                try {
                    // views
                    update_post_meta($pid, Adapter_Views::META_KEY, (int)$item['views']);
                    $real_snapshot = get_post_meta($pid, '_apex_real_views', true);
                    if ($real_snapshot === '') {
                        update_post_meta($pid, '_apex_real_views', $old_views);
                        update_post_meta($pid, '_apex_real_views_captured_at', current_time('mysql', true));
                    }
                    update_post_meta($pid, '_apex_managed_views', (int)$item['views']);
                    update_post_meta($pid, '_apex_views_last_source', 'tier_distribution');
                    update_post_meta($pid, '_apex_views_last_exec', $exec);
                    // kksr
                    update_post_meta($pid, $detect['meta_casts'], (int)$item['casts']);
                    update_post_meta($pid, $detect['meta_ratings'], (float)$item['ratings']);
                    update_post_meta($pid, $detect['meta_avg'], (float)$item['avg']);
                    wp_cache_delete($pid, 'post_meta');
                    // tier meta
                    update_post_meta($pid, self::META_TIER, $tier_id);
                    update_post_meta($pid, '_apex_tier_applied_at', current_time('mysql', true));
                    $wpdb->query('COMMIT');
                    $ok = true;
                } catch (\Throwable $e) {
                    $wpdb->query('ROLLBACK');
                    error_log('[APEX Ω] tier atomic fail: '.$e->getMessage());
                    $ok = false;
                }

                if ($ok) {
                    // تاریخچه
                    History::log($pid, 'views', $old_views, $item['views'], 'tier_distribution', $user_id, $exec, null, ['tier'=>$tier_id]);
                    History::log($pid, 'kksr_combined', wp_json_encode(['casts'=>$old_kksr['casts'],'avg'=>$old_kksr['avg'],'ratings'=>$old_kksr['ratings']], JSON_UNESCAPED_UNICODE), wp_json_encode(['casts'=>$item['casts'],'avg'=>$item['avg'],'ratings'=>$item['ratings']], JSON_UNESCAPED_UNICODE), 'tier_distribution', $user_id, $exec, null, ['tier'=>$tier_id, 'best'=>$best]);
                    do_action('apex_omega_views_updated', $pid, $old_views, (int)$item['views'], 'tier_distribution', $exec);
                    do_action('apex_omega_kksr_updated', $pid, $old_kksr, ['casts'=>(int)$item['casts'],'ratings'=>(float)$item['ratings'],'avg'=>(float)$item['avg'],'best'=>$best], 'tier_distribution', $exec);
                    $applied++;
                    $details[] = [
                        'post_id' => $pid,
                        'post_title' => $item['post_title'] ?? (string)$pid,
                        'edit_url' => get_edit_post_link($pid),
                        'tier' => $tier_id,
                        'old_views' => $old_views,
                        'new_views' => $item['views'],
                        'old_casts' => $old_kksr['casts'],
                        'new_casts' => $item['casts'],
                        'old_avg' => $old_kksr['avg'],
                        'new_avg' => $item['avg'],
                    ];
                } else {
                    $errors[] = "خطا در پست $pid (تراکنش ناموفق)";
                }
                Database::release_lock($lock);
            }
        }

        // ذخیره اسنپ‌شات برای Undo
        try {
            Database::create_snapshot($exec, $user_id, $counts_per_tier, $filters, $total_req, $applied, $snapshot_rows);
        } catch (\Throwable $e) { error_log('[APEX Ω] snapshot fail: '.$e->getMessage()); }

        // ذخیره آخرین توزیع
        update_option(self::OPTION_LAST_DISTRIBUTION, [
            'counts' => $counts_per_tier,
            'filters' => $filters,
            'applied' => $applied,
            'exec' => $exec,
            'at' => current_time('mysql', true),
            'errors' => $errors,
        ], false);

        if (!empty(get_option('apex_omega_auto_tier', [])['enabled'])) {
            self::schedule_auto();
        }

        Database::release_lock($master_lock);

        return [
            'success' => true,
            'applied' => $applied,
            'errors' => $errors,
            'execution_id' => $exec,
            'summary' => $preview['summary'],
            'details' => $details,
            'snapshot_id' => $exec,
            'ttl_used' => $ttl,
        ];
    }

    /**
     * بازگردانی اسنپ‌شات (Undo آخرین توزیع)
     */
    public static function undo_last_distribution(?string $exec_id = null): array {
        $snap = $exec_id ? Database::get_snapshot_by_exec($exec_id) : Database::get_last_snapshot();
        if (!$snap) return ['success'=>false,'message'=>'اسنپ‌شاتی برای بازگردانی یافت نشد'];
        if ((int)$snap['restored'] === 1) return ['success'=>false,'message'=>'این توزیع قبلاً بازگردانی شده است'];
        $data = json_decode($snap['snapshot_json'], true);
        if (!is_array($data) || empty($data)) return ['success'=>false,'message'=>'داده اسنپ‌شات نامعتبر است'];
        $restored = 0;
        $errors = [];
        $restore_exec = apex_omega_generate_uuid();
        foreach ($data as $row) {
            $pid = (int)($row['post_id'] ?? 0);
            if (!$pid || !get_post($pid)) { $errors[]="پست $pid یافت نشد"; continue; }
            $lock = "apex_undo_{$pid}";
            if (!Database::acquire_lock($lock, 15)) { $errors[]="پست $pid قفل است"; continue; }
            global $wpdb;
            $wpdb->query('START TRANSACTION');
            try {
                // restore views
                update_post_meta($pid, Adapter_Views::META_KEY, (int)$row['old_views']);
                // restore kksr
                $detect = Adapter_KKSR::detect();
                update_post_meta($pid, $detect['meta_casts'], (int)$row['old_casts']);
                update_post_meta($pid, $detect['meta_ratings'], (float)$row['old_ratings']);
                update_post_meta($pid, $detect['meta_avg'], (float)$row['old_avg']);
                update_post_meta($pid, self::META_TIER, '');
                $wpdb->query('COMMIT');
                History::log($pid, 'views', $row['new_views'], $row['old_views'], 'tier_undo', get_current_user_id(), $restore_exec, null, ['undo_of'=>$snap['execution_id']]);
                History::log($pid, 'kksr_combined', wp_json_encode(['casts'=>$row['new_casts'],'avg'=>$row['new_avg']], JSON_UNESCAPED_UNICODE), wp_json_encode(['casts'=>$row['old_casts'],'avg'=>$row['old_avg']], JSON_UNESCAPED_UNICODE), 'tier_undo', get_current_user_id(), $restore_exec, null, ['undo_of'=>$snap['execution_id']]);
                $restored++;
            } catch (\Throwable $e) {
                $wpdb->query('ROLLBACK');
                $errors[]="پست $pid: ".$e->getMessage();
            }
            Database::release_lock($lock);
        }
        Database::mark_snapshot_restored((int)$snap['id']);
        return ['success'=>true,'restored'=>$restored,'errors'=>$errors,'restore_exec'=>$restore_exec,'original_exec'=>$snap['execution_id']];
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
