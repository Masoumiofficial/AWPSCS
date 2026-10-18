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

    const OPTION_TAXONOMY = 'apex_omega_artist_taxonomy';

    /**
     * تشخیص تاکسونومی آرتیست: اول انتخاب دستی کاربر، سپس حدس خودکار
     */
    public static function detect_artist_taxonomy(): ?string {
        $manual = get_option(self::OPTION_TAXONOMY, '');
        if (!empty($manual)) {
            $all = get_taxonomies([], 'names');
            if (isset($all[$manual])) return $manual;
            // اگر دستی پاک شده یا نامعتبر، fallback به حدس
        }
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
                if (!is_wp_error($cnt) && $cnt > 5 && $cnt < 500) return $maybe;
            }
        }
        return null;
    }

    public static function get_available_taxonomies(): array {
        $all = get_taxonomies(['public'=>true], 'objects');
        // also include non-public but assigned to post
        $all2 = get_taxonomies([], 'objects');
        $merged = array_merge($all, $all2);
        $out = [];
        foreach ($merged as $tax => $obj) {
            if (empty($tax) || isset($out[$tax])) continue;
            $cnt = wp_count_terms($tax, ['hide_empty'=>false]);
            if (is_wp_error($cnt)) $cnt = 0;
            $out[$tax] = [
                'name' => $tax,
                'label' => $obj->label ?? $tax,
                'count' => $cnt,
                'public' => !empty($obj->public),
            ];
        }
        return $out;
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
        // الگوی ریتم حرفه‌ای — اولویت خواننده، عنوان فقط دریچه (با قاب و فیلتر)
        $rhythm = self::get_rhythm_settings();
        $tax = self::detect_artist_taxonomy();
        $weights = get_option(self::OPTION_WEIGHTS, []);
        $max = 0; $artist_names = [];
        if ($tax) {
            $terms = wp_get_post_terms($post_id, $tax, ['fields'=>'all']);
            if (!empty($terms) && !is_wp_error($terms)) {
                foreach ($terms as $tm) {
                    $tid = is_object($tm) ? $tm->term_id : (int)$tm;
                    $w = $weights[$tid] ?? 50;
                    if ($w > $max) $max = $w;
                    if (is_object($tm)) $artist_names[] = $tm->name;
                }
            }
        }
        // اگر تاکسونومی نداشتیم، سعی کن خواننده را از عنوان استخراج کنی (fallback)
        if (!$max) $max = 50; // بی‌طرف — اما با قاب پایین می‌ماند

        $title = get_the_title($post_id);
        // خواننده اصلی برای ترکیب — اولین نام
        $primary_artist = $artist_names[0] ?? self::extract_artist_from_title($title) ?? '';

        // اگر rhythm بگوید فقط خواننده معیار است، عنوان را فقط در صورت تطابق جفت بررسی کن
        $title_score = self::score_title_for_post($post_id, $primary_artist ?: null);

        // اگر عنوان امتیازی نداد → همان وزن خواننده
        if ($title_score === null) return (int)$max;

        // الگو: وزن ترکیبی با قاب و فیلتر تطابق
        // 1) اگر خواننده ناشناس است (weight < unknown_threshold)، عنوان را تقریباً نادیده بگیر
        $artist_w = (float)$max;
        $title_w = (float)$title_score;

        // قاب سخت: خواننده نوپا هرگز با یک عنوان تکراری به الماس نمی‌جهد
        if ($artist_w < $rhythm['unknown_threshold']) {
            // نسبت عنوان را به شدت کم کن
            $eff_title_ratio = $rhythm['title_ratio'] * ($artist_w / 100); // مثلا ناشناس 20 → 0.15*0.2=0.03
            $combined = $artist_w * (1 - $eff_title_ratio) + $title_w * $eff_title_ratio;
            // سقف سخت: حتی با عنوان 100، از cap فراتر نرو
            if ($combined > $rhythm['unknown_cap']) $combined = $rhythm['unknown_cap'];
            // اضافه: اگر تطابق جفت (artist+title) تایید نشده باشد، باز هم 5 نمره کم کن
            if ($rhythm['require_pair'] && !self::is_pair_match($primary_artist, $title)) {
                $combined = min($combined, $artist_w + 5);
            }
            return (int) round($combined);
        }

        // 2) خواننده نیمه‌شناخته: 30-60
        if ($artist_w < 60) {
            $eff_title_ratio = $rhythm['title_ratio'] * 0.6; // عنوان نصف اثر
            $combined = $artist_w * (1 - $eff_title_ratio) + $title_w * $eff_title_ratio;
            // سقف نرم برای این بازه
            if ($title_w >= 90 && $artist_w < 45) $combined = min($combined, 62);
            return (int) round($combined);
        }

        // 3) خواننده شناخته‌شده (>=60): الگوی اصلی 85/15 با اجازه جهش محدود
        $artist_ratio = $rhythm['artist_ratio']; // 0.85
        $title_ratio = $rhythm['title_ratio'];   // 0.15
        $combined = $artist_w * $artist_ratio + $title_w * $title_ratio;
        // اگر عنوان هیتِ تاییدشده + خواننده قوی → اجازه جهش تا +12
        if ($title_w >= 90 && self::is_pair_match($primary_artist, $title)) {
            $combined = min(100, $combined + 8);
        }
        // هرگز پایین‌تر از وزن خواننده نیا (عنوان منفی ندارد)
        if ($combined < $artist_w) $combined = $artist_w;

        return (int) round(max(0, min(100, $combined)));
    }

    public static function get_rhythm_settings(): array {
        $defaults = [
            'artist_ratio' => 0.85,
            'title_ratio' => 0.15,
            'unknown_threshold' => 30, // زیر این = نوپا
            'unknown_cap' => 45,      // سقف نوپا حتی با عنوان هیت
            'require_pair' => true,   // عنوان هیت فقط با جفت خواننده+عنوان معتبر باشد
        ];
        $saved = get_option('apex_omega_rhythm', []);
        if (!is_array($saved)) $saved = [];
        $out = wp_parse_args($saved, $defaults);
        // نرمال‌سازی
        $out['artist_ratio'] = max(0.5, min(0.95, (float)$out['artist_ratio']));
        $out['title_ratio'] = 1 - $out['artist_ratio'];
        $out['unknown_threshold'] = max(15, min(50, (int)$out['unknown_threshold']));
        $out['unknown_cap'] = max(30, min(65, (int)$out['unknown_cap']));
        $out['require_pair'] = !empty($out['require_pair']);
        return $out;
    }
    public static function save_rhythm_settings(array $data): void {
        $cur = self::get_rhythm_settings();
        $new = [
            'artist_ratio' => isset($data['artist_ratio']) ? (float)$data['artist_ratio'] : $cur['artist_ratio'],
            'unknown_threshold' => isset($data['unknown_threshold']) ? (int)$data['unknown_threshold'] : $cur['unknown_threshold'],
            'unknown_cap' => isset($data['unknown_cap']) ? (int)$data['unknown_cap'] : $cur['unknown_cap'],
            'require_pair' => isset($data['require_pair']) ? !empty($data['require_pair']) : $cur['require_pair'],
        ];
        // اگر title_ratio فرستاده شد، artist_ratio را برعکس حساب کن
        if (isset($data['title_ratio'])) $new['artist_ratio'] = 1 - (float)$data['title_ratio'];
        update_option('apex_omega_rhythm', $new, false);
    }

    // استخراج خواننده از عنوان برای fallback — الگوهای رایج: "آهنگ - خواننده" یا "خواننده - آهنگ"
    public static function extract_artist_from_title(string $title): ?string {
        if (!$title) return null;
        // الگو: "بهت قول میدم - محسن یگانه" یا "محسن یگانه - بهت قول میدم"
        if (preg_match('/(.+?)\s*[—\-–]\s*(.+)/u', $title, $m)) {
            // هر دو طرف را به عنوان کاندید برگردان — اولی احتمال بیشتر خواننده است اگر در لیست باشد
            $a = trim($m[1]); $b = trim($m[2]);
            // اگر یکی از آن‌ها در لیست هنرمندان شناخته‌شده باشد، آن را انتخاب کن
            $weights = get_option(self::OPTION_WEIGHTS, []);
            // اینجا فقط حدس طولی: کوتاه‌تر معمولاً خواننده است
            if (mb_strlen($a) < mb_strlen($b) && mb_strlen($a) < 30) return $a;
            return $a;
        }
        return null;
    }

    // آیا جفت خواننده+عنوان مطابق هیت واقعی است؟ مو لای درز نرود.
    public static function is_pair_match(string $artist, string $title): bool {
        if (!$artist || !$title) return false;
        $a = mb_strtolower(trim($artist));
        $t = mb_strtolower(trim($title));
        $pairs = apply_filters('apex_omega_hit_pairs', [
            'محسن یگانه::بهت قول میدم' => 99,
            'معین::عاشقانه' => 85,
            'ابی::پاییز' => 88,
            // لیست قابل توسعه — کلید "خواننده::عنوان جزئی"
        ]);
        foreach ($pairs as $key => $score) {
            $parts = explode('::', mb_strtolower($key));
            if (count($parts) !== 2) continue;
            [$pa, $pt] = $parts;
            if (mb_stripos($a, $pa) !== false && mb_stripos($t, $pt) !== false) return true;
        }
        // فالوک هوشمند: اگر AI جفت را تایید کند
        $pair_score = self::score_pair_via_ai($artist, $title);
        if ($pair_score !== null && $pair_score >= 85) return true;
        return false;
    }

    // امتیاز عنوان — حالا با زمینه خواننده (جفت)، نه عنوان تنها
    public static function score_title_for_post(int $post_id, ?string $artist_name = null): ?int {
        $title = get_the_title($post_id);
        if (!$title) return null;
        if (!$artist_name) $artist_name = self::extract_artist_from_title($title) ?? '';
        $lower_title = mb_strtolower(trim($title));
        $lower_artist = mb_strtolower(trim($artist_name));

        // 1) تطابق جفت دقیق: خواننده+عنوان (بالاترین اعتبار)
        $pairs = apply_filters('apex_omega_hit_pairs', [
            'محسن یگانه::بهت قول میدم' => 99,
            'محسن یگانه::بهت قول میدم محسن یگانه' => 99,
            'معین::' => 90, // خواننده به تنهایی اگر هیت باشد — برای سازگاری
            'ابی::' => 96,
            'اندی::' => 88,
            'گوگوش::' => 94,
            'همایون شجریان::' => 92,
        ]);
        foreach ($pairs as $pair => $score) {
            if (strpos($pair, '::') === false) continue;
            [$pa, $pt] = explode('::', $pair, 2);
            $pa = mb_strtolower(trim($pa)); $pt = mb_strtolower(trim($pt));
            $artist_match = $pa === '' || ($lower_artist && mb_stripos($lower_artist, $pa) !== false) || mb_stripos($lower_title, $pa) !== false;
            $title_match = $pt === '' || mb_stripos($lower_title, $pt) !== false;
            if ($artist_match && $title_match) {
                // اگر جفت کامل باشد، امتیاز بالا؛ اگر فقط عنوان باشد و خواننده ناشناس، امتیاز را نصف کن
                if ($pa !== '' && $pt !== '' && $artist_match && $title_match) return (int)$score;
                if ($pa !== '' && $pt === '' ) { // فقط خواننده
                    // این امتیاز عنوان نیست، باید از طریق artist_weight بیاید — اینجا نادیده
                    continue;
                }
                // عنوان تنها: اگر خواننده ناشناس است، اعتبار کم
                if ($pt !== '' && $pa === '') {
                    $artist_w = self::get_artist_only_weight($post_id);
                    if ($artist_w < 30) return (int) round($score * 0.35); // 98 → 34 برای نوپا
                    return (int)$score;
                }
            }
        }

        // 2) کلمات عمومی عنوان (مثل "عاشقانه") — بدون زمینه خواننده اعتبار ندارد
        $generic_hits = apply_filters('apex_omega_hit_titles_generic', [
            'عاشقانه' => 70,
            'گناه' => 65,
        ]);
        foreach ($generic_hits as $phrase => $score) {
            if (mb_stripos($lower_title, mb_strtolower($phrase)) !== false) {
                // فقط اگر خواننده حداقل شناخته‌شده باشد
                $aw = self::get_artist_only_weight($post_id);
                if ($aw < 35) return (int) round($score * 0.4);
                return (int)$score;
            }
        }

        // 3) AI جفتی — عنوان+خواننده با هم (دقیق‌ترین)
        if ($artist_name) {
            $pair_score = self::score_pair_via_ai($artist_name, $title);
            if ($pair_score !== null) return $pair_score;
        }
        // فالوک: AI عنوان تنها (کم‌اعتبار)
        $ai = self::score_title_via_ai($title);
        if ($ai !== null) {
            $aw = self::get_artist_only_weight($post_id);
            if ($aw < 30) return (int) round($ai * 0.35);
            return $ai;
        }
        return null;
    }

    private static function get_artist_only_weight(int $post_id): int {
        $tax = self::detect_artist_taxonomy();
        $weights = get_option(self::OPTION_WEIGHTS, []);
        $max = 0;
        if ($tax) {
            $terms = wp_get_post_terms($post_id, $tax, ['fields'=>'ids']);
            if (!empty($terms) && !is_wp_error($terms)) {
                foreach ($terms as $tid) {
                    $w = $weights[$tid] ?? 50;
                    if ($w > $max) $max = $w;
                }
            }
        }
        return $max ?: 50;
    }

    public static function score_pair_via_ai(string $artist, string $title): ?int {
        $settings = AI_Helper::get_settings();
        if (empty($settings['api_key'])) return null;
        $key = 'apex_pair_'.md5($artist.'::'.$title);
        $cached = get_transient($key);
        if ($cached !== false) return (int)$cached ? (int)$cached : null;
        $prompt = "خواننده «$artist» با آهنگ «$title» چقدر پرطرفدار است؟ 0=ناشناس، 100=ابرآهنگ ملی مثل محسن یگانه - بهت قول میدم. اگر همین عنوان را خواننده نوپا بخواند نمره پایین بده (۲۰-۳۰). فقط عدد بده:";
        $payload = [
            'model' => $settings['model'] ?: 'gpt-4o-mini',
            'messages' => [
                ['role'=>'system','content'=>'فقط عدد 0-100 برگردان.'],
                ['role'=>'user','content'=>$prompt],
            ],
            'temperature'=>0.2,
            'max_tokens'=>10,
        ];
        $resp = wp_remote_post($settings['endpoint'], [
            'headers'=>['Authorization'=>'Bearer '.$settings['api_key'],'Content-Type'=>'application/json'],
            'body'=>wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
            'timeout'=>12,
        ]);
        if (is_wp_error($resp)) return null;
        $body = wp_remote_retrieve_body($resp);
        $data = json_decode($body, true);
        $content = $data['choices'][0]['message']['content'] ?? '';
        if (preg_match('/\d+/', $content, $m)) {
            $score = max(0,min(100,(int)$m[0]));
            set_transient($key, $score, 12 * HOUR_IN_SECONDS);
            return $score;
        }
        return null;
    }

    public static function score_title_via_ai(string $title): ?int {
        $settings = AI_Helper::get_settings();
        if (empty($settings['api_key'])) return null;
        $key = 'apex_title_'.md5($title);
        $cached = get_transient($key);
        if ($cached !== false) return (int)$cached ? (int)$cached : null;
        $prompt = "این عنوان آهنگ فارسی چقدر پرطرفدار است؟ 0=ناشناس، 100=ابرآهنگ مثل 'بهت قول میدم' محسن یگانه. فقط عدد بده:\n\"$title\"";
        $payload = [
            'model' => $settings['model'] ?: 'gpt-4o-mini',
            'messages' => [
                ['role'=>'system','content'=>'فقط عدد 0-100 برگردان.'],
                ['role'=>'user','content'=>$prompt],
            ],
            'temperature'=>0.2,
            'max_tokens'=>10,
        ];
        $resp = wp_remote_post($settings['endpoint'], [
            'headers'=>['Authorization'=>'Bearer '.$settings['api_key'],'Content-Type'=>'application/json'],
            'body'=>wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
            'timeout'=>12,
        ]);
        if (is_wp_error($resp)) return null;
        $body = wp_remote_retrieve_body($resp);
        $data = json_decode($body, true);
        $content = $data['choices'][0]['message']['content'] ?? '';
        if (preg_match('/\d+/', $content, $m)) {
            $score = max(0,min(100,(int)$m[0]));
            set_transient($key, $score, 12 * HOUR_IN_SECONDS);
            return $score;
        }
        return null;
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
        // قابل توسعه با فیلتر — شامل منبع‌های جدید درخواستی
        $providers = [
            \ApexOmega\Provider\MyBia2MusicProvider::class,
            \ApexOmega\Provider\Ganja2MusicProvider::class,
            \ApexOmega\Provider\RezMusicProvider::class,
            \ApexOmega\Provider\RadioJavanProvider::class,
            \ApexOmega\Provider\Next1MusicProvider::class,
            \ApexOmega\Provider\Bia2MusicAltProvider::class,
        ];
        return apply_filters('apex_omega_ir_providers', $providers);
    }
}
