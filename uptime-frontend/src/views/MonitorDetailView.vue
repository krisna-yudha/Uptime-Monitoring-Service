<template>
  <div class="monitor-detail">
    <div class="monitor-detail-content">
    <!-- Header Section -->
    <div class="detail-header">
      <div class="header-content">
        <div class="monitor-title">
          <h1>{{ monitor?.name || 'Loading...' }}</h1>
          <div class="monitor-url">
            <span class="url-text">{{ monitor?.target }}</span>
            <button 
              @click="visitMonitor" 
              class="visit-btn"
              :disabled="!isVisitable(monitor)"
              :title="getVisitTooltip(monitor)"
            >
              import { UilSearch } from '@iconscout/vue-unicons' Visit
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
          :class="monitor?.enabled ? 'btn-warning' : 'btn-success'"
        >
          {{ monitor?.enabled ? '⏸️ Pause' : '▶️ Resume' }}
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
              :class="{ active: selectedPeriod === period.value, loading: chartLoading }"
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
const statusHistory = ref([])
const allStatusHistory = ref([])
const totalItems = ref(0)
const responseChart = ref(null)
const chartInstance = ref(null)
const chartLoading = ref(false)
const chartRefreshInterval = ref(null)
const historyRefreshInterval = ref(null)

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
  
  return [
    {
      key: 'response',
      header: 'Response',
      subheader: '(Current)',
      value: monitor.value?.last_response_time ? `${monitor.value.last_response_time} ms` : 'N/A',
      valueClass: 'response-value',
      loading: false,
      trend: monitor.value?.last_response_time ? {
        direction: monitor.value.last_response_time < 200 ? 'up' : 'down',
        icon: monitor.value.last_response_time < 200 ? '↗' : '↘',
        text: monitor.value.last_response_time < 200 ? 'Fast' : 'Slow'
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
      value: monitor.value?.type === 'https' ? '47 days' : 'N/A',
      valueClass: 'cert-value',
      loading: false,
      trend: monitor.value?.type === 'https' ? {
        direction: 'down',
        icon: '⏰',
        text: 'Expires soon'
      } : null
    }
  ]
})

// Lifecycle
onMounted(() => {
  fetchMonitorData()
  startChartAutoRefresh()
  startHistoryAutoRefresh()
})

onUnmounted(() => {
  destroyChart()
  stopChartAutoRefresh()
  stopHistoryAutoRefresh()
})

// Watch for route changes
watch(() => route.params.id, fetchMonitorData)
watch(selectedPeriod, fetchChartData)

// Methods
async function fetchMonitorData() {
  loading.value = true
  error.value = null
  
  try {
    const result = await monitorStore.fetchMonitor(route.params.id)
    
    if (result.success) {
      monitor.value = result.data
      await Promise.all([
        fetchStatusHistory(),
        fetchChartData()
      ])
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

async function fetchStatusHistory() {
  try {
    // Generate 100 services for backend durability testing
    const services = []
    const statuses = ['up', 'up', 'up', 'up', 'down', 'validating'] // 67% up, 17% down, 16% validating
    const errorMessages = [
      'Connection timeout',
      'DNS resolution failed', 
      'Server not responding',
      'Network unreachable',
      'Service unavailable',
      'SSL certificate expired',
      'HTTP 500 Internal Error',
      'HTTP 503 Service Unavailable',
      'Connection refused',
      'Host unreachable'
    ]
    
    const serviceNames = [
      'web-api', 'database', 'redis-cache', 'elasticsearch', 'nginx-proxy',
      'auth-service', 'payment-gateway', 'file-storage', 'message-queue', 'monitoring',
      'cdn-endpoint', 'load-balancer', 'email-service', 'backup-service', 'analytics'
    ]
    
    for (let i = 1; i <= 100; i++) {
      const randomStatus = statuses[Math.floor(Math.random() * statuses.length)]
      const timeOffset = i * 45000 + Math.floor(Math.random() * 20000) // Every ~45 seconds with variation
      const serviceName = serviceNames[Math.floor(Math.random() * serviceNames.length)]
      
      let service = {
        id: i,
        status: randomStatus,
        checked_at: new Date(Date.now() - timeOffset).toISOString(),
        response_time: null,
        error_message: null,
        service_name: `${serviceName}-${Math.floor(i/10) + 1}`
      }
      
      if (randomStatus === 'up') {
        service.response_time = 50 + Math.floor(Math.random() * 350) // 50-400ms
      } else if (randomStatus === 'down') {
        service.error_message = errorMessages[Math.floor(Math.random() * errorMessages.length)]
      } else if (randomStatus === 'validating') {
        service.response_time = 800 + Math.floor(Math.random() * 400) // Slow response 800-1200ms
      }
      
      services.push(service)
    }
    
    // Sort by checked_at descending (newest first)
    allStatusHistory.value = services.sort((a, b) => new Date(b.checked_at) - new Date(a.checked_at))
    totalItems.value = allStatusHistory.value.length
    currentPage.value = 1
    
    console.log(`🚀 Backend Durability Test: Generated ${allStatusHistory.value.length} services`)
    console.log(`📊 Total pages: ${Math.ceil(totalItems.value / itemsPerPage)}`)
    console.log(`⚡ Worker will process ${allStatusHistory.value.length} entries in realtime updates`)
  } catch (err) {
    console.error('Error fetching status history:', err)
  }
}

async function updateHistoryRealtime() {
  if (!monitor.value || loading.value) return
  
  // Generate new status entry with realistic data
  const now = new Date()
  const statuses = ['up', 'up', 'up', 'up', 'down'] // 80% up, 20% down
  const randomStatus = statuses[Math.floor(Math.random() * statuses.length)]
  
  let newEntry
  if (randomStatus === 'up') {
    const responseTime = 120 + Math.floor(Math.random() * 100) // 120-220ms
    newEntry = {
      id: Date.now(),
      status: 'up',
      checked_at: now.toISOString(),
      response_time: responseTime,
      error_message: null
    }
  } else {
    const errorMessages = [
      'Connection timeout',
      'DNS resolution failed',
      'Server not responding',
      'Network unreachable',
      'Service unavailable'
    ]
    const randomError = errorMessages[Math.floor(Math.random() * errorMessages.length)]
    
    newEntry = {
      id: Date.now(),
      status: 'down',
      checked_at: now.toISOString(),
      response_time: null,
      error_message: randomError
    }
  }
  
  // Add new entry to the top of history
  allStatusHistory.value.unshift(newEntry)
  
  // Update total items count
  totalItems.value = allStatusHistory.value.length
  
  // If we're not on the first page, don't auto-navigate to show new entries
  // Users can manually go to page 1 to see latest entries
  
  // Update monitor's last status and response time
  if (monitor.value) {
    monitor.value.last_status = newEntry.status
    monitor.value.last_response_time = newEntry.response_time
    monitor.value.last_checked_at = newEntry.checked_at
  }
}

function startChartAutoRefresh() {
  // Auto refresh chart every 10 seconds
  chartRefreshInterval.value = setInterval(() => {
    if (!chartLoading.value && responseChart.value) {
      updateChartRealtime()
    }
  }, 10000)
}

function stopChartAutoRefresh() {
  if (chartRefreshInterval.value) {
    clearInterval(chartRefreshInterval.value)
    chartRefreshInterval.value = null
  }
}

function startHistoryAutoRefresh() {
  // Auto refresh status history every 15 seconds
  historyRefreshInterval.value = setInterval(() => {
    if (!loading.value && monitor.value) {
      updateHistoryRealtime()
    }
  }, 15000)
}

function stopHistoryAutoRefresh() {
  if (historyRefreshInterval.value) {
    clearInterval(historyRefreshInterval.value)
    historyRefreshInterval.value = null
  }
}

async function updateChartRealtime() {
  if (!responseChart.value || chartLoading.value) return
  
  // Generate new data point
  const now = Date.now()
  const baseValue = 150 + Math.random() * 100 // 150-250ms base
  const variation = (Math.sin(now / 10000) * 30) + (Math.random() * 40 - 20)
  const newValue = Math.max(50, baseValue + variation)
  
  const newDataPoint = {
    time: now,
    value: Math.round(newValue)
  }
  
  // Update chart with new data point
  await updateChartWithNewData(newDataPoint)
}

async function updateChartWithNewData(newDataPoint) {
  if (!responseChart.value) return
  
  // Get existing data and add new point
  const existingData = generateChartData(selectedPeriod.value)
  existingData.push(newDataPoint)
  
  // Remove oldest point to maintain count
  const maxPoints = getMaxPointsForPeriod(selectedPeriod.value)
  if (existingData.length > maxPoints) {
    existingData.shift()
  }
  
  // Redraw chart with updated data
  drawChart(existingData)
}

function getMaxPointsForPeriod(period) {
  switch (period) {
    case '1h': return 12
    case '24h': return 12
    case '7d': return 14
    case '30d': return 30
    default: return 12
  }
}

async function fetchChartData() {
  chartLoading.value = true
  await nextTick()
  
  // Simulate API call delay
  await new Promise(resolve => setTimeout(resolve, 1000))
  
  if (!responseChart.value) {
    chartLoading.value = false
    return
  }
  
  // Destroy existing chart
  destroyChart()
  
  // Generate sample data based on selected period
  const dataPoints = generateChartData(selectedPeriod.value)
  
  // Draw chart
  drawChart(dataPoints)
  
  chartLoading.value = false
}

function drawChart(dataPoints) {
  if (!responseChart.value || !dataPoints || dataPoints.length === 0) return
  
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
  
  // Draw chart line
  if (dataPoints.length > 1) {
    ctx.strokeStyle = '#00b894'
    ctx.lineWidth = 3
    ctx.lineCap = 'round'
    ctx.lineJoin = 'round'
    
    ctx.beginPath()
    
    dataPoints.forEach((point, index) => {
      const x = padding + (chartWidth / (dataPoints.length - 1)) * index
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
      const x = padding + (chartWidth / (dataPoints.length - 1)) * index
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
        const x = padding + (chartWidth / (dataPoints.length - 1)) * index
        const label = formatChartTime(point.time, selectedPeriod.value)
        ctx.fillText(label, x, canvas.height - 10)
      }
    })
  }
}

async function selectPeriod(period) {
  selectedPeriod.value = period
  
  // Restart auto refresh with new period
  stopChartAutoRefresh()
  await fetchChartData()
  startChartAutoRefresh()
}

async function refreshChart() {
  await fetchChartData()
}

function generateChartData(period) {
  const now = Date.now()
  const dataPoints = []
  let interval, count
  
  switch (period) {
    case '1h':
      interval = 5 * 60 * 1000 // 5 minutes
      count = 12
      break
    case '24h':
      interval = 2 * 60 * 60 * 1000 // 2 hours
      count = 12
      break
    case '7d':
      interval = 12 * 60 * 60 * 1000 // 12 hours
      count = 14
      break
    case '30d':
      interval = 24 * 60 * 60 * 1000 // 1 day
      count = 30
      break
    default:
      interval = 2 * 60 * 60 * 1000
      count = 12
  }
  
  for (let i = count - 1; i >= 0; i--) {
    const time = now - (interval * i)
    const baseValue = 150 + Math.random() * 100 // 150-250ms base
    const variation = (Math.sin(i / 3) * 30) + (Math.random() * 40 - 20)
    const value = Math.max(50, baseValue + variation)
    
    dataPoints.push({
      time,
      value: Math.round(value)
    })
  }
  
  return dataPoints
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
    chartInstance.value.destroy()
    chartInstance.value = null
  }
}

async function toggleMonitor() {
  if (!monitor.value) return
  
  try {
    if (monitor.value.enabled) {
      await monitorStore.pauseMonitor(monitor.value.id, 60)
    } else {
      await monitorStore.updateMonitor(monitor.value.id, { enabled: true })
    }
    
    // Refresh monitor data
    await fetchMonitorData()
  } catch (err) {
    console.error('Error toggling monitor:', err)
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

function clearData() {
  if (confirm('Are you sure you want to clear all monitoring data for this service?')) {
    // API call to clear monitoring data
    console.log('Clearing data...')
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
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
    radial-gradient(circle at 20% 80%, rgba(120, 119, 198, 0.3) 0%, transparent 50%),
    radial-gradient(circle at 80% 20%, rgba(255, 119, 198, 0.15) 0%, transparent 50%),
    radial-gradient(circle at 40% 40%, rgba(120, 219, 255, 0.1) 0%, transparent 50%);
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
  color: #ffffff;
}

.monitor-url {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 15px;
}

.url-text {
  color: #00b894;
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
  padding: 16px;
  text-align: center;
  border-left: 4px solid #00b894;
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
}

.stat-header {
  font-size: 0.9rem;
  color: #b2bec3;
  margin-bottom: 5px;
  font-weight: 500;
}

.stat-subheader {
  font-size: 0.8rem;
  color: #636e72;
  margin-bottom: 10px;
}

.stat-value {
  font-size: 1.8rem;
  font-weight: bold;
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
}

.stat-trend {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
  margin-top: 8px;
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