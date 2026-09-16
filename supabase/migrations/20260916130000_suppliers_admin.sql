-- Fornecedores: dados administrativos (CPF/CNPJ, produto, contato)

ALTER TABLE public.suppliers ADD COLUMN IF NOT EXISTS document_kind text DEFAULT 'cnpj';
ALTER TABLE public.suppliers ADD COLUMN IF NOT EXISTS product_type text;
ALTER TABLE public.suppliers ADD COLUMN IF NOT EXISTS phone text;
ALTER TABLE public.suppliers ADD COLUMN IF NOT EXISTS email text;
ALTER TABLE public.suppliers ADD COLUMN IF NOT EXISTS contact_name text;
