<?php
/**
 * API Endpoint - Get all servers status
 * GET /api/get_status.php
 */

header('Content-Type: application/json');
require_once('../includes/config.php');

try {
    // Get all active servers with their status
    $servers = $db->getRows(
        "SELECT 
            s.id,
            s.name,
            s.hostname,
            s.location,
            s.host_provider,
            s.server_type,
            ss.uptime_days,
            ss.load_average,
            ss.mem_total,
            ss.mem_free,
            ss.mem_usage_percent,
            ss.disk_total,
            ss.disk_available,
            ss.disk_usage_percent,
            ss.mail_queue,
            ss.last_update,
            ss.last_update_unix
        FROM servers s
        LEFT JOIN servers_status ss ON s.id = ss.id
        WHERE s.is_active = 1
        ORDER BY s.name ASC"
    );
    
    // Check if server is online and add computed fields
    if (is_array($servers)) {
        foreach ($servers as &$server) {
            $is_online = isServerOnline($server['last_update_unix'] ?? 0);
            $server['status'] = $is_online ? 'online' : 'offline';
            $server['time_since_update'] = getTimeDifference($server['last_update_unix'] ?? 0);
            
            // Convert KB to GB
            $server['mem_total_gb'] = kbToGb($server['mem_total'] ?? 0);
            $server['mem_free_gb'] = kbToGb($server['mem_free'] ?? 0);
            $server['disk_total_gb'] = kbToGb($server['disk_total'] ?? 0);
            $server['disk_available_gb'] = kbToGb($server['disk_available'] ?? 0);
        }
    }
    
    // Summary statistics
    $totalServers = count($servers);
    $onlineServers = count(array_filter($servers, function($s) { return $s['status'] === 'online'; }));
    $offlineServers = $totalServers - $onlineServers;
    
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'data' => $servers,
        'summary' => [
            'total_servers' => $totalServers,
            'online_servers' => $onlineServers,
            'offline_servers' => $offlineServers,
            'online_percentage' => $totalServers > 0 ? round(($onlineServers / $totalServers) * 100, 2) : 0
        ],
        'timestamp' => time(),
        'api_version' => API_VERSION
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error: ' . $e->getMessage()
    ]);
    logMessage('Error in get_status.php: ' . $e->getMessage(), 'ERROR');
}

$db->close();
?>
