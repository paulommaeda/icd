<?php
if(!defined('ABSPATH') && PHP_SAPI!=='cli') exit;
/**
 * Pequeno gerador PDF sem dependências externas, dedicado aos relatórios ICD.
 * Usa fontes PDF base (Helvetica/Helvetica-Bold) com WinAnsi, suficiente para pt-BR.
 */
class ICD_Visitas_PDF {
 private $pages=[];
 private $page=-1;
 private $w=210.0;
 private $h=297.0;
 private $logo='';
 private $logoInfo=null;
 private $title;
 private $subtitle='';
 private $issued='';
 private $y=42.0;
 private $textColor=[25,32,28];
 private $green=[25,219,75];
 private $muted=[105,116,109];
 private $border=[222,229,224];

 public function __construct($logo='',$subtitle='',$title='RELATÓRIO DE VISITAS'){
  $this->logo=$logo;
  $this->title=$title;
  $this->subtitle=$subtitle;
  $this->issued=function_exists('wp_date')?wp_date('d/m/Y H:i'):date('d/m/Y H:i');
  if($logo && is_readable($logo)){
   $info=@getimagesize($logo);
   if($info && ($info[2]??0)===IMAGETYPE_JPEG) $this->logoInfo=$info;
  }
 }
 public function addPage(){
  $this->pages[]='';
  $this->page=count($this->pages)-1;
  $this->y=42.0;
  $this->drawHeader();
  $this->drawFooter();
 }
 public function pageCount(){ return count($this->pages); }
 public function getY(){ return $this->y; }
 public function setY($y){ $this->y=(float)$y; }
 public function ensureSpace($mm){ if($this->page<0) $this->addPage(); if($this->y+(float)$mm>272) $this->addPage(); }
 public function gap($mm=3){ $this->y+=(float)$mm; }
 public function separator(){
  $this->ensureSpace(6);
  $this->setDrawColor($this->border);
  $this->line(15,$this->y,195,$this->y);
  $this->y+=6;
 }
 public function visitStart(){
  $this->ensureSpace(75);
  $this->setFillColor([247,250,248]);
  $this->rect(15,$this->y,180,5,'F');
  $this->setFillColor($this->green);
  $this->rect(15,$this->y,3,5,'F');
  $this->y+=9;
 }
 public function field($label,$value,$options=[]){
  $value=trim((string)$value);
  if($value==='') $value='Não informado';
  $labelSize=(float)($options['label_size']??8.5);
  $valueSize=(float)($options['value_size']??10.5);
  $lineHeight=(float)($options['line_height']??5.2);
  $this->ensureSpace(12);
  $this->text(18,$this->y,$label,$labelSize,true,$this->muted);
  $this->y+=4.2;
  $this->writeWrapped(18,$this->y,174,$value,$valueSize,false,[35,45,39],$lineHeight);
  $this->y+=2.2;
 }
 public function heading($text,$keep_with_next=0){
  $this->ensureSpace(11+$keep_with_next);
  $this->gap(4);
  $this->text(18,$this->y,$text,11,true,$this->textColor);
  $this->gap(7);
 }
 public function notes($value,$label='OBSERVAÇÕES'){
  $value=trim((string)$value);
  if($value==='') $value='Sem observações registradas.';
  $this->ensureSpace(15);
  $this->text(18,$this->y,$label,8.5,true,$this->muted);
  $this->y+=4.3;
  $lines=$this->wrap($value,170,10.3,false);
  foreach($lines as $line){
   if($this->y+5.3>272){
    $this->addPage();
    $this->text(18,$this->y,$label.' - CONTINUAÇÃO',8.5,true,$this->muted);
    $this->y+=4.3;
   }
   $this->text(18,$this->y,$line,10.3,false,[35,45,39]);
   $this->y+=5.3;
  }
  $this->y+=2.5;
 }
 public function emptyMessage($text){
  $this->ensureSpace(30);
  $this->setFillColor([247,250,248]);
  $this->rect(15,$this->y,180,24,'F');
  $this->text(22,$this->y+10,$text,11,false,$this->muted);
  $this->y+=30;
 }
 public function output($filename='relatorio.pdf',$dest='D'){
  if(!$this->pages) $this->addPage();
  $count=count($this->pages);
  foreach($this->pages as &$p) $p=str_replace('{nb}',(string)$count,$p);
  unset($p);
  $pdf=$this->build();
  if($dest==='S') return $pdf;
  if(!headers_sent()){
   header('Content-Type: application/pdf');
   header('Content-Disposition: attachment; filename="'.preg_replace('/[^A-Za-z0-9._-]+/','-',basename($filename)).'"');
   header('Content-Length: '.strlen($pdf));
   header('Cache-Control: private, no-store, max-age=0');
  }
  echo $pdf;
  return null;
 }
 private function drawHeader(){
  if($this->logoInfo){
   $ratio=$this->logoInfo[0]/max(1,$this->logoInfo[1]);
   $lh=18.0; $lw=min(38.0,$lh*$ratio);
   $this->imageJpeg(15,10,$lw,$lh);
  }
  $this->text(62,15,$this->title,15,true,[14,22,17]);
  if($this->subtitle!=='') $this->text(62,23,$this->subtitle,9.3,false,$this->muted);
  $this->setDrawColor($this->green);
  $this->line(15,34,195,34,1.2);
 }
 private function drawFooter(){
  $this->setDrawColor($this->border);
  $this->line(15,282,195,282,.4);
  $this->text(15,288,'Emitido em '.$this->issued,8,false,$this->muted);
  $right='Página '.($this->page+1).' de {nb}';
  $x=195-$this->textWidth($right,8,false);
  $this->text($x,288,$right,8,false,$this->muted);
 }
 private function writeWrapped($x,&$y,$width,$text,$size=10,$bold=false,$color=null,$lineHeight=5.2){
  foreach($this->wrap($text,$width,$size,$bold) as $line){
   if($y+$lineHeight>272){ $this->addPage(); $y=$this->y; }
   $this->text($x,$y,$line,$size,$bold,$color?:$this->textColor);
   $y+=$lineHeight;
   $this->y=$y;
  }
 }
 private function wrap($text,$width,$size,$bold=false){
  $text=str_replace(["\r\n","\r"],"\n",(string)$text);
  $out=[];
  foreach(explode("\n",$text) as $paragraph){
   $paragraph=trim($paragraph);
   if($paragraph===''){ $out[]=''; continue; }
   $words=preg_split('/\s+/u',$paragraph,-1,PREG_SPLIT_NO_EMPTY);
   $line='';
   foreach($words as $word){
    $candidate=$line===''?$word:$line.' '.$word;
    if($this->textWidth($candidate,$size,$bold)<=$width){ $line=$candidate; continue; }
    if($line!=='') $out[]=$line;
    if($this->textWidth($word,$size,$bold)<=$width){ $line=$word; continue; }
    // Palavra muito longa: quebra de forma segura.
    $chunk='';
    foreach(preg_split('//u',$word,-1,PREG_SPLIT_NO_EMPTY) as $ch){
     $test=$chunk.$ch;
     if($this->textWidth($test,$size,$bold)>$width && $chunk!==''){ $out[]=$chunk; $chunk=$ch; }
     else $chunk=$test;
    }
    $line=$chunk;
   }
   if($line!=='') $out[]=$line;
  }
  return $out?:[''];
 }
 private function textWidth($text,$size,$bold=false){
  $encoded=$this->encode($text);
  $factor=$bold?.51:.49;
  return strlen($encoded)*$size*$factor*25.4/72;
 }
 private function text($x,$y,$text,$size=10,$bold=false,$color=null){
  if($this->page<0) $this->addPage();
  $c=$color?:$this->textColor;
  $font=$bold?'F2':'F1';
  $encoded=$this->escape($this->encode($text));
  $this->out(sprintf('%.3f %.3f %.3f rg BT /%s %.2f Tf %.2f %.2f Td (%s) Tj ET', $c[0]/255,$c[1]/255,$c[2]/255,$font,$size,$this->mm($x),$this->mm($this->h-$y),$encoded));
 }
 private function line($x1,$y1,$x2,$y2,$width=.3){
  $this->out(sprintf('%.2f w %.2f %.2f m %.2f %.2f l S',$width,$this->mm($x1),$this->mm($this->h-$y1),$this->mm($x2),$this->mm($this->h-$y2)));
 }
 private function rect($x,$y,$w,$h,$style='S'){
  $op=$style==='F'?'f':($style==='DF'||$style==='FD'?'B':'S');
  $this->out(sprintf('%.2f %.2f %.2f %.2f re %s',$this->mm($x),$this->mm($this->h-$y-$h),$this->mm($w),$this->mm($h),$op));
 }
 private function setFillColor($c){ $this->out(sprintf('%.3f %.3f %.3f rg',$c[0]/255,$c[1]/255,$c[2]/255)); }
 private function setDrawColor($c){ $this->out(sprintf('%.3f %.3f %.3f RG',$c[0]/255,$c[1]/255,$c[2]/255)); }
 private function imageJpeg($x,$y,$w,$h){
  if(!$this->logoInfo) return;
  $this->out(sprintf('q %.2f 0 0 %.2f %.2f %.2f cm /I1 Do Q',$this->mm($w),$this->mm($h),$this->mm($x),$this->mm($this->h-$y-$h)));
 }
 private function out($s){ $this->pages[$this->page].=$s."\n"; }
 private function mm($v){ return $v*72/25.4; }
 private function encode($s){
  $s=(string)$s;
  if(function_exists('iconv')){ $c=@iconv('UTF-8','Windows-1252//TRANSLIT',$s); if($c!==false) return $c; }
  return $s;
 }
 private function escape($s){ return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$s); }
 private function build(){
  $objects=[];
  $objects[1]='<< /Type /Catalog /Pages 2 0 R >>';
  $kids=[];
  $font1=3;$font2=4;$imageObj=5;
  $objects[$font1]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
  $objects[$font2]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
  $imgData='';
  if($this->logoInfo && is_readable($this->logo)) $imgData=file_get_contents($this->logo);
  if($imgData!==false && $imgData!==''){
   $objects[$imageObj]='<< /Type /XObject /Subtype /Image /Width '.(int)$this->logoInfo[0].' /Height '.(int)$this->logoInfo[1].' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($imgData).' >>' . "\nstream\n".$imgData."\nendstream";
  } else $objects[$imageObj]='<< /Length 0 >>\nstream\n\nendstream';
  foreach($this->pages as $i=>$content){
   $pageObj=6+$i*2; $contentObj=$pageObj+1; $kids[]=$pageObj.' 0 R';
   $res='<< /Font << /F1 '.$font1.' 0 R /F2 '.$font2.' 0 R >>';
   if($imgData) $res.=' /XObject << /I1 '.$imageObj.' 0 R >>';
   $res.=' >>';
   $objects[$pageObj]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.$this->mm($this->w).' '.$this->mm($this->h).'] /Resources '.$res.' /Contents '.$contentObj.' 0 R >>';
   $objects[$contentObj]='<< /Length '.strlen($content).' >>' . "\nstream\n".$content."endstream";
  }
  $objects[2]='<< /Type /Pages /Kids ['.implode(' ',$kids).'] /Count '.count($kids).' >>';
  ksort($objects);
  $pdf="%PDF-1.4\n%\xE2\xE3\xCF\xD3\n"; $offsets=[0=>0];
  foreach($objects as $num=>$obj){ $offsets[$num]=strlen($pdf); $pdf.=$num." 0 obj\n".$obj."\nendobj\n"; }
  $xref=strlen($pdf); $max=max(array_keys($objects));
  $pdf.="xref\n0 ".($max+1)."\n0000000000 65535 f \n";
  for($i=1;$i<=$max;$i++){ $off=$offsets[$i]??0; $pdf.=sprintf('%010d 00000 n ', $off)."\n"; }
  $pdf.='trailer << /Size '.($max+1).' /Root 1 0 R >>' . "\nstartxref\n".$xref."\n%%EOF";
  return $pdf;
 }
}
