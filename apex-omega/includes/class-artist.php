<?php
namespace ApexOmega\Core;

if (!defined('ABSPATH')) exit;

/**
 * مدیریت آرتیست / خواننده — تشخیص تاکسونومی، وزن‌دهی، و خودکارسازی
 */
class ArtistManager {
    const OPTION_WEIGHTS = 'apex_omega_artist_weights';
    const OPTION_CACHE = 'apex_omega_artist_external_cache';
    const META_WEIGHT = '_apex_artist_weight';

    /**
     * تشخیص تاکسونومی آرتیست: artist, singer, hanarmand, khanandeh, performer
     */
    public static function detect_artist_taxonomy(): ?string {
        $all = get_taxonomies([], 'names');
        $candidates = ['artist','artists','singer','singers','khanandeh','khanande','hanarmand','performer','performers','music_artist'];
        foreach ($candidates as $c) {
            if (isset($all[$c])) return $c;
            // case-insensitive contains
            foreach ($all as $tax) {
                if (stripos($tax, $c) !== false) return $tax;
            }
        }
        // fallback: هر تاکسونومی اختصاصی پرجمعیت که به پست لینک است
        foreach (['post_tag','genre'] as $maybe) {
            if (isset($all[$maybe])) {
                $cnt = wp_count_terms($maybe, ['hide_empty'=>false]);
                if ($cnt > 5 && $cnt < 500) return $maybe;
            }
        }
        return null;
    }

    public static function get_artist_taxonomies(): array {
        $tax = self::detect_artist_taxonomy();
        if ($tax) return [$tax];
        // همه عمومی
        $all = get_taxonomies(['public'=>true], 'names');
        unset($all['category'],$all['post_tag']);
        return array_values($all);
    }

    /**
     * لیست آرتیست‌ها با تعداد آهنگ
     */
    public static function get_all_artists(string $taxonomy = null, int $limit = 300): array {
        if (!$taxonomy) $taxonomy = self::detect_artist_taxonomy();
        if (!$taxonomy) return [];

        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'number' => $limit,
            'orderby' => 'count',
            'order' => 'DESC',
        ]);
        if (is_wp_error($terms)) return [];

        $weights = get_option(self::OPTION_WEIGHTS, []);
        $out = [];
        foreach ($terms as $t) {
            $out[] = [
                'term_id' => $t->term_id,
                'name' => $t->name,
                'slug' => $t->slug,
                'count' => $t->count,
                'weight' => isset($weights[$t->term_id]) ? (int)$weights[$t->term_id] : 50, // 0-100
                'tier_hint' => self::weight_to_tier_hint($weights[$t->term_id] ?? 50),
            ];
        }
        return $out;
    }

    public static function weight_to_tier_hint(int $w): string {
        if ($w >= 90) return 'tier_1';
        if ($w >= 75) return 'tier_2';
        if ($w >= 60) return 'tier_3';
        if ($w >= 45) return 'tier_4';
        if ($w >= 30) return 'tier_5';
        if ($w >= 15) return 'tier_6';
        return 'tier_7';
    }

    public static function save_weights(array $weights): void {
        $clean = [];
        foreach ($weights as $tid=>$w) {
            $tid = (int)$tid;
            $w = max(0, min(100, (int)$w));
            $clean[$tid] = $w;
        }
        update_option(self::OPTION_WEIGHTS, $clean, false);
    }

    public static function get_weight_for_post(int $post_id): int {
        $tax = self::detect_artist_taxonomy();
        if (!$tax) return 50;
        $terms = wp_get_post_terms($post_id, $tax, ['fields'=>'ids']);
        if (empty($terms) || is_wp_error($terms)) return 50;
        $weights = get_option(self::OPTION_WEIGHTS, []);
        $max = 0;
        foreach ($terms as $tid) {
            $w = $weights[$tid] ?? 50;
            if ($w > $max) $max = $w;
        }
        return $max ?: 50;
    }

    /**
     * خودکار با AI: بر اساس اسم آرتیست امتیاز بده
     */
    public static function auto_weight_via_ai(array $term_ids = []): array {
        $tax = self::detect_artist_taxonomy();
        if (!$tax) return ['success'=>false,'message'=>'تاکسونومی آرتیست یافت نشد'];
        $settings = AI_Helper::get_settings();
        if (empty($settings['api_key'])) return ['success'=>false,'message'=>'کلید GapGPT تنظیم نشده'];

        if (empty($term_ids)) {
            $all = self::get_all_artists($tax, 80);
            $term_ids = array_column($all, 'term_id');
        }

        $names = [];
        foreach ($term_ids as $tid) {
            $t = get_term($tid, $tax);
            if ($t) $names[$tid] = $t->name;
        }
        if (!$names) return ['success'=>false,'message'=>'آرتیستی یافت نشد'];

        // پرامپت دسته‌ای
        $list = implode('، ', array_values($names));
        $prompt = "تو متخصص موسیقی ایرانی هستی. به هر خواننده زیر یک امتیاز شهرت 0 تا 100 بده (100=سوپراستار مثل معین، ابی، اندی؛ 0=ناشناس). خروجی فقط JSON باشد: {\"term_id\": score, ...} با همین term_idها:\n";
        foreach ($names as $tid=>$name) $prompt .= "$tid: $name\n";
        $prompt .= "مثال: {\"123\": 95, \"124\": 20}";

        $payload = [
            'model' => $settings['model'] ?: 'gpt-4o-mini',
            'messages' => [
                ['role'=>'system','content'=>'فقط JSON معتبر برگردان، بدون توضیح اضافی.'],
                ['role'=>'user','content'=>$prompt],
            ],
            'temperature'=>0.3,
            'max_tokens'=>1500,
        ];
        $resp = wp_remote_post($settings['endpoint'], [
            'headers'=>['Authorization'=>'Bearer '.$settings['api_key'],'Content-Type'=>'application/json'],
            'body'=>wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
            'timeout'=>25,
        ]);
        if (is_wp_error($resp)) return ['success'=>false,'message'=>$resp->get_error_message()];

        $body = wp_remote_retrieve_body($resp);
        $data = json_decode($body, true);
        $content = $data['choices'][0]['message']['content'] ?? '';
        if (!$content) return ['success'=>false,'message'=>'پاسخ AI خالی','raw'=>$body];

        // استخراج JSON
        if (preg_match('/\{.*\}/s', $content, $m)) $json = json_decode($m[0], true);
        else $json = json_decode(trim($content), true);

        if (!is_array($json)) return ['success'=>false,'message'=>'JSON نامعتبر','raw'=>$content];

        $clean = [];
        foreach ($json as $tid=>$score) {
            $tid = (int)$tid;
            $score = (int)$score;
            if ($tid && isset($names[$tid])) $clean[$tid] = max(0,min(100,$score));
        }
        if ($clean) self::save_weights(array_merge(get_option(self::OPTION_WEIGHTS, []), $clean));

        return ['success'=>true,'weights'=>$clean,'raw'=>$content];
    }

    /**
     * خودکار از سایت ایرانی: تلاش برای گرفتن محبوبیت خارجی (با کش 24h)
     * اگر سایت در دسترس نبود، fallback به AI
     */
    public static function auto_weight_via_external(array $term_ids = []): array {
        $tax = self::detect_artist_taxonomy();
        if (!$tax) return ['success'=>false,'message'=>'تاکسونومی یافت نشد'];

        if (empty($term_ids)) {
            $all = self::get_all_artists($tax, 60);
            $term_ids = array_column($all, 'term_id');
        }

        $weights = [];
        $cached = get_transient('apex_omega_ir_cache');
        if (!is_array($cached)) $cached = [];

        foreach ($term_ids as $tid) {
            $t = get_term($tid, $tax);
            if (!$t) continue;
            $key = 'ir_'.md5($t->name);
            if (isset($cached[$key])) {
                $weights[$tid] = $cached[$key];
                continue;
            }

            // تلاش providers
            $score = null;
            foreach (self::get_ir_providers() as $provider) {
                $score = $provider::fetch($t->name);
                if ($score !== null) break;
            }
            if ($score === null) {
                // fallback به هیستوریک: بر اساس تعداد پست‌های فعلی
                $cnt = $t->count;
                $score = min(100, max(10, 20 + log(max(1,$cnt), 2)*12));
            }
            $score = (int)$score;
            $weights[$tid] = $score;
            $cached[$key] = $score;
        }
        set_transient('apex_omega_ir_cache', $cached, DAY_IN_SECONDS);
        if ($weights) self::save_weights(array_merge(get_option(self::OPTION_WEIGHTS, []), $weights));
        return ['success'=>true,'weights'=>$weights,'source'=>'ir_sites'];
    }

    public static function get_ir_providers(): array {
        // قابل توسعه با فیلتر
        $providers = [
            \ApexOmega\Provider\RadioJavanProvider::class,
            \ApexOmega\Provider\Next1MusicProvider::class,
        ];
        return apply_filters('apex_omega_ir_providers', $providers);
    }
}
