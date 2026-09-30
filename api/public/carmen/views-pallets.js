/* Carmen WMS · pallets consolidados (SSCC): varios bultos de uno o varios códigos en un mismo pallet,
 * con ubicación, peso, etiqueta logística GS1 y reubicación en bloque. Escritorio y colector comparten
 * las mismas funciones (savePallet, movePallet). */
"use strict";

const PAL_STATES = ["Abierto", "Cerrado", "Desarmado"];
const palPeso = p => sum(p.lines || [], l => (+l.qty || 0) * pesoOf(l.codigo));
const palBultos = p => sum(p.lines || [], l => l.bultos ?? l.qty);
const palOf = code => PALLETS.find(p => p.sscc === String(code || "").replace(/\D/g, "").slice(-18));
const isSSCC = code => /^(\(00\))?\d{18}$/.test(String(code || "").trim());
/** SSCC GS1: dígito de extensión + prefijo de empresa + serial + verificador. */
function nextSSCC() {
  const pre = String(setting("prefijoSSCC") || "0779876").replace(/\D/g, "").padEnd(7, "0").slice(0, 7);
  const serial = Math.max(0, ...PALLETS.map(p => String(p.sscc).startsWith("0" + pre) ? parseInt(String(p.sscc).slice(8, 17), 10) || 0 : 0)) + 1;
  const base = `0${pre}${pad(serial, 9)}`; let t = 0; base.split("").reverse().forEach((d, k) => t += +d * (k % 2 ? 1 : 3));
  return base + ((10 - t % 10) % 10);
}
/** Muestra para la etiqueta SSCC de un pallet. */
function palletLabel(p) {
  const codes = uniq((p.lines || []).map(l => l.codigo)); const lotes = uniq((p.lines || []).map(l => l.lote).filter(Boolean));
  return { kind: "pallet", code: p.sscc, title: `SSCC ${p.sscc}`, desc: codes.length === 1 ? P(codes[0]).desc : `${codes.length} productos · pallet mixto`, sub: codes.length === 1 ? codes[0] : codes.slice(0, 3).join(", ") + (codes.length > 3 ? "…" : ""), lote: lotes.length === 1 ? lotes[0] : lotes.length ? "varios" : "", venc: "", cant: `${fmt(palBultos(p))} bultos · ${fmtKg(palPeso(p))}`, fecha: fmtD(p.fecha) };
}
/** Stock que respalda una línea del pallet en su ubicación actual. */
const palLineStock = (p, l) => (whStock(p.wh).find(s => s.loc === p.loc && s.codigo === l.codigo && (s.lote || null) === (l.lote || null)) || { qty: 0 }).qty;

/** Crea o amplía un pallet: mueve al pallet las líneas nuevas que están en otra ubicación (kardex «Consolidación»). */
async function savePallet(p, newLines, { device } = {}) {
  const loc = L(p.loc); if (!loc || (loc.wh || "BOL2") !== (p.wh || state.wh)) throw new Error("La ubicación del pallet no existe en este almacén.");
  if (loc.blocked) throw new Error(`La ubicación ${loc.id} está bloqueada.`);
  const lines = newLines.filter(l => l.qty > 0);
  if (!lines.length && !(p.lines || []).length) throw new Error("Agrega al menos un bulto al pallet.");
  const taken = {};
  lines.forEach(l => { const s = whStock(p.wh).find(x => x.loc === l.from && x.codigo === l.codigo && (x.lote || null) === (l.lote || null)); const free = s ? s.qty - (s.reservado || 0) - (taken[`${l.from}|${l.codigo}|${l.lote}`] || 0) : 0; if (l.qty > free) throw new Error(`Solo hay ${fmt(Math.max(0, free))} un. libres de ${l.codigo}${l.lote ? " lote " + l.lote : ""} en ${l.from}.`); taken[`${l.from}|${l.codigo}|${l.lote}`] = (taken[`${l.from}|${l.codigo}|${l.lote}`] || 0) + l.qty; });
  const isNew = !PALLETS.some(x => x.sscc === p.sscc);
  const movs = lines.filter(l => l.from !== p.loc).map(l => ({ tipo: "Consolidación", dir: "mv", codigo: l.codigo, lote: l.lote || null, qty: l.qty, from: l.from, to: p.loc, doc: p.sscc, ...(device ? { device } : {}) }));
  if (movs.length) await mov(movs, p.wh || state.wh);
  lines.forEach(l => { const ex = (p.lines = p.lines || []).find(x => x.codigo === l.codigo && (x.lote || null) === (l.lote || null)); if (ex) { ex.qty += l.qty; ex.bultos = (ex.bultos ?? 0) + (l.bultos ?? l.qty); } else p.lines.push({ codigo: l.codigo, lote: l.lote || null, qty: l.qty, bultos: l.bultos ?? l.qty }); });
  p.bultos = palBultos(p); p.peso = Math.round(palPeso(p) * 100) / 100; p.usuario = p.usuario || me(); p.fecha = p.fecha || todayISO(); p.estado = p.estado || "Abierto"; p.wh = p.wh || state.wh;
  await save("PALLETS", p);
  return { isNew, moved: movs.length };
}
/** Mueve el pallet completo (todas sus líneas) a otra ubicación. */
async function movePallet(p, dest, { device } = {}) {
  const to = L(dest); if (!to || (to.wh || "BOL2") !== (p.wh || "BOL2")) throw new Error("La ubicación de destino no existe en este almacén.");
  if (to.blocked) throw new Error(`${to.id} está bloqueada.`); if (isVirtualLoc(to.id) && zoneOf(to.id) !== "Despacho") throw new Error(`${to.id} no es una ubicación de almacenamiento.`);
  if (to.id === p.loc) throw new Error("El pallet ya está en esa ubicación.");
  const movs = []; const faltan = [];
  (p.lines || []).forEach(l => { const have = palLineStock(p, l); const q = Math.min(l.qty, have); if (q > 0) movs.push({ tipo: "Reubicación pallet", dir: "mv", codigo: l.codigo, lote: l.lote || null, qty: q, from: p.loc, to: to.id, doc: p.sscc, ...(device ? { device } : {}) }); if (q < l.qty) faltan.push(`${l.codigo} (${fmt(l.qty - q)} un. ya no están en ${p.loc})`); });
  if (!movs.length) throw new Error("El pallet no tiene stock en su ubicación actual; desármalo o corrige el saldo.");
  await mov(movs, p.wh || state.wh);
  p.loc = to.id; await save("PALLETS", p);
  return faltan;
}

/* ===================================================================== escritorio: pestaña Pallets */
function palletsTab() {
  const q = fq("palQ"), est = fv("palEst", "Activos");
  const rows = whPal().filter(p => (est === "Todos" || (est === "Activos" ? p.estado !== "Desarmado" : p.estado === est)) && (!q || has(p.sscc, q) || has(p.loc, q) || (p.lines || []).some(l => has(l.codigo, q) || has(l.lote, q) || has(P(l.codigo).desc, q)))).sort((a, b) => String(b.fecha).localeCompare(String(a.fecha)) || String(b.sscc).localeCompare(String(a.sscc)));
  const act = whPal().filter(p => p.estado !== "Desarmado");
  return `<div class="card-b" style="padding-bottom:0"><div class="grid g-4">
      <div class="card kpi sm" style="box-shadow:none"><span class="lbl">Pallets abiertos</span><span class="val">${act.filter(p => p.estado === "Abierto").length}</span><span class="delta">se les pueden agregar bultos</span></div>
      <div class="card kpi sm" style="box-shadow:none"><span class="lbl">Pallets cerrados</span><span class="val">${act.filter(p => p.estado === "Cerrado").length}</span><span class="delta">listos para almacenar o despachar</span></div>
      <div class="card kpi sm" style="box-shadow:none"><span class="lbl">Bultos paletizados</span><span class="val">${fmt(sum(act, palBultos))}</span><span class="delta">${fmt(uniq(act.flatMap(p => (p.lines || []).map(l => l.codigo))).length)} SKU distintos</span></div>
      <div class="card kpi sm" style="box-shadow:none"><span class="lbl">Peso paletizado</span><span class="val" style="font-size:20px">${fmtKg(sum(act, palPeso))}</span><span class="delta">según peso por bulto del maestro</span></div></div></div>
    <div class="toolbar">${searchBox("palQ", "SSCC, ubicación, SKU, lote")}${chipBar("palEst", [["Activos", "Activos"], "Abierto", "Cerrado", "Desarmado", "Todos"], "Activos")}<div class="spacer"></div>${can("reubicar") || can("recibir") ? `<button class="btn sm primary" data-a="pal-new">${ic("pallet")} Consolidar pallet</button>` : ""}</div>
    ${table([{ h: "SSCC", f: p => `<span class="mono link">${esc(p.sscc)}</span><div class="small muted">${fmtD(p.fecha)} · ${esc(userName(p.usuario))}</div>` }, { h: "Ubicación", f: p => `<span class="mono link" data-loc="${esc(p.loc)}">${esc(p.loc)}</span><div class="small muted">${esc(zoneOf(p.loc))}</div>` }, { h: "Contenido", f: p => { const cs = uniq((p.lines || []).map(l => l.codigo)); return `<b>${cs.length === 1 ? esc(P(cs[0]).desc) : `${cs.length} productos (mixto)`}</b><div class="small muted mono">${esc(cs.slice(0, 3).join(", "))}${cs.length > 3 ? "…" : ""}</div>`; } }, { h: "Líneas", a: "r", f: p => (p.lines || []).length }, { h: "Bultos", a: "r", f: p => `<b class="num">${fmt(palBultos(p))}</b>` }, { h: "Unidades", a: "r", f: p => fmt(sum(p.lines || [], l => l.qty)) }, { h: "Peso", a: "r", f: p => `<span class="num">${fmtKg(palPeso(p))}</span>` }, { h: "Pedido", f: p => p.pedido ? `<span class="tag">${esc(p.pedido)}</span>` : `<span class="muted">—</span>` }, { h: "Estado", f: p => pillFor(p.estado) }], rows, { rowAttr: p => `class="clickable" data-pal="${esc(p.sscc)}"`, id: "pal", empty: "Aún no hay pallets. Consolida uno desde aquí o desde el colector (Pallet)." })}`;
}
function palLineRow(l = {}) {
  return `<tr class="ln"><td style="min-width:260px"><input class="input mono" name="pick" data-ac="locstock" data-ac-min="0" placeholder="Ubicación, SKU o lote del bulto" value="${esc(l.pick || "")}" autocomplete="off"><input type="hidden" name="codigo" value="${esc(l.codigo || "")}"><input type="hidden" name="lote" value="${esc(l.lote || "")}"><input type="hidden" name="from" value="${esc(l.from || "")}"><div class="small muted ln-desc">${l.codigo ? `${esc(P(l.codigo).desc)} · desde ${esc(l.from)} · lote ${esc(l.lote || "—")}` : "Escribe y elige un saldo"}</div></td><td class="r"><input class="input num" name="qty" type="number" min="1" style="width:96px;text-align:right" value="${l.qty || ""}"></td><td class="r"><input class="input num" name="bultos" type="number" min="1" style="width:84px;text-align:right" value="${l.bultos || ""}" placeholder="= cant."></td><td class="r ln-kg small muted">${l.codigo && l.qty ? fmtKg(l.qty * pesoOf(l.codigo)) : "—"}</td><td><button class="btn sm ghost icon" type="button" data-a="row-del" title="Quitar">${ic("x")}</button></td></tr>`;
}
document.addEventListener("ac:pick", e => {
  const tr = e.target.closest("tr.ln"); if (!tr || e.target.name !== "pick" || !e.detail.item) return; const s = e.detail.item;
  $("[name=codigo]", tr).value = s.codigo; $("[name=lote]", tr).value = s.lote || ""; $("[name=from]", tr).value = s.loc; e.target.value = `${s.loc} · ${s.codigo}`;
  const free = s.qty - (s.reservado || 0); const q = $("[name=qty]", tr); if (!q.value) q.value = free; $(".ln-desc", tr).textContent = `${P(s.codigo).desc} · desde ${s.loc} · lote ${s.lote || "—"} · ${fmt(free)} libres · ${fmtKg(pesoOf(s.codigo))}/${P(s.codigo).um}`; $(".ln-kg", tr).textContent = fmtKg((+q.value || 0) * pesoOf(s.codigo));
});
document.addEventListener("input", e => { const tr = e.target.closest("#palLines tr.ln"); if (!tr || e.target.name !== "qty") return; const c = $("[name=codigo]", tr).value; $(".ln-kg", tr).textContent = c ? fmtKg((+e.target.value || 0) * pesoOf(c)) : "—"; });
function palletModal(p = null) {
  const locs = whLocs().filter(l => !l.blocked && (!isVirtualLoc(l.id) || zoneOf(l.id) === "Recepción")); const muelle = recepLocs()[0];
  openModal(p ? `Agregar bultos al pallet ${esc(p.sscc)}` : "Consolidar pallet", `
    <p class="muted" style="margin:0 0 12px">${p ? `El pallet está en <b class="mono">${esc(p.loc)}</b> con ${fmt(palBultos(p))} bultos (${fmtKg(palPeso(p))}). Los bultos nuevos se mueven a esa ubicación.` : "Agrupa bultos de uno o varios códigos en un pallet con SSCC. Los saldos elegidos se mueven a la ubicación del pallet y quedan en el kardex como «Consolidación»."}</p>
    <div class="grid g-3" style="margin-bottom:12px">${fld("SSCC", inp("sscc", p ? p.sscc : nextSSCC(), "readonly"))}${p ? fld("Ubicación del pallet", inp("loc", p.loc, "readonly")) : fld("Ubicación donde queda el pallet", `<input class="input mono" name="loc" data-ac="loc" data-ac-min="0" value="${esc(muelle ? muelle.id : (locs[0] || {}).id || "")}" placeholder="Muelle o ubicación de rack" autocomplete="off">`)}${fld("Pedido asociado (opcional)", `<input class="input mono" name="pedido" data-ac="ped" value="${esc(p?.pedido || "")}" placeholder="PED-…" autocomplete="off">`)}</div>
    <div class="card" style="box-shadow:none"><div class="card-h"><h3>Bultos a consolidar</h3><button class="btn sm" type="button" data-a="pal-addline">${ic("plus")} Agregar bulto</button></div>
    <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Saldo de origen (ubicación · SKU · lote)</th><th class="r">Cantidad</th><th class="r">Bultos</th><th class="r">Peso</th><th></th></tr></thead><tbody id="palLines">${palLineRow()}${palLineRow()}</tbody></table></div></div>
    ${fld("Observación", `<textarea class="input" name="obs" rows="2" placeholder="Opcional">${esc(p?.obs || "")}</textarea>`)}`,
    `<button class="btn" data-close>Cancelar</button><button class="btn" data-a="pal-save" data-sscc="${esc(p?.sscc || "")}" data-close-after="0">${ic("check")} Guardar abierto</button><button class="btn primary" data-a="pal-save" data-sscc="${esc(p?.sscc || "")}" data-close-after="1">${ic("pallet")} Cerrar pallet e imprimir SSCC</button>`, { wide: true });
}
function readPalLines() {
  const lines = []; const errs = [];
  $$("#palLines tr.ln").forEach((tr, i) => { const v = formVals(tr); if (!v.pick && !v.qty) return; if (!v.codigo || !v.from) errs.push(`Bulto ${i + 1}: elige un saldo de la lista.`); else if (!(v.qty > 0)) errs.push(`Bulto ${i + 1}: cantidad inválida.`); else lines.push({ codigo: v.codigo, lote: v.lote || null, from: v.from, qty: Math.round(v.qty), bultos: v.bultos ? Math.round(v.bultos) : Math.round(v.qty) }); });
  return { lines, errs };
}
ACTIONS["pal-new"] = () => palletModal();
ACTIONS["pal-add"] = b => palletModal(PALLETS.find(p => p.sscc === b.dataset.sscc));
ACTIONS["pal-addline"] = () => { $("#palLines").insertAdjacentHTML("beforeend", palLineRow()); $("#palLines tr:last-child input").focus(); };
ACTIONS["pal-save"] = b => run(b, async () => {
  const v = formVals($("#modalBox")); const { lines, errs } = readPalLines(); if (errs.length) throw new Error(errs[0]);
  const old = PALLETS.find(p => p.sscc === b.dataset.sscc);
  if (v.pedido && !PEDIDOS.some(p => p.nro === v.pedido)) throw new Error("El pedido asociado no existe.");
  const p = old || { sscc: v.sscc, wh: state.wh, loc: (v.loc || "").toUpperCase(), estado: "Abierto", lines: [], usuario: me(), fecha: todayISO() };
  if (!old && !lines.length) throw new Error("Agrega al menos un bulto.");
  p.pedido = v.pedido || null; p.obs = v.obs || null;
  const { isNew, moved } = await savePallet(p, lines);
  if (b.dataset.closeAfter === "1") { p.estado = "Cerrado"; p.cerrado = nowHuman(); await save("PALLETS", p); }
  closeOverlays(); state.sub.stock = "pallets"; go("stock"); DETAIL.pallet(p.sscc);
  toast(`Pallet <b>${esc(p.sscc)}</b> ${isNew ? "creado" : "ampliado"}: ${fmt(palBultos(p))} bultos · ${fmtKg(palPeso(p))}${moved ? ` · ${moved} saldo(s) movidos a ${esc(p.loc)}` : ""}.`);
  if (b.dataset.closeAfter === "1") printLabels("pallet", [palletLabel(p)], `SSCC ${p.sscc}`);
});
DETAIL.pallet = sscc => {
  const p = PALLETS.find(x => x.sscc === sscc); if (!p) { toast("Pallet no encontrado.", "bad"); return; }
  const ok = can("reubicar") || can("recibir") || can("picking"); const open = p.estado === "Abierto";
  const foot = `<button class="btn" data-a="pal-label" data-sscc="${esc(sscc)}">${ic("qr")} Etiqueta SSCC</button><button class="btn" data-a="pal-print" data-sscc="${esc(sscc)}">${ic("print")} Hoja de pallet</button>` + (!ok || p.estado === "Desarmado" ? `${p.estado === "Desarmado" && can("aprobar") ? `<button class="btn danger" data-a="pal-del" data-sscc="${esc(sscc)}">${ic("trash")}</button>` : ""}<button class="btn primary" data-close>Cerrar</button>` : `${open ? `<button class="btn" data-a="pal-add" data-sscc="${esc(sscc)}">${ic("plus")} Agregar bultos</button>` : ""}<button class="btn" data-a="pal-move" data-sscc="${esc(sscc)}">${ic("move")} Reubicar pallet</button><button class="btn" data-a="pal-break" data-sscc="${esc(sscc)}">Desarmar</button>${open ? `<button class="btn primary" data-a="pal-close" data-sscc="${esc(sscc)}">${ic("check")} Cerrar pallet</button>` : `<button class="btn primary" data-a="pal-reopen" data-sscc="${esc(sscc)}">Reabrir</button>`}`);
  const kd = KARDEX.filter(k => k.doc === p.sscc).slice(0, 12);
  openDrawer(`Pallet ${esc(p.sscc)}`, `
    <div class="grid g-3" style="margin-bottom:14px"><div class="card kpi sm" style="box-shadow:none"><span class="lbl">Ubicación</span><span class="val" style="font-size:20px"><span class="mono link" data-loc="${esc(p.loc)}">${esc(p.loc)}</span></span><span class="delta">${esc(zoneOf(p.loc))}</span></div><div class="card kpi sm" style="box-shadow:none"><span class="lbl">Bultos</span><span class="val">${fmt(palBultos(p))}</span><span class="delta">${fmt(sum(p.lines || [], l => l.qty))} unidades · ${(p.lines || []).length} línea(s)</span></div><div class="card kpi sm" style="box-shadow:none"><span class="lbl">Peso</span><span class="val" style="font-size:20px">${fmtKg(palPeso(p))}</span><span class="delta">según maestro</span></div></div>
    ${table([{ h: "SKU", f: l => `<span class="mono link" data-prod="${esc(l.codigo)}">${esc(l.codigo)}</span><div class="small muted">${esc(P(l.codigo).desc)}</div>` }, { h: "Lote", f: l => `<span class="mono small">${esc(l.lote || "—")}</span>` }, { h: "Bultos", a: "r", f: l => fmt(l.bultos ?? l.qty) }, { h: "Unidades", a: "r", f: l => `<b class="num">${fmt(l.qty)}</b>` }, { h: "Peso", a: "r", f: l => fmtKg(l.qty * pesoOf(l.codigo)) }, { h: "En ubicación", a: "r", f: l => { const h = palLineStock(p, l); return `<span class="num" style="color:${h >= l.qty ? "var(--ok)" : "var(--bad)"}" title="Stock del SKU/lote en ${esc(p.loc)}">${fmt(h)}</span>`; } }], p.lines || [])}
    <dl class="kv" style="margin-top:14px"><dt>Estado</dt><dd>${pillFor(p.estado)}</dd><dt>Armó</dt><dd>${esc(userName(p.usuario))} · ${fmtD(p.fecha)}</dd>${p.cerrado ? `<dt>Cerrado</dt><dd>${esc(p.cerrado)}</dd>` : ""}<dt>Pedido</dt><dd>${p.pedido ? `<span class="link" data-ped="${esc(p.pedido)}">${esc(p.pedido)}</span>` : "—"}</dd>${p.obs ? `<dt>Observación</dt><dd>${esc(p.obs)}</dd>` : ""}</dl>
    ${kd.length ? `<h3 style="font-size:14px;margin:14px 0 6px">Movimientos del pallet</h3><ul class="timeline">${kd.map(k => `<li><span class="ic ${k.dir === "in" ? "ok" : ""}">${ic("move")}</span><div><b>${esc(k.tipo)} · ${esc(k.codigo)} · ${fmt(Math.abs(k.qty))} un.</b><span>${esc(k.from)} → ${esc(k.to)} · ${esc(k.ts)} · ${esc(userName(k.user))}</span></div></li>`).join("")}</ul>` : ""}
    <p class="small muted" style="margin:12px 0 0">${ic("scan")} En el colector, escanea el SSCC para ver el contenido o reubicar el pallet completo. Al hacer picking desde su ubicación, la columna «En ubicación» muestra lo que queda.</p>`, foot, "Stock › Pallets");
  state.drawerFn = () => DETAIL.pallet(sscc);
};
ACTIONS["pal-label"] = b => { const p = PALLETS.find(x => x.sscc === b.dataset.sscc); printLabels("pallet", [palletLabel(p)], `SSCC ${p.sscc}`); };
ACTIONS["pal-print"] = b => {
  const p = PALLETS.find(x => x.sscc === b.dataset.sscc); if (!p) return;
  printDoc("Hoja de pallet consolidado", { nro: `SSCC ${p.sscc}`, sub: `${p.loc} · ${p.estado}`, qr: `(00)${p.sscc}`,
    meta: [["Almacén", WH().name], ["Ubicación", p.loc], ["Estado", p.estado], ["Armó", userName(p.usuario)], ["Fecha", fmtD(p.fecha)], ["Bultos", fmt(palBultos(p))], ["Unidades", fmt(sum(p.lines || [], l => l.qty))], ["Peso", fmtKg(palPeso(p))], ["Pedido", p.pedido || "—"]],
    body: docTable([{ h: "#", a: "c", w: "5%", f: (l, i) => i + 1 }, { h: "SKU", f: l => `<span class="mono">${esc(l.codigo)}</span>` }, { h: "Descripción", f: l => esc(P(l.codigo).desc) }, { h: "Lote", f: l => `<span class="mono">${esc(l.lote || "—")}</span>` }, { h: "UM", a: "c", f: l => esc(P(l.codigo).um) }, { h: "Bultos", a: "r", f: l => fmt(l.bultos ?? l.qty) }, { h: "Unidades", a: "r", f: l => fmt(l.qty) }, { h: "Peso", a: "r", f: l => fmtKg(l.qty * pesoOf(l.codigo)) }], p.lines || [], `<tr><th colspan="5">Total</th><th class="r">${fmt(palBultos(p))}</th><th class="r">${fmt(sum(p.lines || [], l => l.qty))}</th><th class="r">${fmtKg(palPeso(p))}</th></tr>`),
    obs: p.obs || "", sigs: [{ lbl: "Armó el pallet", who: userName(p.usuario), sub: `Usuario ${p.usuario}` }, { lbl: "Verificó", who: CW.user.nombre, sub: CW.user.rol }, { lbl: "Recibió / cargó", blank: "Nombre y CI: ______________________" }] });
};
ACTIONS["pal-close"] = b => run(b, async () => { const p = PALLETS.find(x => x.sscc === b.dataset.sscc); p.estado = "Cerrado"; p.cerrado = nowHuman(); await save("PALLETS", p); render(); DETAIL.pallet(p.sscc); toast(`Pallet ${esc(p.sscc)} cerrado. Imprime la etiqueta SSCC.`); });
ACTIONS["pal-reopen"] = b => run(b, async () => { const p = PALLETS.find(x => x.sscc === b.dataset.sscc); p.estado = "Abierto"; await save("PALLETS", p); render(); DETAIL.pallet(p.sscc); toast("Pallet reabierto.", "info"); });
ACTIONS["pal-break"] = b => run(b, async () => { const p = PALLETS.find(x => x.sscc === b.dataset.sscc); if (!await confirmDlg("Desarmar pallet", `El pallet <b>${esc(p.sscc)}</b> deja de existir como unidad; el stock permanece en <b>${esc(p.loc)}</b> y se puede reubicar bulto por bulto.`, { ok: "Desarmar", danger: true })) return; p.estado = "Desarmado"; await save("PALLETS", p); render(); DETAIL.pallet(p.sscc); toast("Pallet desarmado.", "info"); });
ACTIONS["pal-del"] = b => run(b, async () => { if (!await confirmDlg("Eliminar pallet", "Se elimina el registro del pallet (el stock no cambia).", { ok: "Eliminar", danger: true })) return; await del("PALLETS", b.dataset.sscc); closeOverlays(); render(); toast("Pallet eliminado.", "info"); });
ACTIONS["pal-move"] = b => {
  const p = PALLETS.find(x => x.sscc === b.dataset.sscc);
  dialog(`Reubicar pallet ${esc(p.sscc)}`, `<p class="small muted" style="margin:0 0 10px">Se mueven las ${(p.lines || []).length} línea(s) desde <b class="mono">${esc(p.loc)}</b> en un solo movimiento.</p>${fld("Ubicación de destino", `<input class="input mono" name="to" data-ac="loc" placeholder="Escanea o escribe" autocomplete="off">`)}`, { ok: "Reubicar", validate: v => v.to ? null : "Indica el destino." }).then(v => v && run(null, async () => { const faltan = await movePallet(p, v.to.toUpperCase()); render(); DETAIL.pallet(p.sscc); toast(`Pallet ${esc(p.sscc)} reubicado en ${esc(p.loc)}.${faltan.length ? `<br>Sin mover: ${esc(faltan.join("; "))}` : ""}`, faltan.length ? "info" : "ok", faltan.length ? 7000 : 3600); }));
};
document.addEventListener("click", e => { const t = e.target.closest("[data-pal]"); if (!t || e.target.closest("[data-a],[data-loc],[data-prod],[data-ped]")) return; DETAIL.pallet(t.dataset.pal); });
