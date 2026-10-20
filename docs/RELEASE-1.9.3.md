# RELEASE 1.9.3 — 2026-10-20

## خلاصه
- P0 SEC-05: عدم ذخیره plaintext API key
- P1 COR-07: کار تکه‌تکه 100/درخواست با DB jobs + progress bar + retry + idempotent
- PERF-02/03: histogram تک‌کوئری + top_posts batch
- FE-05: جلوگیری از دوبار ارسال preview/apply
- COR-06: orderby پایدار date ID
- PERF-06: export سقف 10K

## مهاجرت
- جدول جدید `apex_omega_jobs` via `Database::create_tables()` dbDelta
- نیاز به `activate()` یا فراخوانی دستی create_tables در ارتقا — افزوده شد

## Chunked Job spec (Phase 3)
- `apex_start_tier_job` → execution_id + total
- `apex_process_tier_chunk` → processed/percent/remaining + lock per job 120s + per-post lock 20s
- `apex_tier_job_status` → status
- آستانه: >100 → chunked، ≤100 → legacy single-request
- Progress bar: div#apex-job-bar width=percent%
- Retry: اگر کل chunk قفل/خطا → increment retry_count

## Files changed
- apex-omega.php 1.9.3 bump
- helpers.php SEC-05
- class-database.php jobs table
- class-tier.php job logic
- class-ajax.php 3 endpoints
- admin/views/tier-distribution.php chunked JS
- admin/js/admin.js dual guard
- class-analytics.php histogram + top_posts batch
- class-export.php limit 10K

## Known limitations
- payload_json برای 884 آهنگ ~170KB — OK؛ برای 5000 آهنگ نیاز به streaming است (future)
- spread operator در top_posts نیاز PHP 8؛ تست 7.4 pending

## Gate: PASS (P0 closed, P1 mitigated)
