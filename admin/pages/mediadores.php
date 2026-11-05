<?php
if (!defined('ABSPATH')) exit;
global $wpdb;
$mediadores = ICD_Visitas_Database::table('mediadores');
$pedagogas = ICD_Visitas_Database::table('pedagogas');
$rows = $wpdb->get_results("SELECT m.*, p.nome AS pedagoga_nome, p.ativo AS pedagoga_ativa
    FROM $mediadores m LEFT JOIN $pedagogas p ON p.id = m.pedagoga_id ORDER BY m.nome, m.id");
$pedagogues = $wpdb->get_results("SELECT id, nome FROM $pedagogas WHERE ativo = 1 ORDER BY nome, id");
$id = ICD_Visitas_Helpers::id_from($_GET, 'edit');
$edit = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $mediadores WHERE id = %d", $id)) : null;
$users = get_users(['orderby' => 'display_name']);
$post_url = ICD_Visitas_Helpers::action_url();
$base_url = ICD_Visitas_Helpers::page_url('icd-visitas-mediadores');
?>
<div class="wrap icd-admin">
    <h1>Mediadores</h1>
    <p>Use a coluna <strong>Pedagoga responsável</strong> para trocar a supervisão. A nova pedagoga passa a consultar a mediadora e os alunos vinculados a ela.</p>
    <?php if (isset($_GET['vinculo_salvo'])): ?><div class="notice notice-success"><p>Vínculo de supervisão atualizado.</p></div><?php endif; ?>
    <?php if (isset($_GET['saved'])): ?><div class="notice notice-success"><p>Cadastro salvo.</p></div><?php endif; ?>
    <form class="icd-form-grid" method="post" action="<?php echo esc_url($post_url); ?>">
        <?php wp_nonce_field('icd_save_mediador'); ?>
        <input type="hidden" name="action" value="icd_save_mediador">
        <input type="hidden" name="id" value="<?php echo (int) ($edit->id ?? 0); ?>">
        <label>Nome<input required name="nome" value="<?php echo esc_attr($edit->nome ?? ''); ?>"></label>
        <label>Cargo<input name="cargo" value="<?php echo esc_attr($edit->cargo ?? 'Mediador'); ?>"></label>
        <label>E-mail<input type="email" name="email" value="<?php echo esc_attr($edit->email ?? ''); ?>"></label>
        <label>Telefone<input name="telefone" value="<?php echo esc_attr($edit->telefone ?? ''); ?>"></label>
        <label>Usuário WordPress
            <select name="wp_user_id">
                <option value="0">Sem login vinculado</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?php echo (int) $user->ID; ?>" <?php selected($edit->wp_user_id ?? 0, $user->ID); ?>><?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php if (!$edit): ?>
            <label>Pedagoga responsável
                <select name="pedagoga_id">
                    <option value="0">Sem vínculo</option>
                    <?php foreach ($pedagogues as $pedagoga): ?>
                        <option value="<?php echo (int) $pedagoga->id; ?>"><?php echo esc_html($pedagoga->nome); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>
        <label class="full">Observações<textarea name="observacoes"><?php echo esc_textarea($edit->observacoes ?? ''); ?></textarea></label>
        <?php if ($edit): ?><label class="icd-check full"><input type="checkbox" name="ativo" <?php checked($edit->ativo); ?>> Ativo</label><?php endif; ?>
        <button class="button button-primary" type="submit"><?php echo $edit ? 'Salvar alterações' : 'Adicionar mediadora'; ?></button>
        <?php if ($edit): ?><a href="<?php echo esc_url($base_url); ?>">Cancelar edição</a><?php endif; ?>
    </form>
    <table class="widefat striped">
        <thead><tr><th scope="col">Nome</th><th scope="col">Cargo</th><th scope="col">E-mail</th><th scope="col">Telefone</th><th scope="col">Pedagoga responsável</th><th scope="col">Ações</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><?php echo esc_html($row->nome . ($row->ativo ? '' : ' (inativa)')); ?></td>
                <td><?php echo esc_html($row->cargo ?? ''); ?></td>
                <td><?php echo esc_html($row->email ?? ''); ?></td>
                <td><?php echo esc_html($row->telefone ?? ''); ?></td>
                <td>
                    <form method="post" class="icd-quick-pedagoga" action="<?php echo esc_url($post_url); ?>">
                        <?php wp_nonce_field('icd_transfer_mediador_' . $row->id, '_wpnonce', false); ?>
                        <input type="hidden" name="action" value="icd_transfer_mediador">
                        <input type="hidden" name="mediador_id" value="<?php echo (int) $row->id; ?>">
                        <input type="hidden" name="pedagoga_anterior" value="<?php echo (int) $row->pedagoga_id; ?>">
                        <select name="pedagoga_id" aria-label="<?php echo esc_attr('Pedagoga responsável por ' . $row->nome); ?>">
                            <option value="0">Sem vínculo</option>
                            <?php if ($row->pedagoga_id && !$row->pedagoga_ativa): ?>
                                <option value="<?php echo (int) $row->pedagoga_id; ?>" selected><?php echo esc_html($row->pedagoga_nome ? $row->pedagoga_nome . ' (inativa)' : 'Pedagoga indisponível'); ?></option>
                            <?php endif; ?>
                            <?php foreach ($pedagogues as $pedagoga): ?>
                                <option value="<?php echo (int) $pedagoga->id; ?>" <?php selected($row->pedagoga_id, $pedagoga->id); ?>><?php echo esc_html($pedagoga->nome); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="button">Salvar vínculo</button>
                    </form>
                </td>
                <td><a href="<?php echo esc_url(add_query_arg('edit', $row->id, $base_url)); ?>">Editar</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="6">Nenhuma mediadora cadastrada.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
