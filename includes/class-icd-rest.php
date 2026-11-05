<?php
if(!defined('ABSPATH')) exit;
class ICD_Visitas_REST {
 public static function init(){ add_action('rest_api_init',[__CLASS__,'routes']); }
 public static function routes(){
  register_rest_route('icd-visitas/v1','/dashboard',['methods'=>'GET','callback'=>[__CLASS__,'dashboard'],'permission_callback'=>function(){return current_user_can('icd_visitas_view_dashboard');}]);
  register_rest_route('icd-visitas/v1','/escolas/(?P<id>\d+)/mediadores',['methods'=>'GET','callback'=>[__CLASS__,'mediadores_escola'],'permission_callback'=>function(){return is_user_logged_in();}]);
  register_rest_route('icd-visitas/v1','/pedagogas',['methods'=>'GET','callback'=>[__CLASS__,'pedagogas'],'permission_callback'=>function(){return is_user_logged_in();}]);
 }
 public static function dashboard($r){ global $wpdb; $v=ICD_Visitas_Database::table('visitas'); $e=ICD_Visitas_Database::table('escolas'); return rest_ensure_response(['visitas'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM $v WHERE status='realizada'"),'escolas'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM $e WHERE ativo=1"),'por_mes'=>$wpdb->get_results("SELECT DATE_FORMAT(data_visita,'%Y-%m') mes,COUNT(*) total FROM $v WHERE status='realizada' GROUP BY mes ORDER BY mes",ARRAY_A)]); }
 public static function mediadores_escola($r){ global $wpdb; $v=ICD_Visitas_Database::table('vinculos'); $m=ICD_Visitas_Database::table('mediadores'); return rest_ensure_response($wpdb->get_results($wpdb->prepare("SELECT m.id,m.nome,m.cargo FROM $v v JOIN $m m ON m.id=v.mediador_id WHERE v.escola_id=%d AND v.ativo=1 AND m.ativo=1 ORDER BY m.nome",absint($r['id'])),ARRAY_A)); }
 public static function pedagogas(){ global $wpdb;$t=ICD_Visitas_Database::table('pedagogas');return rest_ensure_response($wpdb->get_results("SELECT id,nome,email,telefone FROM $t WHERE ativo=1 ORDER BY nome",ARRAY_A)); }
}
