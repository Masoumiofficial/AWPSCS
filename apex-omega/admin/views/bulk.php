<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap apex-wrap">
  <h1>ویرایش گروهی — APEX Ω</h1>
  <?php
    $ids_raw = $_GET['apex_bulk_ids'] ?? '';
    $ids_arr = $ids_raw ? array_filter(array_map('intval', explode(',', $ids_raw))) : [];
    if (isset($_POST['apex_bulk_ids']) && $_POST['apex_bulk_ids']) {
        $ids_arr = array_filter(array_map('intval', explode(',', $_POST['apex_bulk_ids'])));
    }
    // نمایش لیست پست‌ها اگر انتخاب شدند
    $posts = [];
    if ($ids_arr) {
        $posts = get_posts(['post__in'=>$ids_arr, 'post_type'=>'any', 'numberposts'=>-1]);
    }
  ?>

  <div class="apex-card">
    <h3>۱) انتخاب نوشته‌ها</h3>
    <p>می‌توانید از <a href="<?php echo esc_url(admin_url('edit.php')); ?>">فهرست نوشته‌ها</a> چند مورد را انتخاب و «APEX Ω — ویرایش گروهی» را اجرا کنید، یا شناسه‌ها را دستی وارد کنید.</p>
    <form method="get" action="">
      <input type="hidden" name="page" value="apex-omega-bulk">
      <div class="apex-field">
        <label>شناسه نوشته‌ها (با کاما جدا کنید)</label>
        <input type="text" name="apex_bulk_ids" value="<?php echo esc_attr(implode(',', $ids_arr)); ?>" placeholder="مثلاً 12,45,78">
      </div>
      <button type="submit" class="button">بارگذاری</button>
    </form>
    <?php if ($posts): ?>
      <table class="apex-table" style="margin-top:12px;">
        <thead><tr><th>ID</th><th>عنوان</th><th>نوع</th><th>بازدید</th><th>امتیاز</th></tr></thead>
        <tbody>
        <?php foreach ($posts as $p):
            $v = get_post_meta($p->ID,'views',true);
            $casts = get_post_meta($p->ID,'_kksr_casts',true);
            $best = (int) get_option('kksr_stars',5);
            $ratings = get_post_meta($p->ID,'_kksr_ratings',true);
            $avg = $casts && $ratings ? round(((float)$ratings/(int)$casts)/5*$best,1):0;
        ?>
          <tr>
            <td><?php echo esc_html($p->ID); ?></td>
            <td><a href="<?php echo esc_url(get_edit_post_link($p->ID)); ?>"><?php echo esc_html($p->post_title); ?></a></td>
            <td><?php echo esc_html($p->post_type); ?></td>
            <td><?php echo esc_html($v!==''?number_format_i18n((int)$v):'—'); ?></td>
            <td><?php echo esc_html($avg? $avg.' ('.$casts.' رأی)':'—'); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="apex-card" style="margin-top:16px;">
    <h3>۲) مقدار جدید (اعمال گروهی یکسان)</h3>
    <p><small>هر درخواست با شناسه یکتا ثبت و تاریخچه جداگانه برای هر نوشته ذخیره می‌شود. از تأیید بدون کنترل اجتناب کنید.</small></p>

    <div class="apex-field">
      <label for="apex_bulk_ids">شناسه‌ها (کاما جدا)</label>
      <input type="text" id="apex_bulk_ids" value="<?php echo esc_attr(implode(',', $ids_arr)); ?>">
    </div>
    <div class="apex-field">
      <label for="apex_bulk_field">فیلد هدف</label>
      <select id="apex_bulk_field">
        <option value="views">بازدید (views)</option>
        <option value="kksr_casts">تعداد رأی</option>
        <option value="kksr_avg">میانگین امتیاز</option>
      </select>
    </div>
    <div class="apex-field">
      <label for="apex_bulk_value">مقدار جدید</label>
      <input type="number" id="apex_bulk_value" step="0.1" placeholder="مثلاً 50000 یا 4.8">
      <small>برای بازدید/رأی عدد صحیح، برای میانگین تا یک رقم اعشار.</small>
    </div>
    <div class="apex-field">
      <label><input type="checkbox" id="apex_bulk_range_toggle"> بازه تصادفی (هر پست مقدار جداگانه در بازه)</label>
    </div>
    <div id="apex-bulk-range-fields" style="display:none; background:#f9fafb; border:1px dashed #d1d5db; border-radius:8px; padding:12px; margin-top:4px;">
      <div style="display:flex; gap:12px;">
        <div class="apex-field" style="flex:1; margin:0;">
          <label for="apex_bulk_min">از (حداقل)</label>
          <input type="number" id="apex_bulk_min" step="0.1" placeholder="مثلاً 1300000">
        </div>
        <div class="apex-field" style="flex:1; margin:0;">
          <label for="apex_bulk_max">تا (حداکثر)</label>
          <input type="number" id="apex_bulk_max" step="0.1" placeholder="مثلاً 1900000">
        </div>
      </div>
      <small style="display:block; margin-top:6px; color:#6b7280;">مثلاً بازدید بین <b>1300000</b> تا <b>1900000</b> → هر پست یک عدد تصادفی در این بازه می‌گیرد. برای امتیاز مثلاً 4.2 تا 4.9 ، و تعداد رأی 800 تا 1500.</small>
    </div>
    <div class="apex-field">
      <label for="apex_bulk_mode">حالت</label>
      <select id="apex_bulk_mode">
        <option value="manual">دستی</option>
        <option value="scheduled">زمان‌بندی‌شده (نیاز به ایجاد برنامه جداگانه)</option>
      </select>
    </div>
    <div class="apex-actions">
      <button type="button" id="apex-bulk-apply" class="button-apex">اعمال گروهی</button>
    </div>
    <div id="apex-bulk-result"></div>
    <div class="apex-alert warning" style="margin-top:12px;">
      <strong>توجه:</strong> ویرایش گروهی برای مهاجرت/مقداردهی اولیه است. پیش‌نمایش و تأیید برای مقادیر بزرگ توصیه می‌شود. این عملیات قابل بازگردانی از طریق تاریخچه هر نوشته است.
    </div>
  </div>
</div>
