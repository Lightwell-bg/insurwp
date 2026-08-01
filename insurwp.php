<?php
/**
 * Plugin Name:       InsurWP — калькулятор медицинской страховки
 * Plugin URI:        https://bginfo.eu/insur/
 * Description:       Шорткод [insurwp_calculator] — расчёт стоимости медицинской страховки для иностранцев в Болгарии. Тарифы хранятся в JSON и редактируются из админ-панели. Кнопка «Заказать» открывает страницу оформления с переданными параметрами расчёта.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            InsurWP
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       insurwp
 * Domain Path:       /languages
 *
 * @package InsurWP
 */

defined( 'ABSPATH' ) || exit;

define( 'INSURWP_VERSION', '1.0.0' );
define( 'INSURWP_FILE', __FILE__ );
define( 'INSURWP_DIR', plugin_dir_path( __FILE__ ) );
define( 'INSURWP_URL', plugin_dir_url( __FILE__ ) );

require_once INSURWP_DIR . 'includes/class-insurwp-catalog.php';
require_once INSURWP_DIR . 'includes/class-insurwp-calculator.php';
require_once INSURWP_DIR . 'includes/class-insurwp-order-url.php';
require_once INSURWP_DIR . 'includes/class-insurwp-prices.php';
require_once INSURWP_DIR . 'includes/class-insurwp-settings.php';
require_once INSURWP_DIR . 'includes/class-insurwp-shortcode.php';
require_once INSURWP_DIR . 'includes/class-insurwp-rest.php';
require_once INSURWP_DIR . 'includes/class-insurwp-api-sync.php';

if ( is_admin() ) {
	require_once INSURWP_DIR . 'includes/class-insurwp-admin.php';
}

/**
 * Регистрирует хуки всех подсистем плагина.
 */
function insurwp_bootstrap() {
	load_plugin_textdomain( 'insurwp', false, dirname( plugin_basename( INSURWP_FILE ) ) . '/languages' );

	InsurWP_Shortcode::init();
	InsurWP_Rest::init();

	if ( is_admin() ) {
		InsurWP_Admin::init();
	}
}
add_action( 'plugins_loaded', 'insurwp_bootstrap' );

/**
 * При активации засеваем тарифы из data/prices.default.json, если их ещё нет.
 * Существующие тарифы не перезаписываем — иначе правки админа потерялись бы при обновлении.
 */
function insurwp_activate() {
	InsurWP_Prices::seed_if_empty();
	InsurWP_Settings::seed_if_empty();
}
register_activation_hook( __FILE__, 'insurwp_activate' );
