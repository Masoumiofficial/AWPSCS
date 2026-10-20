/**
 * APEX Ω 2.0-alpha — React Dashboard (stub build)
 * Build واقعی: cd apex-omega/admin/js/v2 && npm install && npm run build
 * این فایل stub تا زمان build واقعی، خطا نمی‌دهد.
 */
(function(){
  console.log('[APEX Ω] v2 stub loaded — 2.0.0-alpha');
  // Dashboard stub: اگر #apex-v2-root وجود داشت، پیام نمایش بده
  document.addEventListener('DOMContentLoaded', function(){
    var el = document.getElementById('apex-v2-root');
    if(el){
      el.innerHTML = '<div style="padding:24px;background:#f5f3ff;border:2px dashed #7c3aed;border-radius:12px;text-align:center">'
        + '<h3>APEX Ω 2.0-alpha — React Dashboard</h3>'
        + '<p>Build واقعی هنوز کامپایل نشده. برای کامپایل: <code>cd admin/js/v2 && npm install && npm run build</code></p>'
        + '<p>REST v2 فعال: <code>/wp-json/apex/v2/stats</code> و <code>/wp-json/apex/v2/simulate/preview</code></p>'
        + '</div>';
    }
  });
})();
