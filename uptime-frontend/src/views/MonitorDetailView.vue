<template>
  <div class="monitor-detail">
    <div class="monitor-detail-content">
    <!-- Header Section -->
    <div class="detail-header">
      <div class="header-content">
        <div class="monitor-title">
          <h1>
            {{ monitor?.name || 'Loading...' }}
            <span v-if="chartRefreshInterval" class="live-badge" title="Auto-refreshing every 1 second">
              <span class="live-dot"></span> Live
            </span>
          </h1>
          <div class="monitor-url">
            <span class="url-text">{{ monitor?.target }}</span>
            <button 
              @click="visitMonitor" 
              class="visit-btn"
              :disabled="!isVisitable(monitor)"
              :title="getVisitTooltip(monitor)"
            >
               🔗 Visit
            </button>
          </div>
          <div class="monitor-meta">
            <span class="monitor-type-badge">{{ monitor?.type?.toUpperCase() }}</span>
            <span class="monitor-interval">Check every {{ monitor?.interval_seconds }}s</span>
            <span v-if="monitor?.group_name" class="monitor-group">📁 {{ monitor.group_name }}</span>
            <span v-if="monitor?.created_by_name" class="monitor-creator">👤 Added by {{ monitor.created_by_name }}</span>
            <span v-if="monitor?.created_at" class="monitor-created" :title="formatDateFull(monitor.created_at)">📅 {{ formatDateRelative(monitor.created_at) }}</span>
          </div>
        </div>
        <div class="monitor-status-section">
          <div 
            class="current-status"
            :class="{
              'status-up': monitor?.last_status === 'up',
              'status-down': monitor?.last_status === 'down',
              'status-invalid': monitor?.last_status === 'invalid',
              'status-validating': monitor?.last_status === 'validating',
              'status-unknown': monitor?.last_status === 'unknown'
            }"
          >
            {{ monitor?.last_status?.toUpperCase() || 'UNKNOWN' }}
          </div>
          <div class="status-info">
            <span v-if="monitor?.last_checked_at">
              Last checked: {{ formatDate(monitor.last_checked_at) }}
            </span>
            <span v-else>Never checked</span>
          </div>
        </div>
      </div>
      
      <!-- Action Buttons -->
      <div class="header-actions">
        <button 
          @click="toggleMonitor" 
          class="btn"
          :class="isPaused ? 'btn-success' : 'btn-warning'"
        >
          {{ isPaused ? '▶️ Resume' : '⏸️ Pause' }}
        </button>
        <!-- <router-link :to="`/logs/monitor/${$route.params.id}`" class="btn btn-info">
          📋 View Logs
        </router-link> -->
        <router-link :to="`/monitors/${$route.params.id}/edit`" class="btn btn-primary">
          ⚙️ Edit
        </router-link>
        <button @click="deleteMonitor" class="btn btn-danger">
          🗑️ Delete
        </button>
      </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid" v-if="!loading">
      <div class="stat-card" v-for="(stat, index) in statsData" :key="stat.key" :style="{ animationDelay: `${index * 0.1}s` }">
        <div class="stat-header">{{ stat.header }}</div>
        <div class="stat-subheader">{{ stat.subheader }}</div>
        <div class="stat-value" :class="stat.valueClass">
          <span v-if="stat.loading" class="stat-loading">...</span>
          <span v-else>{{ stat.value }}</span>
        </div>
        <div class="stat-trend" v-if="stat.trend">
          <span class="trend-icon" :class="stat.trend.direction">{{ stat.trend.icon }}</span>
          <span class="trend-text">{{ stat.trend.text }}</span>
        </div>
      </div>
    </div>

    <!-- Stats Loading Skeleton -->
    <div class="stats-grid skeleton" v-if="loading">
      <div class="stat-card skeleton-card" v-for="n in 2" :key="n">
        <div class="skeleton-line short"></div>
        <div class="skeleton-line mini"></div>
        <div class="skeleton-line medium"></div>
      </div>
    </div>

    <!-- Chart Section -->
    <div class="chart-section" v-if="!loading">
      <div class="chart-header">
        <h2>Response Time</h2>
        <div class="chart-controls">
          <div class="chart-period">
            <button 
              v-for="period in chartPeriods" 
              :key="period.value"
              @click="selectPeriod(period.value)"
              class="period-btn"
              :class="{ active: selectedPeriod === period.value }"
              :disabled="chartLoading"
            >
              <span v-if="chartLoading && selectedPeriod === period.value" class="btn-spinner"></span>
              {{ period.label }}
            </button>
          </div>
          <button @click="refreshChart" class="refresh-chart-btn" :disabled="chartLoading">
            <span class="refresh-icon" :class="{ spinning: chartLoading }">🔄</span>
          </button>
        </div>
      </div>
      <div class="chart-container" :class="{ loading: chartLoading }">
        <div v-if="chartLoading" class="chart-loading">
          <div class="chart-spinner"></div>
          <span>Loading chart data...</span>
        </div>
        <Line 
          v-if="chartData && !chartLoading" 
          :data="chartData" 
          :options="chartOptions"
        />
        <div v-if="!chartData && !chartLoading" class="no-chart-data">
          <p>No chart data available</p>
        </div>
      </div>
    </div>

    <!-- Chart Loading Skeleton -->
    <div class="chart-section skeleton" v-if="loading">
      <div class="chart-header">
        <div class="skeleton-line long"></div>
        <div class="skeleton-line short"></div>
      </div>
      <div class="chart-skeleton"></div>
    </div>

    <!-- Status History -->
    <div class="status-history">
      <div class="history-header">
        <div class="history-title-section">
          <h2>Status History</h2>
            <span class="realtime-indicator" :class="{ active: historyRefreshInterval }">🔄 Live</span>
            <span v-if="pollingFirstCheck" class="first-check-note" style="margin-left:12px;color:#ffd166;font-weight:600;">Waiting for first check…</span>
        </div>
        <button @click="clearData" class="btn btn-outline btn-sm">
          🗑️ Clear Data
        </button>
      </div>
      
      <div class="history-table-container">
        <table class="history-table">
          <thead>
            <tr>
              <th>Status</th>
              <th>DateTime</th>
              <th>Message</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="check in paginatedStatusHistory" :key="check.id" class="history-row">
              <td>
                <span 
                  class="status-badge-small"
                  :class="{
                    'status-up': check.status === 'up',
                    'status-down': check.status === 'down',
                    'status-invalid': check.status === 'invalid',
                    'status-validating': check.status === 'validating',
                    'status-unknown': check.status === 'unknown'
                  }"
                >
                  {{ check.status?.toUpperCase() || 'UNKNOWN' }}
                </span>
              </td>
              <td class="datetime-cell">{{ formatDateTime(check.checked_at) }}</td>
              <td class="message-cell">
                <span v-if="check.status === 'up'" class="success-message">
                  {{ check.response_time ? `${check.response_time}ms - OK` : 'OK' }}
                </span>
                <span v-else-if="check.error_message" class="error-message">
                  {{ check.error_message }}
                </span>
                <span v-else class="neutral-message">
                  {{ check.status === 'down' ? 'Service unavailable' : 'Status check completed' }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
        
        <!-- Pagination -->
        <div class="pagination" v-if="totalPages > 1">
          <button 
            @click="currentPage--" 
            :disabled="currentPage === 1"
            class="page-btn"
          >
            ← Previous
          </button>
          <span class="page-info">Page {{ currentPage }} of {{ totalPages }}</span>
          <button 
            @click="currentPage++" 
            :disabled="currentPage === totalPages"
            class="page-btn"
          >
            Next →
          </button>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-overlay">
      <div class="loading-spinner"></div>
      <span>Loading monitor data...</span>
    </div>

    <!-- Error State -->
    <div v-if="error" class="error-state">
      <h3>Error Loading Monitor</h3>
      <p>{{ error }}</p>
      <button @click="fetchMonitorData" class="btn btn-primary">Retry</button>
    </div>
    </div>

    <!-- Notification Popup -->
    <transition name="notification-slide">
      <div v-if="showNotification" class="notification-popup" :class="`notification-${notificationType}`">
        <div class="notification-content">
          <span class="notification-icon">
            <span v-if="notificationType === 'success'">✅</span>
            <span v-else-if="notificationType === 'error'">❌</span>
            <span v-else-if="notificationType === 'warning'">⚠️</span>
            <span v-else>ℹ️</span>
          </span>
          <span class="notification-message">{{ notificationMessage }}</span>
          <button @click="showNotification = false" class="notification-close">×</button>
        </div>
      </div>
    </transition>

    <!-- Confirmation Dialog -->
    <transition name="notification-slide">
      <div v-if="showConfirmation" class="confirmation-dialog">
        <div class="confirmation-content">
          <div class="confirmation-icon">⚠️</div>
          <div class="confirmation-message">{{ confirmationMessage }}</div>
          <div class="confirmation-actions">
            <button @click="handleConfirmNo" class="confirm-btn cancel-btn">Cancel</button>
            <button @click="handleConfirmYes" class="confirm-btn yes-btn">Yes, Continue</button>
          </div>
        </div>
      </div>
    </transition>

    <!-- Confirmation Dialog -->
    <transition name="notification-slide">
      <div v-if="showConfirmation" class="confirmation-dialog">
        <div class="confirmation-content">
          <div class="confirmation-icon">⚠️</div>
          <div class="confirmation-message">{{ confirmationMessage }}</div>
          <div class="confirmation-actions">
            <button @click="handleConfirmNo" class="confirm-btn cancel-btn">Cancel</button>
            <button @click="handleConfirmYes" class="confirm-btn yes-btn">Yes, Continue</button>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>
<script setup>
import { ref, onMounted, onUnmounted, computed, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useMonitorStore } from '../stores/monitors'
import { Line } from 'vue-chartjs'
import { Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend, Filler } from 'chart.js'

// Register Chart.js components
ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend, Filler)

const route = useRoute()
const router = useRouter()
const monitorStore = useMonitorStore()

// State
const monitor = ref(null)
const loading = ref(false)
const error = ref(null)
const selectedPeriod = ref('24h')
const currentPage = ref(1)
const itemsPerPage = 5
const statusHistory = ref([]) // (masih ada, walau belum dipakai)
const allStatusHistory = ref([])
const totalItems = ref(0)
const chartData = ref(null)
const chartOptions = ref(null)
const chartLoading = ref(false)
const chartRefreshInterval = ref(null)
const historyRefreshInterval = ref(null)
const fallbackHistoryPollInterval = ref(null)
const lastUpdateTime = ref(0)
const isUpdating = ref(false)
const lastKnownCheckedAt = ref(null)
const pollingFirstCheck = ref(false)
let firstCheckPollInterval = null
let firstCheckPollAttempts = 0

// Notification popup state
const showNotification = ref(false)
const notificationMessage = ref('')
const notificationType = ref('info') // 'success', 'error', 'warning', 'info'

// Confirmation dialog state
const showConfirmation = ref(false)
const confirmationMessage = ref('')
const confirmationCallback = ref(null)

// Chart periods
const chartPeriods = [
  { label: 'Recent', value: '1h' },
  { label: '24h', value: '24h' },
  { label: '7d', value: '7d' },
  { label: '30d', value: '30d' }
]

// Computed properties
const totalPages = computed(() => Math.ceil(totalItems.value / itemsPerPage))

const paginatedStatusHistory = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage
  const end = start + itemsPerPage
  return allStatusHistory.value.slice(start, end)
})

const statsData = computed(() => {
  if (!monitor.value) return []
  
  // Get latest response time from last check
  const latestCheck = monitor.value?.checks?.[0]
  const currentLatency = latestCheck?.latency_ms || null
  
  // NOTE: Uptime stats removed for faster loading
  // Only showing current response and SSL cert expiry
  
  return [
    {
      key: 'response',
      header: 'Response',
      subheader: '(Current)',
      value: currentLatency ? `${currentLatency} ms` : 'N/A',
      valueClass: 'response-value',
      loading: false,
      trend: currentLatency ? {
        direction: currentLatency < 200 ? 'up' : 'down',
        icon: currentLatency < 200 ? '↗' : '↘',
        text: currentLatency < 200 ? 'Fast' : 'Slow'
      } : null
    },
    {
      key: 'cert_exp',
      header: 'Cert Exp.',
      subheader: '(SSL)',
      value: getCertExpiryDisplay(),
      valueClass: 'cert-value',
      loading: false,
      trend: getCertExpiryTrend()
    }
  ]
})

// Computed property to check if monitor is paused
const isPaused = computed(() => {
  if (!monitor.value || !monitor.value.pause_until) return false
  return new Date(monitor.value.pause_until) > new Date()
})

// Lifecycle
onMounted(async () => {
  await nextTick()
  await fetchMonitorData()
  // Start a lightweight fallback poll to ensure history keeps updating
  ensureHistoryPolling()
  // Auto-refresh akan di-start setelah monitor berhasil di-load
  // Expose small helpers for debugging from DevTools
  try {
    window.__monitorHelpers = window.__monitorHelpers || {}
    window.__monitorHelpers.forceFetchChecks = async (id) => {
      try {
        const response = await monitorStore.api.monitorChecks.getAll({ monitor_id: id || route.params.id, per_page: 100, sort: 'checked_at', order: 'desc', _t: Date.now() })
                return response.data
      } catch (e) {
        console.error('Helper fetch failed', e)
        throw e
      }
    }
    window.__monitorHelpers.forceRefreshUI = async (id) => {
      // force fetch monitor and status history
      try {
        await monitorStore.fetchMonitor(id || route.params.id, { _t: Date.now() })
        await fetchStatusHistory()
              } catch (e) {
        console.error('Helper refresh failed', e)
      }
    }
  } catch (e) {}
})

onUnmounted(() => {
  destroyChart()
  stopChartAutoRefresh()
  stopHistoryAutoRefresh()
  stopFirstCheckPoll()
  stopFallbackHistoryPolling()
  try {
    if (window.__monitorHelpers) {
      delete window.__monitorHelpers.forceFetchChecks
      delete window.__monitorHelpers.forceRefreshUI
    }
  } catch (e) {}
})

// Watchers
watch(() => route.params.id, async (newId, oldId) => {
  // Stop all intervals when switching monitors to prevent data mixup
  if (oldId && newId !== oldId) {
    stopChartAutoRefresh()
    stopHistoryAutoRefresh()
    stopFirstCheckPoll()
    stopFallbackHistoryPolling()
    
    // Clear current data
    monitor.value = null
    allStatusHistory.value = []
    chartData.value = null
  }
  
  await fetchMonitorData()
})

watch(() => monitor.value, async (newVal) => {
  if (newVal && !chartLoading.value) {
    await nextTick()
    await fetchChartData()
  }
}, { deep: false })

// Helper functions for average response time calculation
function getAverageResponse(hours = 1) {
  if (!monitor.value) return 'N/A'
  
  // Use pre-calculated average from backend if available
  if (hours === 1 && monitor.value.avg_response_1h !== undefined && monitor.value.avg_response_1h !== null) {
    return `${monitor.value.avg_response_1h} ms`
  }
  if (hours === 24 && monitor.value.avg_response_24h !== undefined && monitor.value.avg_response_24h !== null) {
    return `${monitor.value.avg_response_24h} ms`
  }
  if (hours === 168 && monitor.value.avg_response_7d !== undefined && monitor.value.avg_response_7d !== null) {
    return `${monitor.value.avg_response_7d} ms`
  }
  if (hours === 720 && monitor.value.avg_response_30d !== undefined && monitor.value.avg_response_30d !== null) {
    return `${monitor.value.avg_response_30d} ms`
  }
  
  // Fallback to client-side calculation from allStatusHistory
  if (!allStatusHistory.value || allStatusHistory.value.length === 0) {
    return 'N/A'
  }
  
  // Filter checks within the time period and with valid response times
  const cutoffTime = new Date(Date.now() - (hours * 60 * 60 * 1000))
  const recentChecks = allStatusHistory.value.filter(check => {
    if (!check.checked_at || !check.response_time) return false
    const checkTime = new Date(check.checked_at)
    return checkTime >= cutoffTime && check.status === 'up'
  })
  
  if (recentChecks.length === 0) return 'N/A'
  
  // Calculate average
  const sum = recentChecks.reduce((acc, check) => acc + parseFloat(check.response_time), 0)
  const average = sum / recentChecks.length
  
  return `${average.toFixed(2)} ms`
}

// Helper functions for uptime calculation
function getUptimeValue(periodHours) {
  if (!monitor.value || !monitor.value.created_at) return 'N/A'
  
  // Calculate how long the monitor has been running
  const createdAt = new Date(monitor.value.created_at)
  const now = new Date()
  const monitorAgeHours = (now - createdAt) / (1000 * 60 * 60)
  
  // If monitor hasn't been running long enough for the period, return N/A
  if (monitorAgeHours < periodHours) {
    return 'N/A'
  }
  
  // Get uptime percentage from monitor data based on period
  let uptimePercentage = null
  
  if (periodHours === 24) {
    // 24-hour uptime
    uptimePercentage = monitor.value.uptime_24h ?? monitor.value.uptime_percentage
  } else if (periodHours === 168) {
    // 7-day uptime (168 hours)
    uptimePercentage = monitor.value.uptime_7d ?? monitor.value.uptime_percentage
  } else if (periodHours === 720) {
    // 30-day/1-month uptime (720 hours)
    uptimePercentage = monitor.value.uptime_30d ?? monitor.value.uptime_1m ?? monitor.value.uptime_percentage
  }
  
  if (uptimePercentage !== null && uptimePercentage !== undefined) {
    return `${parseFloat(uptimePercentage).toFixed(1)}%`
  }
  
  return 'N/A'
}

function getUptimeTrend(uptimeValue) {
  if (uptimeValue === 'N/A') return null
  
  const percentage = parseFloat(uptimeValue)
  
  if (percentage >= 99.9) {
    return { direction: 'up', icon: '✓', text: 'Excellent' }
  } else if (percentage >= 99.0) {
    return { direction: 'up', icon: '↗', text: 'Good' }
  } else if (percentage >= 95.0) {
    return { direction: 'neutral', icon: '→', text: 'Fair' }
  } else {
    return { direction: 'down', icon: '↘', text: 'Poor' }
  }
}

// Helper functions for SSL certificate
function showNotif(message, type = 'info') {
  notificationMessage.value = message
  notificationType.value = type
  showNotification.value = true
  
  // Auto hide after 4 seconds
  setTimeout(() => {
    showNotification.value = false
  }, 4000)
}

function showConfirm(message, callback) {
  confirmationMessage.value = message
  confirmationCallback.value = callback
  showConfirmation.value = true
}

function handleConfirmYes() {
  if (confirmationCallback.value) {
    confirmationCallback.value()
  }
  showConfirmation.value = false
  confirmationCallback.value = null
}

function handleConfirmNo() {
  showConfirmation.value = false
  confirmationCallback.value = null
}

function getCertExpiryDisplay() {
  if (!monitor.value) {
    return 'N/A'
  }
  
  // Only show for HTTPS monitors
  if (monitor.value.type !== 'https') {
    return 'N/A'
  }
  
  // Check if SSL certificate info is available
  if (monitor.value.ssl_cert_expiry) {
    const expiryDate = new Date(monitor.value.ssl_cert_expiry)
    const now = new Date()
    const daysRemaining = Math.floor((expiryDate - now) / (1000 * 60 * 60 * 24))
    
    if (daysRemaining < 0) {
      return 'Expired'
    } else if (daysRemaining === 0) {
      return 'Today'
    } else if (daysRemaining === 1) {
      return '1 day'
    } else {
      return `${daysRemaining} days`
    }
  }
  
  // SSL cert not checked yet
  return 'Not Checked'
}

function getCertExpiryTrend() {
  if (!monitor.value || monitor.value.type !== 'https') {
    return null
  }
  
  if (!monitor.value.ssl_cert_expiry) {
    return {
      direction: 'down',
      icon: '⚠️',
      text: 'No data'
    }
  }
  
  const expiryDate = new Date(monitor.value.ssl_cert_expiry)
  const now = new Date()
  const daysRemaining = Math.floor((expiryDate - now) / (1000 * 60 * 60 * 24))
  
  if (daysRemaining < 0) {
    return {
      direction: 'down',
      icon: '❌',
      text: 'Expired'
    }
  } else if (daysRemaining <= 7) {
    return {
      direction: 'down',
      icon: '🔴',
      text: 'Critical'
    }
  } else if (daysRemaining <= 30) {
    return {
      direction: 'down',
      icon: '⚠️',
      text: 'Expires soon'
    }
  } else {
    return {
      direction: 'up',
      icon: '✓',
      text: 'Valid'
    }
  }
}

// Methods
async function fetchMonitorData() {
  loading.value = true
  error.value = null
  
  try {
    const currentMonitorId = route.params.id
    
    const cacheBuster = Date.now()
    const result = await monitorStore.fetchMonitor(currentMonitorId, { _t: cacheBuster })
    
    if (result.success) {
      if (result.data.id != currentMonitorId) {
        error.value = 'Monitor ID mismatch - data integrity error'
        loading.value = false
        return
      }
      
      if (route.params.id != currentMonitorId) {
        loading.value = false
        return
      }
      
      monitor.value = result.data
      if (monitor.value.checks && monitor.value.checks.length > 0) {
        monitor.value.checks = monitor.value.checks.map(c => ({
          ...c,
          latency_ms: c.latency_ms ?? c.latency ?? c.response_time ?? c.response_time_ms ?? null
        }))
      }
      
      // IMMEDIATE UI RENDER - Hide loading to show stats instantly
      loading.value = false
      
      await nextTick()
      
      if (monitor.value.checks && monitor.value.checks.length > 0) {
        allStatusHistory.value = monitor.value.checks.map(check => ({
          id: check.id,
          status: check.status,
          checked_at: check.checked_at,
          response_time: check.latency_ms ?? check.latency ?? check.response_time ?? check.response_time_ms ?? null,
          error_message: check.error_message
        }))
        totalItems.value = allStatusHistory.value.length
        currentPage.value = 1
      }

      // Load status history in background (non-blocking)
      setTimeout(() => {
        fetchStatusHistory().catch(e => console.error('Background status history fetch failed:', e))
      }, 100)
      
      // Load chart in background with delay (non-blocking)
      setTimeout(() => {
        fetchChartData().catch(e => console.error('Background chart fetch failed:', e))
      }, 200)
      
      // Auto-refresh will be started automatically after chart is created in fetchChartData
      // record last known checked time so live updates can detect new checks
      lastKnownCheckedAt.value = monitor.value.last_checked_at || null
            // Ensure Pinia store currentMonitor reflects any normalized checks
      try {
        monitorStore.currentMonitor = monitor.value
      } catch (e) {
        console.warn('Failed to sync component monitor into store.currentMonitor', e)
      }
    } else {
      error.value = result.message
    }
  } catch (err) {
    error.value = 'Failed to load monitor data'
    console.error('Error fetching monitor:', err)
  } finally {
    loading.value = false
  }
}

// Refresh monitor data silently (without loading state) for auto-refresh
async function refreshMonitorData() {
  try {
    const currentMonitorId = route.params.id
    const result = await monitorStore.fetchMonitor(currentMonitorId, { _t: Date.now() })
    
    if (result.success) {
      // CRITICAL: Validate that the returned data is for the current monitor
      // This prevents data mixup when switching between monitors
      if (result.data.id != currentMonitorId) {
        console.warn('⚠️ Monitor ID mismatch! Expected:', currentMonitorId, 'Got:', result.data.id)
        return
      }
      
      // Verify we're still on the same monitor (user might have navigated away)
      if (route.params.id != currentMonitorId) {
        console.warn('⚠️ Route changed during refresh, aborting update')
        return
      }
      
      // Update monitor data including checks for current response time
      // Always attempt to refresh history after silent monitor refresh --
      // this ensures recent checks are reflected even if last_checked_at
      // formats or small timing differences prevent simple comparisons.
      try {
        await updateHistoryRealtime()
      } catch (e) {
        console.warn('⚠️ updateHistoryRealtime failed during background refresh', e)
      }

      monitor.value.last_status = result.data.last_status
      monitor.value.last_checked_at = result.data.last_checked_at
      monitor.value.uptime_percentage = result.data.uptime_percentage
      
      // Update checks array if available (for current response time)
      if (result.data.checks && result.data.checks.length > 0) {
        // Normalize checks to ensure latency_ms is present (fallbacks for different API shapes)
        monitor.value.checks = result.data.checks.map(c => ({
          ...c,
          latency_ms: c.latency_ms ?? c.latency ?? c.response_time ?? c.response_time_ms ?? null
        }))
      }
    }
  } catch (err) {
    // Silent fail for background refresh
    console.error('⚠️ Background refresh failed:', err)
  }
}


async function fetchStatusHistory() {
  try {
    const currentMonitorId = route.params.id
    
    const response = await monitorStore.api.monitorChecks.getAll({
      monitor_id: currentMonitorId,
      per_page: 20,
      sort: 'checked_at',
      order: 'desc',
      _t: Date.now()
    })
    
      if (response.data.success) {
      let checks = []

      if (response.data.data.data) {
        checks = response.data.data.data
      } else if (Array.isArray(response.data.data)) {
        checks = response.data.data
      }

      // Normalize latency and dedupe by id, then sort by checked_at desc
      const normalized = checks.map(c => ({
        ...c,
        latency_ms: c.latency_ms ?? c.latency ?? c.response_time ?? c.response_time_ms ?? null
      }))

      const byId = normalized.reduce((map, c) => {
        map[c.id] = c
        return map
      }, {})

      let uniqueChecks = Object.values(byId)
      uniqueChecks.sort((a, b) => new Date(b.checked_at) - new Date(a.checked_at))

      allStatusHistory.value = uniqueChecks.map(check => ({
        id: check.id,
        status: check.status,
        checked_at: check.checked_at,
        response_time: check.latency_ms,
        error_message: check.error_message
      }))

      // Also update monitor.checks so stats and current latency reflect latest data
      try {
        if (monitor.value && monitor.value.id == route.params.id) {
          monitor.value.checks = uniqueChecks.map(c => ({ ...c }))
        }
      } catch (e) {
        console.warn('Failed to sync monitor.checks from status history', e)
      }

      totalItems.value = allStatusHistory.value.length
      currentPage.value = 1

      // Verify we're still viewing the same monitor before updating UI
      if (route.params.id != currentMonitorId) {
        return
      }
      
      // Start history auto-refresh
      startHistoryAutoRefresh()

      // If no checks yet, start a short aggressive poll to surface the first checks quickly
      if (allStatusHistory.value.length === 0) {
        startFirstCheckPoll()
      }
    } else {
      console.warn('❌ Failed to fetch monitor checks:', response.data.message)
      allStatusHistory.value = []
      totalItems.value = 0
    }
  } catch (err) {
    console.error('❌ Error fetching status history:', err)
    console.error('Error details:', err.response?.data || err.message)
    allStatusHistory.value = []
    totalItems.value = 0
  }
}

async function updateHistoryRealtime() {
  if (!monitor.value || loading.value) return
  
  const currentMonitorId = route.params.id
  
  try {
    const response = await monitorStore.api.monitorChecks.getAll({
      monitor_id: currentMonitorId,
      per_page: 5,
      sort: 'checked_at',
      order: 'desc',
      _t: Date.now()
    })
    
    if (response.data.success) {
        const latestChecks = response.data.data.data || response.data.data || []
        
        // Verify we're still on the same monitor before updating
        if (route.params.id != currentMonitorId) {
          console.warn('⚠️ Route changed during update, aborting history update')
          return
        }

        let newAdded = 0
        latestChecks.forEach(check => {
          // Validate that this check belongs to the current monitor
          if (check.monitor_id != currentMonitorId) {
            console.warn('⚠️ Check belongs to different monitor:', check.monitor_id, 'expected:', currentMonitorId)
            return
          }
          
          const exists = allStatusHistory.value.find(item => item.id === check.id)
          if (!exists) {
            allStatusHistory.value.unshift({
              id: check.id,
              status: check.status,
              checked_at: check.checked_at,
              response_time: check.latency_ms ?? check.latency ?? check.response_time ?? check.response_time_ms ?? null,
              error_message: check.error_message
            })
            newAdded++
          }
        })

        totalItems.value = allStatusHistory.value.length

        // If we were polling for the first check and we got data, stop the polling
        if (pollingFirstCheck.value && totalItems.value > 0) {
          stopFirstCheckPoll()
        }
        // If we added new checks, update lastKnownCheckedAt to the newest check's timestamp
        if (newAdded > 0 && allStatusHistory.value.length > 0) {
          try {
            lastKnownCheckedAt.value = allStatusHistory.value[0].checked_at || lastKnownCheckedAt.value
          } catch (e) {}
          // Trigger chart update when new checks arrive
          try {
            if (chartData.value) {
              // update chart using latest data points
              updateChartRealtime()
            } else {
              // If chart not initialized yet, ensure fetchChartData will draw it when ready
              fetchChartData().catch(() => {})
            }
          } catch (e) {
            console.warn('Failed to trigger chart update after new checks', e)
          }
        }
    }
  } catch (err) {
    // silent error
  }
}

function startChartAutoRefresh() {
  stopChartAutoRefresh()
  
  if (!monitor.value) {
    console.warn('⚠️ Cannot start auto-refresh - monitor not loaded')
    return
  }
  
  const refreshInterval = 1000 // Fixed 1 second auto-refresh (realtime)
  
  chartRefreshInterval.value = setInterval(() => {
    if (monitor.value && !isUpdating.value) {
      updateChartRealtime()
      // Also refresh monitor data to update last_checked_at and status
      refreshMonitorData()
    }
  }, refreshInterval)
}

function stopChartAutoRefresh() {
  if (chartRefreshInterval.value) {
    clearInterval(chartRefreshInterval.value)
    chartRefreshInterval.value = null
  }
}

function startHistoryAutoRefresh() {
  stopHistoryAutoRefresh()
  
  // Optimize refresh intervals for faster data capture in production
  let refreshInterval = 1500 // Default 1.5 seconds for fast capture
  
  if (monitor.value?.interval_seconds) {
    const monitorInterval = monitor.value.interval_seconds * 1000
    
    // For 10s monitors, check every 1s to catch data immediately
    if (monitorInterval <= 10000) {
      refreshInterval = 1000 // 1 second for 10s monitors
    } else if (monitorInterval <= 30000) {
      refreshInterval = 2000 // 2 seconds for 30s monitors
    } else if (monitorInterval <= 60000) {
      refreshInterval = 5000 // 5 seconds for 1min monitors
    } else {
      refreshInterval = 10000 // 10 seconds for slower monitors
    }
  }
  
  historyRefreshInterval.value = setInterval(() => {
    if (!loading.value && monitor.value && !chartLoading.value) {
      updateHistoryRealtime()
    }
  }, refreshInterval)
}

function stopHistoryAutoRefresh() {
  if (historyRefreshInterval.value) {
    clearInterval(historyRefreshInterval.value)
    historyRefreshInterval.value = null
  }
}

function startFirstCheckPoll() {
  stopFirstCheckPoll()

  if (!monitor.value) return

  pollingFirstCheck.value = true
  firstCheckPollAttempts = 0
    firstCheckPollInterval = setInterval(async () => {
    firstCheckPollAttempts++
    try {
      await updateHistoryRealtime()
    } catch (e) {
      // ignore
    }

    if (allStatusHistory.value.length > 0 || firstCheckPollAttempts >= 20) {
      stopFirstCheckPoll()
    }
  }, 1000)
}

function stopFirstCheckPoll() {
  pollingFirstCheck.value = false
  if (firstCheckPollInterval) {
    clearInterval(firstCheckPollInterval)
    firstCheckPollInterval = null
  }
}

// Fallback polling: ensures updateHistoryRealtime is invoked even if
// startHistoryAutoRefresh wasn't started for any reason (network race, chart failure)
function ensureHistoryPolling() {
  stopFallbackHistoryPolling()
  // Only start fallback if no primary history refresh is active
  if (!historyRefreshInterval.value) {
        fallbackHistoryPollInterval.value = setInterval(async () => {
      try {
        await updateHistoryRealtime()
        // If primary historyAutoRefresh starts, stop the fallback
        if (historyRefreshInterval.value) {
          stopFallbackHistoryPolling()
                  }
      } catch (e) {
        // ignore errors; fallback should be resilient
      }
    }, 3000) // Faster fallback: 3s instead of 5s
  }
}

function stopFallbackHistoryPolling() {
  if (fallbackHistoryPollInterval.value) {
    clearInterval(fallbackHistoryPollInterval.value)
    fallbackHistoryPollInterval.value = null
  }
}

async function updateChartRealtime() {
  if (chartLoading.value) return
  
  // Debounce reduced to 300ms for faster updates in production
  const now = Date.now()
  if (now - lastUpdateTime.value < 300) {
    return
  }
  
  if (isUpdating.value) {
    return
  }
  
  isUpdating.value = true
  
  try {
    const currentMonitorId = route.params.id
    
    // Fetch latest data
    const response = await monitorStore.api.monitorChecks.getAll({
      monitor_id: currentMonitorId,
      per_page: getPeriodLimit(selectedPeriod.value),
      sort: 'checked_at',
      order: 'desc'
      , _t: Date.now()
    })
    
    // Validate we're still on the same monitor
    if (route.params.id != currentMonitorId) {
      console.warn('⚠️ Route changed during chart update, aborting')
      isUpdating.value = false
      return
    }
    
    if (response.data.success) {
      let checks = response.data.data.data || response.data.data || []
      
      // Filter checks to ensure they belong to current monitor
      checks = checks.filter(check => check.monitor_id == currentMonitorId)
      
      if (checks.length === 0) {
        isUpdating.value = false
        return
      }
      
      // Convert to chart data points
      const dataPoints = checks.reverse().map(check => ({
        time: new Date(check.checked_at).getTime(),
        value: (check.latency_ms ?? check.latency ?? check.response_time ?? check.response_time_ms ?? 0) || 0
      }))
      
      // Redraw chart with new data
      drawChart(dataPoints)
      
      lastUpdateTime.value = now
    }
  } catch (err) {
    console.error('❌ Auto-refresh failed:', err)
  } finally {
    isUpdating.value = false
  }
}

async function fetchChartData() {
  if (!monitor.value) {
    console.warn('⚠️ Monitor not loaded yet, skipping chart fetch')
    return
  }
  
  chartLoading.value = true
  await nextTick()
  
  // Stop auto-refresh during manual fetch
  const wasAutoRefreshRunning = !!chartRefreshInterval.value
  if (wasAutoRefreshRunning) {
    stopChartAutoRefresh()
  }
  
  // Destroy existing chart
  destroyChart()
  
    try {
    const response = await monitorStore.api.monitorChecks.getAll({
      monitor_id: route.params.id,
      per_page: getPeriodLimit(selectedPeriod.value),
      sort: 'checked_at',
      order: 'desc'
      , _t: Date.now()
    })
    
        if (response.data.success) {
      let checks = response.data.data.data || response.data.data || []
      
            if (checks.length === 0) {
        console.warn('⚠️ No data available for chart')
      } else {
        // Convert to chart data points
        const dataPoints = checks.reverse().map(check => ({
          time: new Date(check.checked_at).getTime(),
          value: (check.latency_ms ?? check.latency ?? check.response_time ?? check.response_time_ms ?? 0) || 0
        }))
        
                // Draw chart
        drawChart(dataPoints)
      }
    }
  } catch (err) {
    console.error('❌ Error fetching chart data:', err)
  } finally {
    chartLoading.value = false
    
    // Restart auto-refresh after chart is created
    await new Promise(resolve => setTimeout(resolve, 500))
    if (monitor.value) {
      startChartAutoRefresh()
          }
  }
}

function drawChart(dataPoints) {
  if (!dataPoints || dataPoints.length === 0) {
    chartData.value = null
    return
  }
  
  // Prepare labels and data for Chart.js
  const labels = dataPoints.map(d => formatChartTime(d.time, selectedPeriod.value))
  const values = dataPoints.map(d => d.value)
  
  // Configure chart data
  chartData.value = {
    labels: labels,
    datasets: [
      {
        label: 'Response Time (ms)',
        data: values,
        borderColor: '#00b894',
        backgroundColor: 'rgba(0, 184, 148, 0.1)',
        borderWidth: 2,
        fill: true,
        tension: 0.4,
        pointRadius: 0,
        pointHoverRadius: 4,
        pointHoverBackgroundColor: '#00b894',
        pointHoverBorderColor: '#fff',
        pointHoverBorderWidth: 2
      }
    ]
  }
  
  // Configure chart options
  chartOptions.value = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        display: false
      },
      tooltip: {
        mode: 'index',
        intersect: false,
        backgroundColor: 'rgba(45, 52, 54, 0.9)',
        titleColor: '#fff',
        bodyColor: '#00b894',
        borderColor: '#00b894',
        borderWidth: 1,
        padding: 10,
        displayColors: false,
        callbacks: {
          label: function(context) {
            return context.parsed.y + ' ms'
          }
        }
      }
    },
    scales: {
      x: {
        grid: {
          color: 'rgba(255, 255, 255, 0.05)',
          drawBorder: false
        },
        ticks: {
          color: '#b2bec3',
          maxTicksLimit: 8,
          maxRotation: 0,
          font: {
            size: 11
          }
        }
      },
      y: {
        grid: {
          color: 'rgba(255, 255, 255, 0.05)',
          drawBorder: false
        },
        ticks: {
          color: '#b2bec3',
          callback: function(value) {
            return value + ' ms'
          },
          font: {
            size: 11
          }
        },
        beginAtZero: true
      }
    },
    interaction: {
      mode: 'nearest',
      axis: 'x',
      intersect: false
    }
  }
}

function getPeriodLimit(period) {
  switch (period) {
    case '1h': return 60
    case '24h': return 50
    case '7d': return 84
    case '30d': return 200
    default: return 50
  }
}

function formatChartTime(timestamp, period) {
  const date = new Date(timestamp)
  
  switch (period) {
    case '1h':
      return date.toLocaleTimeString('en-US', { 
        hour: '2-digit', 
        minute: '2-digit',
        second: '2-digit',
        hour12: false 
      })
    case '24h':
      return date.toLocaleTimeString('en-US', { 
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false 
      })
    case '7d':
      return date.toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric' 
      })
    case '30d':
      return date.toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric' 
      })
    default:
      return date.toLocaleTimeString('en-US', { 
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false 
      })
  }
}

function destroyChart() {
  chartData.value = null
  chartOptions.value = null
}

async function selectPeriod(period) {
  if (selectedPeriod.value === period) return
  
    // Stop auto-refresh and reset update state
  stopChartAutoRefresh()
  isUpdating.value = false
  lastUpdateTime.value = 0
  
  // Update period
  selectedPeriod.value = period
  
  // Wait for state to settle
  await nextTick()
  await new Promise(resolve => setTimeout(resolve, 100))
  
  // Fetch new data
  await fetchChartData()
  
  // Wait before restarting auto-refresh
  await new Promise(resolve => setTimeout(resolve, 500))
  
  // Restart auto-refresh
  if (monitor.value && chartData.value) {
    startChartAutoRefresh()
      }
}

async function refreshChart() {
  await fetchChartData()
}

async function toggleMonitor() {
  if (!monitor.value) return
  
  try {
    if (isPaused.value) {
      // Resume monitor
      const result = await monitorStore.resumeMonitor(monitor.value.id)
      if (result.success) {
                showNotif('Monitor resumed successfully', 'success')
      } else {
        console.error('❌ Failed to resume monitor:', result.message)
        showNotif('Failed to resume monitor', 'error')
      }
    } else {
      // Pause monitor for 60 minutes
      const result = await monitorStore.pauseMonitor(monitor.value.id, 60)
      if (result.success) {
                showNotif('Monitor paused for 60 minutes', 'success')
      } else {
        console.error('❌ Failed to pause monitor:', result.message)
        showNotif('Failed to pause monitor', 'error')
      }
    }

    await fetchMonitorData()
  } catch (err) {
    console.error('Error toggling monitor:', err)
    showNotif('Error toggling monitor status', 'error')
  }
}

async function deleteMonitor() {
  if (!monitor.value) return
  
  showConfirm(`Are you sure you want to delete "${monitor.value.name}"?`, async () => {
    try {
            const result = await monitorStore.deleteMonitor(monitor.value.id)
      
      if (result.success) {
                showNotif('Monitor deleted successfully', 'success')
        setTimeout(() => {
          router.push('/monitors')
        }, 1500)
      }
    } catch (err) {
      console.error('❌ Error deleting monitor:', err)
      showNotif('Error deleting monitor', 'error')
    }
  })
}

function visitMonitor() {
  if (!monitor.value) return
  
  let url = monitor.value.target
  
  if (monitor.value.type === 'http' || monitor.value.type === 'https') {
    if (!url.startsWith('http://') && !url.startsWith('https://')) {
      url = `${monitor.value.type}://${url}`
    }
  } else if (monitor.value.type === 'tcp') {
    const [host, port] = url.split(':')
    if (['80', '443', '8080', '3000', '8000'].includes(port)) {
      url = port === '443' ? `https://${host}` : `http://${host}:${port}`
    } else {
      showNotif('TCP service cannot be opened in browser', 'warning')
      return
    }
  }
  
  window.open(url, '_blank')
}

function isVisitable(monitor) {
  if (!monitor) return false
  return ['http', 'https'].includes(monitor.type) || 
         (monitor.type === 'tcp' && ['80', '443', '8080', '3000', '8000'].includes(monitor.target.split(':')[1]))
}

function getVisitTooltip(monitor) {
  if (!isVisitable(monitor)) {
    return 'This service cannot be visited in browser'
  }
  return `Visit ${monitor.target}`
}

async function clearData() {
  if (!monitor.value) return
  
  showConfirm('Are you sure you want to clear status history for this monitor?', async () => {
    try {
            // Clear local status history data
      allStatusHistory.value = []
      totalItems.value = 0
      currentPage.value = 1
      
            // Show success message
      showNotif('Status history cleared successfully', 'success')
    } catch (err) {
      console.error('❌ Error clearing status history:', err)
      showNotif('Error clearing status history', 'error')
    }
  })
}

function formatDate(dateString) {
  return new Date(dateString).toLocaleString()
}

function formatDateTime(dateString) {
  return new Date(dateString).toLocaleString('en-US', {
    year: 'numeric',
    month: '2-digit', 
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  })
}

function formatDateFull(dateString) {
  if (!dateString) return ''
  return new Date(dateString).toLocaleString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

function formatDateRelative(dateString) {
  if (!dateString) return ''
  const date = new Date(dateString)
  const now = new Date()
  const diffMs = now - date
  const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24))
  
  if (diffDays === 0) return 'Added today'
  if (diffDays === 1) return 'Added yesterday'
  if (diffDays < 7) return `Added ${diffDays} days ago`
  if (diffDays < 30) return `Added ${Math.floor(diffDays / 7)} weeks ago`
  if (diffDays < 365) return `Added ${Math.floor(diffDays / 30)} months ago`
  return `Added ${Math.floor(diffDays / 365)} years ago`
}
</script>

<style scoped>
.monitor-detail {
  min-height: 100vh;
  background: linear-gradient(135deg, #f8f9ff 0%, #f0f3ff 100%);
  position: relative;
  padding: 0;
  margin: 0;
}

.monitor-detail::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: 
    radial-gradient(circle at 20% 80%, rgba(102, 126, 234, 0.08) 0%, transparent 50%),
    radial-gradient(circle at 80% 20%, rgba(118, 75, 162, 0.06) 0%, transparent 50%),
    radial-gradient(circle at 40% 40%, rgba(0, 184, 148, 0.04) 0%, transparent 50%);
  pointer-events: none;
}

.monitor-detail-content {
  padding: 20px 16px;
  position: relative;
  z-index: 1;
}

.detail-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  /* gap: 16px; */
  margin-bottom: 24px;
  padding: 24px 24px;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0.05) 100%);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.header-content {
  flex: 1;
}

.monitor-title h1 {
  margin: 0 0 10px 0;
  font-size: 2rem;
  font-weight: 600;
  color: #080808;
  display: flex;
  align-items: center;
  gap: 12px;
}

/* Constrain monitor title so it doesn't stretch the header */
.monitor-title {
  flex: 1 1 auto;
  /* keep title proportional — narrower on wide screens so actions stay visible */
  max-width: 560px; /* reduced from 720px */
  min-width: 0; /* allow truncation */
  padding-right: 8px;
  box-sizing: border-box;
}

.monitor-title h1 {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  /* Responsive, proportional sizing */
  font-size: clamp(1.2rem, 1.6vw + 0.6rem, 1.6rem);
}

.header-actions {
  flex: 0 0 auto; /* keep actions from stretching */
}

@media (max-width: 768px) {
  .monitor-title {
    max-width: 100%;
    padding-right: 0;
  }
  .monitor-title h1 {
    white-space: normal;
    font-size: 1.2rem;
  }
}

.live-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 12px;
  background: linear-gradient(135deg, #00b894 0%, #00d2a0 100%);
  color: white;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  box-shadow: 0 2px 8px rgba(0, 184, 148, 0.3);
  animation: pulse-badge 2s ease-in-out infinite;
}

.live-dot {
  width: 8px;
  height: 8px;
  background: white;
  border-radius: 50%;
  animation: pulse-dot 1.5s ease-in-out infinite;
}

@keyframes pulse-badge {
  0%, 100% {
    box-shadow: 0 2px 8px rgba(0, 184, 148, 0.3);
  }
  50% {
    box-shadow: 0 2px 16px rgba(0, 184, 148, 0.5);
  }
}

@keyframes pulse-dot {
  0%, 100% {
    opacity: 1;
    transform: scale(1);
  }
  50% {
    opacity: 0.6;
    transform: scale(0.8);
  }
}


.monitor-url {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 15px;
}

.url-text {
  color: #2c3fce;
  font-family: monospace;
  font-size: 1.1rem;
  word-break: break-all;
  max-width: 300px;
}

.visit-btn {
  background: #00b894;
  color: white;
  border: none;
  padding: 5px 10px;
  border-radius: 4px;
  cursor: pointer;
  font-size: 0.9rem;
  transition: background 0.2s;
}

.visit-btn:hover:not(:disabled) {
  background: #00a085;
}

.visit-btn:disabled {
  background: #636e72;
  cursor: not-allowed;
}

.monitor-meta {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  align-items: center;
}

.monitor-type-badge {
  background: #0984e3;
  color: white;
  padding: 4px 12px;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.5px;
  text-transform: uppercase;
}

.monitor-interval, .monitor-group {
  color: #b2bec3;
  font-size: 0.85rem;
  background: rgba(255, 255, 255, 0.1);
  padding: 3px 8px;
  border-radius: 4px;
  border: 1px solid rgba(255, 255, 255, 0.15);
}

.monitor-creator {
  color: #74b9ff;
  font-size: 0.85rem;
  background: rgba(116, 185, 255, 0.15);
  padding: 3px 8px;
  border-radius: 4px;
  border: 1px solid rgba(116, 185, 255, 0.3);
  font-weight: 500;
}

.monitor-created {
  color: #a29bfe;
  font-size: 0.85rem;
  background: rgba(162, 155, 254, 0.15);
  padding: 3px 8px;
  border-radius: 4px;
  border: 1px solid rgba(162, 155, 254, 0.3);
  cursor: help;
}

.monitor-status-section {
  /* Make status area horizontal and constrained so the status badge isn't stretched full-width */
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 12px;
  text-align: left;
}

.current-status {
  font-size: 1rem;
  font-weight: 700;
  padding: 8px 14px;
  border-radius: 8px;
  margin-bottom: 8px;
  letter-spacing: 0.5px;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
  border: 1px solid rgba(255, 255, 255, 0.2);
  min-width: 0;
  text-align: center;
  /* Constrain width so the pill appears proportional and doesn't span the whole header */
  max-width: 420px;
  width: auto;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.current-status.status-up {
  background: #00b894;
  color: white;
}

/* Narrow padding for status badges so they don't appear overly wide */
.current-status.status-up,
.current-status.status-down,
.current-status.status-invalid,
.current-status.status-validating,
.current-status.status-unknown {
  padding: 6px 10px;
  min-width: 0;
  width: auto;
}

/* Reduce padding for UP status so badge isn't overly wide */
.current-status.status-up {
  padding: 6px 10px; /* narrower horizontal padding */
  min-width: 0;       /* allow natural width */
  width: auto;
}

.current-status.status-down {
  background: #e17055;
  color: white;
}

.current-status.status-invalid {
  background: #fdcb6e;
  color: #2d3436;
}

.current-status.status-validating {
  background: #74b9ff;
  color: white;
}

.current-status.status-unknown {
  background: #636e72;
  color: white;
}

.status-info {
  color: #b2bec3;
  font-size: 0.9rem;
}

.header-actions {
  display: flex;
  gap: 8px;
  align-items: flex-start;
  flex-wrap: wrap;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 16px;
  margin-bottom: 24px;
}

.stat-card,
.stat-card:active,
.stat-card:focus {
  -webkit-tap-highlight-color: transparent;
  touch-action: manipulation;
  background-color: inherit !important;
  color: inherit !important;
}

.stat-card {
  position: relative;
  overflow: hidden;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.06);
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  min-height: 120px;
}

/* Prevent mobile tap highlight and keep background stable on active/click */
.stat-card,
.stat-card:active,
.stat-card:focus {
  -webkit-tap-highlight-color: transparent;
  touch-action: manipulation;
  background-color: inherit !important;
  color: inherit !important;
}

.stat-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 14px 36px rgba(0, 0, 0, 0.12);
  background: #000000; /* invert to black on hover */
  color: #ffffff;
}

.stat-header {
  font-size: 0.85rem;
  color: #7b8794;
  margin-bottom: 4px;
  font-weight: 600;
  letter-spacing: 0.5px;
  text-transform: uppercase;
}

.stat-subheader {
  font-size: 1.48rem;
  color: #7b8794;
  margin-bottom: 12px;
  font-weight: 700;
}

.stat-value {
  font-size: 2rem;
  font-weight: 700;
  color: #111827; /* dark text for values */
  margin: 8px 0;
  line-height: 1.2;
  transition: all 0.3s ease;
}

.stat-value.uptime-value {
  color: #00b894;
}
/* Ensure hover/active/focus do not force child text to white */
.stat-card:hover .stat-value,
.stat-card:active .stat-value,
.stat-card:focus .stat-value,
.stat-card:hover .stat-subheader,
.stat-card:active .stat-subheader,
.stat-card:focus .stat-subheader,
.stat-card:hover .stat-header,
.stat-card:active .stat-header,
.stat-card:focus .stat-header {
  color: inherit !important;
}

.stat-value.response-value {
  color: #74b9ff;
}

.stat-value.cert-value {
  color: #00b894;
}

.stat-loading {
  animation: pulse 1.5s infinite;
  margin-top: 8px;
  font-size: 0.8rem;
}

.stat-trend {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  margin-top: 8px;
  padding-top: 8px;
  font-size: 0.8rem;
  border-top: 1px solid rgba(0, 0, 0, 0.04);
}

.trend-icon {
  font-size: 1rem;
  animation: bounce 2s infinite;
}

.trend-icon.up {
  color: #2fb07b;
}

.trend-icon.down {
  color: #d67b63;
}

.trend-text {
  color: #94a3b8;
  font-weight: 500;
}

/* Loading Skeleton */
.skeleton-card {
  background: #2d3436;
  border-radius: 12px;
  padding: 20px;
  animation: fadeIn 0.5s ease-out;
}

.skeleton-line {
  background: linear-gradient(90deg, #636e72 25%, #74828a 50%, #636e72 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 4px;
  margin-bottom: 10px;
}

.skeleton-line.short { height: 16px; width: 60%; }
.skeleton-line.mini { height: 12px; width: 40%; }
.skeleton-line.medium { height: 20px; width: 80%; }
.skeleton-line.long { height: 24px; width: 90%; }

.chart-section {
  background: linear-gradient(135deg, rgba(45, 52, 54, 0.9) 0%, rgba(99, 110, 114, 0.8) 100%);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 20px;
  margin-bottom: 24px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
  transition: all 0.3s ease;
}

.chart-controls {
  display: flex;
  align-items: center;
  gap: 15px;
}

.chart-container {
  position: relative;
  height: 320px;
  background: rgba(26, 26, 26, 0.9);
  border-radius: 12px;
  padding: 20px;
  transition: all 0.3s ease;
}

.chart-container.loading {
  pointer-events: none;
}

.chart-loading {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 15px;
  z-index: 10;
  color: #b2bec3;
}

.chart-spinner {
  width: 30px;
  height: 30px;
  border: 3px solid #636e72;
  border-top: 3px solid #00b894;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

.no-chart-data {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
  color: #b2bec3;
  font-size: 1rem;
}

.chart-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.chart-header h2 {
  margin: 0;
  color: #ffffff;
  font-size: 1.5rem;
}

.chart-period {
  display: flex;
  gap: 8px;
}

.period-btn {
  background: transparent;
  color: #b2bec3;
  border: 1px solid #636e72;
  padding: 8px 16px;
  border-radius: 8px;
  cursor: pointer;
  font-size: 0.9rem;
  font-weight: 500;
  transition: all 0.2s;
}

.period-btn:hover {
  border-color: #00b894;
  color: #00b894;
  background: rgba(0, 184, 148, 0.05);
}

.period-btn.active {
  background: #00b894;
  color: white;
  border-color: #00b894;
  box-shadow: 0 2px 8px rgba(0, 184, 148, 0.3);
}

.refresh-chart-btn {
  background: transparent;
  border: 1px solid #636e72;
  color: #b2bec3;
  padding: 8px 12px;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
}

.refresh-chart-btn:hover:not(:disabled) {
  border-color: #00b894;
  color: #00b894;
  background: rgba(0, 184, 148, 0.05);
}

.refresh-chart-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.refresh-icon {
  display: inline-block;
  transition: transform 0.3s ease;
}

.refresh-icon.spinning {
  animation: spin 1s linear infinite;
}

.btn-spinner {
  width: 12px;
  height: 12px;
  border: 2px solid transparent;
  border-top: 2px solid currentColor;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin-right: 5px;
}

.status-history {
  background: linear-gradient(135deg, rgba(45, 52, 54, 0.9) 0%, rgba(99, 110, 114, 0.8) 100%);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 20px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
}

.history-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.history-title-section {
  display: flex;
  align-items: center;
  gap: 15px;
}

.history-header h2 {
  margin: 0;
  color: #ffffff;
  font-size: 1.5rem;
}

.realtime-indicator {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 8px;
  background: rgba(99, 110, 114, 0.3);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  color: #636e72;
  font-size: 0.8rem;
  font-weight: 500;
  transition: all 0.3s ease;
}

.realtime-indicator.active {
  background: rgba(0, 184, 148, 0.2);
  border-color: rgba(0, 184, 148, 0.3);
  color: #00b894;
}

.realtime-indicator.active::before {
  content: '';
  width: 6px;
  height: 6px;
  background: #00b894;
  border-radius: 50%;
  animation: pulse 2s infinite;
}

.history-table-container {
  overflow-x: auto;
}

.history-table {
  width: 100%;
  border-collapse: collapse;
  background: rgba(26, 26, 26, 0.8);
  border-radius: 8px;
  overflow: hidden;
}

.history-table th {
  background: #636e72;
  color: white;
  padding: 12px;
  text-align: left;
  font-weight: 600;
}

.history-table td {
  padding: 12px;
  border-bottom: 1px solid #2d3436;
}

.history-row {
  transition: all 0.2s ease;
}

.history-row:hover {
  background: rgba(45, 52, 54, 0.8);
}

.status-badge-small {
  padding: 2px 6px;
  border-radius: 3px;
  font-size: 0.7rem;
  font-weight: bold;
  text-transform: uppercase;
  transition: all 0.2s ease;
}

.status-badge-small:hover {
  transform: scale(1.05);
}

.status-badge-small.status-up {
  background: #00b894;
  color: white;
}

.status-badge-small.status-down {
  background: #e17055;
  color: white;
}

.status-badge-small.status-invalid {
  background: #fdcb6e;
  color: #2d3436;
}

.status-badge-small.status-validating {
  background: #74b9ff;
  color: white;
}

.status-badge-small.status-unknown {
  background: #636e72;
  color: white;
}

.datetime-cell {
  color: #b2bec3;
  font-family: monospace;
  font-size: 0.9rem;
}

.message-cell {
  font-size: 0.9rem;
}

.success-message {
  color: #00b894;
}

.error-message {
  color: #e17055;
}

.neutral-message {
  color: #b2bec3;
}

.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 15px;
  margin-top: 20px;
  padding-top: 20px;
  border-top: 1px solid #636e72;
}

.page-btn {
  background: #0984e3;
  color: white;
  border: none;
  padding: 8px 16px;
  border-radius: 4px;
  cursor: pointer;
  transition: background 0.2s;
}

.page-btn:hover:not(:disabled) {
  background: #0770c4;
}

.page-btn:disabled {
  background: #636e72;
  cursor: not-allowed;
}

.page-info {
  color: #b2bec3;
  font-size: 0.9rem;
}

.btn {
  padding: 8px 16px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  transition: all 0.3s ease;
  font-size: 0.9rem;
  min-width: 100px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
  border: 1px solid transparent;
}

.btn-primary {
  background: #0984e3;
  color: white;
}

.btn-primary:hover {
  background: #0770c4;
}

.btn-success {
  background: #00b894;
  color: white;
}

.btn-success:hover {
  background: #00a085;
}

.btn-warning {
  background: #fdcb6e;
  color: #2d3436;
}

.btn-warning:hover {
  background: #fcb942;
}

.btn-danger {
  background: #e17055;
  color: white;
}

.btn-danger:hover {
  background: #d85a3e;
}

.btn-info {
  background: #0984e3;
  color: white;
}

.btn-info:hover {
  background: #0770cd;
}

.btn-outline {
  background: transparent;
  color: #b2bec3;
  border: 1px solid #636e72;
}

.btn-outline:hover {
  background: #636e72;
  color: white;
}

.btn-sm {
  padding: 5px 10px;
  font-size: 0.8rem;
}

.loading-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.8);
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  gap: 20px;
  z-index: 1000;
}

.loading-spinner {
  width: 40px;
  height: 40px;
  border: 4px solid #636e72;
  border-top: 4px solid #00b894;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

/* Notification Popup */
.notification-popup {
  position: fixed;
  bottom: 20px;
  right: 20px;
  z-index: 9999;
  min-width: 350px;
  max-width: 500px;
  border-radius: 12px;
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
  backdrop-filter: blur(10px);
}

.notification-content {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px 20px;
  border-radius: 12px;
}

.notification-icon {
  font-size: 1.5rem;
  flex-shrink: 0;
}

.notification-message {
  flex: 1;
  font-size: 0.95rem;
  font-weight: 500;
  line-height: 1.4;
}

.notification-close {
  background: none;
  border: none;
  font-size: 1.5rem;
  cursor: pointer;
  color: inherit;
  opacity: 0.7;
  transition: opacity 0.2s;
  padding: 0;
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.notification-close:hover {
  opacity: 1;
}

.notification-success .notification-content {
  background: linear-gradient(135deg, #00b894 0%, #00d2a0 100%);
  color: white;
}

.notification-error .notification-content {
  background: linear-gradient(135deg, #e17055 0%, #d85a3e 100%);
  color: white;
}

.notification-warning .notification-content {
  background: linear-gradient(135deg, #fdcb6e 0%, #fcb942 100%);
  color: #2d3436;
}

.notification-info .notification-content {
  background: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%);
  color: white;
}

/* Notification Animation */
.notification-slide-enter-active,
.notification-slide-leave-active {
  transition: all 0.4s ease;
}

.notification-slide-enter-from {
  transform: translateY(20px);
  opacity: 0;
}

.notification-slide-leave-to {
  transform: translateY(20px);
  opacity: 0;
}

@media (max-width: 768px) {
  .notification-popup {
    top: 50%;
    left: 50%;
    right: auto;
    bottom: auto;
    transform: translate(-50%, -50%);
    min-width: 90%;
    max-width: 90%;
  }
  
  .notification-slide-enter-from {
    transform: translate(-50%, -50%) scale(0.9);
    opacity: 0;
  }
  
  .notification-slide-leave-to {
    transform: translate(-50%, -50%) scale(0.9);
    opacity: 0;
  }
}

/* Confirmation Dialog */
.confirmation-dialog {
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  z-index: 10000;
  min-width: 400px;
  max-width: 500px;
  border-radius: 16px;
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
  backdrop-filter: blur(10px);
}

.confirmation-content {
  background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
  padding: 32px 28px;
  border-radius: 16px;
  text-align: center;
}

.confirmation-icon {
  font-size: 3rem;
  margin-bottom: 16px;
}

.confirmation-message {
  color: #2d3436;
  font-size: 1.1rem;
  font-weight: 600;
  line-height: 1.5;
  margin-bottom: 24px;
}

.confirmation-actions {
  display: flex;
  gap: 12px;
  justify-content: center;
}

.confirm-btn {
  padding: 12px 24px;
  border: none;
  border-radius: 8px;
  font-size: 0.95rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  min-width: 120px;
}

.cancel-btn {
  background: #dfe6e9;
  color: #2d3436;
}

.cancel-btn:hover {
  background: #b2bec3;
}

.yes-btn {
  background: linear-gradient(135deg, #e17055 0%, #d85a3e 100%);
  color: white;
  box-shadow: 0 4px 12px rgba(225, 112, 85, 0.3);
}

.yes-btn:hover {
  box-shadow: 0 6px 16px rgba(225, 112, 85, 0.4);
  transform: translateY(-1px);
}

@media (max-width: 768px) {
  .confirmation-dialog {
    min-width: 90%;
    max-width: 90%;
  }
  
  .confirmation-actions {
    flex-direction: column;
  }
  
  .confirm-btn {
    width: 100%;
  }
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes slideInUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes shimmer {
  0% {
    background-position: -200% 0;
  }
  100% {
    background-position: 200% 0;
  }
}

@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.5;
  }
}

@keyframes bounce {
  0%, 20%, 53%, 80%, 100% {
    transform: translate3d(0, 0, 0);
  }
  40%, 43% {
    transform: translate3d(0, -3px, 0);
  }
  70% {
    transform: translate3d(0, -2px, 0);
  }
  90% {
    transform: translate3d(0, -1px, 0);
  }
}

.error-state {
  text-align: center;
  padding: 60px 20px;
  background: #2d3436;
  border-radius: 12px;
}

.error-state h3 {
  color: #e17055;
  margin-bottom: 15px;
}

.error-state p {
  color: #b2bec3;
  margin-bottom: 20px;
}

/* Responsive */
@media (max-width: 768px) {
  .monitor-detail-content {
    padding: 16px 12px;
  }
  
  .detail-header {
    flex-direction: column;
    gap: 20px;
    padding: 16px 20px;
  }
  
  .monitor-title h1 {
    font-size: 1.5rem;
  }
  
  .monitor-url {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }
  
  .url-text {
    font-size: 0.9rem;
    max-width: 100%;
  }
  
  .visit-btn {
    align-self: flex-start;
    font-size: 0.8rem;
    padding: 4px 8px;
  }
  
  .monitor-status-section {
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 10px;
    text-align: left;
    width: 100%;
  }
  
  .current-status {
    font-size: 1rem;
    padding: 6px 10px;
    min-width: auto;
    max-width: 220px;
    width: auto;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
  }
  
  .chart-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 15px;
  }
  
  .header-actions {
    width: 100%;
    flex-wrap: wrap;
    gap: 8px;
  }
  
  .header-actions .btn {
    flex: 1;
    min-width: 120px;
    font-size: 0.85rem;
    padding: 6px 12px;
  }
}

@media (max-width: 480px) {
  .monitor-detail-content {
    padding: 12px 8px;
  }
  
  .detail-header {
    padding: 12px 16px;
  }
  
  .monitor-title h1 {
    font-size: 1.25rem;
  }
  
  .stats-grid {
    grid-template-columns: 1fr;
    gap: 10px;
  }
  
  .header-actions .btn {
    min-width: 100px;
    font-size: 0.8rem;
    padding: 5px 8px;
  }
  
  .history-table {
    font-size: 0.75rem;
  }
  
  .current-status {
    font-size: 1.1rem;
    padding: 6px 12px;
  }
}
</style>

<style scoped>
/* Mobile readability tweaks: increase spacing, slightly larger values, responsive chart height */
@media (max-width: 768px) {
  .detail-header {
    flex-direction: column;
    align-items: stretch;
    gap: 12px;
    padding: 12px;
  }

  .monitor-title h1 {
    font-size: 1.4rem;
    line-height: 1.2;
  }

  .chart-container {
    height: 320px;
    padding: 12px;
  }

  .chart-container canvas {
    max-height: 320px;
    height: 100% !important;
  }

  .stat-card {
    min-height: auto;
    padding: 14px;
  }

  .stat-header {
    font-size: 0.9rem;
    color: #c7d0d4;
  }

  .stat-value {
    font-size: 1.6rem;
  }

  .chart-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }

  .chart-period {
    flex-wrap: wrap;
    gap: 8px;
  }

  .period-btn {
    padding: 6px 8px;
    font-size: 0.85rem;
  }

  .monitor-url .url-text {
    max-width: 100%;
    font-size: 1rem;
    word-break: break-word;
  }

  .current-status {
    text-align: center;
    align-self: center;
    min-width: auto;
  }

  .history-table th, .history-table td {
    font-size: 0.95rem;
  }
}

@media (max-width: 480px) {
  .detail-header { padding: 10px; }
  .monitor-title h1 { font-size: 1.1rem; }
  .chart-container { height: 280px; }
  .chart-container canvas { height: 100% !important; }
  .stat-card { padding: 12px; }
  .stat-subheader { font-size: 1.2rem; }
  .stat-value { font-size: 1.4rem; }
  .period-btn { padding: 6px 8px; font-size: 0.8rem; }
  .history-table th, .history-table td { font-size: 0.85rem; padding: 8px; }
  .chart-container { padding: 8px; }
}
</style>

<style scoped>
/* Higher-contrast overrides for better readability on small screens */
.monitor-detail .chart-container {
  background: #0b1113 !important;
  border: 1px solid rgba(255,255,255,0.14) !important;
  box-shadow: 0 12px 36px rgba(0,0,0,0.7) !important;
}
.monitor-detail .chart-container canvas {
  background: #07090a !important;
  border-radius: 6px;
  box-shadow: inset 0 0 0 6px rgba(0,0,0,0.6);
}
.monitor-detail .chart-header h2,
.monitor-detail .stat-header,
.monitor-detail .trend-text,
.monitor-detail .history-table th,
.monitor-detail .history-table td,
.monitor-detail .status-info {
  color: #c7d0d4 !important;
}

/* Monitor title: use black for better legibility as requested */
.monitor-detail .monitor-title h1 {
  color: #000000 !important;
}
.monitor-detail .period-btn {
  color: #ffffff !important;
  background: rgba(255,255,255,0.03) !important;
  border-color: rgba(255,255,255,0.12) !important;
}
.monitor-detail .period-btn.active {
  background: #00b894 !important;
  color: #012214 !important;
}

@media (max-width: 768px) {
  .monitor-detail .chart-container { background: #071014 !important; }
  .monitor-detail .chart-container canvas { box-shadow: none !important; }
  .monitor-detail .stat-card { background: #ffffff !important; color: #111827 !important; border-left-color: #00b894 !important; }
  .monitor-detail .history-table { background: #0b0f11 !important; }
  .monitor-detail .history-table th { background: #1b1f22 !important; color: #eef6f7 !important; }
  .monitor-detail .history-table td { color: #ddeff0 !important; }

  /* Ensure detail header doesn't collide with fixed navbar on small screens */
  .monitor-detail .monitor-detail-content {
    padding-top: 80px !important;
  }

  /* Stack header content and tighten spacing on mobile */
  .monitor-detail .detail-header {
    padding: 14px 16px !important;
    flex-direction: column !important;
    align-items: stretch !important;
    gap: 10px !important;
  }
}
</style>

<style scoped>
@media (max-width: 480px) {
  /* Compact 3-column stats layout like provided screenshot */
  .monitor-detail .stats-grid {
    grid-template-columns: repeat(3, 1fr) !important;
    gap: 8px !important;
    margin-bottom: 12px !important;
  }

  .monitor-detail .stat-card {
    padding: 10px !important;
    min-height: 86px !important;
    border-radius: 10px !important;
    box-shadow: 0 6px 18px rgba(0,0,0,0.06) !important;
    display: flex !important;
    flex-direction: column !important;
    justify-content: center !important;
    align-items: center !important;
    text-align: center !important;
  }

  .monitor-detail .stat-header {
    font-size: 0.72rem !important;
    margin-bottom: 6px !important;
    text-transform: uppercase !important;
    letter-spacing: 0.4px !important;
    /* color intentionally inherited from desktop rules */
  }

  .monitor-detail .stat-subheader {
    font-size: 0.96rem !important;
    margin-bottom: 6px !important;
    /* ensure readable on white stat-cards */
    color: #6b7280 !important;
    font-weight: 600 !important;
    /* color inherited */
  }

  /* Force headers/subheaders inside stat-cards to a readable muted tone */
  .monitor-detail .stat-card .stat-header {
    color: #7b8794 !important;
  }
  .monitor-detail .stat-card .stat-subheader {
    color: #6b7280 !important;
  }

  .monitor-detail .stat-value {
    font-size: 1.2rem !important;
    font-weight: 700 !important;
    margin: 4px 0 !important;
    line-height: 1.1 !important;
    /* color inherited */
  }

  /* Slightly smaller trend area, remove strong border so it's subtle */
  .monitor-detail .stat-trend {
    border-top: 1px solid rgba(0,0,0,0.04) !important;
    margin-top: 6px !important;
    padding-top: 6px !important;
    font-size: 0.7rem !important;
    /* color inherited */
  }

  .monitor-detail .trend-icon {
    font-size: 0.85rem !important;
  }

  /* Make status pill full-width and prominent but not too tall */
  .monitor-detail .current-status {
    display: block !important;
    width: 100% !important;
    padding: 10px 12px !important;
    font-size: 1.02rem !important;
    border-radius: 8px !important;
    box-shadow: none !important;
    margin: 0 0 8px 0 !important;
  }
}
</style>