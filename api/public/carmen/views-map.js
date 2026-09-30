/* Carmen WMS · mapa de almacén: plano del almacén (layout real), detalle del rack por columnas y niveles, ubicación seleccionada. */
"use strict";

/* Plano del almacén Bolsas (Santa Cruz) según el layout MP-LY-01 rev. 01: coordenadas en una grilla de 100 × 88.
 * t: rack (estantería con columnas), area (piso), aisle (pasillo), zone (carga), office. cols: rango de columnas que ocupa el bloque. */
const BOLSAS_LAYOUT = { w: 100, h: 88, blocks: [
  { t: "area", rack: "S02", label: "S02", x: 0, y: 0, w: 18, h: 7.6, cols: [1, 8] },
  { t: "area", rack: "S03", label: "S03", x: 0, y: 7.6, w: 18, h: 7.8, cols: [1, 8] },
  { t: "rack", rack: "E6B", x: 0, y: 18.5, w: 4.3, h: 52.5, dir: "v", cols: [1, 30], pre: { name: "Estante 6 · lado B", zona: "Volumen C" } },
  { t: "aisle", label: "P05", x: 4.3, y: 18.5, w: 9.4, h: 52.5 },
  { t: "rack", rack: "E5A", x: 13.7, y: 18.5, w: 4.3, h: 52.5, dir: "v", cols: [1, 30], pre: { name: "Estante 5 · lado A", zona: "Reserva" } },
  { t: "rack", rack: "E5B", x: 18, y: 18.5, w: 4.3, h: 52.5, dir: "v", cols: [1, 30], pre: { name: "Estante 5 · lado B", zona: "Reserva" } },
  { t: "aisle", label: "P04", x: 22.3, y: 18.5, w: 9.4, h: 52.5 },
  { t: "rack", rack: "E4A", x: 31.7, y: 0, w: 4.3, h: 71.5, dir: "v", cols: [1, 30], pre: { name: "Estante 4 · lado A", zona: "Reserva", puente: "6-8, 19-21" } },
  { t: "rack", rack: "E4B", x: 36, y: 0, w: 4.3, h: 71.5, dir: "v", cols: [1, 30], pre: { name: "Estante 4 · lado B", zona: "Reserva", puente: "6-8, 19-21" } },
  { t: "aisle", label: "P03", x: 40.3, y: 18.5, w: 9.4, h: 52.5 },
  { t: "rack", rack: "E3A", x: 49.7, y: 0, w: 4.3, h: 71.5, dir: "v", cols: [1, 30], pre: { name: "Estante 3 · lado A", zona: "Picking A", puente: "6-8, 19-21" } },
  { t: "rack", rack: "E3B", x: 54, y: 0, w: 4.3, h: 71.5, dir: "v", cols: [1, 30], pre: { name: "Estante 3 · lado B", zona: "Picking A", puente: "6-8, 19-21" } },
  { t: "aisle", label: "P02", x: 58.4, y: 18.5, w: 9.3, h: 52.5 },
  { t: "area", rack: "S02", label: "S02", x: 62, y: 0, w: 4.7, h: 14, cols: [9, 12] },
  { t: "office", label: "OFICINA COSTOS", x: 66.7, y: 0, w: 24.8, h: 14 },
  { t: "rack", rack: "E2A", x: 67.7, y: 18.5, w: 4.3, h: 52.5, dir: "v", cols: [1, 30], pre: { name: "Estante 2 · lado A", zona: "Picking A" } },
  { t: "rack", rack: "E2B", x: 72, y: 18.5, w: 4.3, h: 52.5, dir: "v", cols: [1, 30], pre: { name: "Estante 2 · lado B", zona: "Picking A" } },
  { t: "aisle", label: "P01", x: 76.4, y: 18.5, w: 10.8, h: 52.5 },
  { t: "rack", rack: "E1A", x: 87.2, y: 23.6, w: 4.3, h: 19.4, dir: "v", cols: [1, 8], pre: { name: "Estante 1 · lado A", zona: "Picking A" } },
  { t: "area", rack: "BR", label: "ROTOS", x: 87.2, y: 43, w: 4.3, h: 4.2, cols: [1, 1], pre: { name: "Bultos rotos", zona: "Cuarentena" }, cls: "bad" },
  { t: "area", rack: "DEV", label: "DEVOL.", x: 87.2, y: 55.4, w: 4.3, h: 4, cols: [1, 2], pre: { name: "Devoluciones", zona: "Cuarentena" }, cls: "info" },
  { t: "area", rack: "BR", label: "ROTOS", x: 87.2, y: 59.4, w: 4.3, h: 4.3, cols: [2, 2], cls: "bad" },
  { t: "rack", rack: "E1A", x: 87.2, y: 65.8, w: 4.3, h: 6.5, dir: "v", cols: [9, 12] },
  { t: "zone", label: "ZONA DE CARGA", x: 91.5, y: 0, w: 8.5, h: 79.7, rot: true },
  { t: "office", label: "OFICINA ALMACÉN", x: 70.8, y: 74.4, w: 13.2, h: 5.3 },
  { t: "area", rack: "S04", label: "S04", x: 0, y: 74.7, w: 16.6, h: 5, cols: [1, 6] },
  { t: "area", rack: "S04", label: "S04", x: 25, y: 74.7, w: 34, h: 5, cols: [7, 20] },
  { t: "zone", label: "ZONA DE CARGA Y PASO VEHICULAR", x: 0, y: 79.7, w: 86, h: 8.3 },
] };
/** Racks reales del almacén Bolsas (para crearlos con un clic desde el plano o con la plantilla 04_racks_almacen_bolsas). */
const BOLSAS_RACKS_PRE = { S02: { name: "Sector 02 (piso)", filas: 1, cols: 12, tipo: "Área de piso", zona: "Piso" }, S03: { name: "Sector 03 (piso)", filas: 1, cols: 8, tipo: "Área de piso", zona: "Piso" }, S04: { name: "Sector 04 (piso)", filas: 1, cols: 20, tipo: "Área de piso", zona: "Piso" }, BR: { name: "Bultos rotos", filas: 1, cols: 2, tipo: "Área de piso", zona: "Cuarentena" }, DEV: { name: "Devoluciones", filas: 1, cols: 2, tipo: "Área de piso", zona: "Cuarentena" }, E1A: { filas: 4, cols: 12 } };

/** Layout del almacén: guardado en Parámetros (layout:<almacén>), el de Bolsas para BOL2, o uno automático. */
function layoutFor(wh) {
  const saved = setting(`layout:${wh}`); if (saved && saved.blocks) return saved;
  if (wh === "BOL2") return BOLSAS_LAYOUT;
  const racks = whRacks(wh); const storage = racks.filter(r => r.tipo !== "Zona virtual (ajustes, tránsito)" && !VIRTUAL_ZONES.includes(r.zona) && (r.filas || 1) > 1);
  const areas = racks.filter(r => !storage.includes(r) && !["Virtual"].includes(r.zona));
  const blocks = []; let x = 0;
  areas.forEach(a => { blocks.push({ t: "area", rack: a.id, label: `${a.id} · ${a.name}`, x, y: 0, w: Math.max(10, Math.min(24, a.cols * 2)), h: 8, cols: [1, a.cols] }); x += Math.max(10, Math.min(24, a.cols * 2)) + 1.5; });
  x = 0; storage.forEach((r, i) => { blocks.push({ t: "rack", rack: r.id, x, y: 12, w: 4.5, h: Math.max(20, Math.min(70, r.cols * 2.2)), dir: "v", cols: [1, r.cols] }); x += 4.5; if (i % 2 === 1 || i === storage.length - 1) { blocks.push({ t: "aisle", label: `P${pad(Math.floor(i / 2) + 1)}`, x, y: 12, w: 8, h: 70 }); x += 8; } });
  return { w: Math.max(100, x + 2), h: 88, blocks };
}
const colLocs = (rack, c) => LOCATIONS.filter(l => l.rack === rack && l.col === c);
const colOcc = (rack, c) => { const ls = colLocs(rack, c); return ls.length ? sum(ls, occupancy) / ls.length : 0; };
/** SVG del plano: bloques, columnas con calor de ocupación, puentes y zonas. */
function floorPlanSVG(lay, selRack) {
  const parts = lay.blocks.map(b => {
    const label = esc(b.label || b.rack || "");
    if (b.t === "aisle") return `<g class="fp-aisle"><rect x="${b.x}" y="${b.y}" width="${b.w}" height="${b.h}"/><text x="${b.x + b.w / 2}" y="${b.y + b.h / 2}" transform="rotate(-90 ${b.x + b.w / 2} ${b.y + b.h / 2})" text-anchor="middle" dominant-baseline="middle">${label}</text></g>`;
    if (b.t === "zone") return `<g class="fp-zone"><rect x="${b.x}" y="${b.y}" width="${b.w}" height="${b.h}"/><text x="${b.x + b.w / 2}" y="${b.y + b.h / 2}" ${b.rot ? `transform="rotate(-90 ${b.x + b.w / 2} ${b.y + b.h / 2})"` : ""} text-anchor="middle" dominant-baseline="middle">${label}</text></g>`;
    if (b.t === "office") return `<g class="fp-office"><rect x="${b.x}" y="${b.y}" width="${b.w}" height="${b.h}"/><text x="${b.x + b.w / 2}" y="${b.y + b.h / 2}" text-anchor="middle" dominant-baseline="middle">${label}</text></g>`;
    const r = R(b.rack); const [c1, c2] = b.cols || [1, 1]; const n = c2 - c1 + 1; const vert = b.dir === "v";
    if (!r) { const pre = { ...(BOLSAS_RACKS_PRE[b.rack] || {}), ...(b.pre || {}) }; return `<g class="fp-missing clickable" data-a="rack-new-pre" data-code="${esc(b.rack)}" data-name="${esc(pre.name || b.label || b.rack)}" data-filas="${pre.filas || (b.t === "rack" ? 4 : 1)}" data-cols="${pre.cols || Math.max(...lay.blocks.filter(x => x.rack === b.rack).map(x => (x.cols || [1, 1])[1]))}" data-tipo="${esc(pre.tipo || (b.t === "rack" ? "Rack (estantería)" : "Área de piso"))}" data-zona="${esc(pre.zona || (b.t === "rack" ? "Reserva" : "Piso"))}" data-puente="${esc(pre.puente || "")}"><title>${label} · sin registrar · clic para crearlo</title><rect x="${b.x}" y="${b.y}" width="${b.w}" height="${b.h}" rx=".4"/><text x="${b.x + b.w / 2}" y="${b.y + b.h / 2}" ${vert && b.h > b.w * 2 ? `transform="rotate(-90 ${b.x + b.w / 2} ${b.y + b.h / 2})"` : ""} text-anchor="middle" dominant-baseline="middle">${label}</text></g>`; }
    const pu = puenteCols(r.puente); const ls = LOCATIONS.filter(l => l.rack === r.id && l.col >= c1 && l.col <= c2); const occ = ls.filter(l => STOCK.some(s => s.loc === l.id)).length;
    const cells = Array.from({ length: n }, (_, i) => { const c = c1 + i; const cx = vert ? b.x : b.x + i * b.w / n, cy = vert ? b.y + i * b.h / n : b.y, cw = vert ? b.w : b.w / n, ch = vert ? b.h / n : b.h; if (pu.includes(c)) return `<rect class="fp-puente" x="${cx}" y="${cy}" width="${cw}" height="${ch}"><title>${esc(r.id)} · C${pad(c)} · puente (solo niveles 3 y 4)</title></rect>`; const o = colOcc(r.id, c); const cl = colLocs(r.id, c); return `<rect class="fp-cell ${heat(o)} ${cl.some(l => l.blocked) ? "blocked" : ""} ${state.mapCol === `${r.id}|${c}` ? "sel" : ""}" data-mapcol="${esc(r.id)}|${c}" x="${cx}" y="${cy}" width="${cw}" height="${ch}"><title>${esc(r.id)} · C${pad(c)} · ${cl.length} nivel(es) · ${Math.round(o * 100)} % · ${fmt(sum(STOCK.filter(s => cl.some(l => l.id === s.loc)), s => s.qty))} un.</title></rect>`; });
    const puLabels = pu.filter(c => c >= c1 && c <= c2 && !pu.includes(c - 1)).map(c => { const len = (() => { let k = c; while (pu.includes(k + 1)) k++; return k - c + 1; })(); const i = c - c1; const cx = vert ? b.x + b.w / 2 : b.x + (i + len / 2) * b.w / n, cy = vert ? b.y + (i + len / 2) * b.h / n : b.y + b.h / 2; return `<text class="fp-pulbl" x="${cx}" y="${cy}" ${vert ? `transform="rotate(-90 ${cx} ${cy})"` : ""} text-anchor="middle" dominant-baseline="middle">PUENTE</text>`; }).join("");
    const lx = vert ? b.x + b.w / 2 : b.x + b.w / 2, ly = vert ? b.y - 1.2 : b.y + b.h / 2;
    return `<g class="fp-rack ${b.t} ${r.id === selRack ? "on" : ""} ${b.cls || ""}" data-rack="${esc(r.id)}"><title>${esc(r.id)} · ${esc(r.name)} · ${occ}/${ls.length} ocupadas</title><rect class="fp-outline" x="${b.x}" y="${b.y}" width="${b.w}" height="${b.h}"/>${cells.join("")}${puLabels}<text class="fp-lbl" x="${b.t === "area" ? b.x + b.w / 2 : lx}" y="${b.t === "area" ? b.y + b.h / 2 : ly}" ${b.t === "area" && b.w < 8 && b.h > b.w ? `transform="rotate(-90 ${b.x + b.w / 2} ${b.y + b.h / 2})"` : ""} text-anchor="middle" dominant-baseline="${b.t === "area" ? "middle" : "auto"}">${label}</text></g>`;
  });
  return `<svg class="floorplan" viewBox="-1 -3 ${lay.w + 2} ${lay.h + 4}" preserveAspectRatio="xMidYMin meet"><rect class="fp-floor" x="-1" y="-3" width="${lay.w + 2}" height="${lay.h + 4}"/>${parts.join("")}</svg>`;
}

VIEWS.ubicaciones = () => {
  const racks = whRacks(); const wh = WH(); const m = can("maestros");
  if (!racks.length && state.wh !== "BOL2") return `${pageHead("Mapa de almacén", `${esc(wh.name)} · aún no tiene racks ni áreas.`, m ? `<button class="btn primary" data-a="rack-new">${ic("plus")} Nuevo rack / área</button>` : "")}<div class="card"><div class="empty">${ic("map")}<div>Crea el primer rack o área (incluye un <b>Muelle de ingreso</b> con zona «Recepción» para poder recibir), o impórtalos desde Excel.</div><div class="row" style="justify-content:center;margin-top:12px;gap:8px">${m ? `<button class="btn primary" data-a="rack-new">${ic("plus")} Nuevo rack / área</button>` : ""}<button class="btn" data-a="imp-open" data-ds="racks">${ic("upload")} Importar racks</button></div></div></div>`;
  const lay = layoutFor(state.wh); const inPlan = uniq(lay.blocks.map(b => b.rack).filter(Boolean));
  let rack = state.sub.ubicaciones; if (!racks.some(r => r.id === rack)) rack = (racks.find(r => inPlan.includes(r.id)) || racks[0] || {}).id;
  const r = R(rack); const ls = r ? LOCATIONS.filter(l => l.rack === rack) : [];
  const usable = whLocs().filter(l => !isVirtualLoc(l.id)); const occAll = usable.filter(l => STOCK.some(s => s.loc === l.id)).length;
  const missing = inPlan.filter(id => !R(id)); const others = racks.filter(x => !inPlan.includes(x.id));
  const zonas = uniq(racks.map(x => x.zona)).map(z => { const zl = whLocs().filter(l => zoneOf(l.id) === z); return { z, n: zl.length, o: zl.filter(l => STOCK.some(s => s.loc === l.id)).length }; }).filter(z => z.n);
  let detail = "";
  if (r) {
    const pu = puenteCols(r.puente);
    const filas = Math.max(r.filas || 1, ...ls.map(l => l.fila)), cols = Math.max(r.cols || 1, ...ls.map(l => l.col));
    const cells = []; for (let f = filas; f >= 1; f--) for (let c = 1; c <= cols; c++) { const l = ls.find(x => x.fila === f && x.col === c); if (!l) { cells.push(pu.includes(c) && f < 3 ? `<div class="cell puente" title="C${pad(c)} N${f} · puente (paso)"></div>` : `<div class="cell" style="opacity:.25;border-style:dashed" title="Sin ubicación"></div>`); continue; } const o = occupancy(l); cells.push(`<div class="cell ${heat(o)} ${l.blocked ? "blocked" : ""} ${state.selLoc === l.id ? "sel" : ""} ${state.mapCol === `${r.id}|${c}` ? "colsel" : ""}" data-cell="${esc(l.id)}" title="${esc(l.id)} · ${Math.round(o * 100)} %"></div>`); }
    const occ = ls.filter(l => STOCK.some(s => s.loc === l.id)).length;
    const sel = state.selLoc && L(state.selLoc); const selStock = sel ? STOCK.filter(s => s.loc === sel.id) : [];
    detail = `<div class="grid g-2-1" style="margin-top:14px">
    <div class="card"><div class="card-h"><div><h3>${esc(r.id)} · ${esc(r.name)}</h3><span class="muted small">${esc(r.zona)} · ${filas} niveles × ${cols} columnas · ${occ}/${ls.length} ocupadas${pu.length ? ` · puente en C${pu.map(c => pad(c)).join(", C")}` : ""}</span></div><div class="row wrap">${m ? `<button class="btn sm ghost" data-a="rack-edit" data-rack="${esc(r.id)}">${ic("edit")} Editar rack</button>` : ""}<button class="btn sm ghost" data-a="loc-labels" data-rack="${esc(r.id)}">${ic("print")} Etiquetas</button>${m ? `<button class="btn sm ghost" data-a="loc-new" data-rack="${esc(r.id)}">${ic("plus")} Ubicación</button>` : ""}</div></div>
      <div class="card-b"><div class="rack-scroll"><div class="row" style="align-items:stretch;gap:8px;min-width:${Math.max(0, cols * 26)}px"><div class="stack" style="justify-content:space-around;gap:0;font-size:11px;color:var(--ink-3);text-align:right;padding:0 2px">${Array.from({ length: filas }, (_, i) => `<span>N${filas - i}</span>`).join("")}</div><div class="rackmap" style="grid-template-columns:repeat(${cols},minmax(0,1fr));flex:1">${cells.join("")}</div></div><div class="row" style="gap:4px;margin-top:6px;padding-left:28px;font-size:10px;color:var(--ink-3);min-width:${Math.max(0, cols * 26)}px">${Array.from({ length: cols }, (_, i) => `<span style="flex:1;text-align:center">${pad(i + 1)}</span>`).join("")}</div></div>
      <div class="legend" style="margin-top:10px"><span><i style="background:var(--heat-1);border:1px solid var(--line)"></i>vacía</span><span><i style="background:var(--heat-3)"></i>parcial</span><span><i style="background:var(--heat-5)"></i>llena</span><span><i style="background:repeating-linear-gradient(45deg,var(--bad-soft),var(--bad-soft) 3px,transparent 3px,transparent 6px);border:1px solid var(--bad)"></i>bloqueada</span><span><i style="background:var(--surface);border:1px dashed var(--ink-4)"></i>puente</span></div></div></div>
    <div class="card"><div class="card-h"><h3>${sel ? `<span class="mono">${esc(sel.id)}</span>` : "Ubicación"}</h3>${sel ? pillFor(sel.blocked ? "Bloqueada" : selStock.length ? "Ocupada" : "Vacía") : ""}</div><div class="card-b">${sel ? `<dl class="kv small" style="margin-bottom:12px"><dt>Rack</dt><dd>${esc(sel.rack)} · ${esc((R(sel.rack) || {}).name || "")}</dd><dt>Columna / nivel</dt><dd>C${pad(sel.col)} · N${sel.fila}</dd><dt>Zona</dt><dd>${esc(zoneOf(sel.id))}</dd><dt>Capacidad</dt><dd>${sel.cap}</dd><dt>Ocupación</dt><dd>${Math.round(occupancy(sel) * 100)} %</dd><dt>Recorrido</dt><dd class="num">#${sel.sort}</dd></dl>${selStock.length ? `<div class="stack">${selStock.map(s => `<div class="cline"><div><b class="mono small link" data-prod="${esc(s.codigo)}">${esc(s.codigo)}</b><span>${esc(P(s.codigo).desc)}<br>lote ${esc(s.lote || "—")} · ${fmtD(s.ingreso)}${s.reservado ? ` · ${fmt(s.reservado)} res.` : ""}</span></div><span class="q">${fmt(s.qty)}</span></div>`).join("")}</div>` : `<div class="empty" style="padding:20px">${ic("box")}<div>Sin stock en esta ubicación</div></div>`}<div class="stack" style="margin-top:12px"><button class="btn block" data-a="loc-label" data-loc="${esc(sel.id)}">${ic("print")} Imprimir etiqueta QR</button>${selStock.length && can("reubicar") ? `<button class="btn block" data-a="mv-open" data-loc="${esc(sel.id)}">${ic("move")} Reubicar stock</button>` : ""}${can("aprobar") || m ? `<button class="btn block" data-a="loc-block" data-loc="${esc(sel.id)}">${ic("lock")} ${sel.blocked ? "Desbloquear" : "Bloquear"} ubicación</button>` : ""}${m ? `<button class="btn block" data-a="loc-edit" data-loc="${esc(sel.id)}">${ic("edit")} Editar</button>${!selStock.length && !KARDEX.some(k => k.from === sel.id || k.to === sel.id) ? `<button class="btn block danger" data-a="loc-del" data-loc="${esc(sel.id)}">${ic("trash")} Eliminar</button>` : ""}` : ""}</div>` : `<div class="empty">${ic("map")}<div>Toca una columna en el plano o una celda del rack para ver su contenido</div></div>`}</div></div></div>`;
  }
  return `${pageHead("Mapa de almacén", `${esc(wh.name)} · plano del almacén con ocupación por columna. Toca un rack para ver sus niveles.`, `<button class="btn" data-a="imp-open" data-ds="racks">${ic("upload")} Importar racks</button>${m ? `<button class="btn" data-a="rack-new">${ic("plus")} Nuevo rack / área</button>` : ""}${state.wh === "BOL2" && missing.length && m ? `<button class="btn primary" data-a="rack-create-all">${ic("grid")} Crear los ${missing.length} racks del plano</button>` : ""}`)}
  <div class="grid g-4" style="margin-bottom:14px"><div class="card kpi sm"><span class="lbl">Ubicaciones</span><span class="val">${fmt(usable.length)}</span><span class="delta">${racks.length} racks y áreas</span></div><div class="card kpi sm"><span class="lbl">Ocupadas</span><span class="val">${usable.length ? Math.round(occAll / usable.length * 100) : 0}<small>%</small></span><span class="delta">${fmt(occAll)} con stock · ${fmt(usable.length - occAll)} libres</span></div><div class="card kpi sm"><span class="lbl">Bloqueadas</span><span class="val">${usable.filter(l => l.blocked).length}</span><span class="delta">conteo en curso o bloqueo manual</span></div><div class="card kpi sm"><span class="lbl">Por zona</span><span class="val" style="font-size:13px;line-height:1.5">${zonas.slice(0, 4).map(z => `${esc(z.z)} <b>${z.n ? Math.round(z.o / z.n * 100) : 0} %</b>`).join(" · ")}</span></div></div>
  <div class="card"><div class="card-h"><div><h3>Plano · ${esc(wh.name)}</h3><span class="muted small">${state.wh === "BOL2" ? "Layout MP-LY-01 · " : ""}${missing.length ? `<span style="color:var(--warn)">${missing.length} rack(s) del plano sin registrar (punteados: tócalos para crearlos)</span>` : "ocupación por columna"}</span></div><div class="legend"><span><i style="background:var(--heat-1);border:1px solid var(--line)"></i>vacía</span><span><i style="background:var(--heat-3)"></i>parcial</span><span><i style="background:var(--heat-5)"></i>llena</span><span><i style="background:#fff3c4;border:1px solid #e0c060"></i>carga</span><span><i style="background:#ece8f6;border:1px solid #c9bfe6"></i>oficinas</span></div></div>
    <div class="card-b" style="padding:10px 12px">${floorPlanSVG(lay, rack)}</div>
    ${others.length ? `<div class="toolbar" style="border-top:1px solid var(--line);border-bottom:none"><span class="small muted">Otras áreas:</span><div class="chips">${others.map(x => `<button class="chip ${x.id === rack ? "on" : ""}" data-rack="${esc(x.id)}">${esc(x.id)} · ${esc(x.name)}</button>`).join("")}</div></div>` : ""}</div>
  ${detail}`;
};
/** Clic en una columna del plano: selecciona el rack y resalta la columna. */
document.addEventListener("click", e => {
  const c = e.target.closest("[data-mapcol]"); if (!c) return; e.stopPropagation();
  const [rack, col] = c.dataset.mapcol.split("|"); state.sub.ubicaciones = rack; state.mapCol = c.dataset.mapcol;
  const first = LOCATIONS.filter(l => l.rack === rack && l.col === +col).sort((a, b) => a.fila - b.fila)[0]; state.selLoc = first ? first.id : null; state.keepScroll = true; render();
}, true);
ACTIONS["rack-new-pre"] = b => { const d = b.dataset; rackModal(null, { id: d.code, name: d.name, filas: +d.filas, cols: +d.cols, tipo: d.tipo, zona: d.zona, puente: d.puente }); };
ACTIONS["rack-create-all"] = b => run(b, async () => {
  const lay = layoutFor(state.wh); const seen = new Set(); let created = 0, locs = 0;
  for (const blk of lay.blocks) {
    if (!blk.rack || seen.has(blk.rack) || R(blk.rack)) continue; seen.add(blk.rack);
    const pre = { ...(BOLSAS_RACKS_PRE[blk.rack] || {}), ...(blk.pre || {}) }; const cols = pre.cols || Math.max(...lay.blocks.filter(x => x.rack === blk.rack).map(x => (x.cols || [1, 1])[1])); const filas = pre.filas || (blk.t === "rack" ? 4 : 1);
    const r = { id: blk.rack, wh: state.wh, name: pre.name || blk.label || blk.rack, filas, cols, zona: pre.zona || (blk.t === "rack" ? "Reserva" : "Piso"), tipo: pre.tipo || (blk.t === "rack" ? "Rack (estantería)" : "Área de piso"), cap: 6, puente: pre.puente || null };
    RACKS.push(r); await save("RACKS", r); const pu = puenteCols(r.puente); const base = Math.max(0, ...LOCATIONS.map(l => l.sort || 0)) + 5; const nuevas = [];
    for (let c = 1; c <= cols; c++) for (let f = 1; f <= filas; f++) { if (pu.includes(c) && f < 3) continue; const code = locCode(r.id, c, f); if (LOCATIONS.some(l => l.id === code)) continue; nuevas.push({ id: code, rack: r.id, fila: f, col: c, wh: r.wh, cap: 6, sort: base + (c - 1) * filas + (c % 2 ? f : filas - f + 1), blocked: false, tipo: r.tipo }); }
    await saveMany("LOCATIONS", nuevas); created++; locs += nuevas.length;
  }
  render(); toast(`${created} rack(s) creados con ${fmt(locs)} ubicaciones según el plano. Imprime sus etiquetas desde cada rack.`);
});
