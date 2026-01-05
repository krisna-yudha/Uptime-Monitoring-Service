<template>
  <div class="incident-detail">
    <div v-if="loading" class="loading">Loading incident details...</div>
    
    <div v-else-if="error" class="error-view">
      <h2>Error</h2>
      <p>{{ error }}</p>
      <router-link to="/incidents" class="btn btn-primary">Back to Incidents</router-link>
    </div>
    
    <div v-else-if="incident" class="incident-content">
      <!-- Header -->
      <div class="incident-header">
        <div class="header-top">
          <router-link to="/incidents" class="back-link">
            ← Back to Incidents
          </router-link>
          <span class="status-badge" :class="`status-${incident.status || 'open'}`">
            {{ getStatusLabel(incident.status || 'open') }}
          </span>
        </div>
        
        <h1>Incident #{{ incident.id }}</h1>
        
        <div class="monitor-info">
          <router-link :to="`/monitors/${incident.monitor_id}`" class="monitor-link">
            <strong>Monitor:</strong> {{ incident.monitor_name }}
          </router-link>
          <span v-if="incident.monitor?.type" class="monitor-type">
            Type: {{ incident.monitor.type.toUpperCase() }}
          </span>
          <span v-if="incident.monitor?.target" class="monitor-target">
            Target: {{ incident.monitor.target }}
          </span>
          <a 
            v-if="incident.monitor?.target" 
            :href="incident.monitor.target" 
            target="_blank" 
            rel="noopener noreferrer"
            class="btn-visit-website"
            title="Visit Website"
          >
            🔗 Visit Website
          </a>
        </div>
      </div>
      
      <!-- Stats Cards -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-label">Started At</div>
          <div class="stat-value">{{ formatDate(incident.started_at) }}</div>
        </div>
        
        <div class="stat-card">
          <div class="stat-label">Duration</div>
          <div class="stat-value">{{ getIncidentDuration(incident) }}</div>
        </div>
        
        <div class="stat-card" v-if="incident.acknowledged_at">
          <div class="stat-label">Acknowledged At</div>
          <div class="stat-value">{{ formatDate(incident.acknowledged_at) }}</div>
        </div>
        
        <div class="stat-card" v-if="incident.resolved_at">
          <div class="stat-label">Resolved At</div>
          <div class="stat-value">{{ formatDate(incident.resolved_at) }}</div>
        </div>
      </div>
      
      <!-- Timeline -->
      <div class="timeline-section">
        <h2>Timeline</h2>
        <div class="timeline">
          <!-- Start -->
          <div class="timeline-item">
            <div class="timeline-badge timeline-start">⚠</div>
            <div class="timeline-content">
              <div class="timeline-time">{{ formatDate(incident.started_at) }}</div>
              <div class="timeline-text">
                <strong>Incident Started</strong><br>
                Service went down
              </div>
            </div>
          </div>
          
          <!-- Acknowledged -->
          <div v-if="incident.acknowledged_at" class="timeline-item">
            <div class="timeline-badge timeline-acknowledged">👁</div>
            <div class="timeline-content">
              <div class="timeline-time">{{ formatDate(incident.acknowledged_at) }}</div>
              <div class="timeline-text">
                <strong>Acknowledged</strong><br>
                Incident is being investigated
                <span v-if="incident.acknowledged_by"> by {{ incident.acknowledged_by }}</span>
              </div>
            </div>
          </div>
          
          <!-- Resolved -->
          <div v-if="incident.resolved_at" class="timeline-item">
            <div class="timeline-badge timeline-resolved">✓</div>
            <div class="timeline-content">
              <div class="timeline-time">{{ formatDate(incident.resolved_at) }}</div>
              <div class="timeline-text">
                <strong>Resolved</strong><br>
                Service is back online
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Error Details -->
      <div v-if="incident.error_message || incident.description" class="details-section">
        <h2>Details</h2>
        <div class="error-box">
          <h3>Error Message</h3>
          <pre>{{ incident.error_message || incident.description || 'No error message available' }}</pre>
        </div>
      </div>
      
      <!-- Alert Log -->
      <div v-if="incident.alert_log && incident.alert_log.length" class="alert-log-section">
        <h2>Alert Log</h2>
        <div class="alert-log">
          <div v-for="(log, index) in incident.alert_log" :key="index" class="log-entry">
            <div class="log-time">{{ formatDate(log.timestamp || log.created_at) }}</div>
            <div class="log-message">{{ log.message || log.error }}</div>
          </div>
        </div>
      </div>
      
      <!-- Actions -->
      <div class="actions-section">
        <h2>Actions</h2>
        <div class="action-buttons">
          <button
            v-if="incident.status !== 'pending' && incident.status !== 'resolved'"
            class="btn btn-warning"
            @click="markAsPending"
          >
            Mark as Pending
          </button>
          
          <button
            v-if="incident.status !== 'resolved'"
            class="btn btn-success"
            @click="markAsResolved"
          >
            Mark as Resolved
          </button>
          
          <button
            v-if="incident.status === 'resolved'"
            class="btn btn-secondary"
            @click="reopenIncident"
          >
            Reopen Incident
          </button>
          
          <button
            class="btn btn-danger"
            @click="deleteIncident"
          >
            Delete Incident
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../services/api'

const route = useRoute()
const router = useRouter()

const incident = ref(null)
const loading = ref(true)
const error = ref(null)

onMounted(async () => {
  await fetchIncident()
})

async function fetchIncident() {
  loading.value = true
  error.value = null
  
  try {
    const response = await api.incidents.getById(route.params.id)
    
    if (response.data && response.data.success) {
      incident.value = response.data.data
    } else {
      error.value = 'Incident not found'
    }
  } catch (err) {
    console.error('Failed to load incident:', err)
    error.value = err.response?.data?.message || 'Failed to load incident details'
  } finally {
    loading.value = false
  }
}

async function markAsPending() {
  if (!confirm('Mark this incident as pending (under investigation)?')) return
  
  try {
    await api.incidents.markPending(incident.value.id)
    await fetchIncident()
  } catch (err) {
    alert('Failed to update incident: ' + (err.response?.data?.message || err.message))
  }
}

async function markAsResolved() {
  if (!confirm('Mark this incident as resolved?')) return
  
  try {
    await api.incidents.markResolved(incident.value.id)
    await fetchIncident()
  } catch (err) {
    alert('Failed to update incident: ' + (err.response?.data?.message || err.message))
  }
}

async function reopenIncident() {
  if (!confirm('Reopen this incident?')) return
  
  try {
    await api.incidents.reopen(incident.value.id)
    await fetchIncident()
  } catch (err) {
    alert('Failed to reopen incident: ' + (err.response?.data?.message || err.message))
  }
}

async function deleteIncident() {
  if (!confirm('Are you sure you want to delete this incident? This action cannot be undone.')) return
  
  try {
    await api.incidents.delete(incident.value.id)
    router.push('/incidents')
  } catch (err) {
    alert('Failed to delete incident: ' + (err.response?.data?.message || err.message))
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
  if (!dateString) return '-'
  const date = new Date(dateString)
  return date.toLocaleDateString() + ' ' + date.toLocaleTimeString()
}

function getStatusLabel(status) {
  const statusLabels = {
    open: 'Down',
    pending: 'Ditangani',
    resolved: 'Selesai'
  }
  return statusLabels[status] || status.toUpperCase()
}
</script>

<style scoped>
.incident-detail {
  max-width: 1200px;
  margin: 0 auto;
  padding: 24px;
}

.loading {
  text-align: center;
  padding: 80px 20px;
  font-size: 20px;
  color: #667eea;
  font-weight: 700;
  background: white;
  border-radius: 16px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.error-view {
  text-align: center;
  padding: 60px 40px;
  background: white;
  border-radius: 16px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.error-view h2 {
  color: #e74c3c;
  margin-bottom: 20px;
  font-size: 28px;
  font-weight: 700;
}

.incident-content {
  background: white;
  border-radius: 16px;
  box-shadow: 0 8px 32px rgba(0,0,0,0.12);
  overflow: hidden;
}

.incident-header {
  padding: 32px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 16px 16px 0 0;
  color: white;
}

.header-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.back-link {
  color: white;
  text-decoration: none;
  font-weight: 600;
  transition: all 0.3s;
  padding: 8px 16px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 8px;
  backdrop-filter: blur(10px);
}

.back-link:hover {
  background: rgba(255, 255, 255, 0.3);
  transform: translateX(-4px);
}

.incident-header h1 {
  margin: 0 0 20px 0;
  color: white;
  font-size: 32px;
  font-weight: 700;
  text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.monitor-info {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
  align-items: center;
  font-size: 14px;
  color: rgba(255, 255, 255, 0.95);
}

.monitor-link {
  color: white;
  text-decoration: none;
  font-weight: 600;
  padding: 6px 12px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 8px;
  transition: all 0.3s;
}

.monitor-link:hover {
  background: rgba(255, 255, 255, 0.3);
}

.monitor-type,
.monitor-target {
  padding: 6px 12px;
  background: rgba(255, 255, 255, 0.15);
  border-radius: 8px;
  font-family: 'Courier New', monospace;
  font-size: 13px;
  backdrop-filter: blur(10px);
  font-weight: 500;
}

.btn-visit-website {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  background: rgba(255, 255, 255, 0.25);
  color: white;
  text-decoration: none;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  transition: all 0.3s;
  backdrop-filter: blur(10px);
}

.btn-visit-website:hover {
  background: rgba(255, 255, 255, 0.35);
  transform: translateY(-2px);
}

.status-badge {
  padding: 8px 20px;
  border-radius: 20px;
  font-size: 13px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.status-badge.status-open {
  background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
  color: white;
}

.status-badge.status-pending {
  background: linear-gradient(135deg, #ffa726 0%, #fb8c00 100%);
  color: white;
}

.status-badge.status-resolved {
  background: linear-gradient(135deg, #66bb6a 0%, #43a047 100%);
  color: white;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
  padding: 32px;
  background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}

.stat-card {
  background: white;
  padding: 24px;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.08);
  border: 1px solid #e8eaed;
  transition: all 0.3s ease;
}

.stat-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 24px rgba(0,0,0,0.12);
}

.stat-label {
  font-size: 11px;
  color: #6c757d;
  text-transform: uppercase;
  letter-spacing: 1px;
  margin-bottom: 12px;
  font-weight: 700;
}

.stat-value {
  font-size: 20px;
  font-weight: 700;
  color: #2c3e50;
  line-height: 1.4;
}

.timeline-section,
.details-section,
.alert-log-section,
.actions-section {
  padding: 32px;
  background: white;
}

.timeline-section h2,
.details-section h2,
.alert-log-section h2,
.actions-section h2 {
  margin: 0 0 24px 0;
  color: #2c3e50;
  font-size: 22px;
  font-weight: 700;
  padding-bottom: 12px;
  border-bottom: 3px solid #667eea;
  display: inline-block;
}

.timeline {
  position: relative;
  padding-left: 50px;
  margin-top: 24px;
}

.timeline::before {
  content: '';
  position: absolute;
  left: 20px;
  top: 0;
  bottom: 0;
  width: 3px;
  background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
  border-radius: 2px;
}

.timeline-item {
  position: relative;
  padding-bottom: 32px;
}

.timeline-badge {
  position: absolute;
  left: -50px;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: white;
  border: 3px solid #e8eaed;
  font-size: 18px;
  z-index: 1;
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.timeline-badge.timeline-start {
  background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
  border-color: #ff6b6b;
  color: white;
}

.timeline-badge.timeline-acknowledged {
  background: linear-gradient(135deg, #ffa726 0%, #fb8c00 100%);
  border-color: #ffa726;
  color: white;
}

.timeline-badge.timeline-resolved {
  background: linear-gradient(135deg, #66bb6a 0%, #43a047 100%);
  border-color: #66bb6a;
  color: white;
}

.timeline-content {
  background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
  padding: 16px 20px;
  border-radius: 12px;
  border: 1px solid #e8eaed;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
  transition: all 0.3s;
}

.timeline-content:hover {
  transform: translateX(4px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.timeline-time {
  font-size: 12px;
  color: #6c757d;
  margin-bottom: 8px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.timeline-text {
  font-size: 14px;
  line-height: 1.7;
  color: #2c3e50;
}

.timeline-text strong {
  display: block;
  margin-bottom: 6px;
  font-size: 16px;
  color: #667eea;
}

.error-box {
  background: linear-gradient(135deg, #fff5f5 0%, #ffe5e5 100%);
  border-left: 5px solid #ff6b6b;
  padding: 20px;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(255, 107, 107, 0.15);
}

.error-box h3 {
  margin: 0 0 16px 0;
  color: #c62828;
  font-size: 18px;
  font-weight: 700;
}

.error-box pre {
  margin: 0;
  white-space: pre-wrap;
  word-wrap: break-word;
  font-family: 'Courier New', monospace;
  font-size: 14px;
  color: #c62828;
  line-height: 1.7;
  background: rgba(255, 255, 255, 0.7);
  padding: 12px;
  border-radius: 8px;
}

.alert-log {
  background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
  border-radius: 12px;
  padding: 20px;
  max-height: 400px;
  overflow-y: auto;
  border: 1px solid #dee2e6;
}

.log-entry {
  padding: 16px;
  background: white;
  border-radius: 10px;
  margin-bottom: 12px;
  border-left: 4px solid #667eea;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
  transition: all 0.3s;
}

.log-entry:hover {
  transform: translateX(4px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.log-entry:last-child {
  margin-bottom: 0;
}

.log-time {
  font-size: 12px;
  color: #6c757d;
  margin-bottom: 8px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.log-message {
  font-size: 14px;
  color: #2c3e50;
  font-family: 'Courier New', monospace;
  line-height: 1.6;
}

.action-buttons {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
}

.btn {
  padding: 12px 28px;
  border: none;
  border-radius: 10px;
  font-size: 15px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.3s ease;
  text-decoration: none;
  display: inline-block;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
}

.btn-primary {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.btn-primary:hover {
  background: linear-gradient(135deg, #5568d3 0%, #6a3f8f 100%);
}

.btn-secondary {
  background: linear-gradient(135deg, #b0bec5 0%, #90a4ae 100%);
  color: white;
}

.btn-secondary:hover {
  background: linear-gradient(135deg, #9aa8b0 0%, #78909c 100%);
}

.btn-warning {
  background: linear-gradient(135deg, #ffa726 0%, #fb8c00 100%);
  color: white;
}

.btn-warning:hover {
  background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
}

.btn-success {
  background: linear-gradient(135deg, #66bb6a 0%, #43a047 100%);
  color: white;
}

.btn-success:hover {
  background: linear-gradient(135deg, #57ab5a 0%, #388e3c 100%);
}

.btn-danger {
  background: linear-gradient(135deg, #ef5350 0%, #e53935 100%);
  color: white;
}

.btn-danger:hover {
  background: linear-gradient(135deg, #e53935 0%, #c62828 100%);
}

@media (max-width: 768px) {
  .incident-detail {
    padding: 12px;
  }
  
  .stats-grid {
    grid-template-columns: 1fr;
  }
  
  .action-buttons {
    flex-direction: column;
  }
  
  .btn {
    width: 100%;
  }
}
</style>
