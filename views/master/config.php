<?php $platform = platform_settings(); ?>
<div class="card" style="padding:24px;max-width:720px;margin-bottom:16px">
<h1>Configurações da plataforma</h1>
<p>Nome do produto: <b>FirestepCRM</b></p>
<p>Fuso padrão: America/Sao_Paulo</p>
<p>PHP puro, SQLite local, sem Node e sem bibliotecas pesadas.</p>
</div>
<form method="post" action="/master/configuracoes/google" class="card" style="padding:20px;max-width:720px">
  <h2 style="margin-top:0">Login Google (OAuth)</h2>
  <p style="color:#667085">Essas credenciais abrem a tela oficial do Google para cada profissional conectar o Sheets.</p>
  <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
  <label class="label">URI de redirecionamento (copie no Google Cloud)</label>
  <input class="input" readonly value="<?= e(google_redirect_uri()) ?>" onclick="this.select()">
  <label class="label">Client ID</label>
  <input class="input" name="google_client_id" value="<?= e($platform['google_client_id'] ?? '') ?>" placeholder="xxxxx.apps.googleusercontent.com" required>
  <label class="label">Client Secret</label>
  <input class="input" type="password" name="google_client_secret" placeholder="<?= empty($platform['google_client_secret']) ? 'Cole o secret' : 'Deixe em branco para manter o atual' ?>">
  <ol style="color:#667085;font-size:13px;padding-left:18px">
    <li>Acesse console.cloud.google.com e crie um projeto.</li>
    <li>Ative a API Google Sheets.</li>
    <li>Crie credenciais OAuth do tipo Aplicativo da Web.</li>
    <li>Adicione a URI de redirecionamento acima.</li>
  </ol>
  <p><button class="btn btn-primary">Salvar Google OAuth</button></p>
</form>
