<section class="lock-card card">
  <p class="lock-kicker">Primeiro acesso</p>
  <h1>Defina sua senha</h1>
  <p class="subtitle">O painel só abre depois desta etapa. Mínimo de 10 caracteres, com letras e números.</p>
  <form method="post" action="/app/senha" autocomplete="off">
    <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
    <label class="label">Nova senha</label>
    <input class="input" type="password" name="password" minlength="10" required autocomplete="new-password">
    <label class="label" style="margin-top:12px">Confirmar senha</label>
    <input class="input" type="password" name="password_confirm" minlength="10" required autocomplete="new-password">
    <p style="margin:20px 0 0"><button class="btn btn-primary" style="width:100%;min-height:42px">Salvar e entrar</button></p>
  </form>
  <form method="post" action="/logout" style="margin-top:12px">
    <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
    <button class="btn btn-ghost" style="width:100%">Sair</button>
  </form>
</section>
