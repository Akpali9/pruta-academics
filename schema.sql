INSERT INTO users
(
 fullname,
 email,
 phone,
 password,
 role,
 status
)
VALUES
(
 'System Admin',
 'admin@pruta.com',
 '08000000000',
 '$2y$10$JfA7oS0dN4k4dQh1x7zFQeM3N2JQjVx4x9N0Fv3k0x4y2V0Y8lJ7m',
 'nigeria',
 'active'
);
ALTER TABLE enrollments
ADD COLUMN receipt VARCHAR(255) NULL;

ALTER TABLE enrollments
ADD COLUMN access_code_used TINYINT(1) DEFAULT 0;
CREATE TABLE modules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT,
    title VARCHAR(255),
    video VARCHAR(255),
    module_order INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module_id INT,
    question TEXT
);

CREATE TABLE submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT,
    user_id INT,
    answer TEXT,
    file VARCHAR(255),
    status ENUM('pending','passed','failed') DEFAULT 'pending'
);

CREATE TABLE progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    course_id INT,
    module_id INT,
    status ENUM('locked','unlocked','completed') DEFAULT 'locked'
);
ALTER TABLE enrollments
ADD COLUMN last_access DATETIME DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE submissions
ADD COLUMN graded_at DATETIME NULL;

CREATE TABLE certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    course_id INT,
    certificate_code VARCHAR(100),
    file VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE user_sessions (
    user_id INT PRIMARY KEY,
    session_id VARCHAR(255),
    last_active DATETIME
);
ALTER TABLE enrollments
ADD COLUMN payment_method VARCHAR(50) DEFAULT 'bank_transfer',
ADD COLUMN bank_reference VARCHAR(100) NULL;

ALTER TABLE users
ADD COLUMN reset_token VARCHAR(255) NULL,
ADD COLUMN reset_expiry DATETIME NULL;

CREATE TABLE login_attempts (
    email VARCHAR(255) PRIMARY KEY,
    attempts INT DEFAULT 0,
    last_attempt DATETIME
);
