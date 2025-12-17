<template>
  <div class="incidents">
    <div class="page-header">
      <h1>Incidents</h1>
      
      <div class="header-actions">
        <div class="filter-group">
          <select v-model="statusFilter" class="form-control">
            <option value="">All Statuses</option>
            <option value="pending">Pending (Ditangani)</option>
            <option value="resolved">Resolved (Selesai)</option>
          </select>
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
              <div class="col message">{{ getIncidentMessage(incident) }}</div>
              <div class="col actions">
                <button
                  class="btn btn-warning btn-sm"
                  :disabled="incident.status === 'pending' || incident.status === 'resolved'"
                  @click="openActionModal('pending', incident.id)"
                >
                  Ditangani
                </button>

                <button
                  class="btn btn-success btn-sm"
                  :disabled="incident.status === 'resolved'"
                  @click="openActionModal('resolved', incident.id)"
                >
                  Selesai
                </button>
              </div>
            </div>
          </li>
        </ul>
      </div>
    </div>

    <!-- Pagination -->
    <!-- Pagination -->
    <div v-if="pagination.total > pagination.per_page" class="pagination">
      <button
        @click="previousPage"
        :disabled="pagination.current_page === 1"
        class="btn btn-secondary"
      >
        Previous
      </button>

      <div class="pagination-pages">
        <button
          v-for="p in pagesList"
          :key="p"
          class="btn btn-secondary btn-sm"
          :class="{ 'active-page': p === pagination.current_page }"
          @click="gotoPage(p)"
        >
          {{ p }}
        </button>
      </div>

      <span class="pagination-info">
        Page {{ pagination.current_page }} of {{ pagination.last_page }}
        ({{ pagination.total }} total incidents)
      </span>
      
      <button
        @click="nextPage"
        :disabled="pagination.current_page === pagination.last_page"
        class="btn btn-secondary"
      >
        Next
      </button>

      <div class="pagination-select">
        <label>Per page:</label>
        <select v-model.number="pagination.per_page" @change="changePerPage" class="form-control">
          <option :value="10">10</option>
          <option :value="20">20</option>
          <option :value="50">50</option>
        </select>
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

const pagesList = computed(() => {
  const last = pagination.value.last_page || 1
  const arr = []
  for (let i = 1; i <= last; i++) arr.push(i)
  return arr
})

const filteredIncidents = computed(() => {
  // Return incidents as-is since filtering is done server-side
  return incidents.value
})

onMounted(async () => {
  await fetchIncidents()
  await fetchMonitors()
})

watch([statusFilter, monitorFilter], () => {
  pagination.value.current_page = 1
  fetchIncidents()
})

async function fetchIncidents() {
  loading.value = true
  
  try {
    const params = {
      page: pagination.value.current_page,
      per_page: pagination.value.per_page
    }
    
    if (statusFilter.value) {
      params.status = statusFilter.value
    }
    
    if (monitorFilter.value) {
      params.monitor_id = monitorFilter.value
    }
    
    const response = await api.incidents.getAll(params)
    
    if (response.data && response.data.success) {
      const data = response.data.data
      
      // Handle both paginated and non-paginated responses
      if (data.data) {
        // Paginated response
        incidents.value = data.data
        if (data.meta) {
          pagination.value = {
            current_page: data.meta.current_page || 1,
            last_page: data.meta.last_page || 1,
            per_page: data.meta.per_page || 20,
            total: data.meta.total || 0
          }
        }
      } else if (Array.isArray(data)) {
        // Direct array response
        incidents.value = data
      } else {
        incidents.value = []
      }
    } else {
      incidents.value = []
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
  if (pagination.value.current_page > 1) {
    pagination.value.current_page--
    fetchIncidents()
  }
}

function nextPage() {
  if (pagination.value.current_page < pagination.value.last_page) {
    pagination.value.current_page++
    fetchIncidents()
  }
}

function gotoPage(n) {
  const page = Number(n) || 1
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
  border-bottom: 2px solid #edf2f4;
  align-items: center;
}
.list-header .col,
.list-row .col {
  padding: 6px 10px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.list-header .name { flex: 2 1 260px; }
.list-header .status { flex: 0 0 120px; text-align: center }
.list-header .datetime { flex: 0 0 220px; text-align: left }
.list-header .message { flex: 1 1 260px }
.list-header .actions { flex: 0 0 280px; text-align: right }

.list-row {
  display: flex;
  gap: 12px;
  align-items: center;
  padding: 10px 16px;
}
.list-row .name { flex: 2 1 260px; }
.list-row .status { flex: 0 0 120px; text-align: center }
.list-row .datetime { flex: 0 0 220px; text-align: left; color: #6b7a86; font-size: 0.95em }
.list-row .message { flex: 1 1 260px; color: #3b4a54 }
.list-row .actions { 
  flex: 0 0 280px; 
  text-align: right; 
  display: flex; 
  gap: 12px; 
  justify-content: flex-end; 
  align-items: center;
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

.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 15px;
  margin-top: 16px;
  padding: 12px;
}

.pagination-info {
  color: #7f8c8d;
  font-size: 0.9em;
}

.pagination-pages {
  display: flex;
  gap: 6px;
  align-items: center;
}
.pagination-select {
  display: flex;
  gap: 8px;
  align-items: center;
  margin-left: 12px;
}
.active-page {
  background: #3498db;
  color: white;
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
    padding: 8px 12px;
    border-bottom: 1px solid #eef1f3;
    background: transparent;
    border-radius: 0;
    margin-bottom: 0;
  }

  .list-row { display: block }
  .list-row .name { display: block; margin-bottom: 6px; font-weight: 700 }
  .list-row .status { display: inline-block; margin-right: 8px }
  .list-row .datetime { display: block; color: #7f8c8d; margin-top: 6px }
  .list-row .message { display: block; margin-top: 6px; color: #34495e }
  .list-row .actions { margin-top: 8px; display: flex; gap: 8px }

  /* Button layout: keep natural width on desktop, expand on small screens */
  .list-row .actions .btn {
    flex: 0 0 auto;
    white-space: nowrap;
    text-align: center;
  }

  /* Ensure action buttons have a sensible minimum width so labels fit */
  .list-row .actions .btn-sm {
    min-width: 120px;
    padding: 6px 12px;
  }
  
  .page-header {
    padding: 1.25rem;
    flex-direction: column;
    align-items: stretch;
    gap: 1rem;
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
  
  .pagination {
    flex-direction: column;
    gap: 0.75rem;
  }
  
  .pagination .btn {
    width: 100%;
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
</style>