<?php
if(!defined('ABSPATH')) exit;
$user=wp_get_current_user();
$phone=(string)get_user_meta($user->ID,'icd_telefone',true);
if(!$phone){
 global $wpdb;
 $phone=(string)$wpdb->get_var($wpdb->prepare("SELECT telefone FROM ".ICD_Visitas_Database::table('pedagogas')." WHERE wp_user_id=%d LIMIT 1",$user->ID));
 if(!$phone) $phone=(string)$wpdb->get_var($wpdb->prepare("SELECT telefone FROM ".ICD_Visitas_Database::table('mediadores')." WHERE wp_user_id=%d LIMIT 1",$user->ID));
}
$passkeys=ICD_Visitas_Passkeys::current_user_passkeys();
?>
<div class="icd-shell icd-profile-page">
 <header class="icd-app-header">
  <div class="icd-brand"><img src="<?php echo esc_url(ICD_VISITAS_URL.'assets/logo.svg');?>" alt="Instituto Caminho de Davi"><div><small>Minha conta</small><h2>Perfil</h2></div></div>
 </header>

 <section class="icd-card">
  <h3>Meus dados</h3>
  <div id="icd-profile-status" class="icd-login-status" hidden></div>
  <form method="post" id="icd-profile-form" class="icd-mobile-form">
   <label>Usuário<input type="text" value="<?php echo esc_attr($user->user_login);?>" readonly disabled></label>
   <label>Nome<input type="text" name="name" value="<?php echo esc_attr($user->display_name);?>" autocomplete="name" required></label>
   <label>E-mail<input type="email" name="email" value="<?php echo esc_attr($user->user_email);?>" autocomplete="email" required></label>
   <label>Telefone<input type="tel" name="phone" value="<?php echo esc_attr($phone);?>" autocomplete="tel"></label>
   <button type="submit" class="icd-submit">Salvar meus dados</button>
  </form>
 </section>

 <section class="icd-card">
  <h3>Alterar senha</h3>
  <div id="icd-change-password-status" class="icd-login-status" hidden></div>
  <form method="post" id="icd-change-password-form" class="icd-mobile-form">
   <label>Senha atual<input type="password" name="currentPassword" autocomplete="current-password" required></label>
   <label>Nova senha<input type="password" name="newPassword" autocomplete="new-password" minlength="8" required></label>
   <label>Confirmar nova senha<input type="password" name="confirmPassword" autocomplete="new-password" minlength="8" required></label>
   <button type="submit" class="icd-submit">Alterar senha</button>
  </form>
 </section>

 <section class="icd-card icd-security-card" id="icd-profile-passkeys">
  <h3>Login por biometria</h3>
  <p class="icd-security-intro">Cadastre este aparelho para entrar usando Face ID, impressão digital ou o desbloqueio do dispositivo.</p>
  <div id="icd-passkey-status" class="icd-login-status" hidden></div>
  <button type="button" class="icd-passkey-register-btn" id="icd-profile-passkey-activate">Ativar biometria neste aparelho</button>
  <div class="icd-passkey-confirm" id="icd-passkey-confirm" hidden>
   <label>Por segurança, confirme sua senha atual<input type="password" id="icd-passkey-confirm-password" autocomplete="current-password"></label>
   <button type="button" class="icd-submit" id="icd-passkey-confirm-activate">Confirmar e ativar</button>
  </div>
  <div class="icd-passkey-list" id="icd-passkey-list">
   <?php if($passkeys): foreach($passkeys as $pk): ?>
    <div class="icd-passkey-item" data-passkey-id="<?php echo (int)$pk->id;?>">
     <div><b><?php echo esc_html($pk->device_name);?></b><small>Cadastrado em <?php echo esc_html(date_i18n('d/m/Y',strtotime($pk->created_at)));?><?php if($pk->last_used_at):?> · último uso <?php echo esc_html(date_i18n('d/m/Y',strtotime($pk->last_used_at)));?><?php endif;?></small></div>
     <button type="button" class="icd-passkey-remove" data-passkey-delete="<?php echo (int)$pk->id;?>">Remover</button>
    </div>
   <?php endforeach; else: ?>
    <p class="icd-empty" data-no-passkeys>Nenhum aparelho cadastrado.</p>
   <?php endif; ?>
  </div>
 </section>

 <section class="icd-card icd-account-actions">
  <a class="icd-logout-btn" href="<?php echo esc_url(wp_logout_url());?>">Sair do sistema</a>
 </section>

 <?php if (ICD_Visitas_Portal::is_wordpress_admin()): ?>
 <p class="icd-card"><a href="<?php echo esc_url(admin_url()); ?>">Administração do WordPress</a></p>
 <?php endif; ?>
 <?php require ICD_VISITAS_DIR.'public/nav.php';?>
</div>
