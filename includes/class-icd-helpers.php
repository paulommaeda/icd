<?php
if(!defined('ABSPATH')) exit;
class ICD_Visitas_Helpers {
 public static function id_from($source, $key) {
  return filter_var($source[$key] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
 }
 public static function valid_date($value) {
  $value = is_string($value) ? trim(wp_unslash($value)) : '';
  if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $date) || (int) $date[1] < 1000) return '';
  return checkdate((int) $date[2], (int) $date[3], (int) $date[1]) ? $value : '';
 }
 public static function visit_mediator_map($editing = null, $participants = []) {
  global $wpdb;
  $vinculos = ICD_Visitas_Database::table('vinculos');
  $alunos = ICD_Visitas_Database::table('alunos');
  $map = [];
  foreach ($wpdb->get_results("SELECT escola_id, mediador_id FROM $vinculos WHERE ativo = 1
      UNION SELECT escola_id, mediador_id FROM $alunos") as $row) {
   $map[(int) $row->mediador_id][] = (int) $row->escola_id;
  }
  if ($editing) {
   foreach ($participants as $mediador_id) $map[$mediador_id][] = (int) $editing->escola_id;
  }
  return $map;
 }
 public static function turno_label($v){ return ['manha'=>'Manhã','tarde'=>'Tarde','noite'=>'Noite','integral'=>'Integral'][$v]??ucfirst($v); }
 public static function status_label($v){ return ['programada'=>'Programada','confirmada'=>'Confirmada','realizada'=>'Realizada','cancelada'=>'Cancelada','reagendada'=>'Reagendada','nao_realizada'=>'Não realizada'][$v]??ucfirst(str_replace('_',' ',$v)); }
 public static function dia_label($n){ return [1=>'Segunda',2=>'Terça',3=>'Quarta',4=>'Quinta',5=>'Sexta',6=>'Sábado',7=>'Domingo'][(int)$n]??''; }
 public static function page_url($page, $args = []) {
  return is_admin() ? add_query_arg(['page' => $page] + $args, admin_url('admin.php'))
   : ICD_Visitas_Portal::url('gestao', ['icd_secao' => $page] + $args);
 }
 public static function page_input($page) {
  if (!is_admin()) ICD_Visitas_Portal::route_input('gestao');
  $field = is_admin() ? 'page' : 'icd_secao';
  echo '<input type="hidden" name="' . esc_attr($field) . '" value="' . esc_attr($page) . '">';
 }
 public static function action_url() {
  return is_admin() ? admin_url('admin-post.php') : ICD_Visitas_Portal::url('acao');
 }
 public static function redirect($page, $args = []) {
  wp_safe_redirect(self::page_url($page, $args), 303);
  exit;
 }
 public static function log($entidade,$id,$acao,$new=[],$old=[]){ global $wpdb; $wpdb->insert(ICD_Visitas_Database::table('logs'),['usuario_id'=>get_current_user_id(),'entidade'=>$entidade,'entidade_id'=>$id,'acao'=>$acao,'dados_anteriores'=>$old?wp_json_encode($old):null,'dados_novos'=>$new?wp_json_encode($new):null,'ip'=>sanitize_text_field($_SERVER['REMOTE_ADDR']??'')]); }
 public static function current_mediador_id(){ global $wpdb; return (int)$wpdb->get_var($wpdb->prepare('SELECT id FROM '.ICD_Visitas_Database::table('mediadores').' WHERE wp_user_id=%d AND ativo=1',get_current_user_id())); }
 public static function current_pedagoga_id(){ global $wpdb; return (int)$wpdb->get_var($wpdb->prepare('SELECT id FROM '.ICD_Visitas_Database::table('pedagogas').' WHERE wp_user_id=%d AND ativo=1',get_current_user_id())); }
}
