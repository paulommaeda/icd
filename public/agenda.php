<?php
if(!defined('ABSPATH')) exit;
global $wpdb;
$tv=ICD_Visitas_Database::table('visitas');
$te=ICD_Visitas_Database::table('escolas');
$tm=ICD_Visitas_Database::table('mediadores');
$tp=ICD_Visitas_Database::table('participantes');
$tpg=ICD_Visitas_Database::table('pedagogas');
$tvp=ICD_Visitas_Database::table('visita_pedagogas');
$tvinc=ICD_Visitas_Database::table('vinculos');
$schools=$wpdb->get_results("SELECT id,nome FROM $te WHERE ativo=1 ORDER BY nome");
$meds=$wpdb->get_results("SELECT id,nome FROM $tm WHERE ativo=1 ORDER BY nome");
$peds=$wpdb->get_results("SELECT id,nome FROM $tpg WHERE ativo=1 ORDER BY nome");
$currentPed=ICD_Visitas_Helpers::current_pedagoga_id();
$map=[]; foreach($wpdb->get_results("SELECT escola_id,mediador_id FROM $tvinc WHERE ativo=1") as $x) $map[$x->mediador_id][]=(int)$x->escola_id;
$edit_id=absint($_GET['editar']??0); $edit=null; $editMeds=[]; $editPeds=[];
if($edit_id){
 $edit=$wpdb->get_row($wpdb->prepare("SELECT * FROM $tv WHERE id=%d",$edit_id));
 if($edit){
  $editMeds=array_map('intval',$wpdb->get_col($wpdb->prepare("SELECT mediador_id FROM $tp WHERE visita_id=%d",$edit_id)));
  $editPeds=array_map('intval',$wpdb->get_col($wpdb->prepare("SELECT pedagoga_id FROM $tvp WHERE visita_id=%d",$edit_id)));
 }
}
$from=current_time('Y-m-d'); $to=date('Y-m-d',strtotime($from.' +6 days'));
$rows=$wpdb->get_results($wpdb->prepare("SELECT v.*,e.nome escola,
 (SELECT GROUP_CONCAT(pg.nome SEPARATOR ', ') FROM $tvp vp JOIN $tpg pg ON pg.id=vp.pedagoga_id WHERE vp.visita_id=v.id) pedagogas,
 (SELECT GROUP_CONCAT(m.nome SEPARATOR ', ') FROM $tp p JOIN $tm m ON m.id=p.mediador_id WHERE p.visita_id=v.id) mediadores
 FROM $tv v JOIN $te e ON e.id=v.escola_id
 WHERE v.data_visita BETWEEN %s AND %s AND v.status IN ('programada','confirmada')
 ORDER BY v.data_visita ASC,v.turno ASC,e.nome ASC",$from,$to));
?>
<div class="icd-shell">
<header class="icd-app-header"><div class="icd-brand"><img src="<?php echo esc_url(ICD_VISITAS_URL.'assets/logo.svg');?>" alt="Instituto Caminho de Davi"><div><small>Planejamento</small><h2>Agenda de visitas</h2></div></div></header>
<?php if(isset($_GET['saved'])):?><div class="icd-success">Visita agendada com sucesso.</div><?php endif;?>
<?php if(isset($_GET['updated'])):?><div class="icd-success">Visita atualizada com sucesso.</div><?php endif;?>
<?php if(isset($_GET['deleted'])):?><div class="icd-success">Visita excluída.</div><?php endif;?>
<?php if(isset($_GET['done'])):?><div class="icd-success">Visita marcada como realizada.</div><?php endif;?>

<details class="icd-card icd-filter-card" <?php echo $edit?'open':'';?> id="icd-agenda-form">
 <summary><?php echo $edit?'Editar visita agendada':'+ Agendar nova visita';?><span>⌄</span></summary>
 <form class="icd-front-filters" method="post" action="<?php echo esc_url(ICD_Visitas_Helpers::action_url());?>">
  <?php wp_nonce_field('icd_save_visita');?>
  <input type="hidden" name="action" value="icd_save_visita">
  <input type="hidden" name="id" value="<?php echo (int)($edit->id??0);?>">
  <input type="hidden" name="status" value="<?php echo esc_attr($edit->status??'programada');?>">
  <input type="hidden" name="tipo" value="acompanhamento">
  <input type="hidden" name="origem" value="agenda">
  <input type="hidden" name="redirect_to" value="<?php echo esc_url(ICD_Visitas_Portal::url('agenda'));?>">
  <label>Escola<select required name="escola_id" id="icd-school"><option value="">Selecione</option><?php foreach($schools as $x) echo '<option value="'.$x->id.'" '.selected((int)($edit->escola_id??0),$x->id,false).'>'.esc_html($x->nome).'</option>';?></select></label>
  <div class="icd-two"><label>Data<input required type="date" name="data_visita" min="<?php echo esc_attr(current_time('Y-m-d'));?>" value="<?php echo esc_attr($edit->data_visita??current_time('Y-m-d'));?>"></label><label>Turno<select name="turno"><?php foreach(['manha'=>'Manhã','tarde'=>'Tarde','noite'=>'Noite','integral'=>'Integral'] as $k=>$lab) echo '<option value="'.$k.'" '.selected($edit->turno??'manha',$k,false).'>'.$lab.'</option>';?></select></label></div>
  <fieldset><legend>Pedagoga responsável</legend><?php foreach($peds as $x):$checked=$edit?in_array((int)$x->id,$editPeds,true):((int)$currentPed===(int)$x->id);?><label class="icd-check"><input type="checkbox" name="pedagogas[]" value="<?php echo (int)$x->id;?>" <?php checked($checked);?>> <?php echo esc_html($x->nome);?></label><?php endforeach;?></fieldset>
  <fieldset><legend>Mediadores que serão acompanhados</legend><small class="icd-help">Ao selecionar a escola, aparecem apenas os mediadores vinculados a ela.</small><?php foreach($meds as $x):$ids=implode(',',array_map('intval',$map[$x->id]??[]));?><label class="icd-check icd-mediator-option" data-schools="<?php echo esc_attr($ids);?>"><input type="checkbox" name="mediadores[]" value="<?php echo (int)$x->id;?>" <?php checked(in_array((int)$x->id,$editMeds,true));?>> <?php echo esc_html($x->nome);?></label><?php endforeach;?></fieldset>
  <label>Observações<textarea name="observacoes" rows="4" placeholder="Alguma orientação ou objetivo específico para esta visita?"><?php echo esc_textarea($edit->observacoes??'');?></textarea></label>
  <button class="icd-submit" type="submit"><?php echo $edit?'Salvar alterações':'Agendar visita';?></button>
  <?php if($edit):?><a class="icd-secondary-link" href="<?php echo esc_url(ICD_Visitas_Portal::url('agenda'));?>">Cancelar edição</a><?php endif;?>
 </form>
</details>

<section class="icd-card"><h3>Próximos 7 dias</h3><small><?php echo esc_html(date_i18n('d/m',strtotime($from)));?> a <?php echo esc_html(date_i18n('d/m/Y',strtotime($to)));?></small></section>
<?php if(!$rows):?><div class="icd-card">Nenhuma visita programada para os próximos sete dias.</div><?php endif;?>
<?php $last=''; foreach($rows as $r): $day=$r->data_visita; if($last!==$day):?><h3 class="icd-day"><?php echo esc_html(date_i18n('l, d/m',strtotime($day)));?></h3><?php $last=$day; endif;?>
<article class="icd-card icd-scheduled-card icd-visit-summary-open" data-visit-id="<?php echo (int)$r->id;?>" role="button" tabindex="0">
 <div class="icd-visit-top"><div><b><?php echo esc_html($r->escola);?></b><small><?php echo esc_html(ICD_Visitas_Helpers::turno_label($r->turno));?></small></div><span><?php echo esc_html(ICD_Visitas_Helpers::status_label($r->status));?></span></div>
 <small><strong>Pedagoga:</strong> <?php echo esc_html($r->pedagogas?:'Não informada');?></small>
 <small><strong>Mediadores:</strong> <?php echo esc_html($r->mediadores?:'Não informados');?></small>
 <?php if($r->observacoes):?><p><?php echo esc_html($r->observacoes);?></p><?php endif;?>
 <div class="icd-agenda-actions">
  <button type="button" class="icd-done-btn icd-complete-visit-open" data-visit-id="<?php echo (int)$r->id;?>" data-visit-school="<?php echo esc_attr($r->escola);?>" data-visit-date="<?php echo esc_attr(date_i18n('d/m/Y',strtotime($r->data_visita)));?>" data-visit-notes="<?php echo esc_attr($r->observacoes??'');?>" data-visit-nonce="<?php echo esc_attr(wp_create_nonce('icd_complete_visita_'.$r->id));?>">Visita realizada</button>
  <a class="icd-edit-btn" href="<?php echo esc_url(add_query_arg('editar',(int)$r->id,ICD_Visitas_Portal::url('agenda')));?>#icd-agenda-form">Editar</a>
  <form method="post" action="<?php echo esc_url(ICD_Visitas_Helpers::action_url());?>" onsubmit="return confirm('Deseja realmente excluir esta visita?');"><?php wp_nonce_field('icd_delete_visita_'.$r->id);?><input type="hidden" name="action" value="icd_delete_visita"><input type="hidden" name="id" value="<?php echo (int)$r->id;?>"><input type="hidden" name="redirect_to" value="<?php echo esc_url(ICD_Visitas_Portal::url('agenda'));?>"><button type="submit" class="icd-delete-btn">Excluir</button></form>
 </div>
</article>
<?php endforeach;?>
<div class="icd-front-modal" id="icd-complete-visit-modal" aria-hidden="true">
 <div class="icd-front-modal-backdrop" data-icd-complete-close></div>
 <div class="icd-front-modal-dialog icd-complete-visit-dialog" role="dialog" aria-modal="true" aria-labelledby="icd-complete-visit-title">
  <button type="button" class="icd-front-modal-close" data-icd-complete-close aria-label="Fechar">×</button>
  <div class="icd-complete-visit-head">
   <small>Concluir visita</small>
   <h3 id="icd-complete-visit-title">Visita realizada</h3>
   <p id="icd-complete-visit-context"></p>
  </div>
  <form method="post" action="<?php echo esc_url(ICD_Visitas_Helpers::action_url());?>" id="icd-complete-visit-form" class="icd-mobile-form">
   <input type="hidden" name="action" value="icd_complete_visita">
   <input type="hidden" name="id" id="icd-complete-visit-id" value="">
   <input type="hidden" name="_wpnonce" id="icd-complete-visit-nonce" value="">
   <input type="hidden" name="redirect_to" value="<?php echo esc_url(ICD_Visitas_Portal::url('agenda'));?>">
   <div class="icd-observation-prompt">Antes de concluir, aproveite para registrar o que foi observado na escola. Isso evita precisar editar a visita depois.</div>
   <label>Observações da visita
    <textarea name="observacoes" id="icd-complete-visit-notes" rows="7" placeholder="Ex.: andamento do trabalho dos mediadores, orientações realizadas, pontos de atenção e encaminhamentos."></textarea>
   </label>
   <small class="icd-help">As observações são recomendadas, mas você pode concluir a visita mesmo sem preenchê-las.</small>
   <button type="submit" class="icd-submit icd-confirm-complete-btn">Confirmar visita realizada</button>
   <button type="button" class="icd-secondary-button" data-icd-complete-close>Cancelar</button>
  </form>
 </div>
</div>
<?php require ICD_VISITAS_DIR.'public/nav.php';?>
</div>
<script>document.addEventListener('DOMContentLoaded',function(){
 const s=document.getElementById('icd-school');const opts=[...document.querySelectorAll('.icd-mediator-option')];
 function f(){if(!s)return;const id=s.value;opts.forEach(o=>{const ok=id&&o.dataset.schools.split(',').includes(id);o.style.display=ok?'flex':'none';if(!ok)o.querySelector('input').checked=false;});}
 if(s){s.addEventListener('change',f);f();}
 const modal=document.getElementById('icd-complete-visit-modal');
 const idField=document.getElementById('icd-complete-visit-id');
 const nonceField=document.getElementById('icd-complete-visit-nonce');
 const notes=document.getElementById('icd-complete-visit-notes');
 const context=document.getElementById('icd-complete-visit-context');
 const form=document.getElementById('icd-complete-visit-form');
 function openComplete(btn){
  if(!modal)return;
  idField.value=btn.dataset.visitId||'';
  nonceField.value=btn.dataset.visitNonce||'';
  notes.value=btn.dataset.visitNotes||'';
  context.textContent=(btn.dataset.visitSchool||'Visita')+' · '+(btn.dataset.visitDate||'');
  modal.classList.add('is-open');modal.setAttribute('aria-hidden','false');document.body.classList.add('icd-front-modal-open');
  setTimeout(()=>notes.focus(),120);
 }
 function closeComplete(){if(!modal)return;modal.classList.remove('is-open');modal.setAttribute('aria-hidden','true');document.body.classList.remove('icd-front-modal-open');}
 document.querySelectorAll('.icd-complete-visit-open').forEach(btn=>btn.addEventListener('click',function(e){e.preventDefault();e.stopPropagation();openComplete(btn);}));
 document.querySelectorAll('[data-icd-complete-close]').forEach(el=>el.addEventListener('click',function(e){e.preventDefault();closeComplete();}));
 if(form)form.addEventListener('submit',function(){const b=form.querySelector('button[type=submit]');if(b){b.disabled=true;b.textContent='Registrando...';}});
 document.addEventListener('keydown',function(e){if(e.key==='Escape'&&modal&&modal.classList.contains('is-open'))closeComplete();});
});</script>
