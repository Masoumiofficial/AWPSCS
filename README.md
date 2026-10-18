# AWPSCS — APEX Ω

این مخزن حاوی افزونه **APEX Ω — Advanced WordPress Statistics Control System** است.

- مسیر افزونه: `apex-omega/`
- نسخه: 1.0.0
- نویسنده: اتحاد وردپرس | EtehadWP
- مستندات کامل: `apex-omega/README.md`
- ZIP نصب‌پذیر: `apex-omega/build/apex-omega-1.0.0.zip`

## فهرست فایل‌های ایجادشده

```
apex-omega/
├── apex-omega.php
├── uninstall.php
├── readme.txt
├── README.md
├── includes/
│   ├── helpers.php
│   ├── class-database.php (3 جدول: history/schedules/locks)
│   ├── class-validator.php
│   ├── class-adapter-kksr.php (کشف خودکار)
│   ├── class-adapter-views.php
│   ├── class-history.php
│   ├── class-scheduler.php
│   ├── class-activator.php
│   ├── class-deactivator.php
│   ├── class-apex-omega.php
│   ├── class-rest.php
│   └── class-ajax.php
├── admin/
│   ├── class-admin.php (داشبورد)
│   ├── class-metabox.php
│   ├── class-bulk.php
│   ├── css/admin-rtl.css
│   ├── js/admin.js
│   └── views/{dashboard,metabox,bulk,history-table,settings,help}.php
├── public/class-public.php
├── languages/apex-omega-fa_IR.po
├── docs/{ANALYSIS,DATA-STRUCTURE,SCHEDULER-POLICY,TEST-REPORT,BACKUP-GUIDE,QA-CHECKLIST}.md
├── tests/{run.php,run_with_python.py,results.json}
└── build/apex-omega-1.0.0.zip
```

## تست

```bash
python3 apex-omega/tests/run_with_python.py
# یا در محیط PHP
php apex-omega/tests/run.php
```

نرخ قبولی: 100% (23/23)
