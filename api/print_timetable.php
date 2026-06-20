<?php
include '../config/db.php';

$entry_id = intval($_GET['id'] ?? 0);

if ($entry_id <= 0) {
    echo "Invalid entry.";
    exit;
}

$stmt = $conn->prepare("
    SELECT 
        te.id, te.day_of_week, te.start_time, te.end_time,
        c.course_code, c.course_title, c.credit_units,
        d.department_name,
        l.level_name,
        h.hall_name, h.capacity
    FROM timetable_entries te
    JOIN courses c ON te.course_id = c.id
    JOIN departments d ON c.department_id = d.id
    JOIN levels l ON c.level_id = l.id
    JOIN halls h ON te.hall_id = h.id
    WHERE te.id = ?
");

if (!$stmt) {
    echo "Database error.";
    exit;
}

$stmt->bind_param("i", $entry_id);
$stmt->execute();
$result = $stmt->get_result();
$entry = $result->fetch_assoc();
$stmt->close();

if (!$entry) {
    echo "Entry not found.";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Timetable - ATGS</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            color: #333;
            line-height: 1.6;
            padding: 20px;
            background: white;
        }
        .print-container {
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #ddd;
            padding: 40px;
        }
        .header {
            text-align: center;
            margin-bottom: 40px;
            border-bottom: 2px solid #1a237e;
            padding-bottom: 20px;
        }
        .header h1 {
            font-size: 24px;
            color: #1a237e;
            margin-bottom: 8px;
        }
        .header p {
            color: #666;
            font-size: 14px;
        }
        .entry-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .info-group {
            padding: 12px;
            background: #f5f5f5;
            border-radius: 4px;
        }
        .info-label {
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .info-value {
            font-size: 16px;
            color: #333;
            font-weight: 500;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 12px;
            color: #999;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
        @media print {
            body {
                padding: 0;
                background: none;
            }
            .print-container {
                border: none;
                padding: 0;
                max-width: 100%;
            }
            .no-print {
                display: none;
            }
        }
        .print-button {
            text-align: center;
            margin-bottom: 20px;
            padding: 10px 0;
            border-bottom: 1px solid #ddd;
        }
        .print-button button {
            padding: 10px 20px;
            background: #1a237e;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .print-button button:hover {
            background: #0d1852;
        }
    </style>
</head>
<body>
    <div class="print-button no-print">
        <button onclick="window.print()">Print This Page</button>
    </div>
    
    <div class="print-container">
        <div class="header">
            <h1>Timetable Entry</h1>
            <p>Al-Qalam University Katsina - Automatic Timetable Generator System</p>
        </div>
        
        <div class="entry-info">
            <div class="info-group">
                <div class="info-label">Course Code</div>
                <div class="info-value"><?php echo htmlspecialchars($entry['course_code']); ?></div>
            </div>
            
            <div class="info-group">
                <div class="info-label">Course Title</div>
                <div class="info-value"><?php echo htmlspecialchars($entry['course_title']); ?></div>
            </div>
            
            <div class="info-group">
                <div class="info-label">Department</div>
                <div class="info-value"><?php echo htmlspecialchars($entry['department_name']); ?></div>
            </div>
            
            <div class="info-group">
                <div class="info-label">Level</div>
                <div class="info-value"><?php echo htmlspecialchars($entry['level_name']); ?></div>
            </div>
            
            <div class="info-group">
                <div class="info-label">Day</div>
                <div class="info-value"><?php echo htmlspecialchars($entry['day_of_week']); ?></div>
            </div>
            
            <div class="info-group">
                <div class="info-label">Time</div>
                <div class="info-value"><?php echo date('H:i', strtotime($entry['start_time'])) . ' - ' . date('H:i', strtotime($entry['end_time'])); ?></div>
            </div>
            
            <div class="info-group">
                <div class="info-label">Hall</div>
                <div class="info-value"><?php echo htmlspecialchars($entry['hall_name']); ?></div>
            </div>
            
            <div class="info-group">
                <div class="info-label">Capacity</div>
                <div class="info-value"><?php echo $entry['capacity']; ?> seats</div>
            </div>
        </div>
        
        <div class="footer">
            <p>Generated on <?php echo date('d M Y \a\t H:i'); ?></p>
            <p>© 2024 Al-Qalam University Katsina. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
