<?php
$terms = terms_of($tenant);
$from = date('Y-m-01');
$to = date('Y-m-d');
?>
<div class="page-head">
  <div>
    <h1>Relatórios</h1>
    <p>Abra a pasta, selecione o PDF e baixe. Só dados operacionais do CRM — sem prontuário clínico.</p>
  </div>
</div>

<div class="fx">
  <input class="fx-pick" type="radio" name="fx" id="fx-atend" checked>
  <input class="fx-pick" type="radio" name="fx" id="fx-cli">
  <input class="fx-pick" type="radio" name="fx" id="fx-orig">
  <input class="fx-pick" type="radio" name="fx" id="fx-fin">

  <aside class="fx-tree">
    <div class="fx-tree-bar"><?= icon('folder') ?> Relatórios</div>
    <div class="fx-tree-body">
      <details class="fx-folder" open>
        <summary><i class="fx-twist" aria-hidden="true"></i><span class="fx-ico"><?= icon('folder',16) ?></span> Atendimento</summary>
        <ul class="fx-kids">
          <li><label class="fx-file" for="fx-atend"><?= icon('pdf',16) ?> Resumo de atendimentos.pdf</label></li>
        </ul>
      </details>
      <details class="fx-folder" open>
        <summary><i class="fx-twist" aria-hidden="true"></i><span class="fx-ico"><?= icon('folder',16) ?></span> Relacionamento</summary>
        <ul class="fx-kids">
          <li><label class="fx-file" for="fx-cli"><?= icon('pdf',16) ?> Resumo de <?= e(lower($terms['clients'])) ?>.pdf</label></li>
          <li><label class="fx-file" for="fx-orig"><?= icon('pdf',16) ?> Resumo de origens.pdf</label></li>
        </ul>
      </details>
      <details class="fx-folder" open>
        <summary><i class="fx-twist" aria-hidden="true"></i><span class="fx-ico"><?= icon('folder',16) ?></span> Financeiro</summary>
        <ul class="fx-kids">
          <li><label class="fx-file" for="fx-fin"><?= icon('pdf',16) ?> Resumo do caixa.pdf</label></li>
        </ul>
      </details>
    </div>
  </aside>

  <div class="fx-stage">
    <div class="fx-path"><?= icon('folder',14) ?> Relatórios <span>›</span> <b>arquivo selecionado</b></div>

    <article class="fx-doc" data-for="fx-atend">
      <div class="fx-filehead">
        <div class="fx-big"><?= icon('pdf',28) ?></div>
        <div>
          <h2>Resumo de atendimentos.pdf</h2>
          <p>Lista horários, <?= e(lower($terms['clients'])) ?>, serviços e status do período.</p>
          <div class="fx-meta"><span>Tipo <i>PDF</i></span><span>Pasta <i>Atendimento</i></span></div>
        </div>
      </div>
      <form method="get" action="/app/relatorios/atendimentos.pdf" class="grid">
        <div class="grid g2">
          <div><label class="label">De</label><input class="input" type="date" name="from" value="<?= e($from) ?>"></div>
          <div><label class="label">Até</label><input class="input" type="date" name="to" value="<?= e($to) ?>"></div>
        </div>
        <button class="btn btn-primary"><?= icon('download') ?> Baixar PDF</button>
      </form>
    </article>

    <article class="fx-doc" data-for="fx-cli">
      <div class="fx-filehead">
        <div class="fx-big"><?= icon('pdf',28) ?></div>
        <div>
          <h2>Resumo de <?= e(lower($terms['clients'])) ?>.pdf</h2>
          <p>Cadastros, contatos, origem e quantidade de atendimentos.</p>
          <div class="fx-meta"><span>Tipo <i>PDF</i></span><span>Pasta <i>Relacionamento</i></span></div>
        </div>
      </div>
      <form method="get" action="/app/relatorios/clientes.pdf" class="grid">
        <div><label class="label">Status</label>
          <select class="select" name="status"><option value="ALL">Todos</option><option value="ACTIVE">Ativos</option><option value="INACTIVE">Inativos</option></select>
        </div>
        <button class="btn btn-primary"><?= icon('download') ?> Baixar PDF</button>
      </form>
    </article>

    <article class="fx-doc" data-for="fx-orig">
      <div class="fx-filehead">
        <div class="fx-big"><?= icon('pdf',28) ?></div>
        <div>
          <h2>Resumo de origens.pdf</h2>
          <p>Quais canais geraram mais cadastros e solicitações.</p>
          <div class="fx-meta"><span>Tipo <i>PDF</i></span><span>Pasta <i>Relacionamento</i></span></div>
        </div>
      </div>
      <form method="get" action="/app/relatorios/origens.pdf" class="grid">
        <div><label class="label">Período</label>
          <select class="select" name="period"><option value="30">Últimos 30 dias</option><option value="90">Últimos 90 dias</option><option value="365">Último ano</option></select>
        </div>
        <button class="btn btn-primary"><?= icon('download') ?> Baixar PDF</button>
      </form>
    </article>

    <article class="fx-doc" data-for="fx-fin">
      <div class="fx-filehead">
        <div class="fx-big"><?= icon('pdf',28) ?></div>
        <div>
          <h2>Resumo do caixa.pdf</h2>
          <p>Lançamentos, contas a receber e a pagar no intervalo.</p>
          <div class="fx-meta"><span>Tipo <i>PDF</i></span><span>Pasta <i>Financeiro</i></span></div>
        </div>
      </div>
      <form method="get" action="/app/financeiro/relatorio.pdf" class="grid">
        <div class="grid g2">
          <div><label class="label">De</label><input class="input" type="date" name="from" value="<?= e($from) ?>"></div>
          <div><label class="label">Até</label><input class="input" type="date" name="to" value="<?= e($to) ?>"></div>
        </div>
        <button class="btn btn-primary"><?= icon('download') ?> Baixar PDF</button>
      </form>
    </article>

    <div class="fx-status">3 pastas · 4 arquivos · PDF operacional</div>
  </div>
</div>
