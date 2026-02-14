#!/bin/bash

################################################################################
# Server Monitoring Agent v2.0
# Deploy to remote servers and run via cronjob
# Cron: * * * * * /opt/serverstatus/uptime.sh >/dev/null 2>&1
################################################################################

set -o pipefail

# ============== CONFIGURATION ==============
MASTER_URL="${MASTER_URL:-http://your.master.server/api/receiver.php}"
SERVER_ID="${SERVER_ID:-$(hostname -s)}"
API_KEY="${API_KEY:-your_unique_api_key_here}"
TIMEOUT=10
LOG_FILE="/var/log/serverstatus-agent.log"

# ============== FUNCTIONS ==============

# Get uptime in days
get_uptime_days() {
    awk '{print int($1/86400)}' /proc/uptime 2>/dev/null || echo "0"
}

# Get 1-minute load average
get_load_average() {
    awk '{printf "%.2f", $1}' /proc/loadavg 2>/dev/null || echo "0.00"
}

# Get memory information (total|available)
get_memory_info() {
    local mem_total=$(awk '/MemTotal:/ {print $2}' /proc/meminfo 2>/dev/null || echo "0")
    local mem_available=$(awk '/MemAvailable:/ {print $2}' /proc/meminfo 2>/dev/null || echo "0")
    echo "$mem_total|$mem_available"
}

# Get disk information (total|available)
get_disk_info() {
    local disk_info=$(df / 2>/dev/null | awk 'NR==2 {print $2"|"$4}')
    echo "$disk_info"
}

# Get mail queue count (supports Exim, Postfix, Sendmail)
get_mail_queue() {
    local queue=0
    
    # Check Exim
    if command -v exim &> /dev/null; then
        queue=$(exim -bpc 2>/dev/null | tail -1)
        queue=${queue//[^0-9]/}  # Extract numbers only
        echo "${queue:-0}"
        return
    fi
    
    # Check Postfix
    if command -v postqueue &> /dev/null; then
        queue=$(postqueue -p 2>/dev/null | tail -n +2 | grep -v '^-' | wc -l)
        echo "${queue:-0}"
        return
    fi
    
    # Check Sendmail
    if [ -d "/var/spool/mqueue" ]; then
        queue=$(find /var/spool/mqueue -type f | wc -l)
        echo "${queue:-0}"
        return
    fi
    
    echo "0"
}

# Log message (optional)
log_message() {
    if [ -w "$(dirname "$LOG_FILE")" ]; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOG_FILE"
    fi
}

# ============== COLLECT METRICS ==============

# Parse memory info
IFS='|' read -r MEM_TOTAL MEM_AVAILABLE <<< "$(get_memory_info)"
MEM_TOTAL=${MEM_TOTAL:-0}
MEM_AVAILABLE=${MEM_AVAILABLE:-0}

# Parse disk info
IFS='|' read -r DISK_TOTAL DISK_AVAILABLE <<< "$(get_disk_info)"
DISK_TOTAL=${DISK_TOTAL:-0}
DISK_AVAILABLE=${DISK_AVAILABLE:-0}

# Get other metrics
UPTIME=$(get_uptime_days)
LOAD=$(get_load_average)
MAIL_QUEUE=$(get_mail_queue)
TIMESTAMP=$(date +%s)

# ============== VALIDATE DATA ==============

# Check if metrics are valid numbers
if ! [[ "$UPTIME" =~ ^[0-9]+$ ]] || ! [[ "$LOAD" =~ ^[0-9.]+$ ]]; then
    log_message "ERROR: Invalid metric values collected"
    exit 1
fi

# ============== SEND DATA TO MASTER ==============

response=$(curl -s \
    -X POST "$MASTER_URL" \
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
    -d "timestamp=$TIMESTAMP" \
    2>&1)

exit_code=$?

if [ $exit_code -eq 0 ]; then
    log_message "SUCCESS: Data sent to master server"
else
    log_message "ERROR: Failed to send data (exit code: $exit_code)"
fi

exit 0
