CREATE TABLE login_attempts (
  id TEXT PRIMARY KEY,
  email VARCHAR(255) NOT NULL,
  attempted_at TEXT NOT NULL
);
CREATE INDEX idx_login_attempts_email_time ON login_attempts(email, attempted_at);
