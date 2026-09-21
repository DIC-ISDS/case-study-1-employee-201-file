-- Runs once, on first boot of an empty MySQL volume.
-- The test suite exercises the real schema (generated column + unique index),
-- so it needs a MySQL database rather than SQLite.
CREATE DATABASE IF NOT EXISTS laravel_testing
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON laravel_testing.* TO 'uplb'@'%';
FLUSH PRIVILEGES;
