/* Carmen WMS · navegación, eventos y arranque (inicio de sesión, sincronización, búsqueda global). */
"use strict";
const NAV = [
  { grp: "Principal" },
  { id: "inicio", lbl: "Inicio", icon: "home" },
  { grp: "Operación" },
  { id: "ingresos", lbl: "Ingresos", icon: "in", cnt: () => whIng().filter(i => ["Habilitado", "En recepción"].includes(i.estado)).length },
  { id: "salidas", lbl: "Pedidos y despacho", icon: "out", cnt: () => whPed().filter(p => p.estado !== "Despachado").length },
  { id: "stock", lbl: "Stock e inventario", icon: "stock", cnt: () => whAdj().filter(a => a.estado === "Pendiente").length || null },
  { id: "ubicaciones", lbl: "Mapa de almacén", icon: "map" },
  { grp: "Maestros" },
  { id: "productos", lbl: "Productos", icon: "box" },
  { id: "clientes", lbl: "Clientes", icon: "users" },
  { id: "transporte", lbl: "Choferes y camiones", icon: "truck" },
  { grp: "Configuración" },
  { id: "importar", lbl: "Importar datos", icon: "upload" },
  { id: "etiquetas", lbl: "Etiquetas QR", icon: "qr" },
  { id: "usuarios", lbl: "Usuarios y roles", icon: "shield" },
  { id: "dispositivos", lbl: "Colectores", icon: "phone", cnt: () => DEVICES.filter(d => d.wh === state.wh && !d.online).length || null },
  { id: "parametros", lbl: "Parámetros", icon: "sliders" },
];
const MOBILE_NAV = ["inicio", "ingresos", "salidas", "stock", "productos"];

function render() {
  acClose();
  const v = VIEWS[state.route] || VIEWS.inicio;
  const y = state.keepScroll ? window.scrollY : 0; state.keepScroll = false;
  try { $("#view").innerHTML = v(); } catch (e) { console.error(e); $("#view").innerHTML = `<div class="card"><div class="empty">${ic("alert")}<div>No se pudo mostrar esta pantalla: ${esc(e.message)}</div></div></div>`; }
  $$("#nav a.nav").forEach(a => a.classList.toggle("active", a.dataset.id === state.route));
  $$("#mnav a").forEach(a => a.classList.toggle("active", a.dataset.id === state.route));
  window.scrollTo({ top: y });
  if (v.after) v.after();
}
function go(route, sub) { if (!VIEWS[route]) route = "inicio"; state.route = route; if (sub) state.sub[route] = sub; if (location.hash !== "#" + route) history.pushState(null, "", "#" + route); render(); }
function buildNav() {
  $("#nav").innerHTML = NAV.map(n => n.grp ? `<div class="grp">${n.grp}</div>` : `<a class="nav" href="#${n.id}" data-id="${n.id}">${ic(n.icon)}<span class="lbl">${n.lbl}</span>${n.cnt && n.cnt() ? `<span class="cnt">${n.cnt()}</span>` : ""}</a>`).join("");
  $("#mnav").innerHTML = MOBILE_NAV.map(id => { const n = NAV.find(x => x.id === id); return `<a href="#${id}" data-id="${id}">${ic(n.icon)}<span>${n.lbl.split(" ")[0]}</span></a>`; }).join("");
  const allowed = CW.user && CW.user.wh && CW.user.wh.length ? CW.user.wh : WAREHOUSES.filter(w => w.type !== "TR").map(w => w.id);
  $("#ctxWh").innerHTML = WAREHOUSES.filter(w => w.type !== "TR" && allowed.includes(w.id)).map(w => `<option value="${w.id}" ${w.id === state.wh ? "selected" : ""}>${esc(w.name)}</option>`).join("");
  $$("#nav a.nav").forEach(a => a.classList.toggle("active", a.dataset.id === state.route));
}
function applyRail() { $("#app").classList.toggle("rail-collapsed", state.railCollapsed); const b = $("#railToggle"); b.title = state.railCollapsed ? "Expandir menú" : "Contraer menú"; b.querySelector("svg").style.transform = state.railCollapsed ? "rotate(180deg)" : ""; }
function toggleTheme() { const cur = document.documentElement.dataset.theme || (matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light"); const next = cur === "dark" ? "light" : "dark"; document.documentElement.dataset.theme = next; try { localStorage.setItem("cwms-theme", next); } catch (e) {} $("#btnTheme").innerHTML = ic(next === "dark" ? "sun" : "moon"); if ($("#labelStage")) renderLabel(); }
async function switchWh(wh) {
  state.wh = wh; state.selLoc = null; state.pages = {}; buildNav();
  if (!$("#app").hidden) render(); if ($("#colOverlay").classList.contains("open")) { col.task = null; col.screen = "home"; cRender(); }
  try { await api("POST", CW.routes.warehouse, { wh }); } catch (e) {}
  toast(`Almacén de trabajo: ${esc(WH().name)}`, "info");
}
function paintUser() {
  if (!CW.user) return;
  $("#uAvatar").textContent = initials(CW.user.nombre) || "··"; $("#uName").textContent = CW.user.nombre; $("#uRole").textContent = CW.user.rol;
}

/* ---------------------------------------------------------------- inicio de sesión */
const params = new URLSearchParams(location.search);
if (params.get("equipo")) { try { localStorage.setItem("cw-device", params.get("equipo")); } catch (e) {} }
const enrolled = (() => { try { return localStorage.getItem("cw-device"); } catch (e) { return null; } })();
if (enrolled && CW.user) CW.device = enrolled;
function setLoginMode(mode) {
  $$("#lgMode button").forEach(b => b.classList.toggle("on", b.dataset.mode === mode));
  $("#loginForm").dataset.mode = mode;
  const pass = $("#lg-pass"); $("label[for=lg-pass]").textContent = mode === "col" ? "PIN" : "Contraseña";
  pass.placeholder = mode === "col" ? "PIN de 4 a 6 dígitos" : "contraseña"; pass.inputMode = mode === "col" ? "numeric" : "text";
  $(".login-card .sub").textContent = mode === "col" ? "Ingresa con tu usuario y PIN del colector (o escanea tu credencial)." : "Accede con tu usuario de escritorio.";
}
$("#lgMode")?.addEventListener("click", e => { const b = e.target.closest("[data-mode]"); if (b) setLoginMode(b.dataset.mode); });
setLoginMode(params.get("colector") || enrolled || (matchMedia("(max-width:820px)").matches && matchMedia("(pointer:coarse)").matches) ? "col" : "desk");
$("#lg-user").addEventListener("change", e => { const v = e.target.value.trim(); if (/^USR:/i.test(v)) { e.target.value = v.slice(4); setLoginMode("col"); $("#lg-pass").focus(); } });
$("#loginForm").addEventListener("submit", async e => {
  e.preventDefault(); const f = e.target, btn = f.querySelector('button[type="submit"]'), msg = $("#lgMsg");
  btn.disabled = true; btn.textContent = "Verificando…";
  try {
    await api("POST", CW.routes.login, { username: f.username.value.trim().replace(/^USR:/i, ""), password: f.password.value, wh: f.wh.value, mode: f.dataset.mode || "desk" });
    try { sessionStorage.setItem("cw-welcome", "1"); } catch (x) {}
    location.reload();
  } catch (err) {
    btn.disabled = false; btn.textContent = "Entrar";
    msg.innerHTML = `<b style="color:var(--bad)">${esc(err.status === 422 ? err.message : err.status === 419 ? "La sesión expiró. Recarga la página e intenta de nuevo." : err.status === 429 ? "Demasiados intentos. Espera un minuto." : err.message)}</b>`;
  }
});
async function logout() {
  if (Q.n && !await confirmDlg("Operaciones sin enviar", `Hay <b>${Q.n}</b> operación(es) en cola. Si cierras sesión ahora se enviarán cuando vuelvas a entrar en este equipo.`, { ok: "Cerrar sesión igual" })) return;
  try { await api("POST", CW.routes.logout); } catch (e) {}
  try { sessionStorage.removeItem("cw-welcome"); } catch (x) {}
  location.href = CW.routes.home + (enrolled ? "?colector=1" : "");
}

/* ---------------------------------------------------------------- eventos globales */
$("#railToggle").onclick = () => { state.railCollapsed = !state.railCollapsed; try { localStorage.setItem("cwms-rail", state.railCollapsed ? "1" : "0"); } catch (e) {} applyRail(); };
$("#btnTheme").onclick = toggleTheme;
$("#ctxWh").onchange = e => switchWh(e.target.value);
$("#scrim").onclick = () => { if ($("#modal").classList.contains("open")) closeModal(); else closeOverlays(); };
document.addEventListener("keydown", e => {
  if (e.key === "Escape" && !$("#dlg")) { if ($("#modal").classList.contains("open")) closeModal(); else closeOverlays(); $("#gsPop").hidden = true; if ($("#colOverlay").classList.contains("open") && !matchMedia("(max-width:820px)").matches && !overlayOpen()) closeCollector(); }
  if (e.key === "/" && !/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName) && !$("#app").hidden) { e.preventDefault(); $("#gsearch").focus(); }
});
window.addEventListener("popstate", () => { const h = location.hash.replace("#", ""); if (VIEWS[h] && h !== state.route && !$("#app").hidden) { state.route = h; closeOverlays(); render(); } });
$("#btnNotif").onclick = () => openDrawer("Alertas", `<ul class="timeline">${computedAlerts().map(alertHtml).join("")}</ul>`, `<button class="btn" data-close>Cerrar</button>`, esc(WH().name));
$("#btnUser").onclick = () => openDrawer(esc(CW.user.nombre), `<dl class="kv"><dt>Usuario</dt><dd class="mono">${esc(CW.user.user)}</dd><dt>Rol</dt><dd>${esc(CW.user.rol)}</dd><dt>Almacenes</dt><dd>${esc((CW.user.wh || []).join(" · "))}</dd><dt>Permisos</dt><dd>${PERM_KEYS.map((k, i) => can(k) ? PERMS[i] : null).filter(Boolean).join(", ") || "—"}</dd><dt>Sesión</dt><dd>${esc(CW.user.since)}</dd></dl><hr class="sep"><div class="stack"><button class="btn block" data-a="my-pass">${ic("key")} Cambiar mi contraseña</button><button class="btn block" id="drTheme">${ic("moon")} Cambiar tema</button>${can("config") ? `<button class="btn block" data-go="usuarios">${ic("shield")} Administrar usuarios</button>` : ""}<button class="btn block" data-logout>${ic("logout")} Cerrar sesión</button></div>`);
ACTIONS["my-pass"] = () => dialog("Cambiar mi contraseña", `${fld("Contraseña actual", `<input class="input" type="password" name="current" autocomplete="current-password">`)}${fld("Contraseña nueva", `<input class="input" type="password" name="password" autocomplete="new-password">`)}${fld("Repite la nueva", `<input class="input" type="password" name="password2" autocomplete="new-password">`)}`, { ok: "Guardar", validate: v => !v.current ? "Escribe tu contraseña actual." : (v.password || "").length < 6 ? "La nueva debe tener al menos 6 caracteres." : v.password !== v.password2 ? "Las contraseñas nuevas no coinciden." : null }).then(v => v && run(null, async () => { await api("POST", `${CW.routes.api}/mi-clave`, { current: v.current, password: v.password }); toast("Contraseña actualizada."); }));

/* búsqueda global */
const gs = $("#gsearch"), gsPop = $("#gsPop");
gs.addEventListener("input", () => {
  const q = gs.value.trim(); if (q.length < 2) { gsPop.hidden = true; return; }
  const res = [];
  PRODUCTS.filter(p => has(p.codigo, q) || has(p.desc, q)).slice(0, 5).forEach(p => res.push({ tag: "Producto", t: `<span class="mono">${esc(p.codigo)}</span> · ${esc(p.desc)}`, a: `data-prod="${esc(p.codigo)}"` }));
  whLocs().filter(l => has(l.id, q)).slice(0, 4).forEach(l => res.push({ tag: "Ubicación", t: `<span class="mono">${esc(l.id)}</span> · ${esc((R(l.rack) || {}).name || "")}`, a: `data-loc="${esc(l.id)}"` }));
  whPed().filter(p => has(p.nro, q) || has(cli(p.cliente).nombre, q) || has(p.ref, q)).slice(0, 4).forEach(p => res.push({ tag: "Pedido", t: `<span class="mono">${esc(p.nro)}</span> · ${esc(cli(p.cliente).nombre)} ${pillFor(p.estado)}`, a: `data-ped="${esc(p.nro)}"` }));
  whIng().filter(i => has(i.nro, q) || has(i.doc, q)).slice(0, 3).forEach(i => res.push({ tag: "Ingreso", t: `<span class="mono">${esc(i.nro)}</span> · ${esc(i.tipo)} ${pillFor(i.estado)}`, a: `data-ing="${esc(i.nro)}"` }));
  CLIENTS.filter(c => has(c.codigo, q) || has(c.nombre, q) || has(c.nit, q)).slice(0, 3).forEach(c => res.push({ tag: "Cliente", t: `<span class="mono">${esc(c.codigo)}</span> · ${esc(c.nombre)}`, a: `data-client="${esc(c.codigo)}"` }));
  whDsp().filter(d => has(d.nro, q) || has(d.placa, q)).slice(0, 2).forEach(d => res.push({ tag: "Despacho", t: `<span class="mono">${esc(d.nro)}</span> · ${esc(d.placa)} ${pillFor(d.estado)}`, a: `data-dsp="${esc(d.nro)}"` }));
  whStock().filter(s => has(s.lote, q)).slice(0, 3).forEach(s => res.push({ tag: "Lote", t: `<span class="mono">${esc(s.lote)}</span> · ${esc(s.codigo)} en ${esc(s.loc)}`, a: `data-loc="${esc(s.loc)}"` }));
  gsPop.innerHTML = res.length ? res.map((r, i) => `<div class="it ${i === 0 ? "on" : ""}" ${r.a}><span class="tag">${r.tag}</span><span>${r.t}</span></div>`).join("") : `<div class="it muted">Sin resultados para «${esc(gs.value)}»</div>`;
  gsPop.hidden = false;
});
gs.addEventListener("keydown", e => {
  const items = $$(".it[data-prod],.it[data-loc],.it[data-ped],.it[data-ing],.it[data-client],.it[data-dsp]", gsPop); if (!items.length || gsPop.hidden) return;
  let i = items.findIndex(x => x.classList.contains("on"));
  if (e.key === "ArrowDown" || e.key === "ArrowUp") { e.preventDefault(); i = (i + (e.key === "ArrowDown" ? 1 : -1) + items.length) % items.length; items.forEach((x, k) => x.classList.toggle("on", k === i)); }
  else if (e.key === "Enter") { e.preventDefault(); (items[i] || items[0]).click(); gsPop.hidden = true; gs.blur(); }
});
gs.addEventListener("blur", () => setTimeout(() => gsPop.hidden = true, 180));

/* delegación de clics */
document.addEventListener("click", e => {
  if (e.target.closest(".ac-pop")) return;
  const t = e.target.closest("[data-a],[data-go],[data-sub],[data-page],[data-f],[data-ing],[data-ped],[data-dsp],[data-prod],[data-loc],[data-cell],[data-client],[data-count],[data-rack],[data-close],[data-ds],[data-imp-prev],[data-imp-next],[data-imp-reset],[data-mode],[data-tpl],[data-qrfmt],[data-field],[data-qrsize],[data-sample],[data-c],[data-q],[data-sim],#colNet,#colClose,#btnCollector,#drTheme,[data-logout],[data-chipset] .chip,.tog[data-tog]");
  if (!t || t.disabled) return;
  if (t.closest("#loginForm")) return;
  const d = t.dataset;
  if (t.id === "btnCollector") { openCollector(); return; }
  if (t.id === "colClose") { if (colOnly()) logout(); else closeCollector(); return; }
  if (t.id === "colNet") { Q.forced = !Q.forced; if (!Q.forced) flushQueue(); else toast("Sin red (simulado): los movimientos se guardan en el equipo y se envían al recuperar la conexión.", "info", 4500); cRender(); return; }
  if (t.id === "drTheme") { toggleTheme(); return; }
  if (d.logout !== undefined) { e.preventDefault(); closeOverlays(); logout(); return; }
  if (d.sim) { simScan(d.sim); return; }
  if (d.c) { e.preventDefault(); colAction(d.c, t); return; }
  if (d.q && t.closest(".qty")) { const i = $("#cQty"); if (i) { i.value = Math.max(0, (+i.value || 0) + +d.q); i.dispatchEvent(new Event("input")); } return; }
  if (d.a) { if (t.tagName === "INPUT" && t.type === "checkbox") { ACTIONS[d.a]?.(t, e); return; } e.preventDefault(); const fn = ACTIONS[d.a]; if (fn) fn(t, e); else if (d.a) console.warn("Acción sin implementar", d.a); return; }
  if (d.close !== undefined) { if (t.closest("#modal") && $("#drawer").classList.contains("open")) closeModal(); else closeOverlays(); if (d.go === undefined) return; }
  if (d.go) { e.preventDefault(); closeOverlays(); if (d.filt) { const [k, v] = d.filt.split(":"); state.filters[k] = v; } go(d.go, d.sub); return; }
  if (d.sub) { const [r, k] = d.sub.split(":"); state.sub[r] = k; render(); return; }
  if (d.page) { const [id, p] = d.page.split(":"); state.pages[id] = Math.max(1, +p); if ($("#drawer").contains(t) && state.drawerFn) state.drawerFn(); else { state.keepScroll = true; render(); } return; }
  if (d.f !== undefined && d.v !== undefined) { state.filters[d.f] = d.v; Object.keys(state.pages).forEach(k => delete state.pages[k]); render(); return; }
  if (d.rack) { state.sub.ubicaciones = d.rack; state.selLoc = null; render(); return; }
  if (d.cell) { state.selLoc = d.cell; state.keepScroll = true; render(); return; }
  if (d.loc) { e.preventDefault(); const l = L(d.loc); if (!l) return; closeOverlays(); if (l.wh && l.wh !== state.wh) switchWh(l.wh); state.selLoc = l.id; state.sub.ubicaciones = l.rack; go("ubicaciones"); return; }
  if (d.prod) { e.preventDefault(); state.prodTab = "ficha"; DETAIL.prod(d.prod); return; }
  if (d.ing) { DETAIL.ing(d.ing); return; }
  if (d.ped) { DETAIL.ped(d.ped); return; }
  if (d.dsp) { DETAIL.dsp(d.dsp); return; }
  if (d.client) { DETAIL.client(d.client); return; }
  if (d.count) { DETAIL.count(+d.count); return; }
  /* importador */
  if (d.ds && !d.a) { imp.ds = d.ds; imp.file = null; imp.rows = []; imp.headers = []; imp.map = {}; render(); return; }
  if (d.impPrev !== undefined) { imp.step = Math.max(0, imp.step - 1); render(); return; }
  if (d.impNext !== undefined) { imp.step = Math.min(3, imp.step + 1); render(); return; }
  if (d.impReset !== undefined) { Object.assign(imp, { step: 0, ds: null, file: null, rows: [], headers: [], map: {}, result: null }); render(); return; }
  if (d.mode) { imp.mode = d.mode; render(); return; }
  /* etiquetas */
  if (d.tpl) { if (lab.dirty && !confirm("Hay cambios sin guardar en la plantilla. ¿Descartarlos?")) return; lab.tpl = d.tpl; lab.cfg = labCfg(d.tpl); lab.sampleIdx = 0; lab.dirty = false; render(); return; }
  if (d.qrfmt) { lab.cfg.qrfmt = d.qrfmt; lab.dirty = true; render(); return; }
  if (d.field) { lab.cfg.fields[d.field] = !lab.cfg.fields[d.field]; lab.dirty = true; t.classList.toggle("on"); renderLabel(); return; }
  if (d.qrsize) { lab.cfg.qrSize = +d.qrsize; lab.dirty = true; $$("[data-qrsize]").forEach(b => b.classList.toggle("on", b === t)); renderLabel(); return; }
  if (d.sample) { lab.sampleIdx = Math.max(0, lab.sampleIdx + +d.sample); render(); return; }
  /* chips y segmentos de formularios (dentro de modales) */
  if (t.matches(".chip") && t.closest("[data-chipset]")) { t.classList.toggle("on"); return; }
  if (t.matches(".tog[data-tog]")) { t.classList.toggle("on"); return; }
});
document.addEventListener("change", e => {
  const m = e.target.closest("[data-maph]"); if (m) { if (m.value) { Object.keys(imp.map).forEach(h => { if (imp.map[h] === m.value && h !== m.dataset.maph) delete imp.map[h]; }); imp.map[m.dataset.maph] = m.value; } else delete imp.map[m.dataset.maph]; render(); return; }
  const fs = e.target.closest("[data-fsel]"); if (fs) { state.filters[fs.dataset.fsel] = fs.value; state.pages = {}; render(); return; }
  const fd = e.target.closest("[data-fdate]"); if (fd) { state.filters[fd.dataset.fdate] = fd.value; state.pages = {}; render(); return; }
  const ac = e.target.closest("[data-a-change]"); if (ac) ACTIONS[ac.dataset.aChange]?.(ac, e);
});
/* búsquedas en listas: filtran mientras se escribe sin perder el foco */
let qTimer = null;
document.addEventListener("input", e => {
  const i = e.target.closest("[data-q]"); if (!i) return;
  clearTimeout(qTimer); qTimer = setTimeout(() => { state.filters[i.dataset.q] = i.value; Object.keys(state.pages).forEach(k => delete state.pages[k]); const key = i.dataset.q, pos = i.selectionStart; state.keepScroll = true; render(); const n = $(`[data-q="${key}"]`); if (n) { n.focus(); try { n.setSelectionRange(pos, pos); } catch (x) {} } }, 160);
});
/* el colector mantiene el foco en el campo de escaneo (DataWedge escribe ahí) */
document.addEventListener("click", e => { if (!$("#colOverlay").classList.contains("open") || !e.target.closest("#colScreen")) return; if (e.target.closest("input,select,textarea,button,a")) return; const s = $("#cScan"); if (s) s.focus(); });

/* ---------------------------------------------------------------- sincronización y sesión */
window.addEventListener("online", () => { flushQueue(); if ($("#colOverlay").classList.contains("open")) cRender(); });
window.addEventListener("offline", () => { if ($("#colOverlay").classList.contains("open")) cRender(); toast("Sin conexión: el colector guarda los movimientos y los envía al volver la red.", "info", 4500); });
setInterval(() => { if (!CW.user || document.visibilityState !== "visible") return; flushQueue(true).then(() => { if (!Q.n && online() && Date.now() - lastSync > 45000 && !overlayOpen() && !document.activeElement.matches("input,textarea,select")) resync(); }); }, 15000);
document.addEventListener("visibilitychange", () => { if (document.visibilityState === "visible" && CW.user && Date.now() - lastSync > 30000) flushQueue(true).then(() => resync()); });
let lastAct = Date.now(); ["click", "keydown", "touchstart"].forEach(ev => document.addEventListener(ev, () => lastAct = Date.now(), { passive: true }));
setInterval(() => { if (CW.user && Date.now() - lastAct > (+setting("inactividad") || 60) * 60000 && !Q.n) { toast("Sesión cerrada por inactividad.", "info"); logout(); } }, 60000);

/* ---------------------------------------------------------------- arranque */
function enterApp() {
  paintUser(); buildNav(); applyRail();
  $("#login").hidden = true;
  const h = location.hash.replace("#", ""); if (VIEWS[h]) state.route = h;
  const wantCol = CW.mode === "col" || colOnly() || params.get("colector") || enrolled;
  if (colOnly()) { $("#app").hidden = true; document.body.classList.add("col-only"); openCollector(); }
  else { $("#app").hidden = false; render(); if (wantCol) openCollector(); }
  if (params.get("q")) { setTimeout(() => simScan(params.get("q")), 200); }
  let welcome = false; try { welcome = sessionStorage.getItem("cw-welcome") === "1"; sessionStorage.removeItem("cw-welcome"); } catch (x) {}
  if (welcome) toast(`Bienvenido, ${esc(CW.user.first)}. Trabajando en ${esc(WH().name)}.`, "info");
  if (Q.n) flushQueue();
  $("#btnTheme").innerHTML = ic((document.documentElement.dataset.theme || (matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light")) === "dark" ? "sun" : "moon");
}
if (CW.user) {
  const allowed = CW.user.wh && CW.user.wh.length ? CW.user.wh : null; if (allowed && !allowed.includes(state.wh)) state.wh = allowed[0];
  enterApp();
} else { $("#login").hidden = false; $("#app").hidden = true; }
