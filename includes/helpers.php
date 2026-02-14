<?php
/**
 * Helper Functions for Server Monitoring Dashboard
 */

/**
 * Get status badge color based on value
 */
function getStatusColor($value, $type = 'percentage') {
    if ($type === 'percentage') {
        if ($value >= 85) return 'danger';
        if ($value >= 70) return 'warning';
        return 'success';
    }
    
    if ($type === 'load') {
        if ($value > 2.0) return 'danger';
        if ($value > 1.0) return 'warning';
        return 'success';
    }
    
    if ($type === 'queue') {
        if ($value > 500) return 'danger';
        if ($value > 100) return 'warning';
        return 'success';
    }
    
    return 'secondary';
}

/**
 * Get status icon
 */
function getStatusIcon($isOnline) {
    return $isOnline ? 'check-circle' : 'exclamation-circle';
}

/**
 * Convert KB to GB
 */
function kbToGb($kb) {
    return round($kb / 1024 / 1024, 2);
}

/**
 * Get human-readable time difference
 */
function getTimeDifference($timestamp) {
    $diff = time() - $timestamp;
    
    if ($diff < 60) return $diff . 's ago';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    
    return floor($diff / 86400) . 'd ago';
}

/**
 * Validate API key format (optional - can be customized)
 */
function validateApiKey($apiKey) {
    return strlen($apiKey) >= 16 && preg_match('/^[a-zA-Z0-9_-]+$/', $apiKey);
}

?>
