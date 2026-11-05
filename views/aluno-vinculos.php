<?php
if (!defined('ABSPATH')) exit;
?>
<label>Escola
    <select name="escola_id" required>
        <option value="">Selecione a escola</option>
        <?php foreach ($schools as $school): ?>
            <option value="<?php echo (int) $school->id; ?>" <?php selected($student->escola_id ?? 0, $school->id); ?>><?php echo esc_html($school->nome . ($school->ativo ? '' : ' (inativa)')); ?></option>
        <?php endforeach; ?>
    </select>
</label>
<label>Mediadora
    <select name="mediador_id" required>
        <option value="">Selecione a mediadora</option>
        <?php foreach ($mediators as $mediator): ?>
            <option value="<?php echo (int) $mediator->id; ?>" <?php selected($student->mediador_id ?? 0, $mediator->id); ?>><?php echo esc_html($mediator->nome . ($mediator->ativo ? '' : ' (inativa)')); ?></option>
        <?php endforeach; ?>
    </select>
</label>
