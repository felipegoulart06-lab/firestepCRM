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
    <p class="communicate-notice">A mensagem será enviada ao WhatsApp do destinatário pela conexão configurada nesta empresa.</p>
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

      <div id="communicate-appt-extras" hidden>
        <label class="check-row" style="margin-top:12px">
          <input type="checkbox" name="attach_pdf" value="1" id="communicate-pdf">
          Enviar PDF de confirmação da reserva (embutido na conversa)
        </label>
        <p class="settings-hint" style="margin:6px 0 0">O mesmo comprovante de Agendamentos → PDF, junto com os dados da mensagem.</p>
        <label class="check-row" style="margin-top:12px">
          <input type="checkbox" name="send_buttons" value="1" id="communicate-btns">
          Incluir botões no WhatsApp
        </label>
        <div id="communicate-btn-box" hidden>
          <p class="settings-hint">Até 3 botões: resposta, link, ligar ou copiar.</p>
          <?php for ($i = 0; $i < 3; $i++): ?>
          <div class="communicate-btn-row">
            <input class="input" name="btn_text[]" maxlength="20" placeholder="Texto" data-btn-text>
            <select class="select" name="btn_type[]" data-btn-type>
              <option value="REPLY">Resposta</option>
              <option value="URL">Link</option>
              <option value="CALL">Ligar</option>
              <option value="COPY">Copiar</option>
            </select>
            <input class="input" name="btn_value[]" maxlength="500" placeholder="Link, telefone ou texto" data-btn-value>
          </div>
          <?php endfor; ?>
        </div>
      </div>

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
