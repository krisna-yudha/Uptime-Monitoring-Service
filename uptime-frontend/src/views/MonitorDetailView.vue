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
        <router-link :to="`/logs/monitor/${$route.params.id}`" class="btn btn-info">
          📋 View Logs
        </router-link>
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
      <div class="stat-card skeleton-card" v-for="n in 6" :key="n">
        <div class="skeleton-line short"></div>
        <div class="skeleton-line mini"></div>
        <div class="skeleton-line medium"></div>
      </div>
      
      <div class="stat-card">
        <div class="stat-header">Avg. Response</div>
        <div class="stat-subheader">(24-hour)</div>
        <div class="stat-value">{{ avgResponse24h }}</div>
      </div>
      
      <div class="stat-card">
        <div class="stat-header">Uptime</div>
        <div class="stat-subheader">(24-hour)</div>
        <div class="stat-value uptime-value">{{ uptime24h }}%</div>
      </div>
      
      <div class="stat-card">
        <div class="stat-header">Uptime</div>
        <div class="stat-subheader">(30-day)</div>
        <div class="stat-value uptime-value">{{ uptime30d }}%</div>
      </div>
      
      <div class="stat-card">
        <div class="stat-header">Uptime</div>
        <div class="stat-subheader">(1-year)</div>
        <div class="stat-value uptime-value">{{ uptime1y }}%</div>
      </div>
      
      <div class="stat-card">
        <div class="stat-header">Cert Exp.</div>
        <div class="stat-subheader">(SSL)</div>
        <div class="stat-value cert-value">{{ certExpiry }}</div>
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
        <canvas ref="responseChart" width="800" height="300" :style="{ opacity: chartLoading ? 0.3 : 1 }"></canvas>
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
  </div>
</template>
<script setup>
import { ref, onMounted, onUnmounted, computed, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useMonitorStore } from '../stores/monitors'

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
const responseChart = ref(null)
const chartInstance = ref(null)
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
      key: 'avg_response',
      header: 'Avg. Response',
      subheader: '(24-hour)',
      value: '150.50 ms',
      valueClass: 'response-value',
      loading: false,
      trend: { direction: 'up', icon: '↗', text: 'Improving' }
    },
    {
      key: 'uptime_24h',
      header: 'Uptime',
      subheader: '(24-hour)',
      value: '100%',
      valueClass: 'uptime-value',
      loading: false,
      trend: { direction: 'up', icon: '✓', text: 'Excellent' }
    },
    {
      key: 'uptime_30d',
      header: 'Uptime',
      subheader: '(30-day)',
      value: '99.8%',
      valueClass: 'uptime-value',
      loading: false,
      trend: { direction: 'up', icon: '↗', text: 'Good' }
    },
    {
      key: 'uptime_1y',
      header: 'Uptime',
      subheader: '(1-year)',
      value: '99.5%',
      valueClass: 'uptime-value',
      loading: false,
      trend: { direction: 'down', icon: '↘', text: 'Stable' }
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
  console.log('📱 Component mounted, loading monitor data...')
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
        console.log('Helper: monitor-checks response for', id || route.params.id, response.data)
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
        console.log('Helper: forced UI refresh complete')
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
watch(() => route.params.id, fetchMonitorData)

watch(() => monitor.value, async (newVal) => {
  if (newVal && !chartLoading.value && responseChart.value) {
    console.log('🔄 Monitor data changed, refreshing chart...')
    await nextTick()
    await fetchChartData()
  }
}, { deep: false })

// Helper functions for SSL certificate
function getCertExpiryDisplay() {
  console.log('🔍 getCertExpiryDisplay called for:', monitor.value?.name)
  
  if (!monitor.value) {
    console.log('  → No monitor data')
    return 'N/A'
  }
  
  console.log('  → Monitor type:', monitor.value.type)
  console.log('  → Is HTTPS?:', monitor.value.type === 'https')
  
  // Only show for HTTPS monitors
  if (monitor.value.type !== 'https') {
    console.log('  → Not HTTPS, returning N/A')
    return 'N/A'
  }
  
  console.log('  → SSL cert expiry raw:', monitor.value.ssl_cert_expiry)
  console.log('  → SSL cert expiry type:', typeof monitor.value.ssl_cert_expiry)
  console.log('  → SSL cert issuer:', monitor.value.ssl_cert_issuer)
  
  // Check if SSL certificate info is available
  if (monitor.value.ssl_cert_expiry) {
    const expiryDate = new Date(monitor.value.ssl_cert_expiry)
    const now = new Date()
    const daysRemaining = Math.floor((expiryDate - now) / (1000 * 60 * 60 * 24))
    
    console.log('  → Expiry date parsed:', expiryDate)
    console.log('  → Current date:', now)
    console.log('  → Days remaining:', daysRemaining)
    
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
  console.log('  → SSL cert expiry is NULL/undefined, returning Not Checked')
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
    // Add cache buster to force fresh data
    const cacheBuster = Date.now()
    const result = await monitorStore.fetchMonitor(route.params.id, { _t: cacheBuster })
    
    if (result.success) {
      // Store monitor and normalize checks shape so UI always finds latency
      monitor.value = result.data
      if (monitor.value.checks && monitor.value.checks.length > 0) {
        monitor.value.checks = monitor.value.checks.map(c => ({
          ...c,
          latency_ms: c.latency_ms ?? c.latency ?? c.response_time ?? c.response_time_ms ?? null
        }))
        console.log('🔎 First check latency after load:', monitor.value.checks[0].latency_ms)
      } else {
        console.log('🔎 No checks present in monitor payload')
      }
      
      console.log('✅ Monitor loaded:', monitor.value.name)
      console.log('⏱️ Monitor checks every:', monitor.value.interval_seconds, 'seconds')
      console.log('📦 Full Monitor Data:', monitor.value)
      console.log('🔐 SSL Certificate Data:', {
        type: monitor.value.type,
        ssl_cert_expiry: monitor.value.ssl_cert_expiry,
        ssl_cert_issuer: monitor.value.ssl_cert_issuer,
        ssl_checked_at: monitor.value.ssl_checked_at
      })
      
      // Pastikan canvas siap
      await nextTick()
      // If the monitor payload already contains recent checks (create response),
      // seed the status history so the UI updates immediately while full history
      // is fetched in the background.
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
        console.log('🧩 Seeded status history from monitor.checks:', allStatusHistory.value.length)
      }

      await Promise.all([
        fetchStatusHistory(),
        fetchChartData()
      ])
      
      // Auto-refresh will be started automatically after chart is created in fetchChartData
      // record last known checked time so live updates can detect new checks
      lastKnownCheckedAt.value = monitor.value.last_checked_at || null
      console.log('✅ Monitor data and chart loaded successfully')
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
    const result = await monitorStore.fetchMonitor(route.params.id, { _t: Date.now() })
    
    if (result.success) {
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
      
      console.log('🔄 Monitor data refreshed:', {
        status: result.data.last_status,
        checked_at: result.data.last_checked_at,
        current_latency: result.data.checks?.[0]?.latency_ms
      })
    }
  } catch (err) {
    // Silent fail for background refresh
    console.error('⚠️ Background refresh failed:', err)
  }
}


async function fetchStatusHistory() {
  try {
    console.log('🔍 Fetching status history for monitor:', route.params.id)
    
    const response = await monitorStore.api.monitorChecks.getAll({
      monitor_id: route.params.id,
      per_page: 100,
      sort: 'checked_at',
      order: 'desc',
      _t: Date.now()
    })
    
    console.log('📦 API Response:', response.data)
    console.log('📦 Raw checks payload:', response.data.data)
    
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

      console.log('📋 Status checks found (unique):', uniqueChecks.length)

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

      console.log(`✅ Loaded ${allStatusHistory.value.length} status checks from API`)
      console.log('📊 First check:', allStatusHistory.value[0])
      
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
  
  try {
    const response = await monitorStore.api.monitorChecks.getAll({
      monitor_id: route.params.id,
      per_page: 10,
      sort: 'checked_at',
      order: 'desc',
      _t: Date.now()
    })
    
    if (response.data.success) {
        const latestChecks = response.data.data.data || response.data.data || []

        console.log('🔁 updateHistoryRealtime fetched', latestChecks.length, 'checks')

        let newAdded = 0
        latestChecks.forEach(check => {
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
        console.log(`✅ updateHistoryRealtime added ${newAdded} new checks (total ${totalItems.value})`)

        // If we were polling for the first check and we got data, stop the polling
        if (pollingFirstCheck.value && totalItems.value > 0) {
          stopFirstCheckPoll()
          console.log('✅ First checks received, stopped aggressive polling')
        }
        // If we added new checks, update lastKnownCheckedAt to the newest check's timestamp
        if (newAdded > 0 && allStatusHistory.value.length > 0) {
          try {
            lastKnownCheckedAt.value = allStatusHistory.value[0].checked_at || lastKnownCheckedAt.value
            console.log('🔔 lastKnownCheckedAt updated to', lastKnownCheckedAt.value)
          } catch (e) {}
          // Trigger chart update when new checks arrive
          try {
            if (chartInstance.value) {
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
  
  console.log(`🔄 Starting chart auto-refresh - Refreshing every ${refreshInterval/1000}s`)
  
  chartRefreshInterval.value = setInterval(() => {
    if (responseChart.value && monitor.value && !isUpdating.value) {
      const timestamp = new Date().toLocaleTimeString()
      console.log(`⏰ [${timestamp}] Triggering chart auto-refresh...`)
      updateChartRealtime()
      // Also refresh monitor data to update last_checked_at and status
      refreshMonitorData()
    } else {
      console.log('⏭️ Skipping auto-refresh - conditions not met:', {
        hasCanvas: !!responseChart.value,
        hasMonitor: !!monitor.value,
        notUpdating: !isUpdating.value
      })
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
  
  let refreshInterval = 2000 // Faster for realtime (2 seconds)
  
  if (monitor.value?.interval_seconds) {
    const monitorInterval = monitor.value.interval_seconds * 1000
    
    if (monitorInterval <= 2000) {
      refreshInterval = 1000 // 1 second for very fast monitors
    } else if (monitorInterval <= 5000) {
      refreshInterval = 2000 // 2 seconds
    } else if (monitorInterval <= 15000) {
      refreshInterval = 5000 // 5 seconds
    } else {
      refreshInterval = 10000 // 10 seconds for slower monitors
    }
  }
  
  console.log(`🔄 Starting history auto-refresh - Refreshing every ${refreshInterval/1000}s`)
  
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
  console.log('🔎 Starting aggressive first-check poll (1s interval, max 20s)')

  firstCheckPollInterval = setInterval(async () => {
    firstCheckPollAttempts++
    try {
      await updateHistoryRealtime()
    } catch (e) {
      // ignore
    }

    if (allStatusHistory.value.length > 0 || firstCheckPollAttempts >= 20) {
      stopFirstCheckPoll()
      console.log('⏹️ First-check poll stopped (attempts:', firstCheckPollAttempts, ')')
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
    console.log('🔁 Starting fallback history poll (5s)')
    fallbackHistoryPollInterval.value = setInterval(async () => {
      try {
        await updateHistoryRealtime()
        // If primary historyAutoRefresh starts, stop the fallback
        if (historyRefreshInterval.value) {
          stopFallbackHistoryPolling()
          console.log('🔁 Primary history auto-refresh detected, stopped fallback poll')
        }
      } catch (e) {
        // ignore errors; fallback should be resilient
      }
    }, 5000)
  }
}

function stopFallbackHistoryPolling() {
  if (fallbackHistoryPollInterval.value) {
    clearInterval(fallbackHistoryPollInterval.value)
    fallbackHistoryPollInterval.value = null
  }
}

async function updateChartRealtime() {
  if (!responseChart.value || chartLoading.value) return
  
  // Debounce (500ms for realtime updates)
  const now = Date.now()
  if (now - lastUpdateTime.value < 500) {
    console.log('⏭️ Skipping update - too soon (debounce)')
    return
  }
  
  if (isUpdating.value) {
    console.log('⏭️ Update already in progress')
    return
  }
  
  isUpdating.value = true
  
  try {
    // Fetch latest data
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
        console.log('⚠️ No data for realtime update')
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
      console.log('✅ Chart updated successfully at', new Date().toLocaleTimeString())
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
  
  // Wait for canvas to be ready
  let retries = 0
  while (!responseChart.value && retries < 10) {
    await new Promise(resolve => setTimeout(resolve, 100))
    retries++
  }
  
  if (!responseChart.value) {
    console.warn('⚠️ Canvas element not ready after retries')
    chartLoading.value = false
    return
  }
  
  // Destroy existing chart
  destroyChart()
  
  console.log('📊 Fetching chart data for period:', selectedPeriod.value)
  
  try {
    const response = await monitorStore.api.monitorChecks.getAll({
      monitor_id: route.params.id,
      per_page: getPeriodLimit(selectedPeriod.value),
      sort: 'checked_at',
      order: 'desc'
      , _t: Date.now()
    })
    
    console.log('📦 Chart API Response:', response.data)
    
    if (response.data.success) {
      let checks = response.data.data.data || response.data.data || []
      
      console.log('✅ Chart data loaded:', checks.length, 'data points')
      
      if (checks.length === 0) {
        console.warn('⚠️ No data available for chart')
      } else {
        // Convert to chart data points
        const dataPoints = checks.reverse().map(check => ({
          time: new Date(check.checked_at).getTime(),
          value: (check.latency_ms ?? check.latency ?? check.response_time ?? check.response_time_ms ?? 0) || 0
        }))
        
        console.log('📈 Drawing chart with', dataPoints.length, 'points')
        
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
      console.log('✅ Auto-refresh restarted after chart creation')
    }
  }
}

function drawChart(dataPoints) {
  if (!responseChart.value) {
    console.warn('⚠️ Canvas not available for drawing')
    return
  }
  
  if (!dataPoints || dataPoints.length === 0) {
    console.warn('⚠️ No data points to draw')
    return
  }
  
  console.log('🎨 Drawing chart with', dataPoints.length, 'data points')
  
  // Create chart using Canvas API
  const canvas = responseChart.value
  const ctx = canvas.getContext('2d')
  
  // Set canvas size
  canvas.width = 800
  canvas.height = 300
  
  // Clear canvas
  ctx.clearRect(0, 0, canvas.width, canvas.height)
  
  // Draw background
  const gradient = ctx.createLinearGradient(0, 0, 0, canvas.height)
  gradient.addColorStop(0, 'rgba(0, 184, 148, 0.2)')
  gradient.addColorStop(1, 'rgba(0, 184, 148, 0.0)')
  
  // Draw chart area background
  ctx.fillStyle = 'rgba(45, 52, 54, 0.8)'
  ctx.fillRect(0, 0, canvas.width, canvas.height)
  
  // Calculate chart dimensions
  const padding = 40
  const chartWidth = canvas.width - padding * 2
  const chartHeight = canvas.height - padding * 2
  const maxValue = Math.max(...dataPoints.map(d => d.value))
  const minValue = Math.min(...dataPoints.map(d => d.value))
  const valueRange = maxValue - minValue || 100

  // Handle single data point to avoid division by zero
  const segmentCount = Math.max(1, dataPoints.length - 1)
  
  // Draw grid lines
  ctx.strokeStyle = 'rgba(255, 255, 255, 0.1)'
  ctx.lineWidth = 1
  
  // Horizontal grid lines
  for (let i = 0; i <= 5; i++) {
    const y = padding + (chartHeight / 5) * i
    ctx.beginPath()
    ctx.moveTo(padding, y)
    ctx.lineTo(canvas.width - padding, y)
    ctx.stroke()
  }
  
  // Vertical grid lines
  for (let i = 0; i <= 10; i++) {
    const x = padding + (chartWidth / 10) * i
    ctx.beginPath()
    ctx.moveTo(x, padding)
    ctx.lineTo(x, canvas.height - padding)
    ctx.stroke()
  }
  
  const xSpacing = chartWidth / segmentCount

  // Draw chart line
  if (dataPoints.length > 1) {
    ctx.strokeStyle = '#00b894'
    ctx.lineWidth = 3
    ctx.lineCap = 'round'
    ctx.lineJoin = 'round'
    
    ctx.beginPath()
    
    dataPoints.forEach((point, index) => {
      const x = padding + xSpacing * index
      const y = padding + chartHeight - ((point.value - minValue) / valueRange) * chartHeight
      
      if (index === 0) {
        ctx.moveTo(x, y)
      } else {
        ctx.lineTo(x, y)
      }
    })
    
    ctx.stroke()
    
    // Draw gradient fill
    ctx.fillStyle = gradient
    ctx.lineTo(canvas.width - padding, canvas.height - padding)
    ctx.lineTo(padding, canvas.height - padding)
    ctx.closePath()
    ctx.fill()
    
    // Draw data points with animation effect for latest point
    ctx.fillStyle = '#00b894'
    dataPoints.forEach((point, index) => {
      const x = padding + xSpacing * index
      const y = padding + chartHeight - ((point.value - minValue) / valueRange) * chartHeight
      
      ctx.beginPath()
      // Make latest point larger and with glow effect
      const radius = index === dataPoints.length - 1 ? 6 : 4
      ctx.arc(x, y, radius, 0, Math.PI * 2)
      
      if (index === dataPoints.length - 1) {
        // Add glow effect for latest point
        ctx.shadowColor = '#00b894'
        ctx.shadowBlur = 10
        ctx.fill()
        ctx.shadowBlur = 0
      } else {
        ctx.fill()
      }
    })
    
    // Draw Y-axis labels
    ctx.fillStyle = '#b2bec3'
    ctx.font = '12px Arial'
    ctx.textAlign = 'right'
    
    for (let i = 0; i <= 5; i++) {
      const value = maxValue - (valueRange / 5) * i
      const y = padding + (chartHeight / 5) * i
      ctx.fillText(Math.round(value) + 'ms', padding - 10, y + 4)
    }
    
    // Draw X-axis labels
    ctx.textAlign = 'center'
    dataPoints.forEach((point, index) => {
      if (index % Math.ceil(dataPoints.length / 6) === 0 || index === dataPoints.length - 1) {
        const x = padding + xSpacing * index
        const label = formatChartTime(point.time, selectedPeriod.value)
        ctx.fillText(label, x, canvas.height - 10)
      }
    })
  } else if (dataPoints.length === 1) {
    // Single data point: place it in the middle
    const point = dataPoints[0]
    const x = padding + chartWidth / 2
    const y = padding + chartHeight - ((point.value - minValue) / valueRange) * chartHeight

    ctx.fillStyle = '#00b894'
    ctx.beginPath()
    ctx.arc(x, y, 6, 0, Math.PI * 2)
    ctx.fill()

    // Draw labels for single point
    ctx.fillStyle = '#b2bec3'
    ctx.font = '12px Arial'
    ctx.textAlign = 'center'
    ctx.fillText(formatChartTime(point.time, selectedPeriod.value), x, canvas.height - 10)
    ctx.textAlign = 'right'
    ctx.fillText(Math.round(point.value) + 'ms', padding - 10, y + 4)
  }
  
  chartInstance.value = true // Mark as initialized
  console.log('✅ Chart drawn successfully')
}

function getPeriodLimit(period) {
  switch (period) {
    case '1h': return 60
    case '24h': return 100
    case '7d': return 168
    case '30d': return 720
    default: return 100
  }
}

function formatChartTime(timestamp, period) {
  const date = new Date(timestamp)
  
  switch (period) {
    case '1h':
      return date.toLocaleTimeString('en-US', { 
        hour: '2-digit', 
        minute: '2-digit',
        hour12: false 
      })
    case '24h':
      return date.toLocaleTimeString('en-US', { 
        hour: '2-digit',
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
        hour12: false 
      })
  }
}

function destroyChart() {
  if (chartInstance.value) {
    chartInstance.value = null
  }
}

async function selectPeriod(period) {
  if (selectedPeriod.value === period) return
  
  console.log('🔄 Switching period from', selectedPeriod.value, 'to', period)
  
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
  if (monitor.value && chartInstance.value) {
    startChartAutoRefresh()
    console.log('✅ Period switched successfully to', period)
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
        console.log('✅ Monitor resumed successfully')
      } else {
        console.error('❌ Failed to resume monitor:', result.message)
        alert('Failed to resume monitor: ' + result.message)
      }
    } else {
      // Pause monitor for 60 minutes
      const result = await monitorStore.pauseMonitor(monitor.value.id, 60)
      if (result.success) {
        console.log('✅ Monitor paused successfully')
      } else {
        console.error('❌ Failed to pause monitor:', result.message)
        alert('Failed to pause monitor: ' + result.message)
      }
    }

    await fetchMonitorData()
  } catch (err) {
    console.error('Error toggling monitor:', err)
    alert('Error toggling monitor: ' + err.message)
  }
}

async function deleteMonitor() {
  if (!monitor.value) return
  
  if (confirm(`Are you sure you want to delete "${monitor.value.name}"?`)) {
    try {
      const result = await monitorStore.deleteMonitor(monitor.value.id)
      
      if (result.success) {
        router.push('/monitors')
      }
    } catch (err) {
      console.error('Error deleting monitor:', err)
    }
  }
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
      alert('TCP service cannot be opened in browser')
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
  
  if (confirm('Are you sure you want to clear status history for this monitor?')) {
    try {
      console.log('🗑️ Clearing status history for monitor:', monitor.value.id)
      
      // Clear local status history data
      allStatusHistory.value = []
      totalItems.value = 0
      currentPage.value = 1
      
      console.log('✅ Status history cleared successfully')
      
      // Show success message
      alert('Status history cleared successfully. The history will rebuild as new checks are performed.')
    } catch (err) {
      console.error('❌ Error clearing status history:', err)
      alert('Error clearing status history. Please try again.')
    }
  }
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
  margin-bottom: 24px;
  padding: 20px 24px;
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

.monitor-status-section {
  text-align: right;
}

.current-status {
  font-size: 1rem;
  font-weight: 700;
  padding: 8px 16px;
  border-radius: 8px;
  margin-bottom: 8px;
  letter-spacing: 0.5px;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
  border: 1px solid rgba(255, 255, 255, 0.2);
  min-width: 80px;
  text-align: center;
}

.current-status.status-up {
  background: #00b894;
  color: white;
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

.stat-card {
  background: linear-gradient(135deg, rgba(45, 52, 54, 0.95) 0%, rgba(99, 110, 114, 0.85) 100%);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 20px;
  text-align: center;
  border-left: 4px solid #00b894;
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  min-height: 160px;
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
}

.stat-header {
  font-size: 0.85rem;
  color: #b2bec3;
  margin-bottom: 4px;
  font-weight: 600;
  letter-spacing: 0.5px;
  text-transform: uppercase;
}

.stat-subheader {
  font-size: 1.48rem;
  color: #636e72;
  margin-bottom: 12px;
.stat-value {
  font-size: 2rem;
  font-weight: 700;
  color: #ffffff;
  margin: 8px 0;
  line-height: 1.2;
} font-weight: bold;
  color: #ffffff;
}

.stat-value.uptime-value {
  color: #00b894;
}

.stat-value {
  transition: all 0.3s ease;
}

.stat-value.response-value {
  color: #74b9ff;
}

.stat-value.uptime-value {
  color: #00b894;
}

.stat-value.cert-value {
  color: #fdcb6e;
}

.stat-loading {
  animation: pulse 1.5s infinite;
.stat-trend {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  margin-top: auto;
  padding-top: 8px;
  font-size: 0.8rem;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
} margin-top: 8px;
  font-size: 0.8rem;
}

.trend-icon {
  font-size: 1rem;
  animation: bounce 2s infinite;
}

.trend-icon.up {
  color: #00b894;
}

.trend-icon.down {
  color: #e17055;
}

.trend-text {
  color: #b2bec3;
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
  height: 300px;
  background: rgba(26, 26, 26, 0.8);
  border-radius: 8px;
  padding: 10px;
  transition: all 0.3s ease;
  overflow: hidden;
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

.chart-skeleton {
  height: 260px;
  background: linear-gradient(90deg, #636e72 25%, #74828a 50%, #636e72 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 8px;
  margin-top: 20px;
}

.refresh-chart-btn {
  background: transparent;
  border: 1px solid #636e72;
  color: #b2bec3;
  padding: 5px 10px;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.refresh-chart-btn:hover:not(:disabled) {
  border-color: #00b894;
  color: #00b894;
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
  gap: 5px;
}

.period-btn {
  background: transparent;
  color: #b2bec3;
  border: 1px solid #636e72;
  padding: 5px 12px;
  border-radius: 4px;
  cursor: pointer;
  font-size: 0.9rem;
  transition: all 0.2s;
}

.period-btn:hover {
  border-color: #00b894;
  color: #00b894;
}

.period-btn.active {
  background: #00b894;
  color: white;
  border-color: #00b894;
}

.chart-container {
  position: relative;
  height: 300px;
  background: rgba(26, 26, 26, 0.8);
  border-radius: 8px;
  padding: 10px;
  transition: all 0.3s ease;
  overflow: hidden;
}

.chart-container canvas {
  width: 100%;
  height: 100%;
  display: block;
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
    text-align: left;
  }
  
  .current-status {
    font-size: 1.2rem;
    padding: 8px 16px;
    min-width: auto;
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
    color: #e6eef0;
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
  color: #eef6f7 !important;
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
  .monitor-detail .stat-card { background: linear-gradient(180deg,#101315,#0f1214) !important; border-left-color: #00b894 !important; }
  .monitor-detail .history-table { background: #0b0f11 !important; }
  .monitor-detail .history-table th { background: #1b1f22 !important; color: #eef6f7 !important; }
  .monitor-detail .history-table td { color: #ddeff0 !important; }
}
</style>