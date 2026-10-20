# RELEASE 1.9.4 — 2026-10-20 (فاز 4-5 تکمیل)

## P1 بسته‌شده اضافی
- **PERF-03**: class-comment ORDER BY (SELECT COUNT(*)) → LEFT JOIN derived table (cc.c) — از N+1 به 1 کوئری
- **COR-06**: paging آستانه 5000→500، batch 1000→500 — حتی 884 به 2 batch تقسیم می‌شود
- **JOB-02**: INSERT → INSERT IGNORE برای lock race
- **JOB-03**: evergreen offset transient → option+transient dual (پس از flush صفر نمی‌شود)
- **JOB-04**: comment tick `update_option(last_tick_ts)` از ابتدای تابع به انتهای موفق منتقل شد (و empty→update)
- **JOB-05**: history prune 90روزه — `Database::prune_history(90)` + daily cron `apex_omega_prune_history_daily`
- **SEC-03**: per-post `current_user_can(edit_post)` لاگ + strict mode option `apex_omega_strict_per_post`
- **COR-09**: snapshots `is_complete` ستون + فیلتر `get_last_snapshot` + `create_snapshot is_complete=1`
- **DB migrate**: `apex-omega.php` plugins_loaded خودکار `create_tables` اگر `db_version < 1.1.0` (بدون reactivate)

## Data Model (Phase 5)
- `apex_omega_jobs`: execution_id UNIQUE, status pending/running/done/failed, total/processed, chunk_size 10-200, retry_count, payload_json LONGTEXT
- `apex_omega_snapshots`: is_complete TINYINT(1) DEFAULT 1
- `apex_omega_history`: prune via LIMIT 5000 per day
- `apex_omega_locks`: INSERT IGNORE

## Test
- 15 اولیه + 8 جدید = 23 PASS (T16-T23)

## ZIP
- build/apex-omega-1.9.4.zip (170KB~)
