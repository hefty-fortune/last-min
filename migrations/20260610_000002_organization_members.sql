CREATE TABLE organization_members (
  id TEXT PRIMARY KEY,
  organization_id TEXT NOT NULL,
  user_profile_id TEXT NOT NULL,
  organization_role VARCHAR(32) NOT NULL,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  FOREIGN KEY (organization_id) REFERENCES organizations(id)
);
CREATE UNIQUE INDEX uq_org_members_org_profile ON organization_members(organization_id, user_profile_id);
CREATE INDEX idx_org_members_profile ON organization_members(user_profile_id);
