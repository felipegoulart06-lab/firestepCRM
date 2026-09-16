<?php
$platform = platform_settings();
$ready = google_oauth_ready();
$editGoogle = ($_GET['edit'] ?? '') === 'google';
$editLeaflet = ($_GET['edit'] ?? '') === 'leaflet';
$clientId = (string)($platform['google_client_id'] ?? '');
$hasSecret = trim((string)($platform['google_client_secret'] ?? '')) !== '';
$fromEnvGoogle = (bool)(env_str('GOOGLE_CLIENT_ID') || env_str('GOOGLE_CLIENT_SECRET'));
$hasLeaflet = platform_leaflet_has_token();
$fromEnvLeaflet = geocoder_env_token() !== '';
$leafletReady = geocoder_token() !== '';
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
    <p class="subtitle">Somente o administrador master vê estes campos. Tokens e secrets nunca voltam a ser exibidos depois de salvos.</p>
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

  <?php if (!$editGoogle): ?>
    <dl class="settings-kv" style="margin-top:12px">
      <div><dt>Client ID</dt><dd><code><?= e($maskId($clientId)) ?></code></dd></div>
      <div><dt>Client Secret</dt><dd><?= $hasSecret ? 'Cadastrado · oculto' : 'Não cadastrado' ?></dd></div>
    </dl>
    <?php if ($fromEnvGoogle): ?>
      <p class="settings-hint">Há variáveis de ambiente no servidor. Elas prevalecem sobre o que estiver salvo aqui.</p>
    <?php endif; ?>
    <p class="settings-hint">O Secret nunca é mostrado de novo. Para trocar, abra Editar e marque explicitamente a substituição.</p>
    <div class="settings-actions">
      <a class="btn btn-primary" href="/master/configuracoes/tecnico?edit=google">Editar credenciais</a>
    </div>
  <?php else: ?>
    <form method="post" action="/master/configuracoes/google" id="google-creds-form" autocomplete="off">
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

<div class="card settings-panel" style="max-width:680px;margin-top:16px">
  <div class="settings-panel-head">
    <div>
      <h2>Token do geocoder (Leaflet / MapTiler)</h2>
      <p>Usado só no servidor para buscar endereços da Abrangência. O navegador nunca recebe esta chave.</p>
    </div>
    <?php if ($leafletReady): ?>
      <span class="badge" style="background:#dcfce7;color:#166534">Geocoder ativo</span>
    <?php else: ?>
      <span class="badge" style="background:#fffaeb;color:#b54708">Nominatim (sem chave)</span>
    <?php endif; ?>
  </div>

  <?php if (!$editLeaflet): ?>
    <dl class="settings-kv">
      <div><dt>Token</dt><dd><?= $hasLeaflet ? 'Cadastrado · cifrado e oculto' : 'Não cadastrado' ?></dd></div>
    </dl>
    <?php if ($fromEnvLeaflet): ?>
      <p class="settings-hint">Há <code>LEAFLET_TOKEN</code> (ou equivalente) no servidor. Essa variável prevalece sobre o token salvo aqui.</p>
    <?php endif; ?>
    <p class="settings-hint">O valor nunca é mostrado de novo. Cole apenas no campo senha ao cadastrar ou substituir.</p>
    <div class="settings-actions">
      <a class="btn btn-primary" href="/master/configuracoes/tecnico?edit=leaflet"><?= $hasLeaflet ? 'Substituir token' : 'Cadastrar token' ?></a>
    </div>
  <?php else: ?>
    <form method="post" action="/master/configuracoes/leaflet" id="leaflet-token-form" autocomplete="off">
      <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
      <label class="label">Token</label>
      <?php if ($hasLeaflet): ?>
        <p class="settings-hint" style="margin-bottom:8px">Já existe um token cifrado. Ele <b>não</b> será alterado a menos que você marque a substituição e cole o valor novo. O campo abaixo fica vazio de propósito.</p>
        <label class="check-row">
          <input type="checkbox" name="replace_token" value="1" id="replace-leaflet">
          Quero substituir o token atual
        </label>
        <input class="input" type="password" name="leaflet_token" id="leaflet-token" autocomplete="new-password" placeholder="Cole o novo token" disabled style="margin-top:8px" spellcheck="false">
        <label class="check-row" style="margin-top:12px">
          <input type="checkbox" name="clear_token" value="1" id="clear-leaflet">
          Remover o token salvo
        </label>
        <label class="check-row" id="confirm-clear-wrap" style="display:none;margin-top:8px">
          <input type="checkbox" name="confirm_clear" value="1">
          Confirmo a remoção. Os mapas voltam ao Nominatim.
        </label>
      <?php else: ?>
        <input class="input" type="password" name="leaflet_token" autocomplete="new-password" placeholder="Cole o token do MapTiler / geocoder" spellcheck="false">
      <?php endif; ?>
      <ol class="muted" style="padding-left:18px">
        <li>O mapa Leaflet/OSM não exige chave; o token vale para o geocoder (MapTiler, Mapbox ou LocationIQ).</li>
        <li>Guarde o valor só no provedor. Depois de salvar, a Firestep não mostra o token de novo.</li>
        <li>A busca de endereços passa pelo servidor (<code>/app/geo/search</code>) — a chave não vai para o painel do cliente.</li>
      </ol>
      <div class="settings-actions">
        <a class="btn btn-ghost" href="/master/configuracoes/tecnico">Cancelar</a>
        <button class="btn btn-primary">Salvar token</button>
      </div>
    </form>
    <script>
      (function(){
        const box = document.getElementById('replace-leaflet');
        const field = document.getElementById('leaflet-token');
        const clear = document.getElementById('clear-leaflet');
        const confirmWrap = document.getElementById('confirm-clear-wrap');
        if (box && field) {
          box.addEventListener('change', ()=>{
            field.disabled = !box.checked;
            if (!box.checked) field.value = '';
          });
        }
        if (clear && confirmWrap) {
          clear.addEventListener('change', ()=>{
            confirmWrap.style.display = clear.checked ? 'flex' : 'none';
            if (box) box.disabled = clear.checked;
            if (field && clear.checked) { field.disabled = true; field.value = ''; }
            else if (field && box) field.disabled = !box.checked;
          });
        }
        document.getElementById('leaflet-token-form')?.addEventListener('submit', (e)=>{
          if (!confirm('O token fica cifrado no banco e não será exibido outra vez. Continuar?')) {
            e.preventDefault();
          }
        });
      })();
    </script>
  <?php endif; ?>
</div>
