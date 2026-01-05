<template>
  <div class="dashboard">
    <div class="dashboard-header">
      <div class="header-main">
        <h1>Welcome to Uptime Monitor</h1>
        <p v-if="currentUser">Hello, {{ currentUser.name || 'User' }}!</p>
      </div>
      <div class="header-status">
        <div class="user-info" v-if="currentUser">
          <span class="welcome-text">Welcome, {{ currentUser.role || 'user' }}</span>
        </div>
        <router-link v-else to="/login" class="btn-login">
          🔐 Login
        </router-link>
      </div>
    </div>

    <!-- Public Monitors Section -->
    <div v-if="!currentUser" class="public-monitors-section">
      <div class="section-header">
        <h2>🌐 Public Status</h2>
        <router-link to="/public" class="view-all-link">View All →</router-link>
      </div>

      <div v-if="loadingMonitors" class="loading-state">
        Loading public monitors...
      </div>

      <div v-else-if="publicMonitors.length === 0" class="empty-state">
        <p>No public monitors available</p>
        <router-link to="/monitors" class="btn btn-primary">Go to Monitors</router-link>
      </div>

      <div v-else class="monitors-grid">
        <div v-for="monitor in publicMonitors" :key="monitor.id" class="monitor-card" :class="`status-${monitor.status}`">
          <div class="monitor-header">
            <div class="monitor-status">
              <span class="status-dot" :class="`status-${monitor.status}`"></span>
              <span class="status-text">{{ monitor.status === 'up' ? 'Online' : 'Offline' }}</span>
            </div>
            <span class="monitor-type">{{ monitor.type?.toUpperCase() || 'HTTP' }}</span>
          </div>
          
          <h3 class="monitor-name">{{ monitor.name }}</h3>
          <p class="monitor-target">{{ monitor.target }}</p>
          
          <div class="monitor-stats">
            <div class="stat-item">
              <span class="stat-label">Uptime</span>
              <span class="stat-value">{{ monitor.uptime_percentage || '0' }}%</span>
            </div>
            <div class="stat-item" v-if="monitor.last_check_at">
              <span class="stat-label">Last Check</span>
              <span class="stat-value">{{ formatLastCheck(monitor.last_check_at) }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '../services/api'

const userInfo = ref(null)
const publicMonitors = ref([])
const loadingMonitors = ref(true)

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

onMounted(async () => {
  // Load user from localStorage
  try {
    const stored = localStorage.getItem('user')
    if (stored) {
      userInfo.value = JSON.parse(stored)
    }
  } catch (e) {
    console.error('Failed to load user info:', e)
  }

  // Load public monitors
  await loadPublicMonitors()
})

async function loadPublicMonitors() {
  loadingMonitors.value = true
  try {
    const response = await api.publicMonitors.getAll()
    if (response.data && response.data.success) {
      // Get all monitors from groups and flatten them
      const groups = response.data.data || []
      publicMonitors.value = groups.flatMap(group => group.monitors || []).slice(0, 6) // Show max 6 monitors
    }
  } catch (err) {
    console.error('Failed to load public monitors:', err)
  } finally {
    loadingMonitors.value = false
  }
}

function formatLastCheck(dateString) {
  if (!dateString) return '-'
  const date = new Date(dateString)
  const now = new Date()
  const diffMs = now - date
  const diffMins = Math.floor(diffMs / 60000)
  
  if (diffMins < 1) return 'Just now'
  if (diffMins < 60) return `${diffMins}m ago`
  const diffHours = Math.floor(diffMins / 60)
  if (diffHours < 24) return `${diffHours}h ago`
  const diffDays = Math.floor(diffHours / 24)
  return `${diffDays}d ago`
}
</script>

<style scoped>
.dashboard {
  padding: 2rem;
  max-width: 1400px;
  margin: 0 auto;
}

.dashboard-header {
  margin-bottom: 2rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
  padding: 2rem;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 16px;
  box-shadow: 0 8px 24px rgba(102, 126, 234, 0.25);
  color: white;
}

.header-main {
  flex: 1;
  min-width: 250px;
}

.header-main h1 {
  margin: 0 0 0.5rem 0;
  color: white;
  font-size: clamp(1.8rem, 5vw, 2.5rem);
  font-weight: 700;
  line-height: 1.2;
  text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.header-main p {
  margin: 0;
  color: rgba(255, 255, 255, 0.95);
  font-size: clamp(0.95rem, 3vw, 1.1rem);
  line-height: 1.5;
}

.header-status {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex-wrap: wrap;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  background: rgba(255, 255, 255, 0.2);
  padding: 0.75rem 1.5rem;
  border-radius: 2rem;
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.3);
  transition: all 0.3s ease;
}

.user-info:hover {
  background: rgba(255, 255, 255, 0.3);
  transform: translateY(-2px);
}

.welcome-text {
  font-weight: 700;
  color: white;
  font-size: 1rem;
  text-transform: capitalize;
  letter-spacing: 0.5px;
}

.btn-login {
  padding: 0.75rem 1.5rem;
  background: white;
  color: #667eea;
  text-decoration: none;
  border-radius: 2rem;
  font-weight: 700;
  font-size: 1rem;
  transition: all 0.3s ease;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.btn-login:hover {
  background: #f8f9fa;
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
}

/* Public Monitors Section */
.public-monitors-section {
  background: white;
  border-radius: 16px;
  padding: 2rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
  padding-bottom: 1rem;
  border-bottom: 3px solid #667eea;
}

.section-header h2 {
  margin: 0;
  color: #2c3e50;
  font-size: 1.75rem;
  font-weight: 700;
}

.view-all-link {
  color: #667eea;
  text-decoration: none;
  font-weight: 600;
  font-size: 1rem;
  transition: all 0.3s;
}

.view-all-link:hover {
  color: #764ba2;
  transform: translateX(4px);
}

.loading-state,
.empty-state {
  text-align: center;
  padding: 4rem 2rem;
  color: #6c757d;
}

.empty-state p {
  margin-bottom: 1.5rem;
  font-size: 1.1rem;
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

.btn-primary {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.btn-primary:hover {
  background: linear-gradient(135deg, #5568d3 0%, #6a3f8f 100%);
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}

/* Monitors Grid */
.monitors-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 1.5rem;
}

.monitor-card {
  background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
  border-radius: 12px;
  padding: 1.5rem;
  border: 2px solid #e8eaed;
  transition: all 0.3s ease;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

.monitor-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
  border-color: #667eea;
}

.monitor-card.status-up {
  border-left: 5px solid #66bb6a;
}

.monitor-card.status-down {
  border-left: 5px solid #ef5350;
}

.monitor-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.monitor-status {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.status-dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  animation: pulse 2s infinite;
}

.status-dot.status-up {
  background: #66bb6a;
  box-shadow: 0 0 0 3px rgba(102, 187, 106, 0.2);
}

.status-dot.status-down {
  background: #ef5350;
  box-shadow: 0 0 0 3px rgba(239, 83, 80, 0.2);
}

@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.6;
  }
}

.status-text {
  font-weight: 700;
  font-size: 0.9rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.monitor-card.status-up .status-text {
  color: #43a047;
}

.monitor-card.status-down .status-text {
  color: #e53935;
}

.monitor-type {
  font-size: 0.75rem;
  padding: 0.35rem 0.75rem;
  background: #667eea;
  color: white;
  border-radius: 12px;
  font-weight: 700;
  letter-spacing: 0.5px;
}

.monitor-name {
  margin: 0 0 0.5rem 0;
  color: #2c3e50;
  font-size: 1.25rem;
  font-weight: 700;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.monitor-target {
  margin: 0 0 1rem 0;
  color: #6c757d;
  font-size: 0.9rem;
  font-family: 'Courier New', monospace;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.monitor-stats {
  display: flex;
  gap: 1rem;
  padding-top: 1rem;
  border-top: 1px solid #e8eaed;
}

.stat-item {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.stat-label {
  font-size: 0.75rem;
  color: #6c757d;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-weight: 600;
}

.stat-value {
  font-size: 1.1rem;
  color: #2c3e50;
  font-weight: 700;
}

/* Tablet and smaller */
@media (max-width: 768px) {
  .dashboard {
    padding: 1rem;
  }
  
  .dashboard-header {
    flex-direction: column;
    align-items: stretch;
    gap: 1rem;
    padding: 1.5rem;
  }
  
  .header-main h1 {
    margin-bottom: 0.375rem;
  }
  
  .header-status {
    justify-content: center;
  }
  
  .monitors-grid {
    grid-template-columns: 1fr;
  }

  .public-monitors-section {
    padding: 1.5rem;
  }
}

/* Mobile */
@media (max-width: 480px) {
  .dashboard {
    padding: 0.75rem;
  }
  
  .dashboard-header {
    padding: 1.25rem;
  }

  .section-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.75rem;
  }

  .public-monitors-section {
    padding: 1rem;
  }

  .monitor-card {
    padding: 1.25rem;
  }
}
</style>