CREATE TABLE IF NOT EXISTS tenants (
  id TEXT PRIMARY KEY,
  name TEXT NOT NULL,
  business_name TEXT NOT NULL,
  slug TEXT UNIQUE NOT NULL,
  segment TEXT NOT NULL,
  document TEXT,
  email TEXT NOT NULL,
  phone TEXT,
  whatsapp TEXT,
  city TEXT,
  state TEXT,
  address TEXT,
  instagram TEXT,
  website TEXT,
  status TEXT DEFAULT 'ACTIVE',
  plan TEXT DEFAULT 'starter',
  logo TEXT,
  primary_color TEXT DEFAULT '#2563eb',
  display_name TEXT,
  timezone TEXT DEFAULT 'America/Sao_Paulo',
  terminology TEXT DEFAULT '{}',
  business_hours TEXT DEFAULT '{}',
  analytics_config TEXT DEFAULT '{}',
  sheets_config TEXT DEFAULT '{}',
  letterhead_config TEXT DEFAULT '{}',
  signature_config TEXT DEFAULT '{}',
  clauses_config TEXT DEFAULT '{}',
  webhook_access INTEGER DEFAULT 0,
  webhook_requested_at TEXT,
  webhook_approved_at TEXT,
  webhook_approved_by TEXT,
  access_token_generated_at TEXT,
  access_token_viewed_at TEXT,
  onboarding_done INTEGER DEFAULT 0,
  home_path TEXT DEFAULT '/app/agenda',
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS users (
  id TEXT PRIMARY KEY,
  tenant_id TEXT,
  name TEXT NOT NULL,
  email TEXT UNIQUE NOT NULL,
  username TEXT UNIQUE NOT NULL,
  password_hash TEXT NOT NULL,
  role TEXT NOT NULL,
  phone TEXT,
  must_change_password INTEGER DEFAULT 1,
  last_login_at TEXT,
  active INTEGER DEFAULT 1,
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS clients (
  id TEXT PRIMARY KEY,
  tenant_id TEXT NOT NULL,
  name TEXT NOT NULL,
  phone TEXT,
  whatsapp TEXT,
  email TEXT,
  cpf TEXT,
  birth_date TEXT,
  source TEXT DEFAULT 'Outro',
  utm_source TEXT,
  utm_medium TEXT,
  utm_campaign TEXT,
  notes TEXT,
  status TEXT DEFAULT 'ACTIVE',
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS services (
  id TEXT PRIMARY KEY,
  tenant_id TEXT NOT NULL,
  name TEXT NOT NULL,
  category TEXT,
  description TEXT,
  duration_minutes INTEGER DEFAULT 60,
  buffer_minutes INTEGER DEFAULT 0,
  price REAL DEFAULT 0,
  deposit REAL DEFAULT 0,
  color TEXT DEFAULT '#2563eb',
  location_type TEXT DEFAULT 'presencial',
  location_note TEXT,
  bookable_online INTEGER DEFAULT 1,
  requires_confirmation INTEGER DEFAULT 0,
  capacity INTEGER DEFAULT 1,
  min_notice_hours INTEGER DEFAULT 0,
  max_advance_days INTEGER DEFAULT 60,
  client_instructions TEXT,
  internal_notes TEXT,
  status TEXT DEFAULT 'ACTIVE',
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS appointments (
  id TEXT PRIMARY KEY,
  tenant_id TEXT NOT NULL,
  client_id TEXT NOT NULL,
  service_id TEXT,
  request_id TEXT,
  starts_at TEXT NOT NULL,
  ends_at TEXT NOT NULL,
  status TEXT DEFAULT 'SCHEDULED',
  source TEXT DEFAULT 'Manual',
  notes TEXT,
  metadata TEXT,
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS requests (
  id TEXT PRIMARY KEY,
  tenant_id TEXT NOT NULL,
  client_id TEXT,
  service_id TEXT,
  name TEXT NOT NULL,
  phone TEXT,
  email TEXT,
  desired_date TEXT,
  desired_time TEXT,
  message TEXT,
  source TEXT DEFAULT 'Manual',
  utm_source TEXT,
  utm_medium TEXT,
  utm_campaign TEXT,
  metadata TEXT,
  status TEXT DEFAULT 'NEW',
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS calendar_blocks (
  id TEXT PRIMARY KEY,
  tenant_id TEXT NOT NULL,
  starts_at TEXT NOT NULL,
  ends_at TEXT NOT NULL,
  reason TEXT,
  all_day INTEGER DEFAULT 0,
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS custom_fields (
  id TEXT PRIMARY KEY,
  tenant_id TEXT NOT NULL,
  label TEXT NOT NULL,
  key TEXT NOT NULL,
  type TEXT NOT NULL,
  options TEXT,
  sort_order INTEGER DEFAULT 0
);
CREATE TABLE IF NOT EXISTS custom_field_values (
  id TEXT PRIMARY KEY,
  tenant_id TEXT NOT NULL,
  field_id TEXT NOT NULL,
  client_id TEXT NOT NULL,
  value TEXT
);
CREATE TABLE IF NOT EXISTS webhooks (
  id TEXT PRIMARY KEY,
  tenant_id TEXT NOT NULL,
  name TEXT NOT NULL,
  direction TEXT NOT NULL,
  token TEXT UNIQUE NOT NULL,
  secret TEXT NOT NULL,
  url TEXT,
  events TEXT DEFAULT '[]',
  active INTEGER DEFAULT 1,
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS webhook_logs (
  id TEXT PRIMARY KEY,
  tenant_id TEXT NOT NULL,
  webhook_id TEXT,
  event TEXT,
  payload TEXT,
  response TEXT,
  http_status INTEGER,
  status TEXT,
  source TEXT,
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS notifications (
  id TEXT PRIMARY KEY,
  tenant_id TEXT NOT NULL,
  title TEXT NOT NULL,
  body TEXT,
  read_flag INTEGER DEFAULT 0,
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS audit_logs (
  id TEXT PRIMARY KEY,
  tenant_id TEXT,
  user_id TEXT,
  action TEXT NOT NULL,
  entity TEXT,
  entity_id TEXT,
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS analytics_events (
  id TEXT PRIMARY KEY,
  tenant_id TEXT NOT NULL,
  event TEXT NOT NULL,
  source TEXT,
  medium TEXT,
  campaign TEXT,
  metadata TEXT,
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS plans (
  id TEXT PRIMARY KEY,
  name TEXT NOT NULL,
  slug TEXT UNIQUE NOT NULL,
  description TEXT,
  active INTEGER DEFAULT 1
);
CREATE TABLE IF NOT EXISTS segments (
  id TEXT PRIMARY KEY,
  name TEXT NOT NULL,
  slug TEXT UNIQUE NOT NULL,
  category TEXT,
  active INTEGER DEFAULT 1
);
CREATE TABLE IF NOT EXISTS platform_settings (
  key TEXT PRIMARY KEY,
  value TEXT NOT NULL DEFAULT '{}',
  updated_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS php_sessions (
  id TEXT PRIMARY KEY,
  data TEXT NOT NULL,
  expires_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS rate_limits (
  key TEXT NOT NULL,
  hit_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_clients_tenant ON clients(tenant_id);
CREATE INDEX IF NOT EXISTS idx_appt_tenant ON appointments(tenant_id, starts_at);
CREATE INDEX IF NOT EXISTS idx_req_tenant ON requests(tenant_id, status);
CREATE INDEX IF NOT EXISTS idx_wh_token ON webhooks(token);
CREATE INDEX IF NOT EXISTS idx_analytics_tenant ON analytics_events(tenant_id, created_at);
CREATE TABLE IF NOT EXISTS finance_entries (
  id TEXT PRIMARY KEY,
  tenant_id TEXT NOT NULL,
  kind TEXT NOT NULL,
  flow TEXT NOT NULL DEFAULT 'in',
  status TEXT NOT NULL DEFAULT 'open',
  description TEXT NOT NULL,
  amount REAL NOT NULL DEFAULT 0,
  due_date TEXT,
  paid_at TEXT,
  client_id TEXT,
  notes TEXT,
  source_type TEXT,
  source_id TEXT,
  amount_paid REAL DEFAULT 0,
  payment_method TEXT,
  agent_id TEXT,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_finance_tenant ON finance_entries(tenant_id, kind, status);
CREATE UNIQUE INDEX IF NOT EXISTS idx_finance_source ON finance_entries(tenant_id, source_type, source_id) WHERE source_id IS NOT NULL AND source_type IS NOT NULL;
