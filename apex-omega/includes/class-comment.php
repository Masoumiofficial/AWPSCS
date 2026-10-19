<?php
namespace ApexOmega\Core;

if (!defined('ABSPATH')) exit;

/**
 * 💬 نگهبان کامنت هوشمند — APEX Ω 1.7.0
 * برای هر پست/آهنگ بر اساس محبوبیت خواننده + بازدید، کامنت واقع‌گرایانه می‌گذارد
 * - نام‌های ایرانی انگلیسی‌نویسی (200+200) با ترکیب تصادفی اسم 1 + فامیل 5
 * - سه فرمت: فقط اسم / فقط فامیل / اسم+فامیل (20/20/60)
 * - AI: کامنت مرتبط به خواننده/عنوان با fallback قالب فارسی per tier
 * - نگهبان: <10K → 2-3 معمولی، پرطرفدار → متناسب با بازدید تا 60 + رشد روزانه
 */
class CommentGuardian {
    const OPTION = 'apex_omega_commentor';
    const META_LAST = '_apex_comment_guardian_last';
    const META_COUNT = '_apex_comment_guarded_count';

    public static function get_settings(): array {
        $defaults = [
            'enabled' => true,
            'threshold' => 10000, // زیر این = تازه
            'min_comments' => 2,   // حداقل برای تازه
            'max_comments' => 4,   // حداکثر برای تازه
            'daily_growth' => 2,   // هر روز برای پرطرفدار 1-3 تا اضافه
            'max_per_post' => 60,  // سقف کل کامنت هر پست حتی الماس
            'batch' => 0,          // 0=بدون سقف — روزانه همهٔ پست‌ها (درخواست کاربر 1.8.0)
            'only_recent_days' => 0, // 0=همه
            'cooldown_hours' => 24,
        ];
        $saved = get_option(self::OPTION, []);
        if (!is_array($saved)) $saved = [];
        $out = wp_parse_args($saved, $defaults);
        $out['enabled'] = !empty($out['enabled']);
        $out['threshold'] = max(1000, min(50000, (int)$out['threshold']));
        $out['min_comments'] = max(1, min(10, (int)$out['min_comments']));
        $out['max_comments'] = max($out['min_comments'], min(10, (int)$out['max_comments']));
        $out['daily_growth'] = max(1, min(5, (int)$out['daily_growth']));
        $out['max_per_post'] = max(10, min(100, (int)$out['max_per_post']));
        $out['batch'] = max(0, min(500, (int)$out['batch'])); // 0=همه، الگوی روزانه
        $out['only_recent_days'] = max(0, min(365, (int)$out['only_recent_days']));
        $out['cooldown_hours'] = max(1, min(168, (int)$out['cooldown_hours']));
        return $out;
    }

    public static function save_settings(array $data): void {
        $cur = self::get_settings();
        $new = [
            'enabled' => isset($data['enabled']) ? !empty($data['enabled']) : $cur['enabled'],
            'threshold' => isset($data['threshold']) ? (int)$data['threshold'] : $cur['threshold'],
            'min_comments' => isset($data['min_comments']) ? (int)$data['min_comments'] : $cur['min_comments'],
            'max_comments' => isset($data['max_comments']) ? (int)$data['max_comments'] : $cur['max_comments'],
            'daily_growth' => isset($data['daily_growth']) ? (int)$data['daily_growth'] : $cur['daily_growth'],
            'max_per_post' => isset($data['max_per_post']) ? (int)$data['max_per_post'] : $cur['max_per_post'],
            'batch' => isset($data['batch']) ? (int)$data['batch'] : $cur['batch'],
            'only_recent_days' => isset($data['only_recent_days']) ? (int)$data['only_recent_days'] : $cur['only_recent_days'],
            'cooldown_hours' => isset($data['cooldown_hours']) ? (int)$data['cooldown_hours'] : $cur['cooldown_hours'],
        ];
        update_option(self::OPTION, $new, false);
        Scheduler::ensure_cron();
    }

    public static function is_enabled(): bool {
        return !empty(self::get_settings()['enabled']);
    }

    // محاسبهٔ هدف کامنت — الگوی ریتم استاندارد: بازدید + وزن خواننده + امتیاز (همهٔ جوانب)
    public static function calculate_target(int $views, int $weight, float $avg = 4.0): int {
        $views = max(0, $views);
        $weight = max(0, min(100, $weight));
        // پایه بر اساس بازدید (طبق درخواست کاربر)
        if ($views < 10000) {
            $base_min = 2; $base_max = 3;
        } elseif ($views < 100000) {
            $base_min = 5; $base_max = 10;
        } elseif ($views < 500000) {
            $base_min = 12; $base_max = 25;
        } else {
            $base_min = 30; $base_max = 60;
        }
        // ضریب وزن: خواننده ویژه 1.2×، نوپا 0.85× — مو لای درز نره
        if ($weight >= 85) {
            $mult = 1.20;
        } elseif ($weight >= 70) {
            $mult = 1.10;
        } elseif ($weight >= 45) {
            $mult = 1.0;
        } elseif ($weight >= 30) {
            $mult = 0.95;
        } else {
            $mult = 0.85;
        }
        // ضریب امتیاز: ستاره‌دار بیشتر کامنت می‌گیرد (همبستگی بازدید↔امتیاز)
        $rating_mult = 0.85 + ($avg / 5) * 0.30; // avg 5→1.15، avg 3→1.03
        $mult *= $rating_mult;
        // خوانندهٔ شناخته‌شده ولی آهنگ تازه (وزن بالا + بازدید کم) → وسط: نه خیلی کم نه خیلی زیاد
        if ($weight >= 75 && $views < 10000) {
            // آهنگ تازه از سوپراستار — طبیعی‌تر: 3-5 نه 2-3
            $mult = max($mult, 1.05);
        }
        $target = (int) round( (($base_min + $base_max)/2) * $mult + mt_rand(-1,1) );
        $target = max($base_min, min($base_max, $target));
        // سقف نوپا حتی اگر بازدید بالا برود ولی وزن پایین: max 6
        if ($weight < 30 && $target > 6 && $views < 50000) {
            $target = min($target, 6);
        }
        return $target;
    }

    // نام ایرانی — 85% فارسی + 15% انگلیسی، با تنوع (امیر، امیررضا، amir، amir78، امیر)
    public static function generate_name(): array {
        // فارسی — از ویکی‌پدیا: فهرست نام‌های ایرانی + رده:نام‌های خانوادگی
        $first_fa = ['امیر','امیررضا','امیرحسین','امیرعلی','علی','رضا','حسین','محمد','محمدرضا','محمدحسین','مهدی','سجاد','سینا','آراد','آرش','آریا','آرمان','آرشام','آرشیا','آرتا','آرتین','آروین','اشکان','بابک','بهرام','بهداد','بهنام','بهزاد','بهروز','بورنا','دانیال','داریوش','احسان','عرفان','اسماعیل','فرهاد','فربد','فرزاد','فرشاد','فواد','حامد','حمید','حسن','حسام','هیراد','هومن','هادی','ایمان','جواد','کامیار','کاوه','کسری','کیوان','کیان','کیارش','کوروش','ماهان','مهیار','مانی','منوچهر','منصور','مسعود','مهران','مهرداد','مهرشاد','مهراد','میلاد','محسن','مجتبی','مرتضی','نیما','نوید','امید','پارسا','پرهام','پیام','پدرام','پژمان','پویا','پوریا','رامین','رسول','سعید','سجاد','صالح','سامان','سروش','سام','سپهر','شهاب','شاهین','شهرام','شایان','شروین','سهیل','طاها','وحید','یاسین','یوسف','یونس','زکریا','سارا','سحر','نگین','یاسمن','یاسمین','یلدا','یگانه','یسنا','زهرا','زینب','فاطمه','مریم','مهسا','میترا','مینا','مونا','نرگس','نسرین','نسترن','ندا','نگار','نیلوفر','پریسا','پارمیدا','پردیس','پگاه','ریحانه','رها','روکسانا','روژان','رزا','صدف','سمیرا','ساناز','ساره','ستاره','شادی','شقایق','شیما','شیرین','تارا','ترانه','تینا','سارینا','سوگل','سوگند','شیدا','غزل','هانیه','هدیه','نازنین','مژگان','حدیث','الهه','آتنا','آیدا','آناهیتا','باران','بهار','درسا','دینا','الناز','فائزه','هستی','کیمیا','لاله','مرجان','ملیکا','نادیا','پانته‌آ','پرنیا','رامسینا','سحر','ستایش','آوا','آرزو','آذین','آسنا','النا','الیکا','ماندانا','مینو','نوشین','پروانه','ژیلا','ژاله','هما','هنگامه','وحیده','ویدا','ناهید','نسترن','نیکی','پریا','پرستو','نگار','آیسان','آیسو','سایان','ساینا','سارینا','سلما','سحر','سحرناز','شادی','شراره','شهناز','شیوا','غزل','فاطیما','کتایون','لادن','لیلا','مهتاب','مهناز','مهسا','نازیلا','نسرین','نسیم','نیوشا','پانته‌آ','پریسا','پریا','پگاه','ترانه','تینا','حدیث','خاطره','درسا','دریا','دینا','رابعه','راضیه','رقیه','ریحانه','زهره','سارا','سحر','سحرناز','سمیه','سودابه','سوسن','سولماز','سیما','سیمین','شاپرک','شبنم','شراره','شکوفه','شیما','صبا','صدف','طاهره','عاطفه','عسل','غزل','فائزه','فرشته','فرناز','فهیمه','فیروزه','کتایون','کوثر','لادن','لیلا','مائده','محدثه','مرجان','مرضیه','مریم','مژگان','ملیحه','منا','مهتاب','مهناز','مهسا','مهشید','مونا','مونس','میترا','مینا','نادیا','نازنین','ناهید','ندا','نرگس','نگار','نگین','نیلوفر','ویدا','هانیه','هستی','هنگامه','یاسمن','یاسمین'];
        $last_fa = ['احمدی','حسینی','کریمی','مرادی','یگانه','موسوی','هاشمی','جعفری','نجفی','رضایی','محمدی','حسین‌زاده','کریم‌زاده','مرادی‌زاده','احمدی‌زاده','هاشمی‌زاده','جعفرزاده','کاظمی','رحیمی','رحمانی','بهرامی','قاسمی','صادقی','صالحی','سلطانی','فراهانی','فاضلی','ابراهیمی','حسنی','حیدری','علیزاده','امیری','امیرزاده','آزادی','عظیمی','باقری','بختیاری','برزگر','بیات','بهشتی','داداشی','دانش','دانشور','داوودی','دهقانی','عبادتی','عبادی','اسکندری','فلاح','فلاحی','فتاحی','غلامی','قنبری','قربانی','حاجی‌زاده','حمیدی','حقی','جمالی','جلالی','جوان','کارگر','کاشانی','کرمانی','خادمی','خلیلی','خندانی','خسروی','کیانی','محمودی','مجیدی','مالکی','منصوری','مرندی','مزاهری','مهرابی','میرزایی','موذنی','معینی','مقدم','محسنی','مختاری','مولوی','منتظری','موحد','نادری','نقوی','نصیری','نظری','نعمت‌اللهی','نیک‌نام','نیکزاد','نوری','امیدوار','پاک‌نژاد','پناهی','پرهیزکار','پارسا','پورمحمدی','قاسمی','قلی‌زاده','رئیسی','رفیعی','رنجبر','رشیدی','رستمی','روحانی','سفری','صحرایی','سجادی','سالاری','سلمانی','سرلک','ستاری','صیاد','شهبازی','شاهی','شاهسواری','شریفی','شجاعی','سلیمانی','طباطبایی','طاهری','تاجیک','توکلی','تهرانی','وحیدی','یزدی','یوسفی','زمانیان','زارع','زارعی','زارع‌پور','زرابی','ذوالفقاری','عباسی','عبدی','اکبری','اکبرزاده','علوی','علی‌پور','انصاری','عرب','عربی','اسدی','عسگری','اشرفی','عطایی','عطاری','عزیزی','بهادری','بابایی','بدیعی','باقرزاده','بختری','بنی','بهروزی','بستان','چاوشی','درویشی','دهقان','اسماعیلی','فرجی','فتحی','فتوحی','گودرزی','حدادی','حقیقی','حکیم','حکیمی','حمزه','هدایت','ایمانی','ایرانی','جباری','جهانگیری','جوادی','کبیری','کمال','کرامپور','خورشیدی','کرد','لطیف','لطفی','مدنی','مهدوی','مخملباف','ملک','میری','مفیدی','مقیمی','نادمی','نعیمی','ناجی','پهلوان','پرویزی','پاشایی','پویا','پورعلی','پوررضا','قادری','قنبری','ربانی','رفیع','رجبی','رمضانی','رسولی','روستا','صابری','صادق‌زاده','سعیدی','صلاح‌شور','سلیمی','صانعی','سرابی','شفیعی','شکوری','شمس','شریعتی','شایان','سهرابی','تاج‌زاده','طالبی','واعظی','یعقوبی','زمانی','زندی','حبیبی','نوری','اکرمی','شیرازی','تهرانی','اصفهانی','تبریزی','مشهدی','شیرزاد','حبیب‌زاده'];
        // انگلیسی — 15% (transliterated)
        $first_en = ['Ali','Reza','Hossein','Mohammad','Amir','Amirhossein','Amirali','Amirreza','Arad','Arash','Aria','Arman','Arsam','Arshia','Arta','Artin','Arvin','Ashkan','Babak','Bahram','Behdad','Behnam','Behzad','Borna','Danial','Dariush','Ehsan','Erfan','Farhad','Farbod','Farzad','Hamed','Hamid','Hassan','Hesam','Hooman','Hadi','Iman','Javad','Kamyar','Kaveh','Kasra','Keivan','Kian','Kiarash','Koroush','Mahdi','Mani','Masoud','Mehran','Mehrdad','Milad','Mohsen','Mojtaba','Morteza','Nima','Navid','Omid','Parsa','Payam','Pedram','Pejman','Pouya','Pouria','Ramin','Saeed','Sajjad','Saleh','Saman','Sepehr','Shahab','Shahin','Shahram','Shayan','Sina','Soheil','Taha','Vahid','Yasin','Yousef','Sara','Sahar','Negin','Yasaman','Yalda','Zahra','Zeinab','Fatemeh','Maryam','Mahsa','Mina','Mona','Narges','Negar','Niloufar','Parisa','Raha','Reyhaneh','Sadaf','Samira','Shadi','Shirin','Tara','Tina'];
        $last_en = ['Ahmadi','Hosseini','Karimi','Moradi','Yeganeh','Mousavi','Hashemi','Jafari','Najafi','Rezai','Mohammadi','Karimzadeh','Kazemi','Rahimi','Bahrami','Ghasemi','Sadeghi','Salehi','Soltani','Ebrahimi','Hassani','Heidari','Alizadeh','Amiri','Azimi','Bagheri','Barzegar','Danesh','Gholami','Ghorbani','Hajizadeh','Hamidi','Jamali','Kargar','Khademi','Khalili','Khosravi','Kiani','Mahmoudi','Maleki','Mansouri','Mirzaei','Moeini','Naderi','Nasiri','Nazari','Nouri','Paknejad','Ranjbar','Rashidi','Rostami','Rouhani','Safari','Soleimani','Tabatabaei','Taheri','Tajik','Tavakoli','Tehrani','Yousefi','Zare','Abbasi','Akbari','Alavi','Ansari','Asadi','Ataei','Azizi','Babaei','Shariati','Shams','Sohrabi','Zamani'];

        $use_fa = mt_rand(1,100) <= 85;
        if ($use_fa) {
            $f = $first_fa[array_rand($first_fa)];
            $l = $last_fa[array_rand($last_fa)];
            // تنوع نام کوچک: گاهی امیررضا، گاهی امیر + فاصله + رضا
            // تنوع پسوند فارسی
            $suffixes_fa = ['', '', '', '']; // همه پسوندهای عزیز/عزیزم/جون/جان/کوچولو حذف شد — نام تمیز ایرانی
            $suffix = $suffixes_fa[array_rand($suffixes_fa)];
            // 15% عدد چسبان مثل امیر78
            $num_suffix = '';
            if (mt_rand(1,100) <= 15) {
                $nums = ['78','2000','123','007','1369','1375','1380','99','2001'];
                $num_suffix = $nums[array_rand($nums)];
            }
            $r = mt_rand(1,100);
            if ($r <= 20) {
                $author = $f . $suffix . $num_suffix;
            } elseif ($r <= 40) {
                $author = $l . $suffix . $num_suffix;
            } else {
                $author = $f . ' ' . $l . $suffix . $num_suffix;
            }
            // ایمیل از روی transliteration ساده
            $base = strtolower(self::transliterate_fa_to_en($f . ' ' . $l));
            $base = preg_replace('/[^a-z]/','', $base);
            if (strlen($base) < 3) $base = 'user' . mt_rand(100,999);
        } else {
            $f = $first_en[array_rand($first_en)];
            $l = $last_en[array_rand($last_en)];
            // تنوع انگلیسی: amir, Amir, AMIR, amir78, amir_2000
            $variants = ['', '78','2000','123','007','_78','_2000','1369'];
            $num_suffix = $variants[array_rand($variants)];
            $case_r = mt_rand(1,100);
            if ($case_r <= 30) $f = strtolower($f);
            elseif ($case_r <= 60) $f = ucfirst(strtolower($f));
            // else keep as is
            $lvar = $l;
            if (mt_rand(1,100) <= 30) $lvar = strtolower($lvar);
            $r = mt_rand(1,100);
            if ($r <= 20) $author = $f . $num_suffix;
            elseif ($r <= 40) $author = $lvar . $num_suffix;
            else $author = $f . ' ' . $lvar . $num_suffix;
            $base = strtolower(str_replace(' ', '.', $f . ' ' . $l));
            $base = preg_replace('/[^a-z\.]/','', $base);
        }
        $base = preg_replace('/[^a-z\.]/','', $base);
        if (strlen($base) < 3) $base = 'user'.mt_rand(1000,9999);
        $base = trim($base,'.');
        $num = mt_rand(101, 9999);
        $domains = ['gmail.com','yahoo.com','outlook.com','icloud.com'];
        $email = $base . $num . '@' . $domains[array_rand($domains)];
        return ['author'=>trim($author), 'email'=>$email];
    }
    private static function transliterate_fa_to_en(string $fa): string {
        // نگاشت ساده برای ایمیل — کامل نیست ولی کافی
        $map = ['ا'=>'a','ب'=>'b','پ'=>'p','ت'=>'t','ث'=>'s','ج'=>'j','چ'=>'ch','ح'=>'h','خ'=>'kh','د'=>'d','ذ'=>'z','ر'=>'r','ز'=>'z','ژ'=>'zh','س'=>'s','ش'=>'sh','ص'=>'s','ض'=>'z','ط'=>'t','ظ'=>'z','ع'=>'a','غ'=>'gh','ف'=>'f','ق'=>'gh','ک'=>'k','گ'=>'g','ل'=>'l','م'=>'m','ن'=>'n','و'=>'o','ه'=>'h','ی'=>'y','آ'=>'a','ئ'=>'e','ء'=>'','ً'=>'','ٌ'=>'','ٍ'=>'','َ'=>'a','ِ'=>'e','ُ'=>'o','ّ'=>'','ـ'=>'','‌'=>'',' '=>' '];
        $en = strtr($fa, $map);
        // پاکسازی کاراکترهای خاص
        $en = preg_replace('/[^a-zA-Z0-9 ]/','', $en);
        return $en ?: 'user';
    }

    // تمیز کردن عنوان خام (حذف "دانلود آهنگ ..." + اسم خواننده) — از متا کمک بگیر نه کپی عنوان
    private static function clean_song_title(string $raw, string $artist): string {
        $t = trim($raw);
        // حذف پیشوندهای رایج
        $prefixes = ['دانلود آهنگ ','دانلود موزیک ','دانلود ','آهنگ ','موزیک ','Download ','download ','دانلود رایگان '];
        foreach ($prefixes as $pre) {
            if (mb_stripos($t, $pre) === 0) {
                $t = trim(mb_substr($t, mb_strlen($pre)));
            }
        }
        // حذف نام خواننده از عنوان (هر جا بود)
        if ($artist && mb_strlen($artist) > 2) {
            // حذف دقیق
            if (mb_stripos($t, $artist) !== false) {
                $t = trim(str_ireplace($artist, '', $t));
            }
            // حذف نسخه بدون فاصله (مثلاً "همایون شجریانبازگشت زال" — نادر ولی)
        }
        // حذف جداکننده‌های باقی‌مانده از ابتدا/انتها
        $t = trim($t, " -–—|:	

 ");
        $t = preg_replace('/^[\-–—|:\s]+/u','', $t);
        $t = preg_replace('/[\-–—|:\s]+$/u','', $t);
        // اگر هنوز "دانلود" مانده بود
        $t = preg_replace('/^دانلود\s*/u','', $t);
        $t = trim($t);
        // اگر تمیزکاری همه را پاک کرد (مثلاً عنوان فقط "همایون شجریان" بود)، برگشت به خام بدون پیشوند
        if (!$t || mb_strlen($t) < 2) {
            $t = trim(str_ireplace('دانلود آهنگ','', $raw));
            $t = trim(str_ireplace('دانلود','', $t));
            $t = trim($t, " -–—|:");
        }
        // حذف گیومه‌های باقی‌مانده
        $t = trim($t, "«»\"'‘’“”");
        return $t ?: $raw;
    }

    // تولید متن کامنت — AI مرتبط به خواننده + fallback فارسی per tier + خودمونی + تیکه ترانه + غلط تایپی ریز
    public static function generate_text(int $post_id, string $artist, string $title, int $weight, int $views): string {
        $clean = self::clean_song_title($title, $artist);
        $ai = self::generate_via_ai($artist, $clean, $weight, $views);
        $txt = $ai ?: self::fallback_text($weight, $views, $clean);
        // 15% یک تیکه از ترانه را اضافه کن — بدون گیومه «» یا ""، طبیعی مثل "اون تیکه که میگه درد دل منو بشنو خیلی دوس داشتم"
        if (mt_rand(1,100) <= 15 && mb_strlen($clean) > 2 && mb_strlen($clean) < 40) {
            $lyric_addons = [
                " اون تیکه که میگه {$clean} خیلی دوس داشتم",
                " مخصوصا اونجا که میگه {$clean} عااالیه",
                " اون قسمتش که میگی {$clean} دلم رفت",
                " تیکه {$clean} رو رو تکرارم",
            ];
            $txt .= $lyric_addons[array_rand($lyric_addons)];
        }
        // 12% غلط تایپی ریز طبیعی (نه زیاد) — برای انسانی بودن
        if (mt_rand(1,100) <= 12) {
            $txt = self::add_typo($txt);
        }
        // طبیعی‌سازی نهایی: گاهی کشیدن حروف محاوره‌ای (خییلی، عااالی)
        if (mt_rand(1,100) <= 10) {
            $txt = str_replace(['خیلی','عالی','مرسی'], ['خییلی','عااالی','مرررسی'], $txt);
        }
        return $txt;
    }
    private static function add_typo(string $s): string {
        $typos = [
            'خیلی' => 'خییلی',
            'خیلی ' => 'خیلی ',
            'دوست' => 'دوس',
            'دارم' => 'دارم',
            'عالی' => 'عالیی',
            'مرسی' => 'مرصی',
            'داره' => 'داره',
            'بود' => 'بود',
            'ممنون' => 'ممنن',
            'قشنگ' => 'قشنگگ',
            'عشق' => 'عشقق',
        ];
        // فقط یکی از کلمات را به‌صورت تصادفی عوض کن تا اسپم نشود
        $keys = array_keys($typos);
        shuffle($keys);
        foreach ($keys as $k) {
            if (mb_strpos($s, $k) !== false) {
                $s = str_replace($k, $typos[$k], $s);
                break;
            }
        }
        // گاهی یک حرف اضافی تایپی
        if (mt_rand(0,1) && mb_strlen($s) > 10) {
            $s = preg_replace('/([ا-ی])\1/u', '$1', $s, 1); // حذف تشدید برای طبیعی بودن
        }
        return $s;
    }

    private static function generate_via_ai(string $artist, string $title, int $weight, int $views): ?string {
        $clean = self::clean_song_title($title, $artist);
        $settings = AI_Helper::get_settings();
        if (empty($settings['api_key'])) return null;
        // لحن متنوع — نه همه + : 60% تحسین، 15% سلام+تعریف سایت/مدیر، 15% انتقاد ریز، 10% سوال/خنثی
        $roll = mt_rand(1,100);
        if ($roll <= 60) {
            if ($weight >= 85) $tone = 'هیجانی و عاشقانه، با ایموجی ❤️🔥';
            elseif ($weight >= 60) $tone = 'تحسین تخصصی، اشاره به صدا/ملودی';
            elseif ($weight >= 30) $tone = 'صمیمی و دوستانه';
            else $tone = 'ساده و کوتاه، تشویق معمولی';
        } elseif ($roll <= 75) {
            $tone = 'اول با سلام شروع کن و از سایت/مدیر سایت تعریف کن (مثلاً: سلام، سایت عالیه/دم مدیر گرم) بعد نظرت رو بگو';
        } elseif ($roll <= 90) {
            $tone = 'انتقاد ریز و سازنده: هم از خواننده (مثلاً تنظیم/شعر می‌تونست بهتر باشه) و هم یه نکته ریز از سایت (تبلیغ/لود/چینش) — مؤدبانه و کوتاه';
        } else {
            $tone = 'خنثی یا سوالی: مثلاً بپرس کی منتشر شده یا نظر بقیه چیه — بدون اغراق مثبت';
        }
        $tier_label = $views < 10000 ? 'تازه' : ($views < 100000 ? 'متوسط' : ($views < 500000 ? 'محبوب' : 'ابرآهنگ'));
        $colloquial = (mt_rand(1,100) <= 70) ? 'خیلی خودمونی و محاوره‌ای مثل کامنت واقعی سایت‌های موسیقی ایرانی (نکس‌وان، رادیوجوان) — با کلمات عامیانه مثل دمت گرم، عشقی، حال کردم، پشمام' : $tone;
        // 20% از AI بخواه تیکه ترانه را بگوید — بدون گیومه
        $lyric_hint = (mt_rand(1,100) <= 20) ? ' و حتماً بگو اون تیکه که میگه ' . $clean . ' خیلی دوس داشتم — با اشاره به ترانه ' . $clean : '';
        $prompt = "تو یک شنوندهٔ ایرانی هستی. برای آهنگ " . $clean . " از خوانندهٔ " . $artist . " (وزن {$weight}/100، دسته {$tier_label}، بازدید {$views}) یک کامنت فارسی طبیعی 1-2 جمله‌ای بنویس — لحن {$colloquial}{$lyric_hint}، بدون هشتگ و بدون انگلیسی، اصلاً نگو دانلود آهنگ یا دانلود موزیک، فقط اسم ترانه را مثل " . $clean . " بگو، و هرگز از گیومه «» یا \"\" استفاده نکن، دقیقاً مرتبط باش. گاهی یک غلط تایپی ریز طبیعی بگذار (مثل خییلی، دوس، عااالی). فقط متن کامنت را برگردان.";
        $payload = [
            'model' => $settings['model'] ?: 'gpt-4o-mini',
            'messages' => [
                ['role'=>'system','content'=>'تو یک کاربر ایرانی موسیقی هستی. فقط یک کامنت فارسی طبیعی برگردان.'],
                ['role'=>'user','content'=>$prompt],
            ],
            'temperature'=>0.8,
            'max_tokens'=>80,
        ];
        $resp = wp_remote_post($settings['endpoint'], [
            'headers'=>['Authorization'=>'Bearer '.$settings['api_key'],'Content-Type'=>'application/json'],
            'body'=>wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
            'timeout'=>10,
        ]);
        if (is_wp_error($resp)) return null;
        $body = wp_remote_retrieve_body($resp);
        $data = json_decode($body, true);
        $content = $data['choices'][0]['message']['content'] ?? '';
        $content = trim($content);
        if (!$content || mb_strlen($content) < 8 || mb_strlen($content) > 400) return null;
        // پاکسازی: اگر JSON برگردانده بود
        $content = trim($content, " \t\n\r\0\x0B\"'");
        return $content;
    }

    private static function fallback_text(int $weight, int $views, string $title = ''): string {
        // خودمونی — مثل کامنت واقعی سایت پرطرفدار، نه رسمی
        $low_pos = [
            'دمت گرم خیلی حال کردم باهاش 🙏',
            'پشمام صدات چقد قشنگه',
            'وای خدا این چه آهنگیه دلم رفت',
            'عجب چیزی بود عشق کردم',
            'حاجی صدات حرف نداره',
            'خیلی دوس داشتم مخصوصاً اون اولش',
            'آهنگ قشنگیه دمت گرم',
            'حس خوبی داره هی گوش میدم',
            'خسته نباشی ترکوندی',
            'داداش عالی بود',
            'واقعاً چسبید به دلم',
        ];
        $low_greet_site = [
            'سلام بچه‌ها سایتتون عالیه دمتون گرم',
            'سلام مدیر جان دمت گرم آرشیو خفنه',
            'درود سایتتون واقعاً مرتبه ممنون',
            'سلام خسته نباشید کارتون درسته',
        ];
        $low_critic = [
            'صدات خوبه ولی تنظیمش یکم ضعیفه',
            'خوب بود ولی انتظارم بیشتر بود',
            'بدک نیست ولی می‌تونست بهتر باشه',
            'قشنگه ولی کیفیتش پایین بود یه‌کم',
            'سایت خوبه فقط تبلیغش زیاده',
        ];
        $mid_pos = [
            'واقعاً حال کردم این قطعه فوق‌العاده‌ست 👏',
            'ملودیش می‌شینه به دل صداتم گرم',
            'رو تکرار گذاشتم بی‌نظیره',
            'ترکیب شعر و صدات محشر شده',
            'هر بار گوش میدم بیشتر دوسش دارم',
            'اجرت قشنگه حسش میره تو جون آدم',
            'تنظیم حرفه‌ایه دمت گرم',
            'یکی از بهترین کارهاته به نظرم',
            'وایبش خیلی خاصه عشق کردم',
            'این قطعه لایق ترند شدنه واقعاً',
        ];
        $mid_greet_site = [
            'سلام دمتون گرم بچه‌های سایت — چیدمانتون حرف نداره',
            'سلام مدیر عزیز سایتتون همیشه به‌روزه مرسی',
            'درود به تیم سایت خسته نباشید عالیه',
        ];
        $mid_critic = [
            'قشنگه ولی میکسش شلوغه کاش خلوت‌تر بود',
            'خوبه ولی این دفعه یکنواخت شدی',
            'سایت عالیه فقط پلیر یه‌کم لگ داره',
            'کاش ورژن آکوستیک هم بدی اینو',
            'شعرش یه‌جا لنگ می‌زنه به نظرم',
        ];
        $high_pos = [
            '🔥 پشمام ترکوندی این آهنگ ابدیه',
            'وای خدا این چی بود دیوونه شدم ❤️',
            'اسطوره‌ای هیچکی مثل تو نمی‌تونه بخونه',
            'محشره از ۱۰۰ بهت ۱۰۰ میدم',
            'صدات معجزه‌ست این قطعه موندگاره',
            'پرچمت بالاست دمت گرم',
            'هر ثانیه‌ش عشقه مرسی بابت این آهنگ',
            'این باید هیت سال بشه واقعاً',
            'تاریخ موسیقی با تو قشنگ‌تره',
            'بی‌نهایت دوسش دارم این آهنگ جاودانه‌ست',
            'از بچگی با صدات بزرگ شدم ناب',
        ];
        $high_greet_site = [
            'سلام و خسته نباشید به مدیر عزیز — آرشیوتون بی‌نظیره 👏',
            'سلام سایتتون بهترین مرجع موزیکه دمتون گرم',
        ];
        $high_critic = [
            'عالیه ولی کم‌کار شدی کاش بیشتر بدی',
            'شاهکاره ولی موزیک‌ویدیوش می‌تونست بهتر باشه',
            'سایت فوق‌العاده‌ست فقط کامنتا دیر لود میشه',
            'صدات بی‌نظیره ولی این تراک نسبت به قبلی ضعیف‌تر بود',
            'خوبه ولی کاش کنسرت بذاری',
        ];
        // انتخاب لحن
        $roll = mt_rand(1,100);
        $is_low = ($weight < 30 && $views < 10000);
        $is_high = ($weight >= 75 || $views >= 500000);
        if ($roll <= 60) {
            $pool = $is_high ? $high_pos : ($is_low ? $low_pos : $mid_pos);
        } elseif ($roll <= 75) {
            $pool = $is_high ? $high_greet_site : ($is_low ? $low_greet_site : $mid_greet_site);
        } elseif ($roll <= 90) {
            $pool = $is_high ? $high_critic : ($is_low ? $low_critic : $mid_critic);
        } else {
            $pool = ['نظرتون چیه؟ متوسط بود به نظرم','کی منتشر شده کسی می‌دونه؟','منتظر نظرات بقیه‌ام','بد نیست فعلاً گوش میدم','کسی متن ترانه رو داره؟'];
        }
        $extra = ['','', ' ❤️',' 🔥',' 👏',' 🙏',' 😍',' ✨',''];
        if ($roll > 75) $extra = ['','', '',' 🙏'];
        $txt = $pool[array_rand($pool)] . $extra[array_rand($extra)];
        // گاهی محاوره‌ای کشیدن
        if (mt_rand(1,100) <= 15) {
            $txt = str_replace(['عالی','خیلی','دوست'], ['عااالی','خییلی','دوس'], $txt);
        }
        if (mt_rand(1,100) > 85 && count($pool) > 1) {
            $txt .= ' ' . $pool[array_rand($pool)];
        }
        return $txt;
    }

    public static function run_manual(): array {
        ob_start();
        $before = get_option('apex_omega_commentor_last_run', []);
        self::tick(true);
        $after = get_option('apex_omega_commentor_last_run', []);
        $log = ob_get_clean();
        return ['before'=>$before,'after'=>$after,'log'=>$log];
    }
    // تیک هر ۶ ساعت (۴ بار/روز) — همهٔ پست‌ها بر اساس بازدید+امتیاز — درخواست 1.8.5
    public static function tick(bool $verbose = false): void {
        if (!self::is_enabled()) return;
        // گیت ۶ساعته — فقط هر ۲۱۶۰۰ ثانیه اجرا شود (دستی verbose دور می‌زند)
        if (!$verbose) {
            $last = (int) get_option('apex_omega_commentor_last_tick_ts', 0);
            if ($last && (time() - $last < 21600)) return;
            update_option('apex_omega_commentor_last_tick_ts', time(), false);
        }
        $s = self::get_settings();
        $threshold = (int)$s['threshold'];
        $max_per_post = (int)$s['max_per_post'];
        $batch = (int)$s['batch'];
        $recent_days = (int)$s['only_recent_days'];
        $cooldown = (int)$s['cooldown_hours'] * HOUR_IN_SECONDS;
        $daily_growth = (int)$s['daily_growth'];

        global $wpdb;
        $where_recent = '';
        if ($recent_days > 0) {
            $cutoff = gmdate('Y-m-d H:i:s', time() - $recent_days * DAY_IN_SECONDS);
            $where_recent = $wpdb->prepare(" AND p.post_date_gmt >= %s ", $cutoff);
        }
        // 1.8.7: برای هیت‌های میلیونی، recent_days سخت‌گیر نباش — اگر بازدید >500k حتی اگر قدیمی باشد شامل شود
        // این را در SQL جدا هندل می‌کنیم: اگر only_recent_days>0 ولی بازدید بالا باشد، اجازه بده
        // پس where_recent را برای هیت‌ها شل می‌کنیم در PHP بعداً (فعلا نگه می‌داریم اما در tick برای 657 چک ویژه داریم)
        // پیدا کردن پست‌های کاندید: همهٔ پست‌های منتشرشده که کامنت‌شان زیر سقف تیره است
        // اولویت با پست‌های تازه‌تر (post_date_gmt DESC) — مثل AutoWatch
        // 1.8.0: بدون سقف 20 — روزانه همهٔ پست‌های سایت بر اساس الگوی ریتم (بازدید+امتیاز+وزن)
        // batch=0 یعنی بدون LIMIT — روزانه همه
        $limit_clause = ($batch > 0) ? $wpdb->prepare("LIMIT %d", $batch) : "";
        // 1.8.7: cooldown هوشمند — هیت‌ها زودتر دوباره کامنت بگیرند
        // برای پست‌های میلیونی cooldown را از 24h به 12h/6h کاهش می‌دهیم، پس cutoff را پویا می‌سازیم
        // در SQL اول با cooldown عمومی می‌گیریم، بعد در PHP اگر هیت بود و تازه cooldown شده، باز هم اجازه می‌دهیم
        $sql = "
            SELECT p.ID, p.post_title, p.post_date_gmt, COALESCE(CAST(pm.meta_value AS UNSIGNED), 0) as cur_views
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key='views'
            WHERE p.post_type='post' AND p.post_status='publish'
            {$where_recent}
            AND NOT EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm2
                WHERE pm2.post_id = p.ID
                AND pm2.meta_key = %s
                AND pm2.meta_value >= %s
            )
            ORDER BY CAST(pm.meta_value AS UNSIGNED) DESC, p.post_date_gmt DESC, p.ID DESC
            {$limit_clause}
        ";
        $cooldown_cutoff = gmdate('Y-m-d H:i:s', time() - $cooldown);
        if ($batch > 0) {
            $rows = $wpdb->get_results($wpdb->prepare($sql, self::META_LAST, $cooldown_cutoff), ARRAY_A);
        } else {
            // بدون LIMIT — همهٔ پست‌های due
            $rows = $wpdb->get_results($wpdb->prepare($sql, self::META_LAST, $cooldown_cutoff), ARRAY_A);
        }
        if ($verbose) echo "candidates:".count($rows)." | threshold:{$threshold} max_per:{$max_per_post} recent:{$recent_days} cooldown:{$cooldown_cutoff}\n";
        if (empty($rows)) {
            if ($verbose) {
                // دلیل یاب
                $total_posts = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='post' AND post_status='publish'");
                $with_cooldown = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key=%s AND meta_value >= %s", self::META_LAST, $cooldown_cutoff));
                echo "total_posts:{$total_posts} with_cooldown:{$with_cooldown} | no candidates\n";
            }
            return;
        }

        // 1.8.7: تضمین برای هیت میلیونی با 0 کامنت — حتی اگر در cooldown باشد، وارد کن (سوتی ممنوع)
        // اگر پست 657 (یا مشابه 1M+) به خاطر cooldown 24h جا مانده و 0 کامنت دارد، آن را دستی اضافه کن
        if (!$verbose) {
            // فقط یکبار چک سبک برای هیت‌های جا مانده
            $mega_check = $wpdb->get_results($wpdb->prepare("SELECT p.ID, COALESCE(CAST(pm.meta_value AS UNSIGNED),0) as cur_views FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id=p.ID AND pm.meta_key='views' WHERE p.post_type='post' AND p.post_status='publish' AND CAST(pm.meta_value AS UNSIGNED) >= %d ORDER BY CAST(pm.meta_value AS UNSIGNED) DESC LIMIT 20", 500000), ARRAY_A);
            foreach ($mega_check as $mc) {
                $mid = (int)$mc['ID'];
                $mviews = (int)$mc['cur_views'];
                $mcur = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_post_ID=%d AND comment_approved='1'", $mid));
                if ($mcur === 0 && $mviews >= 800000) {
                    // آیا در $rows هست؟
                    $found=false; foreach($rows as $rr) if((int)$rr['ID']===$mid){$found=true;break;}
                    if(!$found){
                        // اگر به خاطر cooldown فیلتر شده، به زور اضافه کن (حتی اگر به تازگی کامنت خورده، چون 0 کامنت ضایع است)
                        $last_meta = get_post_meta($mid, self::META_LAST, true);
                        $in_cooldown = $last_meta && $last_meta >= $cooldown_cutoff;
                        if ($in_cooldown) {
                            error_log("[APEX Ω] Commentor: mega-hit $mid with $mviews views has 0 comments but in cooldown — forcing include");
                        }
                        $title = get_the_title($mid);
                        $rows[] = ['ID'=>$mid,'post_title'=>$title,'post_date_gmt'=>get_post_field('post_date_gmt',$mid),'cur_views'=>$mviews];
                    }
                }
            }
        }

        $exec = apex_omega_generate_uuid();
        $done_posts = 0;
        $done_comments = 0;
        foreach ($rows as $r) {
            $pid = (int)$r['ID'];
            $cur_views = (int)$r['cur_views'];
            $title = $r['post_title'] ?: get_the_title($pid);
            // وزن خواننده — همون الگوی ریتم حرفه‌ای
            $weight = ArtistManager::get_weight_for_post($pid);
            // هدف کامنت بر اساس بازدید + وزن (همبسته)
            $stats = Adapter_KKSR::get_stats($pid);
            $avg = isset($stats['avg']) ? (float)$stats['avg'] : 4.0;
            $target = self::calculate_target($cur_views, $weight, $avg);
            // سقف کلی
            $target = min($target, $max_per_post);
            // تعداد فعلی کامنت‌های تأییدشده
            $current = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_post_ID=%d AND comment_approved='1'", $pid));
            if ($current >= $target) continue;
            // اگر قبلاً خیلی کامنت دارد (مثلاً دستی)، skip
            if ($current >= $max_per_post) continue;

            // چند تا اضافه کنیم؟ — تازه 2-3 یکباره، پرطرفدار روزانه متناسب با بازدید (میلیونی بیشتر)
            // 1.8.7: سوتی ممنوع برای هیت‌ها — اگر بازدید میلیونی ولی کامنت صفر، پر کن تا خجالت نکشی
            $need = $target - $current;
            $is_mega_hit = ($cur_views >= 500000 && ($avg >= 4.7 || $cur_views >= 1000000));
            $is_zero_fill = ($current === 0 && $target >= 20);
            if ($is_zero_fill && $is_mega_hit) {
                // پر کردن اولیه: هیت میلیونی با 0 کامنت ضایع است — یکجا 10-14 تا بگذار
                $need = min($need, mt_rand(10, 14));
                if ($verbose) echo "mega-fill pid=$pid views=$cur_views avg=$avg target=$target need=$need (burst)\n";
            } elseif ($is_zero_fill && $cur_views >= 300000) {
                $need = min($need, mt_rand(6, 9));
            } elseif ($cur_views < $threshold) {
                $need = min($need, mt_rand($s['min_comments'], $s['max_comments']));
            } else {
                // مقیاس هوشمند: میلیونی روزانه 3-5، صد هزار 2-3، تازه‌پرمخاطب 1-2
                if ($cur_views >= 1000000) {
                    $daily_scaled = max($daily_growth + 2, 6);
                    $need = min($need, mt_rand(3, $daily_scaled));
                } elseif ($cur_views >= 500000) {
                    $daily_scaled = max($daily_growth + 1, 5);
                    $need = min($need, mt_rand(2, $daily_scaled));
                } elseif ($cur_views >= 100000) {
                    $daily_scaled = max($daily_growth, 3);
                    $need = min($need, mt_rand(1, $daily_scaled));
                } else {
                    $need = min($need, mt_rand(1, $daily_growth));
                }
            }
            $need = max(1, min(14, $need));

            // اگر گفت‌وگو بسته باشد، باز کن — وگرنه کامنت تو دیتابیس هست ولی قالب نشان نمی‌دهد
            if (get_post_field('comment_status', $pid) !== 'open') {
                wp_update_post(['ID' => $pid, 'comment_status' => 'open']);
            }
            // خواننده برای AI
            $artist_name = self::get_primary_artist($pid);
            if (!$artist_name) $artist_name = 'هنرمند ناشناس';

            $inserted = 0;
            for ($i=0; $i < $need; $i++) {
                // اطمینان از سقف
                if ($current + $inserted >= $target) break;
                if ($current + $inserted >= $max_per_post) break;
                $who = self::generate_name();
                $text = self::generate_text($pid, $artist_name, $title, $weight, $cur_views);
                // کمی تنوع زمانی: هر کامنت 2-10 دقیقه فاصله (برای طبیعی بودن تاریخ)
                $comment_time_gmt = gmdate('Y-m-d H:i:s', time() - mt_rand(0, 600));
                $comment_time = get_date_from_gmt($comment_time_gmt, 'Y-m-d H:i:s');
                // جلوگیری از duplicate exact (همون متن + همون پست در 24h)
                $dup = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_post_ID=%d AND comment_content=%s AND comment_date_gmt >= %s",
                    $pid, $text, gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS)
                ));
                if ($dup) {
                    // متن تکراری — fallback دیگر بگیر
                    $text = self::fallback_text($weight, $cur_views) . ' ' . mt_rand(10,99);
                }
                $cid = wp_insert_comment([
                    'comment_post_ID' => $pid,
                    'comment_author' => $who['author'],
                    'comment_author_email' => $who['email'],
                    'comment_author_url' => '',
                    'comment_content' => $text,
                    'comment_type' => '',
                    'comment_parent' => 0,
                    'user_id' => 0,
                    'comment_author_IP' => self::fake_ip(),
                    'comment_agent' => 'Mozilla/5.0',
                    'comment_date' => $comment_time,
                    'comment_date_gmt' => $comment_time_gmt,
                    'comment_approved' => 1,
                ]);
                if ($cid && !is_wp_error($cid)) {
                    // متای کمکی برای تشخیص کامنت ربات (اختیاری)
                    add_comment_meta($cid, '_apex_commentor', 1, true);
                    add_comment_meta($cid, '_apex_weight', $weight, true);
                    // آپدیت شمارندهٔ کامنت پست (WP خودش می‌کند ولی برای اطمینان)
                    wp_update_comment_count($pid);
                    $inserted++;
                    // تاریخچه: هر کامنت یک لاگ جدا (خلاصه)
                    History::log($pid, 'comment', $current, $current+$inserted, 'commentor', 0, $exec, null, ['author'=>$who['author'],'weight'=>$weight,'target'=>$target]);
                }
                // فاصله کوتاه برای عدم اسپم
                usleep(50000);
            }
            if ($inserted > 0) {
                update_post_meta($pid, self::META_LAST, current_time('mysql', true));
                update_post_meta($pid, self::META_COUNT, (int)get_post_meta($pid, self::META_COUNT, true) + $inserted);
                $done_posts++;
                $done_comments += $inserted;
            }
            // برای اینکه هر تیک خیلی شلوغ نشه، اگر 3 پست پر کردیم و هنوز batch ادامه دارد، ادامه بده — ولی کل کامنت‌ها را لاگ کن
        }
        if ($done_posts > 0) {
            update_option('apex_omega_commentor_last_run', ['at'=>current_time('mysql', true),'posts'=>$done_posts,'comments'=>$done_comments,'exec'=>$exec], false);
            error_log("[APEX Ω] Commentor: {$done_comments} comments on {$done_posts} posts (threshold {$threshold})");
        }
    }

    public static function send_for_post(int $post_id, int $requested_count): array {
        if (!get_post($post_id)) return ['success'=>false,'message'=>'نوشته یافت نشد.'];
        $requested_count = max(1, min(60, $requested_count));
        $settings = self::get_settings();
        $max_per_post = (int)$settings['max_per_post'];
        // Check current count
        global $wpdb;
        $current = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_post_ID=%d AND comment_approved='1'", $post_id));
        $remaining = $max_per_post - $current;
        if ($remaining <= 0) return ['success'=>false,'message'=>"این پست به سقف $max_per_post کامنت رسیده."];
        $count = min($requested_count, $remaining);
        // Also respect target but allow manual to exceed target up to max_per_post
        $title = get_the_title($post_id) ?: '';
        $weight = \ApexOmega\Core\ArtistManager::get_weight_for_post($post_id);
        $cur_views = (int) get_post_meta($post_id, 'views', true);
        $stats = \ApexOmega\Core\Adapter_KKSR::get_stats($post_id);
        $avg = isset($stats['avg']) ? (float)$stats['avg'] : 4.0;
        $artist_name = self::get_primary_artist($post_id);
        if (!$artist_name) $artist_name = 'هنرمند ناشناس';
        // Open comment_status if closed
        if (get_post_field('comment_status', $post_id) !== 'open') {
            wp_update_post(['ID' => $post_id, 'comment_status' => 'open']);
        }
        $exec = apex_omega_generate_uuid();
        $inserted = 0;
        for ($i=0; $i < $count; $i++) {
            $who = self::generate_name();
            $text = self::generate_text($post_id, $artist_name, $title, $weight, $cur_views);
            $comment_time_gmt = gmdate('Y-m-d H:i:s', time() - mt_rand(0, 300));
            $comment_time = get_date_from_gmt($comment_time_gmt, 'Y-m-d H:i:s');
            $dup = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_post_ID=%d AND comment_content=%s AND comment_date_gmt >= %s", $post_id, $text, gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS)));
            if ($dup) $text = self::fallback_text($weight, $cur_views) . ' ' . mt_rand(10,99);
            $cid = wp_insert_comment([
                'comment_post_ID' => $post_id,
                'comment_author' => $who['author'],
                'comment_author_email' => $who['email'],
                'comment_author_url' => '',
                'comment_content' => $text,
                'comment_type' => '',
                'comment_parent' => 0,
                'user_id' => 0,
                'comment_author_IP' => self::fake_ip(),
                'comment_agent' => 'Mozilla/5.0',
                'comment_date' => $comment_time,
                'comment_date_gmt' => $comment_time_gmt,
                'comment_approved' => 1,
            ]);
            if ($cid && !is_wp_error($cid)) {
                add_comment_meta($cid, '_apex_commentor', 1, true);
                add_comment_meta($cid, '_apex_weight', $weight, true);
                wp_update_comment_count($post_id);
                $inserted++;
                \ApexOmega\Core\History::log($post_id, 'comment', $current, $current+$inserted, 'manual_comment', get_current_user_id(), $exec, null, ['author'=>$who['author'],'weight'=>$weight,'requested'=>$requested_count]);
            }
            usleep(30000);
        }
        if ($inserted > 0) {
            update_post_meta($post_id, self::META_LAST, current_time('mysql', true));
            update_post_meta($post_id, self::META_COUNT, (int)get_post_meta($post_id, self::META_COUNT, true) + $inserted);
            update_option('apex_omega_commentor_last_run', ['at'=>current_time('mysql', true),'posts'=>1,'comments'=>$inserted,'exec'=>$exec], false);
        }
        return ['success'=>true,'inserted'=>$inserted,'requested'=>$requested_count,'current'=>$current+$inserted,'max'=>$max_per_post];
    }

    private static function get_primary_artist(int $post_id): string {
        $tax = ArtistManager::detect_artist_taxonomy();
        if ($tax) {
            $terms = wp_get_post_terms($post_id, $tax, ['fields'=>'names']);
            if (!empty($terms) && !is_wp_error($terms)) return $terms[0];
        }
        $t = get_the_title($post_id);
        $ext = ArtistManager::extract_artist_from_title($t);
        return $ext ?: '';
    }

    private static function fake_ip(): string {
        return mt_rand(1,223).'.'.mt_rand(0,255).'.'.mt_rand(0,255).'.'.mt_rand(1,254);
    }
}
