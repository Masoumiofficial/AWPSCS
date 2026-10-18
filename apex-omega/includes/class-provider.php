<?php
namespace ApexOmega\Provider;

if (!defined('ABSPATH')) exit;

/**
 * Provider interface برای سایت‌های ایرانی
 * هر provider باید fetch(string $artist_name): ?int برگرداند (0-100) یا null اگر نیافت
 */
abstract class AbstractProvider {
    const FAIL_OPTION = 'apex_omega_provider_failures';
    const FAIL_THRESHOLD = 3;
    const FAIL_COOLDOWN = DAY_IN_SECONDS; // 24h

    protected static function provider_key(): string {
        return strtolower((new \ReflectionClass(static::class))->getShortName());
    }

    protected static function record_failure(): void {
        $opt = get_option(self::FAIL_OPTION, []);
        $k = static::provider_key();
        $opt[$k] = $opt[$k] ?? ['count'=>0,'last'=>0];
        $opt[$k]['count'] = (int)$opt[$k]['count'] + 1;
        $opt[$k]['last'] = time();
        update_option(self::FAIL_OPTION, $opt, false);
    }
    protected static function record_success(): void {
        $opt = get_option(self::FAIL_OPTION, []);
        $k = static::provider_key();
        if (isset($opt[$k])) { $opt[$k]['count']=0; $opt[$k]['last']=0; update_option(self::FAIL_OPTION,$opt,false); }
    }
    public static function is_in_cooldown(): bool {
        $opt = get_option(self::FAIL_OPTION, []);
        $k = static::provider_key();
        if (empty($opt[$k])) return false;
        if ((int)$opt[$k]['count'] < self::FAIL_THRESHOLD) return false;
        $elapsed = time() - (int)$opt[$k]['last'];
        if ($elapsed < self::FAIL_COOLDOWN) return true;
        // cooldown passed, reset
        $opt[$k]['count']=0; update_option(self::FAIL_OPTION,$opt,false);
        return false;
    }
    public static function reset_failures(): void {
        delete_option(self::FAIL_OPTION);
    }

    // کش داخلی
    protected static function cached_get(string $url, array $args = []) {
        $key = 'apex_ir_'.md5($url);
        $cached = get_transient($key);
        if ($cached !== false) return $cached;
        // rate-limit: max 1 req per 2 sec
        $last_req = get_transient('apex_ir_rate_limit');
        if ($last_req) usleep(400000); // 0.4s gentle throttle
        set_transient('apex_ir_rate_limit', 1, 2);
        $resp = wp_remote_get($url, array_merge([
            'timeout'=>12,
            'headers'=>['User-Agent'=>'APEX-Omega/1.2 (+https://etehadwp.com)'],
        ], $args));
        if (is_wp_error($resp)) { static::record_failure(); return null; }
        $code = wp_remote_retrieve_response_code($resp);
        if ($code !== 200) { static::record_failure(); return null; }
        $body = wp_remote_retrieve_body($resp);
        static::record_success();
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
        if (static::is_in_cooldown()) return null;
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
        if (static::is_in_cooldown()) return null;
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
 * بیا2موزیک - https://www.mybia2music.com/?s=artist
 * منبع درخواستی کاربر — mybia2music
 */
class MyBia2MusicProvider extends AbstractProvider {
    public static function fetch(string $artist): ?int {
        if (static::is_in_cooldown()) return null;
        $q = urlencode($artist);
        // mybia2music از /?s= و /search/ هر دو پشتیبانی می‌کند
        $urls = [
            "https://www.mybia2music.com/?s={$q}",
            "https://mybia2music.com/?s={$q}",
        ];
        foreach ($urls as $url) {
            $html = self::cached_get($url);
            if (!$html) continue;
            // mybia2music معمولاً post- یا entry یا homayoun-shajarian?more=musics
            $cnt = substr_count($html, 'post-') + substr_count($html, 'entry-') + substr_count($html, 'music-item');
            if ($cnt < 2 && stripos($html, 'چیزی یافت نشد') !== false) continue;
            if ($cnt > 0) return self::normalize($cnt, 250);
            // fallback: شمارش لینک‌های /artists/
            $cnt2 = substr_count(strtolower($html), strtolower($artist));
            if ($cnt2 > 3) return self::normalize($cnt2, 400);
        }
        return null;
    }
}

/**
 * گنجا2موزیک / گنجاتو موزیک - https://ganja2music.com / https://ganjat2music.com
 */
class Ganja2MusicProvider extends AbstractProvider {
    public static function fetch(string $artist): ?int {
        if (static::is_in_cooldown()) return null;
        $q = urlencode($artist);
        $urls = [
            "https://ganja2music.com/?s={$q}",
            "https://www.ganja2music.com/?s={$q}",
            "https://ganjat2music.com/?s={$q}",
        ];
        foreach ($urls as $url) {
            $html = self::cached_get($url);
            if (!$html) continue;
            $cnt = substr_count($html, 'post-') + substr_count($html, 'td_block');
            if ($cnt < 2 && stripos($html, 'یافت نشد') !== false) continue;
            if ($cnt > 0) return self::normalize($cnt, 220);
        }
        return null;
    }
}

/**
 * رز موزیک - https://rezmusic.ir/?s=artist
 */
class RezMusicProvider extends AbstractProvider {
    public static function fetch(string $artist): ?int {
        if (static::is_in_cooldown()) return null;
        $q = urlencode($artist);
        $url = "https://rezmusic.ir/?s={$q}";
        $html = self::cached_get($url);
        if (!$html) return null;
        $cnt = substr_count($html, 'post-') + substr_count($html, 'music-post');
        if ($cnt < 2 && stripos($html, 'چیزی یافت نشد') !== false) return null;
        if ($cnt === 0) return null;
        return self::normalize($cnt, 200);
    }
}

/**
 * بیا2 موزیک آلترناتیو دامنه بدون www
 */
class Bia2MusicAltProvider extends AbstractProvider {
    public static function fetch(string $artist): ?int {
        if (static::is_in_cooldown()) return null;
        $q = urlencode($artist);
        $url = "https://bia2music.com/?s={$q}";
        $html = self::cached_get($url);
        if (!$html) return null;
        $cnt = substr_count($html, 'post-');
        if ($cnt < 2) return null;
        return self::normalize($cnt, 180);
    }
}

/**
 * fallback هوک برای توسعه‌دهنده: اضافه کردن provider سفارشی
 * add_filter('apex_omega_ir_providers', fn($p)=> array_merge($p, [MyProvider::class]));
 */
