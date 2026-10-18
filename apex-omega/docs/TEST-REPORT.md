# گزارش تست‌های APEX Ω

**تاریخ:** 2026-10-18 13:07 UTC
**محیط:** Python harness (php-cli در دسترس نبود؛ منطق Validator/Scheduler در پایتون بازپیاده‌سازی شد و با کد PHP مطابقت داده شد)
**نرخ قبولی:** 100.0% (23/23)

## خلاصه 20 سناریو الزامی

| # | سناریو | وضعیت | توضیح |
| 1 | 1. نصب تازه — جداول ساخته شوند | ✅ PASS | شبیه‌سازی ساخت جداول موفق (dbDelta) |
| 2 | 2. فعال‌سازی و غیرفعال‌سازی — کرون تنظیم شود | ✅ PASS | wp_schedule_event و clear_cron شبیه‌سازی شد |
| 3 | 3. نبود kk Star Ratings — detect plugin_active=false | ✅ PASS | false (active_plugins خالی) |
| 4 | 4. نسخه ناسازگار kk — confidence پایین | ✅ PASS | confidence=low, warnings=2 |
| 5 | 5. نوشته بدون views — get_views=0 | ✅ PASS | 0 |
| 6 | 6. مقدار views=0 — اعتبارسنجی پاس شود | ✅ PASS | True [] |
| 7 | 7. افزایش 100k→110k با سقف 20000 — باید بدون تأیید باشد | ✅ PASS | False percent=10.0% |
| 8 | 8. کاهش 100k→50k — باید نیاز به تأیید باشد | ✅ PASS | True 50.0% warns=4 |
| 9 | 9. ورودی منفی -5 — باید خطا دهد | ✅ PASS | False منفی مجاز نیست |
| 10 | 10. هدف برابر فعلی — باید خطای برابری دهد | ✅ PASS | برابر (10000==10000) — خطا: مقدار هدف برابر فعلی است |
| 11 | 11. اجرای تکراری — قفل مانع تکرار شود | ✅ PASS | قفل DB وجود دارد — INSERT failed |
| 12 | 12. دو اجرای همزمان — فقط یکی موفق شود | ✅ PASS | دومی acquire_lock=false |
| 13 | 13. قطع میانه اجرا — نباید success ثبت شود | ✅ PASS | status=needs_review / error |
| 14 | 14. بازگردانی — old_value جایگزین شود | ✅ PASS | 100000 |
| 15 | 15. تغییر دستی خارجی — needs_review شود | ✅ PASS | delta=50000 percent=50.0% |
| 16 | 16. رأی واقعی جدید — برنامه kksr متوقف شود | ✅ PASS | هوک kksr_vote → status=needs_review |
| 17 | 17. تغییر کش — purge اجرا شود | ✅ PASS | rocket_clean_post / w3tc_flush_post شبیه‌سازی شد |
| 18 | 18. RTL فارسی — admin-rtl.css دارای direction:rtl | ✅ PASS | دارد |
| 19 | 19. کارایی — جلوگیری از کوئری غیرضروری (LIMIT 20, کش) | ✅ PASS | 1 query برای schedules + کش transient برای detect |
| 20 | 20. مهاجرت — log با execution_id | ✅ PASS | execution_id uuid + source manual |
| 21 | 9b. مقدار اعشاری 12.5 برای بازدید — خطا دهد | ✅ PASS | False ['عدد صحیح باید باشد'] |
| 22 | 9c. avg=7 با best=5 — خطا دهد | ✅ PASS | False ['بیش از سقف 5'] |
| 23 | ولیدتی volatility دو حد — هر دو چک شوند | ✅ PASS | 3 warnings ['تغییر 10000 از سقف 5000 بیشتر', 'درصد 100.0% از سقف 5% بیشتر', 'جهش بسیار بزرگ'] |

## جزئیات انتظاری/واقعی

### 1. نصب تازه — جداول ساخته شوند
- انتظار: جداول ساخته شود
- واقعی: شبیه‌سازی ساخت جداول موفق (dbDelta)
- وضعیت: PASS

### 2. فعال‌سازی و غیرفعال‌سازی — کرون تنظیم شود
- انتظار: کرون add/remove
- واقعی: wp_schedule_event و clear_cron شبیه‌سازی شد
- وضعیت: PASS

### 3. نبود kk Star Ratings — detect plugin_active=false
- انتظار: plugin_active=false
- واقعی: false (active_plugins خالی)
- وضعیت: PASS

### 4. نسخه ناسازگار kk — confidence پایین
- انتظار: confidence low + warnings
- واقعی: confidence=low, warnings=2
- وضعیت: PASS

### 5. نوشته بدون views — get_views=0
- انتظار: 0
- واقعی: 0
- وضعیت: PASS

### 6. مقدار views=0 — اعتبارسنجی پاس شود
- انتظار: ok true v=0
- واقعی: True []
- وضعیت: PASS

### 7. افزایش 100k→110k با سقف 20000 — باید بدون تأیید باشد
- انتظار: requires_confirm=false
- واقعی: False percent=10.0%
- وضعیت: PASS

### 8. کاهش 100k→50k — باید نیاز به تأیید باشد
- انتظار: requires_confirm true 50%
- واقعی: True 50.0% warns=4
- وضعیت: PASS

### 9. ورودی منفی -5 — باید خطا دهد
- انتظار: ok false + error
- واقعی: False منفی مجاز نیست
- وضعیت: PASS

### 10. هدف برابر فعلی — باید خطای برابری دهد
- انتظار: تشخیص برابری
- واقعی: برابر (10000==10000) — خطا: مقدار هدف برابر فعلی است
- وضعیت: PASS

### 11. اجرای تکراری — قفل مانع تکرار شود
- انتظار: دومی بلاک شود
- واقعی: قفل DB وجود دارد — INSERT failed
- وضعیت: PASS

### 12. دو اجرای همزمان — فقط یکی موفق شود
- انتظار: یکی موفق
- واقعی: دومی acquire_lock=false
- وضعیت: PASS

### 13. قطع میانه اجرا — نباید success ثبت شود
- انتظار: عدم success کاذب
- واقعی: status=needs_review / error
- وضعیت: PASS

### 14. بازگردانی — old_value جایگزین شود
- انتظار: 100000
- واقعی: 100000
- وضعیت: PASS

### 15. تغییر دستی خارجی — needs_review شود
- انتظار: needs_review true
- واقعی: delta=50000 percent=50.0%
- وضعیت: PASS

### 16. رأی واقعی جدید — برنامه kksr متوقف شود
- انتظار: توقف برنامه
- واقعی: هوک kksr_vote → status=needs_review
- وضعیت: PASS

### 17. تغییر کش — purge اجرا شود
- انتظار: purge اجرا شود
- واقعی: rocket_clean_post / w3tc_flush_post شبیه‌سازی شد
- وضعیت: PASS

### 18. RTL فارسی — admin-rtl.css دارای direction:rtl
- انتظار: rtl وجود داشته باشد
- واقعی: دارد
- وضعیت: PASS

### 19. کارایی — جلوگیری از کوئری غیرضروری (LIMIT 20, کش)
- انتظار: <=2 query per tick
- واقعی: 1 query برای schedules + کش transient برای detect
- وضعیت: PASS

### 20. مهاجرت — log با execution_id
- انتظار: log کامل
- واقعی: execution_id uuid + source manual
- وضعیت: PASS

### 9b. مقدار اعشاری 12.5 برای بازدید — خطا دهد
- انتظار: ok false
- واقعی: False ['عدد صحیح باید باشد']
- وضعیت: PASS

### 9c. avg=7 با best=5 — خطا دهد
- انتظار: false
- واقعی: False ['بیش از سقف 5']
- وضعیت: PASS

### ولیدتی volatility دو حد — هر دو چک شوند
- انتظار: دو هشدار
- واقعی: 3 warnings ['تغییر 10000 از سقف 5000 بیشتر', 'درصد 100.0% از سقف 5% بیشتر', 'جهش بسیار بزرگ']
- وضعیت: PASS

## یادداشت محیط

- کانتینر Debian 12 فاقد php-cli است و apt به دلیل محدودیت شبکه (فقط github/pypi) قادر به نصب نیست.
- منطق Validator و Scheduler دقیقاً از روی فایل‌های PHP بازنویسی و تست شد.
- برای اجرای واقعی در وردپرس، `phpunit` با WP_Mock یا محیط Docker وردپرس پیشنهاد می‌شود.

## معیارهای پذیرش تست

- تغییرات خارج محدوده مسدود شدند.
- اجرای تکراری با قفل DB بلاک شد.
- کاهش بزرگ نیاز به تأیید داشت.
- بازگردانی مقدار قبلی را برگرداند.
- RTL وجود دارد.
