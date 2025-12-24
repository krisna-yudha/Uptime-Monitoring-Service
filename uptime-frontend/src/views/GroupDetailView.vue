<template>
  <div class="group-detail">
    <div class="page-header">
      <div class="header-content">
        <div class="header-main">
          <h1>Group: {{ displayName }}</h1>
          <p v-if="groupDescription">{{ groupDescription }}</p>
        </div>
        <div class="header-actions">
          <button @click="$router.back()" class="btn btn-secondary">← Back</button>
          <!-- <button @click="refresh" class="btn btn-primary">Refresh</button> -->
        </div>
      </div>
    </div>

    <div v-if="loading" class="loading">Loading group monitors...</div>
    <div v-else-if="error" class="error">{{ error }}</div>

    <div v-else>
      <div class="group-stats">
        <div class="stat">Total: {{ monitors.length }}</div>
        <div class="stat">Online: {{ upCount }}</div>
        <div class="stat">Offline: {{ downCount }}</div>
      </div>

      <div class="monitors-list">
        <div v-for="m in monitors" :key="m.id" :class="['monitor-item','clickable', `status-${m.last_status || 'unknown'}`]" @click="openMonitor(m.id)">
          <div class="left">
            <h3>{{ m.name }}</h3>
            <p class="target">{{ m.target }}</p>
          </div>
          <div class="right">
            <span :class="['status-badge', m.last_status]">{{ (m.last_status||'unknown').toUpperCase() }}</span>
          </div>
        </div>
      </div>

      <div v-if="!monitors.length" class="empty-state">No monitors in this group.</div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useMonitorStore } from '../stores/monitors'

const route = useRoute()
const router = useRouter()
const monitorStore = useMonitorStore()

const rawName = route.params.name || route.params.group || ''
const groupName = decodeURIComponent(rawName)
const displayName = groupName || 'Ungrouped'

const monitors = ref([])
const loading = ref(false)
const error = ref(null)
const groupDescription = ref('')

const upCount = computed(() => monitors.value.filter(m => m.last_status === 'up').length)
const downCount = computed(() => monitors.value.filter(m => m.last_status === 'down').length)

async function loadGroup() {
  loading.value = true
  error.value = null
  try {
    // Try to fetch monitors filtered by group via store
    const params = {}
    if (displayName !== 'Ungrouped') {
      params.group = displayName
    } else {
      params.group = 'ungrouped'
    }
    const resp = await monitorStore.fetchMonitors(params)
    monitors.value = monitorStore.monitors.filter(m => {
      if (displayName === 'Ungrouped') return !m.group_name
      return m.group_name === displayName
    })

    // If store exposes grouped data, try to get description
    try {
      const gresp = await monitorStore.getGroupedMonitors({})
      const groups = gresp.data || {}
      if (groups[displayName]) {
        groupDescription.value = groups[displayName].description || ''
      }
    } catch (e) {
      // ignore
    }
  } catch (e) {
    error.value = 'Failed to load group monitors.'
    console.error(e)
  } finally {
    loading.value = false
  }
}

function openMonitor(id) {
  router.push(`/monitors/${id}`)
}

function refresh() {
  loadGroup()
}

onMounted(() => {
  loadGroup()
})
</script>

<style scoped>
:root{
  /* Modern high-contrast palette (simple & readable) */
  --bg: #f3f4f6;           /* subtle light gray background */
  --card: #ffffff;         /* white card */
  --muted: #374151;        /* dark gray for secondary text */
  --accent: #2563eb;       /* vibrant blue (primary) */
  --accent-2: #1d4ed8;     /* deeper blue for gradients */
  --success: #059669;      /* strong green */
  --danger: #dc2626;       /* strong red */
}

.group-detail {
  padding: clamp(16px, 2vw, 32px);
  background: linear-gradient(180deg, #f8fafc, var(--bg));
  min-height: calc(100vh - 64px);
  box-sizing: border-box;
}

.page-header .header-content {
  max-width: 1100px;
  margin: 0 auto;
  padding: clamp(12px, 2vw, 20px);
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
}

/* Add top spacing and stacking context to prevent overlap with fixed navbar */
.page-header {
  position: relative;
  z-index: 5;
  margin-top: 12px; /* small offset on desktop */
}

@media (max-width: 1024px) {
  .page-header { margin-top: 24px; }
}

@media (max-width: 768px) {
  /* Account for fixed mobile navbar height (approx 56-72px) */
  .page-header { margin-top: 72px; }
  .page-header .header-content { padding-top: 8px; }
}

@media (max-width: 480px) {
  .page-header { margin-top: 64px; }
  .page-header .header-content { padding-top: 6px; }
}

.header-main h1 {
  margin: 0;
  font-size: clamp(1.25rem, 2.2vw, 2rem);
  color: var(--accent);
  font-weight: 800;
  letter-spacing: -0.2px;
}

.header-main p {
  margin: 4px 0 0;
  color: var(--muted);
  font-size: clamp(.9rem, 1.5vw, 1rem);
}

.header-actions {
  display: flex;
  gap: 10px;
}

.btn {
  padding: 0.6rem 0.9rem;
  border-radius: 10px;
  border: none;
  background: var(--card);
  box-shadow: 0 6px 18px rgba(15,23,42,0.06);
  cursor: pointer;
  font-weight: 600;
}

.btn-primary {
  background: linear-gradient(90deg, var(--accent), var(--accent-2));
  color: #fff;
  box-shadow: 0 8px 28px rgba(37,99,235,0.12);
}

.btn-secondary {
  background: transparent;
  border: 1px solid rgba(37,99,235,0.08);
  color: var(--accent-2);
}

/* Make header back button visually prominent and black without affecting other secondary buttons */
.header-actions .btn-secondary {
  background: #000000;
  color: #ffffff;
  border: none;
  box-shadow: 0 6px 18px rgba(2,6,23,0.12);
}
.header-actions .btn-secondary:hover {
  filter: brightness(0.95);
}

.group-stats {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  margin: 18px 0;
}

.group-stats .stat {
  background: var(--card);
  padding: 12px 16px;
  border-radius: 12px;
  box-shadow: 0 8px 20px rgba(15,23,42,0.04);
  min-width: 120px;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 6px;
}

.group-stats .stat:first-child { font-weight:700; color: var(--accent-2) }
.stat { color: var(--muted); font-weight:600 }

.monitors-list {
  margin-top: 12px;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 12px;
}

.monitor-item {
  position: relative;
  background: linear-gradient(180deg, var(--card), #fbfdff);
  padding: 14px 14px 14px 18px; /* leave space for left accent */
  border-radius: 12px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border: 1px solid rgba(15,23,42,0.04);
  transition: transform .18s ease, box-shadow .18s ease;
  overflow: hidden;
}

/* Colored left accent bar indicating status */
.monitor-item::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 6px;
  border-radius: 12px 0 0 12px;
  background: transparent;
  transition: background 180ms ease, opacity 180ms ease;
}

.monitor-item.status-up::before {
  background: linear-gradient(180deg, rgba(5,150,105,0.20), rgba(5,150,105,0.08));
}

.monitor-item.status-down::before {
  background: linear-gradient(180deg, rgba(220,38,38,0.18), rgba(220,38,38,0.06));
}

.monitor-item.status-unknown::before {
  background: linear-gradient(180deg, rgba(37,99,235,0.12), rgba(37,99,235,0.04));
}

.monitor-item:hover { transform: translateY(-6px); box-shadow: 0 18px 50px rgba(15,23,42,0.08); }

.left h3 { margin: 0; font-size: 1.05rem; color: #0f172a }
.target { margin: 6px 0 0; color: var(--muted); font-size: .9rem; word-break: break-all; opacity: .9 }

.status-badge { padding: 6px 12px; border-radius: 999px; font-weight: 700; font-size: .85rem }
.status-badge.up { background: rgba(5,150,105,0.12); color: var(--success); border: 1px solid rgba(5,150,105,0.18) }
.status-badge.down { background: rgba(220,38,38,0.08); color: var(--danger); border: 1px solid rgba(220,38,38,0.14) }
.clickable { cursor: pointer }

.empty-state { padding: 28px; text-align: center; color: var(--muted); background: transparent; border-radius: 10px }

@media (max-width: 768px) {
  .page-header .header-content { flex-direction: column; align-items: flex-start; gap: 12px }
  .header-actions { width: 100%; display: flex; gap: 8px }
  .btn { flex: 1; border-radius: 10px }
  .monitors-list { grid-template-columns: 1fr }
  .left h3 { font-size: 1rem }
  .target { font-size: .85rem }
  .group-stats { justify-content: space-between }
}

</style>
