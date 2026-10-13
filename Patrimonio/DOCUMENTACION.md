# SIGPE — Sistema Institucional de Gestión de Patrimonio Escolar
## Documentación Técnica Completa

> **Institución:** E.E.S.T. N° 1 "Gral. Manuel Belgrano" · CUE: 0601429-00  
> **Marco normativo:** Decreto Ley 3.300/72 — Dirección General de Cultura y Educación (DGCyE), Provincia de Buenos Aires  
> **Stack:** PHP 8+ · MySQL / MariaDB · JavaScript Vanilla · CSS3 · XAMPP  

---

## Índice

1. [Resumen del Sistema](#1-resumen-del-sistema)
2. [Estructura de Archivos](#2-estructura-de-archivos)
3. [Base de Datos](#3-base-de-datos)
4. [Backend — Configuración y Helpers](#4-backend--configuración-y-helpers)
5. [Backend — APIs REST (PHP)](#5-backend--apis-rest-php)
6. [Frontend — Vista Principal (index.php)](#6-frontend--vista-principal-indexphp)
7. [Frontend — Módulo de API JS (api.js)](#7-frontend--módulo-de-api-js-apijs)
8. [Frontend — Aplicación JS (app.js)](#8-frontend--aplicación-js-appjs)
9. [Estilos CSS (style.css)](#9-estilos-css-stylecss)
10. [Vistas del Sistema](#10-vistas-del-sistema)
11. [Modales](#11-modales)
12. [Flujo de Autenticación](#12-flujo-de-autenticación)
13. [Flujo de un Alta de Bien](#13-flujo-de-un-alta-de-bien)
14. [Flujo de una Baja de Bien](#14-flujo-de-una-baja-de-bien)
15. [Cómo Instalar y Ejecutar](#15-cómo-instalar-y-ejecutar)

---

## 1. Resumen del Sistema

SIGPE es una aplicación web de **gestión patrimonial institucional** para escuelas técnicas de la Provincia de Buenos Aires. Permite:

- Registrar y mantener el **inventario de bienes** (herramientas, mobiliario, informática, consumibles) con codificación oficial.
- Generar **planillas trimestrales de Altas y Bajas** en el formato exigido por la DGCyE (Decreto Ley 3.300/72).
- Realizar la **auditoría física anual** mediante un mapa digital interactivo de ubicaciones.
- Visualizar **métricas y balances** en tiempo real (valuación total, altas/bajas del trimestre, progreso de auditoría).
- **Exportar** datos en CSV con formato oficial para envío a la Provincia o impresión en A4.

El sistema es una **Single-Page Application (SPA)** construida en PHP puro con renderizado del lado del servidor para la autenticación, y JavaScript Vanilla para la navegación dinámica entre vistas sin recargar la página.

---

## 2. Estructura de Archivos

```
Patrimonio/
├── index.php               # Página única: login + toda la app SPA
├── database.sql            # Script de creación e inicialización de la BD
├── DOCUMENTACION.md        # Este archivo
│
├── config/
│   └── db.php              # Conexión PDO, helpers de sesión y respuestas JSON
│
├── api/
│   ├── auth.php            # Login / Logout / Check de sesión
│   ├── bienes.php          # CRUD de bienes + baja patrimonial + auditoría
│   ├── movimientos.php     # Listado y creación de movimientos trimestrales
│   ├── estadisticas.php    # Métricas del dashboard y balances
│   ├── auditoria.php       # Datos del mapa digital y checklist de auditoría
│   └── exportar.php        # Generación de CSV / JSON oficial para la Provincia
│
└── assets/
    ├── css/
    │   └── style.css       # Todos los estilos: tema claro/oscuro, componentes
    ├── js/
    │   ├── api.js          # Cliente HTTP fetch() para todas las APIs
    │   └── app.js          # Lógica principal de la SPA (navegación, vistas, modales)
    └── img/
        ├── sigpe_logo.svg  # Logo vectorial del sistema
        └── sigpe_icon.svg  # Ícono/favicon
```

---

## 3. Base de Datos

**Nombre:** `patrimonio_escolar`  
**Charset:** `utf8mb4` / `utf8mb4_unicode_ci`  
**Motor:** InnoDB

### Tabla `usuarios`
Gestiona la cuenta única de administración.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | INT PK | Identificador |
| `nombre` | VARCHAR(150) | Nombre completo del usuario |
| `email` | VARCHAR(150) UNIQUE | Correo institucional |
| `password_hash` | VARCHAR(255) | Hash bcrypt de la contraseña |
| `rol` | ENUM('director','ayudante') | Rol del usuario |
| `cargo` | VARCHAR(150) | Cargo institucional (ej: "Administrador de Patrimonio") |
| `ultimo_acceso` | DATETIME | Última vez que inició sesión |

**Cuenta inicial:** `admin@patrimonio.edu.ar` / `admin123`

---

### Tabla `ubicaciones`
Representa los espacios físicos de la escuela (mapa digital).

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | INT PK | Identificador |
| `codigo` | VARCHAR(20) UNIQUE | Código corto (ej: `TAL-MEC`) |
| `nombre` | VARCHAR(100) | Nombre completo del sector |
| `tipo` | VARCHAR(50) | Taller / Laboratorio / Aulas / Administración / Depósito / Pañol |
| `responsable` | VARCHAR(150) | Nombre del docente o encargado |
| `piso` | VARCHAR(50) | Planta Baja / 1° Piso / etc. |
| `icono` | VARCHAR(50) | Nombre del ícono (wrench, flame, hammer, etc.) |

**9 ubicaciones predefinidas:**
- `TAL-MEC` — Taller de Mecánica y Ajuste
- `TAL-SOL` — Taller de Soldadura y Herrería
- `TAL-CAR` — Taller de Carpintería y Modelistería
- `PAN-CEN` — Pañol Central de Herramientas
- `LAB-INF` — Laboratorio de Informática y Redes
- `LAB-ELE` — Laboratorio de Electrónica y Automatización
- `AUL-GEN` — Aulas de Ciclo Básico y Superior
- `DIR-SEC` — Dirección, Secretaría y Sala de Profesores
- `DEP-GEN` — Depósito General y Materiales de Consumo

---

### Tabla `bienes`
El inventario principal — cada fila es un bien patrimonial.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | INT PK | Identificador interno |
| `nro_cuenta` | VARCHAR(50) | Código de cuenta gubernamental (01-CON, 02-MOB, 03-INF, 04-HER) |
| `nro_orden` | INT | Número de orden dentro de la cuenta, auto-incremental |
| `identificacion` | VARCHAR(255) | Denominación detallada del bien |
| `categoria` | VARCHAR(100) | Herramientas de Taller / Mobiliario / Informático / Consumo / Instrumentos |
| `ubicacion_id` | INT FK | FK a `ubicaciones` |
| `ubicacion_nombre` | VARCHAR(150) | Nombre de la ubicación (desnormalizado para planillas) |
| `cantidad` | INT | Unidades físicas |
| `valor_unitario` | DECIMAL(14,2) | Precio unitario en ARS |
| `valor_total` | DECIMAL(14,2) | `cantidad × valor_unitario` |
| `estado` | ENUM | Bueno / Regular / En Reparación / Baja |
| `motivo_alta` | VARCHAR(150) | Origen del bien (Compra DGCyE, Plan Mejora, Donación, etc.) |
| `expediente` | VARCHAR(100) | N° de expediente GDEBA/DGCyE |
| `fecha_alta` | DATE | Fecha de incorporación |
| `auditado_anual` | TINYINT(1) | 0 = pendiente, 1 = verificado físicamente |
| `fecha_auditoria` | DATETIME | Cuándo se verificó en la auditoría |
| `auditor_nombre` | VARCHAR(150) | Quién lo verificó |
| `observaciones` | TEXT | Notas adicionales (series, accesorios, etc.) |
| `activo` | TINYINT(1) | 1 = activo, 0 = dado de baja |

---

### Tabla `movimientos`
Registro oficial de Altas y Bajas para las planillas trimestrales.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | INT PK | Identificador |
| `bien_id` | INT | FK al bien relacionado (puede ser NULL) |
| `tipo` | ENUM('ALTA','BAJA') | Tipo de movimiento |
| `trimestre` | INT | Trimestre (1–4) |
| `anio` | INT | Año del movimiento |
| `nro_cuenta` | VARCHAR(50) | Cuenta gubernamental |
| `nro_orden` | INT | Orden del bien |
| `identificacion` | VARCHAR(255) | Denominación del bien |
| `cantidad` | INT | Cantidad involucrada |
| `valor_unitario` | DECIMAL(14,2) | Valor unitario |
| `valor_total` | DECIMAL(14,2) | Valor total del movimiento |
| `motivo` | VARCHAR(255) | Causa del alta o motivo legal de la baja |
| `expediente` | VARCHAR(100) | N° expediente DGCyE |
| `fecha` | DATE | Fecha del movimiento |
| `usuario_nombre` | VARCHAR(150) | Nombre del responsable |
| `acta_disposicion` | VARCHAR(100) | N° de acta (solo para bajas) |
| `observaciones` | TEXT | Detalle técnico |

---

### Tabla `institucion_config`
Configuración institucional usada en encabezados oficiales de planillas.

| Campo | Descripción |
|---|---|
| `nombre_escuela` | Nombre oficial de la institución |
| `cue` | Código Único de Establecimiento |
| `distrito` | Distrito escolar / Región |
| `director_nombre` | Nombre del Director |
| `delegado_patrimonial` | Nombre del Delegado Patrimonial |
| `secretario_nombre` | Nombre del Secretario |
| `decreto_referencia` | Marco normativo |
| `trimestre_activo` | Trimestre activo para métricas del dashboard |
| `anio_activo` | Año activo |

---

## 4. Backend — Configuración y Helpers

### `config/db.php`

Centraliza la conexión a la base de datos y las funciones helper globales.

**`getDBConnection()`**
- Usa el patrón **singleton estático** para reutilizar la misma conexión PDO.
- Si la base de datos no existe, la **crea automáticamente** con `CREATE DATABASE IF NOT EXISTS` y ejecuta `database.sql`.
- Configura PDO en modo excepción y retorno como array asociativo.

**`jsonResponse($data, $statusCode = 200)`**
- Helper para todas las APIs: establece el código HTTP, cabecera `Content-Type: application/json` y hace `echo json_encode(...)` con `JSON_UNESCAPED_UNICODE`.

**`getCurrentUser()`**
- Devuelve `$_SESSION['usuario']` si existe, o `null`.

**`requireAuth()`**
- Si no hay sesión activa, responde con JSON `401 No autorizado` y detiene la ejecución.

**`requireRole($allowedRoles)`**
- Si el rol del usuario no está en la lista permitida, responde `403 Acceso denegado`.

---

## 5. Backend — APIs REST (PHP)

Todas las APIs reciben `?action=...` por GET y datos por JSON en el body de las peticiones POST.

---

### `api/auth.php` — Autenticación

| Action | Método | Descripción |
|---|---|---|
| `login` | POST | Verifica credenciales con `password_verify()` (hash bcrypt). Guarda usuario en `$_SESSION`. |
| `check` | GET | Devuelve si hay sesión activa y los datos del usuario. |
| `logout` | GET/POST | Destruye la sesión y limpia la cookie. |

El login acepta búsqueda por **email completo**, **alias** o **nombre parcial**.

---

### `api/bienes.php` — Catálogo de Bienes

| Action | Método | Descripción |
|---|---|---|
| `list` | GET | Lista bienes con filtros: `search`, `categoria`, `ubicacion`, `estado`, `cuenta`, `auditado`, `activos`. Devuelve totales de valor y unidades. |
| `get` | GET | Obtiene un bien por `?id=`. |
| `create` | POST | Registra un nuevo bien. Auto-calcula `nro_orden`. Genera automáticamente un movimiento de ALTA en la tabla `movimientos`. |
| `update` | POST | Edita todos los campos de un bien existente. |
| `baja` | POST | Registra la baja oficial (Decreto 3.300/72): crea movimiento de BAJA, actualiza cantidad o desactiva el bien. |
| `toggle_audit` | POST | Alterna `auditado_anual` (0→1 o 1→0), registrando auditor y fecha. |

---

### `api/movimientos.php` — Planillas Trimestrales

| Action | Método | Descripción |
|---|---|---|
| `list` | GET | Lista movimientos filtrables por `tipo` (ALTA/BAJA), `trimestre` y `anio`. Devuelve sumatorias. |
| `create` | POST | Registra un movimiento manual independiente de un bien. |

---

### `api/estadisticas.php` — Dashboard y Métricas

| Action | Descripción |
|---|---|
| `dashboard` | Devuelve: datos de la institución, totales del catálogo, altas/bajas del trimestre activo, desglose por categoría, por cuenta y por estado de conservación. |

---

### `api/auditoria.php` — Mapa y Auditoría

| Action | Descripción |
|---|---|
| `map_data` | Obtiene todas las ubicaciones con sus bienes agrupados. Calcula por ubicación: total ítems, unidades, auditados, pendientes, valor total y porcentaje de progreso. También devuelve el resumen global. |

---

### `api/exportar.php` — Exportación Oficial

| Parámetro | Descripción |
|---|---|
| `format=csv` | Genera y descarga un archivo `.csv` con BOM UTF-8 (compatible con Excel) |
| `format=json` | Devuelve los datos en JSON para integración |
| `tipo=ALTAS` | Planilla de Altas del trimestre |
| `tipo=BAJAS` | Planilla de Bajas del trimestre |
| `tipo=INVENTARIO` | Inventario general de bienes activos |
| `trimestre=N&anio=YYYY` | Período de filtro |

El CSV incluye encabezado oficial (institución, CUE, distrito, período, normativa), columnas del formulario, fila de totales y firma al pie con Director y Delegado Patrimonial.

---

## 6. Frontend — Vista Principal (index.php)

El archivo `index.php` tiene **dos grandes bloques** controlados por PHP:

### Bloque 1 — Login (usuario no autenticado)
Se muestra si `$currentUser` es `null`. Contiene:
- Logo e identificación institucional (SIGPE · DGCyE · Decreto 3.300/72).
- Formulario con campos **usuario/email** y **contraseña** (con botón mostrar/ocultar).
- Alerta de error dinámica.
- Botón de relleno rápido de la cuenta admin.
- Botón de cambio de tema claro/oscuro.

### Bloque 2 — App Principal (usuario autenticado)
Contiene el **header**, las **5 vistas** y los **4 modales**.

#### Header
- **Top bar:** franja con gradiente verde-azul, nombre del sistema y establecimiento.
- **Nav bar:** logo + nombre SIGPE, menú de navegación (5 botones), botón "+ Registrar Alta", badge del usuario activo, botón "Cerrar Sesión", botón de tema.

---

## 7. Frontend — Módulo de API JS (api.js)

Define el objeto global `API` con un método `request()` central basado en `fetch()`.

**Namespaces disponibles:**

| Namespace | Métodos |
|---|---|
| `API.auth` | `check()`, `login(email, password)`, `logout()` |
| `API.bienes` | `list(params)`, `get(id)`, `create(data)`, `update(data)`, `baja(data)`, `toggleAudit(id)` |
| `API.movimientos` | `list(params)`, `create(data)` |
| `API.auditoria` | `mapData()` |
| `API.estadisticas` | `dashboard()` |
| `API.exportar` | `csvUrl(tipo, trimestre, anio)`, `json(tipo, trimestre, anio)` |

Serializa automáticamente objetos JS a JSON, maneja errores de red y usa `api/` como URL base.

---

## 8. Frontend — Aplicación JS (app.js)

Define el objeto global `App` con toda la lógica de la SPA.

### Funciones principales

| Función | Descripción |
|---|---|
| `App.init()` | Inicializa la app, carga el dashboard, registra listeners y aplica el tema guardado. |
| `App.navigate(viewName)` | Cambia la vista activa y carga sus datos. |
| `App.handleLogin(e)` | Procesa el formulario de login y redirige si es exitoso. |
| `App.logout()` | Cierra sesión vía API y redirige. |
| `App.loadDashboard()` | Llena las tarjetas de métricas y movimientos recientes. |
| `App.loadCatalogo()` | Carga y renderiza la tabla del inventario. |
| `App.filterCatalogo()` | Aplica filtros locales sobre datos ya cargados (sin nueva petición). |
| `App.loadPlanillas()` | Carga y renderiza la planilla oficial según tipo y período. |
| `App.renderOfficialSheet()` | Genera el HTML con formato de formulario DGCyE listo para imprimir. |
| `App.loadMapaAuditoria()` | Dibuja las tarjetas de sectores del mapa digital. |
| `App.selectSector()` | Muestra el panel lateral con checklist del sector seleccionado. |
| `App.toggleAudit()` | Marca/desmarca un bien como auditado y actualiza barras de progreso. |
| `App.loadCostos()` | Rellena KPIs, grilla por cuentas y tabla de bienes de mayor valor. |
| `App.openAltaModal()` | Abre el modal de alta con la fecha actual pre-rellenada. |
| `App.openBajaModal()` | Abre el modal de baja con los datos del bien. |
| `App.openFichaModal()` | Carga la ficha completa del bien (con QR) en el modal. |
| `App.openEditModal()` | Pre-popula el formulario de edición con los datos actuales del bien. |
| `App.handleAltaSubmit()` | Valida y envía el formulario de alta. |
| `App.handleBajaSubmit()` | Valida y envía el formulario de baja. |
| `App.handleEditSubmit()` | Valida y envía el formulario de edición. |
| `App.toggleTheme()` | Alterna tema claro/oscuro y persiste en `localStorage`. |
| `App.formatCurrency(v)` | Formatea número como `$ 1.234.567,89` en locale `es-AR`. |
| `App.showToast(msg, type)` | Muestra notificación flotante temporal. |

---

## 9. Estilos CSS (style.css)

El archivo usa **variables CSS** en `:root` y `[data-theme="dark"]` para el **tema dual claro/oscuro**.

### Sistema de Diseño

| Token | Valor / Descripción |
|---|---|
| `--primary` | `#16a34a` — Verde institucional |
| `--primary-dark` | `#15803d` — Verde hover |
| `--danger` | Rojo para bajas y acciones destructivas |
| `--bg-main` | Fondo general de la app |
| `--bg-surface` | Fondo de cards y paneles |
| `--font-sans` | `Inter, system-ui` |
| `--font-mono` | `JetBrains Mono, monospace` (valores monetarios) |
| `--radius-md` | `12px` — Border-radius estándar |

### Componentes CSS principales

| Clase | Descripción |
|---|---|
| `.login-page-wrapper` / `.login-card` | Pantalla de login centrada con glassmorphism |
| `.top-bar` | Franja con gradiente verde-azul |
| `.main-nav` | Barra de navegación principal |
| `.nav-item-btn` | Botón de navegación con estado `active` |
| `.view-section` | Contenedor de vista, `display: none` por defecto |
| `.view-section.active` | Vista visible |
| `.metrics-grid` | Grid de 4 columnas para tarjetas de métricas |
| `.metric-card` | Tarjeta de indicador KPI |
| `.card` | Panel genérico con sombra y borde |
| `.data-table` | Tabla con hover en filas |
| `.toolbar-bar` | Barra de filtros y controles |
| `.modal-backdrop` | Overlay oscuro del modal |
| `.modal-dialog` | Contenedor del modal con animación de entrada |
| `.form-grid` | Grid de 2 columnas para formularios |
| `.btn` | Botón base; variantes: `.btn-primary`, `.btn-outline`, `.btn-danger`, `.btn-sm` |
| `.badge` | Etiqueta de estado coloreada |
| `.school-map-container` | Grid de 2 columnas: mapa + panel de auditoría |
| `.sector-progress-bar` | Barra de progreso de auditoría |
| `.official-sheet-preview` | Planilla con estilos de impresión |
| `.bg-glow-orb` | Orbes de fondo animados decorativos |

### Soporte de impresión
Bloque `@media print` que oculta header, nav, controles (`.no-print`) y modales, y ajusta la planilla para papel A4 vertical.

---

## 10. Vistas del Sistema

### Vista 1 — Dashboard (Panel de Control)
- **4 tarjetas de métricas:** Valuación Total, Altas del Trimestre, Bajas del Trimestre, Progreso de Auditoría.
- **Distribución por Categoría:** lista con barras de progreso relativas.
- **Movimientos Recientes:** tabla con las últimas altas y bajas del trimestre activo.

### Vista 2 — Catálogo de Bienes
- **Barra de filtros:** búsqueda libre, filtro por cuenta, categoría y estado.
- **Contador y valuación** filtrada en tiempo real.
- **Tabla completa** con: N° Cuenta, N° Orden, Denominación, Categoría, Ubicación, Cantidad, Valor Unitario, Valor Total, Estado, Auditoría, Acciones.
- Acciones por fila: Ver Ficha, Editar, Baja, Toggle Auditoría.

### Vista 3 — Planillas Trimestrales
- **Selector de tipo:** Planilla de Altas / Planilla de Bajas / Inventario General.
- **Selector de período:** trimestre (1–4) y año.
- **Preview de la planilla oficial:** encabezado con logo e info institucional, tabla de bienes, totales y espacio para firmas.
- Botones: "Imprimir Formulario Oficial (A4)" y "Descargar CSV".

### Vista 4 — Mapa Digital & Auditoría
- **Barra de progreso global** de la auditoría física anual.
- **Grilla de 9 sectores:** tarjetas con ícono, nombre, responsable, ítems y mini-barra de progreso.
- **Panel lateral de checklist:** al seleccionar un sector, muestra la lista de bienes con checkbox de verificación física.

### Vista 5 — Trazabilidad & Costos
- **3 KPIs:** Valuación Total Activa, Unidades Físicas Durables, Costo Promedio por Unidad.
- **Grilla por cuentas patrimoniales:** tarjeta por cada cuenta con cantidad, unidades y valuación.
- **Tabla de bienes de mayor valor:** top N activos más costosos del inventario.

---

## 11. Modales

### Modal Alta de Bien (`#modal-alta-bien`)
Campos: N° de Cuenta, Categoría, Denominación, Ubicación Física, Estado, Cantidad, Valor Unitario (con cálculo automático de Valuación Total), Motivo de Alta, N° Expediente/GDEBA (auto-generado), Fecha de Ingreso (fecha actual), Observaciones.

### Modal Baja de Bien (`#modal-baja-bien`)
Contiene aviso legal. Campos: nombre del bien (solo lectura), cantidad a dar de baja, fecha, motivo legal (Art. 45 Decreto 3.300/72), N° expediente de baja, N° acta/disposición, justificación técnica del dictamen.

### Modal Ficha de Bien (`#modal-ficha-bien`)
Ficha oficial del activo con todos sus datos y un código QR generado dinámicamente. Botón "Imprimir Rótulo".

### Modal Editar Bien (`#modal-edit-bien`)
Formulario pre-populado con los datos actuales del bien para modificar cuenta, orden, identificación, categoría, ubicación, cantidad, valor, estado, expediente y observaciones.

---

## 12. Flujo de Autenticación

```
Usuario ingresa credenciales
  │
  ▼
POST api/auth.php?action=login
  ├─ Busca en BD por email o nombre
  ├─ Verifica password_verify() con hash bcrypt
  ├─ Si OK → guarda en $_SESSION['usuario'], genera token
  └─ Responde {success: true}
        │
        ▼
app.js → redirige a index.php
        │
        ▼
index.php verifica getCurrentUser()
  ├─ NULL → muestra pantalla de login
  └─ Existe → renderiza toda la app SPA
```

El cierre de sesión puede hacerse por JS (`App.logout()`) o directamente por URL (`index.php?logout`).

---

## 13. Flujo de un Alta de Bien

```
Clic en "+ Registrar Alta"
  │
App.openAltaModal() → pre-rellena fecha actual
  │
Usuario completa el formulario y confirma
  │
App.handleAltaSubmit()
  └─ POST api/bienes.php?action=create
        ├─ Auto-calcula nro_orden (MAX + 1 por cuenta)
        ├─ Calcula valor_total = cantidad × valor_unitario
        ├─ Resuelve ubicacion_nombre desde la BD
        ├─ INSERT en tabla `bienes`
        ├─ Calcula trimestre y año de la fecha_alta
        └─ INSERT en tabla `movimientos` (tipo='ALTA')
              │
              ▼
        Responde {success: true, id: N}
              │
App.showToast("Bien registrado") → cierra modal → recarga datos
```

---

## 14. Flujo de una Baja de Bien

```
Clic en "Baja" en la fila del bien
  │
App.openBajaModal(bienId, nombre, cantidad)
  │
Usuario completa motivo, expediente y acta, confirma
  │
App.handleBajaSubmit()
  └─ POST api/bienes.php?action=baja
        ├─ Carga el bien de la BD
        ├─ Calcula valor_total_baja = cantidad_baja × valor_unitario
        ├─ INSERT en `movimientos` (tipo='BAJA', con acta_disposicion)
        ├─ Si baja total:
        │   UPDATE bienes SET activo=0, estado='Baja'
        └─ Si baja parcial:
            UPDATE bienes SET cantidad = cantidad - cantidad_baja
              │
              ▼
        Responde {success: true}
              │
App.showToast("Baja procesada") → recarga catálogo
```

---

## 15. Cómo Instalar y Ejecutar

### Requisitos
- **XAMPP** (PHP 8+ y MySQL/MariaDB)
- Navegador moderno (Chrome, Firefox, Edge)

### Pasos

1. Copiar el proyecto a:
   ```
   c:\xampp\htdocs\Patrimonio\
   ```

2. Iniciar XAMPP: encender **Apache** y **MySQL**.

3. Acceder en el navegador:
   ```
   http://localhost/Patrimonio/
   ```
   La primera vez, `config/db.php` detecta que la BD no existe y **la crea automáticamente** ejecutando `database.sql`.

4. Iniciar sesión:
   - **Email:** `admin@patrimonio.edu.ar`
   - **Contraseña:** `admin123`
   
   O usar el botón de acceso rápido en la pantalla de login.

5. Comenzar a cargar bienes desde el botón "+ Registrar Alta".

### Notas de configuración

Si la contraseña de MySQL es diferente a la de XAMPP por defecto, editar en `config/db.php`:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'patrimonio_escolar');
```

- El CSV exportado incluye BOM UTF-8 para compatibilidad con Microsoft Excel.
- La impresión de planillas está optimizada para papel **A4 vertical**.

---

*Documentación generada automáticamente — SIGPE v1.0*
