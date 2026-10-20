# نقشه‌راه APEX Ω 2.0 — نسل بعد آمار هوشمند

**تاریخ:** 2026-10-20  
**وضعیت فعلی:** 1.9.4 پایدار (23 تست PASS، تگ v1.9.4)  
**هدف 2.0:** از «ابزار توزیع دستی + رشد خطی» به «پلتفرم شبیه‌سازی ترافیک واقعی با یادگیری از داده‌های سایت»  
**کدِنیم:** `Ω Genesis`

---

## 1. چرا 2.0؟ (مسئله)

| مشکل 1.x | راه حل 2.0 |
|---------|-----------|
| Evergreen فقط `views * rate%` خطی با jitter ±30% | منحنی رشد S-curve + فصلی (weekend spike) + decay پس از 90 روز |
| توزیع تیره 7سطحه ثابت | تیره پویا — تعداد تیره‌ها از داده سایت یاد می‌گیرد (k-means روی views واقعی) |
| کامنت فقط بر اساس وزن+views | LLM محلی + تحلیل متن آهنگ (عنوان→احساس) + حافظه کامنت‌های قبلی برای تنوع |
| تاریخچه فقط LOG | تاریخچه قابل کوئری: تایم‌سری، diff گراف، پیش‌بینی |
| بدون API عمومی | REST v2 + Webhook |

**اصل 2.0:** هر عدد باید «قابل توضیح» باشد — نه رندوم.

---

## 2. معماری 2.0 (3 لایه)

```
┌──────────────┐
│  UI 2.0      │  React (داخل WP Admin) — داشبورد زنده با Chart.js v4 + Progress SSE
├──────────────┤
│  Engine 2.0  │  Scheduler v2 (queue + workers) → Simulator → Validator → History
├──────────────┤
│  Data 2.0    │  wp_apex_* (5 جدول فعلی + 3 جدول جدید) + object cache + materialized view
└──────────────┘
```

### 2.1 جداول جدید

**A) `wp_apex_omega_timeseries` (سری زمانی)**
```sql
id BIGINT PK,
post_id BIGINT,
metric ENUM views|casts|avg|comments,
value FLOAT,
sampled_at DATETIME,
sampled_at_gmt DATETIME,
source VARCHAR(32),
granularity ENUM 5m|1h|1d,
KEY (post_id, metric, sampled_at_gmt)
-- هر 5m یک نمونه evergreen+comment → برای 884 پست × 288 نقطه/روز ≈ 254K ردیف/روز
-- نگهداری 30روز دقیق (5m) + 90روز ساعتی (rollup) + 1سال روزانه → prune خودکار
```

**B) `wp_apex_omega_profiles` (پروفایل آرتیست یادگرفته)**
```sql
id BIGINT PK,
term_id BIGINT, -- آرتیست
artist_name VARCHAR(191),
weight FLOAT, -- 0-100 یادگرفته
confidence FLOAT, -- 0-1 (چقدر داده داریم)
samples INT, -- تعداد پست‌های این آرتیست
curve_json LONGTEXT, -- {growth_rate, decay, seasonality}
updated_at_gmt DATETIME,
UNIQUE term_id
```

**C) `wp_apex_omega_queue` (صف Jobها — جایگزین jobs ساده قبلی)**
```sql
id BIGINT PK,
job_id VARCHAR(64) UNIQUE,
type ENUM tier_distribution|evergreen_tick|comment_tick|timeseries_rollup|profile_learn,
status ENUM pending|running|done|failed|retry,
priority TINYINT, -- 0-10 (tier توزیع =10, rollup=1)
payload_json LONGTEXT,
attempts TINYINT,
max_attempts TINYINT DEFAULT 3,
next_retry_gmt DATETIME,
created_at_gmt DATETIME,
started_at_gmt DATETIME,
completed_at_gmt DATETIME,
KEY (status, priority, next_retry_gmt)
-- worker هر تیک 5m → SELECT ... WHERE status=pending AND next_retry_gmt <= NOW ORDER BY priority DESC LIMIT 5
```

**تغییر در جداول فعلی:**
- `schedules` → `schedule_id` به `queue.job_id` لینک می‌شود
- `snapshots.is_complete` قبلاً در 1.9.4 اضافه شد — در 2.0 `snapshot_json` به `snapshot_id` خارجی + diff-only (حجم -70%)
- `history` → پارتیشن‌بندی ماهانه (اختیاری برای سایت >100K ردیف)

### 2.2 موتور شبیه‌ساز (Simulator)

**ورودی هر تیک:**
- `post_id, cur_views, cur_casts, cur_avg, tier_id, artist_profile, age_days, seasonality_factor`

**خروجی:**
- `delta_views, delta_casts, delta_avg` با فرمول:

```
base = cur_views * (rate * tier_mult * artist_curve * season)
noise = gaussian(0, base*0.12)
season = 1 + 0.15*sin(2π*day_of_year/365) + 0.08*(is_weekend?1:-0.5) + 0.10*(is_8pm_peak?1:0)
artist_curve = profile.curve.growth * exp(-age_days/180)  # decay 6ماهه
final = clamp(base+noise, 0, tier_cap)
```

**یادگیری:** هر شب `profile_learn` job:
- برای هر آرتیست: میانگین رشد 7روز گذشته vs پیش‌بینی → `weight += lr * error`
- `lr=0.05`، حداقل 5 نمونه برای آپدیت

### 2.3 Evergreen v2 vs v1

| ویژگی | v1 (1.9.4) | v2 (2.0) |
|------|-------------|----------|
| رشد | خطی `rate% * tier_mult ±30%` | S-curve + فصل + decay |
| offset | option+transient چرخشی | queue priority + cursor پایدار در `apex_omega_queue` |
| تیره | 7 ثابت | پویا (k-means) — کاربر می‌تواند 3-10 انتخاب کند |
| کامنت همبسته؟ | نه، جدا | بله — هر `+1000 views → +1-2 comment` احتمال |
| قابل پیش‌بینی؟ | نه | بله — `preview_30d` با شبیه‌سازی |

---

## 3. API و UI 2.0

### REST v2 (پیشوند `/wp-json/apex/v2/`)

- `GET /stats/timeseries?post_id=123&metric=views&granularity=1h&range=7d` → Chart data
- `POST /simulate/preview` → ورودی counts → خروجی 30روز شبیه‌سازی بدون نوشتن
- `GET /queue` → وضعیت صف (SSE: `GET /queue/stream`)
- `POST /profiles/relearn` → دستی یادگیری مجدد آرتیست‌ها
- احراز: `apex_manage_statistics` + nonce + rate limit 30/min

### UI 2.0

- **داشبورد زنده:** 4 کارت KPI + نمودار 14روز (timeseries) + صف در حال اجرا
- **توزیع:** اسلایدرها → دکمه «پیش‌نمایش 30روز» → نمودار رشد هر تیره → سپس «شروع Job»
- **پروفایل آرتیست:** جدول با `weight | confidence | curve` قابل ویرایش + دکمه «یادگیری»
- **تایم‌ماشین:** اسلایدر تاریخ → نمایش آمار هر روز گذشته (از timeseries)

**جایگزینی:** `admin/views/*` PHP → React build در `admin/js/build/apex-v2.js` (vite). نسخه PHP قدیمی به‌عنوان fallback تا 2.1 باقی می‌ماند.

---

## 4. مهاجرت 1.9.4 → 2.0

**نصب تازه:** `Database::create_tables_v2()` 8 جدول.

**ارتقا:**
1. `db_version 1.1.0 → 2.0.0` → `create_tables_v2()` (3 جدول جدید + ستون‌ها)
2. بک‌فیل: `timeseries` از `history` 90روز گذشته پر می‌شود (`wp apex backfill --days=90`)
3. `profiles` از `term_meta weight` اولیه + 7روز یادگیری
4. `queue` کارهای ناتمام قدیمی `jobs` را import می‌کند (یک‌بار)
5. **غیر مخرب:** جداول قدیمی حذف نمی‌شوند تا 2.1 — downgrade به 1.9.4 با `DROP TABLE timeseries,profiles,queue` امکان‌پذیر

**زمان تخمینی مهاجرت برای 884 پست:** 12ثانیه (backfill) + 3ثانیه (profiles)

---

## 5. تست 2.0 (30 تست — گسترش 1.9.4)

| دسته | تعداد | مثال |
|------|-------|------|
| Unit | 8 | curve formula, seasonality, clamp |
| Integration | 8 | simulate 30d → views monotonic, queue priority |
| Perf | 6 | timeseries 254K/روز insert <2s, rollup <1s, k-means 884 <0.5s |
| Security | 4 | REST rate limit, queue injection, profile weight bounds |
| Chaos | 4 | worker kill mid-job → retry, DB flush → recover |

**معیار پذیرش:** 30/30 PASS + p95 latency <200ms برای داشبورد.

---

## 6. برنامه زمانی

| فاز | مدت | خروجی |
|-----|-----|-------|
| **2.0-alpha** | 2 هفته | جداول + simulator core + REST preview |
| **2.0-beta** | 2 هفته | UI React + queue worker + backfill |
| **2.0-rc** | 1 هفته | 30 تست + بارگذاری 10K پست |
| **2.0.0** | 2026-11-25 | تگ + ریلیز + مهاجرت‌نامه |

**وابستگی:** PHP 8.0+ (برای JIT simulator)، WP 6.0+.

---

## 7. ریسک‌ها

- **حجم timeseries:** 254K/روز → بدون prune 7.6M/ماه → راه‌حل: rollup خودکار (5m→1h پس از 30روز، 1h→1d پس از 90روز)
- **یادگیری اشتباه:** اگر داده سایت آلوده باشد (views دستی نادرست) → weight منحرف → راه‌حل: `confidence` پایین → وزن دستی اولویت دارد
- **سازگاری 1.9.4:** snapshot قدیمی `payload_json` سنگین → در 2.0 به diff-only مهاجرت تدریجی

---

## 8. تصمیم‌های باز (نیاز به تأیید تو)

1. **تیره پویا:** آیا کاربر اجازه دهد 2.0 خودش تعداد تیره‌ها را پیشنهاد دهد (k-means) یا 7 ثابت بماند؟
2. **LLM کامنت:** از GapGPT فعلی استفاده کنیم یا مدل محلی سبک (مثل `qwen2-0.5b` روی سرور) برای تنوع بیشتر؟
3. **UI:** React را همین الان شروع کنیم یا اول PHP را با Chart.js بهبود دهیم (کم‌ریسک) ؟

> پاسخ بده «1-الف، 2-ب، 3-...» تا SPEC دقیق 2.0 را قفل کنیم.

*پایان ROADMAP-2.0 — آماده برای رأی‌گیری*
