<?php
$platform = platform_settings();
$ready = google_oauth_ready();
$edit = ($_GET['edit'] ?? '') === 'google';
$clientId = (string)($platform['google_client_id'] ?? '');
$hasSecret = trim((string)($platform['google_client_secret'] ?? '')) !== '';
$fromEnv = (bool)(env_str('GOOGLE_CLIENT_ID') || env_str('GOOGLE_CLIENT_SECRET'));
$maskId = static function (string $id): string {
    $id = trim($id);
    if ($id === '') {
        return 'Não cadastrado';
    }
    if (strlen($id) < 18) {
        return substr($id, 0, 6).'…';
    }
    return substr($id, 0, 10).'…'.substr($id, -24);
};
?>
<div class="page-head">
  <div>
    <h1>Ajustes técnicos</h1>
    <p class="subtitle">Somente para quem configura o projeto no Google Cloud. O cliente SaaS não vê estes campos — ele só clica em Entrar com o Google.</p>
  </div>
  <a class="btn btn-ghost" href="/master/configuracoes">Voltar</a>
</div>
<div class="card settings-panel" style="max-width:680px">
  <div class="settings-panel-head">
    <div>
      <h2>Credenciais OAuth da plataforma</h2>
      <p>Client ID e Secret do aplicativo Web. Não é o login de nenhum profissional.</p>
    </div>
    <?php if ($ready): ?>
      <span class="badge" style="background:#dcfce7;color:#166534">Pronto para login</span>
    <?php else: ?>
      <span class="badge" style="background:#fffaeb;color:#b54708">Pendente</span>
    <?php endif; ?>
  </div>

  <label class="label">URI de redirecionamento (cole no Google Cloud)</label>
  <input class="input" readonly value="<?= e(google_redirect_uri()) ?>" onclick="this.select()">
  <p class="settings-hint">Authorized redirect URI no console: exatamente este endereço.</p>

  <?php if (!$edit): ?>
    <dl class="settings-kv" style="margin-top:12px">
      <div><dt>Client ID</dt><dd><code><?= e($maskId($clientId)) ?></code></dd></div>
      <div><dt>Client Secret</dt><dd><?= $hasSecret ? 'Cadastrado · oculto' : 'Não cadastrado' ?></dd></div>
    </dl>
    <?php if ($fromEnv): ?>
      <p class="settings-hint">Há variáveis de ambiente no servidor. Elas prevalecem sobre o que estiver salvo aqui.</p>
    <?php endif; ?>
    <p class="settings-hint">O Secret nunca é mostrado de novo. Para trocar, abra Editar e marque explicitamente a substituição.</p>
    <div class="settings-actions">
      <a class="btn btn-primary" href="/master/configuracoes/tecnico?edit=google">Editar credenciais</a>
    </div>
  <?php else: ?>
    <form method="post" action="/master/configuracoes/google" id="google-creds-form">
      <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
      <input type="hidden" name="current_client_id" value="<?= e($clientId) ?>">
      <label class="label">Client ID</label>
      <input class="input" name="google_client_id" value="<?= e($clientId) ?>" placeholder="123456789-xxxx.apps.googleusercontent.com" autocomplete="off">
      <?php if ($clientId !== ''): ?>
        <label class="check-row" style="margin-top:8px">
          <input type="checkbox" name="confirm_id_change" value="1">
          Confirmo que quero trocar o Client ID atual
        </label>
      <?php endif; ?>
      <label class="label" style="margin-top:14px">Client Secret</label>
      <?php if ($hasSecret): ?>
        <p class="settings-hint" style="margin-bottom:8px">Já existe um secret. Ele <b>não</b> será alterado a menos que você marque a opção abaixo e cole o novo valor.</p>
        <label class="check-row">
          <input type="checkbox" name="replace_secret" value="1" id="replace-secret">
          Quero substituir o Client Secret
        </label>
        <input class="input" type="password" name="google_client_secret" id="google-secret" autocomplete="new-password" placeholder="Cole o novo secret" disabled style="margin-top:8px">
      <?php else: ?>
        <input class="input" type="password" name="google_client_secret" autocomplete="new-password" placeholder="Cole o secret do Google Cloud">
      <?php endif; ?>
      <ol class="muted" style="padding-left:18px">
        <li>Em console.cloud.google.com, crie (ou abra) o projeto da Firestep.</li>
        <li>Ative a <b>Google Sheets API</b> uma vez neste projeto.</li>
        <li>Credenciais → OAuth 2.0 → tipo Aplicativo da Web.</li>
        <li>Adicione a URI de redirecionamento acima e cole aqui o Client ID e o Secret.</li>
      </ol>
      <div class="settings-actions">
        <a class="btn btn-ghost" href="/master/configuracoes/tecnico">Cancelar</a>
        <button class="btn btn-primary">Salvar alterações</button>
      </div>
    </form>
    <script>
      (function(){
        const box = document.getElementById('replace-secret');
        const secret = document.getElementById('google-secret');
        if (box && secret) {
          box.addEventListener('change', ()=>{
            secret.disabled = !box.checked;
            if (!box.checked) secret.value = '';
          });
        }
        document.getElementById('google-creds-form')?.addEventListener('submit', (e)=>{
          if (!confirm('Salvar só o que você confirmou nesta tela. O secret atual não muda se a substituição não estiver marcada. Continuar?')) {
            e.preventDefault();
          }
        });
      })();
    </script>
  <?php endif; ?>
</div>
