# 🎨 Uptime Monitor - Vue.js Frontend

Modern, responsive frontend untuk sistem uptime monitoring dengan real-time updates dan notifikasi otomatis.

## ⚡ Quick Start

```bash
# Install dependencies
npm install

# Start development server (default: http://localhost:5173)
npm run dev
```

**Open browser:** http://localhost:5173

**Default Login:**
- Email: `admin@uptimemonitors.local`
- Password: `password`

> **⚠️ Prerequisites:** Backend Laravel harus sudah berjalan di `http://localhost:8000`. Lihat [uptime-monitor/README.md](../uptime-monitor/README.md) untuk setup backend.

---

## 🌟 Features

### ✅ Authentication & Security
- JWT token-based authentication
- Auto token refresh & session management
- Protected routes dengan route guards
- Automatic logout on token expiration
- Secure token storage

### 📊 Dashboard
- Real-time monitor statistics overview
- Interactive uptime charts (Chart.js)
- Recent incidents timeline
- System health status cards
- Quick action buttons

### 🖥️ Monitor Management
- **Full CRUD operations** untuk semua monitor types:
  - HTTP/HTTPS monitoring
  - PING monitoring (ICMP)
  - PORT monitoring (TCP/UDP)
  - KEYWORD monitoring (content check)
  - SSL certificate monitoring
  - HEARTBEAT monitoring
- Advanced filtering & search
- Pause/resume monitoring dengan duration
- Real-time status updates
- Bulk operations support

### 🚨 Incident Management
- Timeline view untuk semua incidents
- Status tracking (Open → Acknowledged → Resolved)
---

## 🛠️ Tech Stack

| Technology | Version | Purpose |
|------------|---------|---------|
| **Vue 3** | Latest | Framework dengan Composition API |
| **Vite** | Latest | Build tool & dev server (HMR) |
| **Vue Router** | 4.x | Client-side routing |
| **Pinia** | Latest | State management store |
| **Axios** | Latest | HTTP client untuk API calls |
| **Chart.js** | 4.x | Data visualization & charts |
| **Node.js** | 20.19+ / 22.12+ | Runtime environment |
  - Discord Webhook
  - Slack Webhook
  - Generic Webhook
- Test notification functionality
- Channel enable/disable toggle
- Channel configuration management

### 📱 Responsive Design
- **Mobile-first approach**
- Touch-optimized controls
- Adaptive layouts untuk semua screen sizes
- Progressive Web App (PWA) ready
- Modern glassmorphism UI design

## 🛠️ Tech Stack

- Vue 3 + Composition API
- Vite (build tool)
---

## 📁 Project Structure

```
uptime-frontend/
├── src/
│   ├── components/           # Reusable components
│   │   └── Navbar.vue       # Navigation bar dengan auth
│   ├── views/               # Page components
│   │   ├── LoginView.vue           # Login page
│   │   ├── DashboardView.vue       # Main dashboard
│   │   ├── MonitorsView.vue        # Monitor listing
│   │   ├── MonitorDetailView.vue   # Monitor details & checks
│   │   ├── CreateMonitorView.vue   # Create monitor form
│   │   ├── EditMonitorView.vue     # Edit monitor form
│   │   ├── NotificationChannelsView.vue  # Notification management
│   │   └── IncidentsView.vue       # Incident management
│   ├── stores/              # Pinia stores
│   │   ├── auth.js          # Authentication state & actions
---

## ⚙️ Configuration

### Environment Variables

Create `.env` file di root folder:

```env
# API Backend URL
VITE_API_BASE_URL=http://localhost:8000/api

# Optional: Production API URL
# VITE_API_BASE_URL=https://api.yourapp.com/api
```

### Vite Configuration

Customize di `vite.config.js`:

```javascript
export default defineConfig({
---

## 🔐 Security Features

### Authentication & Authorization
- ✅ JWT token-based authentication
- ✅ Automatic token refresh mechanism
- ✅ Protected routes dengan navigation guards
- ✅ Automatic logout on token expiration
- ✅ Secure token storage (localStorage dengan encryption)

### API Security
- ✅ Request/response interceptors
- ✅ CSRF token handling
- ✅ XSS protection
- ✅ Input validation & sanitization
- ✅ Rate limiting (via backend)
        changeOrigin: true,
      }
    }
  }
})
```

---

## 🎨 Theme & Styling

### Color Palette
```css
/* Status Colors */
--color-up: #27ae60;        /* Green - Service UP */
--color-down: #e74c3c;      /* Red - Service DOWN */
--color-paused: #f39c12;    /* Orange - Paused */
--color-unknown: #95a5a6;   /* Gray - Unknown */

/* UI Colors */
--color-primary: #3498db;   /* Blue */
--color-success: #27ae60;   /* Green */
--color-warning: #f39c12;   /* Orange */
--color-danger: #e74c3c;    /* Red */
--color-secondary: #95a5a6; /* Gray */
```
---

## 🚀 Development

---

## 🤝 Backend Integration

Frontend ini **fully integrated** dengan Laravel backend uptime monitor.

### Integration Checklist

✅ **Backend Requirements:**
1. Laravel backend running di `http://localhost:8000`
2. Database PostgreSQL sudah di-migrate
3. API endpoints accessible
4. CORS configured untuk frontend domain
5. JWT authentication configured

✅ **Frontend Configuration:**
1. `VITE_API_BASE_URL` di `.env` pointing ke backend
2. Axios interceptors configured untuk JWT
3. API service layer di `src/services/api.js`

### CORS Configuration (Backend)

Pastikan Laravel `config/cors.php` sudah configured:
```php
'paths' => ['api/*', 'sanctum/csrf-cookie'],
'allowed_origins' => ['http://localhost:5173', 'http://localhost:3000'],
'allowed_methods' => ['*'],
'allowed_headers' => ['*'],
'supports_credentials' => true,
```

---

## 🐛 Troubleshooting

### Common Issues & Solutions

| Issue | Solution |
|-------|----------|
| **Connection refused** | ✅ Pastikan backend Laravel running di port 8000 |
| **CORS errors** | ✅ Configure Laravel CORS middleware (lihat di atas) |
| **Build fails** | ✅ Update Node.js ke versi 20.19+ atau 22.12+ |
| **Authentication errors** | ✅ Check JWT configuration di backend `.env` |
| **404 on refresh** | ✅ Configure web server untuk SPA fallback |
| **API timeout** | ✅ Check backend queue workers running |
| **Blank page** | ✅ Check browser console untuk errors |

### Debugging Tools

```bash
# Check API connectivity
curl http://localhost:8000/api/monitors

# Check dev server logs
npm run dev

# Check browser console
# Press F12 → Console tab

# Check Vue DevTools
# Install extension, then inspect Pinia stores
```

### Performance Issues

- ✅ Enable **production mode** (`npm run build`)
- ✅ Use **code splitting** untuk lazy loading
- ✅ Optimize **images** di public folder
- ✅ Enable **gzip compression** di web server
- ✅ Use **CDN** untuk static assets

---

## 📊 Project Status

### Current Version: **v1.0.0**

✅ **Production Ready** - Frontend fully functional dengan semua fitur:

- ✅ Complete authentication system
- ✅ Full CRUD untuk semua monitor types
- ✅ Real-time dashboard dengan charts
- ✅ Incident management dengan timeline
- ✅ Multi-channel notification support
- ✅ Responsive design untuk mobile & desktop
- ✅ Full integration dengan Laravel backend
- ✅ Production-ready build configuration

---

## 📞 Support & Contributing

### Getting Help

1. **Documentation** - Check [FRONTEND-DOCUMENTATION.md](./FRONTEND-DOCUMENTATION.md)
2. **Backend Docs** - See [uptime-monitor/README.md](../uptime-monitor/README.md)
3. **Browser Console** - F12 untuk check errors
4. **Network Tab** - Monitor API calls
5. **Vue DevTools** - Inspect component state

### Development Guidelines

- Follow **Vue 3 Composition API** patterns
- Use **Pinia** untuk state management
- Keep components **small and focused**
- Write **semantic HTML**
- Follow **mobile-first** approach
- Add **comments** untuk complex logic

---

## 📝 License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

---

**Built with ❤️ using Vue.js 3 + Laravel 11**
# Configure environment variables di hosting panel
```

**Option 2: Nginx Server**
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/uptime-frontend/dist;
    index index.html;

    location / {
        try_files $uri $uri/ /index.html;
    }

    # API Proxy (optional)
    location /api {
        proxy_pass http://localhost:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

**Option 3: Apache Server**
```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /path/to/uptime-frontend/dist
    
    <Directory /path/to/uptime-frontend/dist>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
        
        # SPA Fallback
        RewriteEngine On
        RewriteBase /
        RewriteRule ^index\.html$ - [L]
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule . /index.html [L]
    </Directory>
</VirtualHost>
```

### Environment Variables (Production)

Update `.env` untuk production:
```env
VITE_API_BASE_URL=https://api.your-production-domain.com/api
```

### Performance Optimization

```bash
# Production build includes:
# - Code minification
# - Tree shaking
# - Chunk splitting
# - Asset optimization
# - Gzip compression (server-side)
```

---

## 📚 Documentation

Dokumentasi lengkap untuk frontend dan backend:

### Frontend Documentation
- **[FRONTEND-DOCUMENTATION.md](./FRONTEND-DOCUMENTATION.md)** - Complete frontend guide

### Backend Documentation (../uptime-monitor/)
- **[QUICK_START.md](../uptime-monitor/QUICK_START.md)** - Quick start guide
- **[PRODUCTION_DEPLOYMENT.md](../uptime-monitor/PRODUCTION_DEPLOYMENT.md)** - Production setup dengan Cron & Supervisor
- **[RESEARCH_AND_DEVELOPMENT.md](../uptime-monitor/RESEARCH_AND_DEVELOPMENT.md)** - RnD guide untuk developers
- **[ARCHITECTURE.md](../uptime-monitor/ARCHITECTURE.md)** - System architecture & diagrams
- **[DEVELOPER_QUICK_REFERENCE.md](../uptime-monitor/DEVELOPER_QUICK_REFERENCE.md)** - Quick reference
- **[NOTIFICATION_SYSTEM_READY.md](../uptime-monitor/NOTIFICATION_SYSTEM_READY.md)** - Notification system
- **[TROUBLESHOOTING_NOTIFICATIONS.md](../uptime-monitor/TROUBLESHOOTING_NOTIFICATIONS.md)** - Troubleshooting
- **[WORKERS_README.md](../uptime-monitor/WORKERS_README.md)** - Worker documentation
/* Mobile First */
@media (max-width: 768px) { /* Mobile */ }
@media (min-width: 769px) and (max-width: 1024px) { /* Tablet */ }
@media (min-width: 1025px) { /* Desktop */ }
```

---

## 📱 Mobile Support

✅ **Fully Responsive** - Mendukung semua device sizes:
- Touch-optimized controls & gestures
- Responsive navigation dengan burger menu
- Adaptive layouts (grid → stack)
- Mobile-first CSS architecture
- PWA-ready untuk install di mobilean Laravel backend melalui **RESTful API**. Semua endpoints dikonfigurasi di `src/services/api.js`.

### Key Endpoints
- **Auth:** `/auth/login`, `/auth/logout`, `/auth/refresh`, `/auth/user`
- **Monitors:** `/monitors` (CRUD operations)
- **Incidents:** `/incidents` (list, acknowledge, resolve)
- **Channels:** `/notification-channels` (CRUD, test)
- **Checks:** `/monitor-checks` (history)

> **💡 Tip:** Lihat [FRONTEND-DOCUMENTATION.md](./FRONTEND-DOCUMENTATION.md) untuk daftar lengkap API endpoints
```

## 🔌 API Integration

Frontend ini berkomunikasi dengan Laravel backend melalui RESTful API endpoints. Semua endpoint sudah dikonfigurasi di `src/services/api.js`.

## 🎨 Customization

### Environment Variables
```bash
# .env
VITE_API_BASE_URL=http://localhost:8000/api
```

### Styling
Aplikasi menggunakan custom CSS dengan color scheme:
- Primary: #3498db (Blue)
- Success: #27ae60 (Green)
- Warning: #f39c12 (Orange)  
- Danger: #e74c3c (Red)

## 📱 Mobile Support

Aplikasi sudah responsive dan mobile-friendly dengan:
- Touch-optimized controls
- Responsive navigation
- Mobile-first CSS approach

## 🔐 Security

- JWT token authentication
- Protected API routes
- Automatic logout on token expiration
- CSRF protection via backend integration

## 📚 Documentation

Lihat [FRONTEND-DOCUMENTATION.md](./FRONTEND-DOCUMENTATION.md) untuk dokumentasi lengkap.

## 🤝 Backend Integration

Frontend ini dirancang untuk bekerja dengan Laravel backend uptime monitor. Pastikan:

1. Backend Laravel sudah running
2. Database sudah di-migrate
3. API endpoints accessible
4. CORS configured untuk frontend domain

## 🚀 Production Deployment

```bash
# Build for production
npm run build

# Output akan tersedia di folder dist/
# Deploy folder dist/ ke web server
```

## 🐛 Troubleshooting

### Common Issues

1. **Connection refused**: Pastikan backend Laravel running di port 8000
2. **CORS errors**: Configure Laravel CORS middleware
3. **Build fails**: Update Node.js ke versi yang sesuai
4. **Authentication errors**: Check JWT configuration di backend

### Development Tips

- Gunakan browser dev tools untuk debug API calls
- Check Pinia store state di Vue DevTools
- Verify API responses di Network tab

## 📞 Support

Untuk issues dan pertanyaan teknis, check:
1. Console browser untuk errors
2. Network tab untuk failed API calls
3. Vue DevTools untuk state debugging

---

**🎯 Status: Production Ready**

Frontend Vue.js sudah lengkap dan siap digunakan dengan semua fitur monitoring yang diperlukan!
