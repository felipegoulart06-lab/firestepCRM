<?php
$old = $old ?? [];
$viewing = $viewing ?? null;
$edit = $edit ?? null;
$showForm = !empty($novo) || $edit;
$src = $edit ?: [];
$activeOn = $edit ? !empty($src['active']) : true;
$show = static fn($v) => ($v !== null && trim((string)$v) !== '') ? (string)$v : '—';
$communicateItems = communicate_items('agent', $agents, $tenant);
$communicateBack = '/app/agentes'.(!empty($_SERVER['QUERY_STRING']) ? '?'.$_SERVER['QUERY_STRING'] : '');
?>
<div class="page-head">
  <div>
    <h1>Agentes</h1>
    <p>Funcionários da empresa. Cada agente entra no CRM com login próprio (perfil <code>user_agent</code>).</p>
  </div>
  <?php if (!$showForm && !$viewing): ?>
    <a class="btn btn-primary" href="/app/agentes?novo=1"><?= icon('plus') ?> Novo agente</a>
  <?php endif; ?>
</div>

<?php if ($viewing):
  $kind = strtolower((string)($viewing['document_kind'] ?? '')) ?: br_doc_kind_from_value($viewing['cpf'] ?? '');
?>
<div class="card" style="margin-bottom:16px;padding:18px;max-width:760px">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap">
    <h2 style="margin:0">Detalhes do agente</h2>
    <div>
      <a class="btn btn-primary" href="/app/agentes?edit=<?= e($viewing['id']) ?>">Editar</a>
      <a class="btn btn-ghost" href="/app/agentes">Fechar</a>
    </div>
  </div>
  <div class="detail-grid" style="margin-top:14px">
    <div class="detail-item"><small>Nome</small><strong><?= e($show($viewing['name'] ?? '')) ?></strong></div>
    <div class="detail-item"><small>Usuário</small><strong><?= e($show($viewing['username'] ?? '')) ?></strong></div>
    <div class="detail-item"><small>E-mail</small><strong><?= e($show($viewing['email'] ?? '')) ?></strong></div>
    <div class="detail-item"><small>Telefone</small><strong><?= e(phone_fmt($viewing['phone'] ?? '') ?: '—') ?></strong></div>
    <div class="detail-item"><small>Documento</small><strong><?= $kind ? e(strtoupper($kind).' '.format_br_document($viewing['cpf'] ?? '', $kind)) : '—' ?></strong></div>
    <div class="detail-item"><small>Status</small><strong><?= !empty($viewing['active']) ? 'Conta ativa' : 'Conta inativa' ?></strong></div>
    <div class="detail-item"><small>Último acesso</small><strong><?= e(!empty($viewing['last_login_at']) ? date('d/m/Y H:i', strtotime((string)$viewing['last_login_at'])) : 'Ainda não entrou') ?></strong></div>
    <div class="detail-item"><small>Cadastro</small><strong><?= e(!empty($viewing['created_at']) ? date('d/m/Y H:i', strtotime((string)$viewing['created_at'])) : '—') ?></strong></div>
  </div>
</div>
<?php endif; ?>

<?php if ($showForm): ?>
<div class="card" style="margin-bottom:16px;padding:18px">
  <h2 style="margin:0 0 12px"><?= $edit ? 'Editar agente' : 'Novo agente' ?></h2>
  <form method="post" action="/app/agentes/salvar" class="grid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
    <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
    <?php if ($edit): ?><input type="hidden" name="id" value="<?= e($edit['id']) ?>"><?php endif; ?>
    <div><label class="label">Nome</label><input class="input" name="name" required value="<?= e(old_fill($old, 'name', $src['name'] ?? '')) ?>"></div>
    <div><label class="label">E-mail</label><input class="input" type="email" name="email" required value="<?= e(old_fill($old, 'email', $src['email'] ?? '')) ?>"></div>
    <div>
      <label class="label">Usuário de acesso</label>
      <input class="input" name="username" required pattern="[a-zA-Z0-9._-]{3,40}" value="<?= e(old_fill($old, 'username', $src['username'] ?? '')) ?>">
    </div>
    <div><label class="label">Telefone</label><input class="input" name="phone" inputmode="tel" value="<?= e(old_fill($old, 'phone', $src['phone'] ?? '')) ?>"></div>
    <div style="grid-column:1/-1">
      <?php br_document_fields('cpf', old_fill($old, 'cpf', $src['cpf'] ?? '') ?: null, false, 'document_kind', ['document_kind' => old_fill($old, 'document_kind', $src['document_kind'] ?? '')]); ?>
      <p style="color:#667085;font-size:12px;margin:6px 0 0">Usado no relatório de documentos. CPF ou CNPJ do agente.</p>
    </div>
    <?php if (!$edit): ?>
    <div style="grid-column:1/-1">
      <label class="label">Senha de primeiro acesso</label>
      <input class="input" type="password" name="password" required minlength="10" autocomplete="new-password">
      <p style="color:#667085;font-size:12px;margin:6px 0 0">Mínimo 10 caracteres, com letra e número. Só no cadastro. Depois o agente redefine no primeiro login; alteração posterior só no banco.</p>
    </div>
    <?php endif; ?>
    <label class="label" style="grid-column:1/-1;display:flex;gap:8px;align-items:center">
      <input type="checkbox" name="active" value="1" <?= $activeOn ? 'checked' : '' ?>> Conta ativa
    </label>
    <div style="grid-column:1/-1;display:flex;gap:8px">
      <button class="btn btn-primary"><?= $edit ? 'Salvar agente' : 'Criar agente' ?></button>
      <a class="btn btn-ghost" href="<?= $edit ? '/app/agentes?ver='.e($edit['id']) : '/app/agentes' ?>"><?= $edit ? 'Cancelar' : 'Cancelar' ?></a>
    </div>
  </form>
</div>
<?php endif; ?>

<div class="card table-wrap">
<?php if (!$agents): ?>
  <div class="empty"><b>Nenhum agente cadastrado.</b><p>Crie funcionários para atender agenda, clientes e pipeline sem acessar financeiro ou configurações.</p></div>
<?php else: ?>
<table class="data">
<thead><tr><th>Nome</th><th>Usuário</th><th>Documento</th><th>E-mail</th><th>Telefone</th><th>Status</th><th>Último acesso</th><th>Ações</th></tr></thead>
<tbody>
<?php foreach ($agents as $r): ?>
<tr>
  <td><b><?= e($r['name']) ?></b></td>
  <td><?= e($r['username']) ?></td>
  <td><?php
    $kind = strtolower((string)($r['document_kind'] ?? '')) ?: br_doc_kind_from_value($r['cpf'] ?? '');
    echo $kind ? e(strtoupper($kind).' '.format_br_document($r['cpf'] ?? '', $kind)) : '—';
  ?></td>
  <td><?= e($r['email']) ?></td>
  <td><?= e(phone_fmt($r['phone'] ?? '')) ?></td>
  <td><?= !empty($r['active']) ? badge_appt('CONFIRMED') : badge_appt('DONE') ?></td>
  <td><?= e(!empty($r['last_login_at']) ? date('d/m/Y H:i', strtotime((string)$r['last_login_at'])) : 'Ainda não entrou') ?></td>
  <td class="row-actions-cell">
    <div class="row-actions">
      <button class="btn btn-ghost js-communicate" type="button" data-communicate-key="agent:<?= e($r['id']) ?>" title="Comunicar pelo WhatsApp"><?= icon('message', 13) ?> Comunicar</button>
      <a class="btn btn-ghost" href="/app/agentes?ver=<?= e($r['id']) ?>">Ver detalhes</a>
      <a class="btn btn-ghost" href="/app/agentes?edit=<?= e($r['id']) ?>">Editar</a>
      <form method="post" action="/app/agentes/excluir" onsubmit="return confirm('Remover este agente?')">
        <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
        <input type="hidden" name="id" value="<?= e($r['id']) ?>">
        <button class="btn btn-ghost">Excluir</button>
      </form>
    </div>
  </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</div>
<?php include VIEWS . '/app/communicate_modal.php'; ?>
