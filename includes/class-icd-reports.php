<?php
if(!defined('ABSPATH')) exit;
class ICD_Visitas_Reports {
 public static function init(){
  add_action('admin_post_icd_generate_visits_pdf',[__CLASS__,'generate_pdf']);
  add_action('wp_ajax_icd_generate_visits_pdf_ajax',[__CLASS__,'generate_pdf_ajax']);
 }
 private static function valid_date($date,$fallback){ return preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)$date)?$date:$fallback; }
 private static function filters_from_request($source){
  $today=current_time('Y-m-d');
  $from=self::valid_date(sanitize_text_field($source['from']??''),date('Y-m-01',strtotime($today)));
  $to=self::valid_date(sanitize_text_field($source['to']??''),$today);
  if($from>$to){ $tmp=$from;$from=$to;$to=$tmp; }
  return [
   'from'=>$from,
   'to'=>$to,
   'school'=>absint($source['school']??0),
   'pedagoga'=>absint($source['pedagoga']??0),
   'mediador'=>absint($source['mediador']??0),
  ];
 }
 public static function query($filters){
  global $wpdb;
  $v=ICD_Visitas_Database::table('visitas'); $e=ICD_Visitas_Database::table('escolas');
  $p=ICD_Visitas_Database::table('participantes'); $m=ICD_Visitas_Database::table('mediadores');
  $vp=ICD_Visitas_Database::table('visita_pedagogas'); $pg=ICD_Visitas_Database::table('pedagogas');
  $where=['v.status=%s','v.data_visita BETWEEN %s AND %s']; $args=['realizada',$filters['from'],$filters['to']];
  if(!empty($filters['school'])){ $where[]='v.escola_id=%d'; $args[]=(int)$filters['school']; }
  if(!empty($filters['pedagoga'])){ $where[]="EXISTS (SELECT 1 FROM $vp fvp WHERE fvp.visita_id=v.id AND fvp.pedagoga_id=%d)"; $args[]=(int)$filters['pedagoga']; }
  if(!empty($filters['mediador'])){ $where[]="EXISTS (SELECT 1 FROM $p fp WHERE fp.visita_id=v.id AND fp.mediador_id=%d)"; $args[]=(int)$filters['mediador']; }
  $sql="SELECT v.id,v.data_visita,v.observacoes,e.nome escola,
    (SELECT GROUP_CONCAT(DISTINCT pg2.nome ORDER BY pg2.nome SEPARATOR ', ') FROM $vp vp2 JOIN $pg pg2 ON pg2.id=vp2.pedagoga_id WHERE vp2.visita_id=v.id) pedagogas,
    (SELECT GROUP_CONCAT(DISTINCT m2.nome ORDER BY m2.nome SEPARATOR ', ') FROM $p p2 JOIN $m m2 ON m2.id=p2.mediador_id WHERE p2.visita_id=v.id) mediadores
    FROM $v v JOIN $e e ON e.id=v.escola_id WHERE ".implode(' AND ',$where)." ORDER BY v.data_visita ASC,e.nome ASC,v.id ASC";
  return $wpdb->get_results($wpdb->prepare($sql,...$args));
 }
 private static function build_pdf($filters){
  $rows=self::query($filters);
  $subtitle='Período: '.date_i18n('d/m/Y',strtotime($filters['from'])).' a '.date_i18n('d/m/Y',strtotime($filters['to']));
  $pdf=new ICD_Visitas_PDF(ICD_VISITAS_DIR.'assets/logo-report.jpg',$subtitle);
  $pdf->addPage();
  if(!$rows){ $pdf->emptyMessage('Nenhuma visita realizada encontrada para os filtros selecionados.'); }
  foreach($rows as $r){
   $pdf->visitStart();
   $pdf->field('PEDAGOGA RESPONSÁVEL',$r->pedagogas?:'Não informada');
   $pdf->field('DATA',date_i18n('d/m/Y',strtotime($r->data_visita)));
   $pdf->field('ESCOLA',$r->escola);
   $pdf->field('MEDIADOR(ES)',$r->mediadores?:'Não informados');
   $pdf->notes($r->observacoes??'');
   $pdf->separator();
  }
  $filename='relatorio-visitas-'.$filters['from'].'-a-'.$filters['to'].'.pdf';
  return ['filename'=>$filename,'bytes'=>$pdf->output($filename,'S'),'count'=>count($rows)];
 }
 private static function can_generate(){ return is_user_logged_in() && current_user_can('icd_visitas_view_reports'); }
 public static function generate_pdf(){
  if(!self::can_generate()) wp_die('Você não possui permissão para gerar relatórios.');
  check_admin_referer('icd_generate_visits_pdf');
  try{
   $built=self::build_pdf(self::filters_from_request($_POST));
   while(ob_get_level()) @ob_end_clean();
   nocache_headers();
   header('Content-Type: application/pdf');
   header('Content-Disposition: attachment; filename="'.preg_replace('/[^A-Za-z0-9._-]+/','-',basename($built['filename'])).'"');
   header('Content-Length: '.strlen($built['bytes']));
   header('Cache-Control: private, no-store, max-age=0');
   echo $built['bytes'];
  }catch(Throwable $e){
   error_log('ICD Visitas PDF: '.$e->getMessage());
   wp_die('Não foi possível gerar o relatório. Tente novamente.');
  }
  exit;
 }
 public static function generate_pdf_ajax(){
  if(!self::can_generate()) wp_send_json_error(['message'=>'Seu usuário não possui permissão para gerar relatórios.'],403);
  if(!check_ajax_referer('icd_generate_visits_pdf','_wpnonce',false)) wp_send_json_error(['message'=>'A sessão da página expirou. Atualize a tela e tente novamente.'],403);
  try{
   $built=self::build_pdf(self::filters_from_request($_POST));
   if(empty($built['bytes']) || strlen($built['bytes'])<100) throw new RuntimeException('PDF vazio.');
   wp_send_json_success([
    'filename'=>$built['filename'],
    'mime'=>'application/pdf',
    'base64'=>base64_encode($built['bytes']),
    'count'=>(int)$built['count'],
   ]);
  }catch(Throwable $e){
   error_log('ICD Visitas PDF AJAX: '.$e->getMessage());
   wp_send_json_error(['message'=>'Não foi possível gerar o PDF no servidor. Tente novamente; se persistir, verifique o log do WordPress.'],500);
  }
 }
}
