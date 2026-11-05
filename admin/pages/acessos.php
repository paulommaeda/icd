<?php
if (!defined('ABSPATH')) exit;
if (!ICD_Visitas_Portal::is_wordpress_admin() || !current_user_can('promote_users')) wp_die('Acesso negado.', '', ['response' => 403]);
$roles = ICD_Visitas_Admin::access_roles();
$users = array_filter(get_users(['orderby' => 'display_name']), static function ($user) {
    return !ICD_Visitas_Portal::is_wordpress_admin($user) && current_user_can('promote_user', $user->ID);
});
$post_url = ICD_Visitas_Helpers::action_url();
?>
<div class="wrap icd-admin">
    <h1>Acessos ao portal</h1>
    <p>Defina quem pode acessar o sistema e o que cada pessoa pode consultar ou editar.</p>
    <?php if (isset($_GET['saved'])): ?><div class="notice notice-success"><p>Acesso atualizado.</p></div><?php endif; ?>
    <?php if (current_user_can('create_users')): ?>
        <section class="icd-student-card">
            <h2>Criar conta</h2>
            <form class="icd-form-grid" method="post" action="<?php echo esc_url($post_url); ?>">
                <?php wp_nonce_field('icd_save_access'); ?>
                <input type="hidden" name="action" value="icd_save_access">
                <input type="hidden" name="icd_modo" value="criar">
                <label>Nome<input name="nome" required maxlength="190"></label>
                <label>Nome de usuário<input name="login" autocomplete="off" required maxlength="60"></label>
                <label>E-mail<input type="email" name="email" required maxlength="100"></label>
                <label>Senha inicial<input type="password" name="senha" autocomplete="new-password" required minlength="8"></label>
                <label>Perfil ICD<select name="perfil" required>
                    <option value="">Selecione o perfil</option>
                    <?php foreach ($roles as $role => $label): ?>
                        <option value="<?php echo esc_attr($role); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select></label>
                <button type="submit" class="button button-primary">Criar acesso</button>
            </form>
            <p>Para pedagogas e mediadoras, selecione a nova conta no campo Usuário WordPress do cadastro correspondente.</p>
        </section>
    <?php endif; ?>
    <section class="icd-student-card">
        <h2>Definir o perfil de uma conta existente</h2>
        <form class="icd-form-grid" method="post" action="<?php echo esc_url($post_url); ?>">
            <?php wp_nonce_field('icd_save_access'); ?>
            <input type="hidden" name="action" value="icd_save_access">
            <input type="hidden" name="icd_modo" value="atribuir">
            <label>Usuário
                <select name="usuario" required>
                    <option value="">Selecione o usuário</option>
                    <?php foreach ($users as $user):
                        $profiles = array_intersect_key($roles, array_fill_keys($user->roles, true)); ?>
                        <option value="<?php echo (int) $user->ID; ?>"><?php echo esc_html($user->display_name . ' (' . $user->user_login . ') · ' . ($profiles ? implode(', ', $profiles) : 'Sem perfil ICD')); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Perfil ICD<select name="perfil" required>
                <option value="">Selecione o perfil</option>
                <?php foreach ($roles as $role => $label): ?>
                    <option value="<?php echo esc_attr($role); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
                <option value="remover">Remover perfil ICD</option>
            </select></label>
            <button type="submit" class="button button-primary">Salvar perfil</button>
        </form>
    </section>
</div>
