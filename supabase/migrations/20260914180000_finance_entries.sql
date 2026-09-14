-- Financeiro: lançamentos, receber, faturado e pagar por tenant.

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

drop trigger if exists finance_entries_updated_at on public.finance_entries;
create trigger finance_entries_updated_at
  before update on public.finance_entries
  for each row execute function public.set_updated_at();

alter table public.finance_entries enable row level security;

drop policy if exists finance_entries_tenant on public.finance_entries;
create policy finance_entries_tenant on public.finance_entries for all to authenticated
  using (public.is_master() or tenant_id = public.current_tenant_id())
  with check (public.is_master() or tenant_id = public.current_tenant_id());

grant select, insert, update, delete on public.finance_entries to authenticated;
grant all on public.finance_entries to service_role;
