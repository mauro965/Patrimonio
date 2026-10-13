// assets/js/api.js - Cliente de API para Sistema de Patrimonio Escolar

const API = {
  baseUrl: 'api/',

  async request(endpoint, options = {}) {
    const url = `${this.baseUrl}${endpoint}`;
    const defaultHeaders = {
      'Accept': 'application/json'
    };

    if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
      defaultHeaders['Content-Type'] = 'application/json';
      options.body = JSON.stringify(options.body);
    }

    try {
      const response = await fetch(url, {
        ...options,
        headers: { ...defaultHeaders, ...options.headers }
      });
      const data = await response.json();
      return data;
    } catch (error) {
      console.error(`Error en API (${endpoint}):`, error);
      return { success: false, error: 'Error de conexión con el servidor.' };
    }
  },

  // Auth
  auth: {
    check: () => API.request('auth.php?action=check'),
    login: (email, password) => API.request('auth.php?action=login', {
      method: 'POST',
      body: { email, password }
    }),
    quickLogin: (user_id) => API.request('auth.php?action=quick_login', {
      method: 'POST',
      body: { user_id }
    }),
    logout: () => API.request('auth.php?action=logout', { method: 'POST' })
  },

  // Bienes
  bienes: {
    list: (params = {}) => {
      const query = new URLSearchParams(params).toString();
      return API.request(`bienes.php?action=list&${query}`);
    },
    get: (id) => API.request(`bienes.php?action=get&id=${id}`),
    create: (data) => API.request('bienes.php?action=create', {
      method: 'POST',
      body: data
    }),
    update: (data) => API.request('bienes.php?action=update', {
      method: 'POST',
      body: data
    }),
    baja: (data) => API.request('bienes.php?action=baja', {
      method: 'POST',
      body: data
    }),
    toggleAudit: (id) => API.request('bienes.php?action=toggle_audit', {
      method: 'POST',
      body: { id }
    })
  },

  // Movimientos
  movimientos: {
    list: (params = {}) => {
      const query = new URLSearchParams(params).toString();
      return API.request(`movimientos.php?action=list&${query}`);
    },
    create: (data) => API.request('movimientos.php?action=create', {
      method: 'POST',
      body: data
    })
  },

  // Auditoria
  auditoria: {
    mapData: () => API.request('auditoria.php?action=map_data')
  },

  // Estadísticas
  estadisticas: {
    dashboard: () => API.request('estadisticas.php?action=dashboard')
  },

  // Exportar URLs
  exportar: {
    csvUrl: (tipo, trimestre, anio) => `api/exportar.php?format=csv&tipo=${tipo}&trimestre=${trimestre}&anio=${anio}`,
    json: (tipo, trimestre, anio) => API.request(`exportar.php?format=json&tipo=${tipo}&trimestre=${trimestre}&anio=${anio}`)
  }
};
