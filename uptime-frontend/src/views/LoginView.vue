<template>
  <div class="login-container">
    <div class="background-overlay"></div>
    <div class="login-content">
      <div class="login-card">
        <div class="login-header">
          <div class="logo-section">
            <div class="logo-container">
              <div class="logo-icon">
                <i class="fas fa-chart-line"></i>
              </div>
              <div class="logo-dots">
                <span class="dot dot-1"></span>
                <span class="dot dot-2"></span>
                <span class="dot dot-3"></span>
              </div>
            </div>
            <div class="title-container">
              <h1>Uptime Monitor</h1>
              <div class="subtitle-wrapper">
                <div class="subtitle-line"></div>
                <p>Real-time Service Monitoring</p>
                <div class="subtitle-line"></div>
              </div>
            </div>
          </div>
        </div>
        
        <div class="login-body">
          <div v-if="authStore.error" class="error-message">
            <i class="fas fa-exclamation-triangle"></i>
            {{ authStore.error }}
          </div>
          
          <form @submit.prevent="handleLogin" class="login-form">
            <div class="form-group">
              <label for="email" class="form-label">
                <i class="fas fa-envelope"></i>
                Email
              </label>
              <div class="input-wrapper">
                <input
                  id="email"
                  v-model="form.email"
                  type="email"
                  class="form-control"
                  required
                  placeholder="Enter your email"
                >
              </div>
            </div>
            
            <div class="form-group">
              <label for="password" class="form-label">
                <i class="fas fa-lock"></i>
                Password
              </label>
              <div class="input-wrapper">
                    <input
                      id="password"
                      v-model="form.password"
                      :type="showPassword ? 'text' : 'password'"
                      class="form-control"
                      required
                      placeholder="Enter your password"
                    >
                    <button
                      type="button"
                      class="password-toggle"
                      @click="togglePassword"
                      :aria-pressed="showPassword"
                      :title="showPassword ? 'Hide password' : 'Show password'"
                    >
                      <i :class="showPassword ? 'fas fa-eye-slash' : 'fas fa-eye'"></i>
                    </button>
              </div>
            </div>
            
            <button
              type="submit"
              class="btn btn-primary btn-full"
              :disabled="authStore.loading"
            >
              <div class="btn-content">
                <i v-if="authStore.loading" class="fas fa-spinner fa-spin"></i>
                <i v-else class="fas fa-sign-in-alt"></i>
                <span v-if="authStore.loading">Signing in...</span>
                <span v-else>Sign In</span>
              </div>
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useAuthStore } from '../stores/auth'
import { useRouter } from 'vue-router'

const authStore = useAuthStore()
const router = useRouter()

const form = ref({
  email: '',
  password: ''
})

const showPassword = ref(false)

function togglePassword() {
  showPassword.value = !showPassword.value
}

async function handleLogin() {
  const result = await authStore.login(form.value)
  
  if (result.success) {
    router.push('/dashboard')
  }
}

// Quick login functions for testing
function quickLogin(email, password) {
  form.value.email = email
  form.value.password = password
}
</script>

<style scoped>
/* Main container dengan gradient background yang konsisten */

html, body, #app {
  height: 100%;
  width: 100%;
  margin: 0;
  padding: 0;
}

.login-container {
  min-height: 100vh;
  min-width: 100vw;
  height: 100vh;
  width: 100vw;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 0;
  overflow: hidden;
}

/* Background overlay untuk efek depth */
.background-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: 
    radial-gradient(circle at 20% 80%, rgba(120, 119, 198, 0.3) 0%, transparent 50%),
    radial-gradient(circle at 80% 20%, rgba(255, 119, 198, 0.15) 0%, transparent 50%),
    radial-gradient(circle at 40% 40%, rgba(120, 219, 255, 0.1) 0%, transparent 50%);
  pointer-events: none;
}

/* Content wrapper */



.login-content {
  position: relative;
  z-index: 1;
  width: 100%;
  max-width: 360px;
  animation: slideInUp 0.6s ease-out;
}

/* Prevent horizontal page scroll */
html, body {
  overflow-x: hidden;
}

/* Card sizing for large screens but keep compact */
@media (min-width: 1200px) {
  .login-content {
    max-width: 420px;
  }
}

/* Make card fit viewport height and scroll internally if content overflows */
.login-card {
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0.05) 100%);
  backdrop-filter: blur(20px);
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 24px;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
  overflow: hidden;
  position: relative;
  max-height: calc(100vh - 40px);
  box-sizing: border-box;
  overflow-y: auto;
}

/* Slightly reduce header/footer padding so total card height fits */
.login-header {
  padding: 28px 20px 20px 20px;
  text-align: center;
  position: relative;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.05) 0%, transparent 100%);
}

.login-footer {
  padding: 14px 20px 18px 20px;
  border-top: 1px solid rgba(255, 255, 255, 0.06);
}

/* Main login card dengan glass morphism */
.login-card {
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0.05) 100%);
  backdrop-filter: blur(20px);
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 24px;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
  overflow: hidden;
  position: relative;
}

.login-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.1), transparent);
  opacity: 0;
  transition: opacity 0.3s ease;
  pointer-events: none;
}

/* Logo section dengan design yang lebih menarik */
.login-header {
  padding: 50px 35px 40px 35px;
  text-align: center;
  position: relative;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.05) 0%, transparent 100%);
}

.logo-section {
  animation: fadeIn 1.2s ease-out 0.3s both;
}

.logo-container {
  position: relative;
  display: inline-block;
  margin-bottom: 30px;
}

.logo-icon {
  width: 90px;
  height: 90px;
      padding: 1.2rem;
  border-radius: 25px;
  text-align: center;
  display: flex;
      padding: 1.75rem 1.25rem 1.5rem 1.25rem;
  justify-content: center;
  margin: 0 auto;
    .login-body {
      padding: 0 1.25rem 1.75rem 1.25rem; /* sedikit lebih ruang bawah untuk tombol */
    }
  
    .login-footer {
      padding: 1.25rem 1.25rem 1.75rem 1.25rem;
    }
  
    .login-header h1 {
      font-size: 1.6rem;
    }
  
    .subtitle-wrapper p {
      font-size: 0.9rem;
    }
  
    .logo-icon {
      width: 72px;
      height: 72px;
      margin-bottom: 1.25rem;
    }
  
    .logo-icon i {
      font-size: 1.6rem;
    }
  
    .form-label {
      font-size: 0.95rem;
    }
  
    .form-control {
      padding: 0.9rem 1rem;
      font-size: 0.95rem;
      border-radius: 12px;
    }
  
    .btn-primary {
      padding: 1rem 1.25rem;
      font-size: 1rem;
      min-height: 52px;
      border-radius: 14px;
    }

    .form-group {
      margin-bottom: 1rem;
    }

    /* beri sedikit margin agar card tidak menempel di pinggir layar */
    .login-card {
      margin: 8px;
    }
  border-radius: 50%;
  animation: dotPulse 2s ease-in-out infinite;
}

.dot-1 {
  background: #00b894;
  animation-delay: 0s;
}

.dot-2 {
  background: #fdcb6e;
  animation-delay: 0.3s;
}

.dot-3 {
  background: #e17055;
  animation-delay: 0.6s;
}

.title-container {
  position: relative;
}

.login-header h1 {
  margin: 0 0 20px 0;
  color: white;
  font-size: 2.4rem;
  font-weight: 800;
  text-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
  letter-spacing: -1px;
  line-height: 1.1;
  background: linear-gradient(135deg, #ffffff 0%, #e8f4ff 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.subtitle-wrapper {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 15px;
  margin: 0;
}

.subtitle-line {
  width: 40px;
  height: 1px;
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.6), transparent);
  animation: lineGlow 2s ease-in-out infinite alternate;
}

.subtitle-wrapper p {
  margin: 0;
  color: rgba(255, 255, 255, 0.85);
  font-size: 1rem;
  font-weight: 500;
  letter-spacing: 0.5px;
  text-transform: uppercase;
  font-family: 'Inter', sans-serif;
}

/* Logo section dengan design yang lebih menarik */
.login-header {
  padding: 50px 35px 40px 35px;
  text-align: center;
  position: relative;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.05) 0%, transparent 100%);
}

.logo-section {
  animation: fadeIn 1.2s ease-out 0.3s both;
}

.logo-container {
  position: relative;
  display: inline-block;
  margin-bottom: 30px;
}

.logo-icon {
  width: 90px;
  height: 90px;
  background: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%);
  border-radius: 25px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto;
  box-shadow: 
    0 20px 40px rgba(116, 185, 255, 0.4),
    0 0 0 1px rgba(255, 255, 255, 0.1),
    inset 0 1px 0 rgba(255, 255, 255, 0.2);
  animation: logoFloat 4s ease-in-out infinite;
  position: relative;
  overflow: hidden;
}

.logo-icon::before {
  content: '';
  position: absolute;
  top: -2px;
  left: -2px;
  right: -2px;
  bottom: -2px;
  background: linear-gradient(45deg, #74b9ff, #00cec9, #fd79a8, #fdcb6e);
  border-radius: 27px;
  z-index: -1;
  animation: borderRotate 3s linear infinite;
}

.logo-icon i {
  font-size: 2.5rem;
  color: white;
  animation: iconPulse 2.5s ease-in-out infinite;
  filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));
}

.logo-dots {
  position: absolute;
  top: -15px;
  right: -15px;
  display: flex;
  gap: 4px;
}

.dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  animation: dotPulse 2s ease-in-out infinite;
}

.dot-1 {
  background: #00b894;
  animation-delay: 0s;
}

.dot-2 {
  background: #fdcb6e;
  animation-delay: 0.3s;
}

.dot-3 {
  background: #e17055;
  animation-delay: 0.6s;
}

.title-container {
  position: relative;
}

.login-header h1 {
  margin: 0 0 20px 0;
  color: white;
  font-size: 2.4rem;
  font-weight: 800;
  text-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
  letter-spacing: -1px;
  line-height: 1.1;
  background: linear-gradient(135deg, #ffffff 0%, #e8f4ff 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.subtitle-wrapper {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 15px;
  margin: 0;
}

.subtitle-line {
  width: 40px;
  height: 1px;
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.6), transparent);
  animation: lineGlow 2s ease-in-out infinite alternate;
}

.subtitle-wrapper p {
  margin: 0;
  color: rgba(255, 255, 255, 0.85);
  font-size: 1rem;
  font-weight: 500;
  letter-spacing: 0.5px;
  text-transform: uppercase;
  font-family: 'Inter', sans-serif;
}

/* Body section */
.login-body {
  padding: 0 30px 28px 30px; /* increased bottom padding for spacing above button */
}

/* Error message styling */
.error-message {
  background: linear-gradient(135deg, rgba(231, 76, 60, 0.1) 0%, rgba(192, 57, 43, 0.05) 100%);
  border: 1px solid rgba(231, 76, 60, 0.3);
  color: #e74c3c;
  padding: 15px 20px;
  border-radius: 12px;
  margin-bottom: 25px;
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 0.9rem;
  animation: shake 0.5s ease-in-out;
}

.error-message i {
  font-size: 1.1rem;
}

/* Form styling */
.login-form {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.form-label {
  color: rgba(255, 255, 255, 0.9);
  font-weight: 600;
  font-size: 0.95rem;
  display: flex;
  align-items: center;
  gap: 8px;
}

.form-label i {
  font-size: 0.9rem;
  opacity: 0.8;
}

.input-wrapper {
  position: relative;
}

.input-wrapper .form-control {
  padding-right: 56px; /* reserve space for toggle */
}

.password-toggle {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  background: transparent;
  border: none;
  color: rgba(255,255,255,0.95);
  font-size: 1rem;
  padding: 6px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.password-toggle:focus {
  outline: none;
  box-shadow: 0 0 0 4px rgba(116,185,255,0.12);
  border-radius: 6px;
}

.form-control {
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0.03) 100%);
  backdrop-filter: blur(15px);
  border: 1.5px solid rgba(255, 255, 255, 0.15);
  color: white;
  padding: 18px 24px;
  border-radius: 14px;
  font-size: 1rem;
  transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
  width: 100%;
  box-sizing: border-box;
  font-weight: 400;
}

.form-control:focus {
  outline: none;
  border-color: #74b9ff;
  box-shadow: 
    0 0 0 4px rgba(116, 185, 255, 0.15),
    0 8px 25px rgba(116, 185, 255, 0.2);
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.12) 0%, rgba(255, 255, 255, 0.06) 100%);
  transform: translateY(-2px);
}

.form-control::placeholder {
  color: rgba(255, 255, 255, 0.5);
  font-weight: 400;
}

/* Button styling */
.btn-primary {
  background: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%);
  border: none;
  color: white;
  padding: clamp(10px, 1.2vw, 16px) clamp(12px, 2.0vw, 24px);
  border-radius: 12px;
  font-size: clamp(0.95rem, 1.2vw, 1.05rem);
  min-height: 44px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
  position: relative;
  overflow: hidden;
  box-shadow: 0 8px 25px rgba(116, 185, 255, 0.4);
}

.btn-primary::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
  transition: left 0.5s ease;
}

.btn-primary:hover {
  background: linear-gradient(135deg, #0984e3 0%, #74b9ff 100%);
  transform: translateY(-3px);
  box-shadow: 0 12px 35px rgba(116, 185, 255, 0.6);
}

.btn-primary:hover::before {
  left: 100%;
}

.btn-primary:active {
  transform: translateY(-1px);
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}

.btn-primary:disabled:hover {
  transform: none;
  box-shadow: 0 8px 25px rgba(116, 185, 255, 0.4);
}

/* Error message styling yang lebih refined */
.error-message {
  background: linear-gradient(135deg, rgba(231, 76, 60, 0.15) 0%, rgba(192, 57, 43, 0.08) 100%);
  border: 1.5px solid rgba(231, 76, 60, 0.4);
  color: #ff6b6b;
  padding: 16px 22px;
  border-radius: 14px;
  margin-bottom: 25px;
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 0.95rem;
  font-weight: 500;
  animation: errorSlideIn 0.5s cubic-bezier(0.25, 0.8, 0.25, 1);
  backdrop-filter: blur(10px);
  box-shadow: 0 8px 25px rgba(231, 76, 60, 0.2);
}

.error-message i {
  font-size: 1.1rem;
  color: #e74c3c;
}

.btn-content {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  gap: 12px;
  font-weight: 600;
}

.btn-full {
  width: 100%;
  margin-top: 20px; /* slightly larger gap to separate form from button */
}

/* Enhanced animations */
@keyframes logoFloat {
  0%, 100% {
    transform: translateY(0px) rotate(0deg);
  }
  25% {
    transform: translateY(-8px) rotate(1deg);
  }
  50% {
    transform: translateY(-12px) rotate(0deg);
  }
  75% {
    transform: translateY(-6px) rotate(-1deg);
  }
}

@keyframes borderRotate {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
}

@keyframes iconPulse {
  0%, 100% {
    opacity: 1;
    transform: scale(1);
  }
  50% {
    opacity: 0.9;
    transform: scale(1.05);
  }
}

@keyframes dotPulse {
  0%, 100% {
    opacity: 0.6;
    transform: scale(0.8);
  }
  50% {
    opacity: 1;
    transform: scale(1.2);
  }
}

@keyframes lineGlow {
  0% {
    opacity: 0.4;
    transform: scaleX(0.8);
  }
  100% {
    opacity: 0.8;
    transform: scaleX(1.2);
  }
}

@keyframes errorSlideIn {
  from {
    opacity: 0;
    transform: translateY(-20px) scale(0.95);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

/* Footer section dengan design yang lebih elegant */
.login-footer {
  padding: 25px 35px 35px 35px;
  border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.divider {
  text-align: center;
  margin-bottom: 25px;
  position: relative;
}

.divider::before {
  content: '';
  position: absolute;
  top: 50%;
  left: 0;
  right: 0;
  height: 1px;
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
}

.divider span {
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0.05) 100%);
  color: rgba(255, 255, 255, 0.85);
  padding: 10px 20px;
  border-radius: 25px;
  font-size: 0.9rem;
  font-weight: 600;
  position: relative;
  z-index: 1;
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.1);
  letter-spacing: 0.3px;
}

.test-accounts {
  display: flex;
  flex-direction: column;
  gap: 15px;
}

.account-item {
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0.03) 100%);
  border: 1.5px solid rgba(255, 255, 255, 0.12);
  border-radius: 14px;
  padding: 18px 22px;
  cursor: pointer;
  transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
  display: flex;
  justify-content: space-between;
  align-items: center;
  backdrop-filter: blur(10px);
  position: relative;
  overflow: hidden;
}

.account-item::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(116, 185, 255, 0.1), transparent);
  transition: left 0.6s ease;
}

.account-item:hover {
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.12) 0%, rgba(255, 255, 255, 0.06) 100%);
  border-color: rgba(116, 185, 255, 0.4);
  transform: translateY(-3px);
  box-shadow: 0 8px 25px rgba(116, 185, 255, 0.15);
}

.account-item:hover::before {
  left: 100%;
}

.account-role {
  display: flex;
  align-items: center;
  gap: 12px;
  color: white;
  font-weight: 700;
  font-size: 0.95rem;
}

.account-role i {
  font-size: 1.1rem;
  opacity: 0.9;
  color: #74b9ff;
}

.account-credentials {
  color: rgba(255, 255, 255, 0.7);
  font-size: 0.85rem;
  font-family: 'JetBrains Mono', monospace;
  font-weight: 500;
}

/* Animations */
/* Enhanced animations */
@keyframes logoFloat {
  0%, 100% {
    transform: translateY(0px) rotate(0deg);
  }
  25% {
    transform: translateY(-8px) rotate(1deg);
  }
  50% {
    transform: translateY(-12px) rotate(0deg);
  }
  75% {
    transform: translateY(-6px) rotate(-1deg);
  }
}

@keyframes borderRotate {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
}

@keyframes iconPulse {
  0%, 100% {
    opacity: 1;
    transform: scale(1);
  }
  50% {
    opacity: 0.9;
    transform: scale(1.05);
  }
}

@keyframes dotPulse {
  0%, 100% {
    opacity: 0.6;
    transform: scale(0.8);
  }
  50% {
    opacity: 1;
    transform: scale(1.2);
  }
}

@keyframes lineGlow {
  0% {
    opacity: 0.4;
    transform: scaleX(0.8);
  }
  100% {
    opacity: 0.8;
    transform: scaleX(1.2);
  }
}

@keyframes slideInUp {
  from {
    opacity: 0;
    transform: translateY(50px) scale(0.95);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes float {
  0%, 100% {
    transform: translateY(0px);
  }
  50% {
    transform: translateY(-10px);
  }
}

@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.8;
  }
}

@keyframes shake {
  0%, 100% {
    transform: translateX(0);
  }
  25% {
    transform: translateX(-5px);
  }
  75% {
    transform: translateX(5px);
  }
}

/* Responsive design */
@media (max-width: 1024px) {
  .login-content {
    max-width: 500px;
  }
}

@media (max-width: 768px) {
  .login-container {
    padding: 1rem;
  }
  
  .login-content {
    max-width: 100%;
  }
  
  .login-card {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  }
  
  .login-header {
    padding: 2rem 1.5rem 1.5rem 1.5rem;
  }
  
  .login-body {
    padding: 0 1.5rem 1.75rem 1.5rem; /* increased bottom padding on tablet */
  }
  
  .login-footer {
    padding: 1.25rem 1.5rem 2rem 1.5rem;
  }
  
  .login-header h1 {
    font-size: 1.875rem;
  }
  
  .subtitle-wrapper p {
    font-size: 0.9rem;
  }
  
  .logo-icon {
    width: 70px;
    height: 70px;
  }
  
  .logo-icon i {
    font-size: 1.75rem;
  }
  
  .form-group {
    margin-bottom: 1.25rem;
  }
  
  .account-item {
    padding: 0.875rem;
  }
  
  .test-accounts {
    gap: 0.75rem;
  }
}

@media (max-width: 480px) {
  .login-container {
    padding: 0.75rem;
  }
  
  .login-header {
    padding: 1.5rem 1.25rem 1.25rem 1.25rem;
  }
  
  .login-body {
    padding: 0 1.25rem 1.25rem 1.25rem; /* increased bottom padding on small screens */
  }
  
  .login-footer {
    padding: 1rem 1.25rem 1.5rem 1.25rem;
  }
  
  .login-header h1 {
    font-size: 1.5rem;
  }
  
  .subtitle-wrapper p {
    font-size: 0.8rem;
  }
  
  .logo-icon {
    width: 60px;
    height: 60px;
    margin-bottom: 1rem;
  }
  
  .logo-icon i {
    font-size: 1.5rem;
  }
  
  .form-label {
    font-size: 0.875rem;
  }
  
  .form-control {
    padding: 0.625rem 0.875rem;
    font-size: 0.875rem;
  }
  
  .btn-primary {
    padding: 0.85rem 1.25rem;
    font-size: 0.95rem;
    min-height: 48px;
  }
  
  .account-item {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
    text-align: left;
    padding: 0.75rem;
  }
  
  .account-role {
    font-size: 0.85rem;
  }
  
  .account-credentials {
    word-break: break-all;
    font-size: 0.75rem;
  }
  
  .divider span {
    font-size: 0.8rem;
    padding: 0 0.75rem;
  }
}
</style>