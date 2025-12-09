<template>
  <div class="incidents">
    <div class="page-header">
      <h1>Incidents</h1>
      
      <div class="header-actions">
        <div class="filter-group">
          <select v-model="statusFilter" class="form-control">
            <option value="">All Statuses</option>
            <option value="open">Open</option>
            <option value="pending">Pending (Ditangani)</option>
            <option value="resolved">Resolved (Selesai)</option>
          </select>
        </div>
        
        <div class="filter-group">
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
        </div>
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
    
    <div v-else class="incidents-list">
      <div
        v-for="incident in filteredIncidents"
        :key="incident.id"
        class="incident-card"
        :class="`incident-${incident.status || 'open'}`"
      >
        <div class="incident-header">
          <div class="incident-info">
            <h3 class="incident-title">
              <router-link :to="`/monitors/${incident.monitor_id}`">
                {{ incident.monitor_name }}
              </router-link>
            </h3>
            <div class="incident-meta">
              <span 
                class="status-badge"
                :class="`status-${incident.status || 'open'}`"
              >
                {{ getStatusLabel(incident.status || 'open') }}
              </span>
              <span class="incident-duration">
                {{ getIncidentDuration(incident) }}
              </span>
            </div>
          </div>
          
          <div class="incident-actions">
            <button
              v-if="incident.status === 'open' || !incident.status"
              @click="markAsPending(incident.id)"
              class="btn btn-warning btn-sm"
            >
              Tandai Ditangani
            </button>
            
            <button
              v-if="incident.status !== 'resolved'"
              @click="markAsSolved(incident.id)"
              class="btn btn-success btn-sm"
            >
              Tandai Selesai
            </button>
            
            <button
              v-if="incident.status === 'resolved'"
              @click="reopenIncident(incident.id)"
              class="btn btn-warning btn-sm"
            >
              Buka Kembali
            </button>
          </div>
        </div>
        
        <div class="incident-details">
          <div class="incident-timeline">
            <div class="timeline-item">
              <div class="timeline-badge timeline-start">
                <i class="icon-alert"></i>
              </div>
              <div class="timeline-content">
                <div class="timeline-time">{{ formatDate(incident.started_at) }}</div>
                <div class="timeline-text">
                  <strong>Incident started</strong>
                  <div v-if="incident.error_message" class="error-message">
                    {{ incident.error_message }}
                  </div>
                </div>
              </div>
            </div>
            
            <div v-if="incident.acknowledged_at" class="timeline-item">
              <div class="timeline-badge timeline-acknowledged">
                <i class="icon-check"></i>
              </div>
              <div class="timeline-content">
                <div class="timeline-time">{{ formatDate(incident.acknowledged_at) }}</div>
                <div class="timeline-text">
                  <strong>Acknowledged</strong>
                  <span v-if="incident.acknowledged_by">by {{ incident.acknowledged_by }}</span>
                </div>
              </div>
            </div>
            
            <div v-if="incident.resolved_at" class="timeline-item">
              <div class="timeline-badge timeline-resolved">
                <i class="icon-check-circle"></i>
              </div>
              <div class="timeline-content">
                <div class="timeline-time">{{ formatDate(incident.resolved_at) }}</div>
                <div class="timeline-text">
                  <strong>Resolved</strong>
                  <span v-if="incident.resolved_by">by {{ incident.resolved_by }}</span>
                </div>
              </div>
            </div>
          </div>
          
          <div v-if="incident.notes && incident.notes.length > 0" class="incident-notes">
            <h4>Notes</h4>
            <div
              v-for="note in incident.notes"
              :key="note.id"
              class="incident-note"
            >
              <div class="note-header">
                <strong>{{ note.created_by || 'System' }}</strong>
                <span class="note-time">{{ formatDate(note.created_at) }}</span>
              </div>
              <div class="note-content">{{ note.content }}</div>
            </div>
          </div>
          
          <!-- Add Note Form -->
          <div v-if="incident.status !== 'resolved'" class="add-note-form">
            <textarea
              v-model="newNotes[incident.id]"
              placeholder="Add a note about this incident..."
              class="form-control"
              rows="2"
            ></textarea>
            <button
              @click="addNote(incident.id)"
              :disabled="!newNotes[incident.id]?.trim()"
              class="btn btn-primary btn-sm"
            >
              Add Note
            </button>
          </div>
        </div>
        
        <!-- Incident Stats -->
        <div class="incident-stats">
          <div class="stat-item">
            <span class="stat-label">Check Failures:</span>
            <span class="stat-value">{{ incident.failure_count || 0 }}</span>
          </div>
          
          <div v-if="incident.last_check_at" class="stat-item">
            <span class="stat-label">Last Check:</span>
            <span class="stat-value">{{ formatDate(incident.last_check_at) }}</span>
          </div>
          
          <div v-if="incident.expected_recovery_at" class="stat-item">
            <span class="stat-label">Expected Recovery:</span>
            <span class="stat-value">{{ formatDate(incident.expected_recovery_at) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="pagination.total > pagination.per_page" class="pagination">
      <button
        @click="previousPage"
        :disabled="pagination.current_page === 1"
        class="btn btn-secondary"
      >
        Previous
      </button>
      
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

const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 20,
  total: 0
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
  if (!confirm('Apakah Anda yakin ingin menandai incident ini sebagai sedang ditangani?')) {
    return
  }
  
  try {
    const response = await api.incidents.acknowledge(incidentId)
    
    if (response.data && response.data.success) {
      await fetchIncidents()
    } else {
      alert(response.data?.message || 'Gagal menandai incident sebagai pending')
    }
  } catch (err) {
    console.error('Failed to mark incident as pending:', err)
    
    if (err.response?.status === 404) {
      alert('Endpoint tidak ditemukan. Pastikan Laravel server berjalan')
    } else if (err.response?.status === 401) {
      alert('Sesi login expired. Silakan login kembali.')
    } else if (err.code === 'ERR_NETWORK') {
      alert('Server tidak dapat dijangkau. Pastikan Laravel server berjalan di http://localhost:8000')
    } else {
      alert(`Terjadi kesalahan: ${err.response?.data?.message || err.message}`)
    }
  }
}

async function markAsSolved(incidentId) {
  if (!confirm('Apakah Anda yakin ingin menandai incident ini sebagai selesai?')) {
    return
  }
  
  try {
    const response = await api.incidents.resolve(incidentId)
    
    if (response.data && response.data.success) {
      await fetchIncidents()
    } else {
      alert(response.data?.message || 'Gagal menandai incident sebagai selesai')
    }
  } catch (err) {
    console.error('Failed to mark incident as solved:', err)
    
    if (err.code === 'ERR_NETWORK') {
      alert('Server tidak dapat dijangkau. Pastikan Laravel server berjalan')
    } else {
      alert(`Terjadi kesalahan: ${err.response?.data?.message || err.message}`)
    }
  }
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
  padding: 20px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  flex-wrap: wrap;
  gap: 20px;
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
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.incident-card {
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  border-left: 4px solid #bdc3c7;
  overflow: hidden;
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
  padding: 20px 20px 0 20px;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 15px;
}

.incident-info {
  flex: 1;
}

.incident-title {
  margin: 0 0 10px 0;
  font-size: 1.1em;
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
  gap: 15px;
  align-items: center;
  flex-wrap: wrap;
}

.status-badge {
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 0.8em;
  font-weight: bold;
  text-transform: uppercase;
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
  font-size: 0.9em;
  color: #7f8c8d;
}

.incident-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.incident-details {
  padding: 20px;
}

.incident-timeline {
  margin-bottom: 20px;
}

.timeline-item {
  display: flex;
  align-items: flex-start;
  gap: 15px;
  margin-bottom: 15px;
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
  font-size: 0.8em;
}

.timeline-badge.timeline-start {
  background-color: #e74c3c;
}

.timeline-badge.timeline-acknowledged {
  background-color: #f39c12;
}

.timeline-badge.timeline-resolved {
  background-color: #27ae60;
}

.timeline-content {
  flex: 1;
}

.timeline-time {
  font-size: 0.8em;
  color: #7f8c8d;
  margin-bottom: 5px;
}

.timeline-text {
  color: #2c3e50;
}

.error-message {
  margin-top: 5px;
  padding: 8px 12px;
  background-color: #ffebee;
  border-left: 3px solid #e74c3c;
  border-radius: 4px;
  font-size: 0.9em;
  color: #c62828;
}

.incident-notes {
  margin-bottom: 20px;
}

.incident-notes h4 {
  margin: 0 0 15px 0;
  color: #2c3e50;
  font-size: 1em;
}

.incident-note {
  background-color: #f8f9fa;
  border-radius: 4px;
  padding: 15px;
  margin-bottom: 10px;
}

.incident-note:last-child {
  margin-bottom: 0;
}

.note-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.note-header strong {
  color: #2c3e50;
}

.note-time {
  font-size: 0.8em;
  color: #7f8c8d;
}

.note-content {
  color: #34495e;
  line-height: 1.4;
}

.add-note-form {
  display: flex;
  gap: 10px;
  align-items: flex-start;
}

.add-note-form textarea {
  flex: 1;
  resize: vertical;
}

.incident-stats {
  display: flex;
  gap: 20px;
  padding: 15px 20px;
  background-color: #f8f9fa;
  border-top: 1px solid #ecf0f1;
  flex-wrap: wrap;
}

.stat-item {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.stat-label {
  font-size: 0.8em;
  color: #7f8c8d;
  text-transform: uppercase;
}

.stat-value {
  font-weight: 500;
  color: #2c3e50;
}

.no-incidents {
  text-align: center;
  padding: 60px 20px;
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.server-error {
  text-align: center;
  padding: 60px 20px;
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
  gap: 20px;
  margin-top: 30px;
  padding: 20px;
}

.pagination-info {
  color: #7f8c8d;
  font-size: 0.9em;
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
  padding: 4px 8px;
  font-size: 0.8em;
}

/* Icon placeholders */
.icon-alert::before { content: '⚠'; }
.icon-check::before { content: '✓'; }
.icon-check-circle::before { content: '✅'; }

@media (max-width: 768px) {
  .incidents {
    padding: 10px;
  }
  
  .page-header {
    flex-direction: column;
    align-items: stretch;
  }
  
  .header-actions {
    justify-content: stretch;
  }
  
  .filter-group {
    min-width: auto;
    flex: 1;
  }
  
  .incident-header {
    flex-direction: column;
    align-items: stretch;
  }
  
  .incident-actions {
    justify-content: stretch;
  }
  
  .incident-stats {
    flex-direction: column;
    gap: 10px;
  }
  
  .add-note-form {
    flex-direction: column;
    gap: 10px;
  }
  
  .pagination {
    flex-direction: column;
    gap: 10px;
  }
}
</style>