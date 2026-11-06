<?php
if (!defined('ABSPATH')) exit;

class ICD_Visitas_Students {
    public static function init() {
        ICD_Visitas_Portal::register_action('icd_save_aluno', [__CLASS__, 'save']);
        ICD_Visitas_Portal::register_action('icd_transfer_aluno', [__CLASS__, 'transfer']);
        ICD_Visitas_Portal::register_action('icd_observe_aluno', [__CLASS__, 'add_observation']);
        ICD_Visitas_Portal::register_action('icd_report_aluno', [__CLASS__, 'report']);
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

    public static function can_manage() {
        return current_user_can('icd_visitas_manage_cadastros') || current_user_can('icd_visitas_manage_team_students');
    }

    private static function manage_scope($pedagoga = 'p') {
        global $wpdb;
        if (current_user_can('icd_visitas_manage_cadastros')) return '1=1';
        if (!current_user_can('icd_visitas_manage_team_students')) return '1=0';
        return $wpdb->prepare("$pedagoga.ativo = 1 AND $pedagoga.wp_user_id = %d", get_current_user_id());
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

    private static function visit_scope() {
        global $wpdb;
        if (!current_user_can('icd_visitas_manage_visitas') || !self::can_manage()) return '1=0';
        if (current_user_can('icd_visitas_manage_cadastros')) return '1=1';
        $link = ICD_Visitas_Database::table('visita_pedagogas');
        $pedagogas = ICD_Visitas_Database::table('pedagogas');
        return $wpdb->prepare("EXISTS (SELECT 1 FROM $link vp JOIN $pedagogas pg ON pg.id = vp.pedagoga_id
            WHERE vp.visita_id = v.id AND pg.ativo = 1 AND pg.wp_user_id = %d)", get_current_user_id());
    }

    public static function visit_context($id) {
        global $wpdb;
        $visitas = ICD_Visitas_Database::table('visitas');
        $escolas = ICD_Visitas_Database::table('escolas');
        return $wpdb->get_row($wpdb->prepare("SELECT v.id, v.data_visita, e.nome AS escola FROM $visitas v
            JOIN $escolas e ON e.id = v.escola_id WHERE v.id = %d AND v.status = 'realizada' AND " . self::visit_scope(), $id));
    }

    public static function visit_url($id) {
        return $id && self::visit_context($id) ? ICD_Visitas_Public::students_url(0, ['icd_visita' => $id]) : '';
    }

    private static function visit_student_scope($id) {
        global $wpdb;
        $visitas = ICD_Visitas_Database::table('visitas');
        $participantes = ICD_Visitas_Database::table('participantes');
        return $wpdb->prepare("EXISTS (SELECT 1 FROM $visitas v JOIN $participantes pt ON pt.visita_id = v.id
            WHERE v.id = %d AND v.status = 'realizada' AND v.escola_id = a.escola_id
            AND pt.mediador_id = a.mediador_id AND pt.presente = 1 AND " . self::visit_scope() . ')', $id);
    }

    public static function listing($search = '', $visit_id = 0, $managed = false) {
        global $wpdb;
        $where = $managed ? self::manage_scope() : self::mediator_scope();
        if ($visit_id) $where .= ' AND ' . self::visit_student_scope($visit_id);
        if ($search !== '') {
            $where .= $wpdb->prepare(' AND a.nome LIKE %s', '%' . $wpdb->esc_like($search) . '%');
        }
        return $wpdb->get_results('SELECT a.id, a.nome, e.nome AS escola, m.nome AS mediadora FROM '
            . self::student_join() . " WHERE $where ORDER BY a.nome, a.id");
    }

    public static function find($id, $visit_id = 0, $managed = false) {
        global $wpdb;
        $where = $managed ? self::manage_scope() : self::mediator_scope();
        if ($visit_id) $where .= ' AND ' . self::visit_student_scope($visit_id);
        return $wpdb->get_row($wpdb->prepare(
            'SELECT a.*, e.nome AS escola, m.nome AS mediadora, p.nome AS pedagoga FROM '
            . self::student_join() . " WHERE a.id = %d AND $where", $id
        ));
    }

    public static function observation_period($source) {
        $period = [];
        foreach (['icd_registro_de', 'icd_registro_ate'] as $key) {
            $raw = $source[$key] ?? '';
            $period[$key] = ICD_Visitas_Helpers::valid_date($raw);
            if ($raw !== '' && !$period[$key]) return new WP_Error('icd_periodo', 'Informe datas válidas para o período.');
        }
        if ($period['icd_registro_de'] && $period['icd_registro_ate'] && $period['icd_registro_de'] > $period['icd_registro_ate']) {
            return new WP_Error('icd_periodo', 'A data inicial deve ser anterior ou igual à data final.');
        }
        return $period;
    }

    public static function observations($id, $period = []) {
        global $wpdb;
        $table = ICD_Visitas_Database::table('aluno_observacoes');
        $data = 'COALESCE(o.data_registro, DATE(o.created_at))';
        $where = ['a.id = %d', self::mediator_scope()];
        $args = [$id];
        if (!empty($period['icd_registro_de'])) {
            $where[] = "$data >= %s";
            $args[] = $period['icd_registro_de'];
        }
        if (!empty($period['icd_registro_ate'])) {
            $where[] = "$data <= %s";
            $args[] = $period['icd_registro_ate'];
        }
        $notes = $wpdb->get_results($wpdb->prepare(
            "SELECT o.observacao, $data AS data_registro, o.visita_id, u.display_name AS autor FROM " . self::student_join()
            . " JOIN $table o ON a.id = o.aluno_id LEFT JOIN $wpdb->users u ON u.ID = o.autor_id
            WHERE " . implode(' AND ', $where) . ' ORDER BY data_registro, o.created_at, o.id', $args
        ));
        return $wpdb->last_error ? null : $notes;
    }

    public static function observations_for_report($filters, $visit_ids) {
        global $wpdb;
        if (!self::can_view()) return [];
        $table = ICD_Visitas_Database::table('aluno_observacoes');
        $schools = ICD_Visitas_Database::table('escolas');
        $mediators = ICD_Visitas_Database::table('mediadores');
        $date = 'COALESCE(o.data_registro, DATE(o.created_at))';
        $school = 'COALESCE(o.escola_id, a.escola_id)';
        $mediator = 'COALESCE(o.mediador_id, a.mediador_id)';
        $pedagoga = 'CASE WHEN o.mediador_id IS NULL THEN m.pedagoga_id ELSE o.pedagoga_id END';
        $where = ["$date BETWEEN %s AND %s", self::mediator_scope()];
        $args = [$filters['from'], $filters['to']];
        if ($filters['school']) {
            $where[] = "$school = %d";
            $args[] = $filters['school'];
        }
        if ($filters['pedagoga']) {
            $responsavel = "($pedagoga) = %d";
            if ($visit_ids) {
                $responsavel .= ' OR o.visita_id IN (' . implode(',', array_map('intval', $visit_ids)) . ')';
            }
            $where[] = '(' . $responsavel . ')';
            $args[] = $filters['pedagoga'];
        }
        if ($filters['mediador']) {
            $where[] = "$mediator = %d";
            $args[] = $filters['mediador'];
        }
        $notes = $wpdb->get_results($wpdb->prepare(
            "SELECT a.nome AS aluno, o.observacao, o.visita_id, $date AS data_registro,
            registro_escola.nome AS escola, registro_mediador.nome AS mediadora, u.display_name AS autor FROM " . self::student_join()
            . " JOIN $table o ON o.aluno_id = a.id
            LEFT JOIN $schools registro_escola ON registro_escola.id = $school
            LEFT JOIN $mediators registro_mediador ON registro_mediador.id = $mediator
            LEFT JOIN $wpdb->users u ON u.ID = o.autor_id WHERE " . implode(' AND ', $where)
            . ' ORDER BY data_registro, a.nome, o.id', $args
        ));
        if ($wpdb->last_error) throw new RuntimeException('Falha ao consultar os registros dos alunos.');
        return $notes;
    }

    public static function assignment_options($current_id = 0) {
        global $wpdb;
        $table = ICD_Visitas_Database::table('mediadores');
        return $wpdb->get_results($wpdb->prepare("SELECT m.id, m.nome, m.ativo FROM $table m " . self::staff_join()
            . ' WHERE (m.ativo = 1 OR m.id = %d) AND ' . self::manage_scope() . ' ORDER BY m.nome, m.id', $current_id));
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
        return ICD_Visitas_Helpers::page_url('icd-visitas-alunos', $id ? ['icd_aluno' => $id] : []);
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

    public static function save() {
        if (!self::can_manage()) self::fail('Acesso negado.', 403);
        $id = ICD_Visitas_Helpers::id_from($_POST, 'aluno_id');
        self::verify_post('icd_save_aluno_' . $id);
        $nome = sanitize_text_field(wp_unslash($_POST['nome'] ?? ''));
        $nascimento = ICD_Visitas_Helpers::valid_date($_POST['data_nascimento'] ?? '');
        if (!preg_match('/^.{1,190}$/us', $nome)) self::fail('Informe o nome do aluno (até 190 caracteres).', 400);
        if (!$nascimento || $nascimento > current_time('Y-m-d')) self::fail('Informe uma data de nascimento válida.', 400);

        global $wpdb;
        $table = ICD_Visitas_Database::table('alunos');
        $now = current_time('mysql');
        if ($id) {
            if (!self::find($id, 0, true)) self::fail('Aluno indisponível.', 404);
            $result = $wpdb->query($wpdb->prepare('UPDATE ' . self::student_join()
                . ' SET a.nome = %s, a.data_nascimento = %s, a.updated_at = %s WHERE a.id = %d AND ' . self::manage_scope(),
                $nome, $nascimento, $now, $id));
            if ($result === 0 && !self::find($id, 0, true)) self::fail('Aluno indisponível.', 404);
        } else {
            $escolas = ICD_Visitas_Database::table('escolas');
            $mediadores = ICD_Visitas_Database::table('mediadores');
            $escola_id = ICD_Visitas_Helpers::id_from($_POST, 'escola_id');
            $mediador_id = ICD_Visitas_Helpers::id_from($_POST, 'mediador_id');
            $result = $wpdb->query($wpdb->prepare("INSERT INTO $table (nome, data_nascimento, escola_id, mediador_id, created_at, updated_at)
                SELECT %s, %s, e.id, m.id, %s, %s FROM $mediadores m " . self::staff_join()
                . " JOIN $escolas e ON e.id = %d AND e.ativo = 1 WHERE m.id = %d AND m.ativo = 1 AND " . self::manage_scope(),
                $nome, $nascimento, $now, $now, $escola_id, $mediador_id));
            if ($result === 0) self::fail('Selecione uma escola ativa e uma mediadora disponível para seu perfil.', 400);
            $id = $wpdb->insert_id;
        }
        if ($result === false) self::fail('Não foi possível salvar o aluno.', 500);
        wp_safe_redirect(add_query_arg('icd_status', 'salvo', self::admin_url($id)), 303);
        exit;
    }

    public static function transfer() {
        if (!self::can_manage()) self::fail('Acesso negado.', 403);
        $id = ICD_Visitas_Helpers::id_from($_POST, 'aluno_id');
        self::verify_post('icd_transfer_aluno_' . $id);
        $aluno = self::find($id, 0, true);
        if (!$aluno) self::fail('Aluno indisponível.', 404);

        $old = [
            'escola_id' => ICD_Visitas_Helpers::id_from($_POST, 'escola_anterior'),
            'mediador_id' => ICD_Visitas_Helpers::id_from($_POST, 'mediadora_anterior'),
        ];
        $new = [
            'escola_id' => ICD_Visitas_Helpers::id_from($_POST, 'escola_id'),
            'mediador_id' => ICD_Visitas_Helpers::id_from($_POST, 'mediador_id'),
        ];
        $current = ['escola_id' => (int) $aluno->escola_id, 'mediador_id' => (int) $aluno->mediador_id];
        if ($new !== $current) {
            if ($old !== $current) self::fail('Os vínculos mudaram. Atualize a página e tente de novo.', 409);
            global $wpdb;
            $escolas = ICD_Visitas_Database::table('escolas');
            $mediadores = ICD_Visitas_Database::table('mediadores');
            $pedagogas = ICD_Visitas_Database::table('pedagogas');
            $result = $wpdb->query($wpdb->prepare('UPDATE ' . self::student_join() . "
                JOIN $mediadores destino ON destino.id = %d LEFT JOIN $pedagogas dp ON dp.id = destino.pedagoga_id
                JOIN $escolas escola_nova ON escola_nova.id = %d
                SET a.escola_id = escola_nova.id, a.mediador_id = destino.id, a.updated_at = %s
                WHERE a.id = %d AND a.escola_id = %d AND a.mediador_id = %d
                AND (escola_nova.ativo = 1 OR escola_nova.id = a.escola_id)
                AND (destino.ativo = 1 OR destino.id = a.mediador_id)
                AND " . self::manage_scope() . ' AND ' . self::manage_scope('dp'),
                $new['mediador_id'], $new['escola_id'], current_time('mysql'), $id, $old['escola_id'], $old['mediador_id']));
            if ($result === false) self::fail('Não foi possível salvar os vínculos.', 500);
            if (!$result) self::fail('Os vínculos selecionados estão indisponíveis. Atualize a página e tente de novo.', 409);
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

        $today = current_time('Y-m-d');
        $date = ICD_Visitas_Helpers::valid_date($_POST['data_registro'] ?? $today);
        if (!$date || $date > $today) self::fail('Informe uma data de registro válida, até hoje.', 400);
        $visit_id = ICD_Visitas_Helpers::id_from($_POST, 'icd_visita');
        if (isset($_POST['icd_visita']) && !$visit_id) self::fail('Visita inválida.', 400);

        global $wpdb;
        $table = ICD_Visitas_Database::table('aluno_observacoes');
        $where = self::mediator_scope();
        if ($visit_id) $where .= ' AND ' . self::visit_student_scope($visit_id);
        // Confere a equipe e a visita na instrução que grava o contexto do registro.
        $result = $wpdb->query($wpdb->prepare(
            "INSERT INTO $table (aluno_id, autor_id, observacao, created_at, data_registro, visita_id, escola_id, mediador_id, pedagoga_id)
            SELECT a.id, %d, %s, %s, %s, NULLIF(%d, 0), a.escola_id, a.mediador_id, m.pedagoga_id FROM "
            . self::student_join() . " WHERE a.id = %d AND $where",
            get_current_user_id(), $note, current_time('mysql'), $date, $visit_id, $id
        ));
        if ($result === false) self::fail('Não foi possível registrar a observação.', 500);
        if (!$result) self::fail('Aluno indisponível para este registro.', 404);

        $from_admin = !$visit_id && !empty($_POST['icd_admin']) && self::can_manage();
        $url = $from_admin ? self::admin_url($id) : ICD_Visitas_Public::students_url($id, $visit_id ? ['icd_visita' => $visit_id] : []);
        wp_safe_redirect(add_query_arg('icd_status', 'observacao', $url) . '#icd-observacoes', 303);
        exit;
    }

    public static function report() {
        if (!self::can_view()) self::fail('Acesso negado.', 403);
        $id = ICD_Visitas_Helpers::id_from($_POST, 'aluno_id');
        self::verify_post('icd_report_aluno_' . $id);
        $student = self::find($id);
        if (!$student) self::fail('Aluno indisponível.', 404);
        $period = self::observation_period($_POST);
        if (is_wp_error($period)) self::fail($period->get_error_message(), 400);
        $notes = self::observations($id, $period);
        if ($notes === null) self::fail('Não foi possível consultar as observações.', 500);

        $subtitle = 'Histórico de observações';
        if ($period['icd_registro_de']) $subtitle .= ' | Desde ' . mysql2date('d/m/Y', $period['icd_registro_de']);
        if ($period['icd_registro_ate']) $subtitle .= ' | Até ' . mysql2date('d/m/Y', $period['icd_registro_ate']);
        $pdf = new ICD_Visitas_PDF(ICD_VISITAS_DIR . 'assets/logo-report.jpg', $subtitle, 'RELATÓRIO DO ALUNO');
        $pdf->field('ALUNO', $student->nome);
        $pdf->field('DATA DE NASCIMENTO', mysql2date('d/m/Y', $student->data_nascimento));
        $pdf->field('ESCOLA ATUAL', $student->escola ?? '');
        $pdf->field('MEDIADORA ATUAL', $student->mediadora ?? '');
        $pdf->field('PEDAGOGA ATUAL', $student->pedagoga ?? '');
        $pdf->separator();
        if (!$notes) $pdf->emptyMessage('Nenhuma observação registrada.');
        foreach ($notes as $note) {
            $pdf->field($note->visita_id ? 'REGISTRO DE VISITA' : 'REGISTRO DO ALUNO',
                mysql2date('d/m/Y', $note->data_registro) . ' | ' . ($note->autor ?: 'Usuário removido'));
            $pdf->notes($note->observacao);
            $pdf->separator();
        }

        while (ob_get_level()) ob_end_clean();
        $pdf->output('relatorio-aluno-' . $id . '.pdf');
        exit;
    }
}
