import axios from 'axios'

// Create axios instance
const api = axios.create({
  baseURL: import.meta.env.VITE_BACKEND_URL || 'http://localhost:8000/api',
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
    getById: (id) => api.get(`/monitors/${id}`),
    create: (data) => api.post('/monitors', data),
    update: (id, data) => api.put(`/monitors/${id}`, data),
    delete: (id) => api.delete(`/monitors/${id}`),
    pause: (id, duration) => api.post(`/monitors/${id}/pause`, { duration_minutes: duration }),
    resume: (id) => api.post(`/monitors/${id}/resume`),
    getGroups: () => api.get('/monitors/groups'),
    getGrouped: (params = {}) => api.get('/monitors/grouped', { params }),
    bulkAction: (data) => api.post('/monitors/bulk-action', data)
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
    toggle: (id) => api.post(`/notification-channels/${id}/toggle`)
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
    acknowledge: (id) => api.post(`/incidents/${id}/acknowledge`),
    resolve: (id) => api.post(`/incidents/${id}/resolve`),
    reopen: (id) => api.post(`/incidents/${id}/reopen`),
    addNote: (id, data) => api.post(`/incidents/${id}/notes`, data)
  },

  // Heartbeat (public endpoint)
  heartbeat: {
    send: (key, data) => axios.post(`http://localhost:8000/api/heartbeat/${key}`, data)
  }
}