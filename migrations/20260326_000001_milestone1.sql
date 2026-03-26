CREATE TABLE user_profiles (
  id TEXT PRIMARY KEY,
  identity_subject VARCHAR(191) NOT NULL UNIQUE,
  status VARCHAR(32) NOT NULL,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);

CREATE TABLE actor_role_assignments (
  id TEXT PRIMARY KEY,
  user_profile_id TEXT NOT NULL,
  role_code VARCHAR(32) NOT NULL,
  assigned_by_user_profile_id TEXT NULL,
  assigned_at TEXT NOT NULL,
  revoked_at TEXT NULL
);
CREATE UNIQUE INDEX idx_actor_role_assignments_active ON actor_role_assignments(user_profile_id, role_code) WHERE revoked_at IS NULL;

CREATE TABLE providers (
  id TEXT PRIMARY KEY,
  provider_type VARCHAR(32) NOT NULL,
  owner_user_profile_id TEXT NULL,
  organization_id TEXT NULL,
  status VARCHAR(32) NOT NULL,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);

CREATE TABLE service_offerings (
  id TEXT PRIMARY KEY,
  provider_id TEXT NOT NULL,
  name VARCHAR(255) NOT NULL,
  description TEXT NULL,
  duration_minutes INTEGER NOT NULL,
  price_amount BIGINT NOT NULL,
  price_currency CHAR(3) NOT NULL,
  status VARCHAR(32) NOT NULL,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);

CREATE TABLE openings (
  id TEXT PRIMARY KEY,
  provider_id TEXT NOT NULL,
  service_offering_id TEXT NOT NULL,
  starts_at TEXT NOT NULL,
  ends_at TEXT NOT NULL,
  timezone VARCHAR(64) NOT NULL,
  capacity INTEGER NOT NULL,
  status VARCHAR(32) NOT NULL,
  published_at TEXT NULL,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  price_amount BIGINT NOT NULL,
  price_currency CHAR(3) NOT NULL
);

CREATE TABLE bookings (
  id TEXT PRIMARY KEY,
  opening_id TEXT NOT NULL,
  provider_id TEXT NOT NULL,
  client_user_profile_id TEXT NOT NULL,
  state VARCHAR(32) NOT NULL,
  reservation_expires_at TEXT NULL,
  payment_required_amount BIGINT NOT NULL,
  payment_currency CHAR(3) NOT NULL,
  no_show_actor VARCHAR(32) NULL,
  no_show_recorded_at TEXT NULL,
  confirmed_at TEXT NULL,
  completed_at TEXT NULL,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);
CREATE INDEX idx_bookings_active_opening ON bookings(opening_id, state);

CREATE TABLE payments (
  id TEXT PRIMARY KEY,
  booking_id TEXT NOT NULL UNIQUE,
  provider_id TEXT NOT NULL,
  client_user_profile_id TEXT NOT NULL,
  state VARCHAR(32) NOT NULL,
  amount BIGINT NOT NULL,
  currency CHAR(3) NOT NULL,
  stripe_payment_intent_id VARCHAR(128) NULL UNIQUE,
  captured_at TEXT NULL,
  failed_reason VARCHAR(255) NULL,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);

CREATE TABLE idempotency_keys (
  id TEXT PRIMARY KEY,
  scope VARCHAR(64) NOT NULL,
  idempotency_key VARCHAR(128) NOT NULL,
  request_hash VARCHAR(128) NOT NULL,
  response_code INTEGER NULL,
  response_body TEXT NULL,
  resource_type VARCHAR(64) NULL,
  resource_id TEXT NULL,
  locked_until TEXT NULL,
  expires_at TEXT NOT NULL,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  UNIQUE(scope, idempotency_key)
);

CREATE TABLE stripe_webhook_events (
  id TEXT PRIMARY KEY,
  stripe_event_id VARCHAR(128) NOT NULL UNIQUE,
  event_type VARCHAR(128) NOT NULL,
  payload TEXT NOT NULL,
  signature_valid INTEGER NOT NULL,
  processing_state VARCHAR(32) NOT NULL,
  first_received_at TEXT NOT NULL,
  last_received_at TEXT NOT NULL,
  processed_at TEXT NULL,
  failure_reason TEXT NULL
);
