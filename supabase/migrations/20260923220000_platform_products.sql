-- Catálogo de produtos digitais da plataforma (Admin Master → cards no CRM).

create table if not exists public.platform_products (
  id text primary key,
  title text not null,
  summary text not null default '',
  description text not null default '',
  image_url text not null default '',
  link_url text not null default '',
  sort_order integer not null default 0,
  active boolean not null default true,
  created_at timestamptz not null default timezone('utc', now()),
  updated_at timestamptz not null default timezone('utc', now())
);

create index if not exists platform_products_sort on public.platform_products(sort_order, title);

create table if not exists public.platform_product_leads (
  id text primary key,
  product_id text not null,
  tenant_id text not null,
  user_id text not null,
  created_at timestamptz not null default timezone('utc', now())
);

create index if not exists platform_product_leads_created on public.platform_product_leads(created_at desc);

comment on table public.platform_products is 'Produtos digitais cadastrados pelo Admin Master e exibidos no menu Produtos do CRM.';
