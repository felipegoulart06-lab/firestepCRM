-- Dúvidas do assistente do painel (fluxo guiado + Admin Master).

create table if not exists public.assistant_threads (
  id text primary key,
  tenant_id text not null,
  user_id text not null,
  question text not null,
  answer text,
  status text not null default 'open',
  created_at timestamptz not null default timezone('utc', now()),
  answered_at timestamptz,
  answered_by text
);

create index if not exists assistant_threads_tenant on public.assistant_threads(tenant_id, created_at);
create index if not exists assistant_threads_status on public.assistant_threads(status, created_at);
