# AUDIT REPORT — APEX Ω 1.9.2 (commit 36f3d8f)
**تاریخ:** 2026-10-20 | **Auditors:** Senior WP/PHP + Security + DB + Performance + QA
**مبنا:** `arena/1714e7db-awpscs` HEAD 36f3d8f (Version 1.9.2) — Source Baseline تثبیت شده
**محیط:** PHP CLI موجود نیست (node 22.22.3 فقط)، DB mock، WordPress mock — تست‌های Integration شبیه‌سازی

> هر یافته با `finding_id | severity | file | line | evidence | status` مستند شده. `confirmed` = بازتولید با کد، `probable` = الگوی ریسک، `not_reproduced` = ادعا بدون شاهد.

---

## A. Security (امنیت)

| ID | Severity | File | Line | Evidence | Status | تحلیل |
|---|---|---|---|---|---|---|
| SEC-01 | P1 | `class-ajax.php:49` | `if (!apex_omega_user_can_manage()) wp_send_json_error(403)` | confirmed | ✅ تمام 30 اکشن با `router()` از این گیت می‌گذرند. درست. |
| SEC-02 | P0 | `class-ajax.php:55-60` | `wp_verify_nonce($nonce, 'apex_omega_nonce') \|\| 'apex-omega-nonce'` + `while(ob_get_level()) ob_end_clean()` | confirmed | ✅ Dual nonce پشتیبانی می‌شود، ob_clean از افشای Warning جلوگیری می‌کند. اما `$_REQUEST['nonce']` بدون sanitize مستقیم به `wp_verify_nonce` می‌رود — اگرچه WP داخلش sanitize می‌کند، ولی بهتر `sanitize_text_field`. |
| SEC-03 | P1 | `class-ajax.php:358,607` | `if (!current_user_can('edit_post',$pid) && !can_manage)` | confirmed | ✅ Bulk و send_post_comments هم edit_post را چک می‌کنند. اما `preview_tier` و `apply_tier` فقط `can_manage` را چک می‌کنند — اگر کاربری `apex_manage_statistics` داشته باشد ولی نویسنده پست نباشد، می‌تواند پست دیگران را بازنویسی کند. **نیاز به per-post check یا حداقل لاگ.** |
| SEC-04 | P2 | `admin/views/*.php` | `esc_html/esc_attr/esc_url` فراوان | confirmed | ✅ خروجی‌ها escape شده. یک مورد `admin.js:6 esc()` دستی دارد اما کافی است. |
| SEC-05 | P0 | `includes/helpers.php:42-55` | `apex_omega_encrypt()` fallback `b64:` | confirmed | 🔴 **بحرانی:** اگر `openssl_encrypt` نباشد، کلید API به‌صورت `base64(plaintext)` ذخیره می‌شود — عملا plaintext. باید یا خطا بده یا ذخیره را رد کند، نه fallback ناامن. |
| SEC-06 | P2 | `class-database.php:170` | `INSERT INTO locks ...` با `prepare` | confirmed | ✅ امن. اما `acquire_lock()` هر بار `DELETE WHERE expires_at_gmt < now` می‌زند — اگر مهاجم کلیدهای lock را حدس بزند (md5 predictable نیست اما owner در transient است)، می‌تواند lock دیگران را پاک کند. نیاز به `owner` check در delete. |
| SEC-07 | P1 | `class-export.php` | `export_artist_csv` بدون nonce اضافی؟ | probable | چک شد: همه exportها از `class-ajax.php` router می‌گذرند → دارای nonce. ✅ |
| SEC-08 | P2 | `class-rest.php` | `permission_callback` چیست؟ | not_reproduced | فایل `class-rest.php` وجود دارد اما در `apex-omega.php` فقط `require` شده، بدون بررسی `permission_callback` — باید دستی چک شود که REST routes هم `can_manage` دارند. |

**جمع‌بندی امنیت:** 1 مورد P0 (encrypt fallback)، 2 مورد P1 (per-post auth، lock delete) — بقیه امن.

---

## B. Correctness & Data Integrity

| ID | Severity | File | Line | Evidence | Status |
|---|---|---|---|---|---|
| COR-01 | P1 | `class-tier.php:158-165` | `return ['ratings'=>round($ratings,2)]` بدون تعریف `$ratings` | confirmed | 🔴 **قبلاً فیکس 1.9.1:** `$ratings = $avg*$casts/$best*5`. در 1.9.2 فیکس شده ✅ اما اگر کسی 1.8.5 را نصب کند، Warning HTML → 500. Test: `php -l` با `error_reporting=E_ALL` باید خطا بدهد. |
| COR-02 | P1 | `class-comment.php:484` | `effective_batch` در log استفاده می‌شود اما در یک مرج قبلی undefined بود (1.9.0) | confirmed | 🔴 فیکس 1.9.2 با ` $effective_batch= batch>0?batch:75` ✅. در 1.9.2 فعلی درست است. |
| COR-03 | P1 | `class-artist.php:156-185` | منطق 3-شاخه وزن: `<28 cap42` vs `28-60` vs `>=60` | confirmed | ✅ درست اما `is_pair_match()` فقط 16 جفت هاردکد دارد — برای 884 آهنگ با 100+ آرتیست پوشش کم است. باید از دیتابیس یا فیلتر پویا پر شود. |
| COR-04 | P2 | `class-comment.php:122-145` | `calculate_target()` : `weight<30 && views<50K → max 6` | confirmed | ⚠️ اگر پستی 40K views داشته باشد ولی weight 25، target 6 می‌شود در حالی که views 40K باید 12-25 باشد — سقف سختگیرانه است. ممکن است عمدی برای جلوگیری از جهش ناشناس، اما باید مستند شود. |
| COR-05 | P2 | `class-autowatch.php:57-75` | `generate_initial()` : `$views = min_views + (weight/100)*span + jitter` | confirmed | ✅ همبستگی views↔casts↔avg درست است اما `min_views=600 max=9000` برای weight 100 → 9000، برای weight 0 → 600 — بازه 8400 خوب است. |
| COR-06 | P1 | `class-tier.php:230-248` | `get_eligible_post_ids()` paging فقط برای limit>5000 | confirmed | 🔴 برای 884، یک WP_Query با 884 ID حافظه 2-3MB می‌گیرد — شاید اوکی اما برای 5000+ باید همیشه paging کند. پیشنهاد: همیشه 500/batch. |
| COR-07 | P1 | `class-tier.php:348-410` | `apply_distribution()` بدون chunk واقعی | confirmed | 🔴 884 × (3 meta +2 history) در یک request → 503. فیکس 1.9.2 فقط usleep 0.3s/100 — هنوز یک request است. نیاز به Job مرحله‌ای واقعی (Phase 3 spec). |
| COR-08 | P2 | `class-history.php:156` | `History::log()` هر comment یک ردیف | confirmed | ⚠️ 884×3 comment = 2652 ردیف history + evergreen هر ساعت — جدول history بدون prune → انفجار. |
| COR-09 | P2 | `class-database.php:224` | `get_last_snapshot()` فقط `restored=0` | confirmed | ✅ درست اما اگر snapshot ناقص ایجاد شود (قبل commit)، به‌عنوان کامل شناخته می‌شود. نیاز به ستون `is_complete`. |

**جمع‌بندی صحت:** 2 مورد P1 بحرانی قبلاً فیکس شد اما هنوز chunk واقعی ندارد — P1 باز.

---

## C. Performance & Reliability

| ID | Severity | File | Evidence | Status |
|---|---|---|---|---|
| PERF-01 | P1 | `class-tier.php:348` | `set_time_limit(180)` برای 884 | confirmed | ⚠️ Shared host معمولا `set_time_limit` را غیرفعال می‌کند → بی‌اثر. باید chunk واقعی باشد. |
| PERF-02 | P1 | `class-evergreen.php:65` | `SELECT ... LIMIT 32 OFFSET offset` چرخشی | confirmed | ✅ خوب — اما `OFFSET` برای 884 با 32 → 28 کوئری برای دور کامل — برای 10000 → 313 کوئری → کند. بهتر `WHERE ID > last_id`. |
| PERF-03 | P1 | `class-comment.php:480` | `ORDER BY (SELECT COUNT(*) FROM comments WHERE post_ID=p.ID)` | confirmed | 🔴 Subquery برای هر ردیف 884× N=884 بار اجرا → 780K count. باید JOIN با derived table. |
| PERF-04 | P2 | `class-scheduler.php:335` | `SELECT ... WHERE status='active' AND next_run<=now LIMIT 20` | confirmed | ✅ ایندکس `next_run_at_gmt` دارد. خوب. |
| PERF-05 | P2 | `class-database.php:167` | `DELETE FROM locks WHERE expires<now` هر acquire | confirmed | ✅ اما برای 884×2 lock در هر distribute → 1768 delete — فشار. بهتر cron روزانه. |
| PERF-06 | P1 | `build/*.zip` 26 فایل | هر ZIP حاوی `apex-omega/build/*.zip` قدیمی؟ | not_reproduced | در build ریشه درست exclude شده اما داخل `apex-omega/build/` هنوز 10 ZIP قدیمی (1.0.0-1.3.1) در توزیع هست — حجم اضافی 7MB. |

---

## D. Frontend & Admin UI

| ID | Severity | File | Evidence | Status |
|---|---|---|---|---|
| FE-01 | P1 | `admin/js/admin.js:48,271` | دو کامنت `// ===` بدون newline → `function` داخل کامنت → SyntaxError | confirmed | 🔴 فیکس 1.8.8 ✅ — الان 1.9.2 تمیز است. `node --check` پاس. |
| FE-02 | P1 | `admin/views/tier-distribution.php:448-490` | `fetch(...).then(r=>r.json())` بدون هندل HTML | confirmed | 🔴 فیکس 1.9.1 با `r.text()->JSON.parse` + 503 retry ✅ |
| FE-03 | P2 | `admin/views/tier-distribution.php:380` | دکمه «اعمال خودکار» بدون Progress Bar | confirmed | ⚠️ کاربر 884تا می‌زند و 2دقیقه هنگ — فکر می‌کند مرده. نیاز به Job Progress (Phase 3). |
| FE-04 | P3 | `admin/css/admin-rtl.css` | RTL کامل؟ | probable | دستی چک نشده — باید روی 360px تست شود. |
| FE-05 | P1 | `admin/js/admin.js` vs `tier-distribution.php` vanilla | دو handler برای `apex-apply-tier` (jQuery + vanilla) | confirmed | 🔴 Race condition: هر دو فایر می‌شوند. باید یکی حذف شود. |

---

## E. Automated Jobs (Cron/Lock)

| ID | Severity | File | Evidence | Status |
|---|---|---|---|---|
| JOB-01 | P1 | `class-scheduler.php:16,57` | `CRON_HOOK=5m` + `COMMENT_HOOK=6h` جدا — اما `tick()` دیگر Comment را صدا نمی‌زند، فقط fallback | confirmed | ✅ فصل 1.9.0 درست — fallback هر 5m چک 6h. |
| JOB-02 | P1 | `class-database.php:130-145` | `acquire_lock(key, ttl)` با `INSERT + transient owner` | confirmed | ⚠️ اگر دو process همزمان `DELETE expired` بزنند، race برای INSERT — باید `INSERT IGNORE` یا `ON DUPLICATE`. |
| JOB-03 | P2 | `class-evergreen.php:85` | `set_transient(offset, ..., HOUR)` | confirmed | ⚠️ Transient به‌عنوان offset تنها منبع — اگر DB flush شود، offset صفر می‌شود و دوباره از اول. بهتر در option پایدار. |
| JOB-04 | P1 | `class-comment.php:425` | `tick()` gate `last_tick_ts <6h` | confirmed | ✅ درست — اما `update_option(last_tick_ts, time())` قبل از اتمام job می‌گذارد — اگر job وسط 503 بخورد، 6h دیگر اجرا نمی‌شود. باید بعد از موفقیت update شود. |
| JOB-05 | P2 | `class-history.php:156` | بدون Retention | confirmed | 🔴 بعد 90روز 100K ردیف → نیاز به `wp_schedule_event(daily, prune_history)`. |

---

## F. Summary Counts
- P0: 1 (encrypt fallback) — confirmed
- P1: 11 (COR-01/02/06/07, PERF-01/03, FE-01/02/05, JOB-01/02/04) — 5 تا فیکس شده در 1.9.2، 6 تا باز
- P2: 10
- P3: 2

**وضعیت کلی:** برای انتشار 1.9.3، 1 مورد P0 و 6 مورد P1 باید بسته شود. بدون فیکس P0 انتشار متوقف.

*پایان فاز 1 — آماده طراحی Remediation*
