<template>
  <div class="settings">
    <div class="page-header">
      <h1>⚙️ Settings</h1>
      <p class="subtitle">Configure data aggregation and retention policies</p>
    </div>

    <div v-if="loading" class="loading">Loading settings...</div>
    
    <div v-else class="settings-content">
      <!-- Current Configuration Summary -->
      <div class="settings-section info-section">
        <div class="section-header">
          <h2>📋 Current Configuration</h2>
          <p>Overview of active aggregation and retention policies</p>
        </div>

        <div class="settings-card">
          <div class="info-grid">
            <!-- Aggregation Status -->
            <div class="info-card">
              <div class="info-header">
                <div class="info-icon">📊</div>
                <h3>Aggregation Status</h3>
              </div>
              <div class="info-content">
                <div class="info-row">
                  <span class="info-label">Auto Aggregation:</span>
                  <span class="info-value" :class="settings.autoAggregate ? 'enabled' : 'disabled'">
                    {{ settings.autoAggregate ? '✓ Enabled' : '✗ Disabled' }}
                  </span>
                </div>
                <div class="info-row" v-if="settings.autoAggregate">
                  <span class="info-label">Active Intervals:</span>
                  <div class="interval-badges">
                    <span v-if="settings.intervals.minute" class="interval-badge minute">Minute</span>
                    <span v-if="settings.intervals.hour" class="interval-badge hour">Hour</span>
                    <span v-if="settings.intervals.day" class="interval-badge day">Day</span>
                    <span v-if="!settings.intervals.minute && !settings.intervals.hour && !settings.intervals.day" class="interval-badge none">None</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Retention Policy Summary -->
            <div class="info-card">
              <div class="info-header">
                <div class="info-icon">🗄️</div>
                <h3>Retention Policy</h3>
              </div>
              <div class="info-content">
                <div class="info-row">
                  <span class="info-label">Raw Checks:</span>
                  <span class="info-value retention">{{ formatRetention(settings.retention.rawChecks, settings.retention.rawChecksUnit) }}</span>
                </div>
                <div class="info-row">
                  <span class="info-label">Raw Logs:</span>
                  <span class="info-value retention">{{ formatRetention(settings.retention.rawLogs, settings.retention.rawLogsUnit) }}</span>
                </div>
                <div class="info-row">
                  <span class="info-label">Minute Aggregates:</span>
                  <span class="info-value retention">{{ formatRetention(settings.retention.minuteAggregates, settings.retention.minuteAggregatesUnit) }}</span>
                </div>
                <div class="info-row">
                  <span class="info-label">Hour Aggregates:</span>
                  <span class="info-value retention">{{ formatRetention(settings.retention.hourAggregates, settings.retention.hourAggregatesUnit) }}</span>
                </div>
                <div class="info-row">
                  <span class="info-label">Day Aggregates:</span>
                  <span class="info-value retention">{{ formatRetention(settings.retention.dayAggregates, settings.retention.dayAggregatesUnit) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Aggregation Settings -->
      <div class="settings-section">
        <div class="section-header">
          <h2>📊 Data Aggregation</h2>
          <p>Configure how monitoring data is aggregated over time</p>
        </div>

        <div class="settings-card">
          <div class="setting-item">
            <div class="setting-label">
              <label for="auto-aggregate">Auto Aggregation</label>
              <p class="setting-description">Automatically aggregate data based on scheduler</p>
            </div>
            <div class="setting-control">
              <label class="switch">
                <input 
                  type="checkbox" 
                  id="auto-aggregate" 
                  v-model="settings.autoAggregate"
                  @change="settingsChanged = true"
                >
                <span class="slider"></span>
              </label>
              <span class="status-text">{{ settings.autoAggregate ? 'Enabled' : 'Disabled' }}</span>
            </div>
          </div>

          <div class="setting-item" :class="{ disabled: !settings.autoAggregate }">
            <div class="setting-label">
              <label>Aggregation Intervals</label>
              <p class="setting-description">Select which aggregation levels to run</p>
            </div>
            <div class="setting-control checkbox-group">
              <label class="checkbox-label">
                <input 
                  type="checkbox" 
                  v-model="settings.intervals.minute" 
                  :disabled="!settings.autoAggregate"
                  @change="settingsChanged = true"
                >
                <span>Minute (Every 1 minute)</span>
              </label>
              <label class="checkbox-label">
                <input 
                  type="checkbox" 
                  v-model="settings.intervals.hour" 
                  :disabled="!settings.autoAggregate"
                  @change="settingsChanged = true"
                >
                <span>Hour (Every hour)</span>
              </label>
              <label class="checkbox-label">
                <input 
                  type="checkbox" 
                  v-model="settings.intervals.day" 
                  :disabled="!settings.autoAggregate"
                  @change="settingsChanged = true"
                >
                <span>Day (Daily at 01:00 AM)</span>
              </label>
            </div>
          </div>
        </div>
      </div>

      <!-- Retention Settings -->
      <div class="settings-section">
        <div class="section-header">
          <h2>🗄️ Data Retention</h2>
          <p>Configure how long data is kept before being deleted</p>
        </div>

        <div class="settings-card">
          <div class="retention-grid">
            <div class="retention-item">
              <div class="retention-header">
                <div class="retention-icon">🔴</div>
                <div class="retention-title">
                  <h3>Raw Checks</h3>
                  <p>Individual monitoring checks (every 1-10 seconds)</p>
                </div>
              </div>
              <div class="retention-control">
                <input 
                  type="number" 
                  v-model.number="settings.retention.rawChecks" 
                  min="1" 
                  max="365"
                  @input="settingsChanged = true"
                >
                <select v-model="settings.retention.rawChecksUnit" @change="settingsChanged = true">
                  <option value="days">Days</option>
                  <option value="weeks">Weeks</option>
                  <option value="months">Months</option>
                </select>
              </div>
              <div class="retention-info">
                Current: {{ formatRetention(settings.retention.rawChecks, settings.retention.rawChecksUnit) }}
              </div>
            </div>

            <div class="retention-item">
              <div class="retention-header">
                <div class="retention-icon">🟡</div>
                <div class="retention-title">
                  <h3>Raw Logs</h3>
                  <p>Monitoring activity logs</p>
                </div>
              </div>
              <div class="retention-control">
                <input 
                  type="number" 
                  v-model.number="settings.retention.rawLogs" 
                  min="1" 
                  max="365"
                  @input="settingsChanged = true"
                >
                <select v-model="settings.retention.rawLogsUnit" @change="settingsChanged = true">
                  <option value="days">Days</option>
                  <option value="weeks">Weeks</option>
                  <option value="months">Months</option>
                </select>
              </div>
              <div class="retention-info">
                Current: {{ formatRetention(settings.retention.rawLogs, settings.retention.rawLogsUnit) }}
              </div>
            </div>

            <div class="retention-item">
              <div class="retention-header">
                <div class="retention-icon">🔵</div>
                <div class="retention-title">
                  <h3>Minute Aggregates</h3>
                  <p>Data aggregated per minute</p>
                </div>
              </div>
              <div class="retention-control">
                <input 
                  type="number" 
                  v-model.number="settings.retention.minuteAggregates" 
                  min="1" 
                  max="365"
                  @input="settingsChanged = true"
                >
                <select v-model="settings.retention.minuteAggregatesUnit" @change="settingsChanged = true">
                  <option value="days">Days</option>
                  <option value="weeks">Weeks</option>
                  <option value="months">Months</option>
                </select>
              </div>
              <div class="retention-info">
                Current: {{ formatRetention(settings.retention.minuteAggregates, settings.retention.minuteAggregatesUnit) }}
              </div>
            </div>

            <div class="retention-item">
              <div class="retention-header">
                <div class="retention-icon">🟢</div>
                <div class="retention-title">
                  <h3>Hour Aggregates</h3>
                  <p>Data aggregated per hour</p>
                </div>
              </div>
              <div class="retention-control">
                <input 
                  type="number" 
                  v-model.number="settings.retention.hourAggregates" 
                  min="1" 
                  max="365"
                  @input="settingsChanged = true"
                >
                <select v-model="settings.retention.hourAggregatesUnit" @change="settingsChanged = true">
                  <option value="days">Days</option>
                  <option value="weeks">Weeks</option>
                  <option value="months">Months</option>
                </select>
              </div>
              <div class="retention-info">
                Current: {{ formatRetention(settings.retention.hourAggregates, settings.retention.hourAggregatesUnit) }}
              </div>
            </div>

            <div class="retention-item">
              <div class="retention-header">
                <div class="retention-icon">🟣</div>
                <div class="retention-title">
                  <h3>Day Aggregates</h3>
                  <p>Data aggregated per day</p>
                </div>
              </div>
              <div class="retention-control">
                <input 
                  type="number" 
                  v-model.number="settings.retention.dayAggregates" 
                  min="1" 
                  max="3650"
                  @input="settingsChanged = true"
                >
                <select v-model="settings.retention.dayAggregatesUnit" @change="settingsChanged = true">
                  <option value="days">Days</option>
                  <option value="months">Months</option>
                  <option value="years">Years</option>
                </select>
              </div>
              <div class="retention-info">
                Current: {{ formatRetention(settings.retention.dayAggregates, settings.retention.dayAggregatesUnit) }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Manual Actions -->
      <div class="settings-section">
        <div class="section-header">
          <h2>🔧 Manual Actions</h2>
          <p>Trigger aggregation and cleanup manually</p>
        </div>

        <div class="settings-card">
          <div class="actions-grid">
            <div class="action-card">
              <h3>Run Aggregation</h3>
              <p>Manually trigger data aggregation for a specific interval</p>
              <div class="action-controls">
                <select v-model="manualAggregation.interval" class="form-control">
                  <option value="minute">Per Minute</option>
                  <option value="hour">Per Hour</option>
                  <option value="day">Per Day</option>
                </select>
                <input 
                  type="date" 
                  v-model="manualAggregation.date" 
                  class="form-control"
                  :max="today"
                >
                <button 
                  class="btn btn-primary" 
                  @click="runManualAggregation"
                  :disabled="aggregating"
                >
                  {{ aggregating ? 'Running...' : 'Run Aggregation' }}
                </button>
              </div>
              <div v-if="aggregationResult" class="action-result" :class="aggregationResult.success ? 'success' : 'error'">
                {{ aggregationResult.message }}
              </div>
            </div>

            <div class="action-card">
              <h3>Run Cleanup</h3>
              <p>Delete old data according to retention policy</p>
              <div class="action-controls">
                <label class="checkbox-label">
                  <input type="checkbox" v-model="manualCleanup.dryRun">
                  <span>Dry Run (preview only, don't delete)</span>
                </label>
                <button 
                  class="btn btn-danger" 
                  @click="runManualCleanup"
                  :disabled="cleaning"
                >
                  {{ cleaning ? 'Running...' : (manualCleanup.dryRun ? 'Preview Cleanup' : 'Run Cleanup') }}
                </button>
              </div>
              <div v-if="cleanupResult" class="action-result" :class="cleanupResult.success ? 'success' : 'error'">
                <pre>{{ cleanupResult.message }}</pre>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Save Button -->
      <div class="settings-footer">
        <button 
          class="btn btn-success btn-large" 
          @click="saveSettings"
          :disabled="!settingsChanged || saving"
        >
          {{ saving ? 'Saving...' : 'Save Settings' }}
        </button>
        <button 
          class="btn btn-secondary btn-large" 
          @click="resetSettings"
          :disabled="!settingsChanged"
        >
          Reset
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '../services/api'

const loading = ref(true)
const saving = ref(false)
const settingsChanged = ref(false)
const aggregating = ref(false)
const cleaning = ref(false)
const aggregationResult = ref(null)
const cleanupResult = ref(null)

const today = computed(() => new Date().toISOString().split('T')[0])

const settings = ref({
  autoAggregate: true,
  intervals: {
    minute: true,
    hour: true,
    day: true
  },
  retention: {
    rawChecks: 7,
    rawChecksUnit: 'days',
    rawLogs: 30,
    rawLogsUnit: 'days',
    minuteAggregates: 30,
    minuteAggregatesUnit: 'days',
    hourAggregates: 90,
    hourAggregatesUnit: 'days',
    dayAggregates: 1,
    dayAggregatesUnit: 'years'
  }
})

const originalSettings = ref(null)

const manualAggregation = ref({
  interval: 'minute',
  date: new Date().toISOString().split('T')[0]
})

const manualCleanup = ref({
  dryRun: true
})

onMounted(async () => {
  await loadSettings()
})

async function loadSettings() {
  loading.value = true
  try {
    const response = await api.settings.get()
    if (response.data && response.data.success) {
      settings.value = { ...settings.value, ...response.data.data }
      originalSettings.value = JSON.parse(JSON.stringify(settings.value))
    }
  } catch (err) {
    console.error('Failed to load settings:', err)
    // Use default settings
    originalSettings.value = JSON.parse(JSON.stringify(settings.value))
  } finally {
    loading.value = false
  }
}

async function saveSettings() {
  saving.value = true
  try {
    const response = await api.settings.save(settings.value)
    if (response.data && response.data.success) {
      alert('Settings saved successfully!')
      originalSettings.value = JSON.parse(JSON.stringify(settings.value))
      settingsChanged.value = false
    }
  } catch (err) {
    console.error('Failed to save settings:', err)
    alert('Failed to save settings: ' + (err.response?.data?.message || err.message))
  } finally {
    saving.value = false
  }
}

function resetSettings() {
  settings.value = JSON.parse(JSON.stringify(originalSettings.value))
  settingsChanged.value = false
}

async function runManualAggregation() {
  aggregating.value = true
  aggregationResult.value = null
  
  try {
    const response = await api.settings.runAggregation({
      interval: manualAggregation.value.interval,
      date: manualAggregation.value.date
    })
    
    if (response.data && response.data.success) {
      aggregationResult.value = {
        success: true,
        message: `✓ Aggregation completed successfully! ${response.data.data?.total || 0} periods aggregated.`
      }
    }
  } catch (err) {
    console.error('Aggregation failed:', err)
    aggregationResult.value = {
      success: false,
      message: `✗ Aggregation failed: ${err.response?.data?.message || err.message}`
    }
  } finally {
    aggregating.value = false
  }
}

async function runManualCleanup() {
  cleaning.value = true
  cleanupResult.value = null
  
  try {
    const response = await api.settings.runCleanup({
      dryRun: manualCleanup.value.dryRun
    })
    
    if (response.data && response.data.success) {
      const summary = response.data.data?.summary || 'Cleanup completed'
      cleanupResult.value = {
        success: true,
        message: manualCleanup.value.dryRun 
          ? `Preview Results:\n${summary}` 
          : `✓ Cleanup completed!\n${summary}`
      }
    }
  } catch (err) {
    console.error('Cleanup failed:', err)
    cleanupResult.value = {
      success: false,
      message: `✗ Cleanup failed: ${err.response?.data?.message || err.message}`
    }
  } finally {
    cleaning.value = false
  }
}

function formatRetention(value, unit) {
  if (!value) return '0 days'
  if (value === 1) {
    return `1 ${unit.slice(0, -1)}` // Remove 's' for singular
  }
  return `${value} ${unit}`
}
</script>

<style scoped>
.settings {
  max-width: 1400px;
  margin: 0 auto;
  padding: 24px;
  background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
  min-height: calc(100vh - 48px);
  border-radius: 12px;
}

.page-header {
  margin-bottom: 32px;
  padding: 32px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 16px;
  box-shadow: 0 8px 24px rgba(102, 126, 234, 0.25);
  color: white;
}

.page-header h1 {
  margin: 0 0 12px 0;
  color: white;
  font-size: 36px;
  font-weight: 700;
  text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.subtitle {
  color: rgba(255, 255, 255, 0.95);
  font-size: 16px;
  margin: 0;
  font-weight: 400;
}

.loading {
  text-align: center;
  padding: 60px;
  font-size: 18px;
  color: #666;
}

.settings-content {
  display: flex;
  flex-direction: column;
  gap: 32px;
}

.settings-section {
  background: white;
  border-radius: 16px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  overflow: hidden;
}

.section-header {
  padding: 28px 32px;
  border-bottom: 3px solid transparent;
  background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
  position: relative;
  overflow: hidden;
}

.section-header::before {
  content: '';
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: linear-gradient(90deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
}

.section-header h2 {
  margin: 0 0 10px 0;
  color: #2c3e50;
  font-size: 26px;
  font-weight: 700;
}

.section-header p {
  margin: 0;
  color: #6c757d;
  font-size: 15px;
}

.settings-card {
  padding: 32px;
  background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
}

.setting-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 24px;
  border-bottom: 1px solid #e8eaed;
  background: white;
  border-radius: 12px;
  margin-bottom: 16px;
}

.setting-item:hover {
  background: linear-gradient(135deg, #f8f9ff 0%, #fff 100%);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.1);
}

.setting-item:last-child {
  border-bottom: none;
  margin-bottom: 0;
}

.setting-item.disabled {
  opacity: 0.5;
  pointer-events: none;
  background: #f5f5f5;
}

.setting-label label {
  font-size: 17px;
  font-weight: 700;
  color: #2c3e50;
  display: block;
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.setting-label label::before {
  content: '▸';
  color: #667eea;
  font-size: 14px;
}

.setting-description {
  font-size: 14px;
  color: #6c757d;
  margin: 0;
  line-height: 1.6;
}

.setting-control {
  display: flex;
  align-items: center;
  gap: 12px;
}

.status-text {
  font-size: 15px;
  font-weight: 600;
  min-width: 80px;
  padding: 6px 16px;
  border-radius: 20px;
  text-align: center;
  background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
  color: #2e7d32;
  box-shadow: 0 2px 8px rgba(46, 125, 50, 0.15);
}

/* Toggle Switch */
.switch {
  position: relative;
  display: inline-block;
  width: 60px;
  height: 32px;
}

.switch input {
  opacity: 0;
  width: 0;
  height: 0;
}

.slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: linear-gradient(135deg, #e0e0e0 0%, #bdbdbd 100%);
  transition: 0.4s;
  border-radius: 32px;
  box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
}

.slider:before {
  position: absolute;
  content: "";
  height: 24px;
  width: 24px;
  left: 4px;
  bottom: 4px;
  background: white;
  transition: 0.4s;
  border-radius: 50%;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

input:checked + .slider {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

input:checked + .slider:before {
  transform: translateX(28px);
}

input:focus + .slider {
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
}

/* Checkbox Group */
.checkbox-group {
  flex-direction: column;
  align-items: flex-start;
  gap: 14px;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 15px;
  color: #2c3e50;
  cursor: pointer;
  padding: 10px 16px;
  border-radius: 8px;
  transition: all 0.2s ease;
  background: white;
  border: 2px solid #e8eaed;
}

.checkbox-label:hover {
  background: linear-gradient(135deg, #f0f4ff 0%, #ffffff 100%);
  border-color: #667eea;
}

.checkbox-label input[type="checkbox"] {
  width: 20px;
  height: 20px;
  cursor: pointer;
  accent-color: #667eea;
}

/* Retention Grid */
.retention-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
  gap: 24px;
}

.retention-item {
  background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
  border-radius: 16px;
  padding: 24px;
  border: 3px solid #e8eaed;
  position: relative;
  overflow: hidden;
}

.retention-item::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: linear-gradient(90deg, var(--item-color, #667eea) 0%, var(--item-color-light, #764ba2) 100%);
}

.retention-item:hover {
  border-color: var(--item-color, #667eea);
  box-shadow: 0 8px 24px rgba(102, 126, 234, 0.15);
}

.retention-item:nth-child(1) {
  --item-color: #ef5350;
  --item-color-light: #e57373;
}

.retention-item:nth-child(2) {
  --item-color: #ffa726;
  --item-color-light: #ffb74d;
}

.retention-item:nth-child(3) {
  --item-color: #42a5f5;
  --item-color-light: #64b5f6;
}

.retention-item:nth-child(4) {
  --item-color: #66bb6a;
  --item-color-light: #81c784;
}

.retention-item:nth-child(5) {
  --item-color: #ab47bc;
  --item-color-light: #ba68c8;
}

.retention-header {
  display: flex;
  gap: 16px;
  margin-bottom: 20px;
  align-items: center;
}

.retention-icon {
  font-size: 36px;
  line-height: 1;
  filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
}

.retention-title h3 {
  margin: 0 0 6px 0;
  font-size: 18px;
  color: #2c3e50;
  font-weight: 700;
}

.retention-title p {
  margin: 0;
  font-size: 13px;
  color: #6c757d;
  line-height: 1.5;
}

.retention-control {
  display: flex;
  gap: 10px;
  margin-bottom: 16px;
}

.retention-control input[type="number"] {
  flex: 1;
  padding: 12px 16px;
  border: 2px solid #e8eaed;
  border-radius: 10px;
  font-size: 15px;
  font-weight: 600;
  transition: all 0.3s ease;
  background: white;
  color: #2c3e50;
}

.retention-control input[type="number"]:focus {
  outline: none;
  border-color: var(--item-color, #667eea);
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.retention-control select {
  flex: 1;
  padding: 12px 16px;
  border: 2px solid #e8eaed;
  border-radius: 10px;
  font-size: 15px;
  font-weight: 600;
  background: white;
  cursor: pointer;
  transition: all 0.3s ease;
  color: #2c3e50;
}

.retention-control select:focus {
  outline: none;
  border-color: var(--item-color, #667eea);
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.retention-info {
  font-size: 14px;
  color: white;
  font-weight: 700;
  padding: 12px 16px;
  background: linear-gradient(135deg, var(--item-color, #667eea) 0%, var(--item-color-light, #764ba2) 100%);
  border-radius: 10px;
  text-align: center;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

/* Actions Grid */
.actions-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(420px, 1fr));
  gap: 28px;
}

.action-card {
  background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
  border-radius: 16px;
  padding: 28px;
  border: 3px solid #e8eaed;
  position: relative;
  overflow: hidden;
}

.action-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 4px;
  background: linear-gradient(90deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
}

.action-card:hover {
  border-color: #667eea;
  box-shadow: 0 8px 24px rgba(102, 126, 234, 0.15);
}

.action-card h3 {
  margin: 0 0 10px 0;
  font-size: 20px;
  color: #2c3e50;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 10px;
}

.action-card h3::before {
  content: '⚡';
  font-size: 24px;
}

.action-card p {
  margin: 0 0 24px 0;
  font-size: 14px;
  color: #6c757d;
  line-height: 1.6;
}

.action-controls {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.form-control {
  padding: 12px 16px;
  border: 2px solid #e8eaed;
  border-radius: 10px;
  font-size: 15px;
  transition: all 0.3s ease;
  background: white;
  color: #2c3e50;
}

.form-control:focus {
  outline: none;
  border-color: #667eea;
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.action-result {
  margin-top: 20px;
  padding: 16px 20px;
  border-radius: 12px;
  font-size: 14px;
  font-weight: 600;
  animation: slideIn 0.3s ease;
}

@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.action-result.success {
  background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
  color: #2e7d32;
  border: 2px solid #66bb6a;
  box-shadow: 0 4px 12px rgba(46, 125, 50, 0.15);
}

.action-result.success::before {
  content: '✓ ';
  font-size: 18px;
  margin-right: 8px;
}

.action-result.error {
  background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
  color: #c62828;
  border: 2px solid #ef5350;
  box-shadow: 0 4px 12px rgba(198, 40, 40, 0.15);
}

.action-result.error::before {
  content: '✗ ';
  font-size: 18px;
  margin-right: 8px;
}

.action-result pre {
  margin: 8px 0 0 0;
  white-space: pre-wrap;
  font-family: 'Courier New', monospace;
  font-size: 13px;
  opacity: 0.9;
}

/* Buttons */
.btn {
  padding: 12px 24px;
  border: none;
  border-radius: 10px;
  font-size: 15px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  position: relative;
  overflow: hidden;
}

.btn::before {
  content: '';
  position: absolute;
  top: 50%;
  left: 50%;
  width: 0;
  height: 0;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.3);
  transform: translate(-50%, -50%);
  transition: width 0.6s, height 0.6s;
}

.btn:hover::before {
  width: 300px;
  height: 300px;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  box-shadow: none;
}

.btn-primary {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background: linear-gradient(135deg, #5568d3 0%, #6a3f8f 100%);
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}

.btn-success {
  text-align: center;
  background: linear-gradient(135deg, #66bb6a 0%, #43a047 100%);
  color: white;
}

.btn-success:hover:not(:disabled) {
  background: linear-gradient(135deg, #57ab5a 0%, #388e3c 100%);
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(67, 160, 71, 0.4);
}

.btn-secondary {
  background: linear-gradient(135deg, #b0bec5 0%, #90a4ae 100%);
  color: white;
}

.btn-secondary:hover:not(:disabled) {
  background: linear-gradient(135deg, #9aa8b0 0%, #78909c 100%);
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(144, 164, 174, 0.4);
}

.btn-danger {
  background: linear-gradient(135deg, #ef5350 0%, #e53935 100%);
  color: white;
}

.btn-danger:hover:not(:disabled) {
  background: linear-gradient(135deg, #e53935 0%, #c62828 100%);
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(229, 57, 53, 0.4);
}

.btn-large {
  padding: 16px 40px;
  font-size: 17px;
  border-radius: 12px;
}

/* Footer */
.settings-footer {
  display: flex;
  gap: 20px;
  justify-content: flex-end;
  padding: 28px 32px;
  background: white;
  border-radius: 16px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  border-top: 4px solid transparent;
  background-image: 
    linear-gradient(white, white),
    linear-gradient(90deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
  background-origin: padding-box, border-box;
  background-clip: padding-box, border-box;
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

.loading::after {
  content: '...';
  animation: dots 1.5s steps(4, end) infinite;
}

@keyframes dots {
  0%, 20% { content: '.'; }
  40% { content: '..'; }
  60%, 100% { content: '...'; }
}

/* Info Section Styles */
.info-section {
  background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
  border: 3px solid #2196f3;
}

.info-section .section-header {
  background: linear-gradient(135deg, #2196f3 0%, #1976d2 100%);
  color: white;
}

.info-section .section-header::before {
  background: linear-gradient(90deg, #64b5f6 0%, #42a5f5 50%, #2196f3 100%);
}

.info-section .section-header h2,
.info-section .section-header p {
  color: white;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
  gap: 24px;
}

.info-card {
  background: white;
  border-radius: 16px;
  padding: 24px;
  border: 3px solid #e3f2fd;
  box-shadow: 0 4px 16px rgba(33, 150, 243, 0.1);
}

.info-card:hover {
  border-color: #2196f3;
  box-shadow: 0 8px 24px rgba(33, 150, 243, 0.2);
}

.info-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 20px;
  padding-bottom: 16px;
  border-bottom: 2px solid #e3f2fd;
}

.info-icon {
  font-size: 32px;
  filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
}

.info-header h3 {
  margin: 0;
  font-size: 20px;
  color: #1565c0;
  font-weight: 700;
}

.info-content {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.info-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
  border-radius: 10px;
  border: 2px solid #e8eaed;
  transition: all 0.3s ease;
}

.info-row:hover {
  border-color: #2196f3;
  background: linear-gradient(135deg, #e3f2fd 0%, #ffffff 100%);
}

.info-label {
  font-size: 14px;
  font-weight: 600;
  color: #546e7a;
}

.info-value {
  font-size: 15px;
  font-weight: 700;
  color: #2c3e50;
  padding: 6px 14px;
  border-radius: 8px;
  background: white;
  border: 2px solid #e8eaed;
}

.info-value.enabled {
  background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
  color: #2e7d32;
  border-color: #66bb6a;
}

.info-value.disabled {
  background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
  color: #c62828;
  border-color: #ef5350;
}

.info-value.retention {
  background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
  color: #1565c0;
  border-color: #2196f3;
}

.interval-badges {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.interval-badge {
  padding: 6px 14px;
  border-radius: 20px;
  font-size: 13px;
  font-weight: 700;
  border: 2px solid;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.interval-badge.minute {
  background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
  color: #1565c0;
  border-color: #2196f3;
}

.interval-badge.hour {
  background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
  color: #2e7d32;
  border-color: #66bb6a;
}

.interval-badge.day {
  background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%);
  color: #6a1b9a;
  border-color: #ab47bc;
}

.interval-badge.none {
  background: linear-gradient(135deg, #fafafa 0%, #e0e0e0 100%);
  color: #757575;
  border-color: #bdbdbd;
}

@media (max-width: 768px) {
  .settings {
    padding: 16px;
  }
  
  .page-header {
    padding: 24px;
  }
  
  .page-header h1 {
    font-size: 28px;
  }
  
  .retention-grid,
  .actions-grid,
  .info-grid {
    grid-template-columns: 1fr;
  }
  
  .settings-footer {
    flex-direction: column;
  }
  
  .btn {
    text-align: center;
  }
  
  .btn-large {
    width: 100%;
  }
  
  .setting-item {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
  }
  
  .setting-control {
    width: 100%;
  }
  
  .info-row {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }
  
  .info-value {
    width: 100%;
    text-align: center;
  }
}
</style>
