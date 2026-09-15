<?php
$hours = json_arr($tenant['business_hours'] ?: '{}', default_hours());
$terms = terms_of($tenant);
$analytics = analytics_config($tenant);
$days = ['Domingo','Segunda','Terça','Quarta','Quinta','Sexta','Sábado'];
$tab = $_GET['tab'] ?? 'resumo';
$allowed = ['resumo','negocio','agenda','avancado','integracoes','conta'];
if (!in_array($tab, $allowed, true)) $tab = 'resumo';
$edit = ($_GET['edit'] ?? '') === '1';
$editFolha = ($_GET['edit'] ?? '') === 'folha';
$editAssinatura = ($_GET['edit'] ?? '') === 'assinatura';
$editClausulas = ($_GET['edit'] ?? '') === 'clausulas';
$lh = letterhead_config($tenant);
$lhReady = letterhead_complete($lh);
$sig = signature_config($tenant);
$sigReady = signature_complete($sig);
$clauseLists = clauses_lists($tenant);
$clauseAppts = $editClausulas ? clauses_appointments((string)$tenant['id']) : [];
$segRow = one('SELECT name FROM segments WHERE slug=?', [(string)($tenant['segment'] ?? '')]);
$segLabel = (string)($segRow['name'] ?? '');
$sheets = sheets_config($tenant);
$googleReady = google_oauth_ready();
$connected = sheets_connected($sheets);
$dash = static function ($v) {
    $v = trim((string)$v);
    return $v !== '' ? e($v) : '—';
};
$hourLine = static function (array $h) {
    if (!empty($h['closed'])) return 'Fechado';
    $line = ($h['start'] ?? '08:00').' – '.($h['end'] ?? '18:00');
    if (!empty($h['breaks'][0]['start']) && !empty($h['breaks'][0]['end'])) {
        $line .= ' · intervalo '.$h['breaks'][0]['start'].'–'.$h['breaks'][0]['end'];
    }
    return $line;
};
?>
<div class="page-head" style="max-width:860px">
  <div>
    <h1>Configurações</h1>
    <p class="subtitle">Cada área se edita à parte. Nada é salvo até você confirmar.</p>
  </div>
</div>

<nav class="tabs settings-tabs" style="margin-bottom:14px;flex-wrap:wrap;max-width:860px">
  <a href="/app/configuracoes?tab=resumo" class="<?= $tab==='resumo'?'active':'' ?>">Visão geral</a>
  <a href="/app/configuracoes?tab=negocio" class="<?= $tab==='negocio'?'active':'' ?>">Negócio</a>
  <a href="/app/configuracoes?tab=agenda" class="<?= $tab==='agenda'?'active':'' ?>">Horários</a>
  <a href="/app/configuracoes?tab=avancado" class="<?= $tab==='avancado'?'active':'' ?>">Avançado</a>
  <a href="/app/configuracoes?tab=integracoes" class="<?= $tab==='integracoes'?'active':'' ?>">Integrações</a>
  <a href="/app/configuracoes?tab=conta" class="<?= $tab==='conta'?'active':'' ?>">Conta</a>
</nav>

<?php if ($tab === 'resumo'): ?>
<div class="card settings-panel">
  <div class="settings-panel-head">
    <div>
      <h2>Como está hoje</h2>
      <p>Só leitura. Abra a aba correspondente para alterar.</p>
    </div>
  </div>
  <dl class="settings-kv">
    <div><dt>Negócio</dt><dd><?= $dash($tenant['display_name'] ?: $tenant['business_name']) ?></dd></div>
    <div><dt>Contato</dt><dd><?= $dash($tenant['phone']) ?> · <?= $dash($tenant['email']) ?></dd></div>
    <div><dt>Cidade</dt><dd><?= $dash($tenant['city']) ?><?= $tenant['state'] ? ' / '.e($tenant['state']) : '' ?></dd></div>
    <div><dt>Fuso</dt><dd><?= $dash($tenant['timezone']) ?></dd></div>
    <div><dt>Google Sheets</dt><dd><?= $connected ? 'Conectado ('.e($sheets['google_email'] ?: 'conta Google').')' : 'Desconectado' ?></dd></div>
    <div><dt>Tag Manager</dt><dd><?= !empty($analytics['gtm_id']) ? e($analytics['gtm_id']) : 'Não configurado' ?></dd></div>
  </dl>
  <p class="settings-hint">Horários, nomes internos e integrações ficam em abas próprias para evitar alteração acidental.</p>
</div>
<?php endif; ?>

<?php if ($tab === 'negocio'): ?>
<div class="card settings-panel">
  <div class="settings-panel-head">
    <div>
      <h2>Meu negócio</h2>
      <p>Dados de contato e endereço visíveis no CRM.</p>
    </div>
    <?php if (!$edit): ?>
      <a class="btn btn-ghost" href="/app/configuracoes?tab=negocio&amp;edit=1">Editar</a>
    <?php endif; ?>
  </div>
  <?php if (!$edit): ?>
    <dl class="settings-kv">
      <div><dt>Nome comercial</dt><dd><?= $dash($tenant['business_name']) ?></dd></div>
      <div><dt>Nome exibido</dt><dd><?= $dash($tenant['display_name']) ?></dd></div>
      <div><dt>Documento</dt><dd><?= $dash($tenant['document'] ?? '') ?></dd></div>
      <div><dt>Telefone</dt><dd><?= $dash($tenant['phone']) ?></dd></div>
      <div><dt>WhatsApp</dt><dd><?= $dash($tenant['whatsapp']) ?></dd></div>
      <div><dt>E-mail</dt><dd><?= $dash($tenant['email']) ?></dd></div>
      <div><dt>Fuso</dt><dd><?= $dash($tenant['timezone']) ?></dd></div>
      <div><dt>Cidade</dt><dd><?= $dash($tenant['city']) ?></dd></div>
      <div><dt>Estado</dt><dd><?= $dash($tenant['state']) ?></dd></div>
      <div class="wide"><dt>Endereço</dt><dd><?= $dash($tenant['address']) ?></dd></div>
      <div><dt>Instagram</dt><dd><?= $dash($tenant['instagram']) ?></dd></div>
      <div><dt>Website</dt><dd><?= $dash($tenant['website']) ?></dd></div>
    </dl>
  <?php else: ?>
    <form method="post" action="/app/configuracoes/negocio">
      <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
      <label class="label">Nome comercial</label><input class="input" name="business_name" required value="<?= e($tenant['business_name']) ?>">
      <label class="label">Nome exibido</label><input class="input" name="display_name" required value="<?= e($tenant['display_name']) ?>">
      <div class="grid g2">
        <?php br_document_fields('document', $tenant['document'] ?? null, true); ?>
        <div><label class="label">Telefone</label><input class="input" name="phone" required inputmode="tel" value="<?= e($tenant['phone']) ?>"></div>
        <div><label class="label">WhatsApp</label><input class="input" name="whatsapp" inputmode="tel" placeholder="Opcional" value="<?= e($tenant['whatsapp']) ?>"></div>
        <div><label class="label">E-mail</label><input class="input" name="email" type="email" required value="<?= e($tenant['email']) ?>"></div>
        <div><label class="label">Fuso</label>
          <select class="select" name="timezone" required>
            <?php foreach (['America/Sao_Paulo','America/Manaus','America/Fortaleza','America/Recife'] as $tz): ?>
              <option <?= $tenant['timezone']===$tz?'selected':'' ?>><?= $tz ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div><label class="label">Cidade</label><input class="input" name="city" required value="<?= e($tenant['city']) ?>"></div>
        <div><label class="label">Estado</label><input class="input" name="state" required maxlength="2" placeholder="UF" style="text-transform:uppercase" value="<?= e($tenant['state']) ?>"></div>
      </div>
      <label class="label">Endereço</label><input class="input" name="address" required value="<?= e($tenant['address']) ?>">
      <div class="grid g2">
        <div><label class="label">Instagram</label><input class="input" name="instagram" value="<?= e($tenant['instagram']) ?>"></div>
        <div><label class="label">Website</label><input class="input" name="website" value="<?= e($tenant['website']) ?>"></div>
      </div>
      <div class="settings-actions">
        <a class="btn btn-ghost" href="/app/configuracoes?tab=negocio">Cancelar</a>
        <button class="btn btn-primary">Salvar dados do negócio</button>
      </div>
    </form>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($tab === 'agenda'): ?>
<div class="card settings-panel">
  <div class="settings-panel-head">
    <div>
      <h2>Horário de funcionamento</h2>
      <p>Define quando a agenda aceita novos horários.</p>
    </div>
    <?php if (!$edit): ?>
      <a class="btn btn-ghost" href="/app/configuracoes?tab=agenda&amp;edit=1">Editar horários</a>
    <?php endif; ?>
  </div>
  <?php if (!$edit): ?>
    <dl class="settings-kv">
      <?php for ($d=0;$d<=6;$d++): $h=$hours[$d]??$hours[(string)$d]??['closed'=>true]; ?>
        <div><dt><?= $days[$d] ?></dt><dd><?= e($hourLine($h)) ?></dd></div>
      <?php endfor; ?>
    </dl>
  <?php else: ?>
    <form method="post" action="/app/configuracoes/horarios" onsubmit="return confirm('Alterar o horário de funcionamento pode bloquear ou liberar horários na agenda. Continuar?')">
      <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
      <div class="hours-head">
        <span></span><span>Fechado</span><span>Abre</span><span>Fecha</span><span>Intervalo</span><span></span>
      </div>
      <?php for ($d=0;$d<=6;$d++): $h=$hours[$d]??$hours[(string)$d]??['closed'=>false,'start'=>'08:00','end'=>'18:00','breaks'=>[]]; ?>
        <div class="hours-row">
          <b><?= $days[$d] ?></b>
          <label class="check-row" style="margin:0"><input type="checkbox" name="closed_<?= $d ?>" <?= !empty($h['closed'])?'checked':'' ?>> Fechado</label>
          <input class="input" type="time" name="start_<?= $d ?>" value="<?= e($h['start']??'08:00') ?>">
          <input class="input" type="time" name="end_<?= $d ?>" value="<?= e($h['end']??'18:00') ?>">
          <input class="input" type="time" name="bstart_<?= $d ?>" value="<?= e($h['breaks'][0]['start']??'') ?>">
          <input class="input" type="time" name="bend_<?= $d ?>" value="<?= e($h['breaks'][0]['end']??'') ?>">
        </div>
      <?php endfor; ?>
      <div class="settings-actions">
        <a class="btn btn-ghost" href="/app/configuracoes?tab=agenda">Cancelar</a>
        <button class="btn btn-primary">Salvar horários</button>
      </div>
    </form>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($tab === 'avancado'): ?>
<div class="card settings-panel">
  <div class="settings-panel-head">
    <div>
      <h2>Aparência e nomes internos</h2>
      <p>Altera só os textos desta empresa. O menu do CRM continua o mesmo.</p>
    </div>
    <?php if (!$edit): ?>
      <a class="btn btn-ghost" href="/app/configuracoes?tab=avancado&amp;edit=1">Editar</a>
    <?php endif; ?>
  </div>
  <?php if (!$edit): ?>
    <dl class="settings-kv">
      <div><dt>Cor</dt><dd><span class="color-dot" style="background:<?= e($tenant['primary_color'] ?: '#2563eb') ?>"></span> <?= e($tenant['primary_color'] ?: '#2563eb') ?></dd></div>
      <div><dt>Cliente</dt><dd><?= e($terms['client']) ?> / <?= e($terms['clients']) ?></dd></div>
      <div><dt>Agendamento</dt><dd><?= e($terms['appointment']) ?> / <?= e($terms['appointments']) ?></dd></div>
    </dl>
  <?php else: ?>
    <form method="post" action="/app/configuracoes/aparencia" onsubmit="return confirm('Os nomes internos desta empresa serão alterados. Continuar?')">
      <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
      <label class="label">Cor principal</label>
      <input class="input" type="color" name="primary_color" value="<?= e($tenant['primary_color'] ?: '#2563eb') ?>" style="max-width:120px;height:42px">
      <div class="grid g2" style="margin-top:12px">
        <div><label class="label">Cliente (singular)</label><input class="input" name="term_client" value="<?= e($terms['client']) ?>"></div>
        <div><label class="label">Clientes (plural)</label><input class="input" name="term_clients" value="<?= e($terms['clients']) ?>"></div>
        <div><label class="label">Agendamento (singular)</label><input class="input" name="term_appointment" value="<?= e($terms['appointment']) ?>"></div>
        <div><label class="label">Agendamentos (plural)</label><input class="input" name="term_appointments" value="<?= e($terms['appointments']) ?>"></div>
      </div>
      <div class="settings-actions">
        <a class="btn btn-ghost" href="/app/configuracoes?tab=avancado">Cancelar</a>
        <button class="btn btn-primary">Salvar aparência</button>
      </div>
    </form>
  <?php endif; ?>
</div>

<div class="card settings-panel" style="margin-top:14px">
  <div class="settings-panel-head">
    <div>
      <h2>Campos personalizados</h2>
      <p>Aparecem nos cadastros desta empresa. Não dá para desfazer pelo formulário.</p>
    </div>
  </div>
  <?php if ($fields): ?>
    <ul class="settings-list">
      <?php foreach ($fields as $f): ?>
        <li><?= e($f['label']) ?> <span><?= e($f['type']) ?></span></li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <p class="settings-hint">Nenhum campo extra ainda.</p>
  <?php endif; ?>
  <details class="settings-details">
    <summary>Adicionar campo</summary>
    <form method="post" action="/app/configuracoes/campo" onsubmit="return confirm('Adicionar este campo aos cadastros?')">
      <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
      <div class="grid" style="grid-template-columns:1fr 140px 1fr auto;margin-top:10px">
        <input class="input" name="label" required placeholder="Nome do campo">
        <select class="select" name="type">
          <option value="text">Texto</option>
          <option value="number">Número</option>
          <option value="date">Data</option>
          <option value="select">Seleção</option>
          <option value="textarea">Texto longo</option>
        </select>
        <input class="input" name="options" placeholder="Opções, vírgula">
        <button class="btn btn-primary">Adicionar</button>
      </div>
    </form>
  </details>
</div>

<div class="card settings-panel lh-panel" style="margin-top:14px">
  <div class="settings-panel-head">
    <div>
      <h2>Cabeçalho de folha</h2>
      <p>Dados usados só em contratos e PDFs, quando o cabeçalho estiver ativo. Não aparece no restante do CRM.</p>
    </div>
    <?php if (!$editFolha): ?>
      <a class="btn btn-ghost" href="/app/configuracoes?tab=avancado&amp;edit=folha">Editar</a>
    <?php endif; ?>
  </div>
  <?php if (!$editFolha): ?>
    <p class="settings-hint" style="margin-top:0">As informações deste bloco ficam ocultas. Abra <b>Editar</b> para alterar.</p>
    <?php if ($lhReady): ?>
      <div class="lh-status">
        <?php if (!empty($lh['active'])): ?>
          <span class="badge" style="background:#dcfce7;color:#166534">Ativo nos contratos</span>
        <?php else: ?>
          <span class="badge" style="background:#fef9c3;color:#854d0e">Salvo · desativado</span>
        <?php endif; ?>
        <form method="post" action="/app/configuracoes/folha/ativar" class="lh-toggle">
          <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
          <input type="hidden" name="active" value="<?= !empty($lh['active']) ? '0' : '1' ?>">
          <button class="btn <?= !empty($lh['active']) ? 'btn-danger' : 'btn-primary' ?>">
            <?= !empty($lh['active']) ? 'Desativar cabeçalho' : 'Ativar nos contratos' ?>
          </button>
        </form>
      </div>
    <?php endif; ?>
  <?php else: ?>
    <form method="post" action="/app/configuracoes/folha" enctype="multipart/form-data">
      <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
      <label class="label">Nome fantasia</label>
      <input class="input" name="trade_name" required maxlength="120" value="<?= e((string)$lh['trade_name']) ?>">
      <div class="grid g2">
        <div><label class="label">E-mail</label><input class="input" type="email" name="email" required value="<?= e((string)$lh['email']) ?>"></div>
        <div><label class="label">Telefone</label><input class="input" name="phone" required inputmode="tel" value="<?= e((string)$lh['phone']) ?>"></div>
        <?php br_document_fields('document', $lh['document'] ?: null, true, 'document_kind', ['document_kind' => $lh['document_kind'] ?? '']); ?>
        <div>
          <label class="label">CEP</label>
          <input class="input js-cep" name="cep" required inputmode="numeric" maxlength="9" placeholder="00000-000" value="<?= e((string)$lh['cep']) ?>">
        </div>
      </div>
      <label class="label">Moradia / endereço completo</label>
      <input class="input" name="address" required maxlength="220" value="<?= e((string)$lh['address']) ?>">
      <label class="label" style="margin-top:12px">Logo</label>
      <?php if (!empty($lh['logo'])): ?>
        <div class="lh-logo-preview"><img src="<?= e($lh['logo']) ?>" alt=""></div>
        <label class="check-row"><input type="checkbox" name="remove_logo" value="1"> Remover logo atual</label>
      <?php endif; ?>
      <input class="input" type="file" name="logo" accept="image/jpeg,image/png,image/webp">
      <p class="settings-hint">JPEG, PNG ou WEBP · até 200 KB. A cor abaixo vale só para este cabeçalho, não altera o CRM.</p>
      <label class="label">Cor do cabeçalho</label>
      <div class="lh-palette" id="lh-palette">
        <?php foreach (LETTERHEAD_PALETTE as $c): ?>
          <button type="button" class="lh-swatch<?= strtoupper($lh['color'])===strtoupper($c)?' is-on':'' ?>" data-color="<?= e($c) ?>" style="background:<?= e($c) ?>" aria-label="<?= e($c) ?>"></button>
        <?php endforeach; ?>
        <input class="input lh-color" type="color" name="color" id="lh-color" value="<?= e(letterhead_hex((string)$lh['color'])) ?>">
      </div>
      <div class="settings-actions">
        <a class="btn btn-ghost" href="/app/configuracoes?tab=avancado">Cancelar</a>
        <button class="btn btn-primary">Salvar cabeçalho</button>
      </div>
    </form>
  <?php endif; ?>
</div>

<div class="card settings-panel lh-panel" style="margin-top:14px">
  <div class="settings-panel-head">
    <div>
      <h2>Assinatura eletrônica</h2>
      <p>Imagem da assinatura usada só nos contratos, quando estiver ativa. Não aparece no restante do CRM.</p>
    </div>
    <?php if (!$editAssinatura): ?>
      <a class="btn btn-ghost" href="/app/configuracoes?tab=avancado&amp;edit=assinatura">Editar</a>
    <?php endif; ?>
  </div>
  <?php if (!$editAssinatura): ?>
    <p class="settings-hint" style="margin-top:0">O arquivo fica oculto. Abra <b>Editar</b> para enviar ou trocar a imagem.</p>
    <?php if ($sigReady): ?>
      <div class="lh-status">
        <?php if (!empty($sig['active'])): ?>
          <span class="badge" style="background:#dcfce7;color:#166534">Ativa nos contratos</span>
        <?php else: ?>
          <span class="badge" style="background:#fef9c3;color:#854d0e">Salva · desativada</span>
        <?php endif; ?>
        <form method="post" action="/app/configuracoes/assinatura/ativar" class="lh-toggle">
          <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
          <input type="hidden" name="active" value="<?= !empty($sig['active']) ? '0' : '1' ?>">
          <button class="btn <?= !empty($sig['active']) ? 'btn-danger' : 'btn-primary' ?>">
            <?= !empty($sig['active']) ? 'Desativar assinatura' : 'Ativar nos contratos' ?>
          </button>
        </form>
      </div>
    <?php endif; ?>
  <?php else: ?>
    <form method="post" action="/app/configuracoes/assinatura" enctype="multipart/form-data">
      <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
      <label class="label">Arquivo da assinatura</label>
      <?php if (!empty($sig['image'])): ?>
        <div class="lh-logo-preview sig-preview"><img src="<?= e($sig['image']) ?>" alt=""></div>
        <label class="check-row"><input type="checkbox" name="remove_signature" value="1"> Remover imagem atual</label>
      <?php endif; ?>
      <input class="input" type="file" name="signature" accept="image/jpeg,image/png,image/webp" <?= empty($sig['image']) ? 'required' : '' ?>>
      <p class="settings-hint">Somente imagem: JPEG, PNG ou WEBP · até 200 KB. Depois de salvar, ative para aparecer no rodapé dos contratos.</p>
      <div class="settings-actions">
        <a class="btn btn-ghost" href="/app/configuracoes?tab=avancado">Cancelar</a>
        <button class="btn btn-primary">Salvar assinatura</button>
      </div>
    </form>
  <?php endif; ?>
</div>

<div class="card settings-panel lh-panel" style="margin-top:14px">
  <div class="settings-panel-head">
    <div>
      <h2>Contratos de agendamentos</h2>
      <p>Monte listas com um ou mais horários e escreva as cláusulas deste negócio<?= $segLabel ? ' ('.e($segLabel).')' : '' ?>.</p>
    </div>
    <a class="btn btn-ghost" href="/app/configuracoes?tab=avancado&amp;edit=clausulas">Editar</a>
  </div>
  <p class="settings-hint" style="margin-top:0">O texto fica oculto. No editor, cada contrato criado aparece à esquerda. À direita você marca os agendamentos e escreve as regras.</p>
</div>
<?php endif; ?>

<?php if (!empty($editClausulas)): ?>
<div class="fx-overlay" id="cl-overlay">
  <form method="post" action="/app/configuracoes/clausulas" class="cl-panel" id="cl-form" onclick="event.stopPropagation()">
    <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
    <input type="hidden" name="templates" id="cl-templates" value="">
    <div class="fx-modal-head">
      <div>
        <h2>Contratos de agendamentos</h2>
        <p>Crie uma lista com um ou mais horários. Ela aparece à esquerda. À direita, escreva as cláusulas deste contrato.</p>
      </div>
      <a class="fx-x" href="/app/configuracoes?tab=avancado" aria-label="Fechar"><?= icon('x', 18) ?></a>
    </div>
    <div class="cl-split">
      <aside class="cl-cats" id="cl-cats">
        <button type="button" class="cl-new" id="cl-new"><?= icon('plus', 14) ?> Novo contrato</button>
        <div id="cl-lists"></div>
        <p class="settings-hint" id="cl-empty">Nenhum contrato ainda. Use Novo contrato e marque os agendamentos.</p>
      </aside>
      <div class="cl-editor-wrap">
        <div class="cl-pick" id="cl-pick" hidden>
          <p>Marque os agendamentos que entram neste contrato. Pode ser mais de um.</p>
          <div class="cl-pick-list">
            <?php if (!$clauseAppts): ?>
              <p class="settings-hint">Não há agendamentos. Cadastre horários na agenda primeiro.</p>
            <?php else: foreach ($clauseAppts as $ap): ?>
              <label class="cl-pick-row">
                <input type="checkbox" value="<?= e($ap['id']) ?>">
                <span>
                  <b><?= e($ap['client_name']) ?></b>
                  <?= e($ap['service_name'] ?: 'Serviço') ?>
                  <i><?= e(date('d/m/Y H:i', strtotime((string)$ap['starts_at']))) ?></i>
                </span>
              </label>
            <?php endforeach; endif; ?>
          </div>
          <button type="button" class="btn btn-primary" id="cl-pick-ok">Usar selecionados</button>
        </div>
        <div id="cl-work" hidden>
          <div class="cl-tools">
            <button type="button" class="btn btn-ghost" data-cl="bold"><b>N</b></button>
            <button type="button" class="btn btn-ghost" data-cl="italic"><i>I</i></button>
            <input class="input" id="cl-name" placeholder="Nome do contrato" maxlength="120">
            <button type="button" class="btn btn-ghost" id="cl-change-appts">Agendamentos</button>
            <span id="cl-current" class="settings-hint" style="margin:0"></span>
          </div>
          <div id="cl-editor" class="cl-editor" contenteditable="true" data-placeholder="Digite cláusulas, regras e condições deste contrato."></div>
        </div>
      </div>
    </div>
    <div class="settings-actions">
      <a class="btn btn-ghost" href="/app/configuracoes?tab=avancado">Cancelar</a>
      <button class="btn btn-primary" id="cl-save">Salvar contratos</button>
    </div>
  </form>
</div>
<script type="application/json" id="cl-data"><?= json_encode(['lists'=>$clauseLists], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<?php endif; ?>

<?php if ($tab === 'integracoes'): ?>
<div class="card settings-panel">
  <div class="settings-panel-head">
    <div>
      <h2>Google Tag Manager</h2>
      <p>Usado só para campanhas do site externo. Não instala rastreador no CRM.</p>
    </div>
    <?php if (!$edit): ?>
      <a class="btn btn-ghost" href="/app/configuracoes?tab=integracoes&amp;edit=1">Editar</a>
    <?php endif; ?>
  </div>
  <?php if (!$edit): ?>
    <dl class="settings-kv">
      <div><dt>Container</dt><dd><?= !empty($analytics['gtm_id']) ? e($analytics['gtm_id']) : 'Não configurado' ?></dd></div>
      <div><dt>Domínio</dt><dd><?= $dash($analytics['site_domain'] ?? '') ?></dd></div>
    </dl>
  <?php else: ?>
    <form method="post" action="/app/configuracoes/analytics" onsubmit="return confirm('Salvar a integração analítica?')">
      <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
      <div class="grid g2">
        <div>
          <label class="label">Google Tag Manager ID</label>
          <input class="input" name="gtm_id" value="<?= e($analytics['gtm_id'] ?? '') ?>" placeholder="GTM-XXXXXXX">
        </div>
        <div>
          <label class="label">Domínio principal do site</label>
          <input class="input" name="site_domain" value="<?= e($analytics['site_domain'] ?? '') ?>" placeholder="www.seusite.com.br">
        </div>
      </div>
      <p class="settings-hint">Deixe o ID em branco para desligar a integração. O formato esperado é GTM-XXXXXXX.</p>
      <div class="settings-actions">
        <a class="btn btn-ghost" href="/app/configuracoes?tab=integracoes">Cancelar</a>
        <button class="btn btn-primary">Salvar integração</button>
      </div>
    </form>
  <?php endif; ?>
</div>

<div class="card settings-panel google-login-card" style="margin-top:14px">
  <div class="settings-panel-head">
    <div>
      <h2>Google Sheets</h2>
      <p>Entre com a conta Google do negócio. O sistema pede autorização, grava os tokens e cria a planilha automaticamente.</p>
    </div>
  </div>
  <?php if ($connected): ?>
    <p><span class="badge" style="background:#dcfce7;color:#166534">Conta conectada</span> <?= e($sheets['google_email'] ?: 'Google') ?></p>
    <?php if (!empty($sheets['spreadsheet_url'])): ?>
      <p><a class="btn btn-ghost" href="<?= e($sheets['spreadsheet_url']) ?>" target="_blank" rel="noopener">Abrir planilha</a></p>
    <?php endif; ?>
    <?php if (!empty($sheets['last_sync'])): ?>
      <p class="settings-hint">Última sincronização: <?= e(date('d/m/Y H:i', strtotime($sheets['last_sync']))) ?> · <?= ($sheets['last_status']??'')==='ok' ? 'OK' : 'Erro' ?><?php if (!empty($sheets['last_error'])): ?> · <?= e($sheets['last_error']) ?><?php endif; ?></p>
    <?php endif; ?>
    <a class="btn-google" href="/app/google/connect">Continuar com o Google</a>
    <p class="settings-hint">O clique abre a tela oficial do Google. A conta escolhida passa a ser a da planilha deste painel.</p>
    <div class="settings-actions" style="justify-content:flex-start">
      <form method="post" action="/app/configuracoes/sheets/sync">
        <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
        <button class="btn btn-primary">Sincronizar agora</button>
      </form>
      <form method="post" action="/app/google/disconnect" onsubmit="return confirm('Desconectar o Google Sheets? A sincronização automática para até você entrar de novo.')">
        <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
        <button class="btn btn-danger">Desconectar</button>
      </form>
    </div>
  <?php else: ?>
    <a class="btn-google" href="/app/google/connect">Continuar com o Google</a>
    <p class="settings-hint">Obrigatório: a janela do Google vai abrir. Autorize o acesso à planilha; os tokens e a API Sheets desta conta são ligados sozinhos neste painel.</p>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($tab === 'conta'): ?>
<div class="card settings-panel">
  <div class="settings-panel-head">
    <div>
      <h2>Minha conta</h2>
      <p>O e-mail de acesso é definido pelo Admin Master e não pode ser alterado. Nome e senha você atualiza aqui.</p>
    </div>
  </div>
  <form method="post" action="/app/configuracoes/conta">
    <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
    <label class="label">Nome</label>
    <input class="input" name="name" required value="<?= e($user['name']) ?>">
    <label class="label">E-mail de acesso</label>
    <input class="input" type="email" value="<?= e($user['email']) ?>" readonly disabled>
    <label class="label">Usuário de acesso</label>
    <input class="input" value="<?= e($user['username']) ?>" readonly disabled>
    <p class="muted" style="margin:6px 0 12px">O login é o e-mail ou este usuário. O nome da empresa no menu não serve para entrar.</p>
    <div class="settings-warn">
      <?php if (!empty($user['must_change_password'])): ?>
      <p class="muted" style="margin:0 0 12px">Primeiro acesso: defina uma senha permanente (mínimo 10 caracteres, com letras e números). Não é preciso informar a senha temporária de novo.</p>
      <?php else: ?>
      <label class="label">Senha atual</label>
      <input class="input" type="password" name="current_password" autocomplete="current-password" placeholder="Obrigatória para mudar a senha">
      <?php endif; ?>
      <div class="grid g2">
        <div>
          <label class="label">Nova senha</label>
          <input class="input" type="password" name="password" autocomplete="new-password" placeholder="<?= !empty($user['must_change_password']) ? 'Obrigatória neste acesso' : 'Deixe em branco para manter' ?>" <?= !empty($user['must_change_password']) ? 'required' : '' ?>>
        </div>
        <div>
          <label class="label">Confirmar nova senha</label>
          <input class="input" type="password" name="password_confirm" autocomplete="new-password" <?= !empty($user['must_change_password']) ? 'required' : '' ?>>
        </div>
      </div>
    </div>
    <div class="settings-actions">
      <button class="btn btn-primary">Atualizar conta</button>
    </div>
  </form>
</div>
<?php endif; ?>
