<?php
$communicateItems = $communicateItems ?? [];
if ($communicateItems):
?>
<div class="overlay communicate-overlay" id="communicate-modal" hidden>
  <div class="card overlay-panel communicate-panel" onclick="event.stopPropagation()">
    <div class="communicate-head">
      <div>
        <h2><?= icon('message') ?> Comunicar</h2>
        <p id="communicate-destination"></p>
      </div>
      <button class="btn btn-ghost js-communicate-close communicate-close" type="button" aria-label="Fechar"><?= icon('x') ?></button>
    </div>
    <p class="communicate-notice">A mensagem será enviada ao WhatsApp do destinatário pela UAZAPI configurada nesta empresa.</p>
    <form method="post" action="/app/comunicar/enviar" id="communicate-form">
      <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
      <input type="hidden" name="kind" id="communicate-kind">
      <input type="hidden" name="id" id="communicate-id">
      <input type="hidden" name="selected" id="communicate-selected">
      <input type="hidden" name="back" value="<?= e($communicateBack ?? '/') ?>">

      <label class="label" for="communicate-intro">Mensagem inicial</label>
      <textarea class="textarea" name="intro" id="communicate-intro" rows="3" maxlength="1000"></textarea>

      <label class="label communicate-vars-title">Variáveis do registro</label>
      <p class="settings-hint">O último envio deste menu vira o modelo. Clique em uma opção para retirar ou adicionar à mensagem.</p>
      <div class="communicate-vars" id="communicate-vars"></div>

      <label class="label" for="communicate-outro">Mensagem final</label>
      <textarea class="textarea" name="outro" id="communicate-outro" rows="3" maxlength="1000" placeholder="Ex.: Em breve entraremos em contato."></textarea>

      <label class="label communicate-preview-title">Pré-visualização</label>
      <div class="communicate-preview" id="communicate-preview"></div>

      <div class="communicate-actions">
        <button class="btn btn-primary" type="submit"><?= icon('send') ?> Enviar</button>
      </div>
    </form>
  </div>
</div>
<script type="application/json" id="communicate-data"><?= json_encode($communicateItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endif; ?>
