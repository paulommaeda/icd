<?php
if(!defined('ABSPATH')) exit;
class ICD_Visitas_Reports {
 private const STUDENT_RECORD_SPACE = 50;

 public static function init(){
  ICD_Visitas_Portal::register_action('icd_generate_visits_pdf',[__CLASS__,'generate_pdf']);
  ICD_Visitas_Portal::register_action('icd_generate_visits_pdf_ajax',[__CLASS__,'generate_pdf_ajax'],true);
 }
 private static function filters_from_request($source){
  $today=current_time('Y-m-d');
  $from=ICD_Visitas_Helpers::valid_date($source['from']??'') ?: substr($today,0,7).'-01';
  $to=ICD_Visitas_Helpers::valid_date($source['to']??'') ?: $today;
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
  global $wpdb;
  $rows = self::query($filters);
  if ($wpdb->last_error) throw new RuntimeException('Falha ao consultar as visitas realizadas.');
  $linked = array_fill_keys(array_column($rows, 'id'), []);
  $notes = ICD_Visitas_Students::observations_for_report($filters, array_keys($linked));
  $other = [];
  foreach ($notes as $note) {
   if (isset($linked[$note->visita_id])) $linked[$note->visita_id][] = $note;
   else $other[] = $note;
  }
  $subtitle = 'Período de ' . mysql2date('d/m/Y', $filters['from']) . ' a ' . mysql2date('d/m/Y', $filters['to']);
  $pdf = new ICD_Visitas_PDF(ICD_VISITAS_DIR.'assets/logo-report.jpg', $subtitle);
  $pdf->addPage();
  if (!$rows) $pdf->emptyMessage('Nenhuma visita realizada para os filtros selecionados.');
  foreach ($rows as $r) {
   $pdf->visitStart();
   $pdf->field('PEDAGOGA RESPONSÁVEL', $r->pedagogas ?: 'Não informada');
   $pdf->field('DATA DA VISITA', mysql2date('d/m/Y', $r->data_visita));
   $pdf->field('ESCOLA', $r->escola);
   $pdf->field('MEDIADOR(ES)', $r->mediadores ?: 'Não informados');
   $pdf->notes($r->observacoes ?? '', 'OBSERVAÇÕES DA VISITA');
   if ($linked[$r->id]) {
    $pdf->heading('Registros dos alunos nesta visita', self::STUDENT_RECORD_SPACE);
    foreach ($linked[$r->id] as $note) self::write_student_record($pdf, $note);
   }
   $pdf->separator();
  }
  if ($other) {
   $pdf->heading('Demais registros dos alunos no período', self::STUDENT_RECORD_SPACE);
   foreach ($other as $note) self::write_student_record($pdf, $note);
  }
  $filename = 'relatorio-visitas-' . $filters['from'] . '-a-' . $filters['to'] . '.pdf';
  return ['filename'=>$filename, 'bytes'=>$pdf->output($filename, 'S'), 'count'=>count($rows), 'student_count'=>count($notes)];
 }
 private static function write_student_record($pdf, $note) {
  $pdf->ensureSpace(self::STUDENT_RECORD_SPACE);
  $pdf->field('ALUNO', $note->aluno);
  $author = $note->autor ?: 'Usuário removido';
  $pdf->field('DATA E AUTOR', mysql2date('d/m/Y', $note->data_registro) . ' | ' . $author);
  $pdf->field('ESCOLA E MEDIADORA', ($note->escola ?: 'Escola não informada') . ' | ' . ($note->mediadora ?: 'Mediadora não informada'));
  $pdf->notes($note->observacao, 'REGISTRO DO ALUNO');
  $pdf->gap(3);
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
    'student_count'=>(int)$built['student_count'],
   ]);
  }catch(Throwable $e){
   error_log('ICD Visitas PDF AJAX: '.$e->getMessage());
   wp_send_json_error(['message'=>'Não foi possível gerar o PDF no servidor. Tente novamente; se persistir, verifique o log do WordPress.'],500);
  }
 }
}
