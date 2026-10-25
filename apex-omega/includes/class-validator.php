<?php
namespace ApexOmega\Core;

if (!defined('ABSPATH')) exit;

class Validator {
    const MAX_VIEWS = 10000000; // 10M — سقف جدید به درخواست کاربر (قبلا 1.5M)
    const MAX_CASTS = 10000000;
    const MAX_RATINGS_PER_VOTE = 5; // داخلی kk بر مبنای 5

    /**
     * اعتبارسنجی بازدید
     */
    public static function validate_views($value): array {
        $errors = [];
        if ($value === '' || $value === null) {
            $errors[] = 'مقدار بازدید نمی‌تواند خالی باشد.';
            return [false, $errors, 0];
        }
        if (!is_numeric($value)) {
            $errors[] = 'مقدار بازدید باید عددی باشد.';
            return [false, $errors, 0];
        }
        $v = (int) $value;
        if ((string)$v !== (string)intval($value) && floor((float)$value) != (float)$value) {
            $errors[] = 'تعداد بازدید باید عدد صحیح باشد (اعشاری مجاز نیست).';
        }
        if ($v < 0) $errors[] = 'تعداد بازدید منفی مجاز نیست.';
        if ($v > self::MAX_VIEWS) $errors[] = sprintf('تعداد بازدید از سقف مجاز (%s) بیشتر است.', number_format_i18n(self::MAX_VIEWS));
        return [empty($errors), $errors, $v];
    }

    public static function validate_casts($value): array {
        $errors = [];
        if ($value === '' || $value === null) {
            $errors[] = 'تعداد رأی نمی‌تواند خالی باشد.';
            return [false, $errors, 0];
        }
        if (!is_numeric($value)) {
            $errors[] = 'تعداد رأی باید عددی باشد.';
            return [false, $errors, 0];
        }
        $v = (int) $value;
        if ($v < 0) $errors[] = 'تعداد رأی منفی مجاز نیست.';
        if ((string)$v !== (string)intval($value) && floor((float)$value) != (float)$value) {
            $errors[] = 'تعداد رأی باید عدد صحیح باشد.';
        }
        if ($v > self::MAX_CASTS) $errors[] = sprintf('تعداد رأی از سقف مجاز (%s) بیشتر است.', number_format_i18n(self::MAX_CASTS));
        return [empty($errors), $errors, $v];
    }

    /**
     * اعتبارسنجی میانگین
     */
    public static function validate_avg($value, int $best = 5): array {
        $errors = [];
        if ($value === '' || $value === null) {
            $errors[] = 'میانگین امتیاز نمی‌تواند خالی باشد.';
            return [false, $errors, 0];
        }
        if (!is_numeric($value)) {
            $errors[] = 'میانگین امتیاز باید عددی باشد.';
            return [false, $errors, 0];
        }
        $v = (float) $value;
        if ($v < 0) $errors[] = 'میانگین منفی مجاز نیست.';
        if ($v > $best) $errors[] = sprintf('میانگین (%s) از سقف امتیاز (%s) بیشتر است.', $v, $best);
        // گام 0.1
        return [empty($errors), $errors, round($v, 1)];
    }

    public static function validate_ratings_total($ratings, int $casts, int $best = 5): array {
        $errors = [];
        if (!is_numeric($ratings)) {
            $errors[] = 'مجموع امتیازها باید عددی باشد.';
            return [false, $errors, 0];
        }
        $r = (float) $ratings;
        if ($r < 0) $errors[] = 'مجموع امتیاز منفی مجاز نیست.';
        if ($casts === 0 && $r != 0) $errors[] = 'وقتی تعداد رأی صفر است، مجموع امتیاز باید صفر باشد.';
        if ($casts > 0) {
            // هر رأی بین 1 تا 5 امتیاز (بر مبنای 5)
            $max = $casts * 5;
            $min = $casts * 1; // حداقل منطقی
            if ($r > $max) $errors[] = sprintf('مجموع امتیاز (%s) از حداکثر ممکن (%s = %s رأی × ۵) بیشتر است.', $r, $max, $casts);
            if ($r < 0) $errors[] = 'مجموع امتیاز نامعتبر است.';
        }
        return [empty($errors), $errors, $r];
    }

    /**
     * بررسی جهش بزرگ
     */
    public static function check_volatility(int $old, int $new, ?int $max_step = null, ?float $max_percent = null, bool $is_decrease = false): array {
        $warnings = [];
        $requires_confirm = false;
        $delta = $new - $old;
        $abs_delta = abs($delta);
        $percent = $old > 0 ? ($abs_delta / $old * 100) : ($new > 0 ? 100 : 0);

        // 0 یا خالی = بدون محدودیت (پیش‌فرض). این باگ قبلی بود که 0 باعث خطای 1249900% می‌شد
        if ($max_step !== null && $max_step !== '' && (int)$max_step > 0 && $abs_delta > (int)$max_step) {
            $warnings[] = sprintf('تغییر %s از سقف مجاز هر اجرا (%s) بیشتر است.', number_format_i18n($abs_delta), number_format_i18n($max_step));
            $requires_confirm = true;
        }
        if ($max_percent !== null && $max_percent !== '' && (float)$max_percent > 0 && $percent > (float)$max_percent) {
            $warnings[] = sprintf('درصد تغییر %.1f%% از سقف مجاز (%.1f%%) بیشتر است.', $percent, $max_percent);
            $requires_confirm = true;
        }

        // کاهش بزرگ حساس است
        if ($delta < 0 && $percent >= 20) {
            $warnings[] = sprintf('کاهش قابل توجه %.1f%% شناسایی شد. این عملیات نیاز به تأیید جداگانه دارد.', $percent);
            $requires_confirm = true;
        }

        // جهش بسیار بزرگ (مثلاً ۵۰٪) — فقط وقتی مقدار فعلی معنادار است، نه برای پست‌های تازه با 1 بازدید
        // باگ قبلی: 1→10000 را با 1249900% مسدود می‌کرد. الان old<100 را نادیده می‌گیریم
        if ($percent >= 50 && $abs_delta > 1000 && $old >= 100) {
            $warnings[] = 'جهش بسیار بزرگ؛ لطفاً مقدار هدف را بازبینی کنید.';
            $requires_confirm = true;
        }

        return [
            'delta' => $delta,
            'abs_delta' => $abs_delta,
            'percent' => $percent,
            'warnings' => $warnings,
            'requires_confirm' => $requires_confirm,
        ];
    }

    public static function validate_schedule(array $data): array {
        $errors = [];

        $required = ['post_id', 'field_type', 'target_value', 'interval_seconds'];
        foreach ($required as $k) {
            if (!isset($data[$k]) || $data[$k] === '' || $data[$k] === null) {
                $errors[] = "فیلد $k الزامی است.";
            }
        }

        if (isset($data['post_id']) && !(int)$data['post_id'] > 0) $errors[] = 'شناسه نوشته نامعتبر است.';
        if (isset($data['field_type']) && !in_array($data['field_type'], ['views', 'kksr_casts', 'kksr_ratings', 'kksr_avg', 'kksr_combined'], true)) $errors[] = 'نوع فیلد نامعتبر است.';

        if (isset($data['interval_seconds'])) {
            $iv = (int) $data['interval_seconds'];
            if ($iv < 60) $errors[] = 'فاصله اجرا حداقل ۶۰ ثانیه است.';
            elseif ($iv < 300) $errors[] = 'هشدار: فاصله کمتر از ۵ دقیقه فشار زیادی به سرور وارد می‌کند.';
            if ($iv > 2592000) $errors[] = 'فاصله اجرا حداکثر ۳۰ روز است.';
        }

        if (isset($data['start_at_gmt']) && isset($data['end_at_gmt']) && $data['start_at_gmt'] && $data['end_at_gmt']) {
            if (strtotime($data['start_at_gmt']) >= strtotime($data['end_at_gmt'])) {
                $errors[] = 'زمان شروع باید قبل از پایان باشد.';
            }
        }

        if (isset($data['target_value']) && !is_numeric($data['target_value'])) $errors[] = 'مقدار هدف باید عددی باشد.';

        return $errors;
    }
}
