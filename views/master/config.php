<?php
$platform = platform_settings();
$lh = platform_letterhead_config();
$lhReady = platform_letterhead_ready($lh);
$editFolha = ($_GET['edit'] ?? '') === 'folha';
?>
<div class="page-head">
  <div>
    <h1>Configurações da plataforma</h1>
    <p class="subtitle">Dados gerais do produto. Credenciais técnicas ficam escondidas.</p>
  </div>
</div>
<div class="card" style="padding:24px;max-width:720px;margin-bottom:16px">
  <p>Nome do produto: <b>FirestepCRM</b></p>
  <p>Fuso padrão: America/Sao_Paulo</p>
  <p style="color:#667085;margin:0">O login Google dos profissionais acontece no painel de cada cliente, no botão <b>Continuar com o Google</b>. Não cole e-mail nem nome de pessoa no Client ID.</p>
</div>

<div class="card settings-panel lh-panel" style="max-width:720px">
  <div class="settings-panel-head">
    <div>
      <h2>Cabeçalho de contratos e serviços</h2>
      <p>Logo e nome do CRM nos PDFs do Master (dossiê) e, se o cliente não tiver cabeçalho próprio, nos contratos e reservas.</p>
    </div>
    <?php if (!$editFolha): ?>
      <a class="btn btn-ghost" href="/master/configuracoes?edit=folha">Editar</a>
    <?php endif; ?>
  </div>
  <?php if (!$editFolha): ?>
    <p class="settings-hint" style="margin-top:0">O conteúdo fica oculto. Abra <b>Editar</b> para enviar a logo e o nome fantasia.</p>
    <?php if ($lhReady): ?>
      <div class="lh-status">
        <?php if (!empty($lh['active'])): ?>
          <span class="badge" style="background:#dcfce7;color:#166534">Ativo nos PDFs</span>
        <?php else: ?>
          <span class="badge" style="background:#fef9c3;color:#854d0e">Salvo · desativado</span>
        <?php endif; ?>
        <form method="post" action="/master/configuracoes/folha/ativar" class="lh-toggle">
          <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
          <input type="hidden" name="active" value="<?= !empty($lh['active']) ? '0' : '1' ?>">
          <button class="btn <?= !empty($lh['active']) ? 'btn-danger' : 'btn-primary' ?>">
            <?= !empty($lh['active']) ? 'Desativar cabeçalho' : 'Ativar nos PDFs' ?>
          </button>
        </form>
      </div>
    <?php endif; ?>
  <?php else: ?>
    <form method="post" action="/master/configuracoes/folha" enctype="multipart/form-data">
      <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
      <label class="label">Nome fantasia</label>
      <input class="input" name="trade_name" required maxlength="120" value="<?= e((string)($lh['trade_name'] ?: 'FirestepCRM')) ?>">
      <div class="grid g2">
        <div><label class="label">E-mail</label><input class="input" type="email" name="email" required value="<?= e((string)$lh['email']) ?>"></div>
        <div><label class="label">Telefone</label><input class="input" name="phone" required inputmode="tel" value="<?= e((string)$lh['phone']) ?>"></div>
        <?php br_document_fields('document', $lh['document'] ?: null, true, 'document_kind', ['document_kind' => $lh['document_kind'] ?? '']); ?>
        <div>
          <label class="label">CEP</label>
          <input class="input js-cep" name="cep" required inputmode="numeric" maxlength="9" placeholder="00000-000" value="<?= e((string)$lh['cep']) ?>">
        </div>
      </div>
      <label class="label">Endereço completo</label>
      <input class="input" name="address" required maxlength="220" value="<?= e((string)$lh['address']) ?>">
      <label class="label" style="margin-top:12px">Logo do CRM</label>
      <?php if (!empty($lh['logo'])): ?>
        <div class="lh-logo-preview"><img src="<?= e($lh['logo']) ?>" alt=""></div>
        <label class="check-row"><input type="checkbox" name="remove_logo" value="1"> Remover logo atual</label>
      <?php endif; ?>
      <input class="input" type="file" name="logo" accept="image/jpeg,image/png,image/webp">
      <p class="settings-hint">JPEG, PNG ou WEBP · até 200 KB. A cor vale só para o cabeçalho dos PDFs.</p>
      <label class="label">Cor do cabeçalho</label>
      <div class="lh-palette" id="lh-palette">
        <?php foreach (LETTERHEAD_PALETTE as $c): ?>
          <button type="button" class="lh-swatch<?= strtoupper($lh['color'])===strtoupper($c)?' is-on':'' ?>" data-color="<?= e($c) ?>" style="background:<?= e($c) ?>" aria-label="<?= e($c) ?>"></button>
        <?php endforeach; ?>
        <input class="input lh-color" type="color" name="color" id="lh-color" value="<?= e(letterhead_hex((string)$lh['color'])) ?>">
      </div>
      <div class="settings-actions">
        <a class="btn btn-ghost" href="/master/configuracoes">Cancelar</a>
        <button class="btn btn-primary">Salvar cabeçalho</button>
      </div>
    </form>
  <?php endif; ?>
</div>

<?php $wa = uazapi_platform_config(); $waTok = (string)($wa['token'] ?? ''); ?>
<div class="card settings-panel" style="max-width:720px;margin-top:16px">
  <div class="settings-panel-head">
    <div>
      <h2>WhatsApp de atendimento</h2>
      <p>Instância usada quando a Priscila envia o WhatsApp pelo chat do painel.</p>
    </div>
  </div>
  <form method="post" action="/master/configuracoes/whatsapp">
    <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
    <div class="grid g2">
      <div>
        <label class="label">URL da instância</label>
        <input class="input" name="whatsapp_url" value="<?= e($wa['url'] ?? '') ?>" placeholder="https://servidor.exemplo.com">
      </div>
      <div>
        <label class="label">Token da instância</label>
        <input class="input" name="whatsapp_token" type="password" autocomplete="new-password" placeholder="<?= $waTok !== '' ? 'Deixe em branco para manter' : 'token' ?>">
      </div>
    </div>
    <p class="settings-hint">Com a instância conectada, a Priscila dispara sozinha para o WhatsApp informado no chat e encerra a conversa.</p>
    <div class="settings-actions">
      <button class="btn btn-primary">Salvar WhatsApp</button>
    </div>
  </form>
</div>
