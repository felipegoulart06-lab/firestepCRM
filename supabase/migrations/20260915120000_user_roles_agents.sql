-- Roles: user_admin (Master), user_crm (empresa), user_agent (funcionário)

ALTER TABLE public.users DROP CONSTRAINT IF EXISTS users_role_check;

ALTER TABLE public.users
  ADD CONSTRAINT users_role_check
  CHECK (role IN ('user_admin', 'user_crm', 'user_agent', 'MASTER', 'TENANT_ADMIN'));

UPDATE public.users SET role = 'user_admin' WHERE role = 'MASTER';
UPDATE public.users SET role = 'user_crm' WHERE role = 'TENANT_ADMIN';

ALTER TABLE public.users DROP CONSTRAINT IF EXISTS users_role_check;

ALTER TABLE public.users
  ADD CONSTRAINT users_role_check
  CHECK (role IN ('user_admin', 'user_crm', 'user_agent'));

CREATE OR REPLACE FUNCTION public.is_master()
RETURNS boolean
LANGUAGE sql
STABLE
SECURITY DEFINER
SET search_path = public
AS $$
  SELECT EXISTS (
    SELECT 1
    FROM public.users u
    WHERE u.auth_user_id = auth.uid()
      AND u.role IN ('user_admin', 'MASTER')
      AND u.active = true
  );
$$;
