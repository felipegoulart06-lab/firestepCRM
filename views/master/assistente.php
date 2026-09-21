<?php $threads = $threads ?? []; ?>
<div class="page-head">
  <div>
    <h1>Assistente</h1>
    <p class="subtitle">Quando a Priscila transfere para o Felipe, o pedido aparece aqui. O disparo no WhatsApp usa a instância em Configurações.</p>
  </div>
</div>

<section class="card" style="padding:18px">
  <?php if (!$threads): ?>
    <p class="muted" style="margin:0">Nenhuma dúvida recebida ainda.</p>
  <?php else: ?>
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Empresa</th><th>Usuário</th><th>Dúvida</th><th>Status</th><th>Resposta</th></tr></thead>
      <tbody>
      <?php foreach ($threads as $t): ?>
        <tr>
          <td>
            <b><?= e($t['business_name'] ?: $t['display_name']) ?></b>
            <div class="muted" style="font-size:12px"><?= e($t['tenant_email'] ?? '') ?></div>
          </td>
          <td><?= e($t['user_name'] ?: $t['username']) ?><div class="muted" style="font-size:12px"><?= e($t['user_email'] ?? '') ?></div></td>
          <td style="max-width:280px;white-space:normal"><?= e($t['question']) ?><div class="muted" style="font-size:12px"><?= e($t['created_at'] ?? '') ?></div></td>
          <td><?= ($t['status'] ?? '') === 'answered' ? 'Respondida' : 'Aberta' ?></td>
          <td>
            <?php if (($t['status'] ?? '') === 'answered' && trim((string)($t['answer'] ?? '')) !== ''): ?>
              <div style="max-width:280px;white-space:normal"><?= e($t['answer']) ?></div>
            <?php else: ?>
              <form method="post" action="/master/assistente/responder" style="display:flex;gap:8px;align-items:flex-start;min-width:240px">
                <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
                <input type="hidden" name="id" value="<?= e($t['id']) ?>">
                <textarea class="input" name="answer" rows="2" required maxlength="4000" placeholder="Escreva a resposta…" style="min-width:180px"></textarea>
                <button class="btn btn-primary">Enviar</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>
