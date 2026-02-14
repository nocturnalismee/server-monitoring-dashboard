#!/bin/bash

################################################################################
# Server Monitoring Agent - Setup Script
# Run this script to install the monitoring agent on a remote server
# Usage: sudo bash setup.sh
################################################################################

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}================================${NC}"
echo -e "${YELLOW}Server Monitoring Agent Setup${NC}"
echo -e "${YELLOW}================================${NC}"

# ============== CHECK PREREQUISITES ==============

echo -e "\n${YELLOW}[*] Checking prerequisites...${NC}"

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}[!] This script must be run as root${NC}"
    exit 1
fi

# Check for required commands
for cmd in curl awk grep; do
    if ! command -v "$cmd" &> /dev/null; then
        echo -e "${RED}[!] $cmd is not installed${NC}"
        exit 1
    fi
done

echo -e "${GREEN}[✓] Prerequisites OK${NC}"

# ============== INPUT CONFIGURATION ==============

echo -e "\n${YELLOW}[*] Configuration Setup${NC}"

read -p "Master server URL (e.g., http://monitor.example.com/api/receiver.php): " MASTER_URL
read -p "Server hostname/ID (default: $(hostname -s)): " SERVER_ID
SERVER_ID=${SERVER_ID:-$(hostname -s)}

read -p "API Key (from master server configuration): " API_KEY

if [ -z "$MASTER_URL" ] || [ -z "$API_KEY" ]; then
    echo -e "${RED}[!] Master URL and API Key are required${NC}"
    exit 1
fi

# ============== CREATE DIRECTORIES ==============

echo -e "\n${YELLOW}[*] Creating directories...${NC}"

INSTALL_DIR="/opt/serverstatus"
LOG_DIR="/var/log"

mkdir -p "$INSTALL_DIR"
mkdir -p "$LOG_DIR"

echo -e "${GREEN}[✓] Directories created${NC}"

# ============== COPY AGENT SCRIPT ==============

echo -e "\n${YELLOW}[*] Installing agent script...${NC}"

AGENT_SCRIPT="$INSTALL_DIR/uptime.sh"

# Create the agent script with configuration
cat > "$AGENT_SCRIPT" << 'EOF'
#!/bin/bash
set -o pipefail

MASTER_URL="__MASTER_URL__"
SERVER_ID="__SERVER_ID__"
API_KEY="__API_KEY__"
TIMEOUT=10
LOG_FILE="/var/log/serverstatus-agent.log"

get_uptime_days() {
    awk '{print int($1/86400)}' /proc/uptime 2>/dev/null || echo "0"
}

get_load_average() {
    awk '{printf "%.2f", $1}' /proc/loadavg 2>/dev/null || echo "0.00"
}

get_memory_info() {
    local mem_total=$(awk '/MemTotal:/ {print $2}' /proc/meminfo 2>/dev/null || echo "0")
    local mem_available=$(awk '/MemAvailable:/ {print $2}' /proc/meminfo 2>/dev/null || echo "0")
    echo "$mem_total|$mem_available"
}

get_disk_info() {
    local disk_info=$(df / 2>/dev/null | awk 'NR==2 {print $2"|"$4}')
    echo "$disk_info"
}

get_mail_queue() {
    local queue=0
    
    if command -v exim &> /dev/null; then
        queue=$(exim -bpc 2>/dev/null | tail -1)
        queue=${queue//[^0-9]/}
        echo "${queue:-0}"
        return
    fi
    
    if command -v postqueue &> /dev/null; then
        queue=$(postqueue -p 2>/dev/null | tail -n +2 | grep -v '^-' | wc -l)
        echo "${queue:-0}"
        return
    fi
    
    if [ -d "/var/spool/mqueue" ]; then
        queue=$(find /var/spool/mqueue -type f | wc -l)
        echo "${queue:-0}"
        return
    fi
    
    echo "0"
}

IFS='|' read -r MEM_TOTAL MEM_AVAILABLE <<< "$(get_memory_info)"
MEM_TOTAL=${MEM_TOTAL:-0}
MEM_AVAILABLE=${MEM_AVAILABLE:-0}

IFS='|' read -r DISK_TOTAL DISK_AVAILABLE <<< "$(get_disk_info)"
DISK_TOTAL=${DISK_TOTAL:-0}
DISK_AVAILABLE=${DISK_AVAILABLE:-0}

UPTIME=$(get_uptime_days)
LOAD=$(get_load_average)
MAIL_QUEUE=$(get_mail_queue)
TIMESTAMP=$(date +%s)

curl -s -X POST "$MASTER_URL" \
    --max-time "$TIMEOUT" \
    --connect-timeout 5 \
    -d "server_id=$SERVER_ID" \
    -d "api_key=$API_KEY" \
    -d "uptime_days=$UPTIME" \
    -d "load_average=$LOAD" \
    -d "mem_total=$MEM_TOTAL" \
    -d "mem_available=$MEM_AVAILABLE" \
    -d "disk_total=$DISK_TOTAL" \
    -d "disk_available=$DISK_AVAILABLE" \
    -d "mail_queue=$MAIL_QUEUE" \
    -d "timestamp=$TIMESTAMP" >/dev/null 2>&1

exit 0
EOF

# Replace placeholders
sed -i "s|__MASTER_URL__|$MASTER_URL|g" "$AGENT_SCRIPT"
sed -i "s|__SERVER_ID__|$SERVER_ID|g" "$AGENT_SCRIPT"
sed -i "s|__API_KEY__|$API_KEY|g" "$AGENT_SCRIPT"

# Make executable
chmod +x "$AGENT_SCRIPT"
chmod 600 "$AGENT_SCRIPT"  # Restrict permissions for security

echo -e "${GREEN}[✓] Agent script installed to $AGENT_SCRIPT${NC}"

# ============== SETUP CRONTAB ==============

echo -e "\n${YELLOW}[*] Setting up cron job...${NC}"

# Create a temporary crontab file
CRON_JOB="* * * * * $AGENT_SCRIPT >/dev/null 2>&1"

# Check if cron job already exists
if crontab -l 2>/dev/null | grep -q "$AGENT_SCRIPT"; then
    echo -e "${YELLOW}[!] Cron job already exists${NC}"
else
    # Add new cron job
    (crontab -l 2>/dev/null; echo "$CRON_JOB") | crontab -
    echo -e "${GREEN}[✓] Cron job added${NC}"
fi

# ============== SETUP LOG ROTATION ==============

echo -e "\n${YELLOW}[*] Setting up log rotation...${NC}"

cat > /etc/logrotate.d/serverstatus-agent << EOF
$LOG_FILE {
    daily
    rotate 7
    compress
    delaycompress
    notifempty
    create 0644 root root
    sharedscripts
}
EOF

echo -e "${GREEN}[✓] Log rotation configured${NC}"

# ============== VERIFY INSTALLATION ==============

echo -e "\n${YELLOW}[*] Verifying installation...${NC}"

# Test the script
echo -e "${YELLOW}[*] Running first data collection...${NC}"
$AGENT_SCRIPT

echo -e "${GREEN}[✓] Installation complete!${NC}"

# ============== SUMMARY ==============

echo -e "\n${GREEN}================================${NC}"
echo -e "${GREEN}Installation Summary${NC}"
echo -e "${GREEN}================================${NC}"
echo -e "Agent Location: ${YELLOW}$AGENT_SCRIPT${NC}"
echo -e "Log Location: ${YELLOW}$LOG_FILE${NC}"
echo -e "Cron Schedule: ${YELLOW}Every minute${NC}"
echo -e "Master URL: ${YELLOW}$MASTER_URL${NC}"
echo -e "Server ID: ${YELLOW}$SERVER_ID${NC}"
echo ""
echo -e "${YELLOW}To view logs:${NC}"
echo "  tail -f $LOG_FILE"
echo ""
echo -e "${YELLOW}To uninstall:${NC}"
echo "  crontab -e  (remove the line with $AGENT_SCRIPT)"
echo "  rm -rf $INSTALL_DIR"
echo ""

exit 0
