<template>
  <div class="public-monitors">
    <div class="public-header">
      <div class="header-content">
        <h1>🔍 Public Uptime Status</h1>
        <p class="subtitle">Real-time monitoring status</p>
      </div>
      <div class="overall-stats">
        <div class="stat-card">
          <div class="stat-value">{{ statistics.overall_uptime }}%</div>
          <div class="stat-label">Overall Uptime</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">{{ statistics.online }}</div>
          <div class="stat-label">Online</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">{{ statistics.offline }}</div>
          <div class="stat-label">Offline</div>
        </div>
      </div>
    </div>

    <div v-if="loading" class="loading">Loading monitors...</div>

    <div v-else class="monitors-content">
      <div v-if="groups.length === 0" class="no-data">
        No public monitors available
      </div>

      <div v-for="group in groups" :key="group.group" class="monitor-group">
        <div class="group-header">
          <h2>{{ group.group }}</h2>
          <span class="group-count">{{ group.monitors.length }} service{{ group.monitors.length > 1 ? 's' : '' }}</span>
        </div>

        <div class="monitors-grid">
          <div 
            v-for="monitor in group.monitors" 
            :key="monitor.id" 
            class="monitor-card"
            :class="monitor.status"
            @click="viewDetails(monitor.id)"
          >
            <div class="monitor-status-indicator" :class="monitor.status"></div>
            
            <div class="monitor-header">
              <h3>{{ monitor.name }}</h3>
              <span class="status-badge" :class="monitor.status">
                {{ monitor.status === 'online' ? '✓ Online' : '✗ Offline' }}
              </span>
            </div>

            <div class="monitor-info">
              <div class="info-row">
                <span class="info-label">🌐 URL:</span>
                <span class="info-value">{{ monitor.url }}</span>
              </div>
              <div class="info-row">
                <span class="info-label">📊 Type:</span>
                <span class="info-value">{{ monitor.type.toUpperCase() }}</span>
              </div>
              <div class="info-row">
                <span class="info-label">🕐 Last Check:</span>
                <span class="info-value">{{ formatDate(monitor.last_check_at) }}</span>
              </div>
              <div v-if="monitor.ssl_days_remaining !== null" class="info-row">
                <span class="info-label">🔒 SSL:</span>
                <span class="info-value" :class="getSSLClass(monitor.ssl_days_remaining)">
                  {{ monitor.ssl_days_remaining }} days
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Monitor Detail Modal -->
    <div v-if="showDetailModal" class="modal-overlay" @click="closeDetailModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h2>📊 {{ detailData.monitor?.name }}</h2>
          <button class="close-btn" @click="closeDetailModal">✕</button>
        </div>

        <div v-if="loadingDetail" class="loading">Loading details...</div>

        <div v-else class="detail-content">
          <div class="detail-stats">
            <div class="stat-box">
              <div class="stat-number">{{ detailData.statistics?.uptime_24h }}%</div>
              <div class="stat-name">24h Uptime</div>
            </div>
            <div class="stat-box">
              <div class="stat-number">{{ detailData.statistics?.avg_response_time }}ms</div>
              <div class="stat-name">Avg Response</div>
            </div>
            <div class="stat-box">
              <div class="stat-number">{{ detailData.statistics?.successful_checks }}</div>
              <div class="stat-name">Successful</div>
            </div>
            <div class="stat-box">
              <div class="stat-number">{{ detailData.statistics?.failed_checks }}</div>
              <div class="stat-name">Failed</div>
            </div>
          </div>

          <div class="detail-chart">
            <h3>Response Time (24 Hours)</h3>
            <div class="chart-container">
              <div 
                v-for="(stat, index) in detailData.hourly_stats?.slice().reverse()" 
                :key="index"
                class="chart-bar"
                :style="{ height: getBarHeight(stat.avg_response_time) + '%' }"
                :title="`${formatHour(stat.period_start)}: ${stat.avg_response_time}ms (${stat.uptime_percentage}% uptime)`"
              >
                <div class="bar-value">{{ Math.round(stat.avg_response_time) }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../services/api'

const loading = ref(true)
const loadingDetail = ref(false)
const groups = ref([])
const statistics = ref({
  total_monitors: 0,
  online: 0,
  offline: 0,
  overall_uptime: 0
})
const showDetailModal = ref(false)
const detailData = ref({})

onMounted(async () => {
  await loadMonitors()
  await loadStatistics()
})

async function loadMonitors() {
  loading.value = true
  try {
    const response = await api.publicMonitors.getAll()
    if (response.data && response.data.success) {
      groups.value = response.data.data
    }
  } catch (err) {
    console.error('Failed to load monitors:', err)
  } finally {
    loading.value = false
  }
}

async function loadStatistics() {
  try {
    const response = await api.publicMonitors.getStatistics()
    if (response.data && response.data.success) {
      statistics.value = response.data.data
    }
  } catch (err) {
    console.error('Failed to load statistics:', err)
  }
}

async function viewDetails(id) {
  showDetailModal.value = true
  loadingDetail.value = true
  
  try {
    const response = await api.publicMonitors.getById(id)
    if (response.data && response.data.success) {
      detailData.value = response.data.data
    }
  } catch (err) {
    console.error('Failed to load detail:', err)
  } finally {
    loadingDetail.value = false
  }
}

function closeDetailModal() {
  showDetailModal.value = false
  detailData.value = {}
}

function formatDate(dateString) {
  if (!dateString) return 'Never'
  const date = new Date(dateString)
  const now = new Date()
  const diff = Math.floor((now - date) / 1000)
  
  if (diff < 60) return `${diff}s ago`
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`
  return `${Math.floor(diff / 86400)}d ago`
}

function formatHour(dateString) {
  if (!dateString) return ''
  const date = new Date(dateString)
  return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })
}

function getSSLClass(days) {
  if (days < 7) return 'ssl-critical'
  if (days < 30) return 'ssl-warning'
  return 'ssl-ok'
}

function getBarHeight(responseTime) {
  const maxTime = Math.max(...(detailData.value.hourly_stats?.map(s => s.avg_response_time) || [100]))
  return (responseTime / maxTime) * 80 + 10
}
</script>

<style scoped>
.public-monitors {
  max-width: 1400px;
  margin: 0 auto;
  padding: 24px;
  background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
  min-height: 100vh;
}

.public-header {
  margin-bottom: 32px;
  padding: 40px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 20px;
  box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
  color: white;
}

.header-content h1 {
  margin: 0 0 12px 0;
  font-size: 42px;
  font-weight: 800;
  text-shadow: 0 3px 6px rgba(0, 0, 0, 0.2);
}

.subtitle {
  margin: 0 0 24px 0;
  font-size: 18px;
  opacity: 0.95;
}

.overall-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 20px;
  margin-top: 24px;
}

.stat-card {
  background: rgba(255, 255, 255, 0.2);
  padding: 20px;
  border-radius: 12px;
  text-align: center;
  backdrop-filter: blur(10px);
}

.stat-value {
  font-size: 32px;
  font-weight: 800;
  margin-bottom: 8px;
}

.stat-label {
  font-size: 14px;
  opacity: 0.9;
}

.loading {
  text-align: center;
  padding: 80px 20px;
  font-size: 20px;
  color: #667eea;
  font-weight: 600;
  background: white;
  border-radius: 16px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.no-data {
  text-align: center;
  padding: 80px 20px;
  font-size: 18px;
  color: #999;
  background: white;
  border-radius: 16px;
}

.monitor-group {
  margin-bottom: 40px;
}

.group-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  padding: 0 8px;
}

.group-header h2 {
  margin: 0;
  font-size: 24px;
  color: #2c3e50;
  font-weight: 700;
}

.group-count {
  color: #999;
  font-size: 14px;
}

.monitors-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: 20px;
}

.monitor-card {
  background: white;
  border-radius: 16px;
  padding: 24px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  cursor: pointer;
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}

.monitor-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: var(--status-color);
}

.monitor-card.online {
  --status-color: #66bb6a;
}

.monitor-card.offline {
  --status-color: #ef5350;
}

.monitor-card:hover {
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
  transform: translateY(-4px);
}

.monitor-status-indicator {
  position: absolute;
  top: 20px;
  right: 20px;
  width: 12px;
  height: 12px;
  border-radius: 50%;
  animation: pulse 2s infinite;
}

.monitor-status-indicator.online {
  background: #66bb6a;
  box-shadow: 0 0 10px rgba(102, 187, 106, 0.5);
}

.monitor-status-indicator.offline {
  background: #ef5350;
  box-shadow: 0 0 10px rgba(239, 83, 80, 0.5);
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}

.monitor-header {
  margin-bottom: 16px;
}

.monitor-header h3 {
  margin: 0 0 8px 0;
  font-size: 20px;
  color: #2c3e50;
  font-weight: 700;
  padding-right: 20px;
}

.status-badge {
  display: inline-block;
  padding: 6px 14px;
  border-radius: 20px;
  font-size: 13px;
  font-weight: 700;
}

.status-badge.online {
  background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
  color: #2e7d32;
}

.status-badge.offline {
  background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
  color: #c62828;
}

.monitor-info {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.info-row {
  display: flex;
  font-size: 14px;
}

.info-label {
  min-width: 100px;
  color: #666;
  font-weight: 600;
}

.info-value {
  color: #2c3e50;
  word-break: break-all;
}

.ssl-ok { color: #66bb6a; font-weight: 700; }
.ssl-warning { color: #ffa726; font-weight: 700; }
.ssl-critical { color: #ef5350; font-weight: 700; }

/* Modal */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.7);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  backdrop-filter: blur(8px);
}

.modal-content {
  background: white;
  border-radius: 20px;
  max-width: 900px;
  width: 90%;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
}

.modal-header {
  padding: 32px;
  border-bottom: 2px solid #e8eaed;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
}

.modal-header h2 {
  margin: 0;
  font-size: 28px;
  color: #2c3e50;
  font-weight: 700;
}

.close-btn {
  background: none;
  border: none;
  font-size: 32px;
  color: #999;
  cursor: pointer;
  padding: 0;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  transition: all 0.2s ease;
}

.close-btn:hover {
  background: #f5f5f5;
  color: #333;
}

.detail-content {
  padding: 32px;
}

.detail-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 20px;
  margin-bottom: 32px;
}

.stat-box {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  padding: 24px;
  border-radius: 16px;
  text-align: center;
  color: white;
  box-shadow: 0 4px 16px rgba(102, 126, 234, 0.3);
}

.stat-number {
  font-size: 32px;
  font-weight: 800;
  margin-bottom: 8px;
}

.stat-name {
  font-size: 13px;
  opacity: 0.9;
}

.detail-chart {
  background: #f8f9fa;
  padding: 24px;
  border-radius: 16px;
}

.detail-chart h3 {
  margin: 0 0 20px 0;
  font-size: 18px;
  color: #2c3e50;
}

.chart-container {
  display: flex;
  align-items: flex-end;
  gap: 4px;
  height: 200px;
  background: white;
  padding: 16px;
  border-radius: 12px;
}

.chart-bar {
  flex: 1;
  background: linear-gradient(to top, #667eea 0%, #764ba2 100%);
  border-radius: 4px 4px 0 0;
  position: relative;
  min-height: 10%;
  cursor: pointer;
  transition: all 0.3s ease;
}

.chart-bar:hover {
  opacity: 0.8;
  transform: translateY(-4px);
}

.bar-value {
  position: absolute;
  top: -20px;
  left: 50%;
  transform: translateX(-50%);
  font-size: 11px;
  font-weight: 700;
  color: #667eea;
  white-space: nowrap;
}

@media (max-width: 768px) {
  .public-monitors {
    padding: 16px;
  }

  .public-header {
    padding: 24px;
  }

  .header-content h1 {
    font-size: 32px;
  }

  .monitors-grid {
    grid-template-columns: 1fr;
  }

  .overall-stats {
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
  }

  .stat-card {
    padding: 16px;
  }

  .stat-value {
    font-size: 24px;
  }
}
</style>
