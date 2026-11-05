<?php
if(!defined('ABSPATH')) exit;

class ICD_Visitas_Account {
 const NS='icd-visitas/v1';

 public static function init(){
  add_action('rest_api_init',[__CLASS__,'routes']);
  add_filter('show_admin_bar',[__CLASS__,'show_admin_bar'],99);
 }

 public static function show_admin_bar($show){
  if(!is_user_logged_in()) return $show;
  return current_user_can('manage_options') ? $show : false;
 }

 public static function routes(){
  register_rest_route(self::NS,'/account/profile',[
   'methods'=>'POST','callback'=>[__CLASS__,'update_profile'],
   'permission_callback'=>function(){ return current_user_can('icd_visitas_view_dashboard'); }
  ]);
  register_rest_route(self::NS,'/account/password',[
   'methods'=>'POST','callback'=>[__CLASS__,'change_password'],
   'permission_callback'=>function(){ return current_user_can('icd_visitas_view_dashboard'); }
  ]);
  register_rest_route(self::NS,'/auth/password/request-reset',[
   'methods'=>'POST','callback'=>[__CLASS__,'request_reset'],'permission_callback'=>'__return_true'
  ]);
  register_rest_route(self::NS,'/auth/password/reset',[
   'methods'=>'POST','callback'=>[__CLASS__,'reset_password_front'],'permission_callback'=>'__return_true'
  ]);
 }

 private static function body(WP_REST_Request $request){
  $data=$request->get_json_params();
  return is_array($data)?$data:[];
 }
 private static function fail($message,$status=400){ return new WP_Error('icd_account_error',$message,['status'=>$status]); }

 public static function update_profile(WP_REST_Request $request){
  $data=self::body($request); $uid=get_current_user_id(); $user=get_user_by('id',$uid);
  if(!$user) return self::fail('Usuário não encontrado.',404);
  $name=sanitize_text_field($data['name']??'');
  $email=sanitize_email($data['email']??'');
  $phone=sanitize_text_field($data['phone']??'');
  if(!$name) return self::fail('Informe seu nome.');
  if(!$email || !is_email($email)) return self::fail('Informe um e-mail válido.');
  $exists=email_exists($email); if($exists && (int)$exists!==$uid) return self::fail('Este e-mail já está vinculado a outro usuário.',409);

  $updated=wp_update_user(['ID'=>$uid,'display_name'=>$name,'user_email'=>$email]);
  if(is_wp_error($updated)) return self::fail($updated->get_error_message());
  update_user_meta($uid,'icd_telefone',$phone);

  global $wpdb; $now=current_time('mysql');
  foreach(['pedagogas','mediadores'] as $entity){
   $table=ICD_Visitas_Database::table($entity);
   $wpdb->update($table,['nome'=>$name,'email'=>$email,'telefone'=>$phone,'updated_at'=>$now],['wp_user_id'=>$uid],['%s','%s','%s','%s'],['%d']);
  }
  ICD_Visitas_Helpers::log('usuario',$uid,'perfil_atualizado',['nome'=>$name,'email'=>$email,'telefone'=>$phone]);
  return rest_ensure_response(['success'=>true,'message'=>'Dados atualizados com sucesso.','name'=>$name,'email'=>$email,'phone'=>$phone]);
 }

 public static function change_password(WP_REST_Request $request){
  $data=self::body($request); $uid=get_current_user_id(); $user=get_user_by('id',$uid);
  if(!$user) return self::fail('Usuário não encontrado.',404);
  $current=(string)($data['currentPassword']??''); $new=(string)($data['newPassword']??''); $confirm=(string)($data['confirmPassword']??'');
  if(!$current || !wp_check_password($current,$user->user_pass,$uid)) return self::fail('A senha atual está incorreta.',401);
  if(strlen($new)<8) return self::fail('A nova senha deve ter pelo menos 8 caracteres.');
  if($new!==$confirm) return self::fail('A confirmação da nova senha não confere.');
  if(wp_check_password($new,$user->user_pass,$uid)) return self::fail('A nova senha deve ser diferente da senha atual.');

  wp_set_password($new,$uid);
  wp_set_current_user($uid);
  wp_set_auth_cookie($uid,true,is_ssl());
  do_action('wp_login',$user->user_login,$user);
  ICD_Visitas_Helpers::log('usuario',$uid,'senha_alterada');
  return rest_ensure_response(['success'=>true,'message'=>'Senha alterada com sucesso.']);
 }

 public static function request_reset(WP_REST_Request $request){
  $data=self::body($request); $identity=sanitize_text_field($data['identity']??'');
  if(!$identity) return self::fail('Informe seu usuário ou e-mail.');
  $user=is_email($identity)?get_user_by('email',$identity):get_user_by('login',$identity);
  if($user && user_can($user,'icd_visitas_view_dashboard')){
   $key=get_password_reset_key($user);
   if(!is_wp_error($key)){
    $url=add_query_arg(['action'=>'reset','key'=>$key,'login'=>$user->user_login],home_url('/icd-acesso/'));
    $subject='Redefinição de senha - Instituto Caminho de Davi';
    $message="Olá, {$user->display_name}.\n\nRecebemos uma solicitação para redefinir sua senha do sistema Gestão de Visitas.\n\nAbra o link abaixo para criar uma nova senha:\n{$url}\n\nSe você não solicitou esta alteração, ignore esta mensagem.";
    wp_mail($user->user_email,$subject,$message);
   }
  }
  return rest_ensure_response(['success'=>true,'message'=>'Se o usuário/e-mail estiver cadastrado, enviaremos as instruções de redefinição.']);
 }

 public static function reset_password_front(WP_REST_Request $request){
  $data=self::body($request);
  $login=sanitize_user($data['login']??'',true); $key=sanitize_text_field($data['key']??'');
  $new=(string)($data['newPassword']??''); $confirm=(string)($data['confirmPassword']??'');
  if(!$login || !$key) return self::fail('O link de redefinição é inválido ou está incompleto.');
  if(strlen($new)<8) return self::fail('A nova senha deve ter pelo menos 8 caracteres.');
  if($new!==$confirm) return self::fail('A confirmação da nova senha não confere.');
  $user=check_password_reset_key($key,$login);
  if(is_wp_error($user)) return self::fail('Este link de redefinição expirou ou já foi utilizado.',410);
  if(!user_can($user,'icd_visitas_view_dashboard')) return self::fail('Este usuário não possui acesso ao sistema de visitas.',403);
  reset_password($user,$new);
  wp_set_current_user($user->ID);
  wp_set_auth_cookie($user->ID,true,is_ssl());
  do_action('wp_login',$user->user_login,$user);
  ICD_Visitas_Passkeys::mark_recent_password_auth($user->ID);
  return rest_ensure_response(['success'=>true,'message'=>'Senha redefinida com sucesso.','redirect'=>home_url('/visitas/')]);
 }
}
