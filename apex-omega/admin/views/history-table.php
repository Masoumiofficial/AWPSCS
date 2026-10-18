<?php if (!defined('ABSPATH')) exit;
// فیلترها: source, post_id, date
$filter_source = sanitize_text_field($_GET['filter_source'] ?? '');
$filter_post = isset($_GET['filter_post']) ? (int)$_GET['filter_post'] : 0;
?>
<div class="wrap apex-wrap">
  <h1>تاریخچه تغییرات — APEX Ω 1.3.0</h1>
  <p>تمام تغییرات دستی، زمان‌بندی‌شده، توزیع تیره و بازگردانی‌ها با شناسه یکتا و منبع ثبت می‌شوند.</p>
  <div class="apex-card" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin:12px 0;">
    <form method="get" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
      <input type="hidden" name="page" value="apex-omega-history">
      <label>منبع: <select name="filter_source"><option value="">همه</option><option value="manual" <?php selected($filter_source,'manual'); ?>>دستی</option><option value="tier_distribution" <?php selected($filter_source,'tier_distribution'); ?>>توزیع هوشمند</option><option value="scheduled" <?php selected($filter_source,'scheduled'); ?>>زمان‌بندی</option><option value="tier_undo" <?php selected($filter_source,'tier_undo'); ?>>بازگشت توزیع</option></select></label>
      <label>شناسه پست: <input type="number" name="filter_post" value="<?php echo $filter_post?:''; ?>" placeholder="مثلاً 123" style="width:120px;"></label>
      <button type="submit" class="button">فیلتر</button>
      <a href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-history')); ?>" class="button secondary">پاک کردن</a>
    </form>
    <span style="margin-right:auto;"></span>
    <button type="button" id="apex-export-history-csv" class="button button-apex secondary">📥 خروجی CSV</button>
  </div>
  <table class="apex-table">
    <thead>
      <tr>
        <th>ID</th><th>زمان (GMT)</th><th>نوشته</th><th>فیلد</th><th>قبلی</th><th>جدید</th><th>Δ</th><th>منبع</th><th>کاربر</th><th>اجرا</th><th>بازگردانی</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($recent as $r):
        $user = $r['user_id'] ? get_userdata((int)$r['user_id']) : null;
      ?>
        <tr>
          <td><?php echo esc_html($r['id']); ?></td>
          <td><?php echo esc_html($r['created_at_gmt']); ?></td>
          <td><a href="<?php echo esc_url(get_edit_post_link((int)$r['post_id'])); ?>"><?php echo esc_html($r['post_title'] ?? $r['post_id']); ?></a></td>
          <td><code><?php echo esc_html($r['field_type']); ?></code></td>
          <td><?php echo esc_html(mb_strimwidth($r['old_value']??'',0,30,'…')); ?></td>
          <td><?php echo esc_html(mb_strimwidth($r['new_value']??'',0,30,'…')); ?></td>
          <td><?php echo esc_html($r['delta'] ?? '—'); ?></td>
          <td><?php echo esc_html($r['source']); ?></td>
          <td><?php echo $user ? esc_html($user->user_login) : '—'; ?></td>
          <td><small><?php echo esc_html(substr($r['execution_id']??'',0,8)); ?></small></td>
          <td><button type="button" class="button apex-rollback" data-id="<?php echo esc_attr($r['id']); ?>">بازگردانی</button></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($recent)): ?><tr><td colspan="11">تاریخچه‌ای نیست.</td></tr><?php endif; ?>
    </tbody>
  </table>
  <p><small>نکته: تغییرات مدیریتی به‌عنوان رویداد تحلیلی واقعی ثبت نمی‌شوند؛ منبع آن‌ها manual/scheduled/restore است و از real_vote متمایز است.</small></p>
</div>
