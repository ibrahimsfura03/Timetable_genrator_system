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
        if (isset($_POST['bulk_action']) && isset($_POST['student_ids'])) {
            $student_ids = array_map('intval', (array)$_POST['student_ids']);
            $bulk_action = sanitizeInput($_POST['bulk_action']);
            
            if (count($student_ids) > 0) {
                $id_placeholders = implode(',', $student_ids);
                $action_count = 0;
                
                if ($bulk_action === 'bulk_approve') {
                    $stmt = $conn->prepare("UPDATE students SET status = 'approved' WHERE id IN (" . $id_placeholders . ")");
                    if ($stmt) {
                        $stmt->execute();
                        $action_count = $stmt->affected_rows;
                        $stmt->close();
                        foreach ($student_ids as $sid) {
                            logAudit($conn, $user_id, 'BULK_APPROVE', 'STUDENT', $sid);
                        }
                        $message = "$action_count student(s) approved successfully.";
                        $message_type = 'success';
                    }
                } elseif ($bulk_action === 'bulk_reject') {
                    $stmt = $conn->prepare("UPDATE students SET status = 'rejected' WHERE id IN (" . $id_placeholders . ")");
                    if ($stmt) {
                        $stmt->execute();
                        $action_count = $stmt->affected_rows;
                        $stmt->close();
                        foreach ($student_ids as $sid) {
                            logAudit($conn, $user_id, 'BULK_REJECT', 'STUDENT', $sid);
                        }
                        $message = "$action_count student(s) rejected.";
                        $message_type = 'success';
                    }
                } elseif ($bulk_action === 'bulk_block') {
                    $stmt = $conn->prepare("UPDATE students SET status = 'blocked' WHERE id IN (" . $id_placeholders . ")");
                    if ($stmt) {
                        $stmt->execute();
                        $action_count = $stmt->affected_rows;
                        $stmt->close();
                        foreach ($student_ids as $sid) {
                            logAudit($conn, $user_id, 'BULK_BLOCK', 'STUDENT', $sid);
                        }
                        $message = "$action_count student(s) blocked.";
                        $message_type = 'success';
                    }
                } elseif ($bulk_action === 'bulk_delete') {
                    $stmt = $conn->prepare("DELETE FROM students WHERE id IN (" . $id_placeholders . ")");
                    if ($stmt) {
                        $stmt->execute();
                        $action_count = $stmt->affected_rows;
                        $stmt->close();
                        foreach ($student_ids as $sid) {
                            logAudit($conn, $user_id, 'BULK_DELETE', 'STUDENT', $sid);
                        }
                        $message = "$action_count student(s) deleted.";
                        $message_type = 'success';
                    }
                }
            } else {
                $message = 'Please select at least one student.';
                $message_type = 'error';
            }
        } elseif (isset($_POST['action'])) {
            if ($_POST['action'] === 'approve') {
                $student_id = intval($_POST['student_id'] ?? 0);
                if ($student_id > 0) {
                    $stmt = $conn->prepare("UPDATE students SET status = 'approved' WHERE id = ?");
                    if ($stmt) {
                        $stmt->bind_param("i", $student_id);
                        if ($stmt->execute()) {
                            logAudit($conn, $user_id, 'APPROVE', 'STUDENT', $student_id);
                            $message = 'Student approved successfully.';
                            $message_type = 'success';
                        }
                        $stmt->close();
                    }
                }
            } elseif ($_POST['action'] === 'reject') {
                $student_id = intval($_POST['student_id'] ?? 0);
                if ($student_id > 0) {
                    $stmt = $conn->prepare("UPDATE students SET status = 'rejected' WHERE id = ?");
                    if ($stmt) {
                        $stmt->bind_param("i", $student_id);
                        if ($stmt->execute()) {
                            logAudit($conn, $user_id, 'REJECT', 'STUDENT', $student_id);
                            $message = 'Student rejected.';
                            $message_type = 'success';
                        }
                        $stmt->close();
                    }
                }
            } elseif ($_POST['action'] === 'block') {
                $student_id = intval($_POST['student_id'] ?? 0);
                if ($student_id > 0) {
                    $stmt = $conn->prepare("UPDATE students SET status = 'blocked' WHERE id = ?");
                    if ($stmt) {
                        $stmt->bind_param("i", $student_id);
                        if ($stmt->execute()) {
                            logAudit($conn, $user_id, 'BLOCK', 'STUDENT', $student_id);
                            $message = 'Student blocked.';
                            $message_type = 'success';
                        }
                        $stmt->close();
                    }
                }
            } elseif ($_POST['action'] === 'delete') {
                $student_id = intval($_POST['student_id'] ?? 0);
                if ($student_id > 0) {
                    $stmt = $conn->prepare("DELETE FROM students WHERE id = ?");
                    if ($stmt) {
                        $stmt->bind_param("i", $student_id);
                        if ($stmt->execute()) {
                            logAudit($conn, $user_id, 'DELETE', 'STUDENT', $student_id);
                            $message = 'Student deleted.';
                            $message_type = 'success';
                        }
                        $stmt->close();
                    }
                }
            } elseif ($_POST['action'] === 'reset_password') {
                $student_id = intval($_POST['student_id'] ?? 0);
                $new_password = 'password123';
                if ($student_id > 0) {
                    $stmt = $conn->prepare("UPDATE students SET password = ? WHERE id = ?");
                    if ($stmt) {
                        $stmt->bind_param("si", $new_password, $student_id);
                        if ($stmt->execute()) {
                            logAudit($conn, $user_id, 'RESET_PASSWORD', 'STUDENT', $student_id);
                            $message = 'Student password reset to: password123';
                            $message_type = 'success';
                        }
                        $stmt->close();
                    }
                }
            }
        }
    }
}

$filter_status = sanitizeInput($_GET['status'] ?? '');
$search_term = sanitizeInput($_GET['search'] ?? '');

$query = "
    SELECT s.id, s.registration_number, s.full_name, s.status, s.created_at,
           d.department_name, l.level_name
    FROM students s
    JOIN departments d ON s.department_id = d.id
    JOIN levels l ON s.level_id = l.id
    WHERE 1=1
";

if (!empty($filter_status)) {
    $query .= " AND s.status = '$filter_status'";
}

if (!empty($search_term)) {
    $query .= " AND (s.registration_number LIKE '%$search_term%' OR s.full_name LIKE '%$search_term%')";
}

$query .= " ORDER BY s.created_at DESC";

$students = [];
$result = $conn->query($query);
if ($result) {
    $students = $result->fetch_all(MYSQLI_ASSOC);
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management - ATGS Admin</title>
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
                <li><a href="students.php" class="active">Students</a></li>
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
                    <h1>Student Management</h1>
                    <p>Approve, reject, and manage student accounts</p>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <div class="search-container">
                    <form method="GET" action="" style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <input type="text" name="search" placeholder="Search by reg number or name" value="<?php echo htmlspecialchars($search_term); ?>" style="padding: 10px 12px; border: 1px solid #ddd; border-radius: 4px; flex: 1; min-width: 200px;">
                        <select name="status" style="padding: 10px 12px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $filter_status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $filter_status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            <option value="blocked" <?php echo $filter_status === 'blocked' ? 'selected' : ''; ?>>Blocked</option>
                        </select>
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="students.php" class="btn btn-secondary">Clear</a>
                    </form>
                </div>
                
                <div class="table-container" style="margin-top: 20px;">
                    <form method="POST" action="" id="bulkForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <div style="display: flex; gap: 10px; margin-bottom: 15px; align-items: center;">
                            <select name="bulk_action" id="bulkAction" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
                                <option value="">Select Bulk Action</option>
                                <option value="bulk_approve">Approve Selected</option>
                                <option value="bulk_reject">Reject Selected</option>
                                <option value="bulk_block">Block Selected</option>
                                <option value="bulk_delete">Delete Selected</option>
                            </select>
                            <button type="button" onclick="executeBulkAction()" class="btn btn-primary">Apply</button>
                            <span id="selectedCount" style="color: #666; font-size: 14px;"></span>
                        </div>
                        
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 40px;">
                                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)" style="cursor: pointer;">
                                    </th>
                                    <th>Registration Number</th>
                                    <th>Full Name</th>
                                    <th>Department</th>
                                    <th>Level</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($students) > 0): ?>
                                    <?php foreach ($students as $student): ?>
                                        <tr>
                                            <td style="width: 40px;">
                                                <input type="checkbox" name="student_ids[]" value="<?php echo $student['id']; ?>" class="student-checkbox" onchange="updateSelectedCount()" style="cursor: pointer;">
                                            </td>
                                            <td><?php echo htmlspecialchars($student['registration_number']); ?></td>
                                            <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($student['department_name']); ?></td>
                                            <td><?php echo htmlspecialchars($student['level_name']); ?></td>
                                            <td>
                                                <span style="padding: 4px 8px; border-radius: 4px; 
                                                    <?php
                                                        $bg = '#fff3e0'; $color = '#e65100';
                                                        if ($student['status'] === 'approved') { $bg = '#e8f5e9'; $color = '#2e7d32'; }
                                                        elseif ($student['status'] === 'rejected') { $bg = '#ffebee'; $color = '#c62828'; }
                                                        elseif ($student['status'] === 'blocked') { $bg = '#f3e5f5'; $color = '#6a1b9a'; }
                                                        echo "background: $bg; color: $color;";
                                                    ?>
                                                ">
                                                    <?php echo ucfirst($student['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d M Y', strtotime($student['created_at'])); ?></td>
                                            <td>
                                                <div class="table-actions" style="flex-wrap: wrap;">
                                                    <?php if ($student['status'] === 'pending'): ?>
                                                        <form method="POST" action="" style="display: inline;">
                                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                            <input type="hidden" name="action" value="approve">
                                                            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                                        </form>
                                                        <form method="POST" action="" style="display: inline;">
                                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                            <input type="hidden" name="action" value="reject">
                                                            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-danger">Reject</button>
                                                        </form>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($student['status'] !== 'blocked'): ?>
                                                        <form method="POST" action="" style="display: inline;">
                                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                            <input type="hidden" name="action" value="block">
                                                            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Block this student?')">Block</button>
                                                        </form>
                                                    <?php endif; ?>
                                                    
                                                    <form method="POST" action="" style="display: inline;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                        <input type="hidden" name="action" value="reset_password">
                                                        <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-secondary">Reset Password</button>
                                                    </form>
                                                    
                                                    <form method="POST" action="" style="display: inline;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete this student?')">Delete</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center; color: #999;">No students found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </form>
                </div>
                
                <script>
                    function toggleSelectAll(checkbox) {
                        const checkboxes = document.querySelectorAll('.student-checkbox');
                        checkboxes.forEach(cb => cb.checked = checkbox.checked);
                        updateSelectedCount();
                    }
                    
                    function updateSelectedCount() {
                        const selected = document.querySelectorAll('.student-checkbox:checked').length;
                        const countEl = document.getElementById('selectedCount');
                        if (selected > 0) {
                            countEl.textContent = `${selected} student(s) selected`;
                        } else {
                            countEl.textContent = '';
                        }
                    }
                    
                    function executeBulkAction() {
                        const action = document.getElementById('bulkAction').value;
                        const selected = document.querySelectorAll('.student-checkbox:checked').length;
                        
                        if (!action) {
                            alert('Please select an action');
                            return;
                        }
                        
                        if (selected === 0) {
                            alert('Please select at least one student');
                            return;
                        }
                        
                        const confirmMsg = {
                            'bulk_approve': `Approve ${selected} student(s)?`,
                            'bulk_reject': `Reject ${selected} student(s)?`,
                            'bulk_block': `Block ${selected} student(s)?`,
                            'bulk_delete': `Delete ${selected} student(s)?`
                        };
                        
                        if (confirm(confirmMsg[action])) {
                            document.getElementById('bulkForm').submit();
                        }
                    }
                </script>
            </div>
        </div>
    </div>
</body>
</html>
