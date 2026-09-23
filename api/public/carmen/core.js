/* Carmen WMS · núcleo: utilidades, estado, datos, servidor, componentes (modal, tablas, autocompletado, Excel, impresión). */
"use strict";
const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];
const esc = s => String(s ?? "").replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
const ICONS = {
  home: '<path d="M3 11l9-8 9 8v9a2 2 0 0 1-2 2h-4v-7H9v7H5a2 2 0 0 1-2-2z"/>',
  in: '<path d="M12 3v12"/><path d="M7 10l5 5 5-5"/><path d="M4 21h16"/>',
  out: '<path d="M12 21V9"/><path d="M7 14l5-5 5 5"/><path d="M4 3h16"/>',
  stock: '<path d="M3 7l9-4 9 4-9 4z"/><path d="M3 7v10l9 4 9-4V7"/><path d="M12 11v10"/>',
  box: '<rect x="3" y="7" width="18" height="14" rx="2"/><path d="M3 11h18M8 7V4h8v3"/>',
  users: '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><circle cx="17.5" cy="9" r="2.5"/><path d="M15.5 14.5a5 5 0 0 1 6 5"/>',
  truck: '<path d="M1 7h12v9H1z"/><path d="M13 10h5l4 3v3h-9"/><circle cx="5.5" cy="18" r="2"/><circle cx="17.5" cy="18" r="2"/>',
  map: '<path d="M3 5l6-2 6 2 6-2v16l-6 2-6-2-6 2z"/><path d="M9 3v16M15 5v16"/>',
  upload: '<path d="M12 16V4"/><path d="M7 9l5-5 5 5"/><path d="M4 20h16"/>',
  tag: '<path d="M3 3h8l10 10-8 8L3 11z"/><circle cx="8" cy="8" r="1.5"/>',
  shield: '<path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6z"/><path d="M9 12l2 2 4-4"/>',
  phone: '<rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/>',
  sliders: '<path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/>',
  link: '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
  scan: '<path d="M3 8V5a2 2 0 0 1 2-2h3M16 3h3a2 2 0 0 1 2 2v3M21 16v3a2 2 0 0 1-2 2h-3M8 21H5a2 2 0 0 1-2-2v-3"/><path d="M7 12h10"/>',
  check: '<path d="M5 12l5 5L20 7"/>',
  x: '<path d="M6 6l12 12M18 6L6 18"/>',
  plus: '<path d="M12 5v14M5 12h14"/>',
  search: '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>',
  filter: '<path d="M3 5h18l-7 8v6l-4 2v-8z"/>',
  download: '<path d="M12 4v12"/><path d="M7 11l5 5 5-5"/><path d="M4 20h16"/>',
  print: '<path d="M6 9V3h12v6"/><rect x="3" y="9" width="18" height="8" rx="2"/><path d="M6 14h12v7H6z"/>',
  edit: '<path d="M4 20h4l10-10-4-4L4 16z"/><path d="M13 7l4 4"/>',
  clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
  alert: '<path d="M12 3l10 18H2z"/><path d="M12 10v4M12 18h0"/>',
  chev: '<path d="M9 6l6 6-6 6"/>',
  arrow: '<path d="M5 12h14M13 6l6 6-6 6"/>',
  move: '<path d="M5 9l-3 3 3 3M9 5l3-3 3 3M15 19l-3 3-3-3M19 9l3 3-3 3M2 12h20M12 2v20"/>',
  count: '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 9h4M7 13h4M7 17h4M14 9h3M14 13h3M14 17h3"/>',
  qr: '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 14h3v3h-3zM19 14h2M14 19h2M19 19h2"/>',
  barcode: '<path d="M3 5v14M7 5v14M10 5v14M14 5v14M17 5v14M21 5v14"/>',
  cloud: '<path d="M7 18a4 4 0 0 1-.5-8A6 6 0 0 1 18 9a4 4 0 0 1 0 9z"/>',
  wifi: '<path d="M2 9a15 15 0 0 1 20 0M5 12.5a10 10 0 0 1 14 0M8.5 16a5 5 0 0 1 7 0"/><path d="M12 20h0"/>',
  bell: '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
  logout: '<path d="M10 17l5-5-5-5M15 12H3M21 3v18"/>',
  grid: '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
  list: '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
  layers: '<path d="M12 2l10 5-10 5L2 7z"/><path d="M2 12l10 5 10-5M2 17l10 5 10-5"/>',
  refresh: '<path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/>',
  eye: '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
  lock: '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
  history: '<path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/><path d="M12 7v5l4 2"/>',
  file: '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6"/>',
  spark: '<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8z"/>',
  sun: '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
  moon: '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
  zap: '<path d="M13 2L3 14h8l-1 8 10-12h-8z"/>',
  pkg: '<path d="M21 8l-9-5-9 5v8l9 5 9-5z"/><path d="M3 8l9 5 9-5M12 13v8"/>',
  wave: '<path d="M2 12c2-4 4-4 6 0s4 4 6 0 4-4 6 0"/>',
  trash: '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
  save: '<path d="M5 3h11l3 3v15H5z"/><path d="M8 3v5h8V3M8 21v-7h8v7"/>',
  key: '<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M16 7l3 3"/>',
  excel: '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M8 8l8 8M16 8l-8 8"/>',
  undo: '<path d="M9 14L4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 0 10h-3"/>',
  box2: '<rect x="3" y="3" width="18" height="18" rx="2"/>',
};
const ic = (n, cls = "") => `<svg class="i ${cls}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${ICONS[n] || ""}</svg>`;

/* ---------------------------------------------------------------- formato */
const pad = (n, w = 2) => String(n).padStart(w, "0");
const fmt = n => new Intl.NumberFormat("es-BO").format(Math.round((+n || 0) * 100) / 100);
const fmtD = s => { if (!s) return "—"; const [y, m, d] = String(s).slice(0, 10).split("-"); return d ? `${d}/${m}/${y}` : String(s); };
const localISO = (d = new Date()) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const todayISO = () => localISO();
const nowTS = () => { const d = new Date(); return `${localISO(d)} ${pad(d.getHours())}:${pad(d.getMinutes())}`; };
const nowHuman = () => { const d = new Date(); return `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`; };
const daysAgoISO = n => { const d = new Date(); d.setDate(d.getDate() - n); return localISO(d); };
const daysBetween = (a, b) => Math.round((new Date(b) - new Date(a)) / 864e5);
const shiftNow = () => { const h = new Date().getHours(); return h >= 6 && h < 14 ? "T1" : h >= 14 && h < 22 ? "T2" : "T3"; };
const norm = s => String(s ?? "").toLowerCase().normalize("NFD").replace(/[̀-ͯ]/g, "");
const has = (v, q) => norm(v).includes(norm(q));
const sum = (a, f = x => x) => a.reduce((t, x) => t + (+f(x) || 0), 0);
const uniq = a => [...new Set(a)];
const clone = o => JSON.parse(JSON.stringify(o));
const initials = n => String(n || "?").split(" ").filter(Boolean).map(x => x[0]).slice(0, 2).join("").toUpperCase();

const ING_TYPES = ["Producción", "Compra", "Devolución", "Transferencia"];
const ING_STATES = ["Borrador", "Habilitado", "En recepción", "Cerrado", "Anulado"];
const OUT_STATES = ["Recibido", "Preparación", "Validado", "Embalado", "Despachado"];
const UMS = ["BUL", "UN", "KG", "M", "ROLLO", "CJ", "PAQ", "LT"];
const DPTOS = ["Santa Cruz", "Beni", "Pando", "La Paz", "Cochabamba", "Tarija", "Oruro", "Potosí", "Chuquisaca"];
const DESK_ROLES = ["Administrador", "Encargado de almacén", "Operador", "Supervisor (lectura)"];
const COL_ROLES = ["Encargado", "Operador"];
const PERMS = ["Recibir", "Picking / despacho", "Reubicar", "Ajustar stock", "Aprobar diferencias", "Reportes", "Maestros y cargas", "Usuarios y parámetros"];
const PERM_KEYS = ["recibir", "picking", "reubicar", "ajustar", "aprobar", "reportes", "maestros", "config"];
const DEFAULT_PERMS = { "Administrador": [1, 1, 1, 1, 1, 1, 1, 1], "Encargado de almacén": [1, 1, 1, 1, 1, 1, 1, 0], "Operador": [1, 1, 1, 0, 0, 0, 0, 0], "Supervisor (lectura)": [0, 0, 0, 0, 0, 1, 0, 0] };
const VIRTUAL_ZONES = ["Recepción", "Virtual", "Cuarentena", "Despacho"];

/* ---------------------------------------------------------------- estado */
const state = { route: "inicio", wh: CW.wh || "BOL2", railCollapsed: false, sub: {}, selLoc: null, filters: {}, pages: {}, expand: {} };
try { const t = localStorage.getItem("cwms-theme"); if (t) document.documentElement.dataset.theme = t; } catch (e) {}
try { state.railCollapsed = localStorage.getItem("cwms-rail") === "1"; } catch (e) {}

const WH = () => WAREHOUSES.find(w => w.id === state.wh) || WAREHOUSES[0] || { id: state.wh, name: state.wh };
const can = p => !!(CW.user && (CW.user.perms || []).includes(p));
const me = () => CW.user ? CW.user.user : "";

/* ---------------------------------------------------------------- consultas */
const P = code => PRODUCTS.find(p => p.codigo === code) || { codigo: code, desc: "(producto no registrado)", um: "UN", cat: "—", sub: "—", rot: "C", min: 0, max: 0, vidaUtil: 0, precio: 0, estado: "INACTIVO", fabrica: "—", peso: 0 };
const cli = code => CLIENTS.find(c => c.codigo === code) || { codigo: code, nombre: code || "—", dpto: "—", ciudad: "—", canal: "—", tel: "—", nit: "—", estado: "ACTIVO", pedidos: 0 };
const L = id => LOCATIONS.find(l => l.id === id);
const R = id => RACKS.find(r => r.id === id);
const rackWh = r => r.wh || "BOL2";
const whRacks = (wh = state.wh) => RACKS.filter(r => rackWh(r) === wh);
const whLocs = (wh = state.wh) => LOCATIONS.filter(l => (l.wh || "BOL2") === wh);
const whStock = (wh = state.wh) => STOCK.filter(s => (s.wh || "BOL2") === wh);
const whIng = (wh = state.wh) => INGRESOS.filter(i => (i.wh || "BOL2") === wh);
const whPed = (wh = state.wh) => PEDIDOS.filter(p => (p.wh || "BOL2") === wh);
const whDsp = (wh = state.wh) => DESPACHOS.filter(d => (d.wh || "BOL2") === wh);
const whKdx = (wh = state.wh) => KARDEX.filter(k => (k.wh || "BOL2") === wh);
const whCnt = (wh = state.wh) => CONTEOS.filter(c => (c.wh || "BOL2") === wh);
const whAdj = (wh = state.wh) => AJUSTES.filter(a => (a.wh || "BOL2") === wh);
const zoneOf = locId => { const l = L(locId); const r = l && R(l.rack); return r ? r.zona : "—"; };
const isVirtualLoc = locId => VIRTUAL_ZONES.includes(zoneOf(locId));
const recepLocs = (wh = state.wh) => whLocs(wh).filter(l => zoneOf(l.id) === "Recepción").sort((a, b) => a.sort - b.sort);
const stockOf = (code, wh = state.wh) => sum(whStock(wh).filter(s => s.codigo === code), s => s.qty);
const availOf = (code, wh = state.wh) => sum(pickableStock(code, wh), s => s.qty - (s.reservado || 0));
const occupancy = loc => { if (!loc) return 0; const s = STOCK.filter(x => x.loc === loc.id); return s.length ? Math.min(1, sum(s, x => x.qty) / ((loc.cap || 6) * 20)) : 0; };
const isExpired = s => s.venc && s.venc < todayISO();
const activeCountLocs = () => new Set(CONTEOS.filter(c => ["Habilitado", "En curso"].includes(c.estado) && c.bloquear).flatMap(c => (c.lines || []).filter(l => !l.counted).map(l => l.loc)));
function pickableStock(code, wh = state.wh) {
  const blockedByCount = activeCountLocs();
  const skipExpired = setting("bloquearVencido");
  return whStock(wh).filter(s => s.codigo === code && s.estado === "DISPONIBLE" && !isVirtualLoc(s.loc) && !(L(s.loc) || {}).blocked && !blockedByCount.has(s.loc) && !(skipExpired && isExpired(s)) && s.qty - (s.reservado || 0) > 0);
}
function allocOrder(code) {
  const p = P(code); const fefo = setting("asignacion") === "FEFO cuando aplica" && p.vidaUtil > 0;
  const lifo = setting("asignacion") === "LIFO";
  return (a, b) => (fefo ? String(a.venc || "9999").localeCompare(String(b.venc || "9999")) : 0) || (lifo ? String(b.ingreso).localeCompare(String(a.ingreso)) : String(a.ingreso).localeCompare(String(b.ingreso))) || ((L(a.loc) || {}).sort || 0) - ((L(b.loc) || {}).sort || 0);
}
const userName = u => (USERS_COL.find(x => x.user === u) || USERS_DESK.find(x => x.user === u) || { nombre: u || "—" }).nombre;
const whUsersCol = (wh = state.wh) => USERS_COL.filter(u => u.wh === wh && u.estado === "HABILITADO");

/* ---------------------------------------------------------------- parámetros */
const DEFAULT_SETTINGS = {
  asignacion: "FIFO", reservaOla: true, desviarUbicacion: true, ciegaCompras: true, ciegaProduccion: false, tolerancia: 0, alertaVenc: 30, bloquearVencido: true,
  put1: "Consolidar con mismo ítem y lote", put2: "Cercanía a picking (clase A)", put3: "Capacidad disponible", noMezclarMP: true, noMezclarLotes: true, claseCFondo: true,
  formatoUbic: "{RACK}-C{COL:2}-N{NIVEL}", formatoLote: "L{AA}{SEM:2}{DIA}-{TURNO}", prefijoSSCC: "0779876", qrDefecto: "GS1", reutilizarUbic: false, largoSKU: 12,
  urlERP: "", syncItems: "Manual (importación)", syncPedidos: "Manual (importación)", syncMovs: "Exportación diaria", conciliacion: "23:30", simec: false,
  respaldoFrecuencia: "Manual", respaldoRetencion: 30, respaldoDestino: "Descarga Excel", exportMaestros: true, pruebaRestauracion: true,
  razonSocial: "Plásticos Carmen S.R.L.", zonaHoraria: "America/La_Paz (UTC−4)", idioma: "Español (Bolivia)", decimal: "Coma (1.234,50)", turnos: "T1 06:00 · T2 14:00 · T3 22:00", inactividad: 60,
  perms: DEFAULT_PERMS, history: [],
};
const setting = k => { const s = SETTINGS.find(x => x.k === k); return s ? s.v : DEFAULT_SETTINGS[k]; };
function lotCode(turno = shiftNow(), d = new Date()) {
  const start = new Date(d.getFullYear(), 0, 1); const week = Math.ceil(((d - start) / 864e5 + start.getDay() + 1) / 7);
  return String(setting("formatoLote")).replace("{AA}", String(d.getFullYear()).slice(2)).replace("{SEM:2}", pad(week)).replace("{DIA}", ((d.getDay() + 6) % 7) + 1).replace("{TURNO}", String(turno).replace("T", ""));
}
const locCode = (rack, col, nivel) => String(setting("formatoUbic")).replace("{RACK}", rack).replace("{COL:2}", pad(col)).replace("{COL}", col).replace("{NIVEL}", nivel);

/* ---------------------------------------------------------------- presentación */
const PILL = { "Cerrado": "ok", "Despachado": "ok", "Entregado": "ok", "HABILITADO": "ok", "ACTIVO": "ok", "DISPONIBLE": "ok", "Finalizado": "ok", "Aprobado": "ok", "Ocupada": "info", "En recepción": "info", "Preparación": "info", "En curso": "info", "En ruta": "info", "Cargando": "info", "Habilitado": "warn", "Recibido": "warn", "Pendiente": "warn", "Validado": "info", "Embalado": "info", "Borrador": "neutral", "Vacía": "neutral", "Anulado": "bad", "Rechazado": "bad", "Bloqueada": "bad", "DESHABILITADO": "bad", "INACTIVO": "bad", "MANTENIMIENTO": "warn", "CUARENTENA": "warn", "BLOQUEADO": "bad", "VENCIDO": "bad", "EN RUTA": "info" };
const pillFor = s => `<span class="pill ${PILL[s] || "neutral"}">${esc(s)}</span>`;
const rotTag = r => `<span class="tag ${r === "A" ? "a" : ""}" title="Rotación ${esc(r)}">${esc(r)}</span>`;
const heat = v => v === 0 ? "" : v < .25 ? "h2" : v < .5 ? "h3" : v < .8 ? "h4" : "h5";
function sparkline(vals, w = 96, h = 36, cls = "") {
  if (!vals.length) vals = [0, 0]; if (vals.length === 1) vals = [vals[0], vals[0]];
  const mx = Math.max(...vals), mn = Math.min(...vals); const r = mx - mn || 1;
  const pts = vals.map((v, i) => [i * (w - 4) / (vals.length - 1) + 2, h - 4 - (v - mn) / r * (h - 8)]);
  const d = pts.map((p, i) => (i ? "L" : "M") + p[0].toFixed(1) + " " + p[1].toFixed(1)).join(" "); const last = pts[pts.length - 1];
  return `<svg class="spark chart ${cls}" viewBox="0 0 ${w} ${h}" preserveAspectRatio="none"><path class="area" d="${d} L${last[0]} ${h} L2 ${h}Z"/><path class="line" d="${d}"/><circle class="pt" cx="${last[0]}" cy="${last[1]}" r="3"/></svg>`;
}
function barChart(items, { w = 520, h = 180, hi = null, unit = "" } = {}) {
  if (!items.length) return `<div class="empty small">Sin datos</div>`;
  const mx = Math.max(...items.map(i => i.v)) || 1; const padL = 28, padB = 26, padT = 10; const bw = (w - padL - 10) / items.length;
  const ticks = [0, .5, 1].map(t => { const y = padT + (1 - t) * (h - padT - padB); return `<line x1="${padL}" x2="${w - 4}" y1="${y}" y2="${y}"/><text x="${padL - 6}" y="${y + 4}" text-anchor="end">${fmt(Math.round(mx * t))}</text>`; }).join("");
  const bars = items.map((it, i) => { const bh = (it.v / mx) * (h - padT - padB); const x = padL + i * bw + bw * .18; const y = h - padB - bh; return `<g><title>${esc(it.l)}: ${fmt(it.v)} ${unit}</title><rect class="cbar ${it.l === hi ? "hi" : ""}" x="${x}" y="${y}" width="${bw * .64}" height="${Math.max(0, bh)}" rx="4"/><text x="${x + bw * .32}" y="${h - 8}" text-anchor="middle">${esc(it.l)}</text></g>`; }).join("");
  return `<svg class="chart" viewBox="0 0 ${w} ${h}"><g class="grid">${ticks}</g>${bars}</svg>`;
}
function lineChart(series, { w = 520, h = 180, labels = [] } = {}) {
  const all = series.flatMap(s => s.v); const mx = Math.max(...all) || 1; const padL = 36, padB = 24, padT = 10; const n = series[0].v.length;
  const X = i => padL + i * (w - padL - 10) / Math.max(1, n - 1), Y = v => padT + (1 - v / mx) * (h - padT - padB);
  const grid = [0, .5, 1].map(t => `<line x1="${padL}" x2="${w - 4}" y1="${Y(mx * t)}" y2="${Y(mx * t)}"/><text x="${padL - 6}" y="${Y(mx * t) + 4}" text-anchor="end">${fmt(Math.round(mx * t))}</text>`).join("");
  const paths = series.map((s, k) => { const d = s.v.map((v, i) => (i ? "L" : "M") + X(i).toFixed(1) + " " + Y(v).toFixed(1)).join(" "); return `<path class="line" style="stroke:${s.c}" d="${d}"/>${k === 0 ? `<path class="area" style="fill:${s.c}" d="${d} L${X(n - 1)} ${h - padB} L${X(0)} ${h - padB}Z"/>` : ""}<circle class="pt" style="fill:${s.c}" cx="${X(n - 1)}" cy="${Y(s.v[n - 1])}" r="3.5"/>`; }).join("");
  const lbls = labels.map((l, i) => `<text x="${X(i)}" y="${h - 6}" text-anchor="middle">${esc(l)}</text>`).join("");
  return `<svg class="chart" viewBox="0 0 ${w} ${h}"><g class="grid">${grid}</g>${paths}${lbls}</svg>`;
}

/* ---------------------------------------------------------------- avisos, panel lateral, modal, confirmación */
function toast(msg, kind = "ok", ms = 3600) {
  const t = document.createElement("div"); t.className = `toast ${kind}`; t.setAttribute("role", "status");
  t.innerHTML = `${ic(kind === "ok" ? "check" : kind === "bad" ? "alert" : "bell")}<span>${msg}</span>`;
  $("#toasts").appendChild(t); setTimeout(() => t.remove(), ms);
}
function openDrawer(title, body, foot = "", sub = "") {
  const d = $("#drawer");
  d.innerHTML = `<div class="d-h"><div>${sub ? `<div class="crumbs">${sub}</div>` : ""}<h2>${title}</h2></div><button class="btn icon ghost" data-close title="Cerrar">${ic("x")}</button></div><div class="d-b">${body}</div>${foot ? `<div class="d-f">${foot}</div>` : ""}`;
  d.classList.add("open"); d.setAttribute("aria-hidden", "false"); $("#scrim").classList.add("open");
  state.drawerFn = null;
}
function openModal(title, body, foot = "", { wide = false, onOpen } = {}) {
  { const old = $("#modalBox"); const nb = old.cloneNode(false); old.replaceWith(nb); } // sin escuchas de modales anteriores
  $("#modalBox").style.width = wide ? "min(1100px,100%)" : "";
  $("#modalBox").innerHTML = `<div class="m-h"><h2>${title}</h2><button class="btn icon ghost" data-close title="Cerrar">${ic("x")}</button></div><div class="m-b">${body}</div>${foot ? `<div class="m-f">${foot}</div>` : ""}`;
  $("#modal").classList.add("open"); $("#modal").setAttribute("aria-hidden", "false"); $("#scrim").classList.add("open");
  const first = $("#modalBox .m-b input:not([type=checkbox]):not([readonly]), #modalBox .m-b select, #modalBox .m-b textarea"); if (first) setTimeout(() => { if (!$("#modalBox").contains(document.activeElement)) first.focus(); }, 60);
  if (onOpen) onOpen($("#modalBox"));
}
function closeModal() { $("#modal").classList.remove("open"); $("#modal").setAttribute("aria-hidden", "true"); if (!$("#drawer").classList.contains("open")) $("#scrim").classList.remove("open"); }
function closeOverlays() {
  $("#drawer").classList.remove("open"); $("#modal").classList.remove("open"); $("#scrim").classList.remove("open");
  $("#drawer").setAttribute("aria-hidden", "true"); $("#modal").setAttribute("aria-hidden", "true"); acClose();
}
const overlayOpen = () => $("#drawer").classList.contains("open") || $("#modal").classList.contains("open") || !!$("#dlg");
/** Diálogo de confirmación / captura (por encima de todo). resolve(valores) o null. */
function dialog(title, body = "", { ok = "Aceptar", cancel = "Cancelar", danger = false, validate } = {}) {
  return new Promise(resolve => {
    const w = document.createElement("div"); w.id = "dlg"; w.className = "dlg";
    w.innerHTML = `<div class="dlg-box" role="dialog" aria-modal="true"><h3>${title}</h3><div class="dlg-b">${body}</div><div class="dlg-f">${cancel ? `<button class="btn" data-dlg="0">${cancel}</button>` : ""}<button class="btn ${danger ? "danger" : "primary"}" data-dlg="1">${ok}</button></div></div>`;
    document.body.appendChild(w);
    const f = $("input,select,textarea", w); setTimeout(() => (f || $("[data-dlg='1']", w)).focus(), 40);
    const done = v => { w.remove(); document.removeEventListener("keydown", key, true); resolve(v); };
    const accept = () => { const v = formVals(w); if (validate) { const e = validate(v); if (e) { toast(e, "bad"); return; } } done(v); };
    const key = e => { if (e.key === "Escape") { e.stopPropagation(); done(null); } if (e.key === "Enter" && e.target.tagName !== "TEXTAREA" && !e.target.closest("[data-ac]")) { e.preventDefault(); accept(); } };
    document.addEventListener("keydown", key, true);
    w.addEventListener("click", e => { const b = e.target.closest("[data-dlg]"); if (!b) return; b.dataset.dlg === "1" ? accept() : done(null); });
  });
}
const confirmDlg = (title, body, opts) => dialog(title, body ? `<p style="margin:0">${body}</p>` : "", opts).then(v => v !== null);
/** Valores de un formulario por atributo name (checkbox → boolean, number → número). */
function formVals(root) {
  const o = {};
  $$("[name]", root).forEach(el => {
    const n = el.name; if (!n) return;
    if (el.type === "checkbox") { if (n.endsWith("[]")) { (o[n.slice(0, -2)] = o[n.slice(0, -2)] || []); if (el.checked) o[n.slice(0, -2)].push(el.value); } else o[n] = el.checked; }
    else if (el.type === "radio") { if (el.checked) o[n] = el.value; }
    else if (el.type === "number") o[n] = el.value === "" ? null : +el.value;
    else if (el.multiple) o[n] = [...el.selectedOptions].map(x => x.value);
    else o[n] = el.value.trim();
  });
  $$("[data-tog]", root).forEach(t => o[t.dataset.tog] = t.classList.contains("on"));
  $$("[data-chipset]", root).forEach(c => o[c.dataset.chipset] = $$(".chip.on", c).map(x => x.dataset.v));
  return o;
}
const opt = (v, sel, lbl) => `<option value="${esc(v)}" ${String(v) === String(sel) ? "selected" : ""}>${esc(lbl ?? v)}</option>`;
const opts = (list, sel) => list.map(x => Array.isArray(x) ? opt(x[0], sel, x[1]) : opt(x, sel)).join("");
const fld = (label, inner, cls = "") => `<div class="field ${cls}"><label>${label}</label>${inner}</div>`;
const inp = (name, val = "", attrs = "") => `<input class="input" name="${name}" value="${esc(val ?? "")}" ${attrs}>`;

/* ---------------------------------------------------------------- servidor */
async function api(method, url, body) {
  let r;
  try { r = await fetch(url, { method, credentials: "same-origin", headers: { "Content-Type": "application/json", Accept: "application/json", "X-CSRF-TOKEN": CW.csrf, "X-Requested-With": "XMLHttpRequest" }, body: body === undefined ? undefined : JSON.stringify(body) }); }
  catch (e) { const err = new Error("Sin conexión con el servidor."); err.network = true; throw err; }
  let data = null; try { data = await r.json(); } catch (e) {}
  if (!r.ok) {
    const msg = data && data.errors ? Object.values(data.errors).flat()[0] : (data && data.message) || `Error ${r.status}`;
    const err = new Error(r.status === 419 ? "La sesión expiró. Recarga la página." : msg); err.status = r.status; err.data = data;
    if (r.status === 401) { setTimeout(() => location.reload(), 1200); }
    throw err;
  }
  return data;
}
const DATASET_NAMES = ["WAREHOUSES", "RACKS", "PRODUCTS", "LOCATIONS", "STOCK", "CLIENTS", "DRIVERS", "TRUCKS", "USERS_DESK", "USERS_COL", "DEVICES", "INGRESOS", "PEDIDOS", "DESPACHOS", "KARDEX", "CONTEOS", "IMPORT_HISTORY", "ALERTS", "AJUSTES", "SETTINGS", "LABELS"];
const DS = () => ({ WAREHOUSES, RACKS, PRODUCTS, LOCATIONS, STOCK, CLIENTS, DRIVERS, TRUCKS, USERS_DESK, USERS_COL, DEVICES, INGRESOS, PEDIDOS, DESPACHOS, KARDEX, CONTEOS, IMPORT_HISTORY, ALERTS, AJUSTES, SETTINGS, LABELS });
const KEYS = { WAREHOUSES: "id", RACKS: "id", PRODUCTS: "codigo", LOCATIONS: "id", CLIENTS: "codigo", DRIVERS: "ci", TRUCKS: "placa", USERS_DESK: "user", USERS_COL: "user", DEVICES: "id", INGRESOS: "nro", PEDIDOS: "nro", DESPACHOS: "nro", CONTEOS: "nro", AJUSTES: "nro", SETTINGS: "k", LABELS: "tpl" };
function replaceData(data) { const ds = DS(); Object.keys(data || {}).forEach(k => { if (ds[k] && Array.isArray(data[k])) ds[k].splice(0, ds[k].length, ...data[k]); }); }

/* Cola sin conexión (colector): movimientos y registros pendientes de enviar. */
const Q = {
  items: (() => { try { return JSON.parse(localStorage.getItem("cw-queue") || "[]"); } catch (e) { return []; } })(),
  forced: false,
  persist() { try { localStorage.setItem("cw-queue", JSON.stringify(this.items)); } catch (e) {} },
  add(it) { this.items.push(it); this.persist(); },
  get n() { return this.items.length; },
};
const online = () => !Q.forced && navigator.onLine !== false;
let flushing = false;
async function flushQueue(silent = false) {
  if (flushing || !Q.n || !online()) return;
  flushing = true; let sent = 0;
  try {
    while (Q.items.length) {
      const it = Q.items[0];
      if (it.t === "mov") { const r = await api("POST", `${CW.routes.api}/movimientos`, { wh: it.wh, movs: it.movs }); applyMovResult(r); }
      else if (it.t === "save") await api("PUT", `${CW.routes.api}/${it.ds}/${encodeURIComponent(it.key)}`, it.rec);
      Q.items.shift(); Q.persist(); sent++;
    }
  } catch (e) {
    if (!e.network) { toast(`Un movimiento en cola fue rechazado por el servidor: ${esc(e.message)}`, "bad", 6000); Q.items.shift(); Q.persist(); await resync(true); }
  } finally { flushing = false; }
  if (sent && !silent) toast(`Conexión restablecida: ${sent} ${sent === 1 ? "operación enviada" : "operaciones enviadas"} al servidor.`, "ok");
  if (sent) refreshUI();
}
/** Guarda un registro (la interfaz ya lo modificó en memoria). */
async function save(ds, rec) {
  const arr = DS()[ds], key = rec[KEYS[ds]];
  const i = arr.findIndex(x => String(x[KEYS[ds]]) === String(key)); if (i < 0) arr.unshift(rec); else if (arr[i] !== rec) arr[i] = rec;
  if (!online()) { Q.add({ t: "save", ds, key, rec: clone(rec) }); return { queued: true }; }
  try { return await api("PUT", `${CW.routes.api}/${ds}/${encodeURIComponent(key)}`, rec); }
  catch (e) { if (e.network) { Q.add({ t: "save", ds, key, rec: clone(rec) }); return { queued: true }; } await resync(true); throw e; }
}
async function saveMany(ds, recs) {
  if (!recs.length) return; const arr = DS()[ds];
  recs.forEach(rec => { const i = arr.findIndex(x => String(x[KEYS[ds]]) === String(rec[KEYS[ds]])); if (i < 0) arr.push(rec); else arr[i] = rec; });
  if (!online()) { recs.forEach(rec => Q.add({ t: "save", ds, key: rec[KEYS[ds]], rec: clone(rec) })); return; }
  for (let i = 0; i < recs.length; i += 500) await api("POST", `${CW.routes.api}/lote/${ds}`, { records: recs.slice(i, i + 500) });
}
async function del(ds, key) {
  await api("DELETE", `${CW.routes.api}/${ds}/${encodeURIComponent(key)}`);
  const arr = DS()[ds]; const i = arr.findIndex(x => String(x[KEYS[ds]]) === String(key)); if (i >= 0) arr.splice(i, 1);
  if (ds === "RACKS") for (let j = LOCATIONS.length - 1; j >= 0; j--) if (LOCATIONS[j].rack === key) LOCATIONS.splice(j, 1);
}
function applyMovResult(r) { if (r && r.stock) STOCK.splice(0, STOCK.length, ...r.stock); if (r && r.kardex) KARDEX.unshift(...r.kardex.slice().reverse()); }
/** Aplica en memoria (modo sin conexión) lo mismo que hará el servidor. */
function applyMovLocal(movs, wh) {
  movs.forEach(m => {
    const w = m.wh || wh; const qty = Math.abs(m.qty);
    if (m.tipo === "Reserva") { const s = STOCK.find(x => x.wh === w && x.loc === (m.loc || m.from) && x.codigo === m.codigo && (!m.lote || x.lote === m.lote)); if (s) s.reservado = Math.max(0, Math.min(s.qty, (s.reservado || 0) + m.qty)); return; }
    let lote = m.lote, ing = m.ingreso, venc = m.venc;
    if (m.from && L(m.from)) {
      const s = STOCK.find(x => x.wh === w && x.loc === m.from && x.codigo === m.codigo && (!m.lote || x.lote === m.lote));
      if (s) { lote = s.lote; ing = ing || s.ingreso; venc = venc || s.venc; s.qty -= qty; s.reservado = Math.max(0, Math.min(s.qty, (s.reservado || 0) - (m.unreserve || 0))); if (s.qty <= 0) STOCK.splice(STOCK.indexOf(s), 1); }
    }
    if (m.tipo === "Cambio de estado") { const s = STOCK.find(x => x.wh === w && x.loc === m.from && x.codigo === m.codigo && x.lote === m.lote); if (s) s.estado = m.estado; }
    else if (m.to && L(m.to)) {
      const s = STOCK.find(x => x.wh === w && x.loc === m.to && x.codigo === m.codigo && x.lote === (lote || null));
      if (s) s.qty += qty; else STOCK.push({ loc: m.to, rack: L(m.to).rack, wh: w, codigo: m.codigo, lote: lote || null, qty, ingreso: ing || todayISO(), venc: venc || null, estado: m.estado || "DISPONIBLE", reservado: 0 });
    }
    KARDEX.unshift({ ts: nowTS(), tipo: m.tipo, dir: m.dir || "mv", codigo: m.codigo, lote: lote || "—", qty: m.dir === "out" ? -qty : m.tipo === "Cambio de estado" ? 0 : qty, from: m.from || "—", to: m.to || "—", user: me(), doc: m.doc || "—", device: m.device || CW.device, offline: true, wh: w });
  });
}
/** Registra movimientos de stock. Lanza error con el mensaje del servidor si no se pueden aplicar. */
async function mov(movs, wh = state.wh) {
  movs = movs.filter(m => m.tipo === "Reserva" ? m.qty !== 0 : Math.abs(m.qty) > 0 || m.tipo === "Cambio de estado").map(m => ({ device: CW.mode === "col" || $("#colOverlay").classList.contains("open") ? (CW.device || "Colector") : "Escritorio", ...m }));
  if (!movs.length) return;
  if (!online()) { movs.forEach(m => { m.offline = true; m.ts = nowTS(); }); applyMovLocal(movs, wh); Q.add({ t: "mov", wh, movs }); return { queued: true }; }
  try { const r = await api("POST", `${CW.routes.api}/movimientos`, { wh, movs }); applyMovResult(r); return r; }
  catch (e) { if (e.network) { movs.forEach(m => { m.offline = true; m.ts = nowTS(); }); applyMovLocal(movs, wh); Q.add({ t: "mov", wh, movs }); return { queued: true }; } throw e; }
}
let lastSync = Date.now();
async function resync(quiet = false) {
  try { const r = await api("GET", `${CW.routes.api}/datos`); replaceData(r.data); lastSync = Date.now(); if (!quiet) refreshUI(); }
  catch (e) { if (!quiet && !e.network) toast(esc(e.message), "bad"); }
}
/** Vuelve a dibujar lo que esté a la vista, sin perder lo que el usuario escribe. */
function refreshUI() {
  if (typeof buildNav === "function" && !$("#app").hidden) buildNav();
  if (!$("#app").hidden && !overlayOpen() && !document.activeElement.matches("input,textarea,select")) render();
  if ($("#colOverlay").classList.contains("open") && typeof cRender === "function" && !document.activeElement.matches("#colScreen input")) cRender();
  if (state.drawerFn && $("#drawer").classList.contains("open") && !$("#modal").classList.contains("open")) state.drawerFn();
}
/** Ejecuta una acción mostrando errores; bloquea el botón mientras trabaja. */
async function run(btn, fn) {
  if (btn && btn.disabled) return; const txt = btn ? btn.innerHTML : "";
  if (btn) { btn.disabled = true; btn.classList.add("busy"); }
  try { return await fn(); }
  catch (e) { (e.status >= 500 ? console.error : console.warn)(e.message); toast(esc(e.message || "No se pudo completar la acción."), "bad", 5200); }
  finally { if (btn && btn.isConnected) { btn.disabled = false; btn.classList.remove("busy"); btn.innerHTML = txt; } }
}
/** Número correlativo por prefijo: ING-2609231, PED-07811… */
function nextNro(prefix, arr, key = "nro", { daily = true, width = 3 } = {}) {
  const d = new Date(); const base = daily ? `${prefix}-${String(d.getFullYear()).slice(2)}${pad(d.getMonth() + 1)}${pad(d.getDate())}` : `${prefix}-`;
  const nums = arr.map(x => String(x[key])).filter(k => k.startsWith(base)).map(k => parseInt(k.slice(base.length), 10) || 0);
  return base + pad(Math.max(0, ...nums) + 1, daily ? width : 5);
}
const nextPedNro = () => { const nums = PEDIDOS.map(p => parseInt(String(p.nro).replace(/\D/g, ""), 10) || 0); return `PED-${pad(Math.max(7000, ...nums) + 1, 5)}`; };

/* ---------------------------------------------------------------- tablas con paginación y exportación */
const PAGE = 25;
function table(cols, rows, { cls = "", rowAttr = () => "", empty = "Sin registros", id = null, page = PAGE } = {}) {
  if (!rows.length) return `<div class="empty">${ic("search")}<div>${empty}</div></div>`;
  let shown = rows, footer = "";
  if (id) {
    const pages = Math.max(1, Math.ceil(rows.length / page)); let p = Math.min(state.pages[id] || 1, pages); state.pages[id] = p;
    shown = rows.slice((p - 1) * page, p * page);
    const btns = []; const from = Math.max(1, Math.min(p - 2, pages - 4)), to = Math.min(pages, from + 4);
    btns.push(`<button class="btn" data-page="${id}:${p - 1}" ${p <= 1 ? "disabled" : ""} title="Anterior">‹</button>`);
    for (let i = from; i <= to; i++) btns.push(`<button class="btn ${i === p ? "primary" : ""}" data-page="${id}:${i}">${i}</button>`);
    btns.push(`<button class="btn" data-page="${id}:${p + 1}" ${p >= pages ? "disabled" : ""} title="Siguiente">›</button>`);
    footer = `<div class="tbl-foot"><span>${fmt(rows.length)} registros · página ${p} de ${pages}</span>${pages > 1 ? `<div class="pager">${btns.join("")}</div>` : ""}</div>`;
  }
  return `<div class="tbl-wrap"><table class="tbl ${cls}"><thead><tr>${cols.map(c => `<th class="${c.a || ""}">${c.h}</th>`).join("")}</tr></thead><tbody>${shown.map((r, i) => `<tr ${rowAttr(r)}>${cols.map(c => `<td class="${c.a || ""}">${c.f(r, i)}</td>`).join("")}</tr>`).join("")}</tbody></table></div>${footer}`;
}
const pageHead = (title, sub, actions = "", crumbs = "") => `<div class="page-head"><div>${crumbs ? `<div class="crumbs">${crumbs}</div>` : ""}<h1>${title}</h1>${sub ? `<div class="sub">${sub}</div>` : ""}</div><div class="actions">${actions}</div></div>`;
const searchBox = (key, ph) => `<div class="search">${ic("search")}<input data-q="${key}" placeholder="${esc(ph)}" value="${esc(state.filters[key] || "")}" autocomplete="off"></div>`;
const chipBar = (key, list, def = "Todos") => `<div class="chips">${list.map(t => { const [v, l] = Array.isArray(t) ? t : [t, t]; return `<button class="chip ${(state.filters[key] ?? def) === v ? "on" : ""}" data-f="${key}" data-v="${esc(v)}">${esc(l)}</button>`; }).join("")}</div>`;
const fq = key => state.filters[key] || "";
const fv = (key, def = "Todos") => state.filters[key] ?? def;

let XLSX_LOADING = null;
function loadXLSX() {
  if (window.XLSX) return Promise.resolve(window.XLSX);
  const load = src => new Promise((ok, ko) => { const s = document.createElement("script"); s.src = src; s.onload = () => window.XLSX ? ok(window.XLSX) : ko(); s.onerror = () => { s.remove(); ko(); }; document.head.appendChild(s); });
  if (!XLSX_LOADING) XLSX_LOADING = load(`${CW.routes.home.replace(/\/$/, "")}/carmen/xlsx.min.js`).catch(() => load("https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js")).catch(() => { XLSX_LOADING = null; throw new Error("No se pudo cargar el lector de Excel. Revisa la conexión."); });
  return XLSX_LOADING;
}
/** Descarga un .xlsx. sheets: [{name, cols:[{h, v:r=>valor, w}], rows}] */
async function exportXLSX(file, sheets) {
  const X = await loadXLSX(); const wb = X.utils.book_new();
  (Array.isArray(sheets) ? sheets : [sheets]).forEach(sh => {
    const aoa = sh.aoa || [sh.cols.map(c => c.h), ...sh.rows.map(r => sh.cols.map(c => { const v = c.v(r); return v === undefined || v === null ? "" : v; }))];
    const ws = X.utils.aoa_to_sheet(aoa); ws["!cols"] = (sh.cols || aoa[0].map(() => ({}))).map((c, i) => ({ wch: c.w || Math.min(48, Math.max(10, ...aoa.slice(0, 200).map(r => String(r[i] ?? "").length + 2))) }));
    X.utils.book_append_sheet(wb, ws, String(sh.name).slice(0, 31));
  });
  X.writeFile(wb, file.endsWith(".xlsx") ? file : `${file}.xlsx`);
  toast(`Archivo <b>${esc(file)}</b> descargado.`, "info");
}
const stamp = () => todayISO().replace(/-/g, "");

/* ---------------------------------------------------------------- impresión de etiquetas y documentos */
function qrSVG(text, size = 120) {
  try { const q = qrcode(0, "M"); q.addData(String(text)); q.make(); const n = q.getModuleCount(); const cs = size / n; let d = ""; for (let r = 0; r < n; r++) for (let c = 0; c < n; c++) if (q.isDark(r, c)) d += `M${(c * cs).toFixed(2)} ${(r * cs).toFixed(2)}h${cs.toFixed(2)}v${cs.toFixed(2)}h-${cs.toFixed(2)}z`; return `<svg viewBox="0 0 ${size} ${size}" width="${size}" height="${size}" shape-rendering="crispEdges"><rect width="${size}" height="${size}" fill="#fff"/><path d="${d}" fill="#000"/></svg>`; }
  catch (e) { return `<div style="width:${size}px;height:${size}px;background:#eee;display:grid;place-items:center;font-size:10px;color:#666">QR</div>`; }
}
function barcodeSVG(text, h = 40) {
  try { const s = document.createElementNS("http://www.w3.org/2000/svg", "svg"); JsBarcode(s, String(text).replace(/[^\x20-\x7E]/g, ""), { format: "CODE128", displayValue: true, fontSize: 11, height: h, margin: 0, width: 1.4, background: "#ffffff", lineColor: "#000000" }); s.setAttribute("style", "max-width:100%;height:auto"); return s.outerHTML; }
  catch (e) { return `<div>${esc(text)}</div>`; }
}
/** Abre una ventana con el contenido listo para imprimir (etiquetas en mm o documento A4). */
function printHTML(title, inner, { pageCss = "@page{size:A4;margin:14mm}", css = "" } = {}) {
  const w = window.open("", "_blank", "width=900,height=700");
  if (!w) { toast("El navegador bloqueó la ventana de impresión. Permite ventanas emergentes para este sitio.", "bad", 6000); return; }
  w.document.write(`<!doctype html><html lang="es"><head><meta charset="utf-8"><title>${esc(title)}</title><style>${pageCss}*{box-sizing:border-box}body{margin:0;font-family:Carlito,Calibri,Arial,sans-serif;color:#000}.mono{font-family:"JetBrains Mono",Consolas,monospace}h1{font-size:20px;margin:0 0 4px}table{border-collapse:collapse;width:100%;font-size:12px}th,td{border:1px solid #999;padding:5px 6px;text-align:left}th{background:#eee}.r{text-align:right}.muted{color:#555}.brand{color:#E00010;font-weight:800;letter-spacing:.04em}.label{page-break-after:always;overflow:hidden;position:relative;display:flex;gap:2mm;padding:2.5mm}.label:last-child{page-break-after:auto}.noprint{padding:10px;background:#f4f6fb;border-bottom:1px solid #ccd;font-size:13px}@media print{.noprint{display:none}}${css}</style></head><body><div class="noprint">Vista de impresión · ${esc(title)} · <button onclick="print()">Imprimir</button> <button onclick="close()">Cerrar</button></div>${inner}<script>setTimeout(()=>print(),450)<\/script></body></html>`);
  w.document.close();
}

/* ---------------------------------------------------------------- autocompletado
 * <input data-ac="prod|loc|locstock|client|user|ped|ing|lote|any" data-ac-wh="BOL2" data-ac-loc="E1-C01-N1">
 * Busca por código y descripción mientras se escribe; flechas + Enter o clic para elegir.
 * Al elegir: el input toma el código y emite el evento "ac:pick" con {code, item}. */
const AC_SOURCES = {
  prod: (q, el) => PRODUCTS.filter(p => p.estado !== "INACTIVO" || el.dataset.acAll).filter(p => has(p.codigo, q) || has(p.desc, q) || has(p.fabrica, q) || has(p.sub, q)).slice(0, 12).map(p => ({ code: p.codigo, t: p.desc, s: `${p.um} · ${p.sub} · stock ${fmt(stockOf(p.codigo, el.dataset.acWh || state.wh))}`, tag: "Producto" })),
  loc: (q, el) => whLocs(el.dataset.acWh || state.wh).filter(l => has(l.id, q) || has((R(l.rack) || {}).name, q)).filter(l => !el.dataset.acZone || el.dataset.acZone.split(",").includes(zoneOf(l.id)) === !el.dataset.acZoneNot).sort((a, b) => a.sort - b.sort).slice(0, 12).map(l => { const st = STOCK.filter(s => s.loc === l.id); return { code: l.id, t: `${(R(l.rack) || {}).name || l.rack} · C${pad(l.col)} N${l.fila}`, s: l.blocked ? "BLOQUEADA" : st.length ? `${st.length} saldo(s) · ${fmt(sum(st, x => x.qty))} un.` : "vacía", tag: "Ubicación" }; }),
  locstock: (q, el) => { const code = el.dataset.acProd; const rows = whStock(el.dataset.acWh || state.wh).filter(s => (!code || s.codigo === code) && (has(s.loc, q) || has(s.codigo, q) || has(s.lote, q))); return rows.slice(0, 12).map(s => ({ code: s.loc, t: `${s.codigo} · ${P(s.codigo).desc}`, s: `lote ${s.lote || "—"} · ${fmt(s.qty)} un.${s.reservado ? ` (${fmt(s.reservado)} res.)` : ""}`, tag: "Saldo", item: s })); },
  client: q => CLIENTS.filter(c => has(c.codigo, q) || has(c.nombre, q) || has(c.nit, q) || has(c.ciudad, q)).slice(0, 12).map(c => ({ code: c.codigo, t: c.nombre, s: `${c.ciudad}, ${c.dpto} · NIT ${c.nit || "—"}`, tag: "Cliente" })),
  user: (q, el) => USERS_COL.filter(u => (!el.dataset.acWh || u.wh === el.dataset.acWh) && (has(u.user, q) || has(u.nombre, q))).slice(0, 12).map(u => ({ code: u.user, t: u.nombre, s: `${u.rol} · ${u.wh}`, tag: "Operador" })),
  ped: (q, el) => whPed(el.dataset.acWh || state.wh).filter(p => has(p.nro, q) || has(cli(p.cliente).nombre, q) || has(p.ref, q)).slice(0, 12).map(p => ({ code: p.nro, t: cli(p.cliente).nombre, s: `${p.estado} · ${p.lines.length} líneas`, tag: "Pedido" })),
  ing: (q, el) => whIng(el.dataset.acWh || state.wh).filter(i => has(i.nro, q) || has(i.doc, q) || has(i.origen, q)).slice(0, 12).map(i => ({ code: i.nro, t: `${i.tipo} · ${i.origen}`, s: `${i.estado} · ${i.lines.length} líneas`, tag: "Ingreso" })),
  lote: (q, el) => { const rows = whStock(el.dataset.acWh || state.wh).filter(s => (!el.dataset.acProd || s.codigo === el.dataset.acProd) && (!el.dataset.acLoc || s.loc === el.dataset.acLoc) && has(s.lote, q)); const seen = new Set(); return rows.filter(s => !seen.has(s.lote) && seen.add(s.lote)).slice(0, 12).map(s => ({ code: s.lote, t: `${s.codigo} en ${s.loc}`, s: `${fmt(s.qty)} un. · ingreso ${fmtD(s.ingreso)}`, tag: "Lote" })); },
};
AC_SOURCES.any = (q, el) => [...AC_SOURCES.loc(q, el).slice(0, 4), ...AC_SOURCES.prod(q, el).slice(0, 5), ...AC_SOURCES.ped(q, el).slice(0, 3), ...AC_SOURCES.ing(q, el).slice(0, 3)].slice(0, 14);
const ac = { el: null, items: [], idx: -1, pop: null };
function acClose() { if (ac.pop) ac.pop.hidden = true; ac.el = null; ac.items = []; ac.idx = -1; }
function acOpen(el) {
  const q = el.value.trim(); const src = AC_SOURCES[el.dataset.ac]; if (!src) return;
  const min = +(el.dataset.acMin ?? 1); if (q.length < min) { acClose(); return; }
  ac.el = el; ac.items = src(q, el); ac.idx = ac.items.length ? 0 : -1;
  if (!ac.pop) { ac.pop = document.createElement("div"); ac.pop.className = "ac-pop"; ac.pop.setAttribute("role", "listbox"); document.body.appendChild(ac.pop); ac.pop.addEventListener("mousedown", e => { const it = e.target.closest("[data-aci]"); if (!it) return; e.preventDefault(); acPick(+it.dataset.aci); }); }
  const r = el.getBoundingClientRect(); const below = window.innerHeight - r.bottom > 240 || r.top < 260;
  Object.assign(ac.pop.style, { left: `${Math.max(4, r.left)}px`, width: `${Math.max(r.width, Math.min(420, window.innerWidth - r.left - 8))}px`, top: below ? `${r.bottom + 4}px` : "", bottom: below ? "" : `${window.innerHeight - r.top + 4}px` });
  ac.pop.innerHTML = ac.items.length ? ac.items.map((it, i) => `<div class="ac-it ${i === ac.idx ? "on" : ""}" data-aci="${i}" role="option"><span class="tag">${esc(it.tag)}</span><div><b class="mono">${esc(it.code)}</b> <span>${esc(it.t)}</span><small>${esc(it.s || "")}</small></div></div>`).join("") : `<div class="ac-it muted">Sin coincidencias para «${esc(q)}»</div>`;
  ac.pop.hidden = false;
}
function acPick(i) {
  const it = ac.items[i]; const el = ac.el; if (!it || !el) return;
  el.value = it.code; acClose();
  el.dispatchEvent(new CustomEvent("ac:pick", { bubbles: true, detail: { code: it.code, item: it.item } }));
  el.dispatchEvent(new Event("change", { bubbles: true }));
}
document.addEventListener("input", e => { if (e.target.matches("[data-ac]")) acOpen(e.target); });
document.addEventListener("focusin", e => { if (e.target.matches("[data-ac]") && e.target.value.trim() && e.target.dataset.acMin === "0") acOpen(e.target); });
document.addEventListener("focusout", e => { if (e.target === ac.el) setTimeout(() => { if (document.activeElement !== ac.el) acClose(); }, 120); });
document.addEventListener("keydown", e => {
  if (!ac.el || e.target !== ac.el || !ac.pop || ac.pop.hidden) return;
  if (e.key === "ArrowDown" || e.key === "ArrowUp") { e.preventDefault(); ac.idx = (ac.idx + (e.key === "ArrowDown" ? 1 : -1) + ac.items.length) % Math.max(1, ac.items.length); $$(".ac-it", ac.pop).forEach((x, i) => x.classList.toggle("on", i === ac.idx)); $$(".ac-it", ac.pop)[ac.idx]?.scrollIntoView({ block: "nearest" }); }
  else if (e.key === "Enter" && ac.idx >= 0) {
    const exact = ac.items.find(x => norm(x.code) === norm(ac.el.value.trim()));
    // Un escaneo (código exacto) no se reemplaza por otra sugerencia.
    if (exact) { e.preventDefault(); e.stopImmediatePropagation(); acPick(ac.items.indexOf(exact)); }
    else { e.preventDefault(); e.stopImmediatePropagation(); acPick(ac.idx); }
  }
  else if (e.key === "Escape") { e.stopPropagation(); acClose(); }
  else if (e.key === "Tab" && ac.idx >= 0 && ac.el.value.trim()) acPick(ac.idx);
}, true);
window.addEventListener("resize", acClose);
document.addEventListener("scroll", e => { if (ac.el && !ac.pop.contains(e.target)) acClose(); }, true);

/* Acciones de botones: <button data-a="nombre" data-...>. Cada módulo agrega las suyas. */
const ACTIONS = {};
const VIEWS = {};
const DETAIL = {};
