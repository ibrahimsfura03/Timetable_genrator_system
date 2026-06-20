<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Automatic Timetable Generator System - Al-Qalam University Katsina</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="navbar">
        <h2>ATGS - Al-Qalam University Katsina</h2>
        <div>
            <a href="index.php">Home</a>
            <a href="register.php">Register</a>
            <a href="student/login.php">Student Login</a>
            <a href="login.php" style="margin-left: 10px;">Admin</a>
        </div>
    </div>
    
    <div class="hero">
        <h1>Automatic Timetable Generator System</h1>
        <p>Efficiently manage and view academic timetables for all departments and levels.</p>
    </div>
    
    <div class="content">
        <div class="page-header">
            <h2>Welcome to ATGS</h2>
            <p>Search and view published timetables below.</p>
        </div>
        
        <div class="search-container">
            <input type="text" id="searchInput" placeholder="Search timetable...">
            <select id="filterType" onchange="updateSearch()">
                <option value="">All Types</option>
                <option value="department">By Department</option>
                <option value="level">By Level</option>
                <option value="course">By Course</option>
                <option value="hall">By Hall</option>
            </select>
        </div>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Course Code</th>
                        <th>Course Title</th>
                        <th>Department</th>
                        <th>Level</th>
                        <th>Day</th>
                        <th>Time</th>
                        <th>Hall</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="timetableBody">
                    <tr>
                        <td colspan="8" style="text-align: center; color: #999;">Loading timetable data...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
        function loadTimetable() {
            fetch('api/public_timetable.php')
                .then(response => response.json())
                .then(data => {
                    const tbody = document.getElementById('timetableBody');
                    if (data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; color: #999;">No timetable entries found.</td></tr>';
                        return;
                    }
                    
                    tbody.innerHTML = data.map(entry => `
                        <tr>
                            <td>${entry.course_code}</td>
                            <td>${entry.course_title}</td>
                            <td>${entry.department_name}</td>
                            <td>${entry.level_name}</td>
                            <td>${entry.day_of_week}</td>
                            <td>${entry.start_time.substring(0,5)} - ${entry.end_time.substring(0,5)}</td>
                            <td>${entry.hall_name}</td>
                            <td>
                                <button onclick="printTimetable(${entry.id})" class="btn btn-sm btn-secondary">Print</button>
                            </td>
                        </tr>
                    `).join('');
                })
                .catch(error => {
                    console.error('Error loading timetable:', error);
                    document.getElementById('timetableBody').innerHTML = '<tr><td colspan="8" style="text-align: center; color: #c62828;">Error loading timetable.</td></tr>';
                });
        }
        
        function updateSearch() {
            const searchInput = document.getElementById('searchInput');
            const filterType = document.getElementById('filterType').value;
            const searchTerm = searchInput.value;
            
            fetch(`api/search_timetable.php?type=${filterType}&q=${encodeURIComponent(searchTerm)}`)
                .then(response => response.json())
                .then(data => {
                    const tbody = document.getElementById('timetableBody');
                    if (data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; color: #999;">No results found.</td></tr>';
                        return;
                    }
                    
                    tbody.innerHTML = data.map(entry => `
                        <tr>
                            <td>${entry.course_code}</td>
                            <td>${entry.course_title}</td>
                            <td>${entry.department_name}</td>
                            <td>${entry.level_name}</td>
                            <td>${entry.day_of_week}</td>
                            <td>${entry.start_time.substring(0,5)} - ${entry.end_time.substring(0,5)}</td>
                            <td>${entry.hall_name}</td>
                            <td>
                                <button onclick="printTimetable(${entry.id})" class="btn btn-sm btn-secondary">Print</button>
                            </td>
                        </tr>
                    `).join('');
                });
        }
        
        function printTimetable(entryId) {
            window.open(`api/print_timetable.php?id=${entryId}`, '_blank');
        }
        
        document.getElementById('searchInput').addEventListener('input', updateSearch);
        document.getElementById('filterType').addEventListener('change', updateSearch);
        
        loadTimetable();
    </script>
</body>
</html>