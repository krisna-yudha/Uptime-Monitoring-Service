<template>
  <div class="create-monitor">
    <div class="page-header">
      <div class="header-content">
        <div class="header-main">
          <h1>Create Monitor</h1>
          <!-- <p>Set up monitoring for your service</p> -->
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
                
                <!-- Existing Group Selector (searchable) -->
                <div v-if="selectedGroupType === 'existing'" class="group-search-wrapper">
                  <input
                    type="text"
                    class="form-control group-search"
                    placeholder="Search groups..."
                    v-model="groupSearch"
                    @input="onGroupSearchInput"
                    @focus="showGroupDropdown = true"
                    @keydown.down.prevent="focusNextGroup()"
                    @keydown.up.prevent="focusPrevGroup()"
                    @keydown.enter.prevent="selectHighlightedGroup()"
                    aria-autocomplete="list"
                    role="combobox"
                    :aria-expanded="showGroupDropdown"
                  />
                  <div v-if="showGroupDropdown && filteredGroups.length" class="group-dropdown" role="listbox">
                    <div class="group-dropdown-inner">
                      <div class="group-list" role="presentation">
                        <div
                          v-for="(group, idx) in filteredGroups"
                          :key="group.name"
                          class="group-list-item"
                          :class="{ highlighted: idx === highlightedIndex }"
                          @mouseenter="highlightedIndex = idx"
                          @mousedown.prevent="selectGroupFromSearch(group)"
                          role="option"
                          :aria-selected="form.group_name === group.name"
                        >
                          <div class="group-icon">📁</div>
                          <div class="group-name">{{ group.name }}</div>
                          <div class="group-count">{{ group.monitorsCount }}</div>
                        </div>
                      </div>

                      <div class="group-details" v-if="filteredGroups[highlightedIndex]">
                        <h4 class="details-title">{{ filteredGroups[highlightedIndex].name }}</h4>
                        <div class="details-stats">
                          <div class="stat"><span class="dot up"></span> {{ filteredGroups[highlightedIndex].upCount || 0 }} up</div>
                          <div class="stat"><span class="dot down"></span> {{ filteredGroups[highlightedIndex].downCount || 0 }} down</div>
                          <div class="stat">⏱ Avg: {{ filteredGroups[highlightedIndex].avgResponse || 0 }}ms</div>
                        </div>
                        <p class="details-desc">{{ filteredGroups[highlightedIndex].description || 'No description' }}</p>
                        <button type="button" class="btn btn-sm" @click.prevent="selectGroupFromSearch(filteredGroups[highlightedIndex])">Select this group</button>
                      </div>
                    </div>
                  </div>
                  <input type="hidden" v-model="form.group_name" />
                </div>
                
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
            <div style="display:flex;gap:12px;align-items:center;">
              <input
                id="target"
                v-model="form.target"
                type="text"
                class="form-control"
                required
                :placeholder="getTargetPlaceholder()"
                style="flex:1"
              >
              <input
                id="port"
                v-model.number="form.port"
                type="number"
                class="form-control"
                min="1"
                max="65535"
                placeholder="Port (optional)"
                style="width:120px"
              >
            </div>
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

          <div class="form-group">
            <label class="checkbox-label">
              <input type="checkbox" v-model="form.notifications_enabled" class="form-checkbox">
              Enable notifications for this monitor
            </label>
          </div>

          <template v-if="form.notifications_enabled">
            <div v-if="loadingChannels" class="loading-state">
              <span>Loading notification channels...</span>
            </div>

            <div v-else-if="availableChannels.length === 0" class="no-channels-state">
              <div class="no-channels-left">
                <span class="no-channels-emoji">📭</span>
                <div class="no-channels-text">No notification channels available (none configured or all disabled).</div>
              </div>
              <router-link to="/notifications" class="btn manage-notifications-btn" aria-label="Manage Notification Channels">
                Manage Notification Channels
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
          </template>

          <template v-else>
            <small class="form-text">Notifications are disabled for this monitor.</small>
          </template>
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
          <div class="form-group">
            <label class="checkbox-label">
              <input
                v-model="form.is_public"
                type="checkbox"
                class="form-checkbox"
              >
              Make this monitor public (visible without login)
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
import { ref, reactive, onMounted, computed, watch, nextTick } from 'vue'
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

// Searchable group selector state
const groupSearch = ref('')
const showGroupDropdown = ref(false)
const highlightedIndex = ref(-1)

const filteredGroups = computed(() => {
  const q = (groupSearch.value || '').toLowerCase().trim()
  if (!q) return groupsWithInfo.value || []
  return (groupsWithInfo.value || []).filter(g => (g.name || '').toLowerCase().includes(q))
})

const form = reactive({
  name: '',
  type: '',
  target: '',
  port: null,
  group_name: '',
  group_description: '',
  interval_seconds: 1,
  timeout_ms: 5000,
  retries: 3,
  notify_after_retries: 2,
  notification_channels: [],
  notifications_enabled: true,
  enabled: true,
  is_public: false
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
      // Filter out channels that are globally disabled on the Notification settings page.
      // Be defensive: different API versions may use different flag names (enabled, is_enabled, disabled, isActive, status, state, deleted, etc.).
      const raw = response.data.data?.data || response.data.data || []
      availableChannels.value = (raw || []).filter(ch => {
        if (!ch) return false

        // Exclude if explicit deleted flags present
        if (ch.deleted === true) return false
        if (ch.deleted_at) return false

        // Common explicit disabling flags (treat true as disabled for these):
        const disabledChecks = [
          ch.disabled === true,
          ch.is_disabled === true,
          ch.isDisabled === true,
          // some APIs use status/state strings
          typeof ch.status === 'string' && /disable|disabled|off|inactive/i.test(ch.status),
          typeof ch.state === 'string' && /disable|disabled|off|inactive/i.test(ch.state),
          // numeric 0/"0" may indicate disabled
          ch.status === 0,
          ch.status === '0'
        ]
        if (disabledChecks.some(Boolean)) return false

        // Also consider active/enabled flags (require true to keep)
        const enabledChecks = [
          ch.enabled === true,
          ch.is_enabled === true,
          ch.isEnabled === true,
          ch.active === true,
          ch.is_active === true,
          ch.isActive === true
        ]
        if (enabledChecks.some(Boolean)) return true

        // If there are explicit boolean flags that are false for enabled/active, exclude
        const explicitFalse = [
          ch.enabled === false,
          ch.is_enabled === false,
          ch.active === false,
          ch.is_active === false
        ]
        if (explicitFalse.some(Boolean)) return false

        // Fallback: if the object contains a visible UI label like 'DISABLED' in a `status_label` or `label`, try to detect it
        if (typeof ch.status_label === 'string' && /disable|disabled|off|inactive/i.test(ch.status_label)) return false
        if (typeof ch.label === 'string' && /disable|disabled|off|inactive/i.test(ch.label)) return false

        // Otherwise assume available
        return true
      })
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

watch(selectedGroupType, (v) => {
  if (v === 'existing') {
    groupSearch.value = form.group_name || ''
    // small delay to avoid UI flicker
    nextTick(() => { showGroupDropdown.value = !!filteredGroups.value.length })
  } else {
    showGroupDropdown.value = false
  }
})

function onGroupSearchInput() {
  showGroupDropdown.value = true
  highlightedIndex.value = 0
}

function selectGroupFromSearch(group) {
  form.group_name = group.name
  selectedGroupInfo.value = groupsWithInfo.value.find(g => g.name === group.name) || null
  groupSearch.value = group.name
  showGroupDropdown.value = false
}

function focusNextGroup() {
  if (highlightedIndex.value < filteredGroups.value.length - 1) highlightedIndex.value++
}
function focusPrevGroup() {
  if (highlightedIndex.value > 0) highlightedIndex.value--
}
function selectHighlightedGroup() {
  const g = filteredGroups.value[highlightedIndex.value]
  if (g) selectGroupFromSearch(g)
}

// Keep highlighted item visible in list
watch(highlightedIndex, async () => {
  await nextTick()
  const el = document.querySelector('.group-list-item.highlighted')
  if (el && el.scrollIntoView) {
    el.scrollIntoView({ block: 'nearest', inline: 'nearest' })
  }
})

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
    // Validate and attach port if provided
    if (monitorData.port) {
      const portNum = Number(monitorData.port)
      if (!Number.isInteger(portNum) || portNum < 1 || portNum > 65535) {
        error.value = 'Port must be an integer between 1 and 65535'
        loading.value = false
        return
      }

      const targetRaw = String(monitorData.target || '').trim()
      try {
        if (['http', 'https'].includes(form.type)) {
          // Try to preserve URL and set port via URL API
          try {
            const parsed = new URL(targetRaw)
            parsed.port = String(portNum)
            monitorData.target = parsed.toString()
          } catch (e) {
            // If URL parsing fails (no scheme), append port
            if (!targetRaw.includes(':')) {
              monitorData.target = `${targetRaw}:${portNum}`
            }
          }
        } else if (form.type === 'tcp') {
          // For tcp, ensure host:port format
          if (!targetRaw.includes(':')) {
            monitorData.target = `${targetRaw}:${portNum}`
          }
        } else {
          // For other types, append port if not present
          if (!targetRaw.includes(':')) {
            monitorData.target = `${targetRaw}:${portNum}`
          }
        }
      } catch (e) {
        // fallback: append port
        if (!targetRaw.includes(':')) monitorData.target = `${targetRaw}:${portNum}`
      }
    }
    
    // Clear group fields if "none" is selected
    if (selectedGroupType.value === 'none') {
      monitorData.group_name = ''
      monitorData.group_description = ''
    }

    // If notifications disabled for this monitor, ensure channels cleared
    if (!form.notifications_enabled) {
      monitorData.notification_channels = []
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
      // Await prefetch of the newly created monitor to ensure fresh data (SSL fields, checks) is available
      const created = result.data
      try {
        const prefetch = await monitorStore.fetchMonitor(created.id, { _t: Date.now() })
        console.log('Prefetched monitor after create:', prefetch)
      } catch (err) {
        console.warn('Prefetch failed:', err)
      }
      // Try to prefetch recent checks and inject into store so Detail view shows history immediately
      try {
        const checksResp = await monitorStore.api.monitorChecks.getAll({
          monitor_id: created.id,
          per_page: 10,
          sort: 'checked_at',
          order: 'desc',
          _t: Date.now()
        })

        if (checksResp.data && checksResp.data.success) {
          const checks = checksResp.data.data.data || checksResp.data.data || []
          if (checks.length > 0) {
            const normalized = checks.map(c => ({
              ...c,
              latency_ms: c.latency_ms ?? c.latency ?? c.response_time ?? c.response_time_ms ?? null
            }))

            // Update monitors list entry if present
            const idx = monitorStore.monitors.findIndex(m => m.id === created.id)
            if (idx !== -1) {
              monitorStore.monitors[idx].checks = normalized
            }

            // Update currentMonitor if it matches
            if (monitorStore.currentMonitor && monitorStore.currentMonitor.id === created.id) {
              monitorStore.currentMonitor.checks = normalized
            }

            console.log('Prefetched and injected checks for monitor', created.id)
          }
        }
      } catch (e) {
        console.warn('Prefetch checks failed:', e)
      }
      // Navigate to the monitor detail so user sees the created monitor immediately
      router.push(`/monitors/${created.id}`)
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
/* Modern, simple color palette and refined spacing */
.create-monitor {
  --bg: #f6f9fc;
  --card: #ffffff;
  --muted: #6b7a86;
  --accent: #2563eb; /* blue */
  --accent-2: #7c3aed; /* purple */
  --border: #e6eef7;
  --danger: #e04545;
  padding: 26px;
  max-width: 1100px;
  margin: 28px auto 0;
  box-sizing: border-box;
  font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
  background: transparent;
}

.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 18px;
  margin-bottom: 14px;
  padding: 8px 6px;
  position: relative;
}

.header-content { display:flex; gap:12px; align-items:center; margin: 0; padding: 0; }
.header-main { padding: 8px 16px; }
.header-actions { display:flex; align-items:center; position: absolute; right:12px; top:12px; }
.header-main h1 { margin:0 0 4px 0; color: #0f1724; font-size:1.45rem; }
.header-main p { margin:0; color: var(--muted); font-size:0.95rem }

.progress-indicator { display:flex; gap:12px; align-items:center; margin-left:auto; padding: 0 6px; }
.progress-step { display:flex; flex-direction:column; align-items:center; gap:6px; min-width:70px }
.step-number {
  width:36px; height:36px; border-radius:999px; display:flex; align-items:center; justify-content:center; font-weight:700; color:var(--accent); background:rgba(37,99,235,0.08);
}
.progress-step.active .step-number { background: linear-gradient(90deg,var(--accent),var(--accent-2)); color:white; box-shadow:0 8px 20px rgba(37,99,235,0.12) }
.step-label { font-size:0.78rem; color:var(--muted); text-align:center }
.progress-step { display:flex; flex-direction:column; align-items:center; gap:6px; min-width:60px }
.step-number {
  width:32px; height:32px; border-radius:999px; display:flex; align-items:center; justify-content:center; font-weight:700; color:var(--accent); background:rgba(37,99,235,0.08); font-size:0.92rem
}
.progress-line { flex:1; height:6px; background:var(--border); border-radius:6px }
.progress-line.active { background: linear-gradient(90deg,var(--accent),var(--accent-2)) }

/* Card-like form container */
.form-container {
  background: var(--card);
  border-radius: 14px;
  margin-top: 18px; /* breathing space between header and form */
  box-shadow: 0 14px 40px rgba(15,23,36,0.08);
  border: 1px solid var(--border);
  padding: 22px;
  transition: none;
}

/* small visual header inside the form */
.form-container::before {
  content: '';
  display:block;
  height:6px;
  border-radius:8px;
  background: linear-gradient(90deg, rgba(37,99,235,0.12), rgba(124,58,237,0.12));
  margin:-18px 22px 12px 22px;
}

.form-section {
  margin-bottom:16px;
  padding:16px;
  background: #fbfdff;
  border-radius:10px;
  border: 1px solid rgba(230,238,247,0.9);
  box-shadow: 0 6px 18px rgba(15,23,36,0.03);
}

.form-section + .form-section { margin-top:12px }

.form-section h3 {
  display:flex;
  align-items:center;
  gap:10px;
  margin:0 0 10px 0;
  font-size:1.03rem;
  color:#07122a;
}

.form-section .form-row { margin-top:8px }
.form-group { margin-bottom:10px }

/* Channel option improvements */
.channel-option {
  border:1px solid var(--border);
  border-radius:12px;
  padding:14px;
  background:#ffffff;
  transition: none;
  display:flex;
  gap:12px;
  align-items:flex-start;
}
.channel-option:hover { box-shadow: 0 10px 30px rgba(15,23,36,0.06) }
.checkbox-label { display:flex; gap:12px; align-items:flex-start; width:100%; cursor:pointer }
.form-checkbox { margin-top:6px; width:18px; height:18px }
.channel-info { flex:1 }

.form-row { display:grid; grid-template-columns:repeat(2,1fr); gap:14px }
.form-label { display:block; margin-bottom:6px; color:#223344; font-weight:600 }

.form-control { width:100%; padding:11px 12px; border:1px solid var(--border); border-radius:10px; background:#fbfdff; transition:box-shadow .12s ease,border-color .12s ease; font-size:0.95rem; color:#0f1724 }
.form-control::placeholder { color:#9aa6b2 }
.form-control:focus { outline: none; border-color: rgba(37,99,235,0.9); box-shadow: 0 6px 20px rgba(37,99,235,0.06) }

/* Ensure native select popups appear above layout on small screens */
select.form-control {
  position: relative;
  z-index: 60;
  -webkit-appearance: menulist-button;
  appearance: auto;
}
select.form-control:focus { z-index: 80 }

.form-text, .form-help { color:var(--muted); font-size:0.86rem; margin-top:6px }

.form-actions { display:flex; gap:12px; justify-content:flex-end; padding-top:16px }
.btn { font-weight:700; border-radius:10px; padding:9px 14px; cursor:pointer; border:1px solid transparent }
.btn:disabled { opacity:.6; cursor:not-allowed }
.btn-secondary { background:transparent; color:#334155; border:1px solid #dbe7f5 }
.btn-secondary:hover { background:#f8fafc }
.btn-success { color:white; background: linear-gradient(90deg,var(--accent),var(--accent-2)); box-shadow:0 8px 20px rgba(124,58,237,0.12); border:none }

.error-message { background:#fff5f5; color:var(--danger); padding:10px 12px; border-radius:8px; border:1px solid rgba(224,69,69,0.12); margin-bottom:12px }

.group-input-container {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.group-selector {
  margin-bottom: 0;
  min-width: 140px;
  max-width: 220px;
  flex: 0 0 200px;
}

.group-input-container > .form-control:not(.group-selector) {
  flex: 1 1 auto;
}

@media (min-width: 900px) {
  .group-input-container {
    flex-direction: row;
    align-items: center;
    gap: 12px;
  }
}

.existing-group-options { max-height:220px; overflow:auto; border-radius:8px }

.group-info { margin-top:6px; color: var(--muted); font-size:0.92rem }

/* Searchable group dropdown styles */
.group-search-wrapper { position: relative; width:100%; /* allow flex sizing in row layouts */ flex: 1 1 auto; min-width: 0; max-width: none }
.group-search { padding:10px 12px; width:100%; box-sizing: border-box }

/* Dropdown — desktop: align to input width, nice shadow, scrollable */
.group-dropdown {
  position: absolute;
  top: 100%;
  left: 0;
  width: 100%;
  background: #ffffff;
  border: 1px solid rgba(226,234,247,0.9);
  box-shadow: 0 8px 28px rgba(15,23,36,0.06), 0 2px 6px rgba(15,23,36,0.02) inset;
  max-height: 320px;
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
  z-index: 2000;
  border-radius: 8px;
  margin-top: 8px;
  box-sizing: border-box;
  min-width: 420px;
  max-width: min(720px, calc(100vw - 160px));
  transition: transform 180ms cubic-bezier(.2,.9,.2,1), opacity 160ms ease;
  /* start slightly lifted and faded; become visible while input is focused */
  transform: translateY(6px) scale(0.997);
  opacity: 0;
}

/* When the input inside wrapper is focused, show the dropdown with subtle motion */
.group-search-wrapper:focus-within .group-dropdown {
  transform: translateY(0) scale(1);
  opacity: 1;
}

/* Two-column dropdown layout */
.group-dropdown-inner { display: flex; gap: 12px; align-items: stretch }
.group-list { width: 320px; max-height: 320px; overflow-y: auto; border-right: 1px solid var(--border); border-radius: 6px 0 0 6px; background: #fff }
.group-list-item { display:flex; align-items:center; gap:12px; padding:12px 14px; cursor:pointer; min-height:48px; border-bottom: 1px solid rgba(230,238,247,0.9); position:relative; transition: transform 160ms ease, box-shadow 160ms ease, background 120ms ease }
.group-list-item:hover { transform: scale(1.02); box-shadow: 0 8px 18px rgba(15,23,36,0.04); }
.group-list-item.highlighted, .group-list-item:active { background: linear-gradient(90deg, rgba(102,126,234,0.06), rgba(102,126,234,0.03)); }

/* Left accent bar for highlighted item */
.group-list-item::before { content: ''; position: absolute; left: 10px; top: 12px; bottom: 12px; width: 0; border-radius: 6px; transition: width 160ms ease, background 160ms ease }
.group-list-item.highlighted::before, .group-list-item:hover::before { width: 6px; background: linear-gradient(180deg, #4f46e5, #06b6d4); }
.group-list-item .group-name { padding-left: 6px }
.group-icon { font-size:18px }
.group-name { font-weight:600; flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap }
.group-count { color:var(--muted); font-size:0.9rem; margin-left:8px }

.group-details { flex:1; padding:14px; display:flex; flex-direction:column; gap:8px; justify-content:flex-start }
.details-title { margin:0; font-size:1rem }
.details-stats { display:flex; gap:12px; align-items:center }
.details-stats .stat { font-size:0.95rem; color:var(--muted); display:flex; gap:6px; align-items:center }
.dot { width:10px; height:10px; border-radius:50%; display:inline-block }
.dot.up { background: #28a745 }
.dot.down { background: #dc3545 }
.details-desc { margin:0; color:var(--muted); font-size:0.95rem }
.group-details .btn { align-self:flex-start; padding:8px 12px; border-radius:10px; background: #0f1724; color: white; border: none; box-shadow: 0 6px 18px rgba(15,23,36,0.08); transform-origin: center; transition: transform 160ms ease, box-shadow 160ms ease, opacity 120ms ease }
.group-details .btn:hover { transform: translateY(-2px) scale(1.02); box-shadow: 0 10px 26px rgba(15,23,36,0.12); opacity: 0.98 }

/* Small accessibility focus state for keyboard users */
.group-list-item:focus { outline: 3px solid rgba(37,99,235,0.12); outline-offset: 2px }

/* Make search input visually attached to dropdown when open */
.group-search:focus {
  outline: none;
  box-shadow: 0 6px 24px rgba(37,99,235,0.06);
}

/* Rounded corners on top of dropdown to match input */
.group-dropdown { border-top-left-radius: 8px; border-top-right-radius: 8px; }

/* legacy single-column list styles removed (using two-column layout) */

/* Nice thin custom scrollbar where supported */
.group-dropdown::-webkit-scrollbar { width: 10px }
.group-dropdown::-webkit-scrollbar-thumb { background: rgba(15,23,42,0.08); border-radius: 8px }
.group-dropdown::-webkit-scrollbar-track { background: transparent }

/* Mobile: make dropdown easier to use — full-width, higher and fixed if needed */
@media (max-width: 600px) {
  .group-search-wrapper { max-width: 100% }
  .group-dropdown {
    position: fixed;
    left: 8px;
    right: 8px;
    bottom: 12px;
    top: auto;
    max-height: 60vh;
    margin-top: 0;
    border-radius: 12px;
    box-shadow: 0 20px 50px rgba(10,20,40,0.18);
  }

  /* Mobile: stack into single column and show preview below the list */
  .group-dropdown-inner { flex-direction: column; }
  .group-list { width: 100%; max-height: 45vh; overflow-y: auto }
  .group-list-item { padding: 14px 16px; font-size: 1rem }

  /* Show group preview/details on mobile below the list */
  .group-details { display: block; width: 100%; padding: 12px; border-top: 1px solid rgba(230,238,247,0.9); background: #fff; max-height: 30vh; overflow-y: auto; box-sizing: border-box }
  .group-details .details-title { font-size:1rem }

  /* Remove desktop min-width so the fixed mobile dropdown fits the viewport */
  .group-dropdown { min-width: unset; max-width: calc(100% - 16px); padding-bottom: 8px; }

  /* Allow selector to shrink on small screens instead of forcing 200px */
  .group-selector { flex: 1 1 auto; min-width: 0; max-width: 100%; }

  /* Ensure dropdown overlays other elements on mobile */
  .group-search-wrapper { z-index: 2200 }
}

.channel-option { border:1px solid var(--border); border-radius:10px; padding:12px; background:transparent }
.channel-header { display:flex; gap:8px; align-items:center }
.channel-badge { padding:3px 8px; border-radius:8px; font-size:0.72rem; font-weight:700 }
.badge-telegram { background:#eaf6ff; color:#0b79d0 }
.badge-discord { background:#f5eefb; color:#6d28d9 }
.badge-slack { background:#eefaf1; color:#0f9d58 }
.badge-webhook { background:#fff8eb; color:#c76b00 }

/* Further visual refinements */
.channel-badge { text-transform:uppercase; letter-spacing:0.4px; padding:4px 10px; font-size:0.70rem; font-weight:800 }
.form-control:focus-visible { outline: 3px solid rgba(37,99,235,0.12); outline-offset: 2px }
.form-control:focus { box-shadow: 0 10px 30px rgba(37,99,235,0.08) }
.btn-secondary { padding:8px 12px; border-radius:10px; font-size:0.95rem }
.btn-success { padding:10px 18px; border-radius:12px }
.form-section { transition: none; overflow: visible }

/* Only apply lift (transform) on hover-capable devices (desktop) to avoid creating stacking contexts that break native select popups on mobile */
@media (hover: hover) and (min-width: 900px) {
  .form-section:hover { box-shadow: 0 18px 40px rgba(15,23,36,0.06) }
  .channel-option { transition: none }
  .channel-option:hover { box-shadow: 0 18px 40px rgba(15,23,36,0.06) }
}
@media (max-width: 899px) {
  .channel-option:hover { box-shadow: 0 10px 24px rgba(15,23,36,0.04) }
}
.form-checkbox { accent-color: var(--accent); }

.progress-step .step-label { color: #5b6b75 }
.progress-step.active .step-label { color: var(--accent-2) }

/* Responsive */
@media (max-width: 900px) {
  .form-row { grid-template-columns:1fr }
  .progress-indicator { display:none }
  .create-monitor { padding: 18px; }
  .page-header { padding: 12px 8px; }
  .form-container { padding:18px }
}
@media (max-width: 480px) {
  .page-header { flex-direction:column; align-items:flex-start; gap:10px; padding:12px }
  .header-actions { position: static; margin-top:6px; width:100%; display:flex; justify-content:flex-end }
  .header-main h1 { font-size:1.18rem }
  .create-monitor { padding: 12px }
  /* Reduce unused bottom space and expand form to use viewport height */
  .form-container { padding:14px; margin-top:8px; padding-bottom:28px; min-height: calc(100vh - 160px); }
  .form-section { padding:12px }
  .form-row { gap:10px }
  .form-control { padding:10px 10px }
  .btn { width:100%; display:inline-flex; align-items:center; justify-content:center; text-align:center }
  .form-actions { flex-direction:column-reverse }
  /* keep actions visible on mobile when form is long
  .form-actions { position: sticky; bottom: 10px; left: 10px; right: 10px; padding:10px; background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(255,255,255,0.95)); border-radius:10px; gap:8px; z-index:60 }
  .btn-success { min-width:140px } */
}

/* Manage Notification button and no-channels layout */
.no-channels-state {
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:16px;
}
.no-channels-left { display:flex; align-items:center; gap:12px; flex:1 }
.no-channels-emoji { font-size:1.18rem }
.no-channels-text { color:var(--muted); font-size:0.95rem }
.manage-notifications-btn {
  background: linear-gradient(90deg,var(--accent),var(--accent-2));
  color: #fff;
  padding:10px 16px;
  border-radius:10px;
  text-decoration:none;
  box-shadow: 0 8px 20px rgba(37,99,235,0.12);
  border: none;
  font-weight:700;
}
.manage-notifications-btn:hover { opacity:0.95 }

@media (max-width: 600px) {
  .no-channels-state { flex-direction:column; align-items:flex-start }
  .manage-notifications-btn { width:100%; display:inline-flex; justify-content:center }
}

</style>