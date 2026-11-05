<?php
if(!defined('ABSPATH')) exit;
class ICD_Visitas_Importer {
 public static function read_xlsx($path){
  $get=null; $close=function(){};
  if(class_exists('ZipArchive')){ $zip=new ZipArchive(); if($zip->open($path)!==true) return new WP_Error('xlsx','Não foi possível abrir o XLSX.'); $get=function($name)use($zip){return $zip->getFromName($name);}; $close=function()use($zip){$zip->close();}; }
  elseif(class_exists('PharData')){ try{$phar=new PharData($path);$get=function($name)use($phar){return isset($phar[$name])?$phar[$name]->getContent():false;};}catch(Exception $e){return new WP_Error('xlsx','Não foi possível abrir o XLSX: '.$e->getMessage());} }
  else return new WP_Error('zip','O servidor precisa de ZipArchive ou PharData para importar XLSX.');
  $decode=function($x){ return html_entity_decode(strip_tags($x),ENT_QUOTES|ENT_XML1,'UTF-8'); };
  $shared=[]; $ss=$get('xl/sharedStrings.xml');
  if($ss && preg_match_all('/<si\b[^>]*>(.*?)<\/si>/su',$ss,$items)){ foreach($items[1] as $item){ $txt=''; if(preg_match_all('/<t\b[^>]*>(.*?)<\/t>/su',$item,$ts)) foreach($ts[1] as $t)$txt.=$decode($t); $shared[]=$txt; } }
  $sheet=$get('xl/worksheets/sheet1.xml'); $close(); if(!$sheet) return new WP_Error('sheet','A primeira aba não foi encontrada.');
  $rows=[]; if(preg_match_all('/<row\b([^>]*)>(.*?)<\/row>/su',$sheet,$rms,PREG_SET_ORDER)){ foreach($rms as $rm){ $rn=0;if(preg_match('/\br="(\d+)"/u',$rm[1],$x))$rn=(int)$x[1]; if(!$rn)continue; $out=[]; if(preg_match_all('/<c\b([^>]*)\/>|<c\b([^>]*)>(.*?)<\/c>/su',$rm[2],$cms,PREG_SET_ORDER)){ foreach($cms as $cm){ $attrs=trim(($cm[1]??'')!==''?$cm[1]:($cm[2]??'')); $body=$cm[3]??''; if(!preg_match('/\br="([A-Z]+)\d+"/u',$attrs,$x))continue; $col=$x[1]; $type='';if(preg_match('/\bt="([^"]+)"/u',$attrs,$t))$type=$t[1]; $val=''; if($type==='inlineStr'){if(preg_match_all('/<t\b[^>]*>(.*?)<\/t>/su',$body,$ts))foreach($ts[1] as $tt)$val.=$decode($tt);} elseif(preg_match('/<v\b[^>]*>(.*?)<\/v>/su',$body,$vm)){$val=$decode($vm[1]);if($type==='s')$val=$shared[(int)$val]??'';} $out[$col]=trim((string)$val); } }
    if($out)$rows[$rn]=$out;
  }}
  return $rows;
 }
 private static function excel_date($v){ if(!is_numeric($v)) return null; $n=(float)$v; if($n<20000||$n>80000) return null; $ts=($n-25569)*86400; return gmdate('Y-m-d',(int)$ts); }
 public static function parse_dates($raw){ $raw=trim((string)$raw); if($raw==='') return []; if($d=self::excel_date($raw)) return [$d];
  $u=strtoupper($raw); $u=str_replace([' DE ','/ 2026','/2026'],['/','/2026','/2026'],$u);
  $dates=[]; if(preg_match_all('/\b(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})\b/',$raw,$ms,PREG_SET_ORDER)){ foreach($ms as $m){ $y=(int)$m[3]; if($y<100)$y+=2000; if(checkdate((int)$m[2],(int)$m[1],$y))$dates[]=sprintf('%04d-%02d-%02d',$y,$m[2],$m[1]); } }
  if(!$dates && preg_match('/(\d{1,2})\s*E\s*(\d{1,2})\s*(?:DO|DE)?\s*(\d{1,2})[\/\-.](\d{4})/iu',$raw,$m)){ foreach([(int)$m[1],(int)$m[2]] as $day) if(checkdate((int)$m[3],$day,(int)$m[4])) $dates[]=sprintf('%04d-%02d-%02d',$m[4],$m[3],$day); }
  if(!$dates && preg_match_all('/\b(\d{1,2})\s*[\/\-.]\s*(jan|fev|mar|abr|mai|jun|jul|ago|set|out|nov|dez)\.?/iu',$raw,$ms,PREG_SET_ORDER)){ $months=['jan'=>1,'fev'=>2,'mar'=>3,'abr'=>4,'mai'=>5,'jun'=>6,'jul'=>7,'ago'=>8,'set'=>9,'out'=>10,'nov'=>11,'dez'=>12]; foreach($ms as $m){ $key=strtolower($m[2]); $mo=$months[$key]??0; $day=(int)$m[1]; $year=2026; if($mo&&checkdate($mo,$day,$year))$dates[]=sprintf('%04d-%02d-%02d',$year,$mo,$day); } }
  return array_values(array_unique($dates));
 }
 public static function normalize_school($name){ $name=trim(preg_replace('/\s+/u',' ',$name)); $name=preg_replace('/\s*-\s*(MANHÃ|MANHA|TARDE|NOITE)\s*$/iu','',$name); return trim($name); }
 public static function municipality_from_school($raw){
  if(preg_match('/SJP/iu',$raw)) return ['São José dos Pinhais','PR'];
  if(preg_match('/Alm\.?(?:irante)?\s*Tamandar[eé]|Almirante\s+Tamandar[eé]/iu',$raw)) return ['Almirante Tamandaré','PR'];
  return [null,'PR'];
 }
 public static function clean_school_name($raw){
  $name=self::normalize_school($raw);
  $name=preg_replace('/\s*-\s*SJP\s*$/iu','',$name);
  $name=preg_replace('/\s*-\s*Alm\.?(?:irante)?\s*Tamandar[eé]\s*$/iu','',$name);
  return trim($name);
 }
 public static function parse_weekdays($raw){
  preg_match_all('/([2-7])\s*ª/u',(string)$raw,$m); return array_values(array_unique(array_map('intval',$m[1]??[])));
 }
 public static function build_records($path){
  $rows=self::read_xlsx($path); if(is_wp_error($rows)) return $rows;
  $turnos=[]; foreach(($rows[3]??[]) as $col=>$v){ $u=strtoupper((string)$v); if(preg_match('/MANH/iu',$v))$turnos[$col]='manha'; elseif(strpos($u,'TARDE')!==false)$turnos[$col]='tarde'; elseif(strpos($u,'NOITE')!==false)$turnos[$col]='noite'; }
  $mediador=''; $school_raw=''; $dias=''; $records=[]; $people=[]; $schools=[]; $notes=[];
  foreach($rows as $rn=>$r){ if($rn<=3)continue;
   $has_activity=false; foreach($r as $col=>$v) if(!in_array($col,['A','B','C'],true)&&self::parse_dates($v)){ $has_activity=true; break; }
   if(!empty($r['A'])) $mediador=trim($r['A']);
   if(!empty($r['B'])) $school_raw=trim($r['B']);
   if(!empty($r['C'])) $dias=trim($r['C']);
   if(!$mediador||!$school_raw) continue;
   $school=self::clean_school_name($school_raw); [$municipio,$uf]=self::municipality_from_school($school_raw);
   $people[$mediador]=true; $schools[$school]=['raw'=>$school_raw,'municipio'=>$municipio,'uf'=>$uf];
   foreach($r as $col=>$v){ if(in_array($col,['A','B','C'],true)||$v==='')continue; $dates=self::parse_dates($v); if(!$dates){ if(preg_match('/PEDAGOG|RESPONSÁVEL|RESPONSAVEL/iu',(string)$v))$notes[]=['linha'=>$rn,'escola'=>$school,'texto'=>(string)$v]; continue; }
    $turno=$turnos[$col]??'manha'; foreach($dates as $date){ $key=md5(strtolower($school).'|'.$date.'|'.$turno); if(!isset($records[$key]))$records[$key]=['escola'=>$school,'school_raw'=>$school_raw,'municipio'=>$municipio,'uf'=>$uf,'data'=>$date,'turno'=>$turno,'mediadores'=>[]]; $records[$key]['mediadores'][$mediador]=true; }
   }
  }
  return ['mediadores'=>array_keys($people),'escolas'=>$schools,'visitas'=>$records,'observacoes'=>$notes,'rows'=>$rows];
 }
 public static function preview($path){ $d=self::build_records($path); if(is_wp_error($d))return $d; return ['rows'=>count($d['rows']),'mediadores'=>count($d['mediadores']),'escolas'=>count($d['escolas']),'visitas'=>count($d['visitas']),'participacoes'=>array_sum(array_map(fn($v)=>count($v['mediadores']),$d['visitas'])),'observacoes'=>$d['observacoes']]; }
 public static function import($path){
  $d=self::build_records($path); if(is_wp_error($d))return $d; global $wpdb;
  $tm=ICD_Visitas_Database::table('municipios'); $te=ICD_Visitas_Database::table('escolas'); $tmed=ICD_Visitas_Database::table('mediadores'); $tv=ICD_Visitas_Database::table('visitas'); $tp=ICD_Visitas_Database::table('participantes'); $tvin=ICD_Visitas_Database::table('vinculos'); $ta=ICD_Visitas_Database::table('agendas'); $tr=ICD_Visitas_Database::table('responsaveis');
  $mids=[]; foreach($d['mediadores'] as $name){ $id=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $tmed WHERE nome=%s LIMIT 1",$name)); if(!$id){$wpdb->insert($tmed,['nome'=>$name,'cargo'=>'Mediador','ativo'=>1,'created_at'=>current_time('mysql')]);$id=$wpdb->insert_id;} $mids[$name]=$id; }
  $sids=[]; foreach($d['escolas'] as $name=>$info){ $munid=null; if($info['municipio']){ $munid=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $tm WHERE nome=%s LIMIT 1",$info['municipio'])); if(!$munid){$wpdb->insert($tm,['nome'=>$info['municipio'],'uf'=>$info['uf'],'ativo'=>1,'created_at'=>current_time('mysql')]);$munid=$wpdb->insert_id;} } $sid=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $te WHERE nome=%s LIMIT 1",$name)); if(!$sid){$wpdb->insert($te,['nome'=>$name,'municipio_id'=>$munid,'ativo'=>1,'created_at'=>current_time('mysql')]);$sid=$wpdb->insert_id;} $sids[$name]=$sid; }
  $created=0;$parts=0; foreach($d['visitas'] as $key=>$v){ $sid=$sids[$v['escola']]; $import_key='xlsx-'.$key; $vid=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $tv WHERE chave_importacao=%s LIMIT 1",$import_key)); if(!$vid){$wpdb->insert($tv,['escola_id'=>$sid,'data_visita'=>$v['data'],'turno'=>$v['turno'],'status'=>'realizada','tipo'=>'regular','origem'=>'importacao','chave_importacao'=>$import_key,'criado_por'=>get_current_user_id(),'created_at'=>current_time('mysql')]);$vid=$wpdb->insert_id;$created++;} foreach(array_keys($v['mediadores']) as $name){$mid=$mids[$name];$wpdb->query($wpdb->prepare("INSERT IGNORE INTO $tp (visita_id,mediador_id,presente,created_at) VALUES (%d,%d,1,%s)",$vid,$mid,current_time('mysql'))); if($wpdb->rows_affected)$parts++; $exists=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $tvin WHERE escola_id=%d AND mediador_id=%d AND ativo=1 LIMIT 1",$sid,$mid)); if(!$exists)$wpdb->insert($tvin,['escola_id'=>$sid,'mediador_id'=>$mid,'ativo'=>1,'created_at'=>current_time('mysql')]); } }
  foreach($d['observacoes'] as $o){ if(preg_match('/PEDAGOGA\s+([^\s]+).*RESPONS/iu',$o['texto'],$m)){ $sid=$sids[$o['escola']]??0; $name=ucwords(strtolower($m[1])); if($sid&&!$wpdb->get_var($wpdb->prepare("SELECT id FROM $tr WHERE escola_id=%d AND nome=%s",$sid,$name)))$wpdb->insert($tr,['escola_id'=>$sid,'nome'=>$name,'cargo'=>'Pedagoga','principal'=>1,'ativo'=>1,'observacoes'=>$o['texto'],'created_at'=>current_time('mysql')]); } }
  // Agenda: usa a coluna Dias da Semana em cada linha com escola e mediador conhecidos.
  $mediador='';$school_raw='';$dias=''; foreach($d['rows'] as $rn=>$r){ if($rn<=3)continue; if(!empty($r['A']))$mediador=trim($r['A']); if(!empty($r['B']))$school_raw=trim($r['B']); if(!empty($r['C']))$dias=trim($r['C']); if(!$mediador||!$school_raw||!$dias)continue; $school=self::clean_school_name($school_raw);$sid=$sids[$school]??0;$mid=$mids[$mediador]??0;if(!$sid||!$mid)continue;$vin=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $tvin WHERE escola_id=%d AND mediador_id=%d AND ativo=1 LIMIT 1",$sid,$mid)); if(!$vin)continue; foreach(self::parse_weekdays($dias) as $wd){ foreach(['manha','tarde'] as $turno){ $seen=false; foreach($d['visitas'] as $vis){if($vis['escola']===$school&&isset($vis['mediadores'][$mediador])&&$vis['turno']===$turno){$seen=true;break;}} if($seen)$wpdb->query($wpdb->prepare("INSERT IGNORE INTO $ta (vinculo_id,dia_semana,turno,ativo,created_at) VALUES (%d,%d,%s,1,%s)",$vin,$wd,$turno,current_time('mysql'))); } } }
  ICD_Visitas_Helpers::log('importacao',0,'xlsx',['visitas_criadas'=>$created,'participacoes_criadas'=>$parts]); return ['visitas_criadas'=>$created,'participacoes_criadas'=>$parts,'mediadores'=>count($mids),'escolas'=>count($sids)];
 }

 public static function smart_entities(){
  return [
   'pedagoga'=>['label'=>'Pedagogas','fields'=>[
    'nome'=>['label'=>'Nome','required'=>true,'aliases'=>['nome','pedagoga','pedagogo','nome da pedagoga','pedagoga responsavel','pedagoga responsável','profissional']],
    'email'=>['label'=>'E-mail','aliases'=>['email','e-mail','mail']],
    'telefone'=>['label'=>'Telefone','aliases'=>['telefone','celular','whatsapp','fone']],
    'observacoes'=>['label'=>'Observações','aliases'=>['observacoes','observações','obs','anotacoes','anotações']],
    'ativo'=>['label'=>'Ativo','aliases'=>['ativo','status','situacao','situação']],
   ]],
   'mediador'=>['label'=>'Mediadores','fields'=>[
    'nome'=>['label'=>'Nome','required'=>true,'aliases'=>['nome','mediador','mediadora','estagiario','estagiária','estagiaria','estagiário','estagiária responsável','estagiaria responsavel']],
    'cargo'=>['label'=>'Cargo','aliases'=>['cargo','funcao','função']],
    'email'=>['label'=>'E-mail','aliases'=>['email','e-mail','mail']],
    'telefone'=>['label'=>'Telefone','aliases'=>['telefone','celular','whatsapp','fone']],
    'escola'=>['label'=>'Escola para vínculo','aliases'=>['escola','unidade','instituicao','instituição','local']],
    'data_inicio'=>['label'=>'Início do vínculo','aliases'=>['data inicio','data de inicio','início','inicio','inicio vinculo','início vínculo']],
    'observacoes'=>['label'=>'Observações','aliases'=>['observacoes','observações','obs','anotacoes','anotações']],
    'ativo'=>['label'=>'Ativo','aliases'=>['ativo','status','situacao','situação']],
   ]],
   'escola'=>['label'=>'Escolas','fields'=>[
    'nome'=>['label'=>'Nome da escola','required'=>true,'aliases'=>['escola','nome da escola','nome','unidade','instituicao','instituição']],
    'municipio'=>['label'=>'Município','aliases'=>['municipio','município','cidade']],
    'uf'=>['label'=>'UF','aliases'=>['uf','estado','sigla estado']],
    'endereco'=>['label'=>'Endereço','aliases'=>['endereco','endereço','logradouro','rua']],
    'numero'=>['label'=>'Número','aliases'=>['numero','número','num']],
    'bairro'=>['label'=>'Bairro','aliases'=>['bairro']],
    'cep'=>['label'=>'CEP','aliases'=>['cep','codigo postal','código postal']],
    'telefone'=>['label'=>'Telefone','aliases'=>['telefone','fone','celular']],
    'email'=>['label'=>'E-mail','aliases'=>['email','e-mail','mail']],
    'meta_visitas_semana'=>['label'=>'Meta de visitas/semana','aliases'=>['meta','meta semanal','meta visitas','visitas semana','visitas por semana']],
    'observacoes'=>['label'=>'Observações','aliases'=>['observacoes','observações','obs','anotacoes','anotações']],
    'ativo'=>['label'=>'Ativo','aliases'=>['ativo','status','situacao','situação']],
   ]],
   'visita'=>['label'=>'Visitas','fields'=>[
    'data_visita'=>['label'=>'Data da visita','required'=>true,'aliases'=>['data','data visita','data da visita']],
    'escola'=>['label'=>'Escola','required'=>true,'aliases'=>['escola','unidade','instituicao','instituição','local']],
    'pedagoga'=>['label'=>'Pedagoga(s)','aliases'=>['pedagoga','pedagogas','pedagogo','pedagoga responsavel','pedagoga responsável','supervisora','supervisor']],
    'mediador'=>['label'=>'Mediador(es)','aliases'=>['mediador','mediadores','mediadora','mediadoras','estagiaria','estagiária','estagiarias','estagiárias']],
    'turno'=>['label'=>'Turno','aliases'=>['turno','periodo','período']],
    'status'=>['label'=>'Status','aliases'=>['status','situacao','situação']],
    'tipo'=>['label'=>'Tipo','aliases'=>['tipo','tipo visita','tipo de visita']],
    'observacoes'=>['label'=>'Observações','aliases'=>['observacoes','observações','obs','relato','descricao','descrição','comentarios','comentários']],
   ]],
  ];
 }
 private static function smart_norm($s){
  $s=remove_accents(strtolower(trim((string)$s))); $s=preg_replace('/[^a-z0-9]+/u',' ', $s); return trim(preg_replace('/\\s+/',' ',$s));
 }
 private static function smart_col_index($letters){
  $n=0; foreach(str_split(strtoupper($letters)) as $c)$n=$n*26+(ord($c)-64); return $n;
 }
 private static function smart_row_preview($row){
  if(!$row) return ''; ksort($row); $vals=[]; foreach($row as $v){$v=trim((string)$v);if($v!=='')$vals[]=$v;if(count($vals)>=4)break;} return implode(' | ',$vals);
 }
 public static function smart_scan($path,$header_row=0){
  $rows=self::read_xlsx($path); if(is_wp_error($rows))return $rows; if(!$rows)return new WP_Error('empty','A planilha não possui dados legíveis.');
  if(!$header_row){
   $bestRow=0;$bestScore=-1;
   foreach($rows as $rn=>$row){ if($rn>30)break; $non=0;$text=0;$known=0;
    foreach($row as $v){$v=trim((string)$v);if($v==='')continue;$non++; if(!is_numeric($v))$text++; $n=self::smart_norm($v); if(preg_match('/\\b(nome|escola|data|telefone|email|mediador|pedagoga|municipio|observa|turno|status|estagiaria|responsavel)\\b/',$n))$known+=50;}
    if($non<2)continue; $unique=[];foreach($row as $vv){$nn=self::smart_norm($vv);if($nn!=='')$unique[$nn]=true;}$score=$non+($text*1.4)+$known+count($unique)-($rn*0.02); if($score>$bestScore){$bestScore=$score;$bestRow=(int)$rn;}
   }
   $header_row=$bestRow?:array_key_first($rows);
  }
  if(empty($rows[$header_row]))return new WP_Error('header','Não foi possível identificar a linha de cabeçalho.');
  $allCols=[];foreach($rows as $rn=>$r){if($rn<$header_row)continue;if($rn>$header_row+100)break;foreach(array_keys($r) as $c)$allCols[$c]=true;} $cols=array_keys($allCols);usort($cols,fn($a,$b)=>self::smart_col_index($a)<=>self::smart_col_index($b));
  $headers=[];$seen=[]; foreach($cols as $col){ $value=$rows[$header_row][$col]??''; $label=trim((string)$value); if($label==='')$label='Coluna '.$col; $base=$label;$k=self::smart_norm($label); if(isset($seen[$k])){$seen[$k]++;$label.=' ('.$seen[$k].')';}else$seen[$k]=1; $samples=[]; foreach($rows as $rn=>$r){if($rn<=$header_row)continue;$v=trim((string)($r[$col]??''));if($v!=='')$samples[]=$v;if(count($samples)>=3)break;} $headers[$col]=['label'=>$label,'original'=>$base,'samples'=>$samples]; }
  $data_count=0;foreach($rows as $rn=>$r){if($rn<=$header_row)continue;$has=false;foreach($r as $v)if(trim((string)$v)!==''){$has=true;break;}if($has)$data_count++;}
  $candidate_rows=[]; foreach($rows as $rn=>$r){if($rn>20)break;$candidate_rows[$rn]=self::smart_row_preview($r);}
  return ['header_row'=>(int)$header_row,'columns'=>$headers,'data_count'=>$data_count,'candidate_rows'=>$candidate_rows,'rows'=>$rows];
 }
 public static function smart_suggestions($entity,$columns){
  $entities=self::smart_entities(); if(empty($entities[$entity]))return []; $result=[];$used=[];
  foreach($entities[$entity]['fields'] as $field=>$def){$best='';$bestScore=0;foreach($columns as $col=>$meta){if(isset($used[$col]))continue;$h=self::smart_norm($meta['original']??$meta['label']);foreach(array_merge([$def['label']],$def['aliases']??[]) as $alias){$a=self::smart_norm($alias);$score=0;if($h===$a)$score=100;elseif($a!==''&&strpos($h,$a)!==false)$score=75;elseif($h!==''&&strpos($a,$h)!==false)$score=60;if($score>$bestScore){$bestScore=$score;$best=$col;}}}if($best){$result[$field]=$best;$used[$best]=true;}}
  return $result;
 }
 public static function smart_preview($path,$entity,$header_row,$mapping,$options=[]){
  $scan=self::smart_scan($path,$header_row);if(is_wp_error($scan))return $scan;$entities=self::smart_entities();if(empty($entities[$entity]))return new WP_Error('entity','Tipo de importação inválido.');
  $fields=$entities[$entity]['fields'];$errors=[];foreach($fields as $field=>$def)if(!empty($def['required'])&&empty($mapping[$field]))$errors[]='Mapeie o campo obrigatório “'.$def['label'].'”.';if($errors)return new WP_Error('mapping',implode(' ',$errors));
  $out=[];$valid=0;$invalid=0;$warnings=0;$i=0;foreach($scan['rows'] as $rn=>$row){if($rn<=$header_row)continue;$has=false;foreach($row as $v)if(trim((string)$v)!==''){$has=true;break;}if(!$has)continue;$mapped=[];foreach($fields as $field=>$def){$col=$mapping[$field]??'';$mapped[$field]=$col!==''?trim((string)($row[$col]??'')):'';}$check=self::smart_validate_row($entity,$mapped,(bool)($options['create_related']??false));if($check['errors'])$invalid++;else$valid++;$warnings+=count($check['warnings']);if($i<15)$out[]=['row'=>$rn,'data'=>$mapped,'errors'=>$check['errors'],'warnings'=>$check['warnings']];$i++;}
  return ['entity'=>$entity,'label'=>$entities[$entity]['label'],'total'=>$valid+$invalid,'valid'=>$valid,'invalid'=>$invalid,'warnings'=>$warnings,'rows'=>$out];
 }
 private static function smart_validate_row($entity,$d,$createRelated=true){
  $errors=[];$warnings=[];$entities=self::smart_entities();foreach($entities[$entity]['fields'] as $f=>$def)if(!empty($def['required'])&&trim((string)($d[$f]??''))==='')$errors[]='Campo obrigatório vazio: '.$def['label'].'.';
  if($entity==='visita'&&!empty($d['data_visita'])&&!self::smart_date($d['data_visita']))$errors[]='Data inválida.';
  if($entity==='mediador'&&!empty($d['data_inicio'])&&!self::smart_date($d['data_inicio']))$warnings[]='Data de início do vínculo inválida; o vínculo será criado sem data.';
  if($entity==='visita'&&!$createRelated){global $wpdb;if(!empty($d['escola'])){$t=ICD_Visitas_Database::table('escolas');if(!(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE nome=%s LIMIT 1",trim($d['escola']))))$errors[]='A escola não existe e a criação automática está desativada.';}if(!empty($d['pedagoga']))$warnings[]='Pedagogas inexistentes serão ignoradas.';if(!empty($d['mediador']))$warnings[]='Mediadores inexistentes serão ignorados.';}
  return ['errors'=>$errors,'warnings'=>$warnings];
 }
 private static function smart_date($v){$v=trim((string)$v);if($v==='')return null;if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$v)&&strtotime($v))return $v;if(preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})$/',$v,$m)){$y=(int)$m[3];if($y<100)$y+=2000;return checkdate((int)$m[2],(int)$m[1],$y)?sprintf('%04d-%02d-%02d',$y,$m[2],$m[1]):null;}if(preg_match('/^(\d{1,2})[\/\-.](\d{1,2})$/',$v,$m)){$y=(int)current_time('Y');return checkdate((int)$m[2],(int)$m[1],$y)?sprintf('%04d-%02d-%02d',$y,$m[2],$m[1]):null;}if(preg_match('/^(\d{1,2})\s*[\/\-.]\s*(jan|fev|mar|abr|mai|jun|jul|ago|set|out|nov|dez)\.?$/iu',$v,$m)){$months=['jan'=>1,'fev'=>2,'mar'=>3,'abr'=>4,'mai'=>5,'jun'=>6,'jul'=>7,'ago'=>8,'set'=>9,'out'=>10,'nov'=>11,'dez'=>12];$mo=$months[strtolower($m[2])]??0;$y=(int)current_time('Y');return $mo&&checkdate($mo,(int)$m[1],$y)?sprintf('%04d-%02d-%02d',$y,$mo,$m[1]):null;}$ds=self::parse_dates($v);return $ds[0]??null;}
 private static function smart_bool($v,$default=1){$n=self::smart_norm($v);if($n==='')return $default;if(in_array($n,['0','nao','inativo','inativa','false','desativado','desativada'],true))return 0;if(in_array($n,['1','sim','ativo','ativa','true','yes'],true))return 1;return $default;}
 private static function smart_list($v){$parts=preg_split('/[;,|\\n\\r]+/u',(string)$v);$out=[];foreach($parts as $p){$p=trim($p);if($p!=='')$out[$p]=true;}return array_keys($out);}
 private static function smart_turno($v){$n=self::smart_norm($v);if(strpos($n,'tarde')!==false||strpos($n,'vespertino')!==false)return 'tarde';if(strpos($n,'noite')!==false||strpos($n,'noturno')!==false)return 'noite';if(strpos($n,'integral')!==false)return 'integral';return 'manha';}
 private static function smart_status($v){$n=self::smart_norm($v);if(strpos($n,'nao realizada')!==false||strpos($n,'nao realizado')!==false)return 'nao_realizada';if(strpos($n,'program')!==false||strpos($n,'agend')!==false||strpos($n,'pendente')!==false)return 'programada';if(strpos($n,'confirm')!==false)return 'confirmada';if(strpos($n,'cancel')!==false)return 'cancelada';if(strpos($n,'reagend')!==false)return 'reagendada';return 'realizada';}
 private static function smart_find_or_create_municipio($name,$uf='PR'){
  global $wpdb;$name=trim((string)$name);if($name==='')return null;$t=ICD_Visitas_Database::table('municipios');$id=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE nome=%s LIMIT 1",$name));if(!$id){$wpdb->insert($t,['nome'=>$name,'uf'=>strtoupper(substr(trim($uf?:'PR'),0,2)),'ativo'=>1,'created_at'=>current_time('mysql')]);$id=(int)$wpdb->insert_id;}return $id?:null;
 }
 private static function smart_find_or_create_school($name,$create=true,$extra=[]){
  global $wpdb;$name=trim((string)$name);if($name==='')return 0;$t=ICD_Visitas_Database::table('escolas');$id=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE nome=%s LIMIT 1",$name));if(!$id&&$create){$mun=null;if(!empty($extra['municipio']))$mun=self::smart_find_or_create_municipio($extra['municipio'],$extra['uf']??'PR');$wpdb->insert($t,['nome'=>$name,'municipio_id'=>$mun,'ativo'=>1,'created_at'=>current_time('mysql')]);$id=(int)$wpdb->insert_id;}return $id;
 }
 private static function smart_find_or_create_person($kind,$name,$create=true){
  global $wpdb;$name=trim((string)$name);if($name==='')return 0;$table=ICD_Visitas_Database::table($kind==='pedagoga'?'pedagogas':'mediadores');$id=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE nome=%s LIMIT 1",$name));if(!$id&&$create){$data=['nome'=>$name,'ativo'=>1,'created_at'=>current_time('mysql')];if($kind==='mediador')$data['cargo']='Mediador';$wpdb->insert($table,$data);$id=(int)$wpdb->insert_id;}return $id;
 }
 public static function smart_import($path,$entity,$header_row,$mapping,$options=[]){
  $scan=self::smart_scan($path,$header_row);if(is_wp_error($scan))return $scan;$preview=self::smart_preview($path,$entity,$header_row,$mapping,$options);if(is_wp_error($preview))return $preview;if($preview['invalid']>0)return new WP_Error('invalid','Existem linhas com erros na prévia. Corrija a planilha ou o mapeamento antes de importar.');
  global $wpdb;$strategy=($options['duplicate_action']??'update')==='skip'?'skip':'update';$createRelated=!empty($options['create_related']);$created=0;$updated=0;$skipped=0;$relations=0;$fields=self::smart_entities()[$entity]['fields'];$fileHash=@hash_file('sha256',$path)?:md5($path);
  foreach($scan['rows'] as $rn=>$row){if($rn<=$header_row)continue;$has=false;foreach($row as $v)if(trim((string)$v)!==''){$has=true;break;}if(!$has)continue;$d=[];foreach($fields as $field=>$def){$col=$mapping[$field]??'';$d[$field]=$col!==''?trim((string)($row[$col]??'')):'';}
   if($entity==='pedagoga'){
    $t=ICD_Visitas_Database::table('pedagogas');$id=0;if($d['email']!=='')$id=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE email=%s LIMIT 1",sanitize_email($d['email'])));if(!$id)$id=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE nome=%s LIMIT 1",$d['nome']));$data=['updated_at'=>current_time('mysql')];if(!empty($mapping['nome']))$data['nome']=sanitize_text_field($d['nome']);if(!empty($mapping['email']))$data['email']=sanitize_email($d['email']);if(!empty($mapping['telefone']))$data['telefone']=sanitize_text_field($d['telefone']);if(!empty($mapping['observacoes']))$data['observacoes']=sanitize_textarea_field($d['observacoes']);if(!empty($mapping['ativo']))$data['ativo']=self::smart_bool($d['ativo'],1);if($id){if($strategy==='skip'){$skipped++;continue;}$wpdb->update($t,$data,['id'=>$id]);$updated++;}else{$data['created_at']=current_time('mysql');if(!isset($data['ativo']))$data['ativo']=1;$wpdb->insert($t,$data);$created++;}
   }elseif($entity==='mediador'){
    $t=ICD_Visitas_Database::table('mediadores');$id=0;if($d['email']!=='')$id=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE email=%s LIMIT 1",sanitize_email($d['email'])));if(!$id)$id=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE nome=%s LIMIT 1",$d['nome']));$data=['updated_at'=>current_time('mysql')];if(!empty($mapping['nome']))$data['nome']=sanitize_text_field($d['nome']);if(!empty($mapping['cargo']))$data['cargo']=sanitize_text_field($d['cargo']?:'Mediador');if(!empty($mapping['email']))$data['email']=sanitize_email($d['email']);if(!empty($mapping['telefone']))$data['telefone']=sanitize_text_field($d['telefone']);if(!empty($mapping['observacoes']))$data['observacoes']=sanitize_textarea_field($d['observacoes']);if(!empty($mapping['ativo']))$data['ativo']=self::smart_bool($d['ativo'],1);if($id){if($strategy==='skip'){$skipped++;continue;}else{$wpdb->update($t,$data,['id'=>$id]);$updated++;}}else{$data['created_at']=current_time('mysql');if(!isset($data['cargo']))$data['cargo']='Mediador';if(!isset($data['ativo']))$data['ativo']=1;$wpdb->insert($t,$data);$id=(int)$wpdb->insert_id;$created++;}if($id&&!empty($d['escola'])){$sid=self::smart_find_or_create_school($d['escola'],$createRelated);if($sid){$tv=ICD_Visitas_Database::table('vinculos');$exists=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $tv WHERE escola_id=%d AND mediador_id=%d AND ativo=1 LIMIT 1",$sid,$id));if(!$exists){$wpdb->insert($tv,['escola_id'=>$sid,'mediador_id'=>$id,'data_inicio'=>self::smart_date($d['data_inicio']),'ativo'=>1,'created_at'=>current_time('mysql')]);$relations++;}}}
   }elseif($entity==='escola'){
    $t=ICD_Visitas_Database::table('escolas');$id=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE nome=%s LIMIT 1",$d['nome']));$mun=$d['municipio']!==''?self::smart_find_or_create_municipio($d['municipio'],$d['uf']?:'PR'):null;$meta=$d['meta_visitas_semana']!==''?(float)str_replace(',','.',$d['meta_visitas_semana']):null;$data=['updated_at'=>current_time('mysql')];if(!empty($mapping['nome']))$data['nome']=sanitize_text_field($d['nome']);if(!empty($mapping['municipio']))$data['municipio_id']=$mun;if(!empty($mapping['endereco']))$data['endereco']=sanitize_text_field($d['endereco']);if(!empty($mapping['numero']))$data['numero']=sanitize_text_field($d['numero']);if(!empty($mapping['bairro']))$data['bairro']=sanitize_text_field($d['bairro']);if(!empty($mapping['cep']))$data['cep']=sanitize_text_field($d['cep']);if(!empty($mapping['telefone']))$data['telefone']=sanitize_text_field($d['telefone']);if(!empty($mapping['email']))$data['email']=sanitize_email($d['email']);if(!empty($mapping['meta_visitas_semana']))$data['meta_visitas_semana']=$meta;if(!empty($mapping['observacoes']))$data['observacoes']=sanitize_textarea_field($d['observacoes']);if(!empty($mapping['ativo']))$data['ativo']=self::smart_bool($d['ativo'],1);if($id){if($strategy==='skip'){$skipped++;continue;}$wpdb->update($t,$data,['id'=>$id]);$updated++;}else{$data['created_at']=current_time('mysql');if(!isset($data['ativo']))$data['ativo']=1;$wpdb->insert($t,$data);$created++;}
   }elseif($entity==='visita'){
    $date=self::smart_date($d['data_visita']);$sid=self::smart_find_or_create_school($d['escola'],$createRelated);if(!$date||!$sid){$skipped++;continue;}$turno=self::smart_turno($d['turno']);$status=self::smart_status($d['status']);$tipo=sanitize_key($d['tipo']?:'acompanhamento');if($tipo==='')$tipo='acompanhamento';$pedSig=array_map([__CLASS__,'smart_norm'],self::smart_list($d['pedagoga']));sort($pedSig);$medSig=array_map([__CLASS__,'smart_norm'],self::smart_list($d['mediador']));sort($medSig);$sig=['data'=>$date,'escola'=>self::smart_norm($d['escola']),'pedagogas'=>$pedSig,'mediadores'=>$medSig,'turno'=>$turno,'tipo'=>$tipo];$key='smart-'.md5(wp_json_encode($sig));$tv=ICD_Visitas_Database::table('visitas');$vid=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $tv WHERE chave_importacao=%s LIMIT 1",$key));$data=['escola_id'=>$sid,'data_visita'=>$date,'turno'=>$turno,'hora_inicio'=>null,'hora_fim'=>null,'status'=>$status,'tipo'=>$tipo,'observacoes'=>sanitize_textarea_field($d['observacoes']),'origem'=>'importacao','chave_importacao'=>$key,'criado_por'=>get_current_user_id(),'updated_at'=>current_time('mysql')];if($vid){if($strategy==='skip'){$skipped++;continue;}$wpdb->update($tv,$data,['id'=>$vid]);$updated++;$wpdb->delete(ICD_Visitas_Database::table('participantes'),['visita_id'=>$vid]);$wpdb->delete(ICD_Visitas_Database::table('visita_pedagogas'),['visita_id'=>$vid]);}else{$data['created_at']=current_time('mysql');$wpdb->insert($tv,$data);$vid=(int)$wpdb->insert_id;$created++;}
    foreach(self::smart_list($d['pedagoga']) as $name){$pid=self::smart_find_or_create_person('pedagoga',$name,$createRelated);if($pid){$wpdb->query($wpdb->prepare('INSERT IGNORE INTO '.ICD_Visitas_Database::table('visita_pedagogas').' (visita_id,pedagoga_id,created_at) VALUES (%d,%d,%s)',$vid,$pid,current_time('mysql')));if($wpdb->rows_affected)$relations++;}}
    foreach(self::smart_list($d['mediador']) as $name){$mid=self::smart_find_or_create_person('mediador',$name,$createRelated);if($mid){$wpdb->query($wpdb->prepare('INSERT IGNORE INTO '.ICD_Visitas_Database::table('participantes').' (visita_id,mediador_id,presente,created_at) VALUES (%d,%d,1,%s)',$vid,$mid,current_time('mysql')));if($wpdb->rows_affected)$relations++;$tvin=ICD_Visitas_Database::table('vinculos');$exists=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $tvin WHERE escola_id=%d AND mediador_id=%d AND ativo=1 LIMIT 1",$sid,$mid));if(!$exists&&$createRelated){$wpdb->insert($tvin,['escola_id'=>$sid,'mediador_id'=>$mid,'ativo'=>1,'created_at'=>current_time('mysql')]);$relations++;}}}
   }
  }
  ICD_Visitas_Helpers::log('importacao',0,'xlsx_inteligente',['tipo'=>$entity,'criados'=>$created,'atualizados'=>$updated,'ignorados'=>$skipped,'relacoes'=>$relations,'arquivo'=>basename($path),'hash'=>$fileHash]);return ['tipo'=>$entity,'criados'=>$created,'atualizados'=>$updated,'ignorados'=>$skipped,'relacoes'=>$relations];
 }

}
