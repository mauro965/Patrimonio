// assets/js/app.js - Lógica principal del Sistema de Patrimonio Escolar

const App = {
  state: {
    user: null,
    currentView: 'dashboard',
    bienes: [],
    movimientos: [],
    estadisticas: null,
    mapData: null,
    selectedSectorId: 1,
    activeTrimester: 3,
    activeYear: 2024,
    activeReportType: 'ALTAS',
    filters: {
      search: '',
      categoria: '',
      cuenta: '',
      estado: '',
      ubicacion: ''
    }
  },

  async init() {
    this.initTheme();
    this.bindEvents();
    await this.checkAuth();
    if (this.state.user) {
      await this.navigate('dashboard');
    }
  },

  // -------------------------------------------------------------
  // Theme Management
  // -------------------------------------------------------------
  initTheme() {
    const savedTheme = localStorage.getItem('patrimonio_theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
    this.updateThemeIcon(savedTheme);
  },

  toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme') || 'light';
    const next = current === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('patrimonio_theme', next);
    this.updateThemeIcon(next);
  },

  updateThemeIcon(theme) {
    const btn = document.getElementById('btn-theme-toggle');
    if (!btn) return;
    btn.innerHTML = theme === 'dark'
      ? `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>`
      : `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>`;
  },

  // -------------------------------------------------------------
  // Authentication & Gate Management
  // -------------------------------------------------------------
  async checkAuth() {
    const res = await API.auth.check();
    if (res.authenticated && res.usuario) {
      this.state.user = res.usuario;
      this.renderUserUI();
    } else {
      this.state.user = null;
    }
  },

  async handleLoginSubmit(e) {
    e.preventDefault();
    const btn = document.getElementById('btn-login-submit');
    const email = document.getElementById('login-email')?.value.trim();
    const password = document.getElementById('login-password')?.value.trim();
    const alertEl = document.getElementById('login-error-alert');

    if (alertEl) {
      alertEl.classList.remove('show');
      alertEl.textContent = '';
    }

    if (!email || !password) {
      if (alertEl) {
        alertEl.textContent = 'Por favor complete su usuario y contraseña.';
        alertEl.classList.add('show');
      }
      return;
    }

    if (btn) {
      btn.disabled = true;
      btn.textContent = 'Verificando cuenta...';
    }

    const res = await API.auth.login(email, password);

    if (res.success && res.usuario) {
      this.showToast('Acceso autorizado. Ingresando...', 'success');
      setTimeout(() => {
        window.location.reload();
      }, 400);
    } else {
      if (alertEl) {
        alertEl.textContent = res.error || 'Credenciales inválidas. Cuenta no autorizada.';
        alertEl.classList.add('show');
      }
      if (btn) {
        btn.disabled = false;
        btn.textContent = 'Ingresar al Sistema';
      }
    }
  },

  fillLoginForm(email, password) {
    const emailEl = document.getElementById('login-email');
    const pwdEl = document.getElementById('login-password');
    if (emailEl) emailEl.value = email;
    if (pwdEl) pwdEl.value = password;
    const form = document.getElementById('login-form');
    if (form) form.requestSubmit();
  },

  toggleLoginPassword() {
    const pwdEl = document.getElementById('login-password');
    if (!pwdEl) return;
    pwdEl.type = pwdEl.type === 'password' ? 'text' : 'password';
  },

  async logout() {
    await API.auth.logout();
    window.location.href = 'index.php?logout=1';
  },

  renderUserUI() {
    const u = this.state.user;
    if (!u) return;

    const nameEl = document.getElementById('user-display-name');
    const roleEl = document.getElementById('user-display-role');
    const badgeEl = document.getElementById('user-display-badge');

    if (nameEl) nameEl.textContent = u.nombre;
    if (roleEl) roleEl.textContent = u.rol === 'director' ? 'Director' : 'Ayudante';
    if (badgeEl) {
      const initials = u.nombre.split(' ').map(n => n[0]).slice(0, 2).join('');
      badgeEl.textContent = initials;
    }

    // Actualizar items de dropdown
    document.querySelectorAll('.role-option-item').forEach(el => {
      const id = parseInt(el.getAttribute('data-user-id'));
      if (id === u.id) {
        el.classList.add('active');
      } else {
        el.classList.remove('active');
      }
    });

    // Control de permisos visuales
    const isDirector = u.rol === 'director';
    document.querySelectorAll('.director-only').forEach(el => {
      el.style.display = isDirector ? '' : 'none';
    });
  },

  toggleRoleDropdown(e) {
    e.stopPropagation();
    const drop = document.getElementById('role-dropdown');
    if (drop) drop.classList.toggle('show');
  },

  hideRoleDropdown() {
    const drop = document.getElementById('role-dropdown');
    if (drop) drop.classList.remove('show');
  },

  // -------------------------------------------------------------
  // Navigation
  // -------------------------------------------------------------
  async navigate(viewName, force = false) {
    if (this.state.currentView === viewName && !force) return;

    this.state.currentView = viewName;

    // Actualizar botones de navegación
    document.querySelectorAll('.nav-item-btn').forEach(btn => {
      if (btn.getAttribute('data-view') === viewName) {
        btn.classList.add('active');
      } else {
        btn.classList.remove('active');
      }
    });

    // Ocultar todas las secciones y mostrar la activa
    document.querySelectorAll('.view-section').forEach(sec => {
      sec.classList.remove('active');
    });

    const activeSec = document.getElementById(`view-${viewName}`);
    if (activeSec) {
      activeSec.classList.add('active');
    }

    // Cargar datos correspondientes
    switch (viewName) {
      case 'dashboard':
        await this.loadDashboard();
        break;
      case 'catalogo':
        await this.loadCatalogo();
        break;
      case 'planillas':
        await this.loadPlanillas();
        break;
      case 'mapa':
        await this.loadMapaAuditoria();
        break;
      case 'costos':
        await this.loadCostos();
        break;
    }
  },

  // -------------------------------------------------------------
  // View: Dashboard
  // -------------------------------------------------------------
  async loadDashboard() {
    const res = await API.estadisticas.dashboard();
    if (!res.success) return;

    this.state.estadisticas = res;
    const m = res.metricas;
    const cfg = res.config;

    // Métricas principales
    const formatCurrency = (val) => '$ ' + Number(val).toLocaleString('es-AR', { minimumFractionDigits: 2 });

    const elVal = document.getElementById('dash-val-total');
    if (elVal) elVal.textContent = formatCurrency(m.valuacion_total);

    const elUnidades = document.getElementById('dash-unidades-total');
    if (elUnidades) elUnidades.textContent = m.total_unidades + ' unidades (' + m.total_registros + ' bienes)';

    const elAltas = document.getElementById('dash-altas-trim');
    if (elAltas) elAltas.textContent = formatCurrency(m.altas_trimestre.monto);

    const elAltasSub = document.getElementById('dash-altas-sub');
    if (elAltasSub) elAltasSub.textContent = `${m.altas_trimestre.cant} items incorporados (T${cfg.trimestre_activo})`;

    const elBajas = document.getElementById('dash-bajas-trim');
    if (elBajas) elBajas.textContent = formatCurrency(m.bajas_trimestre.monto);

    const elBajasSub = document.getElementById('dash-bajas-sub');
    if (elBajasSub) elBajasSub.textContent = `${m.bajas_trimestre.cant} items dados de baja`;

    const elAudit = document.getElementById('dash-audit-pct');
    if (elAudit) elAudit.textContent = m.progreso_auditoria + '%';

    const elAuditSub = document.getElementById('dash-audit-sub');
    if (elAuditSub) elAuditSub.textContent = `${m.auditados_count} de ${m.total_registros} verificados físicamente`;

    // Desglose por categoría
    this.renderCategoryBreakdown(res.desglose.por_categoria);
    // Actividad reciente
    this.loadRecentMovements();
  },

  renderCategoryBreakdown(categorias) {
    const container = document.getElementById('dash-category-list');
    if (!container) return;

    const totalVal = categorias.reduce((sum, c) => sum + parseFloat(c.valor_total), 0) || 1;

    let html = '';
    categorias.forEach(c => {
      const pct = Math.round((parseFloat(c.valor_total) / totalVal) * 100);
      html += `
        <div style="margin-bottom: 14px;">
          <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 4px;">
            <span style="font-weight: 600;">${c.categoria}</span>
            <span style="color: var(--text-muted);">$ ${Number(c.valor_total).toLocaleString('es-AR')} (${pct}%)</span>
          </div>
          <div class="sector-progress-bar">
            <div class="sector-progress-fill" style="width: ${pct}%;"></div>
          </div>
        </div>
      `;
    });

    container.innerHTML = html;
  },

  async loadRecentMovements() {
    const res = await API.movimientos.list({ trimestre: 3, anio: 2024 });
    const container = document.getElementById('dash-recent-movs');
    if (!container || !res.success) return;

    let html = '';
    res.data.slice(0, 5).forEach(m => {
      const isAlta = m.tipo === 'ALTA';
      const badgeClass = isAlta ? 'status-bueno' : 'status-baja';
      const badgeIcon = isAlta ? '▲ ALTA' : '▼ BAJA';

      html += `
        <tr style="cursor: pointer;" onclick="App.navigate('planillas')">
          <td><span class="status-badge ${badgeClass}">${badgeIcon}</span></td>
          <td style="font-weight: 600;">${m.identificacion}</td>
          <td><span class="code-badge">${m.nro_cuenta}</span></td>
          <td>${m.cantidad} u.</td>
          <td style="font-weight: 700;">$ ${Number(m.valor_total).toLocaleString('es-AR')}</td>
          <td style="color: var(--text-muted); font-size: 0.8rem;">${m.fecha}</td>
        </tr>
      `;
    });

    container.innerHTML = html || '<tr><td colspan="6" style="text-align:center;">Sin movimientos</td></tr>';
  },

  // -------------------------------------------------------------
  // View: Catálogo de Bienes
  // -------------------------------------------------------------
  async loadCatalogo() {
    const params = {
      search: this.state.filters.search,
      categoria: this.state.filters.categoria,
      cuenta: this.state.filters.cuenta,
      estado: this.state.filters.estado,
      activos: 1
    };

    const res = await API.bienes.list(params);
    if (!res.success) return;

    this.state.bienes = res.data;
    this.renderBienesTable(res.data, res.total_valor, res.total_unidades);
  },

  renderBienesTable(items, totalValor, totalUnidades) {
    const tbody = document.getElementById('catalogo-table-body');
    const totalCountEl = document.getElementById('catalogo-total-count');
    const totalValEl = document.getElementById('catalogo-total-val');

    if (totalCountEl) totalCountEl.textContent = `${items.length} bienes (${totalUnidades} u.)`;
    if (totalValEl) totalValEl.textContent = '$ ' + Number(totalValor).toLocaleString('es-AR', { minimumFractionDigits: 2 });

    if (!tbody) return;

    if (items.length === 0) {
      tbody.innerHTML = `<tr><td colspan="9" style="text-align:center; padding: 40px; color: var(--text-muted);">
        No se encontraron bienes con los filtros seleccionados.
      </td></tr>`;
      return;
    }

    let html = '';
    items.forEach(b => {
      const statusClass = b.estado === 'Bueno' ? 'status-bueno' : (b.estado === 'Regular' ? 'status-regular' : 'status-reparacion');
      const auditBadge = b.auditado_anual == 1
        ? `<span class="audit-indicator verified">✓ Auditado</span>`
        : `<span class="audit-indicator pending">○ Pendiente</span>`;

      html += `
        <tr>
          <td><span class="code-badge">${b.nro_cuenta}</span></td>
          <td style="font-family: var(--font-mono); font-weight: 600;">#${b.nro_orden}</td>
          <td>
            <div style="font-weight: 600; color: var(--text-main); margin-bottom: 2px;">${b.identificacion}</div>
            <div style="font-size: 0.75rem; color: var(--text-muted);">Expte: ${b.expediente}</div>
          </td>
          <td><span style="font-size: 0.8rem; font-weight: 500;">${b.categoria}</span></td>
          <td style="font-size: 0.82rem;">${b.ubicacion_nombre}</td>
          <td style="font-weight: 700; text-align: center;">${b.cantidad}</td>
          <td style="font-family: var(--font-mono); font-size: 0.82rem;">$ ${Number(b.valor_unitario).toLocaleString('es-AR')}</td>
          <td style="font-family: var(--font-mono); font-weight: 700; color: var(--primary);">$ ${Number(b.valor_total).toLocaleString('es-AR')}</td>
          <td><span class="status-badge ${statusClass}">${b.estado}</span></td>
          <td style="text-align: center;">${auditBadge}</td>
          <td>
            <div style="display: flex; gap: 6px; justify-content: flex-end;">
              <button class="btn btn-outline btn-sm" title="Ver Ficha y Código QR" onclick="App.openFichaModal(${b.id})">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
              </button>
              <button class="btn btn-outline btn-sm" title="Editar Bien" onclick="App.openEditModal(${b.id})">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
              </button>
              <button class="btn btn-danger btn-sm" title="Registrar Baja Oficial Decreto 3.300/72" onclick="App.openBajaModal(${b.id})">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
              </button>
            </div>
          </td>
        </tr>
      `;
    });

    tbody.innerHTML = html;
  },

  handleFilterChange() {
    const search = document.getElementById('filter-search')?.value || '';
    const categoria = document.getElementById('filter-categoria')?.value || '';
    const cuenta = document.getElementById('filter-cuenta')?.value || '';
    const estado = document.getElementById('filter-estado')?.value || '';

    this.state.filters = { search, categoria, cuenta, estado };
    this.loadCatalogo();
  },

  // -------------------------------------------------------------
  // View: Planillas Trimestrales Oficiales (Decreto 3.300/72)
  // -------------------------------------------------------------
  async loadPlanillas() {
    const tipo = this.state.activeReportType;
    const trimestre = this.state.activeTrimester;
    const anio = this.state.activeYear;

    const res = await API.exportar.json(tipo, trimestre, anio);
    if (!res.success) return;

    this.renderOfficialSheet(res);
  },

  setReportType(tipo) {
    this.state.activeReportType = tipo;
    document.querySelectorAll('.report-type-tab').forEach(btn => {
      if (btn.getAttribute('data-report-type') === tipo) {
        btn.classList.add('btn-primary');
        btn.classList.remove('btn-outline');
      } else {
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-outline');
      }
    });
    this.loadPlanillas();
  },

  setTrimester(t) {
    this.state.activeTrimester = parseInt(t);
    this.loadPlanillas();
  },

  renderOfficialSheet(data) {
    const container = document.getElementById('official-sheet-content');
    if (!container) return;

    const inst = data.institucion;
    const isInventario = data.periodo.tipo === 'INVENTARIO';
    const isBaja = data.periodo.tipo === 'BAJAS';

    let totalMonto = 0;
    let totalCantidad = 0;

    let rowsHtml = '';
    data.items.forEach((item, idx) => {
      const cant = parseInt(item.cantidad);
      const unit = parseFloat(item.valor_unitario);
      const tot = parseFloat(item.valor_total);
      totalCantidad += cant;
      totalMonto += tot;

      if (isInventario) {
        rowsHtml += `
          <tr>
            <td style="text-align: center;">${idx + 1}</td>
            <td style="text-align: center;">${item.nro_cuenta}</td>
            <td style="text-align: center;">${item.nro_orden}</td>
            <td><strong>${item.identificacion}</strong></td>
            <td>${item.ubicacion_nombre}</td>
            <td style="text-align: center;">${cant}</td>
            <td style="text-align: right;">$ ${unit.toLocaleString('es-AR', {minimumFractionDigits: 2})}</td>
            <td style="text-align: right;"><strong>$ ${tot.toLocaleString('es-AR', {minimumFractionDigits: 2})}</strong></td>
            <td style="text-align: center;">${item.estado}</td>
          </tr>
        `;
      } else {
        rowsHtml += `
          <tr>
            <td style="text-align: center;">${idx + 1}</td>
            <td style="text-align: center;">${item.nro_cuenta}</td>
            <td style="text-align: center;">${item.nro_orden}</td>
            <td><strong>${item.identificacion}</strong></td>
            <td>${item.motivo}</td>
            <td style="text-align: center;">${item.expediente}</td>
            <td style="text-align: center;">${item.acta_disposicion || 'N/A'}</td>
            <td style="text-align: center;">${cant}</td>
            <td style="text-align: right;">$ ${unit.toLocaleString('es-AR', {minimumFractionDigits: 2})}</td>
            <td style="text-align: right;"><strong>$ ${tot.toLocaleString('es-AR', {minimumFractionDigits: 2})}</strong></td>
          </tr>
        `;
      }
    });

    const colSpan = isInventario ? 5 : 7;

    const html = `
      <div class="official-header">
        <h2>GOBIERNO DE LA PROVINCIA DE BUENOS AIRES</h2>
        <h3>DIRECCIÓN GENERAL DE CULTURA Y EDUCACIÓN - DIRECCIÓN DE PATRIMONIO</h3>
        <h4>${data.titulo}</h4>
      </div>

      <div class="official-meta-grid">
        <div>
          <p><strong>ESTABLECIMIENTO:</strong> ${inst.nombre_escuela}</p>
          <p><strong>CLAVE ÚNICA DE ESTABLECIMIENTO (CUE):</strong> ${inst.cue}</p>
          <p><strong>DISTRITO Y REGIÓN EDUCATIVA:</strong> ${inst.distrito}</p>
        </div>
        <div style="text-align: right;">
          <p><strong>PERÍODO:</strong> ${data.periodo.trimestre}° Trimestre ${data.periodo.anio}</p>
          <p><strong>MARCO NORMATIVO:</strong> ${inst.decreto_referencia}</p>
          <p><strong>FECHA DE EMISIÓN:</strong> ${new Date().toLocaleDateString('es-AR')}</p>
        </div>
      </div>

      <table class="official-table">
        <thead>
          <tr>
            <th style="width: 35px;">N°</th>
            <th style="width: 70px;">N° Cuenta</th>
            <th style="width: 60px;">N° Orden</th>
            <th>Denominación y Detalle del Artículo</th>
            ${isInventario ? `
              <th>Ubicación</th>
              <th style="width: 50px;">Cant.</th>
              <th style="width: 110px;">Val. Unitario</th>
              <th style="width: 120px;">Val. Total</th>
              <th style="width: 70px;">Estado</th>
            ` : `
              <th>Motivo Legal (${isBaja ? 'Baja' : 'Alta'})</th>
              <th style="width: 130px;">Expediente</th>
              <th style="width: 110px;">Acta / Dispo</th>
              <th style="width: 50px;">Cant.</th>
              <th style="width: 110px;">Val. Unitario</th>
              <th style="width: 120px;">Val. Total</th>
            `}
          </tr>
        </thead>
        <tbody>
          ${rowsHtml || `<tr><td colspan="${isInventario ? 9 : 10}" style="text-align: center; padding: 20px;">Sin registros oficiales para el período seleccionado.</td></tr>`}
        </tbody>
        <tfoot>
          <tr>
            <td colspan="${colSpan}" style="text-align: right; text-transform: uppercase;">
              TOTAL GENERAL DEL PERÍODO (${totalCantidad} unidades):
            </td>
            <td style="text-align: center;"><strong>${totalCantidad}</strong></td>
            <td></td>
            <td style="text-align: right; font-size: 0.92rem;">
              <strong>$ ${totalMonto.toLocaleString('es-AR', {minimumFractionDigits: 2})}</strong>
            </td>
            ${isInventario ? '<td></td>' : ''}
          </tr>
        </tfoot>
      </table>

      <div style="font-size: 0.78rem; font-style: italic; margin-bottom: 24px;">
        * La presente planilla posee carácter de Declaración Jurada Oficial ante el Departamento de Patrimonio de la Dirección General de Cultura y Educación (DGCyE), en cumplimiento del Decreto Ley 3.300/72 y resoluciones complementarias.
      </div>


    `;

    container.innerHTML = html;
  },

  downloadCSV() {
    const url = API.exportar.csvUrl(
      this.state.activeReportType,
      this.state.activeTrimester,
      this.state.activeYear
    );
    window.location.href = url;
  },

  printOfficialSheet() {
    window.print();
  },

  // -------------------------------------------------------------
  // View: Mapa Digital y Auditoría Física
  // -------------------------------------------------------------
  async loadMapaAuditoria() {
    const res = await API.auditoria.mapData();
    if (!res.success) return;

    this.state.mapData = res;

    // Resumen
    const pctEl = document.getElementById('map-global-progress-pct');
    const barEl = document.getElementById('map-global-progress-bar');
    const countsEl = document.getElementById('map-global-counts');

    if (pctEl) pctEl.textContent = res.resumen.porcentaje_global + '%';
    if (barEl) barEl.style.width = res.resumen.porcentaje_global + '%';
    if (countsEl) countsEl.textContent = `${res.resumen.total_auditados} auditados / ${res.resumen.total_pendientes} pendientes de verificación física`;

    // Render sectores
    this.renderMapSectors(res.ubicaciones);

    // Cargar sector seleccionado
    this.selectSector(this.state.selectedSectorId || res.ubicaciones[0]?.id || 1);
  },

  renderMapSectors(sectores) {
    const grid = document.getElementById('map-sectors-grid');
    if (!grid) return;

    let html = '';
    sectores.forEach(s => {
      const isSelected = s.id === this.state.selectedSectorId ? 'selected' : '';
      const isFull = s.progreso_porcentaje >= 100;

      html += `
        <div class="map-sector-card ${isSelected}" id="sector-card-${s.id}" onclick="App.selectSector(${s.id})">
          <div>
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">
              <span class="code-badge">${s.codigo}</span>
              <span style="font-size: 0.75rem; font-weight: 700; color: ${isFull ? 'var(--success)' : 'var(--warning)'};">
                ${s.progreso_porcentaje}%
              </span>
            </div>
            <div class="map-sector-name">${s.nombre}</div>
            <div class="map-sector-meta">Responsable: ${s.responsable}</div>
          </div>
          <div>
            <div style="font-size: 0.75rem; color: var(--text-muted); display: flex; justify-content: space-between; margin-top: 10px;">
              <span>${s.total_auditados}/${s.total_items} verificados</span>
              <span>${s.total_unidades} u.</span>
            </div>
            <div class="sector-progress-bar">
              <div class="sector-progress-fill" style="width: ${s.progreso_porcentaje}%;"></div>
            </div>
          </div>
        </div>
      `;
    });

    grid.innerHTML = html;
  },

  selectSector(sectorId) {
    this.state.selectedSectorId = sectorId;

    document.querySelectorAll('.map-sector-card').forEach(c => c.classList.remove('selected'));
    const card = document.getElementById(`sector-card-${sectorId}`);
    if (card) card.classList.add('selected');

    if (!this.state.mapData) return;
    const sector = this.state.mapData.ubicaciones.find(u => u.id === sectorId);
    if (!sector) return;

    // Render panel derecho de auditoría
    const titleEl = document.getElementById('audit-panel-title');
    const metaEl = document.getElementById('audit-panel-meta');
    const listEl = document.getElementById('audit-panel-list');

    if (titleEl) titleEl.textContent = `${sector.nombre} (${sector.codigo})`;
    if (metaEl) metaEl.textContent = `Piso: ${sector.piso} | Responsable: ${sector.responsable} | Valuación: $ ${Number(sector.valor_total).toLocaleString('es-AR')}`;

    if (!listEl) return;

    if (sector.bienes.length === 0) {
      listEl.innerHTML = `<div style="text-align: center; padding: 30px; color: var(--text-muted);">No hay bienes inventariados en este sector.</div>`;
      return;
    }

    let html = '';
    sector.bienes.forEach(b => {
      const isAudit = b.auditado_anual == 1;

      html += `
        <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px; border-bottom: 1px solid var(--border-color); gap: 12px;">
          <div style="flex: 1;">
            <div style="font-weight: 600; font-size: 0.88rem; margin-bottom: 2px;">${b.identificacion}</div>
            <div style="font-size: 0.75rem; color: var(--text-muted); display: flex; gap: 8px;">
              <span class="code-badge">${b.nro_cuenta} #${b.nro_orden}</span>
              <span>Cant: <strong>${b.cantidad}</strong></span>
              <span>Estado: <strong>${b.estado}</strong></span>
            </div>
            ${isAudit ? `<div style="font-size: 0.72rem; color: var(--success); margin-top: 2px;">✓ Verificado: ${b.auditor_nombre || 'Ayudante'} (${b.fecha_auditoria ? b.fecha_auditoria.substring(0, 10) : 'Al día'})</div>` : ''}
          </div>
          <div>
            <button class="btn btn-sm ${isAudit ? 'btn-success' : 'btn-outline'}" 
                    onclick="App.toggleAuditBien(${b.id})">
              ${isAudit ? '✓ Auditado' : '○ Verificar'}
            </button>
          </div>
        </div>
      `;
    });

    listEl.innerHTML = html;
  },

  async toggleAuditBien(bienId) {
    const res = await API.bienes.toggleAudit(bienId);
    if (res.success) {
      this.showToast(res.message, 'info');
      // Recargar datos de auditoría
      await this.loadMapaAuditoria();
    }
  },

  // -------------------------------------------------------------
  // View: Trazabilidad y Costos
  // -------------------------------------------------------------
  async loadCostos() {
    const res = await API.estadisticas.dashboard();
    if (!res.success) return;

    const m = res.metricas;
    const catList = res.desglose.por_categoria;
    const cuentaList = res.desglose.por_cuenta;

    // Resumen numérico
    const formatCurrency = (val) => '$ ' + Number(val).toLocaleString('es-AR', { minimumFractionDigits: 2 });
    document.getElementById('costos-val-total').textContent = formatCurrency(m.valuacion_total);
    document.getElementById('costos-unidades-total').textContent = m.total_unidades + ' u.';
    document.getElementById('costos-promedio').textContent = formatCurrency(m.total_unidades > 0 ? (m.valuacion_total / m.total_unidades) : 0);

    // Render barras por cuenta patrimonial oficial
    const cuentaContainer = document.getElementById('costos-cuentas-grid');
    if (cuentaContainer) {
      let html = '';
      const cuentaNombres = {
        '01-CON': 'Bienes de Consumo Durable',
        '02-MOB': 'Mobiliario y Enseres Escolares',
        '03-INF': 'Equipamiento Informático y TICs',
        '04-HER': 'Maquinarias y Herramientas de Taller'
      };

      cuentaList.forEach(c => {
        const pct = Math.round((parseFloat(c.valor_total) / (m.valuacion_total || 1)) * 100);
        html += `
          <div class="card" style="padding: 16px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
              <span class="code-badge">${c.nro_cuenta}</span>
              <span style="font-size: 0.8rem; font-weight: 700; color: var(--primary);">${pct}% del total</span>
            </div>
            <div style="font-weight: 700; font-size: 1rem; margin-bottom: 4px;">
              ${cuentaNombres[c.nro_cuenta] || c.nro_cuenta}
            </div>
            <div style="color: var(--text-muted); font-size: 0.8rem; margin-bottom: 12px;">
              ${c.cantidad_bienes} registros (${c.unidades} unidades físicas)
            </div>
            <div style="font-family: var(--font-mono); font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin-bottom: 8px;">
              ${formatCurrency(c.valor_total)}
            </div>
            <div class="sector-progress-bar">
              <div class="sector-progress-fill" style="width: ${pct}%;"></div>
            </div>
          </div>
        `;
      });
      cuentaContainer.innerHTML = html;
    }

    // Render tabla de bienes de mayor valor
    const topContainer = document.getElementById('costos-top-bienes');
    if (topContainer) {
      const topBienes = [...this.state.bienes].sort((a, b) => parseFloat(b.valor_total) - parseFloat(a.valor_total)).slice(0, 5);
      let html = '';
      topBienes.forEach((b, i) => {
        html += `
          <tr>
            <td style="font-weight: 700;">#${i + 1}</td>
            <td><strong>${b.identificacion}</strong></td>
            <td><span class="code-badge">${b.nro_cuenta}</span></td>
            <td>${b.ubicacion_nombre}</td>
            <td style="text-align: center;">${b.cantidad} u.</td>
            <td style="font-family: var(--font-mono); font-weight: 700; color: var(--primary); text-align: right;">
              $ ${Number(b.valor_total).toLocaleString('es-AR')}
            </td>
          </tr>
        `;
      });
      topContainer.innerHTML = html;
    }
  },

  // -------------------------------------------------------------
  // Modals & Formularios
  // -------------------------------------------------------------
  openAltaModal() {
    const modal = document.getElementById('modal-alta-bien');
    if (!modal) return;

    // Reset form
    document.getElementById('form-alta-bien').reset();
    document.getElementById('alta-fecha').value = new Date().toISOString().substring(0, 10);
    this.calculateAltaTotal();

    modal.classList.add('show');
  },

  calculateAltaTotal() {
    const cant = parseFloat(document.getElementById('alta-cantidad')?.value || 0);
    const unit = parseFloat(document.getElementById('alta-valor-unitario')?.value || 0);
    const total = cant * unit;
    const totalEl = document.getElementById('alta-valor-total-display');
    if (totalEl) {
      totalEl.textContent = '$ ' + total.toLocaleString('es-AR', { minimumFractionDigits: 2 });
    }
  },

  async handleAltaSubmit(e) {
    e.preventDefault();
    const f = e.target;

    const data = {
      nro_cuenta: f.nro_cuenta.value,
      identificacion: f.identificacion.value,
      categoria: f.categoria.value,
      ubicacion_id: f.ubicacion_id.value,
      ubicacion_nombre: f.ubicacion_id.options[f.ubicacion_id.selectedIndex].text,
      cantidad: f.cantidad.value,
      valor_unitario: f.valor_unitario.value,
      estado: f.estado.value,
      motivo_alta: f.motivo_alta.value,
      expediente: f.expediente.value,
      fecha_alta: f.fecha_alta.value,
      observaciones: f.observaciones.value,
      generar_movimiento: true
    };

    const res = await API.bienes.create(data);
    if (res.success) {
      this.closeModal('modal-alta-bien');
      this.showToast('Bien incorporado exitosamente al catálogo oficial (Decreto 3.300/72)', 'success');
      await this.loadCatalogo();
      await this.loadDashboard();
    } else {
      this.showToast(res.error || 'Error al guardar el bien', 'danger');
    }
  },

  openBajaModal(bienId) {
    const bien = this.state.bienes.find(b => b.id === bienId);
    if (!bien) return;

    document.getElementById('baja-bien-id').value = bien.id;
    document.getElementById('baja-bien-nombre').textContent = `${bien.identificacion} (${bien.nro_cuenta} #${bien.nro_orden})`;
    document.getElementById('baja-cantidad').max = bien.cantidad;
    document.getElementById('baja-cantidad').value = 1;
    document.getElementById('baja-max-disp').textContent = `(Disponible: ${bien.cantidad} unidades)`;
    document.getElementById('baja-fecha').value = new Date().toISOString().substring(0, 10);

    const modal = document.getElementById('modal-baja-bien');
    if (modal) modal.classList.add('show');
  },

  async handleBajaSubmit(e) {
    e.preventDefault();
    const f = e.target;

    const data = {
      id: f.bien_id.value,
      cantidad_baja: f.cantidad_baja.value,
      motivo_baja: f.motivo_baja.value,
      expediente_baja: f.expediente_baja.value,
      acta_disposicion: f.acta_disposicion.value,
      fecha_baja: f.fecha_baja.value,
      observaciones: f.observaciones.value
    };

    const res = await API.bienes.baja(data);
    if (res.success) {
      this.closeModal('modal-baja-bien');
      this.showToast('Baja patrimonial asentada en planilla oficial Decreto 3.300/72', 'success');
      await this.loadCatalogo();
      await this.loadDashboard();
    } else {
      this.showToast(res.error || 'Error al procesar la baja', 'danger');
    }
  },

  async openFichaModal(bienId) {
    const res = await API.bienes.get(bienId);
    if (!res.success) return;
    const b = res.data;

    const modal = document.getElementById('modal-ficha-bien');
    const body = document.getElementById('ficha-bien-content');
    if (!modal || !body) return;

    body.innerHTML = `
      <div style="display: flex; gap: 24px; align-items: flex-start; flex-wrap: wrap;">
        <!-- Código QR simulado SVG oficial -->
        <div style="background: #ffffff; padding: 14px; border: 1px solid var(--border-color); border-radius: var(--radius-md); text-align: center;">
          <svg width="140" height="140" viewBox="0 0 100 100" fill="#0f172a">
            <!-- QR matrix decorative pattern -->
            <rect width="100" height="100" fill="#ffffff"/>
            <rect x="10" y="10" width="25" height="25" fill="#0f172a"/>
            <rect x="15" y="15" width="15" height="15" fill="#ffffff"/>
            <rect x="18" y="18" width="9" height="9" fill="#0f172a"/>
            
            <rect x="65" y="10" width="25" height="25" fill="#0f172a"/>
            <rect x="70" y="15" width="15" height="15" fill="#ffffff"/>
            <rect x="73" y="18" width="9" height="9" fill="#0f172a"/>

            <rect x="10" y="65" width="25" height="25" fill="#0f172a"/>
            <rect x="15" y="70" width="15" height="15" fill="#ffffff"/>
            <rect x="18" y="73" width="9" height="9" fill="#0f172a"/>

            <!-- Bits -->
            <rect x="42" y="12" width="6" height="6" fill="#0f172a"/>
            <rect x="52" y="20" width="6" height="6" fill="#0f172a"/>
            <rect x="42" y="28" width="6" height="6" fill="#0f172a"/>
            <rect x="50" y="42" width="8" height="8" fill="#0f172a"/>
            <rect x="25" y="45" width="6" height="6" fill="#0f172a"/>
            <rect x="70" y="45" width="8" height="8" fill="#0f172a"/>
            <rect x="42" y="65" width="6" height="6" fill="#0f172a"/>
            <rect x="65" y="65" width="6" height="6" fill="#0f172a"/>
            <rect x="52" y="75" width="6" height="6" fill="#0f172a"/>
            <rect x="75" y="78" width="6" height="6" fill="#0f172a"/>
          </svg>
          <div style="font-family: var(--font-mono); font-size: 0.72rem; font-weight: 700; margin-top: 6px; color: #0f172a;">
            PAT-${b.nro_cuenta}-${b.nro_orden}
          </div>
          <div style="font-size: 0.68rem; color: #64748b;">DGCyE - PBA</div>
        </div>

        <div style="flex: 1; min-width: 260px;">
          <div style="display: flex; gap: 8px; margin-bottom: 8px;">
            <span class="code-badge">${b.nro_cuenta}</span>
            <span class="code-badge">Orden #${b.nro_orden}</span>
            <span class="status-badge ${b.estado === 'Bueno' ? 'status-bueno' : 'status-regular'}">${b.estado}</span>
          </div>

          <h3 style="font-size: 1.15rem; font-weight: 800; margin-bottom: 6px;">${b.identificacion}</h3>
          <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 14px;">Categoría: <strong>${b.categoria}</strong></p>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 0.85rem; background: var(--bg-surface-elevated); padding: 14px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
            <div><strong>Ubicación:</strong> ${b.ubicacion_nombre}</div>
            <div><strong>Cantidad:</strong> ${b.cantidad} unidades</div>
            <div><strong>Valor Unitario:</strong> $ ${Number(b.valor_unitario).toLocaleString('es-AR')}</div>
            <div><strong>Valuación Total:</strong> <span style="color: var(--primary); font-weight: 700;">$ ${Number(b.valor_total).toLocaleString('es-AR')}</span></div>
            <div><strong>Expediente:</strong> ${b.expediente}</div>
            <div><strong>Fecha Alta:</strong> ${b.fecha_alta}</div>
            <div style="grid-column: span 2;"><strong>Motivo Alta:</strong> ${b.motivo_alta}</div>
            <div style="grid-column: span 2;"><strong>Auditoría Anual:</strong> ${b.auditado_anual ? '✓ Auditado en mapa físico (' + (b.auditor_nombre || 'Ayudante') + ')' : '○ Pendiente de recuento'}</div>
            ${b.observaciones ? `<div style="grid-column: span 2;"><strong>Observaciones:</strong> ${b.observaciones}</div>` : ''}
          </div>
        </div>
      </div>
    `;

    modal.classList.add('show');
  },

  async openEditModal(bienId) {
    const res = await API.bienes.get(bienId);
    if (!res.success) return;
    const b = res.data;

    document.getElementById('edit-bien-id').value = b.id;
    document.getElementById('edit-nro-cuenta').value = b.nro_cuenta;
    document.getElementById('edit-nro-orden').value = b.nro_orden;
    document.getElementById('edit-identificacion').value = b.identificacion;
    document.getElementById('edit-categoria').value = b.categoria;
    document.getElementById('edit-ubicacion-id').value = b.ubicacion_id || 1;
    document.getElementById('edit-cantidad').value = b.cantidad;
    document.getElementById('edit-valor-unitario').value = b.valor_unitario;
    document.getElementById('edit-estado').value = b.estado;
    document.getElementById('edit-expediente').value = b.expediente;
    document.getElementById('edit-motivo-alta').value = b.motivo_alta;
    document.getElementById('edit-observaciones').value = b.observaciones || '';

    const modal = document.getElementById('modal-edit-bien');
    if (modal) modal.classList.add('show');
  },

  async handleEditSubmit(e) {
    e.preventDefault();
    const f = e.target;

    const data = {
      id: f.bien_id.value,
      nro_cuenta: f.nro_cuenta.value,
      nro_orden: f.nro_orden.value,
      identificacion: f.identificacion.value,
      categoria: f.categoria.value,
      ubicacion_id: f.ubicacion_id.value,
      ubicacion_nombre: f.ubicacion_id.options[f.ubicacion_id.selectedIndex].text,
      cantidad: f.cantidad.value,
      valor_unitario: f.valor_unitario.value,
      estado: f.estado.value,
      expediente: f.expediente.value,
      motivo_alta: f.motivo_alta.value,
      observaciones: f.observaciones.value
    };

    const res = await API.bienes.update(data);
    if (res.success) {
      this.closeModal('modal-edit-bien');
      this.showToast('Bien patrimonial actualizado correctamente', 'success');
      await this.loadCatalogo();
      await this.loadDashboard();
    } else {
      this.showToast(res.error || 'Error al actualizar', 'danger');
    }
  },

  closeModal(modalId) {
    const m = document.getElementById(modalId);
    if (m) m.classList.remove('show');
  },

  // -------------------------------------------------------------
  // Toast Notifications
  // -------------------------------------------------------------
  showToast(message, type = 'info') {
    let container = document.getElementById('toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toast-container';
      container.className = 'toast-container';
      document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
      <div style="font-weight: 600;">${message}</div>
    `;

    container.appendChild(toast);

    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(10px)';
      toast.style.transition = 'all 0.3s ease';
      setTimeout(() => toast.remove(), 300);
    }, 3500);
  },

  // -------------------------------------------------------------
  // Global Event Listeners
  // -------------------------------------------------------------
  bindEvents() {
    // Cerrar dropdown de roles al hacer click afuera
    window.addEventListener('click', () => this.hideRoleDropdown());

    // Theme toggle
    document.getElementById('btn-theme-toggle')?.addEventListener('click', () => this.toggleTheme());

    // User pill toggle dropdown
    document.getElementById('user-menu-pill')?.addEventListener('click', (e) => this.toggleRoleDropdown(e));

    // Navegación
    document.querySelectorAll('.nav-item-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const v = btn.getAttribute('data-view');
        if (v) this.navigate(v);
      });
    });

    // Filtros de catálogo
    document.getElementById('filter-search')?.addEventListener('input', () => this.handleFilterChange());
    document.getElementById('filter-categoria')?.addEventListener('change', () => this.handleFilterChange());
    document.getElementById('filter-cuenta')?.addEventListener('change', () => this.handleFilterChange());
    document.getElementById('filter-estado')?.addEventListener('change', () => this.handleFilterChange());

    // Formularios
    document.getElementById('login-form')?.addEventListener('submit', (e) => this.handleLoginSubmit(e));
    document.getElementById('form-alta-bien')?.addEventListener('submit', (e) => this.handleAltaSubmit(e));
    document.getElementById('form-baja-bien')?.addEventListener('submit', (e) => this.handleBajaSubmit(e));
    document.getElementById('form-edit-bien')?.addEventListener('submit', (e) => this.handleEditSubmit(e));

    // Cálculos en tiempo real modal alta
    document.getElementById('alta-cantidad')?.addEventListener('input', () => this.calculateAltaTotal());
    document.getElementById('alta-valor-unitario')?.addEventListener('input', () => this.calculateAltaTotal());

    // Selectores de planillas
    document.querySelectorAll('.report-type-tab').forEach(tab => {
      tab.addEventListener('click', () => {
        const type = tab.getAttribute('data-report-type');
        if (type) this.setReportType(type);
      });
    });

    document.getElementById('report-trimester-select')?.addEventListener('change', (e) => {
      this.setTrimester(e.target.value);
    });

    // Botones de impresión y CSV
    document.getElementById('btn-print-official')?.addEventListener('click', () => this.printOfficialSheet());
    document.getElementById('btn-download-csv')?.addEventListener('click', () => this.downloadCSV());
  }
};

// Arrancar cuando cargue el DOM
document.addEventListener('DOMContentLoaded', () => {
  App.init();
});
