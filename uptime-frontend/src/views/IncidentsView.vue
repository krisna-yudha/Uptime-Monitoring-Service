<template>
  <div class="incidents">
    <div class="page-header">
      <h1>Incidents</h1>
      
      <div class="header-actions">
        <div class="action-buttons">
          <div class="dropdown" :class="{ 'is-active': showFilters }" @click.stop>
            <button class="action-btn" @click="toggleFilters">
              <span class="icon">⚙</span>
              <span>Filters</span>
            </button>
            <div v-if="showFilters" class="dropdown-menu" @click.stop>
              <div class="dropdown-content">
                <button 
                  class="dropdown-item" 
                  :class="{ 'is-active': statusFilter === '' }"
                  @click="statusFilter = ''; showFilters = false"
                >
                  All Statuses (Open, Pending, Resolved)
                </button>
                <button 
                  class="dropdown-item" 
                  :class="{ 'is-active': statusFilter === 'open' }"
                  @click="statusFilter = 'open'; showFilters = false"
                >
                  Open (Down)
                </button>
                <button 
                  class="dropdown-item" 
                  :class="{ 'is-active': statusFilter === 'pending' }"
                  @click="statusFilter = 'pending'; showFilters = false"
                >
                  Pending (Ditangani)
                </button>
                <button 
                  class="dropdown-item" 
                  :class="{ 'is-active': statusFilter === 'resolved' }"
                  @click="statusFilter = 'resolved'; showFilters = false"
                >
                  Resolved (Selesai)
                </button>
              </div>
            </div>
          </div>
          
          <div class="dropdown" :class="{ 'is-active': showSort }" @click.stop>
            <button class="action-btn" @click="toggleSort">
              <span class="icon">↕</span>
              <span>Sort</span>
            </button>
            <div v-if="showSort" class="dropdown-menu" @click.stop>
              <div class="dropdown-content">
                <button 
                  class="dropdown-item" 
                  :class="{ 'is-active': sortBy === 'date-desc' }"
                  @click="sortBy = 'date-desc'; showSort = false"
                >
                  Newest First
                </button>
                <button 
                  class="dropdown-item" 
                  :class="{ 'is-active': sortBy === 'date-asc' }"
                  @click="sortBy = 'date-asc'; showSort = false"
                >
                  Oldest First
                </button>
                <button 
                  class="dropdown-item" 
                  :class="{ 'is-active': sortBy === 'status' }"
                  @click="sortBy = 'status'; showSort = false"
                >
                  Sort by Status
                </button>
                <button 
                  class="dropdown-item" 
                  :class="{ 'is-active': sortBy === 'name' }"
                  @click="sortBy = 'name'; showSort = false"
                >
                  Sort by Name
                </button>
              </div>
            </div>
          </div>
        </div>
        
        <!-- <div class="filter-group">
          <select v-model="monitorFilter" class="form-control">
            <option value="">All Monitors</option>
            <option 
              v-for="monitor in availableMonitors" 
              :key="monitor.id" 
              :value="monitor.id"
            >
              {{ monitor.name }}
            </option>
          </select>
        </div> -->

        <!-- <div class="filter-group view-toggle">
          <button
            :class="['btn', viewMode === 'card' ? 'btn-secondary' : 'btn-primary']"
            @click="viewMode = 'list'"
            title="Tampilan daftar"
          >
            List
          </button>
          <button
            :class="['btn', viewMode === 'card' ? 'btn-primary' : 'btn-secondary']"
            @click="viewMode = 'card'"
            title="Tampilan kartu"
          >
            Card
          </button>
        </div> -->
      </div>
    </div>

    <div v-if="loading" class="loading">Loading incidents...</div>
    
    <div v-else-if="serverError" class="server-error">
      <h2>Server Error</h2>
      <p>Tidak dapat terhubung ke Laravel server. Pastikan server berjalan di <code>http://localhost:8000</code></p>
      <button @click="retryConnection" class="btn btn-primary">Coba Lagi</button>
    </div>
    
    <div v-else-if="filteredIncidents.length === 0" class="no-incidents">
      <h2>No incidents found</h2>
      <p>{{ statusFilter ? 'No incidents match your current filters.' : 'Great! No incidents have been recorded yet.' }}</p>
    </div>
    
    <div v-else>
      <div class="list-wrapper">
        <div class="list-header">
          <div class="col name">Name</div>
          <div class="col status">Status</div>
          <div class="col datetime">Date Time</div>
          <div class="col message">Message</div>
          <div class="col actions">Actions</div>
        </div>
        
          <!-- Action Modal: input message before acknowledging/resolving -->
          <div v-if="showActionModal" class="modal-overlay" @click.self="showActionModal = false">
            <div class="modal">
              <div class="modal-header">
                <h3>{{ actionType === 'pending' ? 'Tandai: Ditangani' : 'Tandai: Selesai' }}</h3>
                <button class="btn btn-secondary" @click="showActionModal = false">✖</button>
              </div>
              <div class="modal-body">
                <label class="form-label">Pesan (opsional)</label>
                <textarea v-model="actionMessage" rows="4" class="form-control" placeholder="Tambahkan pesan atau catatan..."></textarea>
              </div>
              <div class="modal-footer">
                <button class="btn btn-secondary" @click="showActionModal = false">Batal</button>
                <button class="btn btn-primary" :disabled="actionLoading" @click="confirmAction">
                  {{ actionLoading ? 'Mengirim...' : (actionType === 'pending' ? 'Tandai Ditangani' : 'Tandai Selesai') }}
                </button>
              </div>
            </div>
          </div>

          <!-- Clear Data Modal: confirmation before deleting -->
          <div v-if="showClearModal" class="modal-overlay" @click.self="showClearModal = false">
            <div class="modal">
              <div class="modal-header">
                <h3>⚠ Konfirmasi Clear Data</h3>
                <button class="btn btn-secondary" @click="showClearModal = false">✖</button>
              </div>
              <div class="modal-body">
                <p class="warning-text">Apakah Anda yakin ingin menghapus incident ini?</p>
                <p class="warning-subtext">Tindakan ini tidak dapat dibatalkan.</p>
              </div>
              <div class="modal-footer">
                <button class="btn btn-secondary" @click="showClearModal = false">Batal</button>
                <button class="btn btn-danger" :disabled="clearLoading" @click="confirmClearData">
                  {{ clearLoading ? 'Menghapus...' : 'Ya, Hapus Data' }}
                </button>
              </div>
            </div>
          </div>

        <ul class="incidents-list view-list">
          <li
            v-for="incident in filteredIncidents"
            :key="incident.id"
            class="incident-card"
            :class="`incident-${incident.status || 'open'}`"
          >
            <div class="list-row">
              <div class="col name">
                <router-link :to="`/monitors/${incident.monitor_id}`">{{ incident.monitor_name }}</router-link>
              </div>
              <div class="col status">
                <span class="status-badge" :class="`status-${incident.status || 'open'}`">{{ getStatusLabel(incident.status || 'open') }}</span>
              </div>
              <div class="col datetime">{{ formatDate(incident.started_at || incident.last_check_at || new Date()) }}</div>
              <div class="col message" :title="getIncidentMessage(incident)">{{ getIncidentMessage(incident) }}</div>
              <div class="col actions">
                <!-- <a 
                  v-if="incident.monitor?.target" 
                  :href="incident.monitor.target" 
                  target="_blank" 
                  rel="noopener noreferrer"
                  class="btn btn-secondary btn-sm btn-view-link"
                  title="Visit Website"
                >
                  <img src="https://img.icons8.com/?size=100&id=132&format=png&color=000000" alt="link" class="icon-link" />
                </a> -->
                
                <router-link
                  :to="`/incidents/${incident.id}`"
                  class="btn btn-secondary btn-sm"
                  title="View Incident Details"
                >
                  View
                </router-link>
                
                <button
                  class="btn btn-warning btn-sm btn-action"
                  title="Ditangani"
                  :disabled="incident.status === 'pending' || incident.status === 'resolved'"
                  @click="openActionModal('pending', incident.id)"
                >
                  <img src="https://img.icons8.com/?size=100&id=s4MzQ849Sdas&format=png&color=000000" alt="pending" class="icon-action" /> Ditangani
                </button>

                <button
                  class="btn btn-success btn-sm btn-action"
                  title="Selesai"
                  :disabled="incident.status === 'resolved'"
                  @click="openActionModal('resolved', incident.id)"
                >
                  <img src="https://img.icons8.com/?size=100&id=3sGpukxLxwGk&format=png&color=000000" alt="resolved" class="icon-action" /> Selesai
                </button>

                <button
                  class="btn btn-danger btn-sm btn-action"
                  title="Clear Data"
                  @click="openClearModal(incident.id)"
                >
                  <img src="https://img.icons8.com/?size=100&id=67884&format=png&color=000000" alt="clear" class="icon-action" /> Clear
                </button>
              </div>
            </div>
          </li>
        </ul>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="!loading && filteredIncidents.length > 0" class="pagination-wrapper">
      <div class="pagination-left">
        <span class="pagination-showing">Showing {{ paginationStart }} to {{ paginationEnd }} of {{ pagination.total }} entries</span>
      </div>
      
      <div class="pagination-right">
        <button
          @click="previousPage"
          :disabled="pagination.current_page === 1"
          class="pagination-nav-btn"
        >
          Back
        </button>

        <div class="pagination-pages">
          <button
            v-for="p in visiblePages"
            :key="p"
            class="pagination-page-btn"
            :class="{ 'active': p === pagination.current_page, 'dots': p === '...' }"
            :disabled="p === '...'"
            @click="gotoPage(p)"
          >
            {{ p }}
          </button>
        </div>
        
        <button
          @click="nextPage"
          :disabled="pagination.current_page >= pagination.last_page"
          class="pagination-nav-btn"
        >
          Next
        </button>

        <div class="pagination-perpage">
          <label>Per page:</label>
          <select v-model.number="pagination.per_page" @change="changePerPage" class="perpage-select">
            <option :value="10">10</option>
            <option :value="20">20</option>
            <option :value="50">50</option>
          </select>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useMonitorStore } from '../stores/monitors'
import api from '../services/api'

const monitorStore = useMonitorStore()

const loading = ref(true)
const incidents = ref([])
const availableMonitors = ref([])
const statusFilter = ref('')
const monitorFilter = ref('')
const sortBy = ref('date-desc')
const showFilters = ref(false)
const showSort = ref(false)
const newNotes = ref({})
const serverError = ref(false)
const viewMode = ref('list')

const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 10,
  total: 0
})

// Action modal state for handling messages when acknowledging/resolving
const showActionModal = ref(false)
const actionType = ref('') // 'pending' or 'resolved'
const actionIncidentId = ref(null)
const actionMessage = ref('')
const actionLoading = ref(false)

// Clear data modal state
const showClearModal = ref(false)
const clearIncidentId = ref(null)
const clearLoading = ref(false)

const pagesList = computed(() => {
  const last = pagination.value.last_page || 1
  const arr = []
  for (let i = 1; i <= last; i++) arr.push(i)
  return arr
})

const paginationStart = computed(() => {
  if (pagination.value.total === 0) return 0
  return ((pagination.value.current_page - 1) * pagination.value.per_page) + 1
})

const paginationEnd = computed(() => {
  const end = pagination.value.current_page * pagination.value.per_page
  return Math.min(end, pagination.value.total)
})

const visiblePages = computed(() => {
  const current = pagination.value.current_page
  const last = pagination.value.last_page
  const pages = []
  
  // Always show first page
  pages.push(1)
  
  // Show pages around current page
  for (let i = Math.max(2, current - 1); i <= Math.min(current + 1, last); i++) {
    if (!pages.includes(i)) pages.push(i)
  }
  
  // Add dots and last pages if needed
  if (current + 2 < last) {
    if (current + 2 < last - 1) {
      pages.push('...')
    }
    if (!pages.includes(last - 1)) pages.push(last - 1)
  }
  
  // Always show last page if > 1
  if (last > 1 && !pages.includes(last)) {
    pages.push(last)
  }
  
  return pages
})

const filteredIncidents = computed(() => {
  // Clone incidents array for sorting
  let result = [...incidents.value]
  
  // Apply sorting
  if (sortBy.value === 'date-desc') {
    result.sort((a, b) => {
      const dateA = new Date(a.started_at || a.last_check_at || 0)
      const dateB = new Date(b.started_at || b.last_check_at || 0)
      return dateB - dateA
    })
  } else if (sortBy.value === 'date-asc') {
    result.sort((a, b) => {
      const dateA = new Date(a.started_at || a.last_check_at || 0)
      const dateB = new Date(b.started_at || b.last_check_at || 0)
      return dateA - dateB
    })
  } else if (sortBy.value === 'status') {
    const statusOrder = { 'open': 1, 'pending': 2, 'resolved': 3 }
    result.sort((a, b) => {
      const statusA = statusOrder[a.status || 'open'] || 0
      const statusB = statusOrder[b.status || 'open'] || 0
      return statusA - statusB
    })
  } else if (sortBy.value === 'name') {
    result.sort((a, b) => {
      const nameA = (a.monitor_name || '').toLowerCase()
      const nameB = (b.monitor_name || '').toLowerCase()
      return nameA.localeCompare(nameB)
    })
  }
  
  return result
})

onMounted(async () => {
  await fetchIncidents()
  await fetchMonitors()
  
  // Close dropdowns when clicking outside
  document.addEventListener('click', closeAllDropdowns)
})

watch([statusFilter, monitorFilter], () => {
  pagination.value.current_page = 1
  fetchIncidents()
})

function closeAllDropdowns() {
  showFilters.value = false
  showSort.value = false
}

function toggleFilters() {
  showSort.value = false
  showFilters.value = !showFilters.value
}

function toggleSort() {
  showFilters.value = false
  showSort.value = !showSort.value
}

async function fetchIncidents() {
  loading.value = true
  
  try {
    const params = {
      page: pagination.value.current_page,
      per_page: pagination.value.per_page,
      // Fetch all statuses by default
      include_all_statuses: true
    }
    
    // Only filter by status if explicitly selected
    if (statusFilter.value && statusFilter.value !== '') {
      params.status = statusFilter.value
      delete params.include_all_statuses
    }
    
    if (monitorFilter.value) {
      params.monitor_id = monitorFilter.value
    }
    
    const response = await api.incidents.getAll(params)
    
    console.log('Full API Response:', response)
    console.log('Response Data:', response.data)
    
    if (response.data && response.data.success) {
      const responseData = response.data.data // Laravel pagination wraps in 'data'
      
      console.log('Extracted Data:', responseData)
      
      // Handle Laravel paginated response structure
      if (responseData && typeof responseData === 'object' && 'data' in responseData) {
        // Laravel pagination format: { current_page, data: [...], total, ... }
        incidents.value = responseData.data || []
        
        pagination.value = {
          current_page: responseData.current_page || 1,
          last_page: responseData.last_page || 1,
          per_page: responseData.per_page || 10,
          total: responseData.total || 0
        }
        
        console.log('Incidents loaded:', incidents.value.length)
        console.log('Pagination:', pagination.value)
      } else if (Array.isArray(responseData)) {
        // Direct array response
        incidents.value = responseData
        pagination.value = {
          current_page: 1,
          last_page: 1,
          per_page: pagination.value.per_page,
          total: responseData.length
        }
        console.log('Incidents loaded (array):', incidents.value.length)
      } else {
        console.warn('Unexpected response structure:', response.data)
        incidents.value = []
        pagination.value = {
          current_page: 1,
          last_page: 1,
          per_page: pagination.value.per_page,
          total: 0
        }
      }
    } else {
      incidents.value = []
      pagination.value = {
        current_page: 1,
        last_page: 1,
        per_page: pagination.value.per_page,
        total: 0
      }
    }
  } catch (err) {
    console.error('Failed to load incidents:', err)
    
    if (err.code === 'ERR_NETWORK') {
      serverError.value = true
      console.error('Server tidak dapat dijangkau')
    } else if (err.response?.status === 500) {
      alert('Server error. Silakan cek log server Laravel untuk detail error.')
    } else if (err.response?.status === 401) {
      // Auth error will be handled by api interceptor
      console.error('Authentication error')
    } else {
      console.error(`Error: ${err.response?.data?.message || err.message}`)
    }
    
    incidents.value = []
  } finally {
    loading.value = false
  }
}

async function fetchMonitors() {
  try {
    await monitorStore.fetchMonitors()
    availableMonitors.value = monitorStore.monitors
  } catch (err) {
    console.error('Failed to load monitors:', err)
  }
}

async function markAsPending(incidentId) {
  // Open modal to collect optional message instead of immediately acting
  openActionModal('pending', incidentId)
}

async function markAsSolved(incidentId) {
  // Open modal to collect optional message instead of immediately acting
  openActionModal('resolved', incidentId)
}

function openActionModal(type, incidentId) {
  actionType.value = type
  actionIncidentId.value = incidentId
  actionMessage.value = ''
  showActionModal.value = true
}

async function confirmAction() {
  if (!actionIncidentId.value || !actionType.value) return
  actionLoading.value = true

  try {
    console.log('confirmAction payload', { incidentId: actionIncidentId.value, type: actionType.value, note: actionMessage.value })
    let response
    if (actionType.value === 'pending') {
      response = await api.incidents.acknowledge(actionIncidentId.value, { note: actionMessage.value })
    } else if (actionType.value === 'resolved') {
      response = await api.incidents.resolve(actionIncidentId.value, { note: actionMessage.value })
    }
    console.log('confirmAction response', response)

    if (response?.data && response.data.success) {
      showActionModal.value = false
      await fetchIncidents()
    } else {
      alert(response?.data?.message || 'Gagal melakukan aksi pada incident')
    }
  } catch (err) {
    console.error('Action failed:', err)
    if (err.code === 'ERR_NETWORK') {
      alert('Server tidak dapat dijangkau. Pastikan Laravel server berjalan')
    } else {
      alert(`Terjadi kesalahan: ${err.response?.data?.message || err.message}`)
    }
  } finally {
    actionLoading.value = false
  }
}

function openClearModal(incidentId) {
  clearIncidentId.value = incidentId
  showClearModal.value = true
}

async function confirmClearData() {
  if (!clearIncidentId.value) return
  clearLoading.value = true

  try {
    console.log('Clearing incident data:', clearIncidentId.value)
    const response = await api.incidents.delete(clearIncidentId.value)
    console.log('Clear response:', response)

    if (response?.data && response.data.success) {
      showClearModal.value = false
      await fetchIncidents()
    } else {
      alert(response?.data?.message || 'Gagal menghapus incident')
    }
  } catch (err) {
    console.error('Clear failed:', err)
    if (err.code === 'ERR_NETWORK') {
      alert('Server tidak dapat dijangkau. Pastikan Laravel server berjalan')
    } else {
      alert(`Terjadi kesalahan: ${err.response?.data?.message || err.message}`)
    }
  } finally {
    clearLoading.value = false
  }
}

function getIncidentMessage(incident) {
  const logs = incident.alert_log || []
  if (Array.isArray(logs) && logs.length) {
    const last = logs[logs.length - 1]
    // Backend stores notes inside the 'metadata' object
    const meta = last.metadata || {}
    return meta.note || meta.resolution_note || meta.note_content || last.message || incident.error_message || '-'
  }
  return incident.error_message || incident.message || '-'
}

async function reopenIncident(incidentId) {
  if (!confirm('Apakah Anda yakin ingin membuka kembali incident ini?')) {
    return
  }
  
  try {
    const response = await api.incidents.reopen(incidentId)
    
    if (response.data && response.data.success) {
      await fetchIncidents()
    } else {
      alert(response.data?.message || 'Gagal membuka kembali incident')
    }
  } catch (err) {
    console.error('Failed to reopen incident:', err)
    
    if (err.code === 'ERR_NETWORK') {
      alert('Server tidak dapat dijangkau. Pastikan Laravel server berjalan')
    } else {
      alert(`Terjadi kesalahan: ${err.response?.data?.message || err.message}`)
    }
  }
}

async function addNote(incidentId) {
  const content = newNotes.value[incidentId]?.trim()
  if (!content) {
    alert('Silakan masukkan catatan terlebih dahulu')
    return
  }
  
  try {
    const response = await api.incidents.addNote(incidentId, { content })
    
    if (response.data && response.data.success) {
      newNotes.value[incidentId] = ''
      await fetchIncidents()
    } else {
      alert(response.data?.message || 'Gagal menambahkan catatan')
    }
  } catch (err) {
    console.error('Failed to add note:', err)
    
    if (err.code === 'ERR_NETWORK') {
      alert('Server tidak dapat dijangkau. Pastikan Laravel server berjalan')
    } else {
      alert(`Terjadi kesalahan: ${err.response?.data?.message || err.message}`)
    }
  }
}

function previousPage() {
  const currentPage = pagination.value.current_page
  if (currentPage > 1) {
    pagination.value.current_page = currentPage - 1
    console.log('Previous page:', pagination.value.current_page)
    fetchIncidents()
  }
}

function nextPage() {
  const currentPage = pagination.value.current_page
  const lastPage = pagination.value.last_page
  if (currentPage < lastPage) {
    pagination.value.current_page = currentPage + 1
    console.log('Next page:', pagination.value.current_page)
    fetchIncidents()
  }
}

function gotoPage(n) {
  // Ignore if dots clicked
  if (n === '...') return
  
  const page = Number(n)
  if (isNaN(page) || page < 1) return
  if (page === pagination.value.current_page) return
  
  pagination.value.current_page = page
  fetchIncidents()
}

function changePerPage() {
  pagination.value.current_page = 1
  fetchIncidents()
}

function getIncidentDuration(incident) {
  const start = new Date(incident.started_at)
  const end = incident.resolved_at ? new Date(incident.resolved_at) : new Date()
  
  const diffMs = end - start
  const diffMins = Math.floor(diffMs / 60000)
  const diffHours = Math.floor(diffMins / 60)
  const diffDays = Math.floor(diffHours / 24)
  
  if (diffDays > 0) {
    return `${diffDays}d ${diffHours % 24}h ${diffMins % 60}m`
  } else if (diffHours > 0) {
    return `${diffHours}h ${diffMins % 60}m`
  } else {
    return `${diffMins}m`
  }
}

function formatDate(dateString) {
  const date = new Date(dateString)
  return date.toLocaleDateString() + ' ' + date.toLocaleTimeString()
}

function getStatusLabel(status) {
  const statusLabels = {
    'open': 'OPEN',
    'pending': 'DITANGANI',
    'investigating': 'INVESTIGASI',
    'resolved': 'SELESAI'
  }
  return statusLabels[status] || status.toUpperCase()
}

async function retryConnection() {
  serverError.value = false
  loading.value = true
  
  try {
    await fetchIncidents()
    await fetchMonitors()
  } catch (err) {
    console.error('Retry failed:', err)
    serverError.value = true
    loading.value = false
  }
}
</script>

<style scoped>
.incidents {
  padding: 18px 28px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
  flex-wrap: wrap;
  gap: 12px;
  /* keep default small-screen spacing; enhanced styles applied at desktop breakpoint */
}

/* Desktop: center page and give header proper breathing space */
@media (min-width: 1024px) {
  .incidents { max-width: 1200px; margin: 0 auto; padding: 28px 20px; }

  .page-header {
    padding: 22px 24px;
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(15,23,36,0.04);
    margin-bottom: 20px;
    align-items: center;
    gap: 18px;
  }

  .page-header h1 {
    font-size: 1.9rem;
    margin: 0;
    line-height: 1;
  }

  .header-actions {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 16px;
  }

  .filter-group { min-width: 220px; }
}

.page-header h1 {
  margin: 0;
  color: #2c3e50;
}

.header-actions {
  display: flex;
  gap: 15px;
  flex-wrap: wrap;
}

.action-buttons {
  display: flex;
  gap: 10px;
  align-items: center;
}

.dropdown {
  position: relative;
}

.action-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  background: #ffffff;
  border: 1px solid #dfe6e9;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  color: #2d3436;
  cursor: pointer;
  transition: all 0.2s ease;
  white-space: nowrap;
}

.action-btn:hover {
  background: #f8f9fa;
  border-color: #b2bec3;
}

.dropdown.is-active .action-btn {
  background: #f8f9fa;
  border-color: #3498db;
}

.action-btn .icon {
  font-size: 16px;
  line-height: 1;
}

.dropdown-menu {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  min-width: 220px;
  background: #ffffff;
  border: 1px solid #dfe6e9;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  z-index: 100;
  animation: dropdownFadeIn 0.15s ease;
}

@keyframes dropdownFadeIn {
  from {
    opacity: 0;
    transform: translateY(-8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.dropdown-content {
  padding: 8px;
}

.dropdown-label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: #636e72;
  margin-bottom: 6px;
  padding: 0 8px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.dropdown-item {
  display: block;
  width: 100%;
  padding: 10px 12px;
  background: transparent;
  border: none;
  border-radius: 4px;
  text-align: left;
  font-size: 14px;
  color: #2d3436;
  cursor: pointer;
  transition: all 0.15s ease;
}

.dropdown-item:hover {
  background: #f1f3f5;
}

.dropdown-item.is-active {
  background: #e3f2fd;
  color: #2980b9;
  font-weight: 600;
}

.dropdown-content .form-control {
  margin-top: 4px;
}

.filter-group {
  min-width: 150px;
}

.incidents-list {
  display: block;
  max-width: none;
  margin: 0;
  width: 100%;
  padding: 0;
  list-style: none;
}

.incidents-list.view-card {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 10px;
  max-width: none;
}

/* List-mode (compact vertical list) */
.incidents-list.view-list .incident-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  box-shadow: none;
  border-left-width: 6px;
  border-radius: 4px;
  margin-bottom: 0;
  border-bottom: 1px solid #e8eaed;
  background: transparent;
}

/* List header and row layout */
.list-wrapper {
  max-width: none;
  margin: 0;
  padding: 0 8px;
  width: 100%;
}
.list-header {
  display: flex;
  gap: 12px;
  padding: 10px 16px;
  font-weight: 700;
  color: #445569;
  border-bottom: 2px solid #cbd5e0;
  border-top: 2px solid #cbd5e0;
  background: #f7fafc;
  align-items: center;
}
.list-header .col,
.list-row .col {
  padding: 6px 10px;
}
.list-header .name { flex: 2 1 240px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.list-header .status { flex: 0 0 110px; text-align: center; white-space: nowrap; }
.list-header .datetime { flex: 0 0 200px; text-align: left; white-space: nowrap; }
.list-header .message { flex: 2 1 350px; white-space: nowrap; }
.list-header .actions { flex: 0 0 360px; text-align: center; white-space: nowrap; }

.list-row {
  display: flex;
  gap: 12px;
  align-items: center;
  padding: 10px 16px;
}
.list-row .name { 
  flex: 2 1 240px; 
  white-space: nowrap; 
  overflow: hidden; 
  text-overflow: ellipsis;
  align-self: center;
}
.list-row .status { 
  flex: 0 0 110px; 
  text-align: center; 
  white-space: nowrap;
  align-self: center;
}
.list-row .datetime { 
  flex: 0 0 200px; 
  text-align: left; 
  color: #6b7a86; 
  font-size: 0.95em; 
  white-space: nowrap;
  align-self: center;
}
.list-row .message { 
  flex: 2 1 350px; 
  color: #3b4a54;
  word-wrap: break-word;
  white-space: normal;
  line-height: 1.5;
  overflow-wrap: break-word;
  align-self: center;
}
.list-row .message {
  cursor: help;
}
.list-row .actions { 
  flex: 0 0 360px; 
  text-align: right; 
  display: flex; 
  gap: 6px; 
  justify-content: flex-end; 
  align-items: center;
  align-self: center;
}

.list-row .actions .btn { margin-left: 0 }
.list-row .actions .btn-sm { padding: 6px 10px; min-width: 82px }
.list-row a { color: #2c3e50; text-decoration: none }
.list-row a:hover { color: #3498db }

/* Small tweak to status badge for compact list */
.incidents-list.view-list .status-badge { padding: 4px 8px; font-size: 0.75em }


.incidents-list.view-list .incident-header {
  padding: 0;
  display: flex;
  align-items: center;
  gap: 12px;
  border-bottom: none;
  flex: 1;
}

.incidents-list.view-list .incident-card {
  display: block;
  align-items: stretch;
  gap: 0;
  padding: 0;
  box-shadow: none;
  border-left-width: 6px;
  border-radius: 0;
  margin-bottom: 0;
  border-bottom: 1px solid #eef1f3;
  background: transparent;
}

.incidents-list.view-list .incident-card + .incident-card {
  /* ensure consistent separation */
  margin-top: 0;
}


/* Hide verbose sections in list mode for compactness */
.incidents-list.view-list .incident-details,
.incidents-list.view-list .incident-stats,
.incidents-list.view-list .incident-notes,
.incidents-list.view-list .add-note-form {
  display: none;
}

.incident-card {
  background: white;
  border-radius: 6px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.06);
  border-left: 3px solid #bdc3c7;
  overflow: hidden;
  display: block;
  width: 100%;
  margin-bottom: 12px;
  list-style: none;
  padding: 0;
}

/* Card-mode overrides */
.incidents-list.view-card .incident-card {
  display: flex;
  flex-direction: column;
  box-shadow: 0 1px 3px rgba(0,0,0,0.12);
  margin-bottom: 0;
}

.incident-card.incident-open {
  border-left-color: #e74c3c;
}

.incident-card.incident-pending {
  border-left-color: #f39c12;
}

.incident-card.incident-resolved {
  border-left-color: #27ae60;
}

.incident-header {
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  border-bottom: 1px solid #f0f0f0;
}

.incident-info {
  flex: 1;
  width: 100%;
}

.incident-title {
  margin: 0 0 8px 0;
  font-size: 1.05em;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 8px;
}

.incident-title a {
  color: #2c3e50;
  text-decoration: none;
}

.incident-title a:hover {
  color: #3498db;
}

.incident-meta {
  display: flex;
  gap: 12px;
  align-items: center;
  flex-wrap: wrap;
}

.status-badge {
  padding: 4px 10px;
  border-radius: 4px;
  font-size: 0.75em;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  box-shadow: 0 1px 2px rgba(0,0,0,0.1);
}

.status-badge.status-open {
  background-color: #ffebee;
  color: #c62828;
}

.status-badge.status-pending {
  background-color: #fff8e1;
  color: #ef6c00;
}

.status-badge.status-resolved {
  background-color: #e8f5e8;
  color: #2e7d32;
}

.incident-duration {
  font-size: 0.85em;
  color: #7f8c8d;
  font-weight: 500;
}

.incident-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  justify-content: flex-start;
  padding-top: 4px;
  border-top: 1px dashed #e8eaed;
}

.incident-details {
  padding: 12px;
  flex-grow: 1;
  background-color: #f8f9fa;
}

.incident-timeline {
  margin-bottom: 10px;
}

.timeline-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  margin-bottom: 8px;
  padding: 10px;
  background: white;
  border-radius: 6px;
  border-left: 4px solid #e8eaed;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.timeline-item:last-child {
  margin-bottom: 0;
}

.timeline-badge {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  color: white;
  font-size: 0.85em;
  box-shadow: 0 2px 6px rgba(0,0,0,0.15);
}

.timeline-badge.timeline-start {
  background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
}

.timeline-item:has(.timeline-start) {
  border-left-color: #e74c3c;
}

.timeline-badge.timeline-acknowledged {
  background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
}

.timeline-item:has(.timeline-acknowledged) {
  border-left-color: #f39c12;
}

.timeline-badge.timeline-resolved {
  background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
}

.timeline-item:has(.timeline-resolved) {
  border-left-color: #27ae60;
}

.timeline-content {
  flex: 1;
  min-width: 0;
}

.timeline-time {
  font-size: 0.7em;
  color: #95a5a6;
  margin-bottom: 4px;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.timeline-text {
  color: #2c3e50;
  font-size: 0.9em;
  line-height: 1.5;
}

.timeline-text strong {
  color: #1a252f;
  font-weight: 600;
}

.error-message {
  margin-top: 4px;
  padding: 6px 10px;
  background-color: #ffebee;
  border-left: 2px solid #e74c3c;
  border-radius: 3px;
  font-size: 0.85em;
  color: #c62828;
}

.incident-notes {
  margin-bottom: 10px;
}

.incident-notes h4 {
  margin: 0 0 8px 0;
  color: #5a6c7d;
  font-size: 0.8em;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.incident-note {
  background-color: white;
  border-radius: 6px;
  padding: 10px;
  margin-bottom: 6px;
  border: 1px solid #e8eaed;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.incident-note:last-child {
  margin-bottom: 0;
}

.note-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 5px;
}

.note-header strong {
  color: #2c3e50;
}

.note-time {
  font-size: 0.75em;
  color: #95a5a6;
}

.note-content {
  color: #34495e;
  font-size: 0.9em;
  line-height: 1.4;
}

.add-note-form {
  display: flex;
  gap: 8px;
  align-items: flex-start;
  background: white;
  padding: 10px;
  border-radius: 6px;
  border: 1px solid #e8eaed;
}

.add-note-form textarea {
  flex: 1;
  resize: vertical;
  min-height: 60px;
  border: 1px solid #dfe6ed;
  border-radius: 4px;
  padding: 8px;
  font-family: inherit;
}

.add-note-form textarea:focus {
  outline: none;
  border-color: #3498db;
  box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

.incident-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
  gap: 8px;
  padding: 12px;
  background: #f8f9fa;
  border-top: 1px solid #e8eaed;
  margin-top: auto;
}

.stat-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 8px;
  background: white;
  border-radius: 6px;
  border: 1px solid #e0e6ed;
  transition: all 0.2s ease;
}

.stat-item:hover {
  border-color: #3498db;
  box-shadow: 0 2px 6px rgba(52, 152, 219, 0.15);
  transform: translateY(-1px);
}

.stat-label {
  font-size: 0.65em;
  color: #7f8c8d;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-weight: 600;
}

.stat-value {
  font-weight: 700;
  color: #2c3e50;
  font-size: 0.95em;
}

.no-incidents {
  text-align: center;
  padding: 40px 20px;
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.server-error {
  text-align: center;
  padding: 40px 20px;
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  border-left: 4px solid #e74c3c;
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

.no-incidents h2 {
  margin: 0 0 15px 0;
  color: #2c3e50;
}

.no-incidents p {
  margin: 0;
  color: #7f8c8d;
}

/* Pagination Styles */
.pagination-wrapper {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 20px;
  padding: 16px 0;
  border-top: 1px solid #e8eaed;
  flex-wrap: wrap;
  gap: 16px;
}

.pagination-left {
  display: flex;
  align-items: center;
}

.pagination-showing {
  font-size: 14px;
  color: #636e72;
}

.pagination-right {
  display: flex;
  align-items: center;
  gap: 6px;
}

.pagination-nav-btn {
  padding: 6px 14px;
  background: #ffffff;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
  color: #555;
  cursor: pointer;
  transition: all 0.2s ease;
  font-weight: 400;
}

.pagination-nav-btn:hover:not(:disabled) {
  background: #f5f5f5;
  border-color: #aaa;
}

.pagination-nav-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  background: #f9f9f9;
  color: #999;
}

.pagination-pages {
  display: flex;
  gap: 4px;
  align-items: center;
}

.pagination-page-btn {
  min-width: 32px;
  height: 32px;
  padding: 0 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #ffffff;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
  color: #555;
  cursor: pointer;
  transition: all 0.2s ease;
}

.pagination-page-btn:hover:not(.active) {
  background: #f5f5f5;
  border-color: #aaa;
}

.pagination-page-btn.active {
  background: #007bff;
  color: white;
  border-color: #007bff;
  font-weight: 500;
}

.pagination-page-btn.dots {
  cursor: default;
  pointer-events: none;
  border-color: transparent;
  background: transparent;
}

.pagination-page-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.pagination-perpage {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-left: 8px;
  padding-left: 8px;
  border-left: 1px solid #ddd;
}

.pagination-perpage label {
  font-size: 14px;
  color: #636e72;
  font-weight: 400;
}

.perpage-select {
  padding: 5px 28px 5px 8px;
  background: #ffffff;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
  color: #555;
  cursor: pointer;
  transition: all 0.2s ease;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23555' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 8px center;
}

.perpage-select:hover {
  border-color: #aaa;
  background-color: #f5f5f5;
}

.perpage-select:focus {
  outline: none;
  border-color: #007bff;
  box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.1);
}

.form-control {
  width: 100%;
  padding: 8px 12px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
}

.form-control:focus {
  outline: none;
  border-color: #3498db;
  box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
}

.btn {
  padding: 6px 12px;
  border: none;
  border-radius: 4px;
  font-size: 14px;
  font-weight: 500;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-primary {
  background-color: #3498db;
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background-color: #2980b9;
}

.btn-secondary {
  background-color: #95a5a6;
  color: white;
}

.btn-secondary:hover:not(:disabled) {
  background-color: #7f8c8d;
}

.btn-warning {
  background-color: #f39c12;
  color: white;
}

.btn-warning:hover:not(:disabled) {
  background-color: #e67e22;
}

.btn-success {
  background-color: #27ae60;
  color: white;
}

.btn-success:hover:not(:disabled) {
  background-color: #229954;
}

.btn-danger {
  background-color: #e74c3c;
  color: white;
}

.btn-danger:hover:not(:disabled) {
  background-color: #c0392b;
}

.btn-sm {
  padding: 6px 12px;
  font-size: 0.75em;
  white-space: nowrap;
  font-weight: 600;
  box-shadow: 0 1px 3px rgba(0,0,0,0.12);
  transition: all 0.2s ease;
}

.btn-sm:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 2px 6px rgba(0,0,0,0.15);
}

/* View Link Button with Icon */
.btn-view-link {
  position: relative;
  overflow: hidden;
  transition: all 0.3s ease;
}

.btn-view-link .icon-link {
  width: 16px;
  height: 16px;
  margin-right: 4px;
  transition: all 0.3s ease;
  filter: brightness(0) invert(1);
}

.btn-view-link:hover {
  background: linear-gradient(135deg, #7f8c8d 0%, #636e72 100%);
  transform: translateY(-2px) scale(1.05);
  box-shadow: 0 4px 12px rgba(127, 140, 141, 0.4);
}

.btn-view-link:hover .icon-link {
  transform: rotate(15deg) scale(1.1);
}

.btn-view-link:active {
  transform: translateY(0) scale(0.98);
}

/* Action Buttons with Icons */
.btn-action {
  position: relative;
  overflow: hidden;
  transition: all 0.3s ease;
}

.btn-action .icon-action {
  width: 16px;
  height: 16px;
  margin-right: 4px;
  transition: all 0.3s ease;
  filter: brightness(0) invert(1);
}

.btn-warning.btn-action:hover:not(:disabled) {
  background: linear-gradient(135deg, #e67e22 0%, #d35400 100%);
  transform: translateY(-2px) scale(1.05);
  box-shadow: 0 4px 12px rgba(230, 126, 34, 0.4);
}

.btn-success.btn-action:hover:not(:disabled) {
  background: linear-gradient(135deg, #229954 0%, #1e8449 100%);
  transform: translateY(-2px) scale(1.05);
  box-shadow: 0 4px 12px rgba(39, 174, 96, 0.4);
}

.btn-action:hover:not(:disabled) .icon-action {
  transform: rotate(15deg) scale(1.1);
}

.btn-action:active:not(:disabled) {
  transform: translateY(0) scale(0.98);
}

.btn-action:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-action:disabled .icon-action {
  opacity: 0.6;
}

.btn-danger.btn-action:hover:not(:disabled) {
  background: linear-gradient(135deg, #c0392b 0%, #a93226 100%);
  transform: translateY(-2px) scale(1.05);
  box-shadow: 0 4px 12px rgba(231, 76, 60, 0.4);
}

/* Modal Styles */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  animation: fadeIn 0.2s ease;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.modal {
  background: white;
  border-radius: 8px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
  max-width: 500px;
  width: 90%;
  animation: slideUp 0.3s ease;
}

@keyframes slideUp {
  from {
    transform: translateY(20px);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  border-bottom: 1px solid #e8eaed;
}

.modal-header h3 {
  margin: 0;
  font-size: 1.1rem;
  color: #2c3e50;
}

.modal-body {
  padding: 20px;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 16px 20px;
  border-top: 1px solid #e8eaed;
  background: #f8f9fa;
}

.form-label {
  display: block;
  margin-bottom: 8px;
  font-weight: 600;
  color: #2c3e50;
  font-size: 0.9rem;
}

.warning-text {
  font-size: 1rem;
  color: #e74c3c;
  font-weight: 600;
  margin: 0 0 8px 0;
}

.warning-subtext {
  font-size: 0.9rem;
  color: #7f8c8d;
  margin: 0;
}

/* Icon placeholders */
.icon-alert::before { content: '⚠'; }
.icon-check::before { content: '✓'; }
.icon-check-circle::before { content: '✅'; }

/* Responsive Design */
@media (max-width: 1024px) {
  .incident-stats {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 768px) {
  .incidents {
    padding: 1rem;
    padding-top: 5rem;
  }
  .incidents-list {
    max-width: 100%;
  }
  .list-header { display: none }

  /* Stack each row vertically and show key fields */
  .incidents-list.view-list .incident-card {
    display: block;
    padding: 0; /* card inner padding handled below */
    border-bottom: none;
    background: transparent;
    border-radius: 0;
    margin-bottom: 0;
  }

  .list-row { display: block }
  .list-row .name { display: block; margin-bottom: 8px; font-weight: 700; font-size: 1rem }
  .list-row .status { display: inline-block; margin-right: 8px }
  .list-row .datetime { display: block; color: #7f8c8d; margin-top: 6px; font-size: 0.92rem }
  .list-row .message { display: block; margin-top: 8px; color: #34495e; line-height: 1.4 }

  /* Wrap each incident in a subtle card for mobile */
  .incidents-list.view-list .incident-card > .list-row {
    background: #ffffff;
    border-radius: 10px;
    box-shadow: 0 6px 16px rgba(15,23,36,0.04);
    padding: 12px;
    margin: 0 0 12px 0;
  }
  .list-row .actions { margin-top: 8px; display: flex; gap: 8px }

  /* Button layout: stack and stretch for easy tapping on mobile */
  .list-row .actions .btn {
    flex: 1 1 auto;
    white-space: normal;
    text-align: center;
    min-width: 0;
  }

  /* Ensure action buttons have a sensible minimum width so labels fit */
  .list-row .actions .btn-sm {
    min-width: 112px;
    padding: 8px 12px;
  }
  
  .pagination-wrapper {
    flex-direction: column;
    gap: 12px;
    margin-top: 16px;
  }
  
  .pagination-left {
    order: 2;
    width: 100%;
    justify-content: center;
  }
  
  .pagination-right {
    order: 1;
    width: 100%;
    justify-content: center;
    flex-wrap: wrap;
  }
  
  .pagination-nav-btn {
    flex: 0 0 auto;
  }
  
  .pagination-pages {
    flex: 1;
    justify-content: center;
  }
  
  .page-header {
    padding: 1rem;
    flex-direction: column;
    align-items: stretch;
    gap: 1rem;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 8px 24px rgba(15,23,36,0.04);
    padding-top: 1.25rem;
    padding-bottom: 1.25rem;
  }
  
  .page-header h1 {
    text-align: center;
    margin-bottom: 0.5rem;
  }
  
  .header-actions {
    flex-direction: column;
    gap: 0.75rem;
  }
  
  .filter-group {
    min-width: auto;
    flex: 1;
  }
  
  .incident-card {
    padding: 1.25rem;
  }
  
  .incident-header {
    flex-direction: column;
    align-items: stretch;
    gap: 1rem;
  }
  
  .incident-actions {
    flex-direction: column;
    gap: 0.5rem;
  }
  
  .incident-actions .btn {
    width: 100%;
    justify-content: center;
  }
  
  .incident-stats {
    grid-template-columns: 1fr;
    gap: 0.75rem;
  }
  
  .add-note-form {
    flex-direction: column;
    gap: 0.75rem;
  }
  
  .add-note-form textarea {
    min-height: 80px;
  }
  
  .add-note-form .btn {
    width: 100%;
  }
  
  .pagination-wrapper {
    margin-top: 16px;
    padding: 12px 0;
  }
  
  .pagination-container {
    flex-direction: column;
    gap: 12px;
  }
  
  .pagination-btn {
    width: 100%;
    justify-content: center;
  }
  
  .pagination-pages {
    order: -1;
  }
  
  .pagination-info {
    order: -2;
    padding: 8px 12px;
    background: #f8f9fa;
    border-radius: 6px;
    width: 100%;
    justify-content: center;
  }
  
  .pagination-perpage {
    width: 100%;
    justify-content: center;
    padding-left: 0;
    border-left: none;
    border-top: 1px solid #e8eaed;
    padding-top: 12px;
  }
}

@media (max-width: 480px) {
  .incidents {
    padding: 0.75rem;
  }
  
  .page-header {
    padding: 1rem;
  }
  
  .page-header h1 {
    font-size: 1.5rem;
  }
  
  .incident-card {
    padding: 1rem;
  }
  
  .incident-title {
    font-size: 1rem;
  }
  
  .incident-meta {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
  }
  
  .status-badge {
    font-size: 0.7rem;
    padding: 0.25rem 0.625rem;
  }
  
  .timeline-item {
    padding-left: 1.5rem;
  }
  
  .timeline-item::before {
    left: 0.25rem;
    width: 0.5rem;
    height: 0.5rem;
  }
  
  .timeline-item::after {
    left: 0.5rem;
  }
  
  .note-item {
    padding: 0.875rem;
  }
  
  .btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.75rem;
  }
}

/* Ensure list view on small screens: force list-row appearance (not card) */
@media (max-width: 768px) {
  .incidents-list.view-list .incident-card {
    display: block !important;
    padding: 8px 12px !important;
    box-shadow: none !important;
    border-left-width: 0 !important;
    border-bottom: 1px solid #eef1f3 !important;
    border-radius: 0 !important;
    background: transparent !important;
    margin-bottom: 0 !important;
  }

  .incidents-list.view-list .list-row {
    display: block !important;
    padding: 0 !important;
  }

  .list-row .name { display: block !important; margin-bottom: 6px; font-weight: 700 }
  .list-row .status { display: inline-block !important; margin-right: 8px }
  .list-row .datetime { display: block !important; color: #7f8c8d; margin-top: 6px }
  .list-row .message { display: block !important; margin-top: 6px; color: #34495e }

  .list-row .actions {
    margin-top: 8px !important;
    display: flex !important;
    gap: 8px !important;
    justify-content: flex-start !important;
    flex-wrap: wrap !important;
  }

  .list-row .actions .btn-sm { min-width: 120px !important }
}
</style>