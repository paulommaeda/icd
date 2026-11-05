<?php if (!defined('ABSPATH')) exit; ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php echo esc_attr(get_bloginfo('charset')); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#19db4b">
    <title><?php echo esc_html($title . ' | ICD Visitas'); ?></title>
    <link rel="icon" href="<?php echo esc_url(ICD_VISITAS_URL . 'assets/logo.svg'); ?>" type="image/svg+xml">
    <link rel="apple-touch-icon" href="<?php echo esc_url(ICD_VISITAS_URL . 'assets/icone-192.png'); ?>">
    <link rel="manifest" href="<?php echo esc_url(ICD_Visitas_Portal::url('manifesto')); ?>">
    <?php wp_styles()->do_items($management ? ['icd-visitas-admin', 'icd-visitas-app'] : ['icd-visitas-app']); ?>
</head>
<body class="icd-portal<?php if ($login) echo ' icd-pwa-login-page'; ?><?php if ($management) echo ' icd-portal-management'; ?>">
<main id="icd-conteudo">
    <?php echo $content; // Markup rendered by the ICD screen. ?>
</main>
<?php wp_scripts()->do_items($management ? ['icd-visitas-admin', 'icd-visitas-app'] : ['icd-visitas-app']); ?>
</body>
</html>
