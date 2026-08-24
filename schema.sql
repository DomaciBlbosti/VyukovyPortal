-- =============================================
-- TypeMaster — databázové schéma
-- (generováno instalačním průvodcem)
-- =============================================

CREATE TABLE IF NOT EXISTS users (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    username     VARCHAR(50)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(100),
    is_admin     TINYINT(1)   NOT NULL DEFAULT 0,
    is_active    TINYINT(1)   NOT NULL DEFAULT 1,
    grade        TINYINT      NOT NULL DEFAULT 0,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    last_login   TIMESTAMP    NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS game_sessions (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    user_id          INT          NOT NULL,
    game_type        VARCHAR(50)  NOT NULL DEFAULT 'classic',
    wpm              DECIMAL(6,2) NOT NULL DEFAULT 0,
    accuracy         DECIMAL(5,2) NOT NULL DEFAULT 0,
    duration_seconds INT          NOT NULL DEFAULT 0,
    chars_typed      INT          NOT NULL DEFAULT 0,
    errors           INT          NOT NULL DEFAULT 0,
    text_snippet     TEXT,
    played_at        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_game (user_id, game_type),
    INDEX idx_played_at (played_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS achievements (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT          NOT NULL,
    achievement_key VARCHAR(100) NOT NULL,
    earned_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_achievement (user_id, achievement_key),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS levels (
    level_number    INT          NOT NULL PRIMARY KEY,
    points_required INT          NOT NULL,
    title           VARCHAR(100) NOT NULL,
    icon            VARCHAR(16)  NOT NULL DEFAULT '⭐'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS game_multipliers (
    game_type  VARCHAR(50)  NOT NULL PRIMARY KEY,
    label      VARCHAR(100) NOT NULL,
    multiplier DECIMAL(4,2) NOT NULL DEFAULT 1.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mistakes (
    id             INT          AUTO_INCREMENT PRIMARY KEY,
    user_id        INT          NOT NULL,
    game_type      VARCHAR(50)  NOT NULL,
    topic          VARCHAR(80)  NOT NULL DEFAULT '',
    topic_label    VARCHAR(120) NOT NULL DEFAULT '',
    item_key       VARCHAR(180) NOT NULL,
    prompt         VARCHAR(255) NOT NULL DEFAULT '',
    correct_answer VARCHAR(255) NOT NULL DEFAULT '',
    hint           VARCHAR(255) NOT NULL DEFAULT '',
    wrong_count    INT          NOT NULL DEFAULT 0,
    right_streak   INT          NOT NULL DEFAULT 0,
    last_wrong_at  DATETIME     NULL,
    updated_at     DATETIME     NULL,
    UNIQUE KEY unique_mistake (user_id, game_type, item_key),
    INDEX idx_practice (user_id, game_type, topic, right_streak),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
