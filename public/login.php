<?php
if(!defined('ABSPATH')) exit;
$reset_mode=isset($_GET['action']) && sanitize_key(wp_unslash($_GET['action']))==='reset';
$reset_key=$reset_mode ? sanitize_text_field(wp_unslash($_GET['key']??'')) : '';
$reset_login=$reset_mode ? sanitize_user(sanitize_text_field(wp_unslash($_GET['login']??'')),true) : '';
$logged_out=!empty($_GET['loggedout']);
?>
<div class="icd-login-shell">
 <section class="icd-login-card">
  <div class="icd-login-brand">
   <img src="<?php echo esc_url(ICD_VISITAS_URL.'assets/logo.svg');?>" alt="Instituto Caminho de Davi">
   <small>Gestão de Visitas</small>
   <h1><?php echo $reset_mode?'Criar nova senha':'Acessar o sistema';?></h1>
   <?php if($logged_out && !$reset_mode):?><p class="icd-login-success-text">Você saiu do sistema com segurança.</p><?php endif;?>
  </div>

  <div id="icd-login-status" class="icd-login-status" hidden></div>

  <?php if($reset_mode): ?>
   <form method="post" id="icd-password-reset-form" class="icd-login-form">
    <input type="hidden" name="key" value="<?php echo esc_attr($reset_key);?>">
    <input type="hidden" name="login" value="<?php echo esc_attr($reset_login);?>">
    <label>Nova senha<input type="password" name="newPassword" autocomplete="new-password" minlength="8" required></label>
    <label>Confirmar nova senha<input type="password" name="confirmPassword" autocomplete="new-password" minlength="8" required></label>
    <button type="submit" class="icd-submit">Redefinir senha</button>
   </form>
   <a class="icd-forgot-link" href="<?php echo esc_url(ICD_Visitas_Portal::url('acesso'));?>">Voltar ao acesso</a>
  <?php else: ?>
   <button type="button" class="icd-passkey-login-btn" id="icd-passkey-login">
    <span class="icd-passkey-icon" aria-hidden="true">◎</span>
    <span><strong>Entrar com biometria</strong><small>Face ID, impressão digital ou desbloqueio do aparelho</small></span>
   </button>
   <p class="icd-webauthn-note" id="icd-webauthn-note">Verificando compatibilidade deste dispositivo…</p>

   <div class="icd-login-divider"><span>ou</span></div>

   <form method="post" id="icd-password-login-form" class="icd-login-form" autocomplete="on">
    <label>Usuário ou e-mail<input type="text" name="username" autocomplete="username" required></label>
    <label>Senha<input type="password" name="password" autocomplete="current-password" required></label>
    <button type="submit" class="icd-submit">Entrar com senha</button>
   </form>
   <button type="button" class="icd-forgot-link icd-link-button" id="icd-forgot-open">Esqueci minha senha</button>

   <form method="post" id="icd-forgot-password-form" class="icd-login-form icd-forgot-panel" hidden>
    <h3>Redefinir senha</h3>
    <p>Informe seu usuário ou e-mail. As instruções serão enviadas para o e-mail cadastrado.</p>
    <label>Usuário ou e-mail<input type="text" name="identity" autocomplete="username" required></label>
    <button type="submit" class="icd-submit">Enviar instruções</button>
    <button type="button" class="icd-forgot-link icd-link-button" id="icd-forgot-close">Voltar</button>
   </form>
  <?php endif; ?>
 </section>
</div>
