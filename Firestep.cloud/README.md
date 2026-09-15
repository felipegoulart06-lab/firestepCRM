# Landing FirestepCRM

Site estático para o domínio **firestep.cloud**. Não use o `vercel.json` da raiz do repositório (esse é o CRM em PHP).

## Publicar na Vercel

1. [New Project](https://vercel.com/new) → importe `firestepCRM`.
2. **Root Directory:** `Firestep.cloud`
3. Framework: **Other** (sem build).
4. Domínio: `firestep.cloud` (e `www` apontando para o mesmo projeto).

O CRM continua em outro projeto, com Root Directory na raiz e domínio `crm.firestep.cloud`.

## Formulário de teste

Em `script.js`, preencha `SITE.whatsapp` com DDI+DDD+número (só dígitos). Se ficar vazio, o pedido vai para `contato@firestep.cloud`.
