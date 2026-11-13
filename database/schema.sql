CREATE TABLE users (
    telegram_id INTEGER PRIMARY KEY,
    username TEXT,
    first_name TEXT,
    state TEXT,
    test_id TEXT,
    question INTEGER,
    score INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    admin_target_id INTEGER
);

CREATE TABLE results (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    telegram_id INTEGER NOT NULL,
    username TEXT,
    first_name TEXT,
    score INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE answers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    result_id INTEGER NOT NULL,
    question TEXT,
    answer TEXT,
    points INTEGER
);

CREATE TABLE current_answers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    telegram_id INTEGER NOT NULL,
    question TEXT,
    answer TEXT,
    points INTEGER
);

CREATE TABLE processed_updates (
    update_id INTEGER PRIMARY KEY,
    processed_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_answers_result_id
    ON answers(result_id);

CREATE INDEX idx_current_answers_telegram_id
    ON current_answers(telegram_id);

CREATE INDEX idx_results_telegram_id
    ON results(telegram_id);

CREATE INDEX idx_results_username
    ON results(username);
