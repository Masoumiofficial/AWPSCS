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
    const META_HIT = '_apex_hit';
    const META_SELECTED = '_apex_selected';

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
                'weight' => isset($weights[$t->term_id]) ? (int)$weights[$t->term_id] : 20, // نوپا 20 // 0-100
                'tier_hint' => self::weight_to_tier_hint($weights[$t->term_id] ?? 20),
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
                    $w = $weights[$tid] ?? 20;
                    if ($w > $max) $max = $w;
                    if (is_object($tm)) $artist_names[] = $tm->name;
                }
            }
        }
        // اگر تاکسونومی نداشتیم، سعی کن خواننده را از عنوان استخراج کنی (fallback)
        if (!$max) $max = 20; // نوپا — پیش‌فرض 20 (نه 50) — اما با قاب پایین می‌ماند

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
            'artist_ratio' => 0.84, // حرفه‌ای: 84٪ وزن خواننده + 16٪ عنوان — تعادل استاندارد جهانی
            'title_ratio' => 0.16,
            'unknown_threshold' => 28, // زیر این = نوپا (استاندارد 28)
            'unknown_cap' => 42,      // سقف نوپا حتی با عنوان هیت — tier_5 (حرفه‌ای)
            'require_pair' => true,   // عنوان هیت فقط با جفت خواننده+عنوان معتبر — ضد جهش
        ];
        $saved = get_option('apex_omega_rhythm', []);
        if (!is_array($saved)) $saved = [];
        $out = wp_parse_args($saved, $defaults);
        // نرمال‌سازی
        $out['artist_ratio'] = max(0.5, min(0.95, (float)$out['artist_ratio']));
        $out['title_ratio'] = 1 - $out['artist_ratio'];
        $out['unknown_threshold'] = max(18, min(45, (int)$out['unknown_threshold']));
        $out['unknown_cap'] = max(32, min(60, (int)$out['unknown_cap']));
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
            'محسن یگانه::قول میدم' => 98,
            'محسن چاوشی::امیر بی گزند' => 92,
            'محسن چاوشی::بغض' => 90,
            'معین::عاشقانه' => 88,
            'معین::پریچه' => 86,
            'ابی::پاییز' => 89,
            'ابی::خلیج فارس' => 91,
            'اندی::لیلا' => 87,
            'گوگوش::من آمده ام' => 93,
            'هایده::سوغاتی' => 90,
            'همایون شجریان::مرغ سحر' => 92,
            'سیاوش قمیشی::فرنگیس' => 90,
            'شادمهر عقیلی::دهاتی' => 88,
            'احسان خواجه امیری::سلام آخر' => 85,
            'فرهاد مهراد::مرد تنها' => 87,
            // لیست حرفه‌ای قابل توسعه via filter apex_omega_hit_pairs — کلید "خواننده::عنوان جزئی"
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
            'محسن چاوشی::امیر بی گزند' => 92,
            'ابی::خلیج فارس' => 91,
            'سیاوش قمیشی::فرنگیس' => 90,
            'گوگوش::من آمده ام' => 93,
            'معین::عاشقانه' => 88,
            // حرفه‌ای: فقط جفت کامل — خواننده تنها امتیاز نمی‌دهد (ضد دیتای کثیف)
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
            'عاشقانه' => 68,
            'باران' => 66,
            'پاییز' => 70,
            'دلتنگی' => 67,
            'خاطره' => 65,
            'ماه' => 64,
            'گناه' => 65,
            'سفر' => 63,
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
                    $w = $weights[$tid] ?? 20;
                    if ($w > $max) $max = $w;
                }
            }
        }
        return $max ?: 20;
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
            set_transient($key, $score, 18 * HOUR_IN_SECONDS);
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
            set_transient($key, $score, 18 * HOUR_IN_SECONDS);
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
    private static function get_weights_batch_fast(array $pids): array {
        if (empty($pids)) return [];
        $tax = self::detect_artist_taxonomy();
        $weights_opt = get_option(self::OPTION_WEIGHTS, []);
        $map = array_fill_keys($pids, 20); // نوپا 20
        if (!$tax) return $map;
        global $wpdb;
        $chunks = array_chunk($pids, 800);
        $term_map = [];
        foreach ($chunks as $chunk) {
            $in = implode(',', array_map('intval', $chunk));
            $rows = $wpdb->get_results("SELECT tr.object_id, tt.term_id FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tt.taxonomy = '" . esc_sql($tax) . "' AND tr.object_id IN ($in)", ARRAY_A);
            if ($rows) foreach ($rows as $r) { $oid=(int)$r['object_id']; $tid=(int)$r['term_id']; $term_map[$oid][]=$tid; }
        }
        foreach ($map as $pid => $def) {
            $tids = $term_map[$pid] ?? [];
            $max=0; foreach($tids as $tid){ $w=$weights_opt[$tid] ?? 20; if($w>$max) $max=$w; }
            if($max) $map[$pid]=$max;
        }
        return $map;
    }
    const SELECTED_WORKS_SLUG = 'selected-works';
    const SELECTED_WORKS_TAXONOMIES = ['category','post_tag','selected-works']; // پشتیبانی دسته منتخب

    public static function is_in_selected_works(int $post_id): bool {
        if (!$post_id) return false;
        static $cache = null;
        if ($cache === null) {
            // کش 800ms برای هر درخواست — جلوگیری از N×tax query
            $ids = self::get_selected_works_ids(5000);
            $cache = array_fill_keys($ids, true);
        }
        if (isset($cache[$post_id])) return true;
        // fallback برای دسته‌های غیر category که در transient نبوده (نادر)
        if (function_exists('has_category') && has_category(self::SELECTED_WORKS_SLUG, $post_id)) { $cache[$post_id]=true; return true; }
        if (has_term(self::SELECTED_WORKS_SLUG, 'category', $post_id)) { $cache[$post_id]=true; return true; }
        $taxes = get_taxonomies(['public'=>true], 'names');
        foreach ($taxes as $tax) {
            if (has_term(self::SELECTED_WORKS_SLUG, $tax, $post_id)) { $cache[$post_id]=true; return true; }
        }
        return false;
    }

    public static function is_hit(int $post_id): bool {
        if (get_post_meta($post_id, self::META_HIT, true)) return true;
        // هر اثر در دسته منتخب به‌صورت خودکار هیت محسوب می‌شود — توجه ویژه، پرفکت، ۱۰M+
        if (self::is_in_selected_works($post_id)) return true;
        return false;
    }
    public static function set_hit(int $post_id, bool $hit): void { if ($hit) update_post_meta($post_id, self::META_HIT, 1); else delete_post_meta($post_id, self::META_HIT); }

    // آیا پست پریمیوم منتخب است؟ (برای امتیاز/کامنت ویژه)
    public static function is_premium_selected(int $post_id): bool { return self::is_in_selected_works($post_id); }

    const OPTION_WEIGHT_SYNC = 'apex_omega_weight_sync';
    public static function get_weight_sync_settings(): array {
        $defs = [
            'mode' => 'all', // all|cooldown|unsynced|lower_only
            'cooldown_hours' => 72, // 3 روز — اگر قبلا همگام شده، دوباره نزن مگر گذشته
            'batch' => 100,
            'exclude_recent_days' => 0, // 0=all — فیلتر اضافی دسته
            'protect_selected' => true, // دسته منتخب همیشه پرمیوم بماند، بازنویسی با منطق پرمیوم
            'protect_manual' => false, // اگر دستی کسی عدد زده، محافظت؟
        ];
        $saved = get_option(self::OPTION_WEIGHT_SYNC, []);
        if (!is_array($saved)) $saved=[];
        $out = wp_parse_args($saved, $defs);
        $out['mode'] = in_array($out['mode'], ['all','cooldown','unsynced','lower_only'], true) ? $out['mode'] : 'all';
        $out['cooldown_hours'] = max(1, min(720, (int)$out['cooldown_hours']));
        $out['batch'] = max(10, min(500, (int)$out['batch']));
        $out['exclude_recent_days'] = max(0, min(365, (int)$out['exclude_recent_days']));
        $out['protect_selected'] = !empty($out['protect_selected']);
        $out['protect_manual'] = !empty($out['protect_manual']);
        return $out;
    }
    public static function save_weight_sync_settings(array $data): void {
        $cur = self::get_weight_sync_settings();
        $new = [
            'mode' => isset($data['mode']) ? sanitize_text_field($data['mode']) : $cur['mode'],
            'cooldown_hours' => isset($data['cooldown_hours']) ? (int)$data['cooldown_hours'] : $cur['cooldown_hours'],
            'batch' => isset($data['batch']) ? (int)$data['batch'] : $cur['batch'],
            'exclude_recent_days' => isset($data['exclude_recent_days']) ? (int)$data['exclude_recent_days'] : $cur['exclude_recent_days'],
            'protect_selected' => isset($data['protect_selected']) ? !empty($data['protect_selected']) : $cur['protect_selected'],
            'protect_manual' => isset($data['protect_manual']) ? !empty($data['protect_manual']) : $cur['protect_manual'],
        ];
        update_option(self::OPTION_WEIGHT_SYNC, $new, false);
    }

    // لیست آی‌دی‌های دسته منتخب به صورت batch (کش 5 دقیقه) — سبک
    public static function get_selected_works_ids(int $limit = 2000): array {
        $key = 'apex_selected_ids_cache';
        $cached = get_transient($key);
        if (is_array($cached)) return $cached;
        $ids = get_posts([
            'post_type'=>'post','post_status'=>'publish','posts_per_page'=>$limit,'fields'=>'ids','no_found_rows'=>true,
            'tax_query'=>[['taxonomy'=>'category','field'=>'slug','terms'=>self::SELECTED_WORKS_SLUG]],
            'orderby'=>'date','order'=>'DESC',
        ]);
        // همچنین taxonomy های عمومی دیگر با همین slug
        try {
            $taxes = get_taxonomies(['public'=>true],'names');
            foreach ($taxes as $tax) {
                if ($tax==='category') continue;
                $more = get_posts(['post_type'=>'post','post_status'=>'publish','posts_per_page'=>$limit,'fields'=>'ids','no_found_rows'=>true,'tax_query'=>[['taxonomy'=>$tax,'field'=>'slug','terms'=>self::SELECTED_WORKS_SLUG]]]);
                if ($more) $ids = array_unique(array_merge($ids,$more));
            }
        } catch(\Throwable $e){}
        set_transient($key, $ids, 5*MINUTE_IN_SECONDS);
        return $ids;
    }

    /* ─── v2.1.8: همگام‌سازی یک‌کلیک وزن→بازه — هیچ آهنگ 95 وزنی 10k نمی‌ماند ─── */
    public static function preview_weight_sync(array $filters = []): array {
        if (function_exists('set_time_limit')) @set_time_limit(90);
        $tiers = \ApexOmega\Core\TierManager::get_tiers();
        $tiers_by_id = [];
        foreach ($tiers as $tt) $tiers_by_id[$tt['id']] = $tt;
        $best = (int) get_option('kksr_stars', 5);
        if ($best < 1) $best = 5;
        // اعمال تنظیمات همگام‌سازی از settings — اگر فیلتر خالی بود، از تنظیمات سراسری بخوان
        $ws = self::get_weight_sync_settings();
        if (empty($filters['exclude_recent_days']) && $ws['exclude_recent_days'] > 0) $filters['exclude_recent_days'] = $ws['exclude_recent_days'];
        $eligible_ids = \ApexOmega\Core\TierManager::get_eligible_post_ids($filters);
        // حالت‌های محافظ — فیلتر پس از get_eligible (سبک، بدون کوئری سنگین اضافی)
        if ($ws['mode'] !== 'all' && !empty($eligible_ids)) {
            $filtered = [];
            foreach ($eligible_ids as $pid) {
                if ($ws['mode'] === 'unsynced') {
                    $has_tier = get_post_meta($pid, \ApexOmega\Core\TierManager::META_TIER, true);
                    if ($has_tier !== '' && $has_tier !== false) continue;
                } elseif ($ws['mode'] === 'cooldown') {
                    $last = get_post_meta($pid, '_apex_tier_applied_at', true);
                    if ($last) {
                        $ts = strtotime($last);
                        if ($ts && (time() - $ts) < $ws['cooldown_hours']*3600) continue;
                    }
                } elseif ($ws['mode'] === 'lower_only') {
                    $cur = (int)get_post_meta($pid,'views',true);
                    // برای منتخب، کف واقعی tier_1 است (900k) نه وزن پایین
                    if (self::is_in_selected_works((int)$pid)) {
                        $vmin = 900000;
                    } else {
                        $w = self::get_weight_for_post($pid);
                        $hint = self::weight_to_tier_hint((int)$w);
                        $tiers_tmp = \ApexOmega\Core\TierManager::get_tiers();
                        $tmap=[]; foreach($tiers_tmp as $tt) $tmap[$tt['id']]=$tt;
                        $vmin = $tmap[$hint]['views_min'] ?? 0;
                    }
                    if ($cur >= $vmin) continue;
                }
                if ($ws['protect_manual']) {
                    $src = get_post_meta($pid,'_apex_views_last_source',true);
                    if ($src==='manual' || $src==='bulk_manual') continue;
                }
                $filtered[] = $pid;
            }
            $eligible_ids = $filtered;
        }
        $total = count($eligible_ids);
        if ($total === 0) {
            return ['success'=>false,'message'=>'هیچ آهنگی با این فیلتر یافت نشد (حالت همگام‌سازی: '.$ws['mode'].').','total'=>0];
        }
        // گروه‌بندی بر اساس وزن هر پست — FAST batch + وزن‌محور
        $grouped = [];
        foreach (array_keys($tiers_by_id) as $tid) $grouped[$tid] = [];
        $weights_batch = self::get_weights_batch_fast($eligible_ids);
        // کش دسته منتخب برای گروه‌بندی سریع — منتخب همیشه tier_1 (الماس 900k-10M) حتی اگر وزن پایین باشد
        $selected_map_fast = array_flip(self::get_selected_works_ids(5000));
        foreach ($eligible_ids as $pid) {
            // پرمیوم (selected-works) همیشه الماس — مستقل از وزن
            if (isset($selected_map_fast[$pid])) {
                $tier_hint = 'tier_1';
            } else {
                $w = $weights_batch[$pid] ?? 20;
                $tier_hint = self::weight_to_tier_hint((int)$w);
                if (!isset($grouped[$tier_hint])) $tier_hint = 'tier_7';
            }
            $grouped[$tier_hint][] = (int)$pid;
        }
        // تولید preview + summary
        $preview = [];
        $summary = [];
        $tier_counts = [];
        $grand_views = 0;
        foreach ($tiers_by_id as $tier_id => $tier) {
            $ids = $grouped[$tier_id] ?? [];
            $count = count($ids);
            $tier_counts[$tier_id] = $count;
            if ($count === 0) continue;
            $slice_preview = [];
            $sum_v = 0; $sum_c = 0; $sum_a = 0;
            foreach ($ids as $pid) {
                $w = $weights_batch[$pid] ?? 20; $is_hit = self::is_hit((int)$pid); $is_premium = self::is_in_selected_works((int)$pid);
                $vals = \ApexOmega\Core\TierManager::generate_values($tier, $best, (int)$w, $is_hit, $pid, $is_premium);
                $sum_v += $vals['views'];
                $sum_c += $vals['casts'];
                $sum_a += $vals['avg'];
                $slice_preview[] = [
                    'post_id' => $pid,
                    'views' => $vals['views'],
                    'casts' => $vals['casts'],
                    'avg' => $vals['avg'],
                    'ratings' => $vals['ratings'],
                    'weight' => $w,
                    'is_hit' => $is_hit,
                ];
            }
            $grand_views += $sum_v;
            $summary[$tier_id] = [
                'label' => $tier['label'],
                'icon' => $tier['icon'],
                'color' => $tier['color'],
                'count' => $count,
                'avg_views' => $count ? (int) round($sum_v / $count) : 0,
                'total_views' => $sum_v,
                'avg_casts' => $count ? (int) round($sum_c / $count) : 0,
                'avg_rating' => $count ? round($sum_a / $count, 2) : 0,
                'views_min' => (int)$tier['views_min'],
                'views_max' => (int)$tier['views_max'],
            ];
            $preview[$tier_id] = $slice_preview;
        }
        return [
            'success' => true,
            'total' => $total,
            'tier_counts' => $tier_counts,
            'preview' => $preview,
            'summary' => $summary,
            'tiers' => $tiers_by_id,
            'message' => sprintf('همگام‌سازی وزن‌محور: %s آهنگ در %s دسته (هیچ 95 وزنی زیر %s نمی‌ماند)', number_format_i18n($total), count(array_filter($tier_counts)), number_format_i18n($tiers_by_id['tier_1']['views_min'] ?? 900000)),
        ];
    }

    public static function apply_weight_sync(array $filters = [], int $chunk_size = 0): array {
        if (function_exists('set_time_limit')) @set_time_limit(180);
        @ini_set('max_execution_time','180');
        @ini_set('memory_limit','256M');
        $preview = self::preview_weight_sync($filters);
        if (empty($preview['success'])) return $preview;
        $best = (int) get_option('kksr_stars', 5);
        if ($best < 1) $best = 5;
        $exec = apex_omega_generate_uuid();
        $user_id = get_current_user_id();
        $total = (int)$preview['total'];
        // v2.2.0: بازسازی کامل وزن‌محور + هیت برای اعمال (preview سبک فقط 3 نمونه دارد)
        $tiers2 = \ApexOmega\Core\TierManager::get_tiers(); $tiers_by_id2=[]; foreach($tiers2 as $tt) $tiers_by_id2[$tt['id']]=$tt;
        $ws2 = self::get_weight_sync_settings();
        if (empty($filters['exclude_recent_days']) && $ws2['exclude_recent_days'] > 0) $filters['exclude_recent_days'] = $ws2['exclude_recent_days'];
        $eligible_ids2 = \ApexOmega\Core\TierManager::get_eligible_post_ids($filters);
        // اعمال حالت محافظ — همسان با preview
        if ($ws2['mode'] !== 'all' && !empty($eligible_ids2)) {
            $flt=[]; foreach($eligible_ids2 as $pid){ if($ws2['mode']==='unsynced'){ $h=get_post_meta($pid, \ApexOmega\Core\TierManager::META_TIER,true); if($h!=='' && $h!==false) continue; } elseif($ws2['mode']==='cooldown'){ $last=get_post_meta($pid,'_apex_tier_applied_at',true); if($last){ $ts=strtotime($last); if($ts && (time()-$ts) < $ws2['cooldown_hours']*3600) continue; } } elseif($ws2['mode']==='lower_only'){ $cur=(int)get_post_meta($pid,'views',true); if (self::is_in_selected_works((int)$pid)) $vmin=900000; else { $w=self::get_weight_for_post($pid); $hint=self::weight_to_tier_hint((int)$w); $tmap=[]; foreach($tiers2 as $tt) $tmap[$tt['id']]=$tt; $vmin=$tmap[$hint]['views_min']??0; } if($cur >= $vmin) continue; } if($ws2['protect_manual']){ $src=get_post_meta($pid,'_apex_views_last_source',true); if($src==='manual'||$src==='bulk_manual') continue; } $flt[]=$pid; } $eligible_ids2=$flt;
        }
        $weights_batch2 = self::get_weights_batch_fast($eligible_ids2);
        $selected_map2 = array_flip(self::get_selected_works_ids(5000));
        $full_preview = [];
        foreach ($eligible_ids2 as $pid) {
            $is_premium_pre = isset($selected_map2[$pid]);
            if ($is_premium_pre) {
                $tier_id = 'tier_1';
            } else {
                $w = $weights_batch2[$pid] ?? 20;
                $tier_id = self::weight_to_tier_hint((int)$w);
                if (!isset($tiers_by_id2[$tier_id])) $tier_id='tier_7';
            }
            $tier=$tiers_by_id2[$tier_id];
            $is_hit = self::is_hit((int)$pid); $is_premium = $is_premium_pre; $w = $weights_batch2[$pid] ?? 20;
            $vals=\ApexOmega\Core\TierManager::generate_values($tier,$best,(int)$w,$is_hit,$pid,$is_premium);
            $full_preview[$tier_id][] = ['post_id'=>$pid,'post_title'=>'ID '.$pid,'tier_id'=>$tier_id,'views'=>$vals['views'],'casts'=>$vals['casts'],'avg'=>$vals['avg'],'ratings'=>$vals['ratings'],'weight'=>$w,'is_hit'=>$is_hit,'is_premium'=>$is_premium];
        }
        $preview['preview'] = $full_preview;
        $ttl = apex_omega_lock_ttl($total, 30, 600, 2);
        $master_lock = 'apex_weight_sync_master';
        if (!\ApexOmega\Core\Database::acquire_lock($master_lock, $ttl)) {
            return ['success'=>false,'message'=>'همگام‌سازی دیگری در حال اجراست. لطفاً '.$ttl.' ثانیه صبر کنید.','ttl'=>$ttl];
        }
        // snapshot rows: جمع old→new
        $snapshot_rows = [];
        foreach ($preview['preview'] as $tier_id => $items) {
            foreach ($items as $item) {
                $pid = (int)$item['post_id'];
                $old_views = \ApexOmega\Core\Adapter_Views::get_views($pid);
                $old_kksr = \ApexOmega\Core\Adapter_KKSR::get_stats($pid);
                $snapshot_rows[] = [
                    'post_id'=>$pid,'post_title'=>$item['post_title']??(string)$pid,'tier'=>$tier_id,
                    'old_views'=>$old_views,'new_views'=>$item['views'],
                    'old_casts'=>$old_kksr['casts'],'old_avg'=>$old_kksr['avg'],'old_ratings'=>$old_kksr['ratings'],'best'=>$best,
                    'new_casts'=>$item['casts'],'new_avg'=>$item['avg'],'new_ratings'=>$item['ratings'],
                ];
            }
        }
        $applied=0; $errors=[]; $details=[]; $counter=0;
        foreach ($preview['preview'] as $tier_id => $items) {
            foreach ($items as $item) {
                if ($counter>0 && $counter%100===0){ usleep(300000); if(function_exists('set_time_limit')) @set_time_limit(180); }
                $counter++;
                $pid=(int)$item['post_id'];
                if(!get_post($pid)){ $errors[]="پست $pid یافت نشد"; continue; }
                $lock="apex_tier_{$pid}";
                if(!\ApexOmega\Core\Database::acquire_lock($lock, max(15,min(120,$ttl)))){ $errors[]="پست $pid قفل است"; continue; }
                $old_views = \ApexOmega\Core\Adapter_Views::get_views($pid);
                $old_kksr = \ApexOmega\Core\Adapter_KKSR::get_stats($pid);
                // 🛡️ محافظ کاهش — خودکار هرگز کم نکند
                $guarded = apex_omega_guard_stats(['casts'=>$old_kksr['casts'],'avg'=>$old_kksr['avg'],'ratings'=>$old_kksr['ratings'],'views'=>$old_views], ['casts'=>$item['casts'],'avg'=>$item['avg'],'ratings'=>$item['ratings'],'views'=>$item['views']], false, $pid);
                $item['casts'] = $guarded['casts']; $item['avg'] = $guarded['avg']; $item['ratings'] = $guarded['ratings']; $item['views'] = $guarded['views'];
                if ($item['views'] === $old_views && $item['casts'] === $old_kksr['casts'] && $item['avg'] === $old_kksr['avg']) { \ApexOmega\Core\Database::release_lock($lock); continue; }
                $detect = \ApexOmega\Core\Adapter_KKSR::detect();
                global $wpdb;
                $wpdb->query('START TRANSACTION');
                try {
                    update_post_meta($pid, \ApexOmega\Core\Adapter_Views::META_KEY, (int)$item['views']);
                    $rv=get_post_meta($pid,'_apex_real_views',true);
                    if($rv===''){ update_post_meta($pid,'_apex_real_views',$old_views); update_post_meta($pid,'_apex_real_views_captured_at', current_time('mysql',true)); }
                    update_post_meta($pid,'_apex_managed_views',(int)$item['views']);
                    update_post_meta($pid,'_apex_views_last_source','weight_sync');
                    update_post_meta($pid,'_apex_views_last_exec',$exec);
                    update_post_meta($pid,$detect['meta_casts'],(int)$item['casts']);
                    update_post_meta($pid,$detect['meta_ratings'],(float)$item['ratings']);
                    update_post_meta($pid,$detect['meta_avg'],(float)$item['avg']);
                    wp_cache_delete($pid,'post_meta');
                    update_post_meta($pid, \ApexOmega\Core\TierManager::META_TIER, $tier_id);
                    update_post_meta($pid,'_apex_tier_applied_at', current_time('mysql',true));
                    $wpdb->query('COMMIT'); $ok=true;
                } catch (\Throwable $e){ $wpdb->query('ROLLBACK'); error_log('[APEX Ω] weight_sync fail '.$e->getMessage()); $ok=false; }
                if($ok){
                    \ApexOmega\Core\History::log($pid,'views',$old_views,$item['views'],'weight_sync',$user_id,$exec,null,['tier'=>$tier_id,'weight_sync'=>true]);
                    \ApexOmega\Core\History::log($pid,'kksr_combined',wp_json_encode(['casts'=>$old_kksr['casts'],'avg'=>$old_kksr['avg'],'ratings'=>$old_kksr['ratings']],JSON_UNESCAPED_UNICODE),wp_json_encode(['casts'=>$item['casts'],'avg'=>$item['avg'],'ratings'=>$item['ratings']],JSON_UNESCAPED_UNICODE),'weight_sync',$user_id,$exec,null,['tier'=>$tier_id,'best'=>$best]);
                    do_action('apex_omega_views_updated',$pid,$old_views,(int)$item['views'],'weight_sync',$exec);
                    $applied++;
                    $details[]=['post_id'=>$pid,'post_title'=>$item['post_title']??(string)$pid,'edit_url'=>get_edit_post_link($pid),'tier'=>$tier_id,'old_views'=>$old_views,'new_views'=>$item['views'],'old_casts'=>$old_kksr['casts'],'new_casts'=>$item['casts'],'old_avg'=>$old_kksr['avg'],'new_avg'=>$item['avg']];
                } else { $errors[]="خطا در پست $pid"; }
                \ApexOmega\Core\Database::release_lock($lock);
            }
        }
        try { \ApexOmega\Core\Database::create_snapshot($exec,$user_id,$preview['tier_counts']??[],$filters,$total,$applied,$snapshot_rows); } catch(\Throwable $e){ error_log('[APEX Ω] weight snapshot fail '.$e->getMessage()); }
        update_option(\ApexOmega\Core\TierManager::OPTION_LAST_DISTRIBUTION,['counts'=>$preview['tier_counts']??[],'filters'=>$filters,'applied'=>$applied,'exec'=>$exec,'at'=>current_time('mysql',true),'errors'=>$errors,'weight_sync'=>true],false);
        \ApexOmega\Core\Database::release_lock($master_lock);
        return ['success'=>true,'applied'=>$applied,'errors'=>$errors,'execution_id'=>$exec,'summary'=>$preview['summary'],'details'=>$details,'snapshot_id'=>$exec,'ttl_used'=>$ttl,'total'=>$total,'tier_counts'=>$preview['tier_counts']??[]];
    }

    // ── Job chunked برای وزن (مثل tier job) ──
    public static function start_weight_sync_job(array $filters = [], int $chunk_size = 100): array {
        if (function_exists('set_time_limit')) @set_time_limit(60);
        $preview = self::preview_weight_sync($filters);
        if (empty($preview['success'])) return $preview;
        $best = (int) get_option('kksr_stars', 5); if ($best < 1) $best = 5;
        $tiers2 = \ApexOmega\Core\TierManager::get_tiers(); $tiers_by_id2=[]; foreach($tiers2 as $tt) $tiers_by_id2[$tt['id']]=$tt;
        $ws2 = self::get_weight_sync_settings();
        if (empty($filters['exclude_recent_days']) && $ws2['exclude_recent_days'] > 0) $filters['exclude_recent_days'] = $ws2['exclude_recent_days'];
        $eligible_ids2 = \ApexOmega\Core\TierManager::get_eligible_post_ids($filters);
        // اعمال حالت محافظ — همسان با preview
        if ($ws2['mode'] !== 'all' && !empty($eligible_ids2)) {
            $flt=[]; foreach($eligible_ids2 as $pid){ if($ws2['mode']==='unsynced'){ $h=get_post_meta($pid, \ApexOmega\Core\TierManager::META_TIER,true); if($h!=='' && $h!==false) continue; } elseif($ws2['mode']==='cooldown'){ $last=get_post_meta($pid,'_apex_tier_applied_at',true); if($last){ $ts=strtotime($last); if($ts && (time()-$ts) < $ws2['cooldown_hours']*3600) continue; } } elseif($ws2['mode']==='lower_only'){ $cur=(int)get_post_meta($pid,'views',true); if (self::is_in_selected_works((int)$pid)) $vmin=900000; else { $w=self::get_weight_for_post($pid); $hint=self::weight_to_tier_hint((int)$w); $tmap=[]; foreach($tiers2 as $tt) $tmap[$tt['id']]=$tt; $vmin=$tmap[$hint]['views_min']??0; } if($cur >= $vmin) continue; } if($ws2['protect_manual']){ $src=get_post_meta($pid,'_apex_views_last_source',true); if($src==='manual'||$src==='bulk_manual') continue; } $flt[]=$pid; } $eligible_ids2=$flt;
        }
        $weights_batch2 = self::get_weights_batch_fast($eligible_ids2);
        $selected_map3 = array_flip(self::get_selected_works_ids(5000));
        $flat=[];
        foreach ($eligible_ids2 as $pid) {
            $is_premium_pre = isset($selected_map3[$pid]);
            if ($is_premium_pre) {
                $tier_id = 'tier_1';
            } else {
                $w = $weights_batch2[$pid] ?? 20;
                $tier_id = self::weight_to_tier_hint((int)$w);
                if (!isset($tiers_by_id2[$tier_id])) $tier_id='tier_7';
            }
            $tier=$tiers_by_id2[$tier_id];
            $is_hit = self::is_hit((int)$pid); $is_premium = $is_premium_pre; $w = $weights_batch2[$pid] ?? 20;
            $vals=\ApexOmega\Core\TierManager::generate_values($tier,$best,(int)$w,$is_hit,$pid,$is_premium);
            $flat[]=['post_id'=>$pid,'post_title'=>'ID '.$pid,'tier_id'=>$tier_id,'views'=>$vals['views'],'casts'=>$vals['casts'],'avg'=>$vals['avg'],'ratings'=>$vals['ratings'],'weight'=>$w,'is_hit'=>$is_hit,'is_premium'=>$is_premium];
        }
        $exec = apex_omega_generate_uuid();
        $user_id = get_current_user_id();
        $total = count($flat);
        \ApexOmega\Core\Database::create_job($exec,$user_id,$preview['tier_counts']??[],$filters,$flat,$total,$chunk_size);
        if (class_exists('\\ApexOmega\\Core\\QueueWorker')) { try { \ApexOmega\Core\QueueWorker::enqueue('weight_sync',['execution_id'=>$exec,'total'=>$total],10);} catch(\Throwable $e){} }
        return ['success'=>true,'execution_id'=>$exec,'total'=>$total,'chunk_size'=>$chunk_size,'message'=>'کار وزن‌محور ایجاد شد — '.$total.' آهنگ در صف','preview_summary'=>$preview['summary'],'tier_counts'=>$preview['tier_counts']??[]];
    }

    public static function process_weight_chunk(string $execution_id, int $chunk_size = 0): array {
        $job = \ApexOmega\Core\Database::get_job($execution_id);
        if (!$job) return ['success'=>false,'message'=>'کار یافت نشد'];
        if (in_array($job['status'],['done','failed'],true) && (int)$job['processed'] >= (int)$job['total']) return ['success'=>true,'status'=>'done','processed'=>(int)$job['processed'],'total'=>(int)$job['total'],'message'=>'کار قبلا کامل شده'];
        $chunk_size = $chunk_size>0 ? $chunk_size : (int)$job['chunk_size'];
        $chunk_size = max(10,min(200,$chunk_size));
        $payload = json_decode($job['payload_json'], true);
        if (!is_array($payload)) return ['success'=>false,'message'=>'payload نامعتبر'];
        $total=(int)$job['total']; $processed=(int)$job['processed'];
        if ($processed >= $total){ \ApexOmega\Core\Database::update_job_progress($execution_id,$total,'done'); return ['success'=>true,'status'=>'done','processed'=>$total,'total'=>$total]; }
        $lock_key='apex_job_'.$execution_id;
        if (!\ApexOmega\Core\Database::acquire_lock($lock_key,120)) return ['success'=>false,'message'=>'کار دیگری در حال پردازش است','retry_after'=>5];
        $job=\ApexOmega\Core\Database::get_job($execution_id); $processed=(int)$job['processed'];
        $slice=array_slice($payload,$processed,$chunk_size);
        if(empty($slice)){ \ApexOmega\Core\Database::release_lock($lock_key); \ApexOmega\Core\Database::update_job_progress($execution_id,$total,'done'); return ['success'=>true,'status'=>'done','processed'=>$total,'total'=>$total]; }
        $best=(int)get_option('kksr_stars',5); if($best<1) $best=5;
        $applied=0; $errors=[]; $snapshot_rows=[];
        global $wpdb;
        foreach($slice as $item){
            $pid=(int)$item['post_id']; $tier_id=$item['tier_id'];
            if(!get_post($pid)){ $errors[]="پست $pid یافت نشد"; continue; }
            $plock="apex_tier_{$pid}";
            if(!\ApexOmega\Core\Database::acquire_lock($plock,20)){ $errors[]="پست $pid قفل است"; continue; }
            $old_views=\ApexOmega\Core\Adapter_Views::get_views($pid);
            $old_kksr=\ApexOmega\Core\Adapter_KKSR::get_stats($pid);
            // 🛡️ محافظ کاهش
            $g2 = apex_omega_guard_stats(['casts'=>$old_kksr['casts'],'avg'=>$old_kksr['avg'],'ratings'=>$old_kksr['ratings'],'views'=>$old_views], ['casts'=>$item['casts'],'avg'=>$item['avg'],'ratings'=>$item['ratings'],'views'=>$item['views']], false, $pid);
            $item['casts']=$g2['casts']; $item['avg']=$g2['avg']; $item['ratings']=$g2['ratings']; $item['views']=$g2['views'];
            if ($item['views']===$old_views && $item['casts']===$old_kksr['casts'] && $item['avg']===$old_kksr['avg']) { \ApexOmega\Core\Database::release_lock($plock); continue; }
            $detect=\ApexOmega\Core\Adapter_KKSR::detect();
            $wpdb->query('START TRANSACTION');
            try {
                update_post_meta($pid,\ApexOmega\Core\Adapter_Views::META_KEY,(int)$item['views']);
                $rv=get_post_meta($pid,'_apex_real_views',true); if($rv===''){ update_post_meta($pid,'_apex_real_views',$old_views); update_post_meta($pid,'_apex_real_views_captured_at',current_time('mysql',true));}
                update_post_meta($pid,'_apex_managed_views',(int)$item['views']);
                update_post_meta($pid,'_apex_views_last_source','weight_sync_chunked');
                update_post_meta($pid,'_apex_views_last_exec',$execution_id);
                update_post_meta($pid,$detect['meta_casts'],(int)$item['casts']);
                update_post_meta($pid,$detect['meta_ratings'],(float)$item['ratings']);
                update_post_meta($pid,$detect['meta_avg'],(float)$item['avg']);
                wp_cache_delete($pid,'post_meta');
                update_post_meta($pid,\ApexOmega\Core\TierManager::META_TIER,$tier_id);
                update_post_meta($pid,'_apex_tier_applied_at',current_time('mysql',true));
                $wpdb->query('COMMIT');
                \ApexOmega\Core\History::log($pid,'views',$old_views,$item['views'],'weight_sync',$user_id??get_current_user_id(),$execution_id,null,['tier'=>$tier_id,'chunked'=>true]);
                \ApexOmega\Core\History::log($pid,'kksr_combined',wp_json_encode(['casts'=>$old_kksr['casts'],'avg'=>$old_kksr['avg'],'ratings'=>$old_kksr['ratings']],JSON_UNESCAPED_UNICODE),wp_json_encode(['casts'=>$item['casts'],'avg'=>$item['avg'],'ratings'=>$item['ratings']],JSON_UNESCAPED_UNICODE),'weight_sync',get_current_user_id(),$execution_id,null,['tier'=>$tier_id,'best'=>$best,'chunked'=>true]);
                do_action('apex_omega_views_updated',$pid,$old_views,(int)$item['views'],'weight_sync_chunked',$execution_id);
                $snapshot_rows[]=['post_id'=>$pid,'post_title'=>$item['post_title']??(string)$pid,'tier'=>$tier_id,'old_views'=>$old_views,'new_views'=>$item['views'],'old_casts'=>$old_kksr['casts'],'old_avg'=>$old_kksr['avg'],'old_ratings'=>$old_kksr['ratings'],'best'=>$best,'new_casts'=>$item['casts'],'new_avg'=>$item['avg'],'new_ratings'=>$item['ratings']];
                $applied++;
            } catch(\Throwable $e){ $wpdb->query('ROLLBACK'); $errors[]="پست $pid: ".$e->getMessage(); }
            \ApexOmega\Core\Database::release_lock($plock);
        }
        $new_processed=$processed+$applied;
        $opt_key='apex_job_snapshot_'.$execution_id;
        $acc=get_option($opt_key,[]); if(!is_array($acc)) $acc=[]; $acc=array_merge($acc,$snapshot_rows); update_option($opt_key,$acc,false);
        if($applied===0 && !empty($errors) && count($slice)===count($errors)){ \ApexOmega\Core\Database::increment_job_retry($execution_id); \ApexOmega\Core\Database::release_lock($lock_key); return ['success'=>false,'message'=>'خطا chunk: '.implode(' | ',array_slice($errors,0,3)),'errors'=>$errors,'processed'=>$processed,'total'=>$total,'retry'=>true]; }
        $is_done=($new_processed >= $total);
        \ApexOmega\Core\Database::update_job_progress($execution_id,$new_processed,$is_done?'done':'running');
        \ApexOmega\Core\Database::release_lock($lock_key);
        if($is_done){
            try { $counts=json_decode($job['counts_json'],true)?:[]; $filters=json_decode($job['filters_json'],true)?:[]; \ApexOmega\Core\Database::create_snapshot($execution_id,(int)$job['user_id'],$counts,$filters,$total,$new_processed,$acc); delete_option($opt_key); update_option(\ApexOmega\Core\TierManager::OPTION_LAST_DISTRIBUTION,['counts'=>$counts,'filters'=>$filters,'applied'=>$new_processed,'exec'=>$execution_id,'at'=>current_time('mysql',true),'errors'=>$errors,'chunked'=>true,'weight_sync'=>true],false); } catch(\Throwable $e){ error_log('[APEX Ω] weight chunk snapshot fail '.$e->getMessage()); }
        }
        return ['success'=>true,'status'=>$is_done?'done':'running','processed'=>$new_processed,'total'=>$total,'applied_in_chunk'=>$applied,'errors'=>$errors,'remaining'=>$total-$new_processed,'percent'=>$total?round($new_processed/$total*100,1):0];
    }


    // پرمیوم‌سازی دسته آثار منتخب — دکمه‌ای برای اعمال 9.5-11.8M به همه اعضای دسته
    public static function apply_premium_to_selected_works(int $chunk_size = 0, bool $with_comments = false): array {
        if (function_exists('set_time_limit')) @set_time_limit(180);
        $ids = self::get_selected_works_ids(5000);
        if (empty($ids)) return ['success'=>false,'message'=>'هیچ پستی در دسته selected-works یافت نشد. ابتدا چند نوشته را به این دسته اضافه کنید.'];
        // فیلتر: فقط آنهایی که واقعا زیر 9M هستند یا هنوز پرمیوم اعمال نشده؟ ولی با guard همه قابل اعمال است
        $tiers = \ApexOmega\Core\TierManager::get_tiers();
        $tiers_by_id = []; foreach($tiers as $t) $tiers_by_id[$t['id']]=$t;
        $best = (int) get_option('kksr_stars', 5); if($best<1) $best=5;
        $exec = apex_omega_generate_uuid();
        $user_id = get_current_user_id();
        $total = count($ids);
        $ttl = apex_omega_lock_ttl($total, 30, 600, 2);
        if (!\ApexOmega\Core\Database::acquire_lock('apex_selected_premium_master', $ttl)) {
            return ['success'=>false,'message'=>'عملیات دیگری در حال اجراست. '.$ttl.' ثانیه صبر کنید.'];
        }
        $weights = self::get_weights_batch_fast($ids);
        $snapshot_rows=[]; $applied=0; $errors=[]; $details=[];
        foreach ($ids as $pid) {
            $w = $weights[$pid] ?? max(20, self::get_weight_for_post($pid));
            // FIX v2.2.9: منتخب همیشه الماس tier_1 (900k-10M → 9.5-11.8M) مستقل از وزن — وزن فقط برای تنوع جزئی داخل tier_1
            $tier_id = 'tier_1';
            $tier = $tiers_by_id[$tier_id];
            $is_hit = true; // پرمیوم = هیت
            $is_premium = true;
            $vals = \ApexOmega\Core\TierManager::generate_values($tier, $best, (int)$w, $is_hit, $pid, $is_premium);
            $old_views = \ApexOmega\Core\Adapter_Views::get_views($pid);
            $old_kksr = \ApexOmega\Core\Adapter_KKSR::get_stats($pid);
            // guard: فقط افزایش
            $guarded = apex_omega_guard_stats(['casts'=>$old_kksr['casts'],'avg'=>$old_kksr['avg'],'ratings'=>$old_kksr['ratings'],'views'=>$old_views], ['casts'=>$vals['casts'],'avg'=>$vals['avg'],'ratings'=>$vals['ratings'],'views'=>$vals['views']], false, $pid);
            $vals['casts']=$guarded['casts']; $vals['avg']=$guarded['avg']; $vals['ratings']=$guarded['ratings']; $vals['views']=$guarded['views'];
            if ($vals['views']===$old_views && $vals['casts']===$old_kksr['casts'] && $vals['avg']===$old_kksr['avg']) {
                continue; // قبلا پرمیوم است
            }
            $detect = \ApexOmega\Core\Adapter_KKSR::detect();
            global $wpdb;
            $lock="apex_tier_{$pid}";
            if (!\ApexOmega\Core\Database::acquire_lock($lock, 30)) { $errors[]="پست $pid قفل"; continue; }
            $wpdb->query('START TRANSACTION');
            try {
                update_post_meta($pid, \ApexOmega\Core\Adapter_Views::META_KEY, (int)$vals['views']);
                $rv=get_post_meta($pid,'_apex_real_views',true); if($rv===''){ update_post_meta($pid,'_apex_real_views',$old_views); update_post_meta($pid,'_apex_real_views_captured_at', current_time('mysql',true));}
                update_post_meta($pid,'_apex_managed_views',(int)$vals['views']);
                update_post_meta($pid,'_apex_views_last_source','selected_premium');
                update_post_meta($pid,'_apex_views_last_exec',$exec);
                update_post_meta($pid,$detect['meta_casts'],(int)$vals['casts']);
                update_post_meta($pid,$detect['meta_ratings'],(float)$vals['ratings']);
                update_post_meta($pid,$detect['meta_avg'],(float)$vals['avg']);
                update_post_meta($pid, \ApexOmega\Core\TierManager::META_TIER, $tier_id);
                update_post_meta($pid,'_apex_tier_applied_at', current_time('mysql',true));
                wp_cache_delete($pid,'post_meta');
                $snapshot_rows[]=['post_id'=>$pid,'post_title'=>get_the_title($pid)?:(string)$pid,'tier'=>$tier_id,'old_views'=>$old_views,'new_views'=>$vals['views'],'old_casts'=>$old_kksr['casts'],'old_avg'=>$old_kksr['avg'],'old_ratings'=>$old_kksr['ratings'],'best'=>$best,'new_casts'=>$vals['casts'],'new_avg'=>$vals['avg'],'new_ratings'=>$vals['ratings']];
                $wpdb->query('COMMIT');
                \ApexOmega\Core\History::log($pid,'views',$old_views,$vals['views'],'selected_premium',$user_id,$exec,null,['tier'=>$tier_id,'premium'=>true]);
                \ApexOmega\Core\History::log($pid,'kksr_combined',wp_json_encode(['casts'=>$old_kksr['casts'],'avg'=>$old_kksr['avg'],'ratings'=>$old_kksr['ratings']],JSON_UNESCAPED_UNICODE),wp_json_encode(['casts'=>$vals['casts'],'avg'=>$vals['avg'],'ratings'=>$vals['ratings']],JSON_UNESCAPED_UNICODE),'selected_premium',$user_id,$exec,null,['tier'=>$tier_id]);
                do_action('apex_omega_views_updated',$pid,$old_views,(int)$vals['views'],'selected_premium',$exec);
                $applied++; $details[]=['post_id'=>$pid,'old_views'=>$old_views,'new_views'=>$vals['views'],'tier'=>$tier_id];
            } catch(\Throwable $e){ $wpdb->query('ROLLBACK'); $errors[]="پست $pid: ".$e->getMessage(); }
            \ApexOmega\Core\Database::release_lock($lock);
            // کامنت پرمیوم (اختیاری)
            if ($with_comments && $applied % 1 === 0) {
                try {
                    // اگر نگهبان دیدگاه فعال باشد، یک دیدگاه پرمیوم اضافه کن
                    if (\ApexOmega\Core\CommentGuardian::is_enabled()) {
                        // یک دیدگاه مصنوعی سبک
                        // استفاده از متد داخلی generate_text + wp_insert_comment
                        $title = get_the_title($pid);
                        $text = \ApexOmega\Core\CommentGuardian::generate_text($pid, '', $title, (int)$w, (int)$vals['views'], true);
                        if ($text && !\ApexOmega\Core\CommentGuardian::has_duplicate($pid, $text)) {
                            wp_insert_comment(['comment_post_ID'=>$pid,'comment_content'=>$text,'comment_approved'=>1,'comment_author'=>'کاربر تاییدشده','comment_author_email'=>'verified@ahangban.local','comment_author_IP'=>'127.0.0.1','comment_agent'=>'APEX-Selected-Premium']);
                        }
                    }
                } catch(\Throwable $e){}
            }
        }
        if ($snapshot_rows) {
            try { \ApexOmega\Core\Database::create_snapshot($exec,$user_id,[],['source'=>'selected_premium'],$total,$applied,$snapshot_rows); } catch(\Throwable $e){}
            update_option(\ApexOmega\Core\TierManager::OPTION_LAST_DISTRIBUTION, ['counts'=>[],'filters'=>['source'=>'selected_premium'],'applied'=>$applied,'exec'=>$exec,'at'=>current_time('mysql',true),'errors'=>$errors,'weight_sync'=>false,'premium_selected'=>true], false);
        }
        \ApexOmega\Core\Database::release_lock('apex_selected_premium_master');
        return ['success'=>true,'applied'=>$applied,'total'=>$total,'execution_id'=>$exec,'errors'=>$errors,'details'=>$details,'message'=> $applied ? "✅ {$applied} از {$total} آهنگ دسته منتخب پرمیوم شد (9.5-11.8M، امتیاز 4.9-5.0)" : 'همه قبلا پرمیوم بودند یا محافظ جلوی کاهش را گرفت'];
    }

    public static function allow_manual_decrease(int $post_id, callable $fn) {
        // helper برای ویرایش دستی: به guard بگو کاهش مجاز است (از bulk/metabox صدا بزن)
        // فعلا فقط flag موقت ست می‌کند
        $GLOBALS['apex_allow_decrease_'. $post_id] = true;
        try { return $fn(); } finally { unset($GLOBALS['apex_allow_decrease_'. $post_id]); }
    }

    // تنوع‌دهی مجدد برای هنرمندی با آثار یکنواخت (مثل علیرضا قربانی 600k یکسان) — فورس بدون cooldown/lower_only
    public static function reseed_artist_variance(int $term_id, string $taxonomy = ''): array {
        if (!$term_id) return ['success'=>false,'message'=>'شناسه هنرمند نامعتبر'];
        if (!$taxonomy) $taxonomy = self::detect_artist_taxonomy();
        if (!$taxonomy) return ['success'=>false,'message'=>'تاکسونومی هنرمند یافت نشد'];
        $term = get_term($term_id, $taxonomy);
        if (!$term || is_wp_error($term)) return ['success'=>false,'message'=>'هنرمند یافت نشد'];
        // تمام آهنگ‌های این هنرمند
        $q = new \WP_Query(['post_type'=>'post','post_status'=>'publish','posts_per_page'=>2000,'fields'=>'ids','no_found_rows'=>true,'tax_query'=>[['taxonomy'=>$taxonomy,'field'=>'term_id','terms'=>$term_id]]]);
        $ids = $q->posts ?: [];
        if (empty($ids)) return ['success'=>false,'message'=>'این هنرمند هیچ آهنگی ندارد (ترم خالی)'];
        $weight = (int)(get_option(self::OPTION_WEIGHTS, [])[$term_id] ?? 20);
        // اگر وزن پیش‌فرض 20 است، سعی کن وزن هوشمند حدس بزنی (برای تنوع بهتر)
        if ($weight === 20) {
            // حدس بر اساس نام: اگر تعداد آهنگ زیاد است، احتمالا مشهور — وزن متوسط بده
            $weight = max(55, min(85, 50 + (int)($term->count / 5)));
        }
        $tier_id = self::weight_to_tier_hint($weight);
        $tiers = \ApexOmega\Core\TierManager::get_tiers();
        $tmap=[]; foreach($tiers as $t) $tmap[$t['id']]=$t;
        $tier = $tmap[$tier_id] ?? $tmap['tier_4'];
        $best = (int) get_option('kksr_stars', 5); if($best<1) $best=5;
        $exec = apex_omega_generate_uuid();
        $user_id = get_current_user_id();
        $total = count($ids);
        $applied=0; $errors=[]; $details=[]; $snapshot_rows=[];
        $before_views = [];
        foreach($ids as $pid) $before_views[$pid]=(int)get_post_meta($pid,'views',true);
        // برای شکستن یکنواختی، حتی اگر تنوع فعلی وجود دارد، یک offset ترتیبی اضافه کن
        // هر پست با هش ID تنوع دارد، اما اگر همه 600k بودند، guard قبلی آنها را با +1-3% متنوع می‌کند — اینجا عمدا بیشتر تنوع می‌دهیم
        foreach($ids as $idx=>$pid) {
            $is_hit = self::is_hit((int)$pid); $is_premium = self::is_in_selected_works((int)$pid);
            // اگر منتخب است، tier را الماس کن — حتی اگر وزن پایین باشد
            $cur_tier = $tier; if ($is_premium) $cur_tier = $tmap['tier_1'] ?? $tier;
            $vals = \ApexOmega\Core\TierManager::generate_values($cur_tier, $best, (int)$weight, $is_hit, $pid, $is_premium);
            $old_views = $before_views[$pid];
            $old_kksr = \ApexOmega\Core\Adapter_KKSR::get_stats($pid);
            // شکستن یکنواختی: اگر oldها همه یکسان هستند، یک jitter ترتیبی اضافه کن تا دیگر مساوی نمانند
            // برای اولین اجرا، val ممکن است نزدیک old باشد — یک پراکندگی اجباری ترتیبی
            $pidHash = (($pid * 9301 + 49297) % 233280)/233280; // 0-1
            // اگر old تکراری است (اکثر پست‌ها هم‌مقدار)، یک variance بیشتر
            $spread = (int) round($cur_tier['views_max'] - $cur_tier['views_min']);
            $forcedVar = (int) round(($pidHash - 0.5) * $spread * 0.18); // ±9% بازه — برای tier_2 => ±36k
            $vals['views'] = max($vals['views'], $vals['views'] + $forcedVar);
            // محافظ هرگز کم نکن اما با تنوع
            $guarded = apex_omega_guard_stats(['casts'=>$old_kksr['casts'],'avg'=>$old_kksr['avg'],'ratings'=>$old_kksr['ratings'],'views'=>$old_views], ['casts'=>$vals['casts'],'avg'=>$vals['avg'],'ratings'=>$vals['ratings'],'views'=>$vals['views']], false, $pid);
            $vals['casts']=$guarded['casts']; $vals['avg']=$guarded['avg']; $vals['ratings']=$guarded['ratings']; $vals['views']=$guarded['views'];
            // اگر هنوز مساوی old ماند (چون guard + forcedVar کم بود)، یک افزایش حداقل اجباری
            if ($vals['views'] === $old_views) {
                $minInc = (int) round($old_views * (0.012 + $pidHash * 0.025)); // 1.2–3.7%
                $vals['views'] = $old_views + max(2500, $minInc);
                // رای و امتیاز هم حداقل کمی بیشتر
                if ($vals['casts'] <= $old_kksr['casts']) $vals['casts'] = $old_kksr['casts'] + (int)round($pidHash*8)+1;
                if ($vals['avg'] <= $old_kksr['avg']) $vals['avg'] = min((float)$cur_tier['avg_max'], round($old_kksr['avg']+0.1,1));
                $vals['ratings'] = round($vals['avg'] * $vals['casts'] / $best * 5, 2);
            }
            if ($vals['views'] < $old_views) continue; // هرگز کم نکن — اضافی
            $detect = \ApexOmega\Core\Adapter_KKSR::detect();
            global $wpdb;
            $lock="apex_tier_{$pid}"; if(!\ApexOmega\Core\Database::acquire_lock($lock, 20)) { $errors[]="پست $pid قفل"; continue; }
            $wpdb->query('START TRANSACTION');
            try {
                update_post_meta($pid, \ApexOmega\Core\Adapter_Views::META_KEY, (int)$vals['views']);
                $rv=get_post_meta($pid,'_apex_real_views',true); if($rv===''){ update_post_meta($pid,'_apex_real_views',$old_views); update_post_meta($pid,'_apex_real_views_captured_at', current_time('mysql',true));}
                update_post_meta($pid,'_apex_managed_views',(int)$vals['views']);
                update_post_meta($pid,'_apex_views_last_source','reseed_variance');
                update_post_meta($pid,'_apex_views_last_exec',$exec);
                update_post_meta($pid,$detect['meta_casts'],(int)$vals['casts']);
                update_post_meta($pid,$detect['meta_ratings'],(float)$vals['ratings']);
                update_post_meta($pid,$detect['meta_avg'],(float)$vals['avg']);
                update_post_meta($pid, \ApexOmega\Core\TierManager::META_TIER, $tier_id);
                update_post_meta($pid,'_apex_tier_applied_at', current_time('mysql',true));
                wp_cache_delete($pid,'post_meta');
                $snapshot_rows[]=['post_id'=>$pid,'post_title'=>get_the_title($pid)?:(string)$pid,'tier'=>$tier_id,'old_views'=>$old_views,'new_views'=>$vals['views'],'old_casts'=>$old_kksr['casts'],'old_avg'=>$old_kksr['avg'],'old_ratings'=>$old_kksr['ratings'],'best'=>$best,'new_casts'=>$vals['casts'],'new_avg'=>$vals['avg'],'new_ratings'=>$vals['ratings']];
                $wpdb->query('COMMIT');
                \ApexOmega\Core\History::log($pid,'views',$old_views,$vals['views'],'reseed_variance',$user_id,$exec,null,['tier'=>$tier_id,'weight'=>$weight,'term_id'=>$term_id]);
                $applied++; $details[]=['post_id'=>$pid,'old'=>$old_views,'new'=>$vals['views']];
            } catch(\Throwable $e){ $wpdb->query('ROLLBACK'); $errors[]="پست $pid: ".$e->getMessage(); }
            \ApexOmega\Core\Database::release_lock($lock);
        }
        if ($snapshot_rows) {
            try { \ApexOmega\Core\Database::create_snapshot($exec,$user_id,[],['artist_term'=>$term_id,'reseed'=>true],$total,$applied,$snapshot_rows); } catch(\Throwable $e){}
            update_option(\ApexOmega\Core\TierManager::OPTION_LAST_DISTRIBUTION, ['counts'=>[],'filters'=>['artist_term'=>$term_id],'applied'=>$applied,'exec'=>$exec,'at'=>current_time('mysql',true),'errors'=>$errors,'reseed'=>true], false);
        }
        return ['success'=>true,'applied'=>$applied,'total'=>$total,'execution_id'=>$exec,'errors'=>$errors,'details'=>$details,'message'=> $applied ? "✅ تنوع‌دهی انجام شد: {$applied} آهنگ «{$term->name}» از {$total} — از 600k یکسان به بازه متنوع (هرگز کم نشد)" : 'هیچ تغییری اعمال نشد (همه قفل یا قبلا متنوع)'];
    }


    // ── v2.2.0: مدیریت هیت‌ها (معروف‌ترین آثار) ──
    public static function get_hit_posts(int $limit = 100): array {
        global $wpdb;
        $ids = $wpdb->get_col($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key=%s LIMIT %d", self::META_HIT, $limit));
        if (!$ids) return [];
        $out=[]; foreach($ids as $pid){ $p=get_post($pid); if($p) $out[]=['post_id'=>(int)$pid,'post_title'=>$p->post_title,'post_url'=>get_permalink($pid)]; }
        return $out;
    }
    public static function get_all_hit_ids(): array {
        global $wpdb;
        return array_map('intval', $wpdb->get_col($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key=%s", self::META_HIT)) ?: []);
    }
    public static function clear_hits(): void { global $wpdb; $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key='_apex_hit'"); }
    public static function save_hits(array $post_ids, bool $clear_first = false): array {
        if ($clear_first) self::clear_hits();
        $saved=0; $errors=[];
        foreach($post_ids as $pid){
            $pid=(int)$pid; if(!$pid || !get_post($pid)){ $errors[]="پست $pid یافت نشد"; continue; }
            update_post_meta($pid, self::META_HIT, 1);
            update_post_meta($pid, self::META_SELECTED, 1);
            $saved++;
        }
        return ['success'=>true,'saved'=>$saved,'errors'=>$errors];
    }
    public static function remove_hit(int $post_id): array {
        delete_post_meta($post_id, self::META_HIT);
        return ['success'=>true];
    }
    public static function toggle_hit(int $post_id): array {
        $is = self::is_hit($post_id);
        if ($is) { delete_post_meta($post_id, self::META_HIT); return ['success'=>true,'is_hit'=>false]; }
        else { update_post_meta($post_id, self::META_HIT, 1); return ['success'=>true,'is_hit'=>true]; }
    }
    public static function suggest_hits_via_ai(?int $term_id = null, int $limit = 20): array {
        $tax = self::detect_artist_taxonomy();
        if (!$tax) return ['success'=>false,'message'=>'تاکسونومی یافت نشد'];
        $settings = \ApexOmega\Core\AI_Helper::get_settings();
        if (empty($settings['api_key'])) return ['success'=>false,'message'=>'کلید AI تنظیم نشده — به تنظیمات برو'];
        // جمع‌آوری پست‌های هنرمند یا همه
        if ($term_id) {
            $q = new \WP_Query(['post_type'=>'post','post_status'=>'publish','posts_per_page'=>$limit,'tax_query'=>[['taxonomy'=>$tax,'field'=>'term_id','terms'=>$term_id]],'fields'=>'ids']);
            $ids = $q->posts ?: [];
            $term = get_term($term_id, $tax);
            $artist_name = $term ? $term->name : 'نامشخص';
        } else {
            // برای کل سایت: 30 پست پر بازدید اخیر
            global $wpdb;
            $ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type='post' AND post_status='publish' ORDER BY post_date DESC LIMIT 30");
            $artist_name = 'مختلف';
        }
        if (!$ids) return ['success'=>false,'message'=>'پستی یافت نشد'];
        $titles=[]; foreach($ids as $pid){ $t=get_the_title($pid); $titles[]="$pid: $t"; }
        $list = implode("\n", $titles);
        $prompt = "تو متخصص موسیقی ایرانی هستی. برای هنرمند «{$artist_name}» این آهنگ‌ها را داریم:\n{$list}\nکدام 5 تا معروف‌ترین و هیت‌ترین هستند که باید 10M بازدید بگیرد؟ فقط JSON آرایه‌ای از IDها برگردان مثل [123,456] بدون توضیح.";
        $payload = [
            'model' => $settings['model'] ?: 'gpt-4o-mini',
            'messages' => [['role'=>'system','content'=>'فقط JSON آرایه برگردان'],['role'=>'user','content'=>$prompt]],
            'temperature'=>0.4,'max_tokens'=>500,
        ];
        $resp = wp_remote_post($settings['endpoint'], ['headers'=>['Authorization'=>'Bearer '.$settings['api_key'],'Content-Type'=>'application/json'],'body'=>wp_json_encode($payload, JSON_UNESCAPED_UNICODE),'timeout'=>25]);
        if (is_wp_error($resp)) return ['success'=>false,'message'=>$resp->get_error_message()];
        $body=wp_remote_retrieve_body($resp); $data=json_decode($body,true);
        $content=$data['choices'][0]['message']['content'] ?? '';
        if (preg_match('/\[.*\]/s',$content,$m)) $arr=json_decode($m[0],true); else $arr=json_decode(trim($content),true);
        if (!is_array($arr)) return ['success'=>false,'message'=>'پاسخ AI نامعتبر','raw'=>$content];
        $clean=[]; foreach($arr as $pid){ $pid=(int)$pid; if(in_array($pid,$ids)) $clean[]=$pid; }
        return ['success'=>true,'suggested'=>$clean,'raw'=>$content,'artist'=>$artist_name];
    }

}
