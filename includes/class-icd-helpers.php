<?php
if(!defined('ABSPATH')) exit;
class ICD_Visitas_Helpers {
 public static function id_from($source, $key) {
  return filter_var($source[$key] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
 }
 public static function turno_label($v){ return ['manha'=>'Manhã','tarde'=>'Tarde','noite'=>'Noite','integral'=>'Integral'][$v]??ucfirst($v); }
 public static function status_label($v){ return ['programada'=>'Programada','confirmada'=>'Confirmada','realizada'=>'Realizada','cancelada'=>'Cancelada','reagendada'=>'Reagendada','nao_realizada'=>'Não realizada'][$v]??ucfirst(str_replace('_',' ',$v)); }
 public static function dia_label($n){ return [1=>'Segunda',2=>'Terça',3=>'Quarta',4=>'Quinta',5=>'Sexta',6=>'Sábado',7=>'Domingo'][(int)$n]??''; }
 public static function redirect($page,$extra=[]){ $url=add_query_arg($extra,admin_url('admin.php?page='.$page)); wp_safe_redirect($url); exit; }
 public static function log($entidade,$id,$acao,$new=[],$old=[]){ global $wpdb; $wpdb->insert(ICD_Visitas_Database::table('logs'),['usuario_id'=>get_current_user_id(),'entidade'=>$entidade,'entidade_id'=>$id,'acao'=>$acao,'dados_anteriores'=>$old?wp_json_encode($old):null,'dados_novos'=>$new?wp_json_encode($new):null,'ip'=>sanitize_text_field($_SERVER['REMOTE_ADDR']??'')]); }
 public static function current_mediador_id(){ global $wpdb; return (int)$wpdb->get_var($wpdb->prepare('SELECT id FROM '.ICD_Visitas_Database::table('mediadores').' WHERE wp_user_id=%d AND ativo=1',get_current_user_id())); }
 public static function current_pedagoga_id(){ global $wpdb; return (int)$wpdb->get_var($wpdb->prepare('SELECT id FROM '.ICD_Visitas_Database::table('pedagogas').' WHERE wp_user_id=%d AND ativo=1',get_current_user_id())); }
}
