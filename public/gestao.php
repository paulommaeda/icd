<?php if (!defined('ABSPATH')) exit; ?>
<div class="icd-shell icd-management-shell">
    <header class="icd-app-header">
        <div class="icd-brand">
            <img src="<?php echo esc_url(ICD_VISITAS_URL . 'assets/logo.svg'); ?>" alt="Instituto Caminho de Davi">
            <div><small>Instituto Caminho de Davi</small><h2>Gestão ICD</h2></div>
        </div>
        <a class="icd-edit-btn" href="<?php echo esc_url(ICD_Visitas_Portal::url()); ?>">Voltar ao dashboard</a>
    </header>
    <nav class="icd-card icd-management-nav" aria-label="Áreas de gestão">
        <?php foreach ($pages as $slug => $page): ?>
            <a href="<?php echo esc_url(ICD_Visitas_Helpers::page_url($slug)); ?>"<?php if ($slug === $section) echo ' aria-current="page"'; ?>><?php echo esc_html($page[0]); ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="icd-management-content">
        <?php call_user_func(['ICD_Visitas_Admin', $pages[$section][2]]); ?>
    </div>
    <?php require ICD_VISITAS_DIR . 'public/nav.php'; ?>
</div>
