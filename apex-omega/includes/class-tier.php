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
     * دسته‌های پیش‌فرض — حرفه‌ای استاندارد 10M (هرم جهانی موسیقی — قبلا 1.5M)
     * توزیع: 2٪ الماس، 4٪ پرطرفدار، 10٪ محبوب، 14٪ متوسط‌بالا، 20٪ متوسط، 25٪ درحال‌رشد، 25٪ تازه
     * همبستگی بازدید↔رای↔میانگین کاملاً رعایت شده (ISO-like)
     */
    public static function default_tiers(): array {
        return [
            [
                'id' => 'tier_1',
                'label' => 'پرطرفدار ویژه — الماس',
                'icon' => '💎',
                'color' => '#7c3aed',
                'views_min' => 900000,
                'views_max' => 10000000,
                'casts_min' => 3500,
                'casts_max' => 22000,
                'avg_min' => 4.7,
                'avg_max' => 5.0,
                'desc' => 'ابرآهنگ‌ها — بهت قول میدم / پرطرفدارترین‌ها',
            ],
            [
                'id' => 'tier_2',
                'label' => 'پرطرفدار',
                'icon' => '🔥',
                'color' => '#ef4444',
                'views_min' => 500000,
                'views_max' => 900000,
                'casts_min' => 1500,
                'casts_max' => 8500,
                'avg_min' => 4.5,
                'avg_max' => 4.8,
                'desc' => 'ترند هفته',
            ],
            [
                'id' => 'tier_3',
                'label' => 'محبوب',
                'icon' => '⭐',
                'color' => '#f59e0b',
                'views_min' => 200000,
                'views_max' => 500000,
                'casts_min' => 700,
                'casts_max' => 4200,
                'avg_min' => 4.3,
                'avg_max' => 4.6,
                'desc' => 'پلی‌لیست پیشنهادی',
            ],
            [
                'id' => 'tier_4',
                'label' => 'متوسط رو به بالا',
                'icon' => '📈',
                'color' => '#10b981',
                'views_min' => 80000,
                'views_max' => 200000,
                'casts_min' => 350,
                'casts_max' => 2200,
                'avg_min' => 4.1,
                'avg_max' => 4.4,
                'desc' => 'شنیده‌شده',
            ],
            [
                'id' => 'tier_5',
                'label' => 'متوسط',
                'icon' => '🎵',
                'color' => '#06b6d4',
                'views_min' => 30000,
                'views_max' => 80000,
                'casts_min' => 150,
                'casts_max' => 900,
                'avg_min' => 3.9,
                'avg_max' => 4.2,
                'desc' => 'در حال رشد',
            ],
            [
                'id' => 'tier_6',
                'label' => 'در حال رشد',
                'icon' => '🌱',
                'color' => '#84cc16',
                'views_min' => 10000,
                'views_max' => 30000,
                'casts_min' => 40,
                'casts_max' => 160,
                'avg_min' => 3.7,
                'avg_max' => 4.0,
                'desc' => 'کشف جدید',
            ],
            [
                'id' => 'tier_7',
                'label' => 'تازه منتشر شده',
                'icon' => '🆕',
                'color' => '#6b7280',
                'views_min' => 1000,
                'views_max' => 10000,
                'casts_min' => 10,
                'casts_max' => 70,
                'avg_min' => 3.4,
                'avg_max' => 3.8,
                'desc' => 'نیاز به دیده شدن',
            ],
        ];
    }

    public static function get_tiers(): array {
        $saved = get_option(self::OPTION_TIERS, null);
        if (is_array($saved) && !empty($saved)) {
            // FIX 2.2.6: اگر سقف قدیمی 1.5M بود به 10M مهاجرت (قبلا برعکس بود)
            $max = 0; foreach ($saved as $t) $max = max($max, (int)($t['views_max'] ?? 0));
            if ($max > 0 && $max < 9000000 && $max <= 1500000) {
                $def = self::default_tiers();
                self::save_tiers($def);
                return $def;
            }
            return $saved;
        }
        return self::default_tiers();
    }

    public static function save_tiers(array $tiers): void {
        update_option(self::OPTION_TIERS, $tiers, false);
    }

    public static function reset_tiers(): void {
        delete_option(self::OPTION_TIERS);
    }

    public static function get_tier_weight_range(string $tier_id): array {
        // نگاشت وزن 0-100 به تیره — باید با ArtistManager::weight_to_tier_hint هم‌خوان باشد
        $map = [
            'tier_1' => ['min'=>90,'max'=>100],
            'tier_2' => ['min'=>75,'max'=>89],
            'tier_3' => ['min'=>60,'max'=>74],
            'tier_4' => ['min'=>45,'max'=>59],
            'tier_5' => ['min'=>30,'max'=>44],
            'tier_6' => ['min'=>15,'max'=>29],
            'tier_7' => ['min'=>0, 'max'=>14],
        ];
        return $map[$tier_id] ?? ['min'=>0,'max'=>100];
    }

    /**
     * تولید مقدار تصادفی منطقی برای یک دسته — وزن‌محور + هیت + همبستگی واقعی
     * @param int|null $weight وزن هنرمند 0-100 — اگر null باشد رفتار قدیمی رندم حفظ می‌شود (سازگاری عقب‌رو)
     * @param bool $is_hit آیا اثر هیت/معروف است؟ اگر true بازدید به سقف تیره می‌رود (9-10M)
     * @param bool $is_premium آیا در دسته منتخب selected-works است؟ اگر true قابلیت 10M+ (تا 12M) و امتیاز/کامنت پرفکت
     */
    public static function generate_values(array $tier, int $best = 5, ?int $weight = null, bool $is_hit = false, ?int $post_id = null, bool $is_premium = false): array {
        $vmin = (int)$tier['views_min']; $vmax = (int)$tier['views_max'];
        $range = $vmax - $vmin;

        // وزن‌محور: 85 با 86 فرق داشته باشد — در همان تیره جایگاه متفاوت
        if ($weight !== null) {
            $wr = self::get_tier_weight_range($tier['id'] ?? '');
            $wSpan = max(1, $wr['max'] - $wr['min']);
            $norm = max(0, min(1, ($weight - $wr['min']) / $wSpan)); // 0-1 داخل تیره
            // هیت: سقف تیره (برای tier1 => 9-10M)، معمولی: کف تا میانه (2-4M برای tier1)
            // هش: هر پست حتی با وزن یکسان بازدید متفاوت بگیرد — ضد گیرکردن 5M
            $pidHash = $post_id ? ((($post_id * 9301 + 49297) % 233280) / 233280) : (mt_rand(0,1000)/1000);
            $hashJitter = ($pidHash - 0.5) * 0.10; // ±5% بر اساس ID
            if ($is_premium) {
                // منتخب ویژه selected-works — پرفکت، قابلیت 10M+ (تا ~12M)
                $baseFactor = 0.92 + $norm * 0.14; // 92% تا 106%
                $jitter = mt_rand(-200, 200) / 10000 + $hashJitter * 0.4; // ±2% + هش
                $factor = max(0.90, min(1.18, $baseFactor + $jitter)); // تا 118% → 900K + 9.1M*1.18 = 11.6M
                $views = (int) round($vmin + $range * $factor);
                // برای منتخب حتی اگر رنج اجازه نده، حداقل 9.5M تضمین کن
                if ($tier['id'] === 'tier_1' && $views < 9500000) $views = mt_rand(9500000, 11800000);
            } elseif ($is_hit) {
                // هیت: 88-100% رنج + وزن تأثیر 12% + هش + جیتر
                $baseFactor = 0.88 + $norm * 0.12;
                $jitter = mt_rand(-300, 300) / 10000 + $hashJitter * 0.5; // ±3% + هش
                $factor = max(0.85, min(1, $baseFactor + $jitter));
                $views = (int) round($vmin + $range * $factor);
            } else {
                // معمولی: 12% + norm*52% => بازه وسیع‌تر 1.9M-6.9M برای tier1 — ضد گیر 5M و شکستن 600k یکسان
                $baseFactor = 0.12 + $norm * 0.52;
                $jitter = mt_rand(-800, 800) / 10000 + $hashJitter * 1.15; // ±8% + هش ±5.7% = تا ±14% پراکندگی
                $factor = max(0.06, min(0.88, $baseFactor + $jitter));
                $views = (int) round($vmin + $range * $factor);
            }
        } else {
            $views = mt_rand($vmin, $vmax);
        }

        // رای‌ها همبسته با بازدید — واقعی: ~1 رای per 400-650 بازدید
        if ($weight !== null) {
            $isPremiumCast = $is_premium || $views >= 9500000;
            $divisor = 520 + mt_rand(-70, 70) + ($is_hit ? -70 : 0) + ($isPremiumCast ? -25 : 0) + ($weight >= 90 ? -30 : 0);
            $divisor = max(320, min(850, $divisor));
            $casts = (int) round($views / $divisor);
            // کلیمپ با محدوده تیره برای جلوگیری از پرت — منتخب اجازه سقف بالاتر
            $extraCap = $is_premium ? 15000 : 8000;
            $casts = max((int)$tier['casts_min'], min((int)$tier['casts_max'] + $extraCap, $casts));
            // برای هیت‌های 9M دیگر 5600 کم است — الان 9M/500≈18000 می‌شود
            if ($views >= 9000000 && $casts < 9000) $casts = $is_premium ? mt_rand(16000, 24000) : mt_rand(11000, 19000);
            elseif ($views >= 8000000 && $casts < 9000) $casts = mt_rand(11000, 19000);
            if ($views >= 5000000 && $views < 8000000 && $casts < 6000) $casts = mt_rand(6500, 11000);
        } else {
            $casts = mt_rand((int)$tier['casts_min'], (int)$tier['casts_max']);
        }

        // میانگین وزن‌محور + هیت
        $avgRange = (float)$tier['avg_max'] - (float)$tier['avg_min'];
        if ($weight !== null) {
            $wr = self::get_tier_weight_range($tier['id'] ?? '');
            $wSpan = max(1, $wr['max'] - $wr['min']);
            $norm = max(0, min(1, ($weight - $wr['min']) / $wSpan));
            $avg = (float)$tier['avg_min'] + $avgRange * (0.35 + $norm * 0.55);
            if ($is_premium) $avg += 0.28;
            elseif ($is_hit) $avg += 0.18;
            $avg += mt_rand(-8, 8) / 100; // ±0.08
        } else {
            $bias = 0;
            if (strpos($tier['id'], 'tier_1') !== false || strpos($tier['id'], 'tier_2') !== false) $bias = 0.25;
            $rand = mt_rand(0, 1000) / 1000;
            $rand = min(1, max(0, $rand + $bias * (mt_rand(0,100)/100)));
            $avg = round((float)$tier['avg_min'] + $avgRange * $rand, 1);
        }
        $avg = min(max($avg, (float)$tier['avg_min']), (float)$tier['avg_max']);
        $avg = min($avg, $best);
        if ($is_premium) $avg = max($avg, (float)$tier['avg_min'] + $avgRange * 0.92);
        elseif ($is_hit) $avg = max($avg, (float)$tier['avg_min'] + $avgRange * 0.85);
        $avg = round($avg, 1);

        // همبستگی بازدید↔امتیاز (نامحسوس)
        if ($weight === null) {
            $views_factor = min(0.15, max(0, ($views - 10000) / 10000000 * 0.12));
            $avg = min((float)$tier['avg_max'], max((float)$tier['avg_min'], $avg + $views_factor));
            $avg = round($avg, 1);
        }
        $ratings = $best > 0 ? round($avg * $casts / $best * 5, 2) : 0;

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
            'orderby' => 'date ID',
            'order' => 'DESC',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]; // 1.9.3 COR-06: پایدار — جلوگیری از دوباره‌شمارش با orderby دوتایی

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
        // فیلتر هنرمند خاص — برای تنوع‌دهی مجدد علیرضا قربانی و امثالهم
        if (!empty($filters['artist_term']) && !empty($filters['artist_taxonomy'])) {
            $tax_q = ['taxonomy'=>sanitize_text_field($filters['artist_taxonomy']),'field'=>'term_id','terms'=>(int)$filters['artist_term']];
            if (empty($base_args['tax_query'])) $base_args['tax_query'] = [$tax_q];
            else {
                $base_args['tax_query'][] = $tax_q;
                if (count($base_args['tax_query']) > 1) $base_args['tax_query']['relation'] = 'AND';
            }
        } elseif (!empty($filters['artist_term'])) {
            // fallback auto detect
            $auto_tax = \ApexOmega\Core\ArtistManager::detect_artist_taxonomy();
            if ($auto_tax) {
                $tax_q = ['taxonomy'=>$auto_tax,'field'=>'term_id','terms'=>(int)$filters['artist_term']];
                if (empty($base_args['tax_query'])) $base_args['tax_query'] = [$tax_q];
                else { $base_args['tax_query'][] = $tax_q; $base_args['tax_query']['relation']='AND'; }
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

        // Paging: 1.9.4 COR-06: همیشه paging برای جلوگیری از مموری spike — حتی برای 884
        $limit = max(0, min(20000, $limit)); // سقف 20K — محافظ منابع
        if ($limit <= 500) {
            $args = $base_args;
            $args['posts_per_page'] = (int)$limit;
            $q = new \WP_Query($args);
            return $q->posts ?: [];
        }
        // medium+large limit: batch 500 (به جای 1000) — فشار کمتر
        $collected = [];
        $batch = 500;
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
        if (function_exists('set_time_limit')) @set_time_limit(90);
        $tiers = self::get_tiers();
        $tiers_by_id = [];
        foreach ($tiers as $t) $tiers_by_id[$t['id']] = $t;

        $best = (int) get_option('kksr_stars', 5);
        if ($best < 1) $best = 5;

        // جمع کل درخواستی
        $total_requested = array_sum(array_map('intval', $counts_per_tier));
        $eligible_ids = self::get_eligible_post_ids($filters);
        $total_available = count($eligible_ids);

        if ($total_requested === 0) {
            return ['success'=>false,'message'=>'هیچ دسته‌ای انتخاب نشده — حداقل یک کادر را پر کنید.','total_requested'=>0,'total_available'=>$total_available];
        }
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
        // حرفه‌ای 1.9.1: افزایش سقف اجرا برای 884 آهنگ — جلوگیری از Timeout HTML
        if (function_exists('set_time_limit')) @set_time_limit(180);
        @ini_set('max_execution_time', '180');
        @ini_set('memory_limit', '256M');
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
        $chunk_counter = 0;

        foreach ($preview['preview'] as $tier_id => $items) {
            foreach ($items as $item) {
                // 1.9.2 حرفه‌ای: هر 100 آهنگ 0.3 ثانیه مکث برای جلوگیری از 503 Service Unavailable
                if ($chunk_counter > 0 && $chunk_counter % 100 === 0) {
                    usleep(300000);
                    if (function_exists('set_time_limit')) @set_time_limit(180);
                }
                $chunk_counter++;
                $pid = (int)$item['post_id'];
                if (!get_post($pid)) { $errors[] = "پست $pid یافت نشد"; continue; }

                // قفل per-post با TTL هوشمند
                $lock = "apex_tier_{$pid}";
                if (!Database::acquire_lock($lock, max(15, min(120, $ttl)))) { $errors[] = "پست $pid قفل است"; continue; }

                $old_views = Adapter_Views::get_views($pid);
                $old_kksr = Adapter_KKSR::get_stats($pid);
                // 🛡️ محافظ هرگز کم نکن — اگر توزیع مقدار کمتری پیشنهاد داد، نادیده بگیر (فقط دستی می‌تواند کم کند)
                $guarded = apex_omega_guard_stats(['casts'=>$old_kksr['casts'],'avg'=>$old_kksr['avg'],'ratings'=>$old_kksr['ratings'],'views'=>$old_views], ['casts'=>$item['casts'],'avg'=>$item['avg'],'ratings'=>$item['ratings'],'views'=>$item['views']], false, $pid);
                $item['casts'] = $guarded['casts']; $item['avg'] = $guarded['avg']; $item['ratings'] = $guarded['ratings']; $item['views'] = $guarded['views'];
                if ($item['views'] === $old_views && $item['casts'] === $old_kksr['casts'] && $item['avg'] === $old_kksr['avg']) { Database::release_lock($lock); continue; }
                // مقدار قدیمی ممکن است با guard تغییر کرده باشد، snapshot را هم به‌روزرسانی کن بعداً؟
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

    // ─── 1.9.3 Chunked Job — تکه‌تکه 100/درخواست با DB lock/idempotent/Retry (COR-07) ───
    public static function start_distribution_job(array $counts_per_tier, array $filters = [], int $chunk_size = 100): array {
        if (function_exists('set_time_limit')) @set_time_limit(60);
        $preview = self::preview_distribution($counts_per_tier, $filters);
        if (empty($preview['success'])) return $preview;
        $flat = [];
        foreach ($preview['preview'] as $tier_id => $items) {
            foreach ($items as $item) {
                $flat[] = array_merge($item, ['tier_id'=>$tier_id]);
            }
        }
        $exec = apex_omega_generate_uuid();
        $user_id = get_current_user_id();
        $total = count($flat);
        // payload: flat already contains generated values — idempotent
        Database::create_job($exec, $user_id, $counts_per_tier, $filters, $flat, $total, $chunk_size);
        // 2.0-beta: هم‌زمان در صف جدید هم ثبت کن تا REST /queue دیده شود
        if (class_exists('\\ApexOmega\\Core\\QueueWorker')) {
            try { \ApexOmega\Core\QueueWorker::enqueue('tier_distribution', ['execution_id'=>$exec,'total'=>$total,'counts'=>$counts_per_tier], 10); } catch (\Throwable $e) {}
        }
        // snapshot برای Undo بعد از شروع (خالی تا پایان job پر می‌شود)
        return [
            'success' => true,
            'execution_id' => $exec,
            'total' => $total,
            'chunk_size' => $chunk_size,
            'message' => 'کار تکه‌تکه ایجاد شد — '.$total.' آهنگ در صف',
            'preview_summary' => $preview['summary'],
        ];
    }

    public static function process_job_chunk(string $execution_id, int $chunk_size = 0): array {
        $job = Database::get_job($execution_id);
        if (!$job) return ['success'=>false,'message'=>'کار یافت نشد'];
        if (in_array($job['status'], ['done','failed'], true) && (int)$job['processed'] >= (int)$job['total']) {
            return ['success'=>true,'status'=>'done','processed'=>(int)$job['processed'],'total'=>(int)$job['total'],'message'=>'کار قبلا کامل شده'];
        }
        $chunk_size = $chunk_size > 0 ? $chunk_size : (int)$job['chunk_size'];
        $chunk_size = max(10, min(200, $chunk_size));
        $payload = json_decode($job['payload_json'], true);
        if (!is_array($payload)) return ['success'=>false,'message'=>'payload نامعتبر'];
        $total = (int)$job['total'];
        $processed = (int)$job['processed'];
        if ($processed >= $total) {
            Database::update_job_progress($execution_id, $total, 'done');
            return ['success'=>true,'status'=>'done','processed'=>$total,'total'=>$total];
        }
        // lock per job
        $lock_key = 'apex_job_' . $execution_id;
        if (!Database::acquire_lock($lock_key, 120)) {
            return ['success'=>false,'message'=>'کار دیگری در حال پردازش است — چند ثانیه صبر کنید','retry_after'=>5];
        }
        // re-read to avoid double process
        $job = Database::get_job($execution_id);
        $processed = (int)$job['processed'];
        $slice = array_slice($payload, $processed, $chunk_size);
        if (empty($slice)) {
            Database::release_lock($lock_key);
            Database::update_job_progress($execution_id, $total, 'done');
            return ['success'=>true,'status'=>'done','processed'=>$total,'total'=>$total];
        }
        $best = (int) get_option('kksr_stars', 5); if ($best<1) $best=5;
        $applied = 0; $errors=[];
        $snapshot_rows=[]; // for this chunk only; accumulated in option
        global $wpdb;
        foreach ($slice as $item) {
            $pid = (int)$item['post_id'];
            $tier_id = $item['tier_id'];
            if (!get_post($pid)) { $errors[]="پست $pid یافت نشد"; continue; }
            if (!current_user_can('edit_post', $pid) && !apex_omega_user_can_manage()) { error_log('[APEX Ω] SEC-03: user '.get_current_user_id().' tried to modify post '.$pid.' without edit_post'); if(get_option('apex_omega_strict_per_post', false)){ $errors[]="دسترسی ویرایش پست $pid ندارید"; continue; } }
            $plock = "apex_tier_{$pid}";
            if (!Database::acquire_lock($plock, 20)) { $errors[]="پست $pid قفل است — دوباره تلاش می‌شود"; continue; }
            $old_views = Adapter_Views::get_views($pid);
            $old_kksr = Adapter_KKSR::get_stats($pid);
            $detect = Adapter_KKSR::detect();
            $wpdb->query('START TRANSACTION');
            try {
                update_post_meta($pid, Adapter_Views::META_KEY, (int)$item['views']);
                $real_snapshot = get_post_meta($pid, '_apex_real_views', true);
                if ($real_snapshot === '') {
                    update_post_meta($pid, '_apex_real_views', $old_views);
                    update_post_meta($pid, '_apex_real_views_captured_at', current_time('mysql', true));
                }
                update_post_meta($pid, '_apex_managed_views', (int)$item['views']);
                update_post_meta($pid, '_apex_views_last_source', 'tier_distribution_chunked');
                update_post_meta($pid, '_apex_views_last_exec', $execution_id);
                update_post_meta($pid, $detect['meta_casts'], (int)$item['casts']);
                update_post_meta($pid, $detect['meta_ratings'], (float)$item['ratings']);
                update_post_meta($pid, $detect['meta_avg'], (float)$item['avg']);
                wp_cache_delete($pid, 'post_meta');
                update_post_meta($pid, self::META_TIER, $tier_id);
                update_post_meta($pid, '_apex_tier_applied_at', current_time('mysql', true));
                $wpdb->query('COMMIT');
                History::log($pid, 'views', $old_views, $item['views'], 'tier_distribution', get_current_user_id(), $execution_id, null, ['tier'=>$tier_id,'chunked'=>true]);
                History::log($pid, 'kksr_combined', wp_json_encode(['casts'=>$old_kksr['casts'],'avg'=>$old_kksr['avg'],'ratings'=>$old_kksr['ratings']], JSON_UNESCAPED_UNICODE), wp_json_encode(['casts'=>$item['casts'],'avg'=>$item['avg'],'ratings'=>$item['ratings']], JSON_UNESCAPED_UNICODE), 'tier_distribution', get_current_user_id(), $execution_id, null, ['tier'=>$tier_id,'best'=>$best,'chunked'=>true]);
                do_action('apex_omega_views_updated', $pid, $old_views, (int)$item['views'], 'tier_distribution_chunked', $execution_id);
                $snapshot_rows[] = [
                    'post_id'=>$pid,'post_title'=>$item['post_title']??(string)$pid,'tier'=>$tier_id,
                    'old_views'=>$old_views,'new_views'=>$item['views'],'old_casts'=>$old_kksr['casts'],'old_avg'=>$old_kksr['avg'],'old_ratings'=>$old_kksr['ratings'],'best'=>$best,
                    'new_casts'=>$item['casts'],'new_avg'=>$item['avg'],'new_ratings'=>$item['ratings'],
                ];
                $applied++;
            } catch (\Throwable $e) {
                $wpdb->query('ROLLBACK');
                $errors[]="پست $pid: ".$e->getMessage();
            }
            Database::release_lock($plock);
        }
        $new_processed = $processed + $applied;
        // ذخیره snapshot تجمعی برای Undo (append)
        $opt_key = 'apex_job_snapshot_' . $execution_id;
        $acc = get_option($opt_key, []);
        if (!is_array($acc)) $acc=[];
        $acc = array_merge($acc, $snapshot_rows);
        update_option($opt_key, $acc, false);
        if ($applied === 0 && !empty($errors) && count($slice) === count($errors)) {
            // کل chunk شکست خورد — retry count
            Database::increment_job_retry($execution_id);
            Database::release_lock($lock_key);
            return ['success'=>false,'message'=>'خطا در پردازش chunk: '.implode(' | ', array_slice($errors,0,3)),'errors'=>$errors,'processed'=>$processed,'total'=>$total,'retry'=>true];
        }
        $is_done = ($new_processed >= $total);
        Database::update_job_progress($execution_id, $new_processed, $is_done ? 'done' : 'running');
        Database::release_lock($lock_key);
        if ($is_done) {
            // ساخت snapshot نهایی برای Undo
            try {
                $counts = json_decode($job['counts_json'], true) ?: [];
                $filters = json_decode($job['filters_json'], true) ?: [];
                Database::create_snapshot($execution_id, (int)$job['user_id'], $counts, $filters, $total, $new_processed, $acc);
                delete_option($opt_key);
                update_option(self::OPTION_LAST_DISTRIBUTION, ['counts'=>$counts,'filters'=>$filters,'applied'=>$new_processed,'exec'=>$execution_id,'at'=>current_time('mysql', true),'errors'=>$errors,'chunked'=>true], false);
            } catch (\Throwable $e) { error_log('[APEX Ω] chunked snapshot fail: '.$e->getMessage()); }
        }
        return [
            'success'=>true,
            'status'=> $is_done ? 'done' : 'running',
            'processed'=>$new_processed,
            'total'=>$total,
            'applied_in_chunk'=>$applied,
            'errors'=>$errors,
            'remaining'=> $total - $new_processed,
            'percent'=> $total ? round($new_processed/$total*100,1) : 0,
        ];
    }

    public static function get_job_status(string $execution_id): array {
        $job = Database::get_job($execution_id);
        if (!$job) return ['success'=>false,'message'=>'کار یافت نشد'];
        return [
            'success'=>true,
            'execution_id'=>$execution_id,
            'status'=>$job['status'],
            'processed'=>(int)$job['processed'],
            'total'=>(int)$job['total'],
            'percent'=> (int)$job['total'] ? round((int)$job['processed']/(int)$job['total']*100,1) : 0,
            'retry_count'=>(int)$job['retry_count'],
            'last_error'=>$job['last_error'],
        ];
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
