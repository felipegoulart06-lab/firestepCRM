# Landing FirestepCRM

Site estático do domínio **https://firestep.cloud**. Projeto separado do CRM PHP (`crm.firestep.cloud`).

Não use o `vercel.json` da pasta do CRM. Esta pasta é o projeto inteiro: HTML, CSS e JS, sem build.

## Publicar na Vercel (projeto novo)

1. Crie um repositório só com o conteúdo desta pasta (não importe `firestepCRM`).
2. Em [vercel.com/new](https://vercel.com/new), importe esse repositório.
3. Framework Preset: **Other**.
4. Root Directory: `.` (raiz).
5. Build Command e Output Directory: vazios.
6. Domínio: `firestep.cloud` (e `www` no mesmo projeto).

Projeto Vercel já criado: **firestep-cloud** (separado de `firestep-crm`).
URL atual: https://firestep-cloud.vercel.app

O painel continua no projeto **firestep-crm**, domínio `crm.firestep.cloud`.

## Formulário de teste

Em `script.js`, `WA` é o WhatsApp comercial (DDI+DDD+número, só dígitos). Se ficar o placeholder, o pedido vai para `contato@firestep.cloud`.
