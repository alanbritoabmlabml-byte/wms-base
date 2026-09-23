/* Carmen WMS · operación: inicio, ingresos, pedidos y despachos. */
"use strict";

/* ===================================================================== INICIO */
function computedAlerts() {
  const out = [];
  DEVICES.filter(d => d.wh === state.wh && !d.online && d.cola).forEach(d => out.push({ k: "bad", t: `${d.id} sin conexión con ${d.cola} movimientos en cola`, s: `${userName(d.user)} · ${d.wh}`, go: "dispositivos" }));
  const bajo = PRODUCTS.filter(p => p.estado === "ACTIVO" && p.min > 0 && stockOf(p.codigo) < p.min);
  if (bajo.length) out.push({ k: "warn", t: `${bajo.length} producto(s) bajo el mínimo`, s: bajo.slice(0, 3).map(p => p.codigo).join(", ") + (bajo.length > 3 ? "…" : ""), go: "stock", sub: "minmax" });
  const adj = whAdj().filter(a => a.estado === "Pendiente");
  if (adj.length) out.push({ k: "warn", t: `${adj.length} ajuste(s) pendientes de aprobación`, s: "Stock e inventario › Ajustes", go: "stock", sub: "ajustes" });
  whCnt().filter(c => ["Habilitado", "En curso"].includes(c.estado)).forEach(c => out.push({ k: c.dif ? "warn" : "info", t: `Conteo ${c.nro} · ${c.contadas}/${c.ubic} ubicaciones${c.dif ? ` · ${c.dif} diferencias` : ""}`, s: `${c.tipo} · ${userName(c.resp)}`, count: c.nro }));
  const lim = daysAgoISO(-setting("alertaVenc"));
  const venc = whStock().filter(s => s.venc && s.venc <= lim);
  if (venc.length) out.push({ k: "bad", t: `${venc.length} saldo(s) vencidos o por vencer en ${setting("alertaVenc")} días`, s: venc.slice(0, 2).map(s => `${s.codigo} · ${fmtD(s.venc)}`).join(" · "), go: "stock", sub: "saldos", filt: ["stockEst", "Vencido"] });
  const urg = whPed().filter(p => p.prioridad === "Urgente" && p.estado === "Recibido");
  if (urg.length) out.push({ k: "bad", t: `${urg.length} pedido(s) urgentes sin liberar`, s: urg.map(p => p.nro).join(", "), go: "salidas" });
  const muelle = whStock().filter(s => zoneOf(s.loc) === "Recepción");
  if (muelle.length) out.push({ k: "info", t: `${fmt(sum(muelle, s => s.qty))} unidades en el muelle pendientes de ubicar`, s: `${muelle.length} saldo(s) · usa «Ubicar» en el colector`, go: "stock", sub: "saldos", filt: ["stockQ", "ING-"] });
  if (!out.length) out.push({ k: "ok", t: "Todo en orden", s: "No hay alertas para este almacén." });
  return out;
}
const alertHtml = a => `<li ${a.go ? `class="clickable" data-go="${a.go}" ${a.sub ? `data-sub="${a.sub}"` : ""} ${a.filt ? `data-filt="${a.filt.join(":")}"` : ""}` : a.count ? `class="clickable" data-count="${a.count}"` : ""}><span class="ic ${a.k}">${ic(a.k === "ok" ? "check" : a.k === "info" ? "cloud" : "alert")}</span><div><b>${esc(a.t)}</b><span>${esc(a.s)}</span></div></li>`;
function weekFlow() {
  const days = Array.from({ length: 7 }, (_, i) => daysAgoISO(6 - i)); const kd = whKdx();
  const lbl = ["Do", "Lu", "Ma", "Mi", "Ju", "Vi", "Sá"];
  return { labels: days.map(d => lbl[new Date(d + "T12:00").getDay()]), in: days.map(d => sum(kd.filter(k => k.ts.startsWith(d) && k.dir === "in"), k => Math.abs(k.qty))), out: days.map(d => sum(kd.filter(k => k.ts.startsWith(d) && k.dir === "out"), k => Math.abs(k.qty))) };
}
function iraSeries() { return whCnt().filter(c => c.estado === "Finalizado").sort((a, b) => a.nro - b.nro).map(c => +(c.ira * 100).toFixed(1)); }
function linesPerHour() {
  const kd = whKdx().filter(k => k.tipo === "Picking" && k.ts >= daysAgoISO(7));
  const byDay = Array.from({ length: 7 }, (_, i) => daysAgoISO(6 - i)).map(d => { const r = kd.filter(k => k.ts.startsWith(d)); const ops = new Set(r.map(k => k.user)).size; return ops ? r.length / ops / 8 : 0; });
  const ops = new Set(kd.map(k => k.user + k.ts.slice(0, 10))).size;
  return { v: ops ? kd.length / ops / 8 : 0, series: byDay };
}
VIEWS.inicio = () => {
  const wh = WH(); const peds = whPed(); const pend = peds.filter(p => p.estado !== "Despachado");
  const usable = whLocs().filter(l => !isVirtualLoc(l.id)); const occ = usable.filter(l => STOCK.some(s => s.loc === l.id)).length;
  const occPct = usable.length ? Math.round(occ / usable.length * 100) : 0;
  const bajoMin = PRODUCTS.filter(p => p.estado === "ACTIVO" && p.min > 0 && stockOf(p.codigo) < p.min);
  const devs = DEVICES.filter(d => d.wh === state.wh); const cola = sum(devs, d => d.cola) + Q.n;
  const ira = iraSeries(); const lph = linesPerHour(); const flow = weekFlow();
  const rackOcc = whRacks().filter(r => !VIRTUAL_ZONES.includes(r.zona)).map(r => { const ls = LOCATIONS.filter(l => l.rack === r.id); return { l: r.id, v: ls.length ? Math.round(ls.filter(l => STOCK.some(s => s.loc === l.id)).length / ls.length * 100) : 0 }; });
  const alerts = computedAlerts();
  return `
  ${pageHead(`${CW.greeting}, ${esc(CW.user.first)}`, `${esc(wh.name)} · ${esc(CW.today)} · turno ${shiftNow()} en curso`, `${can("aprobar") ? `<button class="btn" data-a="cnt-new">${ic("count")} Nuevo conteo</button>` : ""}${can("recibir") ? `<button class="btn" data-a="ing-new">${ic("in")} Orden de ingreso</button>` : ""}${can("picking") ? `<button class="btn primary" data-a="wave-new">${ic("zap")} Liberar ola de picking</button>` : ""}`)}
  <div class="grid g-4" style="margin-bottom:16px">
    <div class="card kpi clickable" data-go="stock" data-sub="conteos"><span class="lbl">Exactitud por ubicación (IRA)</span><span class="val">${ira.length ? String(ira[ira.length - 1]).replace(".", ",") : "—"}<small>%</small></span><span class="delta ${ira.length > 1 && ira[ira.length - 1] >= ira[ira.length - 2] ? "up" : ""}">${ira.length > 1 ? `${ic("arrow")} ${(ira[ira.length - 1] - ira[ira.length - 2] >= 0 ? "+" : "")}${(ira[ira.length - 1] - ira[ira.length - 2]).toFixed(1).replace(".", ",")} pts vs. conteo anterior` : "según el último conteo finalizado"}</span>${sparkline(ira.length ? ira : [0])}</div>
    <div class="card kpi clickable" data-go="salidas"><span class="lbl">Pedidos en proceso</span><span class="val">${pend.length}<small>de ${peds.length}</small></span><span class="delta">${pend.filter(p => p.prioridad === "Urgente").length} urgentes · ${peds.filter(p => p.estado === "Recibido").length} sin liberar</span>${sparkline(OUT_STATES.slice(0, 4).map(s => peds.filter(p => p.estado === s).length))}</div>
    <div class="card kpi clickable" data-go="ubicaciones"><span class="lbl">Ocupación de ubicaciones</span><span class="val">${occPct}<small>%</small></span><span class="delta">${fmt(occ)} de ${fmt(usable.length)} ubicaciones con stock</span>${sparkline(rackOcc.map(r => r.v))}</div>
    <div class="card kpi clickable" data-go="stock" data-sub="kardex"><span class="lbl">Líneas por hora / operador</span><span class="val">${lph.v ? lph.v.toFixed(1).replace(".", ",") : "—"}</span><span class="delta">picking de los últimos 7 días</span>${sparkline(lph.series)}</div>
  </div>
  <div class="grid g-2-1" style="margin-bottom:16px">
    <div class="card"><div class="card-h"><h3>Flujo de la semana · unidades</h3><div class="legend"><span><i style="background:var(--primary)"></i>Ingresos ${fmt(sum(flow.in))}</span><span><i style="background:var(--accent)"></i>Salidas ${fmt(sum(flow.out))}</span></div></div><div class="card-b">${lineChart([{ v: flow.in, c: "var(--primary)" }, { v: flow.out, c: "var(--accent)" }], { labels: flow.labels, w: 640, h: 200 })}</div></div>
    <div class="card"><div class="card-h"><h3>Requiere atención</h3><span class="pill ${alerts[0].k === "ok" ? "ok" : "warn"}">${alerts[0].k === "ok" ? 0 : alerts.length}</span></div><div class="card-b" style="padding:8px 16px"><ul class="timeline">${alerts.slice(0, 7).map(alertHtml).join("")}</ul></div></div>
  </div>
  <div class="grid g-3">
    <div class="card"><div class="card-h"><h3>Ocupación por rack</h3><span class="muted">${esc(state.wh)} · %</span></div><div class="card-b">${barChart(rackOcc, { w: 420, h: 170, hi: rackOcc.length ? rackOcc.reduce((a, b) => a.v > b.v ? a : b).l : null, unit: "%" })}</div></div>
    <div class="card"><div class="card-h"><h3>Bajo el mínimo</h3><span class="pill ${bajoMin.length ? "warn" : "ok"}">${bajoMin.length} SKU</span></div>${table([{ h: "SKU", f: r => `<span class="mono link" data-prod="${esc(r.codigo)}">${esc(r.codigo)}</span><div class="small muted">${esc(r.desc)}</div>` }, { h: "Stock", a: "r", f: r => `<b class="num">${fmt(stockOf(r.codigo))}</b> <span class="muted small">/ mín ${fmt(r.min)}</span>` }], bajoMin.slice(0, 5), { empty: "Todos los productos sobre el mínimo" })}${bajoMin.length > 5 ? `<div class="card-b"><button class="btn sm block" data-go="stock" data-sub="minmax">Ver los ${bajoMin.length}</button></div>` : ""}</div>
    <div class="card"><div class="card-h"><h3>Colectores</h3><span class="pill ${cola ? "warn" : "ok"}">${cola} mov. en cola</span></div><div class="card-b stack">${devs.length ? devs.slice(0, 6).map(d => `<div class="row between clickable" data-a="dev-edit" data-id="${esc(d.id)}"><div class="row"><span class="pill ${d.online ? "ok" : "bad"} plain" style="padding:2px 6px">${d.online ? "●" : "○"}</span><div><b class="small">${esc(d.id)}</b> <span class="muted small">· ${esc(userName(d.user))}</span></div></div><div class="row" style="width:120px"><div class="bar ${d.bat < 20 ? "bad" : d.bat < 50 ? "warn" : "ok"}" style="flex:1"><i style="width:${d.bat}%"></i></div><span class="small num muted" style="width:34px;text-align:right">${d.bat}%</span></div></div>`).join("") : `<div class="empty small">Sin colectores en ${esc(state.wh)}</div>`}</div></div>
  </div>`;
};

/* ===================================================================== INGRESOS */
const ingTotals = o => ({ t: sum(o.lines, l => l.qty), rc: sum(o.lines, l => l.rec) });
VIEWS.ingresos = () => {
  const f = fv("ing"), tipo = fv("ingTipo"), q = fq("ingQ");
  const all = whIng();
  const rows = all.filter(i => (f === "Todos" || i.estado === f) && (tipo === "Todos" || i.tipo === tipo) && (!q || has(i.nro, q) || has(i.doc, q) || has(i.origen, q) || i.lines.some(l => has(l.lote, q) || has(l.codigo, q))))
    .sort((a, b) => String(b.fecha).localeCompare(String(a.fecha)) || String(b.nro).localeCompare(String(a.nro)));
  const counts = s => all.filter(i => i.estado === s).length;
  return `
  ${pageHead("Ingresos", "Órdenes de ingreso desde producción, compras, devoluciones y transferencias.", `<button class="btn" data-a="imp-open" data-ds="ingresos">${ic("upload")} Importar órdenes</button>${can("recibir") ? `<button class="btn primary" data-a="ing-new">${ic("plus")} Nueva orden de ingreso</button>` : ""}`)}
  <div class="grid g-4" style="margin-bottom:16px">
    ${[["Habilitado", "Listas para recibir", "warn"], ["En recepción", "Recibiendo ahora", "info"], ["Cerrado", "Cerradas", "ok"], ["Borrador", "Borradores", "neutral"]].map(([s, l, k]) => `<button class="card kpi" style="text-align:left;cursor:pointer;border-color:${f === s ? "var(--navy)" : "var(--line)"}" data-f="ing" data-v="${f === s ? "Todos" : s}"><span class="lbl">${l}</span><span class="val">${counts(s)}</span><span class="pill ${k}">${s}</span></button>`).join("")}
  </div>
  <div class="card">
    <div class="toolbar">${searchBox("ingQ", "Nro, documento origen, lote, SKU…")}${chipBar("ingTipo", ["Todos", ...ING_TYPES])}<div class="spacer" style="flex:1"></div>${f !== "Todos" ? `<button class="chip on" data-f="ing" data-v="Todos">${esc(f)} ✕</button>` : ""}<button class="btn sm" data-a="ing-export">${ic("download")} Exportar</button></div>
    ${table([
      { h: "Orden", f: r => `<span class="mono link">${esc(r.nro)}</span><div class="small muted">${esc(r.doc)}</div>` },
      { h: "Fecha", f: r => fmtD(r.fecha) },
      { h: "Tipo", f: r => `<span class="tag">${esc(r.tipo)}</span>` },
      { h: "Origen", f: r => esc(r.origen) },
      { h: "Líneas", a: "r", f: r => r.lines.length },
      { h: "Avance", f: r => { const { t, rc } = ingTotals(r); const p = t ? Math.round(rc / t * 100) : 0; return `<div class="row"><div class="bar ${p >= 100 ? "ok" : ""}" style="width:90px"><i style="width:${Math.min(100, p)}%"></i></div><span class="small num">${p}%</span></div>`; } },
      { h: "Muelle", f: r => `<span class="mono small">${esc(r.muelle)}</span>` },
      { h: "Estado", f: r => pillFor(r.estado) },
      { h: "", f: r => `<span class="btn sm ghost">${ic("chev")}</span>` },
    ], rows, { rowAttr: r => `class="clickable" data-ing="${esc(r.nro)}"`, id: "ing", empty: "No hay órdenes con esos filtros" })}
  </div>`;
};
ACTIONS["ing-export"] = () => exportXLSX(`ingresos_${state.wh}_${stamp()}`, { name: "Ingresos", cols: [{ h: "nro", v: r => r.o.nro }, { h: "fecha", v: r => r.o.fecha }, { h: "tipo", v: r => r.o.tipo }, { h: "documento", v: r => r.o.doc }, { h: "origen", v: r => r.o.origen }, { h: "muelle", v: r => r.o.muelle }, { h: "estado", v: r => r.o.estado }, { h: "codigo", v: r => r.l.codigo }, { h: "descripcion", v: r => P(r.l.codigo).desc }, { h: "lote", v: r => r.l.lote }, { h: "turno", v: r => r.l.turno }, { h: "cantidad", v: r => r.l.qty }, { h: "recibido", v: r => r.l.rec }, { h: "diferencia", v: r => r.l.rec - r.l.qty }], rows: whIng().flatMap(o => o.lines.map(l => ({ o, l }))) });

DETAIL.ing = nro => {
  const o = INGRESOS.find(i => i.nro === nro); if (!o) return;
  const { t, rc } = ingTotals(o);
  const stepIdx = o.estado === "Anulado" ? -1 : ["Borrador", "Habilitado", "En recepción", "Cerrado"].indexOf(o.estado);
  const edit = can("recibir");
  const foot = !edit ? `<button class="btn" data-close>Cerrar</button>` :
    o.estado === "Borrador" ? `<button class="btn danger" data-a="ing-del" data-nro="${esc(nro)}">${ic("trash")} Eliminar</button><button class="btn" data-a="ing-edit" data-nro="${esc(nro)}">${ic("edit")} Editar</button><button class="btn primary" data-a="ing-enable" data-nro="${esc(nro)}">Habilitar para recepción</button>` :
    o.estado === "Habilitado" ? `<button class="btn" data-a="ing-cancel" data-nro="${esc(nro)}">Anular</button><button class="btn" data-a="ing-edit" data-nro="${esc(nro)}">${ic("edit")} Editar</button><button class="btn" data-a="ing-col" data-nro="${esc(nro)}">${ic("phone")} Recibir en colector</button><button class="btn primary" data-a="ing-recv" data-nro="${esc(nro)}">${ic("in")} Registrar recepción</button>` :
    o.estado === "En recepción" ? `<button class="btn" data-a="ing-labels" data-nro="${esc(nro)}">${ic("print")} Etiquetas</button><button class="btn" data-a="ing-recv" data-nro="${esc(nro)}">${ic("in")} Registrar recepción</button><button class="btn primary" data-a="ing-close" data-nro="${esc(nro)}">${rc < t ? "Cerrar con diferencias" : "Cerrar recepción"}</button>` :
    o.estado === "Cerrado" ? `<button class="btn" data-a="ing-labels" data-nro="${esc(nro)}">${ic("print")} Etiquetas</button><button class="btn" data-a="ing-print" data-nro="${esc(nro)}">${ic("file")} Imprimir orden</button><button class="btn primary" data-close>Cerrar</button>` :
    `<button class="btn danger" data-a="ing-del" data-nro="${esc(nro)}">${ic("trash")} Eliminar</button><button class="btn primary" data-close>Cerrar</button>`;
  const pendUbicar = sum(whStock(o.wh || "BOL2").filter(s => s.loc === o.muelle && o.lines.some(l => l.codigo === s.codigo && l.lote === s.lote)), s => s.qty);
  openDrawer(`Orden ${esc(o.nro)}`, `
    ${o.estado === "Anulado" ? `<div class="card" style="box-shadow:none;margin-bottom:14px;border-color:var(--bad)"><div class="card-b small">${ic("alert")} Orden anulada. ${esc(o.obs || "")}</div></div>` : `<div class="steps" style="margin-bottom:18px">${["Creada", "Habilitada", "En recepción", "Cerrada"].map((s, i) => `<div class="st ${i < stepIdx || o.estado === "Cerrado" ? "done" : i === stepIdx ? "cur" : ""}">${s}</div>`).join("")}</div>`}
    <div class="grid g-2" style="margin-bottom:16px"><dl class="kv"><dt>Tipo</dt><dd>${esc(o.tipo)}</dd><dt>Origen</dt><dd>${esc(o.origen)}</dd><dt>Documento</dt><dd class="mono">${esc(o.doc)}</dd><dt>Fecha</dt><dd>${fmtD(o.fecha)}</dd></dl><dl class="kv"><dt>Muelle</dt><dd class="mono">${esc(o.muelle)}</dd><dt>Registró</dt><dd>${esc(userName(o.usuario))}</dd><dt>Recibido</dt><dd class="num">${fmt(rc)} / ${fmt(t)} un.</dd><dt>Estado</dt><dd>${pillFor(o.estado)}</dd></dl></div>
    ${table([{ h: "SKU", f: l => `<span class="mono link" data-prod="${esc(l.codigo)}">${esc(l.codigo)}</span><div class="small muted">${esc(P(l.codigo).desc)}</div>` }, { h: "Lote / turno", f: l => `<span class="mono small">${esc(l.lote)}</span> <span class="tag">${esc(l.turno)}</span>` }, { h: "Esperado", a: "r", f: l => fmt(l.qty) }, { h: "Recibido", a: "r", f: l => `<b class="num" style="color:${l.rec >= l.qty ? "var(--ok)" : l.rec ? "var(--warn)" : "var(--ink-3)"}">${fmt(l.rec)}</b>` }, { h: "Dif.", a: "r", f: l => l.rec - l.qty ? `<span class="pill ${l.rec - l.qty < 0 ? "warn" : "bad"} plain num">${l.rec - l.qty > 0 ? "+" : ""}${fmt(l.rec - l.qty)}</span>` : `<span class="muted">—</span>` }], o.lines)}
    ${o.obs && o.estado !== "Anulado" ? `<p class="small" style="margin:12px 0 0"><b>Observación:</b> ${esc(o.obs)}</p>` : ""}
    <p class="small muted" style="margin:14px 0 0">Recibir deja la mercadería en el muelle <b class="mono">${esc(o.muelle)}</b>${pendUbicar ? ` (hoy hay <b>${fmt(pendUbicar)}</b> un. de esta orden por ubicar)` : ""}; ubicarla en rack se hace con <b>Ubicar</b> en el colector o <b>Reubicar</b> en Stock.</p>`,
    foot, `Ingresos › ${esc(o.tipo)}`);
  state.drawerFn = () => DETAIL.ing(nro);
};

function ingLineRow(l = {}) {
  return `<tr class="ln"><td style="min-width:220px"><input class="input mono" name="codigo" data-ac="prod" placeholder="Código o descripción" value="${esc(l.codigo || "")}" autocomplete="off"><div class="small muted ln-desc">${l.codigo ? esc(P(l.codigo).desc) : ""}</div></td><td><input class="input mono" name="lote" style="width:130px" value="${esc(l.lote || lotCode())}"></td><td><select class="select" name="turno" style="width:80px">${opts(["T1", "T2", "T3"], l.turno || shiftNow())}</select></td><td class="r"><input class="input num" name="qty" type="number" min="1" style="width:96px;text-align:right" value="${l.qty || ""}"></td><td><button class="btn sm ghost icon" type="button" data-a="row-del" title="Quitar línea">${ic("x")}</button></td></tr>`;
}
function ingModal(o = null) {
  const muelles = recepLocs();
  if (!muelles.length) { toast(`El almacén ${esc(state.wh)} no tiene un muelle de recepción. Créalo en Mapa de almacén → Nuevo rack / área (zona «Recepción»).`, "bad", 7000); return; }
  const isNew = !o;
  openModal(isNew ? "Nueva orden de ingreso" : `Editar orden ${esc(o.nro)}`, `
    <div class="grid g-2" style="margin-bottom:14px">
      ${fld("Tipo de movimiento", `<select class="select" name="tipo">${opts(ING_TYPES, o?.tipo)}</select>`)}
      ${fld("Documento origen", inp("doc", o?.doc || "", `placeholder="OP-4471 / OC-982 / NDV-…"`))}
      ${fld("Origen", inp("origen", o?.origen || "", `placeholder="Extrusora EX-02 / proveedor / sucursal"`))}
      ${fld("Muelle de recepción", `<select class="select" name="muelle">${muelles.map(m => opt(m.id, o?.muelle, `${m.id} · ${(R(m.rack) || {}).name}`)).join("")}</select>`)}
      ${fld("Fecha", `<input class="input" type="date" name="fecha" value="${esc(o?.fecha || todayISO())}">`)}
    </div>
    <div class="card" style="box-shadow:none"><div class="card-h"><h3>Líneas</h3><button class="btn sm" type="button" data-a="ing-addline">${ic("plus")} Agregar línea</button></div>
    <div class="tbl-wrap"><table class="tbl"><thead><tr><th>SKU</th><th>Lote</th><th>Turno</th><th class="r">Cantidad</th><th></th></tr></thead><tbody id="ingLines">${(o?.lines?.length ? o.lines : [{}]).map(ingLineRow).join("")}</tbody></table></div></div>
    ${fld("Observación", `<textarea class="input" name="obs" rows="2" placeholder="Opcional">${esc(o?.obs || "")}</textarea>`, "")}`,
    `<button class="btn" data-close>Cancelar</button><button class="btn" data-a="ing-save" data-estado="Borrador" data-nro="${esc(o?.nro || "")}">Guardar borrador</button><button class="btn primary" data-a="ing-save" data-estado="Habilitado" data-nro="${esc(o?.nro || "")}">${isNew ? "Registrar y habilitar" : "Guardar y habilitar"}</button>`, { wide: true });
}
ACTIONS["ing-new"] = () => ingModal();
ACTIONS["ing-edit"] = b => ingModal(INGRESOS.find(i => i.nro === b.dataset.nro));
ACTIONS["ing-addline"] = () => { $("#ingLines").insertAdjacentHTML("beforeend", ingLineRow()); $("#ingLines tr:last-child input").focus(); };
ACTIONS["row-del"] = b => { const tb = b.closest("tbody"); if (tb.children.length > 1) b.closest("tr").remove(); else $$("input", b.closest("tr")).forEach(i => { if (i.name !== "lote") i.value = ""; }); };
document.addEventListener("ac:pick", e => { const d = e.target.closest("tr.ln")?.querySelector(".ln-desc"); if (d && e.target.dataset.ac === "prod") d.textContent = P(e.detail.code).desc + (e.target.closest("#pedLines") ? ` · disponible ${fmt(availOf(e.detail.code))}` : ""); });
function readLines(tbodySel, { lote = true } = {}) {
  const lines = []; const errs = [];
  $$(`${tbodySel} tr.ln`).forEach((tr, i) => {
    const v = formVals(tr); if (!v.codigo && !v.qty) return;
    if (!PRODUCTS.some(p => p.codigo === v.codigo)) errs.push(`Línea ${i + 1}: el producto «${v.codigo}» no existe`);
    else if (!(v.qty > 0)) errs.push(`Línea ${i + 1}: cantidad inválida`);
    else lines.push(lote ? { codigo: v.codigo, qty: Math.round(v.qty), rec: 0, lote: v.lote || lotCode(v.turno), turno: v.turno || shiftNow() } : { codigo: v.codigo, qty: Math.round(v.qty), pick: 0 });
  });
  if (!lines.length && !errs.length) errs.push("Agrega al menos una línea con producto y cantidad.");
  return { lines, errs };
}
ACTIONS["ing-save"] = b => run(b, async () => {
  const v = formVals($("#modalBox")); const { lines, errs } = readLines("#ingLines");
  if (errs.length) throw new Error(errs[0]);
  const old = INGRESOS.find(i => i.nro === b.dataset.nro);
  if (old) lines.forEach(l => { const p = old.lines.find(x => x.codigo === l.codigo && x.lote === l.lote); if (p) l.rec = p.rec; });
  const rec = { ...(old || {}), nro: old ? old.nro : nextNro("ING", INGRESOS), fecha: v.fecha || todayISO(), tipo: v.tipo, origen: v.origen || "—", estado: b.dataset.estado, lines, doc: v.doc || "—", usuario: old ? old.usuario : me(), muelle: v.muelle, wh: state.wh, obs: v.obs || null };
  await save("INGRESOS", rec); closeOverlays(); render(); buildNav();
  toast(`Orden <b>${esc(rec.nro)}</b> ${rec.estado === "Habilitado" ? "habilitada: ya aparece en los colectores del muelle" : "guardada como borrador"}.`);
});
ACTIONS["ing-enable"] = b => run(b, async () => { const o = INGRESOS.find(i => i.nro === b.dataset.nro); o.estado = "Habilitado"; await save("INGRESOS", o); render(); buildNav(); DETAIL.ing(o.nro); toast(`Orden ${esc(o.nro)} habilitada para recepción.`); });
ACTIONS["ing-del"] = b => run(b, async () => { if (!await confirmDlg("Eliminar orden", `Se eliminará la orden <b>${esc(b.dataset.nro)}</b>. Esta acción no se puede deshacer.`, { ok: "Eliminar", danger: true })) return; await del("INGRESOS", b.dataset.nro); closeOverlays(); render(); buildNav(); toast("Orden eliminada.", "info"); });
ACTIONS["ing-cancel"] = b => run(b, async () => {
  const o = INGRESOS.find(i => i.nro === b.dataset.nro);
  if (o.lines.some(l => l.rec > 0)) throw new Error("La orden ya tiene mercadería recibida: ciérrala con diferencias en lugar de anularla.");
  const v = await dialog("Anular orden", fld("Motivo", `<textarea class="input" name="motivo" rows="2" required></textarea>`), { ok: "Anular orden", danger: true, validate: v => v.motivo ? null : "Indica el motivo." }); if (!v) return;
  o.estado = "Anulado"; o.obs = `Anulada por ${me()} el ${nowHuman()}: ${v.motivo}`; await save("INGRESOS", o); render(); buildNav(); DETAIL.ing(o.nro); toast("Orden anulada.", "info");
});
ACTIONS["ing-col"] = b => { closeOverlays(); openCollector(); colAction("recv", { dataset: { ing: b.dataset.nro } }); };
ACTIONS["ing-recv"] = b => {
  const o = INGRESOS.find(i => i.nro === b.dataset.nro);
  const blind = (o.tipo === "Compra" && setting("ciegaCompras")) || (o.tipo === "Producción" && setting("ciegaProduccion"));
  openModal(`Registrar recepción · ${esc(o.nro)}`, `<p class="muted" style="margin:0 0 12px">Ingresa lo que llegó al muelle <b class="mono">${esc(o.muelle)}</b>. Se suma a lo ya recibido y queda en el kardex a tu nombre.${blind ? " <b>Recepción ciega:</b> la cantidad esperada está oculta." : ""}</p>
    ${table([{ h: "SKU", f: l => `<span class="mono">${esc(l.codigo)}</span><div class="small muted">${esc(P(l.codigo).desc)}</div>` }, { h: "Lote", f: l => `<span class="mono small">${esc(l.lote)}</span>` }, { h: "Esperado", a: "r", f: l => blind ? "—" : fmt(l.qty) }, { h: "Recibido", a: "r", f: l => fmt(l.rec) }, { h: "Recibir ahora", a: "r", f: (l, i) => `<input class="input num" type="number" min="0" name="q${i}" style="width:100px;text-align:right" value="${blind ? "" : Math.max(0, l.qty - l.rec)}">` }], o.lines)}`,
    `<button class="btn" data-close>Cancelar</button><button class="btn primary" data-a="ing-recv-ok" data-nro="${esc(o.nro)}">${ic("check")} Registrar</button>`, { wide: true });
};
async function receiveLines(o, qtys) {
  const movs = []; o.lines.forEach((l, i) => { const q = Math.round(+qtys[i] || 0); if (q > 0) movs.push({ tipo: o.tipo === "Devolución" ? "Devolución" : "Ingreso", dir: "in", codigo: l.codigo, lote: l.lote, qty: q, from: o.origen || "—", to: o.muelle, doc: o.nro, venc: P(l.codigo).vidaUtil ? daysAgoISO(-P(l.codigo).vidaUtil) : null }); });
  if (!movs.length) throw new Error("No ingresaste ninguna cantidad.");
  await mov(movs, o.wh || state.wh);
  o.lines.forEach((l, i) => { l.rec += Math.round(+qtys[i] || 0); });
  if (["Habilitado", "Borrador"].includes(o.estado)) o.estado = "En recepción";
  await save("INGRESOS", o);
  return sum(movs, m => m.qty);
}
ACTIONS["ing-recv-ok"] = b => run(b, async () => {
  const o = INGRESOS.find(i => i.nro === b.dataset.nro); const v = formVals($("#modalBox"));
  const n = await receiveLines(o, o.lines.map((_, i) => v[`q${i}`]));
  closeModal(); render(); buildNav(); DETAIL.ing(o.nro);
  toast(`${fmt(n)} unidades recibidas en ${esc(o.muelle)}.${o.lines.every(l => l.rec >= l.qty) ? " La orden está completa: ya puedes cerrarla." : ""}`);
});
async function closeIngreso(o) {
  const { t, rc } = ingTotals(o);
  if (rc === 0) throw new Error("No se recibió nada: anula la orden en lugar de cerrarla.");
  if (rc !== t) {
    const v = await dialog("Cerrar con diferencias", `<p style="margin:0 0 10px">Se recibieron <b>${fmt(rc)}</b> de <b>${fmt(t)}</b> unidades. Las diferencias quedarán registradas a tu nombre.</p>${fld("Observación", `<textarea class="input" name="obs" rows="2"></textarea>`)}`, { ok: "Cerrar orden", validate: v => v.obs ? null : "Explica la diferencia." });
    if (!v) return false; o.obs = `${o.obs ? o.obs + " · " : ""}Cerrada con diferencias por ${me()} (${nowHuman()}): ${v.obs}`;
  }
  o.estado = "Cerrado"; await save("INGRESOS", o); return true;
}
ACTIONS["ing-close"] = b => run(b, async () => { const o = INGRESOS.find(i => i.nro === b.dataset.nro); if (await closeIngreso(o)) { render(); buildNav(); DETAIL.ing(o.nro); toast(`Orden ${esc(o.nro)} cerrada. La mercadería queda en el muelle hasta ubicarla.`); } });
ACTIONS["ing-labels"] = b => { const o = INGRESOS.find(i => i.nro === b.dataset.nro); printLabels("item", o.lines.filter(l => l.rec > 0 || o.estado !== "Cerrado").map(l => itemSample(l.codigo, l.lote, l.rec || l.qty, o.fecha)), `Etiquetas ${o.nro}`); };
ACTIONS["ing-print"] = b => {
  const o = INGRESOS.find(i => i.nro === b.dataset.nro); const { t, rc } = ingTotals(o);
  printHTML(`Orden ${o.nro}`, `<div style="padding:4mm"><div class="brand">PLÁSTICOS CARMEN · CARMEN WMS</div><h1>Orden de ingreso ${esc(o.nro)}</h1><p class="muted">${esc(o.tipo)} · ${esc(o.origen)} · documento ${esc(o.doc)} · fecha ${fmtD(o.fecha)} · muelle ${esc(o.muelle)} · estado ${esc(o.estado)}</p><table><thead><tr><th>SKU</th><th>Descripción</th><th>Lote</th><th>Turno</th><th class="r">Esperado</th><th class="r">Recibido</th><th class="r">Dif.</th></tr></thead><tbody>${o.lines.map(l => `<tr><td class="mono">${esc(l.codigo)}</td><td>${esc(P(l.codigo).desc)}</td><td class="mono">${esc(l.lote)}</td><td>${esc(l.turno)}</td><td class="r">${fmt(l.qty)}</td><td class="r">${fmt(l.rec)}</td><td class="r">${fmt(l.rec - l.qty)}</td></tr>`).join("")}<tr><th colspan="4">Total</th><th class="r">${fmt(t)}</th><th class="r">${fmt(rc)}</th><th class="r">${fmt(rc - t)}</th></tr></tbody></table>${o.obs ? `<p><b>Observación:</b> ${esc(o.obs)}</p>` : ""}<p style="margin-top:40px;display:flex;gap:40px"><span>____________________<br>Recibió</span><span>____________________<br>Entregó</span></p></div>`);
};

/* ===================================================================== PEDIDOS Y DESPACHO */
const pedUnits = p => sum(p.lines, l => l.qty), pedPicked = p => sum(p.lines, l => l.pick);
VIEWS.salidas = () => {
  const sub = state.sub.salidas || "tablero"; const peds = whPed(); let body = "";
  if (sub === "tablero") {
    body = `<div class="kanban">${OUT_STATES.map(s => { const ps = peds.filter(p => p.estado === s).sort((a, b) => (b.prioridad === "Urgente") - (a.prioridad === "Urgente") || String(b.fecha).localeCompare(String(a.fecha))); const lim = state.expand[s] ? 999 : s === "Despachado" ? 4 : 9; return `<div class="kcol"><h4>${s}<span>${ps.length}</span></h4>${ps.slice(0, lim).map(p => { const t = pedUnits(p), pk = pedPicked(p); return `<div class="kcard ${p.prioridad === "Urgente" ? "urgent" : ""}" data-ped="${esc(p.nro)}"><div class="t"><span class="mono">${esc(p.nro)}</span><span>${fmtD(p.fecha).slice(0, 5)}</span></div><b>${esc(cli(p.cliente).nombre)}</b><div class="m">${p.picker ? `<span class="mini-av" title="${esc(userName(p.picker))}">${initials(userName(p.picker))}</span>` : s === "Recibido" ? `<span class="pill warn plain" style="padding:0 6px">Sin asignar</span>` : ""}<span>${p.lines.length} líneas · ${fmt(t)} un.</span>${p.prioridad === "Urgente" ? `<span class="tag red">Urgente</span>` : ""}</div>${s === "Preparación" ? `<div class="bar" style="margin-top:8px"><i style="width:${t ? Math.round(pk / t * 100) : 0}%"></i></div>` : ""}${p.transporte ? `<div class="m" style="margin-top:6px">${ic("truck")}<span class="mono">${esc(p.transporte)}</span></div>` : ""}</div>`; }).join("")}${ps.length > lim ? `<button class="btn ghost sm" data-a="kan-more" data-s="${s}">Ver ${ps.length - lim} más</button>` : state.expand[s] && ps.length > 9 ? `<button class="btn ghost sm" data-a="kan-more" data-s="${s}">Ver menos</button>` : ""}${!ps.length ? `<div class="small muted" style="padding:8px">Sin pedidos</div>` : ""}</div>`; }).join("")}</div>`;
  } else if (sub === "pedidos") {
    const f = fv("pedEst"), q = fq("pedQ");
    const rows = peds.filter(p => (f === "Todos" || p.estado === f) && (!q || has(p.nro, q) || has(cli(p.cliente).nombre, q) || has(p.ref, q) || has(p.cliente, q))).sort((a, b) => String(b.fecha).localeCompare(String(a.fecha)) || String(b.nro).localeCompare(String(a.nro)));
    body = `<div class="card"><div class="toolbar">${searchBox("pedQ", "Nro, cliente, referencia…")}${chipBar("pedEst", ["Todos", ...OUT_STATES])}<div style="flex:1"></div><button class="btn sm" data-a="ped-export">${ic("download")} Exportar</button></div>
      ${table([{ h: "Pedido", f: p => `<span class="mono link">${esc(p.nro)}</span><div class="small muted">${esc(p.ref)}</div>` }, { h: "Cliente", f: p => `<b>${esc(cli(p.cliente).nombre)}</b><div class="small muted">${esc(cli(p.cliente).ciudad)}</div>` }, { h: "Fecha", f: p => fmtD(p.fecha) }, { h: "Ola", f: p => `<span class="tag">${esc(p.ola || "—")}</span>` }, { h: "Líneas", a: "r", f: p => p.lines.length }, { h: "Unidades", a: "r", f: p => fmt(pedUnits(p)) }, { h: "Operador", f: p => p.picker ? esc(userName(p.picker)) : `<span class="muted">—</span>` }, { h: "Prioridad", f: p => p.prioridad === "Urgente" ? `<span class="tag red">Urgente</span>` : `<span class="muted small">Normal</span>` }, { h: "Estado", f: p => pillFor(p.estado) }], rows, { rowAttr: p => `class="clickable" data-ped="${esc(p.nro)}"`, id: "ped", empty: "No hay pedidos con esos filtros" })}</div>`;
  } else {
    const dsp = whDsp().sort((a, b) => String(b.nro).localeCompare(String(a.nro))); const emb = peds.filter(p => p.estado === "Embalado");
    const active = dsp.filter(d => ["Cargando", "En ruta"].includes(d.estado));
    body = `<div class="grid g-2-1"><div class="card"><div class="card-h"><h3>Despachos</h3>${can("picking") ? `<button class="btn sm primary" data-a="dsp-new">${ic("plus")} Nuevo despacho</button>` : ""}</div>
      ${table([{ h: "Despacho", f: d => `<span class="mono link">${esc(d.nro)}</span>` }, { h: "Fecha", f: d => fmtD(d.fecha) }, { h: "Vehículo", f: d => `<span class="mono">${esc(d.placa)}</span><div class="small muted">${esc(d.chofer)}</div>` }, { h: "Destino", f: d => esc(d.destino) }, { h: "Pedidos", f: d => (d.pedidos || []).map(p => `<span class="tag">${esc(p)}</span>`).join(" ") || "—" }, { h: "Bultos", a: "r", f: d => fmt(d.bultos) }, { h: "Estado", f: d => pillFor(d.estado) }], dsp, { rowAttr: d => `class="clickable" data-dsp="${esc(d.nro)}"`, id: "dsp", empty: "Sin despachos" })}</div>
      <div class="stack"><div class="card"><div class="card-h"><h3>Muelle de salida ahora</h3><span class="pill info">${active.length}</span></div><div class="card-b stack">${active.length ? active.map(d => `<div class="row between clickable" data-dsp="${esc(d.nro)}"><div class="row">${ic("truck")}<div><b class="small mono">${esc(d.placa)}</b><div class="small muted">${esc(d.chofer)} · ${esc(d.destino)}</div></div></div>${pillFor(d.estado)}</div>`).join("") : `<div class="small muted">No hay vehículos cargando ni en ruta.</div>`}</div></div>
      <div class="card"><div class="card-h"><h3>Embalados listos para cargar</h3><span class="pill info">${emb.length}</span></div><div class="card-b stack">${emb.length ? emb.map(p => `<label class="row between" style="cursor:pointer"><span class="row"><input type="checkbox" name="emb[]" value="${esc(p.nro)}" checked> <span><b class="small mono">${esc(p.nro)}</b><div class="small muted">${esc(cli(p.cliente).nombre)} · ${p.bultos} bultos</div></span></span><span class="link small" data-ped="${esc(p.nro)}">${ic("chev")}</span></label>`).join("") + (can("picking") ? `<button class="btn primary block" data-a="dsp-new" data-from-emb="1">${ic("truck")} Armar despacho</button>` : "") : `<div class="small muted">No hay pedidos embalados.</div>`}</div></div></div></div>`;
  }
  return `${pageHead("Pedidos y despacho", "Del pedido al despacho: picking dirigido, validación, embalaje y carga.", `<button class="btn" data-a="imp-open" data-ds="pedidos">${ic("upload")} Importar pedidos</button>${can("picking") ? `<button class="btn" data-a="ped-new">${ic("plus")} Nuevo pedido</button><button class="btn primary" data-a="wave-new">${ic("wave")} Liberar ola</button>` : ""}`)}
  <div class="row between wrap" style="margin-bottom:14px"><div class="seg">${[["tablero", "Tablero"], ["pedidos", "Lista de pedidos"], ["despachos", "Despachos"]].map(([k, l]) => `<button class="${sub === k ? "on" : ""}" data-sub="salidas:${k}">${l}</button>`).join("")}</div><div class="legend"><span><i style="background:var(--red)"></i>Urgente</span><span>Las tarjetas avanzan al confirmar cada etapa en el colector o aquí.</span></div></div>${body}`;
};
ACTIONS["kan-more"] = b => { state.expand[b.dataset.s] = !state.expand[b.dataset.s]; render(); };
ACTIONS["ped-export"] = () => exportXLSX(`pedidos_${state.wh}_${stamp()}`, { name: "Pedidos", cols: [{ h: "nro", v: r => r.p.nro }, { h: "fecha", v: r => r.p.fecha }, { h: "cliente", v: r => r.p.cliente }, { h: "razon_social", v: r => cli(r.p.cliente).nombre }, { h: "estado", v: r => r.p.estado }, { h: "prioridad", v: r => r.p.prioridad }, { h: "ola", v: r => r.p.ola }, { h: "operador", v: r => r.p.picker || "" }, { h: "codigo", v: r => r.l.codigo }, { h: "descripcion", v: r => P(r.l.codigo).desc }, { h: "cantidad", v: r => r.l.qty }, { h: "tomado", v: r => r.l.pick }, { h: "bultos", v: r => r.p.bultos }, { h: "vehiculo", v: r => r.p.transporte || "" }], rows: whPed().flatMap(p => p.lines.map(l => ({ p, l }))) });

/** Ruta de picking: asignaciones (ubicación/lote) ordenadas por recorrido. */
function pickRoute(p) {
  const rows = [];
  p.lines.forEach((l, li) => {
    if (l.alloc && l.alloc.length) l.alloc.forEach((a, ai) => rows.push({ li, ai, codigo: l.codigo, loc: a.loc, lote: a.lote, qty: a.qty, pick: a.pick || 0, short: a.short || 0 }));
    else { const s = pickableStock(l.codigo, p.wh || state.wh).sort(allocOrder(l.codigo))[0]; rows.push({ li, ai: -1, codigo: l.codigo, loc: s ? s.loc : "—", lote: s ? s.lote : "—", qty: l.qty, pick: l.pick, short: l.short || 0, sug: true }); }
  });
  return rows.sort((a, b) => ((L(a.loc) || {}).sort || 1e9) - ((L(b.loc) || {}).sort || 1e9));
}
DETAIL.ped = nro => {
  const p = PEDIDOS.find(x => x.nro === nro); if (!p) return; const c = cli(p.cliente);
  const idx = OUT_STATES.indexOf(p.estado); const route = pickRoute(p); const ok = can("picking");
  const allPicked = route.every(r => r.pick + (r.short || 0) >= r.qty);
  const foot = !ok ? `<button class="btn" data-close>Cerrar</button>` :
    p.estado === "Recibido" ? `<button class="btn danger" data-a="ped-del" data-nro="${esc(nro)}">${ic("trash")}</button><button class="btn" data-a="ped-edit" data-nro="${esc(nro)}">${ic("edit")} Editar</button><button class="btn primary" data-a="ped-assign" data-nro="${esc(nro)}">${ic("zap")} Asignar y liberar picking</button>` :
    p.estado === "Preparación" ? `<button class="btn" data-a="ped-unpick" data-nro="${esc(nro)}">Anular picking</button><button class="btn" data-a="ped-reassign" data-nro="${esc(nro)}">${ic("users")} Reasignar</button><button class="btn" data-a="ped-col" data-nro="${esc(nro)}">${ic("phone")} Seguir en colector</button><button class="btn primary" data-a="ped-pickdesk" data-nro="${esc(nro)}">${allPicked ? "Validar" : "Confirmar picking"}</button>` :
    p.estado === "Validado" ? `<button class="btn" data-a="ped-picklist" data-nro="${esc(nro)}">${ic("print")} Hoja de picking</button><button class="btn primary" data-a="ped-pack" data-nro="${esc(nro)}">${ic("pkg")} Embalar</button>` :
    p.estado === "Embalado" ? `<button class="btn" data-a="ped-pack" data-nro="${esc(nro)}">${ic("edit")} Bultos</button><button class="btn" data-a="ped-rotulos" data-nro="${esc(nro)}">${ic("print")} Rótulos de bulto</button><button class="btn primary" data-a="ped-todsp" data-nro="${esc(nro)}">${ic("truck")} Agregar a despacho</button>` :
    `<button class="btn" data-a="ped-note" data-nro="${esc(nro)}">${ic("file")} Nota de despacho</button><button class="btn primary" data-close>Cerrar</button>`;
  openDrawer(`Pedido ${esc(p.nro)}`, `
    <div class="steps" style="margin-bottom:18px">${OUT_STATES.map((s, i) => `<div class="st ${i < idx ? "done" : i === idx ? "cur" : ""}">${s}</div>`).join("")}</div>
    <div class="grid g-2" style="margin-bottom:16px"><dl class="kv"><dt>Cliente</dt><dd><span class="link" data-client="${esc(c.codigo)}">${esc(c.nombre)}</span></dd><dt>Destino</dt><dd>${esc(c.ciudad)}, ${esc(c.dpto)}</dd><dt>Referencia</dt><dd class="mono">${esc(p.ref)}</dd><dt>Ola</dt><dd>${esc(p.ola || "—")}</dd></dl><dl class="kv"><dt>Operador</dt><dd>${p.picker ? esc(userName(p.picker)) : "Sin asignar"}</dd><dt>Prioridad</dt><dd>${esc(p.prioridad)}</dd><dt>Bultos</dt><dd>${fmt(p.bultos)}</dd><dt>Vehículo</dt><dd class="mono">${esc(p.transporte || "—")}</dd></dl></div>
    <h3 style="font-size:14px;margin-bottom:8px">${p.estado === "Recibido" ? "Stock disponible por línea" : "Ruta de picking"} <span class="muted small">(orden de recorrido)</span></h3>
    ${p.estado === "Recibido" ? table([{ h: "SKU", f: l => `<span class="mono">${esc(l.codigo)}</span><div class="small muted">${esc(P(l.codigo).desc)}</div>` }, { h: "Pedido", a: "r", f: l => fmt(l.qty) }, { h: "Disponible", a: "r", f: l => { const a = availOf(l.codigo, p.wh || state.wh); return `<b class="num" style="color:${a >= l.qty ? "var(--ok)" : "var(--bad)"}">${fmt(a)}</b>`; } }], p.lines) :
    table([{ h: "#", f: (r, i) => i + 1 }, { h: "Ubicación", f: r => `<span class="mono">${esc(r.loc)}</span>` }, { h: "SKU", f: r => `<span class="mono">${esc(r.codigo)}</span><div class="small muted">${esc(P(r.codigo).desc)}</div>` }, { h: "Lote", f: r => `<span class="mono small">${esc(r.lote)}</span>` }, { h: "Pedido", a: "r", f: r => fmt(r.qty) }, { h: "Tomado", a: "r", f: r => `<b class="num" style="color:${r.pick >= r.qty ? "var(--ok)" : r.pick || r.short ? "var(--warn)" : "var(--ink-3)"}">${fmt(r.pick)}</b>${r.short ? `<div class="small" style="color:var(--bad)">faltó ${fmt(r.short)}</div>` : ""}` }], route)}
    ${p.obs ? `<p class="small" style="margin:12px 0 0"><b>Observación:</b> ${esc(p.obs)}</p>` : ""}
    ${p.estado === "Preparación" ? `<div class="card" style="margin-top:16px;box-shadow:none"><div class="card-b small muted">${ic("zap")} El stock de estas líneas quedó <b>reservado</b> al liberar la ola ${esc(p.ola)}; ningún otro pedido puede tomarlo.</div></div>` : ""}`,
    foot, `Pedidos › ${esc(p.estado)}`);
  state.drawerFn = () => DETAIL.ped(nro);
};
function pedLineRow(l = {}) { return `<tr class="ln"><td style="min-width:240px"><input class="input mono" name="codigo" data-ac="prod" placeholder="Código o descripción" value="${esc(l.codigo || "")}" autocomplete="off"><div class="small muted ln-desc">${l.codigo ? `${esc(P(l.codigo).desc)} · disponible ${fmt(availOf(l.codigo))}` : ""}</div></td><td class="r"><input class="input num" name="qty" type="number" min="1" style="width:100px;text-align:right" value="${l.qty || ""}"></td><td><button class="btn sm ghost icon" type="button" data-a="row-del">${ic("x")}</button></td></tr>`; }
function pedModal(p = null, clientCode = "") {
  openModal(p ? `Editar pedido ${esc(p.nro)}` : "Nuevo pedido", `
    <div class="grid g-3" style="margin-bottom:14px">
      ${fld("Cliente", `<input class="input" name="cliente" data-ac="client" placeholder="Código, razón social o NIT" value="${esc(p?.cliente || clientCode)}" autocomplete="off">`)}
      ${fld("Prioridad", `<select class="select" name="prioridad">${opts(["Normal", "Urgente"], p?.prioridad)}</select>`)}
      ${fld("Referencia (ERP / OC cliente)", inp("ref", p?.ref && p.ref !== "—" ? p.ref : "", `placeholder="WC-…"`))}
      ${fld("Fecha", `<input class="input" type="date" name="fecha" value="${esc(p?.fecha || todayISO())}">`)}
      ${fld("Bultos estimados", `<input class="input num" type="number" min="1" name="bultos" value="${p?.bultos || 1}">`)}
    </div>
    <div class="card" style="box-shadow:none"><div class="card-h"><h3>Líneas</h3><button class="btn sm" type="button" data-a="ped-addline">${ic("plus")} Agregar línea</button></div>
    <div class="tbl-wrap"><table class="tbl"><thead><tr><th>SKU</th><th class="r">Cantidad</th><th></th></tr></thead><tbody id="pedLines">${(p?.lines?.length ? p.lines : [{}]).map(pedLineRow).join("")}</tbody></table></div></div>
    ${fld("Observación", `<textarea class="input" name="obs" rows="2">${esc(p?.obs || "")}</textarea>`)}`,
    `<button class="btn" data-close>Cancelar</button><button class="btn primary" data-a="ped-save" data-nro="${esc(p?.nro || "")}">${ic("check")} Guardar pedido</button>`, { wide: true });
}
ACTIONS["ped-new"] = b => pedModal(null, b?.dataset?.client || "");
ACTIONS["ped-edit"] = b => pedModal(PEDIDOS.find(p => p.nro === b.dataset.nro));
ACTIONS["ped-addline"] = () => { $("#pedLines").insertAdjacentHTML("beforeend", pedLineRow()); $("#pedLines tr:last-child input").focus(); };
ACTIONS["ped-save"] = b => run(b, async () => {
  const v = formVals($("#modalBox")); if (!CLIENTS.some(c => c.codigo === v.cliente)) throw new Error("Elige un cliente de la lista.");
  const { lines, errs } = readLines("#pedLines", { lote: false }); if (errs.length) throw new Error(errs[0]);
  const old = PEDIDOS.find(p => p.nro === b.dataset.nro);
  const rec = { ...(old || {}), nro: old ? old.nro : nextPedNro(), cliente: v.cliente, fecha: v.fecha || todayISO(), estado: "Recibido", lines, picker: null, prioridad: v.prioridad, transporte: null, bultos: v.bultos || 1, ola: "—", ref: v.ref || "—", wh: state.wh, obs: v.obs || null };
  await save("PEDIDOS", rec); closeOverlays(); render(); buildNav(); toast(`Pedido <b>${esc(rec.nro)}</b> guardado. Libéralo en una ola para prepararlo.`);
});
ACTIONS["ped-del"] = b => run(b, async () => { if (!await confirmDlg("Eliminar pedido", `Se eliminará el pedido <b>${esc(b.dataset.nro)}</b>.`, { ok: "Eliminar", danger: true })) return; await del("PEDIDOS", b.dataset.nro); closeOverlays(); render(); buildNav(); toast("Pedido eliminado.", "info"); });

/** Reserva stock para un pedido (FIFO/FEFO) y lo pasa a Preparación. Devuelve faltantes. */
function planAlloc(p) {
  const taken = {}; const plan = []; const missing = [];
  p.lines.forEach(l => {
    let need = l.qty - (l.pick || 0); const alloc = [];
    pickableStock(l.codigo, p.wh || state.wh).sort(allocOrder(l.codigo)).forEach(s => { if (need <= 0) return; const k = `${s.loc}|${s.lote}|${s.codigo}`; const free = s.qty - (s.reservado || 0) - (taken[k] || 0); if (free <= 0) return; const q = Math.min(free, need); alloc.push({ loc: s.loc, lote: s.lote, qty: q, pick: 0 }); taken[k] = (taken[k] || 0) + q; need -= q; });
    if (need > 0) missing.push({ codigo: l.codigo, falta: need }); plan.push(alloc);
  });
  return { plan, missing };
}
async function releaseOrders(list, pickers, ola) {
  let done = 0; const skipped = [];
  for (const [i, p] of list.entries()) {
    const { plan, missing } = planAlloc(p);
    if (missing.length) { skipped.push(`${p.nro} (falta ${missing.map(m => `${fmt(m.falta)} × ${m.codigo}`).join(", ")})`); continue; }
    const res = []; p.lines.forEach((l, li) => { l.alloc = plan[li]; if (setting("reservaOla")) plan[li].forEach(a => res.push({ tipo: "Reserva", codigo: l.codigo, loc: a.loc, lote: a.lote, qty: a.qty })); });
    if (res.length) await mov(res, p.wh || state.wh);
    p.estado = "Preparación"; p.picker = pickers.length ? pickers[i % pickers.length] : null; p.ola = ola;
    await save("PEDIDOS", p); done++;
  }
  return { done, skipped };
}
const nextOla = () => `OLA-${Math.max(0, ...PEDIDOS.map(p => parseInt(String(p.ola || "").replace(/\D/g, ""), 10) || 0)) + 1}`;
ACTIONS["wave-new"] = () => {
  const cands = whPed().filter(p => p.estado === "Recibido").sort((a, b) => (b.prioridad === "Urgente") - (a.prioridad === "Urgente") || String(a.fecha).localeCompare(String(b.fecha)));
  const ops = whUsersCol();
  openModal("Liberar ola de picking", `
    <p class="muted" style="margin:0 0 14px">Reserva stock (${esc(setting("asignacion"))}), ordena la ruta por recorrido de ubicación y reparte los pedidos entre los operadores elegidos. Los pedidos sin stock suficiente se quedan en «Recibido».</p>
    <div class="grid g-2" style="margin-bottom:14px">${fld("Ola", inp("ola", nextOla(), "readonly"))}${fld("Operadores (reparto en orden)", ops.length ? `<div class="chips" data-chipset="pickers">${ops.map(u => `<button type="button" class="chip ${u.online ? "on" : ""}" data-v="${esc(u.user)}">${esc(u.nombre)}${u.online ? "" : " · sin conexión"}</button>`).join("")}</div>` : `<span class="small muted">No hay operadores de colector en ${esc(state.wh)}; los pedidos quedan sin asignar y cualquiera puede tomarlos.</span>`)}</div>
    ${table([{ h: `<input type="checkbox" data-a="chk-all" checked>`, f: p => `<input type="checkbox" name="peds[]" value="${esc(p.nro)}" checked>` }, { h: "Pedido", f: p => `<span class="mono">${esc(p.nro)}</span>` }, { h: "Cliente", f: p => esc(cli(p.cliente).nombre) }, { h: "Líneas", a: "r", f: p => p.lines.length }, { h: "Unidades", a: "r", f: p => fmt(pedUnits(p)) }, { h: "Stock", f: p => { const m = planAlloc(p).missing; return m.length ? `<span class="pill bad" title="${esc(m.map(x => `${x.codigo}: faltan ${x.falta}`).join(" · "))}">Insuficiente</span>` : `<span class="pill ok">Disponible</span>`; } }, { h: "Prioridad", f: p => p.prioridad === "Urgente" ? `<span class="tag red">Urgente</span>` : "Normal" }], cands, { empty: "No hay pedidos en estado «Recibido» en este almacén" })}`,
    `<button class="btn" data-close>Cancelar</button>${cands.length ? `<button class="btn primary" data-a="wave-ok">${ic("zap")} Liberar pedidos seleccionados</button>` : ""}`, { wide: true });
};
ACTIONS["chk-all"] = b => { $$("tbody input[type=checkbox]", b.closest("table")).forEach(c => c.checked = b.checked); };
ACTIONS["wave-ok"] = b => run(b, async () => {
  const v = formVals($("#modalBox")); const list = (v.peds || []).map(n => PEDIDOS.find(p => p.nro === n)).filter(Boolean);
  if (!list.length) throw new Error("Selecciona al menos un pedido.");
  const { done, skipped } = await releaseOrders(list, v.pickers || [], v.ola);
  closeOverlays(); state.sub.salidas = "tablero"; render(); buildNav();
  toast(`Ola <b>${esc(v.ola)}</b> liberada: ${done} pedido(s), stock reservado.${skipped.length ? `<br>Sin stock suficiente: ${esc(skipped.join("; "))}` : ""}`, skipped.length ? "info" : "ok", skipped.length ? 8000 : 4000);
});
ACTIONS["ped-assign"] = b => {
  const p = PEDIDOS.find(x => x.nro === b.dataset.nro); const ops = whUsersCol(p.wh);
  dialog(`Liberar ${esc(p.nro)}`, fld("Operador", `<select class="select" name="picker"><option value="">— cualquiera (sin asignar) —</option>${ops.map(u => opt(u.user, "", `${u.nombre}${u.online ? "" : " · sin conexión"}`)).join("")}</select>`), { ok: "Asignar y liberar" }).then(v => v && run(null, async () => {
    const { done, skipped } = await releaseOrders([p], v.picker ? [v.picker] : [], nextOla());
    render(); buildNav(); DETAIL.ped(p.nro);
    done ? toast(`Pedido ${esc(p.nro)} liberado${v.picker ? ` y asignado a ${esc(userName(v.picker))}` : ""}. Aparece en los colectores.`) : toast(`No se liberó: ${esc(skipped[0])}`, "bad", 7000);
  }));
};
ACTIONS["ped-reassign"] = b => {
  const p = PEDIDOS.find(x => x.nro === b.dataset.nro); const ops = whUsersCol(p.wh);
  dialog(`Reasignar ${esc(p.nro)}`, fld("Operador", `<select class="select" name="picker"><option value="">— sin asignar —</option>${ops.map(u => opt(u.user, p.picker, u.nombre)).join("")}</select>`), { ok: "Reasignar" }).then(v => v && run(null, async () => { p.picker = v.picker || null; await save("PEDIDOS", p); render(); DETAIL.ped(p.nro); toast("Pedido reasignado."); }));
};
ACTIONS["ped-col"] = b => { closeOverlays(); openCollector(); colAction("pick", { dataset: { ped: b.dataset.nro } }); };
/** Confirma en escritorio lo que falta tomar (sale de la ubicación hacia el muelle de salida). */
ACTIONS["ped-pickdesk"] = b => run(b, async () => {
  const p = PEDIDOS.find(x => x.nro === b.dataset.nro);
  const pend = pickRoute(p).filter(r => r.pick + (r.short || 0) < r.qty);
  if (pend.length) {
    if (!await confirmDlg("Confirmar picking", `Se registrará como tomado todo lo pendiente (${pend.length} línea(s), ${fmt(sum(pend, r => r.qty - r.pick))} un.) desde sus ubicaciones reservadas.`, { ok: "Confirmar" })) return;
    await pickRows(p, pend.map(r => ({ ...r, take: r.qty - r.pick })));
  }
  p.estado = "Validado"; await save("PEDIDOS", p); render(); buildNav(); DETAIL.ped(p.nro); toast(`Pedido ${esc(p.nro)} validado. Siguiente paso: embalar.`);
});
/** Registra tomas: rows [{li, ai, codigo, loc, lote, take}] */
async function pickRows(p, rows, { device } = {}) {
  const movs = rows.filter(r => r.take > 0 && r.loc !== "—").map(r => ({ tipo: "Picking", dir: "out", codigo: r.codigo, lote: r.lote, qty: r.take, from: r.loc, to: "MUELLE-SAL", doc: p.nro, unreserve: r.ai >= 0 ? r.take : 0, ...(device ? { device } : {}) }));
  if (movs.length) await mov(movs, p.wh || state.wh);
  rows.forEach(r => { const l = p.lines[r.li]; l.pick = (l.pick || 0) + r.take; if (r.ai >= 0 && l.alloc[r.ai]) l.alloc[r.ai].pick = (l.alloc[r.ai].pick || 0) + r.take; });
}
ACTIONS["ped-unpick"] = b => run(b, async () => {
  const p = PEDIDOS.find(x => x.nro === b.dataset.nro); const picked = pedPicked(p);
  if (!await confirmDlg("Anular picking", `Se liberan las reservas${picked ? ` y las <b>${fmt(picked)}</b> un. ya tomadas vuelven a sus ubicaciones` : ""}. El pedido regresa a «Recibido».`, { ok: "Anular picking", danger: true })) return;
  const movs = [];
  p.lines.forEach(l => (l.alloc || []).forEach(a => { const rest = a.qty - (a.pick || 0); if (rest > 0) movs.push({ tipo: "Reserva", codigo: l.codigo, loc: a.loc, lote: a.lote, qty: -rest }); if (a.pick > 0) movs.push({ tipo: "Anulación picking", dir: "in", codigo: l.codigo, lote: a.lote, qty: a.pick, from: "MUELLE-SAL", to: a.loc, doc: p.nro }); }));
  await mov(movs, p.wh || state.wh);
  p.lines.forEach(l => { l.pick = 0; delete l.alloc; }); p.estado = "Recibido"; p.picker = null; p.ola = "—";
  await save("PEDIDOS", p); render(); buildNav(); DETAIL.ped(p.nro); toast("Picking anulado. Reservas liberadas.", "info");
});
ACTIONS["ped-pack"] = b => {
  const p = PEDIDOS.find(x => x.nro === b.dataset.nro);
  dialog(`Embalar ${esc(p.nro)}`, `${fld("Cantidad de bultos", `<input class="input num" type="number" min="1" name="bultos" value="${p.bultos || 1}">`)}<p class="small muted" style="margin:0">Luego imprime los rótulos (uno por bulto) para verificarlos por QR al cargar.</p>`, { ok: "Guardar", validate: v => v.bultos > 0 ? null : "Indica los bultos." }).then(v => v && run(null, async () => { p.bultos = v.bultos; p.estado = "Embalado"; await save("PEDIDOS", p); render(); buildNav(); DETAIL.ped(p.nro); toast(`Pedido ${esc(p.nro)} embalado en ${v.bultos} bulto(s).`); }));
};
ACTIONS["ped-rotulos"] = b => { const p = PEDIDOS.find(x => x.nro === b.dataset.nro); printLabels("despacho", Array.from({ length: p.bultos || 1 }, (_, i) => pedSample(p, i)), `Rótulos ${p.nro}`); };
ACTIONS["ped-picklist"] = b => {
  const p = PEDIDOS.find(x => x.nro === b.dataset.nro); const c = cli(p.cliente);
  printHTML(`Picking ${p.nro}`, `<div style="padding:4mm"><div class="brand">PLÁSTICOS CARMEN · CARMEN WMS</div><h1>Hoja de picking ${esc(p.nro)}</h1><p class="muted">${esc(c.nombre)} · ${esc(c.ciudad)} · ola ${esc(p.ola)} · operador ${esc(userName(p.picker))}</p><table><thead><tr><th>#</th><th>Ubicación</th><th>SKU</th><th>Descripción</th><th>Lote</th><th class="r">Cantidad</th><th class="r">Tomado</th></tr></thead><tbody>${pickRoute(p).map((r, i) => `<tr><td>${i + 1}</td><td class="mono">${esc(r.loc)}</td><td class="mono">${esc(r.codigo)}</td><td>${esc(P(r.codigo).desc)}</td><td class="mono">${esc(r.lote)}</td><td class="r">${fmt(r.qty)}</td><td class="r">${fmt(r.pick)}</td></tr>`).join("")}</tbody></table></div>`);
};
ACTIONS["ped-note"] = b => { const d = DESPACHOS.find(x => (x.pedidos || []).includes(b.dataset.nro)); if (d) dispatchNote(d); else toast("El pedido no tiene despacho asociado.", "bad"); };
ACTIONS["ped-todsp"] = b => {
  const p = PEDIDOS.find(x => x.nro === b.dataset.nro); const loading = whDsp().filter(d => d.estado === "Cargando");
  if (!loading.length) { closeOverlays(); ACTIONS["dsp-new"]({ dataset: { only: p.nro } }); return; }
  dialog(`Agregar ${esc(p.nro)} a un despacho`, fld("Despacho", `<select class="select" name="dsp">${loading.map(d => opt(d.nro, "", `${d.nro} · ${d.placa} · ${d.destino}`)).join("")}<option value="__new">+ Nuevo despacho…</option></select>`), { ok: "Agregar" }).then(v => v && run(null, async () => {
    if (v.dsp === "__new") { closeOverlays(); ACTIONS["dsp-new"]({ dataset: { only: p.nro } }); return; }
    const d = DESPACHOS.find(x => x.nro === v.dsp); d.pedidos = uniq([...(d.pedidos || []), p.nro]); d.bultos = sum(d.pedidos, n => (PEDIDOS.find(x => x.nro === n) || {}).bultos);
    p.transporte = d.placa; await save("PEDIDOS", p); await save("DESPACHOS", d); render(); DETAIL.ped(p.nro); toast(`Pedido agregado al despacho ${esc(d.nro)}.`);
  }));
};

/* ------------------------------------------------ despachos */
ACTIONS["dsp-new"] = b => {
  const emb = whPed().filter(p => p.estado === "Embalado" && !whDsp().some(d => d.estado === "Cargando" && (d.pedidos || []).includes(p.nro)));
  const pre = b?.dataset?.only ? [b.dataset.only] : b?.dataset?.fromEmb ? $$("input[name='emb[]']:checked").map(x => x.value) : emb.map(p => p.nro);
  const trucks = TRUCKS.filter(t => t.estado === "DISPONIBLE"); const drivers = DRIVERS.filter(d => d.estado === "ACTIVO");
  if (!trucks.length) { toast("No hay vehículos DISPONIBLES. Revisa Choferes y camiones.", "bad", 5000); return; }
  const now = new Date(Date.now() + 36e5); const dt = `${localISO(now)}T${pad(now.getHours())}:00`;
  openModal("Nuevo despacho", `<div class="grid g-3" style="margin-bottom:14px">${fld("Vehículo", `<select class="select" name="placa" data-a-change="dsp-truck">${trucks.map(t => opt(t.placa, "", `${t.placa} · ${t.tipo} · ${t.cap}`)).join("")}</select>`)}${fld("Chofer", `<select class="select" name="chofer">${drivers.map(d => opt(d.ci, trucks[0].chofer, `${d.nombre} · ${d.lic}`)).join("")}</select>`)}${fld("Salida programada", `<input class="input" type="datetime-local" name="salida" value="${dt}">`)}</div>
    ${fld("Destino", inp("destino", uniq(pre.map(n => cli((PEDIDOS.find(p => p.nro === n) || {}).cliente).ciudad)).join(" / "), `placeholder="Ciudad o ruta"`))}
    ${table([{ h: "", f: p => `<input type="checkbox" name="peds[]" value="${esc(p.nro)}" ${pre.includes(p.nro) ? "checked" : ""}>` }, { h: "Pedido", f: p => `<span class="mono">${esc(p.nro)}</span>` }, { h: "Cliente", f: p => esc(cli(p.cliente).nombre) }, { h: "Destino", f: p => esc(cli(p.cliente).ciudad) }, { h: "Bultos", a: "r", f: p => fmt(p.bultos) }], emb, { empty: "No hay pedidos embalados sin despacho" })}`,
    `<button class="btn" data-close>Cancelar</button><button class="btn primary" data-a="dsp-save">${ic("truck")} Crear despacho</button>`, { wide: true });
};
ACTIONS["dsp-save"] = b => run(b, async () => {
  const v = formVals($("#modalBox")); const peds = (v.peds || []).map(n => PEDIDOS.find(p => p.nro === n)).filter(Boolean);
  if (!peds.length) throw new Error("Selecciona al menos un pedido embalado.");
  const drv = DRIVERS.find(d => d.ci === v.chofer); if (!drv) throw new Error("Elige un chofer.");
  const d = { nro: nextNro("DSP", DESPACHOS, "nro", { width: 2 }), fecha: (v.salida || todayISO()).slice(0, 10), placa: v.placa, chofer: drv.nombre, pedidos: peds.map(p => p.nro), bultos: sum(peds, p => p.bultos), destino: v.destino || uniq(peds.map(p => cli(p.cliente).ciudad)).join(" / "), estado: "Cargando", wh: state.wh, salida: v.salida || null };
  for (const p of peds) { p.transporte = d.placa; await save("PEDIDOS", p); }
  await save("DESPACHOS", d); closeOverlays(); state.sub.salidas = "despachos"; render(); DETAIL.dsp(d.nro);
  toast(`Despacho <b>${esc(d.nro)}</b> creado. Verifica los bultos por QR al cargar y confirma la salida.`);
});
DETAIL.dsp = nro => {
  const x = DESPACHOS.find(z => z.nro === nro); if (!x) return; const ok = can("picking");
  const foot = `<button class="btn" data-a="dsp-note" data-nro="${esc(nro)}">${ic("print")} Nota de despacho</button>` + (!ok ? "" : x.estado === "Cargando" ? `<button class="btn danger" data-a="dsp-cancel" data-nro="${esc(nro)}">Anular</button><button class="btn primary" data-a="dsp-out" data-nro="${esc(nro)}">${ic("truck")} Confirmar salida</button>` : x.estado === "En ruta" ? `<button class="btn primary" data-a="dsp-done" data-nro="${esc(nro)}">${ic("check")} Confirmar entrega</button>` : `<button class="btn primary" data-close>Cerrar</button>`);
  openDrawer(`Despacho ${esc(x.nro)}`, `<dl class="kv" style="margin-bottom:14px"><dt>Fecha</dt><dd>${fmtD(x.fecha)}${x.salida ? ` · salida ${esc(String(x.salida).replace("T", " "))}` : ""}</dd><dt>Vehículo</dt><dd class="mono">${esc(x.placa)}</dd><dt>Chofer</dt><dd>${esc(x.chofer)}</dd><dt>Destino</dt><dd>${esc(x.destino)}</dd><dt>Bultos</dt><dd>${fmt(x.bultos)}</dd><dt>Estado</dt><dd>${pillFor(x.estado)}</dd></dl>
    <h3 style="font-size:14px;margin-bottom:8px">Pedidos</h3>${table([{ h: "Pedido", f: n => `<span class="mono link" data-ped="${esc(n)}">${esc(n)}</span>` }, { h: "Cliente", f: n => esc(cli((PEDIDOS.find(p => p.nro === n) || {}).cliente).nombre) }, { h: "Bultos", a: "r", f: n => fmt((PEDIDOS.find(p => p.nro === n) || {}).bultos) }, { h: "Estado", f: n => pillFor((PEDIDOS.find(p => p.nro === n) || {}).estado || "—") }, { h: "", f: n => x.estado === "Cargando" && ok ? `<button class="btn sm ghost icon" data-a="dsp-rm" data-nro="${esc(nro)}" data-ped="${esc(n)}" title="Quitar del despacho">${ic("x")}</button>` : "" }], x.pedidos || [], { empty: "Sin pedidos" })}
    <ul class="timeline" style="margin-top:16px"><li><span class="ic ok">${ic("check")}</span><div><b>Despacho creado</b><span>${fmtD(x.fecha)}</span></div></li><li><span class="ic ${x.estado === "Cargando" ? "" : "ok"}">${ic("truck")}</span><div><b>Salida del muelle</b><span>${x.estado === "Cargando" ? "pendiente" : "confirmada"}</span></div></li><li><span class="ic ${x.estado === "Entregado" ? "ok" : ""}">${ic("check")}</span><div><b>Entrega</b><span>${x.estado === "Entregado" ? "confirmada" : "pendiente"}</span></div></li></ul>`, foot, "Despachos");
  state.drawerFn = () => DETAIL.dsp(nro);
};
ACTIONS["dsp-rm"] = b => run(b, async () => { const d = DESPACHOS.find(x => x.nro === b.dataset.nro); const p = PEDIDOS.find(x => x.nro === b.dataset.ped); d.pedidos = d.pedidos.filter(n => n !== p.nro); d.bultos = sum(d.pedidos, n => (PEDIDOS.find(x => x.nro === n) || {}).bultos); p.transporte = null; await save("PEDIDOS", p); await save("DESPACHOS", d); render(); DETAIL.dsp(d.nro); });
ACTIONS["dsp-cancel"] = b => run(b, async () => { const d = DESPACHOS.find(x => x.nro === b.dataset.nro); if (!await confirmDlg("Anular despacho", "Los pedidos vuelven a «Embalado» y el vehículo queda libre.", { ok: "Anular", danger: true })) return; for (const n of d.pedidos || []) { const p = PEDIDOS.find(x => x.nro === n); if (p) { p.transporte = null; await save("PEDIDOS", p); } } d.estado = "Anulado"; d.pedidos = []; await save("DESPACHOS", d); render(); DETAIL.dsp(d.nro); toast("Despacho anulado.", "info"); });
ACTIONS["dsp-out"] = b => run(b, async () => {
  const d = DESPACHOS.find(x => x.nro === b.dataset.nro); if (!(d.pedidos || []).length) throw new Error("El despacho no tiene pedidos.");
  if (!await confirmDlg("Confirmar salida", `El vehículo <b>${esc(d.placa)}</b> sale con ${d.pedidos.length} pedido(s) y ${fmt(d.bultos)} bultos. Los pedidos pasan a «Despachado».`, { ok: "Confirmar salida" })) return;
  for (const n of d.pedidos) { const p = PEDIDOS.find(x => x.nro === n); if (p) { p.estado = "Despachado"; await save("PEDIDOS", p); const c = CLIENTS.find(c => c.codigo === p.cliente); if (c) { c.pedidos = (c.pedidos || 0) + 1; await save("CLIENTS", c); } } }
  const t = TRUCKS.find(x => x.placa === d.placa); if (t) { t.estado = "EN RUTA"; await save("TRUCKS", t); }
  const dr = DRIVERS.find(x => x.nombre === d.chofer); if (dr) { dr.estado = "EN RUTA"; await save("DRIVERS", dr); }
  d.estado = "En ruta"; await save("DESPACHOS", d); render(); buildNav(); DETAIL.dsp(d.nro); toast(`Despacho ${esc(d.nro)} en ruta.`);
});
ACTIONS["dsp-done"] = b => run(b, async () => {
  const d = DESPACHOS.find(x => x.nro === b.dataset.nro);
  const t = TRUCKS.find(x => x.placa === d.placa); if (t) { t.estado = "DISPONIBLE"; await save("TRUCKS", t); }
  const dr = DRIVERS.find(x => x.nombre === d.chofer); if (dr) { dr.estado = "ACTIVO"; dr.viajes = (dr.viajes || 0) + 1; await save("DRIVERS", dr); }
  d.estado = "Entregado"; await save("DESPACHOS", d); render(); DETAIL.dsp(d.nro); toast(`Despacho ${esc(d.nro)} entregado. Vehículo y chofer disponibles.`);
});
ACTIONS["dsp-note"] = b => dispatchNote(DESPACHOS.find(x => x.nro === b.dataset.nro));
function dispatchNote(d) {
  const peds = (d.pedidos || []).map(n => PEDIDOS.find(p => p.nro === n)).filter(Boolean);
  printHTML(`Nota de despacho ${d.nro}`, `<div style="padding:4mm"><div style="display:flex;justify-content:space-between;align-items:flex-start"><div><div class="brand">PLÁSTICOS CARMEN S.R.L.</div><h1>Nota de despacho ${esc(d.nro)}</h1><p class="muted">Fecha ${fmtD(d.fecha)} · vehículo ${esc(d.placa)} · chofer ${esc(d.chofer)} · destino ${esc(d.destino)} · ${fmt(d.bultos)} bultos</p></div>${qrSVG(d.nro, 90)}</div>
    ${peds.map(p => { const c = cli(p.cliente); return `<h3 style="margin:14px 0 4px;font-size:15px">${esc(p.nro)} · ${esc(c.nombre)} <span class="muted" style="font-weight:400">· ${esc(c.ciudad)} · NIT ${esc(c.nit)} · ${p.bultos} bultos</span></h3><table><thead><tr><th>SKU</th><th>Descripción</th><th>UM</th><th class="r">Cantidad</th></tr></thead><tbody>${p.lines.map(l => `<tr><td class="mono">${esc(l.codigo)}</td><td>${esc(P(l.codigo).desc)}</td><td>${esc(P(l.codigo).um)}</td><td class="r">${fmt(l.pick || l.qty)}</td></tr>`).join("")}</tbody></table>`; }).join("")}
    <p style="margin-top:50px;display:flex;justify-content:space-between"><span>____________________<br>Despachó</span><span>____________________<br>Chofer</span><span>____________________<br>Recibió conforme</span></p></div>`);
}
