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
                
                $dept_name = sanitizeInput($_POST['department_name'] ?? '');
                $description = sanitizeInput($_POST['description'] ?? '');
                
                if (empty($dept_name)) {
                    $message = 'Department name is required.';
                    $message_type = 'error';
                } else {
                    $stmt = $conn->prepare("INSERT INTO departments (department_name, description, status) VALUES (?, ?, 'active')");
                    
                    if ($stmt) {
                        $stmt->bind_param("ss", $dept_name, $description);
                        
                        if ($stmt->execute()) {
                            $dept_id = $stmt->insert_id;
                            logAudit($conn, $user_id, 'CREATE', 'DEPARTMENT', $dept_id, null, 
                                array('name' => $dept_name));
                            $message = 'Department added successfully.';
                            $message_type = 'success';
                        } else {
                            $message = 'Error adding department.';
                            $message_type = 'error';
                        }
                        $stmt->close();
                    }
                }
            }
            
            else if ($_POST['action'] === 'update') {
                
                $dept_id = intval($_POST['department_id'] ?? 0);
                $dept_name = sanitizeInput($_POST['department_name'] ?? '');
                $description = sanitizeInput($_POST['description'] ?? '');
                
                if (empty($dept_id) || empty($dept_name)) {
                    $message = 'Department name is required.';
                    $message_type = 'error';
                } else {
                    $stmt = $conn->prepare("UPDATE departments SET department_name=?, description=? WHERE id=?");
                    
                    if ($stmt) {
                        $stmt->bind_param("ssi", $dept_name, $description, $dept_id);
                        
                        if ($stmt->execute()) {
                            logAudit($conn, $user_id, 'UPDATE', 'DEPARTMENT', $dept_id, null, 
                                array('name' => $dept_name));
                            $message = 'Department updated successfully.';
                            $message_type = 'success';
                        } else {
                            $message = 'Error updating department.';
                            $message_type = 'error';
                        }
                        $stmt->close();
                    }
                }
            }
            
            else if ($_POST['action'] === 'delete') {
                
                $dept_id = intval($_POST['department_id'] ?? 0);
                
                if ($dept_id <= 0) {
                    $message = 'Invalid department.';
                    $message_type = 'error';
                } else {
                    $stmt = $conn->prepare("UPDATE departments SET status='inactive' WHERE id=?");
                    
                    if ($stmt) {
                        $stmt->bind_param("i", $dept_id);
                        
                        if ($stmt->execute()) {
                            logAudit($conn, $user_id, 'DELETE', 'DEPARTMENT', $dept_id);
                            $message = 'Department deleted successfully.';
                            $message_type = 'success';
                        } else {
                            $message = 'Error deleting department.';
                            $message_type = 'error';
                        }
                        $stmt->close();
                    }
                }
            }
        }
    }
}

$departments = [];
$stmt = $conn->prepare("SELECT id, department_name, description, status, created_at FROM departments ORDER BY created_at DESC");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    $departments = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Departments - ATGS Admin</title>
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
                <li><a href="departments.php" class="active">Departments</a></li>
                <li><a href="levels.php">Levels</a></li>
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
                    <h1>Department Management</h1>
                    <p>Add, edit, or delete departments</p>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <div style="margin-bottom: 30px;">
                    <button onclick="toggleForm()" class="btn btn-primary">+ Add Department</button>
                </div>
                
                <div id="departmentForm" class="form-container" style="display: none; margin-bottom: 30px;">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" id="formAction" value="add">
                        <input type="hidden" name="department_id" id="departmentId" value="">
                        
                        <div class="form-group">
                            <label for="departmentName">Department Name *</label>
                            <input type="text" id="departmentName" name="department_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description"></textarea>
                        </div>
                        
                        <div class="button-group">
                            <button type="submit" class="btn btn-success">Save Department</button>
                            <button type="button" onclick="toggleForm()" class="btn btn-secondary">Cancel</button>
                        </div>
                    </form>
                </div>
                
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Department Name</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($departments) > 0): ?>
                                <?php foreach ($departments as $dept): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($dept['department_name']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($dept['description'] ?? '', 0, 50)); ?></td>
                                        <td>
                                            <span style="padding: 4px 8px; border-radius: 4px; 
                                                <?php echo $dept['status'] === 'active' ? 'background: #e8f5e9; color: #2e7d32;' : 'background: #ffebee; color: #c62828;'; ?>">
                                                <?php echo ucfirst($dept['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d M Y', strtotime($dept['created_at'])); ?></td>
                                        <td>
                                            <div class="table-actions">
                                                <button onclick="editDepartment(<?php echo $dept['id']; ?>, '<?php echo htmlspecialchars($dept['department_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($dept['description'] ?? '', ENT_QUOTES); ?>')" class="btn btn-sm btn-secondary">Edit</button>
                                                <form method="POST" action="" style="display: inline;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="department_id" value="<?php echo $dept['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: #999;">No departments found. Add one to get started.</td>
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
            const form = document.getElementById('departmentForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
            if (form.style.display === 'block') {
                document.getElementById('formAction').value = 'add';
                document.getElementById('departmentId').value = '';
                document.getElementById('departmentName').value = '';
                document.getElementById('departmentCode').value = '';
                document.getElementById('description').value = '';
                document.getElementById('departmentName').focus();
            }
        }
        
        function editDepartment(id, name, desc) {
            document.getElementById('formAction').value = 'update';
            document.getElementById('departmentId').value = id;
            document.getElementById('departmentName').value = name;
            document.getElementById('description').value = desc;
            document.getElementById('departmentForm').style.display = 'block';
            document.getElementById('departmentName').focus();
        }
    </script>
</body>
</html>