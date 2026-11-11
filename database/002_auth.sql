-- Migration 002: user accounts. Run ONCE, after schema.sql.
USE todo_app;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Every task belongs to a user. Deleting a user deletes their tasks.
-- Tasks created before this migration have no owner (user_id is NULL).
ALTER TABLE tasks
    ADD COLUMN user_id INT NULL AFTER id,
    ADD CONSTRAINT fk_tasks_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
