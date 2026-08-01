<?php
/**
 * Загрузчик для юнит-тестов.
 *
 * Тестируемые классы намеренно не зависят от WordPress, поэтому ядро WP
 * не требуется — достаточно объявить константу ABSPATH, которую классы
 * используют как защиту от прямого вызова.
 *
 * @package InsurWP
 */

defined( 'ABSPATH' ) || define( 'ABSPATH', dirname( __DIR__ ) . '/' );

require_once dirname( __DIR__ ) . '/includes/class-insurwp-catalog.php';
require_once dirname( __DIR__ ) . '/includes/class-insurwp-calculator.php';
require_once dirname( __DIR__ ) . '/includes/class-insurwp-order-url.php';

/**
 * Загружает тарифы по умолчанию для тестов.
 *
 * @return array Тарифы.
 */
function insurwp_test_prices() {
	static $prices = null;

	if ( null === $prices ) {
		$prices = json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/data/prices.default.json' ), true );
	}

	return $prices;
}
