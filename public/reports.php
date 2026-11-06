<?php if(!defined('ABSPATH'))exit; global $wpdb;
$e=ICD_Visitas_Database::table('escolas');$m=ICD_Visitas_Database::table('mediadores');$pg=ICD_Visitas_Database::table('pedagogas');
$today=current_time('Y-m-d');
$from=sanitize_text_field($_GET['de']??$_GET['icd_de']??date('Y-m-01',strtotime($today)));
$to=sanitize_text_field($_GET['ate']??$_GET['icd_ate']??$today);
$school=absint($_GET['escola']??$_GET['icd_escola']??0);$mid=absint($_GET['mediador']??$_GET['icd_mediador']??0);$pid=absint($_GET['pedagoga']??$_GET['icd_pedagoga']??0);
$schools=$wpdb->get_results("SELECT id,nome FROM $e WHERE ativo=1 ORDER BY nome");$meds=$wpdb->get_results("SELECT id,nome FROM $m WHERE ativo=1 ORDER BY nome");$peds=$wpdb->get_results("SELECT id,nome FROM $pg WHERE ativo=1 ORDER BY nome");
?>
<div class="icd-shell icd-reports-page">
 <header class="icd-app-header"><div class="icd-brand"><img src="<?php echo esc_url(ICD_VISITAS_URL.'assets/logo.svg');?>" alt="Instituto Caminho de Davi"><div><small>Documentos</small><h2>Relatórios de visitas</h2></div></div></header>
 <section class="icd-card icd-report-intro"><h3>Relatório de visitas em PDF</h3><p>Selecione o período. Os demais filtros são opcionais. O PDF reúne as visitas <strong>realizadas</strong>, as observações feitas nelas e os registros de alunos disponíveis ao seu perfil.</p></section>
 <section class="icd-card">
  <form id="icd-report-form" class="icd-mobile-form" method="post" action="<?php echo esc_url(ICD_Visitas_Helpers::action_url());?>">
   <?php wp_nonce_field('icd_generate_visits_pdf');?><input type="hidden" name="action" value="icd_generate_visits_pdf">
   <div class="icd-two"><label>Data inicial<input type="date" name="from" required value="<?php echo esc_attr($from);?>"></label><label>Data final<input type="date" name="to" required value="<?php echo esc_attr($to);?>"></label></div>
   <label>Pedagoga<select name="pedagoga"><option value="0">Todas</option><?php foreach($peds as $x)echo '<option value="'.(int)$x->id.'" '.selected($pid,$x->id,false).'>'.esc_html($x->nome).'</option>';?></select></label>
   <label>Escola<select name="school"><option value="0">Todas</option><?php foreach($schools as $x)echo '<option value="'.(int)$x->id.'" '.selected($school,$x->id,false).'>'.esc_html($x->nome).'</option>';?></select></label>
   <label>Mediador acompanhado<select name="mediador"><option value="0">Todos</option><?php foreach($meds as $x)echo '<option value="'.(int)$x->id.'" '.selected($mid,$x->id,false).'>'.esc_html($x->nome).'</option>';?></select></label>
   <div class="icd-report-preview"><strong>Conteúdo do PDF</strong><span>Data, escola, equipe e observações de cada visita realizada.</span>
   <?php if (ICD_Visitas_Students::can_view()): ?><span>Os registros do período aparecem na visita vinculada, quando ela está no PDF. Os demais ficam em uma seção própria.</span><?php endif; ?></div>
   <button id="icd-report-generate" class="icd-submit icd-pdf-button" type="submit"><span>▣</span> Gerar relatório em PDF</button>
   <div id="icd-report-status" class="icd-report-status" role="status" aria-live="polite" hidden></div>
  </form>
 </section>
 <section id="icd-report-ready" class="icd-card icd-report-ready" hidden>
  <div class="icd-report-ready-head">
   <span class="icd-report-ready-icon">✓</span>
   <div><strong>Relatório pronto</strong><small>Escolha o que deseja fazer com o PDF.</small></div>
  </div>
  <div class="icd-report-actions">
   <button id="icd-report-share" class="icd-report-action icd-report-action-share" type="button"><span>↗</span> Compartilhar / Salvar</button>
   <button id="icd-report-download" class="icd-report-action icd-report-action-download" type="button"><span>↓</span> Baixar PDF</button>
   <button id="icd-report-view" class="icd-report-action icd-report-action-view" type="button"><span>◉</span> Visualizar</button>
  </div>
  <p class="icd-report-mobile-help">No iPhone, use <strong>Compartilhar / Salvar</strong> para abrir o menu nativo e escolher “Salvar em Arquivos”, AirDrop, WhatsApp, Mail e outros aplicativos.</p>
 </section>
 <?php require ICD_VISITAS_DIR.'public/nav.php';?>
</div>
