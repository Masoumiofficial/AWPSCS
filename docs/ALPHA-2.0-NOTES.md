# Alpha 2.0 — یادداشت پیاده‌سازی (2026-10-20)

**شاخه:** `arena/1714e7db-awpscs`  
**وضعیت:** 1.9.4 تگ شد → شروع 2.0-alpha  
**تصمیم‌ها:** dynamic k-means / GapGPT / React (f69ed40 → 0a62658)

## کار انجام‌شده امروز (alpha scaffold)

### 1. دیتابیس 2.0.0-alpha
- `Database::VERSION = '2.0.0-alpha'`
- 3 جدول جدید (dbDelta):
  - `apex_omega_timeseries` (post_id, metric, value, sampled_at_gmt, granularity)
  - `apex_omega_profiles` (term_id UNIQUE, weight, confidence, curve_json)
  - `apex_omega_queue` (job_id UNIQUE, type, status, priority, attempts, next_retry_gmt)
- `apex-omega.php` به‌صورت خودکار `create_tables()` روی `init` prio 15 (بدون نیاز به reactivate)
- `drop_tables(true)` هم 3 جدول جدید را پاک می‌کند

### 2. موتور شبیه‌ساز
- `includes/class-simulator.php` — `Simulator::next_delta()` + `seasonality_factor()` + `artist_factor()` + `preview_30d()`
- فرمول آلفا: `rate * tierMult * season * artistNorm` + نویز ±12% + S-curve cap
- `preview_30d` بدون نوشتن: 30 روز آینده را شبیه‌سازی و فقط روزهای [0,1,3,7,14,21,30] برمی‌گرداند

### 3. REST v2
- `includes/class-rest-v2.php` — `RestV2` با NS `apex/v2`:
  - `GET /apex/v2/stats` → summary + queue_pending + profiles count
  - `POST /apex/v2/simulate/preview` → counts/filters/horizon_days → series + confidence
  - `GET /apex/v2/timeseries?post_id=&metric=&granularity=&range=7d` → 2000 نقطه
  - `GET /apex/v2/queue` → 50 job آخر
  - `POST /apex/v2/profiles/relearn` → stub
- `apex-omega.php` → `require class-rest-v2 + class-simulator`
- `class-apex-omega.php` → `RestV2::init()` در `Plugin::init()`

### 4. فرانت React (سابق)
- `admin/js/v2/` — package.json + vite.config.ts + tsconfig + kmeans.ts + Dashboard.tsx + main.tsx
- تصمیم‌ها در `docs/DECISIONS-2.0.md` قفل شد

## کار باقی‌مانده برای alpha کامل (2 هفته)

- [ ] Evergreen tick → `Simulator::next_delta()` + `Simulator::record()` (timeseries)
- [ ] Queue worker: `Scheduler::tick()` → `SELECT ... FOR UPDATE SKIP LOCKED` + exponential backoff
- [ ] Rollup job روزانه (5m → 1h پس از 30d)
- [ ] Backfill command: `wp apex backfill --days=90`
- [ ] React build و ادغام در منوی WP (enqueue `admin/js/build/apex-v2.js`)

## تست دستی
```bash
curl -X POST https://site.test/wp-json/apex/v2/simulate/preview \
  -H "X-WP-Nonce: ..." -H "Content-Type: application/json" \
  -d '{"counts":{"tier_1":18,"tier_7":220},"filters":{"artist_aware":true},"horizon_days":30}'
```

## نکته ارتقا
- از 1.9.4 به 2.0-alpha: فقط فعال/غیرفعال یا حتی رفرش پیشخوان کافی است — `db_version` خودکار آپدیت می‌شود.

---
*بعدی: پیاده‌سازی worker + evergreen v2*
