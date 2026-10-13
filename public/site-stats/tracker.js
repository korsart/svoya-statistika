/* Своя статистика 1.0. MIT. No external requests, arbitrary URLs or form values. */
(() => {
  'use strict';
  if (window.SiteStats) return;
  const config = window.__siteStatsConfig;
  const script = document.currentScript;
  if (!config || !script || location.origin !== config.origin) return;
  const endpoint = new URL('collect.php', script.src).href;
  if (new URL(endpoint).origin !== location.origin) return;
  const consentKey = 'site-stats:consent:v1';
  const sessionKey = 'site-stats:session:v1';
  const allowed = new Set(['telegram', 'whatsapp', 'phone', 'email', 'lead', 'cta']);
  let enabled = false;
  let memory = null;
  let path = location.pathname;
  let counted = false;
  let generation = 0;
  const retries = new Set();
  const normalize = p => p.replace(/\/+$/, '') || '/';
  const pageId = () => Object.keys(config.pages).find(id => normalize(config.pages[id]) === normalize(location.pathname));
  function getSource() {
    const utm = new URLSearchParams(location.search).get('utm_source')?.toLowerCase();
    const aliases = {tg:'telegram',telegram:'telegram',yt:'youtube',youtube:'youtube',vk:'vk',vkontakte:'vk',instagram:'instagram',ig:'instagram',threads:'threads',yandex:'yandex',google:'google'};
    if (utm) return aliases[utm] || 'other_campaign';
    try {
      const host = new URL(document.referrer).hostname.toLowerCase();
      if (host === location.hostname) return 'direct';
      for (const [domain, source] of Object.entries({'t.me':'telegram','youtube.com':'youtube','youtu.be':'youtube','vk.com':'vk','instagram.com':'instagram','threads.net':'threads','threads.com':'threads','yandex.ru':'search','ya.ru':'search','google.com':'search','google.ru':'search','bing.com':'search'})) {
        if (host === domain || host.endsWith('.' + domain)) return source;
      }
      return 'referral';
    } catch { return 'direct'; }
  }
  function session() {
    let saved = memory;
    try { saved = JSON.parse(sessionStorage.getItem(sessionKey)) || saved; } catch {}
    const now = Date.now();
    if (!saved || typeof saved.id !== 'string' || !/^[a-f0-9-]{36}$/.test(saved.id) || !Number.isFinite(saved.last) || now - saved.last >= 1800000 || saved.last > now) {
      saved = {id:crypto.randomUUID(), last:now, source:getSource()};
    }
    saved.last = now; memory = saved;
    try { sessionStorage.setItem(sessionKey, JSON.stringify(saved)); } catch {}
    return saved;
  }
  function device() {
    const ua = navigator.userAgent || '';
    return /iPad|Tablet|Android(?!.*Mobile)/i.test(ua) || (/Macintosh/i.test(ua) && navigator.maxTouchPoints > 1) ? 'tablet' : /Mobi|iPhone|iPod/i.test(ua) ? 'mobile' : 'desktop';
  }
  function send(event) {
    const page = pageId();
    if (!enabled || !page) return false;
    const current = session();
    const body = JSON.stringify({id:crypto.randomUUID(), session:current.id, event, page, source:current.source, device:device()});
    const epoch = generation;
    const deliver = (attempt = 0) => {
      if (!enabled || epoch !== generation) return;
      fetch(endpoint, {method:'POST', credentials:'omit', headers:{'Content-Type':'application/json'}, body, keepalive:true})
        .then(response => { if (response.status >= 500) throw new Error('temporary'); })
        .catch(() => {
          if (attempt < 2 && enabled && epoch === generation) {
            const timer = setTimeout(() => { retries.delete(timer); deliver(attempt + 1); }, 1000 * (attempt + 1));
            retries.add(timer);
          }
        });
    };
    deliver(); return true;
  }
  function sync() {
    if (path !== location.pathname) { path = location.pathname; counted = false; }
    if (enabled && !counted && pageId()) { counted = true; send('pageview'); }
  }
  function consent(value) {
    enabled = value === true;
    generation++;
    for (const timer of retries) clearTimeout(timer);
    retries.clear();
    try { localStorage.setItem(consentKey, enabled ? 'accepted' : 'rejected'); } catch {}
    if (!enabled) {
      memory = null; counted = false;
      try { sessionStorage.removeItem(sessionKey); } catch {}
    }
    sync();
  }
  window.SiteStats = Object.freeze({consent, track(event) { return allowed.has(event) ? send(event) : false; }, isEnabled() { return enabled; }});
  document.addEventListener('click', e => {
    if (!(e.target instanceof Element)) return;
    if (e.target.closest('[data-stats-revoke]')) { consent(false); return; }
    const link = e.target.closest('a[href]');
    if (link) {
      const href = link.getAttribute('href') || '';
      if (/^tel:/i.test(href)) send('phone');
      else if (/^mailto:/i.test(href)) send('email');
      else {
        try {
          const url = new URL(href, location.href);
          if (url.protocol === 'https:' && url.hostname === 't.me') send('telegram');
          else if (url.protocol === 'https:' && ['wa.me','api.whatsapp.com'].includes(url.hostname)) send('whatsapp');
        } catch {}
      }
    }
    // A link already counted above must not be counted twice as a custom CTA.
    if (!link && e.target.closest('[data-stats-event="cta"]')) send('cta');
  });
  window.addEventListener('storage', e => {
    if (e.key !== consentKey && e.key !== null) return;
    let accepted = false;
    try { accepted = localStorage.getItem(consentKey) === 'accepted'; } catch {}
    consent(accepted);
  });
  for (const method of ['pushState','replaceState']) {
    const original = history[method];
    history[method] = function(...args) { const result = original.apply(this, args); sync(); return result; };
  }
  window.addEventListener('popstate', sync);
  window.addEventListener('pageshow', sync);
  try { enabled = localStorage.getItem(consentKey) === 'accepted'; } catch {}
  sync();
})();
