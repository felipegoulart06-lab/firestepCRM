-- Cabeçalho de folha (contratos/PDFs) por tenant.

alter table public.tenants
  add column if not exists letterhead_config jsonb not null default '{}'::jsonb;
