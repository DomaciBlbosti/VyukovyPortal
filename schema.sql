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

CREATE TABLE IF NOT EXISTS challenges (
    id          INT          AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(120) NOT NULL,
    description VARCHAR(255) NOT NULL DEFAULT '',
    created_by  INT          NULL,
    created_at  DATETIME     NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS challenge_steps (
    id           INT          AUTO_INCREMENT PRIMARY KEY,
    challenge_id INT          NOT NULL,
    position     INT          NOT NULL DEFAULT 0,
    game_type    VARCHAR(50)  NOT NULL,
    topic        VARCHAR(120) NOT NULL DEFAULT '',
    rounds       INT          NOT NULL DEFAULT 1,
    min_accuracy INT          NOT NULL DEFAULT 90,
    INDEX idx_challenge (challenge_id),
    FOREIGN KEY (challenge_id) REFERENCES challenges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS challenge_assignments (
    id           INT      AUTO_INCREMENT PRIMARY KEY,
    challenge_id INT      NOT NULL,
    user_id      INT      NOT NULL,
    assigned_at  DATETIME NULL,
    completed_at DATETIME NULL,
    UNIQUE KEY unique_assignment (challenge_id, user_id),
    INDEX idx_user (user_id, completed_at),
    FOREIGN KEY (challenge_id) REFERENCES challenges(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS challenge_progress (
    id            INT          AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT          NOT NULL,
    step_id       INT          NOT NULL,
    done_rounds   INT          NOT NULL DEFAULT 0,
    best_accuracy DECIMAL(5,2) NOT NULL DEFAULT 0,
    updated_at    DATETIME     NULL,
    UNIQUE KEY unique_progress (assignment_id, step_id),
    FOREIGN KEY (assignment_id) REFERENCES challenge_assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (step_id) REFERENCES challenge_steps(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS custom_sets (
    id         INT          AUTO_INCREMENT PRIMARY KEY,
    subject    VARCHAR(40)  NOT NULL DEFAULT 'ostatni',
    grade      TINYINT      NOT NULL DEFAULT 0,
    title      VARCHAR(120) NOT NULL,
    source     VARCHAR(180) NOT NULL DEFAULT '',
    kind       VARCHAR(20)  NOT NULL DEFAULT 'dvojice',
    passage    TEXT         NULL,
    created_by INT          NULL,
    created_at DATETIME     NULL,
    updated_at DATETIME     NULL,
    INDEX idx_subject (subject, grade)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS custom_set_items (
    id       INT          AUTO_INCREMENT PRIMARY KEY,
    set_id   INT          NOT NULL,
    position INT          NOT NULL DEFAULT 0,
    item_key VARCHAR(180) NOT NULL,
    prompt   VARCHAR(500) NOT NULL,
    answer   VARCHAR(255) NOT NULL,
    options  TEXT         NULL,
    hint     VARCHAR(255) NOT NULL DEFAULT '',
    INDEX idx_set (set_id, position),
    FOREIGN KEY (set_id) REFERENCES custom_sets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
