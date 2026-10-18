<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap apex-wrap">
  <h1>تنظیمات — APEX Ω</h1>
  <form method="post">
    <?php wp_nonce_field('apex_save_settings', '_wpnonce_settings'); ?>
    <div class="apex-card">
      <h3>آستانه‌ها و کنترل نوسان</h3>
      <div class="apex-field">
        <label>آستانه تأیید کاهش بزرگ (%)</label>
        <input type="number" name="large_drop_threshold_percent" value="<?php echo esc_attr($settings['large_drop_threshold_percent']); ?>" min="1" max="100" step="0.1">
        <small>کاهش بیش از این درصد نیاز به تأیید جداگانه دارد (پیش‌فرض ۲۰٪).</small>
      </div>
      <div class="apex-field">
        <label>سقف پیش‌فرض هر اجرا برای بازدید (عدد)</label>
        <input type="number" name="max_default_step_views" value="<?php echo esc_attr($settings['max_default_step_views']); ?>" min="1" step="1">
      </div>
      <div class="apex-field">
        <label>سقف پیش‌فرض درصد هر اجرا (%)</label>
        <input type="number" name="max_default_percent" value="<?php echo esc_attr($settings['max_default_percent']); ?>" min="1" max="100" step="0.1">
      </div>
      <div class="apex-field">
        <label><input type="checkbox" name="enable_real_cron_hint" <?php checked($settings['enable_real_cron_hint']); ?>> هشدار WP-Cron را نمایش بده</label>
      </div>
    </div>

    <div class="apex-card" style="margin-top:16px; border-right-color:#d63638;">
      <h3>حذف داده هنگام حذف افزونه (Uninstall)</h3>
      <div class="apex-field">
        <label><input type="checkbox" name="remove_data_on_uninstall" <?php checked($settings['remove_data_on_uninstall']); ?>> هنگام حذف افزونه، جداول APEX و متاهای _apex_* را پاک کن</label>
        <small>هشدار: آمار اصلی (views و _kksr_*) هرگز به‌صورت خودکار حذف نمی‌شوند؛ فقط جداول تاریخچه/زمان‌بندی پاک می‌شوند و آن هم فقط اگر این گزینه فعال باشد.</small>
      </div>
    </div>

    <div class="apex-card" style="margin-top:16px; border-right-color:#7c3aed;">
      <h3>🤖 هوش مصنوعی — GapGPT (اختیاری)</h3>
      <p>برای پیشنهاد کاملاً خودکار توزیع، کلید را از <a href="https://gapgpt.app/platform-v2" target="_blank">gapgpt.app</a> بگیر. <a href="https://gapgpt.app/platform-v2/docs/quickstart" target="_blank">مستندات quickstart</a></p>
      <div class="apex-field">
        <label>API Key (Bearer)</label>
        <input type="password" name="apex_ai_api_key" value="<?php echo isset($ai_set['api_key']) && $ai_set['api_key'] ? '••••••••' : ''; ?>" placeholder="sk-... از GapGPT">
        <small>خالی بگذاری یعنی بدون تغییر. کلید در دیتابیس رمزنگاری‌شده ذخیره نمی‌شود ولی لاگ هم نمی‌شود.</small>
      </div>
      <div class="apex-field">
        <label>مدل</label>
        <input type="text" name="apex_ai_model" value="<?php echo esc_attr($ai_set['model'] ?? 'gpt-4o-mini'); ?>" placeholder="gpt-4o-mini">
      </div>
      <div class="apex-field">
        <label>Endpoint</label>
        <input type="url" name="apex_ai_endpoint" value="<?php echo esc_attr($ai_set['endpoint'] ?? 'https://api.gapgpt.app/v1/chat/completions'); ?>">
      </div>
    </div>

    <p><button name="apex_save_settings" value="1" class="button button-primary">ذخیره تنظیمات</button></p>
  </form>
</div>
