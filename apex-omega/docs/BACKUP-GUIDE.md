# راهنمای پشتیبان و بازیابی — APEX Ω

## قبل از هر تغییر گسترده

```bash
mysqldump -uUSER -p DBNAME wp_postmeta > backup_postmeta_$(date +%F).sql
mysqldump -uUSER -p DBNAME wp_apex_omega_history wp_apex_omega_schedules > backup_apex.sql
```

## بازگردانی از تاریخچه (توصیه‌شده)

1. به **آمار پیشرفته → تاریخچه** بروید.
2. ردیف مورد نظر → **بازگردانی**.
3. سیستم مقدار را به `old_value` برمی‌گرداند و یک لاگ `restore` ثبت می‌کند.

## بازگردانی دستی SQL

```sql
-- بازدید یک پست
UPDATE wp_postmeta SET meta_value='100000' WHERE post_id=123 AND meta_key='views';

-- امتیاز kk
UPDATE wp_postmeta SET meta_value='98' WHERE post_id=123 AND meta_key='_kksr_casts';
UPDATE wp_postmeta SET meta_value='450' WHERE post_id=123 AND meta_key='_kksr_ratings';
UPDATE wp_postmeta SET meta_value='4.5' WHERE post_id=123 AND meta_key='_kksr_avg';
```

## آرشیو واقعی

`_apex_real_views` اولین مقدار قبل از مدیریت را نگه می‌دارد:

```sql
SELECT post_id, meta_value FROM wp_postmeta WHERE meta_key='_apex_real_views';
```

## حذف کامل (با احتیاط)

فقط اگر در تنظیمات «حذف داده هنگام uninstall» را فعال کرده‌اید، پس از حذف افزونه، جداول APEX پاک می‌شوند. در غیر این صورت، جداول باقی می‌مانند.

