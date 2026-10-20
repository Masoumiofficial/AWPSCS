# SOURCE BASELINE — APEX Ω 1.9.3 Remediation
**تاریخ:** 2026-10-20 (Asia/Tehran)
**Branch در حال بررسی:** `arena/1714e7db-awpscs` (active)
**HEAD:** `36f3d8f` — `fix: 1.9.2 — 503 Service Unavailable: chunk 100 + 0.3s pause`

## 1. Git Truth
- **HEAD commit:** `36f3d8f` (arena)
- **Main branch HEAD:** `1b352e0` (merge PR #2) — نسخه هدر `1.8.5` (عقب‌مانده)
- **Arena تاریخچه:** 1b352e0 → 10e9904 (1.8.6) → 32943e4 (1.8.7) → 1cb4a9e (1.8.8) → 761d713 → 319bc25 → 52e0948 (1.8.9) → e66efd9 (1.8.10) → 8f30d17 (1.9.0) → b6a99c7 (1.9.1) → 36f3d8f (1.9.2)
- **Tags:** هیچ تگی وجود ندارد (`git tag` خالی)
- **Releases:** `gh release list` → هیچ Release منتشر شده‌ای نیست (فقط build/*.zip محلی)
- **Uncommitted:** قبل از این فایل، هیچ فایل dirty نبود (پس از reset --hard تمیز)

**نتیجه:** نسخه ادعایی 1.9.2 *فقط* در arena وجود دارد و در origin/main نیست. main همچنان 1.8.5 است. انتشار رسمی انجام نشده.

## 2. Version Header Comparison
| منبع | Version |
|------|---------|
| `apex-omega/apex-omega.php` (arena HEAD 36f3d8f) | **1.9.2** |
| `apex-omega/readme.txt` Stable tag (arena) | **1.9.2** |
| `apex-omega/apex-omega.php` (origin/main) | **1.8.5** |
| `apex-omega/README.md` (root) | **1.0.0** (منسوخ، به‌روز نشده) |
| `build/` ZIPها | 1.2.2, 1.4.0-1.9.2 (26 فایل) — 1.9.2.zip = 165KB (تأیید شد) |
| `package.json` / `composer.json` | وجود ندارد |

**اختلاف بحرانی:** main و arena واگرایی 10 کامیت دارند. README.md ریشه کاملاً قدیمی است.

## 3. ساختار واقعی
```
apex-omega/ (7307 خط کل)
├── apex-omega.php (1.9.2)
├── includes/ (17 فایل) — هسته منطق
├── admin/ (views 7 + js 1 + css 1)
├── public/
├── build/ (داخل apex-omega/build/ 10 ZIP قدیمی 1.0.0-1.3.1)
├── docs/ (ANALYSIS.md, BACKUP-GUIDE.md, DATA-STRUCTURE.md, QA-CHECKLIST.md, SCHEDULER-POLICY.md, TEST-REPORT.md)
├── tests/ (bootstrap-mock.php, run.php, run_with_python.py, test-qasecurity.php, results.json)
└── languages/
build/ (ریشه) 26 ZIP 1.2.2-1.9.2
```

## 4. مدل داده (از code)
از `includes/class-database.php::create_tables()` (VERSION 1.0.0):
1. `{prefix}apex_omega_history` — id, post_id, field_type, old/new/delta, source, user_id, execution_id, schedule_id, ip, ua, meta_json, created_at(_gmt) — 6 ایندکس
2. `{prefix}apex_omega_schedules` — id, post_id, status, mode, field_type, current/target, min/max, max_step/percent, interval, start/end, last/next, last_value, confirm flags, execution_id, fail_count, last_error, created_by, config_json — 5 ایندکس
3. `{prefix}apex_omega_locks` — lock_key PK, locked_at(_gmt), expires_at(_gmt), owner — 1 ایندکس
4. `{prefix}apex_omega_snapshots` — id, execution_id, user_id, counts/filters/snapshot_json, total_requested/applied, created_at, restored — 2 ایندکس

**جدول پنجم ضمنی:** بدون جدول — `postmeta` برای views, _kksr_*, _apex_* , comment

**Migration:** `Activator::activate()` → `create_tables()` + `Scheduler::ensure_cron()` + ثبت capability.

## 5. تست‌های موجود و محیط
| تست | وضعیت |
|-----|--------|
| `tests/run.php` | وجود دارد — mock WP (bootstrap-mock.php) + PHPUnit-like manual |
| `tests/test-qasecurity.php` | QA Security — چک capability/nonce |
| `tests/results.json` | قدیمی (آخرین run ثبت نشده) |
| `php -v` | **وجود ندارد** در این sandbox (فقط node v22.22.3) |
| `node -v` | 22.22.3 — `node --check admin.js` قابل اجرا |
| `composer` | وجود ندارد |
| DB واقعی (MySQL) | در دسترس نیست — فقط mock Transient/Option |
| WordPress واقعی | در دسترس نیست |

**محدودیت محیط:** بدون PHP CLI و DB، تست‌های Integration واقعی باید شبیه‌سازی شوند یا با python mock اجرا شوند. هیچ تستی “موفق جعلی” گزارش نخواهد شد.

## 6. بررسی وجود 1.9.2 در Git
- `git show origin/arena/1714e7db-awpscs:apex-omega/apex-omega.php | grep Version` → **1.9.2** ✅ وجود دارد در arena remote
- `git show origin/main:apex-omega/apex-omega.php | grep Version` → **1.8.5** → main عقب است
- `build/apex-omega-1.9.2.zip` محلی → 165KB، محتوای آن `apex-omega/apex-omega.php` با Version 1.9.2 ✅
- هیچ Tag/Release رسمی برای 1.9.2 وجود ندارد → انتشار ناقص (فقط branch)

## 7. فایل‌های مرجع برای Audit
- Header: `apex-omega/apex-omega.php`
- Changelog: `apex-omega/readme.txt` (بخش == Changelog ==)
- ZIP مرجع: `build/apex-omega-1.9.2.zip` (از همین commit ساخته شده)
- DB: `includes/class-database.php`, `class-activator.php`
- منطق سنگین: `class-tier.php` (generate_values, apply_distribution), `class-comment.php` (calculate_target, tick), `class-artist.php` (get_weight_for_post)
- امنیت: `class-ajax.php` (30 اکشن), `class-rest.php`, `admin/views/*.php`
- کارایی: `class-scheduler.php`, `class-evergreen.php`, `class-autowatch.php`
- Frontend: `admin/js/admin.js`, `admin/views/tier-distribution.php`

## 8. تصمیم
نسخه مبنا برای 1.9.3 **commit 36f3d8f (arena) با Version 1.9.2** تثبیت شد. تمام Audit و Remediation از این commit شروع می‌شود. واگرایی با main باید پس از Release Gate با PR حل شود.

*پایان فاز 0 — آماده ورود به AUDIT*
