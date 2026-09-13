-- add one-time access token audit fields for saas tenants

alter table public.tenants
  add column if not exists access_token_generated_at timestamptz,
  add column if not exists access_token_viewed_at timestamptz;
