<?php
if(!defined('ABSPATH')) exit;
$uid=get_current_user_id();
$smartKey='icd_visitas_smart_import_'.$uid;
$state=get_transient($smartKey); if(!is_array($state))$state=[];
$step=sanitize_key($_GET['smart_step']??(empty($state)?'upload':'tipo'));
$entities=ICD_Visitas_Importer::smart_entities();
$smartResult=get_transient('icd_visitas_smart_import_result_'.$uid); if($smartResult) delete_transient('icd_visitas_smart_import_result_'.$uid);
$legacyPreview=get_transient('icd_visitas_import_preview_'.$uid);$legacyResult=get_transient('icd_visitas_import_result_'.$uid);if($legacyResult)delete_transient('icd_visitas_import_result_'.$uid);
$error=''; if(!empty($_GET['smart_error']))$error=rawurldecode(sanitize_text_field(wp_unslash($_GET['smart_error'])));
$action=admin_url('admin-post.php');
function icd_imp_form_open($actionUrl,$nonce,$actionName,$class=''){
 echo '<form method="post" class="'.esc_attr($class).'" action="'.esc_url($actionUrl).'">'; wp_nonce_field($nonce); echo '<input type="hidden" name="action" value="'.esc_attr($actionName).'">';
}
?>
<div class="wrap icd-admin icd-importer">
 <div class="icd-title"><img src="<?php echo esc_url(ICD_VISITAS_URL.'assets/logo.svg');?>" alt=""><div><h1>Importação inteligente XLSX</h1><p>Faça cargas de pedagogas, mediadores, escolas e visitas sem depender de uma planilha com colunas fixas.</p></div></div>
 <?php if($error):?><div class="notice notice-error"><p><?php echo esc_html($error);?></p></div><?php endif;?>
 <?php if($smartResult):?><div class="notice notice-success"><p><strong>Importação concluída.</strong> <?php echo (int)$smartResult['criados'];?> criado(s), <?php echo (int)$smartResult['atualizados'];?> atualizado(s), <?php echo (int)$smartResult['ignorados'];?> ignorado(s) e <?php echo (int)$smartResult['relacoes'];?> vínculo(s)/relação(ões) processado(s).</p></div><?php endif;?>

 <div class="icd-import-steps" aria-label="Etapas da importação">
  <?php $steps=['upload'=>'1. Arquivo','tipo'=>'2. Tipo','mapeamento'=>'3. Colunas','confirmar'=>'4. Prévia'];$keys=array_keys($steps);$cur=array_search($step,$keys,true);if($cur===false)$cur=0;foreach($steps as $k=>$label):$ix=array_search($k,$keys,true);?>
   <span class="<?php echo $ix===$cur?'is-current':($ix<$cur?'is-done':'');?>"><?php echo esc_html($label);?></span>
  <?php endforeach;?>
 </div>

 <?php if($step==='upload'||empty($state['file'])):?>
  <div class="icd-panel icd-import-hero">
   <h2>1. Envie a planilha</h2>
   <p>O sistema lê a primeira aba do arquivo, tenta localizar automaticamente a linha de cabeçalho e mostra as colunas encontradas antes de importar qualquer informação.</p>
   <form method="post" enctype="multipart/form-data" class="icd-import-submit-lock" action="<?php echo esc_url($action);?>">
    <?php wp_nonce_field('icd_smart_import_upload');?><input type="hidden" name="action" value="icd_smart_import_upload">
    <label class="icd-file-drop"><strong>Arquivo XLSX</strong><input type="file" name="arquivo" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required><span>Limite: 15 MB</span></label>
    <button class="button button-primary button-hero">Ler planilha</button>
   </form>
  </div>
 <?php elseif($step==='tipo'):?>
  <div class="icd-panel">
   <h2>2. O que você quer importar?</h2>
   <p><strong>Arquivo:</strong> <?php echo esc_html($state['filename']??basename($state['file']));?> · <?php echo (int)($state['scan']['data_count']??0);?> linhas de dados estimadas.</p>
   <?php icd_imp_form_open($action,'icd_smart_import_type','icd_smart_import_type','icd-import-submit-lock');?>
    <div class="icd-import-types">
     <?php foreach($entities as $key=>$ent):?>
      <label><input type="radio" name="entity" value="<?php echo esc_attr($key);?>" required><span><strong><?php echo esc_html($ent['label']);?></strong><small><?php echo $key==='visita'?'Data, escola, pedagoga, mediadores e observações':'Cadastro de '.$ent['label'];?></small></span></label>
     <?php endforeach;?>
    </div>
    <h3>Linha de cabeçalho</h3>
    <p class="description">Detectamos automaticamente a linha <?php echo (int)$state['header_row'];?>. Se a planilha tiver um título antes das colunas, você pode corrigir aqui.</p>
    <select name="header_row" class="icd-header-row-select">
     <?php foreach(($state['scan']['candidate_rows']??[]) as $rn=>$preview):?><option value="<?php echo (int)$rn;?>" <?php selected((int)$rn,(int)$state['header_row']);?>>Linha <?php echo (int)$rn;?> — <?php echo esc_html(wp_html_excerpt($preview,110,'…'));?></option><?php endforeach;?>
    </select>
    <div class="icd-import-actions"><button class="button button-primary button-hero">Continuar para relacionar colunas</button></div>
   </form>
  </div>
 <?php elseif($step==='mapeamento'&&!empty($state['entity'])): $entity=$state['entity'];$def=$entities[$entity];$columns=$state['scan']['columns']??[];$suggest=$state['suggestions']??[];?>
  <div class="icd-panel">
   <h2>3. Relacione as colunas</h2>
   <p>Você escolheu importar <strong><?php echo esc_html($def['label']);?></strong>. Para cada campo do sistema, escolha a coluna correspondente do Excel. Os campos com <span class="icd-required">*</span> são obrigatórios.</p><?php if($entity==='visita'):?><p class="description">Em Pedagoga(s) e Mediador(es), uma mesma célula pode conter vários nomes separados por vírgula, ponto e vírgula, barra vertical ou quebra de linha.</p><?php endif;?>
   <?php icd_imp_form_open($action,'icd_smart_import_map','icd_smart_import_map','icd-import-submit-lock');?>
    <div class="icd-mapping-table">
     <div class="icd-mapping-head"><span>Campo do sistema</span><span>Coluna do XLSX</span><span>Exemplos encontrados</span></div>
     <?php foreach($def['fields'] as $field=>$f): $sel=$state['mapping'][$field]??($suggest[$field]??'');?>
      <div class="icd-mapping-row">
       <label for="map-<?php echo esc_attr($field);?>"><strong><?php echo esc_html($f['label']);?><?php if(!empty($f['required'])):?><span class="icd-required"> *</span><?php endif;?></strong></label>
       <select id="map-<?php echo esc_attr($field);?>" name="map[<?php echo esc_attr($field);?>]">
        <option value="">— Não importar este campo —</option>
        <?php foreach($columns as $col=>$meta): $sampleText=!empty($meta['samples'])?implode(' · ',array_map(fn($v)=>wp_html_excerpt($v,36,'…'),$meta['samples'])):'—';?><option value="<?php echo esc_attr($col);?>" data-samples="<?php echo esc_attr($sampleText);?>" <?php selected($sel,$col);?>><?php echo esc_html($col.' — '.$meta['label']);?></option><?php endforeach;?>
       </select>
       <div class="icd-map-samples"><?php if($sel&&!empty($columns[$sel]['samples']))echo esc_html(implode(' · ',array_map(fn($v)=>wp_html_excerpt($v,36,'…'),$columns[$sel]['samples'])));else echo '—';?></div>
      </div>
     <?php endforeach;?>
    </div>
    <div class="icd-import-options">
     <label><strong>Se o cadastro já existir</strong><select name="duplicate_action"><option value="update">Atualizar com os dados do XLSX</option><option value="skip">Ignorar a linha</option></select></label>
     <?php if(in_array($entity,['visita','mediador'],true)):?><label class="icd-check"><input type="checkbox" name="create_related" value="1" checked><span><strong>Criar relacionados ausentes</strong><small><?php echo $entity==='visita'?'Cria escola, pedagoga e mediador caso o nome ainda não exista.':'Se a coluna Escola estiver mapeada, cria a escola e o vínculo quando necessário.';?></small></span></label><?php endif;?>
    </div>
    <div class="icd-import-actions"><a class="button" href="<?php echo esc_url(add_query_arg(['page'=>'icd-visitas-importacao','smart_step'=>'tipo'],admin_url('admin.php')));?>">Voltar</a><button class="button button-primary button-hero">Validar e ver prévia</button></div>
   </form>
  </div>
 <?php elseif($step==='confirmar'&&!empty($state['preview'])): $p=$state['preview'];?>
  <div class="icd-panel">
   <h2>4. Confira antes de importar</h2>
   <div class="icd-kpis compact"><div><strong><?php echo (int)$p['total'];?></strong><span>Linhas</span></div><div><strong><?php echo (int)$p['valid'];?></strong><span>Prontas</span></div><div><strong><?php echo (int)$p['invalid'];?></strong><span>Com erro</span></div><div><strong><?php echo (int)$p['warnings'];?></strong><span>Avisos</span></div></div>
   <?php if($p['invalid']):?><div class="notice notice-error inline"><p>Existem linhas com erro. Volte ao mapeamento ou corrija o XLSX antes de confirmar.</p></div><?php else:?><div class="notice notice-success inline"><p>As linhas estão prontas para a carga. Nenhum dado foi gravado até este momento.</p></div><?php endif;?>
   <div class="icd-table-wrap"><table class="widefat striped icd-preview-table"><thead><tr><th>Linha</th><?php foreach($entities[$state['entity']]['fields'] as $f=>$meta):if(empty($state['mapping'][$f]))continue;?><th><?php echo esc_html($meta['label']);?></th><?php endforeach;?><th>Validação</th></tr></thead><tbody>
    <?php foreach($p['rows'] as $row):?><tr><td><?php echo (int)$row['row'];?></td><?php foreach($entities[$state['entity']]['fields'] as $f=>$meta):if(empty($state['mapping'][$f]))continue;?><td><?php echo esc_html(wp_html_excerpt((string)($row['data'][$f]??''),90,'…'));?></td><?php endforeach;?><td><?php if($row['errors']):?><span class="icd-import-badge error">Erro</span><small><?php echo esc_html(implode(' ',$row['errors']));?></small><?php elseif($row['warnings']):?><span class="icd-import-badge warn">Aviso</span><small><?php echo esc_html(implode(' ',$row['warnings']));?></small><?php else:?><span class="icd-import-badge ok">Pronto</span><?php endif;?></td></tr><?php endforeach;?>
   </tbody></table></div>
   <div class="icd-import-actions"><a class="button" href="<?php echo esc_url(add_query_arg(['page'=>'icd-visitas-importacao','smart_step'=>'mapeamento'],admin_url('admin.php')));?>">Voltar ao mapeamento</a><?php if(!$p['invalid']):?><?php icd_imp_form_open($action,'icd_smart_import_confirm','icd_smart_import_confirm','icd-import-submit-lock');?><button class="button button-primary button-hero">Confirmar importação de <?php echo (int)$p['valid'];?> linhas</button></form><?php endif;?></div>
  </div>
 <?php elseif($step==='resultado'&&$smartResult):?>
  <div class="icd-panel"><h2>Carga concluída</h2><p>O arquivo foi processado e o resultado está indicado na mensagem acima.</p><a class="button button-primary" href="<?php echo esc_url(add_query_arg(['page'=>'icd-visitas-importacao'],admin_url('admin.php')));?>">Fazer nova importação</a></div>
 <?php else:?>
  <div class="icd-panel"><p>A sessão de importação não está mais disponível.</p><a class="button button-primary" href="<?php echo esc_url(add_query_arg(['page'=>'icd-visitas-importacao'],admin_url('admin.php')));?>">Recomeçar</a></div>
 <?php endif;?>

 <?php if(!empty($state['file'])&&$step!=='upload'):?>
  <div class="icd-import-reset"><?php icd_imp_form_open($action,'icd_smart_import_reset','icd_smart_import_reset');?><button class="button-link-delete" type="submit">Cancelar esta importação e apagar o arquivo temporário</button></form></div>
 <?php endif;?>

 <details class="icd-panel icd-legacy-import">
  <summary><strong>Importação histórica: Geral Visitas.xlsx</strong></summary>
  <p>Use esta opção somente para a planilha histórica no formato original que analisamos. Para novas cargas, prefira o importador inteligente acima.</p>
  <?php if($legacyResult):?><div class="notice notice-success inline"><p><strong>Importação histórica concluída.</strong> <?php echo (int)$legacyResult['visitas_criadas'];?> visitas e <?php echo (int)$legacyResult['participacoes_criadas'];?> participações novas foram criadas.</p></div><?php endif;?>
  <form method="post" enctype="multipart/form-data" class="icd-import-submit-lock" action="<?php echo esc_url($action);?>"><?php wp_nonce_field('icd_import_preview');?><input type="hidden" name="action" value="icd_import_preview"><input type="file" name="arquivo" accept=".xlsx" required><button class="button">Analisar planilha histórica</button></form>
  <?php if($legacyPreview&&!is_wp_error($legacyPreview['preview'])):$lp=$legacyPreview['preview'];?><div class="icd-kpis compact"><div><strong><?php echo (int)$lp['mediadores'];?></strong><span>Mediadores</span></div><div><strong><?php echo (int)$lp['escolas'];?></strong><span>Escolas</span></div><div><strong><?php echo (int)$lp['visitas'];?></strong><span>Visitas</span></div><div><strong><?php echo (int)$lp['participacoes'];?></strong><span>Participações</span></div></div><?php icd_imp_form_open($action,'icd_import_confirm','icd_import_confirm','icd-import-submit-lock');?><button class="button">Confirmar importação histórica</button></form><?php endif;?>
 </details>
</div>
