-- AUTO BACKUP R2: desligado por padrão nas contas novas; hashes evitam reenvio sem alteração.

alter table public.tenants
  add column if not exists auto_backup boolean not null default false;

alter table public.tenants
  add column if not exists auto_backup_at timestamptz;

alter table public.tenants
  add column if not exists auto_backup_hashes jsonb not null default '{}'::jsonb;

comment on column public.tenants.auto_backup is 'Backup diário 03:00 BRT no Cloudflare R2; novas contas começam desligado.';
comment on column public.tenants.auto_backup_hashes is 'SHA-256 do último CSV enviado por tipo; pasta não sobe se não houve mudança.';
