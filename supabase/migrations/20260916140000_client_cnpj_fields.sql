-- Campos extras de cliente CNPJ

ALTER TABLE public.clients ADD COLUMN IF NOT EXISTS trade_name text;
ALTER TABLE public.clients ADD COLUMN IF NOT EXISTS state_registration text;
ALTER TABLE public.clients ADD COLUMN IF NOT EXISTS contact_name text;
