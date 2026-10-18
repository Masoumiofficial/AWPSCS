<?php
namespace ApexOmega\Provider;

if (!defined('ABSPATH')) exit;

/**
 * Provider interface برای سایت‌های ایرانی
 * هر provider باید fetch(string $artist_name): ?int برگرداند (0-100) یا null اگر نیافت
 */
abstract class AbstractProvider {
    // کش داخلی
    protected static function cached_get(string $url, array $args = []) {
        $key = 'apex_ir_'.md5($url);
        $cached = get_transient($key);
        if ($cached !== false) return $cached;
        $resp = wp_remote_get($url, array_merge([
            'timeout'=>12,
            'headers'=>['User-Agent'=>'APEX-Omega/1.1 (+https://etehadwp.com)'],
        ], $args));
        if (is_wp_error($resp)) return null;
        $code = wp_remote_retrieve_response_code($resp);
        if ($code !== 200) return null;
        $body = wp_remote_retrieve_body($resp);
        set_transient($key, $body, 6 * HOUR_IN_SECONDS);
        return $body;
    }

    abstract public static function fetch(string $artist): ?int;
    // تبدیل تعداد نتیجه/بازدید به امتیاز 0-100
    protected static function normalize(int $raw_count, int $max = 5000): int {
        // logarithmic
        if ($raw_count <=0) return 10;
        $score = 20 + log(max(1,$raw_count), 10) * 28; // 1->20, 10->48, 100->76, 1000->104→100
        return max(5, min(100, (int)$score));
    }
}

/**
 * رادیوجوان - https://www.radiojavan.com/search?q=artist
 * تعداد آهنگ یافت‌شده را به امتیاز تبدیل می‌کنیم
 */
class RadioJavanProvider extends AbstractProvider {
    public static function fetch(string $artist): ?int {
        $q = urlencode($artist);
        $url = "https://www.radiojavan.com/search?q={$q}";
        $html = self::cached_get($url);
        if (!$html) return null;
        // شمارش تقریبی تگ‌های mp3Row یا data-count
        $cnt = 0;
        if (preg_match('/(\d+)\s*(?:songs?|آهنگ)/i', $html, $m)) $cnt = (int)$m[1];
        else $cnt = substr_count($html, 'mp3Row') + substr_count($html, 'songRow');
        if ($cnt === 0) return null;
        return self::normalize($cnt, 300);
    }
}

/**
 * نکس1موزیک - https://next1music.ir/?s=artist
 */
class Next1MusicProvider extends AbstractProvider {
    public static function fetch(string $artist): ?int {
        $q = urlencode($artist);
        $url = "https://next1music.ir/?s={$q}";
        $html = self::cached_get($url);
        if (!$html) return null;
        $cnt = substr_count($html, 'post-') ; // هر پست یک article
        // فیلتر false positive
        if ($cnt < 2) {
            if (stripos($html, 'چیزی یافت نشد') !== false) return null;
        }
        if ($cnt === 0) return null;
        return self::normalize($cnt, 200);
    }
}

/**
 * fallback هوک برای توسعه‌دهنده: اضافه کردن provider سفارشی
 * add_filter('apex_omega_ir_providers', fn($p)=> array_merge($p, [MyProvider::class]));
 */
