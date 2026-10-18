<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap apex-wrap">
  <h1>APEX Ω — داشبورد مدیریت آمار</h1>
  <p>مدیریت دقیق، زمان‌بندی‌شده و امن بازدید و امتیاز — <strong>اتحاد وردپرس | EtehadWP</strong> — نسخه <?php echo esc_html(APEX_OMEGA_VERSION); ?></p>

  <?php foreach ($warnings as $wa): ?>
    <div class="apex-alert <?php echo esc_attr($wa['type']==='error'?'error':'warning'); ?>">
      <strong><?php echo $wa['type']==='error'?'خطا':'هشدار'; ?>:</strong> <?php echo esc_html($wa['msg']); ?>
    </div>
  <?php endforeach; ?>

  <div class="apex-cards">
    <div class="apex-card apx-info">
      <h3>نوشته‌های منتشرشده</h3>
      <div class="big"><?php echo esc_html(number_format_i18n($counts['total_posts'])); ?></div>
      <small>کل پست‌ها با post_type=post</small>
    </div>
    <div class="apex-card apx-info">
      <h3>نوشته‌های تحت مدیریت</h3>
      <div class="big"><?php echo esc_html(number_format_i18n($counts['managed'])); ?></div>
      <small>دارای متای _apex_mode</small>
    </div>
    <div class="apex-card apx-success">
      <h3>برنامه‌های فعال</h3>
      <div class="big"><?php echo esc_html(number_format_i18n($counts['active'])); ?></div>
      <small>در حال اجرا</small>
    </div>
    <div class="apex-card apx-warning">
      <h3>نیازمند بررسی</h3>
      <div class="big"><?php echo esc_html(number_format_i18n($counts['needs'])); ?></div>
      <small>متوقف‌شده خودکار</small>
    </div>
    <div class="apex-card">
      <h3>تغییرات امروز</h3>
      <div class="big"><?php echo esc_html(number_format_i18n($counts['today_changes'])); ?></div>
      <small>از <?php echo esc_html(number_format_i18n($counts['total_changes'])); ?> کل</small>
    </div>
    <div class="apex-card <?php echo $counts['failed']?'apx-error':'apx-info'; ?>">
      <h3>خطاهای زمان‌بندی</h3>
      <div class="big"><?php echo esc_html(number_format_i18n($counts['failed'])); ?></div>
      <small>وضعیت error</small>
    </div>
  </div>

  <hr class="apex-hr">

  <div class="apex-metabox-grid">
    <div class="apex-card">
      <h3>وضعیت سازگاری kk Star Ratings</h3>
      <table class="apex-table">
        <tr><th>افزونه فعال</th><td><?php echo $compat_kksr['detect']['plugin_active'] ? 'بله' : 'خیر'; ?></td></tr>
        <tr><th>نسخه</th><td><?php echo esc_html($compat_kksr['detect']['plugin_version'] ?? '—'); ?></td></tr>
        <tr><th>ذخیره‌سازی</th><td><?php echo esc_html($compat_kksr['detect']['storage']); ?></td></tr>
        <tr><th>کلید casts</th><td><code><?php echo esc_html($compat_kksr['detect']['meta_casts']); ?></code></td></tr>
        <tr><th>کلید ratings</th><td><code><?php echo esc_html($compat_kksr['detect']['meta_ratings']); ?></code></td></tr>
        <tr><th>کلید avg (legacy)</th><td><code><?php echo esc_html($compat_kksr['detect']['meta_avg']); ?></code></td></tr>
        <tr><th>تعداد ستاره (best)</th><td><?php echo esc_html($compat_kksr['detect']['best']); ?></td></tr>
        <tr><th>اطمینان</th><td><span class="apex-badge <?php echo $compat_kksr['detect']['confidence']==='high'?'active':'paused'; ?>"><?php echo esc_html($compat_kksr['detect']['confidence']); ?></span></td></tr>
        <tr><th>وضعیت کلی</th><td><span class="apex-badge <?php echo esc_attr($compat_kksr['status']==='ok'?'active':($compat_kksr['status']==='warning'?'paused':'needs_review')); ?>"><?php echo esc_html($compat_kksr['status']); ?></span></td></tr>
      </table>
      <?php if (!empty($compat_kksr['messages'])): ?>
        <ul style="margin:8px 0; padding-right:18px;">
          <?php foreach ($compat_kksr['messages'] as $m): ?><li><?php echo esc_html($m); ?></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <p><small>API شناسایی‌شده: <?php echo esc_html(implode(', ', $compat_kksr['detect']['api_functions']) ?: '—'); ?></small></p>
      <p><a href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-help')); ?>">مستندات سازگاری →</a></p>
    </div>

    <div class="apex-card">
      <h3>فیلد بازدید (views)</h3>
      <table class="apex-table">
        <tr><th>وجود فیلد</th><td><?php echo $compat_views['exists']?'بله':'خیر'; ?></td></tr>
        <tr><th>تعداد پست با views</th><td><?php echo esc_html(number_format_i18n($compat_views['total_posts_with_views'])); ?></td></tr>
        <tr><th>نمونه مقدار</th><td><?php echo esc_html($compat_views['sample_value'] ?? '—'); ?></td></tr>
        <tr><th>مقادیر غیرعددی</th><td><?php echo $compat_views['has_non_numeric']?'دارد (نیاز به پاکسازی)':'ندارد'; ?></td></tr>
        <tr><th>کلید متا</th><td><code><?php echo esc_html($compat_views['meta_key']); ?></code></td></tr>
      </table>
      <p><small><?php echo esc_html($compat_views['limitation']); ?></small></p>
      <div class="apex-alert warning">
        <strong>سیاست تفکیک:</strong> چون قالب فقط <code>views</code> را می‌خواند، مقدار نمایشی مستقیماً در همین فیلد نوشته می‌شود. بازدید واقعی اولیه در <code>_apex_real_views</code> آرشیو و تمام تغییرات در جدول تاریخچه با منبع <code>manual/scheduled/restore</code> ثبت می‌شوند تا گزارش تحلیلی دچار اشتباه نشود.
      </div>
    </div>
  </div>

  <hr class="apex-hr">

  <div class="apex-metabox-grid">
    <div class="apex-card">
      <h3>آخرین تغییرات (۲۰ مورد)</h3>
      <?php if (empty($recent)): ?>
        <p>هنوز تغییری ثبت نشده.</p>
      <?php else: ?>
        <table class="apex-table">
          <thead><tr><th>زمان (GMT)</th><th>نوشته</th><th>فیلد</th><th>قبلی → جدید</th><th>منبع</th><th>عملیات</th></tr></thead>
          <tbody>
          <?php foreach ($recent as $r): ?>
            <tr>
              <td><?php echo esc_html($r['created_at_gmt']); ?></td>
              <td><a href="<?php echo esc_url(get_edit_post_link((int)$r['post_id'])); ?>"><?php echo esc_html($r['post_title'] ?? $r['post_id']); ?></a></td>
              <td><code><?php echo esc_html($r['field_type']); ?></code></td>
              <td><?php echo esc_html(mb_strimwidth($r['old_value']??'',0,30,'…')); ?> → <?php echo esc_html(mb_strimwidth($r['new_value']??'',0,30,'…')); ?></td>
              <td><?php echo esc_html($r['source']); ?></td>
              <td><button type="button" class="button apex-rollback" data-id="<?php echo esc_attr($r['id']); ?>">بازگردانی</button></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
      <p><a href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-history')); ?>">مشاهده همه →</a></p>
    </div>

    <div class="apex-card">
      <h3>برنامه‌های فعال (۱۰ مورد)</h3>
      <?php if (empty($active_schedules)): ?>
        <p>برنامه فعالی وجود ندارد.</p>
      <?php else: ?>
        <table class="apex-table">
          <thead><tr><th>پست</th><th>فیلد</th><th>مقصد</th><th>اجرا بعدی (محلی)</th><th>وضعیت</th><th>عملیات</th></tr></thead>
          <tbody>
          <?php foreach ($active_schedules as $s): ?>
            <tr>
              <td><a href="<?php echo esc_url(get_edit_post_link((int)$s['post_id'])); ?>"><?php echo esc_html($s['post_title'] ?? $s['post_id']); ?></a></td>
              <td><?php echo esc_html($s['field_type']); ?></td>
              <td><?php echo esc_html($s['target_value']); ?></td>
              <td><?php echo esc_html($s['next_run_at'] ?? '—'); ?></td>
              <td><span class="apex-badge <?php echo esc_attr($s['status']); ?>"><?php echo esc_html($s['status']); ?></span></td>
              <td>
                <button type="button" class="button apex-pause" data-id="<?php echo esc_attr($s['id']); ?>">توقف</button>
                <button type="button" class="button apex-cancel" data-id="<?php echo esc_attr($s['id']); ?>">لغو</button>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
      <?php
        $next_cron = wp_next_scheduled(\ApexOmega\Core\Scheduler::CRON_HOOK);
        echo '<p><small>اجرای بعدی WP-Cron: ' . ($next_cron ? esc_html(get_date_from_gmt(gmdate('Y-m-d H:i:s', $next_cron), 'Y-m-d H:i:s')) : '⚠️ تنظیم نشده') . ' — <a href="#" id="apex-heal-cron" class="button button-small">ترمیم کرون</a> <span id="apex-heal-msg"></span></small></p>';
        $heal_nonce = wp_create_nonce('apex-omega-nonce');
        echo '<script>document.addEventListener("DOMContentLoaded",function(){var b=document.getElementById("apex-heal-cron");if(!b)return;b.addEventListener("click",function(e){e.preventDefault();b.disabled=true;b.textContent="در حال ترمیم…";fetch(ajaxurl,{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:"action=apex_heal_cron&nonce="+"' . $heal_nonce . '}).then(r=>r.json()).then(j=>{var m=document.getElementById("apex-heal-msg");if(j.success){m.textContent=" ✅ ترمیم شد — "+j.data.human;b.textContent="ترمیم شد";setTimeout(function(){location.reload();},1200);}else{m.textContent=" ❌ "+(j.data&&j.data.msg?j.data.msg:"خطا");b.disabled=false;b.textContent="ترمیم کرون";}}).catch(function(){document.getElementById("apex-heal-msg").textContent=" خطای شبکه";b.disabled=false;b.textContent="ترمیم کرون";});});});</script>';
      ?>
      <div class="apex-alert warning">
        <strong>WP-Cron:</strong> تضمین اجرای دقیق در ساعت مشخص را نمی‌دهد؛ وابسته به بازدید سایت است. برای دقت ثانیه‌ای، یک Cron واقعی هر ۵ دقیقه به <code>wp-cron.php?doing_wp_cron</code> تنظیم کنید. <a href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-help')); ?>">راهنما</a>
      </div>
    </div>
  </div>
</div>
