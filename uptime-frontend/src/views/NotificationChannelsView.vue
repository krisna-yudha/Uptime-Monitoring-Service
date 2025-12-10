<template>
  <div class="notification-channels">
    <div class="page-header">
      <h1>Notification Channels</h1>
      <button @click="showCreateForm = true" class="btn btn-primary">
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
            @click="toggleChannel(channel.id)" 
            :class="channel.is_enabled ? 'btn btn-warning btn-sm' : 'btn btn-success btn-sm'"
          >
            {{ channel.is_enabled ? 'Disable' : 'Enable' }}
          </button>
          <button @click="editChannel(channel)" class="btn btn-primary btn-sm">
            Edit
          </button>
          <button 
            @click="testChannelById(channel.id)" 
            :disabled="!channel.is_enabled"
            class="btn btn-info btn-sm"
          >
            Test
          </button>
          <button @click="deleteChannel(channel.id)" class="btn btn-danger btn-sm">
            Delete
          </button>
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
</script>

<style scoped>
.notification-channels {
  padding: 20px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
}

.page-header h1 {
  margin: 0;
  color: #2c3e50;
}

.channel-form-container {
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  margin-bottom: 30px;
}

.channel-form {
  padding: 30px;
}

.channel-form h2 {
  margin: 0 0 30px 0;
  color: #2c3e50;
}

.form-section {
  margin: 30px 0;
  padding: 20px;
  background-color: #f8f9fa;
  border-radius: 6px;
}

.form-section h3 {
  margin: 0 0 20px 0;
  color: #34495e;
  font-size: 1.1em;
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

.telegram-help, .discord-help, .slack-help {
  margin-top: 20px;
  padding: 15px;
  background-color: #e8f4f8;
  border-left: 4px solid #3498db;
  border-radius: 4px;
}

.telegram-help h4, .discord-help h4, .slack-help h4 {
  margin: 0 0 10px 0;
  color: #2980b9;
  font-size: 0.9em;
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
  gap: 15px;
  padding-top: 30px;
  border-top: 1px solid #ecf0f1;
  margin-top: 30px;
}

.channels-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: 20px;
}

.channel-card {
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  padding: 20px;
  border: 2px solid transparent;
  transition: all 0.2s ease;
}

.channel-card:hover {
  border-color: #3498db;
  transform: translateY(-2px);
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
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 15px;
}

.channel-type-badge {
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 0.7em;
  font-weight: bold;
  text-transform: uppercase;
}

.channel-type-badge.type-telegram {
  background-color: #e3f2fd;
  color: #1976d2;
}

.channel-type-badge.type-discord {
  background-color: #ede7f6;
  color: #5e35b1;
}

.channel-type-badge.type-slack {
  background-color: #e8f5e8;
  color: #388e3c;
}

.channel-type-badge.type-webhook {
  background-color: #fff3e0;
  color: #f57c00;
}

.status-indicator {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background-color: #bdc3c7;
  display: block;
}

.status-indicator.status-enabled {
  background-color: #27ae60;
  box-shadow: 0 0 8px rgba(39, 174, 96, 0.6);
}

.status-indicator.status-disabled {
  background-color: #e74c3c;
}

.channel-name {
  margin: 0 0 15px 0;
  color: #2c3e50;
  font-size: 1.1em;
  display: flex;
  align-items: center;
  gap: 10px;
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
  margin-bottom: 20px;
  font-size: 0.9em;
  color: #7f8c8d;
  line-height: 1.5;
}

.channel-details strong {
  color: #2c3e50;
}

.channel-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
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
    padding: 1.25rem;
    flex-direction: column;
    gap: 1rem;
    align-items: stretch;
  }
  
  .page-header h1 {
    text-align: center;
    margin-bottom: 0;
  }
  
  .page-header .btn {
    width: 100%;
    justify-content: center;
  }
  
  .channel-form-container {
    padding: 1rem;
  }
  
  .channel-form {
    padding: 1.25rem;
  }
  
  .channel-form h2 {
    font-size: 1.25rem;
  }
  
  .channels-grid {
    grid-template-columns: 1fr;
    gap: 1rem;
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
  
  .channel-actions .btn {
    width: 100%;
  }
  
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
    padding: 1rem;
  }
  
  .page-header h1 {
    font-size: 1.5rem;
  }
  
  .channel-form-container {
    padding: 0.75rem;
  }
  
  .channel-form {
    padding: 1rem;
  }
  
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