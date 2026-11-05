<?php
if (!defined('ABSPATH')) exit;
if (!ICD_Visitas_Students::can_view_all()) wp_die('Acesso negado.', '', ['response' => 403]);

$id = ICD_Visitas_Helpers::id_from($_GET, 'icd_aluno');
$student = $id ? ICD_Visitas_Students::find($id) : null;
$manage = current_user_can('icd_visitas_manage_cadastros');
$in_admin = true;
$base_url = ICD_Visitas_Students::admin_url();
$post_url = ICD_Visitas_Helpers::action_url();
$search = sanitize_text_field(wp_unslash($_GET['icd_busca'] ?? ''));
$messages = ['salvo' => 'Cadastro salvo.', 'vinculos' => 'Vínculos atualizados.', 'observacao' => 'Observação registrada.'];
$message = $messages[sanitize_key($_GET['icd_status'] ?? '')] ?? '';
if ($manage && (!$id || $student)) {
    global $wpdb;
    $escolas = ICD_Visitas_Database::table('escolas');
    $mediadores = ICD_Visitas_Database::table('mediadores');
    $schools = $wpdb->get_results($wpdb->prepare("SELECT id, nome, ativo FROM $escolas WHERE ativo = 1 OR id = %d ORDER BY nome, id", $student->escola_id ?? 0));
    $mediators = $wpdb->get_results($wpdb->prepare("SELECT id, nome, ativo FROM $mediadores WHERE ativo = 1 OR id = %d ORDER BY nome, id", $student->mediador_id ?? 0));
}
?>
<div class="wrap icd-admin icd-students-admin">
    <h1><?php echo esc_html($student ? $student->nome : 'Alunos'); ?></h1>
    <?php if ($id): ?><p><a href="<?php echo esc_url($base_url); ?>">← Todos os alunos</a></p><?php endif; ?>
    <?php if ($message): ?><div class="notice notice-success"><p><?php echo esc_html($message); ?></p></div><?php endif; ?>

    <?php if ($id && !$student): ?>
        <p>Aluno indisponível.</p>
    <?php elseif ($student): ?>
        <p><strong>Nascimento</strong> <?php echo esc_html(mysql2date('d/m/Y', $student->data_nascimento)); ?></p>
        <p><strong>Escola</strong> <?php echo esc_html($student->escola ?? 'Não informada'); ?></p>
        <p><strong>Mediadora</strong> <?php echo esc_html($student->mediadora ?? 'Não informada'); ?></p>
        <p><strong>Pedagoga</strong> <?php echo esc_html($student->pedagoga ?? 'Sem vínculo'); ?></p>
        <?php if ($manage): ?>
            <section class="icd-student-card" id="icd-trocar-vinculos">
                <h2>Trocar vínculos</h2>
                <form class="icd-form-grid" method="post" action="<?php echo esc_url($post_url); ?>">
                    <?php wp_nonce_field('icd_transfer_aluno_' . $student->id); ?>
                    <input type="hidden" name="action" value="icd_transfer_aluno">
                    <input type="hidden" name="aluno_id" value="<?php echo (int) $student->id; ?>">
                    <input type="hidden" name="escola_anterior" value="<?php echo (int) $student->escola_id; ?>">
                    <input type="hidden" name="mediadora_anterior" value="<?php echo (int) $student->mediador_id; ?>">
                    <?php require ICD_VISITAS_DIR . 'views/aluno-vinculos.php'; ?>
                    <button type="submit" class="button button-primary">Salvar vínculos</button>
                </form>
            </section>
            <details class="icd-student-card">
                <summary>Editar nome e data de nascimento</summary>
                <form class="icd-form-grid" method="post" action="<?php echo esc_url($post_url); ?>">
                    <?php wp_nonce_field('icd_save_aluno_' . $student->id); ?>
                    <input type="hidden" name="action" value="icd_save_aluno">
                    <input type="hidden" name="aluno_id" value="<?php echo (int) $student->id; ?>">
                    <label>Nome<input name="nome" maxlength="190" value="<?php echo esc_attr($student->nome); ?>" required></label>
                    <label>Data de nascimento<input type="date" name="data_nascimento" value="<?php echo esc_attr($student->data_nascimento); ?>" max="<?php echo esc_attr(current_time('Y-m-d')); ?>" required></label>
                    <button type="submit" class="button button-primary">Salvar cadastro</button>
                </form>
            </details>
        <?php endif; ?>
        <?php require ICD_VISITAS_DIR . 'views/aluno-observacoes.php'; ?>
    <?php else: ?>
        <?php if ($manage): ?>
            <details class="icd-student-card" <?php if ($search === '') echo 'open'; ?>>
                <summary>Cadastrar aluno</summary>
                <form class="icd-form-grid" method="post" action="<?php echo esc_url($post_url); ?>">
                    <?php wp_nonce_field('icd_save_aluno_0'); ?>
                    <input type="hidden" name="action" value="icd_save_aluno">
                    <label>Nome<input name="nome" maxlength="190" required></label>
                    <label>Data de nascimento<input type="date" name="data_nascimento" max="<?php echo esc_attr(current_time('Y-m-d')); ?>" required></label>
                    <?php require ICD_VISITAS_DIR . 'views/aluno-vinculos.php'; ?>
                    <button type="submit" class="button button-primary">Cadastrar aluno</button>
                </form>
            </details>
        <?php endif; ?>
        <p><a href="<?php echo esc_url(ICD_Visitas_Public::students_url() . '#icd-equipe'); ?>">Consultar equipe no portal</a></p>
        <form method="get" action="<?php echo esc_url(ICD_Visitas_Helpers::page_url('icd-visitas-alunos')); ?>" class="icd-student-search">
            <?php ICD_Visitas_Helpers::page_input('icd-visitas-alunos'); ?>
            <label for="icd-busca-aluno">Nome do aluno</label>
            <input type="search" name="icd_busca" id="icd-busca-aluno" value="<?php echo esc_attr($search); ?>">
            <button type="submit" class="button">Buscar</button>
            <?php if ($search !== ''): ?><a href="<?php echo esc_url($base_url); ?>">Limpar</a><?php endif; ?>
        </form>
        <?php $rows = ICD_Visitas_Students::listing($search); ?>
        <table class="widefat striped">
            <thead><tr><th scope="col">Nome</th><th scope="col">Escola</th><th scope="col">Mediadora</th><th scope="col">Ações</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): $detail_url = ICD_Visitas_Students::admin_url($row->id); ?>
                <tr>
                    <td><strong><a href="<?php echo esc_url($detail_url); ?>"><?php echo esc_html($row->nome); ?></a></strong></td>
                    <td><?php echo esc_html($row->escola ?? 'Não informada'); ?></td>
                    <td><?php echo esc_html($row->mediadora ?? 'Não informada'); ?></td>
                    <td>
                        <a href="<?php echo esc_url($detail_url . '#icd-observacoes'); ?>">Observações e PDF</a>
                        <?php if ($manage): ?> · <a href="<?php echo esc_url($detail_url . '#icd-trocar-vinculos'); ?>">Trocar vínculos</a><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="4">Nenhum aluno encontrado.</td></tr><?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
