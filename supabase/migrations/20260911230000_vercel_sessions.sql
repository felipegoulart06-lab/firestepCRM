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

revoke all on public.php_sessions from anon, authenticated;
revoke all on public.rate_limits from anon, authenticated;
grant all on public.php_sessions to service_role;
grant all on public.rate_limits to service_role;
