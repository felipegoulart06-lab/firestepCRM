<?php $platform = platform_settings(); $ready = google_oauth_ready(); ?>
<div class="page-head">
  <div>
    <h1>Ajustes técnicos</h1>
    <p class="subtitle">Somente para quem configura o projeto no Google Cloud. O cliente SaaS não vê estes campos — ele só clica em Entrar com o Google.</p>
  </div>
  <a class="btn btn-ghost" href="/master/configuracoes">Voltar</a>
</div>
<div class="card" style="padding:22px;max-width:680px">
  <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;margin-bottom:14px">
    <div>
      <h2 style="margin:0 0 4px">Credenciais OAuth da plataforma</h2>
      <p class="muted" style="margin:0">Client ID e Secret do aplicativo Web. Não é o login de nenhum profissional.</p>
    </div>
    <?php if ($ready): ?>
      <span class="badge" style="background:#dcfce7;color:#166534">Pronto para login</span>
    <?php else: ?>
      <span class="badge" style="background:#fffaeb;color:#b54708">Pendente</span>
    <?php endif; ?>
  </div>
  <form method="post" action="/master/configuracoes/google">
    <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
    <label class="label">URI de redirecionamento (cole no Google Cloud)</label>
    <input class="input" readonly value="<?= e(google_redirect_uri()) ?>" onclick="this.select()">
    <p class="muted">Authorized redirect URI no console: exatamente este endereço.</p>
    <label class="label">Client ID</label>
    <input class="input" name="google_client_id" value="<?= e($platform['google_client_id'] ?? '') ?>" placeholder="123456789-xxxx.apps.googleusercontent.com" autocomplete="off">
    <label class="label">Client Secret</label>
    <input class="input" type="password" name="google_client_secret" autocomplete="new-password" placeholder="<?= empty($platform['google_client_secret']) ? 'Cole o secret do Google Cloud' : 'Deixe em branco para manter o atual' ?>">
    <ol class="muted" style="padding-left:18px">
      <li>Em console.cloud.google.com, crie (ou abra) o projeto da Firestep.</li>
      <li>Ative a <b>Google Sheets API</b> uma vez neste projeto.</li>
      <li>Credenciais → OAuth 2.0 → tipo Aplicativo da Web.</li>
      <li>Adicione a URI de redirecionamento acima e cole aqui o Client ID e o Secret.</li>
    </ol>
    <p><button class="btn btn-primary">Salvar credenciais da plataforma</button></p>
  </form>
</div>
