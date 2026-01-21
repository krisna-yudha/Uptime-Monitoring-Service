<template>
  <div class="edit-monitor">
    <h1>Edit Monitor</h1>
    
    <div v-if="loading" class="loading">Loading monitor...</div>
    
    <div v-else-if="error" class="error">{{ error }}</div>
    
    <form v-else @submit.prevent="submitForm" class="monitor-form">
      <div class="form-section">
        <h2>Basic Information</h2>
        
        <div class="form-group">
          <label for="name">Monitor Name *</label>
          <input
            id="name"
            v-model="form.name"
            type="text"
            placeholder="Enter monitor name"
            required
            class="form-control"
          />
        </div>

        <div class="form-group">
          <label for="type">Monitor Type *</label>
          <select
            id="type"
            v-model="form.type"
            required
            class="form-control"
            @change="onTypeChange"
          >
            <option value="http">HTTP</option>
            <option value="https">HTTPS</option>
            <option value="ping">Ping</option>
            <option value="port">Port</option>
            <option value="keyword">Keyword</option>
            <option value="ssl">SSL Certificate</option>
            <option value="heartbeat">Heartbeat</option>
          </select>
        </div>

        <div class="form-group">
          <label for="target">Target URL/IP/Domain *</label>
          <div style="display:flex;gap:12px;align-items:center;">
            <input
              id="target"
              v-model="form.target"
              type="text"
              :placeholder="targetPlaceholder"
              required
              class="form-control"
              style="flex:1"
            />
            <input
              id="port"
              v-model.number="form.port_number"
              type="number"
              min="1"
              max="65535"
              placeholder="Port (optional)"
              class="form-control"
              style="width:120px"
            />
          </div>
        </div>
      </div>

      <!-- HTTP/HTTPS Specific Settings -->
      <div v-if="form.type === 'http' || form.type === 'https'" class="form-section">
        <h2>{{ form.type === 'https' ? 'HTTPS' : 'HTTP' }} Settings</h2>
        
        <div class="form-group">
          <label for="method">HTTP Method</label>
          <select id="method" v-model="form.http_method" class="form-control">
            <option value="GET">GET</option>
            <option value="POST">POST</option>
            <option value="PUT">PUT</option>
            <option value="DELETE">DELETE</option>
            <option value="HEAD">HEAD</option>
          </select>
        </div>

        <div class="form-group">
          <label for="headers">HTTP Headers (JSON format)</label>
          <textarea
            id="headers"
            v-model="form.http_headers"
            class="form-control"
            rows="3"
            placeholder='{"User-Agent": "UptimeMonitor/1.0", "Content-Type": "application/json"}'
          ></textarea>
        </div>

        <div class="form-group">
          <label for="body">Request Body</label>
          <textarea
            id="body"
            v-model="form.http_body"
            class="form-control"
            rows="4"
            placeholder="Request body for POST/PUT requests"
          ></textarea>
        </div>

        <div class="form-group">
          <label for="expected_status">Expected Status Codes</label>
          <input
            id="expected_status"
            v-model="form.http_expected_status_codes"
            type="text"
            class="form-control"
            placeholder="200,201,202"
          />
          <small class="form-hint">Comma-separated status codes (default: 200)</small>
        </div>

        <div class="form-group">
          <label>
            <input
              type="checkbox"
              v-model="form.http_follow_redirects"
            />
            Follow Redirects
          </label>
        </div>

        <div class="form-group">
          <label>
            <input
              type="checkbox"
              v-model="form.http_verify_ssl"
            />
            Verify SSL Certificate
          </label>
        </div>
      </div>

      <!-- Port Specific Settings -->
      <div v-if="form.type === 'port'" class="form-section">
        <h2>Port Settings</h2>
        
        <div class="form-group">
          <label for="port">Port Number *</label>
          <input
            id="port"
            v-model.number="form.port_number"
            type="number"
            min="1"
            max="65535"
            required
            class="form-control"
            placeholder="80"
          />
        </div>
      </div>

      <!-- Keyword Specific Settings -->
      <div v-if="form.type === 'keyword'" class="form-section">
        <h2>Keyword Settings</h2>
        
        <div class="form-group">
          <label for="keyword">Keyword to Search *</label>
          <input
            id="keyword"
            v-model="form.keyword_text"
            type="text"
            required
            class="form-control"
            placeholder="Expected text in response"
          />
        </div>

        <div class="form-group">
          <label>
            <input
              type="checkbox"
              v-model="form.keyword_case_sensitive"
            />
            Case Sensitive
          </label>
        </div>
      </div>

      <!-- Heartbeat Specific Settings -->
      <div v-if="form.type === 'heartbeat'" class="form-section">
        <h2>Heartbeat Settings</h2>
        
        <div class="form-group">
          <label for="heartbeat_grace">Grace Period (minutes) *</label>
          <input
            id="heartbeat_grace"
            v-model.number="form.heartbeat_grace_period_minutes"
            type="number"
            min="1"
            required
            class="form-control"
            placeholder="5"
          />
          <small class="form-hint">How long to wait after expected heartbeat before marking as down</small>
        </div>
      </div>

      <div class="form-section">
        <h2>Monitoring Configuration</h2>
        
        <div class="form-group">
          <label for="priority">Priority Level *</label>
          <select
            id="priority"
            v-model.number="form.priority"
            required
            class="form-control"
          >
            <option :value="1">Critical (1 second)</option>
            <option :value="2">High (1 minute)</option>
            <option :value="3">Medium (5 minutes)</option>
            <option :value="4">Low (30 minutes)</option>
            <option :value="5">Very Low (1 hour)</option>
          </select>
          <small class="form-help">Higher priority = more frequent checks</small>
        </div>

        <div class="form-group">
          <label for="timeout">Timeout (seconds)</label>
          <input
            id="timeout"
            v-model.number="form.timeout_seconds"
            type="number"
            min="1"
            max="300"
            class="form-control"
            placeholder="30"
          />
        </div>

        <div class="form-group">
          <label for="retries">Retry Count</label>
          <input
            id="retries"
            v-model.number="form.retry_count"
            type="number"
            min="0"
            max="5"
            class="form-control"
            placeholder="3"
          />
        </div>

        <div class="form-group">
          <label>
            <input
              type="checkbox"
              v-model="form.is_enabled"
            />
            Enable Monitoring
          </label>
        </div>

        <div class="form-group">
          <label>
            <input
              type="checkbox"
              v-model="form.is_public"
            />
            Make this monitor public (visible without login)
          </label>
        </div>
      </div>

      <div class="form-section">
        <h2>Notification Channels</h2>
        
        <div v-if="availableChannels.length === 0" class="no-channels">
          <p>No notification channels configured.</p>
          <router-link to="/notifications" class="btn btn-primary">
            Configure Channels
          </router-link>
        </div>
        
        <div v-else class="channel-list">
          <div
            v-for="channel in availableChannels"
            :key="channel.id"
            class="channel-item"
            :class="{ 'channel-bound': isChannelBound(channel.id) }"
          >
            <label class="channel-label">
              <input
                type="checkbox"
                :value="channel.id"
                v-model="form.notification_channel_ids"
              />
              <span class="channel-info">
                <strong>{{ channel.name }}</strong>
                <span class="channel-type">{{ channel.type.toUpperCase() }}</span>
                <span v-if="isChannelBound(channel.id)" class="channel-badge">✓ Connected</span>
              </span>
            </label>
          </div>
        </div>
      </div>

      <div class="form-actions">
        <button
          type="submit"
          :disabled="submitting"
          class="btn btn-primary"
        >
          {{ submitting ? 'Saving...' : 'Update Monitor' }}
        </button>
        
        <router-link :to="`/monitors/${$route.params.id}`" class="btn btn-secondary">
          Cancel
        </router-link>
        
        <button
          type="button"
          @click="deleteMonitor"
          class="btn btn-danger"
          :disabled="deleting"
        >
          {{ deleting ? 'Deleting...' : 'Delete Monitor' }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useMonitorStore } from '../stores/monitors'
import api from '../services/api'

const route = useRoute()
const router = useRouter()
const monitorStore = useMonitorStore()

const loading = ref(true)
const error = ref(null)
const submitting = ref(false)
const deleting = ref(false)
const availableChannels = ref([])
const initialBoundChannels = ref([]) // Store initially bound notification channels

const form = ref({
  name: '',
  type: 'http',
  target: '',
  priority: 1,
  interval_seconds: 1,
  timeout_seconds: 30,
  retry_count: 3,
  is_enabled: true,
  is_public: false,
  
  // HTTP specific
  http_method: 'GET',
  http_headers: '',
  http_body: '',
  http_expected_status_codes: '',
  http_follow_redirects: true,
  http_verify_ssl: true,
  
  // Port specific
  port_number: null,
  
  // Keyword specific
  keyword_text: '',
  keyword_case_sensitive: false,
  
  // Heartbeat specific
  heartbeat_grace_period_minutes: 5,
  
  // Notification channels
  notification_channel_ids: []
})

const targetPlaceholder = computed(() => {
  switch (form.value.type) {
    case 'http': return 'https://example.com'
    case 'ping': return 'example.com or 192.168.1.1'
    case 'port': return 'example.com or 192.168.1.1'
    case 'keyword': return 'https://example.com'
    case 'ssl': return 'example.com'
    case 'heartbeat': return 'Unique heartbeat identifier'
    default: return 'Enter target'
  }
})

onMounted(() => {
  fetchMonitorData()
  fetchNotificationChannels()
})

// Check if a channel is already bound to this monitor (from initial load)
function isChannelBound(channelId) {
  return initialBoundChannels.value.includes(channelId)
}

async function fetchMonitorData() {
  loading.value = true
  error.value = null

  try {
    const result = await monitorStore.fetchMonitor(route.params.id)
    
    if (result.success) {
      const monitor = monitorStore.currentMonitor
      // Populate form with existing monitor data
      Object.keys(form.value).forEach(key => {
        if (monitor[key] !== undefined) {
          form.value[key] = monitor[key]
        }
      })
      
      // Handle notification channels - convert from backend format to array of IDs
      if (monitor.notification_channels && Array.isArray(monitor.notification_channels)) {
        // Backend returns array of channel IDs
        form.value.notification_channel_ids = monitor.notification_channels
        initialBoundChannels.value = [...monitor.notification_channels] // Store initial bound channels
      } else if (monitor.notification_channel_ids && Array.isArray(monitor.notification_channel_ids)) {
        form.value.notification_channel_ids = monitor.notification_channel_ids
        initialBoundChannels.value = [...monitor.notification_channel_ids] // Store initial bound channels
      }
      
      // Handle JSON fields
      if (monitor.http_headers) {
        form.value.http_headers = typeof monitor.http_headers === 'string' 
          ? monitor.http_headers 
          : JSON.stringify(monitor.http_headers, null, 2)
      }

      // Populate port_number from monitor if available, or parse from target
      if (monitor.port !== undefined && monitor.port !== null) {
        form.value.port_number = Number(monitor.port)
      } else if (monitor.port_number !== undefined && monitor.port_number !== null) {
        form.value.port_number = Number(monitor.port_number)
      } else if (monitor.target) {
        const targetRaw = String(monitor.target)
        try {
          if (targetRaw.includes('://')) {
            // URL style
            const parsed = new URL(targetRaw)
            if (parsed.port) {
              form.value.port_number = Number(parsed.port)
              // preserve path but set host+path to target for clarity
              form.value.target = parsed.hostname + (parsed.pathname || '') + (parsed.search || '')
            }
          } else if (targetRaw.includes(':')) {
            // host:port style - take last segment if numeric
            const parts = targetRaw.split(':')
            const last = parts[parts.length - 1]
            if (/^\d+$/.test(last)) {
              form.value.port_number = Number(last)
              form.value.target = parts.slice(0, parts.length - 1).join(':')
            }
          }
        } catch (e) {
          // ignore parse errors
        }
      }
      
      console.log('Monitor loaded:', monitor.name)
      console.log('Notification channels attached:', form.value.notification_channel_ids)
    } else {
      error.value = result.message
    }
  } catch (err) {
    error.value = 'Failed to load monitor'
  } finally {
    loading.value = false
  }
}

async function fetchNotificationChannels() {
  try {
    const response = await api.notificationChannels.getAll()
    if (response.data.success) {
      availableChannels.value = response.data.data.data || response.data.data
    }
  } catch (err) {
    console.error('Failed to load notification channels:', err)
  }
}

function onTypeChange() {
  // Reset type-specific fields when type changes
  form.value.port_number = null
  form.value.keyword_text = ''
  form.value.heartbeat_grace_period_minutes = 5
  form.value.http_headers = ''
  form.value.http_body = ''
  form.value.http_expected_status_codes = ''
}

async function submitForm() {
  submitting.value = true
  error.value = null
  
  try {
    // Validate interval_seconds
    if (form.value.interval_seconds < 1) {
      error.value = 'Check interval must be at least 1 second'
      submitting.value = false
      return
    }

    // Prepare form data
    const formData = { ...form.value }
    
    // Parse JSON fields
    if (formData.http_headers) {
      try {
        formData.http_headers = JSON.parse(formData.http_headers)
      } catch (e) {
        error.value = 'Invalid JSON format in HTTP headers'
        submitting.value = false
        return
      }
    }
    
    const result = await monitorStore.updateMonitor(route.params.id, formData)
    
    if (result.success) {
      // Force trigger an immediate check after update to reflect changes
      try {
        console.log('🔄 Triggering immediate check for monitor:', route.params.id)
        const checkResult = await api.monitors.triggerCheck(route.params.id)
        console.log('✅ Monitor updated and immediate check triggered:', checkResult)
      } catch (err) {
        console.error('❌ Failed to trigger immediate check:', err)
        // Don't block navigation, but show warning
        alert('Monitor updated but failed to trigger immediate check. New data may take a few seconds to appear.')
      }
      
      // Small delay to allow check to be queued before navigation
      await new Promise(resolve => setTimeout(resolve, 500))
      router.push(`/monitors/${route.params.id}`)
    } else {
      error.value = result.message || 'Failed to update monitor'
    }
  } catch (err) {
    console.error('Update monitor error:', err)
    error.value = err.response?.data?.message || err.message || 'An error occurred while updating the monitor'
  } finally {
    submitting.value = false
  }
}

async function deleteMonitor() {
  if (!confirm('Are you sure you want to delete this monitor? This action cannot be undone.')) {
    return
  }
  
  deleting.value = true
  
  try {
    const result = await monitorStore.deleteMonitor(route.params.id)
    
    if (result.success) {
      router.push('/monitors')
    } else {
      alert(result.message || 'Failed to delete monitor')
    }
  } catch (err) {
    alert('An error occurred while deleting the monitor')
    console.error(err)
  } finally {
    deleting.value = false
  }
}
</script>

<style scoped>
.edit-monitor {
  padding: 20px;
  max-width: 800px;
  margin: 0 auto;
}

.edit-monitor h1 {
  margin-bottom: 30px;
  color: #2c3e50;
}

.monitor-form {
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.form-section {
  padding: 30px;
  border-bottom: 1px solid #ecf0f1;
}

.form-section:last-of-type {
  border-bottom: none;
}

.form-section h2 {
  margin: 0 0 20px 0;
  color: #34495e;
  font-size: 1.2em;
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  margin-bottom: 5px;
  font-weight: 500;
  color: #2c3e50;
}

.form-control {
  width: 100%;
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
}

.form-control:focus {
  outline: none;
  border-color: #3498db;
  box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
}

.form-hint {
  display: block;
  margin-top: 5px;
  font-size: 0.8em;
  color: #7f8c8d;
}

.form-help {
  color: #6c757d;
  font-size: 0.75em;
  margin-top: 5px;
  display: block;
  font-style: italic;
}

.channel-list {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 15px;
}

.channel-item {
  border: 1px solid #ecf0f1;
  border-radius: 4px;
  padding: 15px;
  transition: all 0.2s ease;
}

.channel-item.channel-bound {
  background-color: #e8f5e9;
  border-color: #66bb6a;
}

.channel-label {
  display: flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
}

.channel-label input[type="checkbox"] {
  margin: 0;
}

.channel-label input[type="checkbox"]:disabled {
  cursor: not-allowed;
  opacity: 0.7;
}

.channel-info {
  display: flex;
  flex-direction: column;
  gap: 5px;
  flex: 1;
}

.channel-type {
  font-size: 0.8em;
  color: #7f8c8d;
  text-transform: uppercase;
}

.channel-badge {
  display: inline-block;
  padding: 2px 8px;
  background-color: #66bb6a;
  color: white;
  border-radius: 12px;
  font-size: 0.75em;
  font-weight: 600;
  margin-top: 4px;
}

.no-channels {
  text-align: center;
  padding: 40px;
  color: #7f8c8d;
}

.no-channels p {
  margin-bottom: 15px;
}

.form-actions {
  padding: 30px;
  display: flex;
  gap: 15px;
  justify-content: flex-end;
  background-color: #f8f9fa;
  border-radius: 0 0 8px 8px;
}

.btn {
  padding: 10px 20px;
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

.btn-secondary:hover {
  background-color: #7f8c8d;
}

.btn-danger {
  background-color: #e74c3c;
  color: white;
}

.btn-danger:hover:not(:disabled) {
  background-color: #c0392b;
}

@media (max-width: 768px) {
  .edit-monitor {
    padding: 10px;
  }
  
  .form-section {
    padding: 20px;
  }
  
  .form-actions {
    flex-direction: column;
    gap: 10px;
  }
  
  .channel-list {
    grid-template-columns: 1fr;
  }
}
</style>