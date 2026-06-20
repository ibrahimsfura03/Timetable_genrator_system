<?php
include '../config/session.php';
include '../config/db.php';

requireAdmin();

$message = '';
$message_type = '';

// Handle clear logs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_logs') {
    $csrf_token_check = $_POST['csrf_token'] ?? '';
    if (validateCSRFToken($csrf_token_check)) {
        $stmt = $conn->prepare("DELETE FROM audit_logs");
        if ($stmt) {
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            $message = "Cleared $affected audit log record(s).";
            $message_type = 'success';
        }
    } else {
        $message = 'Security token invalid.';
        $message_type = 'error';
    }
}

$logs = array();
$filter_action = sanitizeInput($_GET['filter_action'] ?? '');
$filter_entity = sanitizeInput($_GET['filter_entity'] ?? '');

$query = "SELECT al.id, al.action, al.entity_type, al.entity_id, al.created_at, u.fullname 
          FROM audit_logs al 
          LEFT JOIN users u ON al.user_id = u.id";

$conditions = array();
if (!empty($filter_action)) {
    $conditions[] = "al.action = '$filter_action'";
}
if (!empty($filter_entity)) {
    $conditions[] = "al.entity_type = '$filter_entity'";
}

if (count($conditions) > 0) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

$query .= " ORDER BY al.created_at DESC LIMIT 500";

$result = $conn->query($query);
if ($result) {
    $logs = $result->fetch_all(MYSQLI_ASSOC);
}

$actions = array();
$result = $conn->query("SELECT DISTINCT action FROM audit_logs ORDER BY action ASC");
if ($result) {
    $actions = $result->fetch_all(MYSQLI_ASSOC);
}

$entities = array();
$result = $conn->query("SELECT DISTINCT entity_type FROM audit_logs ORDER BY entity_type ASC");
if ($result) {
    $entities = $result->fetch_all(MYSQLI_ASSOC);
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs - ATGS Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="main-wrapper">
        <div class="sidebar">
            <div class="sidebar-header">
                <h3>ATGS</h3>
                <p>Admin Panel</p>
            </div>
            <ul class="sidebar-nav">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="departments.php">Departments</a></li>
                <li><a href="levels.php">Levels</a></li>
                <li><a href="students.php">Students</a></li>
                <li><a href="courses.php">Courses</a></li>
                <li><a href="halls.php">Halls</a></li>
                <li><a href="timeslots.php">Time Slots</a></li>
                <li><a href="timetable.php">Timetables</a></li>
                <li><a href="generate.php">Generate Timetable</a></li>
                <li><a href="audit_logs.php" class="active">Audit Logs</a></li>
                <li><a href="settings.php">Settings</a></li>
            </ul>
            <div class="sidebar-logout">
                <a href="../config/logout.php">Logout</a>
            </div>
        </div>
        <div style="margin-left: 240px;">
            <div class="topbar">
                <div class="topbar-info">
                    <span>Welcome, <strong><?php echo htmlspecialchars(getUserName()); ?></strong></span>
                </div>
                <div class="topbar-user">
                    <span><?php echo date('d M Y, H:i'); ?></span>
                </div>
            </div>
            <div class="content">
                <div class="page-header">
                    <h1>Audit Logs</h1>
                    <p>System activity and user actions log</p>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <div class="search-container" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center; justify-content: space-between;">
                    <form method="GET" action="" style="display: flex; gap: 10px; flex-wrap: wrap; flex: 1;">
                        <select name="filter_action" style="padding: 10px 12px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="">All Actions</option>
                            <?php foreach ($actions as $action): ?>
                                <option value="<?php echo htmlspecialchars($action['action']); ?>" <?php echo ($filter_action === $action['action']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($action['action']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select name="filter_entity" style="padding: 10px 12px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="">All Entities</option>
                            <?php foreach ($entities as $entity): ?>
                                <option value="<?php echo htmlspecialchars($entity['entity_type']); ?>" <?php echo ($filter_entity === $entity['entity_type']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($entity['entity_type']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="audit_logs.php" class="btn btn-secondary">Clear Filters</a>
                    </form>
                    
                    <form method="POST" action="" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="clear_logs">
                        <button type="submit" class="btn btn-danger" onclick="return confirm('Delete all audit logs? This cannot be undone.')">Clear All Logs</button>
                    </form>
                </div>
                
                <div class="table-container" style="margin-top: 20px;">
                    <table>
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Action</th>
                                <th>Entity Type</th>
                                <th>Entity ID</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($logs) > 0): ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($log['fullname'] ?? 'System'); ?></td>
                                        <td>
                                            <span style="padding: 4px 8px; border-radius: 4px; 
                                                <?php
                                                    $bg = '#e3f2fd'; $color = '#1565c0';
                                                    if ($log['action'] === 'CREATE') { $bg = '#e8f5e9'; $color = '#2e7d32'; }
                                                    elseif ($log['action'] === 'UPDATE') { $bg = '#fff3e0'; $color = '#e65100'; }
                                                    elseif ($log['action'] === 'DELETE') { $bg = '#ffebee'; $color = '#c62828'; }
                                                    echo "background: $bg; color: $color;";
                                                ?>
                                            ">
                                                <?php echo htmlspecialchars($log['action']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($log['entity_type']); ?></td>
                                        <td><?php echo $log['entity_id']; ?></td>
                                        <td><?php echo date('d M Y H:i:s', strtotime($log['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: #999;">No audit logs found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
