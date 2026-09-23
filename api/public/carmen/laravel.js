/* Carmen WMS · enlace entre la interfaz (idéntica al artifact) y Laravel.
 * - Inicio de sesión real contra /login y cierre con /logout.
 * - Si ya hay sesión, entra directo al escritorio en el almacén guardado.
 * - Guarda en la base de datos los cambios que la interfaz hace sobre
 *   ingresos, pedidos e historial de importaciones (recepción y picking desde
 *   el colector, cierre de recepción, cargas confirmadas).
 */
(function () {
  const headers = () => ({ "Content-Type": "application/json", Accept: "application/json", "X-CSRF-TOKEN": CW.csrf, "X-Requested-With": "XMLHttpRequest" });

  async function api(method, url, body) {
    const r = await fetch(url, { method, headers: headers(), credentials: "same-origin", body: body ? JSON.stringify(body) : undefined });
    let data = null;
    try { data = await r.json(); } catch (e) {}
    if (!r.ok) { const err = new Error((data && (data.message || data.error)) || `HTTP ${r.status}`); err.status = r.status; err.data = data; throw err; }
    return data;
  }

  /* ---------- usuario en la barra superior ---------- */
  function paintUser() {
    if (!CW.user) return;
    const initials = CW.user.nombre.split(/\s+/).filter(Boolean).slice(0, 2).map(w => w[0]).join("").toUpperCase();
    const a = document.getElementById("uAvatar"), n = document.getElementById("uName"), r = document.getElementById("uRole");
    if (a) a.textContent = initials || "··";
    if (n) n.textContent = CW.user.nombre;
    if (r) r.textContent = CW.user.rol;
  }

  /* ---------- login ---------- */
  document.addEventListener("submit", async e => {
    if (!e.target || e.target.id !== "loginForm") return;
    e.preventDefault(); e.stopImmediatePropagation();
    const f = e.target, btn = f.querySelector('button[type="submit"]'), msg = document.getElementById("lgMsg");
    btn.disabled = true; btn.textContent = "Verificando…";
    try {
      await api("POST", CW.routes.login, { username: f.username.value.trim(), password: f.password.value, wh: f.wh.value });
      try { sessionStorage.setItem("cw-welcome", "1"); } catch (x) {}
      location.reload();
    } catch (err) {
      btn.disabled = false; btn.textContent = "Entrar";
      const text = err.status === 422 && err.data && err.data.errors ? Object.values(err.data.errors).flat()[0] : err.status === 419 ? "La sesión expiró. Recarga la página e intenta de nuevo." : "No se pudo iniciar sesión.";
      if (msg) { msg.innerHTML = `<b style="color:var(--bad)">${esc(text)}</b>`; }
      if (typeof toast === "function") toast(text, "bad");
    }
  }, true);

  /* ---------- logout ---------- */
  document.addEventListener("click", e => {
    const t = e.target.closest && e.target.closest("[data-logout]");
    if (!t) return;
    e.preventDefault(); e.stopImmediatePropagation();
    api("POST", CW.routes.logout).catch(() => {}).finally(() => { try { sessionStorage.removeItem("cw-welcome"); } catch (x) {} location.href = CW.routes.home; });
  }, true);

  /* ---------- almacén de trabajo en sesión ---------- */
  const whSel = document.getElementById("ctxWh");
  if (whSel) whSel.addEventListener("change", () => { if (CW.user) api("POST", CW.routes.warehouse, { wh: whSel.value }).catch(() => {}); });

  /* ---------- persistencia de cambios ---------- */
  const WATCH = { INGRESOS: () => INGRESOS, PEDIDOS: () => PEDIDOS };
  const KEY = { INGRESOS: "nro", PEDIDOS: "nro" };
  const plain = (name, rec) => name === "PEDIDOS" ? Object.assign({}, rec, { cliente: rec.cliente && rec.cliente.codigo !== undefined ? rec.cliente.codigo : rec.cliente }) : rec;
  const snap = {};
  let importCount = 0;

  function takeSnapshot() {
    for (const name in WATCH) { snap[name] = {}; WATCH[name]().forEach(r => { snap[name][r[KEY[name]]] = JSON.stringify(plain(name, r)); }); }
    importCount = IMPORT_HISTORY.length;
  }

  let saving = false, failed = false;
  async function flush() {
    if (!CW.user || saving) return;
    saving = true;
    try {
      // Picking terminado en el colector: registrar lo recogido en el pedido.
      if (typeof col !== "undefined" && col.screen === "pick" && col.task && col.task.lines && col.line >= col.task.lines.length) {
        col.task.lines.forEach(l => { if (!(l.pick >= l.qty)) l.pick = l.qty; });
      }
      for (const name in WATCH) {
        for (const r of WATCH[name]()) {
          const k = r[KEY[name]], cur = JSON.stringify(plain(name, r));
          if (snap[name][k] === cur) continue;
          await api("PUT", `${CW.routes.api}/${name}/${encodeURIComponent(k)}`, JSON.parse(cur));
          snap[name][k] = cur;
        }
      }
      while (IMPORT_HISTORY.length > importCount) {
        const rec = IMPORT_HISTORY[IMPORT_HISTORY.length - importCount - 1];
        await api("POST", `${CW.routes.api}/IMPORT_HISTORY`, rec);
        importCount++;
      }
      if (failed) { failed = false; if (typeof toast === "function") toast("Conexión restablecida. Cambios guardados en el servidor.", "ok"); }
    } catch (err) {
      if (!failed && typeof toast === "function") toast("No se pudieron guardar los cambios en el servidor. Se reintentará.", "bad");
      failed = true;
      if (err.status === 401 || err.status === 419) location.reload();
    } finally { saving = false; }
  }

  /* ---------- arranque ---------- */
  paintUser();
  if (CW.user) {
    takeSnapshot();
    setInterval(flush, 2500);
    document.addEventListener("visibilitychange", () => { if (document.visibilityState === "hidden") flush(); });
    const lw = document.getElementById("lg-wh");
    if (lw && CW.wh) lw.value = CW.wh;
    let welcome = false;
    try { welcome = sessionStorage.getItem("cw-welcome") === "1"; sessionStorage.removeItem("cw-welcome"); } catch (x) {}
    if (welcome) { enter(); }
    else {
      state.wh = (lw && lw.value) || CW.wh || state.wh;
      document.getElementById("login").hidden = true; document.getElementById("app").hidden = false;
      buildNav(); applyRail();
      const h = location.hash.replace("#", ""); if (VIEWS[h]) state.route = h;
      render();
    }
  }
})();
