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
        $map = array_fill_keys($pids, 50);
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
            $max=0; foreach($tids as $tid){ $w=$weights_opt[$tid] ?? 50; if($w>$max) $max=$w; }
            if($max) $map[$pid]=$max;
        }
        return $map;
    }
    public static function is_hit(int $post_id): bool { return (bool) get_post_meta($post_id, self::META_HIT, true); }
    public static function set_hit(int $post_id, bool $hit): void { if ($hit) update_post_meta($post_id, self::META_HIT, 1); else delete_post_meta($post_id, self::META_HIT); }


    /* ─── v2.1.8: همگام‌سازی یک‌کلیک وزن→بازه — هیچ آهنگ 95 وزنی 10k نمی‌ماند ─── */
    public static function preview_weight_sync(array $filters = []): array {
        if (function_exists('set_time_limit')) @set_time_limit(90);
        $tiers = \ApexOmega\Core\TierManager::get_tiers();
        $tiers_by_id = [];
        foreach ($tiers as $tt) $tiers_by_id[$tt['id']] = $tt;
        $best = (int) get_option('kksr_stars', 5);
        if ($best < 1) $best = 5;
        $eligible_ids = \ApexOmega\Core\TierManager::get_eligible_post_ids($filters);
        $total = count($eligible_ids);
        if ($total === 0) {
            return ['success'=>false,'message'=>'هیچ آهنگی با این فیلتر یافت نشد.','total'=>0];
        }
        // گروه‌بندی بر اساس وزن هر پست — FAST batch + وزن‌محور
        $grouped = [];
        foreach (array_keys($tiers_by_id) as $tid) $grouped[$tid] = [];
        $weights_batch = self::get_weights_batch_fast($eligible_ids);
        foreach ($eligible_ids as $pid) {
            $w = $weights_batch[$pid] ?? 50;
            $tier_hint = self::weight_to_tier_hint((int)$w);
            if (!isset($grouped[$tier_hint])) $tier_hint = 'tier_7';
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
                $w = $weights_batch[$pid] ?? 50; $is_hit = self::is_hit((int)$pid);
                $vals = \ApexOmega\Core\TierManager::generate_values($tier, $best, (int)$w, $is_hit);
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
        $eligible_ids2 = \ApexOmega\Core\TierManager::get_eligible_post_ids($filters);
        $weights_batch2 = self::get_weights_batch_fast($eligible_ids2);
        $full_preview = [];
        foreach ($eligible_ids2 as $pid) {
            $w = $weights_batch2[$pid] ?? 50;
            $tier_id = self::weight_to_tier_hint((int)$w);
            if (!isset($tiers_by_id2[$tier_id])) $tier_id='tier_7';
            $tier=$tiers_by_id2[$tier_id];
            $is_hit = self::is_hit((int)$pid);
            $vals=\ApexOmega\Core\TierManager::generate_values($tier,$best,(int)$w,$is_hit);
            $full_preview[$tier_id][] = ['post_id'=>$pid,'post_title'=>'ID '.$pid,'tier_id'=>$tier_id,'views'=>$vals['views'],'casts'=>$vals['casts'],'avg'=>$vals['avg'],'ratings'=>$vals['ratings'],'weight'=>$w,'is_hit'=>$is_hit];
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
        $tiers2 = \ApexOmega\Core\TierManager::get_tiers(); $tiers_by_id2=[]; foreach($tiers2 as $tt) $tiers_by_id2[$tt['id']]=$tt;
        $eligible_ids2 = \ApexOmega\Core\TierManager::get_eligible_post_ids($filters);
        $weights_batch2 = self::get_weights_batch_fast($eligible_ids2);
        $flat=[];
        foreach ($eligible_ids2 as $pid) {
            $w = $weights_batch2[$pid] ?? 50;
            $tier_id = self::weight_to_tier_hint((int)$w);
            if (!isset($tiers_by_id2[$tier_id])) $tier_id='tier_7';
            $tier=$tiers_by_id2[$tier_id];
            $is_hit = self::is_hit((int)$pid);
            $vals=\ApexOmega\Core\TierManager::generate_values($tier,$best,(int)$w,$is_hit);
            $flat[]=['post_id'=>$pid,'post_title'=>'ID '.$pid,'tier_id'=>$tier_id,'views'=>$vals['views'],'casts'=>$vals['casts'],'avg'=>$vals['avg'],'ratings'=>$vals['ratings'],'weight'=>$w,'is_hit'=>$is_hit];
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
