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
                
                $level_name = sanitizeInput($_POST['level_name'] ?? '');
                $level_code = sanitizeInput($_POST['level_code'] ?? '');
                $description = sanitizeInput($_POST['description'] ?? '');
                
                if (empty($level_name) || empty($level_code)) {
                    $message = 'Level name and code are required.';
                    $message_type = 'error';
                } else {
                    $stmt = $conn->prepare("INSERT INTO levels (level_name, level_code, description, status) VALUES (?, ?, ?, 'active')");
                    
                    if ($stmt) {
                        $stmt->bind_param("sss", $level_name, $level_code, $description);
                        
                        if ($stmt->execute()) {
                            $level_id = $stmt->insert_id;
                            logAudit($conn, $user_id, 'CREATE', 'LEVEL', $level_id, null, 
                                array('name' => $level_name, 'code' => $level_code));
                            $message = 'Level added successfully.';
                            $message_type = 'success';
                        } else {
                            $message = 'Error adding level. Code might be duplicate.';
                            $message_type = 'error';
                        }
                        $stmt->close();
                    }
                }
            }
            
            else if ($_POST['action'] === 'update') {
                
                $level_id = intval($_POST['level_id'] ?? 0);
                $level_name = sanitizeInput($_POST['level_name'] ?? '');
                $level_code = sanitizeInput($_POST['level_code'] ?? '');
                $description = sanitizeInput($_POST['description'] ?? '');
                
                if (empty($level_id) || empty($level_name) || empty($level_code)) {
                    $message = 'All fields are required.';
                    $message_type = 'error';
                } else {
                    $stmt = $conn->prepare("UPDATE levels SET level_name=?, level_code=?, description=? WHERE id=?");
                    
                    if ($stmt) {
                        $stmt->bind_param("sssi", $level_name, $level_code, $description, $level_id);
                        
                        if ($stmt->execute()) {
                            logAudit($conn, $user_id, 'UPDATE', 'LEVEL', $level_id, null, 
                                array('name' => $level_name, 'code' => $level_code));
                            $message = 'Level updated successfully.';
                            $message_type = 'success';
                        } else {
                            $message = 'Error updating level.';
                            $message_type = 'error';
                        }
                        $stmt->close();
                    }
                }
            }
            
            else if ($_POST['action'] === 'delete') {
                
                $level_id = intval($_POST['level_id'] ?? 0);
                
                if ($level_id <= 0) {
                    $message = 'Invalid level.';
                    $message_type = 'error';
                } else {
                    $stmt = $conn->prepare("UPDATE levels SET status='inactive' WHERE id=?");
                    
                    if ($stmt) {
                        $stmt->bind_param("i", $level_id);
                        
                        if ($stmt->execute()) {
                            logAudit($conn, $user_id, 'DELETE', 'LEVEL', $level_id);
                            $message = 'Level deleted successfully.';
                            $message_type = 'success';
                        } else {
                            $message = 'Error deleting level.';
                            $message_type = 'error';
                        }
                        $stmt->close();
                    }
                }
            }
        }
    }
}

$levels = [];
$stmt = $conn->prepare("SELECT id, level_name, level_code, description, status, created_at FROM levels ORDER BY level_code ASC");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    $levels = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Levels - ATGS Admin</title>
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
                <li><a href="levels.php" class="active">Levels</a></li>
                <li><a href="students.php">Students</a></li>
                <li><a href="courses.php">Courses</a></li>
                <li><a href="halls.php">Halls</a></li>
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
                    <h1>Level Management</h1>
                    <p>Manage academic levels</p>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <div style="margin-bottom: 30px;">
                    <button onclick="toggleForm()" class="btn btn-primary">+ Add Level</button>
                </div>
                
                <div id="levelForm" class="form-container" style="display: none; margin-bottom: 30px;">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" id="formAction" value="add">
                        <input type="hidden" name="level_id" id="levelId" value="">
                        
                        <div class="form-group">
                            <label for="levelName">Level Name *</label>
                            <input type="text" id="levelName" name="level_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="levelCode">Level Code *</label>
                            <input type="text" id="levelCode" name="level_code" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description"></textarea>
                        </div>
                        
                        <div class="button-group">
                            <button type="submit" class="btn btn-success">Save Level</button>
                            <button type="button" onclick="toggleForm()" class="btn btn-secondary">Cancel</button>
                        </div>
                    </form>
                </div>
                
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Level Name</th>
                                <th>Code</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($levels) > 0): ?>
                                <?php foreach ($levels as $level): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($level['level_name']); ?></td>
                                        <td><?php echo htmlspecialchars($level['level_code']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($level['description'] ?? '', 0, 50)); ?></td>
                                        <td>
                                            <span style="padding: 4px 8px; border-radius: 4px; 
                                                <?php echo $level['status'] === 'active' ? 'background: #e8f5e9; color: #2e7d32;' : 'background: #ffebee; color: #c62828;'; ?>">
                                                <?php echo ucfirst($level['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d M Y', strtotime($level['created_at'])); ?></td>
                                        <td>
                                            <div class="table-actions">
                                                <button onclick="editLevel(<?php echo $level['id']; ?>, '<?php echo htmlspecialchars($level['level_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($level['level_code'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($level['description'] ?? '', ENT_QUOTES); ?>')" class="btn btn-sm btn-secondary">Edit</button>
                                                <form method="POST" action="" style="display: inline;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="level_id" value="<?php echo $level['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: #999;">No levels found.</td>
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
            const form = document.getElementById('levelForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
            if (form.style.display === 'block') {
                document.getElementById('formAction').value = 'add';
                document.getElementById('levelId').value = '';
                document.getElementById('levelName').value = '';
                document.getElementById('levelCode').value = '';
                document.getElementById('description').value = '';
                document.getElementById('levelName').focus();
            }
        }
        
        function editLevel(id, name, code, desc) {
            document.getElementById('formAction').value = 'update';
            document.getElementById('levelId').value = id;
            document.getElementById('levelName').value = name;
            document.getElementById('levelCode').value = code;
            document.getElementById('description').value = desc;
            document.getElementById('levelForm').style.display = 'block';
            document.getElementById('levelName').focus();
        }
    </script>
</body>
</html>
