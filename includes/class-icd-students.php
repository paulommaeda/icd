<?php
if (!defined('ABSPATH')) exit;

class ICD_Visitas_Students {
    public static function init() {
        add_action('admin_post_icd_save_aluno', [__CLASS__, 'save']);
        add_action('admin_post_icd_transfer_aluno', [__CLASS__, 'transfer']);
        add_action('admin_post_icd_observe_aluno', [__CLASS__, 'add_observation']);
        add_action('admin_post_icd_report_aluno', [__CLASS__, 'report']);
    }

    public static function can_view_all() {
        return current_user_can('icd_visitas_view_all_students') || current_user_can('icd_visitas_manage_cadastros');
    }

    public static function can_view_team() {
        return self::can_view_all() || current_user_can('icd_visitas_view_team_students');
    }

    public static function can_view() {
        return is_user_logged_in() && (self::can_view_team() || current_user_can('icd_visitas_view_own_students'));
    }

    public static function can_observe() {
        return current_user_can('icd_visitas_manage_cadastros')
            || current_user_can('icd_visitas_view_team_students')
            || current_user_can('icd_visitas_view_own_students');
    }

    private static function mediator_scope() {
        global $wpdb;
        if (!self::can_view()) return '1=0';
        if (self::can_view_all()) return '1=1';

        $user_id = get_current_user_id();
        return $wpdb->prepare(
            '((%d = 1 AND m.ativo = 1 AND m.wp_user_id = %d) OR (%d = 1 AND p.ativo = 1 AND p.wp_user_id = %d))',
            (int) current_user_can('icd_visitas_view_own_students'), $user_id,
            (int) current_user_can('icd_visitas_view_team_students'), $user_id
        );
    }

    private static function staff_join() {
        $pedagogas = ICD_Visitas_Database::table('pedagogas');
        return "LEFT JOIN $pedagogas p ON p.id = m.pedagoga_id";
    }

    private static function student_join() {
        $alunos = ICD_Visitas_Database::table('alunos');
        $escolas = ICD_Visitas_Database::table('escolas');
        $mediadores = ICD_Visitas_Database::table('mediadores');
        return "$alunos a LEFT JOIN $mediadores m ON m.id = a.mediador_id
            LEFT JOIN $escolas e ON e.id = a.escola_id " . self::staff_join();
    }

    public static function listing($search = '') {
        global $wpdb;
        $where = self::mediator_scope();
        if ($search !== '') {
            $where .= $wpdb->prepare(' AND a.nome LIKE %s', '%' . $wpdb->esc_like($search) . '%');
        }
        return $wpdb->get_results('SELECT a.id, a.nome, e.nome AS escola, m.nome AS mediadora FROM '
            . self::student_join() . " WHERE $where ORDER BY a.nome, a.id");
    }

    public static function find($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            'SELECT a.*, e.nome AS escola, m.nome AS mediadora, p.nome AS pedagoga FROM '
            . self::student_join() . ' WHERE a.id = %d AND ' . self::mediator_scope(), $id
        ));
    }

    public static function observations($id) {
        global $wpdb;
        $table = ICD_Visitas_Database::table('aluno_observacoes');
        $notes = $wpdb->get_results($wpdb->prepare(
            'SELECT o.observacao, o.created_at, u.display_name AS autor FROM ' . self::student_join()
            . " JOIN $table o ON a.id = o.aluno_id LEFT JOIN $wpdb->users u ON u.ID = o.autor_id
            WHERE a.id = %d AND " . self::mediator_scope() . ' ORDER BY o.created_at, o.id', $id
        ));
        return $wpdb->last_error ? null : $notes;
    }

    public static function mediators() {
        global $wpdb;
        if (!self::can_view_team()) return [];
        $mediadores = ICD_Visitas_Database::table('mediadores');
        return $wpdb->get_results("SELECT m.*, p.nome AS pedagoga FROM $mediadores m " . self::staff_join()
            . ' WHERE ' . self::mediator_scope() . ' ORDER BY m.nome, m.id');
    }

    public static function pedagogues() {
        global $wpdb;
        if (!self::can_view_all()) return [];
        $table = ICD_Visitas_Database::table('pedagogas');
        return $wpdb->get_results("SELECT * FROM $table ORDER BY nome, id");
    }

    public static function admin_url($id = 0) {
        $url = admin_url('admin.php?page=icd-visitas-alunos');
        return $id ? add_query_arg('icd_aluno', $id, $url) : $url;
    }

    private static function verify_post($nonce) {
        nocache_headers();
        header('Cache-Control: private, no-store, max-age=0');
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') self::fail('Solicitação inválida.', 405);
        check_admin_referer($nonce);
    }

    private static function fail($message, $status) {
        wp_die(esc_html($message), '', ['response' => $status, 'back_link' => true]);
    }

    private static function validate_links($escola_id, $mediador_id, $aluno = null) {
        global $wpdb;
        $links = ['escolas' => $escola_id, 'mediadores' => $mediador_id];
        $existing = ['escolas' => (int) ($aluno->escola_id ?? 0), 'mediadores' => (int) ($aluno->mediador_id ?? 0)];
        foreach ($links as $kind => $id) {
            if ($id && $id === $existing[$kind]) continue;
            $table = ICD_Visitas_Database::table($kind);
            if (!$id || !$wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE id = %d AND ativo = 1", $id))) {
                self::fail('Selecione uma escola e uma mediadora ativas para os novos vínculos.', 400);
            }
        }
    }

    public static function save() {
        if (!current_user_can('icd_visitas_manage_cadastros')) self::fail('Acesso negado.', 403);
        $id = ICD_Visitas_Helpers::id_from($_POST, 'aluno_id');
        self::verify_post('icd_save_aluno_' . $id);
        $nome = sanitize_text_field(wp_unslash($_POST['nome'] ?? ''));
        $nascimento = sanitize_text_field(wp_unslash($_POST['data_nascimento'] ?? ''));
        if (!preg_match('/^.{1,190}$/us', $nome)) self::fail('Informe o nome do aluno (até 190 caracteres).', 400);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $nascimento, $date)
            || (int) $date[1] < 1000 || !checkdate((int) $date[2], (int) $date[3], (int) $date[1])
            || $nascimento > current_time('Y-m-d')) {
            self::fail('Informe uma data de nascimento válida.', 400);
        }

        global $wpdb;
        $table = ICD_Visitas_Database::table('alunos');
        $now = current_time('mysql');
        $data = ['nome' => $nome, 'data_nascimento' => $nascimento, 'updated_at' => $now];
        if ($id) {
            if (!self::find($id)) self::fail('Aluno indisponível.', 404);
            $result = $wpdb->update($table, $data, ['id' => $id]);
        } else {
            $escola_id = ICD_Visitas_Helpers::id_from($_POST, 'escola_id');
            $mediador_id = ICD_Visitas_Helpers::id_from($_POST, 'mediador_id');
            self::validate_links($escola_id, $mediador_id);
            $result = $wpdb->insert($table, $data + [
                'escola_id' => $escola_id, 'mediador_id' => $mediador_id, 'created_at' => $now,
            ]);
            $id = $wpdb->insert_id;
        }
        if ($result === false) self::fail('Não foi possível salvar o aluno.', 500);
        wp_safe_redirect(add_query_arg('icd_status', 'salvo', self::admin_url($id)), 303);
        exit;
    }

    public static function transfer() {
        if (!current_user_can('icd_visitas_manage_cadastros')) self::fail('Acesso negado.', 403);
        $id = ICD_Visitas_Helpers::id_from($_POST, 'aluno_id');
        self::verify_post('icd_transfer_aluno_' . $id);
        $aluno = self::find($id);
        if (!$aluno) self::fail('Aluno indisponível.', 404);

        $old = [
            'escola_id' => ICD_Visitas_Helpers::id_from($_POST, 'escola_anterior'),
            'mediador_id' => ICD_Visitas_Helpers::id_from($_POST, 'mediadora_anterior'),
        ];
        $new = [
            'escola_id' => ICD_Visitas_Helpers::id_from($_POST, 'escola_id'),
            'mediador_id' => ICD_Visitas_Helpers::id_from($_POST, 'mediador_id'),
        ];
        if ($new !== ['escola_id' => (int) $aluno->escola_id, 'mediador_id' => (int) $aluno->mediador_id]) {
            self::validate_links($new['escola_id'], $new['mediador_id'], $aluno);
            global $wpdb;
            $result = $wpdb->update(ICD_Visitas_Database::table('alunos'),
                $new + ['updated_at' => current_time('mysql')], ['id' => $id] + $old);
            if ($result === false) self::fail('Não foi possível salvar os vínculos.', 500);
            if (!$result) self::fail('Os vínculos mudaram enquanto a página estava aberta. Atualize a página e tente de novo.', 409);
            ICD_Visitas_Helpers::log('aluno', $id, 'trocar_vinculos', $new, $old);
        }
        wp_safe_redirect(add_query_arg('icd_status', 'vinculos', self::admin_url($id)), 303);
        exit;
    }

    public static function add_observation() {
        if (!self::can_observe()) self::fail('Acesso negado.', 403);
        $id = ICD_Visitas_Helpers::id_from($_POST, 'aluno_id');
        self::verify_post('icd_observe_aluno_' . $id);
        $note = sanitize_textarea_field(wp_unslash($_POST['observacao'] ?? ''));
        if ($note === '' || mb_strlen($note, 'UTF-8') > 10000) {
            self::fail('Escreva uma observação de até 10.000 caracteres.', 400);
        }

        global $wpdb;
        $table = ICD_Visitas_Database::table('aluno_observacoes');
        // Confere o vínculo na mesma instrução que grava a observação.
        $result = $wpdb->query($wpdb->prepare(
            "INSERT INTO $table (aluno_id, autor_id, observacao, created_at)
            SELECT a.id, %d, %s, %s FROM " . self::student_join() . ' WHERE a.id = %d AND ' . self::mediator_scope(),
            get_current_user_id(), $note, current_time('mysql'), $id
        ));
        if ($result === false) self::fail('Não foi possível registrar a observação.', 500);
        if (!$result) self::fail('Aluno indisponível.', 404);

        $from_admin = !empty($_POST['icd_admin']) && self::can_view_all();
        $url = $from_admin ? self::admin_url($id) : ICD_Visitas_Public::students_url($id);
        wp_safe_redirect(add_query_arg('icd_status', 'observacao', $url) . '#icd-observacoes', 303);
        exit;
    }

    public static function report() {
        if (!self::can_view()) self::fail('Acesso negado.', 403);
        $id = ICD_Visitas_Helpers::id_from($_POST, 'aluno_id');
        self::verify_post('icd_report_aluno_' . $id);
        $student = self::find($id);
        if (!$student) self::fail('Aluno indisponível.', 404);
        $notes = self::observations($id);
        if ($notes === null) self::fail('Não foi possível consultar as observações.', 500);

        $pdf = new ICD_Visitas_PDF(ICD_VISITAS_DIR . 'assets/logo-report.jpg', 'Histórico de observações', 'RELATÓRIO DO ALUNO');
        $pdf->field('ALUNO', $student->nome);
        $pdf->field('DATA DE NASCIMENTO', mysql2date('d/m/Y', $student->data_nascimento));
        $pdf->field('ESCOLA ATUAL', $student->escola ?? '');
        $pdf->field('MEDIADORA ATUAL', $student->mediadora ?? '');
        $pdf->field('PEDAGOGA ATUAL', $student->pedagoga ?? '');
        $pdf->separator();
        if (!$notes) $pdf->emptyMessage('Nenhuma observação registrada.');
        foreach ($notes as $note) {
            $pdf->field('REGISTRO', mysql2date('d/m/Y H:i', $note->created_at) . ' | ' . ($note->autor ?: 'Usuário removido'));
            $pdf->notes($note->observacao);
            $pdf->separator();
        }

        while (ob_get_level()) ob_end_clean();
        $pdf->output('relatorio-aluno-' . $id . '.pdf');
        exit;
    }
}
