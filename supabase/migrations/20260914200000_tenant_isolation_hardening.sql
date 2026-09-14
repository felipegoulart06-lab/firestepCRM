-- Blindagem extra de isolamento: índices, finance_entries e RLS (2ª camada).
-- O PHP conecta como role de serviço/dono; RLS protege chaves anon/authenticated.

create table if not exists public.finance_entries (
  id text primary key,
  tenant_id text not null references public.tenants(id) on delete cascade,
  kind text not null check (kind in ('entry','receivable','payable')),
  flow text not null default 'in' check (flow in ('in','out')),
  status text not null default 'open' check (status in ('open','paid','billed','cancelled')),
  description text not null,
  amount numeric(12,2) not null default 0,
  due_date date,
  paid_at timestamptz,
  client_id text references public.clients(id) on delete set null,
  notes text,
  created_at timestamptz not null default timezone('utc', now()),
  updated_at timestamptz not null default timezone('utc', now())
);

create index if not exists idx_finance_entries_tenant on public.finance_entries (tenant_id, kind, status);
create index if not exists idx_clients_tenant on public.clients (tenant_id);
create index if not exists idx_appointments_tenant on public.appointments (tenant_id, starts_at);
create index if not exists idx_requests_tenant on public.requests (tenant_id, created_at);
create index if not exists idx_services_tenant on public.services (tenant_id);
create index if not exists idx_webhooks_tenant on public.webhooks (tenant_id);
create index if not exists idx_custom_field_values_tenant on public.custom_field_values (tenant_id);

alter table public.finance_entries enable row level security;
alter table public.clients enable row level security;
alter table public.appointments enable row level security;
alter table public.requests enable row level security;
alter table public.services enable row level security;
alter table public.webhooks enable row level security;
alter table public.custom_field_values enable row level security;
alter table public.notifications enable row level security;
alter table public.calendar_blocks enable row level security;
alter table public.analytics_events enable row level security;
alter table public.audit_logs enable row level security;
alter table public.webhook_logs enable row level security;

drop policy if exists finance_entries_tenant on public.finance_entries;
create policy finance_entries_tenant on public.finance_entries for all to authenticated
  using (public.is_master() or tenant_id = public.current_tenant_id())
  with check (public.is_master() or tenant_id = public.current_tenant_id());

grant select, insert, update, delete on public.finance_entries to authenticated;
grant all on public.finance_entries to service_role;

revoke all on public.finance_entries from anon;
revoke all on public.clients from anon;
revoke all on public.appointments from anon;
revoke all on public.requests from anon;
revoke all on public.services from anon;
revoke all on public.webhooks from anon;
revoke all on public.custom_field_values from anon;
revoke all on public.notifications from anon;
revoke all on public.calendar_blocks from anon;
revoke all on public.analytics_events from anon;
revoke all on public.audit_logs from anon;
revoke all on public.webhook_logs from anon;
revoke all on public.tenants from anon;
revoke all on public.users from anon;
