<script setup>
import { RouterView } from 'vue-router'
import { useAuthStore } from './stores/auth'
import { onMounted, ref } from 'vue'
import Navbar from './components/Navbar.vue'

const authStore = useAuthStore()
const sidebarCollapsed = ref(false)

onMounted(() => {
  // Check if user is logged in on app start
  if (localStorage.getItem('token')) {
    authStore.checkAuth()
  }
  
  // Listen for sidebar toggle events
  document.addEventListener('sidebar-toggled', (event) => {
    sidebarCollapsed.value = event.detail.isCollapsed
  })
})
</script>

<template>
  <div id="app">
    <Navbar v-if="authStore.isAuthenticated" />
    <main class="main-content" :class="{ 
      'with-navbar': authStore.isAuthenticated,
      'with-navbar-collapsed': authStore.isAuthenticated && sidebarCollapsed 
    }">
      <RouterView />
    </main>
  </div>
</template>

<style>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  background-color: #f5f5f5;
  color: #333;
  overflow-x: hidden;
}

#app {
  min-height: 100vh;
  overflow-x: hidden;
}

.main-content {
  min-height: 100vh;
}

.main-content.with-navbar {
  margin-left: 280px;
  transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.main-content.with-navbar-collapsed {
  margin-left: 70px;
}

/* Mobile responsive */
@media (max-width: 768px) {
  .main-content.with-navbar,
  .main-content.with-navbar-collapsed {
    margin-left: 0;
    padding-top: 0;
  }
}

.card {
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  padding: 20px;
  margin-bottom: 20px;
}

.btn {
  padding: 10px 20px;
  border: none;
  border-radius: 5px;
  cursor: pointer;
  text-decoration: none;
  display: inline-block;
  font-size: 14px;
  transition: background-color 0.3s;
}

.btn-primary {
  background-color: #007bff;
  color: white;
}

.btn-primary:hover {
  background-color: #0056b3;
}

.btn-success {
  background-color: #28a745;
  color: white;
}

.btn-success:hover {
  background-color: #1e7e34;
}

.btn-danger {
  background-color: #dc3545;
  color: white;
}

.btn-danger:hover {
  background-color: #c82333;
}

.btn-warning {
  background-color: #ffc107;
  color: #212529;
}

.btn-warning:hover {
  background-color: #e0a800;
}

.form-group {
  margin-bottom: 15px;
}

.form-label {
  display: block;
  margin-bottom: 5px;
  font-weight: 500;
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
  border-color: #007bff;
  box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
}

.status-up {
  color: #28a745;
  font-weight: bold;
}

.status-down {
  color: #dc3545;
  font-weight: bold;
}

.status-unknown {
  color: #6c757d;
  font-weight: bold;
}

.loading {
  text-align: center;
  padding: 40px;
  color: #6c757d;
}

.error {
  background-color: #f8d7da;
  color: #721c24;
  padding: 10px;
  border-radius: 4px;
  margin-bottom: 15px;
}

.success {
  background-color: #d4edda;
  color: #155724;
  padding: 10px;
  border-radius: 4px;
  margin-bottom: 15px;
}
</style>
