-- FirestepCRM — schema inicial para Supabase (PostgreSQL)
-- Aplique com: supabase db push
-- ou no SQL Editor do projeto.

begin;

create extension if not exists "pgcrypto";

-- ---------------------------------------------------------------------------
-- Funções auxiliares
-- ---------------------------------------------------------------------------
create or replace function public.set_updated_at()
returns trigger
language plpgsql
as $$
begin
  new.updated_at = timezone('utc', now());
  return new;
end;
$$;

-- ---------------------------------------------------------------------------
-- Catálogos da plataforma
-- ---------------------------------------------------------------------------
create table if not exists public.plans (
  id text primary key,
  name text not null,
  slug text not null unique,
  description text,
  active boolean not null default true
);

create table if not exists public.segments (
  id text primary key,
  name text not null,
  slug text not null unique,
  category text,
  active boolean not null default true
);

create table if not exists public.platform_settings (
  key text primary key,
  value jsonb not null default '{}'::jsonb,
  updated_at timestamptz not null default timezone('utc', now())
);

-- ---------------------------------------------------------------------------
-- Multi-tenant
-- ---------------------------------------------------------------------------
create table if not exists public.tenants (
  id text primary key,
  name text not null,
  business_name text not null,
  slug text not null unique,
  segment text not null,
  document text,
  email text not null,
  phone text,
  whatsapp text,
  city text,
  state text,
  address text,
  instagram text,
  website text,
  status text not null default 'ACTIVE' check (status in ('ACTIVE','SUSPENDED','OVERDUE','CANCELLED')),
  plan text not null default 'starter' references public.plans(slug) on update cascade on delete restrict,
  logo text,
  primary_color text not null default '#2563eb',
  display_name text,
  timezone text not null default 'America/Sao_Paulo',
  terminology jsonb not null default '{}'::jsonb,
  business_hours jsonb not null default '{}'::jsonb,
  analytics_config jsonb not null default '{}'::jsonb,
  sheets_config jsonb not null default '{}'::jsonb,
  webhook_access boolean not null default false,
  webhook_requested_at timestamptz,
  webhook_approved_at timestamptz,
  webhook_approved_by text,
  onboarding_done boolean not null default false,
  created_at timestamptz not null default timezone('utc', now()),
  updated_at timestamptz not null default timezone('utc', now())
);

create table if not exists public.users (
  id text primary key,
  tenant_id text references public.tenants(id) on delete cascade,
  auth_user_id uuid unique references auth.users(id) on delete set null,
  name text not null,
  email text not null unique,
  username text not null unique,
  password_hash text,
  role text not null check (role in ('MASTER','TENANT_ADMIN')),
  phone text,
  must_change_password boolean not null default true,
  last_login_at timestamptz,
  active boolean not null default true,
  created_at timestamptz not null default timezone('utc', now())
);

create or replace function public.current_tenant_id()
returns text
language sql
stable
security definer
set search_path = public
as $$
  select u.tenant_id
  from public.users u
  where u.auth_user_id = auth.uid()
  limit 1;
$$;

create or replace function public.is_master()
returns boolean
language sql
stable
security definer
set search_path = public
as $$
  select exists (
    select 1
    from public.users u
    where u.auth_user_id = auth.uid()
      and u.role = 'MASTER'
      and u.active = true
  );
$$;

-- ---------------------------------------------------------------------------
-- CRM
-- ---------------------------------------------------------------------------
create table if not exists public.clients (
  id text primary key,
  tenant_id text not null references public.tenants(id) on delete cascade,
  name text not null,
  phone text,
  whatsapp text,
  email text,
  cpf text,
  birth_date date,
  source text not null default 'Outro',
  utm_source text,
  utm_medium text,
  utm_campaign text,
  notes text,
  status text not null default 'ACTIVE' check (status in ('ACTIVE','INACTIVE')),
  created_at timestamptz not null default timezone('utc', now()),
  updated_at timestamptz not null default timezone('utc', now())
);

create table if not exists public.services (
  id text primary key,
  tenant_id text not null references public.tenants(id) on delete cascade,
  name text not null,
  category text,
  description text,
  duration_minutes integer not null default 60,
  buffer_minutes integer not null default 0,
  price numeric(12,2) not null default 0,
  deposit numeric(12,2) not null default 0,
  color text not null default '#2563eb',
  location_type text not null default 'presencial' check (location_type in ('presencial','online','domicilio','ambos')),
  location_note text,
  bookable_online boolean not null default true,
  requires_confirmation boolean not null default false,
  capacity integer not null default 1,
  min_notice_hours integer not null default 0,
  max_advance_days integer not null default 60,
  client_instructions text,
  internal_notes text,
  status text not null default 'ACTIVE' check (status in ('ACTIVE','INACTIVE')),
  created_at timestamptz not null default timezone('utc', now()),
  updated_at timestamptz not null default timezone('utc', now())
);

create table if not exists public.requests (
  id text primary key,
  tenant_id text not null references public.tenants(id) on delete cascade,
  client_id text references public.clients(id) on delete set null,
  service_id text references public.services(id) on delete set null,
  name text not null,
  phone text,
  email text,
  desired_date date,
  desired_time time,
  message text,
  source text not null default 'Manual',
  utm_source text,
  utm_medium text,
  utm_campaign text,
  metadata jsonb,
  status text not null default 'NEW' check (status in ('NEW','CONTACTED','WAITING_CLIENT','SCHEDULED','DONE','LOST','ARCHIVED')),
  created_at timestamptz not null default timezone('utc', now()),
  updated_at timestamptz not null default timezone('utc', now())
);

create table if not exists public.appointments (
  id text primary key,
  tenant_id text not null references public.tenants(id) on delete cascade,
  client_id text not null references public.clients(id) on delete cascade,
  service_id text references public.services(id) on delete set null,
  request_id text references public.requests(id) on delete set null,
  starts_at timestamptz not null,
  ends_at timestamptz not null,
  status text not null default 'SCHEDULED' check (status in ('WAITING','SCHEDULED','CONFIRMED','IN_PROGRESS','DONE','CANCELLED','NO_SHOW')),
  source text not null default 'Manual',
  notes text,
  metadata jsonb,
  created_at timestamptz not null default timezone('utc', now()),
  updated_at timestamptz not null default timezone('utc', now()),
  constraint appointments_time_ok check (ends_at > starts_at)
);

create table if not exists public.calendar_blocks (
  id text primary key,
  tenant_id text not null references public.tenants(id) on delete cascade,
  starts_at timestamptz not null,
  ends_at timestamptz not null,
  reason text,
  all_day boolean not null default false,
  created_at timestamptz not null default timezone('utc', now())
);

create table if not exists public.custom_fields (
  id text primary key,
  tenant_id text not null references public.tenants(id) on delete cascade,
  label text not null,
  key text not null,
  type text not null check (type in ('text','number','date','select','textarea')),
  options jsonb,
  sort_order integer not null default 0,
  unique (tenant_id, key)
);

create table if not exists public.custom_field_values (
  id text primary key,
  tenant_id text not null references public.tenants(id) on delete cascade,
  field_id text not null references public.custom_fields(id) on delete cascade,
  client_id text not null references public.clients(id) on delete cascade,
  value text,
  unique (field_id, client_id)
);

create table if not exists public.webhooks (
  id text primary key,
  tenant_id text not null references public.tenants(id) on delete cascade,
  name text not null,
  direction text not null check (direction in ('INBOUND','OUTBOUND')),
  token text not null unique,
  secret text not null,
  url text,
  events jsonb not null default '[]'::jsonb,
  active boolean not null default true,
  created_at timestamptz not null default timezone('utc', now())
);

create table if not exists public.webhook_logs (
  id text primary key,
  tenant_id text not null references public.tenants(id) on delete cascade,
  webhook_id text references public.webhooks(id) on delete set null,
  event text,
  payload jsonb,
  response text,
  http_status integer,
  status text,
  source text,
  created_at timestamptz not null default timezone('utc', now())
);

create table if not exists public.notifications (
  id text primary key,
  tenant_id text not null references public.tenants(id) on delete cascade,
  title text not null,
  body text,
  read_flag boolean not null default false,
  created_at timestamptz not null default timezone('utc', now())
);

create table if not exists public.audit_logs (
  id text primary key,
  tenant_id text references public.tenants(id) on delete set null,
  user_id text references public.users(id) on delete set null,
  action text not null,
  entity text,
  entity_id text,
  created_at timestamptz not null default timezone('utc', now())
);

create table if not exists public.analytics_events (
  id text primary key,
  tenant_id text not null references public.tenants(id) on delete cascade,
  event text not null,
  source text,
  medium text,
  campaign text,
  metadata jsonb,
  created_at timestamptz not null default timezone('utc', now())
);

alter table public.tenants
  add constraint tenants_webhook_approved_by_fkey
  foreign key (webhook_approved_by) references public.users(id) on delete set null;

-- ---------------------------------------------------------------------------
-- Índices
-- ---------------------------------------------------------------------------
create index if not exists idx_users_tenant on public.users (tenant_id);
create index if not exists idx_users_auth on public.users (auth_user_id);
create index if not exists idx_clients_tenant on public.clients (tenant_id);
create index if not exists idx_clients_phone on public.clients (tenant_id, phone);
create index if not exists idx_clients_email on public.clients (tenant_id, email);
create index if not exists idx_services_tenant on public.services (tenant_id, status);
create index if not exists idx_appointments_tenant_time on public.appointments (tenant_id, starts_at);
create index if not exists idx_appointments_client on public.appointments (tenant_id, client_id);
create index if not exists idx_appointments_request on public.appointments (request_id);
create index if not exists idx_requests_tenant_status on public.requests (tenant_id, status);
create index if not exists idx_requests_desired on public.requests (tenant_id, desired_date);
create index if not exists idx_blocks_tenant_time on public.calendar_blocks (tenant_id, starts_at);
create index if not exists idx_webhooks_token on public.webhooks (token);
create index if not exists idx_webhook_logs_tenant on public.webhook_logs (tenant_id, created_at desc);
create index if not exists idx_notifications_unread on public.notifications (tenant_id, read_flag, created_at desc);
create index if not exists idx_audit_tenant on public.audit_logs (tenant_id, created_at desc);
create index if not exists idx_analytics_tenant on public.analytics_events (tenant_id, created_at desc);

-- ---------------------------------------------------------------------------
-- updated_at
-- ---------------------------------------------------------------------------
drop trigger if exists trg_tenants_updated_at on public.tenants;
create trigger trg_tenants_updated_at before update on public.tenants
for each row execute function public.set_updated_at();

drop trigger if exists trg_clients_updated_at on public.clients;
create trigger trg_clients_updated_at before update on public.clients
for each row execute function public.set_updated_at();

drop trigger if exists trg_services_updated_at on public.services;
create trigger trg_services_updated_at before update on public.services
for each row execute function public.set_updated_at();

drop trigger if exists trg_requests_updated_at on public.requests;
create trigger trg_requests_updated_at before update on public.requests
for each row execute function public.set_updated_at();

drop trigger if exists trg_appointments_updated_at on public.appointments;
create trigger trg_appointments_updated_at before update on public.appointments
for each row execute function public.set_updated_at();

-- ---------------------------------------------------------------------------
-- RLS — isolamento por tenant
-- ---------------------------------------------------------------------------
alter table public.plans enable row level security;
alter table public.segments enable row level security;
alter table public.platform_settings enable row level security;
alter table public.tenants enable row level security;
alter table public.users enable row level security;
alter table public.clients enable row level security;
alter table public.services enable row level security;
alter table public.requests enable row level security;
alter table public.appointments enable row level security;
alter table public.calendar_blocks enable row level security;
alter table public.custom_fields enable row level security;
alter table public.custom_field_values enable row level security;
alter table public.webhooks enable row level security;
alter table public.webhook_logs enable row level security;
alter table public.notifications enable row level security;
alter table public.audit_logs enable row level security;
alter table public.analytics_events enable row level security;

drop policy if exists plans_read on public.plans;
create policy plans_read on public.plans for select to authenticated using (true);
drop policy if exists plans_master on public.plans;
create policy plans_master on public.plans for all to authenticated using (public.is_master()) with check (public.is_master());

drop policy if exists segments_read on public.segments;
create policy segments_read on public.segments for select to authenticated using (true);
drop policy if exists segments_master on public.segments;
create policy segments_master on public.segments for all to authenticated using (public.is_master()) with check (public.is_master());

drop policy if exists platform_settings_master on public.platform_settings;
create policy platform_settings_master on public.platform_settings for all to authenticated
  using (public.is_master()) with check (public.is_master());

drop policy if exists tenants_select on public.tenants;
create policy tenants_select on public.tenants for select to authenticated
  using (public.is_master() or id = public.current_tenant_id());
drop policy if exists tenants_master_write on public.tenants;
create policy tenants_master_write on public.tenants for all to authenticated
  using (public.is_master()) with check (public.is_master());
drop policy if exists tenants_self_update on public.tenants;
create policy tenants_self_update on public.tenants for update to authenticated
  using (id = public.current_tenant_id()) with check (id = public.current_tenant_id());

drop policy if exists users_select on public.users;
create policy users_select on public.users for select to authenticated
  using (public.is_master() or tenant_id = public.current_tenant_id() or auth_user_id = auth.uid());
drop policy if exists users_master_write on public.users;
create policy users_master_write on public.users for all to authenticated
  using (public.is_master()) with check (public.is_master());

drop policy if exists clients_tenant on public.clients;
create policy clients_tenant on public.clients for all to authenticated
  using (public.is_master() or tenant_id = public.current_tenant_id())
  with check (public.is_master() or tenant_id = public.current_tenant_id());

drop policy if exists services_tenant on public.services;
create policy services_tenant on public.services for all to authenticated
  using (public.is_master() or tenant_id = public.current_tenant_id())
  with check (public.is_master() or tenant_id = public.current_tenant_id());

drop policy if exists requests_tenant on public.requests;
create policy requests_tenant on public.requests for all to authenticated
  using (public.is_master() or tenant_id = public.current_tenant_id())
  with check (public.is_master() or tenant_id = public.current_tenant_id());

drop policy if exists appointments_tenant on public.appointments;
create policy appointments_tenant on public.appointments for all to authenticated
  using (public.is_master() or tenant_id = public.current_tenant_id())
  with check (public.is_master() or tenant_id = public.current_tenant_id());

drop policy if exists calendar_blocks_tenant on public.calendar_blocks;
create policy calendar_blocks_tenant on public.calendar_blocks for all to authenticated
  using (public.is_master() or tenant_id = public.current_tenant_id())
  with check (public.is_master() or tenant_id = public.current_tenant_id());

drop policy if exists custom_fields_tenant on public.custom_fields;
create policy custom_fields_tenant on public.custom_fields for all to authenticated
  using (public.is_master() or tenant_id = public.current_tenant_id())
  with check (public.is_master() or tenant_id = public.current_tenant_id());

drop policy if exists custom_field_values_tenant on public.custom_field_values;
create policy custom_field_values_tenant on public.custom_field_values for all to authenticated
  using (public.is_master() or tenant_id = public.current_tenant_id())
  with check (public.is_master() or tenant_id = public.current_tenant_id());

drop policy if exists webhooks_tenant on public.webhooks;
create policy webhooks_tenant on public.webhooks for all to authenticated
  using (public.is_master() or tenant_id = public.current_tenant_id())
  with check (public.is_master() or tenant_id = public.current_tenant_id());

drop policy if exists webhook_logs_tenant on public.webhook_logs;
create policy webhook_logs_tenant on public.webhook_logs for all to authenticated
  using (public.is_master() or tenant_id = public.current_tenant_id())
  with check (public.is_master() or tenant_id = public.current_tenant_id());

drop policy if exists notifications_tenant on public.notifications;
create policy notifications_tenant on public.notifications for all to authenticated
  using (public.is_master() or tenant_id = public.current_tenant_id())
  with check (public.is_master() or tenant_id = public.current_tenant_id());

drop policy if exists audit_logs_tenant on public.audit_logs;
create policy audit_logs_tenant on public.audit_logs for select to authenticated
  using (public.is_master() or tenant_id = public.current_tenant_id());
drop policy if exists audit_logs_insert on public.audit_logs;
create policy audit_logs_insert on public.audit_logs for insert to authenticated
  with check (public.is_master() or tenant_id = public.current_tenant_id());

drop policy if exists analytics_events_tenant on public.analytics_events;
create policy analytics_events_tenant on public.analytics_events for all to authenticated
  using (public.is_master() or tenant_id = public.current_tenant_id())
  with check (public.is_master() or tenant_id = public.current_tenant_id());

grant usage on schema public to anon, authenticated, service_role;
grant select on public.plans, public.segments to anon, authenticated;
grant all on all tables in schema public to service_role;

-- Webhook público lê o token via service_role no backend.
-- Se precisar ingestão direta no banco, use Edge Function + service_role.

-- ---------------------------------------------------------------------------
-- Dados iniciais
-- ---------------------------------------------------------------------------
insert into public.plans (id, name, slug, description, active) values
  ('plan_starter', 'Starter', 'starter', 'Agenda, clientes, solicitações e webhooks.', true),
  ('plan_pro', 'Pro', 'pro', 'Métricas, relatórios e Google Sheets.', true),
  ('plan_business', 'Business', 'business', 'Para operações com mais volume.', true)
on conflict (id) do nothing;

insert into public.segments (id, name, slug, category, active) values
  ('seg_psicologia', 'Psicologia', 'psicologia', 'Saúde e Bem-Estar', true),
  ('seg_psiquiatria', 'Psiquiatria', 'psiquiatria', 'Saúde e Bem-Estar', true),
  ('seg_odontologia', 'Dentista', 'odontologia', 'Saúde e Bem-Estar', true),
  ('seg_fisioterapia', 'Fisioterapia', 'fisioterapia', 'Saúde e Bem-Estar', true),
  ('seg_nutricao', 'Nutrição', 'nutricao', 'Saúde e Bem-Estar', true),
  ('seg_veterinaria', 'Veterinário', 'veterinaria', 'Saúde e Bem-Estar', true),
  ('seg_barbearia', 'Barbearia', 'barbearia', 'Beleza e Estética', true),
  ('seg_salao', 'Cabeleireiro / Salão', 'salao', 'Beleza e Estética', true),
  ('seg_estetica', 'Estética', 'estetica', 'Beleza e Estética', true),
  ('seg_advocacia', 'Advogado', 'advocacia', 'Consultoria e Serviços Jurídicos', true),
  ('seg_contabilidade', 'Contabilista', 'contabilidade', 'Consultoria e Serviços Jurídicos', true),
  ('seg_imobiliaria', 'Corretor de Imóveis', 'imobiliaria', 'Consultoria e Serviços Jurídicos', true),
  ('seg_fotografia', 'Fotógrafo', 'fotografia', 'Serviços Técnicos e Especializados', true),
  ('seg_mecanica', 'Mecânico', 'mecanica', 'Serviços Técnicos e Especializados', true),
  ('seg_educacao', 'Professor particular', 'educacao', 'Educação e Desenvolvimento Pessoal', true),
  ('seg_personal', 'Personal Trainer', 'personal', 'Educação e Desenvolvimento Pessoal', true),
  ('seg_coaching', 'Coach / Mentor', 'coaching', 'Educação e Desenvolvimento Pessoal', true),
  ('seg_outros', 'Outros serviços', 'outros', 'Geral', true)
on conflict (id) do nothing;

commit;
