-- Menu inicial do CRM por empresa (Configurações → Avançado).

alter table public.tenants
  add column if not exists home_path text not null default '/app/agenda';
