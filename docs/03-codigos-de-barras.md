# Codificación y etiquetado

Las decisiones de este documento son casi imposibles de revertir una vez que hay
etiquetas pegadas en 3.000 posiciones de rack. Conviene cerrarlas antes de
imprimir la primera.

---

## 1. Ubicaciones

### Convención
```
E1A - C01 - N03
 │     │     └── Nivel (N01 = piso, N02, N03, N04)
 │     └──────── Columna / módulo del rack (C01 … C20)
 └────────────── Zona + pasillo + lado (E1 = estantería 1, A/B = lado)
```

Zonas operativas fuera de rack, con nombre propio y sin jerarquía:
`RECEP-01`, `DESP-01`, `CUAR-01`, `PISO-01`.

Reglas:
- **Longitud fija** y siempre el mismo número de dígitos (`C01`, no `C1`).
  Un código de largo variable rompe cualquier ordenamiento y cualquier parser.
- **Solo mayúsculas, dígitos y guion.** Nada de espacios, `/`, `#` ni acentos:
  complican el escaneo, el URL-encoding y la impresión.
- **Un código de ubicación nunca se reutiliza.** Si se desarma un rack, la
  ubicación se marca inactiva y su código queda quemado para siempre. Reutilizar
  significa que el kardex histórico apunta a un lugar físico distinto — y el día
  que se investiga una diferencia, el historial miente.
- El código impreso (`barcode`) puede diferir del legible (`code`); por eso son
  dos campos. Útil si más adelante se agrega un prefijo de verificación.

### Simbología y etiqueta física
- **Code 128** (subconjunto B). Es denso, alfanumérico y lo decodifica cualquier
  lector Zebra sin configuración especial.
- Altura de barras: **mínimo 15 mm**; para niveles altos de rack, 25–30 mm.
- Texto legible **grande** debajo del código: el montacarguista lee el texto
  desde la cabina antes de acercar el lector.
- Material: poliéster o vinilo con adhesivo permanente. El papel térmico se
  borra en seis meses.
- **Etiqueta doble**: una pequeña a la altura de la mano (para el operador de
  piso) y una grande en el frente del módulo (para el montacarguista). Cuestan
  centavos y ahorran minutos por movimiento.

### Cuadrícula de impresión
Para 3.000 ubicaciones no se imprime a mano: se genera un lote desde el WMS en
ZPL. Plantilla mínima:

```zpl
^XA
^FO30,20^BY3^BCN,120,N,N,N^FD E1A-C01-N03 ^FS
^FO30,155^A0N,60,60^FD E1A-C01-N03 ^FS
^XZ
```

---

## 2. Ítems

El SKU interno de 12 dígitos que ya se usa (`5T2010201559`) funciona bien: es
fijo, numérico y único. No hay razón para cambiarlo.

Lo que sí conviene agregar:

### Múltiples códigos por ítem
La tabla `item_barcodes` permite que un mismo ítem tenga varios códigos, cada
uno con **su unidad y su factor**:

| barcode | uom | qty_per_scan | uso |
|---|---|---|---|
| `5T2010201559` | BUL | 1 | etiqueta de bulto individual |
| `P5T2010201559` | BUL | 10 | etiqueta de paquete de 10 bultos |
| `7501234567890` | BUL | 1 | EAN del proveedor (materia prima) |

Escanear el código de paquete suma 10 de una vez. En un pallet de 40 bultos son
4 escaneos en vez de 40. Es la mejora de productividad más barata que existe en
un WMS.

---

## 3. Lotes y vencimiento: GS1-128

Para producción propia conviene imprimir la etiqueta de lote en formato GS1-128
desde el inicio, aunque al principio solo se use el lote.

Identificadores de aplicación (AI) relevantes:

| AI | Significado | Formato | Ejemplo |
|---|---|---|---|
| `00` | SSCC (matrícula de pallet) | 18 dígitos | `00 0 7701234 000000123 4` |
| `01` | GTIN del producto | 14 dígitos | `01 07501234567890` |
| `10` | Lote | hasta 20 alfanum. | `10 L2609A` |
| `11` | Fecha de producción | AAMMDD | `11 260919` |
| `17` | Fecha de vencimiento | AAMMDD | `17 270331` |
| `37` | Cantidad contenida | hasta 8 dígitos | `37 40` |
| `310n` | Peso neto en kg (n decimales) | 6 dígitos | `3102 001250` = 12,50 kg |

Los AI de longitud variable (`10`, `21`, `37`…) terminan con el separador
**FNC1**, que el lector entrega como el carácter `GS` (0x1D). El parser de la
app (`pwa/src/lib/gs1.js`) maneja tanto el formato con FNC1 como el de
paréntesis `(01)...(10)...`, y aplica la regla GS1 de que día `00` significa
"último día del mes".

**Detalle que cuesta caro descubrir tarde**: si en DataWedge se configura un
formateo que elimina caracteres no imprimibles, el FNC1 desaparece y los campos
de longitud variable se pegan unos con otros. Deje el *Basic data formatting*
sin sustituciones y valide con una etiqueta real antes de imprimir mil.

---

## 4. Pallets (SSCC) — cuando llegue el momento

El SSCC es una matrícula única de 18 dígitos que identifica una **unidad
logística** (un pallet armado), no un producto. Su valor es que convierte
"mover 40 bultos del ítem X lote Y" en "mover el pallet 000770123400000012 3":
un escaneo.

Estructura: `[dígito de extensión][prefijo GS1 de la empresa][correlativo][dígito de control]`.

Requiere afiliación a **GS1 Bolivia** para obtener el prefijo de empresa. Si el
uso es puramente interno, se puede usar un correlativo propio con prefijo
reservado — pero entonces la etiqueta no sirve para intercambio con clientes, y
migrar después obliga a reetiquetar. Vale la pena averiguar el costo antes de
decidir.

Este proyecto **no** implementa SSCC en v0.1, pero el modelo de datos está
preparado: agregar `lpn_id` a `stock_balances` y `stock_movements` es una
migración aditiva (ver `docs/04-roadmap.md`).

---

## 5. Identificación de personas

El operador se identifica con usuario y contraseña al inicio del turno, y con
**PIN de 6 dígitos** al reanudar. Alternativa que funciona muy bien en piso:
una credencial con Code 128 que el operador escanea; es más rápido que teclear
con guantes y no se comparte tan fácilmente como una contraseña.

---

## Fuentes

- [GS1 Logistic Label Guideline](https://www.gs1.org/standards/gs1-logistic-label-guideline/current-standard)
- [GS1-128 / SSCC-18 Labels — EDI Academy](https://ediacademy.com/blog/gs1-128-sscc-18-labels-edi-guides/)
- [GS1 Barcodes Explained: GTIN, AIs, GS1-128, DataMatrix](https://comcomponent.com/en/blog/2026/04/16/000-gs1-barcode-standards-practical-guide/)
- [Pallet Labeling Regulations — GS1 Requirements](https://www.ubscode.com/en/learn-with-us/international-regulation-for-pallet-labeling/)
