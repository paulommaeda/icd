<?php
if(!defined('ABSPATH')) exit;
class ICD_Visitas_Public {
 private static $forced_redirect='';
 private static $app_paths=['/visitas/','/nova-visita/','/agenda/','/minhas-visitas/','/relatorios-visitas/','/perfil/','/perfil-visitas/','/icd-acesso/'];

 public static function init(){
  add_action('wp_enqueue_scripts',[__CLASS__,'assets']);
  add_action('init',[__CLASS__,'ensure_profile_page'],20);
  add_action('init',[__CLASS__,'ensure_reports_page'],21);
  add_action('init',[__CLASS__,'ensure_students_page'],22);
  add_action('template_redirect',[__CLASS__,'handle_front_logout'],-200);
  add_action('template_redirect',[__CLASS__,'public_login_gate'],-100);
  add_action('template_redirect',[__CLASS__,'nocache'],0);
  add_shortcode('icd_dashboard',[__CLASS__,'dashboard']);
  add_shortcode('icd_nova_visita',[__CLASS__,'nova_visita']);
  add_shortcode('icd_minhas_visitas',[__CLASS__,'minhas_visitas']);
  add_shortcode('icd_agenda',[__CLASS__,'agenda']);
  add_shortcode('icd_perfil',[__CLASS__,'perfil']);
  add_shortcode('icd_relatorios',[__CLASS__,'relatorios']);
  add_shortcode('icd_alunos',[__CLASS__,'students']);
  add_filter('login_url',[__CLASS__,'filter_login_url'],10,3);
  add_filter('logout_url',[__CLASS__,'filter_logout_url'],10,2);
  add_action('login_init',[__CLASS__,'redirect_wp_login_for_app'],-100);
  add_action('wp_logout',[__CLASS__,'force_app_logout_redirect'],99,1);
 }

 public static function ensure_profile_page(){
  $id=(int)get_option('icd_visitas_profile_page_id');
  if($id && get_post_status($id)) return;
  $page=get_page_by_path('perfil');
  if($page && has_shortcode((string)$page->post_content,'icd_perfil')){
   update_option('icd_visitas_profile_page_id',(int)$page->ID); return;
  }
  $slug=$page?'perfil-visitas':'perfil';
  $existing=get_page_by_path($slug);
  if($existing && has_shortcode((string)$existing->post_content,'icd_perfil')){
   update_option('icd_visitas_profile_page_id',(int)$existing->ID); return;
  }
  $new=wp_insert_post(['post_title'=>'Meu perfil','post_name'=>$slug,'post_content'=>'[icd_perfil]','post_status'=>'publish','post_type'=>'page'],true);
  if(!is_wp_error($new) && $new) update_option('icd_visitas_profile_page_id',(int)$new);
 }

 public static function profile_url(){
  $id=(int)get_option('icd_visitas_profile_page_id');
  $url=$id?get_permalink($id):'';
  return $url?:home_url('/perfil/');
 }

 public static function ensure_reports_page(){
  $id=(int)get_option('icd_visitas_reports_page_id');
  if($id && get_post_status($id)) return;
  $slug='relatorios-visitas';
  $page=get_page_by_path($slug);
  if($page && has_shortcode((string)$page->post_content,'icd_relatorios')){ update_option('icd_visitas_reports_page_id',(int)$page->ID); return; }
  $new=wp_insert_post(['post_title'=>'Relatórios de visitas','post_name'=>$slug,'post_content'=>'[icd_relatorios]','post_status'=>'publish','post_type'=>'page'],true);
  if(!is_wp_error($new) && $new) update_option('icd_visitas_reports_page_id',(int)$new);
 }
 public static function reports_url(){
  $id=(int)get_option('icd_visitas_reports_page_id');
  $url=$id?get_permalink($id):'';
  return $url?:home_url('/relatorios-visitas/');
 }

 public static function ensure_students_page() {
  $id = (int) get_option('icd_visitas_students_page_id');
  if ($id && get_post_status($id) === 'publish') return;
  $page = get_page_by_path('icd-alunos');
  if ($page && $page->post_status === 'publish' && has_shortcode($page->post_content, 'icd_alunos')) {
   update_option('icd_visitas_students_page_id', (int) $page->ID);
   return;
  }
  $id = wp_insert_post([
   'post_title' => 'Alunos ICD', 'post_name' => 'icd-alunos', 'post_content' => '[icd_alunos]',
   'post_status' => 'publish', 'post_type' => 'page',
  ], true);
  if ($id && !is_wp_error($id)) update_option('icd_visitas_students_page_id', (int) $id);
 }

 public static function students_url($id = 0) {
  $page_id = (int) get_option('icd_visitas_students_page_id');
  $url = ($page_id ? get_permalink($page_id) : false) ?: home_url('/icd-alunos/');
  return $id ? add_query_arg('icd_aluno', $id, $url) : $url;
 }

 public static function students() {
  if (!is_user_logged_in()) return self::login_screen();
  if (!ICD_Visitas_Students::can_view()) return '<div class="icd-card">Seu usuário não possui acesso aos alunos.</div>';
  self::assets();
  ob_start();
  require ICD_VISITAS_DIR . 'public/alunos.php';
  return ob_get_clean();
 }

 private static function request_path(){
  $uri=isset($_SERVER['REQUEST_URI'])?(string)$_SERVER['REQUEST_URI']:'/';
  $path=(string)wp_parse_url($uri,PHP_URL_PATH);
  if($path==='') $path='/';
  $home_path=(string)wp_parse_url(home_url('/'),PHP_URL_PATH);
  if($home_path && $home_path!=='/' && strpos($path,$home_path)===0){
   $path=substr($path,strlen(rtrim($home_path,'/')));
   if($path==='') $path='/';
  }
  if($path[0]!=='/') $path='/'.$path;
  return trailingslashit($path);
 }

 private static function is_app_path($path=''){
  $path=$path?:self::request_path();
  foreach(self::$app_paths as $allowed){ if($path===trailingslashit($allowed)) return true; }
  return false;
 }


 private static function is_shortcode_app_page(){
  if(!is_singular()) return false;
  global $post; if(!$post || empty($post->post_content)) return false;
  foreach(['icd_dashboard','icd_nova_visita','icd_minhas_visitas','icd_agenda','icd_perfil','icd_relatorios','icd_alunos'] as $sc){
   if(has_shortcode($post->post_content,$sc)) return true;
  }
  return false;
 }

 private static function current_request_url(){
  $scheme=is_ssl()?'https':'http';
  $host=isset($_SERVER['HTTP_HOST'])?sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])):(string)wp_parse_url(home_url('/'),PHP_URL_HOST);
  $uri=isset($_SERVER['REQUEST_URI'])?wp_unslash($_SERVER['REQUEST_URI']):'/visitas/';
  return esc_url_raw($scheme.'://'.$host.$uri);
 }

 public static function public_login_gate(){
  if(is_user_logged_in()) return;
  $path=self::request_path();
  if(!self::is_app_path($path) && !self::is_shortcode_app_page()) return;

  if(!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE',true);
  nocache_headers();
  status_header(200);

  // Mantém o destino original para que, após login por Passkey/senha,
  // o usuário volte exatamente à tela do PWA que tentou abrir.
  if($path==='/icd-acesso/'){
   $requested=isset($_GET['redirect_to'])?esc_url_raw(wp_unslash($_GET['redirect_to'])):'';
   self::$forced_redirect=$requested?:home_url('/visitas/');
  } else {
   self::$forced_redirect=self::current_request_url();
  }

  self::assets();
  self::render_standalone_login();
  exit;
 }

 public static function filter_login_url($login_url,$redirect,$force_reauth){
  if(is_user_logged_in()) return $login_url;
  if(!$redirect) return $login_url;
  $path=(string)wp_parse_url($redirect,PHP_URL_PATH);
  $home_path=(string)wp_parse_url(home_url('/'),PHP_URL_PATH);
  if($home_path && $home_path!=='/' && strpos($path,$home_path)===0){
   $path=substr($path,strlen(rtrim($home_path,'/')));
   if($path==='') $path='/';
  }
  $path=trailingslashit('/'.ltrim($path,'/'));
  if(!self::is_app_path($path) || $path==='/icd-acesso/') return $login_url;
  return add_query_arg('redirect_to',$redirect,home_url('/icd-acesso/'));
 }

 public static function filter_logout_url($logout_url,$redirect){
  if(!is_user_logged_in() || !current_user_can('icd_visitas_view_dashboard')) return $logout_url;
  return self::front_logout_url();
 }

 public static function front_logout_url(){
  return wp_nonce_url(add_query_arg('icd_logout','1',home_url('/icd-acesso/')),'icd_front_logout');
 }

 public static function handle_front_logout(){
  if(empty($_GET['icd_logout'])) return;
  if(!is_user_logged_in()){
   wp_safe_redirect(home_url('/icd-acesso/?loggedout=1')); exit;
  }
  $nonce=isset($_GET['_wpnonce'])?sanitize_text_field(wp_unslash($_GET['_wpnonce'])):'';
  if(!$nonce || !wp_verify_nonce($nonce,'icd_front_logout')){
   wp_die('Não foi possível validar a solicitação de saída.');
  }
  wp_logout();
  wp_safe_redirect(home_url('/icd-acesso/?loggedout=1'));
  exit;
 }

 public static function force_app_logout_redirect($user_id){
  if(!$user_id || !user_can($user_id,'icd_visitas_view_dashboard')) return;
  if((defined('REST_REQUEST')&&REST_REQUEST) || wp_doing_ajax()) return;
  $action=isset($_REQUEST['action'])?sanitize_key(wp_unslash($_REQUEST['action'])):'';
  $uri=isset($_SERVER['REQUEST_URI'])?(string)$_SERVER['REQUEST_URI']:'';
  // Atua somente no logout interativo do wp-login.php, sem afetar logouts programáticos.
  if($action!=='logout' || strpos($uri,'wp-login.php')===false) return;
  $target=home_url('/icd-acesso/?loggedout=1');
  if(!empty($_REQUEST['redirect_to'])) $target=wp_validate_redirect(wp_unslash($_REQUEST['redirect_to']),$target);
  wp_safe_redirect($target);
  exit;
 }


 public static function redirect_wp_login_for_app(){
  if(is_user_logged_in()) return;
  $redirect=isset($_REQUEST['redirect_to'])?esc_url_raw(wp_unslash($_REQUEST['redirect_to'])):'';
  if(!$redirect && !empty($_SERVER['HTTP_REFERER'])) $redirect=esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER']));
  if(!$redirect) return;
  $path=(string)wp_parse_url($redirect,PHP_URL_PATH);
  $home_path=(string)wp_parse_url(home_url('/'),PHP_URL_PATH);
  if($home_path && $home_path!=='/' && strpos($path,$home_path)===0){
   $path=substr($path,strlen(rtrim($home_path,'/')));
   if($path==='') $path='/';
  }
  $path=trailingslashit('/'.ltrim($path,'/'));
  if(!self::is_app_path($path) || $path==='/icd-acesso/') return;
  wp_safe_redirect(add_query_arg('redirect_to',$redirect,home_url('/icd-acesso/')));
  exit;
 }

 private static function render_standalone_login(){
  ?><!doctype html>
  <html <?php language_attributes(); ?>>
  <head>
   <meta charset="<?php bloginfo('charset'); ?>">
   <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
   <meta name="robots" content="noindex,nofollow">
   <meta name="theme-color" content="#19DB4B">
   <title><?php echo esc_html(get_bloginfo('name').' — Gestão de Visitas'); ?></title>
   <?php wp_head(); ?>
  </head>
  <body class="icd-pwa-login-page">
   <?php echo self::login_screen(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
   <?php wp_footer(); ?>
  </body>
  </html><?php
 }

 public static function nocache(){
  if (!self::is_app_path() && !self::is_shortcode_app_page()) return;
  if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
  nocache_headers();
  header('Cache-Control: private, no-store, max-age=0');
 }
 public static function assets(){
  if(wp_style_is('icd-visitas-app','enqueued')||wp_style_is('icd-visitas-app','done')) return;
  wp_enqueue_style('icd-visitas-app',ICD_VISITAS_URL.'public/css/app.css',[],ICD_VISITAS_VERSION);
  wp_enqueue_script('icd-visitas-app',ICD_VISITAS_URL.'public/js/app.js',[],ICD_VISITAS_VERSION,true);
  $redirect=self::$forced_redirect?:((is_singular()&&get_permalink())?get_permalink():home_url('/visitas/'));
  wp_localize_script('icd-visitas-app','ICDVisitasPublic',[
   'ajaxurl'=>admin_url('admin-ajax.php'),'calendarNonce'=>wp_create_nonce('icd_school_calendar'),'visitNonce'=>wp_create_nonce('icd_visit_details'),
   'canManage'=>current_user_can('icd_visitas_manage_visitas')?1:0,'restUrl'=>esc_url_raw(rest_url('icd-visitas/v1/')),
   'restNonce'=>is_user_logged_in()?wp_create_nonce('wp_rest'):'','isLoggedIn'=>is_user_logged_in()?1:0,
   'redirect'=>esc_url_raw($redirect),'https'=>strtolower((string)wp_parse_url(home_url('/'),PHP_URL_SCHEME))==='https'?1:0,
   'hasPasskeys'=>is_user_logged_in()&&ICD_Visitas_Passkeys::current_user_has_passkeys()?1:0,
   'recentPasswordAuth'=>is_user_logged_in()&&ICD_Visitas_Passkeys::has_recent_password_auth()?1:0,
   'showPasskeyPrompt'=>is_user_logged_in()&&ICD_Visitas_Passkeys::should_prompt_onboarding()?1:0
  ]);
 }
 private static function login_screen(){ ob_start(); require ICD_VISITAS_DIR.'public/login.php'; return ob_get_clean(); }
 private static function guard(){ return is_user_logged_in()&&current_user_can('icd_visitas_view_dashboard'); }
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
   if($ped && current_user_can('icd_visitas_manage_visitas')){echo '<div class="icd-agenda-actions"><a class="icd-edit-btn" href="'.esc_url(add_query_arg('editar',(int)$r->id,site_url('/nova-visita/'))).'">Editar</a><form method="post" action="'.esc_url(admin_url('admin-post.php')).'" onsubmit="return confirm(\'Deseja realmente excluir esta visita?\');">';wp_nonce_field('icd_delete_visita_'.$r->id);echo '<input type="hidden" name="action" value="icd_delete_visita"><input type="hidden" name="id" value="'.(int)$r->id.'"><input type="hidden" name="redirect_to" value="'.esc_url(get_permalink()).'"><button type="submit" class="icd-delete-btn">Excluir</button></form></div>';}
   echo '</article>';}
  if(!$rows)echo '<div class="icd-card">Nenhuma visita encontrada.</div>'; require ICD_VISITAS_DIR.'public/nav.php'; echo '</div>'; return ob_get_clean();
 }
 public static function agenda(){ if(!is_user_logged_in()) return self::login_screen(); if(!current_user_can('icd_visitas_view_dashboard'))return '<div class="icd-card">Seu usuário não possui acesso à agenda.</div>'; ob_start(); require ICD_VISITAS_DIR.'public/agenda.php'; return ob_get_clean(); }
 public static function perfil(){ if(!is_user_logged_in()) return self::login_screen(); if(!current_user_can('icd_visitas_view_dashboard'))return '<div class="icd-card">Seu usuário não possui acesso ao perfil.</div>'; ob_start(); require ICD_VISITAS_DIR.'public/profile.php'; return ob_get_clean(); }
 public static function relatorios(){ if(!is_user_logged_in()) return self::login_screen(); if(!current_user_can('icd_visitas_view_reports'))return '<div class="icd-card">Seu usuário não possui acesso aos relatórios.</div>'; ob_start(); require ICD_VISITAS_DIR.'public/reports.php'; return ob_get_clean(); }
}
