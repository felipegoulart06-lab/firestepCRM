-- Cabeçalho de folha e assinatura eletrônica (contratos/PDFs) por tenant.

alter table public.tenants
  add column if not exists letterhead_config jsonb not null default '{}'::jsonb;

alter table public.tenants
  add column if not exists signature_config jsonb not null default '{}'::jsonb;
