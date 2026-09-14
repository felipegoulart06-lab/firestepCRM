-- Origem dos lançamentos (agendamento) e campos de pagamento.

alter table public.finance_entries add column if not exists source_type text;
alter table public.finance_entries add column if not exists source_id text;
alter table public.finance_entries add column if not exists amount_paid numeric(12,2) not null default 0;
alter table public.finance_entries add column if not exists payment_method text;

create unique index if not exists idx_finance_source
  on public.finance_entries (tenant_id, source_type, source_id)
  where source_id is not null and source_type is not null;
