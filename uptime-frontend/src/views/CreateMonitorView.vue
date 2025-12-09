<template>
  <div class="create-monitor">
    <div class="page-header">
      <div class="header-content">
        <div class="header-main">
          <h1>Create New Monitor</h1>
          <p>Set up monitoring for your service</p>
        </div>
        <div class="header-actions">
          <router-link to="/monitors" class="btn btn-secondary">
            <span>←</span> Back to Monitors
          </router-link>
        </div>
      </div>
      <div class="progress-indicator">
        <div class="progress-step" :class="{ active: currentStep >= 1 }">
          <span class="step-number">1</span>
          <span class="step-label">Basic Info</span>
        </div>
        <div class="progress-line" :class="{ active: currentStep >= 2 }"></div>
        <div class="progress-step" :class="{ active: currentStep >= 2 }">
          <span class="step-number">2</span>
          <span class="step-label">Configuration</span>
        </div>
        <div class="progress-line" :class="{ active: currentStep >= 3 }"></div>
        <div class="progress-step" :class="{ active: currentStep >= 3 }">
          <span class="step-number">3</span>
          <span class="step-label">Notifications</span>
        </div>
      </div>
    </div>

    <div class="form-container">
      <div v-if="error" class="error-message">
        <span class="error-icon">⚠️</span>
        <span>{{ error }}</span>
      </div>

      <form @submit.prevent="handleSubmit" class="monitor-form">
        <!-- Basic Information -->
        <div class="form-section">
          <h3>Basic Information</h3>
          
          <div class="form-row">
            <div class="form-group">
              <label for="name" class="form-label">Monitor Name *</label>
              <input
                id="name"
                v-model="form.name"
                type="text"
                class="form-control"
                required
                placeholder="e.g. My Website"
              >
            </div>
            
            <div class="form-group">
              <label for="type" class="form-label">Monitor Type *</label>
              <select
                id="type"
                v-model="form.type"
                class="form-control"
                required
                @change="onTypeChange"
              >
                <option value="">Select Type</option>
                <option value="http">HTTP</option>
                <option value="https">HTTPS</option>
                <option value="tcp">TCP</option>
                <option value="ping">Ping</option>
                <option value="keyword">Keyword Check</option>
                <option value="push">Push Monitor</option>
              </select>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="group_name" class="form-label">
                Group <span class="optional">(Optional)</span>
                <span class="group-count" v-if="selectedGroupInfo">{{ selectedGroupInfo.monitorsCount }} monitors</span>
              </label>
              <div class="group-input-container">
                <select
                  id="group_selector"
                  v-model="selectedGroupType"
                  class="form-control group-selector"
                  @change="onGroupTypeChange(selectedGroupType)"
                >
                  <option value="none">No Group</option>
                  <option value="existing" :disabled="existingGroups.length === 0">Select Existing Group</option>
                  <option value="new">Create New Group</option>
                </select>
                
                <!-- Existing Group Selector -->
                <select
                  v-if="selectedGroupType === 'existing'"
                  v-model="form.group_name"
                  class="form-control"
                  @change="onExistingGroupSelect"
                >
                  <option value="">Choose a group...</option>
                  <option v-for="group in groupsWithInfo" :key="group.name" :value="group.name">
                    📁 {{ group.name }} ({{ group.monitorsCount }} monitors)
                  </option>
                </select>
                
                <!-- New Group Input -->
                <input
                  v-if="selectedGroupType === 'new'"
                  id="new_group_name"
                  v-model="form.group_name"
                  type="text"
                  class="form-control"
                  placeholder="e.g. Web Services, API Endpoints"
                  @input="validateGroupName"
                >
                
                <!-- Group Info Display -->
                <div v-if="selectedGroupInfo && selectedGroupType === 'existing'" class="group-info">
                  <small class="group-description">
                    📝 {{ selectedGroupInfo.description || 'No description' }}
                  </small>
                  <small class="group-stats">
                    🟢 {{ selectedGroupInfo.upCount }} up • 
                    🔴 {{ selectedGroupInfo.downCount }} down •
                    ⏱️ Avg: {{ selectedGroupInfo.avgResponse }}ms
                  </small>
                </div>
              </div>
              <small class="form-text">
                <span v-if="selectedGroupType === 'none'">Monitor will not be grouped</span>
                <span v-else-if="selectedGroupType === 'existing'">Select from existing groups for better organization</span>
                <span v-else-if="selectedGroupType === 'new'">Create a new group to organize similar monitors</span>
              </small>
            </div>

            <div class="form-group" v-if="selectedGroupType === 'new'">
              <label for="group_description" class="form-label">
                Group Description
                <span class="optional">(Optional)</span>
              </label>
              <input
                id="group_description"
                v-model="form.group_description"
                type="text"
                class="form-control"
                placeholder="e.g. Main website and API endpoints"
              >
              <small class="form-text">
                Brief description to help others understand this group's purpose
              </small>
            </div>
          </div>
          
          <div class="form-group">
            <label for="target" class="form-label">Target *</label>
            <input
              id="target"
              v-model="form.target"
              type="text"
              class="form-control"
              required
              :placeholder="getTargetPlaceholder()"
            >
            <small class="form-text">
              {{ getTargetHelp() }}
            </small>
          </div>
        </div>

        <!-- Monitoring Configuration -->
        <div class="form-section">
          <h3>Monitoring Configuration</h3>
          
          <div class="form-row">
            <div class="form-group">
              <label for="interval" class="form-label">Check Interval (seconds) *</label>
              <input
                id="interval"
                v-model.number="form.interval_seconds"
                type="number"
                class="form-control"
                min="1"
                max="3600"
                required
              >
              <small class="form-help">Minimum 1 second for realtime monitoring</small>
            </div>
            
            <div class="form-group">
              <label for="timeout" class="form-label">Timeout (milliseconds)</label>
              <input
                id="timeout"
                v-model.number="form.timeout_ms"
                type="number"
                class="form-control"
                min="1000"
                max="30000"
              >
            </div>
          </div>
          
          <div class="form-row">
            <div class="form-group">
              <label for="retries" class="form-label">Max Retries</label>
              <input
                id="retries"
                v-model.number="form.retries"
                type="number"
                class="form-control"
                min="1"
                max="5"
              >
            </div>
            
            <div class="form-group">
              <label for="notify_after_retries" class="form-label">Notify After Retries</label>
              <input
                id="notify_after_retries"
                v-model.number="form.notify_after_retries"
                type="number"
                class="form-control"
                min="1"
                max="5"
              >
            </div>
          </div>
        </div>

        <!-- Advanced Configuration -->
        <div v-if="form.type && ['http', 'https', 'keyword'].includes(form.type)" class="form-section">
          <h3>HTTP Configuration</h3>
          
          <div class="form-group">
            <label for="expected_status" class="form-label">Expected Status Code</label>
            <input
              id="expected_status"
              v-model.number="config.expected_status_code"
              type="number"
              class="form-control"
              placeholder="200"
            >
          </div>
          
          <div v-if="form.type === 'keyword'" class="form-group">
            <label for="expected_content" class="form-label">Expected Content/Keyword</label>
            <input
              id="expected_content"
              v-model="config.expected_content"
              type="text"
              class="form-control"
              placeholder="Text to find in response"
            >
          </div>
          
          <div class="form-group">
            <label for="user_agent" class="form-label">Custom User Agent</label>
            <input
              id="user_agent"
              v-model="config.user_agent"
              type="text"
              class="form-control"
              placeholder="Leave empty for default"
            >
          </div>
        </div>

        <!-- Tags -->
        <div class="form-section">
          <h3>Tags & Organization</h3>
          
          <div class="form-group">
            <label for="tags" class="form-label">Tags</label>
            <input
              id="tags"
              v-model="tagsInput"
              type="text"
              class="form-control"
              placeholder="production, critical, website (comma separated)"
            >
            <small class="form-text">
              Add tags to organize your monitors (comma separated)
            </small>
          </div>
        </div>

        <!-- Notification Channels -->
        <div class="form-section">
          <h3>🔔 Notification Channels</h3>
          <p class="form-text">Select channels to receive alerts when this monitor goes down</p>
          
          <div v-if="loadingChannels" class="loading-state">
            <span>Loading notification channels...</span>
          </div>
          
          <div v-else-if="availableChannels.length === 0" class="no-channels-state">
            <span>📭 No notification channels configured yet.</span>
            <router-link to="/notifications" class="btn btn-sm btn-primary">
              Create Notification Channel
            </router-link>
          </div>
          
          <div v-else class="channels-selection">
            <div 
              v-for="channel in availableChannels" 
              :key="channel.id"
              class="channel-option"
            >
              <label class="checkbox-label">
                <input
                  type="checkbox"
                  :value="channel.id"
                  v-model="form.notification_channels"
                  class="form-checkbox"
                >
                <div class="channel-info">
                  <div class="channel-header">
                    <span class="channel-name">{{ channel.name }}</span>
                    <span class="channel-badge" :class="`badge-${channel.type}`">
                      {{ channel.type.toUpperCase() }}
                    </span>
                  </div>
                  <div class="channel-details">
                    <span v-if="channel.type === 'telegram'">
                      📱 Telegram: {{ maskChatId(channel.config?.chat_id) }}
                    </span>
                    <span v-else-if="channel.type === 'discord'">
                      💬 Discord Webhook
                    </span>
                    <span v-else-if="channel.type === 'slack'">
                      💼 Slack: {{ channel.config?.channel || 'Default channel' }}
                    </span>
                    <span v-else-if="channel.type === 'webhook'">
                      🔗 Webhook: {{ channel.config?.method || 'POST' }}
                    </span>
                  </div>
                </div>
              </label>
            </div>
          </div>
          
          <small class="form-text">
            Selected {{ form.notification_channels.length }} channel(s). 
            Alerts will be sent after {{ form.notify_after_retries }} failed check(s).
          </small>
        </div>

        <!-- Enable/Disable -->
        <div class="form-section">
          <div class="form-group">
            <label class="checkbox-label">
              <input
                v-model="form.enabled"
                type="checkbox"
                class="form-checkbox"
              >
              Enable monitor immediately
            </label>
          </div>
        </div>

        <!-- Submit Buttons -->
        <div class="form-actions">
          <router-link to="/monitors" class="btn btn-secondary">
            Cancel
          </router-link>
          <button
            type="submit"
            class="btn btn-success"
            :disabled="loading"
          >
            <span v-if="loading">Creating...</span>
            <span v-else>Create Monitor</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useMonitorStore } from '../stores/monitors'
import { useRouter } from 'vue-router'
import api from '../services/api'

const monitorStore = useMonitorStore()
const router = useRouter()

const loading = ref(false)
const error = ref(null)
const tagsInput = ref('')
const existingGroups = ref([])
const groupsWithInfo = ref([])
const selectedGroupType = ref('none')
const selectedGroupInfo = ref(null)
const loadingGroups = ref(false)
const loadingChannels = ref(false)
const availableChannels = ref([])

const form = reactive({
  name: '',
  type: '',
  target: '',
  group_name: '',
  group_description: '',
  interval_seconds: 1,
  timeout_ms: 5000,
  retries: 3,
  notify_after_retries: 2,
  notification_channels: [],
  enabled: true
})

const config = reactive({
  expected_status_code: 200,
  expected_content: '',
  user_agent: ''
})

onMounted(async () => {
  await loadExistingGroups()
  await loadNotificationChannels()
})

// Load notification channels
async function loadNotificationChannels() {
  loadingChannels.value = true
  try {
    const response = await api.notificationChannels.getAll()
    if (response.data.success) {
      availableChannels.value = response.data.data.data || response.data.data || []
    }
  } catch (err) {
    console.error('Failed to load notification channels:', err)
  } finally {
    loadingChannels.value = false
  }
}

function maskChatId(chatId) {
  if (!chatId) return ''
  const str = String(chatId)
  if (str.length <= 4) return str
  return str.substring(0, 3) + '***' + str.substring(str.length - 2)
}

// Group management functions
async function loadExistingGroups() {
  loadingGroups.value = true
  try {
    const groupsResponse = await monitorStore.getGroups()
    if (groupsResponse.success) {
      existingGroups.value = groupsResponse.data.map(group => group.group_name).filter(Boolean)
      
      // Get group statistics
      const monitorsResponse = await monitorStore.fetchMonitors()
      if (monitorsResponse) {
        groupsWithInfo.value = await Promise.all(
          existingGroups.value.map(async (groupName) => {
            const groupMonitors = monitorStore.monitors.filter(m => m.group_name === groupName)
            return {
              name: groupName,
              monitorsCount: groupMonitors.length,
              upCount: groupMonitors.filter(m => m.last_status === 'up').length,
              downCount: groupMonitors.filter(m => m.last_status === 'down').length,
              pendingCount: groupMonitors.filter(m => !m.last_status || m.last_status === 'pending').length,
              avgResponse: groupMonitors.length > 0 
                ? Math.round(groupMonitors.reduce((sum, m) => sum + (m.last_response_time || 0), 0) / groupMonitors.length)
                : 0,
              description: groupMonitors[0]?.group_description || `Group with ${groupMonitors.length} monitors`
            }
          })
        )
      }
    }
  } catch (err) {
    console.error('Failed to load existing groups:', err)
  } finally {
    loadingGroups.value = false
  }
}

function onTypeChange() {
  // Reset target when type changes
  form.target = ''
  
  // Reset config
  Object.keys(config).forEach(key => {
    if (typeof config[key] === 'string') {
      config[key] = ''
    } else if (typeof config[key] === 'number') {
      config[key] = key === 'expected_status_code' ? 200 : 0
    }
  })
}

function onGroupTypeChange(type) {
  selectedGroupType.value = type || selectedGroupType.value
  selectedGroupInfo.value = null
  
  // Clear form fields based on selection
  if (selectedGroupType.value === 'none') {
    form.group_name = ''
    form.group_description = ''
  } else if (selectedGroupType.value === 'new') {
    form.group_name = ''
    form.group_description = ''
  }
}

function onExistingGroupSelect() {
  if (form.group_name) {
    selectedGroupInfo.value = groupsWithInfo.value.find(g => g.name === form.group_name)
    form.group_description = selectedGroupInfo.value?.description || ''
  } else {
    selectedGroupInfo.value = null
  }
}

function validateGroupName() {
  // Check if group name already exists
  const exists = existingGroups.value.includes(form.group_name)
  if (exists && selectedGroupType.value === 'new') {
    error.value = `Group "${form.group_name}" already exists. Use "Select Existing Group" instead.`
    return false
  } else {
    if (error.value && error.value.includes('already exists')) {
      error.value = null
    }
    return true
  }
}

function getTargetPlaceholder() {
  switch (form.type) {
    case 'http':
    case 'https':
    case 'keyword':
      return 'https://example.com'
    case 'tcp':
      return 'example.com:80'
    case 'ping':
      return 'example.com or 192.168.1.1'
    case 'push':
      return 'Heartbeat key will be generated automatically'
    default:
      return 'Enter target to monitor'
  }
}

function getTargetHelp() {
  switch (form.type) {
    case 'http':
    case 'https':
    case 'keyword':
      return 'Enter the full URL to monitor'
    case 'tcp':
      return 'Enter hostname:port (e.g., example.com:80)'
    case 'ping':
      return 'Enter hostname or IP address'
    case 'push':
      return 'Push monitors receive heartbeats from your application'
    default:
      return ''
  }
}

async function handleSubmit() {
  loading.value = true
  error.value = null

  try {
    // Validate interval_seconds
    if (form.interval_seconds < 1) {
      error.value = 'Check interval must be at least 1 second'
      loading.value = false
      return
    }

    // Validate group name if creating new group
    if (!validateGroupName()) {
      loading.value = false
      return
    }

    // Prepare monitor data
    const monitorData = { ...form }
    
    // Clear group fields if "none" is selected
    if (selectedGroupType.value === 'none') {
      monitorData.group_name = ''
      monitorData.group_description = ''
    }
    
    // Add config if applicable
    const configData = {}
    if (['http', 'https', 'keyword'].includes(form.type)) {
      if (config.expected_status_code) {
        configData.expected_status_code = config.expected_status_code
      }
      if (config.expected_content) {
        configData.expected_content = config.expected_content
      }
      if (config.user_agent) {
        configData.user_agent = config.user_agent
      }
    }
    
    if (Object.keys(configData).length > 0) {
      monitorData.config = configData
    }
    
    // Add tags
    if (tagsInput.value.trim()) {
      monitorData.tags = tagsInput.value
        .split(',')
        .map(tag => tag.trim())
        .filter(tag => tag.length > 0)
    }

    const result = await monitorStore.createMonitor(monitorData)

    if (result.success) {
      router.push('/monitors')
    } else {
      error.value = result.message || 'Failed to create monitor'
    }
  } catch (err) {
    console.error('Create monitor error:', err)
    error.value = err.response?.data?.message || err.message || 'Failed to create monitor'
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.create-monitor {
  padding: 20px;
}

.page-header {
  margin-bottom: 30px;
}

.page-header h1 {
  margin: 0 0 5px 0;
  color: #2c3e50;
}

.page-header p {
  margin: 0;
  color: #7f8c8d;
}

.monitor-form {
  padding: 30px;
}

.form-section {
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 1px solid #ecf0f1;
}

.form-section:last-of-type {
  border-bottom: none;
}

.form-section h3 {
  margin: 0 0 20px 0;
  color: #2c3e50;
  font-size: 1.2em;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}

.form-group {
  margin-bottom: 20px;
}

.form-text {
  color: #6c757d;
  font-size: 0.85em;
  margin-top: 5px;
  display: block;
}

.form-help {
  color: #6c757d;
  font-size: 0.75em;
  margin-top: 5px;
  display: block;
  font-style: italic;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  font-weight: normal;
}

.form-checkbox {
  width: auto !important;
  margin: 0;
}

.form-actions {
  display: flex;
  gap: 15px;
  justify-content: flex-end;
  padding-top: 20px;
  border-top: 1px solid #ecf0f1;
  margin-top: 30px;
}

/* Group Selector Styles */
.group-selector {
  margin-bottom: 20px;
}

.group-type-options {
  display: flex;
  gap: 15px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}

.group-type-option {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 15px;
  border: 2px solid #e1e8ed;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
  font-weight: 500;
  background: white;
}

.group-type-option:hover {
  border-color: #667eea;
  background: #f8f9ff;
}

.group-type-option.active {
  border-color: #667eea;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.group-type-option input[type="radio"] {
  margin: 0;
  width: auto;
}

.existing-group-selector {
  margin-bottom: 20px;
}

.existing-group-options {
  max-height: 200px;
  overflow-y: auto;
  border: 1px solid #e1e8ed;
  border-radius: 8px;
  background: white;
}

.existing-group-option {
  padding: 12px 15px;
  border-bottom: 1px solid #f1f3f4;
  cursor: pointer;
  transition: background-color 0.2s ease;
}

.existing-group-option:last-child {
  border-bottom: none;
}

.existing-group-option:hover {
  background: #f8f9ff;
}

.existing-group-option.selected {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.group-option-name {
  font-weight: 600;
  margin-bottom: 4px;
}

.group-option-stats {
  font-size: 0.85em;
  color: #6c757d;
  display: flex;
  gap: 15px;
  flex-wrap: wrap;
}

.existing-group-option.selected .group-option-stats {
  color: rgba(255, 255, 255, 0.8);
}

.group-stat {
  display: flex;
  align-items: center;
  gap: 4px;
}

.group-stat-icon {
  width: 12px;
  height: 12px;
  border-radius: 50%;
}

.stat-total {
  background: #3498db;
}

.stat-up {
  background: #27ae60;
}

.stat-down {
  background: #e74c3c;
}

.stat-pending {
  background: #f39c12;
}

.group-info-panel {
  background: linear-gradient(135deg, #f8f9ff 0%, #e8f0fe 100%);
  border: 1px solid #e1e8ed;
  border-radius: 8px;
  padding: 15px;
  margin-top: 15px;
}

.group-info-title {
  font-weight: 600;
  color: #2c3e50;
  margin-bottom: 10px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.group-info-title::before {
  content: "📊";
  font-size: 16px;
}

.group-stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: 10px;
  margin-bottom: 10px;
}

.group-stat-item {
  text-align: center;
  padding: 8px;
  background: white;
  border-radius: 6px;
  border: 1px solid #e1e8ed;
}

.group-stat-value {
  font-size: 1.2em;
  font-weight: bold;
  color: #2c3e50;
}

.group-stat-label {
  font-size: 0.75em;
  color: #6c757d;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.group-description {
  color: #5a6c7d;
  font-size: 0.9em;
  font-style: italic;
}

.loading-spinner {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: #6c757d;
  font-size: 0.9em;
}

.loading-spinner::before {
  content: "⏳";
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.no-groups-message {
  text-align: center;
  padding: 20px;
  color: #6c757d;
  font-style: italic;
}

.no-groups-message::before {
  content: "📝";
  display: block;
  font-size: 2em;
  margin-bottom: 10px;
}

@media (max-width: 768px) {
  .form-row {
    grid-template-columns: 1fr;
  }
  
  .form-actions {
    flex-direction: column-reverse;
  }
  
  .group-type-options {
    flex-direction: column;
    gap: 10px;
  }
  
  .group-stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

/* Notification Channels Styling */
.channels-selection {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-bottom: 15px;
}

.channel-option {
  border: 2px solid #e1e8ed;
  border-radius: 8px;
  padding: 15px;
  background: white;
  transition: all 0.2s ease;
}

.channel-option:hover {
  border-color: #667eea;
  background: #f8f9ff;
}

.channel-option input[type="checkbox"]:checked + .channel-info {
  color: #667eea;
}

.channel-info {
  margin-left: 8px;
  flex: 1;
}

.channel-header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 5px;
}

.channel-name {
  font-size: 1em;
  color: #2c3e50;
}

.channel-badge {
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 0.7em;
  font-weight: bold;
}

.badge-telegram {
  background: #e3f2fd;
  color: #1976d2;
}

.badge-discord {
  background: #ede7f6;
  color: #5e35b1;
}

.badge-slack {
  background: #e8f5e8;
  color: #388e3c;
}

.badge-webhook {
  background: #fff3e0;
  color: #f57c00;
}

.channel-details {
  font-size: 0.85em;
  color: #7f8c8d;
}

.loading-state,
.no-channels-state {
  text-align: center;
  padding: 30px;
  background: #f8f9fa;
  border-radius: 8px;
  color: #7f8c8d;
}

.no-channels-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 15px;
}

.no-channels-state .btn {
  margin-top: 10px;
}
</style>