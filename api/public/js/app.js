/* Carmen WMS — escritorio. Utilidades sin framework de build: Alpine + este archivo. */
(function () {
  // ----- tema claro/oscuro (persistido por navegador) -----
  const root = document.documentElement;
  try { const t = localStorage.getItem('cwms-theme'); if (t) root.dataset.theme = t; } catch (e) {}
  window.wmsToggleTheme = function () {
    const cur = root.dataset.theme || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    const next = cur === 'dark' ? 'light' : 'dark';
    root.dataset.theme = next;
    try { localStorage.setItem('cwms-theme', next); } catch (e) {}
    document.dispatchEvent(new CustomEvent('wms:theme', { detail: next }));
  };

  // ----- menú lateral contraído -----
  try { if (localStorage.getItem('cwms-rail') === '1') document.getElementById('app')?.classList.add('rail-collapsed'); } catch (e) {}
  window.wmsToggleRail = function () {
    const app = document.getElementById('app'); if (!app) return;
    app.classList.toggle('rail-collapsed');
    try { localStorage.setItem('cwms-rail', app.classList.contains('rail-collapsed') ? '1' : '0'); } catch (e) {}
  };

  // ----- toasts -----
  window.wmsToast = function (msg, kind = 'ok', ms = 3500) {
    const host = document.getElementById('toasts'); if (!host) return;
    const t = document.createElement('div'); t.className = 'toast ' + kind; t.textContent = msg;
    host.appendChild(t); setTimeout(() => t.remove(), ms);
  };
  document.addEventListener('DOMContentLoaded', () => {
    const f = document.getElementById('flash'); if (f && f.dataset.msg) wmsToast(f.dataset.msg, f.dataset.kind || 'ok');
    // atajo "/" enfoca la búsqueda global
    document.addEventListener('keydown', e => {
      if (e.key === '/' && !/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName)) { e.preventDefault(); document.getElementById('gsearch')?.focus(); }
    });
  });

  // ----- QR y Code128 para la vista previa de etiquetas -----
  window.wmsQrSvg = function (text, size) {
    try {
      const q = qrcode(0, 'M'); q.addData(text); q.make();
      const n = q.getModuleCount(); const cs = size / n; let d = '';
      for (let r = 0; r < n; r++) for (let c = 0; c < n; c++) if (q.isDark(r, c)) d += `M${(c * cs).toFixed(2)} ${(r * cs).toFixed(2)}h${cs.toFixed(2)}v${cs.toFixed(2)}h-${cs.toFixed(2)}z`;
      return `<svg viewBox="0 0 ${size} ${size}" width="${size}" height="${size}" shape-rendering="crispEdges"><rect width="${size}" height="${size}" fill="#fff"/><path d="${d}" fill="#000"/></svg>`;
    } catch (e) { return `<div style="width:${size}px;height:${size}px;background:#eee"></div>`; }
  };
  window.wmsBarcode = function (svg, text, fs) {
    try { JsBarcode(svg, text.replace(/[^\x20-\x7E]/g, ''), { format: 'CODE128', displayValue: true, fontSize: fs, height: Math.max(22, fs * 2.6), margin: 0, width: 1.4, background: '#ffffff', lineColor: '#000000' }); } catch (e) { svg.outerHTML = `<div style="font:10px monospace">${text}</div>`; }
  };

  // ----- formularios de confirmación (sin confirm() nativo) -----
  document.addEventListener('submit', e => {
    const f = e.target; if (!(f instanceof HTMLFormElement) || !f.dataset.confirm) return;
    if (f.dataset.confirmed === '1') return;
    e.preventDefault();
    const ok = document.createElement('div'); ok.className = 'scrim open'; ok.style.zIndex = 70;
    ok.innerHTML = `<div class="modal open" style="pointer-events:auto"><div class="m-box" style="width:min(460px,100%)"><div class="m-h"><h2>Confirmar</h2></div><div class="m-b">${f.dataset.confirm}</div><div class="m-f"><button class="btn" data-x>Cancelar</button><button class="btn primary" data-ok>Confirmar</button></div></div></div>`;
    document.body.appendChild(ok);
    ok.querySelector('[data-x]').onclick = () => ok.remove();
    ok.querySelector('[data-ok]').onclick = () => { f.dataset.confirmed = '1'; ok.remove(); f.requestSubmit(); };
  });
})();
