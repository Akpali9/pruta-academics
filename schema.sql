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
