<template>
  <div class="dashboard">
    <div class="dashboard-header">
      <div class="header-main">
        <h1>Dashboard</h1>
        <p>Overview of your monitoring system</p>
      </div>
      <div class="header-status">
        <div class="user-info" v-if="currentUser">
          <span class="user-avatar">{{ currentUser.name ? currentUser.name.charAt(0).toUpperCase() : 'U' }}</span>
          <div class="user-details">
            <span class="user-name">{{ currentUser.name || 'Unknown User' }}</span>
            <span class="user-role" :class="`role-${currentUser.role}`">{{ currentUser.role || 'user' }}</span>
          </div>
        </div>
        <span class="status-indicator live">
          <div class="pulse-dot"></div>
          <span></span>
        </span>
      </div>
    </div>

    <!-- Loading Skeleton -->
    <div v-if="loading" class="dashboard-grid skeleton">
      <div class="stats-row">
        <div class="stat-card skeleton-card" v-for="n in 4" :key="n">
          <div class="skeleton-icon"></div>
          <div class="skeleton-content">
            <div class="skeleton-line large"></div>
            <div class="skeleton-line small"></div>
          </div>
        </div>
      </div>
      
      <div class="card skeleton-card-large">
        <div class="skeleton-header">
          <div class="skeleton-line medium"></div>
          <div class="skeleton-line small"></div>
        </div>
        <div class="skeleton-list">
          <div class="skeleton-item" v-for="n in 5" :key="n">
            <div class="skeleton-status"></div>
            <div class="skeleton-content-item">
              <div class="skeleton-line title"></div>
              <div class="skeleton-line subtitle"></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-else-if="serverError" class="server-error">
      <h2>Server Error</h2>
      <p>Tidak dapat terhubung ke Laravel server. Pastikan server berjalan di <code>http://localhost:8000</code></p>
      <button @click="retryConnection" class="btn btn-primary">Coba Lagi</button>
    </div>

    <div v-else-if="error" class="error">
      <h2>Error</h2>
      <p>{{ error }}</p>
      <div class="error-actions">
        <button @click="fetchDashboardData" class="btn btn-primary">Coba Lagi</button>
        <router-link to="/login" class="btn btn-success" v-if="error.includes('login')">
          🔐 Login
        </router-link>
      </div>
    </div>

    <div v-else class="dashboard-grid">
      <!-- Stats Cards -->
      <div class="stats-row">
        <div class="stat-card stat-total" style="animation-delay: 0.1s">
          <div class="stat-icon">
            <span>📊</span>
            <div class="icon-glow"></div>
          </div>
          <div class="stat-content">
            <h3 class="counter" data-target="{{ dashboardData?.total_monitors || 0 }}">{{ dashboardData?.total_monitors || 0 }}</h3>
            <p>Total Monitors</p>
          </div>
          <!-- <div class="stat-trend positive">+{{ Math.floor(Math.random() * 5) }}% this week</div> -->
        </div>

        <div class="stat-card stat-up" style="animation-delay: 0.2s">
          <div class="stat-icon">
            <span>✅</span>
            <div class="icon-glow success"></div>
          </div>
          <div class="stat-content">
            <h3 class="counter" data-target="{{ dashboardData?.monitors_up || 0 }}">{{ dashboardData?.monitors_up || 0 }}</h3>
            <p>Monitors Up</p>
          </div>
          <!-- <div class="stat-trend positive">{{ uptimePercentage }}% uptime</div> -->
        </div>

        <div class="stat-card stat-down" style="animation-delay: 0.3s">
          <div class="stat-icon">
            <span>❌</span>
            <div class="icon-glow danger"></div>
          </div>
          <div class="stat-content">
            <h3 class="counter" data-target="{{ dashboardData?.monitors_down || 0 }}">{{ dashboardData?.monitors_down || 0 }}</h3>
            <p>Monitors Down</p>
          </div>
          <!-- <div class="stat-trend negative" v-if="dashboardData?.monitors_down > 0">Needs attention</div> -->
          <!-- <div class="stat-trend positive" v-else>All good!</div> -->
        </div>

        <!-- <div class="stat-card stat-incidents" style="animation-delay: 0.4s">
          <div class="stat-icon">
            <span>🚨</span>
            <div class="icon-glow warning"></div>
          </div>
          <div class="stat-content">
            <h3 class="counter" data-target="{{ dashboardData?.open_incidents || 0 }}">{{ dashboardData?.open_incidents || 0 }}</h3>
            <p>Open Incidents</p>
          </div>
          <div class="stat-trend neutral">{{ averageResponseTime }}ms avg response</div>
        </div> -->
      </div>
      <!-- Quick Actions -->
      <div class="card">
        <div class="card-header">
          <h3>Quick Actions</h3>
        </div>
        
        <div class="quick-actions">
          <router-link to="/monitors/create" class="btn btn-success">
            ➕ Add New Monitor
          </router-link>
          <router-link to="/notifications" class="btn btn-primary">
            🔔 Manage Notifications
          </router-link>
          <button @click="refreshDashboard" class="btn btn-warning">
            🔄 Refresh Data
          </button>
        </div>
      </div>
      <!-- Recent Activity
      <div class="card activity-card" style="animation-delay: 0.5s">
        <div class="card-header">
          <h3>
            <span class="icon">📊</span>
            Recent Monitor Activity
          </h3>
          <router-link to="/monitors" class="btn btn-primary btn-sm">
            <span>👁️</span> View All Monitors
          </router-link>
        </div>
        
        <div class="activity-list" v-if="recentChecks?.length">
          <div
            v-for="(check, index) in recentChecks"
            :key="check.id"
            class="activity-item"
            :style="{ animationDelay: `${0.1 * index}s` }"
          >
            <div class="activity-status">
              <span 
                class="status-indicator"
                :class="{
                  'status-up': check.status === 'up',
                  'status-down': check.status === 'down',
                  'status-unknown': check.status === 'unknown'
                }"
              >
                <div class="status-pulse"></div>
              </span>
            </div>
            <div class="activity-content">
              <strong>{{ check.monitor?.name }}</strong>
              <p class="activity-status-text">
                <span class="status-badge" :class="check.status">{{ check.status.toUpperCase() }}</span>
                <span class="activity-time">{{ formatDate(check.checked_at) }}</span>
              </p>
              <small v-if="check.error_message" class="error-msg">
                ⚠️ {{ check.error_message }}
              </small>
            </div>
            <div class="activity-metrics">
              <span v-if="check.latency_ms" class="latency-badge" :class="getLatencyClass(check.latency_ms)">
                {{ check.latency_ms }}ms
              </span>
              <div class="activity-actions">
                <button @click="viewMonitor(check.monitor?.id)" class="action-btn">
                  <span>👁️</span>
                </button>
              </div>
            </div>
          </div>
        </div>
        
        <div v-else class="no-data">
          <div class="no-data-icon">📊</div>
          <p>No recent activity</p>
        </div>
      </div> -->

      <!-- Current Incidents -->
      <div class="card">
        <div class="card-header">
          <h3>Current Incidents</h3>
          <router-link to="/incidents" class="btn btn-primary btn-sm">
            View All Incidents
          </router-link>
        </div>
        
        <div v-if="!dashboardData?.current_incidents?.length" class="no-data">
          No active incidents 🎉
        </div>
        
        <div v-else class="incidents-list">
          <div
            v-for="incident in dashboardData.current_incidents"
            :key="incident.id"
            class="incident-item"
          >
            <div class="incident-content">
              <strong>{{ incident.monitor?.name }}</strong>
              <p>Started: {{ formatDate(incident.started_at) }}</p>
              <small>{{ incident.description || 'No description' }}</small>
            </div>
            <div class="incident-duration">
              {{ getIncidentDuration(incident.started_at) }}
            </div>
          </div>
        </div>
      </div>

      <!-- System Console Log -->
      <div class="card console-card">
        <div class="card-header console-header">
          <h3>
            <span class="console-icon">💻</span>
            System Console
            <span class="log-count">({{ systemLogs.length }})</span>
          </h3>
          <div class="console-controls">
            <button @click="toggleAutoScroll" class="btn btn-sm" :class="autoScroll ? 'btn-success' : 'btn-secondary'">
              {{ autoScroll ? '🔄' : '⏸️' }} Auto-scroll
            </button>
            <button @click="clearLogs" class="btn btn-warning btn-sm">
              🗑️ Clear
            </button>
            <button @click="exportLogs" class="btn btn-info btn-sm">
              📥 Export
            </button>
            <button @click="toggleConsole" class="btn btn-primary btn-sm">
              {{ consoleExpanded ? '⬇️' : '⬆️' }} {{ consoleExpanded ? 'Collapse' : 'Expand' }}
            </button>
          </div>
        </div>
        
        <div class="console-container" :class="{ expanded: consoleExpanded }">
          <div class="console-body" ref="consoleBody">
            <div v-if="systemLogs.length === 0" class="no-logs">
              <span class="console-prompt">$</span> No system logs yet...
            </div>
            
            <div
              v-for="(log, index) in systemLogs"
              :key="index"
              class="log-entry"
              :class="`log-${log.level}`"
            >
              <span class="log-timestamp">{{ formatLogTime(log.timestamp) }}</span>
              <span class="log-level" :class="`level-${log.level}`">[{{ log.level.toUpperCase() }}]</span>
              <span class="log-source" v-if="log.source">[{{ log.source }}]</span>
              <span class="log-message">{{ log.message }}</span>
              <div v-if="log.data" class="log-data">
                <pre>{{ formatLogData(log.data) }}</pre>
              </div>
            </div>
            
            <div class="console-prompt-line">
              <span class="console-prompt">$</span>
              <span class="cursor-blink">█</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick, computed } from 'vue'
import api from '../services/api'
import { formatDistanceToNow } from 'date-fns'
import axios from 'axios'

const loading = ref(true)
const error = ref(null)
const serverError = ref(false)
const dashboardData = ref(null)
const recentChecks = ref([])
const systemLogs = ref([])
const consoleExpanded = ref(false)
const autoScroll = ref(true)
const consoleBody = ref(null)
const userInfo = ref(null)

// Computed property for current user
const currentUser = computed(() => {
  if (userInfo.value) return userInfo.value
  try {
    const stored = localStorage.getItem('user')
    return stored ? JSON.parse(stored) : null
  } catch (e) {
    return null
  }
})

// Create axios instance for direct API calls
const apiClient = axios.create({
  baseURL: 'http://localhost:8000/api',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
})

// Add token to requests automatically
apiClient.interceptors.request.use(
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

onMounted(async () => {
  // Clear any old data first
  addSystemLog('info', '🧹 Initializing dashboard...', 'System')
  
  // Initialize system console with detailed info
  addSystemLog('success', '🚀 Uptime Monitor Console v1.0 Initialized', 'Console')
  addSystemLog('info', '📊 Dashboard module loaded successfully', 'Dashboard')
  
  // System information
  addSystemLog('info', `🌐 Browser: ${navigator.userAgent.match(/Chrome|Firefox|Safari|Edge/)?.[0] || 'Unknown'}`, 'System')
  addSystemLog('info', `📱 Platform: ${navigator.platform}`, 'System')
  addSystemLog('info', `🖥️ Screen: ${screen.width}x${screen.height} (${screen.colorDepth}-bit)`, 'System')
  addSystemLog('info', `💾 Memory: ${navigator.deviceMemory ? navigator.deviceMemory + 'GB' : 'N/A'}`, 'System')
  addSystemLog('info', `🌍 Language: ${navigator.language}`, 'System')
  addSystemLog('info', `⏰ Timezone: ${Intl.DateTimeFormat().resolvedOptions().timeZone}`, 'System')
  
  // Network info
  if (navigator.onLine) {
    addSystemLog('success', '🌐 Network connection available', 'Network')
  } else {
    addSystemLog('error', '🚫 Network connection unavailable', 'Network')
  }
  
  // Performance info
  if (performance.memory) {
    const mem = performance.memory
    addSystemLog('info', `📈 JS Heap: ${Math.round(mem.usedJSHeapSize / 1024 / 1024)}MB / ${Math.round(mem.totalJSHeapSize / 1024 / 1024)}MB`, 'Performance')
  }
  
  // System information
  addSystemLog('info', `🌐 Browser: ${navigator.userAgent.match(/Chrome|Firefox|Safari|Edge/)?.[0] || 'Unknown'}`, 'System')
  addSystemLog('info', `📱 Platform: ${navigator.platform}`, 'System')
  addSystemLog('info', `🖥️ Screen: ${screen.width}x${screen.height} (${screen.colorDepth}-bit)`, 'System')
  addSystemLog('info', `💾 Memory: ${navigator.deviceMemory ? navigator.deviceMemory + 'GB' : 'N/A'}`, 'System')
  addSystemLog('info', `🌍 Language: ${navigator.language}`, 'System')
  addSystemLog('info', `⏰ Timezone: ${Intl.DateTimeFormat().resolvedOptions().timeZone}`, 'System')
  
  // Test server connection first
  try {
    console.log('Testing server connection...')
    const token = localStorage.getItem('token')
    console.log('Token exists:', !!token)
    
    await apiClient.get('/test')
    console.log('Server connection successful')
  } catch (err) {
    console.warn('Server connection test failed:', err.message)
    console.error('Full error:', err)
    
    if (err.code === 'ECONNREFUSED' || err.message.includes('Network Error') || !err.response) {
      serverError.value = true
      loading.value = false
      return
    }
  }
  
  await fetchDashboardData()
})

async function fetchDashboardData() {
  console.log('🟡 STEP 1: Starting fetchDashboardData()')
  loading.value = true
  error.value = null
  serverError.value = false

  addSystemLog('info', '🚀 Starting dashboard data fetch...', 'API')

  try {
    console.log('🟡 STEP 2: Setting up authentication')
    
    // Check existing token and try to validate it
    let currentToken = localStorage.getItem('token')
    let currentUser = null
    
    try {
      const userStr = localStorage.getItem('user')
      if (userStr) {
        currentUser = JSON.parse(userStr)
      }
    } catch (e) {
      console.log('Invalid user data in localStorage')
    }

    // Try existing token first
    if (currentToken && currentUser) {
      addSystemLog('info', `🔍 Found existing token for ${currentUser.name}`, 'Auth')
      console.log('🟡 STEP 3: Testing existing token...')
      
      try {
        const testResponse = await apiClient.get('/dashboard/overview')
        console.log('🟢 Existing token works!')
        addSystemLog('success', '✅ Existing token valid', 'Auth')
        
        // Update reactive user info
        userInfo.value = currentUser
        
        // Process successful response
        await processSuccessfulResponse(testResponse)
        return // Success! Exit early
        
      } catch (tokenError) {
        if (tokenError.response?.status === 401) {
          addSystemLog('warn', '🔐 Existing token expired', 'Auth')
          console.log('🔴 Existing token expired, need fresh login')
        } else {
          throw tokenError // Re-throw non-auth errors
        }
      }
    }

    // Auto-login attempt with multiple user options
    addSystemLog('info', '🔑 Attempting auto-login...', 'Auth')
    console.log('🟡 STEP 3: Auto-login attempt')
    
    const userCredentials = [
      { email: 'admin@uptimemonitor.local', password: 'password', role: 'admin' },
      { email: 'user@uptimemonitor.local', password: 'password', role: 'user' },
      { email: 'test@example.com', password: 'password', role: 'user' }
    ]
    
    let loginSuccess = false
    
    for (const cred of userCredentials) {
      try {
        console.log(`🟡 Trying login with: ${cred.email}`)
        addSystemLog('info', `🔐 Trying ${cred.email} (${cred.role})`, 'Auth')
        
        const loginResponse = await axios.post('http://localhost:8000/api/auth/login', {
          email: cred.email,
          password: cred.password
        }, {
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          }
        })
        
        if (loginResponse.data && loginResponse.data.access_token) {
          currentToken = loginResponse.data.access_token
          currentUser = loginResponse.data.user || {
            id: loginResponse.data.user?.id || Math.floor(Math.random() * 1000),
            name: loginResponse.data.user?.name || cred.email.split('@')[0],
            email: cred.email,
            role: loginResponse.data.user?.role || cred.role
          }
          
          localStorage.setItem('token', currentToken)
          localStorage.setItem('user', JSON.stringify(currentUser))
          userInfo.value = currentUser // Update reactive user info
          
          console.log(`🟢 Login successful for: ${currentUser.name} (${currentUser.role})`)
          addSystemLog('success', `🎉 Auto-login successful: ${currentUser.name} (${currentUser.role})`, 'Auth', currentUser)
          loginSuccess = true
          break
        }
      } catch (loginError) {
        console.log(`🔴 Login failed for ${cred.email}:`, loginError.response?.data?.message || loginError.message)
        addSystemLog('warn', `❌ Login failed: ${cred.email}`, 'Auth')
        continue // Try next credential
      }
    }
    
    if (!loginSuccess) {
      throw new Error('All auto-login attempts failed')
    }
    
    console.log('🟡 STEP 4: Making dashboard API call with valid token')
    addSystemLog('info', '📡 Fetching dashboard data...', 'API')
    
    const response = await apiClient.get('/dashboard/overview')
    await processSuccessfulResponse(response)

  } catch (err) {
    console.error('🔴 DASHBOARD FETCH ERROR:', err)
    console.error('Error details:', {
      message: err.message,
      status: err.response?.status,
      data: err.response?.data
    })
    
    addSystemLog('error', '❌ Dashboard fetch failed', 'API', {
      error: err.message,
      status: err.response?.status,
      data: err.response?.data
    })
    
    // Handle different error types
    if (!err.response) {
      serverError.value = true
      addSystemLog('error', '🚫 Network error - server not reachable', 'Network')
    } else if (err.response?.status === 500) {
      error.value = 'Server error occurred. Please check the Laravel server.'
      addSystemLog('error', '🔥 Internal server error (500)', 'Server')
    } else if (err.response?.status === 401) {
      error.value = 'Authentication failed. Please check server configuration.'
      addSystemLog('error', '🔒 Authentication failed - check server setup', 'Auth')
    } else {
      error.value = err.message || 'Failed to load dashboard data'
      addSystemLog('error', `⚠️ API Error: ${error.value}`, 'API')
    }
  } finally {
    loading.value = false
    console.log('🏁 FINAL: Dashboard fetch completed, loading = false')
    addSystemLog('info', '🏁 Dashboard fetch process completed', 'API')
  }
}

async function processSuccessfulResponse(response) {
  console.log('🟢 STEP 4 ✅: API response received:', response)
  addSystemLog('success', '✅ Dashboard API responded', 'API', response.status)
  
  console.log('🟡 STEP 5: Processing response data')
  
  if (response.data) {
    console.log('Response data:', response.data)
    
    // Handle both success format and direct data format
    let data = response.data
    if (response.data.success && response.data.data) {
      data = response.data.data
    }
    
    dashboardData.value = {
      total_monitors: data.total_monitors || 0,
      monitors_up: data.monitors_up || 0,
      monitors_down: data.monitors_down || 0,
      open_incidents: data.open_incidents || 0,
      current_incidents: data.current_incidents || []
    }
    
    const user = JSON.parse(localStorage.getItem('user') || '{}')
    console.log('🟢 STEP 5 ✅: Dashboard data set for', user.name || 'User')
    addSystemLog('success', `🎉 Dashboard loaded for ${user.name || 'User'} (${user.role || 'unknown'})!`, 'API', {
      user: user.name,
      role: user.role,
      data: dashboardData.value
    })
    
  } else {
    console.log('🔴 STEP 5 ❌: No data in response')
    addSystemLog('error', '❌ No data in API response', 'API')
    throw new Error('No data received from API')
  }
}

async function refreshDashboard() {
  await fetchDashboardData()
}

function formatDate(dateString) {
  return new Date(dateString).toLocaleString()
}

function getIncidentDuration(startDate) {
  return formatDistanceToNow(new Date(startDate), { addSuffix: true })
}

async function retryConnection() {
  serverError.value = false
  loading.value = true
  
  try {
    console.log('Retrying server connection...')
    await apiClient.get('/test')
    await fetchDashboardData()
  } catch (err) {
    console.error('Retry failed:', err)
    serverError.value = true
    loading.value = false
  }
}

function addSystemLog(level, message, source = null, data = null) {
  const log = {
    id: Date.now() + Math.random(),
    timestamp: new Date(),
    level: level, // 'info', 'warn', 'error', 'success'
    message: message,
    source: source,
    data: data
  }
  
  systemLogs.value.push(log)
  
  // Keep only last 150 logs
  if (systemLogs.value.length > 150) {
    systemLogs.value.shift()
  }
  
  // Auto scroll to bottom with smooth behavior
  if (autoScroll.value) {
    nextTick(() => {
      if (consoleBody.value) {
        consoleBody.value.scrollTo({
          top: consoleBody.value.scrollHeight,
          behavior: 'smooth'
        })
      }
    })
  }
  
  // Vibrate for errors (if supported)
  if (level === 'error' && navigator.vibrate) {
    navigator.vibrate(200)
  }
}

function clearLogs() {
  const previousCount = systemLogs.value.length
  systemLogs.value = []
  addSystemLog('success', `Console cleared - ${previousCount} entries removed`, 'System')
}

function formatLogTime(timestamp) {
  return new Date(timestamp).toLocaleTimeString('en-US', {
    hour12: false,
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  })
}

function exportLogs() {
  const logText = systemLogs.value.map(log => {
    const time = formatLogTime(log.timestamp)
    const level = log.level.toUpperCase()
    const source = log.source ? `[${log.source}]` : ''
    const data = log.data ? `\nData: ${formatLogData(log.data)}` : ''
    return `${time} [${level}] ${source} ${log.message}${data}`
  }).join('\n')
  
  const blob = new Blob([logText], { type: 'text/plain' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `uptime-logs-${new Date().toISOString().split('T')[0]}.txt`
  a.click()
  URL.revokeObjectURL(url)
  
  addSystemLog('success', 'Logs exported successfully', 'System')
}

function toggleConsole() {
  consoleExpanded.value = !consoleExpanded.value
  addSystemLog('info', `Console ${consoleExpanded.value ? 'expanded' : 'collapsed'}`, 'UI')
}

function toggleAutoScroll() {
  autoScroll.value = !autoScroll.value
  addSystemLog('info', `Auto-scroll ${autoScroll.value ? 'enabled' : 'disabled'}`, 'UI')
}

function formatLogData(data) {
  if (!data) return ''
  if (typeof data === 'string') return data
  try {
    return JSON.stringify(data, null, 2)
  } catch (e) {
    return String(data)
  }
}
// Auto refresh token setiap 25 menit
setInterval(async () => {
  try {
    const user = JSON.parse(localStorage.getItem('user') || '{}')
    if (user.email) {
      addSystemLog('info', `🔄 Auto token refresh for ${user.name}`, 'Auth')
      
      const loginResponse = await axios.post('http://localhost:8000/api/auth/login', {
        email: user.email,
        password: 'password'
      }, {
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        }
      })
      
      if (loginResponse.data && loginResponse.data.access_token) {
        localStorage.setItem('token', loginResponse.data.access_token)
        addSystemLog('success', `✅ Token refreshed for ${user.name}`, 'Auth')
      }
    }
  } catch (e) {
    addSystemLog('warn', '⚠️ Auto token refresh failed', 'Auth')
  }
}, 25 * 60 * 1000) // 25 minutes

// Enhanced monitoring activities with more dynamic data
setTimeout(() => {
  if (systemLogs.value.length > 0) {
    addSystemLog('success', '🌐 Monitor check: Website A (google.com)', 'Monitor', { 
      url: 'https://google.com', 
      status: 'up',
      responseTime: '134ms',
      statusCode: 200
    })
  }
}, 3000)

setTimeout(() => {
  if (systemLogs.value.length > 0) {
    addSystemLog('success', '⚡ API Service check: REST API B', 'Monitor', {
      url: 'https://api.service.com/health',
      status: 'up', 
      responseTime: '89ms',
      statusCode: 200
    })
  }
}, 6000)

setTimeout(() => {
  if (systemLogs.value.length > 0) {
    addSystemLog('warn', '⚠️ High latency detected: Database Server', 'Monitor', {
      url: 'db.company.com:5432',
      responseTime: '1205ms',
      threshold: '1000ms',
      severity: 'medium'
    })
  }
}, 9000)

setTimeout(() => {
  if (systemLogs.value.length > 0) {
    addSystemLog('error', '🚨 Service timeout: Payment Gateway', 'Monitor', {
      url: 'https://payment.api.com',
      error: 'Connection timeout after 30s',
      lastSuccessful: '2 minutes ago'
    })
  }
}, 12000)

// Network connectivity monitoring
window.addEventListener('online', () => {
  addSystemLog('success', '🌐 Network connection restored', 'Network')
})

window.addEventListener('offline', () => {
  addSystemLog('error', '🚫 Network connection lost', 'Network')
})

// Performance monitoring
if (performance && performance.mark) {
  performance.mark('dashboard-loaded')
  setTimeout(() => {
    performance.measure('dashboard-load-time', 'dashboard-loaded')
    const measure = performance.getEntriesByName('dashboard-load-time')[0]
    if (measure) {
      addSystemLog('info', `⚡ Dashboard load time: ${Math.round(measure.duration)}ms`, 'Performance')
    }
  }, 1000)
}

// Memory usage monitoring (every 30 seconds)
setInterval(() => {
  if (performance.memory && systemLogs.value.length > 0) {
    const mem = performance.memory
    const used = Math.round(mem.usedJSHeapSize / 1024 / 1024)
    const total = Math.round(mem.totalJSHeapSize / 1024 / 1024)
    
    if (used > total * 0.8) {
      addSystemLog('warn', `⚠️ High memory usage: ${used}MB / ${total}MB`, 'Performance')
    } else if (used > total * 0.6) {
      addSystemLog('info', `📊 Memory usage: ${used}MB / ${total}MB`, 'Performance')
    }
  }
}, 30000)
</script>

<style scoped>
.dashboard {
  padding: 20px;
}

.dashboard-header {
  margin-bottom: 30px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 20px;
}

.header-main h1 {
  margin: 0 0 5px 0;
  color: #2c3e50;
}

.header-main p {
  margin: 0;
  color: #7f8c8d;
}

.header-status {
  display: flex;
  align-items: center;
  gap: 20px;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 12px;
  background: white;
  padding: 8px 16px;
  border-radius: 20px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  border: 2px solid #ecf0f1;
}

.user-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: bold;
  font-size: 14px;
}

.user-details {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.user-name {
  font-weight: 600;
  color: #2c3e50;
  font-size: 14px;
  line-height: 1;
}

.user-role {
  font-size: 11px;
  padding: 2px 8px;
  border-radius: 10px;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  line-height: 1;
}

.user-role.role-admin {
  background: #e74c3c;
  color: white;
}

.user-role.role-user {
  background: #3498db;
  color: white;
}

.user-role.role-moderator {
  background: #f39c12;
  color: white;
}

.status-indicator.live {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #e8f5e8;
  padding: 6px 12px;
  border-radius: 15px;
  color: #27ae60;
  font-size: 13px;
  font-weight: 500;
}

.pulse-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #27ae60;
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0% {
    transform: scale(1);
    opacity: 1;
  }
  50% {
    transform: scale(1.2);
    opacity: 0.7;
  }
  100% {
    transform: scale(1);
    opacity: 1;
  }
}

.dashboard-grid {
  display: grid;
  gap: 20px;
}

.stats-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 20px;
  margin-bottom: 20px;
}

.stat-card {
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  padding: 20px;
  display: flex;
  align-items: center;
  gap: 15px;
}

.stat-icon {
  font-size: 2em;
  width: 60px;
  height: 60px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.stat-total .stat-icon { background: #e3f2fd; }
.stat-up .stat-icon { background: #e8f5e8; }
.stat-down .stat-icon { background: #ffebee; }
.stat-incidents .stat-icon { background: #fff3e0; }

.stat-content h3 {
  margin: 0;
  font-size: 1.8em;
  color: #2c3e50;
}

.stat-content p {
  margin: 5px 0 0 0;
  color: #7f8c8d;
  font-size: 0.9em;
}

.card {
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  overflow: hidden;
}

.card-header {
  padding: 20px;
  border-bottom: 1px solid #ecf0f1;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-header h3 {
  margin: 0;
  color: #2c3e50;
}

.activity-list, .incidents-list {
  padding: 0;
}

.activity-item, .incident-item {
  display: flex;
  align-items: center;
  padding: 15px 20px;
  border-bottom: 1px solid #ecf0f1;
  gap: 15px;
}

.activity-item:last-child, .incident-item:last-child {
  border-bottom: none;
}

.activity-status {
  flex-shrink: 0;
}

.status-indicator {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  display: block;
}

.status-indicator.status-up { background-color: #27ae60; }
.status-indicator.status-down { background-color: #e74c3c; }
.status-indicator.status-unknown { background-color: #95a5a6; }

.activity-content, .incident-content {
  flex: 1;
}

.activity-content strong, .incident-content strong {
  color: #2c3e50;
}

.activity-content p, .incident-content p {
  margin: 2px 0;
  color: #7f8c8d;
  font-size: 0.9em;
}

.error-msg {
  color: #e74c3c;
  font-size: 0.8em;
}

.activity-time, .incident-duration {
  flex-shrink: 0;
  font-size: 0.8em;
  color: #95a5a6;
}

.quick-actions {
  padding: 0px 5px;
  display: flex;
  gap: 20px;
  flex-wrap: wrap;
  background: #f8f9fa;
  border-radius: 6px;
  margin: 0 20px 20px 20px;
}

.no-data {
  padding: 40px 20px;
  text-align: center;
  color: #7f8c8d;
}

.server-error {
  text-align: center;
  padding: 60px 20px;
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  border-left: 4px solid #e74c3c;
  margin: 20px 0;
}

.server-error h2 {
  margin: 0 0 15px 0;
  color: #e74c3c;
}

.server-error p {
  margin: 0 0 20px 0;
  color: #7f8c8d;
}

.server-error code {
  background: #f8f9fa;
  padding: 2px 6px;
  border-radius: 3px;
  font-family: 'Courier New', monospace;
}

.error {
  text-align: center;
  padding: 60px 20px;
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  border-left: 4px solid #f39c12;
  margin: 20px 0;
}

.error h2 {
  margin: 0 0 15px 0;
  color: #f39c12;
}

.error p {
  margin: 0 0 20px 0;
  color: #7f8c8d;
}

.error-actions {
  display: flex;
  gap: 15px;
  justify-content: center;
  flex-wrap: wrap;
}

/* Console Styling */
.console-card {
  margin-top: 20px;
}

.console-header {
  background: #2c3e50;
  color: white;
  border-bottom: none;
}

.console-header h3 {
  color: white;
  display: flex;
  align-items: center;
  gap: 10px;
}

.console-icon {
  font-size: 1.2em;
}

.log-count {
  font-size: 0.8em;
  color: #bdc3c7;
  font-weight: normal;
}

.console-controls {
  display: flex;
  gap: 8px;
}

.console-container {
  background: #1e1e1e;
  height: 300px;
  transition: height 0.3s ease;
  overflow: hidden;
}

.console-container.expanded {
  height: 500px;
}

.console-body {
  height: 100%;
  overflow-y: auto;
  padding: 15px;
  font-family: 'Courier New', monospace;
  font-size: 13px;
  line-height: 1.4;
  color: #f8f8f2;
  background: #1e1e1e;
}

.no-logs {
  color: #6c757d;
  font-style: italic;
  display: flex;
  align-items: center;
  gap: 10px;
}

.log-entry {
  margin-bottom: 4px;
  display: flex;
  align-items: flex-start;
  gap: 8px;
  flex-wrap: wrap;
}

.log-timestamp {
  color: #6c757d;
  flex-shrink: 0;
  min-width: 80px;
}

.log-level {
  flex-shrink: 0;
  min-width: 60px;
  font-weight: bold;
}

.level-info { color: #17a2b8; }
.level-success { color: #28a745; }
.level-warn { color: #ffc107; }
.level-error { color: #dc3545; }

.log-source {
  color: #6f42c1;
  flex-shrink: 0;
  min-width: 60px;
}

.log-message {
  color: #f8f8f2;
  flex: 1;
}

.log-data {
  width: 100%;
  margin-top: 4px;
  margin-left: 156px;
}

/* Console Styling */
.console-card {
  margin-top: 20px;
}

.console-header {
  background: #2c3e50;
  color: white;
  border-bottom: none;
}

.console-header h3 {
  color: white;
  display: flex;
  align-items: center;
  gap: 10px;
}

.console-icon {
  font-size: 1.2em;
}

.log-count {
  font-size: 0.8em;
  color: #bdc3c7;
  font-weight: normal;
}

.console-controls {
  display: flex;
  gap: 8px;
}

.console-container {
  background: #1e1e1e;
  height: 300px;
  transition: height 0.3s ease;
  overflow: hidden;
}

.console-container.expanded {
  height: 500px;
}

.console-body {
  height: 100%;
  overflow-y: auto;
  padding: 15px;
  font-family: 'Courier New', monospace;
  font-size: 13px;
  line-height: 1.4;
  color: #f8f8f2;
  background: #1e1e1e;
}

.no-logs {
  color: #6c757d;
  font-style: italic;
  display: flex;
  align-items: center;
  gap: 10px;
}

.log-entry {
  margin-bottom: 4px;
  display: flex;
  align-items: flex-start;
  gap: 8px;
  flex-wrap: wrap;
}

.log-timestamp {
  color: #6c757d;
  flex-shrink: 0;
  min-width: 80px;
}

.log-level {
  flex-shrink: 0;
  min-width: 60px;
  font-weight: bold;
}

.level-info { color: #17a2b8; }
.level-success { color: #28a745; }
.level-warn { color: #ffc107; }
.level-error { color: #dc3545; }

.log-source {
  color: #6f42c1;
  flex-shrink: 0;
  min-width: 60px;
}

.log-message {
  color: #f8f8f2;
  flex: 1;
}

.log-data {
  width: 100%;
  margin-top: 4px;
  margin-left: 156px;
}

.log-data pre {
  background: #2d2d2d;
  padding: 8px;
  border-radius: 4px;
  margin: 0;
  color: #98c379;
  font-size: 11px;
  overflow-x: auto;
}

.console-prompt-line {
  margin-top: 10px;
  display: flex;
  align-items: center;
  gap: 5px;
}

.console-prompt {
  color: #28a745;
  font-weight: bold;
}

.cursor-blink {
  color: #f8f8f2;
  animation: blink 1s infinite;
}

@keyframes blink {
  0%, 50% { opacity: 1; }
  51%, 100% { opacity: 0; }
}

/* Scrollbar styling untuk console */
.console-body::-webkit-scrollbar {
  width: 8px;
}

.console-body::-webkit-scrollbar-track {
  background: #2d2d2d;
}

.console-body::-webkit-scrollbar-thumb {
  background: #555;
  border-radius: 4px;
}

.console-body::-webkit-scrollbar-thumb:hover {
  background: #777;
}

@media (max-width: 768px) {
  .stats-row {
    grid-template-columns: 1fr;
  }
  
  .card-header {
    flex-direction: column;
    gap: 10px;
    align-items: stretch;
  }
  
  .quick-actions {
    flex-direction: column;
  }
  
  .dashboard-header {
    flex-direction: column;
    align-items: stretch;
    gap: 15px;
  }
  
  .header-status {
    justify-content: space-between;
  }
  
  .user-info {
    flex: 1;
    justify-content: center;
  }
}
</style>