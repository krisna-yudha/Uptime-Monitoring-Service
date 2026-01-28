import axios from 'axios'

const host = window.location.hostname

const isPrivateIP =
  host.startsWith('100.') ||
  host.startsWith('192.168.') ||
  host.startsWith('10.')

const isProdDomain =
  host.endsWith('.gentz.me') || host === 'gentz.me'

// Logic OR:
// 👉 Private IP → direct VM HTTP
// 👉 Production domain → HTTPS API domain
// 👉 Else → env / localhost

const baseURL = isPrivateIP
  ? `http://${host}:80/api`
  : isProdDomain
    ? 'https://api.gentz.me/api'
    : (import.meta.env.VITE_BACKEND_URL || 'http://localhost:8000/api')

const api = axios.create({
  baseURL,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
})

// Request interceptor to add auth token
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

// Response interceptor for error handling
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // Token expired or invalid
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      window.location.href = '/login'
    }
    return Promise.reject(error)
  }
)

export default {
  // Auth endpoints
  auth: {
    login: (credentials) => api.post('/auth/login', credentials),
    register: (userData) => api.post('/auth/register', userData),
    logout: () => api.post('/auth/logout'),
    me: () => api.get('/auth/me'),
    refresh: () => api.post('/auth/refresh')
  },

  // Monitor endpoints
  monitors: {
    getAll: (params = {}) => api.get('/monitors', { params }),
    getById: (id, params = {}) => api.get(`/monitors/${id}`, { params }),
    create: (data) => api.post('/monitors', data),
    update: (id, data) => api.put(`/monitors/${id}`, data),
    delete: (id) => api.delete(`/monitors/${id}`),
    pause: (id, duration) => api.post(`/monitors/${id}/pause`, { duration_minutes: duration }),
    resume: (id) => api.post(`/monitors/${id}/resume`),
    triggerCheck: (id) => api.post(`/monitors/${id}/check`),
    getGroups: () => api.get('/monitors/groups'),
    getGrouped: (params = {}) => api.get('/monitors/grouped', { params }),
    bulkAction: (data) => api.post('/monitors/bulk-action', data),
    bulkAssignNotifications: (data) => api.post('/monitors/bulk-assign-notifications', data)
  },

  // Dashboard endpoints
  dashboard: {
    overview: () => api.get('/dashboard/overview'),
    responseTimeStats: (params = {}) => api.get('/dashboard/response-time-stats', { params }),
    uptimeStats: (params = {}) => api.get('/dashboard/uptime-stats', { params }),
    incidentHistory: (params = {}) => api.get('/dashboard/incident-history', { params }),
    checkHistory: (params = {}) => api.get('/dashboard/check-history', { params }),
    sslReport: () => api.get('/dashboard/ssl-report')
  },

  // Notification channels
  notificationChannels: {
    getAll: () => api.get('/notification-channels'),
    getById: (id) => api.get(`/notification-channels/${id}`),
    create: (data) => api.post('/notification-channels', data),
    update: (id, data) => api.put(`/notification-channels/${id}`, data),
    delete: (id) => api.delete(`/notification-channels/${id}`),
    test: (id) => api.post(`/notification-channels/${id}/test`),
    toggle: (id) => api.post(`/notification-channels/${id}/toggle`),
    connect: (id) => api.post(`/notification-channels/${id}/connect`)
  },

  // Monitor checks
  monitorChecks: {
    getAll: (params = {}) => api.get('/monitor-checks', { params }),
    getById: (id) => api.get(`/monitor-checks/${id}`),
    deleteByMonitor: (monitorId) => api.delete(`/monitors/${monitorId}/checks`)
  },

  // Incidents
  incidents: {
    getAll: (params = {}) => api.get('/incidents', { params }),
    getById: (id) => api.get(`/incidents/${id}`),
    update: (id, data) => api.put(`/incidents/${id}`, data),
    markPending: (id, data = {}) => api.post(`/incidents/${id}/pending`, data),
    markResolved: (id, data = {}) => api.post(`/incidents/${id}/resolved`, data),
    acknowledge: (id, data = {}) => api.post(`/incidents/${id}/acknowledge`, data),
    resolve: (id, data = {}) => api.post(`/incidents/${id}/resolve`, data),
    reopen: (id) => api.post(`/incidents/${id}/reopen`),
    addNote: (id, data) => api.post(`/incidents/${id}/notes`, data),
    delete: (id) => api.delete(`/incidents/${id}`)
  },

  // Settings
  settings: {
    get: () => api.get('/settings'),
    save: (data) => api.put('/settings', data),
    runAggregation: (data) => api.post('/settings/aggregate', data),
    runCleanup: (data) => api.post('/settings/cleanup', data)
  },

  // User Management
  users: {
    getAll: () => api.get('/users'),
    create: (data) => api.post('/users', data),
    update: (id, data) => api.put(`/users/${id}`, data),
    delete: (id) => api.delete(`/users/${id}`)
  },

  // Public Monitors (no auth required)
  publicMonitors: {
    getAll: () => axios.get(`${api.defaults.baseURL}/public/monitors`),
    getById: (id) => axios.get(`${api.defaults.baseURL}/public/monitors/${id}`),
    getStatistics: () => axios.get(`${api.defaults.baseURL}/public/monitors/statistics`)
  },

  // Heartbeat (public endpoint)
  heartbeat: {
    send: (key, data) => axios.post(`http://localhost:8000/api/heartbeat/${key}`, data)
  }
}