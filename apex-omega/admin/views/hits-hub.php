<?php if (!defined('ABSPATH')) exit;
$tax = \ApexOmega\Core\ArtistManager::detect_artist_taxonomy();
$artists = $tax ? \ApexOmega\Core\ArtistManager::get_all_artists($tax, 200) : [];
$weights = get_option(\ApexOmega\Core\ArtistManager::OPTION_WEIGHTS, []);
$hit_ids = \ApexOmega\Core\ArtistManager::get_all_hit_ids();
$hit_map = array_flip($hit_ids);
// دسته منتخب — سبک با transient cache
$selected_ids = \ApexOmega\Core\ArtistManager::get_selected_works_ids(2000);
$selected_map = array_flip($selected_ids);
$filter_term = isset($_GET['artist_term']) ? (int)$_GET['artist_term'] : 0;
$search_q = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$source = isset($_GET['source']) ? sanitize_text_field($_GET['source']) : 'all'; // all|manual|auto
$paged = isset($_GET['paged']) ? max(1, (int)$_GET['paged']) : 1;
$per_page = 60;
$args = [
    'post_type' => 'post',
    'post_status' => 'publish',
    'posts_per_page' => $per_page,
    'paged' => $paged,
    'orderby' => 'date',
    'order' => 'DESC',
    'no_found_rows' => false,
];
if ($filter_term && $tax) $args['tax_query'] = [[ 'taxonomy' => $tax, 'field' => 'term_id', 'terms' => $filter_term ]];
if ($search_q) $args['s'] = $search_q;
if ($source === 'manual') {
    $args['meta_query'] = [['key'=>'_apex_hit','compare'=>'EXISTS']];
} elseif ($source === 'auto') {
    // فقط دسته منتخب — via tax_query for selected-works
    $args['tax_query'] = isset($args['tax_query']) ? array_merge($args['tax_query'], [['taxonomy'=>'category','field'=>'slug','terms'=>\ApexOmega\Core\ArtistManager::SELECTED_WORKS_SLUG]]) : [['taxonomy'=>'category','field'=>'slug','terms'=>\ApexOmega\Core\ArtistManager::SELECTED_WORKS_SLUG]];
}
$q = new WP_Query($args);
$total_found = (int)$q->found_posts;
$total_pages = (int)$q->max_num_pages;
$manual_count = count($hit_ids);
$auto_count = count($selected_ids);
$both = count(array_intersect($hit_ids,$selected_ids));
?>
<div class="wrap apex-glass-wrap" dir="rtl">
  <div class="apex-glass-header">
    <div>
      <h1>آثار منتخب — مدیریت هیت و دسته منتخب</h1>
      <p>دستی (تیک) یا خودکار (دسته <code>selected-works</code>). دستی ۹–۱۰M، خودکار ۹٫۵–۱۱٫۸M پرمیوم. سبک، صفحه‌ای، بدون لود سنگین.</p>
    </div>
    <div class="apex-glass-actions">
      <a class="apex-btn-ghost" href="https://ahangban.ir/selected-works/" target="_blank" rel="noopener">مشاهده دسته ←</a>
      <button type="button" class="apex-btn-primary" id="apex-save-hits">ذخیره هیت‌ها</button>
    </div>
  </div>

  <div class="apex-hub-nav">
    <a href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-artists')); ?>">هنرمندان</a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-automation')); ?>">اتوماسیون</a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-weight-sync')); ?>">همگام‌سازی</a>
    <a class="active" href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-hits')); ?>">آثار منتخب</a>
  </div>

  <?php if (!$tax): ?>
    <div class="apex-glass-card apex-danger"><strong>تاکسونومی هنرمند یافت نشد.</strong> از «هنرمندان» تاکسونومی را ذخیره کن.</div>
  <?php endif; ?>

  <div class="apex-glass-card">
    <h3><span class="dot"></span>خلاصه — چیا منتخب هستند؟</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;margin-top:8px">
      <div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:12px;padding:12px;text-align:center"><div style="font-size:22px;font-weight:900"><?php echo esc_html($manual_count); ?></div><small style="color:#92400e">دستی (تیک هیت)</small></div>
      <div style="background:#ede9fe;border:1px solid #c4b5fd;border-radius:12px;padding:12px;text-align:center"><div style="font-size:22px;font-weight:900"><?php echo esc_html($auto_count); ?></div><small style="color:#5b21b6">خودکار دسته selected-works</small></div>
      <div style="background:#ecfdf5;border:1px solid #86efac;border-radius:12px;padding:12px;text-align:center"><div style="font-size:22px;font-weight:900"><?php echo esc_html($total_found); ?></div><small style="color:#065f46">نتایج فعلی فیلتر</small></div>
    </div>
    <p class="desc" style="margin-top:10px">هر نوشته در دسته با نامک <code>selected-works</code> خودکار پرمیوم است — بدون تیک. اگر هر دو باشد، پرمیوم لحاظ می‌شود. اشتراک: <?php echo esc_html($both); ?> مورد.</p>
  </div>

  <div class="apex-glass-card">
    <h3><span class="dot" style="background:#7c3aed"></span>فیلتر و جستجو — سبک</h3>
    <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
      <input type="hidden" name="page" value="apex-omega-hits">
      <label class="apex-f" style="min-width:180px">منبع
        <select name="source" style="padding:8px;border-radius:10px;border:1px solid #e5e7eb;background:#fff">
          <option value="all" <?php selected($source,'all'); ?>>همه</option>
          <option value="manual" <?php selected($source,'manual'); ?>>فقط دستی</option>
          <option value="auto" <?php selected($source,'auto'); ?>>فقط دسته منتخب</option>
        </select>
      </label>
      <label class="apex-f" style="min-width:180px">هنرمند
        <select name="artist_term" style="padding:8px;border-radius:10px;border:1px solid #e5e7eb;background:#fff">
          <option value="0">همه هنرمندان</option>
          <?php foreach ($artists as $a): ?>
            <option value="<?php echo esc_attr($a['term_id']); ?>" <?php selected($filter_term,$a['term_id']); ?>><?php echo esc_html($a['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="apex-f">جستجو
        <input name="s" value="<?php echo esc_attr($search_q); ?>" placeholder="عنوان..." style="padding:8px;border-radius:10px;border:1px solid #e5e7eb">
      </label>
      <button type="submit" class="apex-btn-primary">اعمال</button>
      <a href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-hits')); ?>" class="apex-btn-ghost">پاک</a>
      <span class="apex-pill"><?php echo esc_html($total_found); ?> آهنگ — صفحه <?php echo esc_html($paged); ?>/<?php echo esc_html($total_pages); ?></span>
    </form>
  </div>

  <div class="apex-glass-card" style="padding:0;overflow:hidden">
    <div style="padding:12px 14px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;border-bottom:1px solid #eef2f7;background:rgba(248,250,252,.6)">
      <strong style="font-size:13px">لیست آهنگ‌ها — تیک دستی</strong>
      <label style="display:flex;align-items:center;gap:6px;font-size:13px"><input type="checkbox" id="apex-check-all"> انتخاب همه صفحه</label>
      <span id="apex-hits-selected-count" class="apex-pill" style="background:#ede9fe;color:#5b21b6">۰ انتخاب</span>
    </div>
    <form id="apex-hits-form">
    <div style="max-height:520px;overflow:auto">
      <table class="apex-table">
        <thead><tr><th style="width:36px"><input type="checkbox" id="apex-check-all2" style="display:none"></th><th>عنوان</th><th>هنرمند</th><th>وزن</th><th>وضعیت</th><th>بازدید</th></tr></thead>
        <tbody>
          <?php if ($q->have_posts()): while($q->have_posts()): $q->the_post(); $pid=get_the_ID(); $title=get_the_title(); $artist_terms = $tax ? wp_get_post_terms($pid,$tax,['fields'=>'names']) : []; $artist_name = !empty($artist_terms) && !is_wp_error($artist_terms) ? $artist_terms[0] : '—'; $w=(int)\ApexOmega\Core\ArtistManager::get_weight_for_post($pid); $is_manual=isset($hit_map[$pid]); $is_auto=isset($selected_map[$pid]); $views=(int)get_post_meta($pid,'views',true);
            if ($is_manual && $is_auto) $badge='<span class="apex-badge" style="background:#ede9fe;color:#5b21b6;border-color:#c4b5fd">پرمیوم منتخب ✨ + دستی</span>';
            elseif ($is_auto) $badge='<span class="apex-badge-auto">منتخب خودکار ✨ ۱۱٫۸M</span>';
            elseif ($is_manual) $badge='<span class="apex-badge-hit">هیت دستی ⭐ ۱۰M</span>';
            else $badge='<span class="apex-badge-regular">معمولی</span>';
          ?>
          <tr>
            <td><input type="checkbox" class="apex-hit-check" value="<?php echo esc_attr($pid); ?>" <?php checked($is_manual,true); ?>></td>
            <td><?php echo esc_html($title); ?> <small style="color:#64748b">#<?php echo $pid; ?></small></td>
            <td><?php echo esc_html($artist_name); ?></td>
            <td><span class="apex-pill"><?php echo esc_html($w); ?></span></td>
            <td><?php echo $badge; ?></td>
            <td><?php echo $views ? esc_html(number_format_i18n($views)) : '—'; ?></td>
          </tr>
          <?php endwhile; wp_reset_postdata(); else: ?>
          <tr><td colspan="6" style="text-align:center;color:#64748b;padding:24px">موردی یافت نشد.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    </form>
    <div style="padding:10px 14px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;border-top:1px solid #eef2f7">
      <button type="button" class="apex-btn-primary" id="apex-save-hits2">ذخیره هیت‌های انتخاب‌شده</button>
      <label style="display:flex;align-items:center;gap:6px;font-size:12px"><input type="checkbox" id="apex-clear-before"> پاکِ قبلی قبل ذخیره</label>
      <button type="button" class="apex-btn-ghost" id="apex-clear-hits" style="color:#dc2626;border-color:#fecaca">پاک همه دستی</button>
      <span class="apex-help" style="flex:1">سرور-ساید، سبک — هر صفحه ۶۰ تا. دسته منتخب خودکار است، تیک نمی‌خواهد.</span>
    </div>
    <?php if ($total_pages>1): ?>
    <div style="padding:10px 14px;display:flex;gap:6px;flex-wrap:wrap;border-top:1px solid #eef2f7">
      <?php for($i=1;$i<=$total_pages && $i<=8;$i++): $url=add_query_arg(['artist_term'=>$filter_term?:null,'s'=>$search_q?:null,'source'=>$source!=='all'?$source:null,'paged'=>$i], admin_url('admin.php?page=apex-omega-hits')); ?>
        <a href="<?php echo esc_url($url); ?>" style="padding:6px 12px;border-radius:999px;border:1px solid <?php echo $i==$paged?'#5b5bd6':'#e5e7eb'; ?>;background:<?php echo $i==$paged?'#5b5bd6':'#fff'; ?>;color:<?php echo $i==$paged?'#fff':'#334155'; ?>;text-decoration:none;font-size:13px"><?php echo $i; ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div>

  <div class="apex-glass-card">
    <h3><span class="dot" style="background:#06b6d4"></span>پیشنهاد AI — ۵ هیت برتر هر هنرمند</h3>
    <p class="desc">رز موزیک استایل — بدون شلوغی.</p>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <select id="apex-ai-artist" style="min-width:180px;padding:8px;border-radius:10px;border:1px solid #e5e7eb;background:#fff">
        <option value="">همه هنرمندان (۳۰ اخیر)</option>
        <?php foreach ($artists as $a): ?><option value="<?php echo esc_attr($a['term_id']); ?>"><?php echo esc_html($a['name']); ?> (<?php echo esc_html($a['weight']); ?> · <?php echo esc_html($a['count']); ?>)</option><?php endforeach; ?>
      </select>
      <button type="button" class="apex-btn-primary" id="apex-ai-suggest">AI پیشنهاد ۵ هیت</button>
      <span id="apex-ai-result" class="apex-pill" style="display:none"></span>
    </div>
  </div>
</div>
<script>
(function(){
  function getNonce(){ return (window.ApexOmega&&ApexOmega.nonce)|| document.querySelector('input[name="_wpnonce"]')?.value || '';}
  function getAjax(){ return (window.ApexOmega&&ApexOmega.ajax_url)|| ajaxurl; }
  function upd(){ const n=document.querySelectorAll('.apex-hit-check:checked').length; const el=document.getElementById('apex-hits-selected-count'); if(el) el.textContent=n.toLocaleString('fa-IR')+' انتخاب'; }
  document.addEventListener('change',e=>{ if(e.target.matches('.apex-hit-check')) upd(); });
  document.getElementById('apex-check-all')?.addEventListener('change',function(){ document.querySelectorAll('.apex-hit-check').forEach(c=>c.checked=this.checked); upd(); });
  function saveHits(){
    const ids=[...document.querySelectorAll('.apex-hit-check:checked')].map(c=>parseInt(c.value));
    if(!ids.length){ alert('حداقل یک آهنگ تیک بزن'); return; }
    const clear=document.getElementById('apex-clear-before')?.checked?1:0;
    const nonce=getNonce(), ajax=getAjax();
    const btn=document.getElementById('apex-save-hits')||document.getElementById('apex-save-hits2');
    if(btn){ btn.disabled=true; const o=btn.textContent; btn.textContent='در حال ذخیره...';
      const fd=new FormData(); fd.append('action','apex_save_hits'); fd.append('_ajax_nonce',nonce); ids.forEach(id=>fd.append('post_ids[]',id)); fd.append('clear_first',clear);
      fetch(ajax,{method:'POST',body:fd,credentials:'same-origin'}).then(r=>r.json()).then(j=>{ if(j.success){ alert('ذخیره شد: '+j.data.saved+' هیت'); location.reload(); } else alert(j.data.message||'خطا'); }).catch(e=>alert('خطا:'+e)).finally(()=>{btn.disabled=false; btn.textContent=o;});
    }
  }
  document.getElementById('apex-save-hits')?.addEventListener('click',saveHits);
  document.getElementById('apex-save-hits2')?.addEventListener('click',saveHits);
  document.getElementById('apex-clear-hits')?.addEventListener('click',function(){
    if(!confirm('همه هیت‌های دستی پاک شود؟')) return;
    const fd=new FormData(); fd.append('action','apex_clear_hits'); fd.append('_ajax_nonce',getNonce());
    fetch(getAjax(),{method:'POST',body:fd,credentials:'same-origin'}).then(r=>r.json()).then(j=>{ alert(j.data.message||'پاک شد'); location.reload(); });
  });
  document.getElementById('apex-ai-suggest')?.addEventListener('click',function(){
    const term=document.getElementById('apex-ai-artist')?.value||''; const btn=this; btn.disabled=true; const o=btn.textContent; btn.textContent='AI...';
    const fd=new FormData(); fd.append('action','apex_suggest_hits'); fd.append('_ajax_nonce',getNonce()); fd.append('term_id',term); fd.append('limit',20);
    const resEl=document.getElementById('apex-ai-result');
    fetch(getAjax(),{method:'POST',body:fd,credentials:'same-origin'}).then(r=>r.json()).then(j=>{
      if(j.success){
        if(resEl){ resEl.style.display='inline-flex'; resEl.textContent='پیشنهاد: '+(j.data.suggested||[]).join(', '); resEl.style.background='#ecfdf5'; }
        (j.data.suggested||[]).forEach(id=>{ const cb=document.querySelector('.apex-hit-check[value="'+id+'"]'); if(cb) cb.checked=true; }); upd();
      } else alert(j.data.message||'خطا');
    }).finally(()=>{btn.disabled=false; btn.textContent=o;});
  });
  upd();
})();
</script>
</div>
