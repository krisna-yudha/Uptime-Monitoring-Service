#!/bin/bash

# Uptime Monitor - Notification Worker Installation Script
# This script installs and configures supervisor for the notification worker

echo "============================================"
echo "  NOTIFICATION WORKER - SUPERVISOR SETUP"
echo "============================================"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Get current directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo -e "${RED}Error: This script must be run as root (use sudo)${NC}"
    exit 1
fi

# Check if supervisor is installed
if ! command -v supervisorctl &> /dev/null; then
    echo -e "${YELLOW}Supervisor is not installed. Installing...${NC}"
    apt-get update
    apt-get install -y supervisor
    systemctl enable supervisor
    systemctl start supervisor
    echo -e "${GREEN}Supervisor installed successfully${NC}"
else
    echo -e "${GREEN}Supervisor is already installed${NC}"
fi

# Detect web server user
WEB_USER="www-data"
if id "nginx" &>/dev/null; then
    WEB_USER="nginx"
elif id "apache" &>/dev/null; then
    WEB_USER="apache"
fi

echo ""
echo "Detected web server user: ${WEB_USER}"
echo "Project directory: ${SCRIPT_DIR}"
echo ""

# Create supervisor config from template
SUPERVISOR_CONF="/etc/supervisor/conf.d/uptime-notification-worker.conf"

echo "Creating supervisor configuration..."

cat > ${SUPERVISOR_CONF} << EOF
# Supervisor Configuration for Uptime Monitor - Notification Worker
# Auto-generated on $(date)

[program:uptime-notification-worker]
process_name=%(program_name)s_%(process_num)02d
command=php ${SCRIPT_DIR}/artisan queue:work database --queue=notifications --sleep=0 --tries=3 --timeout=120 --max-jobs=1000 --name=notification-worker
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=${WEB_USER}
numprocs=1
redirect_stderr=true
stdout_logfile=${SCRIPT_DIR}/storage/logs/worker-notifications.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
stopwaitsecs=60
startsecs=3
priority=998
autorestart=unexpected
startretries=3
EOF

echo -e "${GREEN}Supervisor config created: ${SUPERVISOR_CONF}${NC}"

# Ensure log directory exists and is writable
mkdir -p ${SCRIPT_DIR}/storage/logs
chown -R ${WEB_USER}:${WEB_USER} ${SCRIPT_DIR}/storage
chmod -R 775 ${SCRIPT_DIR}/storage

echo -e "${GREEN}Log directory configured${NC}"

# Reload supervisor configuration
echo ""
echo "Reloading supervisor configuration..."
supervisorctl reread
supervisorctl update

# Start the notification worker
echo ""
echo "Starting notification worker..."
supervisorctl start uptime-notification-worker:*

# Wait a moment for startup
sleep 2

# Check status
echo ""
echo "============================================"
echo "  NOTIFICATION WORKER STATUS"
echo "============================================"
supervisorctl status uptime-notification-worker:*

echo ""
echo "============================================"
echo "  INSTALLATION COMPLETE"
echo "============================================"
echo ""
echo "Management commands:"
echo "  Start:   sudo supervisorctl start uptime-notification-worker:*"
echo "  Stop:    sudo supervisorctl stop uptime-notification-worker:*"
echo "  Restart: sudo supervisorctl restart uptime-notification-worker:*"
echo "  Status:  sudo supervisorctl status uptime-notification-worker:*"
echo ""
echo "View logs:"
echo "  sudo tail -f ${SCRIPT_DIR}/storage/logs/worker-notifications.log"
echo "  sudo supervisorctl tail -f uptime-notification-worker:uptime-notification-worker_00"
echo ""
echo -e "${GREEN}Notification worker is now running!${NC}"
echo "It will automatically send alerts to Discord/Telegram/Slack when incidents occur."
echo ""
