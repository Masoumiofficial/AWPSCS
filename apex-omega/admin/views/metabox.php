<?php if (!defined('ABSPATH')) exit; ?>
<div id="apex-omega-metabox-wrap" class="apex-glass-wrap" style="margin:0;">
  <div class="apex-metabox-grid">
    <div>
      <h4>مقادیر فعلی (خوانده‌شده از دیتابیس)</h4>
      <table class="apex-table">
        <tr><th>بازدید (views)</th><td><?php echo esc_html(number_format_i18n($views)); ?> <small>(meta: views)</small></td></tr>
        <tr><th>تعداد رأی (casts)</th><td><?php echo esc_html($kksr['casts']); ?> <small>(meta: <?php echo esc_html($kksr['meta_casts']); ?>)</small></td></tr>
        <tr><th>مجموع امتیاز (ratings)</th><td><?php echo esc_html($kksr['ratings']); ?> <small>(meta: <?php echo esc_html($kksr['meta_ratings']); ?>)</small></td></tr>
        <tr><th>میانگین (avg) — سقف <?php echo esc_html($kksr['best']); ?></th><td><?php echo esc_html($kksr['avg']); ?> <small>(legacy: <?php echo esc_html($kksr['meta_avg']); ?>)</small></td></tr>
        <tr><th>حالت مدیریت</th><td><code><?php echo esc_html($mode); ?></code></td></tr>
      </table>
      <p><small>فرمول میانگین: ratings / casts / 5 × best (گرد ۱ رقم). منبع حقیقت: casts و ratings؛ avg برای سازگاری محاسبه/ذخیره می‌شود.</small></p>

      <div class="apex-alert warning">
        <strong>تغییر دستی فوری:</strong> مقادیر زیر را وارد کنید و «ذخیره دستی» را بزنید. اعتبارسنجی، کنترل نوسان و ثبت تاریخچه اعمال می‌شود.
      </div>

      <div class="apex-field">
        <label for="_apex_mode">حالت مدیریت</label>
        <select id="_apex_mode" name="_apex_mode">
          <option value="manual" <?php selected($mode,'manual'); ?>>دستی</option>
          <option value="scheduled" <?php selected($mode,'scheduled'); ?>>زمان‌بندی‌شده</option>
          <option value="disabled" <?php selected($mode,'disabled'); ?>>غیرفعال (عدم مدیریت)</option>
        </select>
        <small>در حالت زمان‌بندی‌شده، تغییرات مرحله‌ای اجرا می‌شوند.</small>
      </div>

      <div class="apex-field">
        <label for="apex_views">تعداد بازدید مبنا (views)</label>
        <input type="number" id="apex_views" value="<?php echo esc_attr($views); ?>" min="0" step="1" placeholder="مثلاً 100000">
        <small>عدد صحیح ≥0، حداکثر 100,000,000. اعشاری مجاز نیست.</small>
      </div>

      <div class="apex-field">
        <label for="apex_casts">تعداد رأی مبنا (casts)</label>
        <input type="number" id="apex_casts" value="<?php echo esc_attr($kksr['casts']); ?>" min="0" step="1">
        <small>شمارشی، ≥0.</small>
      </div>

      <div class="apex-field">
        <label for="apex_avg">میانگین هدف (0 تا <?php echo esc_html($kksr['best']); ?>)</label>
        <input type="number" id="apex_avg" value="<?php echo esc_attr($kksr['avg']); ?>" min="0" max="<?php echo esc_attr($kksr['best']); ?>" step="0.1">
        <small>تا سقف ستاره (<?php echo esc_html($detect['best']); ?>) مجاز است. مجموع امتیاز به‌صورت خودکار از avg × casts / best ×5 محاسبه می‌شود.</small>
      </div>

      <div class="apex-field">
        <label><input type="checkbox" id="apex_confirm_volatility"> تأیید جهش/نوسان (در صورت هشدار)</label><br>
        <label><input type="checkbox" id="apex_confirm_inconsistency"> تأیید ناسازگاری امتیاز/رأی (در صورت هشدار)</label>
      </div>

      <div class="apex-actions">
        <button type="button" id="apex-save-manual" class="button-apex">ذخیره دستی</button>
        <a href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-history')); ?>" class="button-apex secondary">تاریخچه</a>
      </div>
      <div id="apex-manual-result"></div>

      <div class="apex-card" style="margin:12px 0;background:#fef3ff;border:1px solid #e9d5ff;">
        <h4 style="margin:0 0 8px;">💬 کامنت دستی برای این پست</h4>
        <p><small>تعداد دلخواه کامنت ایرانی واقع‌گرایانه (نام تمیز بدون عزیز/جون/کوچولو) برای همین آهنگ بفرست — جدا از زمان‌بندی خودکار هر ۶ ساعت.</small></p>
        <div class="apex-field" style="display:flex;gap:8px;align-items:center;">
          <label for="apex-manual-comment-count" style="white-space:nowrap;">تعداد کامنت:</label>
          <input type="number" id="apex-manual-comment-count" min="1" max="60" value="3" style="width:80px;">
          <button type="button" id="apex-send-manual-comments" class="button-apex">ارسال کامنت 🚀</button>
        </div>
        <small>سقف هر پست 60 کامنت — اگر قبلاً پر باشد، فقط باقی‌مانده ارسال می‌شود. گفت‌وگو اگر بسته باشد خودکار باز می‌شود.</small>
        <div id="apex-manual-comment-result" style="margin-top:8px;"></div>
      </div>

      <hr class="apex-hr">

      <h4>سابقه آخرین ۵ تغییر</h4>
      <?php if (empty($last_history)): ?>
        <p>تاریخچه‌ای ثبت نشده.</p>
      <?php else: ?>
        <table class="apex-table">
          <thead><tr><th>زمان</th><th>فیلد</th><th>قبلی</th><th>جدید</th><th>منبع</th><th>بازگردانی</th></tr></thead>
          <tbody>
          <?php foreach ($last_history as $h): ?>
            <tr>
              <td><?php echo esc_html($h['created_at_gmt']); ?></td>
              <td><?php echo esc_html($h['field_type']); ?></td>
              <td><?php echo esc_html(mb_strimwidth($h['old_value'] ?? '',0,20,'…')); ?></td>
              <td><?php echo esc_html(mb_strimwidth($h['new_value'] ?? '',0,20,'…')); ?></td>
              <td><?php echo esc_html($h['source']); ?></td>
              <td><button type="button" class="button apex-rollback" data-id="<?php echo esc_attr($h['id']); ?>">بازگردانی</button></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div>
      <div id="apex-schedule-panel" style="<?php echo $mode==='scheduled'?'':'display:none'; ?>">
        <h4>برنامه زمان‌بندی‌شده (هر پست، برنامه مستقل)</h4>
        <div class="apex-alert success">
          مدیر می‌تواند بگوید: «از ۱۰۰٬۰۰۰ به ۱۱۰٬۰۰۰ برو» — سیستم بر اساس سقف هر اجرا و فاصله زمانی پیش می‌رود. کاهش بزرگ نیاز به تأیید جداگانه دارد و تغییر ناگهانی خارجی برنامه را متوقف می‌کند.
        </div>

        <div class="apex-field">
          <label for="apex_sched_field">فیلد هدف</label>
          <select id="apex_sched_field">
            <option value="views">بازدید (views)</option>
            <option value="kksr_casts">تعداد رأی (kksr_casts)</option>
            <option value="kksr_avg">میانگین (kksr_avg)</option>
          </select>
        </div>

        <div class="apex-field">
          <label for="apex_sched_target">مقدار هدف</label>
          <input type="number" id="apex_sched_target" step="0.1" placeholder="مثلاً 110000 یا 4.6">
        </div>

        <div class="apex-field">
          <label for="apex_sched_start">شروع (به وقت محلی مرورگر)</label>
          <input type="datetime-local" id="apex_sched_start" value="<?php echo esc_attr(gmdate('Y-m-d\TH:i', time()+3600)); ?>">
        </div>
        <div class="apex-field">
          <label for="apex_sched_end">پایان</label>
          <input type="datetime-local" id="apex_sched_end" value="<?php echo esc_attr(gmdate('Y-m-d\TH:i', time()+7*86400)); ?>">
        </div>

        <div class="apex-field">
          <label for="apex_sched_interval">فاصله بین اجراها (ثانیه)</label>
          <select id="apex_sched_interval">
            <option value="300">۵ دقیقه</option>
            <option value="900">۱۵ دقیقه</option>
            <option value="1800" selected>۳۰ دقیقه</option>
            <option value="3600">۱ ساعت</option>
            <option value="21600">۶ ساعت</option>
            <option value="86400">۱ روز</option>
          </select>
        </div>

        <div class="apex-field">
          <label for="apex_sched_max_step">سقف افزایش/کاهش هر اجرا (عدد مطلق)</label>
          <input type="number" id="apex_sched_max_step" placeholder="مثلاً 500" min="1" step="1">
          <small>خالی = بدون محدودیت مطلق</small>
        </div>
        <div class="apex-field">
          <label for="apex_sched_max_percent">حد درصد هر اجرا (%)</label>
          <input type="number" id="apex_sched_max_percent" placeholder="مثلاً 5" min="1" max="100" step="0.1">
        </div>
        <div class="apex-field">
          <label for="apex_sched_min">حداقل مجاز کلی</label>
          <input type="number" id="apex_sched_min" placeholder="خالی = بدون حد">
        </div>
        <div class="apex-field">
          <label for="apex_sched_max">حداکثر مجاز کلی</label>
          <input type="number" id="apex_sched_max" placeholder="خالی = بدون حد">
        </div>

        <div class="apex-field">
          <label><input type="checkbox" id="apex_req_confirm" checked> برای کاهش بزرگ تأیید جداگانه بخواه</label><br>
          <label><input type="checkbox" id="apex_confirm_large_drop"> تأیید کاهش بزرگ فعلی</label><br>
          <label><input type="checkbox" id="apex_sched_confirm_vol"> تأیید جهش فعلی</label>
        </div>

        <div class="apex-actions">
          <button type="button" id="apex-preview-schedule" class="button-apex secondary">پیش‌نمایش</button>
          <button type="button" id="apex-create-schedule" class="button-apex">ایجاد برنامه</button>
        </div>
        <div id="apex-preview-box" class="apex-preview-box" style="display:block;">پیش‌نمایش اینجا نمایش داده می‌شود.</div>
        <div id="apex-schedule-result"></div>

        <p><small>نکته WP-Cron: اجرای دقیق ساعت به بازدید وابسته است. برای دقت، Cron سرور را تنظیم کنید. پس از رسیدن به هدف، برنامه متوقف می‌شود و بدون دستور جدید ادامه نمی‌یابد. هر اجرا شناسه یکتا (execution_id) دارد و از اجرای تکراری محافظت می‌شود.</small></p>
      </div>

      <hr class="apex-hr">

      <h4>برنامه‌های این نوشته</h4>
      <?php if (empty($schedules)): ?>
        <p>برنامه‌ای ثبت نشده.</p>
      <?php else: ?>
        <table class="apex-table">
          <thead><tr><th>#</th><th>فیلد</th><th>هدف</th><th>وضعیت</th><th>بعدی</th><th>عملیات</th></tr></thead>
          <tbody>
          <?php foreach ($schedules as $sc): ?>
            <tr>
              <td><?php echo esc_html($sc['id']); ?></td>
              <td><?php echo esc_html($sc['field_type']); ?></td>
              <td><?php echo esc_html($sc['target_value']); ?></td>
              <td><span class="apex-badge <?php echo esc_attr($sc['status']); ?>"><?php echo esc_html($sc['status']); ?></span><?php if($sc['last_error']): ?><br><small><?php echo esc_html($sc['last_error']); ?></small><?php endif; ?></td>
              <td><?php echo esc_html($sc['next_run_at'] ?? '—'); ?></td>
              <td>
                <?php if($sc['status']==='active'): ?><button type="button" class="button apex-pause" data-id="<?php echo esc_attr($sc['id']); ?>">توقف</button><?php endif; ?>
                <?php if($sc['status']==='paused'): ?><button type="button" class="button apex-resume" data-id="<?php echo esc_attr($sc['id']); ?>">ادامه</button><?php endif; ?>
                <button type="button" class="button apex-cancel" data-id="<?php echo esc_attr($sc['id']); ?>">لغو</button>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <hr class="apex-hr">
      <h4>سازگاری kk Star Ratings</h4>
      <p>کلیدها: <code><?php echo esc_html($kksr['meta_casts']); ?></code> / <code><?php echo esc_html($kksr['meta_ratings']); ?></code> / <?php echo esc_html($kksr['best']); ?> ستاره</p>
      <p><small>تا زمانی که نسخه واقعی تأیید نشده، هیچ کلیدی فرض قطعی نیست؛ سیستم با نمونه‌برداری خودکار کشف می‌کند.</small></p>
    </div>
  </div>
</div>
