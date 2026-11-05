<?php
if (!defined('ABSPATH')) exit;

class ICD_Visitas_Updater {
    private const REPOSITORY = 'https://github.com/paulommaeda/icd';
    private const CACHE_KEY = 'icd_visitas_github_update_v1';

    public static function init() {
        add_filter('update_plugins_github.com', [__CLASS__, 'check'], 10, 3);
        add_filter('plugins_api', [__CLASS__, 'details'], 10, 3);
        add_filter('upgrader_source_selection', [__CLASS__, 'normalize_directory'], 20, 4);
    }

    private static function get_text($url, $headers = []) {
        $response = wp_safe_remote_get($url, ['timeout' => 5, 'limit_response_size' => 8192, 'headers' => $headers]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) return '';
        return wp_remote_retrieve_body($response);
    }

    private static function metadata() {
        $cached = get_site_transient(self::CACHE_KEY);
        if (is_array($cached)) return $cached;

        $ref = json_decode(self::get_text('https://api.github.com/repos/paulommaeda/icd/git/ref/heads/main', [
            'Accept' => 'application/vnd.github+json',
        ]), true);
        $sha = $ref['object']['sha'] ?? '';
        if (!is_string($sha) || !preg_match('/^[0-9a-f]{40}$/D', $sha)) {
            set_site_transient(self::CACHE_KEY, [], 10 * MINUTE_IN_SECONDS);
            return [];
        }

        $entry = self::get_text("https://raw.githubusercontent.com/paulommaeda/icd/$sha/icd-visitas.php");
        if (!preg_match('/^\s*\* Version:\s*(\d+\.\d+\.\d+)\s*$/m', $entry, $version)
            || !preg_match('/^\s*\* Requires at least:\s*([\d.]+)\s*$/m', $entry, $wordpress)
            || !preg_match('/^\s*\* Requires PHP:\s*([\d.]+)\s*$/m', $entry, $php)) {
            set_site_transient(self::CACHE_KEY, [], 10 * MINUTE_IN_SECONDS);
            return [];
        }

        $update = [
            'slug' => 'icd-visitas',
            'version' => $version[1],
            'requires' => $wordpress[1],
            'requires_php' => $php[1],
            'url' => self::REPOSITORY . '/blob/' . $sha . '/readme.txt',
            'package' => self::REPOSITORY . '/archive/' . $sha . '.zip',
        ];
        set_site_transient(self::CACHE_KEY, $update, 30 * MINUTE_IN_SECONDS);
        return $update;
    }

    public static function check($update, $plugin_data, $plugin_file) {
        if ($plugin_file !== plugin_basename(ICD_VISITAS_FILE) || ($plugin_data['UpdateURI'] ?? '') !== self::REPOSITORY) {
            return $update;
        }
        return self::metadata() ?: $update;
    }

    public static function details($result, $action, $args) {
        if ($action !== 'plugin_information' || ($args->slug ?? '') !== 'icd-visitas') return $result;
        $update = self::metadata();
        if (!$update) return $result;
        return (object) [
            'name' => 'Instituto Caminho de Davi - Gestão de Visitas',
            'slug' => $update['slug'],
            'version' => $update['version'],
            'requires' => $update['requires'],
            'requires_php' => $update['requires_php'],
            'author' => 'Instituto Caminho de Davi',
            'homepage' => self::REPOSITORY,
            'download_link' => $update['package'],
            'sections' => [
                'description' => 'Gestão de visitas e acompanhamento pedagógico de alunos do Instituto Caminho de Davi.',
                'changelog' => '<p><a href="' . esc_url($update['url']) . '">Ver as alterações desta versão no GitHub</a></p>',
            ],
        ];
    }

    public static function normalize_directory($source, $remote_source, $upgrader, $hook_extra) {
        global $wp_filesystem;
        $plugin = plugin_basename(ICD_VISITAS_FILE);
        if (is_wp_error($source) || ($hook_extra['plugin'] ?? '') !== $plugin
            || !preg_match('/^icd-[0-9a-f]{40}$/D', basename(untrailingslashit($source)))) {
            return $source;
        }
        if (!$wp_filesystem->is_file(trailingslashit($source) . 'icd-visitas.php')) {
            return new WP_Error('icd_update_package', 'O pacote do ICD Visitas está incompleto.');
        }

        // Mantém o caminho do plugin que o WordPress já tem registrado como ativo.
        $directory = dirname($plugin);
        $destination = trailingslashit($remote_source) . $directory;
        if ($directory === '.' || !$wp_filesystem->move(untrailingslashit($source), $destination)) {
            return new WP_Error('icd_update_directory', 'Não foi possível preparar a pasta do ICD Visitas para atualização.');
        }
        return trailingslashit($destination);
    }
}
