# RELEASE 2.0.0-alpha — 2026-10-20

**شاخه:** `arena/1714e7db-awpscs` — تگ بعدی `v2.0.0-alpha` (هنوز تگ نشده، فقط برنچ)

## چه شد؟ هر کاری که بهتر بود:

1. **DB 2.0.0-alpha** — 3 جدول جدید: `timeseries`, `profiles`, `queue` (dbDelta، auto-migrate)
2. **Simulator S-curve** — `class-simulator.php`: seasonality + artist decay + noise ±12% + cap 10%
3. **Evergreen v2** — `class-evergreen.php` دیگر خطی نیست؛ هر تیک `Simulator::next_delta()` + `Simulator::record()` در timeseries
4. **QueueWorker** — `class-queue-worker.php`: priority 1-10، retry نمایی `pow(2,attempts)*60`، `FOR UPDATE SKIP LOCKED`، fallback در Scheduler
5. **Rollup روزانه** — `apex_omega_rollup_daily` ساعت 02:00 → enqueue `timeseries_rollup` → `DELETE 5m <30d LIMIT 1000`
6. **REST v2** — 5 endpoint: `/stats`, `/simulate/preview`, `/timeseries`, `/queue`, `/profiles/relearn`
7. **UI 2.0 scaffold** — `admin/js/v2` با k-means dynamic (انتخاب تو: dynamic/GapGPT/React)

## نصب/ارتقا
- از 1.9.4: فقط پوش جدید را بریز و یک بار پیشخوان را باز کن — `db_version` خودکار می‌پرد به 2.0.0-alpha
- نیاز: PHP 7.4+ (فعلی)، برای JIT 8.0 بهتر

## تست سریع
```bash
curl -X POST /wp-json/apex/v2/simulate/preview -d '{"counts":{"tier_1":10},"filters":{"artist_aware":true}}'
curl /wp-json/apex/v2/stats
curl /wp-json/apex/v2/timeseries?post_id=657&metric=views&range=7d
```

## بعدی (alpha کامل تا 2026-11-03)
- [ ] Evergreen profile از DB واقعی (نه فقط وزن)
- [ ] CommentGuardian → queue
- [ ] WP-CLI `wp apex backfill`
- [ ] React build → enqueue در admin

---
*کامیت: 9f5e9bb — آماده برای تست میدانی*
