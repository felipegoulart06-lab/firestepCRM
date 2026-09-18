<?php
$terms = $terms ?? terms_of($tenant);
$from = date('Y-m-01');
$to = date('Y-m-d');
$tid = $tenant['id'];
coverage_ensure_schema();
$reportUsers = all('SELECT id, name, role FROM users WHERE tenant_id=? AND '.sql_true('active').' ORDER BY name', [$tid]);
$reportAgents = array_values(array_filter($reportUsers, static fn(array $u) => is_user_agent($u)));
$reportServices = all("SELECT id, name, category, status FROM services WHERE tenant_id=? ORDER BY name", [$tid]);
$reportCats = [];
foreach ($reportServices as $s) {
    $cat = trim((string)($s['category'] ?? ''));
    if ($cat !== '') {
        $reportCats[$cat] = $cat;
    }
}
ksort($reportCats, SORT_NATURAL | SORT_FLAG_CASE);
$reportSources = [];
foreach (all("SELECT COALESCE(NULLIF(utm_source,''), source) s FROM clients WHERE tenant_id=?", [$tid]) as $r) {
    $v = trim((string)($r['s'] ?? ''));
    if ($v !== '') {
        $reportSources[$v] = $v;
    }
}
foreach (all("SELECT COALESCE(NULLIF(utm_source,''), source) s FROM requests WHERE tenant_id=?", [$tid]) as $r) {
    $v = trim((string)($r['s'] ?? ''));
    if ($v !== '') {
        $reportSources[$v] = $v;
    }
}
foreach (all('SELECT DISTINCT source s FROM appointments WHERE tenant_id=?', [$tid]) as $r) {
    $v = trim((string)($r['s'] ?? ''));
    if ($v !== '') {
        $reportSources[$v] = $v;
    }
}
ksort($reportSources, SORT_NATURAL | SORT_FLAG_CASE);
$reportCities = [];
$reportStates = [];
foreach (['clients', 'suppliers'] as $tbl) {
    foreach (all("SELECT city, state FROM $tbl WHERE tenant_id=?", [$tid]) as $r) {
        $city = trim((string)($r['city'] ?? ''));
        $st = strtoupper(trim((string)($r['state'] ?? '')));
        if ($city !== '') {
            $reportCities[$city] = $city;
        }
        if (preg_match('/^[A-Z]{2}$/', $st)) {
            $reportStates[$st] = $st;
        }
    }
}
ksort($reportCities, SORT_NATURAL | SORT_FLAG_CASE);
ksort($reportStates);
$reportProducts = [];
foreach (all("SELECT DISTINCT product_type FROM suppliers WHERE tenant_id=? AND product_type IS NOT NULL AND product_type!='' ORDER BY product_type", [$tid]) as $r) {
    $reportProducts[trim((string)$r['product_type'])] = trim((string)$r['product_type']);
}
$clientWord = lower($terms['clients']);
$fxRoot = $fxRoot ?? 'Relatórios';
$requestWord = lower($terms['requests'] ?? 'solicitações');
if (!function_exists('fx_filter_people')) {
    function fx_filter_people(array $people): void
    {
        echo '<div class="fx-fields"><div class="fx-block-head"><div class="fx-block-title">Equipe</div>';
        echo '<label class="fx-check-mini"><input type="checkbox" class="js-fx-all" data-group="users" checked> Todos</label></div>';
        echo '<div class="fx-checks fx-checks-scroll js-fx-group" data-group="users">';
        if (!$people) {
            echo '<p class="fx-note">Nenhum usuário ativo neste painel.</p>';
        }
        foreach ($people as $u) {
            $tag = is_user_crm($u) ? 'admin' : (is_user_agent($u) ? 'agente' : 'usuário');
            echo '<label><input type="checkbox" name="users[]" value="'.e($u['id']).'"> '.e($u['name']).' <i>'.e($tag).'</i></label>';
        }
        echo '</div></div>';
    }
    function fx_filter_services(array $services): void
    {
        echo '<div class="fx-fields"><div class="fx-block-head"><div class="fx-block-title">Serviços</div>';
        echo '<label class="fx-check-mini"><input type="checkbox" class="js-fx-all" data-group="services" checked> Todos</label></div>';
        echo '<div class="fx-checks fx-checks-scroll js-fx-group" data-group="services">';
        if (!$services) {
            echo '<p class="fx-note">Nenhum serviço cadastrado.</p>';
        }
        foreach ($services as $s) {
            echo '<label><input type="checkbox" name="services[]" value="'.e($s['id']).'"> '.e($s['name']).'</label>';
        }
        echo '</div></div>';
    }
    function fx_filter_period(string $from, string $to, string $fromLabel = 'De', string $toLabel = 'Até'): void
    {
        echo '<div class="fx-fields"><div class="fx-block-title">Período</div><div class="fx-dates">';
        echo '<div><label class="label">'.e($fromLabel).'</label><input class="input" type="date" name="from" value="'.e($from).'"></div>';
        echo '<div><label class="label">'.e($toLabel).'</label><input class="input" type="date" name="to" value="'.e($to).'"></div>';
        echo '</div></div>';
    }
    function fx_filter_totals(bool $summary = false): void
    {
        echo '<div class="fx-fields">';
        echo '<label class="fx-check"><input type="checkbox" name="totals" value="1" checked> Incluir totais no rodapé</label>';
        if ($summary) {
            echo '<label class="fx-check"><input type="checkbox" name="client_summary" value="1"> Incluir resumo por cliente</label>';
        }
        echo '</div>';
    }
    function fx_options(array $items, string $empty = 'Todos'): void
    {
        echo '<option value="">'.e($empty).'</option>';
        foreach ($items as $k => $v) {
            $val = is_int($k) ? (string)$v : (string)$k;
            echo '<option value="'.e($val).'">'.e((string)$v).'</option>';
        }
    }
}
$fxFolders = $fxFolders ?? [
    ['name' => 'Atendimento', 'files' => [
        ['kind' => 'agendamentos', 'file' => 'Relatório de agendamentos', 'hint' => 'Horários, clientes, serviços e status no período.'],
        ['kind' => 'solicitacoes', 'file' => 'Relatório de '.$requestWord, 'hint' => 'Pedidos recebidos pelo site, WhatsApp e integrações.'],
    ]],
    ['name' => 'Relacionamento', 'files' => [
        ['kind' => 'clientes', 'file' => 'Relatório de '.$clientWord, 'hint' => 'Cadastros, contatos, origem e atendimentos no período.'],
        ['kind' => 'agentes', 'file' => 'Relatório de agentes', 'hint' => 'Equipe de atendimento, situação e volume de agendamentos/cadastros.'],
        ['kind' => 'servicos', 'file' => 'Relatório de serviços', 'hint' => 'Catálogo, preço, duração e quantidade de agendamentos.'],
        ['kind' => 'origens', 'file' => 'Resumo de origens', 'hint' => 'Canais que geraram cadastros e solicitações.'],
    ]],
    ['name' => 'Documentos', 'files' => [
        ['kind' => 'documentos', 'file' => 'Todos os documentos', 'hint' => 'CPF e CNPJ de todos os clientes e documentos dos agentes.'],
        ['kind' => 'documentos_cpf', 'file' => 'Clientes CPF', 'hint' => 'Documentos de todos os clientes pessoa física.'],
        ['kind' => 'documentos_cnpj', 'file' => 'Clientes CNPJ', 'hint' => 'Documentos de todos os clientes pessoa jurídica.'],
        ['kind' => 'documentos_agentes', 'file' => 'Agentes', 'hint' => 'CPF ou CNPJ cadastrado de cada agente.'],
    ]],
    ['name' => 'Financeiro', 'files' => [
        ['kind' => 'financeiro', 'file' => 'Resumo do caixa', 'hint' => 'Lançamentos, receber e pagar no intervalo.'],
    ]],
    ['name' => 'Contratos', 'files' => (static function () use ($tenant) {
        $files = [];
        foreach (clauses_lists($tenant) as $list) {
            $files[] = [
                'kind' => 'contratos',
                'file' => $list['name'],
                'hint' => 'Cabeçalho, agendamentos desta lista, valores, cláusulas e rodapé.',
                'contract_id' => $list['id'],
            ];
        }
        if (!$files) {
            $files[] = [
                'kind' => 'contratos',
                'file' => 'Contrato de prestação',
                'hint' => 'Crie listas em Configurações → Contratos de agendamentos. Sem lista, o PDF usa o período filtrado.',
                'contract_id' => '',
            ];
        }
        return $files;
    })()],
    ['name' => 'Abrangência', 'files' => [
        ['kind' => 'abrangencia', 'file' => 'Mapa de abrangência', 'hint' => 'Fornecedores CNPJ, clientes CNPJ e atendimentos externos manuais no período.'],
    ]],
    ['name' => 'Fornecedores', 'files' => [
        ['kind' => 'fornecedores', 'file' => 'Lista de fornecedores', 'hint' => 'Cadastro administrativo: documento, produto, contato e local.'],
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
                <button type="button" class="fx-file" data-kind="<?= e($file['kind']) ?>" data-file="<?= e($file['file']) ?>" data-folder-name="<?= e($folder['name']) ?>" data-hint="<?= e($file['hint']) ?>" data-contract-id="<?= e((string)($file['contract_id'] ?? '')) ?>">
                  <?= e($file['file']) ?>
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
      <p>Clique no <b>+</b> para expandir a pasta. Dois cliques no nome abrem os filtros para gerar o arquivo.</p>
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
      <input type="hidden" name="types[]" id="fx-kind" value="">
      <input type="hidden" name="contract_id" id="fx-contract-id" value="">

      <div class="fx-kind-panel" data-fx-kind="agendamentos" hidden>
        <div class="fx-filter-grid fx-filter-grid-2">
          <div class="fx-filter-col">
            <?php fx_filter_period($from, $to, 'Data da agenda', 'Até'); ?>
            <div class="fx-fields">
              <div class="fx-block-title">Status do agendamento</div>
              <select class="select" name="appt_status">
                <option value="ALL">Todos</option>
                <?php foreach (APPT_STATUS as $k => $v): ?>
                  <option value="<?= e($k) ?>"><?= e($v[0]) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="fx-fields">
              <label class="label">Canal / origem</label>
              <select class="select" name="source"><?php fx_options($reportSources, 'Todos os canais'); ?></select>
            </div>
            <?php fx_filter_totals(true); ?>
          </div>
          <div class="fx-filter-col">
            <?php fx_filter_people($reportUsers); ?>
            <?php fx_filter_services($reportServices); ?>
          </div>
        </div>
      </div>

      <div class="fx-kind-panel" data-fx-kind="solicitacoes" hidden>
        <div class="fx-filter-grid fx-filter-grid-2">
          <div class="fx-filter-col">
            <?php fx_filter_period($from, $to, 'Recebida de', 'Até'); ?>
            <div class="fx-fields">
              <label class="label">Protocolo, nome ou telefone</label>
              <input class="input" type="search" name="q" placeholder="Buscar solicitação">
            </div>
            <div class="fx-fields">
              <label class="label">Status da solicitação</label>
              <select class="select" name="request_status">
                <option value="ALL">Todos</option>
                <?php foreach (REQ_STATUS as $k => $v): ?>
                  <option value="<?= e($k) ?>"><?= e($v) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="fx-fields">
              <label class="label">Canal de entrada</label>
              <select class="select" name="source"><?php fx_options($reportSources, 'Todos os canais'); ?></select>
            </div>
            <?php fx_filter_totals(); ?>
          </div>
          <div class="fx-filter-col">
            <?php fx_filter_services($reportServices); ?>
          </div>
        </div>
      </div>

      <div class="fx-kind-panel" data-fx-kind="clientes" hidden>
        <div class="fx-filter-grid fx-filter-grid-2">
          <div class="fx-filter-col">
            <?php fx_filter_period($from, $to, 'Cadastro de', 'Até'); ?>
            <div class="fx-fields">
              <label class="label">Status do cliente</label>
              <select class="select" name="client_status">
                <option value="ALL">Todos</option>
                <option value="ACTIVE">Ativos</option>
                <option value="INACTIVE">Inativos</option>
              </select>
            </div>
            <div class="fx-fields">
              <label class="label">Origem / tag de captação</label>
              <select class="select" name="source"><?php fx_options($reportSources, 'Todas as origens'); ?></select>
            </div>
            <div class="fx-fields">
              <label class="label">Cidade</label>
              <select class="select" name="city"><?php fx_options($reportCities, 'Todas'); ?></select>
            </div>
            <?php fx_filter_totals(); ?>
          </div>
          <div class="fx-filter-col">
            <label class="fx-check"><input type="checkbox" name="admins" value="1"> Somente cadastros feitos por admin</label>
            <?php fx_filter_people($reportUsers); ?>
            <?php fx_filter_services($reportServices); ?>
          </div>
        </div>
      </div>

      <div class="fx-kind-panel" data-fx-kind="agentes" hidden>
        <div class="fx-filter-grid fx-filter-grid-2">
          <div class="fx-filter-col">
            <?php fx_filter_period($from, $to, 'Volume de', 'Até'); ?>
            <div class="fx-fields">
              <label class="label">ID, nome ou e-mail</label>
              <input class="input" type="search" name="q" placeholder="Filtrar agente">
            </div>
            <div class="fx-fields">
              <label class="label">Nível de acesso</label>
              <select class="select" name="agent_role">
                <option value="user_agent">Agentes</option>
                <option value="user_crm">Administradores</option>
                <option value="all">Toda a equipe</option>
              </select>
            </div>
            <div class="fx-fields">
              <label class="label">Situação</label>
              <select class="select" name="agent_status">
                <option value="ALL">Todos</option>
                <option value="ACTIVE">Ativos</option>
                <option value="INACTIVE">Inativos</option>
              </select>
            </div>
            <?php fx_filter_totals(); ?>
          </div>
          <div class="fx-filter-col">
            <?php fx_filter_people($reportAgents ?: $reportUsers); ?>
          </div>
        </div>
      </div>

      <div class="fx-kind-panel" data-fx-kind="servicos" hidden>
        <div class="fx-filter-grid fx-filter-grid-2">
          <div class="fx-filter-col">
            <?php fx_filter_period($from, $to, 'Agendamentos de', 'Até'); ?>
            <div class="fx-fields">
              <label class="label">Categoria</label>
              <select class="select" name="category"><?php fx_options($reportCats, 'Todas as categorias'); ?></select>
            </div>
            <div class="fx-fields">
              <label class="label">Vigência / status</label>
              <select class="select" name="service_status">
                <option value="ALL">Todos</option>
                <option value="ACTIVE">Ativos</option>
                <option value="INACTIVE">Inativos</option>
              </select>
            </div>
            <?php fx_filter_totals(); ?>
          </div>
          <div class="fx-filter-col">
            <?php fx_filter_services($reportServices); ?>
          </div>
        </div>
      </div>

      <div class="fx-kind-panel" data-fx-kind="origens" hidden>
        <div class="fx-filter-grid">
          <div class="fx-filter-col">
            <?php fx_filter_period($from, $to); ?>
            <div class="fx-fields">
              <label class="label">Canal de marketing</label>
              <select class="select" name="source"><?php fx_options($reportSources, 'Todos os canais'); ?></select>
            </div>
            <?php fx_filter_totals(); ?>
          </div>
        </div>
      </div>

      <div class="fx-kind-panel" data-fx-kind="documentos" hidden>
        <div class="fx-filter-grid fx-filter-grid-2">
          <div class="fx-filter-col">
            <?php fx_filter_period($from, $to, 'Cadastro de', 'Até'); ?>
            <div class="fx-fields">
              <label class="label">Tipo de documento</label>
              <select class="select" name="document_kind">
                <option value="all">CPF e CNPJ</option>
                <option value="cpf">Somente CPF</option>
                <option value="cnpj">Somente CNPJ</option>
              </select>
            </div>
            <div class="fx-fields">
              <label class="label">Validação</label>
              <select class="select" name="doc_check">
                <option value="all">Todos</option>
                <option value="validated">Com documento</option>
                <option value="pending">Pendentes</option>
              </select>
            </div>
            <?php fx_filter_totals(); ?>
          </div>
          <div class="fx-filter-col">
            <div class="fx-fields">
              <label class="label">Status do cadastro</label>
              <select class="select" name="client_status">
                <option value="ALL">Todos</option>
                <option value="ACTIVE">Ativos</option>
                <option value="INACTIVE">Inativos</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <div class="fx-kind-panel" data-fx-kind="documentos_cpf" hidden>
        <div class="fx-filter-grid fx-filter-grid-2">
          <div class="fx-filter-col">
            <?php fx_filter_period($from, $to, 'Cadastro de', 'Até'); ?>
            <div class="fx-fields">
              <div class="fx-dates">
                <div><label class="label">Idade mínima</label><input class="input" type="number" name="age_min" min="0" max="120" placeholder="—"></div>
                <div><label class="label">Idade máxima</label><input class="input" type="number" name="age_max" min="0" max="120" placeholder="—"></div>
              </div>
            </div>
            <div class="fx-fields">
              <label class="label">Validação do CPF</label>
              <select class="select" name="doc_check">
                <option value="all">Todos</option>
                <option value="validated">Validados</option>
                <option value="pending">Pendentes</option>
              </select>
            </div>
            <?php fx_filter_totals(); ?>
          </div>
          <div class="fx-filter-col">
            <div class="fx-fields">
              <label class="label">UF</label>
              <select class="select" name="state"><?php fx_options($reportStates, 'Todas'); ?></select>
            </div>
            <div class="fx-fields">
              <label class="label">Cidade / região</label>
              <select class="select" name="city"><?php fx_options($reportCities, 'Todas'); ?></select>
            </div>
            <div class="fx-fields">
              <label class="label">Status</label>
              <select class="select" name="client_status">
                <option value="ALL">Todos</option>
                <option value="ACTIVE">Ativos</option>
                <option value="INACTIVE">Inativos</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <div class="fx-kind-panel" data-fx-kind="documentos_cnpj" hidden>
        <div class="fx-filter-grid fx-filter-grid-2">
          <div class="fx-filter-col">
            <?php fx_filter_period($from, $to, 'Cadastro de', 'Até'); ?>
            <div class="fx-fields">
              <label class="label">Validação do CNPJ</label>
              <select class="select" name="doc_check">
                <option value="all">Todos</option>
                <option value="validated">Validados</option>
                <option value="pending">Pendentes</option>
              </select>
            </div>
            <div class="fx-fields">
              <label class="label">Status</label>
              <select class="select" name="client_status">
                <option value="ALL">Todos</option>
                <option value="ACTIVE">Ativos</option>
                <option value="INACTIVE">Inativos</option>
              </select>
            </div>
            <?php fx_filter_totals(); ?>
          </div>
          <div class="fx-filter-col">
            <div class="fx-fields">
              <label class="label">UF</label>
              <select class="select" name="state"><?php fx_options($reportStates, 'Todas'); ?></select>
            </div>
            <div class="fx-fields">
              <label class="label">Cidade / setor de atuação</label>
              <select class="select" name="city"><?php fx_options($reportCities, 'Todas'); ?></select>
            </div>
            <div class="fx-fields">
              <label class="label">Nome</label>
              <input class="input" type="search" name="q" placeholder="Razão social">
            </div>
          </div>
        </div>
      </div>

      <div class="fx-kind-panel" data-fx-kind="documentos_agentes" hidden>
        <div class="fx-filter-grid fx-filter-grid-2">
          <div class="fx-filter-col">
            <?php fx_filter_period($from, $to, 'Cadastro de', 'Até'); ?>
            <div class="fx-fields">
              <label class="label">Tipo de documento</label>
              <select class="select" name="document_kind">
                <option value="all">CPF e CNPJ</option>
                <option value="cpf">CPF</option>
                <option value="cnpj">CNPJ</option>
              </select>
            </div>
            <div class="fx-fields">
              <label class="label">Pendência</label>
              <select class="select" name="doc_check">
                <option value="all">Todos</option>
                <option value="validated">Validados</option>
                <option value="pending">Pendentes</option>
              </select>
            </div>
            <?php fx_filter_totals(); ?>
          </div>
          <div class="fx-filter-col">
            <div class="fx-fields">
              <label class="label">Situação do agente</label>
              <select class="select" name="agent_status">
                <option value="ALL">Todos</option>
                <option value="ACTIVE">Ativos</option>
                <option value="INACTIVE">Inativos</option>
              </select>
            </div>
            <div class="fx-fields">
              <label class="label">Nome ou ID</label>
              <input class="input" type="search" name="q" placeholder="Filtrar">
            </div>
          </div>
        </div>
      </div>

      <div class="fx-kind-panel" data-fx-kind="financeiro" hidden>
        <div class="fx-filter-grid fx-filter-grid-2">
          <div class="fx-filter-col">
            <?php fx_filter_period($from, $to); ?>
            <div class="fx-fields">
              <label class="label">Competência ou caixa</label>
              <select class="select" name="finance_date">
                <option value="competence">Data de competência (vencimento)</option>
                <option value="caixa">Data de caixa (pagamento)</option>
              </select>
            </div>
            <div class="fx-fields">
              <label class="label">Plano de contas / tipo</label>
              <select class="select" name="finance_kind">
                <option value="all">Receber e pagar</option>
                <option value="receivable">Contas a receber</option>
                <option value="payable">Contas a pagar</option>
              </select>
            </div>
            <div class="fx-fields">
              <label class="label">Fluxo</label>
              <select class="select" name="finance_flow">
                <option value="all">Entrada e saída</option>
                <option value="in">Entrada</option>
                <option value="out">Saída</option>
              </select>
            </div>
            <?php fx_filter_totals(); ?>
          </div>
          <div class="fx-filter-col">
            <div class="fx-fields">
              <label class="label">Status financeiro</label>
              <select class="select" name="finance_status">
                <option value="all">Todos</option>
                <option value="open">Em aberto</option>
                <option value="billed">Faturado</option>
                <option value="paid">Pago</option>
              </select>
            </div>
            <div class="fx-fields">
              <label class="label">Forma de pagamento</label>
              <select class="select" name="payment_method">
                <option value="">Todas</option>
                <?php foreach (FINANCE_PAY_METHODS as $k => $v): ?>
                  <option value="<?= e($k) ?>"><?= e($v) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php fx_filter_services($reportServices); ?>
          </div>
        </div>
      </div>

      <div class="fx-kind-panel" data-fx-kind="contratos" hidden>
        <div class="fx-filter-grid fx-filter-grid-2">
          <div class="fx-filter-col">
            <?php fx_filter_period($from, $to, 'Agendamentos de', 'Até'); ?>
            <div class="fx-fields">
              <label class="label">Status do agendamento</label>
              <select class="select" name="appt_status">
                <option value="ALL">Todos (exceto cancelados na lista vazia)</option>
                <?php foreach (APPT_STATUS as $k => $v): ?>
                  <option value="<?= e($k) ?>"><?= e($v[0]) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="fx-fields">
              <div class="fx-dates">
                <div><label class="label">Valor mínimo</label><input class="input" type="number" name="min_amount" min="0" step="0.01" placeholder="R$"></div>
                <div><label class="label">Valor máximo</label><input class="input" type="number" name="max_amount" min="0" step="0.01" placeholder="R$"></div>
              </div>
            </div>
            <?php fx_filter_totals(); ?>
          </div>
          <div class="fx-filter-col">
            <?php fx_filter_services($reportServices); ?>
          </div>
        </div>
      </div>

      <div class="fx-kind-panel" data-fx-kind="abrangencia" hidden>
        <div class="fx-filter-grid fx-filter-grid-2">
          <div class="fx-filter-col">
            <?php fx_filter_period($from, $to); ?>
            <div class="fx-fields">
              <label class="label">Camada do mapa</label>
              <select class="select" name="coverage_layer">
                <option value="all">Fornecedores, clientes e visitas</option>
                <option value="suppliers">Só fornecedores CNPJ</option>
                <option value="clients">Só clientes CNPJ</option>
                <option value="visits">Só atendimentos externos</option>
              </select>
            </div>
            <?php fx_filter_totals(); ?>
          </div>
          <div class="fx-filter-col">
            <div class="fx-fields">
              <label class="label">Estado</label>
              <select class="select" name="state"><?php fx_options($reportStates, 'Todos'); ?></select>
            </div>
            <div class="fx-fields">
              <label class="label">Cidade / região de atendimento</label>
              <select class="select" name="city"><?php fx_options($reportCities, 'Todas'); ?></select>
            </div>
          </div>
        </div>
      </div>

      <div class="fx-kind-panel" data-fx-kind="fornecedores" hidden>
        <div class="fx-filter-grid fx-filter-grid-2">
          <div class="fx-filter-col">
            <?php fx_filter_period($from, $to, 'Cadastro de', 'Até'); ?>
            <div class="fx-fields">
              <label class="label">Categoria de insumo</label>
              <select class="select" name="product_type"><?php fx_options($reportProducts, 'Todas'); ?></select>
            </div>
            <div class="fx-fields">
              <label class="label">Tipo de documento</label>
              <select class="select" name="document_kind">
                <option value="all">CPF e CNPJ</option>
                <option value="cpf">CPF</option>
                <option value="cnpj">CNPJ</option>
              </select>
            </div>
            <?php fx_filter_totals(); ?>
          </div>
          <div class="fx-filter-col">
            <div class="fx-fields">
              <label class="label">UF</label>
              <select class="select" name="state"><?php fx_options($reportStates, 'Todas'); ?></select>
            </div>
            <div class="fx-fields">
              <label class="label">Cidade</label>
              <select class="select" name="city"><?php fx_options($reportCities, 'Todas'); ?></select>
            </div>
            <div class="fx-fields">
              <label class="label">Nome</label>
              <input class="input" type="search" name="q" placeholder="Fornecedor">
            </div>
          </div>
        </div>
      </div>

      <div class="fx-filter-actions">
        <p class="fx-err" id="fx-err" hidden>Abra um relatório na árvore para gerar o PDF.</p>
        <button type="submit" class="btn btn-primary"><?= icon('file') ?> Visualizar</button>
      </div>
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
