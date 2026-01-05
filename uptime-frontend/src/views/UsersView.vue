<template>
  <div class="users-management">
    <div class="page-header">
      <h1>👥 User Management</h1>
      <p class="subtitle">Manage users and their roles</p>
      <button class="btn btn-primary" @click="showAddUserModal">
        ➕ Add New User
      </button>
    </div>

    <div v-if="loading" class="loading">Loading users...</div>

    <div v-else class="users-content">
      <div class="users-table-container">
        <table class="users-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Role</th>
              <th>Created At</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in users" :key="user.id">
              <td>{{ user.name }}</td>
              <td>{{ user.email }}</td>
              <td>
                <span class="role-badge" :class="user.role">
                  {{ user.role === 'admin' ? '🔑 Admin' : '👤 User' }}
                </span>
              </td>
              <td>{{ formatDate(user.created_at) }}</td>
              <td class="actions">
                <button class="btn btn-small btn-edit" @click="editUser(user)">
                  ✏️ Edit
                </button>
                <button 
                  class="btn btn-small btn-delete" 
                  @click="confirmDeleteUser(user)"
                  :disabled="user.id === currentUserId"
                >
                  🗑️ Delete
                </button>
              </td>
            </tr>
            <tr v-if="users.length === 0">
              <td colspan="5" class="no-data">No users found</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Add/Edit User Modal -->
    <div v-if="showModal" class="modal-overlay" @click="closeModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h2>{{ isEditing ? '✏️ Edit User' : '➕ Add New User' }}</h2>
          <button class="close-btn" @click="closeModal">✕</button>
        </div>

        <form @submit.prevent="saveUser" class="user-form">
          <div class="form-group">
            <label for="name">Name *</label>
            <input 
              type="text" 
              id="name" 
              v-model="formData.name" 
              required
              placeholder="Enter user name"
            >
          </div>

          <div class="form-group">
            <label for="email">Email *</label>
            <input 
              type="email" 
              id="email" 
              v-model="formData.email" 
              required
              placeholder="Enter email address"
            >
          </div>

          <div class="form-group">
            <label for="password">
              Password {{ isEditing ? '(leave blank to keep current)' : '*' }}
            </label>
            <input 
              type="password" 
              id="password" 
              v-model="formData.password"
              :required="!isEditing"
              :placeholder="isEditing ? 'Leave blank to keep current password' : 'Enter password (min. 6 characters)'"
              minlength="6"
            >
          </div>

          <div class="form-group">
            <label for="role">Role *</label>
            <select id="role" v-model="formData.role" required>
              <option value="">Select role...</option>
              <option value="user">👤 User</option>
              <option value="admin">🔑 Admin</option>
            </select>
          </div>

          <div v-if="error" class="error-message">{{ error }}</div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="closeModal">
              Cancel
            </button>
            <button type="submit" class="btn btn-success" :disabled="saving">
              {{ saving ? 'Saving...' : (isEditing ? 'Update User' : 'Create User') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '../services/api'

const loading = ref(true)
const saving = ref(false)
const showModal = ref(false)
const isEditing = ref(false)
const error = ref(null)
const users = ref([])

const currentUserId = computed(() => {
  const user = JSON.parse(localStorage.getItem('user') || '{}')
  return user.id
})

const formData = ref({
  id: null,
  name: '',
  email: '',
  password: '',
  role: ''
})

onMounted(async () => {
  await loadUsers()
})

async function loadUsers() {
  loading.value = true
  try {
    const response = await api.users.getAll()
    if (response.data && response.data.success) {
      users.value = response.data.data
    }
  } catch (err) {
    console.error('Failed to load users:', err)
    alert('Failed to load users: ' + (err.response?.data?.message || err.message))
  } finally {
    loading.value = false
  }
}

function showAddUserModal() {
  isEditing.value = false
  formData.value = {
    id: null,
    name: '',
    email: '',
    password: '',
    role: ''
  }
  error.value = null
  showModal.value = true
}

function editUser(user) {
  isEditing.value = true
  formData.value = {
    id: user.id,
    name: user.name,
    email: user.email,
    password: '',
    role: user.role
  }
  error.value = null
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  formData.value = {
    id: null,
    name: '',
    email: '',
    password: '',
    role: ''
  }
  error.value = null
}

async function saveUser() {
  saving.value = true
  error.value = null

  try {
    const data = {
      name: formData.value.name,
      email: formData.value.email,
      role: formData.value.role
    }

    // Only include password if it's set
    if (formData.value.password) {
      data.password = formData.value.password
    }

    if (isEditing.value) {
      await api.users.update(formData.value.id, data)
      alert('User updated successfully!')
    } else {
      await api.users.create(data)
      alert('User created successfully!')
    }

    closeModal()
    await loadUsers()
  } catch (err) {
    console.error('Failed to save user:', err)
    error.value = err.response?.data?.message || err.message
    if (err.response?.data?.errors) {
      const errors = Object.values(err.response.data.errors).flat()
      error.value = errors.join(', ')
    }
  } finally {
    saving.value = false
  }
}

async function confirmDeleteUser(user) {
  if (confirm(`Are you sure you want to delete user "${user.name}"? This action cannot be undone.`)) {
    await deleteUser(user.id)
  }
}

async function deleteUser(id) {
  try {
    await api.users.delete(id)
    alert('User deleted successfully!')
    await loadUsers()
  } catch (err) {
    console.error('Failed to delete user:', err)
    alert('Failed to delete user: ' + (err.response?.data?.message || err.message))
  }
}

function formatDate(dateString) {
  if (!dateString) return '-'
  const date = new Date(dateString)
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}
</script>

<style scoped>
.users-management {
  max-width: 1400px;
  margin: 0 auto;
  padding: 24px;
}

.page-header {
  margin-bottom: 32px;
  padding: 24px 28px;
  background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  border: 1px solid #dee2e6;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
}

.page-header > div {
  flex: 1;
  min-width: 200px;
}

.page-header .btn-primary {
  flex: 0 0 auto;
  white-space: nowrap;
}

.page-header h1 {
  margin: 0 0 4px 0;
  color: #2c3e50;
  font-size: 28px;
  font-weight: 700;
}

.subtitle {
  color: #6c757d;
  font-size: 14px;
  margin: 0;
  font-weight: 400;
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

.users-table-container {
  background: white;
  border-radius: 16px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  overflow: hidden;
}

.users-table {
  width: 100%;
  border-collapse: collapse;
}

.users-table thead {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.users-table th {
  padding: 16px 24px;
  text-align: left;
  font-weight: 700;
  font-size: 15px;
}

.users-table td {
  padding: 16px 24px;
  border-bottom: 1px solid #e8eaed;
  color: #2c3e50;
}

.users-table tbody tr:hover {
  background: linear-gradient(135deg, #f8f9ff 0%, #fff 100%);
}

.users-table tbody tr:last-child td {
  border-bottom: none;
}

.role-badge {
  display: inline-block;
  padding: 6px 16px;
  border-radius: 20px;
  font-size: 14px;
  font-weight: 600;
}

.role-badge.admin {
  background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
  color: #7c5a00;
  box-shadow: 0 2px 8px rgba(255, 215, 0, 0.3);
}

.role-badge.user {
  background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
  color: #0d47a1;
  box-shadow: 0 2px 8px rgba(33, 150, 243, 0.2);
}

.actions {
  display: flex;
  gap: 10px;
}

.no-data {
  text-align: center;
  padding: 40px !important;
  color: #999;
  font-style: italic;
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
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-primary {
  background: linear-gradient(135deg, #66eab1 0%, #81a24b 100%);
  color: white;
  width: auto;
  max-width: fit-content;
}

.btn-primary:hover:not(:disabled) {
  background: linear-gradient(135deg, #5568d3 0%, #6a3f8f 100%);
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}

.btn-success {
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
}

.btn-small {
  padding: 8px 16px;
  font-size: 13px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.btn-edit {
  background: linear-gradient(135deg, #42a5f5 0%, #1976d2 100%);
  color: white;
}

.btn-edit:hover:not(:disabled) {
  background: linear-gradient(135deg, #1e88e5 0%, #1565c0 100%);
  transform: translateY(-2px);
}

.btn-delete {
  background: linear-gradient(135deg, #ef5350 0%, #e53935 100%);
  color: white;
}

.btn-delete:hover:not(:disabled) {
  background: linear-gradient(135deg, #e53935 0%, #c62828 100%);
  transform: translateY(-2px);
}

/* Modal */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  backdrop-filter: blur(4px);
}

.modal-content {
  background: white;
  border-radius: 16px;
  max-width: 500px;
  width: 90%;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.modal-header {
  padding: 24px 32px;
  border-bottom: 2px solid #e8eaed;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
}

.modal-header h2 {
  margin: 0;
  color: #2c3e50;
  font-size: 24px;
  font-weight: 700;
}

.close-btn {
  background: none;
  border: none;
  font-size: 28px;
  color: #999;
  cursor: pointer;
  padding: 0;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  transition: all 0.2s ease;
}

.close-btn:hover {
  background: #f5f5f5;
  color: #333;
}

.user-form {
  padding: 32px;
}

.form-group {
  margin-bottom: 24px;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  color: #2c3e50;
  font-weight: 600;
  font-size: 14px;
}

.form-group input,
.form-group select {
  width: 100%;
  padding: 12px 16px;
  border: 2px solid #e8eaed;
  border-radius: 10px;
  font-size: 15px;
  transition: all 0.3s ease;
  background: white;
  color: #2c3e50;
  box-sizing: border-box;
}

.form-group input:focus,
.form-group select:focus {
  outline: none;
  border-color: #667eea;
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.error-message {
  margin: 16px 0;
  padding: 12px 16px;
  background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
  color: #c62828;
  border: 2px solid #ef5350;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
}

.modal-footer {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
  margin-top: 24px;
}

@media (max-width: 768px) {
  .page-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
  }

  .users-table-container {
    overflow-x: auto;
  }

  .users-table {
    min-width: 600px;
  }

  .actions {
    flex-direction: column;
    gap: 6px;
  }

  .btn-small {
    width: 100%;
  }
}
</style>
