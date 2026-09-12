-- Checkup idempotente: colunas, índices, sessões Vercel, plano business e RLS restante.
-- Pode rodar em banco novo ou já migrado.

alter table public.users add column if not exists last_login_at timestamptz;

create unique index if not exists users_email_lc on public.users (lower(email));
create unique index if not exists users_username_lc on public.users (lower(username));

insert into public.plans (id, name, slug, description, active)
values ('plan_business', 'Business', 'business', 'Para operações com mais volume.', true)
on conflict (slug) do nothing;

create table if not exists public.php_sessions (
  id text primary key,
  data text not null,
  expires_at timestamptz not null
);

create table if not exists public.rate_limits (
  key text not null,
  hit_at timestamptz not null default now()
);

create index if not exists idx_php_sessions_exp on public.php_sessions (expires_at);
create index if not exists idx_rate_limits_key on public.rate_limits (key, hit_at);

alter table public.php_sessions enable row level security;
alter table public.rate_limits enable row level security;
alter table public.php_sessions force row level security;
alter table public.rate_limits force row level security;

drop policy if exists php_sessions_no_client_access on public.php_sessions;
create policy php_sessions_no_client_access on public.php_sessions
  for all to authenticated using (false) with check (false);

drop policy if exists rate_limits_no_client_access on public.rate_limits;
create policy rate_limits_no_client_access on public.rate_limits
  for all to authenticated using (false) with check (false);

revoke all on public.php_sessions from public, anon, authenticated;
revoke all on public.rate_limits from public, anon, authenticated;
grant all on public.php_sessions to service_role;
grant all on public.rate_limits to service_role;

drop trigger if exists trg_protect_user on public.users;
create trigger trg_protect_user
before update on public.users
for each row execute function public.protect_user_privileged_columns();

do $$
begin
  if to_regclass('public.firestep_pedidos') is not null then
    execute 'alter table public.firestep_pedidos enable row level security';
    execute 'alter table public.firestep_pedidos force row level security';
    execute 'drop policy if exists firestep_pedidos_select on public.firestep_pedidos';
    execute 'drop policy if exists firestep_pedidos_update on public.firestep_pedidos';
    execute 'drop policy if exists firestep_pedidos_insert on public.firestep_pedidos';
    execute $p$create policy firestep_pedidos_insert on public.firestep_pedidos for insert to anon, authenticated with check (true)$p$;
    execute 'revoke select, update, delete on public.firestep_pedidos from anon, authenticated';
    execute 'grant insert on public.firestep_pedidos to anon, authenticated';
    execute 'grant all on public.firestep_pedidos to service_role';
  end if;
end $$;
