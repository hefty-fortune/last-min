CREATE TABLE refunds (
  id TEXT PRIMARY KEY,
  payment_id TEXT NOT NULL,
  booking_id TEXT NOT NULL,
  state VARCHAR(32) NOT NULL,
  reason VARCHAR(64) NOT NULL,
  amount BIGINT NOT NULL,
  currency CHAR(3) NOT NULL,
  stripe_refund_id VARCHAR(128) NULL UNIQUE,
  decided_by_actor_id TEXT NULL,
  decision_note TEXT NULL,
  decided_at TEXT NULL,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);
CREATE INDEX idx_refunds_booking ON refunds(booking_id);
CREATE INDEX idx_refunds_payment ON refunds(payment_id);
CREATE UNIQUE INDEX uq_refunds_active_payment ON refunds(payment_id) WHERE state IN ('requested', 'pending');
