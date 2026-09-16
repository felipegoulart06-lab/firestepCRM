-- Abrangência: localização de fornecedores CNPJ, clientes CNPJ e atendimentos externos

ALTER TABLE public.clients ADD COLUMN IF NOT EXISTS address text;
ALTER TABLE public.clients ADD COLUMN IF NOT EXISTS city text;
ALTER TABLE public.clients ADD COLUMN IF NOT EXISTS state text;
ALTER TABLE public.clients ADD COLUMN IF NOT EXISTS cep text;
ALTER TABLE public.clients ADD COLUMN IF NOT EXISTS lat double precision;
ALTER TABLE public.clients ADD COLUMN IF NOT EXISTS lng double precision;

ALTER TABLE public.appointments ADD COLUMN IF NOT EXISTS visit_type text DEFAULT 'interno';

CREATE TABLE IF NOT EXISTS public.suppliers (
  id text PRIMARY KEY,
  tenant_id text NOT NULL,
  name text NOT NULL,
  cnpj text NOT NULL,
  address text,
  city text,
  state text,
  cep text,
  lat double precision,
  lng double precision,
  notes text,
  created_at text NOT NULL
);

CREATE TABLE IF NOT EXISTS public.appointment_stops (
  id text PRIMARY KEY,
  tenant_id text NOT NULL,
  appointment_id text NOT NULL,
  sort_order integer DEFAULT 0,
  address text NOT NULL,
  lat double precision,
  lng double precision,
  created_at text NOT NULL
);

CREATE INDEX IF NOT EXISTS suppliers_tenant_idx ON public.suppliers (tenant_id);
CREATE INDEX IF NOT EXISTS appointment_stops_tenant_idx ON public.appointment_stops (tenant_id, appointment_id);
