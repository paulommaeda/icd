<?php
if (!defined('ABSPATH')) exit;
$id = ICD_Visitas_Helpers::id_from($_GET, 'icd_aluno');
$student = $id ? ICD_Visitas_Students::find($id) : null;
$base_url = ICD_Visitas_Public::students_url();
$in_admin = false;
?>
<div class="icd-shell icd-students-page">
    <header class="icd-app-header">
        <div class="icd-brand">
            <img src="<?php echo esc_url(ICD_VISITAS_URL . 'assets/logo.svg'); ?>" alt="Instituto Caminho de Davi">
            <div><small>Acompanhamento pedagógico</small><h2><?php echo esc_html($student ? $student->nome : 'Alunos'); ?></h2></div>
        </div>
    </header>
    <?php if ($id): ?><p><a href="<?php echo esc_url($base_url); ?>">← Lista de alunos</a></p><?php endif; ?>
    <?php if ($id && !$student): ?>
        <p class="icd-card">Aluno indisponível.</p>
    <?php elseif ($student): ?>
        <section class="icd-card icd-student-card">
            <h3>Dados do aluno</h3>
            <dl class="icd-student-data">
                <dt>Nascimento</dt><dd><?php echo esc_html(mysql2date('d/m/Y', $student->data_nascimento)); ?></dd>
                <dt>Escola</dt><dd><?php echo esc_html($student->escola ?? 'Não informada'); ?></dd>
                <dt>Mediadora</dt><dd><?php echo esc_html($student->mediadora ?? 'Não informada'); ?></dd>
                <dt>Pedagoga</dt><dd><?php echo esc_html($student->pedagoga ?? 'Sem vínculo'); ?></dd>
            </dl>
            <?php if (current_user_can('icd_visitas_manage_cadastros')): ?>
                <a class="icd-edit-btn" href="<?php echo esc_url(ICD_Visitas_Students::admin_url($id) . '#icd-trocar-vinculos'); ?>">Trocar vínculos no painel</a>
            <?php endif; ?>
        </section>
        <?php if (($_GET['icd_status'] ?? '') === 'observacao'): ?>
            <p class="icd-success" role="status">Observação registrada.</p>
        <?php endif; ?>
        <?php require ICD_VISITAS_DIR . 'views/aluno-observacoes.php'; ?>
    <?php else: ?>
        <?php
        $search = sanitize_text_field(wp_unslash($_GET['icd_busca'] ?? ''));
        $rows = ICD_Visitas_Students::listing($search);
        ?>
        <section class="icd-card">
            <h3>Alunos acompanhados</h3>
            <?php if (current_user_can('icd_visitas_manage_cadastros')): ?>
                <p><a href="<?php echo esc_url(ICD_Visitas_Students::admin_url()); ?>">Gerenciar alunos no painel</a></p>
            <?php endif; ?>
            <?php if (ICD_Visitas_Students::can_view_team()): ?><p><a href="#icd-equipe">Consultar equipe</a></p><?php endif; ?>
            <form class="icd-mobile-form" method="get" action="<?php echo esc_url($base_url); ?>">
                <?php if (!get_option('permalink_structure')): ?>
                    <input type="hidden" name="page_id" value="<?php echo (int) get_option('icd_visitas_students_page_id'); ?>">
                <?php endif; ?>
                <label>Nome do aluno<input type="search" name="icd_busca" value="<?php echo esc_attr($search); ?>"></label>
                <button type="submit" class="icd-submit">Buscar alunos</button>
                <?php if ($search !== ''): ?><a href="<?php echo esc_url($base_url); ?>">Limpar busca</a><?php endif; ?>
            </form>
            <?php foreach ($rows as $row): ?>
                <a class="icd-student-row" href="<?php echo esc_url(ICD_Visitas_Public::students_url($row->id)); ?>">
                    <strong><?php echo esc_html($row->nome); ?></strong>
                    <small><?php echo esc_html(($row->escola ?? 'Escola não informada') . ' · ' . ($row->mediadora ?? 'Mediadora não informada')); ?></small>
                </a>
            <?php endforeach; ?>
            <?php if (!$rows): ?><p class="icd-empty">Nenhum aluno encontrado.</p><?php endif; ?>
        </section>
        <?php if (ICD_Visitas_Students::can_view_team()): ?>
            <section class="icd-card" id="icd-equipe">
                <h3>Equipe</h3>
                <?php
                $staff = ['Mediadoras' => ICD_Visitas_Students::mediators()];
                if (ICD_Visitas_Students::can_view_all()) $staff['Pedagogas'] = ICD_Visitas_Students::pedagogues();
                foreach ($staff as $label => $people):
                ?>
                    <h4><?php echo esc_html($label); ?></h4>
                    <?php if (!$people): ?><p class="icd-empty">Nenhum cadastro disponível.</p><?php endif; ?>
                    <?php foreach ($people as $person): ?>
                        <details class="icd-student-staff">
                            <summary><?php echo esc_html($person->nome . ($person->ativo ? '' : ' (inativa)')); ?></summary>
                            <dl class="icd-student-data">
                                <?php if (property_exists($person, 'pedagoga')): ?>
                                    <dt>Cargo</dt><dd><?php echo esc_html($person->cargo); ?></dd>
                                    <dt>Pedagoga</dt><dd><?php echo esc_html($person->pedagoga ?? 'Sem vínculo'); ?></dd>
                                <?php endif; ?>
                                <dt>E-mail</dt><dd><?php echo esc_html($person->email ?: 'Não informado'); ?></dd>
                                <dt>Telefone</dt><dd><?php echo esc_html($person->telefone ?: 'Não informado'); ?></dd>
                            </dl>
                            <?php if ($person->observacoes): ?><p class="icd-student-staff-notes"><?php echo esc_html($person->observacoes); ?></p><?php endif; ?>
                        </details>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    <?php endif; ?>
    <?php require ICD_VISITAS_DIR . 'public/nav.php'; ?>
</div>
