<?php if(!defined('ABSPATH')) exit; ?>
<div class="icd-front-modal icd-passkey-onboarding" id="icd-passkey-onboarding" aria-hidden="true">
 <div class="icd-front-modal-backdrop" data-icd-passkey-onboarding-close></div>
 <div class="icd-front-modal-dialog icd-passkey-onboarding-dialog" role="dialog" aria-modal="true" aria-labelledby="icd-passkey-onboarding-title">
  <div class="icd-onboarding-icon" aria-hidden="true">◎</div>
  <small class="icd-onboarding-eyebrow">Acesso mais rápido</small>
  <h3 id="icd-passkey-onboarding-title">Ativar login com biometria neste celular?</h3>
  <p>Na próxima vez você poderá entrar usando Face ID, impressão digital ou o desbloqueio do aparelho, sem digitar a senha do WordPress.</p>
  <div class="icd-detected-device">
   <small>Este aparelho será cadastrado como</small>
   <strong id="icd-passkey-detected-device">Detectando aparelho…</strong>
  </div>
  <div id="icd-passkey-onboarding-status" class="icd-login-status" hidden></div>
  <button type="button" class="icd-passkey-register-btn" id="icd-passkey-onboarding-activate">Ativar agora</button>
  <button type="button" class="icd-onboarding-later" data-icd-passkey-onboarding-close>Agora não</button>
  <small class="icd-onboarding-privacy">O Instituto não recebe nem armazena sua biometria.</small>
 </div>
</div>
