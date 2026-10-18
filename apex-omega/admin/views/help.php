<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap apex-wrap">
  <h1>راهنمای APEX Ω</h1>

  <div class="apex-card">
    <h3>۱) معماری داده</h3>
    <ul>
      <li><strong>آمار واقعی:</strong> آنچه کاربر/بازدید واقعی ایجاد کرده (قبل از مدیریت)</li>
      <li><strong>مقدار مبنا:</strong> عددی که مدیر دستی تعیین می‌کند</li>
      <li><strong>مقدار نمایشی:</strong> عددی که در قالب/فرانت دیده می‌شود (در حال حاضر همان مقدار مبنا؛ چون قالب فقط <code>views</code> و متای kk را می‌خواند)</li>
      <li><strong>تغییرات زمان‌بندی‌شده:</strong> گام‌های مجاز با سقف و فاصله زمانی</li>
    </ul>
    <p><strong>محدودیت تفکیک:</strong> قالب فقط یک فیلد <code>views</code> دارد؛ تفکیک کامل ممکن نیست. APEX مقدار اولیه را در <code>_apex_real_views</code> آرشیو و هر تغییر را با منبع <code>manual/scheduled</code> در تاریخچه ثبت می‌کند تا در گزارش تحلیلی با رویداد واقعی اشتباه نشود.</p>
  </div>

  <div class="apex-card">
    <h3>۲) سازگاری kk Star Ratings (کشف خودکار)</h3>
    <p>APEX هیچ کلیدی را فرض قطعی نمی‌گیرد. در فعال‌سازی و هر درخواست، نمونه‌برداری از <code>wp_postmeta</code> انجام می‌دهد:</p>
    <ol>
      <li>بررسی <code>_kksr_casts</code>، <code>_kksr_ratings</code>، <code>_kksr_avg</code> و گزینه <code>kksr_stars</code></li>
      <li>سعی در خواندن نسخه افزونه از <code>get_plugins()</code></li>
      <li>شناسایی API (<code>kk_star_ratings</code>/<code>Bhittani\StarRating\KKSR</code>)</li>
      <li>محاسبه میانگین: <code>round(ratings / casts /5 * best, 1)</code></li>
    </ol>
    <p>اگر کلیدی یافت نشد، هشدار نمایش می‌دهد و از معروف‌ترین کلید به‌عنوان پیش‌فرض امن استفاده می‌کند اما نوشتن را بدون تأیید مدیر انجام نمی‌دهد.</p>
    <p><strong>جدول kk:</strong> kk جدول اختصاصی ندارد؛ همه در <code>postmeta</code> است. جدول <code>kksr</code> در نسخه‌های قدیمی حذف شده.</p>
  </div>

  <div class="apex-card">
    <h3>۳) سیاست زمان‌بندی و کنترل نوسان</h3>
    <ul>
      <li>هر پست برنامه مستقل دارد (post_id + field_type).</li>
      <li>مدیر می‌گوید: «مقدار فعلی X، هدف Y، بازه T، فاصله I، سقف S» → سیستم گام را خطی تقسیم می‌کند: <code>step = (Y-X)/remaining_steps</code></li>
      <li>سقف مطلق و درصد جداگانه اعمال می‌شود؛ از هدف عبور نمی‌کند.</li>
      <li>کاهش بیش از ۲۰٪ نیاز به تأیید جداگانه دارد؛ جهش &gt;۵۰٪ هشدار بحرانی.</li>
      <li>پس از رسیدن به هدف، برنامه <strong>completed</strong> و متوقف می‌شود؛ بدون دستور جدید ادامه نمی‌یابد.</li>
      <li>هر اجرا <code>execution_id</code> یکتا دارد؛ اجرای تکراری با قفل DB مسدود می‌شود.</li>
      <li>اگر مقدار فعلی ناگهان خارج از محدوده یا تغییر خارجی بزرگ (&gt;۱۰٪ و &gt;۱۰۰۰) شناسایی شد، برنامه به <strong>needs_review</strong> می‌رود.</li>
      <li>رأی واقعی جدید (هوک <code>kksr_vote</code>) برنامه را متوقف می‌کند تا تداخل نشود.</li>
    </ul>
  </div>

  <div class="apex-card">
    <h3>۴) WP-Cron و Cron واقعی</h3>
    <p>WP-Cron تنها هنگام بازدید اجرا می‌شود؛ تضمین ساعت دقیق نمی‌دهد. APEX هر ۵ دقیقه تیک می‌زند اما اگر ترافیک کم باشد، تأخیر محتمل است.</p>
    <p><strong>راهکار:</strong> در هاست یک Cron واقعی بسازید:</p>
    <pre style="direction:ltr; text-align:left; background:#f6f7f7; padding:10px; border:1px solid #dcdcde;">*/5 * * * * wget -q -O - https://example.com/wp-cron.php?doing_wp_cron &gt;/dev/null 2&gt;&amp;1</pre>
    <p>یا: <code>curl -s https://example.com/wp-cron.php?doing_wp_cron &gt;/dev/null</code></p>
    <p>پس از تنظیم، در <code>wp-config.php</code> می‌توانید <code>DISABLE_WP_CRON=true</code> بگذارید تا فقط Cron سرور اجرا شود.</p>
  </div>

  <div class="apex-card">
    <h3>۵) امنیت</h3>
    <ul>
      <li>بررسی <code>apex_manage_statistics</code> (و <code>manage_options</code>) برای همه عملیات</li>
      <li>nonce در فرم و AJAX، <code>sanitize_text_field</code> و <code>esc_html</code></li>
      <li>Prepared Statements برای همه کوئری‌ها</li>
      <li>قفل DB برای جلوگیری از Race Condition</li>
      <li>تراکنش (START TRANSACTION) برای نوشتن‌ها</li>
      <li>عدم حذف آمار اصلی در غیرفعال‌سازی؛ حذف فقط با گزینه صریح در uninstall</li>
    </ul>
  </div>

  <div class="apex-card">
    <h3>۶) تهیه پشتیبان و بازیابی</h3>
    <ol>
      <li>پیش از مهاجرت، از <code>wp_postmeta</code> پشتیبان بگیرید: <code>mysqldump ... wp_postmeta</code></li>
      <li>در APEX پیش‌نمایش را ببینید، سپس تأیید کنید.</li>
      <li>پس از خطا، از تاریخچه «بازگردانی» بزنید یا دستی متا را به snapshot <code>_apex_real_views</code> برگردانید.</li>
      <li>برای بازگردانی گروهی، از SQL: <code>UPDATE wp_postmeta SET meta_value = ... WHERE meta_key='views'</code> با WHERE دقیق.</li>
    </ol>
  </div>
</div>
