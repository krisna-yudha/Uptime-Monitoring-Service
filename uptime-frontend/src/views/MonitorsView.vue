<template>
  <div class="monitors">
    <div class="page-header">
      <div class="header-content">
        <div class="header-main">
          <h1>Monitors Dashboard</h1>
          <!-- <p>Monitor your services in real-time with advanced analytics</p> -->
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
            <h3>{{ totalMonitors }}</h3>
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
          <label for="search-filter" class="form-label">Search:</label>
          <input
        id="search-filter"
        v-model="filters.search"
        type="text"
        class="form-control"
        placeholder="Search by name, target, or group..."
          >
        </div>

        <div class="filter-group">
          <label for="group-filter" class="form-label">Group:</label>
          <select 
        id="group-filter"
        v-model="filters.group" 
        class="form-control modern-select"
          >
        <option value="">All Groups</option>
        <option value="ungrouped">Ungrouped</option>
        <option v-for="group in groupFilterOptions" :key="group.name" :value="group.name">
          {{ group.name }} ({{ group.count }})
        </option>
          </select>
          
        </div>
         <div class="filter-group">
          <label for="groups-perpage" class="form-label">Groups / page</label>
          <select id="groups-perpage" v-model.number="groupsPerPage" class="form-control groups-perpage-select">
            <option :value="5">5</option>
            <option :value="10">10</option>
            <option :value="20">20</option>
            <option :value="100">100</option>
          </select>
        </div>
        
        <!-- <div class="filter-group">
          <label for="view-mode" class="form-label">View Mode:</label>
          <div class="view-mode-toggle">
        <button
          @click="viewMode = 'list'"
          class="toggle-btn"
          :class="{ active: viewMode === 'list' }"
          title="List View"
        >
          📋
        </button>
        <button
          @click="viewMode = 'grid'"
          class="toggle-btn"
          :class="{ active: viewMode === 'grid' }"
          title="Grid View"
        >
          ▦
        </button>
        <button
          @click="viewMode = 'grouped'"
          class="toggle-btn"
          :class="{ active: viewMode === 'grouped' }"
          title="Grouped View"
        >
          📁
        </button>
              // reset pagination when filters change
              currentPage.value = 1
              Object.keys(groupPages).forEach(k => { groupPages[k] = 1 })
              // Trigger data fetch when filters change
              if (viewMode.value === 'grouped') {
                fetchGroupedMonitors()
              } else {
                fetchData()
              }
            id="type-filter"
            v-model="filters.type" 
            class="form-control modern-select"
          >
            <option value="">All Types</option>
            <option value="http">HTTP</option>
              // reset pagination when changing modes
              currentPage.value = 1
              if (newMode === 'grouped') {
                fetchGroupedMonitors()
              }
            <option value="keyword">Keyword</option>
            <option value="push">Push</option>
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
        v-for="monitor in paginatedMonitors"
        :key="monitor.id"
        class="monitor-card-grid clickable"
        @click="navigateToDetails(monitor.id)"
        title="Click to view monitor details"
      >
        <div class="monitor-header-grid">
          <div class="monitor-status-large">
            <span
              class="status-indicator-large"
              @mouseenter="showErrorTooltip($event, monitor)"
              @mouseleave="hideErrorTooltip()"
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

    <!-- Pagination for grid/list
    <div v-if="!monitorStore.loading && !monitorStore.error && filteredMonitors.length" class="pagination-bar">
      <div class="pagination-info">
        Menampilkan {{ filteredMonitors.length ? ((currentPage - 1) * pageSize + 1) : 0 }} - {{ Math.min(currentPage * pageSize, filteredMonitors.length) }} dari {{ filteredMonitors.length }}
      </div>
      <div class="pagination-controls">
        <button class="btn btn-sm" :disabled="currentPage === 1" @click.stop="prevPage">Prev</button>
        <button
          v-for="p in totalPages"
          :key="p"
          class="btn btn-sm"
          :class="{ 'active-page': p === currentPage }"
          @click.stop="gotoPage(p)"
        >{{ p }}</button>
        <button class="btn btn-sm" :disabled="currentPage === totalPages" @click.stop="nextPage">Next</button>
        <select v-model.number="pageSize" @change="changePageSize(pageSize)" class="page-size-select">
          <option v-for="s in pageSizes" :key="s" :value="s">{{ s }} / page</option>
        </select>
      </div>
    </div> -->

    <!-- Monitors List View -->
    <div v-if="viewMode === 'list' && !monitorStore.loading && !monitorStore.error" class="monitors-grid">
      <div
        v-for="monitor in paginatedMonitors"
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
                  @mouseenter="showErrorTooltip($event, monitor)"
                  @mouseleave="hideErrorTooltip()"
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
      <div v-for="groupName in paginatedGroupKeys" :key="groupName" class="group-section">
        <div class="group-header-wrapper">
          <div
            class="group-header clickable-header"
            role="button"
            tabindex="0"
            @click="navigateToGroup(groupName)"
            @keyup.enter="navigateToGroup(groupName)"
            @keyup.space.prevent="navigateToGroup(groupName)"
          >
            <div class="group-title-section">
                <h2 class="group-title">
                  <span v-if="groupName === 'Ungrouped'" class="group-icon">📂</span>
                  <span v-else class="group-icon">📁</span>
                  {{ groupName }}
                  <span class="monitor-count">({{ getFilteredGroupMonitors(filteredGroupedMonitors[groupName].monitors, groupName).length }})</span>
                </h2>
              <p v-if="filteredGroupedMonitors[groupName].description" class="group-description">{{ filteredGroupedMonitors[groupName].description }}</p>
            </div>
          
          <div class="group-stats-enhanced">
            <div class="stat-item">
              <span class="stat-number up">{{ getGroupUpCount(getFilteredGroupMonitors(filteredGroupedMonitors[groupName].monitors, groupName)) }}</span>
              <span class="stat-label">Online</span>
            </div>
            <div class="stat-item">
              <span class="stat-number down">{{ getGroupDownCount(getFilteredGroupMonitors(filteredGroupedMonitors[groupName].monitors, groupName)) }}</span>
              <span class="stat-label">Offline</span>
            </div>
            <div class="stat-item health">
              <span 
                class="stat-number health-badge"
                :class="getGroupHealthClass(getFilteredGroupMonitors(filteredGroupedMonitors[groupName].monitors, groupName))"
              >
                {{ getGroupHealth(getFilteredGroupMonitors(filteredGroupedMonitors[groupName].monitors, groupName)) }}%
              </span>
              <span class="stat-label">Health</span>
            </div>
          </div>
        </div>
          
          <!-- Edit Group Button -->
          <!-- <button 
            v-if="groupName !== 'Ungrouped'"
            @click.stop="openEditGroupModal(groupName, filteredGroupedMonitors[groupName])"
            class="btn-edit-group"
            title="Edit group name and description"
          >
            ✏️
          </button> -->
        </div>

        <!-- Group Search
        <div class="group-search">
          <input
            v-model="groupSearches[groupName]"
            type="text"
            class="form-control group-search-input"
            :placeholder="`Search in ${groupName}...`"
          >
        </div> -->

        <div class="group-monitors">
          <div
            v-for="monitor in getPaginatedGroupMonitors(filteredGroupedMonitors[groupName].monitors, groupName)"
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
          </div>
        </div>
      </div>
      <!-- Groups pagination controls -->
      <div class="groups-pagination" v-if="totalGroupsPages > 1">
        <button class="btn btn-sm" :disabled="groupsPage === 1" @click.stop="groupsPrev">Prev</button>
        <span class="group-page-info">Halaman {{ groupsPage }} / {{ totalGroupsPages }}</span>
        <button class="btn btn-sm" :disabled="groupsPage >= totalGroupsPages" @click.stop="groupsNext">Next</button>
      </div>
    </div>

    <!-- Empty State -->
    <div v-if="!monitorStore.loading && !monitorStore.error && (!filteredMonitors.length && !Object.keys(filteredGroupedMonitors).length)" class="empty-state">
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

  <!-- Edit Group Modal -->
  <div v-if="showEditGroupModal" class="modal-overlay" @click="showEditGroupModal = false">
    <div class="modal edit-group-modal" @click.stop>
      <div class="modal-header">
        <h3>✏️ Edit Group</h3>
        <button @click="showEditGroupModal = false" class="btn-close">×</button>
      </div>
      
      <div class="modal-body">
        <div class="form-group">
          <label for="edit-group-name" class="form-label">Group Name:</label>
          <input
            id="edit-group-name"
            v-model="editingGroup.name"
            type="text"
            class="form-control"
            placeholder="Enter new group name"
            required
          >
        </div>
        
        <div class="form-group">
          <label for="edit-group-description" class="form-label">Description:</label>
          <textarea
            id="edit-group-description"
            v-model="editingGroup.description"
            class="form-control"
            rows="3"
            placeholder="Enter group description (optional)"
          ></textarea>
        </div>
        
        <div class="form-info">
          <p class="info-text">💡 This will update the group name for all {{ editingGroup.monitorCount }} monitor(s) in this group.</p>
        </div>
      </div>
      
      <div class="modal-footer">
        <button @click="showEditGroupModal = false" class="btn btn-secondary">Cancel</button>
        <button @click="saveGroupEdit" class="btn btn-primary" :disabled="savingGroup || !editingGroup.name || editingGroup.name.trim() === ''">
          <span v-if="savingGroup">Saving...</span>
          <span v-else>Save Changes</span>
        </button>
      </div>
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
const groupSearches = ref({})

// Pagination state (list/grid)
const groupPages = reactive({})
const currentPage = ref(1)
const pageSize = ref(10)
const pageSizes = [10, 20, 50]
const totalPages = computed(() => Math.max(1, Math.ceil(filteredMonitors.value.length / pageSize.value)))
const paginatedMonitors = computed(() => {
  const start = (currentPage.value - 1) * pageSize.value
  return filteredMonitors.value.slice(start, start + pageSize.value)
})

// Per-group monitors pagination helpers
function prevPage() { if (currentPage.value > 1) currentPage.value -= 1 }
function nextPage() { if (currentPage.value < totalPages.value) currentPage.value += 1 }
function gotoPage(n) { currentPage.value = Math.min(Math.max(1, n), totalPages.value) }
function changePageSize(size) { pageSize.value = size; currentPage.value = 1 }

function getGroupTotalPages(monitors) {
  const len = (monitors && monitors.length) ? monitors.length : 0
  return Math.max(1, Math.ceil(len / pageSize.value))
}

function getPaginatedGroupMonitors(monitors, groupName) {
  const list = getFilteredGroupMonitors(monitors, groupName) || []
  const page = groupPages[groupName] || 1
  const start = (page - 1) * pageSize.value
  return list.slice(start, start + pageSize.value)
}

function groupPagePrev(groupName) { if ((groupPages[groupName] || 1) > 1) groupPages[groupName] -= 1 }
function groupPageNext(groupName, monitors) {
  const total = getGroupTotalPages(getFilteredGroupMonitors(monitors, groupName))
  if ((groupPages[groupName] || 1) < total) groupPages[groupName] += 1
}

// Groups pagination (paginate group sections)
const groupsPage = ref(1)
const groupsPerPage = ref(5)
const totalGroupsPages = computed(() => {
  const count = Object.keys(filteredGroupedMonitors.value).length
  return Math.max(1, Math.ceil(count / groupsPerPage.value))
})
const paginatedGroupKeys = computed(() => {
  const keys = Object.keys(filteredGroupedMonitors.value)
  const start = (groupsPage.value - 1) * groupsPerPage.value
  return keys.slice(start, start + groupsPerPage.value)
})
function groupsPrev() { if (groupsPage.value > 1) groupsPage.value -= 1 }
function groupsNext() { if (groupsPage.value < totalGroupsPages.value) groupsPage.value += 1 }
function gotoGroupsPage(n) { groupsPage.value = Math.min(Math.max(1, n), totalGroupsPages.value) }

const showPauseModal = ref(false)
const selectedMonitor = ref(null)
const pauseDuration = ref(60)
const deleting = ref(null)
const refreshInterval = ref(null)
const lastUpdate = ref(null)
const filtersExpanded = ref(true)

// Edit group state
const showEditGroupModal = ref(false)
const editingGroup = ref({
  originalName: '',
  name: '',
  description: '',
  monitorCount: 0
})
const savingGroup = ref(false)

let searchTimeout = null

// Computed properties untuk stats - memastikan reaktivitas penuh
const totalMonitors = computed(() => {
  // Force reactivity dengan mengakses array secara eksplisit
  const monitors = monitorStore.monitors
  if (!monitors || !Array.isArray(monitors)) return 0
  return monitors.length
})

const upMonitors = computed(() => {
  // Force reactivity dengan mengakses array secara eksplisit
  const monitors = monitorStore.monitors
  if (!monitors || !Array.isArray(monitors)) return 0
  return monitors.filter(monitor => monitor && monitor.last_status === 'up').length
})

const downMonitors = computed(() => {
  // Force reactivity dengan mengakses array secara eksplisit
  const monitors = monitorStore.monitors
  if (!monitors || !Array.isArray(monitors)) return 0
  return monitors.filter(monitor => monitor && monitor.last_status === 'down').length
})

const unknownMonitors = computed(() => {
  // Force reactivity dengan mengakses array secara eksplisit
  const monitors = monitorStore.monitors
  if (!monitors || !Array.isArray(monitors)) return 0
  return monitors.filter(monitor => 
    monitor && (monitor.last_status === 'unknown' || !monitor.last_status)
  ).length
})

const totalGroups = computed(() => {
  // Force reactivity dengan mengakses array secara eksplisit
  const monitors = monitorStore.monitors
  if (!monitors || !Array.isArray(monitors) || monitors.length === 0) return 0
  
  const uniqueGroups = new Set()
  monitors.forEach(monitor => {
    // Only count monitors that actually have a group name
    if (monitor && monitor.group_name && typeof monitor.group_name === 'string' && monitor.group_name.trim() !== '') {
      uniqueGroups.add(monitor.group_name.trim())
    }
  })
  
  console.log('Total Groups Computed:', uniqueGroups.size, 'from', monitors.length, 'monitors')
  return uniqueGroups.size
})

const overallHealth = computed(() => {
  // Calculate from all monitors, not filtered
  const total = totalMonitors.value
  if (total === 0) return 0
  const upCount = upMonitors.value
  const health = Math.round((upCount / total) * 100)
  console.log('Overall Health:', health, '% (', upCount, '/', total, ')')
  return health
})

const overallHealthClass = computed(() => {
  const health = overallHealth.value
  if (health >= 95) return 'health-excellent'
  if (health >= 80) return 'health-good'
  if (health >= 50) return 'health-warning'
  return 'health-critical'
})

// Computed property for group filter options with accurate counts
const groupFilterOptions = computed(() => {
  // Force reactivity dengan mengakses array secara eksplisit
  const monitors = monitorStore.monitors
  if (!monitors || !Array.isArray(monitors) || monitors.length === 0) return []
  
  // Count monitors per group from actual data
  const groupCounts = {}
  monitors.forEach(monitor => {
    if (monitor && monitor.group_name && typeof monitor.group_name === 'string' && monitor.group_name.trim() !== '') {
      const groupName = monitor.group_name.trim()
      groupCounts[groupName] = (groupCounts[groupName] || 0) + 1
    }
  })
  
  // Create group options with counts, sorted by name
  const options = Object.keys(groupCounts)
    .sort((a, b) => a.localeCompare(b))
    .map(name => ({
      name: name,
      count: groupCounts[name]
    }))
  
  console.log('Group Filter Options:', options.length, 'groups', options)
  return options
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

// Computed property for filtered grouped monitors (client-side filtering)
const filteredGroupedMonitors = computed(() => {
  if (!groupedMonitors.value || Object.keys(groupedMonitors.value).length === 0) {
    return {}
  }
  
  let result = { ...groupedMonitors.value }
  
  // Apply group filter - only show specific group
  if (filters.group && filters.group !== '') {
    if (filters.group === 'ungrouped') {
      result = { 'Ungrouped': result['Ungrouped'] || { monitors: [], description: '' } }
    } else {
      result = { [filters.group]: result[filters.group] || { monitors: [], description: '' } }
    }
  }
  
  // Apply global search filter - search in group names or monitors.
  // If searching by monitor (name/target), only include the matching monitors
  // inside each group so the grouped view shows relevant monitors instead
  // of the whole group.
  if (filters.search && filters.search.trim() !== '') {
    const searchTerm = filters.search.toLowerCase()
    const filtered = {}

    Object.keys(result).forEach(groupName => {
      // If the group name matches the search, include the whole group
      if (groupName.toLowerCase().includes(searchTerm)) {
        filtered[groupName] = result[groupName]
        return
      }

      // Otherwise filter monitors within the group by name/target
      const matchingMonitors = (result[groupName].monitors || []).filter(monitor => {
        const nameMatch = monitor.name?.toLowerCase().includes(searchTerm)
        const targetMatch = monitor.target?.toLowerCase().includes(searchTerm)
        return nameMatch || targetMatch
      })

      if (matchingMonitors.length > 0) {
        // clone group object but replace monitors with only matching ones
        filtered[groupName] = {
          ...result[groupName],
          monitors: matchingMonitors
        }
      }
    })

    result = filtered
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

function getFilteredGroupMonitors(monitors, groupName) {
  if (!monitors || !Array.isArray(monitors)) return []
  
  const searchTerm = groupSearches.value[groupName]
  
  if (!searchTerm || searchTerm.trim() === '') {
    return monitors
  }
  
  const search = searchTerm.toLowerCase()
  return monitors.filter(monitor => {
    const nameMatch = monitor.name?.toLowerCase().includes(search)
    const targetMatch = monitor.target?.toLowerCase().includes(search)
    return nameMatch || targetMatch
  })
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

function navigateToGroup(groupName) {
  // route to group detail view; encode group name to be URL-safe
  const encoded = encodeURIComponent(groupName)
  router.push(`/groups/${encoded}`)
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
    // support both shapes: store returns { success, data } and API wrapper may return { data }
    let data = response?.data ?? response?.data?.data ?? response?.data
    if (response && response.success && response.data) data = response.data

    if (!Array.isArray(data)) data = []

    // Clean up groups: remove falsy/empty names, exclude any literal "All Groups",
    // and deduplicate by lower-cased name.
    const seen = new Set()
    const cleaned = []
    data.forEach(g => {
      const name = (g && (g.name || g.group_name)) ? (g.name || g.group_name) : null
      if (!name) return
      const trimmed = String(name).trim()
      if (!trimmed) return
      const key = trimmed.toLowerCase()
      if (key === 'all groups') return
      if (seen.has(key)) return
      seen.add(key)
      cleaned.push({ id: g.id ?? trimmed, name: trimmed })
    })

    groups.value = cleaned
  } catch (error) {
    console.error('Failed to fetch groups:', error)
  }
}

async function fetchGroupedMonitors() {
  try {
    const params = {}
    
    // Only send status, type, and enabled filters to backend
    // Search and group filters will be handled client-side
    if (filters.status) params.status = filters.status
    if (filters.type) params.type = filters.type
    if (filters.enabled) params.enabled = filters.enabled
    // Don't send filters.group or filters.search to backend for grouped view
    
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
    tooltip.style.left = `${event.pageX + 10}px`
    tooltip.style.top = `${event.pageY + 10}px`
    tooltip.style.display = 'block'
    // trigger CSS transition
    requestAnimationFrame(() => tooltip.classList.add('show'))
  }
}

function hideErrorTooltip() {
  const tooltip = document.querySelector('.tooltip')
  if (tooltip) {
    tooltip.classList.remove('show')
    // allow transition to finish before hiding
    setTimeout(() => {
      tooltip.style.display = 'none'
    }, 140)
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

// Edit Group Functions
function openEditGroupModal(groupName, groupData) {
  const monitors = groupData.monitors || []
  editingGroup.value = {
    originalName: groupName,
    name: groupName,
    description: groupData.description || '',
    monitorCount: monitors.length,
    monitorsList: [...monitors]
  }
  console.log('Opening edit modal for:', groupName, 'with', monitors.length, 'monitors')
  console.log('Current description:', groupData.description)
  showEditGroupModal.value = true
}

async function saveGroupEdit() {
  if (!editingGroup.value.name || editingGroup.value.name.trim() === '') {
    alert('Group name is required')
    return
  }

  const newGroupName = editingGroup.value.name.trim()
  const originalGroupName = editingGroup.value.originalName
  const newDescription = editingGroup.value.description.trim()
  
  // Check if there's any change
  const nameChanged = newGroupName !== originalGroupName
  
  if (!nameChanged) {
    alert('No changes detected. Group name is the same.')
    showEditGroupModal.value = false
    return
  }

  savingGroup.value = true

  try {
    // Use stored monitors list
    const groupMonitors = editingGroup.value.monitorsList || []
    
    console.log('=== STARTING GROUP UPDATE ===')
    console.log('From:', originalGroupName)
    console.log('To:', newGroupName)
    console.log('Description:', newDescription || '(empty)')
    console.log('Monitors to update:', groupMonitors.length)
    
    if (groupMonitors.length === 0) {
      alert('No monitors found in this group')
      savingGroup.value = false
      showEditGroupModal.value = false
      return
    }
    
    // Update each monitor one by one and track results
    let successCount = 0
    let failCount = 0
    const errors = []
    
    for (const monitor of groupMonitors) {
      try {
        console.log(`Updating monitor ${monitor.id}: ${monitor.name}`)
        
        const updateData = {
          group_name: newGroupName
        }
        
        // Add description if it's provided
        if (newDescription) {
          updateData.group_description = newDescription
        }
        
        const result = await monitorStore.updateMonitor(monitor.id, updateData)
        
        console.log(`Result for ${monitor.id}:`, result)
        
        if (result.success) {
          successCount++
          console.log(`✓ Monitor ${monitor.id} updated successfully`)
        } else {
          failCount++
          errors.push(`${monitor.name}: ${result.message}`)
          console.error(`✗ Monitor ${monitor.id} failed:`, result.message)
        }
      } catch (err) {
        failCount++
        errors.push(`${monitor.name}: ${err.message}`)
        console.error(`✗ Monitor ${monitor.id} exception:`, err)
      }
    }
    
    console.log('=== UPDATE SUMMARY ===')
    console.log('Success:', successCount)
    console.log('Failed:', failCount)
    
    // Close modal
    showEditGroupModal.value = false
    
    // Refresh grouped monitors
    await fetchGroupedMonitors()
    
    // Show result
    if (failCount === 0) {
      let message = `Group updated successfully!\n${successCount} monitor(s) updated.`
      if (nameChanged) {
        message = `Group renamed from "${originalGroupName}" to "${newGroupName}"!\n${successCount} monitor(s) updated.`
      }
      if (newDescription) {
        message += `\n\nDescription has been saved.`
      }
      alert(message)
    } else {
      alert(`Partially completed:\n${successCount} succeeded, ${failCount} failed.\n\nErrors:\n${errors.join('\n')}`)
    }
    
  } catch (error) {
    console.error('=== CRITICAL ERROR ===')
    console.error(error)
    if (error.response?.data) {
      console.error('Server response:', error.response.data)
      alert(`Failed to update group: ${error.response.data.message || JSON.stringify(error.response.data)}`)
    } else {
      alert(`Failed to update group: ${error.message}`)
    }
  } finally {
    savingGroup.value = false
  }
}

// Lifecycle
onMounted(async () => {
  await fetchGroups()
  await fetchData()
  startAutoRefresh()
})

// Watch filters for reactivity - reset pagination only, computed properties handle filtering
watch(
  () => [filters.status, filters.type, filters.enabled, filters.group, filters.search],
  () => {
    // Reset pagination when filters change so user sees first page of results
    currentPage.value = 1
    groupsPage.value = 1
    Object.keys(groupPages).forEach(k => { groupPages[k] = 1 })
    // No need to fetch - computed properties (filteredMonitors, filteredGroupedMonitors) are reactive
  },
  { deep: true, immediate: false }
)

// Watch view mode changes
watch(
  () => viewMode.value,
  (newMode) => {
    // reset pagination when switching modes
    currentPage.value = 1
    groupsPage.value = 1
    if (newMode === 'grouped') {
      fetchGroupedMonitors()
    }
  }
)

// reset groups pagination when groupsPerPage changes
watch(
  () => groupsPerPage.value,
  () => {
    groupsPage.value = 1
  }
)

// Initialize group searches when grouped monitors change
watch(
  () => groupedMonitors.value,
  (newGroups) => {
    if (newGroups) {
      Object.keys(newGroups).forEach(groupName => {
        if (!groupSearches.value[groupName]) {
          groupSearches.value[groupName] = ''
        }
        if (!groupPages[groupName]) {
          groupPages[groupName] = 1
        }
      })
    }
  },
  { deep: true, immediate: true }
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
  background-color: transparent;
  color: #121212;
}

.page-header {
  margin: 0 0 18px 0;
  padding: 0; /* we'll control inner spacing via header-content */
  width: 100%;
  background: transparent;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 20px;
}

/* Make header stretch full width but keep inner content centered with controlled padding */
.page-header .header-content {
  max-width: 1280px;
  margin: 0 auto;
  padding: 18px 20px; /* main header inner padding */
  box-sizing: border-box;
  align-items: center;
}

.header-main h1 {
  margin: 0 0 5px 0;
  color: var(--color-text);
  font-size: 2em;
}

.header-main p {
  margin: 0;
  color: var(--color-muted);
  font-size: 1.1em;
}

.header-actions {
  display: flex;
  gap: 15px;
  align-items: center;
}

.form-container {
  background: var(--color-surface);
  border-radius: 12px;
  box-shadow: var(--shadow-1);
  overflow: hidden;
  margin-bottom: 32px;
  padding: 24px;
  border: 1px solid rgba(15,23,42,0.04);
}
/* Responsive grid for mobile & better padding/columns */
.monitors-grid-view {
  grid-template-columns: repeat(3, 1fr);
  gap: 1.2rem;
  padding: 1.2rem 0.5rem;
}

@media (max-width: 1024px) {
  .monitors-grid-view {
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    padding: 1rem 0.25rem;
  }
}

@media (max-width: 768px) {
  .monitors-grid-view {
    grid-template-columns: 1fr;
    gap: 0.5rem;
    padding: 0.5rem 0.05rem;
  }
  .monitor-card-grid {
    min-width: 0;
    padding: 10px 2px;
  }
}

@media (max-width: 480px) {
  .monitors-grid-view {
    grid-template-columns: 1fr !important;
    gap: 0.25rem;
    padding: 0.25rem 0;
  }
  .monitor-card-grid {
    padding: 4px 0;
  }
}

/* Stats Cards */
.stats-cards {
  display: grid;
  /* fixed-ish card width and centered layout */
  grid-template-columns: repeat(auto-fit, minmax(140px, 160px));
  gap: 14px;
  margin-bottom: 24px;
  justify-content: center;
  justify-items: center;
}

.stat-card {
  background: #ffffff;
  color: #111827;
  border-radius: 12px;
  padding: 14px 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
  border: 1px solid #e6e6e6;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  max-width: 160px;
  transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.15s ease, color 0.15s ease;
  animation: fadeInUp 0.5s ease forwards;
  opacity: 0;
  transform: translateY(20px);
  position: relative;
  overflow: visible;
  box-sizing: border-box;
  z-index: 1;
}

.stat-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 14px 36px rgba(0, 0, 0, 0.12);
  border-color: #d1d5db;
  z-index: 10;
}

/* Keep stat-card stable on tap/click */
.stat-card,
.stat-card:active,
.stat-card:focus {
  -webkit-tap-highlight-color: transparent;
  touch-action: manipulation;
  background-color: inherit !important;
  color: inherit !important;
}

.stat-icon {
  font-size: 1.6em;
  line-height: 1;
  width: 44px;
  height: 44px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 10px;
  background: rgba(0, 0, 0, 0.03);
  flex-shrink: 0;
  box-sizing: border-box;
}

.stat-content h3 {
  margin: 0;
  font-size: 1.4em;
  font-weight: 700;
  color: #111827;
  line-height: 1.2;
  text-align: center;
}

/* Removed empty ruleset: .stat-card:hover .stat-content h3 */

.stat-card:hover .stat-icon {
  background: rgba(0,0,0,0.04);
}

.stat-content p {
  margin: 5px 0 0 0;
  color: #7f8c8d;
  font-weight: 500;
  font-size: 0.85rem;
}

/* Center the number and label inside stat cards */
.stat-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  flex: 1 1 auto;
}

/* Mobile: make stat-cards more square by stacking icon above text */
@media (max-width: 640px) {
  .stats-cards {
    /* Use two columns on narrow devices to avoid very narrow cards */
    grid-template-columns: repeat(2, minmax(120px, 1fr));
    gap: 12px;
    grid-auto-rows: 1fr; /* make rows equal height */
    align-items: stretch;
    justify-items: center;
  }

  .stat-card {
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 10px 8px;
    gap: 8px;
    /* Let grid control height for consistent rows */
    height: 100%;
    max-width: 140px;
    box-sizing: border-box;
  }

  .stat-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    font-size: 1.0em;
    margin-bottom: 6px;
    box-sizing: border-box;
  }

  .stat-content {
    align-items: center;
    justify-content: center;
  }

  .stat-content h3 {
    font-size: 1.05em;
    margin: 0 0 4px 0;
    line-height: 1.05;
    word-break: keep-all;
  }

  .stat-content p {
    font-size: 0.72rem; /* slightly smaller label on mobile */
    margin: 0;
    line-height: 1.05;
    text-align: center;
    word-break: break-word;
  }
}

.stat-card.services {
  background: white;
  color: white;
}

.stat-card.health.health-excellent {
  /* background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%); */
  color: white;
}

.stat-card.health.health-good {
  /* background: linear-gradient(135deg, #f7971e 0%, #ffd200 100%); */
  color: white;
}

.stat-card.health.health-warning {
  /* background: linear-gradient(135deg, #f12711 0%, #f5af19 100%); */
  color: white;
}

.stat-card.health.health-critical {
  /* background: linear-gradient(135deg, #c94b4b 0%, #4b134f 100%); */
  color: white;
}

/* Consolidated .form-container defined earlier - keep that one to avoid duplication */

/* Filters */
.filters {
  background: var(--color-bg);
  padding: 24px;
  border: 1px solid rgba(15,23,42,0.04);
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
  background: var(--color-accent);
  color: white;
  transform: translateY(-1px);
  box-shadow: var(--shadow-1);
}

/* Grid View */
.monitors-grid-view {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(520px, 1fr));
  gap: 28px;
  margin-top: 22px;
  align-items: start;
  justify-content: center;
}

.monitor-card-grid {
  --card-accent: rgba(102,126,234,0.08);
  background: linear-gradient(180deg, var(--color-surface), rgba(250,252,255,0.95));
  border-radius: 16px;
  padding: 32px 30px;
  min-height: 220px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 14px;
  position: relative;
  overflow: hidden;
  box-shadow: var(--shadow-2);
  border: 1px solid var(--card-accent);
  transition: transform 0.28s cubic-bezier(.22,.9,.36,1), box-shadow 0.28s ease, border-color 0.28s ease;
  animation: fadeInUp 0.5s ease forwards;
  opacity: 0;
  transform: translateY(10px);
  align-items: center;
}

/* Decorative left accent bar and soft background glow */
.monitor-card-grid::before {
  content: "";
  position: absolute;
  left: 0;
  top: 8px;
  bottom: 8px;
  width: 8px;
  border-radius: 8px 0 0 8px;
  background: linear-gradient(180deg, var(--color-accent), var(--color-accent-2));
  box-shadow: 0 6px 18px rgba(29,41,74,0.06);
  transform-origin: left center;
  transition: transform .28s ease, opacity .28s ease;
}

.monitor-card-grid::after {
  /* soft top-right glow */
  content: "";
  position: absolute;
  right: -40px;
  top: -40px;
  width: 180px;
  height: 180px;
  background: radial-gradient(circle at 30% 30%, rgba(102,126,234,0.12), rgba(102,126,234,0.03) 40%, transparent 60%);
  filter: blur(18px);
  pointer-events: none;
}

.monitor-card-grid.clickable {
  cursor: pointer;
}

.monitor-card-grid.clickable:hover {
  transform: translateY(-10px) scale(1.02);
  box-shadow: 0 34px 90px rgba(29,41,74,0.14);
  border-color: rgba(102,126,234,0.22);
}

.monitor-card-grid.clickable:hover::before {
  transform: scaleY(1.06);
  opacity: 0.98;
}

.monitor-header-grid {
  display: flex;
  align-items: center;
  gap: 15px;
  margin-bottom: 15px;
}

/* For grid cards, stack header elements and center them */
.monitor-card-grid .monitor-header-grid {
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 8px;
  margin-bottom: 8px;
}

.status-indicator-large {
  width: 28px;
  height: 28px;
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
  color: var(--color-text);
  font-size: 1.35em;
  font-weight: 600;
}

/* Decorative accent circle behind status for visual weight */
.monitor-card-grid .status-accent {
  position: absolute;
  top: 18px;
  right: 18px;
  width: 56px;
  height: 56px;
  border-radius: 12px;
  background: linear-gradient(180deg, rgba(255,255,255,0.6), rgba(255,255,255,0.15));
  box-shadow: 0 6px 18px rgba(29,41,74,0.06) inset;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* Make status indicator more prominent */
.monitor-card-grid .status-indicator-large {
  width: 34px;
  height: 34px;
  box-shadow: 0 6px 18px rgba(29,41,74,0.06);
}

/* Color the status-accent according to status by targeting sibling indicator colors */
.monitor-card-grid .status-indicator-large.status-up {
  background: linear-gradient(180deg,#dff6e6,#bff3c9);
  border: 2px solid #34b36b;
}
.monitor-card-grid .status-indicator-large.status-down {
  background: linear-gradient(180deg,#ffd6d6,#ffbdbd);
  border: 2px solid #e55a56;
}
.monitor-card-grid .status-indicator-large.status-unknown {
  background: linear-gradient(180deg,#f0f0f2,#e6e6e9);
  border: 2px solid #9aa0a6;
}

/* Title decoration */
.monitor-card-grid .monitor-info-grid h4 {
  position: relative;
}
.monitor-card-grid .monitor-info-grid h4::after {
  content: "";
  display: block;
  height: 4px;
  width: 48px;
  border-radius: 3px;
  margin: 8px auto 0 auto;
  background: linear-gradient(90deg, rgba(102,126,234,0.95), rgba(102,126,234,0.45));
  opacity: 0.9;
}

/* Slightly larger name and spaced letters for elegance */
.monitor-card-grid .monitor-info-grid h4 {
  font-size: 1.5rem;
  letter-spacing: 0.2px;
}

/* Ensure monitor info (title/target) is centered inside grid cards */
.monitor-card-grid .monitor-info-grid {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
}

/* Subtle translucent panel for stats */
.monitor-card-grid .monitor-stats-grid {
  width: 100%;
  padding: 14px 18px;
  background: rgba(255,255,255,0.6);
  border-radius: 10px;
  border: 1px solid rgba(102,126,234,0.04);
}

/* Make action buttons slightly larger inside grid cards */
.monitor-card-grid .monitor-actions-grid .btn {
  padding: 8px 12px;
  font-size: 0.95rem;
}

.monitor-target-grid {
  margin: 0 0 8px 0;
  color: var(--color-muted);
  font-size: 0.95em;
  word-break: break-word;
  display: none; /* hidden as requested */
}

.monitor-group-badge {
  background: rgba(15,23,42,0.03);
  color: var(--color-muted);
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
  background: var(--color-bg);
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
  color: var(--color-muted);
  text-transform: uppercase;
  font-weight: 500;
}

.stat-value {
  font-size: 0.85em;
  font-weight: 600;
  color: var(--color-text);
}

.monitor-actions-grid {
  display: flex;
  gap: 12px;
  justify-content: center;
  flex-wrap: wrap;
}

/* Larger, pill-style status badge for grid cards */
.monitor-card-grid .status-badge {
  padding: 8px 12px;
  border-radius: 12px;
  font-size: 0.95rem;
  font-weight: 700;
}

/* List View */
.monitors-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(420px, 1fr));
  gap: 20px;
  align-items: start;
}

.monitor-card {
  background: linear-gradient(180deg,#ffffff,#fcfdff);
  border-radius: 14px;
  padding: 22px;
  box-shadow: 0 8px 28px rgba(15,30,60,0.06);
  border: 1px solid rgba(102,126,234,0.04);
  transition: transform 0.22s cubic-bezier(.22,.9,.36,1), box-shadow 0.22s ease;
  margin-bottom: 18px;
}

.monitor-card.clickable {
  cursor: pointer;
}

.monitor-card.clickable:hover {
  transform: translateY(-6px);
  box-shadow: 0 14px 40px rgba(34,56,100,0.09);
  border-color: rgba(102,126,234,0.12);
}

/* Limit page width so cards look consistent on very wide screens */
.monitors {
  max-width: 1280px;
  margin-left: auto;
  margin-right: auto;
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
  margin-bottom: 16px;
}

.group-header-wrapper {
  position: relative;
}

.btn-edit-group {
  position: absolute;
  top: 20px;
  right: 20px;
  background: rgba(102, 126, 234, 0.1);
  border: 1px solid rgba(102, 126, 234, 0.2);
  color: #667eea;
  padding: 6px 10px;
  border-radius: 8px;
  cursor: pointer;
  font-size: 14px;
  transition: all 0.3s ease;
  backdrop-filter: blur(10px);
  z-index: 5;
}

.btn-edit-group:hover {
  background: rgba(102, 126, 234, 0.15);
  border-color: rgba(102, 126, 234, 0.3);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
}

.btn-edit-group:active {
  transform: translateY(0);
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
  gap: 16px;
  margin-bottom: 12px;
  padding: 16px 20px;
  position: relative;
  overflow: hidden;
  min-height: 80px;
}

.clickable-header {
  cursor: pointer;
}

.clickable-header:focus {
  outline: none;
  box-shadow: 0 0 0 6px rgba(116,185,255,0.10);
  border-radius: 12px;
}

.group-title.clickable-group {
  cursor: pointer;
  text-decoration: underline dotted rgba(0,0,0,0.12);
}

.group-title.clickable-group:focus {
  outline: none;
  box-shadow: 0 0 0 4px rgba(116,185,255,0.12);
  border-radius: 6px;
}

.group-title-section {
  flex: 1 1 auto;
  min-width: 0;
  max-width: 100%;
}

.group-title-section h2 {
  margin: 0 0 5px 0;
  font-size: 1.3em;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 8px;
  color: #2c3e50;
  flex-wrap: wrap;
  word-break: break-word;
  overflow-wrap: break-word;
  line-height: 1.4;
  white-space: nowrap;
  flex-shrink: 0;
}

.group-icon {
  flex-shrink: 0;
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
  word-break: break-word;
  overflow-wrap: break-word;
  max-width: 100%;
  line-height: 1.5;
  font-style: italic;
  font-size: 0.9em;
}

.group-stats-enhanced {
  display: flex;
  gap: 16px;
  align-items: center;
  flex-wrap: wrap;
  flex-shrink: 0;
}

.group-stats-enhanced .stat-item {
  text-align: center;
  min-width: 60px;
  flex-shrink: 0;
}

.stat-number {
  display: block;
  font-size: 1.5em;
  font-weight: bold;
  margin-bottom: 2px;
  white-space: nowrap;
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

.group-search {
  margin-bottom: 16px;
  padding: 0 4px;
}

.group-search-input {
  width: 100%;
  padding: 10px 16px;
  border: 2px solid #e3e8f5;
  border-radius: 8px;
  font-size: 0.95em;
  transition: all 0.3s ease;
  background: white;
  color: #2c3e50;
}

.group-search-input:focus {
  outline: none;
  border-color: #667eea;
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.group-search-input::placeholder {
  color: #9ca3af;
  font-style: italic;
}

.groups-controls {
  display: inline-flex;
  gap: 6px;
  align-items: center;
  justify-content: flex-end;
  margin: 6px 0 10px 0;
  font-size: 0.9rem;
}
.groups-perpage-label { font-weight: 500; color: #495057; font-size: 0.85rem }
.groups-perpage-select { width: 72px; padding: 4px 8px; border-radius: 6px; font-size: 0.85rem }
.groups-perpage-select option { font-size: 0.85rem }

.group-monitors {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 16px;
}

.monitor-card.compact {
  padding: 18px 16px;
  border-radius: 10px;
  box-shadow: 0 2px 8px rgba(19,35,62,0.06);
  border: 1px solid rgba(102,126,234,0.06);
  background: linear-gradient(180deg,#ffffff,#fbfdff);
  transition: transform 0.18s cubic-bezier(.22,.9,.36,1), box-shadow 0.18s ease;
  margin-bottom: 10px;
}

/* Hide compact group monitor cards when requested */
.monitor-card.compact {
  display: none !important;
}

.monitor-card.compact.clickable {
  cursor: pointer;
}

.monitor-card.compact.clickable:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
  border-color: #667eea;
}

/* Center header content for compact group monitors (name + status) */
.monitor-card.compact .monitor-header {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  gap: 6px;
}

.monitor-card.compact .monitor-info h4 {
  margin: 0;
  font-size: 1.05rem;
  line-height: 1.2;
}

.monitor-card.compact .monitor-status {
  margin-top: 4px;
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

.form-control:focus {
  outline: none;
  border-color: #667eea;
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-label {
  display: block;
  margin-bottom: 5px;
  font-weight: 500;
  color: #495057;
}

/* Edit Group Modal Styles */
.edit-group-modal {
  max-width: 500px;
}

.edit-group-modal .modal-body {
  padding: 24px;
}

.edit-group-modal .form-group {
  margin-bottom: 20px;
}

.edit-group-modal textarea.form-control {
  resize: vertical;
  min-height: 80px;
}

.form-info {
  background: #f0f7ff;
  border-left: 4px solid #667eea;
  padding: 12px 16px;
  border-radius: 6px;
  margin-top: 16px;
}

.info-text {
  margin: 0;
  font-size: 0.9em;
  color: #495057;
  line-height: 1.5;
}

.modal-footer button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
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
@media (max-width: 1024px) {
  .stats-cards {
    grid-template-columns: repeat(3, 1fr);
  }
}

@media (max-width: 768px) {
  .monitors {
    padding: 1rem;
    padding-top: 5rem;
  }
  
  /* On smaller screens reduce header inner padding and center title */
  .page-header .header-content {
    padding: 12px 10px;
    gap: 8px;
    flex-direction: column;
    align-items: stretch;
  }

  .page-header {
    margin-bottom: 12px;
  }
  
  .header-content {
    flex-direction: column;
    align-items: stretch;
    gap: 1rem;
  }
  
  .header-main {
    text-align: center;
  }
  
  .header-actions {
    flex-direction: row;
    justify-content: stretch;
  }
  
  .header-actions .btn {
    flex: 1;
  }
  
  .stats-cards {
    grid-template-columns: repeat(3, 1fr);
    gap: 0.5rem;
  }
  
  .filters {
    grid-template-columns: 1fr;
    padding: 1rem;
  }
  
  .monitors-grid {
    grid-template-columns: 1fr;
    gap: 1rem;
  }
  
  .monitor-card {
    padding: 1.25rem;
  }
  
  .monitor-actions {
    flex-wrap: wrap;
    gap: 0.5rem;
  }
  
  .monitor-actions .btn {
    flex: 1 1 calc(50% - 0.25rem);
    justify-content: center;
    font-size: 0.875rem;
  }
  
  .group-header {
    flex-direction: column;
    text-align: center;
    padding: 1.25rem;
    gap: 1rem;
  }
  
  .group-stats-enhanced {
    justify-content: center;
    flex-wrap: wrap;
  }
  
  .modal {
    width: 95%;
    margin: 1rem;
  }
}

@media (max-width: 480px) {
  .monitors {
    padding: 0.75rem;
  }
  
  .page-header {
    padding: 1rem;
  }
  
  .header-actions {
    flex-direction: column;
    width: 100%;
  }
  
  .header-actions .btn {
    width: 100%;
  }
  
  .stats-cards {
    grid-template-columns: repeat(3, 1fr);
    gap: 0.5rem;
  }
  
  .stat-card {
    padding: 1rem;
  }

  /* Responsive stat-card padding for mobile */
  .stat-card {
    padding: 0.5rem;
  }
  .monitor-card {
    padding: 1rem;
  }
  
  .monitor-header {
    flex-direction: column;
    gap: 0.75rem;
  }
  
  .monitor-info h4 {
    font-size: 1rem;
  }
  
  .monitor-stats {
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
  }
  
  .stat-item {
    padding: 0.625rem;
  }
  
  .monitor-actions .btn {
    flex: 1 1 100%;
    padding: 0.5rem;
  }
  
  .group-header h2 {
    font-size: 1.25rem;
  }
  
  .modal-header,
  .modal-body,
  .modal-footer {
    padding: 1rem;
  }
}
</style>

<style scoped>
/* Status and tooltip polish */
.monitor-target,
.monitor-target-grid {
  display: none !important;
}
.status-badge {
  padding: 6px 10px;
  border-radius: 999px;
  font-weight: 700;
  font-size: 0.75rem;
  display: inline-block;
  cursor: default;
  transition: transform .12s ease, box-shadow .12s ease;
  box-shadow: 0 1px 2px rgba(0,0,0,0.04);
}
.status-badge.status-up {
  background: #e6faf0;
  color: #156a3a;
  border: 1px solid #bfe9c9;
}
.status-badge.status-down {
  background: #fff0f0;
  color: #8a1f1f;
  border: 1px solid #f3bcbc;
}
.status-badge.status-invalid,
.status-badge.status-unknown {
  background: #f7f7f8;
  color: #333;
  border: 1px solid #e6e6e6;
}
.status-badge:hover {
  transform: translateY(-3px);
  box-shadow: 0 6px 18px rgba(0,0,0,0.08);
}

/* Large indicator */
.status-indicator-large { 
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  transition: transform .12s ease, box-shadow .12s ease;
}
.status-indicator-large.status-up {
  background: linear-gradient(180deg,#dff6e6,#bff3c9);
  border: 1px solid #9fe0a7;
}
.status-indicator-large.status-down {
  background: linear-gradient(180deg,#ffd6d6,#ffbdbd);
  border: 1px solid #f2b3b3;
}
.status-indicator-large .status-pulse {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: rgba(255,255,255,0.9);
  box-shadow: 0 0 0 4px rgba(0,0,0,0.02) inset;
}

/* Tooltip */
.tooltip {
  position: fixed;
  z-index: 1400;
  background: rgba(20,20,20,0.95);
  color: #fff;
  padding: 8px 10px;
  border-radius: 6px;
  font-size: 13px;
  box-shadow: 0 8px 28px rgba(0,0,0,0.18);
  pointer-events: none;
  opacity: 0;
  transform: translateY(-6px);
  transition: opacity .12s ease, transform .12s ease;
  max-width: 360px;
  word-break: break-word;
}
.tooltip.show {
  opacity: 1;
  transform: translateY(0);
}
.tooltip .tooltip-text {
  display: block;
  line-height: 1.35;
}
</style>

<style scoped>
/* Pagination styles */
.pagination-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  margin-top: 18px;
}
.pagination-controls {
  display: flex;
  align-items: center;
  gap: 8px;
}
.pagination-info {
  color: #6c757d;
  font-size: 0.95rem;
  flex: 1 1 auto;
  text-align: center;
}
.page-size-select {
  padding: 6px 8px;
  border-radius: 6px;
  border: 1px solid #e6e9ef;
  background: white;
}
.active-page {
  background: #007bff;
  color: white;
}
.group-pagination {
  display: flex;
  gap: 8px;
  justify-content: center;
  align-items: center;
  margin-top: 10px;
}
.group-page-info {
  font-weight: 600;
  color: #495057;
}
.groups-pagination {
  display: flex;
  gap: 10px;
  justify-content: center;
  align-items: center;
  margin-top: 16px;
}
.groups-pagination {
  margin-top: 20px;
}

/* Mobile-specific overrides: make stat cards a two-column grid and tighten spacing */
@media (max-width: 768px) {
  .group-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
    padding: 14px 16px;
  }

  .group-title-section {
    width: 100%;
  }

  .group-title-section h2 {
    font-size: 1.1em;
  }

  .group-stats-enhanced {
    width: 100%;
    justify-content: space-between;
    gap: 12px;
  }

  .group-stats-enhanced .stat-item {
    flex: 1;
    min-width: 0;
  }

  .stat-number {
    font-size: 1.3em;
  }

  .stat-label {
    font-size: 0.8em;
  }
}

@media (max-width: 480px) {
  .stats-cards {
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
  }

  .stat-card {
    padding: 16px;
    border-radius: 10px;
    min-height: 100px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    text-align: center;
  }

  .stat-icon {
    width: 52px;
    height: 52px;
    font-size: 1.6em;
    margin-bottom: 8px;
  }

  .stat-content h3 {
    font-size: 1.5em;
    margin: 0;
    line-height: 1.1;
  }

  .stat-content p {
    font-size: 0.95em;
    margin: 6px 0 0 0;
  }

  /* Ensure header action buttons stack and are full-width on small screens */
  .header-actions {
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  .header-actions .btn {
    width: 100%;
  }

  .header-main h1 {
    font-size: 1.6rem;
  }
}
</style>