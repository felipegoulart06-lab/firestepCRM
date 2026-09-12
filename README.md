# FirestepCRM — CRM em PHP

Sistema rápido, multi-tenant, **sem Node e sem bibliotecas pesadas**. Cada clique no menu é uma página PHP pronta — abre na hora.

## Como abrir

Dê dois cliques em **`iniciar.bat`**.

Ou no terminal, nesta pasta:

```bash
php -c php.ini -S localhost:8080 -t public public/router.php
```

Abra [http://localhost:8080](http://localhost:8080)

| Perfil | Login |
|---|---|
| Admin Master (único) | `nathan.k@example.net` |

A senha do Admin Master no Supabase Auth é gerada no provisionamento e deve ser trocada no primeiro acesso. Contas de demonstração foram removidas.

O banco SQLite é criado sozinho em `storage/nexo.sqlite` no primeiro acesso.

## Webhook

No painel: **Webhooks**. `POST` para:

`http://localhost:8080/api/webhooks/<token>`

Requer PHP 8.1+ com extensão `pdo_sqlite` (já vem no PHP Windows).
