// === Member Panel: "Recommended for you" strip ===
document.addEventListener('DOMContentLoaded', () => {
  const panel  = document.getElementById('recs-panel');
  if (!panel) return;

  const userId = window.CURRENT_USER_ID || 0;
  const listEl = document.getElementById('recs-list');
  const errEl  = document.getElementById('recs-error');

  if (!userId) { errEl.textContent = 'Sign in to see recommendations.'; return; }
  errEl.textContent = 'Loading…';

  fetch(`php/recs-proxy.php?user_id=${encodeURIComponent(userId)}`, { cache: 'no-store' })
    .then(r => r.json())
    .then(raw => {
      // Accept either [{...}] or { recommendations: [{...}] }
      const recs = Array.isArray(raw) ? raw
                 : (raw && Array.isArray(raw.recommendations)) ? raw.recommendations
                 : [];
      listEl.innerHTML = '';

      if (!recs.length) { errEl.textContent = 'No recommendations yet.'; return; }

      recs.forEach(r => {
        const card = document.createElement('div');
        card.className = 'rec-card';
        card.style.cssText = 'padding:8px 10px;border:3px solid green;border-radius:8px;background:#fff;';
        card.textContent = r.name || '';
        listEl.appendChild(card);
      });

      errEl.textContent = '';
    })
    .catch(() => { errEl.textContent = 'Could not load recommendations.'; });
});
