<?php $from = date('Y-m-01'); $to = date('Y-m-d'); ?>
<div class="page-head">
  <div>
    <h1>Relatórios financeiros</h1>
    <p>Abra a pasta, selecione o PDF e baixe o resumo do caixa.</p>
  </div>
</div>

<div class="fx">
  <input class="fx-pick" type="radio" name="fx" id="fx-caixa">

  <aside class="fx-tree">
    <div class="fx-tree-bar"><?= icon('folder') ?> Financeiro</div>
    <div class="fx-tree-body">
      <details class="fx-folder">
        <summary><i class="fx-twist" aria-hidden="true"></i><span class="fx-ico"><?= icon('folder',16) ?></span> Caixa</summary>
        <ul class="fx-kids">
          <li><label class="fx-file" for="fx-caixa"><?= icon('pdf',16) ?> Resumo do caixa.pdf</label></li>
        </ul>
      </details>
    </div>
  </aside>

  <div class="fx-stage">
    <div class="fx-path fx-path-idle"><?= icon('folder',14) ?> Financeiro <span>›</span> escolha um arquivo</div>
    <div class="fx-path fx-path-caixa"><?= icon('folder',14) ?> Financeiro <span>›</span> Caixa <span>›</span> <b>Resumo do caixa.pdf</b></div>
    <div class="fx-idle">
      <div class="fx-idle-ico"><?= icon('folder',28) ?></div>
      <b>Nenhum arquivo aberto</b>
      <p>Abra a pasta Caixa à esquerda e clique no PDF para escolher o período e baixar.</p>
    </div>
    <article class="fx-doc" data-for="fx-caixa">
      <div class="fx-filehead">
        <div class="fx-big"><?= icon('pdf',28) ?></div>
        <div>
          <h2>Resumo do caixa.pdf</h2>
          <p>Lista lançamentos, recebíveis e pagamentos no intervalo escolhido.</p>
          <div class="fx-meta"><span>Tipo <i>PDF</i></span><span>Pasta <i>Caixa</i></span></div>
        </div>
      </div>
      <form method="get" action="/app/financeiro/relatorio.pdf" class="fx-form">
        <div class="fx-fields">
          <div class="grid g2">
            <div><label class="label">De</label><input class="input" type="date" name="from" value="<?= e($from) ?>"></div>
            <div><label class="label">Até</label><input class="input" type="date" name="to" value="<?= e($to) ?>"></div>
          </div>
        </div>
        <button class="btn btn-primary"><?= icon('download') ?> Baixar PDF</button>
      </form>
      <div class="fx-preview" aria-hidden="true"><span>Prévia do documento</span><div class="fx-sheet"><i></i><i></i><i></i></div></div>
    </article>
    <div class="fx-status">1 pasta · 1 arquivo · PDF operacional</div>
  </div>
</div>
