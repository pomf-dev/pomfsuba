-- pomfIB database schema
-- MariaDB / MySQL
-- No database name, username, or password is stored here.

SET NAMES utf8mb4;

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS admins (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(64) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_admins_username (username)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS boards (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(32) NOT NULL,
    name VARCHAR(120) NOT NULL,
    description TEXT NULL,
    banner VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_boards_slug (slug)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS threads (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    board_id INT UNSIGNED NOT NULL,
    subject VARCHAR(120) NOT NULL DEFAULT 'No subject',
    pinned TINYINT(1) NOT NULL DEFAULT 0,
    locked TINYINT(1) NOT NULL DEFAULT 0,
    deleted TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    bumped_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    KEY idx_threads_board (
        board_id
    ),

    KEY idx_threads_bumped (
        board_id,
        deleted,
        pinned,
        bumped_at
    ),

    CONSTRAINT fk_threads_board
        FOREIGN KEY (board_id)
        REFERENCES boards(id)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS posts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    thread_id INT UNSIGNED NOT NULL,
    board_id INT UNSIGNED NOT NULL,

    name VARCHAR(80) NOT NULL DEFAULT 'Anonymous',
    body TEXT NOT NULL,

    file_name VARCHAR(255) NULL,
    file_original VARCHAR(255) NULL,
    file_mime VARCHAR(100) NULL,
    file_size BIGINT UNSIGNED NULL,

    ip_hash CHAR(64) NOT NULL,
    country_code CHAR(2) NULL,

    deleted TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    KEY idx_posts_thread (
        thread_id,
        deleted,
        id
    ),

    KEY idx_posts_board (
        board_id,
        deleted,
        id
    ),

    KEY idx_posts_ip (
        ip_hash,
        id
    ),

    CONSTRAINT fk_posts_thread
        FOREIGN KEY (thread_id)
        REFERENCES threads(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_posts_board
        FOREIGN KEY (board_id)
        REFERENCES boards(id)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS reports (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    post_id INT UNSIGNED NOT NULL,
    reason TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    KEY idx_reports_post (
        post_id
    ),

    CONSTRAINT fk_reports_post
        FOREIGN KEY (post_id)
        REFERENCES posts(id)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


SET FOREIGN_KEY_CHECKS = 1;
