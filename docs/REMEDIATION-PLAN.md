# REMEDIATION PLAN — 1.9.3
**مبنا:** AUDIT-REPORT.md (36f3d8f) | **هدف:** بستن P0+P1 قبل Release Gate

## جدول اولویت (P0→P3)

| finding_id | severity | file | line | evidence | root_cause | proposed_fix | regression_risk | test |
|---|---|---|---|---|---|---|---|---|
| SEC-05 | P0 | helpers.php:42 | fallback `b64:` ذخیره plaintext | عدم وجود openssl در برخی هاست‌ها | به‌جای fallback ناامن، خطا بده و ذخیره نکن + پیام به کاربر | ذخیره امن کلید از بین می‌رود (ریسک کم) | test: ذخیره با openssl غیرفعال → error |
| COR-07 | P1 | class-tier.php:348 | 884 در یک request → 503 | عدم وجود Job مرحله‌ای | Phase3: Job جدول `apex_omega_jobs` + endpoint chunked (100/batch) + progress | نیاز Migration جدول جدید → test downgrade | test: 884 با 100/batch → 9 request موفق، 503=0 |
| PERF-03 | P1 | class-comment.php:480 | `ORDER BY (SELECT COUNT(*) FROM comments)` N+1 | Subquery per row | JOIN با derived: `LEFT JOIN (SELECT post_ID, COUNT(*) c FROM comments GROUP BY post_ID) cc ON cc.post_ID=p.ID` | تغییر پلان کوئری → EXPLAIN قبل/بعد | test: 884 explain <0.2s |
| JOB-04 | P1 | class-comment.php:428 | `update_option(last_tick_ts) قبل از اتمام` | اگر 503 وسط کار، 6h قفل می‌ماند | انتقال update به بعد از `if(done_posts>0)` + transient برای in-progress | اگر کرش کند، دوباره زود اجرا می‌شود (قابل قبول) | test: kill mid-tick → next tick <6h اجرا شود |
| FE-05 | P1 | admin/js/admin.js vs tier-distribution.php | دو handler برای apex-apply-tier | باقی‌مانده از بازنویسی vanilla | حذف handler قدیمی در admin.js (خط 260-270) | ممکن است پیش‌نمایش قدیمی بشکند → test هر دو | test: کلیک یک‌بار → یک request |
| JOB-02 | P1 | class-database.php:170 | `INSERT locks` بدون IGNORE | race دو process همزمان | `INSERT IGNORE` + `SELECT ROW_COUNT()` | ممکن است lock کاذب بگیرد → test همزمانی | test: 10 parallel acquire → فقط 1 موفق |
| COR-06 | P1 | class-tier.php:230 | paging فقط برای >5000 | WP_Query 884 یک‌جا | همیشه paging 500/batch حتی برای 884 | کندتر اما ایمن → test 5000 | test: 5000 IDs <1s |
| SEC-03 | P1 | class-ajax.php:preview/apply | فقط can_manage، بدون per-post | فرض اینکه manage = مالک همه پست‌ها | افزودن `if(!can_edit_post) log warning` + option strict mode | ممکن است ادمین واقعی block شود → default warn only | test: editor با manage ولی نه مالک → log |
| PERF-01 | P1 | class-tier.php:348 | set_time_limit بی‌اثر در shared | host غیرفعال | حذف اتکا به set_time_limit، chunk واقعی | هیچ | test: روی host با disabled → still works |
| B1 | P1 | class-comment.php:480 | پوشش 34% | starvation قدیمی | فیکس 1.9.0 قبلا انجام شد — نیاز به backfill یک‌باره | حجم کامنت جدید → test 584 insert |
| P0 باید قبل هر چیز بسته شود، سپس P1 به ترتیب: COR-07 (503) → PERF-03 → JOB-04 → FE-05 → بقیه |

## وضعیت هر یافته
- SEC-05: confirmed → fix فوری
- COR-07: confirmed → نیاز به جدول جدید `apex_omega_jobs` (Migration + Rollback)
- بقیه: probable/confirmed → در همین 1.9.3 فیکس

## Migration & Rollback
- جدول جدید `apex_omega_jobs` (id, job_id, type, status, total, done, params_json, created_at, updated_at) — `dbDelta` در `Activator::activate` نسخه 1.1.0
- Rollback: `DROP TABLE IF EXISTS jobs` + delete option `apex_omega_chunked_jobs` — ایمن چون داده موقت
- Downgrade: اگر کاربر به 1.9.2 برگردد، جدول jobs باقی می‌ماند (no harm) — مستند شود

## تست برای هر fix
- Unit: Validator, generate_values, calculate_target مرزها
- Integration: preview 884 + apply 100×9 با DB mock
- Security: nonce invalid → 403
- Performance: EXPLAIN + time <2s برای 884
- Recovery: kill mid-batch → resume از offset
