<?php
if(!defined('ABSPATH')) exit;
class ICD_Visitas_Admin {
 public static function init(){
  add_action('admin_menu',[__CLASS__,'menu']); add_action('admin_enqueue_scripts',[__CLASS__,'assets']);
  foreach(['municipio','escola','mediador','pedagoga','vinculo','agenda','visita','responsavel'] as $a) add_action('admin_post_icd_save_'.$a,[__CLASS__,'save_'.$a]);
  add_action('admin_post_icd_import_preview',[__CLASS__,'import_preview']); add_action('admin_post_icd_import_confirm',[__CLASS__,'import_confirm']);
  add_action('admin_post_icd_smart_import_upload',[__CLASS__,'smart_import_upload']); add_action('admin_post_icd_smart_import_type',[__CLASS__,'smart_import_type']); add_action('admin_post_icd_smart_import_map',[__CLASS__,'smart_import_map']); add_action('admin_post_icd_smart_import_confirm',[__CLASS__,'smart_import_confirm']); add_action('admin_post_icd_smart_import_reset',[__CLASS__,'smart_import_reset']);
  add_action('admin_post_icd_complete_visita',[__CLASS__,'complete_visita']); add_action('admin_post_icd_delete_visita',[__CLASS__,'delete_visita']);
  add_action('admin_post_icd_transfer_mediador',[__CLASS__,'transfer_mediador']);
  add_action('wp_ajax_icd_school_calendar',[__CLASS__,'school_calendar']);
  add_action('wp_ajax_icd_visit_details',[__CLASS__,'visit_details']);
 }
 public static function menu(){
  $cap='icd_visitas_view_dashboard';
  add_menu_page('Instituto Caminho de Davi - Visitas','ICD Visitas',$cap,'icd-visitas',[__CLASS__,'dashboard'],'dashicons-location-alt',26);
  add_submenu_page('icd-visitas','Dashboard','Dashboard',$cap,'icd-visitas',[__CLASS__,'dashboard']);
  add_submenu_page('icd-visitas','Visitas','Visitas','icd_visitas_manage_visitas','icd-visitas-registros',[__CLASS__,'visitas']);
  add_submenu_page('icd-visitas','Agenda dos mediadores','Agenda dos mediadores','icd_visitas_manage_cadastros','icd-visitas-agenda',[__CLASS__,'agenda']);
  add_submenu_page('icd-visitas','Escolas','Escolas','icd_visitas_manage_cadastros','icd-visitas-escolas',[__CLASS__,'escolas']);
  add_submenu_page('icd-visitas','Mediadores','Mediadores','icd_visitas_manage_cadastros','icd-visitas-mediadores',[__CLASS__,'mediadores']);
  add_submenu_page('icd-visitas','Alunos','Alunos','icd_visitas_view_all_students','icd-visitas-alunos',[__CLASS__,'alunos']);
  add_submenu_page('icd-visitas','Pedagogas','Pedagogas','icd_visitas_manage_cadastros','icd-visitas-pedagogas',[__CLASS__,'pedagogas']);
  add_submenu_page('icd-visitas','Vínculos','Vínculos','icd_visitas_manage_cadastros','icd-visitas-vinculos',[__CLASS__,'vinculos']);
  add_submenu_page('icd-visitas','Responsáveis','Responsáveis','icd_visitas_manage_cadastros','icd-visitas-responsaveis',[__CLASS__,'responsaveis']);
  add_submenu_page('icd-visitas','Municípios','Municípios','icd_visitas_manage_cadastros','icd-visitas-municipios',[__CLASS__,'municipios']);
  add_submenu_page('icd-visitas','Importação','Importação','icd_visitas_manage_settings','icd-visitas-importacao',[__CLASS__,'importacao']);
 }
 public static function assets($hook){ if(strpos($hook,'icd-visitas')===false) return; wp_enqueue_style('icd-visitas-admin',ICD_VISITAS_URL.'admin/css/admin.css',[],ICD_VISITAS_VERSION); wp_enqueue_script('icd-visitas-admin',ICD_VISITAS_URL.'admin/js/admin.js',[],ICD_VISITAS_VERSION,true); wp_localize_script('icd-visitas-admin','ICDVisitasAdmin',['ajaxurl'=>admin_url('admin-ajax.php'),'calendarNonce'=>wp_create_nonce('icd_school_calendar')]); }
 public static function dashboard(){ require ICD_VISITAS_DIR.'admin/pages/dashboard.php'; }
 public static function visitas(){ require ICD_VISITAS_DIR.'admin/pages/visitas.php'; }
 public static function agenda(){ require ICD_VISITAS_DIR.'admin/pages/agenda.php'; }
 public static function escolas(){ require ICD_VISITAS_DIR.'admin/pages/escolas.php'; }
 public static function mediadores(){ require ICD_VISITAS_DIR.'admin/pages/mediadores.php'; }
 public static function alunos(){ require ICD_VISITAS_DIR.'admin/pages/alunos.php'; }
 public static function pedagogas(){ require ICD_VISITAS_DIR.'admin/pages/pedagogas.php'; }
 public static function vinculos(){ require ICD_VISITAS_DIR.'admin/pages/vinculos.php'; }
 public static function responsaveis(){ require ICD_VISITAS_DIR.'admin/pages/responsaveis.php'; }
 public static function municipios(){ require ICD_VISITAS_DIR.'admin/pages/municipios.php'; }
 public static function importacao(){ require ICD_VISITAS_DIR.'admin/pages/importacao.php'; }
 private static function allow($cap,$nonce){ if(!current_user_can($cap)||!check_admin_referer($nonce)) wp_die('Acesso negado.'); }
 public static function save_municipio(){ self::allow('icd_visitas_manage_cadastros','icd_save_municipio'); global $wpdb; $id=absint($_POST['id']??0); $d=['nome'=>sanitize_text_field($_POST['nome']??''),'uf'=>strtoupper(substr(sanitize_text_field($_POST['uf']??'PR'),0,2)),'ativo'=>isset($_POST['ativo'])?1:0,'updated_at'=>current_time('mysql')]; if($id)$wpdb->update(ICD_Visitas_Database::table('municipios'),$d,['id'=>$id]); else{$d['created_at']=current_time('mysql');$d['ativo']=1;$wpdb->insert(ICD_Visitas_Database::table('municipios'),$d);$id=$wpdb->insert_id;} ICD_Visitas_Helpers::log('municipio',$id,'salvar',$d); ICD_Visitas_Helpers::redirect('icd-visitas-municipios',['saved'=>1]); }
 public static function save_escola(){ self::allow('icd_visitas_manage_cadastros','icd_save_escola'); global $wpdb; $id=absint($_POST['id']??0); $d=['nome'=>sanitize_text_field($_POST['nome']??''),'municipio_id'=>absint($_POST['municipio_id']??0)?:null,'endereco'=>sanitize_text_field($_POST['endereco']??''),'numero'=>sanitize_text_field($_POST['numero']??''),'bairro'=>sanitize_text_field($_POST['bairro']??''),'cep'=>sanitize_text_field($_POST['cep']??''),'telefone'=>sanitize_text_field($_POST['telefone']??''),'email'=>sanitize_email($_POST['email']??''),'meta_visitas_semana'=>($_POST['meta_visitas_semana']??'')!==''?(float)str_replace(',','.',$_POST['meta_visitas_semana']):null,'observacoes'=>sanitize_textarea_field($_POST['observacoes']??''),'ativo'=>isset($_POST['ativo'])?1:0,'updated_at'=>current_time('mysql')]; if($id)$wpdb->update(ICD_Visitas_Database::table('escolas'),$d,['id'=>$id]); else{$d['created_at']=current_time('mysql');$d['ativo']=1;$wpdb->insert(ICD_Visitas_Database::table('escolas'),$d);$id=$wpdb->insert_id;} ICD_Visitas_Helpers::log('escola',$id,'salvar',$d); ICD_Visitas_Helpers::redirect('icd-visitas-escolas',['saved'=>1]); }
 private static function pedagoga_available($id) {
  global $wpdb;
  if (!$id) return true;
  $table = ICD_Visitas_Database::table('pedagogas');
  return (bool) $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE id = %d AND ativo = 1", $id));
 }
 public static function save_mediador() {
  self::allow('icd_visitas_manage_cadastros', 'icd_save_mediador');
  global $wpdb;
  $id = ICD_Visitas_Helpers::id_from($_POST, 'id');
  $table = ICD_Visitas_Database::table('mediadores');
  $data = [
   'nome' => sanitize_text_field(wp_unslash($_POST['nome'] ?? '')),
   'cargo' => sanitize_text_field(wp_unslash($_POST['cargo'] ?? 'Mediador')),
   'email' => sanitize_email(wp_unslash($_POST['email'] ?? '')),
   'telefone' => sanitize_text_field(wp_unslash($_POST['telefone'] ?? '')),
   'wp_user_id' => ICD_Visitas_Helpers::id_from($_POST, 'wp_user_id') ?: null,
   'observacoes' => sanitize_textarea_field(wp_unslash($_POST['observacoes'] ?? '')),
   'ativo' => $id && !isset($_POST['ativo']) ? 0 : 1,
   'updated_at' => current_time('mysql'),
  ];
  if ($id) {
   $result = $wpdb->update($table, $data, ['id' => $id]);
  } else {
   $pedagoga_id = ICD_Visitas_Helpers::id_from($_POST, 'pedagoga_id');
   if (!self::pedagoga_available($pedagoga_id)) wp_die('Selecione uma pedagoga ativa.', '', ['response' => 400, 'back_link' => true]);
   $data['pedagoga_id'] = $pedagoga_id ?: null;
   $data['created_at'] = $data['updated_at'];
   $result = $wpdb->insert($table, $data);
   $id = $wpdb->insert_id;
  }
  if ($result === false) wp_die('Não foi possível salvar a mediadora.', '', ['response' => 500, 'back_link' => true]);
  ICD_Visitas_Helpers::log('mediador', $id, 'salvar', $data);
  ICD_Visitas_Helpers::redirect('icd-visitas-mediadores', ['saved' => 1]);
 }
 public static function transfer_mediador() {
  if (!current_user_can('icd_visitas_manage_cadastros')) wp_die('Acesso negado.', '', ['response' => 403]);
  if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') wp_die('Solicitação inválida.', '', ['response' => 405]);
  $id = ICD_Visitas_Helpers::id_from($_POST, 'mediador_id');
  check_admin_referer('icd_transfer_mediador_' . $id);
  global $wpdb;
  $table = ICD_Visitas_Database::table('mediadores');
  $row = $wpdb->get_row($wpdb->prepare("SELECT pedagoga_id FROM $table WHERE id = %d", $id));
  if (!$row) wp_die('Mediadora indisponível.', '', ['response' => 404]);

  $pedagoga_id = ICD_Visitas_Helpers::id_from($_POST, 'pedagoga_id');
  if ($pedagoga_id !== (int) $row->pedagoga_id) {
   if (!self::pedagoga_available($pedagoga_id)) wp_die('Selecione uma pedagoga ativa.', '', ['response' => 400, 'back_link' => true]);
   $old = ['pedagoga_id' => ICD_Visitas_Helpers::id_from($_POST, 'pedagoga_anterior') ?: null];
   $new = ['pedagoga_id' => $pedagoga_id ?: null];
   $result = $wpdb->update($table, $new + ['updated_at' => current_time('mysql')], ['id' => $id] + $old);
   if ($result === false) wp_die('Não foi possível salvar o vínculo.', '', ['response' => 500, 'back_link' => true]);
   if (!$result) wp_die('A pedagoga mudou enquanto a página estava aberta. Atualize a página e tente de novo.', '', ['response' => 409, 'back_link' => true]);
   ICD_Visitas_Helpers::log('mediador', $id, 'trocar_pedagoga', $new, $old);
  }
  ICD_Visitas_Helpers::redirect('icd-visitas-mediadores', ['vinculo_salvo' => 1]);
 }
 public static function save_pedagoga(){ self::allow('icd_visitas_manage_cadastros','icd_save_pedagoga'); global $wpdb; $id=absint($_POST['id']??0); $d=['nome'=>sanitize_text_field($_POST['nome']??''),'email'=>sanitize_email($_POST['email']??''),'telefone'=>sanitize_text_field($_POST['telefone']??''),'wp_user_id'=>absint($_POST['wp_user_id']??0)?:null,'observacoes'=>sanitize_textarea_field($_POST['observacoes']??''),'ativo'=>isset($_POST['ativo'])?1:0,'updated_at'=>current_time('mysql')]; if($id)$wpdb->update(ICD_Visitas_Database::table('pedagogas'),$d,['id'=>$id]); else{$d['created_at']=current_time('mysql');$d['ativo']=1;$wpdb->insert(ICD_Visitas_Database::table('pedagogas'),$d);$id=$wpdb->insert_id;} ICD_Visitas_Helpers::log('pedagoga',$id,'salvar',$d); ICD_Visitas_Helpers::redirect('icd-visitas-pedagogas',['saved'=>1]); }
 public static function save_vinculo(){ self::allow('icd_visitas_manage_cadastros','icd_save_vinculo'); global $wpdb; $d=['escola_id'=>absint($_POST['escola_id']??0),'mediador_id'=>absint($_POST['mediador_id']??0),'data_inicio'=>sanitize_text_field($_POST['data_inicio']??current_time('Y-m-d')),'data_fim'=>sanitize_text_field($_POST['data_fim']??'')?:null,'observacoes'=>sanitize_textarea_field($_POST['observacoes']??''),'ativo'=>1,'updated_at'=>current_time('mysql'),'created_at'=>current_time('mysql')]; $wpdb->replace(ICD_Visitas_Database::table('vinculos'),$d); ICD_Visitas_Helpers::redirect('icd-visitas-vinculos',['saved'=>1]); }
 public static function save_agenda(){ self::allow('icd_visitas_manage_cadastros','icd_save_agenda'); global $wpdb; $d=['vinculo_id'=>absint($_POST['vinculo_id']??0),'dia_semana'=>absint($_POST['dia_semana']??0),'turno'=>sanitize_text_field($_POST['turno']??'manha'),'hora_inicio'=>sanitize_text_field($_POST['hora_inicio']??'')?:null,'hora_fim'=>sanitize_text_field($_POST['hora_fim']??'')?:null,'ativo'=>1,'created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')]; $wpdb->replace(ICD_Visitas_Database::table('agendas'),$d); ICD_Visitas_Helpers::redirect('icd-visitas-agenda',['saved'=>1]); }
 public static function save_responsavel(){ self::allow('icd_visitas_manage_cadastros','icd_save_responsavel'); global $wpdb; $d=['escola_id'=>absint($_POST['escola_id']??0),'nome'=>sanitize_text_field($_POST['nome']??''),'cargo'=>sanitize_text_field($_POST['cargo']??''),'telefone'=>sanitize_text_field($_POST['telefone']??''),'whatsapp'=>sanitize_text_field($_POST['whatsapp']??''),'email'=>sanitize_email($_POST['email']??''),'principal'=>isset($_POST['principal'])?1:0,'ativo'=>1,'observacoes'=>sanitize_textarea_field($_POST['observacoes']??''),'created_at'=>current_time('mysql')]; $wpdb->insert(ICD_Visitas_Database::table('responsaveis'),$d); ICD_Visitas_Helpers::redirect('icd-visitas-responsaveis',['saved'=>1]); }
 public static function save_visita(){
  self::allow('icd_visitas_manage_visitas','icd_save_visita'); global $wpdb; $id=absint($_POST['id']??0);
  $d=['escola_id'=>absint($_POST['escola_id']??0),'data_visita'=>sanitize_text_field($_POST['data_visita']??''),'turno'=>sanitize_text_field($_POST['turno']??'manha'),'hora_inicio'=>null,'hora_fim'=>null,'status'=>sanitize_text_field($_POST['status']??'realizada'),'tipo'=>sanitize_text_field($_POST['tipo']??'acompanhamento'),'observacoes'=>sanitize_textarea_field($_POST['observacoes']??''),'origem'=>sanitize_text_field($_POST['origem']??'manual'),'criado_por'=>get_current_user_id(),'updated_at'=>current_time('mysql')];
  if($id)$wpdb->update(ICD_Visitas_Database::table('visitas'),$d,['id'=>$id]); else{$d['created_at']=current_time('mysql');$wpdb->insert(ICD_Visitas_Database::table('visitas'),$d);$id=$wpdb->insert_id;}
  $wpdb->delete(ICD_Visitas_Database::table('participantes'),['visita_id'=>$id]); foreach(array_map('absint',(array)($_POST['mediadores']??[])) as $mid) if($mid)$wpdb->insert(ICD_Visitas_Database::table('participantes'),['visita_id'=>$id,'mediador_id'=>$mid,'presente'=>1]);
  $wpdb->delete(ICD_Visitas_Database::table('visita_pedagogas'),['visita_id'=>$id]); foreach(array_map('absint',(array)($_POST['pedagogas']??[])) as $pid) if($pid)$wpdb->insert(ICD_Visitas_Database::table('visita_pedagogas'),['visita_id'=>$id,'pedagoga_id'=>$pid]);
  ICD_Visitas_Helpers::log('visita',$id,'salvar',$d);
  $redirect=!empty($_POST['redirect_to'])?esc_url_raw(wp_unslash($_POST['redirect_to'])):wp_get_referer();
  if($redirect && strpos($redirect,'wp-admin')===false){ wp_safe_redirect(add_query_arg($id&&isset($_POST['id'])&&absint($_POST['id'])?'updated':'saved','1',$redirect)); exit; }
  ICD_Visitas_Helpers::redirect('icd-visitas-registros',['saved'=>1]);
 }
 public static function complete_visita(){
  if(!current_user_can('icd_visitas_manage_visitas')) wp_die('Acesso negado.'); global $wpdb; $id=absint($_POST['id']??0); check_admin_referer('icd_complete_visita_'.$id);
  if(!$id) wp_die('Visita inválida.');
  $observacoes=isset($_POST['observacoes'])?sanitize_textarea_field(wp_unslash($_POST['observacoes'])):'';
  $data=['status'=>'realizada','observacoes'=>$observacoes,'updated_at'=>current_time('mysql')];
  $wpdb->update(ICD_Visitas_Database::table('visitas'),$data,['id'=>$id]);
  ICD_Visitas_Helpers::log('visita',$id,'marcar_realizada',['status'=>'realizada','observacoes'=>$observacoes]);
  $redirect=!empty($_POST['redirect_to'])?esc_url_raw(wp_unslash($_POST['redirect_to'])):wp_get_referer(); wp_safe_redirect(add_query_arg('done','1',$redirect?:home_url('/'))); exit;
 }
 public static function delete_visita(){
  if(!current_user_can('icd_visitas_manage_visitas')) wp_die('Acesso negado.'); global $wpdb; $id=absint($_POST['id']??0); check_admin_referer('icd_delete_visita_'.$id);
  if(!$id) wp_die('Visita inválida.');
  $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.ICD_Visitas_Database::table('visitas').' WHERE id=%d',$id),ARRAY_A);
  $wpdb->delete(ICD_Visitas_Database::table('participantes'),['visita_id'=>$id]); $wpdb->delete(ICD_Visitas_Database::table('visita_pedagogas'),['visita_id'=>$id]); $wpdb->delete(ICD_Visitas_Database::table('visitas'),['id'=>$id]);
  ICD_Visitas_Helpers::log('visita',$id,'excluir',$row?:[]);
  $redirect=!empty($_POST['redirect_to'])?esc_url_raw(wp_unslash($_POST['redirect_to'])):wp_get_referer(); wp_safe_redirect(add_query_arg('deleted','1',$redirect?:home_url('/'))); exit;
 }

 public static function visit_details(){
  if(!current_user_can('icd_visitas_view_dashboard')) wp_send_json_error(['message'=>'Acesso negado.'],403);
  check_ajax_referer('icd_visit_details','nonce'); global $wpdb;
  $id=absint($_GET['visit_id']??0); if(!$id) wp_send_json_error(['message'=>'Visita inválida.'],400);
  $tv=ICD_Visitas_Database::table('visitas'); $te=ICD_Visitas_Database::table('escolas'); $tm=ICD_Visitas_Database::table('mediadores'); $tp=ICD_Visitas_Database::table('participantes'); $tpg=ICD_Visitas_Database::table('pedagogas'); $tvp=ICD_Visitas_Database::table('visita_pedagogas');
  $r=$wpdb->get_row($wpdb->prepare("SELECT v.id,v.data_visita,v.turno,v.status,v.observacoes,e.nome escola,(SELECT GROUP_CONCAT(m.nome SEPARATOR ', ') FROM $tp p JOIN $tm m ON m.id=p.mediador_id WHERE p.visita_id=v.id) mediadores,(SELECT GROUP_CONCAT(pg.nome SEPARATOR ', ') FROM $tvp vp JOIN $tpg pg ON pg.id=vp.pedagoga_id WHERE vp.visita_id=v.id) pedagogas FROM $tv v JOIN $te e ON e.id=v.escola_id WHERE v.id=%d",$id));
  if(!$r) wp_send_json_error(['message'=>'Visita não encontrada.'],404);
  wp_send_json_success(['id'=>(int)$r->id,'data'=>date_i18n('d/m/Y',strtotime($r->data_visita)),'escola'=>$r->escola,'turno'=>ICD_Visitas_Helpers::turno_label($r->turno),'status'=>ICD_Visitas_Helpers::status_label($r->status),'mediadores'=>$r->mediadores?:'Não informados','pedagogas'=>$r->pedagogas?:'Não informada','observacoes'=>$r->observacoes?:'Nenhuma observação registrada.','front_edit_url'=>add_query_arg('editar',(int)$r->id,site_url('/nova-visita/'))]);
 }

 public static function school_calendar(){
  if(!current_user_can('icd_visitas_view_dashboard')) wp_send_json_error(['message'=>'Acesso negado.'],403);
  check_ajax_referer('icd_school_calendar','nonce'); global $wpdb;
  $school_id=absint($_GET['school_id']??0); $month=sanitize_text_field($_GET['month']??'');
  if(!$school_id) wp_send_json_error(['message'=>'Escola inválida.'],400);
  $te=ICD_Visitas_Database::table('escolas'); $tv=ICD_Visitas_Database::table('visitas'); $tm=ICD_Visitas_Database::table('mediadores'); $tp=ICD_Visitas_Database::table('participantes'); $tpg=ICD_Visitas_Database::table('pedagogas'); $tvp=ICD_Visitas_Database::table('visita_pedagogas');
  $school=$wpdb->get_row($wpdb->prepare("SELECT id,nome FROM $te WHERE id=%d",$school_id)); if(!$school) wp_send_json_error(['message'=>'Escola não encontrada.'],404);
  if(!preg_match('/^\d{4}-\d{2}$/',$month)){ $last=$wpdb->get_var($wpdb->prepare("SELECT MAX(data_visita) FROM $tv WHERE escola_id=%d",$school_id)); $month=$last?substr($last,0,7):current_time('Y-m'); }
  $start=$month.'-01'; $end=date('Y-m-t',strtotime($start));
  $rows=$wpdb->get_results($wpdb->prepare("SELECT v.id,v.data_visita,v.turno,v.status,v.observacoes,(SELECT GROUP_CONCAT(m.nome SEPARATOR ', ') FROM $tp p JOIN $tm m ON m.id=p.mediador_id WHERE p.visita_id=v.id) mediadores,(SELECT GROUP_CONCAT(pg.nome SEPARATOR ', ') FROM $tvp vp JOIN $tpg pg ON pg.id=vp.pedagoga_id WHERE vp.visita_id=v.id) pedagogas FROM $tv v WHERE v.escola_id=%d AND v.data_visita BETWEEN %s AND %s ORDER BY v.data_visita,v.id",$school_id,$start,$end));
  $days=[]; foreach($rows as $r){ $day=(int)substr($r->data_visita,8,2); if(!isset($days[$day]))$days[$day]=[]; $days[$day][]= ['id'=>(int)$r->id,'date'=>$r->data_visita,'turno'=>ICD_Visitas_Helpers::turno_label($r->turno),'status'=>ICD_Visitas_Helpers::status_label($r->status),'status_key'=>$r->status,'observacoes'=>$r->observacoes,'mediadores'=>$r->mediadores?:'—','pedagogas'=>$r->pedagogas?:'—','edit_url'=>admin_url('admin.php?page=icd-visitas-registros&edit='.(int)$r->id),'front_edit_url'=>add_query_arg('editar',(int)$r->id,site_url('/nova-visita/'))]; }
  wp_send_json_success(['school'=>['id'=>(int)$school->id,'name'=>$school->nome],'month'=>$month,'label'=>date_i18n('F \d\e Y',strtotime($start)),'firstWeekday'=>(int)date('w',strtotime($start)),'daysInMonth'=>(int)date('t',strtotime($start)),'visits'=>$days]);
 }

 public static function import_confirm(){ self::allow('icd_visitas_manage_settings','icd_import_confirm'); $data=get_transient('icd_visitas_import_preview_'.get_current_user_id()); if(!$data||empty($data['file'])) wp_die('A prévia expirou. Envie a planilha novamente.'); $result=ICD_Visitas_Importer::import($data['file']); if(is_wp_error($result)) wp_die(esc_html($result->get_error_message())); set_transient('icd_visitas_import_result_'.get_current_user_id(),$result,300); delete_transient('icd_visitas_import_preview_'.get_current_user_id()); ICD_Visitas_Helpers::redirect('icd-visitas-importacao',['imported'=>1]); }
 public static function import_preview(){ self::allow('icd_visitas_manage_settings','icd_import_preview'); if(empty($_FILES['arquivo']['tmp_name'])) wp_die('Selecione um arquivo XLSX.'); $name=sanitize_file_name($_FILES['arquivo']['name']); if(strtolower(pathinfo($name,PATHINFO_EXTENSION))!=='xlsx') wp_die('Envie um arquivo .xlsx.'); $up=wp_upload_bits('icd-visitas-import-'.time().'.xlsx',null,file_get_contents($_FILES['arquivo']['tmp_name'])); if(!empty($up['error'])) wp_die(esc_html($up['error'])); $preview=ICD_Visitas_Importer::preview($up['file']); set_transient('icd_visitas_import_preview_'.get_current_user_id(),['file'=>$up['file'],'preview'=>$preview],HOUR_IN_SECONDS); ICD_Visitas_Helpers::redirect('icd-visitas-importacao',['preview'=>1]); }

 private static function smart_import_key(){ return 'icd_visitas_smart_import_'.get_current_user_id(); }
 private static function smart_import_state(){ $s=get_transient(self::smart_import_key()); return is_array($s)?$s:[]; }
 private static function smart_import_save_state($s){ set_transient(self::smart_import_key(),$s,HOUR_IN_SECONDS); }
 private static function smart_import_redirect($step='upload',$args=[]){ $args=array_merge(['page'=>'icd-visitas-importacao','smart_step'=>$step],$args); wp_safe_redirect(add_query_arg($args,admin_url('admin.php'))); exit; }
 public static function smart_import_upload(){
  self::allow('icd_visitas_manage_settings','icd_smart_import_upload');
  if(empty($_FILES['arquivo']['tmp_name'])) wp_die('Selecione um arquivo XLSX.');
  $name=sanitize_file_name($_FILES['arquivo']['name']??''); if(strtolower(pathinfo($name,PATHINFO_EXTENSION))!=='xlsx') wp_die('Envie um arquivo .xlsx.');
  $size=(int)($_FILES['arquivo']['size']??0); if($size>15*1024*1024) wp_die('O arquivo excede o limite de 15 MB deste importador.');
  $up=wp_upload_bits('icd-carga-'.get_current_user_id().'-'.time().'.xlsx',null,file_get_contents($_FILES['arquivo']['tmp_name'])); if(!empty($up['error'])) wp_die(esc_html($up['error']));
  $scan=ICD_Visitas_Importer::smart_scan($up['file']); if(is_wp_error($scan)){@unlink($up['file']);wp_die(esc_html($scan->get_error_message()));}
  $state=['file'=>$up['file'],'filename'=>$name,'header_row'=>$scan['header_row'],'scan'=>['columns'=>$scan['columns'],'data_count'=>$scan['data_count'],'candidate_rows'=>$scan['candidate_rows']]]; self::smart_import_save_state($state); self::smart_import_redirect('tipo');
 }
 public static function smart_import_type(){
  self::allow('icd_visitas_manage_settings','icd_smart_import_type'); $state=self::smart_import_state(); if(empty($state['file'])||!is_file($state['file']))wp_die('A sessão de importação expirou. Envie o arquivo novamente.');
  $entity=sanitize_key($_POST['entity']??''); $entities=ICD_Visitas_Importer::smart_entities(); if(empty($entities[$entity]))wp_die('Escolha um tipo de cadastro válido.');
  $header=absint($_POST['header_row']??$state['header_row']); $scan=ICD_Visitas_Importer::smart_scan($state['file'],$header); if(is_wp_error($scan))wp_die(esc_html($scan->get_error_message()));
  $state['entity']=$entity;$state['header_row']=$header;$state['scan']=['columns'=>$scan['columns'],'data_count'=>$scan['data_count'],'candidate_rows'=>$scan['candidate_rows']];$state['suggestions']=ICD_Visitas_Importer::smart_suggestions($entity,$scan['columns']);unset($state['mapping'],$state['options'],$state['preview']);self::smart_import_save_state($state);self::smart_import_redirect('mapeamento');
 }
 public static function smart_import_map(){
  self::allow('icd_visitas_manage_settings','icd_smart_import_map'); $state=self::smart_import_state(); if(empty($state['file'])||empty($state['entity']))wp_die('A sessão de importação expirou.');
  $entities=ICD_Visitas_Importer::smart_entities();$fields=$entities[$state['entity']]['fields'];$cols=array_keys($state['scan']['columns']??[]);$mapping=[];foreach($fields as $field=>$def){$col=sanitize_text_field($_POST['map'][$field]??'');$mapping[$field]=in_array($col,$cols,true)?$col:'';}
  $options=['duplicate_action'=>($_POST['duplicate_action']??'update')==='skip'?'skip':'update','create_related'=>!empty($_POST['create_related'])?1:0];
  $preview=ICD_Visitas_Importer::smart_preview($state['file'],$state['entity'],(int)$state['header_row'],$mapping,$options);if(is_wp_error($preview)){self::smart_import_redirect('mapeamento',['smart_error'=>$preview->get_error_message()]);}
  $state['mapping']=$mapping;$state['options']=$options;$state['preview']=$preview;self::smart_import_save_state($state);self::smart_import_redirect('confirmar');
 }
 public static function smart_import_confirm(){
  self::allow('icd_visitas_manage_settings','icd_smart_import_confirm'); $state=self::smart_import_state(); if(empty($state['file'])||empty($state['entity'])||empty($state['mapping']))wp_die('A sessão de importação expirou.');
  $result=ICD_Visitas_Importer::smart_import($state['file'],$state['entity'],(int)$state['header_row'],$state['mapping'],$state['options']??[]);if(is_wp_error($result)){self::smart_import_redirect('confirmar',['smart_error'=>$result->get_error_message()]);}
  @unlink($state['file']);delete_transient(self::smart_import_key());set_transient('icd_visitas_smart_import_result_'.get_current_user_id(),$result,5*MINUTE_IN_SECONDS);self::smart_import_redirect('resultado');
 }
 public static function smart_import_reset(){
  self::allow('icd_visitas_manage_settings','icd_smart_import_reset');$state=self::smart_import_state();if(!empty($state['file'])&&is_file($state['file']))@unlink($state['file']);delete_transient(self::smart_import_key());self::smart_import_redirect('upload');
 }

}
