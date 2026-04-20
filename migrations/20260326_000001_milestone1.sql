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
  display_name VARCHAR(255) NULL,
  status VARCHAR(32) NOT NULL,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  FOREIGN KEY (organization_id) REFERENCES organizations(id)
);

CREATE TABLE organizations (
  id TEXT PRIMARY KEY,
  legal_name VARCHAR(255) NOT NULL,
  display_name VARCHAR(255) NOT NULL,
  tax_id VARCHAR(64) NULL,
  contact_email VARCHAR(255) NOT NULL,
  contact_phone VARCHAR(64) NOT NULL,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);
CREATE UNIQUE INDEX idx_organizations_legal_name ON organizations(legal_name);

CREATE TABLE users (
  id TEXT PRIMARY KEY,
  provider_id TEXT NOT NULL,
  first_name VARCHAR(128) NOT NULL,
  last_name VARCHAR(128) NOT NULL,
  email VARCHAR(255) NOT NULL,
  phone VARCHAR(64) NOT NULL,
  password_hash VARCHAR(255) NULL,
  status VARCHAR(32) NOT NULL,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  FOREIGN KEY (provider_id) REFERENCES providers(id)
);
CREATE UNIQUE INDEX idx_users_email ON users(email);

CREATE TABLE user_roles (
  id TEXT PRIMARY KEY,
  user_id TEXT NOT NULL,
  role_code VARCHAR(64) NOT NULL,
  created_at TEXT NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id)
);
CREATE UNIQUE INDEX idx_user_roles_user_role ON user_roles(user_id, role_code);

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

CREATE TABLE api_keys (
  id TEXT PRIMARY KEY,
  client_id VARCHAR(64) NOT NULL,
  key_name VARCHAR(128) NOT NULL,
  key_hash CHAR(64) NOT NULL UNIQUE,
  key_prefix VARCHAR(16) NOT NULL,
  created_at TEXT NOT NULL,
  revoked_at TEXT NULL
);
CREATE INDEX idx_api_keys_client_active ON api_keys(client_id, revoked_at);
CREATE UNIQUE INDEX idx_api_keys_client_name_active ON api_keys(client_id, key_name) WHERE revoked_at IS NULL;
