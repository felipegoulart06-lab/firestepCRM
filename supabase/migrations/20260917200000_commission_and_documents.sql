-- Comissão em agendamentos, documento do agente e atribuição no financeiro.

alter table public.appointments add column if not exists commission_agent_id text;
alter table public.appointments add column if not exists commission_type text;
alter table public.appointments add column if not exists commission_value numeric(12,2);
alter table public.appointments add column if not exists commission_amount numeric(12,2);

alter table public.users add column if not exists document_kind text;
alter table public.users add column if not exists cpf text;

alter table public.finance_entries add column if not exists agent_id text;
