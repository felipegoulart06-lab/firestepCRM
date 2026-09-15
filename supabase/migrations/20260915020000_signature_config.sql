-- Assinatura eletrônica nos contratos (idempotente).

alter table public.tenants
  add column if not exists signature_config jsonb not null default '{}'::jsonb;
