<?php
$hours = json_arr($tenant['business_hours'] ?: '{}', default_hours());
$terms = terms_of($tenant);
$analytics = analytics_config($tenant);
$days = ['Domingo','Segunda','Terça','Quarta','Quinta','Sexta','Sábado'];
$tab = $_GET['tab'] ?? 'resumo';
$allowed = ['resumo','negocio','agenda','avancado','integracoes','conta'];
if (!in_array($tab, $allowed, true)) $tab = 'resumo';
$edit = ($_GET['edit'] ?? '') === '1';
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
      <label class="label">Nome exibido</label><input class="input" name="display_name" value="<?= e($tenant['display_name']) ?>">
      <div class="grid g2">
        <div><label class="label">Telefone</label><input class="input" name="phone" value="<?= e($tenant['phone']) ?>"></div>
        <div><label class="label">WhatsApp</label><input class="input" name="whatsapp" value="<?= e($tenant['whatsapp']) ?>"></div>
        <div><label class="label">E-mail</label><input class="input" name="email" type="email" value="<?= e($tenant['email']) ?>"></div>
        <div><label class="label">Fuso</label>
          <select class="select" name="timezone">
            <?php foreach (['America/Sao_Paulo','America/Manaus','America/Fortaleza','America/Recife'] as $tz): ?>
              <option <?= $tenant['timezone']===$tz?'selected':'' ?>><?= $tz ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div><label class="label">Cidade</label><input class="input" name="city" value="<?= e($tenant['city']) ?>"></div>
        <div><label class="label">Estado</label><input class="input" name="state" value="<?= e($tenant['state']) ?>"></div>
      </div>
      <label class="label">Endereço</label><input class="input" name="address" value="<?= e($tenant['address']) ?>">
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

<div class="card settings-panel" style="margin-top:14px">
  <div class="settings-panel-head">
    <div>
      <h2>Google Sheets</h2>
      <p>Clientes, agendamentos e serviços sincronizam depois do login no Google.</p>
    </div>
  </div>
  <?php if (!$googleReady): ?>
    <p class="flash" style="background:#fff7ed;color:#9a3412;border-color:#fed7aa">O Admin Master ainda precisa configurar o Client ID e o Client Secret do Google.</p>
  <?php elseif (!$connected): ?>
    <a class="btn btn-primary" href="/app/google/connect">Entrar com Google</a>
    <p class="settings-hint">Você será levado à página oficial do Google para autorizar o acesso.</p>
  <?php else: ?>
    <p><span class="badge" style="background:#dcfce7;color:#166534">Conectado</span> <?= e($sheets['google_email'] ?: 'Conta Google') ?></p>
    <?php if (!empty($sheets['spreadsheet_url'])): ?>
      <p><a class="btn btn-ghost" href="<?= e($sheets['spreadsheet_url']) ?>" target="_blank" rel="noopener">Abrir planilha</a></p>
    <?php endif; ?>
    <?php if (!empty($sheets['last_sync'])): ?>
      <p class="settings-hint">Última sincronização: <?= e(date('d/m/Y H:i', strtotime($sheets['last_sync']))) ?> · <?= ($sheets['last_status']??'')==='ok' ? 'OK' : 'Erro' ?><?php if (!empty($sheets['last_error'])): ?> · <?= e($sheets['last_error']) ?><?php endif; ?></p>
    <?php endif; ?>
    <div class="settings-actions" style="justify-content:flex-start">
      <form method="post" action="/app/configuracoes/sheets/sync">
        <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
        <button class="btn btn-primary">Sincronizar agora</button>
      </form>
      <a class="btn btn-ghost" href="/app/google/connect">Trocar conta Google</a>
      <form method="post" action="/app/google/disconnect" onsubmit="return confirm('Desconectar o Google Sheets? A sincronização automática para até você entrar de novo.')">
        <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
        <button class="btn btn-danger">Desconectar</button>
      </form>
    </div>
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
    <p class="muted" style="margin:6px 0 12px">Fixo. Só o Admin Master pode redefinir o acesso desta conta.</p>
    <div class="settings-warn">
      <label class="label">Senha atual</label>
      <input class="input" type="password" name="current_password" autocomplete="current-password" placeholder="Obrigatória para mudar a senha">
      <div class="grid g2">
        <div>
          <label class="label">Nova senha</label>
          <input class="input" type="password" name="password" autocomplete="new-password" placeholder="Deixe em branco para manter">
        </div>
        <div>
          <label class="label">Confirmar nova senha</label>
          <input class="input" type="password" name="password_confirm" autocomplete="new-password">
        </div>
      </div>
    </div>
    <div class="settings-actions">
      <button class="btn btn-primary">Atualizar conta</button>
    </div>
  </form>
</div>
<?php endif; ?>
