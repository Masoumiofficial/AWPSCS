# مستند ساختار داده — APEX Ω

## جداول وردپرس موجود

### wp_postmeta
- `views` INT — بازدید نمایشی (قالب)
- `_kksr_casts` INT — تعداد رأی
- `_kksr_ratings` FLOAT — مجموع امتیاز (بر مبنای 5)
- `_kksr_avg` FLOAT — میانگین legacy (برای سازگاری)
- `_apex_real_views` INT — snapshot اولیه
- `_apex_managed_views` INT — آخرین مدیریت
- `_apex_mode` ENUM manual|scheduled|disabled
- `_apex_schedule_id` BIGINT — آخرین برنامه

## جداول جدید APEX Ω

### wp_apex_omega_history
```sql
id BIGINT UNSIGNED PK AI,
post_id BIGINT UNSIGNED,
field_type VARCHAR(32) -- views | kksr_casts | kksr_ratings | kksr_avg | kksr_combined
old_value LONGTEXT,
new_value LONGTEXT,
delta LONGTEXT,
source VARCHAR(32) -- manual|scheduled|bulk_manual|restore|real_vote|external_change
user_id BIGINT UNSIGNED,
execution_id VARCHAR(64), -- uuid
schedule_id BIGINT UNSIGNED,
ip_address VARCHAR(45),
meta_json LONGTEXT,
created_at DATETIME,
created_at_gmt DATETIME,
KEY post_id, field_type, source, schedule_id
```

### wp_apex_omega_schedules
```sql
id BIGINT PK,
post_id BIGINT,
status ENUM active|paused|completed|needs_review|cancelled|error,
mode VARCHAR(32),
field_type VARCHAR(32),
current_value LONGTEXT,
target_value LONGTEXT,
min_allowed LONGTEXT,
max_allowed LONGTEXT,
max_step LONGTEXT,
max_percent_step DECIMAL(5,2),
interval_seconds INT,
start_at DATETIME,
start_at_gmt DATETIME,
end_at DATETIME,
end_at_gmt DATETIME,
last_run_at DATETIME,
next_run_at DATETIME,
last_value LONGTEXT,
require_confirm_large_drop TINYINT(1),
large_drop_threshold_percent DECIMAL(5,2) DEFAULT 20.00,
execution_id VARCHAR(64),
fail_count INT UNSIGNED,
last_error LONGTEXT,
created_by BIGINT,
config_json LONGTEXT -- {kksr_avg_target, kksr_casts_target, warnings_initial}
```

### wp_apex_omega_locks
قفل‌های کوتاه‌مدت 120 ثانیه‌ای برای جلوگیری از race.

## دیاگرام جریان

```
مدیر → متاباکس/REST → Validator → check_volatility → DB lock → Adapter_*::set → History::log → purge cache → Scheduler::tick (هر 5m) → قفل → calculate_next_step → Adapter_*::set → History → advance/mark_completed
```

## نمونه داده kksr

- پست با 100 رأی، avg 4.5، best 5 → ratings = 4.5*100/5*5 = 450
- اگر avg هدف 4.7 شود و casts 100 ثابت → ratings جدید = 4.7*100/5*5 = 470

## سازگاری

- بدون جدول kk → فقط postmeta.
- اگر kksr_stars=10 و best=10 → فرمول همان است اما best عوض می‌شود.
- گزینه‌های wp_options: kksr_stars, kksr_settings (serial), kksr_options.

