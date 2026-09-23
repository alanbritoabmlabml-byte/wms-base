# Carmen WMS (wms-base)

Módulo base de un **Warehouse Management System** para operación corporativa
multi-almacén y multi-sucursal, con captura en **colectores Zebra**.

Está pensado como cimiento versionable: v0.1 resuelve la topología del almacén,
el núcleo de stock con kardex, el motor de escaneo y un proceso operativo
completo (recepción). Cada versión posterior agrega un proceso entero y
desplegable, no un avance parcial. Ver [`docs/04-roadmap.md`](docs/04-roadmap.md).

```
wms-base/
├── api/    Laravel 13 + MySQL — Carmen WMS (escritorio + colector) y API REST
├── pwa/    Cliente PWA (Vue 3 + Vite) para el colector Zebra
└── docs/   Investigación, modelo de datos, contrato de API, integración Zebra
    └── prototipo/   Prototipo navegable escritorio + colector (HTML autocontenido)
```

---

## Publicar en Render (enlace para todos)

El repositorio trae todo lo necesario: `render.yaml` (Blueprint), `api/Dockerfile`
y `api/docker/entrypoint.sh`. Render crea el servicio web (PHP 8.4 + Apache) y
una base PostgreSQL; al arrancar, el contenedor migra la base y carga los datos
de Carmen WMS la primera vez.

1. Entra a https://dashboard.render.com → **New +** → **Blueprint**.
2. Conecta GitHub si te lo pide y elige `alanbritoabmlabml-byte/wms-base`.
3. Render lee `render.yaml` y muestra `carmen-wms` (web) y `carmen-wms-db`
   (PostgreSQL). Pulsa **Apply**.
4. Espera a que el despliegue termine (5–10 min la primera vez) y abre la URL
   `https://carmen-wms-XXXX.onrender.com`. Usuario **amoscoso / wms1234**.

> Plan gratuito: el servicio se duerme tras 15 min sin uso (tarda ~1 min en
> despertar) y la base PostgreSQL gratuita vence a los 30 días. Para uso diario
> conviene el plan Starter del servicio y Basic de la base.

## Carmen WMS en Laravel 13 (v0.3) — instalación en Windows

La aplicación web es **la misma interfaz del artifact Carmen WMS**: login,
tablero, ingresos, pedidos y despacho, stock e inventario, mapa de almacén,
maestros, configuración (importar, etiquetas QR, usuarios, colectores,
parámetros) y el **modo colector** (Zebra TC21/TC52, responsive). Laravel
sirve la página, autentica al usuario, entrega los datos desde MySQL (tablas
`cw_*`) y guarda los cambios de recepción y picking que se hacen en la interfaz.
No usa Node ni Vite.

**Requisitos**: PHP 8.3 o superior (extensiones `pdo_mysql`, `mbstring`,
`openssl`, `fileinfo`), Composer 2 y MySQL 8 / MariaDB 10.6+. En Windows lo más
rápido es [Laravel Herd](https://herd.laravel.com) o XAMPP con PHP 8.3.

```powershell
cd C:\Users\DELL\Documents\wms-base\api
composer install
copy .env.example .env
php artisan key:generate
# Edite .env: DB_DATABASE, DB_USERNAME, DB_PASSWORD (cree antes la base vacía)
php artisan migrate --seed
php artisan serve --host=0.0.0.0 --port=8000
```

Abra `http://localhost:8000` y entre con **amoscoso / wms1234** (también
`jguasace`, `frivero`, `abolsas`… con la misma contraseña). Desde un Zebra o un
celular, abra `http://<ip-del-servidor>:8000`: la interfaz se adapta sola y el
botón del teléfono en la barra superior abre el modo colector.

| Pieza | Dónde está |
|---|---|
| Página (login + escritorio + colector) | `resources/views/carmen/app.blade.php`, `_shell.blade.php` |
| Estilos e interfaz (idénticos al artifact) | `public/carmen/app.css`, `public/carmen/app.js`, `public/carmen/vendor.js` |
| Enlace con Laravel (login, sesión, guardado) | `public/carmen/laravel.js` |
| Controlador | `app/Http/Controllers/Web/CarmenController.php` |
| Modelo de datos | `app/Support/CarmenSchema.php`, migración `2026_09_23_000300_create_carmen_tables` |
| Datos iniciales (los mismos del artifact) | `database/seeders/CarmenSeeder.php` + `database/seeders/data/carmen-demo.json` |

Las pantallas Blade de administración de la versión anterior siguen disponibles
en `/gestion` (importador CSV real, usuarios, parámetros).

> **Importación de datos**: por ahora acepta **CSV** (exportación de Excel:
> `Guardar como → CSV UTF-8`). Detecta separador `,` `;` o tabulador, BOM y
> Windows-1252. Las plantillas se descargan desde la misma pantalla.

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

### Prototipo navegable de escritorio (v0.2)

Antes de programar las vistas reales, el diseño completo del escritorio —y el
del colector— se puede recorrer en
[`docs/prototipo/index.html`](docs/prototipo/index.html): tablero, ingresos,
pedidos y despacho, stock e inventario cíclico, mapa de almacén, maestros y el
módulo de **configuración** (importador de datos, diseñador de etiquetas QR,
usuarios y roles, colectores, parámetros). Se publica en Pages bajo
`/prototipo/`. Detalle en [`docs/08-prototipo-escritorio.md`](docs/08-prototipo-escritorio.md).

### Publicarlo en GitHub Pages

El workflow está en `docs/github-pages.workflow.yml`; cópielo a
`.github/workflows/pages.yml` en el repositorio (esa carpeta no se puede
escribir desde la sesión que generó este código). Al hacer push a `main`,
compila la PWA en modo demo y la publica junto con `docs/prototipo/`. Actívelo
en **Settings → Pages → Source: GitHub Actions**.

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

## Backend y API del colector

La API del colector vive en la misma app (`/api/v1`, `routes/api.php`). Tras
levantar el servidor (sección anterior), en la PWA: **Ajustes → Servidor** e
ingrese `http://<ip>:8000`. Dejarlo vacío vuelve al modo demostración.

Usuarios sembrados (contraseña `wms1234`, PIN de colector `123456` / `1234`):

| Usuario | Tipo | Rol |
|---|---|---|
| `admin` | Escritorio + colector | ADMIN en todos los almacenes |
| `amoscoso` | Escritorio + colector | SUPERVISOR en Bolsas y Materia prima |
| `pgarcia` | Colector | SUPERVISOR en Bolsas |
| `rsuarez`, `jguasace`, `operador1` | Colector | OPERADOR en Bolsas |

Versiones: **Laravel 13** (`laravel/framework ^13.0`), PHP `^8.3`, Sanctum 4,
PHPUnit 12. `composer.json` incluye los scripts `composer setup` (instala,
crea `.env`, migra y siembra) y `composer test`.

> **Estado**: todo el PHP pasa `php -l` y las 42 vistas Blade una verificación
> de sintaxis, pero **no se pudo ejecutar `composer install` ni levantar la app
> en el entorno donde se generó** (sin acceso a Packagist). Cuente con una o dos
> iteraciones de ajuste en la primera instalación — sobre todo en la migración
> `2026_09_22_000200_create_desktop_tables` y en el seeder de escritorio. La
> PWA sí está verificada de punta a punta.

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
