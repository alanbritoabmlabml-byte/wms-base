/* Carmen WMS · modo colector (Zebra TC21/TC52 con DataWedge en modo teclado, o cualquier teléfono).
 * Cada pantalla tiene un campo de escaneo: el lector escribe el código y Enter; también se puede
 * escribir y elegir de la lista de autocompletado (productos, ubicaciones, pedidos, órdenes). */
"use strict";
const col = { screen: "home", task: null, step: 0, scan: null, qty: 0, from: null, item: null, cnt: null, sel: null, skip: [], last: null };
const beep = (ok = true) => { try { const A = window.AudioContext || window.webkitAudioContext; if (!A) return; const ctx = beep.ctx || (beep.ctx = new A()); const o = ctx.createOscillator(); const g = ctx.createGain(); o.frequency.value = ok ? 1320 : 220; g.gain.value = .06; o.connect(g); g.connect(ctx.destination); o.start(); o.stop(ctx.currentTime + (ok ? .09 : .25)); if (navigator.vibrate) navigator.vibrate(ok ? 60 : [80, 40, 80]); } catch (e) {} };
const colOnly = () => CW.user && CW.user.type === "COLECTOR";
function openCollector() { $("#colOverlay").classList.add("open"); document.body.classList.add("col-open"); cRender(); startTelemetry(); }
function closeCollector() { if (colOnly()) return; $("#colOverlay").classList.remove("open"); document.body.classList.remove("col-open"); if (!$("#app").hidden) render(); }

const cTop = (title, sub, back = true) => `<div class="c-top">${back ? `<button class="btn icon" data-c="${back === true ? "home" : back}" title="Volver">${ic("chev")}</button>` : `<svg viewBox="0 0 600 430" width="40" height="29"><use href="#pc-logo-white"/></svg>`}<div style="flex:1;min-width:0"><b>${title}</b><small>${sub}</small></div><button class="pill plain" data-c="mas" style="background:rgba(255,255,255,.12);color:#fff;font-size:11px;border:0">${online() ? ic("wifi") : ic("cloud")} ${Q.n ? Q.n + " en cola" : online() ? "al día" : "sin red"}</button></div>`;
const cNav = on => `<div class="c-nav">${[["home", "home", "Inicio"], ["tareas", "list", "Tareas"], ["consulta", "search", "Consultar"], ["mas", "grid", "Más"]].map(([k, i, l]) => `<button class="${on === k ? "on" : ""}" data-c="${k}">${ic(i)}<span>${l}</span></button>`).join("")}</div>`;
/** Caja de escaneo con campo de texto y autocompletado. */
const scanBox = (label, hint, ac = "any", extra = "") => `<div class="scanbox ${col.scan ? col.scan.st : ""}">${ic("scan")}<b>${label}</b><span>${hint}</span>${col.scan?.val ? `<span class="mono">${esc(col.scan.val)}</span>` : ""}</div><div class="scan-row"><input class="input mono" id="cScan" data-ac="${ac}" ${extra} placeholder="Escanea o escribe y elige…" autocomplete="off" autocapitalize="characters" enterkeyhint="go"><button class="btn primary" data-c="scan-go" title="Aceptar">${ic("arrow")}</button></div>`;
const myPicks = () => whPed().filter(p => p.estado === "Preparación" && (!p.picker || p.picker === me() || !USERS_COL.some(u => u.user === me()))).sort((a, b) => (b.prioridad === "Urgente") - (a.prioridad === "Urgente") || (a.picker === me() ? -1 : 1));
const openCounts = () => whCnt().filter(c => ["Habilitado", "En curso"].includes(c.estado) && (c.lines || []).length);
const pendingRoute = p => { const r = pickRoute(p).filter(x => x.pick + (x.short || 0) < x.qty && x.loc !== "—"); return [...r.filter(x => !col.skip.includes(`${x.li}|${x.ai}`)), ...r.filter(x => col.skip.includes(`${x.li}|${x.ai}`))]; };
const muelleStock = code => whStock().filter(s => zoneOf(s.loc) === "Recepción" && (!code || s.codigo === code));

/** Sugerencia de ubicación para dejar un ítem (reglas de Parámetros › Ubicación sugerida). */
function suggestLoc(code, lote, exclude = null) {
  const p = P(code); const locs = whLocs().filter(l => !isVirtualLoc(l.id) && !l.blocked && l.id !== exclude).sort((a, b) => a.sort - b.sort);
  const empty = l => !STOCK.some(s => s.loc === l.id);
  const rules = [setting("put1"), setting("put2"), setting("put3")];
  for (const r of rules) {
    let hit = null;
    if (r === "Consolidar con mismo ítem y lote") hit = locs.find(l => STOCK.some(s => s.loc === l.id && s.codigo === code && (!setting("noMezclarLotes") || s.lote === lote)) && occupancy(l) < 0.9 && !STOCK.some(s => s.loc === l.id && s.codigo !== code));
    else if (r === "Cercanía a picking (clase A)") { const zone = p.rot === "A" ? ["Picking A", "Piso"] : p.rot === "C" && setting("claseCFondo") ? ["Volumen C"] : ["Reserva"]; hit = locs.find(l => zone.includes(zoneOf(l.id)) && empty(l)); }
    else hit = locs.find(empty);
    if (hit) return hit;
  }
  return locs.find(l => occupancy(l) < 0.9) || null;
}
/** Interpreta lo escaneado: GS1 (01)(10)(37), JSON de etiqueta, credencial, o código plano. */
function parseScan(raw) {
  let code = String(raw || "").trim().replace(/^\]C1/, ""); const out = { raw: code, code, lote: null, qty: null };
  if (code.startsWith("{")) { try { const j = JSON.parse(code); out.code = j.id; out.lote = j.lot || null; out.qty = j.q || null; return out; } catch (e) {} }
  const gs = code.match(/^\(01\)(\d{14})(?:\(10\)([^()]+))?(?:\(17\)(\d{6}))?(?:\(37\)(\d+))?/) || code.match(/^01(\d{14})(?:10([^\x1d]+)\x1d?)?/);
  if (gs) { const gtin = gs[1]; const p = PRODUCTS.find(x => String(x.codigo).replace(/\D/g, "").padStart(14, "0").slice(-14) === gtin || x.codigo === gtin.replace(/^0+/, "")); out.code = p ? p.codigo : gtin; out.lote = gs[2] || null; out.qty = gs[4] ? +gs[4] : null; return out; }
  const m = code.match(/^\(00\)(\d{18})$/); if (m) { out.code = m[1]; return out; }
  const up = code.toUpperCase(); if (L(up)) out.code = up; else if (PEDIDOS.some(p => p.nro === up) || INGRESOS.some(i => i.nro === up)) out.code = up;
  return out;
}

function cRender() {
  const s = col.screen; let h = ""; const wh = WH();
  if (s === "home") {
    const t = myPicks(), rc = whIng().filter(i => ["Habilitado", "En recepción"].includes(i.estado)), cn = openCounts(), mu = muelleStock();
    h = `${cTop("Carmen WMS", `${esc(wh.name)} · ${esc(CW.user.nombre)}`, false)}<div class="c-body stack">
      <div class="c-stat"><div><b>${t.length}</b><span>picking</span></div><div><b>${rc.length}</b><span>por recibir</span></div><div><b>${cn.length}</b><span>conteos</span></div></div>
      ${scanBox("Escanea cualquier código", "ubicación, ítem, pedido u orden: la app sabe qué hacer")}
      <div class="tiles">${[["tareas", "out", "Picking", `${t.length} pedido(s)`, "picking"], ["recepcion", "in", "Recepción", `${rc.length} orden(es)`, "recibir"], ["ubicar", "map", "Ubicar", `${fmt(sum(mu, x => x.qty))} un. en muelle`, "recibir"], ["reubicar", "move", "Reubicar", "mover stock", "reubicar"], ["conteo", "count", "Conteo", `${cn.length} activo(s)`, null], ["consulta", "search", "Consultar", "stock por ubicación", null]].map(([k, i, l, d, perm]) => `<button class="tile" data-c="${k}" ${perm && !can(perm) ? "disabled title=\"Tu rol no lo permite\"" : ""}><span class="ic">${ic(i)}</span><b>${l}</b><span>${d}</span></button>`).join("")}</div></div>${cNav("home")}`;
  } else if (s === "tareas") {
    const t = myPicks();
    h = `${cTop("Mis tareas", "picking por prioridad · conteos · recepciones")}<div class="c-body stack">${t.map(p => { const r = pickRoute(p); return `<button class="cline" data-c="pick" data-ped="${esc(p.nro)}" style="width:100%;text-align:left;border-left:3px solid ${p.prioridad === "Urgente" ? "var(--red)" : "var(--navy)"}"><div><b>${esc(p.nro)} · ${esc(cli(p.cliente).nombre)}</b><span>${r.length} tomas · ${esc(p.ola)}${p.picker ? "" : " · libre"}</span></div><span class="q">${r.filter(x => x.pick + (x.short || 0) >= x.qty).length}/${r.length}</span></button>`; }).join("") || `<div class="cline"><div><b>Sin pedidos para preparar</b><span>Cuando el supervisor libere una ola aparecerán aquí.</span></div></div>`}
      ${openCounts().map(c => `<button class="cline" data-c="cnt-open" data-cnt="${c.nro}" style="width:100%;text-align:left"><div><b>Conteo ${c.nro} · ${esc(c.tipo)}</b><span>${c.ubic - c.contadas} ubicaciones pendientes</span></div>${ic("chev")}</button>`).join("")}
      ${whIng().filter(i => ["Habilitado", "En recepción"].includes(i.estado)).map(o => `<button class="cline" data-c="recv" data-ing="${esc(o.nro)}" style="width:100%;text-align:left"><div><b>${esc(o.nro)} · ${esc(o.tipo)}</b><span>${esc(o.origen)} · ${o.lines.length} líneas</span></div>${pillFor(o.estado)}</button>`).join("")}</div>${cNav("tareas")}`;
  } else if (s === "pick") {
    const p = col.task; const all = pickRoute(p); const pend = pendingRoute(p); const r = pend[0];
    if (!r) { h = `${cTop(esc(p.nro), "picking completo")}<div class="c-body stack"><div class="scanbox ok">${ic("check")}<b>Pedido completo</b><span>${all.length} tomas · ${fmt(sum(all, x => x.pick))} unidades${sum(all, x => x.short || 0) ? ` · faltaron ${fmt(sum(all, x => x.short || 0))}` : ""}</span></div><div class="cline"><div><b>Llevar a validación y embalaje</b><span>El pedido pasó a «Validado». El supervisor registra los bultos.</span></div>${ic("arrow")}</div><button class="btn primary bigbtn" data-c="tareas">Siguiente tarea</button></div>${cNav("tareas")}`; }
    else {
      const done = all.length - pend.length; const steps = ["Ir a la ubicación", "Escanear ítem", "Confirmar cantidad"]; const need = r.qty - r.pick - (r.short || 0); const lc = L(r.loc) || {};
      h = `${cTop(esc(p.nro), `toma ${done + 1} de ${all.length} · ${esc(cli(p.cliente).nombre)}`, "tareas")}<div class="c-body stack">
        <div class="bar"><i style="width:${Math.round(done / all.length * 100)}%"></i></div>
        <div class="cline" style="border-left:4px solid var(--navy)"><div><span style="font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:var(--ink-3)">${steps[col.step]}</span><b class="mono" style="font-size:26px">${esc(col.step === 0 ? r.loc : r.codigo)}</b><span>${col.step === 0 ? `${esc((R(lc.rack) || {}).name || "")} · columna ${lc.col || "—"} · nivel ${lc.fila || "—"}` : `${esc(P(r.codigo).desc)} · lote ${esc(r.lote)}`}</span></div></div>
        ${col.step < 2 ? scanBox(col.step === 0 ? "Escanea la ubicación" : "Escanea el ítem", col.step === 0 ? "confirma que estás en el lugar correcto" : `lote ${esc(r.lote)} · ${fmt(need)} ${esc(P(r.codigo).um)}`, col.step === 0 ? "loc" : "prod") : `<div class="cline"><div><b>Cantidad a tomar</b><span>${fmt(need)} ${esc(P(r.codigo).um)} · hay ${fmt((whStock().find(x => x.loc === r.loc && x.codigo === r.codigo && x.lote === r.lote) || { qty: 0 }).qty)} en la ubicación</span></div></div><div class="qty"><button class="btn" data-q="-1">−</button><input class="input num" id="cQty" type="number" min="0" value="${col.qty}" inputmode="numeric"><button class="btn" data-q="1">+</button></div><button class="btn primary bigbtn" data-c="confirm">Confirmar ${fmt(col.qty)} ${esc(P(r.codigo).um)}</button><button class="btn bigbtn" data-c="short">Faltante · tomar ${fmt(col.qty)} y reportar</button>`}
        <div class="row between small muted"><span>Siguiente: <b class="mono">${esc(pend[1]?.loc || "validación")}</b></span>${pend.length > 1 ? `<button class="btn sm ghost" data-c="skip">Saltar toma</button>` : ""}</div></div>${cNav("tareas")}`;
    }
  } else if (s === "recepcion") {
    const os = whIng().filter(i => ["Habilitado", "En recepción"].includes(i.estado));
    h = `${cTop("Recepción", "órdenes habilitadas en el almacén")}<div class="c-body stack">${scanBox("Escanea la orden", "o elige una de la lista", "ing")}${os.map(o => `<button class="cline" data-c="recv" data-ing="${esc(o.nro)}" style="width:100%;text-align:left"><div><b>${esc(o.nro)} · ${esc(o.tipo)}</b><span>${esc(o.origen)} · ${o.lines.length} líneas · ${esc(o.muelle)}</span></div>${pillFor(o.estado)}</button>`).join("") || `<div class="cline"><div><b>No hay órdenes habilitadas</b><span>El supervisor las habilita en Ingresos.</span></div></div>`}</div>${cNav("home")}`;
  } else if (s === "recv") {
    const o = col.task; const blind = (o.tipo === "Compra" && setting("ciegaCompras")) || (o.tipo === "Producción" && setting("ciegaProduccion"));
    if (col.sel !== null && col.sel !== undefined) { const l = o.lines[col.sel]; h = `${cTop(esc(o.nro), `recibir · ${esc(l.codigo)}`, "recv-back")}<div class="c-body stack"><div class="cline" style="border-left:4px solid var(--navy)"><div><b class="mono">${esc(l.codigo)}</b><span>${esc(P(l.codigo).desc)}<br>lote ${esc(l.lote)} · ${esc(l.turno)}${blind ? "" : ` · esperado ${fmt(l.qty)}, recibido ${fmt(l.rec)}`}</span></div></div><div class="cline"><div><b>Cantidad recibida ahora</b><span>${esc(P(l.codigo).um)} · queda en ${esc(o.muelle)}</span></div></div><div class="qty"><button class="btn" data-q="-1">−</button><input class="input num" id="cQty" type="number" min="0" value="${col.qty}" inputmode="numeric"><button class="btn" data-q="1">+</button></div><button class="btn primary bigbtn" data-c="recv-ok">Confirmar ${fmt(col.qty)} ${esc(P(l.codigo).um)}</button></div>${cNav("home")}`; }
    else h = `${cTop(esc(o.nro), `${esc(o.tipo)} · ${esc(o.origen)}`, "recepcion")}<div class="c-body stack">${scanBox("Escanea el bulto", "código del ítem o etiqueta GS1 (SKU, lote y cantidad)", "prod")}${o.lines.map((l, i) => `<button class="cline" data-c="recv-line" data-i="${i}" style="width:100%;text-align:left;border-left:3px solid ${l.rec >= l.qty ? "var(--ok)" : l.rec ? "var(--warn)" : "var(--line)"}"><div><b class="mono small">${esc(l.codigo)}</b><span>${esc(P(l.codigo).desc)}<br>lote ${esc(l.lote)} · ${esc(l.turno)}</span></div><span class="q" style="color:${l.rec >= l.qty ? "var(--ok)" : "inherit"}">${fmt(l.rec)}${blind ? "" : `<span class="muted" style="font-size:13px">/${fmt(l.qty)}</span>`}</span></button>`).join("")}<button class="btn primary bigbtn" data-c="recv-close" ${o.lines.some(l => l.rec > 0) ? "" : "disabled"}>Cerrar recepción</button></div>${cNav("home")}`;
  } else if (s === "ubicar") {
    const mu = muelleStock();
    if (!col.item) h = `${cTop("Ubicar", "del muelle al rack")}<div class="c-body stack">${scanBox("Escanea el bulto en el muelle", "la app propone dónde dejarlo", "prod")}${mu.length ? mu.slice(0, 30).map((x, i) => `<button class="cline" data-c="ub-pick" data-i="${i}" style="width:100%;text-align:left"><div><b class="mono small">${esc(x.codigo)}</b><span>${esc(P(x.codigo).desc)}<br>lote ${esc(x.lote || "—")} · ${esc(x.loc)}</span></div><span class="q">${fmt(x.qty - (x.reservado || 0))}</span></button>`).join("") : `<div class="cline"><div><b>El muelle está vacío</b><span>No hay mercadería pendiente de ubicar.</span></div></div>`}</div>${cNav("home")}`;
    else { const sug = suggestLoc(col.item.codigo, col.item.lote); h = `${cTop("Ubicar", `${esc(col.item.codigo)} · lote ${esc(col.item.lote || "—")}`, "ub-back")}<div class="c-body stack"><div class="cline" style="border-left:4px solid var(--navy)"><div><span class="small muted">Sugerida · ${esc(setting("put1"))}</span><b class="mono" style="font-size:26px">${esc(sug ? sug.id : "sin sugerencia")}</b><span>${esc(P(col.item.codigo).desc)}</span></div>${sug ? `<button class="btn sm" data-c="ub-use" data-loc="${esc(sug.id)}">Usar</button>` : ""}</div><div class="qty"><button class="btn" data-q="-1">−</button><input class="input num" id="cQty" type="number" min="1" value="${col.qty}" inputmode="numeric"><button class="btn" data-q="1">+</button></div>${scanBox("Escanea la ubicación al dejarlo", setting("desviarUbicacion") ? "puedes desviarte de la sugerida; queda registrado" : "debe ser la ubicación sugerida", "loc", `data-ac-zone="${VIRTUAL_ZONES.join(",")}" data-ac-zone-not="1"`)}</div>${cNav("home")}`; }
  } else if (s === "reubicar") {
    h = `${cTop("Reubicar", col.step === 0 ? "1 · origen" : col.step === 1 ? "2 · ítem y cantidad" : "3 · destino")}<div class="c-body stack">
      <div class="steps" style="font-size:12px"><div class="st ${col.step > 0 ? "done" : "cur"}">Origen</div><div class="st ${col.step > 1 ? "done" : col.step === 1 ? "cur" : ""}">Ítem</div><div class="st ${col.step === 2 ? "cur" : ""}">Destino</div></div>
      ${col.from ? `<div class="cline"><div><span class="small muted">Origen</span><b class="mono">${esc(col.from)}</b></div>${col.item ? `<div style="text-align:right"><span class="small muted">Ítem</span><b class="mono small">${esc(col.item.codigo)}</b><div class="small muted">${fmt(col.qty)} un. · ${esc(col.item.lote || "—")}</div></div>` : ""}</div>` : ""}
      ${col.step === 0 ? scanBox("Escanea la ubicación de origen", "solo ubicaciones con saldo", "locstock") : col.step === 1 ? `${scanBox("Escanea el ítem a mover", "o elígelo de la lista", "prod")}${whStock().filter(x => x.loc === col.from).map((x, i) => `<button class="cline" data-c="mv-item" data-i="${i}" style="width:100%;text-align:left;${col.item === x ? "border-color:var(--navy)" : ""}"><div><b class="mono small">${esc(x.codigo)}</b><span>${esc(P(x.codigo).desc)} · lote ${esc(x.lote || "—")}</span></div><span class="q">${fmt(x.qty - (x.reservado || 0))}</span></button>`).join("")}${col.item ? `<div class="qty"><button class="btn" data-q="-1">−</button><input class="input num" id="cQty" type="number" min="1" value="${col.qty}"><button class="btn" data-q="1">+</button></div><button class="btn primary bigbtn" data-c="mv-next">Continuar al destino</button>` : ""}` : (() => { const sug = suggestLoc(col.item.codigo, col.item.lote, col.from); return `${scanBox("Escanea la ubicación de destino", sug ? `sugerida: ${esc(sug.id)}` : "cualquier ubicación libre", "loc", `data-ac-zone="${VIRTUAL_ZONES.join(",")}" data-ac-zone-not="1"`)}${sug ? `<div class="cline"><div><b>Sugerencia</b><span>${esc(sug.id)} · ${esc(zoneOf(sug.id))}</span></div><button class="btn sm" data-c="mv-use" data-loc="${esc(sug.id)}">Usar</button></div>` : ""}`; })()}
      </div>${cNav("home")}`;
  } else if (s === "conteo") {
    const c = col.cnt ? CONTEOS.find(x => +x.nro === +col.cnt) : null;
    if (!c) h = `${cTop("Conteo", "elige un conteo activo")}<div class="c-body stack">${openCounts().map(x => `<button class="cline" data-c="cnt-open" data-cnt="${x.nro}" style="width:100%;text-align:left"><div><b>Conteo ${x.nro} · ${esc(x.tipo)}</b><span>${x.ubic - x.contadas} de ${x.ubic} pendientes · ${esc(x.modalidad || "")}</span></div>${ic("chev")}</button>`).join("") || `<div class="cline"><div><b>No hay conteos activos</b><span>El encargado los habilita en Stock › Inventario cíclico.</span></div></div>`}</div>${cNav("tareas")}`;
    else {
      const pend = c.lines.filter(l => !l.counted); const blind = /Ciego/.test(c.modalidad || "");
      if (!col.from) h = `${cTop(`Conteo ${c.nro}`, `${esc(c.tipo)} · ${pend.length} pendientes`, "cnt-list")}<div class="c-body stack">${pend.length ? `${scanBox("Escanea la ubicación", "cuenta lo que ves" + (blind ? ", sin cantidad esperada" : ""), "loc")}<div class="cline"><div><b>Siguiente sugerida</b><span class="mono">${esc(pend[0].loc)}</span></div><button class="btn sm" data-c="cnt-loc" data-loc="${esc(pend[0].loc)}">Ir</button></div>${pend.slice(1, 6).map(l => `<div class="cline"><div><span class="mono small">${esc(l.loc)}</span></div><button class="btn sm ghost" data-c="cnt-loc" data-loc="${esc(l.loc)}">Contar</button></div>`).join("")}` : `<div class="scanbox ok">${ic("check")}<b>Conteo completo</b><span>Avisa al encargado para aprobar las diferencias.</span></div>`}</div>${cNav("tareas")}`;
      else { const l = c.lines.find(x => x.loc === col.from); const rows = col.rows || []; h = `${cTop(`Conteo ${c.nro} · ${esc(col.from)}`, blind ? "conteo ciego" : "con cantidad esperada", "cnt-back")}<div class="c-body stack">${scanBox("Escanea cada ítem y cuenta", "si aparece un ítem no listado, escanéalo para agregarlo", "prod")}${rows.map((r, i) => `<div class="cline"><div><b class="mono small">${esc(r.codigo)}</b><span>${esc(P(r.codigo).desc)} · lote ${esc(r.lote)}${blind ? "" : ` · sistema ${fmt(r.sys)}`}</span></div><input class="input num cnt-q" data-i="${i}" type="number" min="0" inputmode="numeric" placeholder="0" value="${r.qty ?? ""}" style="width:84px;text-align:center;font-size:20px;font-weight:700"></div>`).join("") || `<div class="cline"><div><b>Ubicación vacía en el sistema</b><span>Si encuentras algo, escanéalo. Si no, confirma vacía.</span></div></div>`}<button class="btn primary bigbtn" data-c="cnt-ok">Confirmar ubicación</button></div>${cNav("tareas")}`; }
    }
  } else if (s === "consulta") {
    h = `${cTop("Consultar", "stock, ubicación, pedido, orden o lote")}<div class="c-body stack">${scanBox("Escanea o escribe un código", "", "any")}${col.scan?.res || ""}</div>${cNav("consulta")}`;
  } else {
    const whs = (CW.user.wh && CW.user.wh.length ? CW.user.wh : WAREHOUSES.filter(w => w.type !== "TR").map(w => w.id));
    h = `${cTop("Más", `${esc(CW.user.nombre)} · ${esc(CW.device)}`)}<div class="c-body stack">
      <button class="cline" data-c="flush" style="width:100%;text-align:left"><div class="row">${ic("cloud")}<div><b>Cola de sincronización</b><span>${Q.n ? `${Q.n} operación(es) sin enviar · toca para enviar` : "todo enviado"} · ${online() ? "en línea" : "sin conexión"}</span></div></div>${ic("chev")}</button>
      <button class="cline" data-c="print-last" style="width:100%;text-align:left" ${col.last ? "" : "disabled"}><div class="row">${ic("print")}<div><b>Imprimir etiqueta</b><span>${col.last ? `último escaneo: ${esc(col.last)}` : "consulta un código primero"}</span></div></div>${ic("chev")}</button>
      <div class="cline"><div class="row">${ic("layers")}<div><b>Almacén</b><span>${esc(WH().name)}</span></div></div><select class="select" id="cWh" style="width:auto;min-height:40px">${whs.map(w => opt(w, state.wh, w)).join("")}</select></div>
      <button class="cline" data-c="theme" style="width:100%;text-align:left"><div class="row">${ic("moon")}<div><b>Tema</b><span>claro / oscuro</span></div></div>${ic("chev")}</button>
      <button class="cline" data-c="refresh" style="width:100%;text-align:left"><div class="row">${ic("refresh")}<div><b>Actualizar datos</b><span>descargar lo último del servidor</span></div></div>${ic("chev")}</button>
      ${colOnly() ? "" : `<button class="cline" data-c="desk" style="width:100%;text-align:left"><div class="row">${ic("grid")}<div><b>Volver al escritorio</b><span>vista de supervisor</span></div></div>${ic("chev")}</button>`}
      <button class="cline" data-logout style="width:100%;text-align:left"><div class="row">${ic("logout")}<div><b>Cerrar sesión</b><span></span></div></div>${ic("chev")}</button>
      <div class="small muted" style="text-align:center">Carmen WMS · colector · ${esc(setting("appVersion") || "0.4.0")}</div></div>${cNav("mas")}`;
  }
  $("#colScreen").innerHTML = `<div class="capp">${h}</div>`;
  const si = $("#cScan"); if (si && !matchMedia("(pointer:coarse)").matches) setTimeout(() => si.focus(), 30); else if (si && document.activeElement === document.body) si.focus({ preventScroll: true });
  const q = $("#cQty"); if (q) q.oninput = e => { col.qty = Math.max(0, Math.round(+e.target.value || 0)); const b = $("[data-c=confirm],[data-c=recv-ok]"); if (b) b.textContent = b.textContent.replace(/[\d.,]+/, fmt(col.qty)); };
  $$(".cnt-q").forEach(inp => inp.oninput = e => { col.rows[+e.target.dataset.i].qty = e.target.value === "" ? null : Math.max(0, Math.round(+e.target.value)); });
  const cw = $("#cWh"); if (cw) cw.onchange = e => switchWh(e.target.value);
  colSimButtons();
}

async function simScan(raw) {
  const sc = parseScan(raw); const code = sc.code; if (!code) return; const s = col.screen;
  const isLoc = !!L(code) && (L(code).wh || "BOL2") === state.wh, prod = PRODUCTS.find(p => p.codigo === code), ped = whPed().find(p => p.nro === code), ing = whIng().find(i => i.nro === code);
  const ok = (val, extra) => { col.scan = { st: "ok", val, ...extra }; beep(true); };
  const bad = val => { col.scan = { st: "err", val }; beep(false); };
  col.last = code;
  try {
    if (s === "home") { if (isLoc) { col.screen = "consulta"; ok(code, { res: consultaLoc(code) }); } else if (prod) { col.screen = "consulta"; ok(code, { res: consultaProd(code) }); } else if (ped) { if (ped.estado === "Preparación") return colAction("pick", { dataset: { ped: code } }); col.screen = "consulta"; ok(code, { res: consultaPed(ped) }); } else if (ing) return colAction("recv", { dataset: { ing: code } }); else if (whStock().some(x => x.lote === code)) { col.screen = "consulta"; ok(code, { res: consultaLote(code) }); } else bad(`${code} — no reconocido`); }
    else if (s === "pick") { const r = pendingRoute(col.task)[0]; if (!r) return; if (col.step === 0) { if (code === r.loc) { ok(code); col.step = 1; col.scan = null; } else bad(isLoc ? `${code} — ubicación equivocada, ve a ${r.loc}` : `${code} — no es una ubicación`); } else if (col.step === 1) { if (code === r.codigo && (!sc.lote || sc.lote === r.lote)) { ok(code); col.step = 2; col.qty = Math.min(r.qty - r.pick - (r.short || 0), sc.qty || Infinity); col.scan = null; } else bad(prod ? `${code} — ítem distinto${sc.lote && sc.lote !== r.lote ? " o lote distinto" : ""}` : `${code} — no reconocido`); } }
    else if (s === "recepcion") { if (ing) return colAction("recv", { dataset: { ing: code } }); bad(`${code} — no es una orden habilitada`); }
    else if (s === "recv") { const o = col.task; let i = o.lines.findIndex(l => l.codigo === code && (!sc.lote || l.lote === sc.lote) && l.rec < l.qty); if (i < 0) i = o.lines.findIndex(l => l.codigo === code); if (i >= 0) { ok(code); col.sel = i; col.qty = sc.qty || Math.max(0, o.lines[i].qty - o.lines[i].rec) || 1; } else bad(prod ? `${code} — no está en la orden` : `${code} — no reconocido`); }
    else if (s === "ubicar") { if (!col.item) { const rows = muelleStock(prod ? code : null).filter(x => (!sc.lote || x.lote === sc.lote) && (prod || x.loc === code)); if (rows.length) { ok(code); col.item = rows[0]; col.qty = sc.qty || (rows[0].qty - (rows[0].reservado || 0)); } else bad(prod || isLoc ? `${code} — no hay stock en el muelle` : `${code} — no reconocido`); } else { if (isLoc) await doPutaway(code); else bad(`${code} — escanea una ubicación`); } }
    else if (s === "reubicar") { if (col.step === 0) { if (isLoc && whStock().some(x => x.loc === code)) { col.from = code; ok(code); col.step = 1; col.scan = null; const rows = whStock().filter(x => x.loc === code); if (rows.length === 1) { col.item = rows[0]; col.qty = rows[0].qty - (rows[0].reservado || 0); } } else bad(isLoc ? `${code} — sin stock` : `${code} — no es una ubicación`); } else if (col.step === 1) { const st = whStock().find(x => x.loc === col.from && x.codigo === code && (!sc.lote || x.lote === sc.lote)); if (st) { col.item = st; col.qty = st.qty - (st.reservado || 0); ok(code); } else bad(prod ? `${code} — no está en ${col.from}` : code); } else { if (isLoc && code !== col.from) await doMove(code); else bad(code === col.from ? "El destino es igual al origen" : `${code} — no es una ubicación`); } }
    else if (s === "conteo") { const c = CONTEOS.find(x => +x.nro === +col.cnt); if (!c) return; if (!col.from) { const l = c.lines.find(x => x.loc === code); if (l && !l.counted) { ok(code); startCountLoc(c, code); } else bad(l ? `${code} — ya contada` : `${code} — no pertenece a este conteo`); } else { if (prod) { const lote = sc.lote || (whStock().find(x => x.loc === col.from && x.codigo === code) || {}).lote || "S/L"; let r = col.rows.find(x => x.codigo === code && x.lote === lote); if (!r) { r = { codigo: code, lote, sys: 0, qty: 0 }; col.rows.push(r); } r.qty = (r.qty || 0) + (sc.qty || 1); ok(code); } else bad(`${code} — escanea un ítem`); } }
    else if (s === "consulta") { if (isLoc) ok(code, { res: consultaLoc(code) }); else if (prod) ok(code, { res: consultaProd(code) }); else if (ped) ok(code, { res: consultaPed(ped) }); else if (ing) ok(code, { res: `<div class="cline"><div><b>${esc(ing.nro)} · ${esc(ing.tipo)}</b><span>${esc(ing.origen)} · ${ing.lines.length} líneas · ${esc(ing.muelle)}</span></div>${pillFor(ing.estado)}</div>` }); else if (whStock().some(x => x.lote === code)) ok(code, { res: consultaLote(code) }); else bad(`${code} — no encontrado`); }
  } catch (e) { bad(e.message); toast(esc(e.message), "bad", 5000); }
  cRender();
}
const consultaLoc = code => { const st = STOCK.filter(x => x.loc === code); const l = L(code); return `<div class="cline"><div><span class="small muted">Ubicación · ${esc(zoneOf(code))}</span><b class="mono" style="font-size:22px">${esc(code)}</b><span>${esc((R(l.rack) || {}).name || "")} · ${l.blocked ? "BLOQUEADA" : st.length ? "ocupada" : "vacía"}</span></div><span class="q">${fmt(sum(st, x => x.qty))}</span></div>${st.map(x => `<div class="cline"><div><b class="mono small">${esc(x.codigo)}</b><span>${esc(P(x.codigo).desc)}<br>lote ${esc(x.lote || "—")} · ${fmtD(x.ingreso)}${x.reservado ? ` · ${fmt(x.reservado)} res.` : ""}</span></div><span class="q">${fmt(x.qty)}</span></div>`).join("")}`; };
const consultaProd = code => { const p = P(code); const st = whStock().filter(x => x.codigo === code).sort((a, b) => ((L(a.loc) || {}).sort || 0) - ((L(b.loc) || {}).sort || 0)); return `<div class="cline"><div><span class="small muted">Ítem · clase ${esc(p.rot)}</span><b class="mono">${esc(code)}</b><span>${esc(p.desc)}</span></div><span class="q">${fmt(stockOf(code))}<div class="small muted" style="font-weight:400">${esc(p.um)}</div></span></div>${st.map(x => `<div class="cline"><div><b class="mono small">${esc(x.loc)}</b><span>lote ${esc(x.lote || "—")} · ${fmtD(x.ingreso)}</span></div><span class="q">${fmt(x.qty)}</span></div>`).join("") || `<div class="cline"><div><span>Sin stock en ${esc(state.wh)}</span></div></div>`}`; };
const consultaPed = p => `<div class="cline"><div><b>${esc(p.nro)} · ${esc(cli(p.cliente).nombre)}</b><span>${p.lines.length} líneas · ${p.picker ? esc(userName(p.picker)) : "sin asignar"} · ${fmt(p.bultos)} bultos</span></div>${pillFor(p.estado)}</div>${p.lines.map(l => `<div class="cline"><div><b class="mono small">${esc(l.codigo)}</b><span>${esc(P(l.codigo).desc)}</span></div><span class="q">${fmt(l.pick)}/${fmt(l.qty)}</span></div>`).join("")}`;
const consultaLote = lote => whStock().filter(x => x.lote === lote).map(x => `<div class="cline"><div><b class="mono small">${esc(x.codigo)} · ${esc(x.loc)}</b><span>${esc(P(x.codigo).desc)} · lote ${esc(lote)}</span></div><span class="q">${fmt(x.qty)}</span></div>`).join("");

async function doPutaway(dest) {
  const it = col.item; const q = Math.round(col.qty || 0); const sug = suggestLoc(it.codigo, it.lote);
  if (!setting("desviarUbicacion") && sug && dest !== sug.id) throw new Error(`Debes dejarlo en ${sug.id} (desviarse está desactivado en Parámetros).`);
  if (L(dest).blocked) throw new Error(`${dest} está bloqueada.`); if (isVirtualLoc(dest)) throw new Error(`${dest} no es una ubicación de almacenamiento.`);
  if (q <= 0 || q > it.qty - (it.reservado || 0)) throw new Error(`Cantidad inválida (máx. ${fmt(it.qty - (it.reservado || 0))}).`);
  await mov([{ tipo: "Ubicación", dir: "mv", codigo: it.codigo, lote: it.lote, qty: q, from: it.loc, to: dest, doc: nextNro("MOV", KARDEX, "doc") }]);
  toast(`Ubicado ${fmt(q)} × ${esc(it.codigo)} en ${esc(dest)}${sug && dest !== sug.id ? " (desviación registrada)" : ""}.`); beep(true);
  col.item = null; col.scan = null; col.qty = 0;
}
async function doMove(dest) {
  const it = col.item; const q = Math.round(col.qty || 0);
  if (L(dest).blocked) throw new Error(`${dest} está bloqueada.`);
  if (q <= 0 || q > it.qty - (it.reservado || 0)) throw new Error(`Cantidad inválida (máx. ${fmt(it.qty - (it.reservado || 0))} libres).`);
  await mov([{ tipo: "Reubicación", dir: "mv", codigo: it.codigo, lote: it.lote, qty: q, from: col.from, to: dest, doc: nextNro("MOV", KARDEX, "doc") }]);
  toast(`Reubicado ${fmt(q)} × ${esc(it.codigo)}: ${esc(col.from)} → ${esc(dest)}.`); beep(true);
  Object.assign(col, { screen: "reubicar", step: 0, from: null, item: null, scan: null, qty: 0 });
}
function startCountLoc(c, loc) { const l = c.lines.find(x => x.loc === loc); col.from = loc; col.rows = l.items.map(i => ({ codigo: i.codigo, lote: i.lote, sys: i.sys, qty: null })); }

function colAction(a, t) {
  const d = t.dataset || {};
  if (["home", "tareas", "consulta", "mas", "reubicar", "recepcion", "ubicar"].includes(a)) { Object.assign(col, { screen: a, scan: null, step: 0, from: null, item: null, sel: null }); if (a === "home") col.task = null; cRender(); return; }
  if (a === "conteo" || a === "cnt-list") { Object.assign(col, { screen: "conteo", scan: null, from: null, cnt: a === "cnt-list" ? null : (openCounts().length === 1 ? openCounts()[0].nro : null) }); cRender(); return; }
  if (a === "scan-go") { const i = $("#cScan"); if (i && i.value.trim()) simScan(i.value.trim()); return; }
  if (a === "pick") { col.task = PEDIDOS.find(p => p.nro === d.ped); if (!col.task) return; Object.assign(col, { screen: "pick", step: 0, qty: 0, scan: null, skip: [] }); cRender(); return; }
  if (a === "recv") { col.task = INGRESOS.find(i => i.nro === d.ing); if (!col.task) return; Object.assign(col, { screen: "recv", scan: null, sel: null }); cRender(); return; }
  if (a === "recv-back") { col.sel = null; col.scan = null; cRender(); return; }
  if (a === "recv-line") { const o = col.task; col.sel = +d.i; col.qty = Math.max(0, o.lines[col.sel].qty - o.lines[col.sel].rec) || 1; cRender(); return; }
  if (a === "recv-ok") return run(t, async () => { const o = col.task; const qtys = o.lines.map((_, i) => i === col.sel ? col.qty : 0); await receiveLines(o, qtys); toast(`${fmt(col.qty)} × ${esc(o.lines[col.sel].codigo)} recibidos en ${esc(o.muelle)}.`); beep(true); col.sel = null; col.scan = null; cRender(); });
  if (a === "recv-close") return run(t, async () => { if (await closeIngreso(col.task)) { toast(`${esc(col.task.nro)}: recepción cerrada. Pendiente de ubicar.`); col.screen = "ubicar"; col.task = null; col.item = null; cRender(); } });
  if (a === "confirm" || a === "short") return run(t, async () => {
    const p = col.task; const r = pendingRoute(p)[0]; if (!r) return; const need = r.qty - r.pick - (r.short || 0); const q = Math.min(Math.max(0, Math.round(col.qty)), need);
    if (a === "confirm" && q <= 0) throw new Error("La cantidad debe ser mayor a cero (usa «Faltante» si no hay).");
    if (q > 0) await pickRows(p, [{ ...r, take: q }], {});
    if (a === "short" || q < need) {
      const rest = need - q; const l = p.lines[r.li];
      if (r.ai >= 0) { l.alloc[r.ai].short = (l.alloc[r.ai].short || 0) + rest; await mov([{ tipo: "Reserva", codigo: r.codigo, loc: r.loc, lote: r.lote, qty: -rest }], p.wh || state.wh); } else l.short = (l.short || 0) + rest;
      p.obs = `${p.obs ? p.obs + " · " : ""}Faltante ${fmt(rest)} × ${r.codigo} en ${r.loc} (${me()})`;
      const cn = { nro: Math.max(0, ...CONTEOS.map(x => +x.nro)) + 1, tipo: "Por excepción", wh: p.wh || state.wh, apertura: nowHuman(), estado: "Habilitado", ubic: 1, contadas: 0, dif: 0, resp: me(), ira: 0, alcance: `Faltante en ${r.loc} · ${p.nro}`, modalidad: "Ciego (sin cantidad esperada)", bloquear: false, lines: [{ loc: r.loc, items: STOCK.filter(s => s.loc === r.loc).map(s => ({ codigo: s.codigo, lote: s.lote, sys: s.qty })), counted: false, conteo: [], user: null, ts: null }] };
      await save("CONTEOS", cn); toast(`Faltante reportado. Se creó el conteo por excepción ${cn.nro} en ${esc(r.loc)}.`, "info", 4500);
    }
    if (!pendingRoute(p).length) { p.estado = "Validado"; }
    await save("PEDIDOS", p); beep(true); Object.assign(col, { step: 0, scan: null, qty: 0 }); col.skip = col.skip.filter(k => k !== `${r.li}|${r.ai}`); cRender();
  });
  if (a === "skip") { const r = pendingRoute(col.task)[0]; if (r) col.skip.push(`${r.li}|${r.ai}`); Object.assign(col, { step: 0, scan: null, qty: 0 }); cRender(); return; }
  if (a === "ub-pick") { col.item = muelleStock()[+d.i]; col.qty = col.item.qty - (col.item.reservado || 0); col.scan = null; cRender(); return; }
  if (a === "ub-back") { col.item = null; col.scan = null; cRender(); return; }
  if (a === "ub-use") return run(t, async () => { await doPutaway(d.loc); cRender(); });
  if (a === "mv-item") { col.item = whStock().filter(x => x.loc === col.from)[+d.i]; col.qty = col.item.qty - (col.item.reservado || 0); cRender(); return; }
  if (a === "mv-next") { if (!col.item || col.qty <= 0) { toast("Elige el ítem y la cantidad.", "bad"); return; } col.step = 2; col.scan = null; cRender(); return; }
  if (a === "mv-use") return run(t, async () => { await doMove(d.loc); cRender(); });
  if (a === "cnt-open") { col.cnt = +d.cnt; col.from = null; col.scan = null; col.screen = "conteo"; cRender(); return; }
  if (a === "cnt-loc") { const c = CONTEOS.find(x => +x.nro === +col.cnt); startCountLoc(c, d.loc); col.scan = null; cRender(); return; }
  if (a === "cnt-back") { col.from = null; col.scan = null; cRender(); return; }
  if (a === "cnt-ok") return run(t, async () => { const c = CONTEOS.find(x => +x.nro === +col.cnt); if (col.rows.some(r => r.qty === null) && !await confirmDlg("Ítems sin contar", "Los ítems sin cantidad se registrarán en 0 (no encontrados).", { ok: "Confirmar" })) return; await recordCount(c, col.from, col.rows.map(r => ({ codigo: r.codigo, lote: r.lote, qty: r.qty || 0 }))); const l = c.lines.find(x => x.loc === col.from); beep(true); toast(`Ubicación ${esc(col.from)} contada${lineDiff(l).length ? " · con diferencia" : ""}. Quedan ${c.lines.filter(x => !x.counted).length}.`); col.from = null; col.scan = null; cRender(); });
  if (a === "flush") return run(t, async () => { if (!online()) throw new Error("Sin conexión: la cola se enviará sola al recuperar la red."); await flushQueue(); cRender(); if (!Q.n) toast("Todo enviado.", "ok"); });
  if (a === "print-last") { const c = col.last; if (L(c)) printLabels("ubicacion", [locSample(L(c))], `Etiqueta ${c}`); else if (PRODUCTS.some(p => p.codigo === c)) { const s = whStock().find(x => x.codigo === c); printLabels("item", [itemSample(c, s?.lote || "", s?.qty || 1, s?.ingreso || todayISO())], `Etiqueta ${c}`); } else toast("Escanea una ubicación o un producto para imprimir su etiqueta.", "info"); return; }
  if (a === "theme") { toggleTheme(); cRender(); return; }
  if (a === "refresh") return run(t, async () => { await flushQueue(true); await resync(); toast("Datos actualizados.", "info"); });
  if (a === "desk") { closeCollector(); return; }
}
document.addEventListener("keydown", e => { if (e.key === "Enter" && e.target.id === "cScan" && e.target.value.trim()) { e.preventDefault(); simScan(e.target.value.trim()); } });
document.addEventListener("ac:pick", e => { if (e.target.id === "cScan") simScan(e.detail.code); });

/* Botones de simulación (solo en la vista de escritorio del colector). */
function colSimButtons() {
  const box = $("#colSims"); if (!box) return; const s = col.screen; let btns = [];
  if (s === "pick" && col.task) { const r = pendingRoute(col.task)[0]; if (r) btns = col.step === 0 ? [["Ubicación correcta", r.loc], ["Ubicación equivocada", (whLocs().find(l => l.id !== r.loc && !isVirtualLoc(l.id)) || {}).id]] : col.step === 1 ? [["Ítem correcto", r.codigo], ["Ítem distinto", (PRODUCTS.find(p => p.codigo !== r.codigo) || {}).codigo]] : []; }
  else if (s === "reubicar") { const src = whStock().find(x => !isVirtualLoc(x.loc)); btns = col.step === 0 ? (src ? [["Origen " + src.loc, src.loc]] : []) : col.step === 1 ? [["Ítem del origen", (whStock().find(x => x.loc === col.from) || {}).codigo]] : (() => { const sg = suggestLoc(col.item.codigo, col.item.lote, col.from); return sg ? [["Destino " + sg.id, sg.id]] : []; })(); }
  else if (s === "recv" && col.task && col.sel === null) btns = col.task.lines.slice(0, 3).map(l => [`Bulto ${l.codigo.slice(-4)}`, l.codigo]);
  else if (s === "recepcion") btns = whIng().filter(i => ["Habilitado", "En recepción"].includes(i.estado)).slice(0, 2).map(i => [`Orden ${i.nro}`, i.nro]);
  else if (s === "conteo" && col.cnt) { const c = CONTEOS.find(x => +x.nro === +col.cnt); btns = col.from ? (col.rows || []).slice(0, 2).map(x => [`Ítem ${x.codigo.slice(-4)}`, x.codigo]) : (c ? c.lines.filter(l => !l.counted).slice(0, 1).map(l => [`Ubicación ${l.loc}`, l.loc]) : []); }
  else if (s === "ubicar") { const mu = muelleStock(); btns = col.item ? (() => { const sg = suggestLoc(col.item.codigo, col.item.lote); return sg ? [[`Sugerida ${sg.id}`, sg.id]] : []; })() : mu.slice(0, 2).map(x => [`Bulto ${x.codigo.slice(-4)}`, x.codigo]); }
  else { const st = whStock().find(x => !isVirtualLoc(x.loc)); const tp = myPicks()[0]; btns = [st && ["Ubicación " + st.loc, st.loc], st && ["Ítem " + st.codigo.slice(-4), st.codigo], tp && ["Pedido " + tp.nro, tp.nro], ["Código inválido", "XYZ-000"]].filter(Boolean); }
  box.innerHTML = btns.filter(b => b && b[1]).map(([l, c]) => `<button class="btn sm" data-sim="${esc(c)}">${ic("scan")} ${esc(l)}</button>`).join("") + `<button class="btn sm" id="colNet">${Q.forced ? ic("cloud") + " Recuperar WiFi" : ic("wifi") + " Simular pérdida de WiFi"}</button>`;
}

/* Telemetría del equipo: batería, cola y conexión (solo si el equipo fue enrolado). */
let telTimer = null;
function startTelemetry() {
  if (telTimer || !localStorage.getItem("cw-device")) return;
  const send = async () => { if (!online() || !CW.user) return; let bat = null; try { if (navigator.getBattery) bat = Math.round((await navigator.getBattery()).level * 100); } catch (e) {} try { await api("POST", `${CW.routes.api}/telemetria`, { id: localStorage.getItem("cw-device"), bat, cola: Q.n, app: setting("appVersion") || "0.4.0", wh: state.wh }); } catch (e) {} };
  send(); telTimer = setInterval(send, 60000);
}
