<?php
include '../config/session.php';
include '../config/db.php';

requireAdmin();
$user_id = getCurrentUser();

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Security token invalid.';
        $message_type = 'error';
    } else {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'add') {
                $name = sanitizeInput($_POST['hall_name'] ?? '');
                $capacity = intval($_POST['capacity'] ?? 0);
                
                if (empty($name) || $capacity <= 0) {
                    $message = 'Hall name and capacity are required.';
                    $message_type = 'error';
                } else {
                    $stmt = $conn->prepare("INSERT INTO halls (hall_name, capacity, status) VALUES (?, ?, 'active')");
                    if ($stmt) {
                        $stmt->bind_param("si", $name, $capacity);
                        if ($stmt->execute()) {
                            $hall_id = $stmt->insert_id;
                            logAudit($conn, $user_id, 'CREATE', 'HALL', $hall_id, null, array('name' => $name));
                            $message = 'Hall added successfully.';
                            $message_type = 'success';
                        } else {
                            $message = 'Error adding hall.';
                            $message_type = 'error';
                        }
                        $stmt->close();
                    }
                }
            }
            else if ($_POST['action'] === 'delete') {
                $hall_id = intval($_POST['hall_id'] ?? 0);
                if ($hall_id <= 0) {
                    $message = 'Invalid hall.';
                    $message_type = 'error';
                } else {
                    $stmt = $conn->prepare("UPDATE halls SET status='inactive' WHERE id=?");
                    if ($stmt) {
                        $stmt->bind_param("i", $hall_id);
                        if ($stmt->execute()) {
                            logAudit($conn, $user_id, 'DELETE', 'HALL', $hall_id);
                            $message = 'Hall deleted successfully.';
                            $message_type = 'success';
                        } else {
                            $message = 'Error deleting hall.';
                            $message_type = 'error';
                        }
                        $stmt->close();
                    }
                }
            }
        }
    }
}

$halls = [];
$stmt = $conn->prepare("SELECT id, hall_name, capacity, status, created_at FROM halls WHERE status = 'active' ORDER BY hall_name ASC");
if ($stmt) {
    $stmt->execute();
    $halls = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Halls - ATGS Admin</title>
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
                <li><a href="halls.php" class="active">Halls</a></li>
                <li><a href="timeslots.php">Time Slots</a></li>
                <li><a href="timetable.php">Timetables</a></li>
                <li><a href="generate.php">Generate Timetable</a></li>
                <li><a href="audit_logs.php">Audit Logs</a></li>
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
                    <h1>Hall Management</h1>
                    <p>Add, edit, or delete lecture halls</p>
                </div>
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                <div style="margin-bottom: 30px;">
                    <button onclick="toggleForm()" class="btn btn-primary">+ Add Hall</button>
                </div>
                <div id="hallForm" class="form-container" style="display: none; margin-bottom: 30px;">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="add">
                        <div class="form-group">
                            <label for="hallName">Hall Name *</label>
                            <input type="text" id="hallName" name="hall_name" placeholder="e.g., Lecture Theater 1" required>
                        </div>
                        <div class="form-group">
                            <label for="capacity">Capacity *</label>
                            <input type="number" id="capacity" name="capacity" placeholder="e.g., 100" min="1" required>
                        </div>
                        <div class="button-group">
                            <button type="submit" class="btn btn-success">Save Hall</button>
                            <button type="button" onclick="toggleForm()" class="btn btn-secondary">Cancel</button>
                        </div>
                    </form>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Hall Name</th>
                                <th>Capacity</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($halls) > 0): ?>
                                <?php foreach ($halls as $hall): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($hall['hall_name']); ?></td>
                                        <td><?php echo $hall['capacity']; ?> seats</td>
                                        <td><span style="padding: 4px 8px; border-radius: 4px; background: #e8f5e9; color: #2e7d32;">Active</span></td>
                                        <td><?php echo date('d M Y', strtotime($hall['created_at'])); ?></td>
                                        <td>
                                            <form method="POST" action="" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="hall_id" value="<?php echo $hall['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: #999;">No halls found. Add one to get started.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script>
        function toggleForm() {
            document.getElementById('hallForm').style.display = document.getElementById('hallForm').style.display === 'none' ? 'block' : 'none';
        }
    </script>
</body>
</html>
