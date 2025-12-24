<template>
  <div class="notification-channels">
    <div class="content-inner">
      <div class="page-header">
      <div class="page-header-inner">
        <h1>Notification Channels</h1>
          <button v-if="!showCreateForm && !editingChannel" @click="showCreateForm = true" class="btn btn-primary">
            Add Channel
          </button>
      </div>

      <!-- Create/Edit Form -->
    <div v-if="showCreateForm || editingChannel" class="channel-form-container">
      <form @submit.prevent="submitForm" class="channel-form">
        <h2>{{ editingChannel ? 'Edit' : 'Add New' }} Notification Channel</h2>
        
        <div class="form-group">
          <label for="name">Channel Name *</label>
          <input
            id="name"
            v-model="form.name"
            type="text"
            placeholder="Enter channel name"
            required
            class="form-control"
          />
        </div>

        <div class="form-group">
          <label for="type">Channel Type *</label>
          <select
            id="type"
            v-model="form.type"
            required
            class="form-control"
            @change="onTypeChange"
          >
            <option value="telegram">Telegram</option>
            <option value="discord">Discord</option>
            <option value="slack">Slack</option>
            <option value="webhook">Generic Webhook</option>
          </select>
        </div>

        <div class="form-group">
          <label class="checkbox-label">
            <input
              type="checkbox"
              v-model="form.is_enabled"
              class="form-checkbox"
            >
            <span>Enable this notification channel</span>
          </label>
          <small class="form-hint">
            Disabled channels will not receive notifications even if linked to monitors
          </small>
        </div>

        <!-- Telegram Configuration -->
        <div v-if="form.type === 'telegram'" class="form-section">
          <h3>Telegram Configuration</h3>
          
          <div class="form-group">
            <label for="telegram_bot_token">Bot Token *</label>
            <input
              id="telegram_bot_token"
              v-model="form.telegram_bot_token"
              type="text"
              placeholder="Bot token from @BotFather"
              required
              class="form-control"
            />
          </div>

          <div class="form-group">
            <label for="telegram_chat_id">Chat ID *</label>
            <input
              id="telegram_chat_id"
              v-model="form.telegram_chat_id"
              type="text"
              placeholder="Chat ID (user ID or group ID)"
              required
              class="form-control"
            />
          </div>

          <div class="telegram-help">
            <h4>How to get Chat ID:</h4>
            <ol>
              <li>Start a chat with your bot</li>
              <li>Send any message to the bot</li>
              <li>Visit: https://api.telegram.org/bot{TOKEN}/getUpdates</li>
              <li>Look for "chat":{"id": YOUR_CHAT_ID}</li>
            </ol>
          </div>
        </div>

        <!-- Discord Configuration -->
        <div v-if="form.type === 'discord'" class="form-section">
          <h3>Discord Configuration</h3>
          
          <div class="form-group">
            <label for="discord_webhook_url">Webhook URL *</label>
            <input
              id="discord_webhook_url"
              v-model="form.discord_webhook_url"
              type="url"
              placeholder="https://discord.com/api/webhooks/..."
              required
              class="form-control"
            />
          </div>

          <div class="discord-help">
            <h4>How to create Discord webhook:</h4>
            <ol>
              <li>Go to your Discord server settings</li>
              <li>Navigate to Integrations > Webhooks</li>
              <li>Create a new webhook</li>
              <li>Copy the webhook URL</li>
            </ol>
          </div>
        </div>

        <!-- Slack Configuration -->
        <div v-if="form.type === 'slack'" class="form-section">
          <h3>Slack Configuration</h3>
          
          <div class="form-group">
            <label for="slack_webhook_url">Webhook URL *</label>
            <input
              id="slack_webhook_url"
              v-model="form.slack_webhook_url"
              type="url"
              placeholder="https://hooks.slack.com/services/..."
              required
              class="form-control"
            />
          </div>

          <div class="form-group">
            <label for="slack_channel">Channel (optional)</label>
            <input
              id="slack_channel"
              v-model="form.slack_channel"
              type="text"
              placeholder="#general or @username"
              class="form-control"
            />
          </div>

          <div class="slack-help">
            <h4>How to create Slack webhook:</h4>
            <ol>
              <li>Go to your Slack app settings</li>
              <li>Create a new app or use existing one</li>
              <li>Add Incoming Webhooks feature</li>
              <li>Create webhook for your workspace</li>
            </ol>
          </div>
        </div>

        <!-- Webhook Configuration -->
        <div v-if="form.type === 'webhook'" class="form-section">
          <h3>Generic Webhook Configuration</h3>
          
          <div class="form-group">
            <label for="webhook_url">Webhook URL *</label>
            <input
              id="webhook_url"
              v-model="form.webhook_url"
              type="url"
              placeholder="https://your-webhook-endpoint.com/notify"
              required
              class="form-control"
            />
          </div>

          <div class="form-group">
            <label for="webhook_method">HTTP Method</label>
            <select
              id="webhook_method"
              v-model="form.webhook_method"
              class="form-control"
            >
              <option value="POST">POST</option>
              <option value="PUT">PUT</option>
              <option value="PATCH">PATCH</option>
            </select>
          </div>

          <div class="form-group">
            <label for="webhook_headers">Headers (JSON format)</label>
            <textarea
              id="webhook_headers"
              v-model="form.webhook_headers"
              class="form-control"
              rows="3"
              placeholder='{"Content-Type": "application/json", "Authorization": "Bearer token"}'
            ></textarea>
          </div>

          <div class="form-group">
            <label for="webhook_payload">Custom Payload Template (JSON)</label>
            <textarea
              id="webhook_payload"
              v-model="form.webhook_payload"
              class="form-control"
              rows="5"
              placeholder='{"message": "{{message}}", "status": "{{status}}", "monitor": "{{monitor_name}}"}'
            ></textarea>
            <small class="form-hint">
              Available variables: {{message}}, {{status}}, {{monitor_name}}, {{timestamp}}
            </small>
          </div>
        </div>

        <div class="form-actions">
          <button
            type="submit"
            :disabled="submitting"
            class="btn btn-primary"
          >
            {{ submitting ? 'Saving...' : (editingChannel ? 'Update Channel' : 'Create Channel') }}
          </button>
          
          <button
            type="button"
            @click="cancelForm"
            class="btn btn-secondary"
          >
            Cancel
          </button>
          
          <button
            v-if="editingChannel"
            type="button"
            @click="testChannel"
            :disabled="testing"
            class="btn btn-info"
          >
            {{ testing ? 'Testing...' : 'Test Channel' }}
          </button>
        </div>
      </form>
    </div>

    <!-- Channels List -->
    <div v-if="loading" class="loading">Loading notification channels...</div>
    
    <div v-else-if="channels.length === 0 && !showCreateForm" class="no-channels">
      <h2>No notification channels configured</h2>
      <p>Create your first notification channel to receive alerts when your monitors go down.</p>
      <button @click="showCreateForm = true" class="btn btn-primary btn-lg">
        Create First Channel
      </button>
    </div>
    
    <div v-else-if="!showCreateForm && !editingChannel" class="channels-grid">
      <div
        v-for="channel in channels"
        :key="channel.id"
        class="channel-card"
        :class="{ 'channel-disabled': !channel.is_enabled }"
        @click="openActionSheet(channel, $event)"
      >
        <div class="channel-header">
          <div class="channel-type-badge" :class="`type-${channel.type}`">
            {{ channel.type.toUpperCase() }}
          </div>
          <div class="status-indicator" :class="channel.is_enabled ? 'status-enabled' : 'status-disabled'"></div>
        </div>
        
        <h3 class="channel-name">
          {{ channel.name }}
          <span v-if="!channel.is_enabled" class="disabled-badge">DISABLED</span>
        </h3>
        
        <div class="channel-details">
          <div v-if="channel.type === 'telegram' && channel.config">
            <strong>Bot Token:</strong> {{ maskToken(channel.config.bot_token) }}<br>
            <strong>Chat ID:</strong> {{ channel.config.chat_id }}
          </div>
          
          <div v-else-if="channel.type === 'discord' && channel.config">
            <strong>Webhook:</strong> {{ maskUrl(channel.config.webhook_url) }}
          </div>
          
          <div v-else-if="channel.type === 'slack' && channel.config">
            <strong>Webhook:</strong> {{ maskUrl(channel.config.webhook_url) }}<br>
            <span v-if="channel.config.channel">
              <strong>Channel:</strong> {{ channel.config.channel }}
            </span>
          </div>
          
          <div v-else-if="channel.type === 'webhook' && channel.config">
            <strong>URL:</strong> {{ maskUrl(channel.config.webhook_url) }}<br>
            <strong>Method:</strong> {{ channel.config.method || 'POST' }}
          </div>
        </div>
        
        <div class="channel-actions">
          <button
            @click.stop="toggleChannel(channel.id)"
            :class="channel.is_enabled ? 'btn btn-warning btn-sm' : 'btn btn-success btn-sm'"
          >
            {{ channel.is_enabled ? 'Disable' : 'Enable' }}
          </button>
          <button @click.stop="editChannel(channel)" class="btn btn-primary btn-sm">
            Edit
          </button>
          <button
            @click.stop="testChannelById(channel.id)"
            :disabled="!channel.is_enabled"
            class="btn btn-info btn-sm"
          >
            Test
          </button>
          <button @click.stop="deleteChannel(channel.id)" class="btn btn-danger btn-sm">
            Delete
          </button>
        </div>
      </div>
    </div>
    </div>

  <!-- Mobile Action Sheet -->
  <div v-if="actionChannel" class="action-sheet-backdrop" @click.self="closeActionSheet">
    <div class="action-sheet">
      <h3 class="action-sheet-title">{{ actionChannel.name }}</h3>
      <div class="action-sheet-actions">
        <button @click="actionToggle" :class="actionChannel.is_enabled ? 'btn btn-warning btn-block' : 'btn btn-success btn-block'">
          {{ actionChannel.is_enabled ? 'Disable' : 'Enable' }}
        </button>
        <button @click="actionEdit" class="btn btn-primary btn-block">Edit</button>
        <button @click="actionTest" :disabled="!actionChannel.is_enabled" class="btn btn-info btn-block">Test</button>
        <button @click="actionDelete" class="btn btn-danger btn-block">Delete</button>
        <button @click="closeActionSheet" class="btn btn-secondary btn-block">Cancel</button>
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
const channels = ref([])
const showCreateForm = ref(false)
const editingChannel = ref(null)
const submitting = ref(false)
const testing = ref(false)
const actionChannel = ref(null)

const form = ref({
  name: '',
  type: 'telegram',
  is_enabled: true,
  
  // Telegram
  telegram_bot_token: '',
  telegram_chat_id: '',
  
  // Discord
  discord_webhook_url: '',
  
  // Slack
  slack_webhook_url: '',
  slack_channel: '',
  
  // Generic webhook
  webhook_url: '',
  webhook_method: 'POST',
  webhook_headers: '',
  webhook_payload: ''
})

onMounted(() => {
  fetchChannels()
})

async function fetchChannels() {
  loading.value = true
  
  try {
    const response = await api.notificationChannels.getAll()
    if (response.data.success) {
      channels.value = response.data.data.data || response.data.data
    }
  } catch (err) {
    console.error('Failed to load notification channels:', err)
    alert('Failed to load notification channels')
  } finally {
    loading.value = false
  }
}

function onTypeChange() {
  // Reset form fields when type changes
  const type = form.value.type
  Object.keys(form.value).forEach(key => {
    if (key !== 'name' && key !== 'type') {
      form.value[key] = ''
    }
  })
  
  if (type === 'webhook') {
    form.value.webhook_method = 'POST'
  }
}

function editChannel(channel) {
  editingChannel.value = channel
  showCreateForm.value = false
  
  // Reset form first
  resetForm()
  
  // Set basic fields
  form.value.name = channel.name
  form.value.type = channel.type
  form.value.is_enabled = channel.is_enabled !== undefined ? channel.is_enabled : true
  
  // Parse config object based on channel type
  if (channel.config) {
    if (channel.type === 'telegram') {
      form.value.telegram_bot_token = channel.config.bot_token || ''
      form.value.telegram_chat_id = channel.config.chat_id || ''
    } else if (channel.type === 'discord') {
      form.value.discord_webhook_url = channel.config.webhook_url || ''
    } else if (channel.type === 'slack') {
      form.value.slack_webhook_url = channel.config.webhook_url || ''
      form.value.slack_channel = channel.config.channel || ''
    } else if (channel.type === 'webhook') {
      form.value.webhook_url = channel.config.webhook_url || ''
      form.value.webhook_method = channel.config.method || 'POST'
      
      if (channel.config.headers) {
        form.value.webhook_headers = typeof channel.config.headers === 'string'
          ? channel.config.headers
          : JSON.stringify(channel.config.headers, null, 2)
      }
      
      if (channel.config.payload) {
        form.value.webhook_payload = typeof channel.config.payload === 'string'
          ? channel.config.payload
          : JSON.stringify(channel.config.payload, null, 2)
      }
    }
  }
}

function cancelForm() {
  showCreateForm.value = false
  editingChannel.value = null
  resetForm()
}

function resetForm() {
  form.value = {
    name: '',
    type: 'telegram',
    is_enabled: true,
    telegram_bot_token: '',
    telegram_chat_id: '',
    discord_webhook_url: '',
    slack_webhook_url: '',
    slack_channel: '',
    webhook_url: '',
    webhook_method: 'POST',
    webhook_headers: '',
    webhook_payload: ''
  }
}

async function submitForm() {
  submitting.value = true
  
  try {
    // Prepare config object based on channel type
    let config = {}
    
    if (form.value.type === 'telegram') {
      config = {
        bot_token: form.value.telegram_bot_token,
        chat_id: form.value.telegram_chat_id
      }
    } else if (form.value.type === 'discord') {
      config = {
        webhook_url: form.value.discord_webhook_url
      }
    } else if (form.value.type === 'slack') {
      config = {
        webhook_url: form.value.slack_webhook_url,
        channel: form.value.slack_channel || null
      }
    } else if (form.value.type === 'webhook') {
      config = {
        webhook_url: form.value.webhook_url,
        method: form.value.webhook_method || 'POST'
      }
      
      // Parse JSON fields
      if (form.value.webhook_headers) {
        try {
          config.headers = JSON.parse(form.value.webhook_headers)
        } catch (e) {
          alert('Invalid JSON format in webhook headers')
          submitting.value = false
          return
        }
      }
      
      if (form.value.webhook_payload) {
        try {
          config.payload = JSON.parse(form.value.webhook_payload)
        } catch (e) {
          alert('Invalid JSON format in webhook payload')
          submitting.value = false
          return
        }
      }
    }
    
    const formData = {
      name: form.value.name,
      type: form.value.type,
      config: config,
      is_enabled: form.value.is_enabled
    }
    
    let response
    if (editingChannel.value) {
      response = await api.notificationChannels.update(editingChannel.value.id, formData)
    } else {
      response = await api.notificationChannels.create(formData)
    }
    
    if (response.data.success) {
      await fetchChannels()
      cancelForm()
      alert(editingChannel.value ? 'Channel berhasil diupdate!' : 'Channel berhasil dibuat!')
    } else {
      alert(response.data.message || 'Gagal menyimpan channel')
    }
  } catch (err) {
    console.error('Failed to save channel:', err)
    console.error('Error details:', err.response?.data)
    
    let errorMessage = 'Terjadi kesalahan saat menyimpan channel'
    
    if (err.response?.data?.errors) {
      // Validation errors
      const errors = err.response.data.errors
      errorMessage = Object.values(errors).flat().join('\n')
    } else if (err.response?.data?.message) {
      errorMessage = err.response.data.message
    } else if (err.message) {
      errorMessage = err.message
    }
    
    alert(`Error: ${errorMessage}`)
  } finally {
    submitting.value = false
  }
}

async function deleteChannel(channelId) {
  if (!confirm('Are you sure you want to delete this notification channel?')) {
    return
  }
  
  try {
    const response = await api.notificationChannels.delete(channelId)
    
    if (response.data.success) {
      await fetchChannels()
    } else {
      alert(response.data.message || 'Failed to delete channel')
    }
  } catch (err) {
    console.error('Failed to delete channel:', err)
    alert('An error occurred while deleting the channel')
  }
}

async function testChannel() {
  testing.value = true
  
  try {
    const response = await api.notificationChannels.test(editingChannel.value.id)
    
    if (response.data.success) {
      alert('Notifikasi test berhasil dikirim! Periksa Discord channel Anda.')
    } else {
      alert(response.data.message || 'Gagal mengirim notifikasi test')
    }
  } catch (err) {
    console.error('Failed to test channel:', err)
    console.error('Error response:', err.response?.data)
    
    let errorMessage = 'Terjadi kesalahan saat mengirim notifikasi test'
    
    if (err.response?.data?.message) {
      errorMessage = err.response.data.message
    } else if (err.message) {
      errorMessage = err.message
    }
    
    alert(`Error: ${errorMessage}`)
  } finally {
    testing.value = false
  }
}

async function testChannelById(channelId) {
  try {
    const response = await api.notificationChannels.test(channelId)
    
    if (response.data.success) {
      alert('Notifikasi test berhasil dikirim! Periksa Discord channel Anda.')
    } else {
      alert(response.data.message || 'Gagal mengirim notifikasi test')
    }
  } catch (err) {
    console.error('Failed to test channel:', err)
    console.error('Error response:', err.response?.data)
    
    let errorMessage = 'Terjadi kesalahan saat mengirim notifikasi test'
    
    if (err.response?.data?.message) {
      errorMessage = err.response.data.message
    } else if (err.message) {
      errorMessage = err.message
    }
    
    alert(`Error: ${errorMessage}`)
  }
}

async function toggleChannel(channelId) {
  try {
    const response = await api.notificationChannels.toggle(channelId)
    
    if (response.data.success) {
      await fetchChannels()
    } else {
      alert(response.data.message || 'Gagal mengubah status channel')
    }
  } catch (err) {
    console.error('Failed to toggle channel:', err)
    alert('Terjadi kesalahan saat mengubah status channel')
  }
}

function maskToken(token) {
  if (!token) return ''
  return token.substring(0, 8) + '...' + token.substring(token.length - 8)
}

function maskUrl(url) {
  if (!url) return ''
  return url.substring(0, 30) + '...'
}

function openActionSheet(channel, evt) {
  // Ignore clicks that originated on interactive elements (buttons/links)
  if (evt && evt.target) {
    const btn = evt.target.closest && evt.target.closest('button, a')
    if (btn) return
  }

  // Only open sheet on smaller viewports
  if (typeof window !== 'undefined' && !window.matchMedia('(max-width: 768px)').matches) return
  actionChannel.value = channel
}

function closeActionSheet() {
  actionChannel.value = null
}

function actionEdit() {
  if (!actionChannel.value) return
  editChannel(actionChannel.value)
  closeActionSheet()
}

function actionTest() {
  if (!actionChannel.value) return
  testChannelById(actionChannel.value.id)
  closeActionSheet()
}

function actionDelete() {
  if (!actionChannel.value) return
  deleteChannel(actionChannel.value.id)
  closeActionSheet()
}

async function actionToggle() {
  if (!actionChannel.value) return
  await toggleChannel(actionChannel.value.id)
  closeActionSheet()
}
</script>

<style scoped>
.notification-channels {
  padding: 20px 0; /* move horizontal padding to inner container to match app layout */
}

/* Mobile action-sheet and responsive tweaks */
.action-sheet-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.36);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
}
.action-sheet {
  width: calc(100% - 40px);
  max-width: 540px;
  background: linear-gradient(180deg, #ffffff, #fbfdff);
  border-radius: 14px;
  padding: 18px;
  box-shadow: 0 12px 40px rgba(2,6,23,0.18);
  transform: translateY(0) scale(1);
  opacity: 1;
  transition: transform 220ms ease, opacity 220ms ease;
  max-height: 80vh;
  overflow: auto;
}
.action-sheet.enter {
  transform: translateY(8px) scale(0.98);
  opacity: 0;
}
.action-sheet-title { margin: 0 0 8px 0; font-weight:600; }
.action-sheet-actions { display:flex; flex-direction:column; gap:10px; }
.btn-block { display:block; width:100%; }

@media (max-width: 768px) {
  /* keep the primary enable/disable button visible on mobile, hide the other inline buttons */
  .channel-actions { display: flex; align-items: center; gap: 8px; }
  .channel-actions > .btn:not(:first-child) { display: none !important; }
  .channel-card { cursor: pointer; }
}

.page-header {
  display: block;
  padding: 12px 0; /* vertical spacing only; inner container handles horizontal padding */
  margin: 0 0 32px 0;
  width: 100%;
  position: relative;
  border-radius: 10px;
  background: rgba(255,255,255,0.98);
  box-shadow: 0 6px 20px rgba(12,17,23,0.06);
}

.page-header h1 {
  margin: 0;
  color: #2c3e50;
  font-size: clamp(1.25rem, 2.0vw, 1.6rem);
  font-weight: 600;
}

.channel-form-container {
  background: var(--color-surface);
  border-radius: 12px;
  box-shadow: var(--shadow-1);
  margin: 24px auto 30px;
  max-width: 980px;
  border: 1px solid rgba(15,23,42,0.04);
}

.channel-form {
  padding: 20px;
  display: grid;
  grid-template-columns: 1fr 320px;
  gap: 16px;
  align-items: start;
}

/* Prevent grid children and inputs from forcing horizontal overflow on narrow screens */
.channel-form, .channel-form * {
  box-sizing: border-box;
}
.channel-form > * {
  min-width: 0; /* allow children to shrink inside the grid */
}

.form-control {
  max-width: 100%;
  box-sizing: border-box;
  word-break: break-word;
}

.channel-form h2 {
  grid-column: 1 / -1;
  margin: 0 0 12px 0;
  color: var(--color-text);
  font-size: 1.25rem;
}

.channel-form h2 {
  margin: 0 0 8px 0;
  color: var(--color-text);
  font-size: 1.25rem;
}

.form-section {
  margin: 0;
  padding: 14px;
  background-color: var(--color-bg);
  border-radius: 8px;
  grid-column: 1 / -1;
}

.form-section h3 {
  margin: 0 0 20px 0;
  color: #34495e;
  font-size: 1.1em;
}

.form-group {
  margin-bottom: 16px;
}

.form-group label {
  display: block;
  margin-bottom: 6px;
  font-weight: 600;
  color: var(--color-text);
}

.form-control {
  width: 100%;
  padding: 12px 14px;
  border: 1px solid rgba(15,23,42,0.06);
  border-radius: 10px;
  font-size: 0.95rem;
  background: #fff;
  color: var(--color-text);
  transition: box-shadow .14s ease, border-color .14s ease;
}

.form-control:focus {
  outline: none;
  border-color: var(--color-accent);
  box-shadow: 0 6px 20px rgba(37,99,235,0.1);
}

/* Ensure selects/textareas and their options render dark text */
select.form-control,
textarea.form-control {
  color: var(--color-text);
}

/* Some browsers allow styling option text color */
.form-control option {
  color: var(--color-text);
  background: var(--color-surface);
}

.form-control::placeholder {
  color: rgba(15,23,42,0.45);
}

.form-hint {
  display: block;
  margin-top: 6px;
  font-size: 0.85rem;
  color: var(--color-muted);
}

.telegram-help, .discord-help, .slack-help {
  margin-top: 12px;
  padding: 14px;
  background-color: rgba(37,99,235,0.06);
  border-left: 4px solid var(--color-accent);
  border-radius: 8px;
}

.telegram-help h4, .discord-help h4, .slack-help h4 {
  margin: 0 0 8px 0;
  color: var(--color-accent-2);
  font-size: 0.95rem;
}

.telegram-help ol, .discord-help ol, .slack-help ol {
  margin: 0;
  padding-left: 20px;
}

.telegram-help li, .discord-help li, .slack-help li {
  margin-bottom: 5px;
  font-size: 0.8em;
  color: #34495e;
}

.form-actions {
  display: flex;
  gap: 12px;
  padding-top: 14px;
  border-top: 1px solid rgba(15,23,42,0.04);
  margin-top: 14px;
  align-items: center;
  grid-column: 1 / -1;
  justify-content: flex-end;
}

.channels-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 16px;
  margin-top: 12px;
  width: 100%;
  box-sizing: border-box;
}


/* Standardize small primary buttons used across the view */
.btn-primary-sm {
  --btn-padding-y: 8px;
  --btn-padding-x: 12px;
  padding: var(--btn-padding-y) var(--btn-padding-x);
  font-size: 0.95rem;
  line-height: 1;
  min-height: 40px;
  border-radius: 8px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

/* Ensure small buttons don't stretch full-width on desktop but remain full on narrow screens */
.btn-primary-sm.full-width-mobile {
  width: auto;
}

@media (max-width: 640px) {
  .btn-primary-sm.full-width-mobile {
    width: 100%;
  }
}
.channel-card {
  background: var(--color-surface);
  border-radius: 10px;
  box-shadow: var(--shadow-1);
  padding: clamp(12px, 1.2vw, 20px);
  border: 1px solid rgba(15,23,42,0.03);
  transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
  display: flex;
  flex-direction: column;
  justify-content: flex-start;
  min-height: 150px;
}

.channel-card:hover {
  border-color: rgba(37,99,235,0.14);
  transform: translateY(-6px);
  box-shadow: var(--shadow-2);
}

.channel-card.channel-disabled {
  opacity: 0.7;
  background-color: #f8f9fa;
  border: 2px solid #e0e0e0;
}

.channel-card.channel-disabled:hover {
  border-color: #bdc3c7;
}

.channel-header {
  display: flex;
  justify-content: flex-start;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
}

.channel-type-badge {
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 0.7em;
  font-weight: bold;
  text-transform: uppercase;
}

.channel-type-badge.type-telegram {
  background-color: rgba(37,99,235,0.08);
  color: var(--color-accent-2);
}

.channel-type-badge.type-discord {
  background-color: rgba(125,106,255,0.06);
  color: #5e35b1;
}

.channel-type-badge.type-slack {
  background-color: rgba(16,185,129,0.06);
  color: #10b981;
}

.channel-type-badge.type-webhook {
  background-color: rgba(245,124,0,0.06);
  color: #f57c00;
}

.status-indicator {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background-color: rgba(15,23,42,0.08);
  display: block;
}

.status-indicator.status-enabled {
  background-color: var(--color-success);
  box-shadow: 0 0 10px rgba(5,150,105,0.18);
}

.status-indicator.status-disabled {
  background-color: var(--color-danger);
}

.channel-name {
  margin: 0 0 12px 0;
  color: var(--color-text);
  font-size: clamp(1rem, 1.1vw, 1.1em);
  display: flex;
  align-items: center;
  gap: 8px;
}

.disabled-badge {
  font-size: 0.6em;
  padding: 3px 6px;
  background-color: #e74c3c;
  color: white;
  border-radius: 3px;
  font-weight: bold;
}

.channel-details {
  margin-bottom: 16px;
  font-size: clamp(0.88rem, 0.9vw, 0.95rem);
  color: var(--color-muted);
  line-height: 1.45;
  min-height: 44px;
}

.channel-details strong {
  color: #2c3e50;
}

.channel-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  /* Push actions to the bottom of the card for consistent alignment */
  margin-top: auto;
}

/* Make channel action buttons uniform in size and spacing */
.channel-actions .btn {
  padding: 4px 8px;
  font-size: 0.85rem;
  height: 32px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 6px;
  flex: 1 1 0%;
  min-width: 0;
}

.channel-actions .btn-sm {
  padding: 4px 8px;
  font-size: 0.85rem;
  min-width: 60px;
  height: 32px;
}

.channel-actions .btn + .btn {
  margin-left: 6px;
}

.no-channels {
  text-align: center;
  padding: 60px 20px;
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.no-channels h2 {
  margin: 0 0 15px 0;
  color: #2c3e50;
}

.no-channels p {
  margin: 0 0 30px 0;
  color: #7f8c8d;
  max-width: 500px;
  margin-left: auto;
  margin-right: auto;
}

.btn {
  padding: 8px 16px;
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
  background: linear-gradient(90deg, var(--color-accent), var(--color-accent-2));
  color: white;
  box-shadow: 0 8px 24px rgba(37,99,235,0.12);
}

.btn-primary:hover:not(:disabled) {
  filter: brightness(0.98);
}

/* Header layout: stack title above the button (button below title) */
.page-header {
  /* keep outer header as visual container; inner wrapper controls content width */
  padding: 12px 16px;
  background: rgba(255,255,255,0.98);
  border-radius: 10px;
}

.content-inner {
  width: 100%;
  max-width: 1280px; /* match other pages like Monitors/Incidents */
  margin: 0 auto 24px;
  padding: 18px 20px; /* same inner padding as header-content in other views */
  box-sizing: border-box;
}

.page-header-inner {
  width: 100%;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 12px;
  align-items: stretch;
  padding: 0;
}

.page-header-inner h1 {
  margin: 0 0 6px 0;
  color: #2c3e50;
}

.page-header-inner .btn {
  width: auto;
  max-width: 360px; /* smaller max so header button looks compact */
  justify-content: center;
  align-self: flex-start; /* align to the left edge of the inner container */
  padding: 8px 12px;
  font-size: 0.95rem;
  height: 36px;
}

/* Disable animation for header button to avoid float/hover effects */
.page-header .btn,
.page-header .btn:hover,
.page-header .btn:active {
  transition: none !important;
  transform: none !important;
  box-shadow: none !important;
}

.btn-secondary {
  background-color: #95a5a6;
  color: white;
}

.btn-secondary:hover {
  background-color: #7f8c8d;
}

.btn-info {
  background-color: #17a2b8;
  color: white;
}

.btn-info:hover:not(:disabled) {
  background-color: #138496;
}

.btn-danger {
  background-color: #e74c3c;
  color: white;
}

.btn-danger:hover:not(:disabled) {
  background-color: #c0392b;
}

.btn-sm {
  padding: 5px 10px;
  font-size: 0.8em;
  min-height: 34px;
}

.btn-lg {
  padding: 12px 24px;
  font-size: 1.1em;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  user-select: none;
}

.form-checkbox {
  width: 18px;
  height: 18px;
  cursor: pointer;
}

/* Responsive Design */
@media (max-width: 1024px) {
  .channels-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 768px) {
  .notification-channels {
    padding: 1rem;
    padding-top: 5rem;
  }
  
  .page-header {
    padding: 12px;
    flex-direction: column;
    gap: 12px;
    align-items: stretch;
  }
  
  .page-header h1 {
    text-align: center;
    margin-bottom: 0;
  }

  /* Mobile header: improve contrast, layout and CTA appearance */
  .page-header {
    background: linear-gradient(90deg, var(--color-accent), var(--color-accent-2));
    color: #ffffff;
    box-shadow: 0 6px 20px rgba(12,17,23,0.08);
  }

  .page-header h1,
  .page-header-inner h1 {
    color: #ffffff;
    font-weight: 700;
  }

  .page-header-inner {
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
  }

  .page-header-inner .btn {
    background: #ffffff;
    color: var(--color-accent-2);
    border: none;
    box-shadow: 0 6px 18px rgba(37,99,235,0.12);
    padding: 8px 12px;
    border-radius: 10px;
    max-width: 180px;
    width: auto;
    align-self: center;
  }
  
  .page-header .btn,
  .page-header-inner .btn {
    position: static;
    width: 100%;
    justify-content: center;
    padding: 10px 16px;
    align-self: stretch;
  }
  
  .channel-form-container {
    padding: 0.5rem;
    margin: 8px 0;
    max-width: 100%;
    width: 100%;
    border-radius: 8px;
  }
  
  .channel-form {
    padding: 1rem;
    grid-template-columns: 1fr;
    gap: 12px;
  }

  /* Improve spacing for checkbox label and form hints on mobile */
  .checkbox-label {
    gap: 12px;
    align-items: center;
  }

  .form-hint {
    margin-top: 10px;
    display: block;
    font-size: 0.95rem;
    line-height: 1.35;
  }

  .form-checkbox {
    margin-right: 6px;
    flex: 0 0 auto;
  }

  /* Ensure sidebar column content collapses cleanly under the main column */
  .channel-form > :not(.form-section) {
    width: 100%;
    max-width: 100%;
  }

  /* Make help boxes and form sections respect container width */
  .form-section,
  .telegram-help,
  .discord-help,
  .slack-help,
  .webhook-help {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    overflow-wrap: anywhere;
  }
  
  .channel-form h2 {
    font-size: 1.25rem;
  }
  
  .channels-grid {
    grid-template-columns: 1fr;
    gap: 1.5rem; /* increased vertical spacing between cards on mobile */
  }
  
  .channel-card {
    padding: 1.25rem;
  }
  
  .channel-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.75rem;
  }
  
  .form-actions {
    flex-direction: column-reverse;
    gap: 0.75rem;
  }
  
  .form-actions .btn {
    width: 100%;
  }
  
  .channel-actions {
    flex-direction: column;
    gap: 0.5rem;
  }
  .channel-actions .btn,
  .channel-actions .btn-sm {
    display: block;
    width: 100%;
    max-width: 100%;
    margin: 0;
    box-sizing: border-box;
  }
  /* keep only the first (toggle) button visible inline on narrow screens */
  .channel-actions > .btn:not(:first-child) { display: none !important; }
  
  .telegram-help,
  .discord-help,
  .slack-help,
  .webhook-help {
    padding: 1rem;
  }
  
  .telegram-help ol,
  .discord-help ol,
  .slack-help ol {
    padding-left: 1.25rem;
  }
}

@media (max-width: 480px) {
  .notification-channels {
    padding: 0.75rem;
  }
  
  .page-header {
    padding: 8px 10px;
  }
  
  .page-header h1 {
    font-size: 1.25rem;
  }

  /* Slightly smaller header CTA on very small screens and keep good contrast */
  .page-header-inner {
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
  }

  .page-header-inner .btn {
    max-width: 140px;
    padding: 7px 10px;
    font-size: 0.95rem;
  }
  
  .channel-form-container {
    padding: 0.5rem;
    margin: 6px 0;
    max-width: 100%;
    width: 100%;
    border-radius: 8px;
  }
  
  .channel-form {
    padding: 1rem;
  }

  /* Reduce inner spacing to maximize usable width on very small screens */
  .channel-form h2 { margin-bottom: 8px; }
  .form-group { margin-bottom: 10px; }

  /* Ensure all inputs/selects/textareas never overflow their container */
  .form-control,
  select.form-control,
  textarea.form-control {
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
    box-sizing: border-box !important;
    overflow-wrap: anywhere;
  }

  /* Tidy help box visuals so they don't push layout horizontally */
  .telegram-help,
  .discord-help,
  .slack-help,
  .webhook-help {
    padding: 10px;
    margin: 0;
    border-left-width: 4px;
    border-left-style: solid;
    border-left-color: rgba(37,99,235,0.18);
    background-color: rgba(37,99,235,0.04);
    overflow: hidden;
  }

  /* Extra spacing for checkbox and hint on very small screens */
  .checkbox-label {
    gap: 14px;
  }

  .form-hint {
    margin-top: 12px;
    font-size: 0.95rem;
    color: var(--color-muted);
  }

  .form-checkbox {
    margin-right: 8px;
  }

  /* Prevent the action buttons area from overflowing horizontally */
  .form-actions { padding-top: 10px; gap: 8px; }
  .form-actions .btn { width: 100%; }
  
  .channel-form h2 {
    font-size: 1.125rem;
  }
  
  .form-section h3 {
    font-size: 1rem;
  }
  
  .form-group {
    margin-bottom: 0.875rem;
  }
  
  .form-control {
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
  }
  
  .channel-card {
    padding: 1rem;
  }

  /* Mobile-specific modern card polish */
  .channel-card {
    padding: 12px;
    border-radius: 12px;
    box-shadow: 0 8px 20px rgba(3, 18, 28, 0.06);
    border: 1px solid rgba(12, 17, 23, 0.04);
    min-height: auto;
  }

  .channel-header {
    flex-direction: row;
    align-items: center;
    justify-content: flex-start;
    gap: 8px;
    margin-bottom: 8px;
  }

  .channel-type-badge {
    font-size: 0.75rem;
    padding: 6px 10px;
    border-radius: 8px;
  }

  .status-indicator {
    width: 10px;
    height: 10px;
  }

  .channel-name {
    font-size: 1.05rem;
    font-weight: 600;
    margin-bottom: 8px;
  }

  .channel-details {
    font-size: 0.88rem;
    color: #6b7280;
    min-height: 40px;
    margin-bottom: 12px;
  }

  .channel-actions {
    flex-direction: column;
    gap: 8px;
    padding: 0; /* ensure no extra padding around buttons */
  }

  .channel-actions .btn,
  .channel-actions .btn-sm {
    display: block;
    width: 100%;
    max-width: none;
    margin: 0;
    padding: 6px 8px;
    border-radius: 6px;
    height: 32px;
    box-sizing: border-box;
  }

  /* remove left margins between stacked buttons on small screens */
  .channel-actions .btn + .btn,
  .channel-actions .btn + .btn-sm,
  .channel-actions .btn-sm + .btn,
  .channel-actions .btn-sm + .btn-sm {
    margin-left: 0;
    margin-top: 8px;
  }

  /* keep only the first (toggle) button visible inline on very small screens */
  .channel-actions > .btn:not(:first-child) { display: none !important; }

  /* remove tiny right gap by expanding buttons to cover card inner padding */
  .channel-card {
    /* ensure consistent padding reference */
    padding-left: 12px;
    padding-right: 12px;
  }

  .channel-actions .btn,
  .channel-actions .btn-sm {
    width: calc(100% + 24px); /* extend full width across card inner padding */
    margin-left: -12px;
    margin-right: -12px;
  }

  /* Keep the first (toggle) button compact so it doesn't cover the card area */
  .channel-actions > .btn:first-child {
    width: auto !important;
    display: inline-flex !important;
    margin-left: 0 !important;
    margin-right: 0 !important;
    align-self: flex-end;
    padding: 6px 10px;
    box-sizing: border-box;
  }
  
  .channel-title {
    font-size: 1rem;
  }
  
  .channel-type {
    font-size: 0.7rem;
    padding: 0.25rem 0.5rem;
  }
  
  .channel-details {
    font-size: 0.8rem;
  }
  
  .btn {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
  }
  
  .btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.75rem;
  }
  
  .telegram-help h4,
  .discord-help h4,
  .slack-help h4 {
    font-size: 0.95rem;
  }
  
  .telegram-help,
  .discord-help,
  .slack-help,
  .webhook-help {
    padding: 0.875rem;
    font-size: 0.85rem;
  }
}
</style>