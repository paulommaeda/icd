<?php
if (!defined('ABSPATH')) exit;

class ICD_Visitas_Portal {
    private const SCREENS = [
        'visitas' => ['visitas', 'Dashboard', 'dashboard', 'icd_dashboard'],
        'nova-visita' => ['nova-visita', 'Registrar visita', 'nova_visita', 'icd_nova_visita'],
        'agenda' => ['agenda', 'Agenda', 'agenda', 'icd_agenda'],
        'minhas-visitas' => ['minhas-visitas', 'Histórico', 'minhas_visitas', 'icd_minhas_visitas'],
        'relatorios' => ['relatorios-visitas', 'Relatórios de visitas', 'relatorios', 'icd_relatorios'],
        'perfil' => ['perfil', 'Meu perfil', 'perfil', 'icd_perfil'],
        'alunos' => ['icd-alunos', 'Alunos', 'students', 'icd_alunos'],
        'gestao' => ['icd-gestao', 'Gestão ICD'],
        'acesso' => ['icd-acesso', 'Acessar o sistema'],
        'acao' => ['icd-acao'],
        'ajax' => ['icd-servico'],
        'manifesto' => ['icd-manifesto'],
    ];

    private static $actions = ['acao' => [], 'ajax' => []];
    private static $serving = false;

    public static function init() {
        add_action('template_redirect', [__CLASS__, 'serve'], -100);
        add_action('wp_loaded', [__CLASS__, 'restrict_wordpress'], -100);
        add_action('login_init', [__CLASS__, 'restrict_login'], -100);
        add_filter('login_url', [__CLASS__, 'login_url'], 10, 3);
        add_filter('logout_url', [__CLASS__, 'logout_url']);
        add_filter('show_admin_bar', [__CLASS__, 'show_admin_bar'], 99);
        add_filter('rest_pre_dispatch', [__CLASS__, 'restrict_rest'], 10, 3);
        add_filter('wp_die_handler', [__CLASS__, 'error_handler']);

        foreach (self::SCREENS as $screen) {
            if (isset($screen[3])) add_shortcode($screen[3], ['ICD_Visitas_Public', $screen[2]]);
        }
    }

    public static function register_action($action, $callback, $ajax = false) {
        self::$actions[$ajax ? 'ajax' : 'acao'][$action] = $callback;
        add_action(($ajax ? 'wp_ajax_' : 'admin_post_') . $action, $callback);
    }

    private static function pretty_urls() {
        $structure = (string) get_option('permalink_structure');
        return $structure !== '' && strpos($structure, 'index.php') === false;
    }

    public static function url($route = 'visitas', $args = []) {
        if (self::pretty_urls()) return add_query_arg($args, home_url('/' . self::SCREENS[$route][0] . '/'));
        return add_query_arg(['icd_portal' => $route] + $args, home_url('/'));
    }

    public static function route_input($route) {
        if (!self::pretty_urls()) {
            echo '<input type="hidden" name="icd_portal" value="' . esc_attr($route) . '">';
        }
    }

    public static function is_wordpress_admin($user = null) {
        $user = $user ?: wp_get_current_user();
        return in_array('administrator', (array) $user->roles, true) || (is_multisite() && is_super_admin($user->ID));
    }

    public static function show_admin_bar($show) {
        return self::is_wordpress_admin() ? $show : false;
    }

    public static function restrict_wordpress() {
        global $pagenow;
        if (in_array($pagenow, ['xmlrpc.php', 'wp-links-opml.php', 'wp-comments-post.php', 'wp-trackback.php',
            'wp-mail.php', 'wp-signup.php', 'wp-activate.php'], true)) {
            nocache_headers();
            self::render_error('Rota indisponível.', '', ['response' => 404]);
        }
        if (!is_admin() || self::is_wordpress_admin()) return;
        nocache_headers();
        if (wp_doing_ajax()) wp_send_json_error(['message' => 'Acesso restrito à administração do WordPress.'], 403);
        if ($pagenow === 'admin-post.php') self::render_error('Acesso restrito à administração do WordPress.', '', ['response' => 403]);
        if (!is_user_logged_in()) {
            $redirect = esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'] ?? ''));
            wp_safe_redirect(self::url('acesso', ['redirect_to' => $redirect]));
            exit;
        }
        $page = sanitize_key($_GET['page'] ?? '');
        if ($page && isset(ICD_Visitas_Admin::available_pages()[$page])) {
            $args = ['icd_secao' => $page];
            foreach (['edit', 'icd_aluno'] as $param) {
                $id = ICD_Visitas_Helpers::id_from($_GET, $param);
                if ($id) $args[$param] = $id;
            }
            wp_safe_redirect(self::url('gestao', $args));
        } else {
            wp_safe_redirect(self::url());
        }
        exit;
    }

    public static function login_url($url, $redirect, $force_reauth) {
        if (self::is_wordpress_admin()) return $url;
        $args = $redirect ? ['redirect_to' => $redirect] : [];
        if ($force_reauth) $args['reauth'] = '1';
        return self::url('acesso', $args);
    }

    public static function restrict_login() {
        if (self::is_wordpress_admin()) return;
        $action = sanitize_key($_REQUEST['action'] ?? '');
        if ($action === 'logout' && wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'] ?? '')), 'log-out')) {
            wp_logout();
            wp_safe_redirect(self::url('acesso', ['loggedout' => '1']));
            exit;
        }
        $args = [];
        if (in_array($action, ['rp', 'resetpass'], true)) {
            $args = [
                'action' => 'reset',
                'key' => sanitize_text_field(wp_unslash($_GET['key'] ?? '')),
                'login' => sanitize_user(sanitize_text_field(wp_unslash($_GET['login'] ?? '')), true),
            ];
        }
        $redirect = self::requested_redirect($_REQUEST);
        if ($redirect) $args['redirect_to'] = $redirect;
        if (!empty($_REQUEST['reauth'])) $args['reauth'] = '1';
        wp_safe_redirect(self::url('acesso', $args));
        exit;
    }

    private static function requested_redirect($source) {
        $value = $source['redirect_to'] ?? '';
        return is_string($value) ? esc_url_raw(wp_unslash($value)) : '';
    }

    public static function logout_url($url) {
        if (!is_user_logged_in()) return $url;
        return wp_nonce_url(self::url('acesso', ['icd_logout' => '1']), 'icd_front_logout');
    }

    public static function login_destination($requested, $user) {
        $fallback = self::url('visitas');
        $url = wp_validate_redirect(is_string($requested) ? $requested : '', $fallback) ?: $fallback;
        $path = trailingslashit((string) wp_parse_url($url, PHP_URL_PATH));
        $admin = trailingslashit((string) wp_parse_url(admin_url(), PHP_URL_PATH));
        if (strpos($path, $admin) === 0 && !self::is_wordpress_admin($user)) return $fallback;
        $login_path = trailingslashit((string) wp_parse_url(site_url('wp-login.php'), PHP_URL_PATH));
        if ($path === $login_path || self::resolve_url($url) === 'acesso') return $fallback;
        return $url;
    }

    public static function restrict_rest($result, $server, $request) {
        $route = $request->get_route();
        if (self::is_wordpress_admin() || $route === '/icd-visitas/v1' || strpos($route, '/icd-visitas/v1/') === 0) return $result;
        return new WP_Error('rest_no_route', 'Rota indisponível.', ['status' => 404]);
    }

    private static function resolve_url($url) {
        $path = untrailingslashit((string) wp_parse_url($url, PHP_URL_PATH));
        $base = untrailingslashit((string) wp_parse_url(home_url('/'), PHP_URL_PATH));
        if ($base && ($path === $base || strpos($path, $base . '/') === 0)) $path = substr($path, strlen($base));
        $query = [];
        parse_str((string) wp_parse_url($url, PHP_URL_QUERY), $query);
        $route = sanitize_key($query['icd_portal'] ?? '');
        if (isset(self::SCREENS[$route])) return $route;
        $slug = ltrim($path, '/');
        if ($slug === 'perfil-visitas') return 'perfil';
        foreach (self::SCREENS as $name => $screen) {
            if ($slug === $screen[0]) return $name;
        }
        return '';
    }

    private static function resolve_page() {
        if (!is_singular()) return '';
        $page = get_queried_object();
        foreach (self::SCREENS as $name => $screen) {
            if (isset($screen[3]) && has_shortcode($page->post_content, $screen[3])) return $name;
        }
        return '';
    }

    public static function serve() {
        self::$serving = true;
        $uri = wp_unslash($_SERVER['REQUEST_URI'] ?? '/');
        $route = self::resolve_url($uri) ?: self::resolve_page() ?: 'visitas';
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
        nocache_headers();
        header('Cache-Control: private, no-store, max-age=0');
        header('Referrer-Policy: same-origin');
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($route === 'acao' || $route === 'ajax') self::dispatch($route, $method);
        if (!in_array($method, ['GET', 'HEAD'], true)) wp_die('Método inválido.', '', ['response' => 405]);
        if ($route === 'manifesto') self::manifest();

        if ($route === 'acesso' && !empty($_GET['icd_logout'])) {
            if (is_user_logged_in() && !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? '')), 'icd_front_logout')) {
                wp_die('Não foi possível validar a solicitação de saída.', '', ['response' => 403]);
            }
            wp_logout();
            wp_safe_redirect(self::url('acesso', ['loggedout' => '1']));
            exit;
        }

        if ($route === 'acesso' || !is_user_logged_in()) {
            $redirect = $route === 'acesso' ? self::requested_redirect($_GET) : $uri;
            $reset = $route === 'acesso' && sanitize_key($_GET['action'] ?? '') === 'reset';
            if (!$reset && is_user_logged_in() && current_user_can('icd_visitas_view_dashboard') && empty($_GET['reauth'])) {
                wp_safe_redirect(self::login_destination($redirect, wp_get_current_user()));
                exit;
            }
            if (!$reset) unset($_GET['action']);
            self::render('Acessar o sistema', ICD_Visitas_Public::login_screen(), true, false, 200, $redirect);
        }

        if (!current_user_can('icd_visitas_view_dashboard')) wp_die('Seu usuário não possui acesso ao portal ICD.', '', ['response' => 403]);
        if ($route === 'gestao') {
            $section = sanitize_key($_GET['icd_secao'] ?? 'icd-visitas');
            $pages = ICD_Visitas_Admin::available_pages();
            if (!isset($pages[$section])) wp_die('Você não possui acesso a esta área de gestão.', '', ['response' => 403]);
            ob_start();
            require ICD_VISITAS_DIR . 'public/gestao.php';
            self::render($pages[$section][0], ob_get_clean(), false, true);
        }
        $screen = self::SCREENS[$route];
        $allowed = [
            'nova-visita' => 'icd_visitas_manage_visitas', 'relatorios' => 'icd_visitas_view_reports',
        ];
        if ((isset($allowed[$route]) && !current_user_can($allowed[$route]))
            || ($route === 'alunos' && !ICD_Visitas_Students::can_view())) {
            wp_die('Seu perfil não possui acesso a esta área.', '', ['response' => 403]);
        }
        $content = call_user_func(['ICD_Visitas_Public', $screen[2]]);
        self::render($screen[1], $content);
    }

    private static function dispatch($route, $method) {
        $ajax = $route === 'ajax';
        if ($ajax && !defined('DOING_AJAX')) define('DOING_AJAX', true);
        $allowed = $ajax ? ['GET', 'POST'] : ['POST'];
        if (!in_array($method, $allowed, true)) self::endpoint_error($ajax, 'Método inválido.', 405);
        if (!current_user_can('icd_visitas_view_dashboard')) self::endpoint_error($ajax, 'Acesso negado.', 403);
        $source = $method === 'POST' ? $_POST : $_GET;
        $action = sanitize_key($source['action'] ?? '');
        if (!isset(self::$actions[$route][$action])) self::endpoint_error($ajax, 'Operação indisponível.', 404);
        status_header(200);
        call_user_func(self::$actions[$route][$action]);
        exit;
    }

    private static function endpoint_error($ajax, $message, $status) {
        if ($ajax) wp_send_json_error(['message' => $message], $status);
        wp_die($message, '', ['response' => $status]);
    }

    public static function error_handler($handler) {
        return self::$serving ? [__CLASS__, 'render_error'] : $handler;
    }

    public static function render_error($message, $title = '', $args = []) {
        $status = is_array($args) ? (int) ($args['response'] ?? 500) : (int) $args;
        if (is_wp_error($message)) $message = $message->get_error_message();
        $text = esc_html(html_entity_decode(wp_strip_all_tags((string) $message), ENT_QUOTES, 'UTF-8'));
        $url = self::url(is_user_logged_in() && current_user_can('icd_visitas_view_dashboard') ? 'visitas' : 'acesso');
        $content = '<div class="icd-shell"><section class="icd-card icd-portal-error"><h1>Ação não concluída</h1><p>' . $text
            . '</p><p><a class="icd-edit-btn" href="' . esc_url($url) . '">Voltar ao portal</a></p></section></div>';
        self::render('Ação não concluída', $content, false, false, $status ?: 500);
    }

    private static function render($title, $content, $login = false, $management = false, $status = 200, $redirect = '') {
        status_header($status);
        header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
        ICD_Visitas_Public::assets($redirect);
        if ($management) ICD_Visitas_Admin::enqueue_assets();
        require ICD_VISITAS_DIR . 'public/portal.php';
        exit;
    }

    private static function manifest() {
        status_header(200);
        header('Content-Type: application/manifest+json; charset=UTF-8');
        $icons = [];
        foreach ([192, 512] as $size) {
            $icons[] = ['src' => ICD_VISITAS_URL . "assets/icone-$size.png", 'sizes' => "$size" . 'x' . "$size", 'type' => 'image/png'];
        }
        echo wp_json_encode([
            'id' => home_url('/'), 'name' => 'Instituto Caminho de Davi – Gestão de Visitas', 'short_name' => 'ICD Visitas',
            'lang' => 'pt-BR', 'start_url' => self::url(), 'scope' => home_url('/'), 'display' => 'standalone',
            'background_color' => '#f5f7f6', 'theme_color' => '#19db4b', 'icons' => $icons,
        ]);
        exit;
    }
}
