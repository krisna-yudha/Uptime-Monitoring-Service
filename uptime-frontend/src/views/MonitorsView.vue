<template>
  <div class="monitors">
    <div class="page-header">
      <div class="header-content">
        <div class="header-main">
          <h1>🖥️ Monitors Dashboard</h1>
          <p>Monitor your services in real-time with advanced analytics</p>
        </div>
        <div class="header-actions">
          <button @click="manualRefresh" class="btn btn-secondary">
            <span>🔄</span> Refresh
          </button>
          <router-link to="/monitors/create" class="btn btn-success">
            <span>➕</span> Add Monitor
          </router-link>
        </div>
      </div>
    </div>

    <div class="form-container">
      <!-- Enhanced Stats Cards -->
      <div class="stats-cards" v-if="!monitorStore.loading">
        <div class="stat-card total" style="animation-delay: 0.1s">
          <div class="stat-icon">
            <span>📊</span>
          </div>
          <div class="stat-content">
            <h3>{{ totalGroups }}</h3>
            <p>Total Groups</p>
          </div>
        </div>
        <div class="stat-card services" style="animation-delay: 0.2s">
          <div class="stat-icon">
            <span>🖥️</span>
          </div>
          <div class="stat-content">
            <h3>{{ filteredMonitors.length }}</h3>
            <p>Total Services</p>
          </div>
        </div>
        <div class="stat-card up" style="animation-delay: 0.3s">
          <div class="stat-icon">
            <span>✅</span>
          </div>
          <div class="stat-content">
            <h3>{{ upMonitors }}</h3>
            <p>Online</p>
          </div>
        </div>
        <div class="stat-card down" style="animation-delay: 0.4s">
          <div class="stat-icon">
            <span>❌</span>
          </div>
          <div class="stat-content">
            <h3>{{ downMonitors }}</h3>
            <p>Offline</p>
          </div>
        </div>
        <div class="stat-card unknown" style="animation-delay: 0.5s">
          <div class="stat-icon">
            <span>❓</span>
          </div>
          <div class="stat-content">
            <h3>{{ unknownMonitors }}</h3>
            <p>Unknown</p>
          </div>
        </div>
        <div class="stat-card health" style="animation-delay: 0.6s" :class="overallHealthClass">
          <div class="stat-icon">
            <span>💚</span>
          </div>
          <div class="stat-content">
            <h3>{{ overallHealth }}%</h3>
            <p>Overall Health</p>
          </div>
        </div>
      </div>

      <!-- Filters and Controls -->
      <div v-if="filtersExpanded" class="filters">
        <div class="filter-group">
          <label for="status-filter" class="form-label">Status:</label>
          <select 
            id="status-filter"
            v-model="filters.status" 
            class="form-control modern-select"
          >
            <option value="">All Status</option>
            <option value="up">Up</option>
            <option value="down">Down</option>
            <option value="unknown">Unknown</option>
            <option value="invalid">Invalid</option>
            <option value="validating">Validating</option>
          </select>
        </div>
        
        <div class="filter-group">
          <label for="type-filter" class="form-label">Type:</label>
          <select 
            id="type-filter"
            v-model="filters.type" 
            class="form-control modern-select"
          >
            <option value="">All Types</option>
            <option value="http">HTTP</option>
            <option value="https">HTTPS</option>
            <option value="tcp">TCP</option>
            <option value="ping">Ping</option>
            <option value="keyword">Keyword</option>
            <option value="push">Push</option>
          </select>
        </div>
        
        <!-- <div class="filter-group">
          <label for="enabled-filter" class="form-label">Enabled:</label>
          <select 
            id="enabled-filter"
            v-model="filters.enabled" 
            @change="fetchData"
            class="form-control modern-select"
          >
            <option value="">All</option>
            <option value="true">Enabled</option>
            <option value="false">Disabled</option>
          </select>
        </div> -->
        
        <!-- <div class="filter-group">
          <label for="group-filter" class="form-label">Group:</label>
          <select 
            id="group-filter"
            v-model="filters.group" 
            @change="fetchData"
            class="form-control modern-select"
          >
            <option value="">All Groups</option>
            <option value="ungrouped">Ungrouped</option>
            <option v-for="group in groups" :key="group.group_name" :value="group.group_name">
              {{ group.group_name }} ({{ group.monitors_count }})
            </option>
          </select>
        </div> -->
        
        <!-- <div class="filter-group search-group">
          <label for="search-filter" class="form-label">Search:</label>
          <div class="search-input-wrapper">
            <input
              id="search-filter"
              v-model="filters.search"
              @input="debounceSearch"
              type="text"
              class="form-control modern-input"
              placeholder="Search monitors..."
            >
            <i class="fas fa-search search-icon"></i>
          </div>
        </div> -->

          <!-- <div class="filter-group">
            <label for="view-mode" class="form-label">View:</label>
            <select 
              id="view-mode"
              v-model="viewMode" 
              @change="fetchData"
              class="form-control modern-select"
            >
              <option value="grouped">📁 Grouped View</option>
              <option value="list">📋 List View</option>
              <option value="grid">🔲 Grid View</option>
            </select>
          </div> -->
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="monitorStore.loading" class="loading">
      Loading monitors...
    </div>

    <!-- Error State -->
    <div v-else-if="monitorStore.error" class="error">
      {{ monitorStore.error }}
    </div>

    <!-- Grid View -->
    <div v-if="viewMode === 'grid' && !monitorStore.loading && !monitorStore.error" class="monitors-grid-view">
      <div
        v-for="monitor in filteredMonitors"
        :key="monitor.id"
        class="monitor-card-grid clickable"
        @click="navigateToDetails(monitor.id)"
        title="Click to view monitor details"
      >
        <div class="monitor-header-grid">
          <div class="monitor-status-large">
            <span 
              class="status-indicator-large"
              :class="{
                'status-up': monitor.last_status === 'up',
                'status-down': monitor.last_status === 'down',
                'status-invalid': monitor.last_status === 'invalid',
                'status-unknown': monitor.last_status === 'unknown',
                'status-paused': monitor.pause_until && new Date(monitor.pause_until) > new Date()
              }"
            >
              <div class="status-pulse"></div>
            </span>
          </div>
          <div class="monitor-info-grid">
            <h4>{{ monitor.name }}</h4>
            <p class="monitor-target-grid">{{ monitor.target }}</p>
            <span v-if="monitor.group_name" class="monitor-group-badge">{{ monitor.group_name }}</span>
          </div>
        </div>
        
        <div class="monitor-stats-grid">
          <div class="stat-item">
            <span class="stat-label">Type:</span>
            <span class="stat-value">{{ monitor.type?.toUpperCase() }}</span>
          </div>
          <div class="stat-item">
            <span class="stat-label">Interval:</span>
            <span class="stat-value">{{ monitor.interval_seconds }}s</span>
          </div>
        </div>
        
        <div class="monitor-actions-grid">
          <button
            @click.stop="visitMonitor(monitor)"
            class="btn btn-info btn-sm"
            :disabled="!isVisitable(monitor)"
            :title="getVisitTooltip(monitor)"
          >
            🌐 Visit
          </button>
          <router-link :to="`/monitors/${monitor.id}`" class="btn btn-primary btn-sm" @click.stop>
            📊 View
          </router-link>
          <router-link :to="`/monitors/${monitor.id}/edit`" class="btn btn-secondary btn-sm" @click.stop>
            ✏️ Edit
          </router-link>
          <button
            @click.stop="deleteMonitor(monitor)"
            class="btn btn-danger btn-sm"
            :disabled="deleting === monitor.id"
          >
            <i v-if="deleting === monitor.id" class="fas fa-spinner fa-spin"></i>
            <i v-else class="fas fa-trash"></i>
          </button>
        </div>
      </div>
    </div>

    <!-- Monitors List View -->
    <div v-if="viewMode === 'list' && !monitorStore.loading && !monitorStore.error" class="monitors-grid">
      <div
        v-for="monitor in filteredMonitors"
        :key="monitor.id"
        class="monitor-card clickable"
        @click="navigateToDetails(monitor.id)"
        title="Click to view monitor details"
      >
        <div class="monitor-header">
          <div class="monitor-info">
            <h3>{{ monitor.name }}</h3>
            <p class="monitor-target">{{ monitor.target }}</p>
            <p v-if="monitor.group_name" class="monitor-group">📁 {{ monitor.group_name }}</p>
          </div>
          <div class="monitor-status">
            <span 
              class="status-badge"
              :class="{
                'status-up': monitor.last_status === 'up',
                'status-down': monitor.last_status === 'down',
                'status-invalid': monitor.last_status === 'invalid',
                'status-validating': monitor.last_status === 'validating',
                'status-unknown': monitor.last_status === 'unknown'
              }"
              @mouseenter="showErrorTooltip($event, monitor)"
              @mouseleave="hideErrorTooltip()"
            >
              {{ monitor.last_status?.toUpperCase() || 'UNKNOWN' }}
            </span>
          </div>
        </div>

        <div class="monitor-details">
          <div class="monitor-meta">
            <span class="monitor-type">{{ monitor.type.toUpperCase() }}</span>
            <span class="monitor-interval">{{ monitor.interval_seconds }}s interval</span>
            <span class="monitor-last-check">
              Last check: {{ formatLastCheck(monitor.last_checked_at) }}
            </span>
          </div>
          
          <div class="monitor-stats">
            <div class="stat-item">
              <span class="stat-label">Response</span>
              <span 
                class="stat-value response"
                :class="getResponseClass(monitor.last_response_time)"
              >
                {{ monitor.last_response_time ? `${monitor.last_response_time}ms` : 'N/A' }}
              </span>
            </div>
            <div class="stat-item">
              <span class="stat-label">Uptime</span>
              <span class="stat-value uptime">{{ getUptimeDisplay(monitor) }}</span>
            </div>
            <div class="stat-item">
              <span class="stat-label">Last Check</span>
              <span class="stat-value last-check">{{ formatLastCheck(monitor.last_checked_at) }}</span>
            </div>
          </div>
        </div>

        <div class="monitor-actions">
          <button
            @click.stop="visitMonitor(monitor)"
            class="btn btn-info btn-sm"
            :disabled="!isVisitable(monitor)"
            :title="getVisitTooltip(monitor)"
          >
            🌐 Visit
          </button>
          <router-link :to="`/monitors/${monitor.id}`" class="btn btn-primary btn-sm" @click.stop>
            📊 View
          </router-link>
          
          <router-link :to="`/monitors/${monitor.id}/edit`" class="btn btn-secondary btn-sm" @click.stop>
            ✏️ Edit
          </router-link>
          
          <button
            v-if="monitor.pause_until && new Date(monitor.pause_until) > new Date()"
            @click.stop="resumeMonitor(monitor)"
            class="btn btn-success btn-sm"
          >
            Resume
          </button>
          
          <button
            v-else
            @click.stop="pauseMonitor(monitor)"
            class="btn btn-warning btn-sm"
          >
            Pause
          </button>
          
          <button
            @click.stop="deleteMonitor(monitor)"
            class="btn btn-danger btn-sm"
            :disabled="deleting === monitor.id"
          >
            <span v-if="deleting === monitor.id">Deleting...</span>
            <span v-else>Delete</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Enhanced Grouped View -->
    <div v-if="viewMode === 'grouped' && !monitorStore.loading && !monitorStore.error" class="grouped-view">
      <div v-for="(groupData, groupName) in groupedMonitors" :key="groupName" class="group-section">
        <div class="group-header">
          <div class="group-title-section">
            <h2>
              <span v-if="groupName === 'Ungrouped'" class="group-icon">📂</span>
              <span v-else class="group-icon">📁</span>
              {{ groupName }}
              <span class="monitor-count">({{ groupData.monitors.length }})</span>
            </h2>
            <p v-if="groupData.description" class="group-description">{{ groupData.description }}</p>
          </div>
          
          <div class="group-stats-enhanced">
            <div class="stat-item">
              <span class="stat-number up">{{ getGroupUpCount(groupData.monitors) }}</span>
              <span class="stat-label">Online</span>
            </div>
            <div class="stat-item">
              <span class="stat-number down">{{ getGroupDownCount(groupData.monitors) }}</span>
              <span class="stat-label">Offline</span>
            </div>
            <div class="stat-item health">
              <span 
                class="stat-number health-badge"
                :class="getGroupHealthClass(groupData.monitors)"
              >
                {{ getGroupHealth(groupData.monitors) }}%
              </span>
              <span class="stat-label">Health</span>
            </div>
          </div>
        </div>

        <div class="group-monitors">
          <div
            v-for="monitor in groupData.monitors"
            :key="monitor.id"
            class="monitor-card compact clickable"
            @click="navigateToDetails(monitor.id)"
            title="Click to view monitor details"
          >
            <div class="monitor-header">
              <div class="monitor-info">
                <h4>{{ monitor.name }}</h4>
                <p class="monitor-target">{{ monitor.target }}</p>
              </div>
              <div class="monitor-status">
                <span 
                  class="status-badge"
                  :class="{
                    'status-up': monitor.last_status === 'up',
                    'status-down': monitor.last_status === 'down',
                    'status-invalid': monitor.last_status === 'invalid',
                    'status-validating': monitor.last_status === 'validating',
                    'status-unknown': monitor.last_status === 'unknown'
                  }"
                >
                  {{ monitor.last_status?.toUpperCase() || 'UNKNOWN' }}
                </span>
              </div>
            </div>

            <div class="monitor-actions compact">
              <button
                @click.stop="visitMonitor(monitor)"
                class="btn btn-info btn-sm"
                :disabled="!isVisitable(monitor)"
                :title="getVisitTooltip(monitor)"
              >
                🌐 Visit
              </button>
              <router-link :to="`/monitors/${monitor.id}`" class="btn btn-primary btn-sm" @click.stop>
                📊 View
              </router-link>
              <router-link :to="`/monitors/${monitor.id}/edit`" class="btn btn-secondary btn-sm" @click.stop>
                ✏️ Edit
              </router-link>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Empty State -->
    <div v-if="!monitorStore.loading && !monitorStore.error && (!filteredMonitors.length && !Object.keys(groupedMonitors).length)" class="empty-state">
      <div class="empty-icon">📊</div>
      <h3 v-if="Object.values(filters).some(f => f && f !== '')">No monitors match your filters</h3>
      <h3 v-else>No monitors found</h3>
      <p v-if="Object.values(filters).some(f => f && f !== '')">Try adjusting your search criteria</p>
      <p v-else>Get started by creating your first monitor</p>
      <div v-if="Object.values(filters).some(f => f && f !== '')" class="empty-actions">
        <button @click="clearFilters" class="btn btn-secondary">Clear Filters</button>
      </div>
      <router-link v-else to="/monitors/create" class="btn btn-success">
        Create Monitor
      </router-link>
    </div>
  </div>

  <!-- Pause Modal -->
  <div v-if="showPauseModal" class="modal-overlay" @click="showPauseModal = false">
    <div class="modal" @click.stop>
      <div class="modal-header">
        <h3>Pause Monitor</h3>
        <button @click="showPauseModal = false" class="btn-close">×</button>
      </div>
      
      <div class="modal-body">
        <p>How long would you like to pause <strong>{{ selectedMonitor?.name }}</strong>?</p>
        
        <div class="form-group">
          <label for="pause-duration" class="form-label">Duration (minutes):</label>
          <input
            id="pause-duration"
            v-model="pauseDuration"
            type="number"
            class="form-control"
            min="1"
            max="10080"
            placeholder="60"
          >
        </div>
      </div>
      
      <div class="modal-footer">
        <button @click="showPauseModal = false" class="btn btn-secondary">Cancel</button>
        <button @click="confirmPause" class="btn btn-warning">Pause Monitor</button>
      </div>
    </div>
  </div>

  <!-- Error tooltip -->
  <div class="tooltip" ref="tooltip" style="display: none;">
    <div class="tooltip-content">
      <span class="tooltip-text"></span>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, reactive, computed, watch } from 'vue'
import { useMonitorStore } from '../stores/monitors'
import { useRouter } from 'vue-router'

const monitorStore = useMonitorStore()
const router = useRouter()

const filters = reactive({
  status: '',
  type: '',
  enabled: '',
  group: '',
  search: ''
})

const viewMode = ref('grouped')
const groups = ref([])
const groupedMonitors = ref({})

const showPauseModal = ref(false)
const selectedMonitor = ref(null)
const pauseDuration = ref(60)
const deleting = ref(null)
const refreshInterval = ref(null)
const lastUpdate = ref(null)
const filtersExpanded = ref(true)

let searchTimeout = null

// Computed properties untuk stats
const upMonitors = computed(() => {
  return filteredMonitors.value.filter(monitor => monitor.last_status === 'up').length
})

const downMonitors = computed(() => {
  return filteredMonitors.value.filter(monitor => monitor.last_status === 'down').length
})

const unknownMonitors = computed(() => {
  return filteredMonitors.value.filter(monitor => 
    monitor.last_status === 'unknown' || !monitor.last_status
  ).length
})

const totalGroups = computed(() => {
  return Object.keys(groupedMonitors.value).length
})

const overallHealth = computed(() => {
  if (filteredMonitors.value.length === 0) return 0
  const upCount = upMonitors.value
  return Math.round((upCount / filteredMonitors.value.length) * 100)
})

const overallHealthClass = computed(() => {
  const health = overallHealth.value
  if (health >= 95) return 'health-excellent'
  if (health >= 80) return 'health-good'
  if (health >= 50) return 'health-warning'
  return 'health-critical'
})

// Computed property for client-side filtering
const filteredMonitors = computed(() => {
  if (!monitorStore.monitors || monitorStore.monitors.length === 0) {
    return []
  }
  
  let result = [...monitorStore.monitors] // Create a copy to avoid mutation
  
  // Apply status filter
  if (filters.status && filters.status !== '') {
    result = result.filter(monitor => {
      const status = monitor.last_status?.toLowerCase()
      const filterStatus = filters.status.toLowerCase()
      return status === filterStatus
    })
  }
  
  // Apply type filter
  if (filters.type && filters.type !== '') {
    result = result.filter(monitor => {
      const type = monitor.type?.toLowerCase()
      const filterType = filters.type.toLowerCase() 
      return type === filterType
    })
  }
  
  // Apply enabled filter
  if (filters.enabled && filters.enabled !== '') {
    const isEnabled = filters.enabled === 'true'
    result = result.filter(monitor => monitor.enabled === isEnabled)
  }
  
  // Apply group filter
  if (filters.group && filters.group !== '') {
    if (filters.group === 'ungrouped') {
      result = result.filter(monitor => !monitor.group_name)
    } else {
      result = result.filter(monitor => monitor.group_name === filters.group)
    }
  }
  
  // Apply search filter
  if (filters.search && filters.search.trim() !== '') {
    const searchTerm = filters.search.toLowerCase()
    result = result.filter(monitor => {
      const nameMatch = monitor.name?.toLowerCase().includes(searchTerm)
      const targetMatch = monitor.target?.toLowerCase().includes(searchTerm)
      const groupMatch = monitor.group_name && monitor.group_name.toLowerCase().includes(searchTerm)
      return nameMatch || targetMatch || groupMatch
    })
  }
  
  return result
})

// Helper functions for groups
function getGroupUpCount(monitors) {
  return monitors.filter(m => m.last_status === 'up').length
}

function getGroupDownCount(monitors) {
  return monitors.filter(m => m.last_status === 'down').length
}

// Helper functions
function getResponseClass(responseTime) {
  if (!responseTime) return 'no-data'
  if (responseTime < 200) return 'excellent'
  if (responseTime < 500) return 'good'
  if (responseTime < 1000) return 'fair'
  return 'poor'
}

function getUptimeDisplay(monitor) {
  // This would come from actual uptime calculation
  const uptimePercentage = Math.floor(Math.random() * 5) + 95 // Simulate 95-100%
  return `${uptimePercentage}%`
}

function formatLastCheck(timestamp) {
  if (!timestamp) return 'Never'
  const now = new Date()
  const checkTime = new Date(timestamp)
  const diffMs = now - checkTime
  const diffMins = Math.floor(diffMs / 60000)
  
  if (diffMins < 1) return 'Just now'
  if (diffMins < 60) return `${diffMins}m ago`
  const diffHours = Math.floor(diffMins / 60)
  if (diffHours < 24) return `${diffHours}h ago`
  const diffDays = Math.floor(diffHours / 24)
  return `${diffDays}d ago`
}

function navigateToDetails(monitorId) {
  router.push(`/monitors/${monitorId}`)
}

function visitMonitor(monitor) {
  if (!isVisitable(monitor)) return
  
  let url = monitor.target
  if (!url.startsWith('http://') && !url.startsWith('https://')) {
    url = `https://${url}`
  }
  
  window.open(url, '_blank')
}

function isVisitable(monitor) {
  return monitor.type === 'http' || monitor.type === 'https' || monitor.type === 'keyword'
}

function getVisitTooltip(monitor) {
  if (!isVisitable(monitor)) {
    return `Cannot visit ${monitor.type.toUpperCase()} monitor`
  }
  return `Visit ${monitor.target}`
}

async function fetchData() {
  try {
    // Always fetch ALL monitors without any filters
    // Filtering will be handled by the computed property
    await monitorStore.fetchMonitors()
    
    // If in grouped view, also fetch grouped data
    if (viewMode.value === 'grouped') {
      await fetchGroupedMonitors()
    }
  } catch (error) {
    console.error('Error in fetchData:', error)
  }
}

async function fetchGroups() {
  try {
    const response = await monitorStore.getGroups()
    groups.value = response.data || []
  } catch (error) {
    console.error('Failed to fetch groups:', error)
  }
}

async function fetchGroupedMonitors() {
  try {
    const params = {}
    
    if (filters.status) params.status = filters.status
    if (filters.type) params.type = filters.type
    if (filters.enabled) params.enabled = filters.enabled
    if (filters.group) params.group = filters.group
    if (filters.search) params.search = filters.search
    
    console.log('Fetching grouped monitors with params:', params)
    const response = await monitorStore.getGroupedMonitors(params)
    groupedMonitors.value = response.data || {}
  } catch (error) {
    console.error('Failed to fetch grouped monitors:', error)
  }
}

async function fetchMonitors() {
  try {
    const params = {}
    
    if (filters.status) params.status = filters.status
    if (filters.type) params.type = filters.type
    if (filters.enabled) params.enabled = filters.enabled
    if (filters.group) params.group = filters.group
    if (filters.search) params.search = filters.search
    
    console.log('Fetching monitors with params:', params)
    await monitorStore.fetchMonitors(params)
    console.log('Monitors fetched:', monitorStore.monitors.length, 'items')
  } catch (error) {
    console.error('Failed to fetch monitors:', error)
  }
}

// Silent fetch without changing loading state
async function fetchMonitorsSilently() {
  try {
    const params = {}
    
    if (filters.status) params.status = filters.status
    if (filters.type) params.type = filters.type
    if (filters.enabled) params.enabled = filters.enabled
    if (filters.group) params.group = filters.group
    if (filters.search) params.search = filters.search
    
    await monitorStore.fetchMonitorsSilently(params)
    
    // Also fetch grouped data silently if in grouped view
    if (viewMode.value === 'grouped') {
      const response = await monitorStore.getGroupedMonitors(params)
      groupedMonitors.value = response.data || {}
    }
    
    lastUpdate.value = new Date()
  } catch (error) {
    console.warn('Silent fetch failed:', error)
  }
}

function debounceSearch() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    fetchData()
  }, 500)
}

function pauseMonitor(monitor) {
  selectedMonitor.value = monitor
  showPauseModal.value = true
}

async function confirmPause() {
  if (!selectedMonitor.value) return
  
  try {
    await monitorStore.pauseMonitor(selectedMonitor.value.id, pauseDuration.value)
    showPauseModal.value = false
    selectedMonitor.value = null
    pauseDuration.value = 60
    await fetchData()
  } catch (error) {
    console.error('Failed to pause monitor:', error)
  }
}

async function resumeMonitor(monitor) {
  try {
    await monitorStore.resumeMonitor(monitor.id)
    await fetchData()
  } catch (error) {
    console.error('Failed to resume monitor:', error)
  }
}

async function deleteMonitor(monitor) {
  if (!confirm(`Are you sure you want to delete "${monitor.name}"?`)) {
    return
  }
  
  deleting.value = monitor.id
  
  try {
    await monitorStore.deleteMonitor(monitor.id)
    await fetchData()
  } catch (error) {
    console.error('Failed to delete monitor:', error)
  } finally {
    deleting.value = null
  }
}

async function manualRefresh() {
  await fetchData()
  lastUpdate.value = new Date()
}

function startAutoRefresh() {
  refreshInterval.value = setInterval(() => {
    fetchMonitorsSilently()
  }, 30000) // Refresh every 30 seconds
}

function stopAutoRefresh() {
  if (refreshInterval.value) {
    clearInterval(refreshInterval.value)
    refreshInterval.value = null
  }
}

function showErrorTooltip(event, monitor) {
  if (monitor.last_status === 'down' && monitor.error_message) {
    const tooltip = document.querySelector('.tooltip')
    const tooltipText = tooltip.querySelector('.tooltip-text')
    
    tooltipText.textContent = `Error: ${monitor.error_message}`
    tooltip.style.display = 'block'
    tooltip.style.left = `${event.pageX + 10}px`
    tooltip.style.top = `${event.pageY + 10}px`
  }
}

function hideErrorTooltip() {
  const tooltip = document.querySelector('.tooltip')
  if (tooltip) {
    tooltip.style.display = 'none'
  }
}

function getGroupHealth(monitors) {
  if (!monitors.length) return 0
  const upCount = monitors.filter(m => m.last_status === 'up').length
  return Math.round((upCount / monitors.length) * 100)
}

function getGroupHealthClass(monitors) {
  const health = getGroupHealth(monitors)
  
  if (health >= 95) return 'health-excellent'
  if (health >= 80) return 'health-good'
  if (health >= 50) return 'health-warning'
  return 'health-critical'
}

function clearFilters() {
  filters.status = ''
  filters.type = ''
  filters.enabled = ''
  filters.group = ''
  filters.search = ''
}

// Lifecycle
onMounted(async () => {
  await fetchGroups()
  await fetchData()
  startAutoRefresh()
})

// Watch filters for reactivity
watch(
  () => [filters.status, filters.type, filters.enabled, filters.group, filters.search],
  () => {
    // Computed property automatically handles filtering
  },
  { deep: true }
)

onUnmounted(() => {
  stopAutoRefresh()
})
</script>

<style scoped>
/* Basic Layout */
.monitors {
  padding: 20px;
  min-height: calc(100vh - 80px);
}

.page-header {
  margin-bottom: 30px;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 20px;
}

.header-main h1 {
  margin: 0 0 5px 0;
  color: #2c3e50;
  font-size: 2em;
}

.header-main p {
  margin: 0;
  color: #7f8c8d;
  font-size: 1.1em;
}

.header-actions {
  display: flex;
  gap: 15px;
  align-items: center;
}

.form-container {
  background: white;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  overflow: hidden;
  margin-bottom: 32px;
  padding: 24px;
  border: 1px solid rgba(0, 0, 0, 0.05);
}

/* Stats Cards */
.stats-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 16px;
  margin-bottom: 24px;
}

.stat-card {
  background: white;
  border-radius: 12px;
  padding: 24px 20px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  border: 1px solid rgba(0, 0, 0, 0.05);
  display: flex;
  align-items: center;
  gap: 16px;
  transition: all 0.3s ease;
  animation: fadeInUp 0.5s ease forwards;
  opacity: 0;
  transform: translateY(20px);
  position: relative;
  overflow: hidden;
}

.stat-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 32px rgba(0, 0, 0, 0.12);
  border-color: rgba(0, 0, 0, 0.1);
}

.stat-icon {
  font-size: 2.2em;
  line-height: 1;
  width: 56px;
  height: 56px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 12px;
  background: rgba(0, 0, 0, 0.03);
  flex-shrink: 0;
}

.stat-content h3 {
  margin: 0;
  font-size: 1.75em;
  font-weight: 700;
  color: #2c3e50;
  line-height: 1.2;
}

.stat-content p {
  margin: 5px 0 0 0;
  color: #7f8c8d;
  font-weight: 500;
}

.stat-card.services {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.stat-card.health.health-excellent {
  background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
  color: white;
}

.stat-card.health.health-good {
  background: linear-gradient(135deg, #f7971e 0%, #ffd200 100%);
  color: white;
}

.stat-card.health.health-warning {
  background: linear-gradient(135deg, #f12711 0%, #f5af19 100%);
  color: white;
}

.stat-card.health.health-critical {
  background: linear-gradient(135deg, #c94b4b 0%, #4b134f 100%);
  color: white;
}

.form-container {
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  overflow: hidden;
  margin-bottom: 30px;
}

/* Filters */
.filters {
  background: #f8f9fa;
  padding: 24px;
  border: 1px solid #e9ecef;
  border-radius: 12px;
  margin-bottom: 24px;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 20px;
  align-items: end;
}

.view-mode-toggle {
  display: flex;
  gap: 5px;
  border-radius: 8px;
  background: #f8f9fa;
  padding: 4px;
}

.toggle-btn {
  background: transparent;
  border: none;
  padding: 8px 12px;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s ease;
  font-size: 1.2em;
}

.toggle-btn:hover {
  background: #e9ecef;
}

.toggle-btn.active {
  background: #007bff;
  color: white;
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(0, 123, 255, 0.3);
}

/* Grid View */
.monitors-grid-view {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 16px;
  margin-top: 16px;
}

.monitor-card-grid {
  background: white;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
  border: 1px solid rgba(0, 0, 0, 0.04);
  transition: all 0.3s ease;
  animation: fadeInUp 0.5s ease forwards;
  opacity: 0;
  transform: translateY(20px);
}

.monitor-card-grid.clickable {
  cursor: pointer;
}

.monitor-card-grid.clickable:hover {
  transform: translateY(-5px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
  border-color: #667eea;
}

.monitor-header-grid {
  display: flex;
  align-items: center;
  gap: 15px;
  margin-bottom: 15px;
}

.status-indicator-large {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  position: relative;
  display: inline-block;
}

.status-indicator-large.status-up {
  background-color: #28a745;
  box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.2);
}

.status-indicator-large.status-down {
  background-color: #dc3545;
  box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.2);
}

.status-indicator-large.status-unknown {
  background-color: #6c757d;
  box-shadow: 0 0 0 3px rgba(108, 117, 125, 0.2);
}

.monitor-info-grid h4 {
  margin: 0 0 5px 0;
  color: #2c3e50;
  font-size: 1.1em;
  font-weight: 600;
}

.monitor-target-grid {
  margin: 0 0 8px 0;
  color: #6c757d;
  font-size: 0.9em;
  word-break: break-all;
}

.monitor-group-badge {
  background: #e9ecef;
  color: #495057;
  font-size: 0.8em;
  padding: 3px 8px;
  border-radius: 4px;
  font-weight: 500;
}

.monitor-stats-grid {
  display: flex;
  justify-content: space-between;
  margin-bottom: 15px;
  padding: 10px;
  background: #f8f9fa;
  border-radius: 6px;
}

.stat-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
}

.stat-label {
  font-size: 0.75em;
  color: #6c757d;
  text-transform: uppercase;
  font-weight: 500;
}

.stat-value {
  font-size: 0.85em;
  font-weight: 600;
  color: #495057;
}

.monitor-actions-grid {
  display: flex;
  gap: 12px;
  justify-content: center;
  flex-wrap: wrap;
}

/* List View */
.monitors-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(420px, 1fr));
  gap: 16px;
}

.monitor-card {
  background: white;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  border: 1px solid rgba(0, 0, 0, 0.05);
  transition: all 0.3s ease;
  margin-bottom: 16px;
}

.monitor-card.clickable {
  cursor: pointer;
}

.monitor-card.clickable:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
  border-color: #667eea;
}

/* Monitor Actions */
.monitor-actions {
  display: flex;
  gap: 12px;
  justify-content: flex-start;
  flex-wrap: wrap;
  margin-top: 16px;
}

.monitor-actions.compact {
  gap: 10px;
  justify-content: center;
  margin-top: 12px;
}

/* Grouped View */
.grouped-view {
  margin-top: 20px;
}

.group-section {
  margin-bottom: 40px;
}

.group-header {
  background: linear-gradient(135deg, #f8f9ff 0%, #f0f3ff 100%);
  color: #2c3e50;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  border: 1px solid #e3e8f5;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 20px;
  margin-bottom: 20px;
  padding: 18px 24px;
  position: relative;
  overflow: hidden;
}

.group-title-section h2 {
  margin: 0 0 5px 0;
  font-size: 1.3em;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 8px;
  color: #2c3e50;
}

.monitor-count {
  background: rgba(44, 62, 80, 0.1);
  color: #2c3e50;
  padding: 3px 8px;
  border-radius: 10px;
  font-size: 0.75em;
  font-weight: 500;
  margin-left: 6px;
}

.group-description {
  margin: 0;
  color: #6c757d;
  font-style: italic;
  font-size: 0.9em;
}

.group-stats-enhanced {
  display: flex;
  gap: 20px;
  align-items: center;
}

.group-stats-enhanced .stat-item {
  text-align: center;
}

.stat-number {
  display: block;
  font-size: 1.5em;
  font-weight: bold;
  margin-bottom: 2px;
}

.stat-number.up {
  color: #28a745;
}

.stat-number.down {
  color: #dc3545;
}

.health-badge {
  background: rgba(44, 62, 80, 0.08);
  color: #2c3e50;
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 0.9em;
  font-weight: 600;
}

.health-badge.health-excellent {
  background: rgba(40, 167, 69, 0.1);
  color: #28a745;
}

.health-badge.health-good {
  background: rgba(255, 193, 7, 0.1);
  color: #ffc107;
}

.health-badge.health-warning {
  background: rgba(255, 87, 34, 0.1);
  color: #ff5722;
}

.health-badge.health-critical {
  background: rgba(244, 67, 54, 0.1);
  color: #f44336;
}

.group-monitors {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 16px;
}

.monitor-card.compact {
  padding: 20px;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
  border: 1px solid rgba(0, 0, 0, 0.04);
  transition: all 0.3s ease;
}

.monitor-card.compact.clickable {
  cursor: pointer;
}

.monitor-card.compact.clickable:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
  border-color: #667eea;
}

/* Status Badges */
.status-badge {
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 0.8em;
  font-weight: bold;
  text-transform: uppercase;
}

.status-badge.status-up {
  background-color: #d4edda;
  color: #155724;
}

.status-badge.status-down {
  background-color: #f8d7da;
  color: #721c24;
}

.status-badge.status-unknown {
  background-color: #e9ecef;
  color: #495057;
}

/* Buttons */
.btn {
  padding: 8px 16px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-weight: 500;
  transition: all 0.2s ease;
}

.btn-sm {
  padding: 4px 8px;
  font-size: 0.85em;
}

.btn-primary {
  background-color: #007bff;
  color: white;
}

.btn-secondary {
  background-color: #6c757d;
  color: white;
}

.btn-warning {
  background-color: #ffc107;
  color: #212529;
}

.btn-danger {
  background-color: #dc3545;
  color: white;
}

.btn-success {
  background-color: #28a745;
  color: white;
}

.btn-info {
  background-color: #17a2b8;
  color: white;
}

/* Form Controls */
.form-control {
  width: 100%;
  padding: 8px 12px;
  border: 1px solid #ced4da;
  border-radius: 4px;
  font-size: 0.9em;
}

.form-label {
  display: block;
  margin-bottom: 5px;
  font-weight: 500;
  color: #495057;
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 40px 20px;
  color: #7f8c8d;
}

.empty-icon {
  font-size: 4em;
  margin-bottom: 20px;
}

.empty-actions {
  margin-top: 20px;
}

/* Animations */
@keyframes fadeInUp {
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Modal */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0,0,0,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal {
  background: white;
  border-radius: 8px;
  width: 90%;
  max-width: 500px;
  max-height: 90vh;
  overflow-y: auto;
}

.modal-header {
  padding: 20px 25px;
  border-bottom: 1px solid #e9ecef;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-body {
  padding: 25px;
}

.modal-footer {
  padding: 15px 25px;
  border-top: 1px solid #e9ecef;
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

/* Responsive */
@media (max-width: 768px) {
  .header-content {
    flex-direction: column;
    align-items: stretch;
  }
  
  .header-actions {
    flex-direction: column-reverse;
  }
  
  .filters {
    grid-template-columns: 1fr;
    padding: 20px;
  }
  
  .monitors-grid {
    grid-template-columns: 1fr;
  }
  
  .group-header {
    flex-direction: column;
    text-align: center;
    padding: 20px;
  }
  
  .monitors {
    padding: 10px;
  }
}
</style>