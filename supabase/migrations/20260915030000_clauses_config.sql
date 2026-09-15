-- Cláusulas de contrato por categoria (tenant).

alter table public.tenants
  add column if not exists clauses_config jsonb not null default '{}'::jsonb;
