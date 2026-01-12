# Uptime Monitor - Production Deployment Guide

## Environment Setup

### 1. Environment Variables

Untuk deployment production, Anda perlu mengkonfigurasi file `.env`:

#### Development (.env)
```bash
VITE_BACKEND_URL=http://localhost:8000/api
VITE_APP_ENV=development
```

#### Production (.env.production)
```bash
VITE_BACKEND_URL=https://your-domain.com/api
VITE_APP_ENV=production
VITE_APP_DEBUG=false
```

### 2. Build untuk Production

```bash
# Install dependencies
npm install

# Build untuk production (akan menggunakan .env.production)
npm run build

# Preview production build locally
npm run preview
```

### 3. Deployment Steps

#### Option A: Manual Deployment

1. Build aplikasi:
```bash
npm run build
```

2. Upload folder `dist/` ke server production Anda (Apache/Nginx)

3. Konfigurasi web server:

**Nginx Configuration:**
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/uptime-monitor/dist;
    index index.html;

    location / {
        try_files $uri $uri/ /index.html;
    }

    location /api {
        proxy_pass http://backend-server:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

**Apache Configuration (.htaccess):**
```apache
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteBase /
  RewriteRule ^index\.html$ - [L]
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteRule . /index.html [L]
</IfModule>
```

#### Option B: Using CI/CD (GitHub Actions)

Create `.github/workflows/deploy.yml`:
```yaml
name: Deploy to Production

on:
  push:
    branches: [ main ]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      
      - name: Setup Node.js
        uses: actions/setup-node@v2
        with:
          node-version: '18'
      
      - name: Install dependencies
        run: npm install
      
      - name: Build
        run: npm run build
        env:
          VITE_BACKEND_URL: ${{ secrets.VITE_BACKEND_URL }}
          VITE_APP_ENV: production
      
      - name: Deploy to Server
        uses: easingthemes/ssh-deploy@main
        env:
          SSH_PRIVATE_KEY: ${{ secrets.SSH_PRIVATE_KEY }}
          REMOTE_HOST: ${{ secrets.REMOTE_HOST }}
          REMOTE_USER: ${{ secrets.REMOTE_USER }}
          TARGET: /var/www/uptime-monitor/dist
```

### 4. Environment Variables di Production

Untuk menggunakan environment variables di production:

1. **Set di Server:**
```bash
# Di server production
export VITE_BACKEND_URL=https://your-domain.com/api
export VITE_APP_ENV=production
npm run build
```

2. **Atau gunakan .env.production:**
   - File `.env.production` akan otomatis digunakan saat `npm run build`
   - Pastikan sudah dikonfigurasi dengan benar sesuai domain production Anda

3. **Untuk Vercel/Netlify:**
   - Tambahkan environment variables di dashboard
   - Set `VITE_BACKEND_URL` dan variabel lainnya

### 5. Checklist Production

- [ ] Update `VITE_BACKEND_URL` ke URL production
- [ ] Set `VITE_APP_ENV=production`
- [ ] Set `VITE_APP_DEBUG=false`
- [ ] Test koneksi API production
- [ ] Konfigurasi CORS di backend Laravel
- [ ] Setup SSL certificate (HTTPS)
- [ ] Test semua fitur monitoring
- [ ] Setup CDN untuk assets (opsional)

### 6. Backend Laravel Configuration

Pastikan backend Laravel juga sudah dikonfigurasi:

```php
// config/cors.php
'allowed_origins' => [
    'https://your-domain.com',
],

// .env
APP_URL=https://api.your-domain.com
FRONTEND_URL=https://your-domain.com
```

### 7. Troubleshooting

**Masalah: API tidak tersambung**
- Check CORS configuration di Laravel
- Verify `VITE_BACKEND_URL` sudah benar
- Check browser console untuk error

**Masalah: 404 pada refresh**
- Setup URL rewriting di web server
- Pastikan `history` mode Vue Router dikonfigurasi dengan benar

**Masalah: Environment variables tidak terbaca**
- Environment variables harus prefix `VITE_`
- Rebuild aplikasi setelah mengubah .env
- Clear cache browser

### 8. Performance Optimization

```javascript
// vite.config.js - production optimization
export default {
  build: {
    minify: 'terser',
    terserOptions: {
      compress: {
        drop_console: true,
      },
    },
    rollupOptions: {
      output: {
        manualChunks: {
          vendor: ['vue', 'vue-router', 'pinia'],
        },
      },
    },
  },
}
```

## Quick Commands

```bash
# Development
npm run dev

# Build for production
npm run build

# Preview production build
npm run preview

# Check build size
npm run build -- --report
```
