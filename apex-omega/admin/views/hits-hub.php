<?php if (!defined('ABSPATH')) exit;
$tax = \ApexOmega\Core\ArtistManager::detect_artist_taxonomy();
$artists = $tax ? \ApexOmega\Core\ArtistManager::get_all_artists($tax, 200) : [];
$weights = get_option(\ApexOmega\Core\ArtistManager::OPTION_WEIGHTS, []);
$hit_ids = \ApexOmega\Core\ArtistManager::get_all_hit_ids();
$hit_map = array_flip($hit_ids);
$selected_hits_count = count($hit_ids);
$filter_term = isset($_GET['artist_term']) ? (int)$_GET['artist_term'] : 0;
$search_q = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
// لیست پست‌ها سمت سرور — بدون JS لود بی‌نهایت
$paged = isset($_GET['paged']) ? max(1, (int)$_GET['paged']) : 1;
$per_page = 80;
$args = [
    'post_type' => 'post',
    'post_status' => 'publish',
    'posts_per_page' => $per_page,
    'paged' => $paged,
    'orderby' => 'date',
    'order' => 'DESC',
];
if ($filter_term) {
    $args['tax_query'] = [[ 'taxonomy' => $tax, 'field' => 'term_id', 'terms' => $filter_term ]];
}
if ($search_q) $args['s'] = $search_q;
$q = new WP_Query($args);
$total_found = $q->found_posts;
$total_pages = $q->max_num_pages;
?>
<div class="wrap apex-omega-wrap" dir="rtl">
<style>
.apex-hits-wrap{max-width:1180px;margin:20px 0;font-family:IRANSans,Tahoma,sans-serif}
.apex-hub-nav{display:flex;gap:8px;margin:10px 0 18px;flex-wrap:wrap}
.apex-hub-nav a{padding:8px 14px;border-radius:999px;text-decoration:none;font-size:13px;border:1px solid #d1d5db;background:#fff;color:#334155}
.apex-hub-nav a.active{background:#7c3aed;color:#fff;border-color:#7c3aed}
.apex-card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:16px;margin-bottom:14px;box-shadow:0 6px 18px rgba(15,23,42,.06)}
.apex-card h3{margin:0 0 8px;font-size:15px}
.apex-hint{color:#64748b;font-size:12px;margin:6px 0}
.apex-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
@media(max-width:800px){.apex-grid{grid-template-columns:1fr}}
.apex-btn{padding:8px 12px;border-radius:10px;border:1px solid #e5e7eb;background:#fff;cursor:pointer;font-size:13px}
.apex-btn-primary{background:#7c3aed;color:#fff;border-color:#7c3aed}
.apex-btn-danger{background:#fff;color:#dc2626;border-color:#fecaca}
.apex-table{width:100%;border-collapse:collapse;font-size:13px}
.apex-table th,.apex-table td{padding:8px 10px;border-bottom:1px solid #eef2f7;text-align:right}
.apex-table th{background:#f8fafc}
.apex-badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px}
.apex-badge-hit{background:#fef3c7;color:#92400e;border:1px solid #fcd34d}
.apex-badge-regular{background:#f1f5f9;color:#334155}
</style>
<div class="apex-hits-wrap">
  <h1 style="font-size:22px;margin:0">آثار منتخب و معروف‌ترین‌ها ⭐ <span style="font-weight:400;color:#64748b;font-size:13px">— هیت ۱۰M / معمولی ۲-۴M (رز موزیک استایل)</span></h1>
  <p class="apex-hint">هنرمند وزن ۹۵: فقط تیک «هیت» → ۹-۱۰M (۴.۹-۵.۰ ⭐ ۱۱-۱۹K رای). بقیه همون هنرمند وزن ۹۵ ولی معمولی → ۲-۴M — فاصله عقلانی. نوپا وزن ۲۰ → ۱۰K-30K. کامنت‌ها هم بر اساس رز موزیک: کوتاه، کشیده، غلط تایپی ریز، ایموجی، انتقاد — بدون تکرار و AI-like.</p>
  <div class="apex-hub-nav">
    <a href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-artists')); ?>">🎤 هنرمندان</a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-automation')); ?>">⚙️ اتوماسیون</a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-weight-sync')); ?>">⚡ همگام‌سازی</a>
    <a class="active" href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-hits')); ?>">⭐ آثار منتخب</a>
  </div>
  <?php if (!$tax): ?>
    <div class="apex-card" style="border-color:#fecaca;background:#fef2f2"><strong>تاکسونومی هنرمند یافت نشد.</strong> ابتدا در «هنرمندان» تاکسونومی را ذخیره کن.</div>
  <?php endif; ?>
  <div class="apex-grid">
    <div class="apex-card">
      <h3>وضعیت فعلی — خلاصه هیت</h3>
      <p class="apex-hint">تعداد کل هیت‌های ثبت‌شده: <strong><?php echo esc_html($selected_hits_count); ?></strong> — اینها در همگام‌سازی بعدی ۹-۱۰M می‌گیرند. بقیه ضریب ۰.۱۰+norm*0.45 می‌گیرند.</p>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
        <button type="button" class="apex-btn apex-btn-danger" id="apex-clear-hits">پاک کردن همه هیت‌ها</button>
        <button type="button" class="apex-btn" onclick="location.reload()">↻ بروزرسانی</button>
      </div>
      <?php if ($hit_ids): ?>
        <div style="margin-top:12px;max-height:180px;overflow:auto;border:1px solid #e5e7eb;border-radius:10px;padding:8px">
          <?php foreach ($hit_ids as $pid): $p=get_post($pid); if(!$p) continue; $w = \ApexOmega\Core\ArtistManager::get_weight_for_post($pid); ?>
            <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px dashed #f1f5f9"><span><?php echo esc_html($p->post_title); ?> <small style="color:#64748b">(ID <?php echo $pid; ?> · وزن <?php echo $w; ?>)</small></span><button type="button" class="apex-btn" style="padding:2px 8px;font-size:11px" data-remove-hit="<?php echo esc_attr($pid); ?>">حذف</button></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <div class="apex-card">
      <h3>هوش مصنوعی — پیشنهاد هیت (رز موزیک استایل)</h3>
      <p class="apex-hint">AI با همون لحن رز موزیک پیشنهاد میده — 5 تا معروف‌ترین.</p>
      <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <select id="apex-ai-artist" style="min-width:180px;padding:6px;border-radius:8px;border:1px solid #e5e7eb">
          <option value="">— همه هنرمندان (۳۰ پست اخیر) —</option>
          <?php foreach ($artists as $a): ?>
            <option value="<?php echo esc_attr($a['term_id']); ?>"><?php echo esc_html($a['name']); ?> (وزن <?php echo esc_html($a['weight']); ?> · <?php echo esc_html($a['count']); ?> آهنگ)</option>
          <?php endforeach; ?>
        </select>
        <button type="button" class="apex-btn apex-btn-primary" id="apex-ai-suggest">✨ پیشنهاد AI (۵ هیت)</button>
      </div>
      <div id="apex-ai-result" style="margin-top:10px"></div>
    </div>
  </div>

  <div class="apex-card">
    <h3>انتخاب دستی — لیست آهنگ‌ها (سرور-ساید، بدون لودینگ بی‌نهایت)</h3>
    <form method="get" action="" style="display:flex;gap:8px;flex-wrap:wrap;margin:10px 0;align-items:end">
      <input type="hidden" name="page" value="apex-omega-hits">
      <label>فیلتر هنرمند:
        <select name="artist_term" style="padding:6px;border-radius:8px;border:1px solid #e5e7eb;min-width:180px">
          <option value="0">— همه هنرمندان —</option>
          <?php foreach ($artists as $a): ?>
            <option value="<?php echo esc_attr($a['term_id']); ?>" <?php selected($filter_term, $a['term_id']); ?>><?php echo esc_html($a['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>جستجو: <input name="s" value="<?php echo esc_attr($search_q); ?>" placeholder="عنوان..." style="padding:6px 10px;border-radius:8px;border:1px solid #e5e7eb"></label>
      <button type="submit" class="apex-btn apex-btn-primary">اعمال فیلتر</button>
      <a href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-hits')); ?>" class="apex-btn">حذف فیلتر</a>
      <span class="apex-hint" style="margin-right:8px"><?php echo esc_html($total_found); ?> آهنگ یافت شد — صفحه <?php echo esc_html($paged); ?> از <?php echo esc_html($total_pages); ?></span>
    </form>

    <form id="apex-hits-form">
    <div id="apex-hits-table-wrap" style="max-height:520px;overflow:auto;border:1px solid #e5e7eb;border-radius:12px">
      <table class="apex-table" id="apex-hits-table">
        <thead><tr><th style="width:40px"><input type="checkbox" id="apex-check-all"></th><th>عنوان آهنگ</th><th>هنرمند</th><th>وزن</th><th>وضعیت</th><th>بازدید فعلی</th></tr></thead>
        <tbody id="apex-hits-tbody">
          <?php if ($q->have_posts()): while($q->have_posts()): $q->the_post(); $pid=get_the_ID(); $title=get_the_title(); $artist_terms = $tax ? wp_get_post_terms($pid, $tax, ['fields'=>'names']) : []; $artist_name = !empty($artist_terms) && !is_wp_error($artist_terms) ? $artist_terms[0] : '—'; $w = \ApexOmega\Core\ArtistManager::get_weight_for_post($pid); $is_hit = isset($hit_map[$pid]); $views = (int)get_post_meta($pid,'views',true); $badge = $is_hit ? '<span class="apex-badge apex-badge-hit">هیت ⭐ ۱۰M</span>' : '<span class="apex-badge apex-badge-regular">معمولی ۲-۴M</span>'; ?>
          <tr>
            <td><input type="checkbox" class="apex-hit-check" value="<?php echo esc_attr($pid); ?>" <?php checked($is_hit, true); ?>></td>
            <td><?php echo esc_html($title); ?> <small style="color:#64748b">#<?php echo $pid; ?></small></td>
            <td><?php echo esc_html($artist_name); ?></td>
            <td><span style="background:#f1f5f9;padding:2px 8px;border-radius:999px;font-size:12px"><?php echo esc_html($w); ?></span></td>
            <td><?php echo $badge; ?></td>
            <td><?php echo $views ? esc_html(number_format_i18n($views)) : '—'; ?></td>
          </tr>
          <?php endwhile; wp_reset_postdata(); else: ?>
          <tr><td colspan="6" style="text-align:center;color:#64748b;padding:20px">موردی یافت نشد — فیلتر را عوض کن.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    </form>
    <div style="margin-top:10px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <span id="apex-hits-selected-count" class="apex-badge" style="background:#ede9fe;color:#5b21b6">۰ انتخاب</span>
      <button type="button" class="apex-btn apex-btn-primary" id="apex-save-hits">💾 ذخیره هیت‌های انتخاب‌شده (این صفحه)</button>
      <label style="display:flex;align-items:center;gap:6px;font-size:13px"><input type="checkbox" id="apex-clear-before"> پاک‌سازی قبلی قبل از ذخیره</label>
      <span class="apex-hint">نکته: این جدول سرور-ساید هست — دیگه لود بی‌نهایت نداره. برای همه هنرمند صفحه‌به‌صفحه تیک بزن.</span>
    </div>
    <?php if ($total_pages > 1): ?>
    <div style="margin-top:12px;display:flex;gap:6px;flex-wrap:wrap">
      <?php for($i=1;$i<=$total_pages && $i<=8;$i++): $url = add_query_arg(['artist_term'=>$filter_term?:null,'s'=>$search_q?:null,'paged'=>$i], admin_url('admin.php?page=apex-omega-hits')); ?>
        <a href="<?php echo esc_url($url); ?>" style="padding:6px 10px;border-radius:8px;border:1px solid <?php echo $i==$paged?'#7c3aed':'#e5e7eb'; ?>;background:<?php echo $i==$paged?'#7c3aed':'#fff'; ?>;color:<?php echo $i==$paged?'#fff':'#334155'; ?>;text-decoration:none"><?php echo $i; ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div>

  <div class="apex-card" style="background:#f8fafc">
    <h3>روند کامنت — رز موزیک استایل (برنگشتیم عقب)</h3>
    <ul style="margin:6px 0 0 18px;font-size:13px;color:#334155;line-height:1.9">
      <li>12 نمونه رز موزیک فعاله: «ممنون عالی بود»، «آخرشه حرف نداره»، «عالیییییییه»، «محشره محسن جان 20👌»، «زیبا بود آرامش گرفتم»، داستان شخصی طولانی، «هم ریتمش هم متنش عالی»، انتقاد «چرا آهنگای قرن بوقی»، «به جرئت بهترین آهنگ محسن همینه همشهری 😍»، کشش حروف/ایموجی/کوتاه/لهجه عام.</li>
      <li>کپی نمی‌کنیم — ایده می‌گیریم: AI همون لحن رو با 60% تحسین / 15% سلام+تعریف سایت / 15% انتقاد ریز / 10% سوال می‌سازه، بدون «پشمام»، بدون تکرار، بدون نگارشی/AI-like.</li>
      <li>سقف 1.5M→10M قبلاً بود — الان هم 10M می‌مونه، وزن 20 برای نوپا هم اعمال شد.</li>
    </ul>
  </div>
</div>
<script>
jQuery(function($){
  const nonce = (window.ApexOmega && ApexOmega.nonce) || '<?php echo esc_js(wp_create_nonce('apex_omega_nonce')); ?>';
  const ajaxUrl = (window.ApexOmega && ApexOmega.ajax_url) || ajaxurl;
  function updateCount(){ const n=$('.apex-hit-check:checked').length; $('#apex-hits-selected-count').text(n.toLocaleString('fa-IR')+' انتخاب'); }
  $('#apex-check-all').on('change', function(){ $('.apex-hit-check').prop('checked', this.checked); updateCount(); });
  $(document).on('change','.apex-hit-check', updateCount); updateCount();
  $('#apex-save-hits').on('click', function(){
    const ids = $('.apex-hit-check:checked').map(function(){return parseInt(this.value)}).get();
    if(!ids.length){ alert('حداقل یک آهنگ انتخاب کن (همین صفحه)'); return; }
    const clear = $('#apex-clear-before').is(':checked');
    const $b=$(this); $b.prop('disabled',true).text('در حال ذخیره...');
    $.post(ajaxUrl, {action:'apex_save_hits', _ajax_nonce: nonce, post_ids: ids, clear_first: clear?1:0}, function(res){
      $b.prop('disabled',false).text('💾 ذخیره هیت‌های انتخاب‌شده (این صفحه)');
      if(res.success){ alert('ذخیره شد: '+res.data.saved+' هیت'); location.reload(); } else alert(res.data.message||'خطا');
    }).fail(function(xhr){ $b.prop('disabled',false).text('💾 ذخیره'); alert('خطا: '+(xhr.responseJSON?.data?.message||xhr.statusText)); });
  });
  $('#apex-clear-hits').on('click', function(){
    if(!confirm('همه هیت‌ها پاک شود؟')) return;
    const $b=$(this); $b.prop('disabled',true).text('...');
    $.post(ajaxUrl,{action:'apex_clear_hits',_ajax_nonce:nonce}, function(res){ alert(res.data.message||'پاک شد'); location.reload(); }).fail(function(){ alert('خطا'); $b.prop('disabled',false).text('پاک کردن همه هیت‌ها'); });
  });
  $(document).on('click','[data-remove-hit]', function(){
    const pid=$(this).data('remove-hit');
    $.post(ajaxUrl,{action:'apex_toggle_hit',_ajax_nonce:nonce,post_id:pid}, function(res){ location.reload(); });
  });
  $('#apex-ai-suggest').on('click', function(){
    const termId=$('#apex-ai-artist').val();
    const $b=$(this); $b.prop('disabled',true).text('در حال پیشنهاد...');
    $('#apex-ai-result').html('<span style="color:#64748b">AI در حال بررسی...</span>');
    $.post(ajaxUrl,{action:'apex_suggest_hits',_ajax_nonce:nonce,term_id:termId,limit:20}, function(res){
      $b.prop('disabled',false).text('✨ پیشنهاد AI (۵ هیت)');
      if(res.success){
        const ids=res.data.suggested||[];
        let html='<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:10px"><strong>پیشنهاد AI برای '+$('<div>').text(res.data.artist||'').html()+':</strong> '+ids.join(', ')+'<br><small>این‌ها را تیک بزن و ذخیره کن — تیک‌ها خودکار زده میشن.</small><div style="margin-top:8px"><button type="button" class="apex-btn apex-btn-primary" id="apex-apply-ai">اعمال پیشنهاد به جدول</button></div></div>';
        $('#apex-ai-result').html(html);
        $('#apex-apply-ai').on('click', function(){
          ids.forEach(id=> $(`.apex-hit-check[value="${id}"]`).prop('checked',true));
          updateCount();
          $('html,body').animate({scrollTop: $('#apex-hits-table-wrap').offset().top-80},300);
        });
      } else alert(res.data.message||'خطا');
    }).fail(function(xhr){
      $b.prop('disabled',false).text('✨ پیشنهاد AI (۵ هیت)');
      const msg=xhr.responseJSON?.data?.message||xhr.statusText;
      $('#apex-ai-result').html('<span style="color:#dc2626">'+$('<div>').text(msg).html()+'</span>');
    });
  });
});
</script>
</div>
