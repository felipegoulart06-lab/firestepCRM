<?php
$terms = $terms ?? terms_of($tenant);
$from = date('Y-m-01');
$to = date('Y-m-d');
$reportUsers = all('SELECT id, name, role FROM users WHERE tenant_id=? AND '.sql_true('active').' ORDER BY name', [$tenant['id']]);
$reportServices = all("SELECT id, name FROM services WHERE tenant_id=? AND status='ACTIVE' ORDER BY name", [$tenant['id']]);
$clientWord = lower($terms['clients']);
$fxRoot = $fxRoot ?? 'Relatórios';
$fxFolders = $fxFolders ?? [
    ['name' => 'Atendimento', 'files' => [
        ['kind' => 'atendimentos', 'file' => 'Resumo de atendimentos.pdf', 'hint' => 'Horários, clientes, serviços e status.'],
    ]],
    ['name' => 'Relacionamento', 'files' => [
        ['kind' => 'clientes', 'file' => 'Resumo de '.$clientWord.'.pdf', 'hint' => 'Cadastros, contatos, origem e atendimentos.'],
        ['kind' => 'origens', 'file' => 'Resumo de origens.pdf', 'hint' => 'Canais que geraram cadastros e solicitações.'],
    ]],
    ['name' => 'Financeiro', 'files' => [
        ['kind' => 'financeiro', 'file' => 'Resumo do caixa.pdf', 'hint' => 'Lançamentos, receber e pagar no intervalo.'],
    ]],
];
$fileCount = 0;
foreach ($fxFolders as $folder) {
    $fileCount += count($folder['files']);
}
?>
<div class="fx" id="fx-root" data-root="<?= e($fxRoot) ?>" data-from="<?= e($from) ?>" data-to="<?= e($to) ?>">
  <aside class="fx-tree">
    <div class="fx-tree-bar"><?= icon('folder') ?> <?= e($fxRoot) ?></div>
    <div class="fx-tree-body">
      <?php foreach ($fxFolders as $fi => $folder): ?>
        <div class="fx-folder" data-folder="<?= e((string)$fi) ?>">
          <div class="fx-folder-row">
            <button type="button" class="fx-twist" aria-label="Abrir pasta <?= e($folder['name']) ?>"></button>
            <span class="fx-ico"><?= icon('folder', 16) ?></span>
            <span class="fx-folder-name"><?= e($folder['name']) ?></span>
          </div>
          <ul class="fx-kids" hidden>
            <?php foreach ($folder['files'] as $file): ?>
              <li>
                <button type="button" class="fx-file" data-kind="<?= e($file['kind']) ?>" data-file="<?= e($file['file']) ?>" data-folder-name="<?= e($folder['name']) ?>" data-hint="<?= e($file['hint']) ?>">
                  <?= icon('pdf', 16) ?> <?= e($file['file']) ?>
                </button>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endforeach; ?>
    </div>
  </aside>

  <div class="fx-stage">
    <div class="fx-path" id="fx-path"><?= icon('folder', 14) ?> <?= e($fxRoot) ?> <span>›</span> dois cliques para abrir</div>
    <div class="fx-idle">
      <div class="fx-idle-ico"><?= icon('folder', 28) ?></div>
      <b>Nenhum arquivo aberto</b>
      <p>Clique no <b>+</b> para expandir a pasta. Dois cliques abrem a pasta ou o PDF — aí entram os filtros.</p>
    </div>
    <div class="fx-status"><?= count($fxFolders) ?> pastas · <?= $fileCount ?> arquivos · PDF operacional</div>
  </div>
</div>

<div class="fx-overlay" id="fx-filters" hidden>
  <div class="fx-filter-panel" onclick="event.stopPropagation()">
    <div class="fx-modal-head">
      <div>
        <h2 id="fx-filter-title">Filtros do relatório</h2>
        <p id="fx-filter-hint">Defina o recorte e o que entra no documento.</p>
      </div>
      <button type="button" class="fx-x js-fx-close" aria-label="Fechar"><?= icon('x', 18) ?></button>
    </div>
    <form id="fx-form" class="fx-form">
      <div class="fx-fields">
        <div class="fx-block-title">Período</div>
        <div class="grid g2">
          <div><label class="label">De</label><input class="input" type="date" name="from" value="<?= e($from) ?>"></div>
          <div><label class="label">Até</label><input class="input" type="date" name="to" value="<?= e($to) ?>"></div>
        </div>
      </div>

      <div class="fx-fields">
        <div class="fx-block-head">
          <div class="fx-block-title">Tipos de relatório</div>
          <label class="fx-check-mini"><input type="checkbox" id="fx-types-all"> Marcar todos</label>
        </div>
        <div class="fx-checks" id="fx-types">
          <label><input type="checkbox" name="types[]" value="atendimentos"> Resumo de atendimentos</label>
          <label><input type="checkbox" name="types[]" value="clientes"> Resumo de <?= e($clientWord) ?></label>
          <label><input type="checkbox" name="types[]" value="origens"> Resumo de origens</label>
          <label><input type="checkbox" name="types[]" value="financeiro"> Resumo do caixa</label>
          <label><input type="checkbox" name="types[]" value="cliente_resumo"> Resumo de cliente</label>
        </div>
      </div>

      <div class="grid g2">
        <div class="fx-fields">
          <div class="fx-block-title">Status</div>
          <label class="label">Atendimento</label>
          <select class="select" name="appt_status">
            <option value="ALL">Todos</option>
            <?php foreach (APPT_STATUS as $k => $v): ?>
              <option value="<?= e($k) ?>"><?= e($v[0]) ?></option>
            <?php endforeach; ?>
          </select>
          <label class="label" style="margin-top:10px">Cadastro</label>
          <select class="select" name="client_status">
            <option value="ALL">Todos</option>
            <option value="ACTIVE">Ativos</option>
            <option value="INACTIVE">Inativos</option>
          </select>
          <label class="label" style="margin-top:10px">Financeiro</label>
          <select class="select" name="finance_status">
            <option value="all">Todos</option>
            <option value="open">Em aberto</option>
            <option value="billed">Faturado</option>
            <option value="paid">Pago</option>
          </select>
        </div>
        <div class="fx-fields">
          <div class="fx-block-title">Administradores</div>
          <label class="fx-check"><input type="checkbox" name="admins" value="1"> Somente lançamentos de admin</label>
          <p class="fx-note">Usa o usuário que criou o cadastro ou o agendamento.</p>
        </div>
      </div>

      <div class="fx-fields">
        <div class="fx-block-head">
          <div class="fx-block-title">Usuários</div>
          <label class="fx-check-mini"><input type="checkbox" id="fx-users-all" checked> Todos</label>
        </div>
        <div class="fx-checks fx-checks-scroll" id="fx-users">
          <?php if (!$reportUsers): ?>
            <p class="fx-note">Nenhum usuário ativo neste painel.</p>
          <?php endif; ?>
          <?php foreach ($reportUsers as $u): ?>
            <label>
              <input type="checkbox" name="users[]" value="<?= e($u['id']) ?>">
              <?= e($u['name']) ?>
              <i><?= $u['role'] === 'TENANT_ADMIN' ? 'admin' : 'usuário' ?></i>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="fx-fields">
        <div class="fx-block-head">
          <div class="fx-block-title">Serviços</div>
          <label class="fx-check-mini"><input type="checkbox" id="fx-services-all" checked> Todos</label>
        </div>
        <div class="fx-checks fx-checks-scroll" id="fx-services">
          <?php if (!$reportServices): ?>
            <p class="fx-note">Nenhum serviço ativo.</p>
          <?php endif; ?>
          <?php foreach ($reportServices as $s): ?>
            <label><input type="checkbox" name="services[]" value="<?= e($s['id']) ?>"> <?= e($s['name']) ?></label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="fx-fields">
        <label class="fx-check"><input type="checkbox" name="totals" value="1" checked> Incluir totais no rodapé</label>
        <label class="fx-check"><input type="checkbox" name="client_summary" value="1"> Incluir resumo de cliente no atendimento</label>
      </div>

      <p class="fx-err" id="fx-err" hidden>Marque pelo menos um tipo de relatório.</p>
      <button type="submit" class="btn btn-primary"><?= icon('file') ?> Visualizar</button>
    </form>
  </div>
</div>

<div class="fx-overlay" id="fx-preview" hidden>
  <div class="fx-preview-shell" onclick="event.stopPropagation()">
    <div class="fx-preview-bar">
      <div>
        <b id="fx-preview-title">Prévia</b>
        <span id="fx-preview-meta"></span>
      </div>
      <div class="fx-preview-actions">
        <a class="btn btn-primary" id="fx-download" href="#"><?= icon('download') ?> Baixar PDF</a>
        <button type="button" class="fx-x js-fx-close" aria-label="Fechar"><?= icon('x', 18) ?></button>
      </div>
    </div>
    <div class="fx-a4-wrap" id="fx-a4-wrap"></div>
  </div>
</div>
