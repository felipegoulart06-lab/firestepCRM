<?php
$fxRoot = 'Financeiro';
$fxFolders = [
    ['name' => 'Caixa', 'files' => [
        ['kind' => 'financeiro', 'file' => 'Resumo do caixa.pdf', 'hint' => 'Lançamentos, recebíveis e pagamentos no intervalo.'],
    ]],
];
?>
<div class="page-head">
  <div>
    <h1>Relatórios financeiros</h1>
    <p>Dois cliques no PDF abrem os filtros. O + abre a pasta com um clique.</p>
  </div>
</div>
<?php include VIEWS . '/app/relatorios_fx.php'; ?>
