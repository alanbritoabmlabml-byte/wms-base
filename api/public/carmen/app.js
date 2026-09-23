/* ==== 05-core.html ==== */
/* ===================== NÚCLEO ===================== */
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
};
const ic = (n, cls = "") => `<svg class="i ${cls}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${ICONS[n] || ""}</svg>`;

const NAV = [
  { grp: "Principal" },
  { id: "inicio", lbl: "Inicio", icon: "home" },
  { grp: "Operación" },
  { id: "ingresos", lbl: "Ingresos", icon: "in", cnt: () => INGRESOS.filter(i => ["Habilitado", "En recepción"].includes(i.estado)).length },
  { id: "salidas", lbl: "Pedidos y despacho", icon: "out", cnt: () => PEDIDOS.filter(p => p.estado !== "Despachado").length },
  { id: "stock", lbl: "Stock e inventario", icon: "stock" },
  { id: "ubicaciones", lbl: "Mapa de almacén", icon: "map" },
  { grp: "Maestros" },
  { id: "productos", lbl: "Productos", icon: "box" },
  { id: "clientes", lbl: "Clientes", icon: "users" },
  { id: "transporte", lbl: "Choferes y camiones", icon: "truck" },
  { grp: "Configuración" },
  { id: "importar", lbl: "Importar datos", icon: "upload" },
  { id: "etiquetas", lbl: "Etiquetas QR", icon: "qr" },
  { id: "usuarios", lbl: "Usuarios y roles", icon: "shield" },
  { id: "dispositivos", lbl: "Colectores", icon: "phone", cnt: () => DEVICES.filter(d => !d.online).length || null },
  { id: "parametros", lbl: "Parámetros", icon: "sliders" },
];
const MOBILE_NAV = ["inicio", "ingresos", "salidas", "stock", "importar"];

const state = { route: "inicio", wh: "BOL2", railCollapsed: false, sub: {}, theme: null, selLoc: null, filters: {} };
try { const t = localStorage.getItem("cwms-theme"); if (t) { state.theme = t; document.documentElement.dataset.theme = t; } } catch (e) {}
try { state.railCollapsed = localStorage.getItem("cwms-rail") === "1"; } catch (e) {}

const WH = () => WAREHOUSES.find(w => w.id === state.wh);

/* toasts / drawer / modal */
function toast(msg, kind = "ok", ms = 3200) {
  const t = document.createElement("div"); t.className = `toast ${kind}`; t.innerHTML = `${ic(kind === "ok" ? "check" : kind === "bad" ? "alert" : "bell")}<span>${msg}</span>`;
  $("#toasts").appendChild(t); setTimeout(() => t.remove(), ms);
}
function openDrawer(title, body, foot = "", sub = "") {
  const d = $("#drawer");
  d.innerHTML = `<div class="d-h"><div>${sub ? `<div class="crumbs">${sub}</div>` : ""}<h2>${title}</h2></div><button class="btn icon ghost" data-close>${ic("x")}</button></div><div class="d-b">${body}</div>${foot ? `<div class="d-f">${foot}</div>` : ""}`;
  d.classList.add("open"); d.setAttribute("aria-hidden", "false"); $("#scrim").classList.add("open");
}
function openModal(title, body, foot = "") {
  $("#modalBox").innerHTML = `<div class="m-h"><h2>${title}</h2><button class="btn icon ghost" data-close>${ic("x")}</button></div><div class="m-b">${body}</div>${foot ? `<div class="m-f">${foot}</div>` : ""}`;
  $("#modal").classList.add("open"); $("#modal").setAttribute("aria-hidden", "false"); $("#scrim").classList.add("open");
}
function closeOverlays() {
  $("#drawer").classList.remove("open"); $("#modal").classList.remove("open"); $("#scrim").classList.remove("open");
  $("#drawer").setAttribute("aria-hidden", "true"); $("#modal").setAttribute("aria-hidden", "true");
}

/* helpers de presentación */
const pillFor = s => {
  const m = { "Cerrado": "ok", "Despachado": "ok", "Entregado": "ok", "HABILITADO": "ok", "ACTIVO": "ok", "DISPONIBLE": "ok", "Finalizado": "ok", "En recepción": "info", "Preparación": "info", "En curso": "info", "En ruta": "info", "Cargando": "info", "Habilitado": "warn", "Recibido": "warn", "Validado": "info", "Embalado": "info", "Borrador": "neutral", "Anulado": "bad", "DESHABILITADO": "bad", "INACTIVO": "bad", "MANTENIMIENTO": "warn", "CUARENTENA": "warn", "EN RUTA": "info" };
  return `<span class="pill ${m[s] || "neutral"}">${esc(s)}</span>`;
};
const rotTag = r => `<span class="tag ${r === "A" ? "a" : r === "C" ? "" : ""}" title="Rotación ${r}">${r}</span>`;
const heat = v => v === 0 ? "" : v < .25 ? "h2" : v < .5 ? "h3" : v < .8 ? "h4" : "h5";
const initials = n => n.split(" ").map(x => x[0]).slice(0, 2).join("").toUpperCase();
const userName = u => (USERS_COL.find(x => x.user === u) || USERS_DESK.find(x => x.user === u) || { nombre: u || "—" }).nombre;

function sparkline(vals, w = 96, h = 36, cls = "") {
  const mx = Math.max(...vals), mn = Math.min(...vals); const r = mx - mn || 1;
  const pts = vals.map((v, i) => [i * (w - 4) / (vals.length - 1) + 2, h - 4 - (v - mn) / r * (h - 8)]);
  const d = pts.map((p, i) => (i ? "L" : "M") + p[0].toFixed(1) + " " + p[1].toFixed(1)).join(" ");
  const last = pts[pts.length - 1];
  return `<svg class="spark chart ${cls}" viewBox="0 0 ${w} ${h}" preserveAspectRatio="none"><path class="area" d="${d} L${last[0]} ${h} L2 ${h}Z"/><path class="line" d="${d}"/><circle class="pt" cx="${last[0]}" cy="${last[1]}" r="3"/></svg>`;
}
function barChart(items, { w = 520, h = 180, hi = null, unit = "" } = {}) {
  const mx = Math.max(...items.map(i => i.v)) || 1; const padL = 28, padB = 26, padT = 10; const bw = (w - padL - 10) / items.length;
  const ticks = [0, .5, 1].map(t => { const y = padT + (1 - t) * (h - padT - padB); return `<line x1="${padL}" x2="${w - 4}" y1="${y}" y2="${y}"/><text x="${padL - 6}" y="${y + 4}" text-anchor="end">${fmt(Math.round(mx * t))}</text>`; }).join("");
  const bars = items.map((it, i) => { const bh = (it.v / mx) * (h - padT - padB); const x = padL + i * bw + bw * .18; const y = h - padB - bh; return `<g><title>${esc(it.l)}: ${fmt(it.v)} ${unit}</title><rect class="cbar ${it.l === hi ? "hi" : ""}" x="${x}" y="${y}" width="${bw * .64}" height="${bh}" rx="4"/><text x="${x + bw * .32}" y="${h - 8}" text-anchor="middle">${esc(it.l)}</text></g>`; }).join("");
  return `<svg class="chart" viewBox="0 0 ${w} ${h}"><g class="grid">${ticks}</g>${bars}</svg>`;
}
function lineChart(series, { w = 520, h = 180, labels = [] } = {}) {
  const all = series.flatMap(s => s.v); const mx = Math.max(...all) || 1; const padL = 32, padB = 24, padT = 10; const n = series[0].v.length;
  const X = i => padL + i * (w - padL - 10) / (n - 1), Y = v => padT + (1 - v / mx) * (h - padT - padB);
  const grid = [0, .5, 1].map(t => `<line x1="${padL}" x2="${w - 4}" y1="${Y(mx * t)}" y2="${Y(mx * t)}"/><text x="${padL - 6}" y="${Y(mx * t) + 4}" text-anchor="end">${fmt(Math.round(mx * t))}</text>`).join("");
  const paths = series.map((s, k) => { const d = s.v.map((v, i) => (i ? "L" : "M") + X(i).toFixed(1) + " " + Y(v).toFixed(1)).join(" "); return `<path class="line" style="stroke:${s.c}" d="${d}"/>${k === 0 ? `<path class="area" style="fill:${s.c}" d="${d} L${X(n - 1)} ${h - padB} L${X(0)} ${h - padB}Z"/>` : ""}<circle class="pt" style="fill:${s.c}" cx="${X(n - 1)}" cy="${Y(s.v[n - 1])}" r="3.5"/>`; }).join("");
  const lbls = labels.map((l, i) => `<text x="${X(i)}" y="${h - 6}" text-anchor="middle">${esc(l)}</text>`).join("");
  return `<svg class="chart" viewBox="0 0 ${w} ${h}"><g class="grid">${grid}</g>${paths}${lbls}</svg>`;
}

/* tabla genérica */
function table(cols, rows, { cls = "", rowAttr = () => "", empty = "Sin registros" } = {}) {
  if (!rows.length) return `<div class="empty">${ic("search")}<div>${empty}</div></div>`;
  return `<div class="tbl-wrap"><table class="tbl ${cls}"><thead><tr>${cols.map(c => `<th class="${c.a || ""}">${c.h}</th>`).join("")}</tr></thead><tbody>${rows.map(r => `<tr ${rowAttr(r)}>${cols.map(c => `<td class="${c.a || ""}">${c.f(r)}</td>`).join("")}</tr>`).join("")}</tbody></table></div>`;
}
const foot = (n, label = "registros") => `<div class="tbl-foot"><span>${fmt(n)} ${label}</span><div class="pager"><button class="btn">‹</button><button class="btn primary">1</button><button class="btn">2</button><button class="btn">3</button><button class="btn">›</button></div></div>`;
const pageHead = (title, sub, actions = "", crumbs = "") => `<div class="page-head"><div>${crumbs ? `<div class="crumbs">${crumbs}</div>` : ""}<h1>${title}</h1>${sub ? `<div class="sub">${sub}</div>` : ""}</div><div class="actions">${actions}</div></div>`;

/* ===================== ROUTER ===================== */
const VIEWS = {};
function render() {
  const v = VIEWS[state.route] || VIEWS.inicio;
  $("#view").innerHTML = v();
  $$("#nav a.nav").forEach(a => a.classList.toggle("active", a.dataset.id === state.route));
  $$("#mnav a").forEach(a => a.classList.toggle("active", a.dataset.id === state.route));
  window.scrollTo({ top: 0 });
  if (v.after) v.after();
}
function go(route, sub) { state.route = route; if (sub) state.sub[route] = sub; location.hash = route; render(); }
function buildNav() {
  $("#nav").innerHTML = NAV.map(n => n.grp ? `<div class="grp">${n.grp}</div>` : `<a class="nav" href="#${n.id}" data-id="${n.id}">${ic(n.icon)}<span class="lbl">${n.lbl}</span>${n.cnt && n.cnt() ? `<span class="cnt">${n.cnt()}</span>` : ""}</a>`).join("");
  $("#mnav").innerHTML = MOBILE_NAV.map(id => { const n = NAV.find(x => x.id === id); return `<a href="#${id}" data-id="${id}">${ic(n.icon)}<span>${n.lbl.split(" ")[0]}</span></a>`; }).join("");
  $("#ctxWh").innerHTML = WAREHOUSES.filter(w => w.type !== "TR").map(w => `<option value="${w.id}" ${w.id === state.wh ? "selected" : ""}>${w.name}</option>`).join("");
}

/* ==== 06-views.html ==== */
/* ===================== VISTAS: INICIO ===================== */
VIEWS.inicio = () => {
  const wh = WH();
  const pend = PEDIDOS.filter(p => p.estado !== "Despachado").length;
  const ingAb = INGRESOS.filter(i => ["Habilitado", "En recepción"].includes(i.estado)).length;
  const occupied = LOCATIONS.filter(l => !["AJ", "ING"].includes(l.rack) && STOCK.some(s => s.loc === l.id)).length;
  const usable = LOCATIONS.filter(l => !["AJ", "ING"].includes(l.rack)).length;
  const bajoMin = PRODUCTS.filter(p => p.estado === "ACTIVO" && stockOf(p.codigo) < p.min);
  const offlineQ = DEVICES.reduce((a, d) => a + d.cola, 0);
  const lineasHoy = [412, 388, 455, 430, 502, 476, 531];
  const flujo = { labels: ["Lu", "Ma", "Mi", "Ju", "Vi", "Sá", "Lu"], in: [820, 640, 910, 700, 1180, 300, 760], out: [610, 720, 680, 940, 1250, 210, 880] };
  const rackOcc = RACKS.filter(r => r.id[0] === "E" || r.id === "A2").map(r => { const ls = LOCATIONS.filter(l => l.rack === r.id); return { l: r.id, v: Math.round(ls.filter(l => STOCK.some(s => s.loc === l.id)).length / ls.length * 100) }; });
  return `
  ${pageHead(`${CW.greeting}, ${CW.user.first}`, `${wh.name} · ${CW.today} · turno ${CW.shift} en curso`, `<button class="btn" data-go="stock" data-sub="conteos">${ic("count")} Nuevo conteo</button><button class="btn" data-go="ingresos">${ic("in")} Orden de ingreso</button><button class="btn primary" data-go="salidas">${ic("zap")} Liberar ola de picking</button>`)}
  <div class="grid g-4" style="margin-bottom:16px">
    <div class="card kpi"><span class="lbl">Exactitud por ubicación (IRA)</span><span class="val">97,6<small>%</small></span><span class="delta up">${ic("arrow")} +0,8 pts vs. conteo anterior</span>${sparkline([94, 95, 95.5, 96.2, 96, 96.8, 97.6])}</div>
    <div class="card kpi"><span class="lbl">Pedidos en proceso</span><span class="val">${pend}<small>de ${PEDIDOS.length}</small></span><span class="delta">${PEDIDOS.filter(p => p.prioridad === "Urgente" && p.estado !== "Despachado").length} urgentes · ${PEDIDOS.filter(p => p.estado === "Recibido").length} sin asignar</span>${sparkline([14, 18, 12, 16, 20, 15, pend])}</div>
    <div class="card kpi"><span class="lbl">Ocupación de ubicaciones</span><span class="val">${Math.round(occupied / usable * 100)}<small>%</small></span><span class="delta">${fmt(occupied)} de ${fmt(usable)} ubicaciones con stock</span>${sparkline([61, 63, 66, 64, 68, 70, Math.round(occupied / usable * 100)])}</div>
    <div class="card kpi"><span class="lbl">Líneas por hora / operador</span><span class="val">38,4</span><span class="delta up">${ic("arrow")} +6 % con ruta ordenada</span>${sparkline(lineasHoy)}</div>
  </div>
  <div class="grid g-2-1" style="margin-bottom:16px">
    <div class="card"><div class="card-h"><h3>Flujo de la semana · unidades</h3><div class="legend"><span><i style="background:var(--primary)"></i>Ingresos</span><span><i style="background:var(--accent)"></i>Salidas</span></div></div><div class="card-b">${lineChart([{ v: flujo.in, c: "var(--primary)" }, { v: flujo.out, c: "var(--accent)" }], { labels: flujo.labels, w: 640, h: 200 })}</div></div>
    <div class="card"><div class="card-h"><h3>Requiere atención</h3><a href="#" class="small" data-go="dispositivos">Ver todo</a></div><div class="card-b" style="padding:8px 16px"><ul class="timeline">${ALERTS.map(a => `<li><span class="ic ${a.k}">${ic(a.k === "ok" ? "check" : a.k === "info" ? "cloud" : "alert")}</span><div><b>${esc(a.t)}</b><span>${esc(a.s)}</span></div></li>`).join("")}</ul></div></div>
  </div>
  <div class="grid g-3">
    <div class="card"><div class="card-h"><h3>Ocupación por rack</h3><span class="muted">BOL2 · %</span></div><div class="card-b">${barChart(rackOcc, { w: 420, h: 170, hi: rackOcc.reduce((a, b) => a.v > b.v ? a : b).l, unit: "%" })}</div></div>
    <div class="card"><div class="card-h"><h3>Bajo el mínimo</h3><span class="pill warn">${bajoMin.length} SKU</span></div>${table([{ h: "SKU", f: r => `<span class="mono link" data-prod="${r.codigo}">${r.codigo}</span><div class="small muted">${esc(r.desc)}</div>` }, { h: "Stock", a: "r", f: r => `<b class="num">${fmt(stockOf(r.codigo))}</b> <span class="muted small">/ mín ${r.min}</span>` }], bajoMin.slice(0, 5))}</div>
    <div class="card"><div class="card-h"><h3>Colectores</h3><span class="pill ${offlineQ ? "warn" : "ok"}">${offlineQ} mov. en cola</span></div><div class="card-b stack">${DEVICES.filter(d => d.wh === state.wh || state.wh === "BOL2").slice(0, 5).map(d => `<div class="row between"><div class="row"><span class="pill ${d.online ? "ok" : "bad"} plain" style="padding:2px 6px">${d.online ? "●" : "○"}</span><div><b class="small">${d.id}</b> <span class="muted small">· ${userName(d.user)}</span></div></div><div class="row" style="width:120px"><div class="bar ${d.bat < 20 ? "bad" : d.bat < 50 ? "warn" : "ok"}" style="flex:1"><i style="width:${d.bat}%"></i></div><span class="small num muted" style="width:34px;text-align:right">${d.bat}%</span></div></div>`).join("")}</div></div>
  </div>`;
};

/* ===================== INGRESOS ===================== */
VIEWS.ingresos = () => {
  const f = state.filters.ing || "Todos";
  const rows = INGRESOS.filter(i => f === "Todos" || i.estado === f);
  const counts = s => INGRESOS.filter(i => i.estado === s).length;
  return `
  ${pageHead("Ingresos", "Órdenes de ingreso desde producción, compras, devoluciones y transferencias.", `<button class="btn">${ic("cloud")} Sincronizar WorkCorp</button><button class="btn">${ic("upload")} Importar orden de compra</button><button class="btn primary" data-act="new-ing">${ic("plus")} Nueva orden de ingreso</button>`)}
  <div class="grid g-4" style="margin-bottom:16px">
    ${[["Habilitado", "Listas para recibir", "warn"], ["En recepción", "Recibiendo ahora", "info"], ["Cerrado", "Cerradas (30 d)", "ok"], ["Borrador", "Borradores", "neutral"]].map(([s, l, k]) => `<button class="card kpi" style="text-align:left;cursor:pointer;border-color:${f === s ? "var(--navy)" : "var(--line)"}" data-filter-ing="${s}"><span class="lbl">${l}</span><span class="val">${counts(s)}</span><span class="pill ${k}">${s}</span></button>`).join("")}
  </div>
  <div class="card">
    <div class="toolbar"><div class="search">${ic("search")}<input placeholder="Nro, documento origen, lote…"></div><div class="chips">${["Todos", ...ING_TYPES].map(t => `<button class="chip ${t === "Todos" ? "on" : ""}">${t}</button>`).join("")}</div><div class="spacer" style="flex:1"></div>${f !== "Todos" ? `<button class="chip on" data-filter-ing="Todos">${esc(f)} ✕</button>` : ""}<button class="btn sm">${ic("download")} Exportar</button></div>
    ${table([
      { h: "Orden", f: r => `<span class="mono link">${r.nro}</span><div class="small muted">${r.doc}</div>` },
      { h: "Fecha", f: r => fmtD(r.fecha) },
      { h: "Tipo", f: r => `<span class="tag">${r.tipo}</span>` },
      { h: "Origen", f: r => esc(r.origen) },
      { h: "Líneas", a: "r", f: r => r.lines.length },
      { h: "Avance", f: r => { const t = r.lines.reduce((a, l) => a + l.qty, 0), rc = r.lines.reduce((a, l) => a + l.rec, 0); const p = Math.round(rc / t * 100); return `<div class="row"><div class="bar ${p === 100 ? "ok" : ""}" style="width:90px"><i style="width:${p}%"></i></div><span class="small num">${p}%</span></div>`; } },
      { h: "Muelle", f: r => `<span class="mono small">${r.muelle}</span>` },
      { h: "Estado", f: r => pillFor(r.estado) },
      { h: "", f: r => `<button class="btn sm ghost">${ic("chev")}</button>` },
    ], rows, { rowAttr: r => `class="clickable" data-ing="${r.nro}"` })}
    ${foot(rows.length, "órdenes")}
  </div>`;
};
function drawerIngreso(nro) {
  const o = INGRESOS.find(i => i.nro === nro); if (!o) return;
  const t = o.lines.reduce((a, l) => a + l.qty, 0), rc = o.lines.reduce((a, l) => a + l.rec, 0);
  const stepIdx = ["Borrador", "Habilitado", "En recepción", "Cerrado"].indexOf(o.estado);
  openDrawer(`Orden ${o.nro}`, `
    <div class="steps" style="margin-bottom:18px">${["Creada", "Habilitada", "En recepción", "Cerrada"].map((s, i) => `<div class="st ${i < stepIdx ? "done" : i === stepIdx ? "cur" : ""}">${s}</div>`).join("")}</div>
    <div class="grid g-2" style="margin-bottom:16px"><dl class="kv"><dt>Tipo</dt><dd>${o.tipo}</dd><dt>Origen</dt><dd>${esc(o.origen)}</dd><dt>Documento</dt><dd class="mono">${o.doc}</dd><dt>Fecha</dt><dd>${fmtD(o.fecha)}</dd></dl><dl class="kv"><dt>Muelle</dt><dd class="mono">${o.muelle}</dd><dt>Receptor</dt><dd>${userName(o.usuario)}</dd><dt>Recibido</dt><dd class="num">${fmt(rc)} / ${fmt(t)} un.</dd><dt>Estado</dt><dd>${pillFor(o.estado)}</dd></dl></div>
    ${table([{ h: "SKU", f: l => `<span class="mono">${l.codigo}</span><div class="small muted">${esc(P(l.codigo).desc)}</div>` }, { h: "Lote / turno", f: l => `<span class="mono small">${l.lote}</span> <span class="tag">${l.turno}</span>` }, { h: "Esperado", a: "r", f: l => fmt(l.qty) }, { h: "Recibido", a: "r", f: l => `<b class="num ${l.rec < l.qty ? "" : ""}" style="color:${l.rec === l.qty ? "var(--ok)" : l.rec ? "var(--warn)" : "var(--ink-3)"}">${fmt(l.rec)}</b>` }, { h: "Dif.", a: "r", f: l => l.rec - l.qty ? `<span class="pill ${l.rec - l.qty < 0 ? "warn" : "bad"} plain num">${l.rec - l.qty > 0 ? "+" : ""}${fmt(l.rec - l.qty)}</span>` : `<span class="muted">—</span>` }], o.lines)}
    <p class="small muted" style="margin:14px 0 0">Recibir deja la mercadería en el muelle <b class="mono">${o.muelle}</b>; ubicarla en rack es una tarea separada que el colector propone según consolidación y clase de rotación.</p>`,
    `${o.estado === "Borrador" ? `<button class="btn" data-close>Editar</button><button class="btn primary" data-toast="Orden habilitada. Ya aparece en los colectores del muelle.">Habilitar para recepción</button>` : o.estado === "En recepción" ? `<button class="btn">${ic("print")} Etiquetas</button><button class="btn primary" data-toast="Orden cerrada. Diferencias registradas a nombre de Alan Moscoso.">Cerrar con diferencias</button>` : o.estado === "Habilitado" ? `<button class="btn" data-toast="Orden anulada." data-kind="bad">Anular</button><button class="btn primary" data-close>Ver en colector</button>` : `<button class="btn" data-close>Cerrar</button>`}`,
    `Ingresos › ${o.tipo}`);
}
function modalNuevoIngreso() {
  openModal("Nueva orden de ingreso", `
    <div class="grid g-2" style="margin-bottom:14px">
      <div class="field"><label>Tipo de movimiento</label><select class="select">${ING_TYPES.map(t => `<option>${t}</option>`).join("")}</select></div>
      <div class="field"><label>Documento origen</label><input class="input" placeholder="OP-4471 / OC-982 / NDV-…"></div>
      <div class="field"><label>Origen</label><input class="input" value="Extrusora EX-02"></div>
      <div class="field"><label>Muelle de recepción</label><select class="select"><option>ING-C01-N1 · Muelle 1</option><option>ING-C02-N1 · Muelle 2</option><option>ING-C03-N1 · Muelle 3</option></select></div>
    </div>
    <div class="card" style="box-shadow:none"><div class="card-h"><h3>Líneas</h3><button class="btn sm">${ic("plus")} Agregar línea</button></div>
    ${table([{ h: "SKU", f: l => `<span class="mono">${l.codigo}</span><div class="small muted">${esc(P(l.codigo).desc)}</div>` }, { h: "Lote", f: l => `<input class="input" style="min-height:34px;padding:4px 8px;width:130px" value="${l.lote}">` }, { h: "Turno", f: l => `<select class="select" style="min-height:34px;padding:4px 28px 4px 8px;width:80px"><option>T1</option><option>T2</option><option>T3</option></select>` }, { h: "Cantidad", a: "r", f: l => `<input class="input num" style="min-height:34px;padding:4px 8px;width:90px;text-align:right" value="${l.qty}">` }, { h: "", f: () => `<button class="btn sm ghost icon">${ic("x")}</button>` }], INGRESOS[3].lines.slice(0, 3))}</div>
    <div class="field" style="margin-top:14px"><label>Observación</label><textarea class="input" rows="2" placeholder="Opcional"></textarea></div>`,
    `<button class="btn" data-close>Cancelar</button><button class="btn" data-toast="Borrador guardado.">Guardar borrador</button><button class="btn primary" data-toast="Orden ING-260913 habilitada para recepción.">Registrar y habilitar</button>`);
}

/* ===================== SALIDAS ===================== */
VIEWS.salidas = () => {
  const sub = state.sub.salidas || "tablero";
  let body = "";
  if (sub === "tablero") {
    body = `<div class="kanban">${OUT_STATES.map(s => { const ps = PEDIDOS.filter(p => p.estado === s); return `<div class="kcol"><h4>${s}<span>${ps.length}</span></h4>${ps.slice(0, s === "Despachado" ? 4 : 9).map(p => { const t = p.lines.reduce((a, l) => a + l.qty, 0), pk = p.lines.reduce((a, l) => a + l.pick, 0); return `<div class="kcard ${p.prioridad === "Urgente" ? "urgent" : ""}" data-ped="${p.nro}"><div class="t"><span class="mono">${p.nro}</span><span>${fmtD(p.fecha).slice(0, 5)}</span></div><b>${esc(p.cliente.nombre)}</b><div class="m">${p.picker ? `<span class="mini-av" title="${userName(p.picker)}">${initials(userName(p.picker))}</span>` : `<span class="pill warn plain" style="padding:0 6px">Sin asignar</span>`}<span>${p.lines.length} líneas · ${fmt(t)} un.</span>${p.prioridad === "Urgente" ? `<span class="tag red">Urgente</span>` : ""}</div>${s === "Preparación" ? `<div class="bar" style="margin-top:8px"><i style="width:${Math.round(pk / t * 100)}%"></i></div>` : ""}${p.transporte ? `<div class="m" style="margin-top:6px">${ic("truck")}<span class="mono">${p.transporte}</span></div>` : ""}</div>`; }).join("")}${ps.length > 9 && s !== "Despachado" ? `<button class="btn ghost sm">Ver ${ps.length - 9} más</button>` : ""}</div>`; }).join("")}</div>`;
  } else if (sub === "pedidos") {
    body = `<div class="card"><div class="toolbar"><div class="search">${ic("search")}<input placeholder="Nro, cliente, referencia WorkCorp…"></div><div class="chips">${["Todos", ...OUT_STATES].map((t, i) => `<button class="chip ${i === 0 ? "on" : ""}">${t}</button>`).join("")}</div><div style="flex:1"></div><button class="btn sm">${ic("download")} Exportar</button></div>
      ${table([{ h: "Pedido", f: p => `<span class="mono link">${p.nro}</span><div class="small muted">${p.ref}</div>` }, { h: "Cliente", f: p => `<b>${esc(p.cliente.nombre)}</b><div class="small muted">${p.cliente.ciudad}</div>` }, { h: "Fecha", f: p => fmtD(p.fecha) }, { h: "Ola", f: p => `<span class="tag">${p.ola}</span>` }, { h: "Líneas", a: "r", f: p => p.lines.length }, { h: "Unidades", a: "r", f: p => fmt(p.lines.reduce((a, l) => a + l.qty, 0)) }, { h: "Operador", f: p => p.picker ? userName(p.picker) : `<span class="muted">—</span>` }, { h: "Prioridad", f: p => p.prioridad === "Urgente" ? `<span class="tag red">Urgente</span>` : `<span class="muted small">Normal</span>` }, { h: "Estado", f: p => pillFor(p.estado) }], PEDIDOS, { rowAttr: p => `class="clickable" data-ped="${p.nro}"` })}${foot(PEDIDOS.length, "pedidos")}</div>`;
  } else {
    body = `<div class="grid g-2-1"><div class="card"><div class="card-h"><h3>Despachos</h3><button class="btn sm primary" data-act="new-dsp">${ic("plus")} Nuevo despacho</button></div>
      ${table([{ h: "Despacho", f: d => `<span class="mono link">${d.nro}</span>` }, { h: "Fecha", f: d => fmtD(d.fecha) }, { h: "Vehículo", f: d => `<span class="mono">${d.placa}</span><div class="small muted">${esc(d.chofer)}</div>` }, { h: "Destino", f: d => d.destino }, { h: "Pedidos", f: d => d.pedidos.map(p => `<span class="tag">${p}</span>`).join(" ") || "—" }, { h: "Bultos", a: "r", f: d => fmt(d.bultos) }, { h: "Estado", f: d => pillFor(d.estado) }], DESPACHOS, { rowAttr: d => `class="clickable" data-dsp="${d.nro}"` })}</div>
      <div class="stack"><div class="card"><div class="card-h"><h3>Muelle de salida ahora</h3></div><div class="card-b stack">${TRUCKS.filter(t => t.estado !== "MANTENIMIENTO").slice(0, 3).map((t, i) => `<div class="row between"><div class="row">${ic("truck")}<div><b class="small mono">${t.placa}</b><div class="small muted">${t.tipo} · ${DRIVERS.find(d => d.ci === t.chofer)?.nombre || "sin chofer"}</div></div></div>${pillFor(i === 0 ? "Cargando" : t.estado === "EN RUTA" ? "En ruta" : "DISPONIBLE")}</div>`).join("")}</div></div>
      <div class="card"><div class="card-h"><h3>Embalados listos para cargar</h3><span class="pill info">${PEDIDOS.filter(p => p.estado === "Embalado").length}</span></div><div class="card-b stack">${PEDIDOS.filter(p => p.estado === "Embalado").map(p => `<label class="row between" style="cursor:pointer"><span class="row"><input type="checkbox" checked> <span><b class="small mono">${p.nro}</b><div class="small muted">${esc(p.cliente.nombre)} · ${p.bultos} bultos</div></span></span>${ic("chev")}</label>`).join("")}<button class="btn primary block" data-act="new-dsp">${ic("truck")} Armar despacho</button></div></div></div></div>`;
  }
  return `${pageHead("Pedidos y despacho", "Del pedido de WorkCorp a la nota de despacho: picking dirigido, validación, packing y carga.", `<button class="btn">${ic("cloud")} Sincronizar pedidos</button><button class="btn">${ic("upload")} Importar</button><button class="btn primary" data-act="new-wave">${ic("wave")} Liberar ola</button>`)}
  <div class="row between wrap" style="margin-bottom:14px"><div class="seg">${[["tablero", "Tablero"], ["pedidos", "Lista de pedidos"], ["despachos", "Despachos"]].map(([k, l]) => `<button class="${sub === k ? "on" : ""}" data-sub-salidas="${k}">${l}</button>`).join("")}</div><div class="legend"><span><i style="background:var(--red)"></i>Urgente</span><span>Las tarjetas se mueven al confirmar cada etapa desde el colector.</span></div></div>${body}`;
};
function drawerPedido(nro) {
  const p = PEDIDOS.find(x => x.nro === nro); if (!p) return;
  const idx = OUT_STATES.indexOf(p.estado);
  openDrawer(`Pedido ${p.nro}`, `
    <div class="steps" style="margin-bottom:18px">${OUT_STATES.map((s, i) => `<div class="st ${i < idx ? "done" : i === idx ? "cur" : ""}">${s}</div>`).join("")}</div>
    <div class="grid g-2" style="margin-bottom:16px"><dl class="kv"><dt>Cliente</dt><dd>${esc(p.cliente.nombre)}</dd><dt>Destino</dt><dd>${p.cliente.ciudad}, ${p.cliente.dpto}</dd><dt>Ref. WorkCorp</dt><dd class="mono">${p.ref}</dd><dt>Ola</dt><dd>${p.ola}</dd></dl><dl class="kv"><dt>Operador</dt><dd>${p.picker ? userName(p.picker) : "Sin asignar"}</dd><dt>Prioridad</dt><dd>${p.prioridad}</dd><dt>Bultos</dt><dd>${p.bultos}</dd><dt>Vehículo</dt><dd class="mono">${p.transporte || "—"}</dd></dl></div>
    <h3 style="font-size:14px;margin-bottom:8px">Ruta de picking <span class="muted small">(orden de recorrido)</span></h3>
    ${table([{ h: "#", f: (l, i) => "" }, { h: "Ubicación", f: l => { const s = STOCK.find(x => x.codigo === l.codigo) || { loc: "E1-C01-N1", lote: "—", rack: "E1", qty: 0 }; return `<span class="mono">${s.loc}</span>`; } }, { h: "SKU", f: l => `<span class="mono">${l.codigo}</span><div class="small muted">${esc(P(l.codigo).desc)}</div>` }, { h: "Lote (FIFO)", f: l => `<span class="mono small">${(STOCK.find(x => x.codigo === l.codigo) || { lote: "—" }).lote}</span>` }, { h: "Pedido", a: "r", f: l => fmt(l.qty) }, { h: "Tomado", a: "r", f: l => `<b class="num" style="color:${l.pick === l.qty ? "var(--ok)" : l.pick ? "var(--warn)" : "var(--ink-3)"}">${fmt(l.pick)}</b>` }], p.lines.map((l, i) => ({ ...l, i })).sort((a, b) => ((STOCK.find(x => x.codigo === a.codigo) || {}).loc || "").localeCompare((STOCK.find(x => x.codigo === b.codigo) || {}).loc || "")))}
    <div class="card" style="margin-top:16px;box-shadow:none"><div class="card-b small muted">${ic("zap")} El stock de estas líneas quedó <b>reservado</b> al liberar la ola ${p.ola}; ningún otro operador puede tomarlo.</div></div>`,
    `${p.estado === "Recibido" ? `<button class="btn" data-close>Editar</button><button class="btn primary" data-toast="Pedido asignado a Pedro García. Aparece en su colector.">Asignar y liberar picking</button>` : p.estado === "Embalado" ? `<button class="btn">${ic("print")} Rótulos de bulto</button><button class="btn primary" data-toast="Pedido agregado al despacho DSP-260340.">Agregar a despacho</button>` : p.estado === "Despachado" ? `<button class="btn">${ic("file")} Nota de despacho</button><button class="btn" data-close>Cerrar</button>` : `<button class="btn" data-toast="Picking anulado. Reservas liberadas." data-kind="bad">Anular picking</button><button class="btn primary" data-close>Seguir en colector</button>`}`,
    `Pedidos › ${p.estado}`);
}
function modalOla() {
  const cands = PEDIDOS.filter(p => p.estado === "Recibido");
  openModal("Liberar ola de picking", `
    <p class="muted" style="margin:0 0 14px">Agrupa pedidos por horario de despacho o transportista. El sistema reserva stock (FIFO por lote), ordena la ruta por <span class="mono">sort_seq</span> de ubicación y reparte entre operadores conectados.</p>
    <div class="grid g-3" style="margin-bottom:14px"><div class="field"><label>Criterio</label><select class="select"><option>Por horario de despacho</option><option>Por transportista</option><option>Por cliente</option><option>Manual</option></select></div><div class="field"><label>Estrategia</label><select class="select"><option>Discreto con ruta ordenada</option><option>Por lotes (batch)</option><option>Por zonas</option></select></div><div class="field"><label>Operadores</label><select class="select" multiple size="3">${USERS_COL.filter(u => u.online && u.wh === "BOL2").map(u => `<option selected>${u.nombre}</option>`).join("")}</select></div></div>
    ${table([{ h: "", f: p => `<input type="checkbox" checked>` }, { h: "Pedido", f: p => `<span class="mono">${p.nro}</span>` }, { h: "Cliente", f: p => esc(p.cliente.nombre) }, { h: "Líneas", a: "r", f: p => p.lines.length }, { h: "Unidades", a: "r", f: p => fmt(p.lines.reduce((a, l) => a + l.qty, 0)) }, { h: "Stock", f: p => `<span class="pill ok">Disponible</span>` }, { h: "Prioridad", f: p => p.prioridad === "Urgente" ? `<span class="tag red">Urgente</span>` : "Normal" }], cands)}`,
    `<button class="btn" data-close>Cancelar</button><button class="btn primary" data-toast="Ola OLA-32 liberada: ${cands.length} pedidos, ${cands.reduce((a, p) => a + p.lines.length, 0)} líneas reservadas.">Liberar ${cands.length} pedidos</button>`);
}
function modalDespacho() {
  const emb = PEDIDOS.filter(p => p.estado === "Embalado");
  openModal("Nuevo despacho", `<div class="grid g-3" style="margin-bottom:14px"><div class="field"><label>Vehículo</label><select class="select">${TRUCKS.filter(t => t.estado === "DISPONIBLE").map(t => `<option>${t.placa} · ${t.tipo}</option>`).join("")}</select></div><div class="field"><label>Chofer</label><select class="select">${DRIVERS.filter(d => d.estado === "ACTIVO").map(d => `<option>${d.nombre} · ${d.lic}</option>`).join("")}</select></div><div class="field"><label>Salida programada</label><input class="input" type="datetime-local" value="2026-09-22T14:30"></div></div>
    ${table([{ h: "", f: () => `<input type="checkbox" checked>` }, { h: "Pedido", f: p => `<span class="mono">${p.nro}</span>` }, { h: "Cliente", f: p => esc(p.cliente.nombre) }, { h: "Destino", f: p => `${p.cliente.ciudad}` }, { h: "Bultos", a: "r", f: p => p.bultos }], emb)}
    <div class="row between" style="margin-top:12px"><span class="muted small">Capacidad estimada: ${emb.reduce((a, p) => a + p.bultos, 0)} bultos ≈ 61 % del vehículo</span><div class="bar" style="width:200px"><i style="width:61%"></i></div></div>`,
    `<button class="btn" data-close>Cancelar</button><button class="btn">${ic("print")} Nota de despacho</button><button class="btn primary" data-toast="Despacho DSP-260341 creado. El chofer verifica bultos por QR al cargar.">Crear despacho</button>`);
}

/* ===================== STOCK E INVENTARIO ===================== */
VIEWS.stock = () => {
  const sub = state.sub.stock || "saldos";
  const tabs = [["saldos", "Stock por ubicación"], ["kardex", "Kardex"], ["conteos", "Inventario cíclico"], ["ajustes", "Ajustes"], ["abc", "Rotación ABC"], ["minmax", "Máximos y mínimos"]];
  let body = "";
  if (sub === "saldos") {
    const q = (state.filters.stockQ || "").toLowerCase();
    const rows = STOCK.filter(s => !q || s.codigo.includes(q) || s.loc.toLowerCase().includes(q) || P(s.codigo).desc.toLowerCase().includes(q) || s.lote.toLowerCase().includes(q)).sort((a, b) => a.loc.localeCompare(b.loc));
    const tot = rows.reduce((a, s) => a + s.qty, 0);
    body = `<div class="toolbar"><div class="search">${ic("search")}<input id="stockQ" placeholder="SKU, descripción, ubicación o lote" value="${esc(state.filters.stockQ || "")}"></div><div class="chips">${["Todos", "Disponible", "Reservado", "Cuarentena", "Vencido"].map((t, i) => `<button class="chip ${i === 0 ? "on" : ""}">${t}</button>`).join("")}</div><div style="flex:1"></div><span class="muted small">${fmt(rows.length)} saldos · ${fmt(new Set(rows.map(r => r.codigo)).size)} SKU · <b class="num">${fmt(tot)}</b> un.</span><button class="btn sm">${ic("download")} Exportar</button></div>
      ${table([{ h: "Ubicación", f: s => `<span class="mono link" data-loc="${s.loc}">${s.loc}</span>` }, { h: "SKU", f: s => `<span class="mono link" data-prod="${s.codigo}">${s.codigo}</span><div class="small muted">${esc(P(s.codigo).desc)}</div>` }, { h: "UM", f: s => P(s.codigo).um }, { h: "Lote", f: s => `<span class="mono small">${s.lote}</span>` }, { h: "Ingreso", f: s => fmtD(s.ingreso) }, { h: "Vence", f: s => s.venc ? fmtD(s.venc) : `<span class="muted">—</span>` }, { h: "Cantidad", a: "r", f: s => `<b class="num">${fmt(s.qty)}</b>` }, { h: "Reservado", a: "r", f: s => s.reservado ? `<span class="num" style="color:var(--warn)">${fmt(s.reservado)}</span>` : `<span class="muted">0</span>` }, { h: "Estado", f: s => pillFor(s.estado) }], rows.slice(0, 40))}${foot(rows.length, "saldos")}`;
  } else if (sub === "kardex") {
    body = `<div class="toolbar"><div class="search">${ic("search")}<input placeholder="SKU, lote, documento, usuario…"></div><div class="seg">${["Por producto", "Por pedido", "Por usuario", "Por ubicación"].map((t, i) => `<button class="${i === 0 ? "on" : ""}">${t}</button>`).join("")}</div><div style="flex:1"></div><input class="input" type="date" value="2026-09-15" style="width:150px;min-height:36px"><input class="input" type="date" value="2026-09-22" style="width:150px;min-height:36px"><button class="btn sm">${ic("download")} Exportar</button></div>
      ${table([{ h: "Fecha / hora", f: k => `<span class="num small">${k.ts}</span>` }, { h: "Movimiento", f: k => `<span class="pill ${k.dir === "in" ? "ok" : k.dir === "out" ? "bad" : "info"} plain">${k.tipo}</span>` }, { h: "SKU", f: k => `<span class="mono link" data-prod="${k.codigo}">${k.codigo}</span><div class="small muted">${esc(P(k.codigo).desc)}</div>` }, { h: "Lote", f: k => `<span class="mono small">${k.lote}</span>` }, { h: "Desde", f: k => `<span class="mono small">${k.from}</span>` }, { h: "Hacia", f: k => `<span class="mono small">${k.to}</span>` }, { h: "Cantidad", a: "r", f: k => `<b class="num" style="color:${k.qty < 0 ? "var(--bad)" : "var(--ok)"}">${k.qty > 0 ? "+" : ""}${fmt(k.qty)}</b>` }, { h: "Documento", f: k => `<span class="mono small">${k.doc}</span>` }, { h: "Usuario / equipo", f: k => `${userName(k.user)}<div class="small muted">${k.device}${k.offline ? ` · <span style="color:var(--warn)">en cola</span>` : ""}</div>` }], KARDEX.slice(0, 35))}${foot(KARDEX.length, "movimientos")}`;
  } else if (sub === "conteos") {
    body = `<div class="card-b"><div class="grid g-1-2"><div class="stack">
        <div class="card kpi" style="box-shadow:none"><span class="lbl">IRA acumulado 30 días</span><span class="val">97,6<small>%</small></span><span class="delta up">${ic("arrow")} meta 98 %</span>${sparkline([94, 95, 95.5, 96.2, 96, 96.8, 97.6])}</div>
        <div class="card" style="box-shadow:none"><div class="card-h"><h3>Plan cíclico</h3></div><div class="card-b stack small">${[["Clase A", "cada 30 días", 82, "ok"], ["Clase B", "cada 180 días", 54, "warn"], ["Clase C", "anual", 21, ""], ["Excepción", "ubicación en cero / diferencia en picking", 100, "ok"]].map(([c, f, p, k]) => `<div><div class="row between"><b>${c}</b><span class="muted">${f}</span></div><div class="row"><div class="bar ${k}" style="flex:1"><i style="width:${p}%"></i></div><span class="num muted" style="width:36px;text-align:right">${p}%</span></div></div>`).join("")}</div></div></div>
        <div class="card" style="box-shadow:none"><div class="card-h"><h3>Conteos</h3><button class="btn sm primary" data-act="new-count">${ic("plus")} Nuevo conteo</button></div>
        ${table([{ h: "Nro", f: c => `<b class="num">${c.nro}</b>` }, { h: "Tipo", f: c => c.tipo }, { h: "Almacén", f: c => c.wh }, { h: "Apertura", f: c => `<span class="small num">${c.apertura}</span>` }, { h: "Avance", f: c => `<div class="row"><div class="bar ${c.contadas === c.ubic ? "ok" : ""}" style="width:80px"><i style="width:${Math.round(c.contadas / c.ubic * 100)}%"></i></div><span class="small num">${c.contadas}/${c.ubic}</span></div>` }, { h: "Dif.", a: "r", f: c => c.dif ? `<span class="pill warn plain num">${c.dif}</span>` : "0" }, { h: "IRA", a: "r", f: c => `<b class="num">${(c.ira * 100).toFixed(1)} %</b>` }, { h: "Estado", f: c => pillFor(c.estado) }], CONTEOS, { rowAttr: c => `class="clickable" data-count="${c.nro}"` })}</div></div></div>`;
  } else if (sub === "ajustes") {
    body = `<div class="card-b"><div class="grid g-1-2"><div class="card" style="box-shadow:none"><div class="card-h"><h3>Nuevo ajuste</h3></div><div class="card-b stack">
      <div class="field"><label>Ubicación</label><input class="input mono" placeholder="Escanea o escribe · E1-C03-N2"></div><div class="field"><label>SKU / lote</label><input class="input mono" placeholder="5T2010101032 · L263502-1"></div>
      <div class="grid g-2"><div class="field"><label>Tipo</label><select class="select"><option>Ajuste + (sobrante)</option><option>Ajuste − (faltante)</option><option>Cambio de estado</option><option>Baja por merma</option></select></div><div class="field"><label>Cantidad</label><input class="input num" type="number" value="0"></div></div>
      <div class="field"><label>Motivo <span class="muted">(obligatorio)</span></label><select class="select"><option>Diferencia de conteo</option><option>Rotura / merma</option><option>Error de recepción</option><option>Error de picking</option><option>Otro</option></select></div>
      <div class="field"><label>Observación</label><textarea class="input" rows="2"></textarea></div>
      <div class="small muted">${ic("lock")} Requiere aprobación de un <b>Encargado de almacén</b>. El movimiento queda en el kardex con usuario, equipo y hora aunque se apruebe después.</div><button class="btn primary" data-toast="Ajuste enviado a aprobación de Fredy Rivero.">Enviar a aprobación</button></div></div>
      <div class="card" style="box-shadow:none"><div class="card-h"><h3>Pendientes de aprobación</h3><span class="pill warn">3</span></div>${table([{ h: "Fecha", f: a => a.f }, { h: "Ubicación", f: a => `<span class="mono">${a.loc}</span>` }, { h: "SKU", f: a => `<span class="mono">${a.sku}</span>` }, { h: "Ajuste", a: "r", f: a => `<b class="num" style="color:${a.q < 0 ? "var(--bad)" : "var(--ok)"}">${a.q > 0 ? "+" : ""}${a.q}</b>` }, { h: "Motivo", f: a => a.m }, { h: "Solicita", f: a => a.u }, { h: "", f: () => `<div class="row"><button class="btn sm" data-toast="Ajuste rechazado." data-kind="bad">Rechazar</button><button class="btn sm primary" data-toast="Ajuste aprobado y aplicado al saldo.">Aprobar</button></div>` }], [{ f: "22/09 09:14", loc: "E2-C04-N3", sku: "5T2010101032", q: -4, m: "Diferencia de conteo", u: "S. Mariscal" }, { f: "22/09 08:50", loc: "A2-C03-N1", sku: "5T2010101081", q: 12, m: "Error de recepción", u: "P. García" }, { f: "21/09 17:40", loc: "E5-C09-N2", sku: "5T2010101016", q: -2, m: "Rotura / merma", u: "R. Suárez" }])}</div></div></div>`;
  } else if (sub === "abc") {
    const groups = ["A", "B", "C"].map(r => { const ps = PRODUCTS.filter(p => p.rot === r); return { r, n: ps.length, u: ps.reduce((a, p) => a + stockOf(p.codigo), 0) }; });
    const totU = groups.reduce((a, g) => a + g.u, 0);
    body = `<div class="card-b"><div class="grid g-3" style="margin-bottom:16px">${groups.map(g => `<div class="card kpi" style="box-shadow:none"><span class="lbl">Clase ${g.r} · ${g.r === "A" ? "alta rotación, cerca de picking" : g.r === "B" ? "rotación media" : "baja rotación, fondo de nave"}</span><span class="val">${g.n}<small>SKU</small></span><span class="delta">${Math.round(g.u / totU * 100)} % de las unidades en stock</span></div>`).join("")}</div>
      <div class="row between wrap" style="margin-bottom:10px"><span class="muted small">Recalculado el 20/09/2026 sobre salidas de 90 días. Cambios de clase sugieren reubicación: la columna <b>Zona actual</b> se marca cuando no coincide con la clase.</span><button class="btn sm" data-toast="Recalculando clasificación ABC sobre 90 días…" data-kind="info">${ic("refresh")} Recalcular</button></div>
      ${table([{ h: "SKU", f: p => `<span class="mono link" data-prod="${p.codigo}">${p.codigo}</span><div class="small muted">${esc(p.desc)}</div>` }, { h: "Clase", f: p => rotTag(p.rot) }, { h: "Salidas 90 d", a: "r", f: p => fmt(p.rot === "A" ? ri(900, 2400) : p.rot === "B" ? ri(200, 800) : ri(10, 180)) }, { h: "Stock", a: "r", f: p => fmt(stockOf(p.codigo)) }, { h: "Zona actual", f: p => { const s = STOCK.find(x => x.codigo === p.codigo); const z = s ? RACKS.find(r => r.id === s.rack).zona : "—"; const ok = p.rot === "A" ? z.includes("Picking") || z === "Piso" : p.rot === "C" ? z.includes("Volumen") || z === "Reserva" : true; return `${z} ${ok ? "" : `<span class="pill warn plain">reubicar</span>`}`; } }, { h: "Sugerencia", f: p => p.rot === "A" ? "E1 / E2 · picking" : p.rot === "B" ? "E3–E5 · reserva" : "E6 · volumen" }], PRODUCTS.filter(p => p.estado === "ACTIVO").slice(0, 20))}`;
  } else {
    body = `<div class="toolbar"><div class="search">${ic("search")}<input placeholder="SKU o descripción"></div><div class="chips">${["Todos", "Bajo mínimo", "Sobre máximo", "En rango"].map((t, i) => `<button class="chip ${i === 0 ? "on" : ""}">${t}</button>`).join("")}</div><div style="flex:1"></div><button class="btn sm" data-toast="Propuesta de reposición enviada a Producción (7 SKU).">${ic("zap")} Generar propuesta de reposición</button></div>
      ${table([{ h: "SKU", f: p => `<span class="mono link" data-prod="${p.codigo}">${p.codigo}</span><div class="small muted">${esc(p.desc)}</div>` }, { h: "Clase", f: p => rotTag(p.rot) }, { h: "Mín", a: "r", f: p => fmt(p.min) }, { h: "Máx", a: "r", f: p => fmt(p.max) }, { h: "Stock", a: "r", f: p => `<b class="num">${fmt(stockOf(p.codigo))}</b>` }, { h: "Cobertura", f: p => { const s = stockOf(p.codigo); const pct = Math.min(100, Math.round(s / p.max * 100)); const k = s < p.min ? "bad" : s > p.max ? "warn" : "ok"; return `<div class="row"><div class="bar ${k}" style="width:120px"><i style="width:${pct}%"></i></div><span class="small num muted">${pct}%</span></div>`; } }, { h: "Situación", f: p => { const s = stockOf(p.codigo); return s < p.min ? `<span class="pill bad">Bajo mínimo</span>` : s > p.max ? `<span class="pill warn">Sobre máximo</span>` : `<span class="pill ok">En rango</span>`; } }, { h: "Días de stock", a: "r", f: p => `${ri(3, 60)} d` }], PRODUCTS.filter(p => p.estado === "ACTIVO").sort((a, b) => (stockOf(a.codigo) / a.min) - (stockOf(b.codigo) / b.min)).slice(0, 20))}`;
  }
  return `${pageHead("Stock e inventario", "Saldos por ubicación, lote y estado; kardex inmutable; conteos cíclicos con aprobación.", `<button class="btn">${ic("download")} Exportar</button><button class="btn primary" data-act="new-count">${ic("count")} Nuevo conteo</button>`)}
  <div class="card"><div class="tabs">${tabs.map(([k, l]) => `<button class="${sub === k ? "on" : ""}" data-sub-stock="${k}">${l}</button>`).join("")}</div>${body}</div>`;
};
VIEWS.stock.after = () => { const i = $("#stockQ"); if (i) { i.addEventListener("input", e => { state.filters.stockQ = e.target.value; const pos = e.target.selectionStart; render(); const n = $("#stockQ"); n.focus(); n.setSelectionRange(pos, pos); }); } };
function modalConteo() {
  openModal("Nuevo conteo de inventario", `<div class="grid g-3" style="margin-bottom:14px"><div class="field"><label>Tipo</label><select class="select"><option>Cíclico por clase</option><option>Por ubicación</option><option>Por producto</option><option>Por excepción</option><option>General (con bloqueo)</option></select></div><div class="field"><label>Alcance</label><select class="select"><option>Clase A · 80 ubicaciones</option><option>Rack E1</option><option>Rack E2</option><option>Área A2 · piso</option></select></div><div class="field"><label>Modalidad</label><select class="select"><option>Ciego (sin cantidad esperada)</option><option>Con cantidad esperada</option><option>Doble conteo si hay diferencia</option></select></div></div>
    <div class="grid g-2"><div class="field"><label>Operadores</label><div class="stack">${USERS_COL.filter(u => u.wh === "BOL2" && u.estado === "HABILITADO").slice(0, 5).map((u, i) => `<label class="row"><input type="checkbox" ${i < 3 ? "checked" : ""}> <span class="mini-av">${initials(u.nombre)}</span> ${u.nombre} <span class="muted small">· ${u.device || "sin equipo"}</span></label>`).join("")}</div></div>
    <div class="stack"><div class="field"><label>Tolerancia para aprobación automática</label><input class="input" value="0 unidades (todo requiere aprobación)"></div><div class="row"><button class="tog on" type="button"></button><span class="small">Bloquear picking en las ubicaciones mientras se cuentan</span></div><div class="row"><button class="tog" type="button"></button><span class="small">Congelar saldo inicial ahora (inventario general)</span></div></div></div>`,
    `<button class="btn" data-close>Cancelar</button><button class="btn primary" data-toast="Conteo 123 habilitado. Asignado a 3 colectores.">Habilitar conteo</button>`);
}
function drawerConteo(nro) {
  const c = CONTEOS.find(x => x.nro === nro); if (!c) return;
  const difs = [["E1-C03-N2", "5T2010101032", 40, 36], ["E2-C07-N1", "5T2010101001", 18, 21], ["E1-C09-N4", "5T2010101054", 12, 11], ["A2-C02-N1", "5T2010101081", 86, 86]].slice(0, c.dif + 1);
  openDrawer(`Conteo ${c.nro} · ${c.tipo}`, `<div class="grid g-3" style="margin-bottom:16px"><div class="card kpi" style="box-shadow:none"><span class="lbl">Avance</span><span class="val">${Math.round(c.contadas / c.ubic * 100)}<small>%</small></span><span class="delta">${c.contadas} de ${c.ubic} ubicaciones</span></div><div class="card kpi" style="box-shadow:none"><span class="lbl">Diferencias</span><span class="val">${c.dif}</span><span class="delta">pendientes de aprobar</span></div><div class="card kpi" style="box-shadow:none"><span class="lbl">IRA parcial</span><span class="val">${(c.ira * 100).toFixed(1)}<small>%</small></span><span class="delta">meta 98 %</span></div></div>
    <h3 style="font-size:14px;margin-bottom:8px">Ubicaciones con diferencia</h3>${table([{ h: "Ubicación", f: d => `<span class="mono">${d[0]}</span>` }, { h: "SKU", f: d => `<span class="mono small">${d[1]}</span>` }, { h: "Sistema", a: "r", f: d => d[2] }, { h: "Contado", a: "r", f: d => `<b class="num">${d[3]}</b>` }, { h: "Dif.", a: "r", f: d => d[3] - d[2] ? `<span class="pill ${d[3] - d[2] < 0 ? "bad" : "warn"} plain num">${d[3] - d[2] > 0 ? "+" : ""}${d[3] - d[2]}</span>` : `<span class="pill ok plain">OK</span>` }, { h: "", f: d => d[3] - d[2] ? `<button class="btn sm" data-toast="Recuento solicitado al colector.">Recontar</button>` : "" }], difs)}
    <dl class="kv" style="margin-top:16px"><dt>Responsable</dt><dd>${userName(c.resp)}</dd><dt>Apertura</dt><dd>${c.apertura}</dd><dt>Estado</dt><dd>${pillFor(c.estado)}</dd></dl>`,
    c.estado === "Finalizado" ? `<button class="btn">${ic("download")} Informe</button><button class="btn" data-close>Cerrar</button>` : `<button class="btn">${ic("download")} Exportar diferencias</button><button class="btn primary" data-toast="Conteo ${c.nro} finalizado. ${c.dif} ajustes aplicados con tu aprobación.">Aprobar diferencias y finalizar</button>`,
    "Inventario cíclico");
}

/* ===================== MAPA DE ALMACÉN ===================== */
VIEWS.ubicaciones = () => {
  const rack = state.sub.ubicaciones || "E1";
  const r = RACKS.find(x => x.id === rack);
  const ls = LOCATIONS.filter(l => l.rack === rack);
  const cells = [];
  for (let f = r.filas; f >= 1; f--) for (let c = 1; c <= r.cols; c++) { const l = ls.find(x => x.fila === f && x.col === c); const o = occupancy(l); cells.push(`<div class="cell ${heat(o)} ${l.blocked ? "blocked" : ""} ${state.selLoc === l.id ? "sel" : ""}" data-loc="${l.id}" title="${l.id} · ${Math.round(o * 100)} %"></div>`); }
  const sel = state.selLoc && LOCATIONS.find(l => l.id === state.selLoc);
  const selStock = sel ? STOCK.filter(s => s.loc === sel.id) : [];
  const occ = ls.filter(l => STOCK.some(s => s.loc === l.id)).length;
  return `${pageHead("Mapa de almacén", `${WH().name} · sucursal → bodega → rack/área → ubicación. Las ubicaciones se crean aquí y nunca se reutilizan.`, `<button class="btn">${ic("print")} Etiquetas del rack</button><button class="btn" data-act="new-rack">${ic("plus")} Nuevo rack / área</button><button class="btn primary" data-act="new-loc">${ic("plus")} Nueva ubicación</button>`)}
  <div class="grid" style="grid-template-columns:260px minmax(0,1fr) 340px">
    <div class="card"><div class="card-h"><h3>Topología</h3></div><div class="card-b" style="padding:8px"><ul class="tree"><li><div class="node">${ic("layers")}<b>${WH().name}</b></div><ul><li><div class="node">${ic("stock")}Bodega BOL2 <span class="cnt">${fmt(LOCATIONS.length)}</span></div><ul>${RACKS.map(x => `<li><div class="node ${x.id === rack ? "on" : ""}" data-rack="${x.id}">${ic("grid")}<span>${x.id} · ${x.name}</span><span class="cnt">${LOCATIONS.filter(l => l.rack === x.id).length}</span></div></li>`).join("")}</ul></li></ul></li></ul></div></div>
    <div class="card"><div class="card-h"><div><h3>${r.id} · ${r.name}</h3><span class="muted small">${r.zona} · ${r.filas} niveles × ${r.cols} columnas · ${occ}/${ls.length} ocupadas</span></div><div class="legend"><span><i style="background:var(--heat-1);border:1px solid var(--line)"></i>vacía</span><span><i style="background:var(--heat-3)"></i>parcial</span><span><i style="background:var(--heat-5)"></i>llena</span><span><i style="background:repeating-linear-gradient(45deg,var(--bad-soft),var(--bad-soft) 3px,transparent 3px,transparent 6px);border:1px solid var(--bad)"></i>bloqueada</span></div></div>
      <div class="card-b"><div class="row" style="align-items:stretch;gap:8px"><div class="stack" style="justify-content:space-around;gap:0;font-size:11px;color:var(--ink-3);text-align:right;padding:0 2px">${Array.from({ length: r.filas }, (_, i) => `<span>N${r.filas - i}</span>`).join("")}</div><div class="rackmap" style="grid-template-columns:repeat(${r.cols},minmax(0,1fr));flex:1">${cells.join("")}</div></div><div class="row" style="gap:4px;margin-top:6px;padding-left:28px;font-size:11px;color:var(--ink-3)">${Array.from({ length: r.cols }, (_, i) => `<span style="flex:1;text-align:center">C${pad(i + 1)}</span>`).join("")}</div>
      <hr class="sep"><div class="grid g-3 small"><div><div class="muted">Formato de código</div><b class="mono">RACK-Cnn-Nn</b> <span class="muted">· ej. ${ls[5]?.id}</span></div><div><div class="muted">Capacidad por ubicación</div><b>${ls[0].cap} pallets/bultos</b></div><div><div class="muted">Orden de recorrido</div><b>Serpentina por columna</b> <span class="muted">(sort_seq)</span></div></div></div></div>
    <div class="card"><div class="card-h"><h3>${sel ? `<span class="mono">${sel.id}</span>` : "Ubicación"}</h3>${sel ? pillFor(sel.blocked ? "Bloqueada" : selStock.length ? "Ocupada" : "Vacía") : ""}</div><div class="card-b">${sel ? `<dl class="kv small" style="margin-bottom:12px"><dt>Rack</dt><dd>${sel.rack} · ${r.name}</dd><dt>Columna / nivel</dt><dd>C${pad(sel.col)} · N${sel.fila}</dd><dt>Ocupación</dt><dd>${Math.round(occupancy(sel) * 100)} %</dd><dt>Recorrido</dt><dd class="num">#${sel.sort}</dd></dl>${selStock.length ? `<div class="stack">${selStock.map(s => `<div class="cline"><div><b class="mono small">${s.codigo}</b><span>${esc(P(s.codigo).desc)}<br>lote ${s.lote} · ${fmtD(s.ingreso)}</span></div><span class="q">${fmt(s.qty)}</span></div>`).join("")}</div>` : `<div class="empty" style="padding:20px">${ic("box")}<div>Sin stock en esta ubicación</div></div>`}<div class="stack" style="margin-top:12px"><button class="btn block">${ic("print")} Imprimir etiqueta QR</button><button class="btn block" data-toast="Ubicación ${sel.id} ${sel.blocked ? "desbloqueada" : "bloqueada"} para picking.">${ic("lock")} ${sel.blocked ? "Desbloquear" : "Bloquear"} ubicación</button><button class="btn block">${ic("edit")} Editar</button></div>` : `<div class="empty">${ic("map")}<div>Selecciona una celda del rack para ver su contenido</div></div>`}</div></div>
  </div>`;
};
function modalRack() { openModal("Nuevo rack / área", `<div class="grid g-2"><div class="field"><label>Bodega</label><select class="select"><option>BOL2 · Bolsas</option></select></div><div class="field"><label>Tipo</label><select class="select"><option>Rack (estantería)</option><option>Área de piso</option><option>Zona virtual (ajustes, tránsito)</option><option>Muelle</option></select></div><div class="field"><label>Código</label><input class="input mono" value="E7"></div><div class="field"><label>Descripción</label><input class="input" value="Estante 7"></div><div class="field"><label>Niveles (filas)</label><input class="input num" type="number" value="4"></div><div class="field"><label>Columnas</label><input class="input num" type="number" value="10"></div><div class="field"><label>Zona</label><select class="select"><option>Picking A</option><option>Reserva</option><option>Volumen C</option><option>Cuarentena</option></select></div><div class="field"><label>Capacidad por ubicación</label><input class="input num" type="number" value="6"></div></div><p class="small muted">Se generarán <b>40 ubicaciones</b> con código <span class="mono">E7-C01-N1 … E7-C10-N4</span> y su orden de recorrido en serpentina. Podrás imprimir las 40 etiquetas QR al confirmar.</p>`, `<button class="btn" data-close>Cancelar</button><button class="btn primary" data-toast="Rack E7 creado con 40 ubicaciones. Etiquetas listas para imprimir.">Crear rack y ubicaciones</button>`); }
function modalLoc() { openModal("Nueva ubicación", `<div class="grid g-2"><div class="field"><label>Rack / área</label><select class="select">${RACKS.map(r => `<option>${r.id} · ${r.name}</option>`).join("")}</select></div><div class="field"><label>Código</label><input class="input mono" value="E1-C11-N1"></div><div class="field"><label>Columna</label><input class="input num" type="number" value="11"></div><div class="field"><label>Nivel</label><input class="input num" type="number" value="1"></div><div class="field"><label>Tipo</label><select class="select"><option>Picking</option><option>Reserva</option><option>Piso</option><option>Cuarentena</option></select></div><div class="field"><label>Capacidad</label><input class="input num" type="number" value="6"></div></div>`, `<button class="btn" data-close>Cancelar</button><button class="btn primary" data-toast="Ubicación E1-C11-N1 creada.">Crear</button>`); }

/* ===================== MAESTROS ===================== */
VIEWS.productos = () => {
  const q = (state.filters.prodQ || "").toLowerCase();
  const rows = PRODUCTS.filter(p => !q || p.codigo.includes(q) || p.desc.toLowerCase().includes(q) || p.sub.toLowerCase().includes(q));
  return `${pageHead("Productos", "Los ítems se crean en WorkCorp y bajan al WMS; aquí se administran los parámetros logísticos.", `<button class="btn" data-go="importar">${ic("upload")} Importar</button><button class="btn" data-go="etiquetas">${ic("qr")} Etiquetas</button><button class="btn primary" data-act="new-prod">${ic("plus")} Nuevo producto</button>`)}
  <div class="grid g-4" style="margin-bottom:16px"><div class="card kpi"><span class="lbl">SKU activos</span><span class="val">${PRODUCTS.filter(p => p.estado === "ACTIVO").length}</span><span class="delta">${PRODUCTS.filter(p => p.estado !== "ACTIVO").length} inactivos</span></div><div class="card kpi"><span class="lbl">Última sincronización</span><span class="val">08:41</span><span class="delta up">${ic("check")} WorkCorp · 1.279 ítems</span></div><div class="card kpi"><span class="lbl">Sin parámetros logísticos</span><span class="val">7</span><span class="delta">sin mín/máx o sin clase</span></div><div class="card kpi"><span class="lbl">Con vida útil</span><span class="val">${PRODUCTS.filter(p => p.vidaUtil).length}</span><span class="delta">FEFO obligatorio</span></div></div>
  <div class="card"><div class="toolbar"><div class="search">${ic("search")}<input id="prodQ" placeholder="Código, descripción, subcategoría" value="${esc(state.filters.prodQ || "")}"></div><div class="chips">${["Todos", "Clase A", "Clase B", "Clase C", "Inactivos"].map((t, i) => `<button class="chip ${i === 0 ? "on" : ""}">${t}</button>`).join("")}</div><div style="flex:1"></div><button class="btn sm">${ic("download")} Exportar</button></div>
  ${table([{ h: "Código", f: p => `<span class="mono link" data-prod="${p.codigo}">${p.codigo}</span>` }, { h: "Descripción", f: p => `<b>${esc(p.desc)}</b><div class="small muted">${p.cat} › ${p.sub} · ${p.fabrica}</div>` }, { h: "UM", f: p => p.um }, { h: "Clase", f: p => rotTag(p.rot) }, { h: "Mín / Máx", a: "r", f: p => `<span class="num">${p.min} / ${p.max}</span>` }, { h: "Stock", a: "r", f: p => `<b class="num">${fmt(stockOf(p.codigo))}</b>` }, { h: "Ubicaciones", a: "r", f: p => STOCK.filter(s => s.codigo === p.codigo).length }, { h: "Vida útil", f: p => p.vidaUtil ? `${p.vidaUtil} d` : `<span class="muted">—</span>` }, { h: "Estado", f: p => pillFor(p.estado) }], rows, { rowAttr: p => `class="clickable" data-prod="${p.codigo}"` })}${foot(rows.length, "productos")}</div>`;
};
VIEWS.productos.after = () => { const i = $("#prodQ"); if (i) i.addEventListener("input", e => { state.filters.prodQ = e.target.value; const pos = e.target.selectionStart; render(); const n = $("#prodQ"); n.focus(); n.setSelectionRange(pos, pos); }); };
function drawerProducto(code) {
  const p = P(code); if (!p) return;
  const st = STOCK.filter(s => s.codigo === code);
  const hist = KARDEX.filter(k => k.codigo === code).slice(0, 6);
  openDrawer(esc(p.desc), `
    <div class="grid g-3" style="margin-bottom:16px"><div class="card kpi" style="box-shadow:none"><span class="lbl">Stock</span><span class="val">${fmt(stockOf(code))}<small>${p.um}</small></span><span class="delta">${st.length} ubicaciones</span></div><div class="card kpi" style="box-shadow:none"><span class="lbl">Reservado</span><span class="val">${fmt(st.reduce((a, s) => a + s.reservado, 0))}</span><span class="delta">en olas activas</span></div><div class="card kpi" style="box-shadow:none"><span class="lbl">Cobertura</span><span class="val">${ri(4, 40)}<small>días</small></span><span class="delta ${stockOf(code) < p.min ? "down" : ""}">${stockOf(code) < p.min ? "bajo mínimo" : "en rango"}</span></div></div>
    <div class="tabs" style="margin:0 -20px 14px;padding:0 20px"><button class="on">Ficha</button><button>Saldos</button><button>Kardex</button><button>Etiqueta</button></div>
    <div class="grid g-2"><dl class="kv"><dt>Código</dt><dd class="mono">${p.codigo}</dd><dt>Cód. fábrica</dt><dd class="mono">${p.fabrica}</dd><dt>Categoría</dt><dd>${p.cat}</dd><dt>Subcategoría</dt><dd>${p.sub}</dd><dt>Unidad</dt><dd>${p.um}</dd><dt>Peso bulto</dt><dd>${p.peso} kg</dd></dl><dl class="kv"><dt>Clase ABC</dt><dd>${rotTag(p.rot)}</dd><dt>Mínimo</dt><dd>${p.min}</dd><dt>Máximo</dt><dd>${p.max}</dd><dt>Vida útil</dt><dd>${p.vidaUtil ? p.vidaUtil + " días · FEFO" : "No aplica · FIFO"}</dd><dt>Serializado</dt><dd>No</dd><dt>Estado</dt><dd>${pillFor(p.estado)}</dd></dl></div>
    <h3 style="font-size:14px;margin:18px 0 8px">Saldos por ubicación</h3>${table([{ h: "Ubicación", f: s => `<span class="mono link" data-loc="${s.loc}">${s.loc}</span>` }, { h: "Lote", f: s => `<span class="mono small">${s.lote}</span>` }, { h: "Ingreso", f: s => fmtD(s.ingreso) }, { h: "Cant.", a: "r", f: s => `<b class="num">${fmt(s.qty)}</b>` }, { h: "Estado", f: s => pillFor(s.estado) }], st, { empty: "Sin stock" })}
    <h3 style="font-size:14px;margin:18px 0 8px">Últimos movimientos</h3>${table([{ h: "Fecha", f: k => `<span class="small num">${k.ts}</span>` }, { h: "Mov.", f: k => `<span class="pill ${k.dir === "in" ? "ok" : k.dir === "out" ? "bad" : "info"} plain">${k.tipo}</span>` }, { h: "Cant.", a: "r", f: k => `<b class="num">${k.qty > 0 ? "+" : ""}${fmt(k.qty)}</b>` }, { h: "Usuario", f: k => userName(k.user) }], hist, { empty: "Sin movimientos recientes" })}`,
    `<button class="btn" data-go="etiquetas">${ic("qr")} Etiqueta</button><button class="btn">${ic("edit")} Editar parámetros</button><button class="btn primary" data-close>Cerrar</button>`, `Productos › ${p.sub}`);
}
function drawerLoc(id) {
  const l = LOCATIONS.find(x => x.id === id); if (!l) return; state.selLoc = id; state.sub.ubicaciones = l.rack;
  closeOverlays(); go("ubicaciones");
}
VIEWS.clientes = () => `${pageHead("Clientes", "Sincronizados desde WorkCorp. Direcciones de entrega y zona logística se administran aquí.", `<button class="btn" data-go="importar">${ic("upload")} Importar</button><button class="btn primary" data-act="new-client">${ic("plus")} Nuevo cliente</button>`)}
  <div class="card"><div class="toolbar"><div class="search">${ic("search")}<input placeholder="Código, razón social, NIT, ciudad"></div><div class="chips">${["Todos", "Santa Cruz", "Beni", "Pando", "La Paz", "Cochabamba", "Tarija"].map((t, i) => `<button class="chip ${i === 0 ? "on" : ""}">${t}</button>`).join("")}</div><div style="flex:1"></div><button class="btn sm">${ic("download")} Exportar</button></div>
  ${table([{ h: "Código", f: c => `<span class="mono link">${c.codigo}</span>` }, { h: "Cliente", f: c => `<b>${esc(c.nombre)}</b><div class="small muted">NIT ${c.nit}</div>` }, { h: "Canal", f: c => `<span class="tag">${c.canal}</span>` }, { h: "Ciudad", f: c => `${c.ciudad}<div class="small muted">${c.dpto}</div>` }, { h: "Teléfono", f: c => `<span class="num">${c.tel}</span>` }, { h: "Pedidos 12 m", a: "r", f: c => fmt(c.pedidos) }, { h: "Estado", f: c => pillFor(c.estado) }], CLIENTS, { rowAttr: c => `class="clickable" data-client="${c.codigo}"` })}${foot(642, "clientes")}</div>`;
function drawerCliente(code) { const c = CLIENTS.find(x => x.codigo === code); if (!c) return; const ps = PEDIDOS.filter(p => p.cliente.codigo === code); openDrawer(esc(c.nombre), `<div class="grid g-2" style="margin-bottom:16px"><dl class="kv"><dt>Código</dt><dd class="mono">${c.codigo}</dd><dt>NIT</dt><dd>${c.nit}</dd><dt>Canal</dt><dd>${c.canal}</dd><dt>Teléfono</dt><dd>${c.tel}</dd></dl><dl class="kv"><dt>Ciudad</dt><dd>${c.ciudad}</dd><dt>Departamento</dt><dd>${c.dpto}</dd><dt>Zona logística</dt><dd>${c.dpto === "Santa Cruz" ? "Local" : "Interior · ruta norte"}</dd><dt>Estado</dt><dd>${pillFor(c.estado)}</dd></dl></div><h3 style="font-size:14px;margin-bottom:8px">Pedidos recientes</h3>${table([{ h: "Pedido", f: p => `<span class="mono link" data-ped="${p.nro}">${p.nro}</span>` }, { h: "Fecha", f: p => fmtD(p.fecha) }, { h: "Líneas", a: "r", f: p => p.lines.length }, { h: "Estado", f: p => pillFor(p.estado) }], ps, { empty: "Sin pedidos en el periodo" })}`, `<button class="btn">${ic("edit")} Editar</button><button class="btn primary" data-close>Cerrar</button>`, "Clientes"); }
VIEWS.transporte = () => `${pageHead("Choferes y camiones", "Flota propia y terceros. Cada despacho queda asociado a vehículo y conductor.", `<button class="btn" data-go="importar">${ic("upload")} Importar</button><button class="btn" data-act="new-truck">${ic("plus")} Nuevo vehículo</button><button class="btn primary" data-act="new-driver">${ic("plus")} Nuevo chofer</button>`)}
  <div class="grid g-2"><div class="card"><div class="card-h"><h3>Choferes</h3><span class="muted small">${DRIVERS.filter(d => d.estado !== "INACTIVO").length} activos</span></div>${table([{ h: "Chofer", f: d => `<div class="row"><span class="mini-av">${initials(d.nombre)}</span><div><b>${esc(d.nombre)}</b><div class="small muted mono">${d.ci}</div></div></div>` }, { h: "Licencia", f: d => `<span class="tag">${d.lic}</span>` }, { h: "Teléfono", f: d => d.tel }, { h: "Viajes", a: "r", f: d => fmt(d.viajes) }, { h: "Estado", f: d => pillFor(d.estado) }], DRIVERS)}</div>
  <div class="card"><div class="card-h"><h3>Vehículos</h3><span class="muted small">${TRUCKS.filter(t => t.estado === "DISPONIBLE").length} disponibles</span></div>${table([{ h: "Placa", f: t => `<b class="mono">${t.placa}</b><div class="small muted">${t.marca}</div>` }, { h: "Tipo", f: t => t.tipo }, { h: "Capacidad", f: t => `<span class="small">${t.cap}</span>` }, { h: "Chofer habitual", f: t => DRIVERS.find(d => d.ci === t.chofer)?.nombre || `<span class="muted">—</span>` }, { h: "Estado", f: t => pillFor(t.estado) }], TRUCKS)}</div></div>`;

/* ==== 07-config.html ==== */
/* ===================== CONFIGURACIÓN: IMPORTAR DATOS ===================== */
const DATASETS = [
  { id: "productos", lbl: "Productos", desc: "SKU, descripción, UM, categoría, mín/máx, clase", icon: "box", fields: ["codigo*", "descripcion*", "um*", "categoria", "subcategoria", "cod_fabrica", "minimo", "maximo", "clase_abc", "vida_util_dias", "peso_kg", "estado"], sample: "codigo,descripcion,um,categoria,subcategoria,minimo,maximo,clase\n5T2010101001,BOLSA 8 X 12 ECO COLORES,BUL,Bolsas,Eco,40,400,A\n5T2010101002,BOLSA 11 X 14 ECO COLORES,BUL,Bolsas,Eco,40,400,A\n5T2010101099,BOLSA 12 X 18 ECO AZUL,BUL,Bolsas,Eco,20,200,B\n5T2010101006,BOLSA 13 X 16 COMUN CD,,Bolsas,Común,40,400,A\n5T2010101001,BOLSA 8 X 12 ECO COLORES (DUP),BUL,Bolsas,Eco,40,400,A" },
  { id: "clientes", lbl: "Clientes", desc: "Código, razón social, NIT, dirección, zona", icon: "users", fields: ["codigo*", "razon_social*", "nit", "nombre", "telefono", "direccion", "departamento", "provincia", "ciudad", "zona", "canal", "estado"], sample: "codigo,razon_social,nit,ciudad,departamento,telefono\nB0100004,FERRETERIA TAROPE,,Trinidad,Beni,72033118\nS0200011,SUPERMERCADOS HIPERMAXI,1028374012,Santa Cruz,Santa Cruz,33456700\nX9,SIN NOMBRE,,,," },
  { id: "ubicaciones", lbl: "Ubicaciones", desc: "Bodega, rack, columna, nivel, tipo, capacidad", icon: "map", fields: ["codigo*", "bodega*", "rack*", "columna*", "nivel*", "tipo", "capacidad", "sort_seq", "estado"], sample: "codigo,bodega,rack,columna,nivel,tipo,capacidad\nE7-C01-N1,BOL2,E7,1,1,Picking,6\nE7-C01-N2,BOL2,E7,1,2,Picking,6\nE7-C02-N1,BOL2,E7,2,1,Reserva,6" },
  { id: "choferes", lbl: "Choferes", desc: "CI, nombre, licencia, teléfono", icon: "users", fields: ["ci*", "nombre*", "apellidos*", "licencia", "telefono", "direccion", "estado"], sample: "ci,nombre,apellidos,licencia,telefono\n5891192,Fredy,Rivero Padilla,CAT-C,78912233\n6242792,Omar,Ponce,CAT-C,72479200" },
  { id: "camiones", lbl: "Camiones", desc: "Placa, tipo, marca, capacidad, chofer habitual", icon: "truck", fields: ["placa*", "tipo*", "marca", "modelo", "capacidad_kg", "volumen_m3", "chofer_ci", "estado"], sample: "placa,tipo,marca,capacidad_kg,volumen_m3\n2384-KHT,Camion 8t,Hino 500,8000,42\n4102-LPD,Camion 12t,Volvo VM,12000,60" },
  { id: "usuarios_colector", lbl: "Usuarios colector", desc: "Cuenta, nombre, rol, almacén, PIN", icon: "phone", fields: ["usuario*", "nombre*", "documento", "rol*", "almacen*", "pin", "estado"], sample: "usuario,nombre,rol,almacen,pin\npgarcia,Pedro Garcia Ortiz,Encargado,BOL2,1234\nrsuarez,Ronaldo Suarez Dorado,Operador,BOL2,4321" },
  { id: "usuarios_escritorio", lbl: "Usuarios escritorio", desc: "Cuenta, nombre, rol, almacenes permitidos", icon: "shield", fields: ["usuario*", "nombre*", "correo", "rol*", "almacenes*", "estado"], sample: "usuario,nombre,correo,rol,almacenes\njguasace,Juan Pablo Guasace,jguasace@plasticoscarmen.com,Administrador,BOL2\nfrivero,Fredy Rivero,frivero@plasticoscarmen.com,Encargado,BOL2|TAN" },
];
const imp = { step: 0, ds: null, file: null, rows: [], headers: [], map: {}, mode: "upsert" };
function parseCSV(text) {
  const sep = text.split("\n")[0].includes(";") ? ";" : text.split("\n")[0].includes("\t") ? "\t" : ",";
  const lines = text.replace(/\r/g, "").split("\n").filter(l => l.trim());
  const headers = lines[0].split(sep).map(h => h.trim().replace(/^"|"$/g, ""));
  const rows = lines.slice(1).map(l => { const cells = l.split(sep).map(c => c.trim().replace(/^"|"$/g, "")); const o = {}; headers.forEach((h, i) => o[h] = cells[i] ?? ""); return o; });
  return { headers, rows };
}
const norm = s => s.toLowerCase().normalize("NFD").replace(/[^a-z0-9]/g, "");
function autoMap() { imp.map = {}; const fields = imp.ds.fields.map(f => f.replace("*", "")); imp.headers.forEach(h => { const n = norm(h); const hit = fields.find(f => norm(f) === n) || fields.find(f => n.includes(norm(f)) || norm(f).includes(n)); if (hit) imp.map[h] = hit; }); }
function validateRows() {
  const req = imp.ds.fields.filter(f => f.endsWith("*")).map(f => f.slice(0, -1));
  const keyField = req[0]; const seen = new Set(); const inv = Object.fromEntries(Object.entries(imp.map).map(([h, f]) => [f, h]));
  return imp.rows.map((r, i) => { const errs = []; req.forEach(f => { const h = inv[f]; if (!h) errs.push(`Falta mapear ${f}`); else if (!r[h]) errs.push(`${f} vacío`); }); const k = r[inv[keyField]]; if (k) { if (seen.has(k)) errs.push(`${keyField} duplicado`); seen.add(k); } if (imp.ds.id === "productos" && inv.um && r[inv.um] && !["BUL", "KG", "UN", "M", "ROLLO"].includes(r[inv.um].toUpperCase())) errs.push("UM desconocida"); return { i: i + 2, r, errs }; });
}
VIEWS.importar = () => {
  const steps = ["Elegir datos", "Cargar archivo", "Mapear columnas", "Validar", "Confirmar"];
  const stepsHtml = `<div class="steps" style="margin-bottom:20px">${steps.map((s, i) => `<div class="st ${i < imp.step ? "done" : i === imp.step ? "cur" : ""}">${s}</div>`).join("")}</div>`;
  let body = "";
  if (imp.step === 0) {
    body = `<p class="muted" style="margin:0 0 14px">Elige qué tabla vas a cargar. Acepta <b>.csv</b>, <b>.txt</b> y <b>.xlsx</b> (hoja 1). Cada carga queda en el historial con quién la hizo y qué filas cambiaron.</p><div class="grid g-3" style="margin-bottom:8px">${DATASETS.map(d => `<button class="dataset ${imp.ds?.id === d.id ? "on" : ""}" data-ds="${d.id}"><span class="ic">${ic(d.icon)}</span><span><b>${d.lbl}</b><span>${d.desc}</span></span></button>`).join("")}</div>`;
  } else if (imp.step === 1) {
    body = `<div class="grid g-2-1"><div class="drop" id="drop"><input type="file" id="fileIn" accept=".csv,.txt,.xlsx" hidden>${ic("upload")}<h3 style="font-size:17px">Arrastra aquí el archivo de <b>${imp.ds.lbl}</b></h3><p class="muted" style="margin:6px 0 14px">o</p><div class="row" style="justify-content:center;gap:8px;flex-wrap:wrap"><button class="btn primary" id="pickFile">${ic("file")} Elegir archivo</button><button class="btn" id="useSample">Usar archivo de ejemplo</button></div>${imp.file ? `<p class="small" style="margin-top:14px">${ic("check")} <b>${esc(imp.file)}</b> · ${imp.rows.length} filas · ${imp.headers.length} columnas</p>` : ""}</div>
      <div class="card" style="box-shadow:none"><div class="card-h"><h3>Plantilla esperada</h3><button class="btn sm" data-toast="Plantilla copiada al portapapeles." data-copy="${esc(imp.ds.fields.map(f => f.replace("*", "")).join(","))}">${ic("file")} Copiar encabezados</button></div><div class="card-b"><div class="chips">${imp.ds.fields.map(f => `<span class="chip" style="cursor:default">${f.endsWith("*") ? `<b>${f.slice(0, -1)}</b> <span style="color:var(--red)">*</span>` : f}</span>`).join("")}</div><p class="small muted" style="margin:12px 0 0">Los campos con <span style="color:var(--red)">*</span> son obligatorios. El orden de las columnas no importa: en el siguiente paso las emparejas.</p><hr class="sep"><div class="field"><label>Modo de carga</label><div class="seg"><button class="${imp.mode === "upsert" ? "on" : ""}" data-mode="upsert">Crear y actualizar</button><button class="${imp.mode === "insert" ? "on" : ""}" data-mode="insert">Solo nuevos</button><button class="${imp.mode === "replace" ? "on" : ""}" data-mode="replace">Reemplazar tabla</button></div></div></div></div></div>`;
  } else if (imp.step === 2) {
    const fields = imp.ds.fields.map(f => f.replace("*", ""));
    body = `<div class="grid g-2-1"><div class="card" style="box-shadow:none"><div class="card-h"><h3>Columnas del archivo → campos del WMS</h3><span class="muted small">${Object.keys(imp.map).length}/${imp.headers.length} emparejadas</span></div><div class="card-b"><div class="mapping">${imp.headers.map(h => `<span class="src">${esc(h)}</span><span class="arr">→</span><select class="select" data-maph="${esc(h)}"><option value="">— no importar —</option>${fields.map(f => `<option value="${f}" ${imp.map[h] === f ? "selected" : ""}>${f}${imp.ds.fields.includes(f + "*") ? " *" : ""}</option>`).join("")}</select><span class="small muted mono" title="Ejemplo">${esc((imp.rows[0] || {})[h] || "").slice(0, 18)}</span>`).join("")}</div></div></div>
      <div class="card" style="box-shadow:none"><div class="card-h"><h3>Vista previa</h3></div><div class="tbl-wrap" style="max-height:380px;overflow:auto"><table class="tbl small" style="white-space:nowrap"><thead><tr>${imp.headers.slice(0, 5).map(h => `<th>${esc(h)}</th>`).join("")}</tr></thead><tbody>${imp.rows.slice(0, 8).map(r => `<tr>${imp.headers.slice(0, 5).map(h => `<td>${esc(r[h])}</td>`).join("")}</tr>`).join("")}</tbody></table></div></div></div>`;
  } else if (imp.step === 3) {
    const v = validateRows(); const bad = v.filter(x => x.errs.length); const okN = v.length - bad.length;
    body = `<div class="grid g-4" style="margin-bottom:16px"><div class="card kpi" style="box-shadow:none"><span class="lbl">Filas leídas</span><span class="val">${fmt(v.length)}</span></div><div class="card kpi" style="box-shadow:none"><span class="lbl">Listas para cargar</span><span class="val" style="color:var(--ok)">${fmt(okN)}</span></div><div class="card kpi" style="box-shadow:none"><span class="lbl">Con errores</span><span class="val" style="color:${bad.length ? "var(--bad)" : "var(--ink)"}">${fmt(bad.length)}</span><span class="delta">se omiten al confirmar</span></div><div class="card kpi" style="box-shadow:none"><span class="lbl">Modo</span><span class="val" style="font-size:18px">${{ upsert: "Crear y actualizar", insert: "Solo nuevos", replace: "Reemplazar" }[imp.mode]}</span></div></div>
      ${bad.length ? `<div class="card" style="box-shadow:none"><div class="card-h"><h3>Filas con errores</h3><button class="btn sm">${ic("download")} Descargar errores</button></div>${table([{ h: "Fila", a: "r", f: x => x.i }, ...imp.headers.slice(0, 4).map(h => ({ h: esc(h), f: x => esc(x.r[h]) })), { h: "Error", f: x => x.errs.map(e => `<span class="pill bad plain">${esc(e)}</span>`).join(" ") }], bad)}</div>` : `<div class="card" style="box-shadow:none"><div class="card-b row">${ic("check")} <b>Todo en orden.</b> <span class="muted">Ninguna fila tiene errores de validación.</span></div></div>`}`;
  } else {
    const v = validateRows(); const okN = v.filter(x => !x.errs.length).length;
    body = `<div class="card" style="box-shadow:none;max-width:640px;margin:0 auto;text-align:center"><div class="card-b" style="padding:36px"><div style="width:64px;height:64px;border-radius:50%;background:var(--ok-soft);color:var(--ok);display:grid;place-items:center;margin:0 auto 14px">${ic("check")}</div><h2 style="font-size:22px">Carga completada</h2><p class="muted">${fmt(okN)} registros de <b>${imp.ds.lbl}</b> ${imp.mode === "insert" ? "creados" : "creados o actualizados"} desde <span class="mono small">${esc(imp.file)}</span>.<br>Los colectores reciben el cambio en su próxima sincronización de maestros.</p><div class="row" style="justify-content:center;gap:8px"><button class="btn" data-imp-reset>Nueva carga</button><button class="btn primary" data-go="${{ productos: "productos", clientes: "clientes", ubicaciones: "ubicaciones", choferes: "transporte", camiones: "transporte", usuarios_colector: "usuarios", usuarios_escritorio: "usuarios" }[imp.ds.id]}">Ver ${imp.ds.lbl}</button></div></div></div>`;
  }
  const canNext = imp.step === 0 ? !!imp.ds : imp.step === 1 ? imp.rows.length > 0 : imp.step === 2 ? imp.ds.fields.filter(f => f.endsWith("*")).every(f => Object.values(imp.map).includes(f.slice(0, -1))) : true;
  const nav = imp.step < 4 ? `<div class="row between" style="margin-top:18px;flex-wrap:wrap;gap:8px"><button class="btn" data-imp-prev ${imp.step === 0 ? "disabled" : ""}>Atrás</button><div class="row">${imp.step === 2 && !canNext ? `<span class="small" style="color:var(--warn)">${ic("alert")} Falta mapear un campo obligatorio</span>` : ""}<button class="btn primary" data-imp-next ${canNext ? "" : "disabled"}>${imp.step === 3 ? `Confirmar carga de ${validateRows().filter(x => !x.errs.length).length} filas` : "Continuar"} ${ic("arrow")}</button></div></div>` : "";
  return `${pageHead("Importar datos", "Carga masiva de maestros desde archivos de WorkCorp, Excel o del SGLA anterior.", `<button class="btn" data-toast="Exportación programada. Recibirás el archivo por correo." data-kind="info">${ic("download")} Exportar maestros</button>`)}
  <div class="grid g-2-1"><div class="card"><div class="card-b">${stepsHtml}${body}${nav}</div></div>
  <div class="stack"><div class="card"><div class="card-h"><h3>Historial de cargas</h3></div><div class="card-b stack">${IMPORT_HISTORY.map(h => `<div><div class="row between"><b class="small">${h.dataset}</b><span class="small muted">${h.fecha}</span></div><div class="small muted mono" style="word-break:break-all">${esc(h.archivo)}</div><div class="row small" style="margin-top:4px"><span class="pill ok plain">${fmt(h.ok)} ok</span>${h.err ? `<span class="pill bad plain">${h.err} errores</span>` : ""}<span class="muted">· ${h.user}</span></div></div>`).join("")}</div></div>
  <div class="card"><div class="card-h"><h3>Sincronización WorkCorp</h3><span class="pill ok">Conectado</span></div><div class="card-b stack small">${[["Ítems", "cada hora", "08:41", "ok"], ["Clientes", "cada hora", "08:41", "ok"], ["Órdenes de ingreso", "cada 5 min", "09:35", "ok"], ["Pedidos", "cada 2 min", "09:38", "ok"], ["Movimientos confirmados → ERP", "inmediato", "3 en reintento", "warn"]].map(([n, f, l, k]) => `<div class="row between"><span><b>${n}</b> <span class="muted">· ${f}</span></span><span class="pill ${k} plain">${l}</span></div>`).join("")}<button class="btn sm block" data-toast="Sincronización manual iniciada." data-kind="info">${ic("refresh")} Sincronizar ahora</button></div></div></div></div>`;
};
VIEWS.importar.after = () => {
  const drop = $("#drop"); if (!drop) return;
  const load = (name, text) => { const { headers, rows } = parseCSV(text); imp.file = name; imp.headers = headers; imp.rows = rows; autoMap(); render(); toast(`${rows.length} filas leídas de ${name}`, "info"); };
  $("#pickFile").onclick = () => $("#fileIn").click();
  $("#useSample").onclick = () => load(`ejemplo_${imp.ds.id}.csv`, imp.ds.sample);
  $("#fileIn").onchange = e => { const f = e.target.files[0]; if (!f) return; if (/\.xlsx$/i.test(f.name)) { toast("Lectura de .xlsx se conecta al servidor en la versión real; usa CSV en este prototipo.", "info", 4500); return; } const rd = new FileReader(); rd.onload = () => load(f.name, rd.result); rd.readAsText(f, "utf-8"); };
  ["dragenter", "dragover"].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.add("over"); }));
  ["dragleave", "drop"].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.remove("over"); }));
  drop.addEventListener("drop", e => { const f = e.dataTransfer.files[0]; if (!f) return; const rd = new FileReader(); rd.onload = () => load(f.name, rd.result); rd.readAsText(f, "utf-8"); });
};

/* ===================== ETIQUETAS QR ===================== */
const LABEL_TEMPLATES = {
  ubicacion: { lbl: "Ubicación", desc: "Rack y piso · legible a 2–3 m", size: "100x50", fields: { qr: true, code128: false, titulo: true, desc: true, logo: true, franja: true, lote: false, venc: false, cant: false, fecha: false }, qrfmt: "plain", sample: "loc" },
  item: { lbl: "Ítem / bulto", desc: "Producto terminado", size: "100x50", fields: { qr: true, code128: true, titulo: true, desc: true, logo: true, franja: true, lote: true, venc: true, cant: true, fecha: true }, qrfmt: "gs1", sample: "prod" },
  pallet: { lbl: "Pallet (SSCC)", desc: "Etiqueta logística GS1", size: "100x150", fields: { qr: true, code128: true, titulo: true, desc: true, logo: true, franja: true, lote: true, venc: false, cant: true, fecha: true }, qrfmt: "gs1", sample: "pallet" },
  despacho: { lbl: "Bulto de despacho", desc: "Cliente, pedido y ruta", size: "100x100", fields: { qr: true, code128: true, titulo: true, desc: true, logo: true, franja: true, lote: false, venc: false, cant: true, fecha: true }, qrfmt: "json", sample: "ped" },
};
const lab = { tpl: "ubicacion", cfg: JSON.parse(JSON.stringify(LABEL_TEMPLATES.ubicacion)), sampleIdx: 0, qrSize: 3, font: 100, printer: "Zebra ZD421 · Almacén BOL2", copies: 1 };
function labelSample() {
  const t = lab.cfg.sample;
  if (t === "loc") { const l = LOCATIONS.filter(x => x.rack === "E1")[lab.sampleIdx % 40]; return { code: l.id, title: l.id, desc: `${RACKS.find(r => r.id === l.rack).name} · Col ${pad(l.col)} · Nivel ${l.fila}`, sub: WH().name, lote: "", venc: "", cant: "", fecha: "" }; }
  if (t === "prod") { const s = STOCK[lab.sampleIdx % STOCK.length]; const p = P(s.codigo); return { code: p.codigo, title: p.codigo, desc: p.desc, sub: `${p.um} · ${p.sub}`, lote: s.lote, venc: s.venc ? fmtD(s.venc) : "", cant: `${s.qty} ${p.um}`, fecha: fmtD(s.ingreso) }; }
  if (t === "pallet") { const s = STOCK[(lab.sampleIdx + 7) % STOCK.length]; const p = P(s.codigo); return { code: `00779876543210${pad(lab.sampleIdx + 1, 3)}5`, title: `SSCC 0 0779876 54321000${pad(lab.sampleIdx + 1, 2)} `, desc: p.desc, sub: p.codigo, lote: s.lote, venc: "", cant: `${s.qty * 4} ${p.um} · 4 niveles`, fecha: fmtD(s.ingreso) }; }
  const pd = PEDIDOS[lab.sampleIdx % PEDIDOS.length]; return { code: pd.nro, title: pd.nro, desc: pd.cliente.nombre, sub: `${pd.cliente.ciudad}, ${pd.cliente.dpto}`, lote: "", venc: "", cant: `Bulto ${(lab.sampleIdx % pd.bultos) + 1} de ${pd.bultos}`, fecha: fmtD(pd.fecha) };
}
function qrPayload(s) {
  const f = lab.cfg.qrfmt;
  if (f === "plain") return s.code;
  if (f === "gs1") return lab.cfg.sample === "pallet" ? `(00)${s.code}` : `(01)0${s.code.slice(0, 12)}0${s.lote ? `(10)${s.lote}` : ""}${s.venc ? `(17)${s.venc.split("/").reverse().join("").slice(2)}` : ""}${s.cant ? `(37)${s.cant.split(" ")[0]}` : ""}`;
  if (f === "url") return `https://wms.plasticoscarmen.com/s/${encodeURIComponent(s.code)}`;
  return JSON.stringify({ t: lab.cfg.sample, id: s.code, lot: s.lote || undefined, q: s.cant ? +s.cant.split(" ")[0] : undefined, wh: state.wh });
}
function qrSVG(text, size = 120) {
  try { const q = qrcode(0, "M"); q.addData(text); q.make(); const n = q.getModuleCount(); const cs = size / n; let d = ""; for (let r = 0; r < n; r++) for (let c = 0; c < n; c++) if (q.isDark(r, c)) d += `M${(c * cs).toFixed(2)} ${(r * cs).toFixed(2)}h${cs.toFixed(2)}v${cs.toFixed(2)}h-${cs.toFixed(2)}z`; return `<svg viewBox="0 0 ${size} ${size}" width="${size}" height="${size}" shape-rendering="crispEdges"><rect width="${size}" height="${size}" fill="#fff"/><path d="${d}" fill="#000"/></svg>`; }
  catch (e) { return `<div style="width:${size}px;height:${size}px;background:#eee;display:grid;place-items:center;font-size:10px;color:#666">QR</div>`; }
}
function renderLabel() {
  const s = labelSample(); const c = lab.cfg; const [w, h] = c.size.split("x").map(Number); const scale = 3.2; const W = w * scale, H = h * scale; const fs = lab.font / 100;
  const qrPx = Math.round(Math.min(H * .72, W * .42) * [0.8, 0.9, 1, 1.1][lab.qrSize - 1]);
  const tall = h > w * 0.8;
  const html = `<div class="label-preview" id="labelBox" style="width:${W}px;height:${H}px;padding:${8 * scale / 3}px">${c.fields.franja ? `<div class="corner ${lab.cfg.sample === "despacho" ? "red" : ""}"></div>` : ""}
    <div style="display:flex;gap:${8 * scale / 3}px;height:100%;${tall ? "flex-direction:column;align-items:center;text-align:center" : ""}">
      ${c.fields.qr ? `<div style="flex:none;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px">${qrSVG(qrPayload(s), qrPx)}${c.qrfmt === "gs1" ? `<span class="lbl-small" style="font-size:${7 * fs}px">GS1 · ${c.sample === "pallet" ? "AI 00" : "AI 01 10 17"}</span>` : ""}</div>` : ""}
      <div style="flex:1;min-width:0;display:flex;flex-direction:column;justify-content:space-between;${tall ? "width:100%" : ""}">
        <div>${c.fields.logo ? `<div style="display:flex;align-items:center;gap:4px;margin-bottom:${3 * fs}px;${tall ? "justify-content:center" : ""}"><svg viewBox="0 0 600 430" width="${20 * fs}" height="${14 * fs}"><use href="#pc-logo"/></svg><span class="wordmark" style="font-size:${8 * fs}px;color:#E00010">PLÁSTICOS CARMEN</span><span style="font-size:${7 * fs}px;color:#666;margin-left:auto">${esc(state.wh)}</span></div>` : ""}
        ${c.fields.titulo ? `<div class="lbl-code" style="font-size:${(c.sample === "loc" ? 30 : 15) * fs * (tall ? 1.1 : 1)}px;line-height:1;word-break:break-all">${esc(s.title)}</div>` : ""}
        ${c.fields.desc ? `<div class="lbl-title" style="font-size:${10 * fs}px;margin-top:${3 * fs}px;line-height:1.15">${esc(s.desc)}</div><div class="lbl-small" style="font-size:${8 * fs}px">${esc(s.sub)}</div>` : ""}</div>
        <div style="display:grid;grid-template-columns:repeat(${[c.fields.lote && s.lote, c.fields.venc && s.venc, c.fields.cant && s.cant, c.fields.fecha && s.fecha].filter(Boolean).length || 1},auto);gap:${6 * fs}px;font-size:${8 * fs}px;margin-top:${4 * fs}px;${tall ? "justify-content:center" : ""}">${c.fields.lote && s.lote ? `<div><span class="lbl-small">LOTE</span><br><b class="lbl-code">${esc(s.lote)}</b></div>` : ""}${c.fields.venc && s.venc ? `<div><span class="lbl-small">VENCE</span><br><b>${esc(s.venc)}</b></div>` : ""}${c.fields.cant && s.cant ? `<div><span class="lbl-small">CANT.</span><br><b>${esc(s.cant)}</b></div>` : ""}${c.fields.fecha && s.fecha ? `<div><span class="lbl-small">FECHA</span><br><b>${esc(s.fecha)}</b></div>` : ""}</div>
        ${c.fields.code128 ? `<svg id="c128" style="width:100%;height:${Math.max(28, 40 * fs)}px;margin-top:${3 * fs}px"></svg>` : ""}
      </div></div></div>`;
  $("#labelStage").innerHTML = html;
  const svg = $("#c128"); if (svg) { try { JsBarcode(svg, s.code.replace(/[^\x20-\x7E]/g, ""), { format: "CODE128", displayValue: true, fontSize: Math.max(9, 11 * fs), height: Math.max(22, 30 * fs), margin: 0, width: 1.4, background: "#ffffff", lineColor: "#000000", font: "JetBrains Mono, monospace" }); } catch (e) { svg.outerHTML = `<div class="lbl-small">${esc(s.code)}</div>`; } }
  $("#qrPayload").textContent = qrPayload(s);
}
VIEWS.etiquetas = () => {
  const c = lab.cfg;
  const F = [["qr", "Código QR"], ["code128", "Code 128 (lectores 1D)"], ["titulo", "Código en grande"], ["desc", "Descripción y detalle"], ["logo", "Logo y almacén"], ["franja", "Franja de color"], ["lote", "Lote"], ["venc", "Vencimiento"], ["cant", "Cantidad"], ["fecha", "Fecha"]];
  return `${pageHead("Etiquetas QR", "Define una plantilla por tipo de etiqueta. Lo que ves es lo que imprime la Zebra.", `<button class="btn" data-toast="Plantilla guardada como nueva versión (v4).">${ic("history")} Versiones</button><button class="btn primary" data-toast="Plantilla «${c.lbl}» guardada. Los colectores la usan desde ahora.">${ic("check")} Guardar plantilla</button>`)}
  <div class="grid" style="grid-template-columns:300px minmax(0,1fr) 300px">
    <div class="stack"><div class="card"><div class="card-h"><h3>Plantilla</h3></div><div class="card-b stack" style="gap:8px">${Object.entries(LABEL_TEMPLATES).map(([k, t]) => `<button class="dataset ${lab.tpl === k ? "on" : ""}" data-tpl="${k}"><span class="ic">${ic(k === "ubicacion" ? "map" : k === "item" ? "box" : k === "pallet" ? "layers" : "truck")}</span><span><b>${t.lbl}</b><span>${t.desc} · ${t.size} mm</span></span></button>`).join("")}</div></div>
      <div class="card"><div class="card-h"><h3>Contenido del QR</h3></div><div class="card-b stack"><div class="seg" style="display:flex">${[["plain", "Texto"], ["gs1", "GS1"], ["json", "JSON"], ["url", "URL"]].map(([k, l]) => `<button style="flex:1" class="${c.qrfmt === k ? "on" : ""}" data-qrfmt="${k}">${l}</button>`).join("")}</div><pre class="mono small" id="qrPayload" style="margin:0;background:var(--surface-3);padding:10px;border-radius:6px;white-space:pre-wrap;word-break:break-all"></pre><p class="small muted" style="margin:0">${c.qrfmt === "gs1" ? "Identificadores de aplicación GS1: (01) GTIN, (10) lote, (17) vencimiento, (37) cantidad, (00) SSCC. Lo lee cualquier WMS." : c.qrfmt === "json" ? "Estructura propia. Un solo escaneo entrega tipo, id, lote y cantidad al colector." : c.qrfmt === "url" ? "Abre la ficha en el navegador de cualquier teléfono. Útil para supervisores." : "Solo el código. Compatible con el SGLA anterior y con lectores 1D vía Code 128."}</p></div></div></div>
    <div class="card"><div class="card-h"><h3>Vista previa · ${c.size.replace("x", " × ")} mm</h3><div class="row"><button class="btn sm icon" data-sample="-1">‹</button><span class="small muted">muestra ${lab.sampleIdx + 1}</span><button class="btn sm icon" data-sample="1">›</button></div></div><div class="card-b"><div class="label-stage" id="labelStage"></div>
      <div class="row between wrap" style="margin-top:14px"><div class="row wrap"><div class="field" style="margin:0"><label>Impresora</label><select class="select" style="min-height:36px;padding:6px 32px 6px 10px" id="labPrinter"><option>Zebra ZD421 · Almacén BOL2</option><option>Zebra ZT411 · Muelle ingreso</option><option>Zebra ZQ630 (Bluetooth) · Colector TC52-004</option><option>PDF · hoja A4 con 8 etiquetas</option></select></div><div class="field" style="margin:0"><label>Copias</label><input class="input num" type="number" value="1" style="width:80px;min-height:36px"></div></div><div class="row"><button class="btn" data-toast="Etiqueta de prueba enviada a ${esc(lab.printer)}.">${ic("print")} Imprimir prueba</button><button class="btn primary" data-act="print-batch">${ic("print")} Imprimir lote…</button></div></div></div></div>
    <div class="stack"><div class="card"><div class="card-h"><h3>Campos</h3></div><div class="card-b" style="padding:6px 16px">${F.map(([k, l]) => `<div class="opt-row"><span>${l}</span><button class="tog ${c.fields[k] ? "on" : ""}" data-field="${k}" aria-label="${l}"></button></div>`).join("")}</div></div>
      <div class="card"><div class="card-h"><h3>Formato</h3></div><div class="card-b stack"><div class="field"><label>Tamaño</label><select class="select" id="labSize">${["50x30", "100x50", "100x100", "100x150"].map(s => `<option ${c.size === s ? "selected" : ""}>${s}</option>`).join("")}</select></div><div class="field"><label>Tamaño del QR</label><div class="seg" style="display:flex">${[1, 2, 3, 4].map(n => `<button style="flex:1" class="${lab.qrSize === n ? "on" : ""}" data-qrsize="${n}">${["S", "M", "L", "XL"][n - 1]}</button>`).join("")}</div></div><div class="field"><label>Tamaño de texto · ${lab.font} %</label><input type="range" min="70" max="140" step="5" value="${lab.font}" id="labFont"></div><p class="small muted" style="margin:0">Corrección de error <b>M</b>; módulo mínimo 0,5 mm a 203 dpi. La etiqueta de ubicación se lee desde el montacargas.</p></div></div></div>
  </div>`;
};
VIEWS.etiquetas.after = () => { renderLabel(); const f = $("#labFont"); if (f) f.oninput = e => { lab.font = +e.target.value; e.target.previousElementSibling.textContent = `Tamaño de texto · ${lab.font} %`; renderLabel(); }; const s = $("#labSize"); if (s) s.onchange = e => { lab.cfg.size = e.target.value; render(); }; const p = $("#labPrinter"); if (p) p.onchange = e => lab.printer = e.target.value; };
function modalPrintBatch() { openModal("Imprimir lote de etiquetas", `<div class="grid g-2"><div class="field"><label>Origen</label><select class="select"><option>Rack E1 · 40 ubicaciones</option><option>Orden de ingreso ING-260112 · 5 líneas</option><option>Pedido PED-07810 · 12 bultos</option><option>Selección de productos…</option></select></div><div class="field"><label>Impresora</label><select class="select"><option>${esc(lab.printer)}</option></select></div></div><div class="field"><label>Rango</label><input class="input" value="Todas"></div><div class="row"><button class="tog on"></button><span class="small">Registrar impresión en el kardex de la ubicación / lote</span></div>`, `<button class="btn" data-close>Cancelar</button><button class="btn primary" data-toast="40 etiquetas enviadas a la cola de impresión.">Imprimir 40 etiquetas</button>`); }

/* ===================== USUARIOS Y ROLES ===================== */
const ROLES = [
  { id: "Administrador", desc: "Todo el sistema, todos los almacenes", n: USERS_DESK.filter(u => u.rol === "Administrador").length, perms: [1, 1, 1, 1, 1, 1, 1, 1] },
  { id: "Encargado de almacén", desc: "Opera y aprueba ajustes y conteos de su almacén", n: USERS_DESK.filter(u => u.rol.startsWith("Encargado")).length + USERS_COL.filter(u => u.rol === "Encargado").length, perms: [1, 1, 1, 1, 1, 1, 0, 0] },
  { id: "Operador", desc: "Ejecuta tareas asignadas en el colector", n: USERS_DESK.filter(u => u.rol === "Operador").length + USERS_COL.filter(u => u.rol === "Operador").length, perms: [1, 1, 1, 0, 0, 0, 0, 0] },
  { id: "Supervisor (lectura)", desc: "Tableros y reportes, sin operar", n: 0, perms: [0, 0, 0, 0, 0, 1, 0, 0] },
];
const PERMS = ["Recibir", "Picking / despacho", "Reubicar", "Ajustar stock", "Aprobar diferencias", "Reportes", "Maestros y cargas", "Usuarios y parámetros"];
VIEWS.usuarios = () => {
  const sub = state.sub.usuarios || "escritorio";
  let body;
  if (sub === "escritorio") body = `${table([{ h: "Usuario", f: u => `<div class="row"><span class="mini-av">${initials(u.nombre)}</span><div><b>${esc(u.nombre)}</b><div class="small muted mono">${u.user}</div></div></div>` }, { h: "Rol", f: u => `<span class="tag ${u.rol === "Administrador" ? "a" : ""}">${u.rol}</span>` }, { h: "Almacenes", f: u => u.wh.map(w => `<span class="tag">${w}</span>`).join(" ") }, { h: "Último acceso", f: u => u.ult }, { h: "Estado", f: u => pillFor(u.estado) }, { h: "", f: u => `<div class="row"><button class="btn sm ghost icon" title="Editar">${ic("edit")}</button><button class="btn sm ghost icon" title="Restablecer contraseña" data-toast="Enlace de restablecimiento enviado a ${u.user}@plasticoscarmen.com" data-kind="info">${ic("lock")}</button></div>` }], USERS_DESK)}${foot(USERS_DESK.length, "usuarios de escritorio")}`;
  else if (sub === "colector") body = `${table([{ h: "Usuario", f: u => `<div class="row"><span class="mini-av">${initials(u.nombre)}</span><div><b>${esc(u.nombre)}</b><div class="small muted mono">${u.user}</div></div></div>` }, { h: "Rol", f: u => `<span class="tag">${u.rol}</span>` }, { h: "Almacén", f: u => `<span class="tag">${u.wh}</span>` }, { h: "Equipo asignado", f: u => u.device ? `<span class="mono small">${u.device}</span> <span class="pill ${u.online ? "ok" : "bad"} plain" style="padding:0 6px">${u.online ? "en línea" : "sin conexión"}</span>` : `<span class="muted">—</span>` }, { h: "Acceso", f: u => `PIN de 4 dígitos + credencial QR` }, { h: "Estado", f: u => pillFor(u.estado) }, { h: "", f: u => `<div class="row"><button class="btn sm ghost icon" title="Editar">${ic("edit")}</button><button class="btn sm ghost icon" title="Imprimir credencial QR" data-toast="Credencial QR de ${u.user} enviada a la impresora.">${ic("qr")}</button></div>` }], USERS_COL)}${foot(USERS_COL.length, "usuarios de colector")}`;
  else body = `<div class="tbl-wrap"><table class="tbl"><thead><tr><th>Permiso</th>${ROLES.map(r => `<th class="c">${r.id}<div class="small muted" style="text-transform:none;letter-spacing:0;font-weight:400">${r.n} usuarios</div></th>`).join("")}</tr></thead><tbody>${PERMS.map((p, i) => `<tr><td><b>${p}</b></td>${ROLES.map(r => `<td class="c"><button class="tog ${r.perms[i] ? "on" : ""}" aria-label="${p} · ${r.id}"></button></td>`).join("")}</tr>`).join("")}</tbody></table></div><div class="card-b small muted">${ic("lock")} Los permisos se aplican por almacén: un Encargado de BOL2 no ve saldos de TAN. El backend valida <span class="mono">user_warehouse</span> en cada petición.</div>`;
  return `${pageHead("Usuarios y roles", "Una sola administración para escritorio y colectores. Los roles se asignan por almacén.", `<button class="btn" data-go="importar">${ic("upload")} Importar usuarios</button><button class="btn primary" data-act="new-user">${ic("plus")} Nuevo usuario</button>`)}
  <div class="grid g-4" style="margin-bottom:16px">${ROLES.map(r => `<div class="card kpi"><span class="lbl">${r.id}</span><span class="val">${r.n}</span><span class="delta">${r.desc}</span></div>`).join("")}</div>
  <div class="card"><div class="tabs">${[["escritorio", `Escritorio (${USERS_DESK.length})`], ["colector", `Colector (${USERS_COL.length})`], ["roles", "Roles y permisos"]].map(([k, l]) => `<button class="${sub === k ? "on" : ""}" data-sub-usuarios="${k}">${l}</button>`).join("")}</div>${body}</div>`;
};
function modalUser() { openModal("Nuevo usuario", `<div class="seg" style="margin-bottom:16px"><button class="on">Escritorio</button><button>Colector</button><button>Ambos</button></div><div class="grid g-2"><div class="field"><label>Nombre completo</label><input class="input" placeholder="Nombre y apellidos"></div><div class="field"><label>Usuario</label><input class="input mono" placeholder="inicial + apellido"></div><div class="field"><label>Correo</label><input class="input" placeholder="@plasticoscarmen.com"></div><div class="field"><label>Rol</label><select class="select">${ROLES.map(r => `<option>${r.id}</option>`).join("")}</select></div><div class="field"><label>Almacenes</label><div class="chips">${WAREHOUSES.filter(w => w.type !== "TR").map((w, i) => `<button class="chip ${i === 0 ? "on" : ""}" type="button">${w.id}</button>`).join("")}</div></div><div class="field"><label>Equipo asignado</label><select class="select"><option>— sin asignar —</option>${DEVICES.map(d => `<option>${d.id}</option>`).join("")}</select></div></div><div class="row" style="margin-top:8px"><button class="tog on"></button><span class="small">Generar credencial QR y PIN para el colector</span></div>`, `<button class="btn" data-close>Cancelar</button><button class="btn primary" data-toast="Usuario creado. Credencial QR lista para imprimir.">Crear usuario</button>`); }

/* ===================== COLECTORES (DISPOSITIVOS) ===================== */
VIEWS.dispositivos = () => `${pageHead("Colectores", "Equipos Zebra registrados, versión de la app, batería y cola de movimientos sin enviar.", `<button class="btn" data-toast="Actualización 0.2.2 programada para el próximo inicio de sesión de cada equipo." data-kind="info">${ic("cloud")} Publicar versión 0.2.2</button><button class="btn primary" data-act="new-device">${ic("plus")} Registrar equipo</button>`)}
  <div class="grid g-4" style="margin-bottom:16px"><div class="card kpi"><span class="lbl">En línea</span><span class="val">${DEVICES.filter(d => d.online).length}<small>de ${DEVICES.length}</small></span><span class="delta">${DEVICES.filter(d => !d.online).length} sin conexión</span></div><div class="card kpi"><span class="lbl">Movimientos en cola</span><span class="val">${DEVICES.reduce((a, d) => a + d.cola, 0)}</span><span class="delta ${DEVICES.reduce((a, d) => a + d.cola, 0) > 10 ? "down" : ""}">se envían al recuperar WiFi</span></div><div class="card kpi"><span class="lbl">Batería baja</span><span class="val">${DEVICES.filter(d => d.bat < 20).length}</span><span class="delta">menos de 20 %</span></div><div class="card kpi"><span class="lbl">Versión desactualizada</span><span class="val">${DEVICES.filter(d => d.app !== "0.2.1").length}</span><span class="delta">objetivo 0.2.1</span></div></div>
  <div class="grid g-2-1"><div class="card"><div class="toolbar"><div class="chips">${["Todos", "BOL2", "TAN", "MP", "LPZ"].map((t, i) => `<button class="chip ${i === 0 ? "on" : ""}">${t}</button>`).join("")}</div><div style="flex:1"></div><span class="muted small">Modo de escaneo: <b>DataWedge · keystroke</b></span></div>
    ${table([{ h: "Equipo", f: d => `<div class="device" style="border:none;padding:0"><span class="ph ${d.online ? "" : "off"}"></span><div><b class="mono">${d.id}</b><span>${d.modelo} · ${d.so}</span></div></div>` }, { h: "Usuario", f: d => userName(d.user) }, { h: "Almacén", f: d => `<span class="tag">${d.wh}</span>` }, { h: "App", f: d => `<span class="mono small ${d.app !== "0.2.1" ? "" : ""}" style="color:${d.app !== "0.2.1" ? "var(--warn)" : "inherit"}">${d.app}</span>` }, { h: "Batería", f: d => `<div class="row" style="width:120px"><div class="bar ${d.bat < 20 ? "bad" : d.bat < 50 ? "warn" : "ok"}" style="flex:1"><i style="width:${d.bat}%"></i></div><span class="small num" style="width:34px;text-align:right">${d.bat}%</span></div>` }, { h: "Cola", a: "r", f: d => d.cola ? `<span class="pill ${d.cola > 5 ? "bad" : "warn"} plain num">${d.cola}</span>` : `<span class="muted">0</span>` }, { h: "Estado", f: d => `<span class="pill ${d.online ? "ok" : "bad"}">${d.online ? "En línea" : "Sin conexión"}</span>` }, { h: "", f: d => `<div class="row"><button class="btn sm ghost icon" title="Cerrar sesión remota" data-toast="Sesión cerrada en ${d.id}." data-kind="info">${ic("logout")}</button><button class="btn sm ghost icon" title="Editar">${ic("edit")}</button></div>` }], DEVICES)}</div>
    <div class="stack"><div class="card"><div class="card-h"><h3>Perfil DataWedge</h3><span class="pill ok">Sincronizado</span></div><div class="card-b" style="padding:6px 16px">${[["Simbologías", "Code 128, QR, GS1-128, DataMatrix"], ["Salida", "Keystroke + sufijo Enter"], ["Gatillo", "Botón físico · escaneo continuo apagado"], ["Confirmación", "Beep + vibración 80 ms"], ["Pantalla", "Tema oscuro automático 18:00–06:00"], ["Sesión", "Cierre por inactividad 30 min"]].map(([k, v]) => `<div class="opt-row"><span class="muted">${k}</span><b class="small" style="text-align:right">${v}</b></div>`).join("")}</div></div>
    <div class="card"><div class="card-h"><h3>Cobertura WiFi real</h3><span class="muted small">% mov. en cola</span></div><div class="card-b">${barChart(RACKS.filter(r => r.id[0] === "E").map(r => ({ l: r.id, v: r.id === "E5" ? 14 : r.id === "E6" ? 9 : ri(1, 4) })), { w: 360, h: 150, hi: "E5", unit: "%" })}<p class="small muted" style="margin:8px 0 0">Los movimientos guardados sin red y enviados después se agrupan por rack: E5 y E6 concentran la zona muerta.</p></div></div></div></div>`;
function modalDevice() { openModal("Registrar colector", `<div class="grid g-2"><div class="field"><label>Identificador</label><input class="input mono" value="TC21-017"></div><div class="field"><label>Modelo</label><select class="select"><option>Zebra TC21</option><option>Zebra TC52</option><option>Zebra MC3300</option><option>Otro Android</option></select></div><div class="field"><label>Almacén</label><select class="select">${WAREHOUSES.filter(w => w.type !== "TR").map(w => `<option>${w.name}</option>`).join("")}</select></div><div class="field"><label>Usuario habitual</label><select class="select"><option>— libre —</option>${USERS_COL.map(u => `<option>${u.nombre}</option>`).join("")}</select></div></div><div class="card" style="box-shadow:none;margin-top:8px"><div class="card-b row"><div style="flex:none">${qrSVG("https://wms.plasticoscarmen.com/enrolar/TC21-017?k=8f3a", 96)}</div><div class="small"><b>Enrolar con un escaneo.</b><br><span class="muted">Abre la app en el equipo y escanea este código: instala la PWA, aplica el perfil DataWedge y descarga los maestros del almacén.</span></div></div></div>`, `<button class="btn" data-close>Cancelar</button><button class="btn primary" data-toast="Equipo TC21-017 registrado. Pendiente de enrolar.">Registrar</button>`); }

/* ===================== PARÁMETROS ===================== */
VIEWS.parametros = () => {
  const sub = state.sub.parametros || "reglas";
  const sect = {
    reglas: [["Asignación de stock en picking", "FIFO por fecha de ingreso; FEFO si el ítem tiene vida útil", "select", ["FIFO", "FEFO cuando aplica", "LIFO", "Manual"]], ["Reserva al liberar ola", "El stock queda apartado hasta confirmar o anular", "tog", true], ["Permitir desviarse de la ubicación sugerida", "Se registra la desviación para diagnóstico de layout", "tog", true], ["Recepción ciega para compras", "El operador no ve la cantidad esperada", "tog", true], ["Recepción ciega para producción", "", "tog", false], ["Tolerancia de diferencia sin aprobación", "unidades", "input", "0"], ["Días de alerta antes de vencimiento", "", "input", "30"], ["Bloquear picking de lote vencido", "", "tog", true]],
    putaway: [["Prioridad 1", "", "select", ["Ubicación fija del ítem", "Consolidar con mismo ítem y lote", "Cercanía a picking (clase A)", "Capacidad disponible"]], ["Prioridad 2", "", "select", ["Consolidar con mismo ítem y lote", "Ubicación fija del ítem", "Cercanía a picking (clase A)", "Capacidad disponible"]], ["Prioridad 3", "", "select", ["Cercanía a picking (clase A)", "Consolidar con mismo ítem y lote", "Ubicación fija del ítem", "Capacidad disponible"]], ["No mezclar MP con PT en una ubicación", "", "tog", true], ["No mezclar lotes en ubicaciones de picking", "", "tog", true], ["Clase C al fondo de nave (E6)", "", "tog", true]],
    codigos: [["Formato de ubicación", "", "input", "{RACK}-C{COL:2}-N{NIVEL}"], ["Formato de lote", "", "input", "L{AA}{SEM:2}{DIA}-{TURNO}"], ["Prefijo SSCC (GS1 Bolivia)", "", "input", "0 0779876"], ["Contenido QR por defecto", "", "select", ["GS1", "Texto plano", "JSON", "URL"]], ["Reutilizar códigos de ubicación eliminada", "Nunca: rompe la trazabilidad", "tog", false], ["Longitud del SKU", "", "input", "12"]],
    integracion: [["URL de WorkCorp", "", "input", "https://erp.plasticoscarmen.com/api/v2"], ["Ítems y clientes", "", "select", ["Cada hora", "Cada 15 min", "Webhook", "Manual"]], ["Pedidos y órdenes", "", "select", ["Cada 2 min", "Cada 5 min", "Webhook"]], ["Movimientos confirmados → ERP", "", "select", ["Inmediato con reintento", "Cada 5 min"]], ["Conciliación diaria de saldos", "compara SUM(stock) por ítem contra el ERP", "input", "23:30"], ["SIMEC · lotes de extrusión", "lee OP y turno para la recepción de producción", "tog", true]],
    respaldo: [["Respaldo automático de la base de datos", "MariaDB · completo + binlog", "select", ["Diario 02:00", "Cada 12 h", "Semanal"]], ["Retención", "días", "input", "30"], ["Destino", "", "select", ["SharePoint · SISTEMASPC/Respaldos", "Servidor local + nube", "Solo servidor local"]], ["Último respaldo correcto", "", "input", "22/09/2026 02:00 · 1,8 GB · verificado"], ["Exportar maestros con cada respaldo (CSV)", "", "tog", true], ["Probar restauración mensual en entorno de pruebas", "", "tog", true]],
    empresa: [["Razón social", "", "input", "Plásticos Carmen S.R.L."], ["Zona horaria", "", "select", ["America/La_Paz (UTC−4)"]], ["Idioma", "", "select", ["Español (Bolivia)"]], ["Separador decimal", "", "select", ["Coma (1.234,50)", "Punto (1,234.50)"]], ["Inicio de jornada / turnos", "", "input", "T1 06:00 · T2 14:00 · T3 22:00"], ["Cierre de sesión por inactividad (escritorio)", "minutos", "input", "60"]],
  };
  const tabs = [["reglas", "Reglas de stock"], ["putaway", "Ubicación sugerida"], ["codigos", "Códigos y formatos"], ["integracion", "Integración ERP"], ["respaldo", "Respaldos"], ["empresa", "Empresa"]];
  return `${pageHead("Parámetros", "Reglas del negocio que el colector aplica sin preguntar. Cambiarlas requiere rol Administrador.", `<button class="btn">${ic("history")} Historial de cambios</button><button class="btn primary" data-toast="Parámetros guardados. Vigentes para nuevas tareas.">${ic("check")} Guardar cambios</button>`)}
  <div class="grid g-1-2" style="grid-template-columns:240px minmax(0,1fr)"><div class="card"><div class="card-b" style="padding:8px"><ul class="tree">${tabs.map(([k, l]) => `<li><div class="node ${sub === k ? "on" : ""}" data-sub-parametros="${k}">${ic(k === "reglas" ? "sliders" : k === "putaway" ? "map" : k === "codigos" ? "barcode" : k === "integracion" ? "link" : k === "respaldo" ? "history" : "layers")}<span>${l}</span></div></li>`).join("")}</ul></div></div>
  <div class="card"><div class="card-h"><h3>${tabs.find(t => t[0] === sub)[1]}</h3>${sub === "respaldo" ? `<button class="btn sm" data-toast="Respaldo manual iniciado. Te avisamos al terminar." data-kind="info">${ic("download")} Respaldar ahora</button>` : ""}<span class="muted small">Almacén: <b>${WH().id}</b> · hereda de global</span></div><div class="card-b" style="padding:6px 16px">${sect[sub].map(([k, d, t, v]) => `<div class="opt-row"><div><b>${k}</b>${d ? `<div class="small muted">${d}</div>` : ""}</div>${t === "tog" ? `<button class="tog ${v ? "on" : ""}" aria-label="${k}"></button>` : t === "input" ? `<input class="input ${/URL|Formato|Prefijo/.test(k) ? "mono" : ""}" value="${esc(v)}" style="width:min(320px,45%);min-height:36px;padding:6px 10px">` : `<select class="select" style="width:min(320px,45%);min-height:36px;padding:6px 32px 6px 10px">${v.map(o => `<option>${o}</option>`).join("")}</select>`}</div>`).join("")}</div></div></div>`;
};

/* ==== 08-collector.html ==== */
/* ===================== MODO COLECTOR ===================== */
const col = { screen: "home", task: null, step: 0, line: 0, scan: null, qty: 0, queue: 3, online: true, from: null, item: null, log: [] };
const beep = (ok = true) => { try { const A = window.AudioContext || window.webkitAudioContext; if (!A) return; const ctx = beep.ctx || (beep.ctx = new A()); const o = ctx.createOscillator(); const g = ctx.createGain(); o.frequency.value = ok ? 1320 : 220; g.gain.value = .06; o.connect(g); g.connect(ctx.destination); o.start(); o.stop(ctx.currentTime + (ok ? .09 : .25)); if (navigator.vibrate) navigator.vibrate(ok ? 60 : [80, 40, 80]); } catch (e) {} };

const cTop = (title, sub, back = true) => `<div class="c-top">${back ? `<button class="btn icon" data-c="home">${ic("chev")}</button>` : `<svg viewBox="0 0 600 430" width="40" height="29"><use href="#pc-logo-white"/></svg>`}<div style="flex:1;min-width:0"><b>${title}</b><small>${sub}</small></div><span class="pill ${col.online ? "ok" : "warn"} plain" style="background:rgba(255,255,255,.12);color:#fff;font-size:11px">${col.online ? ic("wifi") : ic("cloud")} ${col.queue ? col.queue + " en cola" : "al día"}</span></div>`;
const cNav = (on) => `<div class="c-nav">${[["home", "home", "Inicio"], ["tareas", "list", "Tareas"], ["consulta", "search", "Consultar"], ["mas", "grid", "Más"]].map(([k, i, l]) => `<button class="${on === k ? "on" : ""}" data-c="${k}">${ic(i)}<span>${l}</span></button>`).join("")}</div>`;
const scanBox = (label, hint, st = "", val = "") => `<div class="scanbox ${st}" id="cScan">${ic("scan")}<b>${label}</b><span>${hint}</span>${val ? `<span class="mono">${val}</span>` : ""}</div>`;

const myTasks = () => PEDIDOS.filter(p => p.estado === "Preparación").slice(0, 3);

function cRender() {
  const s = col.screen; let h = "";
  if (s === "home") {
    const t = myTasks();
    h = `${cTop("Carmen WMS", `${WH().name} · ${CW.user.nombre}`, false)}<div class="c-body stack">
      <div class="c-stat"><div><b>${t.length}</b><span>picking</span></div><div><b>${INGRESOS.filter(i => i.estado === "Habilitado").length}</b><span>por recibir</span></div><div><b>${CONTEOS.filter(c => c.estado !== "Finalizado").length}</b><span>conteos</span></div></div>
      ${scanBox("Escanea cualquier código", "ubicación, ítem, pedido o pallet: la app sabe qué hacer")}
      <div class="tiles">${[["picking", "out", "Picking", `${t.length} pedidos asignados`], ["recepcion", "in", "Recepción", "muelle 1 · 2 órdenes"], ["ubicar", "map", "Ubicar", "del muelle al rack"], ["reubicar", "move", "Reubicar", "mover stock"], ["conteo", "count", "Conteo", "cíclico A · 18 pendientes"], ["consulta", "search", "Consultar", "stock por ubicación"]].map(([k, i, l, d]) => `<button class="tile" data-c="${k}"><span class="ic">${ic(i)}</span><b>${l}</b><span>${d}</span></button>`).join("")}</div></div>${cNav("home")}`;
  } else if (s === "tareas" || s === "picking") {
    h = `${cTop("Mis tareas", "ordenadas por prioridad y hora de despacho")}<div class="c-body stack">${myTasks().map((p, i) => `<button class="cline" data-c="pick" data-ped="${p.nro}" style="width:100%;text-align:left;border-left:3px solid ${p.prioridad === "Urgente" ? "var(--red)" : "var(--navy)"}"><div><b>${p.nro} · ${esc(p.cliente.nombre)}</b><span>${p.lines.length} líneas · ${p.ola} · despacho 14:30</span></div><span class="q">${p.lines.filter(l => l.pick >= l.qty).length}/${p.lines.length}</span></button>`).join("")}<div class="cline"><div><b>Conteo 121 · Clase A</b><span>18 ubicaciones pendientes · E1, E2</span></div><button class="btn sm" data-c="conteo">Ir</button></div></div>${cNav("tareas")}`;
  } else if (s === "pick") {
    const p = col.task; const L = p.lines.map(l => ({ ...l, loc: (STOCK.find(x => x.codigo === l.codigo) || { loc: "E1-C01-N1", lote: "—", rack: "E1", qty: 0 }) })).sort((a, b) => a.loc.loc.localeCompare(b.loc.loc)); const l = L[col.line]; const done = col.line >= L.length;
    if (done) h = `${cTop(p.nro, "picking completo")}<div class="c-body stack"><div class="scanbox ok">${ic("check")}<b>Pedido completo</b><span>${L.length} líneas · ${fmt(L.reduce((a, x) => a + x.qty, 0))} unidades en ${ri(6, 14)} min</span></div><div class="cline"><div><b>Llevar a validación</b><span>Mesa 2 · escanea la mesa al llegar</span></div>${ic("arrow")}</div><button class="btn primary bigbtn" data-c="home">Siguiente tarea</button></div>${cNav("tareas")}`;
    else {
      const steps = ["Ir a la ubicación", "Escanear ítem", "Confirmar cantidad"];
      h = `${cTop(p.nro, `línea ${col.line + 1} de ${L.length} · ${esc(p.cliente.nombre)}`)}<div class="c-body stack">
        <div class="bar"><i style="width:${Math.round(col.line / L.length * 100)}%"></i></div>
        <div class="cline" style="border-left:4px solid var(--navy)"><div><span style="font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:var(--ink-3)">${steps[col.step]}</span><b class="mono" style="font-size:26px">${col.step === 0 ? l.loc.loc : l.codigo}</b><span>${col.step === 0 ? `${RACKS.find(r => r.id === l.loc.rack).name} · columna ${l.loc.loc.split("-")[1]} · nivel ${l.loc.loc.split("-")[2].slice(1)}` : esc(P(l.codigo).desc)}</span></div></div>
        ${col.step < 2 ? scanBox(col.step === 0 ? "Escanea la ubicación" : "Escanea el ítem", col.step === 0 ? "el gatillo confirma que estás en el lugar correcto" : `lote ${l.loc.lote} · FIFO`, col.scan ? col.scan.st : "", col.scan?.val || "") : `<div class="cline"><div><b>Cantidad a tomar</b><span>${fmt(l.qty)} ${P(l.codigo).um} · hay ${fmt(l.loc.qty || 40)} en la ubicación</span></div></div><div class="qty"><button class="btn" data-q="-1">−</button><input class="input num" id="cQty" type="number" value="${col.qty || l.qty}" inputmode="numeric"><button class="btn" data-q="1">+</button></div><button class="btn primary bigbtn" data-c="confirm">Confirmar ${col.qty || l.qty} ${P(l.codigo).um}</button><button class="btn bigbtn" data-c="short">Faltante · reportar</button>`}
        <div class="row between small muted"><span>Siguiente: <b class="mono">${L[col.line + 1]?.loc.loc || "validación"}</b></span><button class="btn sm ghost" data-c="skip">Saltar línea</button></div></div>${cNav("tareas")}`;
    }
  } else if (s === "reubicar") {
    h = `${cTop("Reubicar", col.step === 0 ? "1 · origen" : col.step === 1 ? "2 · ítem y cantidad" : "3 · destino")}<div class="c-body stack">
      <div class="steps" style="font-size:12px"><div class="st ${col.step > 0 ? "done" : "cur"}">Origen</div><div class="st ${col.step > 1 ? "done" : col.step === 1 ? "cur" : ""}">Ítem</div><div class="st ${col.step === 2 ? "cur" : ""}">Destino</div></div>
      ${col.from ? `<div class="cline"><div><span class="small muted">Origen</span><b class="mono">${col.from}</b></div>${col.item ? `<div style="text-align:right"><span class="small muted">Ítem</span><b class="mono small">${col.item.codigo}</b><div class="small muted">${col.qty} un.</div></div>` : ""}</div>` : ""}
      ${col.step === 0 ? scanBox("Escanea la ubicación de origen", "o el pallet completo", col.scan?.st || "", col.scan?.val || "") : col.step === 1 ? `${scanBox("Escanea el ítem a mover", "solo se admiten ítems con saldo en el origen", col.scan?.st || "", col.scan?.val || "")}${col.item ? `<div class="qty"><button class="btn" data-q="-1">−</button><input class="input num" id="cQty" type="number" value="${col.qty}"><button class="btn" data-q="1">+</button></div><button class="btn primary bigbtn" data-c="mv-next">Continuar al destino</button>` : ""}` : `${scanBox("Escanea la ubicación de destino", "sugerida: E2-C05-N2 · consolidar mismo lote", col.scan?.st || "", col.scan?.val || "")}<div class="cline"><div><b>Sugerencia</b><span>E2-C05-N2 · ya hay ${col.item?.codigo} lote igual</span></div><button class="btn sm" data-c="mv-accept">Usar</button></div>`}
      </div>${cNav("home")}`;
  } else if (s === "recepcion") {
    const os = INGRESOS.filter(i => ["Habilitado", "En recepción"].includes(i.estado));
    h = `${cTop("Recepción", "muelle ING-C01 · órdenes habilitadas")}<div class="c-body stack">${scanBox("Escanea la orden o el lote", "el lote de extrusión trae OP y turno")}${os.map(o => `<button class="cline" data-c="recv" data-ing="${o.nro}" style="width:100%;text-align:left"><div><b>${o.nro} · ${o.tipo}</b><span>${esc(o.origen)} · ${o.lines.length} líneas</span></div>${pillFor(o.estado)}</button>`).join("")}</div>${cNav("home")}`;
  } else if (s === "recv") {
    const o = col.task;
    h = `${cTop(o.nro, `${o.tipo} · ${esc(o.origen)}`)}<div class="c-body stack">${scanBox("Escanea el bulto", "GS1-128: lee SKU, lote y cantidad de una vez", col.scan?.st || "", col.scan?.val || "")}${o.lines.map(l => `<div class="cline" style="border-left:3px solid ${l.rec >= l.qty ? "var(--ok)" : l.rec ? "var(--warn)" : "var(--line)"}"><div><b class="mono small">${l.codigo}</b><span>${esc(P(l.codigo).desc)}<br>lote ${l.lote} · ${l.turno}</span></div><span class="q" style="color:${l.rec >= l.qty ? "var(--ok)" : "inherit"}">${l.rec}<span class="muted" style="font-size:13px">/${l.qty}</span></span></div>`).join("")}<button class="btn primary bigbtn" data-c="recv-close">Cerrar recepción</button></div>${cNav("home")}`;
  } else if (s === "conteo") {
    h = `${cTop("Conteo 121 · Clase A", "ciego · 18 ubicaciones pendientes")}<div class="c-body stack">${scanBox(col.from ? "Ubicación " + col.from : "Escanea la ubicación", col.from ? "ahora escanea cada ítem y cuenta" : "cuenta lo que ves, sin cantidad esperada", col.scan?.st || "", col.scan?.val || "")}${col.from ? `${STOCK.filter(x => x.loc === col.from).map(x => `<div class="cline"><div><b class="mono small">${x.codigo}</b><span>${esc(P(x.codigo).desc)} · lote ${x.lote}</span></div><input class="input num" type="number" placeholder="0" style="width:84px;text-align:center;font-size:20px;font-weight:700"></div>`).join("") || `<div class="cline"><div><b>Ubicación vacía</b><span>confirma si no hay nada</span></div></div>`}<button class="btn primary bigbtn" data-c="count-ok">Confirmar ubicación</button>` : `<div class="cline"><div><b>Siguiente sugerida</b><span class="mono">E1-C03-N2</span></div><span class="small muted">17 más</span></div>`}</div>${cNav("tareas")}`;
  } else if (s === "consulta") {
    const r = col.scan?.res;
    h = `${cTop("Consultar", "stock, ubicación, pedido o lote")}<div class="c-body stack">${scanBox("Escanea o escribe un código", "", col.scan?.st || "", col.scan?.val || "")}<input class="input mono" id="cManual" placeholder="Escribir código y Enter" style="min-height:52px;font-size:17px">${r ? r : ""}</div>${cNav("consulta")}`;
  } else if (s === "ubicar") {
    h = `${cTop("Ubicar", "del muelle al rack")}<div class="c-body stack">${scanBox("Escanea el pallet o bulto en el muelle", "la app propone dónde dejarlo", col.scan?.st || "", col.scan?.val || "")}${col.item ? `<div class="cline" style="border-left:4px solid var(--navy)"><div><span class="small muted">Sugerido · consolidación clase ${P(col.item.codigo).rot}</span><b class="mono" style="font-size:26px">E2-C05-N2</b><span>${esc(P(col.item.codigo).desc)}</span></div></div>${scanBox("Escanea la ubicación al dejarlo", "puedes desviarte; quedará registrado")}` : ""}</div>${cNav("home")}`;
  } else {
    h = `${cTop("Más", `${CW.user.nombre} · ${CW.device}`)}<div class="c-body stack">${[["Cola de sincronización", `${col.queue} movimientos`, "cloud"], ["Imprimir etiqueta (ZQ630)", "Bluetooth", "print"], ["Cambiar de almacén", WH().name, "layers"], ["Tema", "automático", "moon"], ["Cerrar sesión", "", "logout"]].map(([l, d, i]) => `<div class="cline"><div class="row">${ic(i)}<div><b>${l}</b><span>${d}</span></div></div>${ic("chev")}</div>`).join("")}<div class="small muted" style="text-align:center">Carmen WMS 0.2.1 · DataWedge keystroke · ${col.online ? "en línea" : "sin conexión"}</div></div>${cNav("mas")}`;
  }
  $("#colScreen").innerHTML = `<div class="capp">${h}</div>`;
  const mi = $("#cManual"); if (mi) { mi.focus(); mi.onkeydown = e => { if (e.key === "Enter" && mi.value.trim()) { simScan(mi.value.trim()); } }; }
  const q = $("#cQty"); if (q) q.oninput = e => col.qty = +e.target.value;
}
function simScan(code) {
  const s = col.screen; code = code.trim();
  const isLoc = LOCATIONS.some(l => l.id === code), prod = P(code), ped = PEDIDOS.find(p => p.nro === code), ing = INGRESOS.find(i => i.nro === code);
  const ok = (val, extra) => { col.scan = { st: "ok", val, ...extra }; beep(true); };
  const bad = (val) => { col.scan = { st: "err", val }; beep(false); };
  if (s === "home") { if (isLoc) { col.screen = "consulta"; ok(code); col.scan.res = consultaLoc(code); } else if (prod) { col.screen = "consulta"; ok(code); col.scan.res = consultaProd(code); } else if (ped) { col.task = ped; col.line = 0; col.step = 0; col.qty = 0; col.scan = null; col.screen = "pick"; beep(true); } else if (ing) { col.task = ing; col.screen = "recv"; col.scan = null; beep(true); } else bad(code); }
  else if (s === "pick") { const p = col.task; const L = p.lines.map(l => ({ ...l, loc: (STOCK.find(x => x.codigo === l.codigo) || { loc: "E1-C01-N1", lote: "—", rack: "E1", qty: 0 }) })).sort((a, b) => a.loc.loc.localeCompare(b.loc.loc)); const l = L[col.line]; if (!l) return; if (col.step === 0) { if (code === l.loc.loc) { ok(code); setTimeout(() => { col.step = 1; col.scan = null; cRender(); }, 500); } else bad(isLoc ? `${code} — ubicación equivocada` : code); } else if (col.step === 1) { if (code === l.codigo) { ok(code); setTimeout(() => { col.step = 2; col.qty = l.qty; col.scan = null; cRender(); }, 500); } else bad(prod ? `${code} — ítem distinto` : code); } }
  else if (s === "reubicar") { if (col.step === 0) { if (isLoc && STOCK.some(x => x.loc === code)) { col.from = code; ok(code); setTimeout(() => { col.step = 1; col.scan = null; cRender(); }, 450); } else bad(isLoc ? `${code} — sin stock` : code); } else if (col.step === 1) { const st = STOCK.find(x => x.loc === col.from && x.codigo === code); if (st) { col.item = st; col.qty = st.qty; ok(code); } else bad(prod ? `${code} — no está en ${col.from}` : code); } else { if (isLoc && code !== col.from) { ok(code); col.queue += col.online ? 0 : 1; setTimeout(() => { toast(`Reubicado ${col.qty} × ${col.item.codigo}: ${col.from} → ${code}`, "ok"); col.screen = "home"; col.step = 0; col.from = null; col.item = null; col.scan = null; cRender(); }, 600); } else bad(code); } }
  else if (s === "recv") { const o = col.task; const l = o.lines.find(x => x.codigo === code && x.rec < x.qty); if (l) { l.rec = Math.min(l.qty, l.rec + Math.ceil(l.qty / 3)); ok(code); } else bad(prod ? `${code} — no está en la orden` : code); }
  else if (s === "conteo") { if (!col.from) { if (isLoc) { col.from = code; ok(code); } else bad(code); } else { if (prod) ok(code); else bad(code); } }
  else if (s === "ubicar") { if (prod || code.startsWith("00")) { col.item = STOCK.find(x => x.codigo === code) || STOCK[0]; ok(code); } else if (isLoc && col.item) { ok(code); setTimeout(() => { toast(`Ubicado ${col.item.codigo} en ${code}${code !== "E2-C05-N2" ? " (desviación registrada)" : ""}`, "ok"); col.screen = "home"; col.item = null; col.scan = null; cRender(); }, 600); } else bad(code); }
  else if (s === "consulta") { if (isLoc) { ok(code); col.scan.res = consultaLoc(code); } else if (prod) { ok(code); col.scan.res = consultaProd(code); } else if (ped) { ok(code); col.scan.res = `<div class="cline"><div><b>${ped.nro} · ${esc(ped.cliente.nombre)}</b><span>${ped.lines.length} líneas · ${ped.picker ? userName(ped.picker) : "sin asignar"}</span></div>${pillFor(ped.estado)}</div>`; } else bad(code); }
  else if (s === "recepcion") { if (ing) { col.task = ing; col.screen = "recv"; col.scan = null; beep(true); } else bad(code); }
  cRender();
}
const consultaLoc = code => { const st = STOCK.filter(x => x.loc === code); const l = LOCATIONS.find(x => x.id === code); return `<div class="cline"><div><span class="small muted">Ubicación</span><b class="mono" style="font-size:22px">${code}</b><span>${RACKS.find(r => r.id === l.rack).name} · ${l.blocked ? "BLOQUEADA" : st.length ? "ocupada" : "vacía"}</span></div><span class="q">${fmt(st.reduce((a, x) => a + x.qty, 0))}</span></div>${st.map(x => `<div class="cline"><div><b class="mono small">${x.codigo}</b><span>${esc(P(x.codigo).desc)}<br>lote ${x.lote} · ${fmtD(x.ingreso)}</span></div><span class="q">${fmt(x.qty)}</span></div>`).join("")}`; };
const consultaProd = code => { const p = P(code); const st = STOCK.filter(x => x.codigo === code); return `<div class="cline"><div><span class="small muted">Ítem · clase ${p.rot}</span><b class="mono">${code}</b><span>${esc(p.desc)}</span></div><span class="q">${fmt(stockOf(code))}<div class="small muted" style="font-weight:400">${p.um}</div></span></div>${st.map(x => `<div class="cline"><div><b class="mono small">${x.loc}</b><span>lote ${x.lote} · ${fmtD(x.ingreso)}</span></div><span class="q">${fmt(x.qty)}</span></div>`).join("")}`; };

function colSimButtons() {
  const s = col.screen; let btns = [];
  if (s === "pick" && col.task) { const L = col.task.lines.map(l => ({ ...l, loc: (STOCK.find(x => x.codigo === l.codigo) || { loc: "E1-C01-N1", lote: "—", rack: "E1", qty: 0 }) })).sort((a, b) => a.loc.loc.localeCompare(b.loc.loc)); const l = L[col.line]; if (l) btns = col.step === 0 ? [["Ubicación correcta", l.loc.loc], ["Ubicación equivocada", "E6-C01-N1"]] : col.step === 1 ? [["Ítem correcto", l.codigo], ["Ítem distinto", PRODUCTS[0].codigo]] : []; }
  else if (s === "reubicar") { const src = STOCK[3]; btns = col.step === 0 ? [["Escanear " + src.loc, src.loc], ["Ubicación vacía", "AJ-C01-N1"]] : col.step === 1 ? [["Ítem del origen", (STOCK.find(x => x.loc === col.from) || src).codigo], ["Ítem ajeno", PRODUCTS[5].codigo]] : [["Destino sugerido", "E2-C05-N2"], ["Otro destino", "E4-C02-N1"]]; }
  else if (s === "recv" && col.task) btns = col.task.lines.slice(0, 2).map(l => [`Bulto ${l.codigo.slice(-4)}`, l.codigo]).concat([["Ítem ajeno", PRODUCTS[9].codigo]]);
  else if (s === "recepcion") btns = INGRESOS.filter(i => i.estado === "Habilitado").slice(0, 1).map(i => [`Orden ${i.nro}`, i.nro]);
  else if (s === "conteo") btns = col.from ? STOCK.filter(x => x.loc === col.from).slice(0, 2).map(x => [`Ítem ${x.codigo.slice(-4)}`, x.codigo]) : [["Ubicación E1-C03-N2", STOCK[2].loc]];
  else if (s === "ubicar") btns = col.item ? [["Sugerida E2-C05-N2", "E2-C05-N2"], ["Desviarse a E4-C01-N1", "E4-C01-N1"]] : [["Bulto del muelle", STOCK[7].codigo]];
  else btns = [["Ubicación " + STOCK[3].loc, STOCK[3].loc], ["Ítem " + STOCK[3].codigo.slice(-4), STOCK[3].codigo], ["Pedido " + myTasks()[0]?.nro, myTasks()[0]?.nro], ["Código inválido", "XYZ-000"]];
  $("#colSims").innerHTML = btns.map(([l, c]) => `<button class="btn sm" data-sim="${esc(c)}">${ic("scan")} ${esc(l)}</button>`).join("") + `<button class="btn sm" id="colNet">${col.online ? ic("wifi") + " Simular pérdida de WiFi" : ic("cloud") + " Recuperar WiFi"}</button>`;
}
const _cRender = cRender; cRender = function () { _cRender(); colSimButtons(); };

/* ==== 09-boot.html ==== */
/* ===================== EVENTOS Y ARRANQUE ===================== */
function applyRail() { $("#app").classList.toggle("rail-collapsed", state.railCollapsed); const b = $("#railToggle"); b.title = state.railCollapsed ? "Expandir menú" : "Contraer menú"; b.querySelector("svg").style.transform = state.railCollapsed ? "rotate(180deg)" : ""; }
function toggleTheme() { const cur = document.documentElement.dataset.theme || (matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light"); const next = cur === "dark" ? "light" : "dark"; document.documentElement.dataset.theme = next; state.theme = next; try { localStorage.setItem("cwms-theme", next); } catch (e) {} $("#btnTheme").innerHTML = ic(next === "dark" ? "sun" : "moon"); if ($("#labelStage")) renderLabel(); }

function enter() {
  state.wh = $("#lg-wh").value;
  $("#login").hidden = true; $("#app").hidden = false;
  buildNav(); applyRail();
  const h = location.hash.replace("#", ""); if (VIEWS[h]) state.route = h;
  render(); toast(`Bienvenido, ${CW.user.first}. Trabajando en ${WH().name}.`, "info");
}
$("#loginForm").addEventListener("submit", e => { e.preventDefault(); enter(); });
$("#railToggle").onclick = () => { state.railCollapsed = !state.railCollapsed; try { localStorage.setItem("cwms-rail", state.railCollapsed ? "1" : "0"); } catch (e) {} applyRail(); };
$("#btnTheme").onclick = toggleTheme;
$("#ctxWh").onchange = e => { state.wh = e.target.value; state.selLoc = null; render(); toast(`Almacén de trabajo: ${WH().name}`, "info"); };
$("#scrim").onclick = closeOverlays;
document.addEventListener("keydown", e => { if (e.key === "Escape") { closeOverlays(); $("#gsPop").hidden = true; if ($("#colOverlay").classList.contains("open") && !matchMedia("(max-width:820px)").matches) $("#colOverlay").classList.remove("open"); } if (e.key === "/" && !/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName) && !$("#app").hidden) { e.preventDefault(); $("#gsearch").focus(); } });
window.addEventListener("hashchange", () => { const h = location.hash.replace("#", ""); if (VIEWS[h] && h !== state.route && !$("#app").hidden) { state.route = h; render(); } });
$("#btnNotif").onclick = () => openDrawer("Alertas", `<ul class="timeline">${ALERTS.map(a => `<li><span class="ic ${a.k}">${ic(a.k === "ok" ? "check" : a.k === "info" ? "cloud" : "alert")}</span><div><b>${esc(a.t)}</b><span>${esc(a.s)}</span></div></li>`).join("")}</ul>`, `<button class="btn" data-toast="Alertas marcadas como leídas.">Marcar todo como leído</button>`);
$("#btnUser").onclick = () => openDrawer(esc(CW.user.nombre), `<dl class="kv"><dt>Usuario</dt><dd class="mono">${esc(CW.user.user)}</dd><dt>Rol</dt><dd>${esc(CW.user.rol)}</dd><dt>Almacenes</dt><dd>${esc(CW.user.wh.join(" · "))}</dd><dt>Sesión</dt><dd>${esc(CW.user.since)} · escritorio</dd></dl><hr class="sep"><div class="stack"><button class="btn block" id="drTheme">${ic("moon")} Cambiar tema</button><button class="btn block" data-go="usuarios">${ic("shield")} Administrar usuarios</button><button class="btn block" data-close data-logout>${ic("logout")} Cerrar sesión</button></div>`);

/* búsqueda global */
const gs = $("#gsearch"), gsPop = $("#gsPop");
gs.addEventListener("input", () => {
  const q = gs.value.trim().toLowerCase(); if (q.length < 2) { gsPop.hidden = true; return; }
  const res = [];
  PRODUCTS.filter(p => p.codigo.toLowerCase().includes(q) || p.desc.toLowerCase().includes(q)).slice(0, 4).forEach(p => res.push({ tag: "Producto", t: `<span class="mono">${p.codigo}</span> · ${esc(p.desc)}`, a: `data-prod="${p.codigo}"` }));
  LOCATIONS.filter(l => l.id.toLowerCase().includes(q)).slice(0, 3).forEach(l => res.push({ tag: "Ubicación", t: `<span class="mono">${l.id}</span> · ${RACKS.find(r => r.id === l.rack).name}`, a: `data-loc="${l.id}"` }));
  PEDIDOS.filter(p => p.nro.toLowerCase().includes(q) || p.cliente.nombre.toLowerCase().includes(q)).slice(0, 3).forEach(p => res.push({ tag: "Pedido", t: `<span class="mono">${p.nro}</span> · ${esc(p.cliente.nombre)} ${pillFor(p.estado)}`, a: `data-ped="${p.nro}"` }));
  INGRESOS.filter(i => i.nro.toLowerCase().includes(q)).slice(0, 2).forEach(i => res.push({ tag: "Ingreso", t: `<span class="mono">${i.nro}</span> · ${i.tipo} ${pillFor(i.estado)}`, a: `data-ing="${i.nro}"` }));
  STOCK.filter(s => s.lote.toLowerCase().includes(q)).slice(0, 2).forEach(s => res.push({ tag: "Lote", t: `<span class="mono">${s.lote}</span> · ${s.codigo} en ${s.loc}`, a: `data-loc="${s.loc}"` }));
  gsPop.innerHTML = res.length ? res.map(r => `<div class="it" ${r.a}><span class="tag">${r.tag}</span><span>${r.t}</span></div>`).join("") : `<div class="it muted">Sin resultados para «${esc(gs.value)}»</div>`;
  gsPop.hidden = false;
});
gs.addEventListener("blur", () => setTimeout(() => gsPop.hidden = true, 150));

/* delegación global */
document.addEventListener("click", e => {
  const t = e.target.closest("[data-go],[data-sub-salidas],[data-sub-stock],[data-sub-usuarios],[data-sub-parametros],[data-ing],[data-ped],[data-dsp],[data-prod],[data-loc],[data-client],[data-count],[data-rack],[data-act],[data-toast],[data-close],[data-filter-ing],[data-ds],[data-imp-prev],[data-imp-next],[data-imp-reset],[data-mode],[data-tpl],[data-qrfmt],[data-field],[data-qrsize],[data-sample],[data-copy],[data-c],[data-q],[data-sim],#colNet,#colClose,#btnCollector,#drTheme,.chip,.tog,.seg button,.tabs button,[data-logout]");
  if (!t) return;
  const d = t.dataset;
  if (t.id === "btnCollector") { $("#colOverlay").classList.add("open"); cRender(); return; }
  if (t.id === "colClose") { $("#colOverlay").classList.remove("open"); return; }
  if (t.id === "colNet") { col.online = !col.online; if (col.online) { toast(`WiFi recuperado. ${col.queue} movimientos enviados al servidor.`, "ok"); col.queue = 0; } else toast("Sin red: los movimientos se guardan en el equipo y se envían después.", "info", 4000); cRender(); return; }
  if (t.id === "drTheme") { toggleTheme(); return; }
  if (d.logout !== undefined) { closeOverlays(); $("#app").hidden = true; $("#login").hidden = false; return; }
  if (d.sim) { simScan(d.sim); return; }
  if (d.c) { colAction(d.c, t); return; }
  if (d.q) { const i = $("#cQty"); if (i) { i.value = Math.max(0, (+i.value || 0) + +d.q); col.qty = +i.value; const b = i.closest(".c-body")?.querySelector("[data-c=confirm]"); if (b) b.textContent = `Confirmar ${col.qty} ${P(col.task.lines[0].codigo).um}`; } return; }
  if (d.copy !== undefined) { navigator.clipboard?.writeText(d.copy).catch(() => {}); }
  if (d.toast) { toast(d.toast, d.kind || "ok"); closeOverlays(); if (d.go === undefined) return; }
  if (d.close !== undefined) { closeOverlays(); if (d.go === undefined) return; }
  if (d.go) { e.preventDefault(); closeOverlays(); go(d.go, d.sub); return; }
  if (d.subSalidas) { state.sub.salidas = d.subSalidas; render(); return; }
  if (d.subStock) { state.sub.stock = d.subStock; render(); return; }
  if (d.subUsuarios) { state.sub.usuarios = d.subUsuarios; render(); return; }
  if (d.subParametros) { state.sub.parametros = d.subParametros; render(); return; }
  if (d.filterIng) { state.filters.ing = d.filterIng; render(); return; }
  if (d.rack) { state.sub.ubicaciones = d.rack; state.selLoc = null; render(); return; }
  if (d.loc && state.route === "ubicaciones" && t.classList.contains("cell")) { state.selLoc = d.loc; render(); return; }
  if (d.loc) { e.preventDefault(); drawerLoc(d.loc); return; }
  if (d.prod) { e.preventDefault(); drawerProducto(d.prod); return; }
  if (d.ing) { drawerIngreso(d.ing); return; }
  if (d.ped) { drawerPedido(d.ped); return; }
  if (d.client) { drawerCliente(d.client); return; }
  if (d.count) { drawerConteo(+d.count); return; }
  if (d.dsp) { const x = DESPACHOS.find(z => z.nro === d.dsp); openDrawer(`Despacho ${x.nro}`, `<dl class="kv" style="margin-bottom:14px"><dt>Fecha</dt><dd>${fmtD(x.fecha)}</dd><dt>Vehículo</dt><dd class="mono">${x.placa}</dd><dt>Chofer</dt><dd>${esc(x.chofer)}</dd><dt>Destino</dt><dd>${x.destino}</dd><dt>Bultos</dt><dd>${x.bultos}</dd><dt>Estado</dt><dd>${pillFor(x.estado)}</dd></dl><h3 style="font-size:14px;margin-bottom:8px">Pedidos cargados</h3>${table([{ h: "Pedido", f: n => `<span class="mono link" data-ped="${n}">${n}</span>` }, { h: "Cliente", f: n => esc(PEDIDOS.find(p => p.nro === n)?.cliente.nombre || "") }, { h: "Bultos verificados", f: n => `<span class="pill ok plain">${PEDIDOS.find(p => p.nro === n)?.bultos || 0}/${PEDIDOS.find(p => p.nro === n)?.bultos || 0}</span>` }], x.pedidos, { empty: "Sin pedidos" })}<ul class="timeline" style="margin-top:16px"><li><span class="ic ok">${ic("check")}</span><div><b>Bultos verificados por QR al cargar</b><span>${x.bultos} de ${x.bultos} · chofer ${esc(x.chofer)}</span></div></li><li><span class="ic ${x.estado === "Cargando" ? "" : "ok"}">${ic("truck")}</span><div><b>Salida del muelle</b><span>${x.estado === "Cargando" ? "pendiente" : "confirmada · GPS del chofer"}</span></div></li><li><span class="ic ${x.estado === "Entregado" ? "ok" : ""}">${ic("check")}</span><div><b>Entrega</b><span>${x.estado === "Entregado" ? "firma y foto del cliente" : "pendiente"}</span></div></li></ul>`, `<button class="btn">${ic("print")} Nota de despacho</button><button class="btn primary" data-close>Cerrar</button>`, "Despachos"); return; }
  if (d.act) { ({ "new-ing": modalNuevoIngreso, "new-wave": modalOla, "new-dsp": modalDespacho, "new-count": modalConteo, "new-rack": modalRack, "new-loc": modalLoc, "print-batch": modalPrintBatch, "new-user": modalUser, "new-device": modalDevice, "new-prod": () => openModal("Nuevo producto", `<div class="card" style="box-shadow:none;margin-bottom:14px"><div class="card-b row small">${ic("alert")} <span>Los ítems se crean en <b>WorkCorp</b> y bajan al WMS. Usa este formulario solo para ítems logísticos internos (embalajes, pallets, insumos de almacén).</span></div></div><div class="grid g-2"><div class="field"><label>Código</label><input class="input mono" placeholder="Se asigna al guardar"></div><div class="field"><label>Descripción</label><input class="input"></div><div class="field"><label>Unidad</label><select class="select"><option>UN</option><option>BUL</option><option>KG</option><option>M</option></select></div><div class="field"><label>Categoría</label><select class="select"><option>Insumos de almacén</option><option>Embalaje</option></select></div><div class="field"><label>Clase ABC</label><select class="select"><option>C</option><option>B</option><option>A</option></select></div><div class="field"><label>Vida útil (días)</label><input class="input num" type="number" value="0"></div><div class="field"><label>Mínimo</label><input class="input num" type="number" value="0"></div><div class="field"><label>Máximo</label><input class="input num" type="number" value="0"></div></div>`, `<button class="btn" data-close>Cancelar</button><button class="btn primary" data-toast="Producto creado.">Guardar</button>`), "new-client": () => openModal("Nuevo cliente", `<div class="grid g-2"><div class="field"><label>Código</label><input class="input mono"></div><div class="field"><label>NIT</label><input class="input"></div><div class="field" style="grid-column:1/-1"><label>Razón social</label><input class="input"></div><div class="field"><label>Departamento</label><select class="select">${["Santa Cruz", "Beni", "Pando", "La Paz", "Cochabamba", "Tarija", "Oruro", "Potosí", "Chuquisaca"].map(x => `<option>${x}</option>`).join("")}</select></div><div class="field"><label>Ciudad</label><input class="input"></div><div class="field" style="grid-column:1/-1"><label>Dirección de entrega</label><input class="input"></div><div class="field"><label>Teléfono</label><input class="input"></div><div class="field"><label>Canal</label><select class="select"><option>Ferretería</option><option>Distribuidor</option><option>Cadena</option><option>Mayorista</option><option>Agro</option></select></div></div>`, `<button class="btn" data-close>Cancelar</button><button class="btn primary" data-toast="Cliente creado.">Guardar</button>`), "new-driver": () => openModal("Nuevo chofer", `<div class="grid g-2"><div class="field"><label>CI</label><input class="input"></div><div class="field"><label>Licencia</label><select class="select"><option>CAT-A</option><option>CAT-B</option><option>CAT-C</option></select></div><div class="field"><label>Nombre</label><input class="input"></div><div class="field"><label>Apellidos</label><input class="input"></div><div class="field"><label>Teléfono</label><input class="input"></div><div class="field"><label>Vehículo habitual</label><select class="select"><option>—</option>${TRUCKS.map(x => `<option>${x.placa}</option>`).join("")}</select></div></div>`, `<button class="btn" data-close>Cancelar</button><button class="btn primary" data-toast="Chofer registrado.">Guardar</button>`), "new-truck": () => openModal("Nuevo vehículo", `<div class="grid g-2"><div class="field"><label>Placa</label><input class="input mono"></div><div class="field"><label>Tipo</label><select class="select"><option>Camión 5 t</option><option>Camión 8 t</option><option>Camión 12 t</option><option>Tráiler</option><option>Camioneta</option></select></div><div class="field"><label>Marca / modelo</label><input class="input"></div><div class="field"><label>Capacidad (kg)</label><input class="input num" type="number"></div><div class="field"><label>Volumen (m³)</label><input class="input num" type="number"></div><div class="field"><label>Chofer habitual</label><select class="select"><option>—</option>${DRIVERS.map(x => `<option>${x.nombre}</option>`).join("")}</select></div></div>`, `<button class="btn" data-close>Cancelar</button><button class="btn primary" data-toast="Vehículo registrado.">Guardar</button>`) }[d.act] || (() => {}))(); return; }
  /* importador */
  if (d.ds) { imp.ds = DATASETS.find(x => x.id === d.ds); imp.file = null; imp.rows = []; imp.headers = []; imp.map = {}; render(); return; }
  if (d.impPrev !== undefined) { imp.step = Math.max(0, imp.step - 1); render(); return; }
  if (d.impNext !== undefined) { if (imp.step === 3) { IMPORT_HISTORY.unshift({ fecha: new Date().toLocaleDateString("es-BO", { day: "2-digit", month: "2-digit", year: "numeric" }) + " " + new Date().toTimeString().slice(0, 5), dataset: imp.ds.lbl, archivo: imp.file, filas: imp.rows.length, ok: validateRows().filter(x => !x.errs.length).length, err: validateRows().filter(x => x.errs.length).length, user: "amoscoso" }); } imp.step = Math.min(4, imp.step + 1); render(); return; }
  if (d.impReset !== undefined) { Object.assign(imp, { step: 0, ds: null, file: null, rows: [], headers: [], map: {} }); render(); return; }
  if (d.mode) { imp.mode = d.mode; render(); return; }
  /* etiquetas */
  if (d.tpl) { lab.tpl = d.tpl; lab.cfg = JSON.parse(JSON.stringify(LABEL_TEMPLATES[d.tpl])); lab.sampleIdx = 0; render(); return; }
  if (d.qrfmt) { lab.cfg.qrfmt = d.qrfmt; render(); return; }
  if (d.field) { lab.cfg.fields[d.field] = !lab.cfg.fields[d.field]; t.classList.toggle("on"); renderLabel(); return; }
  if (d.qrsize) { lab.qrSize = +d.qrsize; $$("[data-qrsize]").forEach(b => b.classList.toggle("on", b === t)); renderLabel(); return; }
  if (d.sample) { lab.sampleIdx = Math.max(0, lab.sampleIdx + +d.sample); render(); return; }
  /* genéricos decorativos */
  if (t.classList.contains("chip") && !t.closest(".mapping")) { const g = t.parentElement; if (g.classList.contains("chips") && g.closest(".toolbar")) { $$(".chip", g).forEach(c => c.classList.remove("on")); t.classList.add("on"); } else t.classList.toggle("on"); return; }
  if (t.classList.contains("tog")) { t.classList.toggle("on"); return; }
  if (t.matches(".seg button") && !d.subSalidas) { $$("button", t.parentElement).forEach(b => b.classList.remove("on")); t.classList.add("on"); return; }
  if (t.matches(".tabs button") && !Object.keys(d).some(k => k.startsWith("sub"))) { $$("button", t.parentElement).forEach(b => b.classList.remove("on")); t.classList.add("on"); return; }
});
document.addEventListener("change", e => { const s = e.target.closest("[data-maph]"); if (s) { if (s.value) imp.map[s.dataset.maph] = s.value; else delete imp.map[s.dataset.maph]; render(); } });

/* acciones del colector */
function colAction(a, t) {
  if (["home", "tareas", "consulta", "mas", "reubicar", "recepcion", "conteo", "ubicar", "picking"].includes(a)) { col.screen = a === "picking" ? "tareas" : a; col.scan = null; col.step = 0; col.from = null; col.item = null; if (a === "home") col.task = null; cRender(); return; }
  if (a === "pick") { col.task = PEDIDOS.find(p => p.nro === t.dataset.ped); col.line = 0; col.step = 0; col.qty = 0; col.scan = null; col.screen = "pick"; cRender(); return; }
  if (a === "recv") { col.task = INGRESOS.find(i => i.nro === t.dataset.ing); col.scan = null; col.screen = "recv"; cRender(); return; }
  if (a === "confirm" || a === "skip" || a === "short") { const l = col.task.lines[col.line]; if (a === "confirm") { l.pick = l.qty; beep(true); if (!col.online) col.queue++; } if (a === "short") { toast("Faltante reportado. Se generó conteo por excepción en la ubicación.", "info", 3800); } col.line++; col.step = 0; col.scan = null; col.qty = 0; cRender(); return; }
  if (a === "mv-next") { col.step = 2; col.scan = null; cRender(); return; }
  if (a === "mv-accept") { simScan("E2-C05-N2"); return; }
  if (a === "recv-close") { toast(`${col.task.nro}: recepción cerrada en el muelle. Pendiente de ubicar.`, "ok"); col.task.estado = "Cerrado"; col.screen = "home"; cRender(); if (!$("#app").hidden) render(); return; }
  if (a === "count-ok") { beep(true); toast(`Ubicación ${col.from} contada. 17 pendientes.`, "ok"); col.from = null; col.scan = null; cRender(); return; }
}

/* arranque: en pantallas angostas, ir directo al modo colector tras login */
buildNav();
if (location.hash && VIEWS[location.hash.replace("#", "")]) { /* deep link: entrar directo */ enter(); }
