# Relatório — isolamento multi-tenant (FirestepCRM)

## Vulnerabilidades encontradas

1. **IDOR em JOINs** — `clients`/`services` ligados só por `id`, sem `tenant_id`. Com colisão de IDs (SQLite/legado) o painel poderia mostrar nome de outro tenant.
2. **IDOR em `custom_field_values`** — `UPDATE ... WHERE id=?` sem tenant.
3. **IDOR em webhook de saída** — `UPDATE webhooks WHERE id=?` sem tenant.
4. **Mass assignment** — `tenant_id` / `role` / `is_admin` podiam chegar em `$_POST`/`$_GET`.
5. **Open redirect** — `Location` aceitava URLs absolutas.
6. **CSRF** — token vazio + falta de checagem de Origin/Referer.
7. **SSRF** — webhook de saída podia apontar para localhost/rede privada.
8. **Agendamento** — `client_id` de outro tenant podia ser gravado no INSERT antes da validação.
9. **OAuth Google** — o tenant da sessão OAuth não era conferido com o usuário logado.
10. **Sessão** — logout não expirava o cookie; troca de senha não regenerava o ID.

O tenant operacional **já vinha da sessão** (`users.tenant_id`), não do body. Webhook de entrada já mapeava **token → hook.tenant_id**.

## Arquivos modificados

- `app/security.php` (novo)
- `app/helpers.php`, `app/core.php`, `app/sheets.php`
- `public/index.php`
- `tests/security.php`, `tests/isolation_static.php`, `tests/isolation_sqlite.php`
- `supabase/migrations/20260914200000_tenant_isolation_hardening.sql`
- este relatório

## Tabelas / policies

- `finance_entries` (CREATE IF NOT EXISTS + RLS `finance_entries_tenant`)
- RLS reafirmado em tabelas de CRM; `REVOKE` de `anon`
- índices por `tenant_id`

## Proteções implementadas

- Tenant só da sessão; fail-closed se empresa ausente/cancelada
- Strip de campos privilegiados no request
- Redirect apenas para caminhos internos
- CSRF + SameSite + Origin
- HSTS, `frame-ancestors 'none'`
- Rate limit de login por IP e por usuário
- Cookie de sessão apagado no logout; `session_regenerate_id` após senha
- SSRF em webhook HTTPS público
- Body de webhook ignora `tenant_id` de terceiro
- JOINs e mutações com `tenant_id`
- Cliente/solicitação validados no `create_appointment`

## Testes

```
php tests/security.php
php tests/isolation_static.php
php tests/isolation_sqlite.php
```

Simulam Tenant A vs B (GET/UPDATE/DELETE/busca/export/JOIN). Não substituem pentest autenticado em produção.

## Riscos que permanecem

- PHP usa a connection string do banco (role privilegiada): **RLS não bloqueia o backend**. RLS vale se alguém usar a chave `anon`/`authenticated` no Supabase.
- CORS `*` só no ingest público `/api/webhooks/{token}` (necessário para sites). Isolamento é pelo token.
- Sem 2FA, CAPTCHA, antivírus de upload nem filas Redis (o app não tem esses módulos).
- Super Admin (`MASTER`) vê todos os tenants por desenho; rotas `/master` exigem `role=MASTER` no servidor.
- Chave `service_role` do MCP Cursor aponta para **outro** projeto (`eocgftemfjjyhlvzneie`). O CRM usa `xcudogkslwoqvwsmxoit`.
