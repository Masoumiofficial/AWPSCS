# DATA STRUCTURE v2.0 — پیش‌نویس فنی

**نسخه:** 2.0.0-draft (2026-10-20)  
**پایه:** 1.9.4 (5 جدول) → 2.0 (8 جدول)  
**سازگاری:** DB 1.1.0 → 2.0.0 (dbDelta)

---

## تغییرات نسبت به v1

### جدول `apex_omega_snapshots` (تغییر جزئی)
```sql
-- افزوده در 1.9.4:
is_complete TINYINT(1) DEFAULT 1
-- جدید در 2.0:
snapshot_diff LONGTEXT NULL, -- فقط diff نه کل snapshot (حجم -70%)
parent_snapshot_id BIGINT NULL, -- زنجیره برای Undo چندمرحله‌ای
KEY parent_snapshot_id
```

### جدول جدید: `apex_omega_timeseries`
نقل و انتقال هر 5m:
- `post_id` FK → posts
- `metric` 4 حالت
- `granularity` برای rollup
- **بک‌فیل:** `SELECT post_id, field_type, new_value, created_at_gmt FROM apex_omega_history WHERE created_at_gmt > NOW()-90d`

**نمونه ردیف:**
```
post_id=657, metric=views, value=823450, sampled_at_gmt=2026-10-20 08:05:00, granularity=5m, source=evergreen
```

**Rollup jobs (روزانه 02:00):**
- `DELETE FROM timeseries WHERE granularity='5m' AND sampled_at_gmt < NOW()-30d`
- `INSERT INTO timeseries (granularity='1h') SELECT post_id, metric, AVG(value), DATE_FORMAT(...) GROUP BY ...`
- مشابه برای 1d پس از 90روز

### جدول جدید: `apex_omega_profiles`
```sql
CREATE TABLE wp_apex_omega_profiles (
  id BIGINT PK AI,
  term_id BIGINT UNIQUE,
  artist_name VARCHAR(191),
  weight FLOAT 0-100,
  confidence FLOAT 0-1,
  samples INT,
  curve_json LONGTEXT, -- {"growth":0.035,"decay":0.002,"seasonality":0.15}
  updated_at_gmt DATETIME,
  KEY confidence
);
-- پر شدن اولیه: SELECT term_id, AVG(weight) FROM termmeta GROUP BY term_id
```

**یادگیری:**
```php
// هر شب 03:00
foreach (profiles as $p) {
  $recent = $wpdb->get_results("SELECT value FROM timeseries WHERE post_id IN (SELECT post_id FROM term_relationships WHERE term_taxonomy_id=...) AND metric='views' ORDER BY sampled_at_gmt DESC LIMIT 7");
  $avg_growth = array_avg_growth($recent);
  $predicted = $p->curve->growth;
  $error = $avg_growth - $predicted;
  $p->weight += 0.05 * $error * $p->confidence;
  $p->confidence = min(0.95, $p->samples / 20); // 20 نمونه = اعتماد کامل
}
```

### جدول جدید: `apex_omega_queue`
**جایگزین `apex_omega_jobs` ساده:**
- `priority` 10 برای tier_distribution، 5 برای comment، 1 برای rollup
- `attempts` + `max_attempts` + `next_retry_gmt = NOW() + POW(2, attempts)*60` (exponential backoff)
- Worker: `SELECT * FROM queue WHERE status='pending' AND next_retry_gmt <= NOW() ORDER BY priority DESC, created_at_gmt ASC LIMIT 5 FOR UPDATE SKIP LOCKED`

**حالت‌ها:**
- `pending` → `running` (acquire) → `done` / `failed` → `retry` (اگر attempts < max) → `pending` دوباره

---

## ERD ساده

```
posts 1--* postmeta (views,kksr)
posts 1--* history
posts 1--* timeseries
posts 1--* snapshots (via execution_id)
terms 1--1 profiles
queue *--1 posts (via payload_json post_ids)
schedules 1--* queue (schedule_id → job_id)
```

---

## API Payload نمونه

**POST /apex/v2/simulate/preview**
```json
{
  "counts": {"tier_1": 18, "tier_2": 35, "tier_7": 220},
  "filters": {"category": "all", "artist_aware": true},
  "horizon_days": 30
}
→
{
  "execution_id": "uuid",
  "series": [
    {"day": 0, "tier_1_avg": 1123450, "tier_1_total": 20242100},
    {"day": 7, "tier_1_avg": 1156023},
    {"day": 30, "tier_1_avg": 1245000, "capped": 2}
  ],
  "confidence": 0.82
}
```

---

## Migration SQL (خلاصه)

```sql
-- 2.0.0
ALTER TABLE wp_apex_omega_snapshots ADD COLUMN is_complete TINYINT(1) DEFAULT 1, ADD COLUMN snapshot_diff LONGTEXT, ADD COLUMN parent_snapshot_id BIGINT;
CREATE TABLE wp_apex_omega_timeseries (...);
CREATE TABLE wp_apex_omega_profiles (...);
CREATE TABLE wp_apex_omega_queue (...);
UPDATE wp_options SET option_value='2.0.0' WHERE option_name='apex_omega_db_version';
```

**زمان:** ~15ثانیه برای 1K پست.

---

## تصمیم‌های باز فنی

- **TimescaleDB؟** برای 10K+ پست بهتر است، ولی در هاست اشتراکی نداریم → فعلاً MySQL با prune.
- **Partition؟** اگر `history >1M` → `PARTITION BY RANGE (YEAR(created_at_gmt))`.

*منتظر تأیید ROADMAP برای شروع کدنویسی.*
