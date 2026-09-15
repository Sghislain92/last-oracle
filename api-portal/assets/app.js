(() => {
  'use strict';

  // ================================================================
  // Routes façade — jamais les vrais chemins .php (voir .htaccess).
  // ================================================================
  const API = {
    login: '../api/partners/session/start',
    register: '../api/partners/session/create',
    me: '../api/partners/session/current',
    logout: '../api/partners/session/end',
    keys: '../api/partners/keys',
    deleteKey: '../api/partners/keys/delete',
    revoke: '../api/partners/keys/revoke',
    activity: '../api/partners/activity',
    errors: '../api/partners/incidents',
    usage: '../api/partners/usage',
    updateProfile: '../api/partners/profile/update',
    changePassword: '../api/partners/profile/password',
    reportError: '../api/partners/errors/report',
  };

  const PAGES = ['overview', 'keys', 'usage', 'activity', 'errors', 'settings', 'docs'];
  const PAGE_TITLES = {
    overview: "Vue d'ensemble", keys: 'Mes clés API', usage: 'Utilisation API',
    activity: 'Activité', errors: 'Erreurs', settings: 'Paramètres', docs: 'Documentation',
  };

  // ================================================================
  // Combinaisons de classes Tailwind réutilisées à plusieurs endroits —
  // centralisées ici pour ne jamais retaper une longue chaîne de classes
  // dans chaque gabarit, et pour n'avoir qu'un seul endroit à modifier.
  // ================================================================
  const CLS = {
    cardBase: 'rounded-2xl border border-line bg-card p-5 shadow-sm shadow-ink/5',
    statCard: 'rounded-2xl border border-line bg-card p-4',
    pill: 'inline-flex items-center rounded-full px-2.5 py-1 text-[9px] font-black',
    pillActive: 'bg-emerald-50 text-emerald-700',
    pillRevoked: 'bg-red-50 text-red-700',
    pillExpired: 'bg-amber-50 text-amber-700',
    linkDanger: 'text-[10px] font-extrabold text-red-600 hover:underline',
    linkMuted: 'text-[10px] font-extrabold text-muted hover:underline',
    emptyRow: 'p-8 text-center text-muted',
    eventIconGood: 'grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-emerald-50 text-emerald-700',
    eventIconBad: 'grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-red-50 text-red-700',
    eventStatusGood: 'shrink-0 rounded-full bg-emerald-50 px-2 py-1 text-[8px] font-black text-emerald-700',
    eventStatusBad: 'shrink-0 rounded-full bg-red-50 px-2 py-1 text-[8px] font-black text-red-700',
  };

  // ---------------- Utilitaires ----------------
  const $ = (s, root = document) => root.querySelector(s);
  const $$ = (s, root = document) => [...root.querySelectorAll(s)];
  let toastTimer;

  function notify(message, isError = false) {
    const el = $('#toast');
    el.textContent = message;
    el.className = `fixed bottom-4 right-4 left-4 z-[100] ml-auto max-w-sm rounded-lg px-4 py-3 text-[11px] font-extrabold text-white shadow-xl transition duration-200 ${isError ? 'bg-red-600' : 'bg-ink'} translate-y-0 opacity-100`;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
      el.className = 'fixed bottom-4 right-4 left-4 z-[100] ml-auto max-w-sm translate-y-5 opacity-0 pointer-events-none transition duration-200';
    }, 3600);
  }

  function esc(v) {
    return String(v ?? '').replace(/[&<>'"]/g, c => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;',
    }[c]));
  }

  function fmt(v) {
    if (!v) return '—';
    const d = new Date(String(v).replace(' ', 'T') + 'Z');
    return Number.isNaN(d.getTime()) ? v : d.toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' });
  }

  function fmtDate(v) {
    if (!v) return '—';
    const d = new Date(String(v).replace(' ', 'T') + 'Z');
    return Number.isNaN(d.getTime()) ? v : d.toLocaleDateString('fr-FR', { dateStyle: 'short' });
  }

  function icons() { window.lucide?.createIcons(); }

  // ================================================================
  // Empreinte navigateur — envoyée avec les actions sensibles (connexion,
  // inscription, création/révocation/suppression de clé) pour savoir
  // réellement depuis quel appareil/navigateur une action a été faite.
  // Best-effort : jamais bloquant si une valeur manque.
  // ================================================================
  function clientFingerprint() {
    try {
      return {
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || '',
        language: navigator.language || '',
        screen: `${screen.width}x${screen.height}`,
        platform: navigator.userAgentData?.platform || navigator.platform || '',
      };
    } catch (_) { return {}; }
  }

  async function api(url, opt = {}) {
    const r = await fetch(url, {
      credentials: 'same-origin', ...opt,
      headers: { 'Content-Type': 'application/json', ...(opt.headers || {}) },
    });
    let data = null;
    let parseFailed = false;
    try { data = await r.json(); } catch (_) { parseFailed = true; }
    if (!r.ok) {
      const e = new Error(data?.error || `Erreur serveur (${r.status})`);
      e.status = r.status;
      throw e;
    }
    // Statut 200 mais corps illisible (erreur PHP mêlée à la réponse,
    // sortie vide...) — ne JAMAIS renvoyer null silencieusement : ça
    // fait planter le premier accès à une propriété chez l'appelant avec
    // un message qui ne dit rien de la vraie cause. Une erreur claire ici
    // vaut mieux qu'un crash confus plus loin.
    if (parseFailed || data === null) {
      const e = new Error('Réponse du serveur illisible. Réessayez, ou signalez-le si ça persiste.');
      e.status = r.status;
      throw e;
    }
    return data;
  }

  // ================================================================
  // Modale de confirmation générique — remplace confirm() natif,
  // utilisée pour la révocation et la suppression de clé.
  // ================================================================
  function confirmModal({ title, message, confirmLabel = 'Confirmer', danger = false, icon = 'help-circle' }) {
    return new Promise(resolve => {
      const root = $('#modalRoot');
      const finish = v => { root.innerHTML = ''; resolve(v); };
      root.innerHTML = `
        <div id="modalBackdrop" class="fixed inset-0 z-[80] grid place-items-center bg-navy/80 p-5 backdrop-blur-sm">
          <div class="w-full max-w-sm rounded-2xl bg-card p-7 text-center shadow-2xl">
            <div class="mx-auto grid h-11 w-11 place-items-center rounded-xl ${danger ? 'bg-red-50 text-red-600' : 'bg-orange/10 text-orange-deep'}">
              <i data-lucide="${icon}"></i>
            </div>
            <h2 class="mt-3 font-serif text-2xl">${esc(title)}</h2>
            <p class="mt-2 text-xs leading-relaxed text-muted">${esc(message)}</p>
            <div class="mt-5 flex gap-2.5">
              <button id="modalCancel" class="flex-1 rounded-lg border border-line bg-card px-4 py-3 text-[11px] font-extrabold text-ink hover:bg-orange/10">Annuler</button>
              <button id="modalOk" class="flex-1 rounded-lg px-4 py-3 text-[11px] font-extrabold text-white shadow-md transition hover:-translate-y-0.5 ${danger ? 'bg-red-600 shadow-red-600/25' : 'bg-orange shadow-orange/25'}">${esc(confirmLabel)}</button>
            </div>
          </div>
        </div>`;
      $('#modalBackdrop').addEventListener('click', e => { if (e.target.id === 'modalBackdrop') finish(false); });
      $('#modalCancel').onclick = () => finish(false);
      $('#modalOk').onclick = () => finish(true);
      icons();
    });
  }

  // ================================================================
  // Modale de DÉTAIL (lecture seule, complète — utilisée par les
  // tableaux clés/usage et les listes activité/erreurs). Sur mobile et
  // tablette, ces tableaux n'affichent plus qu'un minimum de colonnes
  // (le reste défilait horizontalement — pénible), chaque ligne étant
  // cliquable pour voir l'intégralité des informations ici.
  // ================================================================
  function detailModal({ title, icon = 'info', rows, actions = [] }) {
    const root = $('#modalRoot');
    const close = () => { root.innerHTML = ''; };
    root.innerHTML = `
      <div id="detailBackdrop" class="fixed inset-0 z-[80] grid place-items-center bg-navy/80 p-4 backdrop-blur-sm">
        <div class="max-h-[85vh] w-full max-w-md overflow-y-auto rounded-2xl bg-card p-6 shadow-2xl">
          <div class="flex items-start justify-between gap-3">
            <div class="flex items-center gap-2.5">
              <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-orange/10 text-orange-deep"><i data-lucide="${icon}"></i></span>
              <h2 class="font-serif text-xl leading-tight">${esc(title)}</h2>
            </div>
            <button id="detailClose" class="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-muted hover:bg-orange/10"><i data-lucide="x" class="h-[18px] w-[18px]"></i></button>
          </div>
          <div class="mt-4 grid gap-3 border-t border-line pt-4">
            ${rows.map(([label, value]) => `
              <div class="grid grid-cols-[auto_1fr] items-start gap-3">
                <span class="pt-0.5 text-[9px] font-black uppercase tracking-wide text-muted">${esc(label)}</span>
                <span class="text-right text-[11px] leading-relaxed text-ink break-words">${value}</span>
              </div>
            `).join('')}
          </div>
          ${actions.length ? `<div class="mt-5 flex flex-wrap gap-2 border-t border-line pt-4">${actions.map((a, i) => `<button data-modal-action="${i}" class="${a.className}">${esc(a.label)}</button>`).join('')}</div>` : ''}
        </div>
      </div>`;
    $('#detailBackdrop').addEventListener('click', e => { if (e.target.id === 'detailBackdrop') close(); });
    $('#detailClose').onclick = close;
    actions.forEach((a, i) => { $(`[data-modal-action="${i}"]`).onclick = () => { close(); a.onClick(); }; });
    icons();
  }

  function proxyBadge(isProxy) {
    return Number(isProxy)
      ? '<span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-1 text-[9px] font-black text-amber-700">VPN/proxy probable</span>'
      : '<span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-1 text-[9px] font-black text-emerald-700">Connexion directe</span>';
  }

  function locationLine(row) {
    const country = row.country ? `${esc(row.country)}${row.country_code ? ' (' + esc(row.country_code) + ')' : ''}` : 'Pays inconnu';
    return `${esc(row.ip_address || '—')} · ${country}<br>${proxyBadge(row.is_proxy)}`;
  }

  // ================================================================
  // Auth (connexion / inscription)
  // ================================================================
  function showAuth(tab = 'login') {
    $('#authView').classList.remove('hidden');
    $('#appView').classList.add('hidden');
    $$('.auth-tab').forEach(b => {
      const active = b.dataset.authTab === tab;
      b.classList.toggle('text-ink', active);
      b.classList.toggle('border-orange', active);
      b.classList.toggle('text-slate-400', !active);
      b.classList.toggle('border-transparent', !active);
    });
    $('#loginPanel').classList.toggle('hidden', tab !== 'login');
    $('#registerPanel').classList.toggle('hidden', tab !== 'register');
    icons();
  }

  function setError(id, msg) {
    const el = $(id);
    el.textContent = msg;
    el.classList.toggle('hidden', !msg);
  }

  async function login(e) {
    e.preventDefault();
    setError('#loginError', '');
    const form = e.currentTarget;
    const b = form.querySelector('button');
    b.disabled = true; b.textContent = 'Connexion…';
    try {
      const r = await api(API.login, {
        method: 'POST',
        body: JSON.stringify({ email: $('#loginEmail').value.trim(), password: $('#loginPassword').value, clientInfo: clientFingerprint() }),
      });
      showApp(r.user);
      notify('Connexion réussie.');
    } catch (err) {
      setError('#loginError', err.message);
    } finally {
      b.disabled = false;
      b.innerHTML = '<i data-lucide="log-in"></i> Se connecter';
      icons();
    }
  }

  async function register(e) {
    e.preventDefault();
    setError('#registerError', '');
    const form = e.currentTarget;
    const b = form.querySelector('button');
    b.disabled = true; b.textContent = 'Création…';
    try {
      const r = await api(API.register, {
        method: 'POST',
        body: JSON.stringify({
          companyName: $('#registerCompany').value.trim(),
          contactName: $('#registerName').value.trim(),
          email: $('#registerEmail').value.trim(),
          password: $('#registerPassword').value,
          clientInfo: clientFingerprint(),
        }),
      });
      showApp(r.user);
      notify('Votre espace partenaire est créé.');
    } catch (err) {
      setError('#registerError', err.message);
    } finally {
      b.disabled = false;
      b.innerHTML = '<i data-lucide="user-plus"></i> Créer mon espace';
      icons();
    }
  }

  async function logout() {
    try { await api(API.logout, { method: 'POST' }); }
    finally {
      location.hash = '';
      showAuth();
      notify('Vous êtes déconnecté.');
    }
  }

  // ================================================================
  // Coquille applicative (une fois connecté)
  // ================================================================
  let currentUser = null;

  function showApp(user) {
    currentUser = user;
    $('#authView').classList.add('hidden');
    $('#appView').classList.remove('hidden');
    const initials = (user.contactName || user.companyName || 'P')
      .split(/\s+/).slice(0, 2).map(x => x[0]).join('').toUpperCase();
    $$('.js-avatar').forEach(el => { el.textContent = initials; });
    $$('.js-company').forEach(el => { el.textContent = user.companyName; });
    $$('.js-email').forEach(el => { el.textContent = user.email; });
    $('#helloName').textContent = (user.contactName || user.companyName).split(' ')[0];
    icons();
  }

  async function boot() {
    try {
      const r = await api(API.me);
      showApp(r.user);
      const requested = location.hash.replace('#', '');
      showPage(PAGES.includes(requested) ? requested : 'overview');
    } catch (_) {
      showAuth('login');
    } finally {
      // Le chargeur plein écran (visible par défaut dans le HTML, AVANT
      // que ce fetch ne réponde) ne disparaît qu'une fois qu'on sait
      // réellement quel écran afficher — jamais de "flash" de la page de
      // connexion pendant une fraction de seconde chez un agent déjà
      // connecté, le temps que la vérification de session réponde.
      $('#bootLoader').classList.add('hidden');
    }
    icons();
  }

  // ---------------- Navigation : tiroir mobile + routage par hash ----------------
  function openDrawer() {
    $('#mobileDrawer').classList.remove('translate-x-full');
    $('#mobileDrawer').classList.add('translate-x-0');
    $('#drawerBackdrop').classList.remove('opacity-0', 'invisible');
    $('#drawerBackdrop').classList.add('opacity-100', 'visible');
  }
  function closeDrawer() {
    $('#mobileDrawer').classList.add('translate-x-full');
    $('#mobileDrawer').classList.remove('translate-x-0');
    $('#drawerBackdrop').classList.add('opacity-0', 'invisible');
    $('#drawerBackdrop').classList.remove('opacity-100', 'visible');
  }

  function setNavActive(name) {
    // Chaque bouton de nav porte data-active="classe1 classe2..." — les
    // classes Tailwind à appliquer quand il est actif. Généralisable au
    // sidebar desktop, au tiroir mobile et à la barre du bas sans logique
    // spécifique à chacun (voir index.php).
    $$('.nav-item[data-page]').forEach(b => {
      const classes = (b.dataset.active || '').split(' ').filter(Boolean);
      const active = b.dataset.page === name;
      classes.forEach(c => b.classList.toggle(c, active));
    });
  }

  function showPage(name) {
    if (!PAGES.includes(name)) name = 'overview';
    $('#secretBox').classList.add('hidden');
    $$('.app-page').forEach(p => p.classList.toggle('hidden', p.id !== `page-${name}`));
    setNavActive(name);
    $('#pageTitle').textContent = PAGE_TITLES[name];
    $('#pageTitleMobile').textContent = PAGE_TITLES[name];
    closeDrawer();

    if (location.hash !== `#${name}`) window.history.replaceState(null, '', `#${name}`);

    if (name === 'overview') loadOverview();
    if (name === 'keys') loadKeys();
    if (name === 'usage') loadUsage();
    if (name === 'activity') loadActivity();
    if (name === 'errors') loadErrors();
    if (name === 'settings') loadSettings();
    icons();
  }

  window.addEventListener('hashchange', () => {
    const h = location.hash.replace('#', '');
    if (PAGES.includes(h)) showPage(h);
  });

  // ================================================================
  // Vue d'ensemble — stats + mini graphique (7 derniers jours)
  // ================================================================
  function buildUsageChart(calls) {
    const days = [];
    for (let i = 6; i >= 0; i--) {
      const d = new Date();
      d.setDate(d.getDate() - i);
      days.push({ key: d.toISOString().slice(0, 10), label: d.toLocaleDateString('fr-FR', { weekday: 'short' }), found: 0, notFound: 0 });
    }
    const byDay = Object.fromEntries(days.map(d => [d.key, d]));
    calls.forEach(c => {
      const key = String(c.created_at).slice(0, 10);
      const bucket = byDay[key];
      if (!bucket) return;
      if (Number(c.found)) bucket.found++; else bucket.notFound++;
    });
    const max = Math.max(1, ...days.map(d => d.found + d.notFound));
    const w = 560, h = 160, padB = 22, barGap = 10;
    const barW = (w - barGap * (days.length - 1)) / days.length;
    let bars = '';
    days.forEach((d, i) => {
      const x = i * (barW + barGap);
      const totalH = ((d.found + d.notFound) / max) * (h - padB - 10);
      const foundH = max ? (d.found / max) * (h - padB - 10) : 0;
      const notFoundH = totalH - foundH;
      const yTop = h - padB - totalH;
      if (d.notFound) bars += `<rect class="fill-amber-400" x="${x}" y="${yTop}" width="${barW}" height="${notFoundH}" rx="3"></rect>`;
      if (d.found) bars += `<rect class="fill-emerald-600" x="${x}" y="${yTop + notFoundH}" width="${barW}" height="${foundH}" rx="3"></rect>`;
      bars += `<text class="fill-muted text-[8px]" x="${x + barW / 2}" y="${h - 6}" text-anchor="middle">${esc(d.label)}</text>`;
    });
    return `<svg class="block h-auto w-full" viewBox="0 0 ${w} ${h}" xmlns="http://www.w3.org/2000/svg">
      <line class="stroke-line" x1="0" y1="${h - padB}" x2="${w}" y2="${h - padB}"></line>
      ${bars}
    </svg>`;
  }

  function statCard(label, value, hint, valueClass = '') {
    return `
      <div class="${CLS.statCard}">
        <span class="text-[10px] text-muted">${esc(label)}</span>
        <b class="mt-2 block font-serif text-3xl leading-none ${valueClass}">${esc(value)}</b>
        <small class="mt-1 block text-[9px] text-muted">${esc(hint)}</small>
      </div>`;
  }

  async function loadOverview() {
    const grid = $('#overviewStats');
    const chartBox = $('#overviewChart');
    // Promise.allSettled plutôt que Promise.all : si UN SEUL de ces trois
    // appels échoue (endpoint pas encore déployé, panne ponctuelle...),
    // les deux autres doivent quand même s'afficher normalement — un
    // service dégradé sur une carte, pas une page entièrement vide.
    const [keysR, usageR, errorsR] = await Promise.allSettled([api(API.keys), api(API.usage), api(API.errors)]);

    const keys = keysR.status === 'fulfilled' ? (keysR.value.keys || []) : null;
    const usage = usageR.status === 'fulfilled' ? usageR.value : null;
    const errorsList = errorsR.status === 'fulfilled' ? (errorsR.value.errors || []) : null;

    const active = keys ? keys.filter(k => Number(k.active) && !isExpired(k)) : null;
    const summary = usage ? (usage.summary || { total: 0, found: 0, byKey: [] }) : null;

    const na = (label, hint) => `<div class="${CLS.statCard}"><span class="text-[10px] text-muted">${esc(label)}</span><b class="mt-2 block font-serif text-2xl text-slate-300">—</b><small class="mt-1 block text-[9px] text-red-600">${esc(hint)}</small></div>`;

    grid.innerHTML = [
      keys ? statCard('Clés actives', active.length, `sur ${keys.length} au total`) : na('Clés actives', 'Indisponible'),
      usage ? statCard('Appels API (total)', summary.total, 'toutes clés confondues') : na('Appels API (total)', 'Indisponible'),
      usage ? statCard('Taux de succès', `${summary.total ? Math.round((summary.found / summary.total) * 100) : 0}%`, `${summary.found} résultat(s) trouvé(s)`) : na('Taux de succès', 'Indisponible'),
      errorsList !== null ? statCard('Erreurs récentes', errorsList.length, "voir l'onglet Erreurs", errorsList.length ? 'text-red-600' : 'text-emerald-700') : na('Erreurs récentes', 'Indisponible'),
    ].join('');

    chartBox.innerHTML = usage
      ? buildUsageChart(usage.calls || [])
      : `<div class="${CLS.emptyRow} text-red-600">Graphique indisponible (${esc(usageR.reason?.message || 'erreur inconnue')}).</div>`;
    icons();
  }

  // ================================================================
  // Mes clés API — création (avec durée de vie optionnelle), révocation,
  // suppression définitive.
  // ================================================================
  function isExpired(k) {
    if (!k.expires_at) return false;
    return new Date(String(k.expires_at).replace(' ', 'T') + 'Z').getTime() < Date.now();
  }

  function keyStatusBadge(k) {
    if (!Number(k.active)) return `<span class="${CLS.pill} ${CLS.pillRevoked}">Révoquée</span>`;
    if (isExpired(k)) return `<span class="${CLS.pill} ${CLS.pillExpired}">Expirée</span>`;
    return `<span class="${CLS.pill} ${CLS.pillActive}">Active</span>`;
  }

  async function loadKeys() {
    const body = $('#keysBody');
    if (!body) return;
    body.innerHTML = `<tr><td colspan="7" class="${CLS.emptyRow}">Chargement…</td></tr>`;
    try {
      const r = await api(API.keys);
      const keys = r.keys || [];
      if (!keys.length) {
        body.innerHTML = `<tr><td colspan="7" class="${CLS.emptyRow}"><i data-lucide="key-round" class="mx-auto mb-2 block h-6 w-6 text-slate-300"></i>Aucune clé pour le moment. Générez votre première clé ci-dessus.</td></tr>`;
        icons();
        return;
      }
      body.innerHTML = keys.map(k => `
        <tr class="cursor-pointer border-b border-line/70 last:border-0 hover:bg-paper" data-open-key="${esc(k.id)}">
          <td class="px-3 py-3 text-[10px]"><b class="block text-[10px]">${esc(k.label)}</b><small class="text-muted">${esc(fmt(k.created_at))}</small></td>
          <td class="hidden px-3 py-3 text-[10px] lg:table-cell"><code class="text-orange-deep">${esc(k.key_prefix)}••••••</code></td>
          <td class="hidden px-3 py-3 text-[10px] lg:table-cell">${esc(k.rate_limit_per_minute)} / min</td>
          <td class="hidden px-3 py-3 text-[10px] lg:table-cell">${k.expires_at ? esc(fmtDate(k.expires_at)) : 'Jamais'}</td>
          <td class="hidden px-3 py-3 text-[10px] lg:table-cell">${esc(fmt(k.last_used_at))}<small class="block text-muted">${esc(k.request_count || 0)} appel(s)</small></td>
          <td class="px-3 py-3">${keyStatusBadge(k)}</td>
          <td class="px-3 py-3 text-right"><i data-lucide="chevron-right" class="inline-block h-4 w-4 text-muted"></i></td>
        </tr>
      `).join('');
      $$('[data-open-key]', body).forEach(tr => tr.addEventListener('click', () => openKeyDetail(keys.find(k => k.id === tr.dataset.openKey))));
      icons();
    } catch (e) {
      if (e.status === 401) showAuth();
      else body.innerHTML = `<tr><td colspan="7" class="${CLS.emptyRow} text-red-600">${esc(e.message)}</td></tr>`;
    }
  }

  function openKeyDetail(k) {
    if (!k) return;
    const actions = [];
    if (Number(k.active)) actions.push({ label: 'Révoquer', className: `${CLS.linkMuted} rounded-lg border border-line px-3 py-2`, onClick: () => revokeKey(k.id, k.label) });
    actions.push({ label: 'Supprimer', className: `${CLS.linkDanger} rounded-lg border border-red-200 px-3 py-2`, onClick: () => deleteKey(k.id, k.label) });
    detailModal({
      title: k.label,
      icon: 'key-round',
      rows: [
        ['Préfixe', `<code class="text-orange-deep">${esc(k.key_prefix)}••••••</code>`],
        ['Limite', `${esc(k.rate_limit_per_minute)} / min`],
        ['IP autorisée', k.allowed_ip ? esc(k.allowed_ip) : 'Toutes'],
        ['Expiration', k.expires_at ? esc(fmtDate(k.expires_at)) : 'Jamais'],
        ['État', keyStatusBadge(k)],
        ['Créée le', esc(fmt(k.created_at))],
        ['Dernier appel', esc(fmt(k.last_used_at))],
        ['Appels enregistrés', esc(k.request_count || 0)],
        ['Dernière IP appelante', esc(k.last_used_ip || '—')],
      ],
      actions,
    });
  }

  async function createKey(e) {
    e.preventDefault();
    const form = e.currentTarget;
    const b = form.querySelector('button[type="submit"]');
    b.disabled = true;
    try {
      const r = await api(API.keys, {
        method: 'POST',
        body: JSON.stringify({
          label: $('#keyLabel').value.trim(),
          rateLimitPerMinute: Number($('#rateLimit').value),
          allowedIp: $('#allowedIp').value.trim(),
          expiresInDays: Number($('#keyTtl').value),
          clientInfo: clientFingerprint(),
        }),
      });
      $('#newKey').textContent = r.apiKey;
      $('#secretBox').classList.remove('hidden');
      form.reset();
      $('#rateLimit').value = 30;
      $('#keyTtl').value = '0';
      notify('Clé générée. Copiez-la maintenant.');
      loadKeys();
    } catch (err) {
      notify(err.message, true);
    } finally {
      b.disabled = false;
      b.innerHTML = '<i data-lucide="plus"></i> Générer';
      icons();
    }
  }

  async function revokeKey(id, label) {
    const ok = await confirmModal({
      title: 'Révoquer cette clé ?',
      message: `« ${label} » cessera immédiatement de fonctionner. Cette action ne peut pas être annulée depuis le portail.`,
      confirmLabel: 'Révoquer', danger: true, icon: 'shield-off',
    });
    if (!ok) return;
    try {
      await api(API.revoke, { method: 'POST', body: JSON.stringify({ id, clientInfo: clientFingerprint() }) });
      notify('Clé révoquée.');
      loadKeys();
    } catch (e) { notify(e.message, true); }
  }

  async function deleteKey(id, label) {
    const ok = await confirmModal({
      title: 'Supprimer définitivement cette clé ?',
      message: `« ${label} » sera effacée pour toujours. L'historique de ses appels reste conservé dans "Utilisation API".`,
      confirmLabel: 'Supprimer', danger: true, icon: 'trash-2',
    });
    if (!ok) return;
    try {
      await api(API.deleteKey, { method: 'POST', body: JSON.stringify({ id, clientInfo: clientFingerprint() }) });
      notify('Clé supprimée.');
      loadKeys();
    } catch (e) { notify(e.message, true); }
  }

  // ================================================================
  // Utilisation API — détail complet des appels (api_key_audit)
  // ================================================================
  let usageCache = [];

  function renderUsageTable(filterKeyId) {
    const body = $('#usageBody');
    const rows = filterKeyId ? usageCache.filter(c => c.key_id === filterKeyId) : usageCache;
    if (!rows.length) {
      body.innerHTML = `<tr><td colspan="7" class="${CLS.emptyRow}"><i data-lucide="inbox" class="mx-auto mb-2 block h-6 w-6 text-slate-300"></i>Aucun appel enregistré pour le moment.</td></tr>`; icons();
      return;
    }
    body.innerHTML = rows.slice(0, 200).map((c, i) => `
      <tr class="cursor-pointer border-b border-line/70 last:border-0 hover:bg-paper" data-open-usage="${i}">
        <td class="px-3 py-3 text-[10px]">${esc(fmt(c.created_at))}</td>
        <td class="hidden px-3 py-3 text-[10px] lg:table-cell"><b class="block">${esc(c.key_label)}</b><small class="text-muted">${esc(c.key_prefix)}••••••</small></td>
        <td class="hidden px-3 py-3 text-[10px] lg:table-cell">${esc(String(c.query_type).toUpperCase())}</td>
        <td class="hidden px-3 py-3 text-[10px] lg:table-cell"><code class="text-orange-deep">${esc(c.query_value)}</code></td>
        <td class="px-3 py-3">${Number(c.found) ? `<span class="${CLS.pill} ${CLS.pillActive}">Trouvé</span>` : `<span class="${CLS.pill} ${CLS.pillRevoked}">Introuvable</span>`}</td>
        <td class="hidden px-3 py-3 text-[10px] lg:table-cell">${esc(c.ip_address || '—')}</td>
        <td class="px-3 py-3 text-right"><i data-lucide="chevron-right" class="inline-block h-4 w-4 text-muted"></i></td>
      </tr>
    `).join('');
    $$('[data-open-usage]', body).forEach(tr => tr.addEventListener('click', () => openUsageDetail(rows[Number(tr.dataset.openUsage)])));
    icons();
  }

  function openUsageDetail(c) {
    if (!c) return;
    detailModal({
      title: `${String(c.query_type).toUpperCase()} · ${c.query_value}`,
      icon: 'bar-chart-3',
      rows: [
        ['Date', esc(fmt(c.created_at))],
        ['Clé utilisée', `${esc(c.key_label)} <code class="text-orange-deep">(${esc(c.key_prefix)}••••••)</code>`],
        ['Résultat', Number(c.found) ? `<span class="${CLS.pill} ${CLS.pillActive}">Trouvé</span>` : `<span class="${CLS.pill} ${CLS.pillRevoked}">Introuvable</span>`],
        ['Origine', locationLine(c)],
        ['Navigateur / agent', esc(c.user_agent || 'Non transmis (appel serveur direct)')],
      ],
    });
  }

  async function loadUsage() {
    const body = $('#usageBody');
    if (!body) return;
    body.innerHTML = `<tr><td colspan="7" class="${CLS.emptyRow}">Chargement…</td></tr>`;
    try {
      const r = await api(API.usage);
      usageCache = r.calls || [];
      const summary = r.summary || { total: 0, found: 0, byKey: [] };
      $('#usageSummary').innerHTML = [
        statCard('Appels enregistrés', summary.total, 'toutes clés confondues'),
        statCard('Trouvés', summary.found, `${summary.total ? Math.round((summary.found / summary.total) * 100) : 0}% de succès`),
        statCard('Clés utilisées', summary.byKey.length, 'ayant reçu au moins un appel'),
      ].join('');
      const select = $('#usageKeyFilter');
      const keysRes = await api(API.keys);
      select.innerHTML = '<option value="">Toutes les clés</option>' +
        (keysRes.keys || []).map(k => `<option value="${esc(k.id)}">${esc(k.label)}</option>`).join('');
      renderUsageTable('');
    } catch (e) {
      body.innerHTML = `<tr><td colspan="7" class="${CLS.emptyRow} text-red-600">${esc(e.message)}</td></tr>`;
    }
  }

  // ================================================================
  // Activité (actions du compte) & Erreurs
  // ================================================================
  function eventRow(e, i, error = false) {
    return `
      <div class="flex cursor-pointer items-center gap-3 border-b border-line/70 py-3.5 last:border-0 hover:bg-paper" data-open-event="${i}">
        <span class="${error ? CLS.eventIconBad : CLS.eventIconGood}">
          <i data-lucide="${error ? 'triangle-alert' : 'activity'}"></i>
        </span>
        <div class="min-w-0 flex-1">
          <b class="text-[11px]">${esc(e.type)}</b>
          <p class="mt-1 truncate text-[10px] text-muted">${esc(e.message || '—')}</p>
          <small class="mt-1 block text-[8px] text-muted">${esc(fmt(e.created_at))} · ${esc(e.country || 'pays inconnu')}</small>
        </div>
        <i data-lucide="chevron-right" class="h-4 w-4 shrink-0 text-muted"></i>
      </div>`;
  }

  function prettyMetadata(raw) {
    if (!raw) return null;
    try {
      const obj = typeof raw === 'string' ? JSON.parse(raw) : raw;
      return Object.entries(obj).map(([k, v]) => `<div><span class="text-muted">${esc(k)}</span> : ${esc(typeof v === 'object' ? JSON.stringify(v) : v)}</div>`).join('');
    } catch (_) { return esc(String(raw)); }
  }

  function openEventDetail(e) {
    if (!e) return;
    const meta = prettyMetadata(e.metadata);
    detailModal({
      title: e.type,
      icon: e.status === 'error' ? 'triangle-alert' : 'activity',
      rows: [
        ['Message', esc(e.message || '—')],
        ['Date', esc(fmt(e.created_at))],
        ['Statut', esc(e.status)],
        ['Origine', locationLine(e)],
        ['Navigateur / agent', esc(e.user_agent || '—')],
        ...(meta ? [['Détails techniques', `<div class="text-left font-mono text-[10px] leading-relaxed">${meta}</div>`]] : []),
      ],
    });
  }

  let activityCache = [];
  let errorsCache = [];

  async function loadActivity() {
    const el = $('#activityList');
    if (!el) return;
    try {
      const r = await api(API.activity);
      activityCache = r.events || [];
      el.innerHTML = activityCache.length
        ? activityCache.map((e, i) => eventRow(e, i)).join('')
        : `<div class="${CLS.emptyRow}">Aucun événement enregistré.</div>`;
      $$('[data-open-event]', el).forEach(row => row.addEventListener('click', () => openEventDetail(activityCache[Number(row.dataset.openEvent)])));
      icons();
    } catch (e) {
      el.innerHTML = `<div class="${CLS.emptyRow} text-red-600">${esc(e.message)}</div>`;
    }
  }

  async function loadErrors() {
    const el = $('#errorsList');
    if (!el) return;
    try {
      const r = await api(API.errors);
      errorsCache = r.errors || [];
      el.innerHTML = errorsCache.length
        ? errorsCache.map((e, i) => eventRow(e, i, true)).join('')
        : `<div class="${CLS.emptyRow} text-emerald-700"><i data-lucide="check-circle-2" class="inline"></i> Aucune erreur récente.</div>`;
      $$('[data-open-event]', el).forEach(row => row.addEventListener('click', () => openEventDetail(errorsCache[Number(row.dataset.openEvent)])));
      icons();
    } catch (e) {
      el.innerHTML = `<div class="${CLS.emptyRow} text-red-600">${esc(e.message)}</div>`;
    }
  }

  // ================================================================
  // Paramètres — profil & sécurité
  // ================================================================
  function loadSettings() {
    if (!currentUser) return;
    $('#settingsCompany').value = currentUser.companyName || '';
    $('#settingsName').value = currentUser.contactName || '';
    $('#settingsEmail').value = currentUser.email || '';
    setError('#profileError', '');
    setError('#passwordError', '');
  }

  async function updateProfile(e) {
    e.preventDefault();
    setError('#profileError', '');
    const b = e.currentTarget.querySelector('button');
    b.disabled = true;
    try {
      const r = await api(API.updateProfile, {
        method: 'POST',
        body: JSON.stringify({
          companyName: $('#settingsCompany').value.trim(),
          contactName: $('#settingsName').value.trim(),
          email: $('#settingsEmail').value.trim(),
        }),
      });
      currentUser = { ...currentUser, ...r.user };
      $$('.js-company').forEach(el => { el.textContent = currentUser.companyName; });
      $$('.js-email').forEach(el => { el.textContent = currentUser.email; });
      notify('Profil mis à jour.');
    } catch (err) {
      setError('#profileError', err.message);
    } finally {
      b.disabled = false;
      icons();
    }
  }

  async function changePassword(e) {
    e.preventDefault();
    setError('#passwordError', '');
    const current = $('#currentPassword').value;
    const next = $('#newPassword').value;
    const confirmVal = $('#confirmNewPassword').value;
    if (next.length < 10) { setError('#passwordError', 'Le nouveau mot de passe doit contenir au moins 10 caractères.'); return; }
    if (next !== confirmVal) { setError('#passwordError', 'La confirmation ne correspond pas.'); return; }
    const b = e.currentTarget.querySelector('button');
    b.disabled = true;
    try {
      await api(API.changePassword, {
        method: 'POST',
        body: JSON.stringify({ currentPassword: current, newPassword: next }),
      });
      e.currentTarget.reset();
      notify('Mot de passe modifié.');
    } catch (err) {
      setError('#passwordError', err.message);
    } finally {
      b.disabled = false;
      icons();
    }
  }

  // ================================================================
  // Câblage initial
  // ================================================================
  $$('.auth-tab').forEach(b => b.addEventListener('click', () => showAuth(b.dataset.authTab)));
  $('#loginForm').addEventListener('submit', login);
  $('#registerForm').addEventListener('submit', register);
  $('#keyForm').addEventListener('submit', createKey);
  $('#profileForm').addEventListener('submit', updateProfile);
  $('#passwordForm').addEventListener('submit', changePassword);

  $$('.nav-item[data-page]').forEach(b => b.addEventListener('click', () => { location.hash = b.dataset.page; showPage(b.dataset.page); }));
  $$('.js-logout').forEach(b => b.addEventListener('click', logout));
  $$('.js-refresh').forEach(b => b.addEventListener('click', () => showPage(b.dataset.page)));

  $('#hamburgerBtn').addEventListener('click', openDrawer);
  $('#drawerClose').addEventListener('click', closeDrawer);
  $('#drawerBackdrop').addEventListener('click', closeDrawer);

  $('#usageKeyFilter').addEventListener('change', e => renderUsageTable(e.target.value));

  $('#copyNewKey').addEventListener('click', async () => {
    try { await navigator.clipboard.writeText($('#newKey').textContent); notify('Clé copiée.'); }
    catch (_) { notify('Copie impossible.', true); }
  });

  $$('[data-copy-target]').forEach(btn => {
    btn.addEventListener('click', async () => {
      try { await navigator.clipboard.writeText($(btn.dataset.copyTarget).textContent.trim()); notify('Copié.'); }
      catch (_) { notify('Copie impossible.', true); }
    });
  });

  // ================================================================
  // Capture globale des erreurs JS — sans ça, un plantage côté client
  // (comme ceux qui ont motivé cette section) ne laissait AUCUNE trace
  // dans l'onglet Erreurs : il restait visible uniquement dans la
  // console du navigateur de l'agent, invisible à toute vérification a
  // posteriori. Ne journalise que si un partenaire est bien connecté
  // (voir log-client-error.php) — le serveur ignore silencieusement le
  // reste, jamais d'erreur secondaire à cause d'un rapport d'erreur.
  // ================================================================
  function reportClientError(message, stack) {
    try {
      api(API.reportError, {
        method: 'POST',
        body: JSON.stringify({ message: String(message || 'Erreur inconnue').slice(0, 500), stack: String(stack || '').slice(0, 1000), url: location.href, clientInfo: clientFingerprint() }),
      }).catch(() => {});
    } catch (_) { /* jamais bloquant */ }
  }
  window.addEventListener('error', e => reportClientError(e.message, e.error?.stack));
  window.addEventListener('unhandledrejection', e => reportClientError(e.reason?.message || String(e.reason), e.reason?.stack));

  boot();
})();
