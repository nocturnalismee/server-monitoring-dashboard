<?php
/**
 * API Endpoint - Receive monitoring data from bash agents
 * POST /api/receiver.php
 */

header('Content-Type: application/json');
require_once('../includes/config.php');

// ============== REQUEST VALIDATION ==============

function validateRequest() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        return ['status' => 'error', 'message' => 'Method not allowed'];
    }
    
    $required_fields = ['server_id', 'api_key', 'uptime_days', 'load_average', 
                       'mem_total', 'mem_available', 'disk_total', 'disk_available', 'mail_queue'];
    
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field])) {
            http_response_code(400);
            return ['status' => 'error', 'message' => "Missing required field: $field"];
        }
    }
    
    return null; // No error
}

// ============== VERIFY SERVER CREDENTIALS ==============

function verifyServer($db, $hostname, $api_key) {
    $server = $db->getRow(
        "SELECT id, name, is_active FROM servers WHERE hostname = ? AND api_key = ? LIMIT 1",
        "ss",
        [&$hostname, &$api_key]
    );
    
    if (!$server) {
        return null;
    }
    
    if (!$server['is_active']) {
        return ['error' => 'Server is inactive'];
    }
    
    return $server;
}

// ============== MAIN HANDLER ==============

// Validate request
$error = validateRequest();
if ($error) {
    echo json_encode($error);
    exit;
}

// Extract and sanitize input data
$server_id = sanitize($_POST['server_id']);
$api_key = sanitize($_POST['api_key']);
$uptime_days = (int)$_POST['uptime_days'];
$load_average = (float)$_POST['load_average'];
$mem_total = (int)$_POST['mem_total'];
$mem_available = (int)$_POST['mem_available'];
$disk_total = (int)$_POST['disk_total'];
$disk_available = (int)$_POST['disk_available'];
$mail_queue = (int)$_POST['mail_queue'];
$timestamp = (int)($_POST['timestamp'] ?? time());

// Verify server credentials
$server = verifyServer($db, $server_id, $api_key);
if (!$server) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized - Invalid credentials']);
    exit;
}

if (isset($server['error'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => $server['error']]);
    exit;
}

$db_id = $server['id'];

// ============== STORE DATA IN DATABASE ==============

$result = $db->execute(
    "REPLACE INTO servers_status 
    (id, uptime_days, load_average, mem_total, mem_free, disk_total, disk_available, mail_queue, status, last_update_unix) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'online', ?)",
    "idddddddi",
    [
        &$db_id,
        &$uptime_days,
        &$load_average,
        &$mem_total,
        &$mem_available,
        &$disk_total,
        &$disk_available,
        &$mail_queue,
        &$timestamp
    ]
);

if ($result !== false) {
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Data received and stored',
        'server_id' => $db_id,
        'server_name' => $server['name'],
        'timestamp' => $timestamp
    ]);
    logMessage("Data received from server {$server['name']} (ID: {$db_id})");
} else {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to store data: ' . $db->getError()
    ]);
    logMessage("Error storing data for server {$db_id}: " . $db->getError(), 'ERROR');
}

$db->close();
?>
