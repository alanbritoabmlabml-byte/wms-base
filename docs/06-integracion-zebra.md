# Integración con colectores Zebra

Guía práctica para poner esta PWA a funcionar en los colectores, con el lector
láser/imager integrado. Probado contra el comportamiento estándar de DataWedge
en Android 11–14 (TC2x, TC5x, MC33xx, MC9xxx).

---

## 1. Cómo llega el código escaneado a una página web

DataWedge —el servicio de captura que viene en todo equipo Zebra— puede entregar
lo escaneado de tres maneras:

| Salida | Qué hace | ¿Sirve para una PWA? |
|---|---|---|
| **Keystroke** | "teclea" el código en el campo con foco, como un teclado | **Sí.** Es la única que funciona sin envolver la app en un APK |
| **Intent** | envía un `Intent` de Android con el dato en un extra | No: una página web no recibe intents |
| **IP / API** | lo manda por red o por API de servicio | no aplica |

Por eso el módulo base usa **Keystroke**. La app mantiene un campo invisible
siempre enfocado, mide la cadencia de las teclas (una ráfaga de < 45 ms entre
caracteres es un lector; un dedo humano nunca va tan rápido) y corta con el
ENTER final. El código está en `pwa/src/lib/scanner.js`.

Cuando en el futuro se empaquete como APK (WebView), conviene pasar a **Intent
Output**: es más robusto, no depende del foco y entrega además la simbología
(`com.symbol.datawedge.label_type`), que sirve para distinguir una etiqueta de
ubicación de una GS1 sin mirar el contenido.

---

## 2. Configuración de DataWedge, paso a paso

En el colector: **DataWedge** (viene instalado) → menú ⋮ → **New profile**.

1. **Profile name**: `WMS`
2. **Applications → Associated apps → New app/activity**
   - Para probar en el navegador: `com.android.chrome` → `*` (todas las actividades)
   - Para la PWA instalada desde Chrome ("Añadir a pantalla de inicio"), el
     paquete **no** es Chrome: es un WebAPK con nombre
     `org.chromium.webapk.<hash>`. Búsquelo en la lista de aplicaciones de esa
     pantalla; si no aparece, asocie el perfil a `*` (todas) mientras prueba.
     **Este es el error más común**: se configura para Chrome, se instala la PWA
     y el lector "deja de funcionar".
3. **Barcode input → Enabled**
   - **Decoders**: deje activos Code 128, Code 39, EAN-13, ITF y GS1 DataBar.
     Apagar los que no se usan **acelera la decodificación** de forma notoria.
   - **Scan params → Illumination**: `always on` si la nave es oscura.
4. **Keystroke output → Enabled**
   - **Action key character**: `LF` (o `CR`). Sin esto, la app no sabe dónde
     termina un código y depende del temporizador de respaldo.
   - **Key event delay**: `0`. Si nota caracteres perdidos en equipos antiguos,
     suba a `10` ms.
5. **Basic data formatting → Enabled**
   - **Send data**: `Enabled`
   - **Send ENTER key**: `Enabled`
   - **Send TAB key**: `Disabled`
6. **Intent output → Disabled** (con Keystroke activo, tenerlo también encendido
   duplica el escaneo).

Verificación rápida: abra la app, vaya a **Consulta** y dispare el gatillo sobre
cualquier etiqueta de ubicación. Debe sonar el pitido, vibrar y mostrar el
contenido de la ubicación. Si el código aparece **duplicado**, hay dos perfiles
activos sobre la misma app; si aparece **partido en dos**, falta el ENTER.

### Despliegue masivo
No configure 20 colectores a mano. Exporte el perfil desde DataWedge
(**⋮ → Settings → Export profile**, deja un `.db` en
`/storage/emulated/0/Android/data/com.symbol.datawedge/files/`) e impórtelo en
los demás, o genere un perfil de **StageNow** con un código de barras que el
operador escanea una vez y deja el equipo listo. Con MDM (SOTI, Scalefusion,
Workspace ONE), el perfil se envía como archivo administrado.

---

## 3. Instalar la app en el colector

### Opción A — PWA instalada (recomendada para empezar)
1. Publique el `pwa/dist/` en un servidor de la intranet o en GitHub Pages.
2. En el colector, abra la URL en Chrome.
3. Menú ⋮ → **Añadir a pantalla de inicio**. Queda como una app, a pantalla
   completa, sin barra de direcciones.
4. Asocie el perfil de DataWedge al WebAPK resultante (ver el punto 2.2).

Ventajas: se actualiza sola al recargar, no requiere firmar ni distribuir APK.
Limitación: depende de que Chrome esté actualizado; en colectores muy viejos
(Android 8) conviene verificar la versión de WebView.

### Opción B — Kiosco
Con **Enterprise Home Screen** (gratuito de Zebra) se deja el colector con la
app como única aplicación visible. Es lo que evita que el operador termine en
YouTube. Se configura con un archivo `enterprisehomescreen.xml`.

### Opción C — APK con WebView
Un proyecto Android mínimo que carga la misma URL en un `WebView` y traduce los
intents de DataWedge a eventos JavaScript vía `addJavascriptInterface`. Es el
paso natural cuando haga falta:
- control del gatillo por pantalla (activar/desactivar el lector según el paso),
- impresión directa a impresoras Zebra por Bluetooth (ZPL),
- almacenamiento offline mayor a la cuota del navegador,
- despliegue por MDM con versión controlada.

El código de la PWA no cambia: el puente se limita a llamar a
`window.__wmsScan(code)`, que ya está previsto en `scanner.js` a través de
`emitScan()`.

---

## 4. Elección de equipo

Para una operación de planta con 4+ almacenes, la referencia actual:

| Modelo | Perfil | Nota |
|---|---|---|
| **TC22 / TC27** | gama de entrada táctil | el más barato de la línea empresarial; suficiente para recepción y consulta |
| **TC53 / TC58** | caballo de batalla | pantalla 6", buena batería, lector SE4770; el estándar para picking |
| **MC3300ax** | pistola / ángulo de 45° | cuando se escanea mucho y alto: racks de 4 niveles con lector de largo alcance |
| **MC9400** | uso rudo, exteriores | patio, muelle, frío |
| **WT6000 + RS5100** | vestible, anillo | manos libres; solo si el volumen lo justifica |

Para escanear etiquetas de ubicación a 3–5 m en el nivel alto del rack hace
falta un lector de **rango extendido** (SE4850 o similar). Un imager estándar
obliga al operador a bajarse del montacargas, y eso se paga todos los días.

**Un consejo sobre baterías**: presupueste una batería de repuesto por equipo y
una base de carga múltiple. Un colector descargado a media mañana devuelve la
operación al papel, y de ahí cuesta mucho volver.

---

## 5. Red inalámbrica

El WMS es tan bueno como la cobertura WiFi, y la cobertura de una nave con racks
metálicos llenos no se parece a la de una oficina.

- **Site survey antes del despliegue**, con los racks **llenos**. Un relevamiento
  con la nave vacía no sirve: el producto absorbe señal.
- **Roaming**: todos los AP con el mismo SSID y soporte 802.11r/k/v. Sin roaming
  rápido, el colector se desconecta al cambiar de pasillo y el operador ve
  "sin red" caminando.
- **5 GHz** para los colectores; deje 2.4 GHz para equipos legados.
- Documente las **zonas muertas** conocidas (cámara de frío, fondo de nave,
  muelle exterior). La cola offline las cubre, pero hay que saber cuáles son
  para interpretar el indicador de "% de operaciones en cola".

---

## 6. Impresión de etiquetas

Aunque no está en v0.1, conviene decidirlo temprano porque condiciona el modelo:

- **Etiquetas de ubicación**: se imprimen una vez, en impresora de escritorio
  (ZD421 o similar), material resistente y adhesivo permanente. Tamaño grande:
  el montacarguista tiene que leerlas desde la cabina.
- **Etiquetas de pallet (SSCC)**: impresora móvil (ZQ511/ZQ630) en el cinturón
  del operador, o impresora fija en la zona de recepción. Aquí sí se imprime a
  demanda, en el momento.
- **Lenguaje**: ZPL directo. Desde una PWA se envía por HTTP a la impresora
  (las Link-OS tienen servidor HTTP) o a través del servidor Laravel; desde un
  APK, por Bluetooth con el Link-OS SDK.

---

## Fuentes

- [Intent Output — Zebra TechDocs](https://techdocs.zebra.com/datawedge/latest/guide/output/intent/)
- [Tutorial: recibir datos escaneados — Zebra TechDocs](https://techdocs.zebra.com/datawedge/8-0/guide/samples/tutorial-receivescanneddata/)
- [Enterprise Browser — Zebra TechDocs](https://techdocs.zebra.com/enterprise-browser/2-0/guide/about/)
- [Progressive Web Applications in the Enterprise — Zebra](https://developer.zebra.com/community/home/blog/2016/11/14/progressive-web-applications-in-the-enterprise)
- [TC53/TC58 — ficha técnica Zebra](https://www.zebra.com/us/en/products/spec-sheets/mobile-computers/handheld/tc53-tc58.html)
- [Zebra TC-Series: lineup actual y modelos discontinuados — Scalefusion](https://blog.scalefusion.com/zebra-tc-series-devices-compared/)
- [DataWedge Kotlin — ejemplo de referencia (Darryn Campbell, Zebra)](https://github.com/darryncampbell/DataWedgeKotlin)
