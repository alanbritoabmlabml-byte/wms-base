# WMS Base

Módulo base de un **Warehouse Management System** para operación corporativa
multi-almacén y multi-sucursal, con captura en **colectores Zebra**.

Está pensado como cimiento versionable: v0.1 resuelve la topología del almacén,
el núcleo de stock con kardex, el motor de escaneo y un proceso operativo
completo (recepción). Cada versión posterior agrega un proceso entero y
desplegable, no un avance parcial. Ver [`docs/04-roadmap.md`](docs/04-roadmap.md).

```
wms-base/
├── api/    Backend Laravel 12 + MySQL — API REST, ledger transaccional
├── pwa/    Cliente PWA (Vue 3 + Vite) para el colector
└── docs/   Investigación, modelo de datos, contrato de API, integración Zebra
```

---

## Probarlo en un colector (o en cualquier teléfono) en 2 minutos

La PWA trae un **modo demostración**: un backend simulado sobre IndexedDB con
los mismos datos y las mismas reglas que el servidor real. No hace falta
levantar Laravel ni base de datos.

```bash
cd pwa
npm install
npm run dev -- --host      # imprime una URL http://192.168.x.x:5173
```

Abra esa URL en el colector (misma red WiFi) y entre con
`amoscoso` / `wms1234`. Menú ⋮ de Chrome → **Añadir a pantalla de inicio** para
instalarla como app.

Para que el lector funcione hay que crear un perfil en DataWedge —son cinco
opciones y está paso a paso en
[`docs/06-integracion-zebra.md`](docs/06-integracion-zebra.md). Mientras tanto,
cada pantalla de escaneo tiene los botones **Teclear** y **Cámara**, así que
también se puede probar en un celular común.

### Publicarlo en GitHub Pages

El repositorio incluye el workflow `.github/workflows/pages.yml`. Al hacer push
a `main`, compila la PWA en modo demo y la publica. Actívelo en
**Settings → Pages → Source: GitHub Actions**. Después basta con abrir la URL de
Pages en el colector.

---

## Qué trae la v0.1

**Cliente (colector)**
- Interfaz de terminal industrial: objetivos táctiles de 48 px, teclado numérico
  propio, tema claro/oscuro, alto contraste para uso con guantes y a contraluz.
- Motor de escaneo por **DataWedge keystroke** con detección de ráfaga, más
  entrada manual y lectura por cámara como respaldo.
- Parser **GS1-128** (AI 00, 01, 10, 11, 17, 21, 30, 37, 310n), con FNC1 y con
  paréntesis.
- **Cola offline idempotente**: todo escaneo se encola con un `client_uuid` y se
  envía solo al recuperar señal. Reintentar nunca duplica stock.
- Procesos: recepción de punta a punta, reubicación, consulta de stock por
  ubicación y por ítem (ordenada FEFO), kardex, pantalla de sincronización.
- Retroalimentación sonora y vibratoria: el operador trabaja mirando el pallet,
  no la pantalla.

**Servidor**
- Modelo multi-almacén donde `warehouse_id` es obligatorio en toda consulta de
  stock, y un middleware valida la asignación del usuario en cada request.
- `StockLedger`: **único** punto por donde pasa cualquier movimiento, con
  transacción, bloqueo pesimista por fila de saldo en orden determinista, y
  reconstrucción de saldo desde el kardex para auditar contra el ERP.
- Kardex append-only: `stock_balances` es una proyección, `stock_movements` es
  la verdad.
- API versionada `/api/v1` con endpoint de sincronización por lotes
  (`207 Multi-Status`).

---

## Documentación

| Documento | Contenido |
|---|---|
| [`01-investigacion-wms.md`](docs/01-investigacion-wms.md) | Cómo se construye un WMS corporativo: procesos, estrategias de picking, arquitectura multi-sitio, convivencia con el ERP, KPIs y por qué fracasan estos proyectos |
| [`02-modelo-de-datos.md`](docs/02-modelo-de-datos.md) | Esquema completo, invariante transaccional del ledger |
| [`03-codigos-de-barras.md`](docs/03-codigos-de-barras.md) | Convención de ubicaciones, etiquetado físico, GS1-128, SSCC |
| [`04-roadmap.md`](docs/04-roadmap.md) | Plan por versiones y criterio para dar una por terminada |
| [`05-contrato-api.md`](docs/05-contrato-api.md) | Endpoints, payloads, códigos de error, idempotencia |
| [`06-integracion-zebra.md`](docs/06-integracion-zebra.md) | DataWedge paso a paso, elección de equipo, red WiFi, impresión |
| [`07-offline-y-sincronizacion.md`](docs/07-offline-y-sincronizacion.md) | Cola de salida, idempotencia, límites reales de una PWA |

---

## Backend

```bash
cd api
composer install
cp .env.example .env && php artisan key:generate
# configure DB_* en .env
php artisan migrate --seed
php artisan serve --host=0.0.0.0 --port=8000
```

Luego, en la PWA: **Ajustes → Servidor** e ingrese `http://<ip>:8000`.
Dejarlo vacío vuelve al modo demostración.

Usuarios sembrados: `admin`, `amoscoso` (supervisor), `operador1` — contraseña
`wms1234` en todos.

> **Estado del backend**: el código está completo (modelos, migraciones, ledger,
> controladores, seeder y 36 tests) pero **no se pudo ejecutar `composer install`
> en el entorno donde se generó**, así que los tests no están verificados en
> verde. Cuente con una o dos iteraciones de ajuste en la primera instalación.
> La PWA sí está verificada de punta a punta.

---

## Decisiones de diseño que conviene no revertir

1. **El kardex es la verdad, el saldo es una proyección.** Cualquier saldo se
   reconstruye sumando movimientos. Es lo que permite auditar diferencias contra
   WorkCorp/SIMEC en vez de discutirlas.
2. **Nada mueve stock fuera de `StockLedger`.** Ni el seeder, que siembra los
   saldos iniciales a través de él para que el kardex cuadre desde el día cero.
3. **`client_uuid` obligatorio en toda escritura.** Sin él no hay cola offline
   segura.
4. **`warehouse_id` en todo.** Una sola consulta sin filtro de almacén es una
   fuga de datos entre sucursales.
5. **Un código de ubicación nunca se reutiliza.** Reutilizarlo convierte el
   historial en ficción.
6. **Traspaso entre sucursales = dos movimientos + almacén de tránsito.** Nunca
   un movimiento instantáneo.

---

## Licencia

MIT. Ver [`LICENSE`](LICENSE).
