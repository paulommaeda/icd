<?php
if (!defined('ABSPATH')) exit;
$period = ICD_Visitas_Students::observation_period($_GET);
$period_error = is_wp_error($period) ? $period->get_error_message() : '';
$notes = $period_error ? [] : ICD_Visitas_Students::observations((int) $student->id, $period);
$visit_id = (int) ($visit_context->id ?? 0);
$detail_url = $in_admin ? ICD_Visitas_Students::admin_url($student->id)
    : ICD_Visitas_Public::students_url($student->id, $visit_id ? ['icd_visita' => $visit_id] : []);
$post_url = ICD_Visitas_Helpers::action_url();
$today = current_time('Y-m-d');
$default_date = min($visit_context->data_visita ?? $today, $today);
?>
<section class="icd-card icd-student-card" id="icd-observacoes">
    <h3>Histórico de observações</h3>
    <?php if (ICD_Visitas_Students::can_observe()): ?>
        <form class="icd-mobile-form" method="post" action="<?php echo esc_url($post_url); ?>">
            <?php wp_nonce_field('icd_observe_aluno_' . $student->id); ?>
            <input type="hidden" name="action" value="icd_observe_aluno">
            <input type="hidden" name="aluno_id" value="<?php echo (int) $student->id; ?>">
            <?php if ($in_admin): ?><input type="hidden" name="icd_admin" value="1"><?php endif; ?>
            <?php if ($visit_id): ?><input type="hidden" name="icd_visita" value="<?php echo $visit_id; ?>"><?php endif; ?>
            <label>Data do encontro ou da observação
                <input type="date" name="data_registro" value="<?php echo esc_attr($default_date); ?>" max="<?php echo esc_attr($today); ?>" required>
            </label>
            <label><?php echo $visit_id ? 'Observação do aluno nesta visita' : 'Registro do aluno'; ?>
                <textarea name="observacao" rows="4" maxlength="10000" required></textarea>
            </label>
            <button type="submit" class="<?php echo $in_admin ? 'button button-primary' : 'icd-submit'; ?>">Salvar registro</button>
        </form>
    <?php endif; ?>
    <form class="icd-mobile-form icd-student-period" method="get" action="<?php echo esc_url($detail_url . '#icd-observacoes'); ?>">
        <?php if ($in_admin) ICD_Visitas_Helpers::page_input('icd-visitas-alunos'); else ICD_Visitas_Portal::route_input('alunos'); ?>
        <input type="hidden" name="icd_aluno" value="<?php echo (int) $student->id; ?>">
        <?php if ($visit_id): ?><input type="hidden" name="icd_visita" value="<?php echo $visit_id; ?>"><?php endif; ?>
        <div class="icd-two">
            <label>De<input type="date" name="icd_registro_de" value="<?php echo esc_attr($period_error ? '' : $period['icd_registro_de']); ?>"></label>
            <label>Até<input type="date" name="icd_registro_ate" value="<?php echo esc_attr($period_error ? '' : $period['icd_registro_ate']); ?>"></label>
        </div>
        <button type="submit" class="<?php echo $in_admin ? 'button' : 'icd-edit-btn'; ?>">Filtrar por data</button>
        <?php if ($period_error || $period['icd_registro_de'] || $period['icd_registro_ate']): ?>
            <a href="<?php echo esc_url($detail_url . '#icd-observacoes'); ?>">Limpar filtro</a>
        <?php endif; ?>
    </form>
    <?php if ($period_error): ?>
        <p role="alert"><?php echo esc_html($period_error); ?></p>
    <?php elseif ($notes === null): ?>
        <p role="alert">Não foi possível consultar o histórico.</p>
    <?php else: ?>
        <?php foreach ($notes as $note): ?>
            <article class="icd-student-note">
                <small><time datetime="<?php echo esc_attr($note->data_registro); ?>"><?php echo esc_html(mysql2date('d/m/Y', $note->data_registro)); ?></time>
                    · <strong><?php echo esc_html($note->autor ?: 'Usuário removido'); ?></strong><?php if ($note->visita_id): ?> · Registro de visita<?php endif; ?></small>
                <p><?php echo esc_html($note->observacao); ?></p>
            </article>
        <?php endforeach; ?>
        <?php if (!$notes): ?><p>Nenhuma observação para o período.</p><?php endif; ?>
        <form method="post" class="icd-student-download" action="<?php echo esc_url($post_url); ?>" data-icd-download>
            <?php wp_nonce_field('icd_report_aluno_' . $student->id); ?>
            <input type="hidden" name="action" value="icd_report_aluno">
            <input type="hidden" name="aluno_id" value="<?php echo (int) $student->id; ?>">
            <input type="hidden" name="icd_registro_de" value="<?php echo esc_attr($period['icd_registro_de']); ?>">
            <input type="hidden" name="icd_registro_ate" value="<?php echo esc_attr($period['icd_registro_ate']); ?>">
            <button type="submit" class="<?php echo $in_admin ? 'button' : 'icd-edit-btn'; ?>">Baixar relatório em PDF</button>
        </form>
    <?php endif; ?>
</section>
