<?php
$hosts = $hosts ?? [];
$ready = !empty($ready);
$reveal = !empty($reveal);
$siteDomain = (string)($siteDomain ?? '');
?>
<h1>Webhooks</h1>
<div class="card" style="padding:20px;margin-bottom:16px">
  <h2 style="margin-top:0">Domínio do site</h2>
  <p style="color:#667085">O endpoint de entrada só aceita requisições do domínio cadastrado (cabeçalho Origin). Sem domínio, o webhook não recebe nada e o endereço fica oculto.</p>
  <form method="post" action="/app/webhooks/dominio">
    <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
    <label class="label">Domínio autorizado</label>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <input class="input" name="site_domain" required value="<?= e($siteDomain) ?>" placeholder="meusite.com.br" style="max-width:360px">
      <button class="btn btn-primary">Salvar domínio</button>
    </div>
  </form>
  <?php if ($hosts): ?>
    <p class="settings-hint">Liberado para: <b><?= e(implode(', ', $hosts)) ?></b></p>
  <?php endif; ?>
</div>

<div class="card" style="padding:20px;margin-bottom:16px">
  <h2 style="margin-top:0">Integração do seu site</h2>
  <?php if (!$ready): ?>
    <p style="color:#667085">Cadastre o domínio acima para liberar o endereço. Enquanto isso o webhook recusa qualquer origem.</p>
  <?php elseif (!$reveal): ?>
    <p style="color:#667085">O endereço fica oculto. Só o site no domínio salvo consegue enviar solicitações. Não publique o link em página aberta.</p>
    <a class="btn btn-ghost" href="/app/webhooks?show=1">Mostrar endpoint neste painel</a>
  <?php else: ?>
    <p style="color:#667085">Use este endereço apenas no código do site <b><?= e(implode(', ', $hosts)) ?></b>.</p>
    <label class="label">Endpoint</label>
    <div style="display:flex;gap:8px"><input class="input" id="whurl" readonly value="<?= e($url) ?>"><button class="btn btn-ghost" type="button" onclick="copyTxt('whurl')">Copiar</button></div>
    <label class="label" style="margin-top:12px">Segredo interno</label>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <input class="input" id="whsec" readonly value="<?= e($inbound['secret'] ?? '') ?>">
      <form method="post" action="/app/webhooks/rotacionar"><input type="hidden" name="_csrf" value="<?= e(csrf()) ?>"><button class="btn btn-ghost">Gerar novo token</button></form>
    </div>
    <p><a class="btn btn-ghost" href="/app/webhooks">Ocultar de novo</a></p>
  <?php endif; ?>
  <p>
    <span class="badge" style="background:<?= !empty($inbound['active'])?'#dcfce7':'#fee2e2' ?>;color:<?= !empty($inbound['active'])?'#166534':'#991b1b' ?>"><?= !empty($inbound['active'])?'ATIVO':'INATIVO' ?></span>
    <form method="post" action="/app/webhooks/toggle" style="display:inline"><input type="hidden" name="_csrf" value="<?= e(csrf()) ?>"><button class="btn btn-ghost"><?= !empty($inbound['active'])?'Desativar':'Ativar' ?></button></form>
  </p>
  <p style="font-size:13px;color:#667085">Campos mínimos: <b>name</b> e <b>phone</b> ou <b>email</b>. O navegador do visitante precisa chamar a partir do domínio salvo (CORS). Chamadas sem Origin ou de outro site são recusadas.</p>
  <pre style="background:#0f1c2e;color:#dbeafe;padding:14px;border-radius:10px;overflow:auto;font-size:12px">{
  "name": "Maria Oliveira",
  "phone": "47999999999",
  "email": "maria@email.com",
  "service": "Psicoterapia",
  "desired_date": "2026-09-20",
  "desired_time": "15:00",
  "message": "Gostaria de agendar uma consulta",
  "source": "website",
  "utm_source": "instagram",
  "utm_medium": "social",
  "utm_campaign": "agenda_setembro"
}</pre>
</div>
<form method="post" action="/app/webhooks/saida" class="card" style="padding:20px;margin-bottom:16px">
  <h2 style="margin-top:0">Webhook de saída</h2>
  <p style="color:#667085">O CRM avisa Make, n8n ou outro sistema.</p>
  <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
  <label class="label">URL de destino</label>
  <input class="input" name="url" value="<?= e($outbound['url'] ?? '') ?>" placeholder="https://hook.make.com/...">
  <?php $evs = json_arr($outbound['events'] ?? '[]');
  foreach (['appointment.created','appointment.confirmed','appointment.cancelled','request.received','client.created'] as $ev): ?>
    <label style="display:block"><input type="checkbox" name="events[]" value="<?= $ev ?>" <?= in_array($ev,$evs,true)?'checked':'' ?>> <?= $ev ?></label>
  <?php endforeach; ?>
  <label><input type="checkbox" name="active" <?= !empty($outbound['active'])?'checked':'' ?>> Ativo</label>
  <p><button class="btn btn-primary">Salvar saída</button></p>
</form>
<div class="card table-wrap">
  <div style="padding:16px;font-weight:700">Últimas requisições</div>
  <table class="data">
    <thead><tr><th>Data</th><th>Origem</th><th>Evento</th><th>Status</th><th>HTTP</th><th>Mensagem</th></tr></thead>
    <tbody>
    <?php foreach ($logs as $l): ?>
      <tr>
        <td><?= e(date('d/m/Y H:i', strtotime($l['created_at']))) ?></td>
        <td><?= e($l['source'] ?: '—') ?></td>
        <td><?= e($l['event']) ?></td>
        <td><?= e($l['status']) ?></td>
        <td><?= e((string)($l['http_status'] ?? '—')) ?></td>
        <td><?= e($l['response'] ?: '—') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
