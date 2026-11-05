<?php
if (!defined('ABSPATH')) exit;
class ICD_Visitas_Permissions {
 public static function activate(){
  $caps=['icd_visitas_view_dashboard','icd_visitas_manage_visitas','icd_visitas_manage_cadastros','icd_visitas_view_reports','icd_visitas_manage_settings','icd_visitas_view_all_students'];
  $admin=get_role('administrator'); if($admin) foreach($caps as $c) $admin->add_cap($c);
  $coord=get_role('icd_coordenador') ?: add_role('icd_coordenador','Coordenador ICD',['read'=>true]);
  if($coord) foreach(['read','icd_visitas_view_dashboard','icd_visitas_manage_visitas','icd_visitas_manage_cadastros','icd_visitas_view_reports','icd_visitas_view_all_students'] as $c)$coord->add_cap($c);
  $ped=get_role('icd_pedagoga') ?: add_role('icd_pedagoga','Pedagoga ICD',['read'=>true]);
  if($ped) foreach(['read','icd_visitas_view_dashboard','icd_visitas_manage_visitas','icd_visitas_view_reports','icd_visitas_view_team_students'] as $c)$ped->add_cap($c);
  $med=get_role('icd_mediador') ?: add_role('icd_mediador','Mediador ICD',['read'=>true]);
  if($med) foreach(['read','icd_visitas_view_dashboard','icd_visitas_view_own_students'] as $c)$med->add_cap($c);
  $direcao=get_role('icd_direcao') ?: add_role('icd_direcao','Direção ICD',['read'=>true]);
  if($direcao) foreach(['read','icd_visitas_view_dashboard','icd_visitas_view_reports','icd_visitas_view_all_students'] as $c)$direcao->add_cap($c);
  $con=get_role('icd_consulta') ?: add_role('icd_consulta','Consulta ICD',['read'=>true]);
  if($con) foreach(['read','icd_visitas_view_dashboard','icd_visitas_view_reports'] as $c)$con->add_cap($c);
 }
}
