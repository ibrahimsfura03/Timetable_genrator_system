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
        if (isset($_POST['bulk_action']) && isset($_POST['course_ids'])) {
            $course_ids = array_map('intval', (array)$_POST['course_ids']);
            $bulk_action = sanitizeInput($_POST['bulk_action']);
            
            if (count($course_ids) > 0) {
                $id_placeholders = implode(',', $course_ids);
                $action_count = 0;
                
                if ($bulk_action === 'bulk_delete') {
                    $stmt = $conn->prepare("UPDATE courses SET status = 'inactive' WHERE id IN (" . $id_placeholders . ")");
                    if ($stmt) {
                        $stmt->execute();
                        $action_count = $stmt->affected_rows;
                        $stmt->close();
                        foreach ($course_ids as $cid) {
                            logAudit($conn, $user_id, 'BULK_DELETE', 'COURSE', $cid);
                        }
                        $message = "$action_count course(s) deleted successfully.";
                        $message_type = 'success';
                    }
                }
            } else {
                $message = 'Please select at least one course.';
                $message_type = 'error';
            }
        } elseif (isset($_POST['action'])) {
            
            if ($_POST['action'] === 'add') {
                $code = sanitizeInput($_POST['course_code'] ?? '');
                $title = sanitizeInput($_POST['course_title'] ?? '');
                $dept_id = intval($_POST['department_id'] ?? 0);
                $level_id = intval($_POST['level_id'] ?? 0);
                $credits = intval($_POST['credit_units'] ?? 3);
                $duration = sanitizeInput($_POST['duration'] ?? '1 Hour');
                
                if (empty($code) || empty($title) || $dept_id <= 0 || $level_id <= 0) {
                    $message = 'All required fields must be filled.';
                    $message_type = 'error';
                } else {
                    $stmt = $conn->prepare("INSERT INTO courses (course_code, course_title, department_id, level_id, credit_units, duration, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
                    if ($stmt) {
                        $stmt->bind_param("ssiiis", $code, $title, $dept_id, $level_id, $credits, $duration);
                        if ($stmt->execute()) {
                            $course_id = $stmt->insert_id;
                            logAudit($conn, $user_id, 'CREATE', 'COURSE', $course_id, null, array('code' => $code, 'title' => $title));
                            $message = 'Course added successfully.';
                            $message_type = 'success';
                        } else {
                            $message = 'Error adding course.';
                            $message_type = 'error';
                        }
                        $stmt->close();
                    }
                }
            }
            else if ($_POST['action'] === 'update') {
                $course_id = intval($_POST['course_id'] ?? 0);
                $code = sanitizeInput($_POST['course_code'] ?? '');
                $title = sanitizeInput($_POST['course_title'] ?? '');
                $dept_id = intval($_POST['department_id'] ?? 0);
                $level_id = intval($_POST['level_id'] ?? 0);
                $credits = intval($_POST['credit_units'] ?? 3);
                $duration = sanitizeInput($_POST['duration'] ?? '1 Hour');
                
                if (empty($course_id) || empty($code) || empty($title) || $dept_id <= 0 || $level_id <= 0) {
                    $message = 'All required fields must be filled.';
                    $message_type = 'error';
                } else {
                    $stmt = $conn->prepare("UPDATE courses SET course_code=?, course_title=?, department_id=?, level_id=?, credit_units=?, duration=? WHERE id=?");
                    if ($stmt) {
                        $stmt->bind_param("ssiiisi", $code, $title, $dept_id, $level_id, $credits, $duration, $course_id);
                        if ($stmt->execute()) {
                            logAudit($conn, $user_id, 'UPDATE', 'COURSE', $course_id, null, array('code' => $code, 'title' => $title));
                            $message = 'Course updated successfully.';
                            $message_type = 'success';
                        } else {
                            $message = 'Error updating course.';
                            $message_type = 'error';
                        }
                        $stmt->close();
                    }
                }
            }
            else if ($_POST['action'] === 'delete') {
                $course_id = intval($_POST['course_id'] ?? 0);
                if ($course_id <= 0) {
                    $message = 'Invalid course.';
                    $message_type = 'error';
                } else {
                    $stmt = $conn->prepare("UPDATE courses SET status='inactive' WHERE id=?");
                    if ($stmt) {
                        $stmt->bind_param("i", $course_id);
                        if ($stmt->execute()) {
                            logAudit($conn, $user_id, 'DELETE', 'COURSE', $course_id);
                            $message = 'Course deleted successfully.';
                            $message_type = 'success';
                        } else {
                            $message = 'Error deleting course.';
                            $message_type = 'error';
                        }
                        $stmt->close();
                    }
                }
            }
        }
    }
}

$courses = [];
$stmt = $conn->prepare("
    SELECT c.id, c.course_code, c.course_title, c.credit_units, c.duration, c.status, c.created_at,
           d.department_name, l.level_name
    FROM courses c
    JOIN departments d ON c.department_id = d.id
    JOIN levels l ON c.level_id = l.id
    WHERE d.status = 'active' AND l.status = 'active'
    ORDER BY c.course_code ASC
");
if ($stmt) {
    $stmt->execute();
    $courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$departments = [];
$stmt = $conn->prepare("SELECT id, department_name FROM departments WHERE status = 'active' ORDER BY department_name ASC");
if ($stmt) {
    $stmt->execute();
    $departments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$levels = [];
$stmt = $conn->prepare("SELECT id, level_name FROM levels WHERE status = 'active' ORDER BY level_code ASC");
if ($stmt) {
    $stmt->execute();
    $levels = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Courses - ATGS Admin</title>
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
                <li><a href="courses.php" class="active">Courses</a></li>
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
                    <h1>Course Management</h1>
                    <p>Add, edit, or delete courses</p>
                </div>
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                <div style="margin-bottom: 30px;">
                    <button onclick="toggleForm()" class="btn btn-primary">+ Add Course</button>
                </div>
                <div id="courseForm" class="form-container" style="display: none; margin-bottom: 30px;">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" id="formAction" value="add">
                        <input type="hidden" name="course_id" id="courseId" value="">
                        <div class="form-group">
                            <label for="courseCode">Course Code *</label>
                            <input type="text" id="courseCode" name="course_code" required>
                        </div>
                        <div class="form-group">
                            <label for="courseTitle">Course Title *</label>
                            <input type="text" id="courseTitle" name="course_title" required>
                        </div>
                        <div class="form-group">
                            <label for="departmentId">Department *</label>
                            <select id="departmentId" name="department_id" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['department_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="levelId">Level *</label>
                            <select id="levelId" name="level_id" required>
                                <option value="">Select Level</option>
                                <?php foreach ($levels as $level): ?>
                                    <option value="<?php echo $level['id']; ?>"><?php echo htmlspecialchars($level['level_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="creditUnits">Credit Units</label>
                            <input type="number" id="creditUnits" name="credit_units" value="3" min="1" max="6">
                        </div>
                        <div class="form-group">
                            <label for="duration">Duration</label>
                            <select id="duration" name="duration">
                                <option>1 Hour</option>
                                <option>2 Hours</option>
                                <option>3 Hours</option>
                            </select>
                        </div>
                        <div class="button-group">
                            <button type="submit" class="btn btn-success">Save Course</button>
                            <button type="button" onclick="toggleForm()" class="btn btn-secondary">Cancel</button>
                        </div>
                    </form>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Title</th>
                                <th>Department</th>
                                <th>Level</th>
                                <th>Credits</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($courses) > 0): ?>
                                <?php foreach ($courses as $course): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                                        <td><?php echo htmlspecialchars($course['course_title']); ?></td>
                                        <td><?php echo htmlspecialchars($course['department_name']); ?></td>
                                        <td><?php echo htmlspecialchars($course['level_name']); ?></td>
                                        <td><?php echo $course['credit_units']; ?></td>
                                        <td><?php echo htmlspecialchars($course['duration']); ?></td>
                                        <td><span style="padding: 4px 8px; border-radius: 4px; background: #e8f5e9; color: #2e7d32;"><?php echo ucfirst($course['status']); ?></span></td>
                                        <td>
                                            <div class="table-actions">
                                                <button onclick="editCourse(<?php echo $course['id']; ?>, '<?php echo htmlspecialchars($course['course_code'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($course['course_title'], ENT_QUOTES); ?>', <?php echo $course['credit_units']; ?>, '<?php echo htmlspecialchars($course['duration'], ENT_QUOTES); ?>')" class="btn btn-sm btn-secondary">Edit</button>
                                                <form method="POST" action="" style="display: inline;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; color: #999;">No courses found.</td>
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
            document.getElementById('courseForm').style.display = document.getElementById('courseForm').style.display === 'none' ? 'block' : 'none';
            if (document.getElementById('courseForm').style.display === 'block') {
                document.getElementById('formAction').value = 'add';
                document.getElementById('courseId').value = '';
                document.getElementById('courseCode').value = '';
                document.getElementById('courseTitle').value = '';
                document.getElementById('departmentId').value = '';
                document.getElementById('levelId').value = '';
                document.getElementById('creditUnits').value = '3';
                document.getElementById('duration').value = '1 Hour';
            }
        }
        
        function editCourse(id, code, title, credits, duration) {
            document.getElementById('formAction').value = 'update';
            document.getElementById('courseId').value = id;
            document.getElementById('courseCode').value = code;
            document.getElementById('courseTitle').value = title;
            document.getElementById('creditUnits').value = credits;
            document.getElementById('duration').value = duration;
            document.getElementById('courseForm').style.display = 'block';
            document.getElementById('courseCode').focus();
        }
    </script>
</body>
</html>