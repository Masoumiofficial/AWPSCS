<?php
namespace ApexOmega\Core;

if (!defined('ABSPATH')) exit;

/**
 * آداپتور kk Star Ratings
 * کشف خودکار ساختار واقعی ذخیره‌سازی بدون فرض قطعی
 */
class Adapter_KKSR {

    const META_CASTS_CANDIDATES = ['_kksr_casts', '_kksr_ratings_casts', '_kk_ratings_casts', 'kksr_casts'];
    const META_RATINGS_CANDIDATES = ['_kksr_ratings', '_kksr_rating', '_kksr_total', '_kk_ratings_ratings', 'kksr_ratings'];
    const META_AVG_CANDIDATES = ['_kksr_avg', '_kksr_average', '_kksr_score', 'kksr_avg'];
    const OPTION_STARS_CANDIDATES = ['kksr_stars', 'kksr_star_count', 'kksr_settings', 'kksr_options'];

    private static $detected = null;

    /**
     * تشخیص ساختار واقعی
     */
    public static function detect(): array {
        if (self::$detected !== null) return self::$detected;

        $info = [
            'plugin_active' => false,
            'plugin_version' => null,
            'storage' => 'unknown', // postmeta|table|unknown
            'meta_casts' => null,
            'meta_ratings' => null,
            'meta_avg' => null,
            'best' => 5,
            'has_api' => false,
            'api_functions' => [],
            'confidence' => 'low',
            'warnings' => [],
        ];

        // بررسی افزونه فعال
        if (defined('KK_STAR_RATINGS') || class_exists('\Bhittani\StarRating\KKSR') || function_exists('kk_star_ratings') || function_exists('kksr_freemius')) {
            $info['plugin_active'] = true;
        } else {
            // بررسی از لیست افزونه‌های فعال
            $active = get_option('active_plugins', []);
            foreach ($active as $p) {
                if (strpos($p, 'kk-star-ratings') !== false) {
                    $info['plugin_active'] = true;
                    break;
                }
            }
        }

        // نسخه
        if (function_exists('get_plugin_data')) {
            $plugins = get_plugins();
            foreach ($plugins as $file => $data) {
                if (stripos($file, 'kk-star-ratings') !== false) {
                    $info['plugin_version'] = $data['Version'] ?? null;
                    break;
                }
            }
        }

        // تشخیص متاکی‌ها با نمونه‌برداری از پست‌ها
        global $wpdb;
        $candidates_casts = self::META_CASTS_CANDIDATES;
        $candidates_ratings = self::META_RATINGS_CANDIDATES;
        $candidates_avg = self::META_AVG_CANDIDATES;

        // جستجو در postmeta
        $found_casts = self::find_existing_meta_key($candidates_casts);
        $found_ratings = self::find_existing_meta_key($candidates_ratings);
        $found_avg = self::find_existing_meta_key($candidates_avg);

        if ($found_casts) {
            $info['meta_casts'] = $found_casts;
            $info['storage'] = 'postmeta';
            $info['confidence'] = 'high';
        } else {
            // پیش‌فرض امن: معروف‌ترین کلیدها
            $info['meta_casts'] = '_kksr_casts';
            $info['warnings'][] = 'کلید casts با نمونه‌برداری یافت نشد؛ از _kksr_casts به‌عنوان پیش‌فرض استفاده شد. پیش از نوشتن، اعتبارسنجی دستی توصیه می‌شود.';
        }

        if ($found_ratings) {
            $info['meta_ratings'] = $found_ratings;
            $info['storage'] = 'postmeta';
            if ($info['confidence'] !== 'high') $info['confidence'] = 'medium';
        } else {
            $info['meta_ratings'] = '_kksr_ratings';
            $info['warnings'][] = 'کلید ratings یافت نشد؛ از _kksr_ratings استفاده شد.';
        }

        if ($found_avg) {
            $info['meta_avg'] = $found_avg;
        } else {
            // avg در نسخه‌های جدید legacy است اما هنوز نوشته می‌شود
            $info['meta_avg'] = '_kksr_avg';
        }

        // تشخیص تعداد ستاره‌ها
        $best = self::detect_best();
        $info['best'] = $best;

        // API توابع
        $funcs = [];
        if (function_exists('kk_star_ratings')) $funcs[] = 'kk_star_ratings';
        if (function_exists('kksr')) $funcs[] = 'kksr';
        if (class_exists('\Bhittani\StarRating\KKSR')) $funcs[] = 'Bhittani\\StarRating\\KKSR';
        $info['api_functions'] = $funcs;
        $info['has_api'] = !empty($funcs);

        // اگر هیچ نمونه‌ای نبود اما افزونه فعال است -> اعتماد متوسط
        if (!$info['plugin_active']) {
            $info['warnings'][] = 'افزونه kk Star Ratings فعال نیست. مدیریت دستی امکان‌پذیر است اما نمایش در فرانت‌اند نیاز به افزونه دارد.';
            $info['confidence'] = 'low';
        }

        self::$detected = $info;
        // کش کوتاه‌مدت
        set_transient('apex_omega_kksr_detect', $info, 3600);
        return $info;
    }

    private static function find_existing_meta_key(array $candidates): ?string {
        global $wpdb;
        // بررسی وجود هر کلید در جدول postmeta
        foreach ($candidates as $key) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s LIMIT 1", $key));
            if ($exists && intval($exists) > 0) {
                return $key;
            }
        }
        return null;
    }

    private static function detect_best(): int {
        // تلاش برای خواندن از گزینه‌های مختلف
        $try = get_option('kksr_stars');
        if ($try !== false && $try !== null && is_numeric($try)) {
            $v = (int) $try;
            if ($v >= 1 && $v <= 10) return $v;
        }
        // گزینه‌های سریالیزه
        $opts = get_option('kksr_settings');
        if (is_array($opts) && isset($opts['stars']) && is_numeric($opts['stars'])) {
            return max(1, min(10, (int) $opts['stars']));
        }
        $opts2 = get_option('kksr_options');
        if (is_array($opts2) && isset($opts2['stars'])) {
            return max(1, min(10, (int) $opts2['stars']));
        }
        // پیش‌فرض استاندارد
        return 5;
    }

    public static function invalidate_cache(): void {
        self::$detected = null;
        delete_transient('apex_omega_kksr_detect');
    }

    /**
     * خواندن وضعیت فعلی یک پست
     */
    public static function get_stats(int $post_id): array {
        $detect = self::detect();
        $casts = get_post_meta($post_id, $detect['meta_casts'], true);
        $ratings = get_post_meta($post_id, $detect['meta_ratings'], true);
        $avg_raw = get_post_meta($post_id, $detect['meta_avg'], true);

        $casts = is_numeric($casts) ? (int) $casts : 0;
        $ratings = is_numeric($ratings) ? (float) $ratings : 0;
        $best = $detect['best'];

        // محاسبه میانگین دقیق
        $avg = 0;
        if ($casts > 0 && $ratings > 0) {
            $avg = round(($ratings / $casts) / 5 * $best, 1, PHP_ROUND_HALF_DOWN);
            // محدودسازی
            $avg = min(max($avg, 0), $best);
        } elseif (is_numeric($avg_raw) && $avg_raw !== '') {
            $avg = (float) $avg_raw;
            $avg = min(max($avg, 0), $best);
        }

        return [
            'casts' => max(0, $casts),
            'ratings' => max(0, $ratings),
            'avg' => $avg,
            'best' => $best,
            'meta_casts' => $detect['meta_casts'],
            'meta_ratings' => $detect['meta_ratings'],
            'meta_avg' => $detect['meta_avg'],
        ];
    }

    /**
     * نوشتن امن آمار - فقط از طریق Validator باید صدا زده شود
     */
    public static function set_stats(int $post_id, int $casts, $ratings, ?float $avg = null, string $source = 'manual', ?string $execution_id = null): bool {
        $detect = self::detect();
        $best = $detect['best'];

        // اعتبارسنجی پایه
        $casts = max(0, $casts);
        $ratings = max(0, (float) $ratings);

        // محاسبه avg اگر داده نشد
        if ($avg === null) {
            if ($casts > 0) {
                $avg = round(($ratings / $casts) / 5 * $best, 1, PHP_ROUND_HALF_DOWN);
                $avg = min(max($avg, 0), $best);
            } else {
                $avg = 0;
            }
        }

        // دریافت مقادیر قدیمی برای تاریخچه
        $old = self::get_stats($post_id);

        // استفاده از تراکنش اگر InnoDB باشد
        global $wpdb;
        $wpdb->query('START TRANSACTION');
        try {
            update_post_meta($post_id, $detect['meta_casts'], $casts);
            update_post_meta($post_id, $detect['meta_ratings'], $ratings);
            // avg را برای سازگاری legacy هم بنویس
            update_post_meta($post_id, $detect['meta_avg'], $avg);

            // کش افزونه را پاک کن
            if (function_exists('wp_cache_delete')) {
                wp_cache_delete($post_id, 'post_meta');
            }
            // هوک اختصاصی برای سایر افزونه‌ها
            do_action('apex_omega_kksr_updated', $post_id, $old, [
                'casts' => $casts,
                'ratings' => $ratings,
                'avg' => $avg,
                'best' => $best,
            ], $source, $execution_id);

            $wpdb->query('COMMIT');
            return true;
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            error_log('[APEX Ω] KKSR set_stats failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * بررسی اینکه آیا مقدار جدید با میانگین سازگار است
     */
    public static function validate_consistency(int $casts, float $ratings, float $avg, int $best): array {
        $errors = [];
        if ($casts === 0 && ($ratings != 0 || $avg != 0)) {
            $errors[] = 'تعداد رأی صفر است اما مجموع امتیاز یا میانگین غیرصفر ثبت شده.';
        }
        if ($casts > 0) {
            $expected_avg = round(($ratings / $casts) / 5 * $best, 1, PHP_ROUND_HALF_DOWN);
            if (abs($expected_avg - $avg) > 0.15) {
                $errors[] = sprintf('ناسازگاری: میانگین مورد انتظار %s است اما %s وارد شده (اختلاف بیش از ۰٫۱۵).', $expected_avg, $avg);
            }
            // هر رأی حداقل 1 و حداکثر best امتیاز دارد
            $min_ratings = $casts * 1; // اگر مقیاس 1..best اما ذخیره بر مبنای ۵ است؟
            // در kk، ratings ذخیره بر مبنای ۵ است، سپس به best نگاشت می‌شود
            // لذا محتاطانه چک می‌کنیم
            if ($ratings < 0) $errors[] = 'مجموع امتیاز منفی غیرمجاز است.';
        }
        return $errors;
    }

    public static function get_compatibility_status(): array {
        $d = self::detect();
        $status = 'ok';
        $messages = [];
        if (!$d['plugin_active']) {
            $status = 'warning';
            $messages[] = 'kk Star Ratings فعال نیست.';
        }
        if ($d['confidence'] === 'low') {
            $status = $status === 'ok' ? 'warning' : $status;
            $messages[] = 'ساختار داده با اطمینان پایین شناسایی شد.';
        }
        foreach ($d['warnings'] as $w) $messages[] = $w;
        if ($d['storage'] === 'unknown') {
            $status = 'error';
            $messages[] = 'محل ذخیره‌سازی نامشخص است.';
        }
        return [
            'status' => $status, // ok|warning|error
            'detect' => $d,
            'messages' => $messages,
        ];
    }

    /**
     * فیلتر برای نمایش - اگر بخواهیم مقدار نمایشی را override کنیم بدون دست زدن به دیتابیس
     * در حال حاضر ما مستقیماً متا را می‌نویسیم، اما این فیلتر برای سازگاری آینده نگه داشته می‌شود
     */
    public static function register_display_filters(): void {
        // kksr از فیلترهای kksr_score و kksr_count استفاده می‌کند
        add_filter('kksr_score', [__CLASS__, 'filter_score'], 10, 4);
        add_filter('kksr_count', [__CLASS__, 'filter_count'], 10, 3);
    }

    public static function filter_score($score, $best, $post_id, $slug) {
        // اگر slug خاص باشد (مثلاً برای آرشیو) دست نزن
        if ($slug) return $score;
        // ما مقدار واقعی را دستکاری نمی‌کنیم مگر اینکه حالت override فعال باشد
        // در حال حاضر مستقیم از DB می‌خوانیم، پس نیازی به override نیست
        return $score;
    }

    public static function filter_count($count, $post_id, $slug) {
        if ($slug) return $count;
        return $count;
    }
}
