-- FirestepCRM — RLS forçado, grants mínimos, auth por convite, funções de sessão.

create or replace function public.set_updated_at()
returns trigger
language plpgsql
set search_path = pg_catalog, public
as $$
begin
  new.updated_at = timezone('utc', now());
  return new;
end;
$$;

create or replace function public.current_tenant_id()
returns text
language sql
stable
security definer
set search_path = pg_catalog, public
as $$
  select u.tenant_id
  from public.users u
  where u.auth_user_id = auth.uid()
    and u.active = true
  limit 1;
$$;

create or replace function public.is_master()
returns boolean
language sql
stable
security definer
set search_path = pg_catalog, public
as $$
  select exists (
    select 1
    from public.users u
    where u.auth_user_id = auth.uid()
      and u.role = 'MASTER'
      and u.active = true
  );
$$;

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
  end if;
  return new;
end;
$$;

create or replace function public.protect_user_privileged_columns()
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
    new.role := old.role;
    new.tenant_id := old.tenant_id;
    new.auth_user_id := old.auth_user_id;
    new.email := old.email;
    new.username := old.username;
    new.password_hash := old.password_hash;
    new.active := old.active;
  end if;
  return new;
end;
$$;

drop trigger if exists trg_protect_tenant on public.tenants;
create trigger trg_protect_tenant
before update on public.tenants
for each row execute function public.protect_tenant_privileged_columns();

drop trigger if exists trg_protect_user on public.users;
create trigger trg_protect_user
before update on public.users
for each row execute function public.protect_user_privileged_columns();

create or replace function public.protect_webhook_columns()
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
    new.token := old.token;
    new.secret := old.secret;
    new.tenant_id := old.tenant_id;
    new.direction := old.direction;
  end if;
  return new;
end;
$$;

drop trigger if exists trg_protect_webhook on public.webhooks;
create trigger trg_protect_webhook
before update on public.webhooks
for each row execute function public.protect_webhook_columns();

-- Auth: só vincula login se o e-mail já foi convidado em public.users
create or replace function public.handle_auth_user_created()
returns trigger
language plpgsql
security definer
set search_path = pg_catalog, public
as $$
begin
  update public.users
     set auth_user_id = new.id
   where lower(email) = lower(new.email)
     and active = true
     and (auth_user_id is null or auth_user_id = new.id);
  return new;
end;
$$;

drop trigger if exists on_auth_user_created on auth.users;
create trigger on_auth_user_created
after insert on auth.users
for each row execute function public.handle_auth_user_created();

create table if not exists public.tenant_secrets (
  tenant_id text primary key references public.tenants(id) on delete cascade,
  sheets_config jsonb not null default '{}'::jsonb,
  created_at timestamptz not null default timezone('utc', now()),
  updated_at timestamptz not null default timezone('utc', now())
);

drop trigger if exists trg_tenant_secrets_updated_at on public.tenant_secrets;
create trigger trg_tenant_secrets_updated_at before update on public.tenant_secrets
for each row execute function public.set_updated_at();

-- FORCE RLS em todas as tabelas de negócio
alter table public.plans force row level security;
alter table public.segments force row level security;
alter table public.platform_settings force row level security;
alter table public.tenants force row level security;
alter table public.users force row level security;
alter table public.clients force row level security;
alter table public.services force row level security;
alter table public.requests force row level security;
alter table public.appointments force row level security;
alter table public.calendar_blocks force row level security;
alter table public.custom_fields force row level security;
alter table public.custom_field_values force row level security;
alter table public.webhooks force row level security;
alter table public.webhook_logs force row level security;
alter table public.notifications force row level security;
alter table public.audit_logs force row level security;
alter table public.analytics_events force row level security;
alter table public.tenant_secrets enable row level security;
alter table public.tenant_secrets force row level security;

-- Políticas mais estreitas
drop policy if exists webhook_logs_tenant on public.webhook_logs;
drop policy if exists webhook_logs_select on public.webhook_logs;
drop policy if exists webhook_logs_insert_master on public.webhook_logs;
create policy webhook_logs_select on public.webhook_logs
  for select to authenticated
  using (public.is_master() or tenant_id = public.current_tenant_id());
create policy webhook_logs_insert_master on public.webhook_logs
  for insert to authenticated
  with check (public.is_master());

drop policy if exists webhooks_tenant on public.webhooks;
drop policy if exists webhooks_select on public.webhooks;
drop policy if exists webhooks_write_master on public.webhooks;
drop policy if exists webhooks_update_tenant on public.webhooks;
create policy webhooks_select on public.webhooks
  for select to authenticated
  using (public.is_master() or tenant_id = public.current_tenant_id());
create policy webhooks_write_master on public.webhooks
  for all to authenticated
  using (public.is_master())
  with check (public.is_master());
create policy webhooks_update_tenant on public.webhooks
  for update to authenticated
  using (tenant_id = public.current_tenant_id())
  with check (tenant_id = public.current_tenant_id());

drop policy if exists users_self_update on public.users;
create policy users_self_update on public.users
  for update to authenticated
  using (auth_user_id = auth.uid())
  with check (auth_user_id = auth.uid());

-- Segredos: ninguém autenticado lê via PostgREST; só service_role (bypass RLS)
-- (nenhuma policy de SELECT para authenticated)

revoke all on schema public from public;
grant usage on schema public to anon, authenticated, service_role;
revoke create on schema public from public, anon, authenticated;

revoke all on all tables in schema public from public, anon, authenticated;
revoke all on all sequences in schema public from public, anon, authenticated;
revoke all on all functions in schema public from public, anon, authenticated;

grant select on public.plans, public.segments to authenticated;

grant select, insert, update, delete on
  public.clients,
  public.services,
  public.requests,
  public.appointments,
  public.calendar_blocks,
  public.custom_fields,
  public.custom_field_values,
  public.notifications,
  public.analytics_events
to authenticated;

grant select, insert, update, delete on public.tenants to authenticated;
grant select, insert, update, delete on public.users to authenticated;
grant select, insert on public.audit_logs to authenticated;
grant select, insert, update, delete on public.webhooks to authenticated;
grant select, insert on public.webhook_logs to authenticated;
grant select, insert, update, delete on public.plans, public.segments, public.platform_settings to authenticated;

grant all on all tables in schema public to service_role;
grant all on all sequences in schema public to service_role;
grant all on all functions in schema public to service_role;

revoke all on public.tenant_secrets from anon, authenticated;
grant all on public.tenant_secrets to service_role;

revoke execute on function public.current_tenant_id() from public, anon;
grant execute on function public.current_tenant_id() to authenticated, service_role;

revoke execute on function public.is_master() from public, anon;
grant execute on function public.is_master() to authenticated, service_role;

revoke execute on function public.set_updated_at() from public, anon, authenticated;
revoke execute on function public.protect_tenant_privileged_columns() from public, anon, authenticated;
revoke execute on function public.protect_user_privileged_columns() from public, anon, authenticated;
revoke execute on function public.protect_webhook_columns() from public, anon, authenticated;
revoke execute on function public.handle_auth_user_created() from public, anon, authenticated;
do $$
begin
  if exists (
    select 1 from pg_proc p
    join pg_namespace n on n.oid = p.pronamespace
    where n.nspname = 'public' and p.proname = 'rls_auto_enable'
  ) then
    execute 'revoke execute on function public.rls_auto_enable() from public, anon, authenticated';
  end if;
end $$;

alter default privileges in schema public revoke all on tables from public, anon;
alter default privileges in schema public revoke all on functions from public, anon;
alter default privileges in schema public revoke all on sequences from public, anon;

insert into public.platform_settings (key, value)
values ('auth_mode', jsonb_build_object('invite_only', true, 'email', true, 'anonymous', false))
on conflict (key) do update set value = excluded.value, updated_at = timezone('utc', now());

drop policy if exists tenant_secrets_no_client_access on public.tenant_secrets;
create policy tenant_secrets_no_client_access on public.tenant_secrets
  for all to authenticated
  using (false)
  with check (false);
