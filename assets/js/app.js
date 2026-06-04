/* TT Team Planner — Direction A "Coach"
 * Vanilla JS · No build step · No dependencies
 * Tokens from app-coach.jsx (Claude Design handoff)
 */
(function (w, d) {
  'use strict';

  // ═══════════════════════════════════════════
  // CONFIG — injected by wp_localize_script
  // ═══════════════════════════════════════════
  var cfg = w.TTPConfig || {};
  var API = cfg.apiBase || '/wp-json/ttp/v1';
  var NONCE = cfg.nonce || '';

  // ═══════════════════════════════════════════
  // DESIGN TOKENS — Direction A "Coach"
  // ═══════════════════════════════════════════
  var C = {
    bg: '#f5f7fb',      bgD: '#0b1220',
    surf: '#ffffff',    surfD: '#131c2e',
    surf2: '#eef2f8',   surf2D: '#1b263d',
    bord: '#e2e8f0',    bordD: '#243049',
    ink: '#0f172a',     inkD: '#e7eaf3',
    ink2: '#475569',    ink2D: '#94a3b8',
    pri: '#2563eb',     priInk: '#1d4ed8',
    priSoft: '#dbeafe', priSoftD: '#1e3a5f',
    ok: '#16a34a',      okSoft: '#dcfce7',
    warn: '#ca8a04',    warnSoft: '#fef3c7',
    err: '#dc2626',     errSoft: '#fee2e2',
    purp: '#7c3aed',    purpSoft: '#ede9fe'
  };

  function tk(dark) {
    return {
      bg:      dark ? C.bgD    : C.bg,
      surf:    dark ? C.surfD  : C.surf,
      surf2:   dark ? C.surf2D : C.surf2,
      bord:    dark ? C.bordD  : C.bord,
      ink:     dark ? C.inkD   : C.ink,
      ink2:    dark ? C.ink2D  : C.ink2,
      priSoft: dark ? C.priSoftD : C.priSoft
    };
  }

  // ═══════════════════════════════════════════
  // STATE
  // ═══════════════════════════════════════════
  var S = {
    screen: 'dashboard', tab: 'dashboard',
    dark: false, phase: cfg.phase || 0,
    journeeN: 3, playerId: null,
    activeTeamI: 0,
    offline: !navigator.onLine,
    players: [], availabilities: [], compositions: [],
    ruleAlerts: [],
    loading: true, syncing: false, loadError: null,
    picker: null, pickerQ: '',
    searchQ: '', playerFilter: 'all',
    playerEdit: false, editPhone: '', editNotes: '', editSaving: false,
    teams: cfg.teams || [],
    journees: buildJournees(cfg.phase || 0)
  };

  function todayISO() {
    var d = new Date();
    return d.getFullYear() + '-' +
      String(d.getMonth() + 1).padStart(2, '0') + '-' +
      String(d.getDate()).padStart(2, '0');
  }

  function buildJournees(phase) {
    if (phase === undefined) phase = S ? S.phase : (cfg.phase || 0);
    var allDates = cfg.journeeDates || [];
    // journeeDates est maintenant [[p1 dates], [p2 dates]]
    var phDates = Array.isArray(allDates[phase]) ? allDates[phase] : allDates;
    var today = todayISO();
    var foundNext = false;
    return Array.from({ length: 7 }, function (_, i) {
      var date = phDates[i] || '';
      var done = !!(date && date < today);
      var current = false;
      if (!done && date && !foundNext) { current = true; foundNext = true; }
      return { n: i + 1, date: date, done: done, complete: 0, alerts: 0, current: current };
    });
  }

  function findGlobalNext() {
    var allDates = cfg.journeeDates || [];
    if (!Array.isArray(allDates[0])) return null;
    var today = todayISO();
    var best = null;
    for (var ph = 0; ph < 2; ph++) {
      var dates = allDates[ph] || [];
      for (var i = 0; i < dates.length; i++) {
        var d = dates[i];
        if (d && d >= today && (!best || d < best.date)) {
          best = { phase: ph + 1, round: i + 1, date: d };
        }
      }
    }
    return best;
  }

  // ═══════════════════════════════════════════
  // UTILS
  // ═══════════════════════════════════════════
  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function initials(name) {
    return (name || '').split(' ').filter(Boolean).map(function (w) { return w[0] || ''; }).join('').slice(0, 2).toUpperCase() || '?';
  }

  function avStatus(av) {
    return { available: 'Dispo', unavailable: 'Indispo', uncertain: 'Incertain', unknown: 'Non renseigné' }[av] || '—';
  }
  function avEmoji(av) {
    return { available: '✅', unavailable: '🚫', uncertain: '❓', unknown: '⚪' }[av] || '⚪';
  }
  function avColor(av, dark) {
    if (av === 'available') return C.ok;
    if (av === 'unavailable') return C.err;
    if (av === 'uncertain') return C.warn;
    return tk(dark).ink2;
  }

  function smsHref(phone, firstName, jn, date) {
    var tmpl = (cfg.smsTemplates && cfg.smsTemplates.availability) ||
      'Bonjour {prenom}, es-tu dispo pour la J{journee} ({date}) ?';
    var body = tmpl
      .replace('{prenom}', firstName || '')
      .replace('{journee}', String(jn || ''))
      .replace('{date}', date || '');
    return 'sms:' + (phone || '').replace(/\s/g, '') + '?body=' + encodeURIComponent(body);
  }

  // ═══════════════════════════════════════════
  // API
  // ═══════════════════════════════════════════
  function apiUrl(path) {
    // When pretty permalinks are off, API ends with ?rest_route=...
    // Extra query params must use & not ? to avoid breaking the route.
    if (API.indexOf('?') !== -1 && path.indexOf('?') !== -1) {
      return API + path.replace('?', '&');
    }
    return API + path;
  }

  function apiFetch(path, opts) {
    return fetch(apiUrl(path), Object.assign(
      { headers: { 'X-WP-Nonce': NONCE, 'Content-Type': 'application/json' } },
      opts || {}
    )).then(function (r) {
      if (!r.ok) return r.text().then(function (t) { throw new Error(t); });
      return r.json();
    });
  }

  function loadAll() {
    return Promise.all([
      apiFetch('/players'),
      apiFetch('/availability?season=' + encodeURIComponent(cfg.season || ''))
    ]).then(function (res) {
      setState({ players: res[0] || [], availabilities: res[1] || [], loading: false, loadError: null });
    }).catch(function (err) {
      setState({ loading: false, loadError: String(err) });
    });
  }

  function loadCompositions() {
    apiFetch('/compositions?season=' + encodeURIComponent(cfg.season || '') +
             '&phase=' + (S.phase + 1) + '&round=' + S.journeeN)
      .then(function (c) { setState({ compositions: c || [] }); })
      .catch(function () {});
  }

  function loadAlerts() {
    apiFetch('/rules/check?season=' + encodeURIComponent(cfg.season || '') +
             '&phase=' + (S.phase + 1) + '&round=' + S.journeeN)
      .then(function (a) { setState({ ruleAlerts: a || [] }); })
      .catch(function () {});
  }

  function assignSlot(teamCode, slotNum, playerId) {
    var ph = S.phase + 1, rn = S.journeeN, se = cfg.season || '';
    // Optimistic
    var compos = S.compositions.filter(function (c) {
      return !(c.team_code === teamCode && c.slot_number === slotNum);
    });
    compos.push({ team_code: teamCode, slot_number: slotNum, player_id: playerId, phase: ph, round: rn, season: se });
    setState({ compositions: compos, picker: null, pickerQ: '' });
    apiFetch('/compositions', {
      method: 'POST',
      body: JSON.stringify({ team_code: teamCode, slot_number: slotNum, player_id: playerId, phase: ph, round: rn, season: se })
    }).then(function () { loadAlerts(); }).catch(function () { loadCompositions(); });
  }

  function removeSlot(teamCode, slotNum) {
    var ph = S.phase + 1, rn = S.journeeN, se = cfg.season || '';
    // Optimistic
    var compos = S.compositions.map(function (c) {
      return (c.team_code === teamCode && c.slot_number === slotNum)
        ? Object.assign({}, c, { player_id: null }) : c;
    });
    setState({ compositions: compos });
    apiFetch('/compositions', {
      method: 'DELETE',
      body: JSON.stringify({ team_code: teamCode, slot_number: slotNum, phase: ph, round: rn, season: se })
    }).then(function () { loadAlerts(); }).catch(function () { loadCompositions(); });
  }

  function syncPlayers() {
    setState({ syncing: true });
    apiFetch('/players/sync', { method: 'POST' })
      .then(function () { return loadAll(); })
      .finally(function () { setState({ syncing: false }); });
  }

  // ═══════════════════════════════════════════
  // COMPUTED
  // ═══════════════════════════════════════════
  function getPlayer(id) {
    var sid = String(id);
    return S.players.find(function (p) { return String(p.id) === sid; });
  }

  function getAvail(playerId) {
    var sid = String(playerId);
    var av = S.availabilities.find(function (a) {
      return String(a.player_id) === sid &&
             Number(a.phase) === (S.phase + 1) &&
             Number(a.round) === S.journeeN;
    });
    return av ? av.status : 'unknown';
  }

  function getAvailForRound(playerId, phase, round) {
    var sid = String(playerId);
    var av = S.availabilities.find(function (a) {
      return String(a.player_id) === sid &&
             Number(a.phase) === phase &&
             Number(a.round) === round;
    });
    return av ? av.status : 'unknown';
  }

  function getCompoSlots(teamCode) {
    return S.compositions
      .filter(function (c) { return c.team_code === teamCode; })
      .sort(function (a, b) { return a.slot_number - b.slot_number; });
  }

  function filteredPlayers() {
    var q = S.searchQ.toLowerCase();
    return S.players.filter(function (p) {
      var name = ((p.first_name || '') + ' ' + (p.last_name || '')).toLowerCase();
      if (q && !name.includes(q)) return false;
      var f = S.playerFilter;
      if (f === 'all') return true;
      if (f === 'ko')    return getAvail(p.id) === 'unavailable';
      if (f === 'capt')  return !!p.is_captain;
      if (f === 'E')     return !!p.is_foreign;
      if (f === 'jeune') return !!p.is_young;
      if (f === 'brule') return !!p.is_burned;
      return true;
    });
  }

  // ═══════════════════════════════════════════
  // RENDER HELPERS
  // ═══════════════════════════════════════════
  function badge(text, color, dark, xs) {
    var pal = {
      primary: { bg: dark ? C.priSoftD : C.priSoft, fg: dark ? '#bfdbfe' : C.priInk },
      ok:      { bg: dark ? '#14532d'  : C.okSoft,  fg: dark ? '#86efac' : '#15803d' },
      warn:    { bg: dark ? '#713f12'  : C.warnSoft, fg: dark ? '#fde68a' : '#a16207' },
      danger:  { bg: dark ? '#7f1d1d'  : C.errSoft,  fg: dark ? '#fca5a5' : '#b91c1c' },
      purple:  { bg: dark ? '#3b1e6b'  : C.purpSoft, fg: dark ? '#c4b5fd' : '#6d28d9' },
      neutral: { bg: dark ? C.surf2D   : '#f1f5f9',  fg: dark ? '#cbd5e1' : C.ink2 }
    };
    var p = pal[color] || pal.neutral;
    var sz = xs ? '10px' : '11px';
    var pad = xs ? '2px 6px' : '3px 8px';
    return '<span style="display:inline-flex;align-items:center;gap:4px;background:' + p.bg + ';color:' + p.fg + ';font-size:' + sz + ';font-weight:600;padding:' + pad + ';border-radius:999px;line-height:1.2;white-space:nowrap">' + esc(text) + '</span>';
  }

  function playerBadges(p, dark) {
    var h = '<div style="display:flex;gap:3px;flex-wrap:wrap;margin-top:3px">';
    if (p.is_captain) h += badge('© Cap.', 'primary', dark, true);
    if (p.is_foreign) h += badge('E', 'purple', dark, true);
    if (p.is_young)   h += badge('Jeune', 'ok', dark, true);
    if (p.is_mutation)h += badge('Mut.', 'warn', dark, true);
    if (p.is_burned)  h += badge('🔥 Brûlé', 'danger', dark, true);
    h += '</div>';
    return h;
  }

  function avatar(init, avail, dark, size) {
    size = size || 38;
    var grad = avail === 'unavailable'
      ? 'linear-gradient(135deg,#ef4444,#b91c1c)'
      : 'linear-gradient(135deg,' + C.pri + ',' + C.priInk + ')';
    var dot = avColor(avail, dark);
    var surf = tk(dark).surf;
    return '<div style="position:relative;width:' + size + 'px;height:' + size + 'px;flex-shrink:0">' +
      '<div style="width:' + size + 'px;height:' + size + 'px;border-radius:50%;background:' + grad + ';color:white;display:flex;align-items:center;justify-content:center;font-size:' + Math.round(size * 0.34) + 'px;font-weight:700">' + esc(init) + '</div>' +
      '<div style="position:absolute;bottom:-2px;right:-2px;width:13px;height:13px;border-radius:50%;background:' + dot + ';border:2px solid ' + surf + '"></div>' +
    '</div>';
  }

  function topBar(title, sub, dark, back, action) {
    var t = tk(dark);
    return '<div style="padding:14px 16px 12px;display:flex;align-items:flex-start;gap:10px;border-bottom:1px solid ' + t.bord + ';background:' + t.surf + ';position:sticky;top:0;z-index:5">' +
      (back ? '<button data-action="back" style="width:32px;height:32px;border-radius:8px;border:none;background:' + t.surf2 + ';color:' + t.ink + ';font-size:18px;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0">‹</button>' : '') +
      '<div style="flex:1;min-width:0">' +
        '<div style="font-size:17px;font-weight:700;color:' + t.ink + ';letter-spacing:-0.2px">' + esc(title) + '</div>' +
        (sub ? '<div style="font-size:12px;color:' + t.ink2 + ';margin-top:2px">' + esc(sub) + '</div>' : '') +
      '</div>' +
      (action || '') +
    '</div>';
  }

  function sectionWrap(title, content, dark) {
    var t = tk(dark);
    return '<div style="margin-bottom:14px">' +
      '<div style="font-size:10px;color:' + t.ink2 + ';font-weight:600;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;padding:0 2px">' + esc(title) + '</div>' +
      '<div style="background:' + t.surf + ';border:1px solid ' + t.bord + ';border-radius:10px;padding:4px 0;overflow:hidden">' +
        content +
      '</div></div>';
  }

  function infoRow(label, value, dark, hi) {
    var t = tk(dark);
    return '<div style="display:flex;justify-content:space-between;padding:8px 12px;font-size:12px;align-items:center">' +
      '<span style="color:' + t.ink2 + '">' + esc(label) + '</span>' +
      '<span style="color:' + (hi ? C.pri : t.ink) + ';font-weight:' + (hi ? '600' : '500') + '">' + value + '</span>' +
    '</div>';
  }

  // ═══════════════════════════════════════════
  // BOTTOM NAV
  // ═══════════════════════════════════════════
  function renderNav() {
    var t = tk(S.dark);
    var tabs = [
      { id: 'dashboard', icon: '🏠', label: 'Accueil' },
      { id: 'journees',  icon: '📅', label: 'Journées' },
      { id: 'joueurs',   icon: '🏓', label: 'Joueurs' },
      { id: 'alertes',   icon: '🔔', label: 'Alertes' },
      { id: 'reglages',  icon: '⚙️',  label: 'Réglages' }
    ];
    var h = '<nav style="display:flex;border-top:1px solid ' + t.bord + ';background:' + t.surf + ';padding:4px 0;flex-shrink:0">';
    var alertCount = S.ruleAlerts.length;
    tabs.forEach(function (tab) {
      var active = S.tab === tab.id;
      var color  = active ? C.priInk : t.ink2;
      var pillBg = active ? t.priSoft : 'transparent';
      h += '<button data-action="tab" data-value="' + tab.id + '" style="flex:1;border:none;background:transparent;padding:6px 4px 8px;display:flex;flex-direction:column;align-items:center;gap:2px;cursor:pointer;color:' + color + ';position:relative">' +
        '<div style="width:52px;height:28px;border-radius:14px;background:' + pillBg + ';display:flex;align-items:center;justify-content:center">' +
          '<span style="font-size:17px;line-height:1">' + tab.icon + '</span>' +
        '</div>' +
        '<span style="font-size:10px;font-weight:' + (active ? '700' : '600') + '">' + tab.label + '</span>' +
        (tab.id === 'alertes' && alertCount > 0
          ? '<span style="position:absolute;top:2px;right:50%;margin-right:-22px;background:' + C.err + ';color:white;border-radius:10px;padding:0 5px;font-size:9px;font-weight:700;min-width:14px;text-align:center;box-shadow:0 0 0 2px ' + t.surf + '">' + alertCount + '</span>'
          : '') +
      '</button>';
    });
    h += '</nav>';
    return h;
  }

  // ═══════════════════════════════════════════
  // DASHBOARD
  // ═══════════════════════════════════════════
  function renderDashboard() {
    var dark = S.dark; var t = tk(dark);
    var jj = S.journees;
    var done = jj.filter(function (j) { return j.done; }).length;
    var alerts = jj.reduce(function (s, j) { return s + (j.alerts || 0); }, 0) + S.ruleAlerts.length;
    var tc = cfg.teamsCount || S.teams.length || 11;

    // Prochaine journée globale (toutes phases confondues)
    var nextGlobal = findGlobalNext();
    // Affiche la journée globale si elle est dans une autre phase que la courante
    var showGlobal = !!(nextGlobal && nextGlobal.phase !== S.phase + 1);
    // Journée courante dans la phase affichée (première avec date future)
    var nextLocal  = jj.find(function (j) { return j.current; }) || jj[jj.length - 1];
    // Objet unifié : { round, date, phase, complete }
    var next = showGlobal
      ? nextGlobal
      : { round: nextLocal.n, date: nextLocal.date, phase: S.phase + 1, complete: nextLocal.complete };

    var h = '<div style="padding:16px;display:flex;flex-direction:column;gap:14px">';

    // Phase switcher
    h += '<div style="display:flex;background:' + t.surf2 + ';padding:3px;border-radius:10px">';
    ['Phase 1 · Sept → Janv', 'Phase 2 · Févr → Juin'].forEach(function (lbl, i) {
      var active = S.phase === i;
      h += '<button data-action="phase" data-value="' + i + '" style="flex:1;padding:8px 6px;border:none;border-radius:8px;background:' + (active ? t.surf : 'transparent') + ';color:' + (active ? t.ink : t.ink2) + ';font-size:11px;font-weight:600;cursor:pointer;box-shadow:' + (active ? '0 1px 2px rgba(0,0,0,0.08)' : 'none') + '">' + lbl + '</button>';
    });
    h += '</div>';

    // KPIs
    h += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">';
    h += '<div style="background:' + t.surf + ';border:1px solid ' + t.bord + ';border-radius:12px;padding:12px">' +
      '<div style="font-size:11px;color:' + t.ink2 + ';font-weight:500">Journées bouclées</div>' +
      '<div style="font-size:26px;font-weight:700;color:' + t.ink + ';margin-top:4px;letter-spacing:-0.5px">' + done + '<span style="color:' + t.ink2 + ';font-weight:500;font-size:16px"> / 7</span></div>' +
      '<div style="height:4px;background:' + t.surf2 + ';border-radius:2px;margin-top:8px;overflow:hidden">' +
        '<div style="width:' + Math.round(done / 7 * 100) + '%;height:100%;background:' + C.pri + '"></div>' +
      '</div></div>';
    h += '<div style="background:' + t.surf + ';border:1px solid ' + t.bord + ';border-radius:12px;padding:12px">' +
      '<div style="font-size:11px;color:' + t.ink2 + ';font-weight:500">Alertes</div>' +
      '<div style="font-size:26px;font-weight:700;color:' + (alerts ? C.err : C.ok) + ';margin-top:4px;letter-spacing:-0.5px">' + alerts + '</div>' +
      '<button data-action="goto" data-screen="alertes" data-tab="alertes" style="margin-top:4px;font-size:11px;color:' + C.pri + ';background:none;border:none;padding:0;cursor:pointer;font-weight:600">Voir →</button>' +
    '</div>';
    h += '</div>';

    // Next journée card
    var nextPhaseLabel = showGlobal ? ' · Phase ' + next.phase : '';
    var nextAction = showGlobal
      ? 'data-action="goto-phase-journee" data-phase="' + (next.phase - 1) + '" data-journee="' + next.round + '"'
      : 'data-action="journee" data-value="' + next.round + '"';
    h += '<div style="background:linear-gradient(135deg,' + C.pri + ',' + C.priInk + ');color:white;border-radius:14px;padding:14px;position:relative;overflow:hidden">' +
      '<div style="font-size:11px;opacity:0.85;font-weight:500;letter-spacing:0.4px;text-transform:uppercase">Prochaine journée' + esc(nextPhaseLabel) + '</div>' +
      '<div style="font-size:22px;font-weight:700;margin-top:4px;letter-spacing:-0.3px">J' + next.round + (next.date ? ' · ' + esc(next.date) : '') + '</div>' +
      '<div style="font-size:12px;opacity:0.9;margin-top:2px">' + (next.complete || 0) + ' / ' + tc + ' compositions complètes</div>' +
      '<div style="display:flex;gap:6px;margin-top:12px">' +
        '<button ' + nextAction + ' style="background:white;color:' + C.priInk + ';border:none;padding:8px 14px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer">Préparer les compos →</button>' +
      '</div>' +
      '<div style="position:absolute;right:-20px;top:-30px;font-size:90px;opacity:0.12;line-height:1;pointer-events:none">🏓</div>' +
    '</div>';

    // Journées list
    h += '<div><div style="font-size:11px;color:' + t.ink2 + ';font-weight:600;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;padding:0 2px">Journées de la phase</div>';
    h += '<div style="display:flex;flex-direction:column;gap:6px">';
    jj.forEach(function (j) {
      var cur = j.current;
      var numBg = j.done ? C.okSoft : cur ? t.priSoft : t.surf2;
      var numFg = j.done ? '#15803d' : cur ? C.priInk : t.ink2;
      h += '<button data-action="journee" data-value="' + j.n + '" style="display:flex;align-items:center;gap:12px;padding:10px 12px;background:' + t.surf + ';border:1px solid ' + (cur ? C.pri : t.bord) + ';border-radius:10px;cursor:pointer;text-align:left;width:100%;box-shadow:' + (cur ? '0 0 0 3px ' + C.pri + '22' : 'none') + '">' +
        '<div style="width:36px;height:36px;border-radius:8px;background:' + numBg + ';color:' + numFg + ';display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0">J' + j.n + '</div>' +
        '<div style="flex:1;min-width:0;text-align:left">' +
          '<div style="font-size:13px;font-weight:600;color:' + t.ink + '">' + (j.date ? esc(j.date) : 'Journée ' + j.n) + '</div>' +
          '<div style="font-size:11px;color:' + t.ink2 + ';margin-top:1px">' +
            (j.done ? 'Terminée' : ((j.complete || 0) + ' / ' + tc + ' compos')) +
            (j.alerts ? ' <span style="color:' + C.err + '">· ' + j.alerts + ' alerte' + (j.alerts > 1 ? 's' : '') + '</span>' : '') +
          '</div>' +
        '</div>' +
        (j.done ? '<span style="font-size:14px">✅</span>' : j.alerts ? '<span style="font-size:14px">⚠️</span>' : '') +
        '<span style="color:' + t.ink2 + ';font-size:16px">›</span>' +
      '</button>';
    });
    h += '</div></div></div>';
    return h;
  }

  // ═══════════════════════════════════════════
  // JOURNÉE
  // ═══════════════════════════════════════════
  function renderJournee() {
    var dark = S.dark; var t = tk(dark);
    var jn = S.journeeN;
    var j = S.journees.find(function (x) { return x.n === jn; }) || S.journees[0];
    var tc = cfg.teamsCount || S.teams.length || 11;
    var teams = S.teams;

    var publish = '<button style="background:' + C.pri + ';color:white;border:none;padding:6px 12px;border-radius:8px;font-size:11px;font-weight:600;cursor:pointer">Publier</button>';
    var h = topBar('Journée ' + jn, (j.date ? esc(j.date) + ' · ' : '') + (j.complete || 0) + '/' + tc + ' compos', dark, true, publish);

    // Journée tabs (scrollable)
    h += '<div style="display:flex;overflow-x:auto;padding:10px 12px;gap:6px;background:' + t.surf + ';border-bottom:1px solid ' + t.bord + ';-webkit-overflow-scrolling:touch">';
    S.journees.forEach(function (jx) {
      var active = jx.n === jn;
      h += '<button data-action="journee" data-value="' + jx.n + '" style="padding:5px 11px;border-radius:999px;font-size:11px;font-weight:600;border:1px solid ' + (active ? C.pri : t.bord) + ';background:' + (active ? C.pri : t.surf) + ';color:' + (active ? 'white' : t.ink2) + ';cursor:pointer;white-space:nowrap;flex-shrink:0">J' + jx.n + '</button>';
    });
    h += '</div>';

    // Alert banner
    var alertsHere = S.ruleAlerts.filter(function (a) { return a.journee === jn; });
    if (alertsHere.length) {
      var aB = dark ? '#7f1d1d33' : '#fef2f2', aBo = dark ? '#7f1d1d' : '#fecaca', aFg = dark ? '#fca5a5' : '#b91c1c';
      h += '<div style="padding:10px 12px 4px">' +
        '<button data-action="goto" data-screen="alertes" data-tab="alertes" style="width:100%;display:flex;align-items:center;gap:8px;background:' + aB + ';border:1px solid ' + aBo + ';color:' + aFg + ';padding:8px 10px;border-radius:10px;font-size:11px;font-weight:600;cursor:pointer;text-align:left">' +
          '<span style="font-size:14px">⚠️</span><span style="flex:1">' + alertsHere.length + ' alerte' + (alertsHere.length > 1 ? 's' : '') + ' sur cette journée</span><span>›</span>' +
        '</button></div>';
    }

    // Team carousel
    if (teams.length) {
      h += '<div style="display:flex;overflow-x:auto;padding:8px 12px 4px;gap:8px;-webkit-overflow-scrolling:touch">';
      teams.forEach(function (team, i) {
        var active = i === S.activeTeamI;
        var slots = getCompoSlots(team.code || team.id || '');
        var filled = slots.filter(function (s) { return s.player_id; }).length;
        h += '<button data-action="team-tab" data-value="' + i + '" style="padding:6px 10px;border-radius:8px;border:1px solid ' + (active ? (team.color || C.pri) : t.bord) + ';background:' + (active ? (team.color || C.pri) : t.surf) + ';color:' + (active ? 'white' : t.ink) + ';cursor:pointer;font-size:11px;font-weight:600;white-space:nowrap;display:flex;align-items:center;gap:6px;flex-shrink:0">' +
          '<span>' + esc(team.code || team.id || 'E' + (i + 1)) + '</span>' +
          '<span style="opacity:0.7">' + filled + '/4</span>' +
        '</button>';
      });
      h += '</div>';
      if (teams[S.activeTeamI]) {
        h += renderTeamCompo(teams[S.activeTeamI], dark);
      }
    } else {
      h += '<div style="padding:32px;text-align:center;color:' + t.ink2 + ';font-size:13px"><div style="font-size:32px;margin-bottom:8px">📋</div>Aucune équipe.<br><small>Configurez vos équipes dans les réglages admin.</small></div>';
    }
    return h;
  }

  function renderTeamCompo(team, dark) {
    var t = tk(dark);
    var tc = team.color || C.pri;
    var slots = getCompoSlots(team.code || team.id || '');
    var arr = [null, null, null, null];
    slots.forEach(function (s) { if (s.slot_number >= 1 && s.slot_number <= 4) arr[s.slot_number - 1] = s; });
    var filled = arr.filter(function (s) { return s && s.player_id; }).length;

    var h = '<div style="padding:12px">';
    // Team header
    h += '<div style="background:' + t.surf + ';border:1px solid ' + t.bord + ';border-left:4px solid ' + tc + ';border-radius:10px;padding:12px;margin-bottom:10px">' +
      '<div style="display:flex;align-items:center;gap:8px">' +
        '<div style="flex:1"><div style="font-size:15px;font-weight:700;color:' + t.ink + '">' + esc(team.name || team.id || 'Équipe') + '</div><div style="font-size:11px;color:' + t.ink2 + '">' + esc(team.level || '') + '</div></div>' +
        badge(filled + '/4', filled === 4 ? 'ok' : 'warn', dark) +
      '</div>' +
    '</div>';

    // Slots
    h += '<div style="font-size:10px;color:' + t.ink2 + ';font-weight:600;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;padding:0 2px">4 slots joueurs</div>';
    h += '<div style="display:flex;flex-direction:column;gap:8px">';
    arr.forEach(function (slot, i) {
      var num = i + 1;
      var tcode = esc(team.code || team.id || '');
      if (!slot || !slot.player_id) {
        h += '<button data-action="slot-open" data-team="' + tcode + '" data-slot="' + num + '" style="width:100%;display:flex;align-items:center;gap:10px;padding:10px;background:transparent;border:1px dashed ' + t.bord + ';border-radius:10px;cursor:pointer;text-align:left;min-height:56px">' +
          '<div style="width:22px;height:22px;border-radius:6px;background:' + t.surf2 + ';color:' + t.ink2 + ';font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center">' + num + '</div>' +
          '<div style="flex:1;font-size:13px;color:' + t.ink2 + ';font-weight:500">Slot vide<div style="font-size:11px;opacity:0.7;margin-top:2px">Toucher pour ajouter</div></div>' +
          '<span style="color:' + C.pri + ';font-size:18px;font-weight:600">+</span>' +
        '</button>';
      } else {
        var p = getPlayer(slot.player_id);
        if (!p) { h += '<div style="padding:10px;background:' + t.surf + ';border:1px solid ' + t.bord + ';border-radius:10px;font-size:12px;color:' + t.ink2 + '">Joueur introuvable</div>'; return; }
        var avail = getAvail(p.id);
        var danger = avail === 'unavailable' || p.is_burned;
        var init = initials((p.first_name || '') + ' ' + (p.last_name || ''));
        h += '<div style="display:flex;align-items:center;background:' + t.surf + ';border:1px solid ' + (danger ? C.err : t.bord) + ';border-radius:10px;overflow:hidden">' +
          '<button data-action="player" data-value="' + esc(p.id) + '" style="flex:1;display:flex;align-items:center;gap:10px;padding:10px;background:transparent;border:none;cursor:pointer;text-align:left;min-width:0">' +
            '<div style="width:22px;height:22px;border-radius:6px;background:' + t.surf2 + ';color:' + t.ink2 + ';font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0">' + num + '</div>' +
            '<div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,' + C.pri + ',' + C.priInk + ');color:white;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0">' + esc(init) + '</div>' +
            '<div style="flex:1;min-width:0">' +
              '<div style="font-size:13px;font-weight:600;color:' + t.ink + ';overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + esc((p.first_name || '') + ' ' + (p.last_name || '')) + '</div>' +
              '<div style="display:flex;align-items:center;gap:6px;margin-top:3px">' +
                '<span style="font-size:11px;color:' + t.ink2 + '">' + (p.ranking || 0) + '</span>' +
                '<span style="font-size:11px;color:' + t.ink2 + '">·</span>' +
                '<span style="font-size:11px;color:' + avColor(avail, dark) + '">' + avEmoji(avail) + ' ' + avStatus(avail) + '</span>' +
              '</div>' +
              playerBadges(p, dark) +
            '</div>' +
          '</button>' +
          '<button data-action="slot-remove" data-team="' + tcode + '" data-slot="' + num + '" style="width:40px;height:100%;min-height:56px;border:none;border-left:1px solid ' + t.bord + ';background:transparent;color:' + t.ink2 + ';font-size:15px;cursor:pointer;flex-shrink:0" title="Retirer">✕</button>' +
        '</div>';
      }
    });
    h += '</div>';
    h += '<button style="margin-top:14px;width:100%;padding:11px;border-radius:10px;background:' + t.surf + ';border:1px dashed ' + t.bord + ';color:' + t.ink2 + ';font-size:12px;font-weight:600;cursor:pointer">+ Ajouter un remplaçant</button>';
    h += '</div>';
    return h;
  }

  // ═══════════════════════════════════════════
  // PLAYER PICKER
  // ═══════════════════════════════════════════
  function renderPicker() {
    var dark = S.dark; var t = tk(dark);
    var teamCode = S.picker.teamCode;
    var slotNum  = S.picker.slotNum;
    var q = S.pickerQ.toLowerCase();

    var team = S.teams.find(function (tm) { return (tm.code || tm.id) === teamCode; });
    var tc       = team ? (team.color || C.pri) : C.pri;
    var teamName = team ? (team.name || teamCode) : teamCode;

    // Joueurs déjà placés dans une AUTRE équipe ce round
    var elsewhere = {};
    S.compositions.forEach(function (c) {
      if (c.player_id && c.team_code !== teamCode) elsewhere[c.player_id] = c.team_code;
    });
    // Joueurs déjà dans CETTE équipe (autre slot)
    var inTeam = {};
    S.compositions.forEach(function (c) {
      if (c.player_id && c.team_code === teamCode && c.slot_number !== slotNum) inTeam[c.player_id] = true;
    });

    // Filtrage + tri : joueurs habituels en tête, puis classement DESC
    var filtered = S.players.filter(function (p) {
      if (!q) return true;
      return ((p.first_name || '') + ' ' + (p.last_name || '')).toLowerCase().includes(q)
          || String(p.ranking || '').includes(q);
    }).sort(function (a, b) {
      var au = (a.usual_team === teamCode) ? 0 : 1;
      var bu = (b.usual_team === teamCode) ? 0 : 1;
      return au !== bu ? au - bu : (b.ranking || 0) - (a.ranking || 0);
    });

    var h = '<div style="position:absolute;inset:0;z-index:50;display:flex;flex-direction:column">';

    // Backdrop semi-transparent (tap = fermer)
    h += '<div data-action="picker-close" style="background:rgba(0,0,0,0.45);flex:0 0 64px;cursor:pointer"></div>';

    // Panel
    h += '<div style="flex:1;background:' + t.surf + ';display:flex;flex-direction:column;overflow:hidden">';

    // En-tête
    h += '<div style="padding:14px 16px 10px;border-bottom:1px solid ' + t.bord + ';display:flex;align-items:center;gap:10px;flex-shrink:0">';
    h += '<div style="width:8px;height:8px;border-radius:50%;background:' + tc + ';flex-shrink:0"></div>';
    h += '<div style="flex:1"><div style="font-size:15px;font-weight:700;color:' + t.ink + '">Choisir un joueur</div>';
    h += '<div style="font-size:11px;color:' + t.ink2 + ';margin-top:1px">' + esc(teamName) + ' &mdash; Slot ' + slotNum + '</div></div>';
    h += '<button data-action="picker-close" style="width:28px;height:28px;border-radius:8px;border:none;background:' + t.surf2 + ';color:' + t.ink2 + ';font-size:14px;cursor:pointer">✕</button>';
    h += '</div>';

    // Recherche
    h += '<div style="padding:10px 12px;border-bottom:1px solid ' + t.bord + ';flex-shrink:0">';
    h += '<div style="display:flex;align-items:center;gap:8px;background:' + t.surf2 + ';border-radius:8px;padding:8px 12px">';
    h += '<span style="font-size:13px">🔍</span>';
    h += '<input data-input="picker-search" value="' + esc(S.pickerQ) + '" placeholder="Rechercher…" autocomplete="off" style="flex:1;border:none;background:transparent;outline:none;color:' + t.ink + ';font-size:13px">';
    if (S.pickerQ) {
      h += '<button data-action="picker-clear" style="border:none;background:none;color:' + t.ink2 + ';cursor:pointer;font-size:14px;padding:0;line-height:1">✕</button>';
    }
    h += '</div></div>';

    // Liste joueurs
    h += '<div class="ttp-screen-content">';
    if (filtered.length === 0) {
      h += '<div style="padding:32px;text-align:center;color:' + t.ink2 + ';font-size:13px">Aucun joueur trouvé</div>';
    } else {
      var lastGroup = null;
      filtered.forEach(function (p) {
        var grp = p.usual_team === teamCode ? 'Joueurs habituels' : 'Autres joueurs';
        if (grp !== lastGroup) {
          lastGroup = grp;
          h += '<div style="padding:8px 12px 4px;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:' + t.ink2 + '">' + grp + '</div>';
        }

        var avail  = getAvail(p.id);
        var init   = initials((p.first_name || '') + ' ' + (p.last_name || ''));
        var busy   = !!elsewhere[p.id];   // placé ailleurs — déconseillé mais autorisé
        var taken  = !!inTeam[p.id];      // déjà dans ce slot ou autre slot de l'équipe
        var grad   = avail === 'unavailable' ? 'linear-gradient(135deg,#ef4444,#b91c1c)' : 'linear-gradient(135deg,' + C.pri + ',' + C.priInk + ')';

        h += '<button data-action="slot-assign" data-player-id="' + p.id + '"'
           + (taken ? ' disabled' : '')
           + ' style="width:100%;display:flex;align-items:center;gap:10px;padding:10px 12px;background:transparent;border:none;border-bottom:1px solid ' + t.bord + ';cursor:' + (taken ? 'not-allowed' : 'pointer') + ';text-align:left;opacity:' + (taken ? '0.35' : '1') + '">';

        // Avatar
        h += '<div style="position:relative;flex-shrink:0">'
           + '<div style="width:36px;height:36px;border-radius:50%;background:' + grad + ';color:white;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700">' + esc(init) + '</div>'
           + '<div style="position:absolute;bottom:-1px;right:-1px;width:10px;height:10px;border-radius:50%;background:' + avColor(avail, dark) + ';border:2px solid ' + t.surf + '"></div>'
           + '</div>';

        // Infos
        h += '<div style="flex:1;min-width:0">';
        h += '<div style="font-size:13px;font-weight:600;color:' + t.ink + ';overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + esc((p.first_name || '') + ' ' + (p.last_name || '')) + '</div>';
        h += '<div style="display:flex;align-items:center;gap:6px;margin-top:2px;flex-wrap:wrap">';
        h += '<span style="font-size:11px;color:' + t.ink2 + '">' + (p.ranking || 0) + '</span>';
        if (busy) h += badge('Déjà en ' + elsewhere[p.id], 'warn', dark, true);
        h += '</div>' + playerBadges(p, dark) + '</div>';

        h += (taken ? '' : '<span style="color:' + t.ink2 + ';font-size:18px;opacity:0.4">›</span>');
        h += '</button>';
      });
    }
    h += '</div></div></div>';
    return h;
  }

  // ═══════════════════════════════════════════
  // JOUEURS
  // ═══════════════════════════════════════════
  function renderJoueurs() {
    var dark = S.dark; var t = tk(dark);
    var players = filteredPlayers();
    var addBtn = '<button style="width:32px;height:32px;border-radius:8px;border:none;background:' + C.pri + ';color:white;font-size:18px;cursor:pointer">+</button>';
    var h = topBar('Joueurs', S.players.length + ' licenciés', dark, false, addBtn);

    // Search + filters
    h += '<div style="padding:12px;background:' + t.surf + ';border-bottom:1px solid ' + t.bord + '">';
    h += '<div style="display:flex;align-items:center;gap:8px;background:' + t.surf2 + ';padding:9px 12px;border-radius:10px">' +
      '<span style="font-size:14px;opacity:0.6">🔍</span>' +
      '<input data-input="search" value="' + esc(S.searchQ) + '" placeholder="Rechercher…" style="flex:1;border:none;background:transparent;outline:none;color:' + t.ink + ';font-size:13px">' +
    '</div>';
    var filters = [['all','Tous'],['ko','🚫 Indispo'],['capt','© Capitaines'],['E','E Étrangers'],['jeune','Jeunes'],['brule','🔥 Brûlés']];
    h += '<div style="display:flex;gap:6px;margin-top:10px;overflow-x:auto;-webkit-overflow-scrolling:touch">';
    filters.forEach(function (f) {
      var active = S.playerFilter === f[0];
      h += '<button data-action="filter" data-value="' + f[0] + '" style="padding:5px 10px;border-radius:999px;font-size:11px;font-weight:600;border:1px solid ' + (active ? C.pri : t.bord) + ';background:' + (active ? C.pri : t.surf) + ';color:' + (active ? 'white' : t.ink2) + ';cursor:pointer;white-space:nowrap;flex-shrink:0">' + f[1] + '</button>';
    });
    h += '</div></div>';

    h += '<div style="padding:12px">';
    var teams = S.teams;
    var shown = false;
    if (teams.length) {
      teams.forEach(function (team) {
        var tp = players.filter(function (p) { return p.usual_team === (team.code || team.id || ''); });
        if (!tp.length) return;
        shown = true;
        h += '<div style="margin-bottom:16px">';
        h += '<div style="display:flex;align-items:center;gap:6px;padding:0 2px 6px;font-size:10px;color:' + t.ink2 + ';font-weight:600;text-transform:uppercase;letter-spacing:0.5px">' +
          '<div style="width:6px;height:6px;border-radius:50%;background:' + (team.color || C.pri) + '"></div>' +
          esc(team.name || team.id || '') + (team.level ? ' · ' + esc(team.level) : '') +
        '</div>';
        h += '<div style="display:flex;flex-direction:column;gap:6px">';
        tp.forEach(function (p) { h += renderPlayerCard(p, dark); });
        h += '</div></div>';
      });
    }
    // Ungrouped or empty search
    if (!teams.length) {
      h += '<div style="display:flex;flex-direction:column;gap:6px">';
      players.forEach(function (p) { h += renderPlayerCard(p, dark); });
      h += '</div>';
      shown = players.length > 0;
    }
    if (!shown) {
      h += '<div style="padding:40px;text-align:center;color:' + t.ink2 + ';font-size:13px"><div style="font-size:36px;margin-bottom:8px">🔍</div>Aucun joueur trouvé</div>';
    }
    h += '</div>';
    return h;
  }

  function renderPlayerCard(p, dark) {
    var t = tk(dark);
    var avail = getAvail(p.id);
    var init = initials((p.first_name || '') + ' ' + (p.last_name || ''));
    var phone = p.phone || '';
    var jDate = (S.journees[S.journeeN - 1] || {}).date || '';
    var h = '<div style="background:' + t.surf + ';border:1px solid ' + t.bord + ';border-radius:10px;padding:10px;display:flex;align-items:center;gap:10px">';
    h += '<button data-action="player" data-value="' + esc(p.id) + '" style="flex:1;display:flex;align-items:center;gap:10px;background:transparent;border:none;padding:0;cursor:pointer;text-align:left;min-width:0">' +
      avatar(init, avail, dark, 38) +
      '<div style="flex:1;min-width:0">' +
        '<div style="display:flex;align-items:center;gap:5px">' +
          '<span style="font-size:13px;font-weight:600;color:' + t.ink + ';overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + esc((p.first_name || '') + ' ' + (p.last_name || '')) + '</span>' +
          '<span style="font-size:11px;color:' + t.ink2 + ';font-weight:500;flex-shrink:0">' + (p.ranking || 0) + '</span>' +
        '</div>' +
        playerBadges(p, dark) +
      '</div>' +
    '</button>';
    if (phone) {
      h += '<div style="display:flex;gap:6px;flex-shrink:0">';
      h += '<a href="tel:' + esc(phone.replace(/\s/g, '')) + '" onclick="event.stopPropagation()" style="width:34px;height:34px;border-radius:8px;background:' + C.okSoft + ';color:#15803d;display:flex;align-items:center;justify-content:center;text-decoration:none;font-size:14px">📞</a>';
      h += '<a href="' + esc(smsHref(phone, p.first_name || '', S.journeeN, jDate)) + '" onclick="event.stopPropagation()" style="width:34px;height:34px;border-radius:8px;background:' + t.priSoft + ';color:' + C.priInk + ';display:flex;align-items:center;justify-content:center;text-decoration:none;font-size:14px">💬</a>';
      h += '</div>';
    }
    h += '</div>';
    return h;
  }

  // ═══════════════════════════════════════════
  // PLAYER DETAIL
  // ═══════════════════════════════════════════
  function renderPlayerDetail() {
    var dark = S.dark; var t = tk(dark);
    var p = getPlayer(S.playerId);
    if (!p) return '<div style="padding:32px;text-align:center;color:' + t.ink2 + '">Joueur introuvable.</div>';

    if (S.playerEdit) return renderPlayerEdit(p, dark, t);

    var avail = getAvail(p.id);
    var init = initials((p.first_name || '') + ' ' + (p.last_name || ''));
    var phone = p.phone || '';
    var jDate = (S.journees[S.journeeN - 1] || {}).date || '';
    var team = S.teams.find(function (tm) { return (tm.code || tm.id) === p.usual_team; });
    var editBtn = '<button data-action="player-edit-open" style="width:32px;height:32px;border-radius:8px;border:none;background:' + t.surf2 + ';color:' + t.ink + ';font-size:14px;cursor:pointer">✏️</button>';

    var h = topBar('Fiche joueur', '', dark, true, editBtn);
    h += '<div style="padding:16px">';

    // Hero
    var heroKo = avail === 'unavailable';
    var heroBg = heroKo ? (dark ? '#7f1d1d33' : C.errSoft) : 'transparent';
    var heroBord = heroKo ? '1px solid ' + (dark ? '#7f1d1d' : '#fecaca') : 'none';
    h += '<div style="display:flex;align-items:center;gap:14px;margin-bottom:12px;padding:' + (heroKo ? '12px' : '0') + ';background:' + heroBg + ';border:' + heroBord + ';border-radius:14px">';
    h += '<div style="position:relative;width:64px;height:64px;flex-shrink:0">' +
      '<div style="width:64px;height:64px;border-radius:50%;background:' + (heroKo ? 'linear-gradient(135deg,#ef4444,#b91c1c)' : 'linear-gradient(135deg,' + C.pri + ',' + C.priInk + ')') + ';color:white;display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:700">' + esc(init) + '</div>' +
      (heroKo ? '<span style="position:absolute;bottom:-2px;right:-2px;width:22px;height:22px;border-radius:50%;background:white;display:flex;align-items:center;justify-content:center;font-size:12px;box-shadow:0 1px 3px rgba(0,0,0,0.15)">🚫</span>' : '') +
    '</div>';
    h += '<div style="flex:1;min-width:0">' +
      '<div style="font-size:18px;font-weight:700;color:' + (heroKo ? (dark ? '#fca5a5' : '#b91c1c') : t.ink) + ';letter-spacing:-0.3px">' + esc((p.first_name || '') + ' ' + (p.last_name || '')) + '</div>' +
      '<div style="font-size:12px;color:' + t.ink2 + ';margin-top:2px">' + (p.ranking || 0) + ' pts · ' + esc(team ? team.name : p.usual_team || '—') + '</div>' +
      playerBadges(p, dark) +
    '</div></div>';

    // Status banners
    if (avail === 'unavailable') {
      h += '<div style="display:flex;align-items:flex-start;gap:10px;background:' + (dark ? '#7f1d1d40' : '#fee2e2') + ';border:1px solid ' + (dark ? '#991b1b' : '#fca5a5') + ';color:' + (dark ? '#fca5a5' : '#991b1b') + ';padding:10px 12px;border-radius:10px;margin-bottom:14px">' +
        '<span style="font-size:16px;line-height:1.2">🚫</span>' +
        '<div style="flex:1;font-size:12px;line-height:1.45"><strong style="font-weight:700">Indisponible</strong> pour la J' + S.journeeN + ' · ' + esc(jDate) +
          (p.notes ? '<div style="font-weight:500;opacity:0.85;margin-top:2px">' + esc(p.notes) + '</div>' : '') +
        '</div></div>';
    } else if (avail === 'uncertain') {
      h += '<div style="display:flex;align-items:center;gap:10px;background:' + (dark ? '#713f1240' : '#fef3c7') + ';border:1px solid ' + (dark ? '#854d0e' : '#fde68a') + ';color:' + (dark ? '#fde68a' : '#92400e') + ';padding:10px 12px;border-radius:10px;margin-bottom:14px;font-size:12px">' +
        '<span style="font-size:16px">❓</span>' +
        '<span style="flex:1"><strong style="font-weight:700">Réponse en attente</strong> — relance par SMS pré-rédigé.</span>' +
      '</div>';
    }

    // Quick actions
    h += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:16px">';
    h += '<a href="tel:' + esc(phone.replace(/\s/g, '')) + '" style="display:flex;align-items:center;justify-content:center;gap:6px;background:' + C.ok + ';color:white;padding:12px;border-radius:10px;text-decoration:none;font-size:13px;font-weight:600">📞 Appeler</a>';
    h += '<a href="' + esc(smsHref(phone, p.first_name || '', S.journeeN, jDate)) + '" style="display:flex;align-items:center;justify-content:center;gap:6px;background:' + C.pri + ';color:white;padding:12px;border-radius:10px;text-decoration:none;font-size:13px;font-weight:600">💬 SMS</a>';
    h += '</div>';

    // Availability grids — both phases, interactive
    h += renderAvailGrid(p, 1, dark, t);
    h += renderAvailGrid(p, 2, dark, t);

    // Coordonnées
    h += sectionWrap('Coordonnées',
      infoRow('Téléphone',  phone ? '<a href="tel:' + esc(phone.replace(/\s/g,'')) + '" style="color:' + C.pri + ';text-decoration:none;font-weight:600">' + esc(phone) + '</a>' : '<span style="color:' + t.ink2 + '">—</span>', dark, false) +
      infoRow('Licence',    esc(p.license_number || '—'), dark, false) +
      infoRow('Classement', (p.ranking || 0) + ' pts', dark, false),
      dark);

    h += sectionWrap('Notes internes',
      '<div style="padding:10px 12px;font-size:12px;color:' + (p.notes ? t.ink : t.ink2) + ';line-height:1.5;font-style:' + (p.notes ? 'normal' : 'italic') + '">' + esc(p.notes || 'Aucune note') + '</div>',
      dark);

    h += '</div>';
    return h;
  }

  function renderAvailGrid(p, phase, dark, t) {
    var CYCLE = ['unknown', 'available', 'unavailable', 'uncertain'];
    var cells = S.journees.map(function (jx) {
      var av  = getAvailForRound(p.id, phase, jx.n);
      var bg  = av === 'available' ? C.okSoft : av === 'unavailable' ? C.errSoft : av === 'uncertain' ? C.warnSoft : t.surf2;
      var fg  = av === 'available' ? '#15803d' : av === 'unavailable' ? '#b91c1c' : av === 'uncertain' ? '#a16207' : t.ink2;
      var brd = av === 'unknown' ? '1px dashed ' + t.bord : '1px solid transparent';
      return '<button data-action="avail-toggle" data-player-id="' + p.id + '" data-phase="' + phase + '" data-round="' + jx.n + '"' +
        ' style="padding:7px 2px;border-radius:8px;background:' + bg + ';color:' + fg + ';text-align:center;font-size:11px;font-weight:600;border:' + brd + ';cursor:pointer;width:100%">' +
        '<div>J' + jx.n + '</div><div style="font-size:13px;margin-top:2px">' + avEmoji(av) + '</div>' +
      '</button>';
    }).join('');
    var hint = '<div style="font-size:10px;color:' + t.ink2 + ';padding:4px 8px 8px;text-align:right">Tap pour changer</div>';
    return sectionWrap('Disponibilités · Phase ' + phase,
      '<div style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px;padding:8px">' + cells + '</div>' + hint,
      dark);
  }

  function renderPlayerEdit(p, dark, t) {
    var cancelBtn = '<button data-action="player-edit-cancel" style="padding:0 12px;height:32px;border-radius:8px;border:none;background:' + t.surf2 + ';color:' + t.ink2 + ';font-size:13px;font-weight:600;cursor:pointer">Annuler</button>';
    var saveBtn   = S.editSaving
      ? '<button disabled style="padding:0 14px;height:32px;border-radius:8px;border:none;background:' + t.surf2 + ';color:' + t.ink2 + ';font-size:13px;font-weight:600;cursor:not-allowed">…</button>'
      : '<button data-action="player-edit-save" style="padding:0 14px;height:32px;border-radius:8px;border:none;background:' + C.pri + ';color:white;font-size:13px;font-weight:600;cursor:pointer">Enregistrer</button>';
    var actions = '<div style="display:flex;gap:6px">' + cancelBtn + saveBtn + '</div>';

    var h = topBar('Modifier', (p.first_name || '') + ' ' + (p.last_name || ''), dark, false, actions);
    h += '<div style="padding:16px;display:flex;flex-direction:column;gap:14px">';

    var fieldStyle = 'width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid ' + t.bord + ';border-radius:10px;background:' + t.surf + ';color:' + t.ink + ';font-size:14px;outline:none;font-family:inherit';

    // Phone field
    h += '<div>';
    h += '<label style="display:block;font-size:11px;font-weight:600;color:' + t.ink2 + ';text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px">Téléphone</label>';
    h += '<input data-input="edit-phone" type="tel" value="' + esc(S.editPhone) + '" placeholder="06 XX XX XX XX" style="' + fieldStyle + '">';
    h += '</div>';

    // Notes textarea
    h += '<div>';
    h += '<label style="display:block;font-size:11px;font-weight:600;color:' + t.ink2 + ';text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px">Notes internes</label>';
    h += '<textarea data-input="edit-notes" rows="5" placeholder="Disponibilités habituelles, contraintes, remarques…" style="' + fieldStyle + ';resize:vertical;line-height:1.5">' + esc(S.editNotes) + '</textarea>';
    h += '</div>';

    // Read-only info
    h += sectionWrap('Informations FFTT (non modifiables)',
      infoRow('Licence',    esc(p.license_number || '—'), dark, false) +
      infoRow('Classement', (p.ranking || 0) + ' pts',    dark, false),
      dark);

    h += '</div>';
    return h;
  }

  // ═══════════════════════════════════════════
  // ALERTES
  // ═══════════════════════════════════════════
  function renderAlertes() {
    var dark = S.dark; var t = tk(dark);
    var alerts = S.ruleAlerts;
    var h = topBar('Alertes', alerts.length + ' à traiter', dark, false, '');
    h += '<div style="padding:12px;display:flex;flex-direction:column;gap:8px">';
    if (!alerts.length) {
      h += '<div style="padding:40px;text-align:center;color:' + t.ink2 + ';font-size:13px"><div style="font-size:36px;margin-bottom:8px">✅</div>Aucune alerte à traiter.</div>';
    }
    alerts.forEach(function (a) {
      var hi = a.sev === 'high';
      var sevColor = hi ? C.err : C.warn;
      var sevBg = hi ? C.errSoft : C.warnSoft;
      var icon = { brule: '🔥', avail: '🚫', E: '🌍', doublon: '🧬' }[a.type] || '⚠️';
      h += '<div style="background:' + t.surf + ';border:1px solid ' + t.bord + ';border-left:3px solid ' + sevColor + ';border-radius:12px;padding:12px">' +
        '<div style="display:flex;align-items:flex-start;gap:10px">' +
          '<div style="width:36px;height:36px;border-radius:8px;background:' + sevBg + ';display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0">' + icon + '</div>' +
          '<div style="flex:1">' +
            '<div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">' +
              '<span style="font-size:13px;font-weight:700;color:' + t.ink + '">' + esc(a.title) + '</span>' +
              badge(hi ? 'Bloquant' : 'À vérifier', hi ? 'danger' : 'warn', dark, true) +
            '</div>' +
            '<div style="font-size:12px;color:' + t.ink2 + ';margin-top:4px;line-height:1.45">' + esc(a.detail) + '</div>' +
            '<div style="display:flex;gap:6px;margin-top:10px">' +
              '<button data-action="journee" data-value="' + (a.journee || 1) + '" style="background:' + C.pri + ';color:white;border:none;padding:6px 10px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer">Voir J' + (a.journee || '?') + '</button>' +
              (a.player_id ? '<button data-action="player" data-value="' + a.player_id + '" style="background:' + t.surf2 + ';color:' + t.ink + ';border:none;padding:6px 10px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer">Fiche joueur</button>' : '') +
            '</div>' +
          '</div>' +
        '</div>' +
      '</div>';
    });
    h += '</div>';
    return h;
  }

  // ═══════════════════════════════════════════
  // RÉGLAGES
  // ═══════════════════════════════════════════
  function renderReglages() {
    var dark = S.dark; var t = tk(dark);
    var h = topBar('Réglages', '', dark, false, '');
    h += '<div style="padding:16px;display:flex;flex-direction:column;gap:14px">';

    // Club card
    h += '<div style="background:' + t.surf + ';border:1px solid ' + t.bord + ';border-radius:12px;padding:14px">' +
      '<div style="display:flex;align-items:center;gap:12px">' +
        '<div style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,#fbbf24,' + C.pri + ');display:flex;align-items:center;justify-content:center;font-size:22px">🏓</div>' +
        '<div>' +
          '<div style="font-size:14px;font-weight:700;color:' + t.ink + '">' + esc(cfg.clubName || 'Mon Club TT') + '</div>' +
          '<div style="font-size:11px;color:' + t.ink2 + ';margin-top:2px">Saison ' + esc(cfg.season || '—') + ' · ' + (cfg.teamsCount || S.teams.length || 0) + ' équipes</div>' +
        '</div>' +
      '</div></div>';

    // Affichage
    h += sectionWrap('Affichage',
      renderToggle('Mode sombre', S.dark, 'toggle-dark', t) +
      '<div style="display:flex;justify-content:space-between;padding:8px 12px;font-size:12px;align-items:center;color:' + t.ink + '"><span>Badges en couleur</span><span style="color:' + C.ok + ';font-size:11px;font-weight:600">Activé</span></div>',
      dark);

    // Données
    h += sectionWrap('Données',
      infoRow('Joueurs synchronisés', String(S.players.length), dark, false) +
      infoRow('Hors ligne', navigator.onLine ? '✅ Connecté' : '📡 Hors ligne', dark, false) +
      '<div style="padding:8px 12px">' +
        '<button data-action="sync" style="width:100%;background:' + C.pri + ';color:white;border:none;padding:10px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer">' + (S.syncing ? 'Synchronisation…' : '🔄 Synchroniser les joueurs') + '</button>' +
      '</div>',
      dark);

    // SMS
    h += sectionWrap('SMS pré-rédigés',
      infoRow('Demande de dispo', 'Modifier →', dark, true) +
      infoRow('Confirmation compo', 'Modifier →', dark, true),
      dark);

    // About
    h += sectionWrap('À propos',
      infoRow('Version', '1.0.0 · Open source', dark, false) +
      infoRow('Plugin', 'TT Team Planner', dark, false),
      dark);

    h += '</div>';
    return h;
  }

  function renderToggle(label, value, action, t) {
    var bg = value ? C.pri : t.surf2;
    var left = value ? '18px' : '2px';
    return '<div style="display:flex;justify-content:space-between;padding:10px 12px;font-size:12px;align-items:center;color:' + t.ink + '">' +
      '<span>' + label + '</span>' +
      '<button data-action="' + action + '" style="width:38px;height:22px;border-radius:999px;border:none;cursor:pointer;background:' + bg + ';position:relative">' +
        '<div style="position:absolute;top:2px;left:' + left + ';width:18px;height:18px;border-radius:50%;background:white;box-shadow:0 1px 3px rgba(0,0,0,0.2)"></div>' +
      '</button>' +
    '</div>';
  }

  // ═══════════════════════════════════════════
  // LOADING
  // ═══════════════════════════════════════════
  function renderLoading() {
    var t = tk(S.dark);
    if (S.loadError) {
      return '<div style="padding:24px;display:flex;flex-direction:column;gap:12px">'
        + '<div style="background:' + C.errSoft + ';border:1px solid ' + C.err + ';border-radius:10px;padding:14px">'
        + '<div style="font-size:13px;font-weight:700;color:' + C.err + ';margin-bottom:6px">⚠️ Erreur de chargement</div>'
        + '<code style="font-size:11px;color:' + t.ink + ';word-break:break-all;white-space:pre-wrap">' + esc(S.loadError) + '</code>'
        + '<div style="font-size:11px;color:' + t.ink2 + ';margin-top:8px">URL API : <code>' + esc(API) + '</code></div>'
        + '</div></div>';
    }
    return '<div style="display:flex;align-items:center;justify-content:center;height:60%;color:' + t.ink2 + ';font-size:13px;flex-direction:column;gap:12px"><div style="font-size:40px">🏓</div>Chargement…</div>';
  }

  // ═══════════════════════════════════════════
  // RENDER APP
  // ═══════════════════════════════════════════
  function renderApp() {
    var dark = S.dark; var t = tk(dark);
    var off = S.offline
      ? '<div class="ttp-offline">📡 Hors ligne — données du cache</div>'
      : '';
    var screen;
    if (S.loading) {
      screen = renderLoading();
    } else {
      switch (S.screen) {
        case 'journee':  screen = renderJournee(); break;
        case 'joueurs':  screen = renderJoueurs(); break;
        case 'player':   screen = renderPlayerDetail(); break;
        case 'alertes':  screen = renderAlertes(); break;
        case 'reglages': screen = renderReglages(); break;
        default:         screen = renderDashboard(); break;
      }
    }
    return '<div style="width:100%;height:100%;display:flex;flex-direction:column;background:' + t.bg + ';color:' + t.ink + ';overflow:hidden;position:relative">' +
      off +
      '<div style="flex:1;overflow-y:auto;overflow-x:hidden;background:' + t.bg + ';-webkit-overflow-scrolling:touch">' + screen + '</div>' +
      renderNav() +
      (S.picker ? renderPicker() : '') +
    '</div>';
  }

  // ═══════════════════════════════════════════
  // STATE + EVENTS
  // ═══════════════════════════════════════════
  var root;

  function setState(patch) {
    var prevScreen = S.screen;
    Object.assign(S, patch);
    if ('screen' in patch && S.screen !== prevScreen) pushHash(S);
    render();
  }

  function render() {
    if (!root) return;
    var active = document.activeElement && root.contains(document.activeElement) ? document.activeElement : null;
    var prevInput = active ? active.dataset.input : null;
    root.innerHTML = renderApp();
    attachEvents();
    if (S.picker) {
      var pi = root.querySelector('[data-input="picker-search"]');
      if (pi) { pi.focus(); }
    } else if (prevInput === 'search' && S.screen === 'joueurs') {
      var si = root.querySelector('[data-input="search"]');
      if (si) { si.focus(); try { si.setSelectionRange(S.searchQ.length, S.searchQ.length); } catch (e) {} }
    } else if (prevInput === 'edit-phone') {
      var ef = root.querySelector('[data-input="edit-phone"]');
      if (ef) { ef.focus(); try { ef.setSelectionRange(ef.value.length, ef.value.length); } catch (e) {} }
    } else if (prevInput === 'edit-notes') {
      var nt = root.querySelector('[data-input="edit-notes"]');
      if (nt) { nt.focus(); }
    }
  }

  function attachEvents() {
    root.addEventListener('click', handleClick);
    var inp = root.querySelector('[data-input="search"]');
    if (inp) inp.addEventListener('input', function (e) { S.searchQ = e.target.value; render(); });
    var pi = root.querySelector('[data-input="picker-search"]');
    if (pi) pi.addEventListener('input', function (e) { S.pickerQ = e.target.value; render(); });
    var ep = root.querySelector('[data-input="edit-phone"]');
    if (ep) ep.addEventListener('input', function (e) { S.editPhone = e.target.value; });
    var en = root.querySelector('[data-input="edit-notes"]');
    if (en) en.addEventListener('input', function (e) { S.editNotes = e.target.value; });
  }

  function toggleAvailability(playerId, phase, round) {
    var CYCLE = ['unknown', 'available', 'unavailable', 'uncertain'];
    var cur = S.availabilities.find(function (a) {
      return String(a.player_id) === String(playerId) && a.phase === phase && a.round === round;
    });
    var curStatus = cur ? cur.status : 'unknown';
    var nextStatus = CYCLE[(CYCLE.indexOf(curStatus) + 1) % CYCLE.length];
    var season = cfg.season || '';

    // Optimistic update
    var found = false;
    var updated = S.availabilities.map(function (a) {
      if (String(a.player_id) === String(playerId) && a.phase === phase && a.round === round) {
        found = true;
        return Object.assign({}, a, { status: nextStatus });
      }
      return a;
    });
    if (!found) updated.push({ player_id: playerId, season: season, phase: phase, round: round, status: nextStatus, comment: '' });
    setState({ availabilities: updated });

    apiFetch('/availability', {
      method: 'POST',
      body: JSON.stringify({ player_id: playerId, season: season, phase: phase, round: round, status: nextStatus, comment: '' })
    }).catch(function () {
      // Rollback on error
      setState({ availabilities: S.availabilities.map(function (a) {
        if (String(a.player_id) === String(playerId) && a.phase === phase && a.round === round) {
          return Object.assign({}, a, { status: curStatus });
        }
        return a;
      })});
    });
  }

  function savePlayerEdit() {
    var id = S.playerId;
    setState({ editSaving: true });
    apiFetch('/players/' + id, {
      method: 'PATCH',
      body: JSON.stringify({ phone: S.editPhone, notes: S.editNotes })
    }).then(function (updated) {
      // Mise à jour optimiste dans S.players
      S.players = S.players.map(function (p) {
        return String(p.id) === String(id) ? updated : p;
      });
      setState({ playerEdit: false, editSaving: false });
    }).catch(function () {
      setState({ editSaving: false });
    });
  }

  function handleClick(e) {
    var el = e.target.closest('[data-action]');
    if (!el || el.tagName === 'A') return;
    var a = el.dataset.action;
    var v = el.dataset.value;
    switch (a) {
      case 'tab':          goTab(v); break;
      case 'journee':      setState({ journeeN: parseInt(v), screen: 'journee', tab: 'journees' }); loadCompositions(); loadAlerts(); break;
      case 'goto-phase-journee':
        var targetPhase = parseInt(el.dataset.phase);
        var targetJournee = parseInt(el.dataset.journee);
        S.phase = targetPhase;
        S.journees = buildJournees();
        setState({ journeeN: targetJournee, screen: 'journee', tab: 'journees' });
        loadCompositions(); loadAlerts();
        break;
      case 'player':       if (!S.picker) setState({ playerId: parseInt(v), screen: 'player' }); break;
      case 'back':         goBack(); break;
      case 'goto':         setState({ screen: el.dataset.screen, tab: el.dataset.tab || el.dataset.screen }); break;
      case 'phase':        var newPhase = parseInt(v); setState({ phase: newPhase, journees: buildJournees(newPhase) }); break;
      case 'team-tab':     setState({ activeTeamI: parseInt(v) }); break;
      case 'filter':       setState({ playerFilter: v }); break;
      case 'toggle-dark':  setState({ dark: !S.dark }); break;
      case 'sync':         syncPlayers(); break;
      case 'slot-open':    setState({ picker: { teamCode: el.dataset.team, slotNum: parseInt(el.dataset.slot) }, pickerQ: '' }); break;
      case 'slot-assign':  if (S.picker) assignSlot(S.picker.teamCode, S.picker.slotNum, parseInt(el.dataset.playerId)); break;
      case 'slot-remove':  e.stopPropagation(); removeSlot(el.dataset.team, parseInt(el.dataset.slot)); break;
      case 'picker-close': setState({ picker: null, pickerQ: '' }); break;
      case 'picker-clear':      S.pickerQ = ''; render(); break;
      case 'avail-toggle':
        toggleAvailability(parseInt(el.dataset.playerId), parseInt(el.dataset.phase), parseInt(el.dataset.round));
        break;
      case 'player-edit-open':
        var ep = getPlayer(S.playerId);
        if (ep) setState({ playerEdit: true, editPhone: ep.phone || '', editNotes: ep.notes || '', editSaving: false });
        break;
      case 'player-edit-cancel': setState({ playerEdit: false }); break;
      case 'player-edit-save':   savePlayerEdit(); break;
    }
  }

  // ═══════════════════════════════════════════
  // ROUTING — hash-based
  // ═══════════════════════════════════════════
  function screenToHash(s) {
    if (s.screen === 'journee')  return '#journee/' + s.journeeN;
    if (s.screen === 'player')   return '#joueur/'  + s.playerId;
    if (s.screen === 'joueurs')  return '#joueurs';
    if (s.screen === 'alertes')  return '#alertes';
    if (s.screen === 'reglages') return '#reglages';
    return '#';
  }

  function hashToState(hash) {
    var h = (hash || '').replace(/^#\/?/, '');
    if (!h || h === 'dashboard') return { screen: 'dashboard', tab: 'dashboard' };
    if (h === 'joueurs')  return { screen: 'joueurs',  tab: 'joueurs' };
    if (h === 'alertes')  return { screen: 'alertes',  tab: 'alertes' };
    if (h === 'reglages') return { screen: 'reglages', tab: 'reglages' };
    var m = h.match(/^journee\/(\d+)$/);
    if (m) return { screen: 'journee', tab: 'journees', journeeN: parseInt(m[1]) };
    m = h.match(/^joueur\/(\d+)$/);
    if (m) return { screen: 'player', tab: 'joueurs', playerId: parseInt(m[1]) };
    return { screen: 'dashboard', tab: 'dashboard' };
  }

  function pushHash(s) {
    var hash = screenToHash(s);
    if (w.location.hash !== hash) history.pushState(null, '', hash);
  }

  function goTab(id) {
    var map = { dashboard: 'dashboard', journees: 'journee', joueurs: 'joueurs', alertes: 'alertes', reglages: 'reglages' };
    setState({ tab: id, screen: map[id] || id });
    if (id === 'journees') { loadCompositions(); loadAlerts(); }
  }

  function goBack() {
    if (S.screen === 'player')  { setState({ screen: S.tab === 'joueurs' ? 'joueurs' : 'journee' }); }
    else if (S.screen === 'journee') { setState({ screen: 'dashboard', tab: 'dashboard' }); }
    else { setState({ screen: 'dashboard', tab: 'dashboard' }); }
  }

  // ═══════════════════════════════════════════
  // PWA — register service worker
  // ═══════════════════════════════════════════
  function registerSW() {
    if ('serviceWorker' in navigator && cfg.swUrl) {
      navigator.serviceWorker.register(cfg.swUrl).catch(function () {});
    }
  }

  // ═══════════════════════════════════════════
  // INIT
  // ═══════════════════════════════════════════
  function applyHash() {
    var parsed = hashToState(w.location.hash);
    Object.assign(S, parsed);
    if (parsed.screen === 'journee') { loadCompositions(); loadAlerts(); }
    render();
  }

  function init() {
    root = d.getElementById('ttp-app');
    if (!root) return;

    w.addEventListener('online',  function () { setState({ offline: false }); });
    w.addEventListener('offline', function () { setState({ offline: true });  });

    // Restore state from URL hash (deep link / browser back-forward)
    w.addEventListener('popstate', applyHash);

    // Apply hash from URL on first load
    var initial = hashToState(w.location.hash);
    Object.assign(S, initial);
    // Ensure the initial URL reflects the canonical hash
    history.replaceState(null, '', screenToHash(S));

    render();          // show loading spinner immediately
    // After data loads, re-apply hash (player detail needs players in S)
    loadAll().then(function () {
      var h = hashToState(w.location.hash);
      if (h.screen !== 'dashboard') { Object.assign(S, h); render(); }
      if (h.screen === 'journee') { loadCompositions(); loadAlerts(); }
    });
    registerSW();
  }

  if (d.readyState === 'loading') {
    d.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})(window, document);
