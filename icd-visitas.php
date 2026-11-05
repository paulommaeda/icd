<?php
/**
 * Plugin Name: Instituto Caminho de Davi - Gestão de Visitas
 * Description: Gestão mobile first de visitas pedagógicas às escolas, mediadores, vínculos, agendas, importação e dashboards do Instituto Caminho de Davi.
 * Version: 0.9.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Update URI: https://github.com/paulommaeda/icd
 * Author: Instituto Caminho de Davi
 * Text Domain: icd-visitas
 */
if (!defined('ABSPATH')) exit;
define('ICD_VISITAS_VERSION','0.9.0');
define('ICD_VISITAS_FILE',__FILE__);
define('ICD_VISITAS_DIR',plugin_dir_path(__FILE__));
define('ICD_VISITAS_URL',plugin_dir_url(__FILE__));
require_once ICD_VISITAS_DIR.'includes/class-icd-database.php';
require_once ICD_VISITAS_DIR.'includes/class-icd-permissions.php';
require_once ICD_VISITAS_DIR.'includes/class-icd-helpers.php';
require_once ICD_VISITAS_DIR.'includes/class-icd-importer.php';
require_once ICD_VISITAS_DIR.'includes/class-icd-admin.php';
require_once ICD_VISITAS_DIR.'includes/class-icd-public.php';
require_once ICD_VISITAS_DIR.'includes/class-icd-rest.php';
require_once ICD_VISITAS_DIR.'includes/class-icd-passkeys.php';
require_once ICD_VISITAS_DIR.'includes/class-icd-account.php';
require_once ICD_VISITAS_DIR.'includes/class-icd-pdf.php';
require_once ICD_VISITAS_DIR.'includes/class-icd-reports.php';
require_once ICD_VISITAS_DIR.'includes/class-icd-students.php';
require_once ICD_VISITAS_DIR.'includes/class-icd-updater.php';
register_activation_hook(__FILE__,['ICD_Visitas_Database','install']);
add_action('plugins_loaded',function(){
 if(get_option('icd_visitas_db_version') !== ICD_Visitas_Database::SCHEMA_VERSION) ICD_Visitas_Database::install();
 ICD_Visitas_Students::init();
 ICD_Visitas_Updater::init();
 ICD_Visitas_Admin::init(); ICD_Visitas_Public::init(); ICD_Visitas_REST::init(); ICD_Visitas_Passkeys::init(); ICD_Visitas_Account::init(); ICD_Visitas_Reports::init();
});
