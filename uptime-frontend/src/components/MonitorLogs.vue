<template>
  <div class="monitor-logs">
    <!-- Header -->
    <div class="logs-header">
      <h2 class="text-xl font-semibold text-gray-800 mb-4">
        Monitor Logs
        <span v-if="currentMonitor" class="text-sm text-gray-600">
          - {{ currentMonitor.name }}
        </span>
      </h2>
      
      <!-- Controls -->
      <div class="flex flex-wrap gap-4 mb-6">
        <!-- Monitor Selection -->
        <div class="flex-1 min-w-48">
          <label class="block text-sm font-medium text-gray-700 mb-1">Monitor</label>
          <select 
            v-model="selectedMonitorId" 
            @change="onMonitorChange"
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="">All Monitors</option>
            <option v-for="monitor in monitors" :key="monitor.id" :value="monitor.id">
              {{ monitor.name }} ({{ monitor.type }})
            </option>
          </select>
        </div>

        <!-- Time Period -->
        <div class="flex-1 min-w-40">
          <label class="block text-sm font-medium text-gray-700 mb-1">Time Period</label>
          <select 
            v-model="selectedHours" 
            @change="fetchLogs"
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="1">Last 1 hour</option>
            <option value="6">Last 6 hours</option>
            <option value="24">Last 24 hours</option>
            <option value="72">Last 3 days</option>
            <option value="168">Last 7 days</option>
          </select>
        </div>

        <!-- Event Type Filter -->
        <div class="flex-1 min-w-40">
          <label class="block text-sm font-medium text-gray-700 mb-1">Event Type</label>
          <select 
            v-model="selectedEventType" 
            @change="fetchLogs"
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="">All Events</option>
            <option v-for="eventType in availableEventTypes" :key="eventType" :value="eventType">
              {{ formatEventType(eventType) }}
            </option>
          </select>
        </div>

        <!-- Status Filter -->
        <div class="flex-1 min-w-32">
          <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
          <select 
            v-model="selectedStatus" 
            @change="fetchLogs"
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="">All Status</option>
            <option v-for="status in availableStatuses" :key="status" :value="status">
              {{ status }}
            </option>
          </select>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-end gap-2">
          <button 
            @click="fetchLogs" 
            :disabled="loading"
            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <span v-if="loading" class="flex items-center">
              <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              Loading...
            </span>
            <span v-else>Refresh</span>
          </button>

          <button 
            v-if="selectedMonitorId"
            @click="exportLogs" 
            :disabled="loading || logs.length === 0"
            class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Export JSON
          </button>
        </div>
      </div>

      <!-- Stats (only for specific monitor) -->
      <div v-if="selectedMonitorId && stats" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-blue-50 p-4 rounded-lg">
          <div class="text-sm text-blue-600 font-medium">Total Logs</div>
          <div class="text-xl font-bold text-blue-900">{{ stats.total_logs }}</div>
        </div>
        <div class="bg-green-50 p-4 rounded-lg">
          <div class="text-sm text-green-600 font-medium">Avg Response</div>
          <div class="text-xl font-bold text-green-900">
            {{ stats.avg_response_time ? Math.round(stats.avg_response_time) + 'ms' : 'N/A' }}
          </div>
        </div>
        <div class="bg-yellow-50 p-4 rounded-lg">
          <div class="text-sm text-yellow-600 font-medium">Checks</div>
          <div class="text-xl font-bold text-yellow-900">{{ stats.by_event_type?.check_complete || 0 }}</div>
        </div>
        <div class="bg-red-50 p-4 rounded-lg">
          <div class="text-sm text-red-600 font-medium">Errors</div>
          <div class="text-xl font-bold text-red-900">{{ stats.error_count }}</div>
        </div>
      </div>
    </div>

    <!-- Logs List -->
    <div class="logs-content">
      <!-- Loading State -->
      <div v-if="loading" class="text-center py-8">
        <div class="inline-flex items-center">
          <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          Loading logs...
        </div>
      </div>

      <!-- Empty State -->
      <div v-else-if="logs.length === 0" class="text-center py-12">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        <h3 class="mt-2 text-sm font-medium text-gray-900">No logs found</h3>
        <p class="mt-1 text-sm text-gray-500">No logs match your current filters.</p>
      </div>

      <!-- Logs Table -->
      <div v-else class="bg-white shadow-sm rounded-lg border">
        <div class="px-4 py-3 border-b border-gray-200">
          <h3 class="text-lg font-medium">
            Log Entries 
            <span class="text-sm text-gray-500">({{ logs.length }} of {{ pagination?.total || logs.length }})</span>
          </h3>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Monitor</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Event</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Response</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="log in logs" :key="log.id" class="hover:bg-gray-50">
                <td class="px-4 py-3 text-sm text-gray-900">
                  <div class="font-medium">{{ formatTime(log.logged_at) }}</div>
                  <div class="text-xs text-gray-500">{{ formatDate(log.logged_at) }}</div>
                </td>
                <td class="px-4 py-3 text-sm">
                  <div v-if="log.monitor" class="font-medium text-gray-900">{{ log.monitor.name }}</div>
                  <div v-if="log.monitor" class="text-xs text-gray-500">{{ log.monitor.type }}</div>
                  <div v-else class="text-gray-400">Unknown Monitor</div>
                </td>
                <td class="px-4 py-3 text-sm">
                  <span :class="getEventTypeClass(log.event_type)" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                    {{ formatEventType(log.event_type) }}
                  </span>
                  <div v-if="log.severity" class="text-xs mt-1" :class="getSeverityClass(log.severity)">
                    {{ log.severity }}
                  </div>
                </td>
                <td class="px-4 py-3 text-sm">
                  <span v-if="log.status" :class="getStatusClass(log.status)" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                    {{ log.status }}
                  </span>
                  <span v-else class="text-gray-400">-</span>
                </td>
                <td class="px-4 py-3 text-sm">
                  <div v-if="log.response_time" class="font-medium">{{ Math.round(log.response_time) }}ms</div>
                  <div v-else class="text-gray-400">-</div>
                </td>
                <td class="px-4 py-3 text-sm">
                  <button 
                    @click="showLogDetails(log)" 
                    class="text-blue-600 hover:text-blue-800 text-xs font-medium"
                  >
                    View Details
                  </button>
                  <div v-if="log.error_message" class="text-red-600 text-xs mt-1 truncate max-w-xs" :title="log.error_message">
                    {{ log.error_message }}
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="pagination && pagination.last_page > 1" class="px-4 py-3 border-t border-gray-200 bg-gray-50">
          <div class="flex items-center justify-between">
            <div class="text-sm text-gray-700">
              Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }} entries
            </div>
            <div class="flex items-center space-x-2">
              <button 
                @click="changePage(pagination.current_page - 1)"
                :disabled="pagination.current_page <= 1"
                class="px-3 py-1 text-sm border border-gray-300 rounded hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Previous
              </button>
              <span class="px-3 py-1 text-sm">
                Page {{ pagination.current_page }} of {{ pagination.last_page }}
              </span>
              <button 
                @click="changePage(pagination.current_page + 1)"
                :disabled="pagination.current_page >= pagination.last_page"
                class="px-3 py-1 text-sm border border-gray-300 rounded hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Next
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Log Detail Modal -->
    <div v-if="selectedLog" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50" @click="selectedLog = null">
      <div class="bg-white rounded-lg max-w-4xl w-full max-h-[90vh] overflow-hidden" @click.stop>
        <div class="px-6 py-4 border-b border-gray-200">
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-medium text-gray-900">Log Details</h3>
            <button @click="selectedLog = null" class="text-gray-400 hover:text-gray-600">
              <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>

        <div class="p-6 overflow-y-auto max-h-[calc(90vh-140px)]">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Basic Info -->
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">Timestamp</label>
                <div class="mt-1 text-sm text-gray-900">{{ formatFullTime(selectedLog.logged_at) }}</div>
              </div>
              
              <div v-if="selectedLog.monitor">
                <label class="block text-sm font-medium text-gray-700">Monitor</label>
                <div class="mt-1 text-sm text-gray-900">{{ selectedLog.monitor.name }}</div>
                <div class="text-xs text-gray-500">{{ selectedLog.monitor.target }}</div>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700">Event Type</label>
                <div class="mt-1">
                  <span :class="getEventTypeClass(selectedLog.event_type)" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                    {{ formatEventType(selectedLog.event_type) }}
                  </span>
                </div>
              </div>

              <div v-if="selectedLog.status">
                <label class="block text-sm font-medium text-gray-700">Status</label>
                <div class="mt-1">
                  <span :class="getStatusClass(selectedLog.status)" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                    {{ selectedLog.status }}
                  </span>
                </div>
              </div>

              <div v-if="selectedLog.response_time">
                <label class="block text-sm font-medium text-gray-700">Response Time</label>
                <div class="mt-1 text-sm text-gray-900">{{ Math.round(selectedLog.response_time) }}ms</div>
              </div>

              <div v-if="selectedLog.severity">
                <label class="block text-sm font-medium text-gray-700">Severity</label>
                <div class="mt-1">
                  <span :class="getSeverityClass(selectedLog.severity)" class="text-sm font-medium">
                    {{ selectedLog.severity }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Error Message -->
            <div v-if="selectedLog.error_message" class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700">Error Message</label>
              <div class="mt-1 p-3 bg-red-50 border border-red-200 rounded-md">
                <div class="text-sm text-red-800 font-mono">{{ selectedLog.error_message }}</div>
              </div>
            </div>

            <!-- Log Data -->
            <div v-if="selectedLog.formatted_log_data" class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-2">Log Data</label>
              <div class="bg-gray-50 border border-gray-200 rounded-md p-4 overflow-x-auto">
                <pre class="text-sm text-gray-800 font-mono">{{ JSON.stringify(selectedLog.formatted_log_data, null, 2) }}</pre>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useMonitorStore } from '@/stores/monitor'

export default {
  name: 'MonitorLogs',
  props: {
    monitorId: {
      type: [String, Number],
      default: null
    }
  },
  setup(props) {
    const monitorStore = useMonitorStore()
    
    // Reactive state
    const loading = ref(false)
    const logs = ref([])
    const pagination = ref(null)
    const stats = ref(null)
    const selectedLog = ref(null)
    
    // Filters
    const selectedMonitorId = ref(props.monitorId || '')
    const selectedHours = ref(24)
    const selectedEventType = ref('')
    const selectedStatus = ref('')
    const currentPage = ref(1)
    
    // Available filter options
    const availableEventTypes = ref([])
    const availableStatuses = ref([])
    
    // Computed
    const monitors = computed(() => monitorStore.monitors)
    const currentMonitor = computed(() => {
      if (!selectedMonitorId.value) return null
      return monitors.value.find(m => m.id == selectedMonitorId.value)
    })

    // Methods
    const fetchLogs = async () => {
      if (loading.value) return
      
      loading.value = true
      try {
        let url = '/api/logs/recent'
        const params = new URLSearchParams({
          page: currentPage.value,
          per_page: 50,
          hours: selectedHours.value
        })

        // Add filters
        if (selectedMonitorId.value) {
          url = `/api/logs/monitor/${selectedMonitorId.value}`
        }
        if (selectedEventType.value) {
          params.append('event_type', selectedEventType.value)
        }
        if (selectedStatus.value) {
          params.append('status', selectedStatus.value)
        }
        if (selectedMonitorId.value && !props.monitorId) {
          params.append('monitor_id', selectedMonitorId.value)
        }

        const response = await fetch(`${url}?${params}`)
        const data = await response.json()

        if (data.success) {
          if (selectedMonitorId.value && data.data.logs) {
            // Single monitor response format
            logs.value = data.data.logs
            pagination.value = data.data.pagination
          } else {
            // Multi-monitor response format
            logs.value = data.data.data || data.data
            pagination.value = {
              current_page: data.data.current_page,
              last_page: data.data.last_page,
              per_page: data.data.per_page,
              total: data.data.total,
              from: data.data.from,
              to: data.data.to
            }
          }
        }
      } catch (error) {
        console.error('Error fetching logs:', error)
      } finally {
        loading.value = false
      }
    }

    const fetchStats = async () => {
      if (!selectedMonitorId.value) {
        stats.value = null
        return
      }

      try {
        const response = await fetch(`/api/logs/monitor/${selectedMonitorId.value}/stats?hours=${selectedHours.value}`)
        const data = await response.json()
        
        if (data.success) {
          stats.value = data.data.stats
        }
      } catch (error) {
        console.error('Error fetching stats:', error)
      }
    }

    const fetchFilters = async () => {
      try {
        const response = await fetch('/api/logs/filters')
        const data = await response.json()
        
        if (data.success) {
          availableEventTypes.value = data.data.event_types
          availableStatuses.value = data.data.statuses
        }
      } catch (error) {
        console.error('Error fetching filters:', error)
      }
    }

    const onMonitorChange = () => {
      currentPage.value = 1
      fetchLogs()
      fetchStats()
    }

    const changePage = (page) => {
      if (page < 1 || (pagination.value && page > pagination.value.last_page)) return
      currentPage.value = page
      fetchLogs()
    }

    const showLogDetails = (log) => {
      selectedLog.value = log
    }

    const exportLogs = async () => {
      if (!selectedMonitorId.value) return

      try {
        const params = new URLSearchParams({
          hours: selectedHours.value
        })
        
        if (selectedEventType.value) {
          params.append('event_type', selectedEventType.value)
        }
        if (selectedStatus.value) {
          params.append('status', selectedStatus.value)
        }

        const response = await fetch(`/api/logs/monitor/${selectedMonitorId.value}/export?${params}`)
        const blob = await response.blob()
        
        // Create download
        const url = window.URL.createObjectURL(blob)
        const a = document.createElement('a')
        a.href = url
        a.download = `monitor_${selectedMonitorId.value}_logs_${new Date().toISOString().split('T')[0]}.json`
        document.body.appendChild(a)
        a.click()
        window.URL.revokeObjectURL(url)
        document.body.removeChild(a)
      } catch (error) {
        console.error('Error exporting logs:', error)
      }
    }

    // Formatting methods
    const formatTime = (timestamp) => {
      return new Date(timestamp).toLocaleTimeString()
    }

    const formatDate = (timestamp) => {
      return new Date(timestamp).toLocaleDateString()
    }

    const formatFullTime = (timestamp) => {
      return new Date(timestamp).toLocaleString()
    }

    const formatEventType = (eventType) => {
      return eventType.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
    }

    const getEventTypeClass = (eventType) => {
      const classes = {
        'check_start': 'bg-blue-100 text-blue-800',
        'check_complete': 'bg-green-100 text-green-800',
        'check_failed': 'bg-red-100 text-red-800',
        'check_skipped': 'bg-yellow-100 text-yellow-800',
        'status_change': 'bg-purple-100 text-purple-800'
      }
      return classes[eventType] || 'bg-gray-100 text-gray-800'
    }

    const getStatusClass = (status) => {
      const classes = {
        'up': 'bg-green-100 text-green-800',
        'down': 'bg-red-100 text-red-800',
        'pending': 'bg-yellow-100 text-yellow-800'
      }
      return classes[status] || 'bg-gray-100 text-gray-800'
    }

    const getSeverityClass = (severity) => {
      const classes = {
        'low': 'text-green-600',
        'medium': 'text-yellow-600',
        'high': 'text-orange-600',
        'critical': 'text-red-600'
      }
      return classes[severity] || 'text-gray-600'
    }

    // Watchers
    watch(() => selectedHours.value, () => {
      fetchStats()
    })

    watch(() => props.monitorId, (newId) => {
      if (newId) {
        selectedMonitorId.value = newId
        onMonitorChange()
      }
    }, { immediate: true })

    // Lifecycle
    onMounted(async () => {
      await monitorStore.fetchMonitors()
      await fetchFilters()
      await fetchLogs()
      await fetchStats()
    })

    return {
      // State
      loading,
      logs,
      pagination,
      stats,
      selectedLog,
      selectedMonitorId,
      selectedHours,
      selectedEventType,
      selectedStatus,
      availableEventTypes,
      availableStatuses,
      monitors,
      currentMonitor,
      
      // Methods
      fetchLogs,
      onMonitorChange,
      changePage,
      showLogDetails,
      exportLogs,
      formatTime,
      formatDate,
      formatFullTime,
      formatEventType,
      getEventTypeClass,
      getStatusClass,
      getSeverityClass
    }
  }
}
</script>

<style scoped>
.monitor-logs {
  max-width: 80rem;
  margin-left: auto;
  margin-right: auto;
  padding-left: 1rem;
  padding-right: 1rem;
  padding-top: 1.5rem;
  padding-bottom: 1.5rem;
}

@media (min-width: 640px) {
  .monitor-logs {
    padding-left: 1.5rem;
    padding-right: 1.5rem;
  }
}

@media (min-width: 1024px) {
  .monitor-logs {
    padding-left: 2rem;
    padding-right: 2rem;
  }
}

.logs-header {
  margin-bottom: 1.5rem;
}

.logs-content {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

/* Custom scrollbar for modal */
.overflow-y-auto::-webkit-scrollbar {
  width: 6px;
}

.overflow-y-auto::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 3px;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
  background: #c1c1c1;
  border-radius: 3px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
  background: #a8a8a8;
}
</style>