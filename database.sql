DROP DATABASE IF EXISTS university_timetable;
CREATE DATABASE university_timetable;
USE university_timetable;

CREATE TABLE users(
    id INT AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'Admin',
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO users(fullname, email, password, role, status)
VALUES('Administrator', 'admin@alqalam.edu.ng', '$2y$10$sF9fR8K2xH5pL2mN9vQ3L.ZY8tK3jM5xP1vQ9sL8xM2pR4yT7uV9W', 'Admin', 'active');

CREATE TABLE departments(
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_name VARCHAR(150) NOT NULL UNIQUE,
    department_code VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_code (department_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE levels(
    id INT AUTO_INCREMENT PRIMARY KEY,
    level_name VARCHAR(50) NOT NULL UNIQUE,
    level_code VARCHAR(20) NOT NULL UNIQUE,
    description TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_code (level_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO levels(level_name, level_code) VALUES
('100 Level', '100'),
('200 Level', '200'),
('300 Level', '300'),
('400 Level', '400'),
('500 Level', '500');

CREATE TABLE courses(
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_code VARCHAR(20) NOT NULL,
    course_title VARCHAR(150) NOT NULL,
    department_id INT NOT NULL,
    level_id INT NOT NULL,
    credit_units INT NOT NULL DEFAULT 3,
    duration VARCHAR(50) NOT NULL DEFAULT '1 Hour',
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (level_id) REFERENCES levels(id) ON DELETE CASCADE,
    UNIQUE KEY unique_course_per_dept_level (course_code, department_id, level_id),
    INDEX idx_status (status),
    INDEX idx_department (department_id),
    INDEX idx_level (level_id),
    INDEX idx_code (course_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE halls(
    id INT AUTO_INCREMENT PRIMARY KEY,
    hall_name VARCHAR(100) NOT NULL UNIQUE,
    hall_code VARCHAR(50) NOT NULL UNIQUE,
    capacity INT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_code (hall_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE time_slots(
    id INT AUTO_INCREMENT PRIMARY KEY,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    duration VARCHAR(50) NOT NULL,
    day_of_week VARCHAR(20),
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_day (day_of_week),
    UNIQUE KEY unique_slot (start_time, end_time, day_of_week)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE system_settings(
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type VARCHAR(50),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO system_settings(setting_key, setting_value, setting_type, description)
VALUES
('timetable_days', 'Monday,Tuesday,Wednesday,Thursday,Friday', 'array', 'Days for timetable generation'),
('allow_weekend', '0', 'boolean', 'Allow weekend scheduling'),
('max_courses_per_day', '6', 'integer', 'Maximum courses per day'),
('institution_name', 'Al-Qalam University Katsina', 'string', 'Institution name'),
('semester_current', '1', 'integer', 'Current semester'),
('academic_year', '2024/2025', 'string', 'Current academic year');

CREATE TABLE generated_timetables(
    id INT AUTO_INCREMENT PRIMARY KEY,
    timetable_name VARCHAR(150) NOT NULL,
    department_id INT,
    level_id INT,
    semester INT,
    academic_year VARCHAR(20),
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    is_published TINYINT DEFAULT 0,
    generation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (level_id) REFERENCES levels(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_published (is_published),
    INDEX idx_department (department_id),
    INDEX idx_level (level_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE timetable_entries(
    id INT AUTO_INCREMENT PRIMARY KEY,
    timetable_id INT NOT NULL,
    course_id INT NOT NULL,
    hall_id INT NOT NULL,
    time_slot_id INT NOT NULL,
    day_of_week VARCHAR(20) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (timetable_id) REFERENCES generated_timetables(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (hall_id) REFERENCES halls(id) ON DELETE CASCADE,
    FOREIGN KEY (time_slot_id) REFERENCES time_slots(id) ON DELETE CASCADE,
    UNIQUE KEY unique_entry (timetable_id, course_id, hall_id, day_of_week, start_time),
    INDEX idx_timetable (timetable_id),
    INDEX idx_course (course_id),
    INDEX idx_hall (hall_id),
    INDEX idx_day (day_of_week)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE audit_logs(
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT,
    old_values TEXT,
    new_values TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_action (action),
    INDEX idx_entity (entity_type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;