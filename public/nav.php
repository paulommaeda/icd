<?php
$show_students = ICD_Visitas_Students::can_view();
$show_register = current_user_can('icd_visitas_manage_visitas');
?>
<nav class="icd-bottom-nav<?php if ($show_students && $show_register) echo ' icd-bottom-nav-six'; elseif (!$show_students && !$show_register) echo ' icd-bottom-nav-four'; ?>">
 <a href="<?php echo esc_url(ICD_Visitas_Portal::url('visitas'));?>"><span>⌂</span>Dashboard</a>
 <?php if ($show_register): ?><a href="<?php echo esc_url(ICD_Visitas_Portal::url('nova-visita'));?>"><span>＋</span>Registrar</a><?php endif; ?>
 <a href="<?php echo esc_url(ICD_Visitas_Portal::url('agenda'));?>"><span>▣</span>Agenda</a>
 <a href="<?php echo esc_url(ICD_Visitas_Portal::url('minhas-visitas'));?>"><span>≡</span>Histórico</a>
 <?php if ($show_students): ?><a href="<?php echo esc_url(ICD_Visitas_Public::students_url()); ?>"><span aria-hidden="true">◎</span>Alunos</a><?php endif; ?>
 <a href="<?php echo esc_url(ICD_Visitas_Public::profile_url());?>"><span>◉</span>Perfil</a>
</nav>

<div class="icd-front-modal icd-visit-summary-modal" id="icd-visit-summary-modal" aria-hidden="true">
 <div class="icd-front-modal-backdrop" data-icd-visit-close></div>
 <div class="icd-front-modal-dialog icd-visit-summary-dialog" role="dialog" aria-modal="true" aria-labelledby="icd-visit-summary-title">
  <button type="button" class="icd-front-modal-close" data-icd-visit-close aria-label="Fechar">×</button>
  <div id="icd-visit-summary-content"><p class="icd-empty">Carregando visita...</p></div>
 </div>
</div>

<?php if(is_user_logged_in() && ICD_Visitas_Passkeys::should_prompt_onboarding()) require ICD_VISITAS_DIR.'public/passkey-onboarding.php'; ?>
