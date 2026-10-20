# APEX Ω — تحلیل جامع پلاگین برای تیم هوش مصنوعی (Auditor Agents)
**نسخه مبنا:** 1.9.2 (commit 36f3d8f) — تاریخ 2026-10-20  
**سایت موردی:** 884 پست نوع `post` (آهنگ)، 300 دارای کامنت (34٪)، 584 بدون کامنت (66٪)  
**هدف سند:** خوراک استاندارد برای چند Agent موازی تا تمام باگ‌های منطقی، امنیتی، کارایی و همبستگی آماری را شکار کنند.

---

## 1) معماری کلی (نقشه ذهنی)

```
apex-omega.php (bootstrap)
 ├── includes/
 │   ├── class-database.php      — 4 جدول: history, schedules, locks, snapshots + قفل DB
 │   ├── class-validator.php     — سقف‌ها و volatility
 │   ├── class-adapter-kksr.php  — کشف خودکار kk Star Ratings (_kksr_casts/_ratings/_avg, best)
 │   ├── class-adapter-views.php — متای views (+ _apex_real_views آرشیو)
 │   ├── class-history.php       — لاگ هر تغییر + rollback
 │   ├── class-scheduler.php     — cron 5m (tick) + 6h (commentor) + weekly + health
 │   ├── class-tier.php          — 7 دسته هرم 1.5M + توزیع اتمیک + اسنپ‌شات
 │   ├── class-artist.php        — تشخیص taxonomy + وزن 0-100 + الگوی ریتم 84/16
 │   ├── class-ai.php            — GapGPT (key رمز با AUTH_KEY)
 │   ├── class-provider.php      — 6 سایت ایرانی (MyBia2Music…Next1Music)
 │   ├── class-evergreen.php     — رشد 0.035%/5m چرخشی 32/تیک
 │   ├── class-autowatch.php     — شارژ اولیه کم‌بازدید 600-9000
 │   ├── class-comment.php       — CommentGuardian (tick 6h, batch 75, threshold 10K)
 │   ├── class-ajax.php          — 30 اکشن + ob_clean + dual nonce
 │   ├── class-analytics.php / class-export.php / class-rest.php
 │   └── helpers.php (uuid, encrypt, lock_ttl)
 ├── admin/
 │   ├── class-admin.php + metabox + bulk
 │   ├── views/tier-distribution.php — پنل هرم + Evergreen + AutoWatch + Commentor + AI
 │   ├── views/dashboard.php / history / settings
 │   └── js/admin.js + css/admin-rtl.css
 └── public/class-public.php
```

**نقطه ورود کاربر:** `توزیع هوشمند` (هرم) → `اعمال خودکار` → `TierManager::apply_distribution()` (سنگین‌ترین تراکنش). `AutoWatch/Evergreen/CommentGuardian` هم هر 5m/6h خودکار می‌دوند.

---

## 2) تحلیل آماری سایت فعلی (884 آهنگ)

| شاخص | مقدار | تفسیر |
|---|---|---|
| کل پست | 884 | مبنای هرم 2/4/10/14/20/25/25 = 18/35/88/124/177/221/221 |
| دارای کامنت تاییدشده | ~300 (34%) | توزیع ناقص — باید 100% حداقل 2 کامنت داشته باشد |
| بدون کامنت | ~584 (66%) | **ناهنجاری بحرانی** — سایت موزیک با 66% سکوت غیرطبیعی است |
| میانگین کامنت برای 300تای کامنت‌دار (تخمین) | 12/5=2.4 در آخرین تیک | یعنی حتی کامنت‌دارها هم کف هرم هستند |
| بازدید (تخمین از 884) | احتمالا 60% زیر 10K (چون تازه) | Autowatch باید 600-9000 + Evergreen 0.035% کار کند اما 503 مانع شده |
| رای/kksr | باید همبسته با بازدید باشد: 10K→40-160 رای، 500K→1200-3500 | اگر 503 اعمال را ناقص گذاشته، همبستگی شکسته |

**نتیجه:** نرخ پوشش کامنت 34% < حد استاندارد 85%+ برای سایت موزیک فعال. علت ریشه‌ای در بخش 4-B.

---

## 3) الگوریتم‌های کلیدی و منطق مورد انتظار

### 3.1 TierManager — هرم 1.5M
- 7 tier با views/casts/avg پیوسته بدون گپ.
- `generate_values()` رندوم + bias 0.25 برای tier_1/2 + views_factor +0.12.
- `preview_distribution()` → `get_eligible_post_ids()` (paging 1000/batch, filter 7روزه) → shuffle یا artist_aware sort → slice.
- `apply_distribution()` → preview → master_lock (TTL=count*2) → snapshot_rows → per-post TRANSACTION (views+kksr+tier) → history 2×.

**انتظار حرفه‌ای:** توزیع اتمیک، قابل Undo، همبستگی views↔casts↔avg، TTL پویا.

### 3.2 ArtistManager — ریتم 84/16
- `get_weight_for_post()` = ترکیب `artist_weight` (از taxonomy) و `title_score` (hit_pairs+generic+AI).
- سه شاخه: `<28 → cap42`, `28-60 → نرم`, `>=60 → 84/16 + جهش 8`.
- `score_title_for_post()` جفت‌محور، نه عنوان تنها.

**انتظار:** خواننده ناشناس هرگز با عنوان «بهت قول میدم» به الماس نرود.

### 3.3 AutoWatch — تزریق اولیه
- هر 5m، 22 پست با `views<threshold (10K)` و `post_date>=45روز` و `not in cooldown 22h` → `generate_initial(weight)` → views 600-9000 + casts + avg + tier_hint.
- Evergreen سپس هر 5m، 32 پست چرخشی +0.035% با ضریب tier.

**انتظار:** پست تازه هرگز 0 نماند، قدیمی‌ها Evergreen.

### 3.4 CommentGuardian — نگهبان کامنت
- `calculate_target(views, weight, avg)`:
  - پایه views: <10K→2-3, 10K-100K→5-10, 100K-500K→12-25, 500K+→30-60 → میانگین + jitter ±1 → clamp
  - ضریب وزن: 85+→1.2, 70→1.1, 45→1.0, 30→0.95, <30→0.85
  - ضریب رای: 0.85+avg/5*0.30 → 0.85-1.15
  - استثنا: weight≥75 + views<10K → mult≥1.05
  - سقف: weight<30 + views<50K → max 6
- `tick(6h)` → candidates SQL `NOT EXISTS _apex_comment_guardian_last >= cooldown` + `ORDER BY comment_count ASC, views DESC LIMIT 75` → per-post need (mega 10-14, 300K+ 6-9, <threshold 2-4, else daily 1-6) → wp_insert_comment + history.

**انتظار:** 584 صفر باید در 8 تیک (2روز) پر شود، هر پست 24h cooldown.

---

## 4) فهرست باگ‌ها (دسته‌بندی برای Agentها)

### 4.A بحرانی (Critical) — قبلاً فیکس اما درس‌آموز
1. **A1 - Undefined $ratings in Tier::generate_values()** (1.9.1 فیکس)
   - `return ['ratings'=>round($ratings,2)]` بدون تعریف → Warning HTML → `SyntaxError: '<'` در اعمال خودکار. باید ` $ratings = $avg*$casts/$best*5`.
   - **تست Agent:** آیا هر متغیری قبل return مقدار می‌گیرد؟ grep `return.*\$ratings` بدون تعریف قبلی.

2. **A2 - effective_batch undefined** (1.9.2 فیکس جزئی)
   - `if($verbose) echo ...$effective_batch` در حالی که `$effective_batch` فقط در شاخه `batch>0? :75` تعریف شده بود اما در یک مرج پاک شده بود → Notice → HTML.
   - **تست:** اجرای tick با batch=0 در حالت verbose.

3. **A3 - 503 Service Unavailable on 884 apply** (1.9.2 فیکس ناقص)
   - اعمال 884×(3 meta +2 history+lock) در یک request → timeout/overload → host 503. فیکس فعلی فقط `usleep(300ms per 100)` و retry 1× — برای shared host کافی نیست.
   - **نیاز Agent:** طراحی chunked apply واقعی: preview → transient eligible_ids → offset/limit + progress bar + background queue.

4. **A4 - JS conflict marker `<<<<<<< HEAD`** (1.8.8)
   - ادغام دستی `admin.js` باقی‌مانده → `Uncaught SyntaxError: missing )` → همه دکمه‌ها مرده.
   - **درس:** CI باید `node --check admin.js` و `grep -r '<<<<<<<'` را قبل push چک کند.

### 4.B بالا (High) — فعال و نیازمند رسیدگی
5. **B1 - پوشش 34% کامنت (584 صفر)**
   - ریشه: batch قدیمی نامحدود → timeout → starvation کم‌بازدیدها + `ORDER BY views DESC` بدون اولویت صفر. فیکس 1.9.0 `ORDER BY comment_count ASC` و `effective_batch 75` و `bypass recent for 0` و `fallback cron` اعمال شد اما هنوز 584 باقی — یعنی یا هنوز 1.9.0 نصب نشده یا cooldown 24h مانع.
   - **تست Agent:** `SELECT COUNT(*) FROM posts WHERE NOT EXISTS (SELECT 1 FROM comments WHERE comment_post_ID=ID)` vs `SELECT COUNT(*) FROM postmeta WHERE meta_key='_apex_comment_guardian_last' AND value>=cooldown` → ببین چندتا due واقعی هست.

6. **B2 - Autowatch `only_recent_days=45` vs Comment `0` ناهمگونی**
   - AutoWatch فقط 45روز اخیر را شارژ می‌کند، اما CommentGuardian همه را کامنت می‌دهد → پست قدیمی با views=0 اما 2 کامنت → ناهمگونی بازدید-کامنت غیرطبیعی.
   - **پیشنهاد:** همگام‌سازی: هر دو 0 یا هر دو 45، یا AutoWatch هم bypass برای 0 views.

7. **B3 - Evergreen inc برای <10K**
   - `min($inc, mt_rand(10,18))` هر 5m = 120-216/ساعت = 2880/روز → پست 1K در یک روز به 4K می‌جهد — برای تازه‌ها سریع است اما شاید قابل قبول. با AutoWatch 600-9000 همپوشانی دارد و ممکن است دوبار شارژ شود.
   - **تست:** شبیه‌سازی 30روزه برای یک پست 800 views ببین به کجا می‌رسد.

8. **B4 - Tier apply بدون paging واقعی**
   - `get_eligible_post_ids()` با `WP_Query posts_per_page=limit (884)` یک‌جا 884 ID را می‌گیرد → حافظه 884×WP_Post. فیکس paging 1000/batch دارد اما فقط برای limit>5000. برای 884 هنوز یک کوئری 884تایی است — شاید 884 مشکلی نباشد اما برای 5000+ خطرناک.
   - **پیشنهاد:** همیشه paging 500/batch.

9. **B5 - Lock table بدون TTL پاکسازی منظم**
   - `acquire_lock()` هر بار `DELETE WHERE expires_at_gmt < now` می‌زند اما `cleanup_expired_locks` فقط روزانه cron است. اگر سایتی 884×2 lock در هر توزیع بگذارد و fail شود، جدول locks باد می‌کند.
   - **تست:** `SELECT COUNT(*) FROM apex_omega_locks` بعد 10 distribute.

### 4.C متوسط (Medium)
10. **C1 - Artist detection fallback به post_tag/genre**
    - اگر taxonomy آرتیست پیدا نشود، `post_tag` را به‌عنوان آرتیست می‌گیرد → وزن‌دهی اشتباه.
    - تست: سایتی که فقط دسته‌بندی دارد، `detect_artist_taxonomy()` چی برمی‌گرداند؟

11. **C2 - weight_to_tier_hint thresholds vs tier ranges ناهم‌تراز**
    - weight 45 → tier_4 (80K-200K) اما AutoWatch max 9000 → وزن 45 هرگز به 80K نمی‌رسد → ناهمگونی.
    - باید mapping دوباره کالیبره شود.

12. **C3 - Validator volatility old<100 bypass**
    - `if(old>=100 && percent>=50 ...)` → پست تازه 1→10000 بدون هشدار می‌گذرد — درست برای تازه‌ها اما برای دستکاری عمدی خطرناک.
    - پیشنهاد: هشدار نرم، نه block.

13. **C4 - History table بدون prune**
    - هر comment 1 history + evergreen هر ساعت 1 → 884×(30روز×288 تیک) = 7M ردیف بالقوه → انفجار DB.
    - نیاز به retention policy (90روز).

14. **C5 - AI key fallback b64 insecure**
    - اگر openssl نباشد، `b64:` ذخیره می‌شود — عملا plaintext.
    - باید هشدار بده یا غیرفعال کند.

15. **C6 - Scheduler maybe_heal_cron transient abuse**
    - `set_transient('apex_omega_heal_last',1,300)` هر init → 288 بار/روز write به options → فشار.

### 4.D پایین (Low) / بهبود
- D1: `apex_omega_generate_uuid()` fallback `uniqid+wp_generate_password` ضعیف — بهتر `wp_generate_uuid4()` وردپرس 6+
- D2: `Adapter_KKSR::detect_best()` فقط گزینه‌های محدود را چک می‌کند، نه `get_option('kksr_star_num')` احتمالی.
- D3: `CommentGuardian::generate_name()` آرایه‌های 200+200 هاردکد — باید از فایل زبان جدا شود برای تست‌پذیری.
- D4: ` TierManager::suggested_pyramid()` 2/4/10/14/20/25/25 جمع 100% اما برای 884 → 17.68→18 الماس، رند کردن باعث drift — باید از بزرگ‌ترین باقیمانده استفاده کند.
- D5: UI/UX: دکمه «اعمال خودکار» بدون progress bar → کاربر فکر می‌کند هنگ کرده و چندبار می‌زند → دوبار distribute → race.

---

## 5) چک‌لیست برای Agentهای موازی

**Agent-1 (Logic):** تمام `calculate_target`, `generate_initial`, `generate_values`, `get_weight_for_post` را با 100 ورودی تصادفی fuzz کن، ببین آیا خروجی همیشه در بازه tier است و همبستگی views↔casts↔avg حفظ می‌شود.

**Agent-2 (Security):** `class-ajax.php` → هر اکشن `current_user_can` + `wp_verify_nonce` + `sanitize` را چک کن. SQL injection با `prepare`؟ XSS در `admin/views/*.php` با `esc_html`؟

**Agent-3 (Performance):** `EXPLAIN` برای هر SQL (tier eligible, autowatch, comment candidates) + تخمین `wp_insert_comment` 584×3 برای 884 پست → زمان و 503.

**Agent-4 (Data Integrity):** History→Rollback→Snapshot زنجیره را با یک distribute → Undo → Redo تست کن. آیا `postmeta _real_views` و `kksr` همزمان برمی‌گردد؟

**Agent-5 (Rhythm):** 20 جفت آرتیست+عنوان معروف ایرانی بده، ببین `get_weight_for_post()` آیا ناشناس با عنوان هیت جهش نمی‌کند (cap42) و ستاره با عنوان معمولی افت نمی‌کند.

---

## 6) توصیه‌های فوق‌حرفه‌ای فوری (برای 884 سایت)

1. **Chunked Apply اجباری:** هر `apply_distribution` را به 100تایی تقسیم کن، بین هر chunk AJAX جدید بزن، progress bar نشان بده. 503 را 0 کن.
2. **Backfill 584 صفر:** یک اسکریپت یک‌بار مصرف `wp apex-omega backfill-comments --all-zero` که بدون cooldown، 584 تا را با `send_for_post` پر کند (همان Burst 10-14 برای 1M+).
3. **Autowatch recent → 0:** برای این سایت تاریخی، `only_recent_days=0` بگذار تا 45روزه نماند.
4. **Evergreen rate → 0.03:** برای 884 پست فعلی، کمی کندتر طبیعی‌تر است.
5. **History prune:** `wp cron` روزانه که `history WHERE created_at_gmt < NOW()-90d` را پاک کند.

---

## 7) فایل‌های کلیدی برای شروع Audit

- `includes/class-tier.php:147-165` (generate_values $ratings)
- `includes/class-artist.php:135-195` (get_weight_for_post 3 شاخه)
- `includes/class-comment.php:110-180` (calculate_target)
- `includes/class-comment.php:470-600` (tick SQL + need)
- `includes/class-autowatch.php:55-130` (tick)
- `includes/class-evergreen.php:40-110` (tick)
- `admin/views/tier-distribution.php:300-470` (JS fetch + retry)
- `includes/class-ajax.php:20-60` (router + ob_clean)
- `includes/class-database.php:95-160` (locks)

**فرمان سریع برای Agent:** `grep -rn "TODO\|FIXME\|Undefined\|prepare.*%s" includes/`

---

*پایان — این سند را به هر Agent بده و بخواه خروجی را به‌صورت JSON با فیلدهای {severity, file, line, proof, fix} برگرداند.*
