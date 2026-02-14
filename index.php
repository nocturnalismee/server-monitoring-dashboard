<?php
include('./includes/config.php');

// Get all server status
$query = "SELECT s.id, s.name, s.hostname, s.location, s.host_provider, s.server_type,
                 ss.uptime_days, ss.load_average, ss.mem_total, ss.mem_free, ss.mem_usage_percent,
                 ss.disk_total, ss.disk_available, ss.disk_usage_percent, ss.mail_queue,
                 ss.status, ss.last_update, ss.last_update_unix
          FROM servers s
          LEFT JOIN servers_status ss ON s.id = ss.id
          ORDER BY s.name ASC";

$servers = $db->getRows($query);
$db->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ServerStatus Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="templates/default/css/custom.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark sticky-top">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">
                <i class="bi bi-speedometer2"></i> ServerStatus Dashboard
            </span>
            <span class="navbar-text text-muted" id="last-update">Last update: --:--:--</span>
        </div>
    </nav>

    <div class="container-fluid pt-4">
        <div class="row mb-3">
            <div class="col-12">
                <h5>Server Monitoring</h5>
                <p class="text-muted small">Showing <?php echo count($servers); ?> server(s)</p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle">
                <thead class="table-dark sticky-top">
                    <tr>
                        <th style="width: 20%">Server Name</th>
                        <th style="width: 10%">Status</th>
                        <th style="width: 12%">Uptime</th>
                        <th style="width: 12%">Load</th>
                        <th style="width: 12%">RAM Usage</th>
                        <th style="width: 12%">Disk Usage</th>
                        <th style="width: 10%">Mail Queue</th>
                        <th style="width: 12%">Last Update</th>
                    </tr>
                </thead>
                <tbody id="servers-table">
                    <?php foreach ($servers as $server): ?>
                        <tr class="server-row" data-server-id="<?php echo $server['id']; ?>">
                            <td>
                                <strong><?php echo htmlspecialchars($server['name']); ?></strong>
                                <br>
                                <small class="text-muted"><?php echo htmlspecialchars($server['hostname']); ?></small>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo isServerOnline($server['last_update_unix'] ?? 0) ? 'success' : 'danger'; ?>">
                                    <i class="bi bi-<?php echo isServerOnline($server['last_update_unix'] ?? 0) ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                                    <?php echo isServerOnline($server['last_update_unix'] ?? 0) ? 'Online' : 'Offline'; ?>
                                </span>
                            </td>
                            <td>
                                <?php echo $server['uptime_days'] ?? 0; ?> days
                            </td>
                            <td>
                                <span class="badge bg-<?php 
                                    $load = (float)($server['load_average'] ?? 0);
                                    echo $load > 2 ? 'danger' : ($load > 1 ? 'warning' : 'success');
                                ?>">
                                    <?php echo number_format($load, 2); ?>
                                </span>
                            </td>
                            <td>
                                <?php $mem_pct = (float)($server['mem_usage_percent'] ?? 0); ?>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar bg-<?php echo $mem_pct > 80 ? 'danger' : ($mem_pct > 60 ? 'warning' : 'success'); ?>" 
                                         role="progressbar" 
                                         style="width: <?php echo $mem_pct; ?>%"
                                         aria-valuenow="<?php echo $mem_pct; ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                        <?php echo number_format($mem_pct, 1); ?>%
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php $disk_pct = (float)($server['disk_usage_percent'] ?? 0); ?>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar bg-<?php echo $disk_pct > 85 ? 'danger' : ($disk_pct > 70 ? 'warning' : 'success'); ?>" 
                                         role="progressbar" 
                                         style="width: <?php echo $disk_pct; ?>%"
                                         aria-valuenow="<?php echo $disk_pct; ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                        <?php echo number_format($disk_pct, 1); ?>%
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo ($server['mail_queue'] ?? 0) > 100 ? 'warning' : 'info'; ?>">
                                    <?php echo $server['mail_queue'] ?? 0; ?>
                                </span>
                            </td>
                            <td>
                                <small class="text-muted" id="update-<?php echo $server['id']; ?>">
                                    <?php echo $server['last_update'] ? date('H:i:s', strtotime($server['last_update'])) : '--:--:--'; ?>
                                </small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Refresh data setiap 10 detik
        const refreshInterval = <?php echo REFRESH_INTERVAL; ?>;
        
        function refreshStatus() {
            fetch('api/get_status.php')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        updateTable(data.data);
                        updateLastRefreshTime();
                    }
                })
                .catch(error => console.error('Error:', error));
        }
        
        function updateTable(servers) {
            servers.forEach(server => {
                const row = document.querySelector(`tr[data-server-id="${server.id}"]`);
                if (row) {
                    // Update status
                    const statusBadge = row.querySelector('.badge');
                    const isOnline = server.status === 'online';
                    statusBadge.className = `badge bg-${isOnline ? 'success' : 'danger'}`;
                    statusBadge.innerHTML = `<i class="bi bi-${isOnline ? 'check-circle' : 'exclamation-circle'}"></i> ${isOnline ? 'Online' : 'Offline'}`;
                    
                    // Update last update time
                    const updateEl = document.getElementById(`update-${server.id}`);
                    if (updateEl && server.last_update) {
                        updateEl.textContent = new Date(server.last_update).toLocaleTimeString();
                    }
                }
            });
        }
        
        function updateLastRefreshTime() {
            document.getElementById('last-update').textContent = 'Last update: ' + new Date().toLocaleTimeString();
        }
        
        // Initial refresh
        refreshStatus();
        
        // Auto-refresh
        setInterval(refreshStatus, refreshInterval);
    </script>
</body>
</html>
