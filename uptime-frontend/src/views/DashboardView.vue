<template>
  <div class="dashboard">
    <div class="dashboard-header">
      <div class="header-main">
        <h1>Welcome to Uptime Monitor</h1>
        <p v-if="currentUser">Hello, {{ currentUser.name || 'User' }}!</p>
      </div>
      <div class="header-status">
        <div class="user-info" v-if="currentUser">
          <span class="user-avatar">{{ currentUser.name ? currentUser.name.charAt(0).toUpperCase() : 'U' }}</span>
          <div class="user-details">
            <span class="user-name">{{ currentUser.name || 'Unknown User' }}</span>
            <span class="user-role" :class="`role-${currentUser.role}`">{{ currentUser.role || 'user' }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'

const userInfo = ref(null)

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

onMounted(() => {
  // Load user from localStorage
  try {
    const stored = localStorage.getItem('user')
    if (stored) {
      userInfo.value = JSON.parse(stored)
    }
  } catch (e) {
    console.error('Failed to load user info:', e)
  }
})
</script>

<style scoped>
.dashboard {
  padding: 1rem;
  max-width: auto;
  margin: 0 auto;
  height: 85vh;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
}

.dashboard-header {
  margin-bottom: 0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
  width: 100%;
  max-width: 900px;
}

.header-main {
  flex: 1;
  min-width: 250px;
}
.header-main h1 {
  margin: 0 0 0.5rem 0;
  color: #2c3e50;
  font-size: clamp(1.8rem, 5vw, 2.5rem);
  font-weight: 700;
  line-height: 1.2;
}

.header-main p {
  margin: 0;
  color: #7f8c8d;
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
  gap: 0.625rem;
  background: white;
  padding: 0.5rem 1rem;
  border-radius: 2rem;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  border: 2px solid #ecf0f1;
  transition: all 0.3s ease;
}

.user-info:hover {
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  transform: translateY(-2px);
}

.user-avatar {
  width: 2.25rem;
  height: 2.25rem;
  border-radius: 50%;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: bold;
  font-size: 1rem;
  flex-shrink: 0;
}

.user-details {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-width: 0;
}

.user-name {
  font-weight: 600;
  color: #2c3e50;
  font-size: 0.95rem;
  line-height: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.user-role {
  font-size: 0.7rem;
  padding: 0.25rem 0.625rem;
  border-radius: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  line-height: 1;
  white-space: nowrap;
}

.user-role.role-admin {
  background: #e74c3c;
  color: white;
}

.user-role.role-user {
  background: #3498db;
  color: white;
}

.user-role.role-moderator {
  background: #f39c12;
  color: white;
}

/* Tablet and smaller */
@media (max-width: 768px) {
  .dashboard {
    padding: 1rem;
    padding-top: 5rem;
    position: relative;
    left: auto;
    right: auto;
    top: auto;
    bottom: auto;
  }
  
  .dashboard-header {
    flex-direction: column;
    align-items: stretch;
    gap: 1rem;
    margin-bottom: 0;
  }
  
  .header-main h1 {
    margin-bottom: 0.375rem;
  }
  
  .header-status {
    justify-content: center;
    gap: 0.625rem;
  }
  
  .user-info {
    padding: 0.5rem 1rem;
  }
  
  .user-avatar {
    width: 2rem;
    height: 2rem;
    font-size: 0.95rem;
  }
  
  .user-name {
    font-size: 0.85rem;
  }
  
  .user-role {
    font-size: 0.65rem;
    padding: 0.2rem 0.5rem;
  }
}

/* Mobile */
@media (max-width: 480px) {
  .dashboard {
    padding: 0.75rem;
    padding-top: 4.5rem;
    position: relative;
    left: auto;
    right: auto;
    top: auto;
    bottom: auto;
  }
  
  .dashboard-header {
    gap: 0.75rem;
    margin-bottom: 0;
  }
  
  .header-main h1 {
    margin-bottom: 0.25rem;
  }
  
  .header-status {
    width: 100%;
  }
  
  .user-info {
    width: 100%;
    justify-content: center;
    padding: 0.5rem 0.875rem;
  }
  
  .user-avatar {
    width: 2rem;
    height: 2rem;
    font-size: 0.9rem;
  }
}
</style>