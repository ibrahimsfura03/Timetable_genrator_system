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
        if (isset($_POST['bulk_action']) && isset($_POST['timetable_ids'])) {
            $timetable_ids = array_map('intval', (array)$_POST['timetable_ids']);
            $bulk_action = sanitizeInput($_POST['bulk_action']);
            
            if (count($timetable_ids) > 0) {
                $id_placeholders = implode(',', $timetable_ids);
                $action_count = 0;
                
                if ($bulk_action === 'bulk_publish') {
                    $is_published = 1;
                    $stmt = $conn->prepare("UPDATE generated_timetables SET is_published = 1 WHERE id IN (" . $id_placeholders . ")");
                    if ($stmt) {
                        $stmt->execute();
                        $action_count = $stmt->affected_rows;
                        $stmt->close();
                        foreach ($timetable_ids as $tid) {
                            logAudit($conn, $user_id, 'BULK_PUBLISH', 'TIMETABLE', $tid);
                        }
                        $message = "$action_count timetable(s) published successfully.";
                        $message_type = 'success';
                    }
                } elseif ($bulk_action === 'bulk_delete') {
                    $stmt = $conn->prepare("UPDATE generated_timetables SET status = 'inactive' WHERE id IN (" . $id_placeholders . ")");
                    if ($stmt) {
                        $stmt->execute();
                        $action_count = $stmt->affected_rows;
                        $stmt->close();
                        foreach ($timetable_ids as $tid) {
                            logAudit($conn, $user_id, 'BULK_DELETE', 'TIMETABLE', $tid);
                        }
                        $message = "$action_count timetable(s) deleted successfully.";
                        $message_type = 'success';
                    }
                }
            } else {
                $message = 'Please select at least one timetable.';
                $message_type = 'error';
            }
        } elseif (isset($_POST['action'])) {
            if ($_POST['action'] === 'delete') {
                $timetable_id = intval($_POST['timetable_id'] ?? 0);
                if ($timetable_id <= 0) {
                    $message = 'Invalid timetable.';
                    $message_type = 'error';
                } else {
                    $stmt = $conn->prepare("UPDATE generated_timetables SET status='inactive' WHERE id=?");
                    if ($stmt) {
                        $stmt->bind_param("i", $timetable_id);
                        if ($stmt->execute()) {
                            logAudit($conn, $user_id, 'DELETE', 'TIMETABLE', $timetable_id);
                            $message = 'Timetable deleted successfully.';
                            $message_type = 'success';
                        } else {
                            $message = 'Error deleting timetable.';
                            $message_type = 'error';
                        }
                        $stmt->close();
                    }
                }
            }
            else if ($_POST['action'] === 'publish') {
                $timetable_id = intval($_POST['timetable_id'] ?? 0);
                if ($timetable_id <= 0) {
                    $message = 'Invalid timetable.';
                    $message_type = 'error';
                } else {
                    $is_published = 1;
                    $stmt = $conn->prepare("UPDATE generated_timetables SET is_published=? WHERE id=?");
                    if ($stmt) {
                        $stmt->bind_param("ii", $is_published, $timetable_id);
                        if ($stmt->execute()) {
                            logAudit($conn, $user_id, 'PUBLISH', 'TIMETABLE', $timetable_id);
                            $message = 'Timetable published successfully.';
                            $message_type = 'success';
                        }
                        $stmt->close();
                    }
                }
            }
        }
    }
}

$timetables = array();
$stmt = $conn->prepare("
    SELECT gt.id, gt.timetable_name, gt.department_id, gt.level_id, gt.semester, gt.is_published, gt.generation_date, gt.status,
           d.department_name, l.level_name
    FROM generated_timetables gt
    LEFT JOIN departments d ON gt.department_id = d.id
    LEFT JOIN levels l ON gt.level_id = l.id
    WHERE gt.status = 'active'
    ORDER BY gt.generation_date DESC
");
if ($stmt) {
    $stmt->execute();
    $timetables = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$csrf_token = generateCSRFToken();
$selected_timetable = null;
$timetable_entries = array();

if (isset($_GET['view'])) {
    $view_id = intval($_GET['view']);
    foreach ($timetables as $tt) {
        if ($tt['id'] === $view_id) {
            $selected_timetable = $tt;
            break;
        }
    }
    
    if ($selected_timetable) {
        $stmt = $conn->prepare("
            SELECT te.id, te.day_of_week, te.start_time, te.end_time,
                   c.course_code, c.course_title,
                   h.hall_name,
                   d.department_name, l.level_name
            FROM timetable_entries te
            JOIN courses c ON te.course_id = c.id
            JOIN halls h ON te.hall_id = h.id
            JOIN departments d ON c.department_id = d.id
            JOIN levels l ON c.level_id = l.id
            WHERE te.timetable_id = ?
            ORDER BY 
                CASE WHEN te.day_of_week = 'Monday' THEN 1
                     WHEN te.day_of_week = 'Tuesday' THEN 2
                     WHEN te.day_of_week = 'Wednesday' THEN 3
                     WHEN te.day_of_week = 'Thursday' THEN 4
                     WHEN te.day_of_week = 'Friday' THEN 5
                END,
                te.start_time ASC
        ");
        if ($stmt) {
            $stmt->bind_param("i", $view_id);
            $stmt->execute();
            $timetable_entries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timetables - ATGS Admin</title>
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
                <li><a href="timetable.php" class="active">Timetables</a></li>
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
                    <h1>Manage Timetables</h1>
                    <p>View, edit, publish, and manage generated timetables</p>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!$selected_timetable): ?>
                    <form method="POST" action="" id="bulkForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <div style="display: flex; gap: 10px; margin-bottom: 15px; align-items: center;">
                            <select name="bulk_action" id="bulkAction" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
                                <option value="">Select Bulk Action</option>
                                <option value="bulk_publish">Publish Selected</option>
                                <option value="bulk_delete">Delete Selected</option>
                            </select>
                            <button type="button" onclick="executeBulkAction()" class="btn btn-primary">Apply</button>
                            <span id="selectedCount" style="color: #666; font-size: 14px;"></span>
                        </div>
                        
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th style="width: 40px;">
                                            <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)" style="cursor: pointer;">
                                        </th>
                                        <th>Timetable Name</th>
                                        <th>Department</th>
                                        <th>Level</th>
                                        <th>Semester</th>
                                        <th>Status</th>
                                        <th>Generated</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($timetables) > 0): ?>
                                        <?php foreach ($timetables as $tt): ?>
                                            <tr>
                                                <td style="width: 40px;">
                                                    <input type="checkbox" name="timetable_ids[]" value="<?php echo $tt['id']; ?>" class="timetable-checkbox" onchange="updateSelectedCount()" style="cursor: pointer;">
                                                </td>
                                                <td><?php echo htmlspecialchars($tt['timetable_name']); ?></td>
                                                <td><?php echo htmlspecialchars($tt['department_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($tt['level_name'] ?? 'N/A'); ?></td>
                                                <td>Sem <?php echo $tt['semester']; ?></td>
                                                <td>
                                                    <?php if ($tt['is_published']): ?>
                                                        <span style="padding: 4px 8px; border-radius: 4px; background: #c8e6c9; color: #2e7d32;">Published</span>
                                                    <?php else: ?>
                                                        <span style="padding: 4px 8px; border-radius: 4px; background: #fff9c4; color: #f57f17;">Draft</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('d M Y, H:i', strtotime($tt['generation_date'])); ?></td>
                                                <td>
                                                    <div class="table-actions">
                                                        <a href="?view=<?php echo $tt['id']; ?>" class="btn btn-sm btn-secondary">View</a>
                                                        <?php if (!$tt['is_published']): ?>
                                                            <form method="POST" action="" style="display: inline;">
                                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                                <input type="hidden" name="action" value="publish">
                                                                <input type="hidden" name="timetable_id" value="<?php echo $tt['id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-success">Publish</button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <form method="POST" action="" style="display: inline;">
                                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="timetable_id" value="<?php echo $tt['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" style="text-align: center; color: #999;">No timetables found. <a href="generate.php">Generate one</a>.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>
                    
                    <script>
                        function toggleSelectAll(checkbox) {
                            const checkboxes = document.querySelectorAll('.timetable-checkbox');
                            checkboxes.forEach(cb => cb.checked = checkbox.checked);
                            updateSelectedCount();
                        }
                        
                        function updateSelectedCount() {
                            const selected = document.querySelectorAll('.timetable-checkbox:checked').length;
                            const countEl = document.getElementById('selectedCount');
                            if (selected > 0) {
                                countEl.textContent = `${selected} timetable(s) selected`;
                            } else {
                                countEl.textContent = '';
                            }
                        }
                        
                        function executeBulkAction() {
                            const action = document.getElementById('bulkAction').value;
                            const selected = document.querySelectorAll('.timetable-checkbox:checked').length;
                            
                            if (!action) {
                                alert('Please select an action');
                                return;
                            }
                            
                            if (selected === 0) {
                                alert('Please select at least one timetable');
                                return;
                            }
                            
                            const confirmMsg = {
                                'bulk_publish': `Publish ${selected} timetable(s)?`,
                                'bulk_delete': `Delete ${selected} timetable(s)?`
                            };
                            
                            if (confirm(confirmMsg[action])) {
                                document.getElementById('bulkForm').submit();
                            }
                        }
                    </script>
                <?php else: ?>
                    <div style="margin-bottom: 20px;">
                        <a href="timetable.php" class="btn btn-secondary">Back to List</a>
                    </div>
                    
                    <div class="form-container" style="background: white; max-width: none;">
                        <h2><?php echo htmlspecialchars($selected_timetable['timetable_name']); ?></h2>
                        <p style="color: #999; margin-bottom: 20px;">
                            <?php echo htmlspecialchars($selected_timetable['department_name'] ?? 'N/A'); ?> - 
                            <?php echo htmlspecialchars($selected_timetable['level_name'] ?? 'N/A'); ?> - 
                            Semester <?php echo $selected_timetable['semester']; ?>
                        </p>
                        
                        <?php if (count($timetable_entries) > 0): ?>
                            <div style="margin-bottom: 20px;">
                                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                    <button onclick="filterByDay('all')" class="btn btn-primary" id="btn-all">All Days</button>
                                    <button onclick="filterByDay('Monday')" class="btn btn-secondary" id="btn-Monday">Monday</button>
                                    <button onclick="filterByDay('Tuesday')" class="btn btn-secondary" id="btn-Tuesday">Tuesday</button>
                                    <button onclick="filterByDay('Wednesday')" class="btn btn-secondary" id="btn-Wednesday">Wednesday</button>
                                    <button onclick="filterByDay('Thursday')" class="btn btn-secondary" id="btn-Thursday">Thursday</button>
                                    <button onclick="filterByDay('Friday')" class="btn btn-secondary" id="btn-Friday">Friday</button>
                                </div>
                            </div>
                            
                            <div style="overflow-x: auto;">
                                <table style="width: 100%;" id="entriesTable">
                                    <thead>
                                        <tr>
                                            <th>Course Code</th>
                                            <th>Course Title</th>
                                            <th>Day</th>
                                            <th>Time</th>
                                            <th>Hall</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($timetable_entries as $entry): ?>
                                            <tr class="day-row" data-day="<?php echo htmlspecialchars($entry['day_of_week']); ?>">
                                                <td><?php echo htmlspecialchars($entry['course_code']); ?></td>
                                                <td><?php echo htmlspecialchars($entry['course_title']); ?></td>
                                                <td><?php echo htmlspecialchars($entry['day_of_week']); ?></td>
                                                <td><?php echo date('H:i', strtotime($entry['start_time'])) . ' - ' . date('H:i', strtotime($entry['end_time'])); ?></td>
                                                <td><?php echo htmlspecialchars($entry['hall_name']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <script>
                                let currentFilter = 'all';
                                
                                function filterByDay(day) {
                                    currentFilter = day;
                                    const rows = document.querySelectorAll('.day-row');
                                    
                                    rows.forEach(row => {
                                        if (day === 'all') {
                                            row.style.display = '';
                                        } else {
                                            const rowDay = row.getAttribute('data-day');
                                            row.style.display = (rowDay === day) ? '' : 'none';
                                        }
                                    });
                                    
                                    // Update button styles
                                    document.querySelectorAll('[id^="btn-"]').forEach(btn => {
                                        btn.className = btn.id === 'btn-' + day ? 'btn btn-primary' : 'btn btn-secondary';
                                    });
                                }
                            </script>
                        <?php else: ?>
                            <p style="color: #999;">No entries in this timetable.</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>