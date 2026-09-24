-- Trial de 30 dias, planos SaaS e cobranças acompanhadas pelo Admin Master.

alter table public.tenants add column if not exists trial_ends_at timestamptz;
alter table public.tenants add column if not exists billing_cycle text;
alter table public.tenants add column if not exists communicate_addon boolean not null default false;
alter table public.tenants add column if not exists billing_paid_until timestamptz;
alter table public.tenants add column if not exists billing_status text not null default 'trial';
alter table public.tenants add column if not exists billing_requested_at timestamptz;
alter table public.tenants add column if not exists billing_requested_cycle text;
alter table public.tenants add column if not exists billing_requested_addon boolean not null default false;

create table if not exists public.saas_invoices (
  id text primary key,
  tenant_id text not null references public.tenants(id) on delete cascade,
  cycle text not null,
  communicate_addon boolean not null default false,
  months integer not null,
  amount numeric(12,2) not null default 0,
  status text not null default 'open',
  period_start timestamptz,
  period_end timestamptz,
  created_at timestamptz not null default timezone('utc', now()),
  paid_at timestamptz,
  paid_by text,
  notes text
);

create index if not exists saas_invoices_tenant on public.saas_invoices (tenant_id, created_at desc);
create index if not exists saas_invoices_status on public.saas_invoices (status, created_at desc);

alter table public.saas_invoices enable row level security;
alter table public.saas_invoices force row level security;

drop policy if exists saas_invoices_read on public.saas_invoices;
create policy saas_invoices_read on public.saas_invoices for select to authenticated
  using (public.is_master() or tenant_id = public.current_tenant_id());

drop policy if exists saas_invoices_master on public.saas_invoices;
create policy saas_invoices_master on public.saas_invoices for all to authenticated
  using (public.is_master()) with check (public.is_master());

grant select, insert, update, delete on public.saas_invoices to authenticated;

create or replace function public.protect_tenant_privileged_columns()
returns trigger
language plpgsql
security definer
set search_path = pg_catalog, public
as $$
begin
  if auth.uid() is null then
    return new;
  end if;
  if not public.is_master() then
    new.plan := old.plan;
    new.status := old.status;
    new.slug := old.slug;
    new.webhook_access := old.webhook_access;
    new.webhook_requested_at := old.webhook_requested_at;
    new.webhook_approved_at := old.webhook_approved_at;
    new.webhook_approved_by := old.webhook_approved_by;
    new.sheets_config := old.sheets_config;
    new.trial_ends_at := old.trial_ends_at;
    new.billing_cycle := old.billing_cycle;
    new.communicate_addon := old.communicate_addon;
    new.billing_paid_until := old.billing_paid_until;
    new.billing_status := old.billing_status;
    new.billing_requested_at := old.billing_requested_at;
    new.billing_requested_cycle := old.billing_requested_cycle;
    new.billing_requested_addon := old.billing_requested_addon;
  end if;
  return new;
end;
$$;

comment on table public.saas_invoices is 'Cobranças dos planos SaaS (mensal, semestral, anual) + adicional Comunicador.';
