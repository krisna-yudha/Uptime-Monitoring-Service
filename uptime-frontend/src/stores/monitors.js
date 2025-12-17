import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '../services/api'

export const useMonitorStore = defineStore('monitors', () => {
  // State
  const monitors = ref([])
  const currentMonitor = ref(null)
  const loading = ref(false)
  const error = ref(null)

  // Actions
  // Helper: normalize monitor shape (ensure latency_ms present on checks)
  function normalizeMonitor(monitor) {
    if (!monitor) return monitor
    if (Array.isArray(monitor)) {
      return monitor.map(normalizeMonitor)
    }
    if (monitor.checks && Array.isArray(monitor.checks)) {
      monitor.checks = monitor.checks.map(c => ({
        ...c,
        latency_ms: c.latency_ms ?? c.latency ?? c.response_time ?? c.response_time_ms ?? null
      }))
    }
    return monitor
  }

  async function fetchMonitors(params = {}) {
    loading.value = true
    error.value = null
    
    try {
      const response = await api.monitors.getAll(params)
      
      if (response.data.success) {
        const payload = response.data.data.data || response.data.data
        monitors.value = Array.isArray(payload) ? payload.map(normalizeMonitor) : payload
        return { success: true, data: response.data.data }
      }
    } catch (err) {
      const message = err.response?.data?.message || 'Failed to fetch monitors'
      error.value = message
      return { success: false, message }
    } finally {
      loading.value = false
    }
  }

  // Silent fetch without loading state for background updates
  async function fetchMonitorsSilently(params = {}) {
    try {
      const response = await api.monitors.getAll(params)
      
      if (response.data.success) {
        // Update monitors state without changing loading state
        const payload = response.data.data.data || response.data.data
        monitors.value = Array.isArray(payload) ? payload.map(normalizeMonitor) : payload
        return { success: true, data: response.data.data }
      }
    } catch (err) {
      console.warn('Silent fetch failed:', err)
      return { success: false }
    }
  }

  async function fetchMonitor(id, params = {}) {
    loading.value = true
    error.value = null
    
    try {
      const response = await api.monitors.getById(id, params)
      
      console.log('🌐 API Response for monitor', id, ':', response.data)
      
      if (response.data.success) {
        currentMonitor.value = normalizeMonitor(response.data.data)
        
        console.log('📦 Monitor data stored:', {
          name: response.data.data.name,
          type: response.data.data.type,
          ssl_cert_expiry: response.data.data.ssl_cert_expiry,
          ssl_cert_issuer: response.data.data.ssl_cert_issuer
        })
        
        return { success: true, data: response.data.data }
      }
    } catch (err) {
      const message = err.response?.data?.message || 'Failed to fetch monitor'
      error.value = message
      return { success: false, message }
    } finally {
      loading.value = false
    }
  }

  async function createMonitor(monitorData) {
    loading.value = true
    error.value = null
    
    try {
      const response = await api.monitors.create(monitorData)
      
      if (response.data.success) {
        // Normalize and add to local state
        const created = normalizeMonitor(response.data.data)
        monitors.value.unshift(created)
        return { success: true, data: created }
      }
    } catch (err) {
      const message = err.response?.data?.message || 'Failed to create monitor'
      error.value = message
      return { success: false, message }
    } finally {
      loading.value = false
    }
  }

  async function updateMonitor(id, monitorData) {
    loading.value = true
    error.value = null
    
    try {
      const response = await api.monitors.update(id, monitorData)
      
      if (response.data.success) {
        const updated = normalizeMonitor(response.data.data)
        // Update in local state
        const index = monitors.value.findIndex(m => m.id === id)
        if (index !== -1) {
          monitors.value[index] = updated
        }
        
        // Update current monitor if it's the same
        if (currentMonitor.value?.id === id) {
          currentMonitor.value = updated
        }
        
        return { success: true, data: updated }
      }
    } catch (err) {
      const message = err.response?.data?.message || 'Failed to update monitor'
      error.value = message
      return { success: false, message }
    } finally {
      loading.value = false
    }
  }

  async function deleteMonitor(id) {
    loading.value = true
    error.value = null
    
    try {
      const response = await api.monitors.delete(id)
      
      if (response.data.success) {
        // Remove from local state
        monitors.value = monitors.value.filter(m => m.id !== id)
        
        // Clear current monitor if it's the same
        if (currentMonitor.value?.id === id) {
          currentMonitor.value = null
        }
        
        return { success: true }
      }
    } catch (err) {
      const message = err.response?.data?.message || 'Failed to delete monitor'
      error.value = message
      return { success: false, message }
    } finally {
      loading.value = false
    }
  }

  async function pauseMonitor(id, duration) {
    loading.value = true
    error.value = null
    
    try {
      const response = await api.monitors.pause(id, duration)
      
      if (response.data.success) {
        // Update in local state
        const index = monitors.value.findIndex(m => m.id === id)
        if (index !== -1) {
          monitors.value[index] = response.data.data
        }
        
        return { success: true, data: response.data.data }
      }
    } catch (err) {
      const message = err.response?.data?.message || 'Failed to pause monitor'
      error.value = message
      return { success: false, message }
    } finally {
      loading.value = false
    }
  }

  async function resumeMonitor(id) {
    loading.value = true
    error.value = null
    
    try {
      const response = await api.monitors.resume(id)
      
      if (response.data.success) {
        // Update in local state
        const index = monitors.value.findIndex(m => m.id === id)
        if (index !== -1) {
          monitors.value[index] = response.data.data
        }
        
        return { success: true, data: response.data.data }
      }
    } catch (err) {
      const message = err.response?.data?.message || 'Failed to resume monitor'
      error.value = message
      return { success: false, message }
    } finally {
      loading.value = false
    }
  }

  async function getGroups() {
    try {
      const response = await api.monitors.getGroups()
      return { success: true, data: response.data.data }
    } catch (err) {
      const message = err.response?.data?.message || 'Failed to fetch groups'
      error.value = message
      return { success: false, message }
    }
  }

  async function getGroupedMonitors(params = {}) {
    try {
      const response = await api.monitors.getGrouped(params)
      return { success: true, data: response.data.data }
    } catch (err) {
      const message = err.response?.data?.message || 'Failed to fetch grouped monitors'
      error.value = message
      return { success: false, message }
    }
  }

  // Silent version for background updates
  async function getGroupedMonitorsSilently(params = {}) {
    try {
      const response = await api.monitors.getGrouped(params)
      return { success: true, data: response.data.data }
    } catch (err) {
      console.warn('Silent grouped fetch failed:', err)
      return { success: false }
    }
  }

  async function bulkAction(action, monitorIds, extraData = {}) {
    try {
      const response = await api.monitors.bulkAction({
        action,
        monitor_ids: monitorIds,
        ...extraData
      })
      
      if (response.data.success) {
        // Refresh monitors list
        await fetchMonitors()
        return { success: true, data: response.data }
      }
    } catch (err) {
      const message = err.response?.data?.message || `Failed to ${action} monitors`
      error.value = message
      return { success: false, message }
    }
  }

  return {
    // State
    monitors,
    currentMonitor,
    loading,
    error,
    
    // API access (for direct API calls)
    api,
    
    // Actions
    fetchMonitors,
    fetchMonitorsSilently,
    fetchMonitor,
    createMonitor,
    updateMonitor,
    deleteMonitor,
    pauseMonitor,
    resumeMonitor,
    getGroups,
    getGroupedMonitors,
    getGroupedMonitorsSilently,
    bulkAction
  }
})