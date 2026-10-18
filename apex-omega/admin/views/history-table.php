<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap apex-wrap">
  <h1>تاریخچه تغییرات — APEX Ω</h1>
  <p>تمام تغییرات دستی، زمان‌بندی‌شده و بازگردانی‌ها با شناسه یکتا و منبع ثبت می‌شوند.</p>
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
          <td><button class="button apex-rollback" data-id="<?php echo esc_attr($r['id']); ?>">بازگردانی</button></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($recent)): ?><tr><td colspan="11">تاریخچه‌ای نیست.</td></tr><?php endif; ?>
    </tbody>
  </table>
  <p><small>نکته: تغییرات مدیریتی به‌عنوان رویداد تحلیلی واقعی ثبت نمی‌شوند؛ منبع آن‌ها manual/scheduled/restore است و از real_vote متمایز است.</small></p>
</div>
