<?php
// index.php - Página principal del Sistema de Patrimonio Escolar
require_once __DIR__ . '/config/db.php';

// Manejo de cierre de sesión
if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header("Location: index.php");
    exit;
}

$currentUser = getCurrentUser();
$pdo = getDBConnection();

// Obtener ubicaciones solo si el usuario está autenticado
$ubicaciones = [];
if ($currentUser) {
    $stmtUbi = $pdo->query("SELECT id, codigo, nombre FROM ubicaciones ORDER BY nombre ASC");
    $ubicaciones = $stmtUbi->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SIGPE - Sistema Institucional de Gestión de Patrimonio Escolar</title>
  <meta name="description" content="Plataforma SIGPE de gestión patrimonial, auditoría física y generación de planillas oficiales de Altas y Bajas para instituciones de educación técnica de la Provincia de Buenos Aires.">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="icon" type="image/svg+xml" href="assets/img/sigpe_icon.svg">
</head>
<body>

  <!-- Glow orbs background -->
  <div class="bg-glow-orb"></div>
  <div class="bg-glow-orb-2"></div>

  <?php if (!$currentUser): ?>
  <!-- ========================================================== -->
  <!-- PANTALLA DE ACCESO RESTRINGIDO (LOGIN INSTITUCIONAL SIGPE) -->
  <!-- Nadie puede ver ni interactuar con el sistema sin iniciar sesión -->
  <!-- ========================================================== -->
  <div style="position: absolute; top: 20px; right: 20px; z-index: 20;">
    <button class="btn-theme-toggle" id="btn-theme-toggle" title="Cambiar Tema Claro / Oscuro">
      <!-- Icon injected by JS -->
    </button>
  </div>

  <div class="login-page-wrapper">
    <div class="login-card">
      <div class="login-header-group">
        <img src="assets/img/sigpe_logo.svg" alt="Logo SIGPE" style="width: 140px; height: auto; margin: 0 auto 12px auto; display: block; filter: drop-shadow(0 6px 16px rgba(22, 163, 74, 0.25));">
        <h1 class="login-title" style="color: var(--primary); letter-spacing: 0.05em;">SIGPE</h1>
        <p class="login-subtitle">
          Sistema Institucional de Gestión de Patrimonio Escolar<br>
          <strong>DGCyE Provincia de Buenos Aires &bull; Decreto Ley 3.300/72</strong>
        </p>
      </div>

      <div class="login-error-alert" id="login-error-alert"></div>

      <form id="login-form">
        <div class="login-form-group">
          <label class="form-label">Usuario o Correo Institucional</label>
          <div class="login-input-wrapper">
            <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
            <input type="text" class="login-input" id="login-email" placeholder="admin@patrimonio.edu.ar o admin" required autocomplete="username">
          </div>
        </div>

        <div class="login-form-group">
          <label class="form-label">Contraseña de Seguridad</label>
          <div class="login-input-wrapper">
            <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
            <input type="password" class="login-input" id="login-password" placeholder="••••••••" required autocomplete="current-password">
            <button type="button" class="login-toggle-pwd" onclick="App.toggleLoginPassword()" title="Mostrar/ocultar contraseña">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
            </button>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-login-submit" id="btn-login-submit">
          Ingresar al Sistema
        </button>
      </form>

      <div class="login-divider">Acceso Autorizado Único</div>

      <button class="quick-account-btn" type="button" onclick="App.fillLoginForm('admin@patrimonio.edu.ar', 'admin123')" style="justify-content: center; gap: 10px;">
        <img src="assets/img/sigpe_icon.svg" style="width: 24px; height: 24px; border-radius: 6px;" alt="SIGPE">
        <span style="font-size: 0.85rem;">Rellenar cuenta: <strong>admin@patrimonio.edu.ar</strong></span>
      </button>

      <div class="login-footer-meta">
        Establecimiento: luciano reyes &bull; CUE: 0601429-00<br>
        Acceso restringido: Cuenta única de administración patrimonial institucional.
      </div>
    </div>
  </div>

  <?php else: ?>
  <!-- ========================================================== -->
  <!-- SISTEMA PRINCIPAL (AUTENTICADO CON UNA SOLA CUENTA ACTIVA) -->
  <!-- ========================================================== -->
  <!-- Header -->
  <header class="app-header">
    <div class="top-bar" style="background: linear-gradient(90deg, #14532d 0%, #15803d 50%, #0369a1 100%);">
      <div class="top-bar-tags">
        <span class="top-bar-tag">
          <img src="assets/img/sigpe_icon.svg" style="width: 14px; height: 14px; border-radius: 3px;" alt="SIGPE">
          SIGPE &bull; DGCyE - Provincia de Buenos Aires
        </span>
        <span class="top-bar-tag">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
          Decreto Ley 3.300/72
        </span>
      </div>
      <div>
        <span style="opacity: 0.9;">Escuela Tecnica N°1 Luciano Reyes &bull; CUE: 0601429-00</span>
      </div>
    </div>

    <nav class="main-nav">
      <div class="brand-wrapper" onclick="App.navigate('dashboard')">
        <img src="assets/img/sigpe_icon.svg" alt="SIGPE Logo" style="width: 44px; height: 44px; border-radius: 12px; box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);">
        <div class="brand-text-col">
          <span class="brand-title" style="letter-spacing: 0.04em; background: linear-gradient(90deg, var(--text-main) 0%, var(--primary) 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">SIGPE</span>
          <span class="brand-subtitle">Gestión de Patrimonio Escolar</span>
        </div>
      </div>

      <ul class="nav-links">
        <li>
          <button class="nav-item-btn active" data-view="dashboard" id="nav-btn-dashboard">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
            Dashboard
          </button>
        </li>
        <li>
          <button class="nav-item-btn" data-view="catalogo" id="nav-btn-catalogo">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
            Catálogo de Bienes
          </button>
        </li>
        <li>
          <button class="nav-item-btn" data-view="planillas" id="nav-btn-planillas">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
            Planillas Trimestrales
          </button>
        </li>
        <li>
          <button class="nav-item-btn" data-view="mapa" id="nav-btn-mapa">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"></polygon><line x1="8" y1="2" x2="8" y2="18"></line><line x1="16" y1="6" x2="16" y2="22"></line></svg>
            Mapa & Auditoría
          </button>
        </li>
        <li>
          <button class="nav-item-btn" data-view="costos" id="nav-btn-costos">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
            Trazabilidad & Costos
          </button>
        </li>
      </ul>

      <div class="header-actions">
        <button class="btn btn-primary btn-sm" onclick="App.openAltaModal()" id="btn-quick-alta">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
          + Registrar Alta
        </button>

        <!-- Cuenta Activa Única -->
        <div class="active-account-display">
          <div class="user-avatar-badge" id="user-display-badge" style="background: linear-gradient(135deg, #16a34a, #15803d);">
            AD
          </div>
          <div class="user-info-text">
            <span class="user-name-title" id="user-display-name"><?= htmlspecialchars($currentUser['nombre']) ?></span>
            <span class="user-role-tag" id="user-display-role" style="color: var(--primary);">Cuenta Única &bull; <?= htmlspecialchars($currentUser['cargo']) ?></span>
          </div>
        </div>

        <!-- Botón de Cerrar Sesión -->
        <button class="btn-logout" onclick="App.logout()" title="Cerrar la sesión activa">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
          Cerrar Sesión
        </button>

        <button class="btn-theme-toggle" id="btn-theme-toggle" title="Cambiar Tema Claro / Oscuro">
          <!-- Icon injected by JS -->
        </button>
      </div>
    </nav>
  </header>


  <!-- Main Container -->
  <main class="app-container">

    <!-- ========================================================== -->
    <!-- VIEW 1: DASHBOARD -->
    <!-- ========================================================== -->
    <section class="view-section active" id="view-dashboard">
      <div class="view-header">
        <div class="view-title-group">
          <h1>Panel de Control Patrimonial</h1>
          <p>Supervisión en tiempo real de bienes de uso, insumos durables y balances del período lectivo.</p>
        </div>
        <div class="view-actions">
          <button class="btn btn-outline" onclick="App.navigate('planillas')">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
            Planillas Trimestrales
          </button>
          <button class="btn btn-primary" onclick="App.openAltaModal()">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Incorporar Nuevo Bien
          </button>
        </div>
      </div>

      <!-- Metrics Cards -->
      <div class="metrics-grid">
        <div class="metric-card">
          <div class="metric-card-header">
            <span class="metric-label">Valuación Patrimonial Total</span>
            <div class="metric-icon-box metric-icon-blue">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
            </div>
          </div>
          <div class="metric-value" id="dash-val-total">$ 0,00</div>
          <div class="metric-sub" id="dash-unidades-total">0 unidades físicas registradas</div>
        </div>

        <div class="metric-card">
          <div class="metric-card-header">
            <span class="metric-label">Altas del Trimestre (Activo)</span>
            <div class="metric-icon-box metric-icon-green">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
            </div>
          </div>
          <div class="metric-value" id="dash-altas-trim">$ 0,00</div>
          <div class="metric-sub" id="dash-altas-sub">0 items incorporados</div>
        </div>

        <div class="metric-card">
          <div class="metric-card-header">
            <span class="metric-label">Bajas del Trimestre (Activo)</span>
            <div class="metric-icon-box metric-icon-amber">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"></polyline><polyline points="17 18 23 18 23 12"></polyline></svg>
            </div>
          </div>
          <div class="metric-value" id="dash-bajas-trim">$ 0,00</div>
          <div class="metric-sub" id="dash-bajas-sub">Decreto 3.300/72 Art. 45</div>
        </div>

        <div class="metric-card">
          <div class="metric-card-header">
            <span class="metric-label">Recuento Físico Anual</span>
            <div class="metric-icon-box metric-icon-purple">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            </div>
          </div>
          <div class="metric-value" id="dash-audit-pct">0%</div>
          <div class="metric-sub" id="dash-audit-sub">Auditoría obligatoria de fin de ciclo</div>
        </div>
      </div>

      <!-- Dashboard Two Column -->
      <div style="display: grid; grid-template-columns: 1fr 1.6fr; gap: 24px;">
        <!-- Left: Categorías -->
        <div class="card">
          <h2 class="card-title">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
            Distribución por Categoría
          </h2>
          <p class="card-subtitle">Desglose de valuación económica de bienes durables</p>
          <div id="dash-category-list">
            <!-- Dynamically populated -->
          </div>
          <button class="btn btn-outline btn-sm" style="width: 100%; margin-top: 10px;" onclick="App.navigate('costos')">
            Ver balances detallados &rarr;
          </button>
        </div>

        <!-- Right: Movimientos Recientes -->
        <div class="card">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
            <h2 class="card-title" style="margin-bottom: 0;">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
              Movimientos Oficiales del Trimestre
            </h2>
            <button class="btn btn-outline btn-sm" onclick="App.navigate('planillas')">Ver Planilla Oficial</button>
          </div>
          <p class="card-subtitle">Registro de Altas y Bajas según expedientes de gobierno</p>
          
          <div class="table-responsive">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Tipo</th>
                  <th>Identificación del Bien</th>
                  <th>Cuenta</th>
                  <th>Cant.</th>
                  <th>Val. Total</th>
                  <th>Fecha</th>
                </tr>
              </thead>
              <tbody id="dash-recent-movs">
                <!-- Dynamically populated -->
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </section>

    <!-- ========================================================== -->
    <!-- VIEW 2: CATÁLOGO DE BIENES -->
    <!-- ========================================================== -->
    <section class="view-section" id="view-catalogo">
      <div class="view-header">
        <div class="view-title-group">
          <h1>Catálogo General de Inventario</h1>
          <p>Registro unificado de herramientas de taller, mobiliario y bienes de consumo durable.</p>
        </div>
        <div class="view-actions">
          <button class="btn btn-outline" onclick="App.downloadCSV()">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
            Exportar CSV
          </button>
          <button class="btn btn-primary" onclick="App.openAltaModal()">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            + Cargar Nuevo Bien
          </button>
        </div>
      </div>

      <!-- Toolbar con filtros específicos de gobierno -->
      <div class="toolbar-bar">
        <div class="search-box">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
          <input type="text" id="filter-search" placeholder="Buscar por identificación, N° de cuenta, expediente...">
        </div>

        <div class="filters-group">
          <select class="select-filter" id="filter-cuenta">
            <option value="">Todas las Cuentas</option>
            <option value="01-CON">01-CON (Consumo Durable)</option>
            <option value="02-MOB">02-MOB (Mobiliario)</option>
            <option value="03-INF">03-INF (Informática/TICs)</option>
            <option value="04-HER">04-HER (Herramientas Taller)</option>
          </select>

          <select class="select-filter" id="filter-categoria">
            <option value="">Todas las Categorías</option>
            <option value="Herramientas de Taller">Herramientas de Taller</option>
            <option value="Mobiliario Escolar">Mobiliario Escolar</option>
            <option value="Equipamiento Informático">Equipamiento Informático</option>
            <option value="Bienes de Consumo Durable">Bienes de Consumo Durable</option>
            <option value="Instrumentos y Aparatos">Instrumentos y Aparatos</option>
          </select>

          <select class="select-filter" id="filter-estado">
            <option value="">Todos los Estados</option>
            <option value="Bueno">Bueno</option>
            <option value="Regular">Regular</option>
            <option value="En Reparación">En Reparación</option>
          </select>
        </div>

        <div style="font-size: 0.85rem; color: var(--text-muted); display: flex; align-items: center; gap: 12px;">
          <span>Registros: <strong id="catalogo-total-count" style="color: var(--text-main);">0</strong></span>
          <span>Valuación: <strong id="catalogo-total-val" style="color: var(--primary);">0</strong></span>
        </div>
      </div>

      <!-- Data Table -->
      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th style="width: 80px;">N° Cuenta</th>
              <th style="width: 70px;">N° Orden</th>
              <th>Identificación / Denominación</th>
              <th>Categoría</th>
              <th>Ubicación</th>
              <th style="text-align: center;">Cant.</th>
              <th style="text-align: right;">Val. Unitario</th>
              <th style="text-align: right;">Val. Total</th>
              <th>Estado</th>
              <th style="text-align: center;">Auditoría</th>
              <th style="text-align: right; width: 120px;">Acciones</th>
            </tr>
          </thead>
          <tbody id="catalogo-table-body">
            <!-- Dynamically populated -->
          </tbody>
        </table>
      </div>
    </section>

    <!-- ========================================================== -->
    <!-- VIEW 3: PLANILLAS TRIMESTRALES OFICIALES (DECRETO 3.300/72) -->
    <!-- ========================================================== -->
    <section class="view-section" id="view-planillas">
      <div class="view-header no-print">
        <div class="view-title-group">
          <h1>Planillas Trimestrales Oficiales</h1>
          <p>Módulo de Exportación Oficial con formato idéntico a las planillas exigidas por la Provincia de Buenos Aires (Decreto 3.300/72).</p>
        </div>
        <div class="view-actions">
          <button class="btn btn-outline" id="btn-download-csv">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
            Descargar CSV (Envío a Provincia)
          </button>
          <button class="btn btn-primary" id="btn-print-official">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
            Imprimir Formulario Oficial (A4)
          </button>
        </div>
      </div>

      <!-- Controls bar -->
      <div class="toolbar-bar no-print" style="margin-bottom: 24px;">
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
          <button class="btn btn-primary btn-sm report-type-tab" data-report-type="ALTAS">
            Planilla de Altas
          </button>
          <button class="btn btn-outline btn-sm report-type-tab" data-report-type="BAJAS">
            Planilla de Bajas
          </button>
          <button class="btn btn-outline btn-sm report-type-tab" data-report-type="INVENTARIO">
            Inventario General
          </button>
        </div>

        <div style="display: flex; gap: 12px; align-items: center;">
          <label style="font-size: 0.85rem; font-weight: 600;">Período:</label>
          <select class="select-filter" id="report-trimester-select">
            <option value="1">1° Trimestre</option>
            <option value="2">2° Trimestre</option>
            <option value="3" selected>3° Trimestre (En curso)</option>
            <option value="4">4° Trimestre</option>
          </select>
          <select class="select-filter" id="report-year-select">
            <option value="2024" selected>Año 2024</option>
            <option value="2025">Año 2025</option>
            <option value="2026">Año 2026</option>
          </select>
        </div>
      </div>

      <!-- Official Sheet Preview (Optimized for Screen and Print) -->
      <div class="official-sheet-preview" id="official-sheet-content">
        <!-- Rendered dynamically -->
      </div>
    </section>

    <!-- ========================================================== -->
    <!-- VIEW 4: MAPA DIGITAL & AUDITORÍA ANUAL -->
    <!-- ========================================================== -->
    <section class="view-section" id="view-mapa">
      <div class="view-header">
        <div class="view-title-group">
          <h1>Mapa Digital & Auditoría Anual</h1>
          <p>Optimización del recuento anual obligatorio de fin de ciclo lectivo mediante mapa digital de ubicaciones.</p>
        </div>
        <div class="view-actions">
          <button class="btn btn-outline" onclick="App.loadMapaAuditoria()">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
            Actualizar Recuento
          </button>
        </div>
      </div>

      <!-- Progress banner -->
      <div class="card" style="margin-bottom: 24px; padding: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
          <div>
            <h3 style="font-size: 1.05rem; font-weight: 700; margin-bottom: 2px;">Progreso de Auditoría Física Anual</h3>
            <p style="font-size: 0.8rem; color: var(--text-muted);" id="map-global-counts">Cargando datos de verificación...</p>
          </div>
          <div style="font-size: 1.8rem; font-weight: 800; color: var(--primary);" id="map-global-progress-pct">0%</div>
        </div>
        <div class="sector-progress-bar" style="height: 10px;">
          <div class="sector-progress-fill" id="map-global-progress-bar" style="width: 0%;"></div>
        </div>
      </div>

      <!-- Map + Audit Checklist Grid -->
      <div class="school-map-container">
        <!-- Left: Plano Interactivo de Sectores -->
        <div>
          <h3 style="font-size: 1rem; font-weight: 700; margin-bottom: 14px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">
            Sectores e Instalaciones de la Institución
          </h3>
          <div class="map-layout-grid" id="map-sectors-grid">
            <!-- Sectores dynamically populated -->
          </div>
        </div>

        <!-- Right: Checklist de Verificación Física del Sector -->
        <div class="audit-sidebar-panel">
          <div style="border-bottom: 1px solid var(--border-color); padding-bottom: 14px; margin-bottom: 14px;">
            <h3 style="font-size: 1.15rem; font-weight: 800;" id="audit-panel-title">Seleccione un Sector</h3>
            <p style="font-size: 0.78rem; color: var(--text-muted);" id="audit-panel-meta">Haga clic en una tarjeta de taller o aula</p>
          </div>

          <div style="flex: 1; overflow-y: auto; max-height: 520px;" id="audit-panel-list">
            <!-- Items checklist -->
          </div>
        </div>
      </div>
    </section>

    <!-- ========================================================== -->
    <!-- VIEW 5: TRAZABILIDAD & COSTOS -->
    <!-- ========================================================== -->
    <section class="view-section" id="view-costos">
      <div class="view-header">
        <div class="view-title-group">
          <h1>Trazabilidad de Costos & Balances</h1>
          <p>Cálculo instantáneo de valores unitarios y totales de los activos ingresados para los balances oficiales.</p>
        </div>
      </div>

      <!-- KPI Summary -->
      <div class="metrics-grid">
        <div class="metric-card">
          <div class="metric-label">Valuación Total Activa</div>
          <div class="metric-value" id="costos-val-total">$ 0,00</div>
          <div class="metric-sub">Suma acumulada de bienes en catálogo</div>
        </div>
        <div class="metric-card">
          <div class="metric-label">Unidades Físicas Durables</div>
          <div class="metric-value" id="costos-unidades-total">0 u.</div>
          <div class="metric-sub">Herramientas, mobiliario y TICs</div>
        </div>
        <div class="metric-card">
          <div class="metric-label">Costo Promedio por Unidad</div>
          <div class="metric-value" id="costos-promedio">$ 0,00</div>
          <div class="metric-sub">Valuación total / Unidades</div>
        </div>
      </div>

      <div class="card" style="margin-bottom: 24px;">
        <h2 class="card-title">Distribución por Cuentas Patrimoniales Oficiales</h2>
        <p class="card-subtitle">Desglose presupuestario de acuerdo a la codificación de la DGCyE</p>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px;" id="costos-cuentas-grid">
          <!-- Populated dynamically -->
        </div>
      </div>

      <div class="card">
        <h2 class="card-title">Bienes de Mayor Envergadura Institucional</h2>
        <p class="card-subtitle">Principales activos durables inventariados en talleres y laboratorios</p>
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th style="width: 50px;">#</th>
                <th>Denominación del Bien</th>
                <th>Cuenta</th>
                <th>Ubicación</th>
                <th style="text-align: center;">Cantidad</th>
                <th style="text-align: right;">Valuación Total</th>
              </tr>
            </thead>
            <tbody id="costos-top-bienes">
              <!-- Dynamically populated -->
            </tbody>
          </table>
        </div>
      </div>
    </section>

  </main>

  <!-- ========================================================== -->
  <!-- MODAL: REGISTRAR ALTA DE BIEN (CON DATOS DE GOBIERNO) -->
  <!-- ========================================================== -->
  <div class="modal-backdrop" id="modal-alta-bien">
    <div class="modal-dialog">
      <div class="modal-header">
        <h2 class="modal-title">Carga de Bien Patrimonial (Alta Oficial)</h2>
        <button class="modal-close-btn" onclick="App.closeModal('modal-alta-bien')">&times;</button>
      </div>
      <form id="form-alta-bien">
        <div class="modal-body">
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">N° de Cuenta Gubernamental *</label>
              <select class="form-control" name="nro_cuenta" required>
                <option value="04-HER">04-HER (Herramientas de Taller)</option>
                <option value="02-MOB">02-MOB (Mobiliario Escolar)</option>
                <option value="03-INF">03-INF (Equipamiento Informático)</option>
                <option value="01-CON">01-CON (Bienes de Consumo Durable)</option>
              </select>
              <span class="input-hint">Codificación oficial según Decreto 3.300/72</span>
            </div>

            <div class="form-group">
              <label class="form-label">Categoría *</label>
              <select class="form-control" name="categoria" required>
                <option value="Herramientas de Taller">Herramientas de Taller</option>
                <option value="Mobiliario Escolar">Mobiliario Escolar</option>
                <option value="Equipamiento Informático">Equipamiento Informático</option>
                <option value="Bienes de Consumo Durable">Bienes de Consumo Durable</option>
                <option value="Instrumentos y Aparatos">Instrumentos y Aparatos</option>
              </select>
            </div>

            <div class="form-group col-full">
              <label class="form-label">Identificación / Denominación Detallada *</label>
              <input type="text" class="form-control" name="identificacion" placeholder="Ej: Torno Paralelo 1000mm / Soldadora Inverter 200A" required>
            </div>

            <div class="form-group">
              <label class="form-label">Ubicación Física *</label>
              <select class="form-control" name="ubicacion_id" required>
                <?php foreach ($ubicaciones as $u): ?>
                  <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['nombre']) ?> (<?= $u['codigo'] ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Estado de Conservación *</label>
              <select class="form-control" name="estado" required>
                <option value="Bueno" selected>Bueno</option>
                <option value="Regular">Regular</option>
                <option value="En Reparación">En Reparación</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Cantidad *</label>
              <input type="number" class="form-control" name="cantidad" id="alta-cantidad" min="1" value="1" required>
            </div>

            <div class="form-group">
              <label class="form-label">Valor Unitario ($ ARS) *</label>
              <input type="number" step="0.01" class="form-control" name="valor_unitario" id="alta-valor-unitario" min="0" placeholder="0.00" required>
            </div>

            <div class="form-group col-full" style="background: var(--bg-surface-elevated); padding: 12px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
              <span style="font-size: 0.85rem; font-weight: 600;">Valuación Total Calculada:</span>
              <span style="font-family: var(--font-mono); font-size: 1.15rem; font-weight: 800; color: var(--primary);" id="alta-valor-total-display">$ 0,00</span>
            </div>

            <div class="form-group">
              <label class="form-label">Motivo de Alta *</label>
              <select class="form-control" name="motivo_alta" required>
                <option value="Compra Fondos DGCyE Provincia">Compra Fondos DGCyE Provincia</option>
                <option value="Adquisición Plan Mejora INET">Adquisición Plan Mejora INET</option>
                <option value="Adquisición Fondos Propios SITRARED">Adquisición Fondos Propios SITRARED</option>
                <option value="Donación Institucional">Donación Institucional</option>
                <option value="Fabricación Propia en Taller">Fabricación Propia en Taller</option>
                <option value="Transferencia Intercolegial">Transferencia Intercolegial</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">N° de Expediente / GDEBA *</label>
              <input type="text" class="form-control" name="expediente" value="EX-2024-<?= rand(10000, 99999) ?>-GDEBA-DGCYE" required>
            </div>

            <div class="form-group">
              <label class="form-label">Fecha de Ingreso *</label>
              <input type="date" class="form-control" name="fecha_alta" id="alta-fecha" required>
            </div>

            <div class="form-group col-full">
              <label class="form-label">Observaciones Adicionales</label>
              <textarea class="form-control" name="observaciones" rows="2" placeholder="Número de serie, accesorios o detalles técnicos"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline" onclick="App.closeModal('modal-alta-bien')">Cancelar</button>
          <button type="submit" class="btn btn-primary">Registrar e Incorporar</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ========================================================== -->
  <!-- MODAL: REGISTRAR BAJA PATRIMONIAL OFICIAL -->
  <!-- ========================================================== -->
  <div class="modal-backdrop" id="modal-baja-bien">
    <div class="modal-dialog">
      <div class="modal-header">
        <h2 class="modal-title">Registrar Baja Patrimonial (Decreto 3.300/72)</h2>
        <button class="modal-close-btn" onclick="App.closeModal('modal-baja-bien')">&times;</button>
      </div>
      <form id="form-baja-bien">
        <input type="hidden" name="bien_id" id="baja-bien-id">
        <div class="modal-body">
          <div style="background: var(--danger-light); color: var(--danger); padding: 12px; border-radius: var(--radius-sm); margin-bottom: 16px; font-size: 0.85rem; font-weight: 500;">
            Aviso Legal: Esta acción generará una partida en la <strong>Planilla Trimestral de Bajas</strong> sujeta a auditoría del Departamento de Patrimonio de la Provincia.
          </div>

          <div class="form-grid">
            <div class="form-group col-full">
              <label class="form-label">Bien a Dar de Baja</label>
              <div id="baja-bien-nombre" style="font-weight: 700; font-size: 1rem; color: var(--text-main);"></div>
            </div>

            <div class="form-group">
              <label class="form-label">Cantidad a dar de baja *</label>
              <input type="number" class="form-control" name="cantidad_baja" id="baja-cantidad" min="1" value="1" required>
              <span class="input-hint" id="baja-max-disp"></span>
            </div>

            <div class="form-group">
              <label class="form-label">Fecha de Baja *</label>
              <input type="date" class="form-control" name="fecha_baja" id="baja-fecha" required>
            </div>

            <div class="form-group col-full">
              <label class="form-label">Motivo Legal de Baja (Decreto 3.300/72) *</label>
              <select class="form-control" name="motivo_baja" required>
                <option value="Deterioro irreparable por uso intensivo en taller (Art. 45)">Deterioro irreparable por uso intensivo en taller (Art. 45)</option>
                <option value="Obsolescencia tecnológica y desuso definitivo">Obsolescencia tecnológica y desuso definitivo</option>
                <option value="Rotura irreparable sin repuestos en plaza">Rotura irreparable sin repuestos en plaza</option>
                <option value="Pérdida / Extravío justificado mediante acta policial">Pérdida / Extravío justificado mediante acta policial</option>
                <option value="Transferencia a otro establecimiento educativo">Transferencia a otro establecimiento educativo</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">N° Expediente de Baja *</label>
              <input type="text" class="form-control" name="expediente_baja" value="EX-2024-BAJA-DGCYE" required>
            </div>

            <div class="form-group">
              <label class="form-label">N° de Acta o Disposición *</label>
              <input type="text" class="form-control" name="acta_disposicion" value="DISPO-2024-<?= rand(10, 99) ?>" required>
            </div>

            <div class="form-group col-full">
              <label class="form-label">Justificación Técnica del Dictamen</label>
              <textarea class="form-control" name="observaciones" rows="2" placeholder="Informe técnico rubricado por el jefe de área o pañolero"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline" onclick="App.closeModal('modal-baja-bien')">Cancelar</button>
          <button type="submit" class="btn btn-danger">Confirmar y Asentar Baja</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ========================================================== -->
  <!-- MODAL: FICHA DE BIEN CON CÓDIGO QR -->
  <!-- ========================================================== -->
  <div class="modal-backdrop" id="modal-ficha-bien">
    <div class="modal-dialog">
      <div class="modal-header">
        <h2 class="modal-title">Ficha Oficial del Activo Patrimonial</h2>
        <button class="modal-close-btn" onclick="App.closeModal('modal-ficha-bien')">&times;</button>
      </div>
      <div class="modal-body" id="ficha-bien-content">
        <!-- Rendered dynamically -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="App.closeModal('modal-ficha-bien')">Cerrar</button>
        <button type="button" class="btn btn-primary" onclick="window.print()">Imprimir Rótulo</button>
      </div>
    </div>
  </div>

  <!-- ========================================================== -->
  <!-- MODAL: EDITAR BIEN PATRIMONIAL -->
  <!-- ========================================================== -->
  <div class="modal-backdrop" id="modal-edit-bien">
    <div class="modal-dialog">
      <div class="modal-header">
        <h2 class="modal-title">Editar Bien Patrimonial</h2>
        <button class="modal-close-btn" onclick="App.closeModal('modal-edit-bien')">&times;</button>
      </div>
      <form id="form-edit-bien">
        <input type="hidden" name="bien_id" id="edit-bien-id">
        <div class="modal-body">
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">N° de Cuenta</label>
              <input type="text" class="form-control" name="nro_cuenta" id="edit-nro-cuenta" required>
            </div>
            <div class="form-group">
              <label class="form-label">N° de Orden</label>
              <input type="number" class="form-control" name="nro_orden" id="edit-nro-orden" required>
            </div>
            <div class="form-group col-full">
              <label class="form-label">Identificación</label>
              <input type="text" class="form-control" name="identificacion" id="edit-identificacion" required>
            </div>
            <div class="form-group">
              <label class="form-label">Categoría</label>
              <input type="text" class="form-control" name="categoria" id="edit-categoria" required>
            </div>
            <div class="form-group">
              <label class="form-label">Ubicación</label>
              <select class="form-control" name="ubicacion_id" id="edit-ubicacion-id" required>
                <?php foreach ($ubicaciones as $u): ?>
                  <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Cantidad</label>
              <input type="number" class="form-control" name="cantidad" id="edit-cantidad" required>
            </div>
            <div class="form-group">
              <label class="form-label">Valor Unitario ($)</label>
              <input type="number" step="0.01" class="form-control" name="valor_unitario" id="edit-valor-unitario" required>
            </div>
            <div class="form-group">
              <label class="form-label">Estado</label>
              <select class="form-control" name="estado" id="edit-estado" required>
                <option value="Bueno">Bueno</option>
                <option value="Regular">Regular</option>
                <option value="En Reparación">En Reparación</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Expediente</label>
              <input type="text" class="form-control" name="expediente" id="edit-expediente" required>
            </div>
            <div class="form-group col-full">
              <label class="form-label">Motivo de Alta</label>
              <input type="text" class="form-control" name="motivo_alta" id="edit-motivo-alta">
            </div>
            <div class="form-group col-full">
              <label class="form-label">Observaciones</label>
              <textarea class="form-control" name="observaciones" id="edit-observaciones" rows="2"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline" onclick="App.closeModal('modal-edit-bien')">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar Modificaciones</button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <!-- Scripts -->
  <script src="assets/js/api.js"></script>
  <script src="assets/js/app.js"></script>
</body>
</html>
