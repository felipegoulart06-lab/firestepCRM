<?php $from = date('Y-m-01'); $to = date('Y-m-d'); ?>
<div class="page-head">
  <div>
    <h1>Relatórios financeiros</h1>
    <p>Selecione o PDF na árvore à esquerda e baixe o resumo do caixa.</p>
  </div>
</div>

<div class="fx">
  <input class="fx-pick" type="radio" name="fx" id="fx-caixa" checked>

  <aside class="fx-tree">
    <div class="fx-tree-bar"><?= icon('folder') ?> Financeiro</div>
    <div class="fx-tree-body">
      <details class="fx-folder" open>
        <summary><i class="fx-twist" aria-hidden="true"></i><span class="fx-ico"><?= icon('folder',16) ?></span> Caixa</summary>
        <ul class="fx-kids">
          <li><label class="fx-file" for="fx-caixa"><?= icon('pdf',16) ?> Resumo do caixa.pdf</label></li>
        </ul>
      </details>
    </div>
  </aside>

  <div class="fx-stage">
    <div class="fx-path"><?= icon('folder',14) ?> Financeiro <span>›</span> Caixa <span>›</span> <b>Resumo do caixa.pdf</b></div>
    <article class="fx-doc" data-for="fx-caixa">
      <div class="fx-filehead">
        <div class="fx-big"><?= icon('pdf',28) ?></div>
        <div>
          <h2>Resumo do caixa.pdf</h2>
          <p>Lista lançamentos, recebíveis e pagamentos no intervalo escolhido.</p>
          <div class="fx-meta"><span>Tipo <i>PDF</i></span><span>Pasta <i>Caixa</i></span></div>
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
    <div class="fx-status">1 pasta · 1 arquivo · PDF operacional</div>
  </div>
</div>
