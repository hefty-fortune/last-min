CREATE TABLE audit_log (
  id TEXT PRIMARY KEY,
  actor_id TEXT NULL,
  actor_roles TEXT NULL,
  action VARCHAR(128) NOT NULL,
  resource_type VARCHAR(64) NOT NULL,
  resource_id TEXT NULL,
  context TEXT NULL,
  recorded_at TEXT NOT NULL
);
CREATE INDEX idx_audit_log_resource ON audit_log(resource_type, resource_id);
CREATE INDEX idx_audit_log_actor ON audit_log(actor_id);

CREATE TABLE outbox_messages (
  id TEXT PRIMARY KEY,
  message_type VARCHAR(128) NOT NULL,
  payload TEXT NOT NULL,
  status VARCHAR(32) NOT NULL,
  available_at TEXT NOT NULL,
  attempts INTEGER NOT NULL DEFAULT 0,
  last_error TEXT NULL,
  created_at TEXT NOT NULL,
  dispatched_at TEXT NULL
);
CREATE INDEX idx_outbox_status_available ON outbox_messages(status, available_at);
