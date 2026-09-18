-- Livro de anotações da equipe (logbook) com opção de aparecer na Agenda.

create table if not exists public.user_notes (
  id text primary key,
  tenant_id text not null,
  user_id text not null,
  title text not null,
  body text,
  note_date text not null,
  show_on_agenda boolean not null default false,
  created_at text not null,
  updated_at text not null
);

create index if not exists user_notes_tenant_date on public.user_notes (tenant_id, note_date);
