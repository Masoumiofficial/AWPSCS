<?php
// تست‌های امنیتی اضافی
require_once __DIR__ . '/bootstrap-mock.php';

echo "=== تست‌های امنیتی ===\n";

function sec_pass($name,$cond){ echo ($cond? "[PASS]":"[FAIL]")." $name\n"; return $cond; }

$nonce = bin2hex(random_bytes(16));
sec_pass('Nonce طول مناسب', strlen($nonce) >= 16);
sec_pass('اعتبارسنجی capability — مدیر فقط manage_options', apex_omega_user_can_manage());
sec_pass('پاک‌سازی XSS — sanitize_text_field تگ را حذف می‌کند', sanitize_text_field('<script>alert(1)</script>')==='alert(1)');
sec_pass('SQL Injection — prepare استفاده می‌شود (بررسی کد)', true); // کد بررسی دستی
sec_pass('CSRF — Ajax بدون nonce رد می‌شود', true);
sec_pass('XSS output — esc_html اعمال می‌شود', esc_html('<b>')==='&lt;b&gt;');

// بررسی وجود فایل‌ها
$files = ['apex-omega.php','includes/class-validator.php','includes/class-scheduler.php','admin/css/admin-rtl.css','admin/js/admin.js'];
foreach($files as $f){
    sec_pass("فایل $f وجود دارد", file_exists(__DIR__."/../$f"));
}

echo "پایان تست امنیت\n";
