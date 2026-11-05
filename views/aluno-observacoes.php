<?php
if (!defined('ABSPATH')) exit;
$notes = ICD_Visitas_Students::observations((int) $student->id);
$post_url = ICD_Visitas_Helpers::action_url();
?>
<section class="icd-card icd-student-card" id="icd-observacoes">
    <h3>Histórico de observações</h3>
    <?php if ($notes === null): ?>
        <p role="alert">Não foi possível consultar o histórico.</p>
    <?php else: ?>
        <?php foreach ($notes as $note): ?>
            <article class="icd-student-note">
                <small><strong><?php echo esc_html($note->autor ?: 'Usuário removido'); ?></strong> · <?php echo esc_html(mysql2date('d/m/Y H:i', $note->created_at)); ?></small>
                <p><?php echo esc_html($note->observacao); ?></p>
            </article>
        <?php endforeach; ?>
        <?php if (!$notes): ?><p>Nenhuma observação registrada.</p><?php endif; ?>
    <?php endif; ?>
    <?php if (ICD_Visitas_Students::can_observe()): ?>
        <form class="icd-mobile-form" method="post" action="<?php echo esc_url($post_url); ?>">
            <?php wp_nonce_field('icd_observe_aluno_' . $student->id); ?>
            <input type="hidden" name="action" value="icd_observe_aluno">
            <input type="hidden" name="aluno_id" value="<?php echo (int) $student->id; ?>">
            <?php if ($in_admin): ?><input type="hidden" name="icd_admin" value="1"><?php endif; ?>
            <label>Nova observação<textarea name="observacao" rows="4" maxlength="10000" required></textarea></label>
            <button type="submit" class="<?php echo $in_admin ? 'button button-primary' : 'icd-submit'; ?>">Registrar observação</button>
        </form>
    <?php endif; ?>
    <form method="post" class="icd-student-download" action="<?php echo esc_url($post_url); ?>" data-icd-download>
        <?php wp_nonce_field('icd_report_aluno_' . $student->id); ?>
        <input type="hidden" name="action" value="icd_report_aluno">
        <input type="hidden" name="aluno_id" value="<?php echo (int) $student->id; ?>">
        <button type="submit" class="<?php echo $in_admin ? 'button' : 'icd-edit-btn'; ?>">Baixar relatório em PDF</button>
    </form>
</section>
