-- Tipo de preço do serviço: cobrado, convênio, cortesia ou reunião

ALTER TABLE public.services ADD COLUMN IF NOT EXISTS price_kind text NOT NULL DEFAULT 'priced';
