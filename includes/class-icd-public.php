<?php
if(!defined('ABSPATH')) exit;
class ICD_Visitas_Public {
 public static function profile_url() { return ICD_Visitas_Portal::url('perfil'); }
 public static function reports_url() { return ICD_Visitas_Portal::url('relatorios'); }
 public static function students_url($id = 0) {
  return ICD_Visitas_Portal::url('alunos', $id ? ['icd_aluno' => $id] : []);
 }
 public static function students() {
  if (!is_user_logged_in()) return self::login_screen();
  if (!ICD_Visitas_Students::can_view()) return '<div class="icd-card">Seu usuário não possui acesso aos alunos.</div>';
  ob_start();
  require ICD_VISITAS_DIR . 'public/alunos.php';
  return ob_get_clean();
 }

 public static function assets($redirect = ''){
  if(wp_style_is('icd-visitas-app','enqueued')||wp_style_is('icd-visitas-app','done')) return;
  wp_enqueue_style('icd-visitas-app',ICD_VISITAS_URL.'public/css/app.css',[],ICD_VISITAS_VERSION);
  wp_enqueue_script('icd-visitas-app',ICD_VISITAS_URL.'public/js/app.js',[],ICD_VISITAS_VERSION,true);
  $redirect = $redirect ?: ICD_Visitas_Portal::url();
  wp_localize_script('icd-visitas-app','ICDVisitasPublic',[
   'ajaxurl'=>ICD_Visitas_Portal::url('ajax'),'calendarNonce'=>wp_create_nonce('icd_school_calendar'),'visitNonce'=>wp_create_nonce('icd_visit_details'),
   'canManage'=>current_user_can('icd_visitas_manage_visitas')?1:0,'restUrl'=>esc_url_raw(rest_url('icd-visitas/v1/')),
   'restNonce'=>is_user_logged_in()?wp_create_nonce('wp_rest'):'','isLoggedIn'=>is_user_logged_in()?1:0,
   'redirect'=>esc_url_raw($redirect),'homeUrl'=>ICD_Visitas_Portal::url(),'https'=>strtolower((string)wp_parse_url(home_url('/'),PHP_URL_SCHEME))==='https'?1:0,
   'hasPasskeys'=>is_user_logged_in()&&ICD_Visitas_Passkeys::current_user_has_passkeys()?1:0,
   'recentPasswordAuth'=>is_user_logged_in()&&ICD_Visitas_Passkeys::has_recent_password_auth()?1:0,
   'showPasskeyPrompt'=>is_user_logged_in()&&ICD_Visitas_Passkeys::should_prompt_onboarding()?1:0
  ]);
 }
 public static function login_screen(){ ob_start(); require ICD_VISITAS_DIR.'public/login.php'; return ob_get_clean(); }
 public static function dashboard(){ if(!is_user_logged_in()) return self::login_screen(); if(!current_user_can('icd_visitas_view_dashboard')) return '<div class="icd-card">Seu usuário não possui acesso ao dashboard.</div>'; ob_start(); require ICD_VISITAS_DIR.'public/dashboard.php'; return ob_get_clean(); }
 public static function nova_visita(){ if(!is_user_logged_in()) return self::login_screen(); if(!current_user_can('icd_visitas_manage_visitas')) return '<div class="icd-card">Sem permissão para registrar visitas.</div>'; ob_start(); require ICD_VISITAS_DIR.'public/nova-visita.php'; return ob_get_clean(); }
 public static function minhas_visitas(){
  if(!is_user_logged_in()) return self::login_screen(); global $wpdb;
  $ped=ICD_Visitas_Helpers::current_pedagoga_id();$med=ICD_Visitas_Helpers::current_mediador_id();$v=ICD_Visitas_Database::table('visitas');$e=ICD_Visitas_Database::table('escolas');$vp=ICD_Visitas_Database::table('visita_pedagogas');$p=ICD_Visitas_Database::table('participantes');
  if($ped)$rows=$wpdb->get_results($wpdb->prepare("SELECT DISTINCT v.*,e.nome escola FROM $v v JOIN $vp x ON x.visita_id=v.id JOIN $e e ON e.id=v.escola_id WHERE x.pedagoga_id=%d ORDER BY v.data_visita DESC,v.id DESC LIMIT 100",$ped));
  elseif($med)$rows=$wpdb->get_results($wpdb->prepare("SELECT DISTINCT v.*,e.nome escola FROM $v v JOIN $p x ON x.visita_id=v.id JOIN $e e ON e.id=v.escola_id WHERE x.mediador_id=%d ORDER BY v.data_visita DESC,v.id DESC LIMIT 100",$med));
  else return '<div class="icd-card">Seu usuário ainda não está vinculado a uma pedagoga ou mediador.</div>';
  ob_start(); echo '<div class="icd-shell"><header class="icd-app-header"><div class="icd-brand"><img src="'.esc_url(ICD_VISITAS_URL.'assets/logo.svg').'" alt="Instituto Caminho de Davi"><div><small>Histórico</small><h2>'.($ped?'Minhas visitas':'Acompanhamentos recebidos').'</h2></div></div></header>';
  if(isset($_GET['deleted'])) echo '<div class="icd-success">Visita excluída.</div>';
  foreach($rows as $r){echo '<article class="icd-card icd-history-card icd-visit-summary-open" data-visit-id="'.(int)$r->id.'" role="button" tabindex="0"><div class="icd-visit-top"><b>'.esc_html($r->escola).'</b><span>'.esc_html(date_i18n('d/m/Y',strtotime($r->data_visita))).'</span></div><small>'.esc_html(ICD_Visitas_Helpers::turno_label($r->turno)).' · '.esc_html(ICD_Visitas_Helpers::status_label($r->status)).'</small>';if($r->observacoes)echo '<p>'.esc_html($r->observacoes).'</p>';
   if($ped && current_user_can('icd_visitas_manage_visitas')){echo '<div class="icd-agenda-actions"><a class="icd-edit-btn" href="'.esc_url(add_query_arg('editar',(int)$r->id,ICD_Visitas_Portal::url('nova-visita'))).'">Editar</a><form method="post" action="'.esc_url(ICD_Visitas_Portal::url('acao')).'" onsubmit="return confirm(\'Deseja realmente excluir esta visita?\');">';wp_nonce_field('icd_delete_visita_'.$r->id);echo '<input type="hidden" name="action" value="icd_delete_visita"><input type="hidden" name="id" value="'.(int)$r->id.'"><input type="hidden" name="redirect_to" value="'.esc_url(ICD_Visitas_Portal::url('minhas-visitas')).'"><button type="submit" class="icd-delete-btn">Excluir</button></form></div>';}
   echo '</article>';}
  if(!$rows)echo '<div class="icd-card">Nenhuma visita encontrada.</div>'; require ICD_VISITAS_DIR.'public/nav.php'; echo '</div>'; return ob_get_clean();
 }
 public static function agenda(){ if(!is_user_logged_in()) return self::login_screen(); if(!current_user_can('icd_visitas_view_dashboard'))return '<div class="icd-card">Seu usuário não possui acesso à agenda.</div>'; ob_start(); require ICD_VISITAS_DIR.'public/agenda.php'; return ob_get_clean(); }
 public static function perfil(){ if(!is_user_logged_in()) return self::login_screen(); if(!current_user_can('icd_visitas_view_dashboard'))return '<div class="icd-card">Seu usuário não possui acesso ao perfil.</div>'; ob_start(); require ICD_VISITAS_DIR.'public/profile.php'; return ob_get_clean(); }
 public static function relatorios(){ if(!is_user_logged_in()) return self::login_screen(); if(!current_user_can('icd_visitas_view_reports'))return '<div class="icd-card">Seu usuário não possui acesso aos relatórios.</div>'; ob_start(); require ICD_VISITAS_DIR.'public/reports.php'; return ob_get_clean(); }
}
