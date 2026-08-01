<?php
/**
 * Минимальные заглушки WordPress для смоук-тестов.
 *
 * Позволяют прогнать серверный путь плагина (шорткод, REST, синхронизацию)
 * обычным PHP, без установки WordPress. Для юнит-тестов эти заглушки не нужны:
 * ядро расчёта от WordPress не зависит и грузится через tests/bootstrap.php.
 *
 * @package InsurWP
 */

$insurwp_root = dirname( __DIR__ ) . '/';

defined( 'ABSPATH' ) || define( 'ABSPATH', $insurwp_root );
defined( 'INSURWP_DIR' ) || define( 'INSURWP_DIR', $insurwp_root );
defined( 'INSURWP_URL' ) || define( 'INSURWP_URL', 'https://example.test/wp-content/plugins/insurwp/' );
defined( 'INSURWP_VERSION' ) || define( 'INSURWP_VERSION', '1.0.0' );

$GLOBALS['insurwp_options']    = array();
$GLOBALS['insurwp_transients'] = array();
$GLOBALS['insurwp_enqueued']   = array();
$GLOBALS['insurwp_localized']  = array();
$GLOBALS['insurwp_routes']     = array();

// --- Локализация и экранирование ---------------------------------------------

function __( $text, $domain = null ) { return $text; }
function _e( $text, $domain = null ) { echo $text; }
function esc_html__( $text, $domain = null ) { return $text; }
function esc_html_e( $text, $domain = null ) { echo htmlspecialchars( (string) $text, ENT_QUOTES ); }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function esc_textarea( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function esc_url( $url ) { return $url; }
function esc_url_raw( $url ) { return $url; }
function sanitize_text_field( $text ) { return trim( strip_tags( (string) $text ) ); }
function sanitize_hex_color( $color ) { return preg_match( '/^#[0-9a-fA-F]{6}$/', (string) $color ) ? $color : null; }

// --- Опции, транзиенты, время -------------------------------------------------

function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['insurwp_options'] ) ? $GLOBALS['insurwp_options'][ $key ] : $default;
}

function update_option( $key, $value ) {
	$GLOBALS['insurwp_options'][ $key ] = $value;

	return true;
}

function add_option( $key, $value ) {
	if ( ! array_key_exists( $key, $GLOBALS['insurwp_options'] ) ) {
		$GLOBALS['insurwp_options'][ $key ] = $value;
	}

	return true;
}

function set_transient( $key, $value, $ttl = 0 ) {
	$GLOBALS['insurwp_transients'][ $key ] = $value;

	return true;
}

function get_transient( $key ) {
	return array_key_exists( $key, $GLOBALS['insurwp_transients'] ) ? $GLOBALS['insurwp_transients'][ $key ] : false;
}

function delete_transient( $key ) {
	unset( $GLOBALS['insurwp_transients'][ $key ] );

	return true;
}

function current_time( $format ) {
	return 'mysql' === $format ? date( 'Y-m-d H:i:s' ) : date( $format );
}

function wp_json_encode( $data, $flags = 0 ) { return json_encode( $data, $flags ); }

// --- Хуки, ассеты, шорткоды ---------------------------------------------------

function add_action( ...$args ) {}
function add_filter( ...$args ) {}
function add_shortcode( ...$args ) {}
function is_admin() { return false; }
function wp_register_style( ...$args ) {}
function wp_register_script( ...$args ) {}
function wp_enqueue_style( $handle ) { $GLOBALS['insurwp_enqueued'][] = 'style:' . $handle; }
function wp_enqueue_script( $handle ) { $GLOBALS['insurwp_enqueued'][] = 'script:' . $handle; }
function wp_localize_script( $handle, $name, $data ) { $GLOBALS['insurwp_localized'][ $name ] = $data; }
function rest_url( $path ) { return 'https://example.test/wp-json/' . $path; }
function wp_create_nonce( $action ) { return 'test-nonce'; }

function selected( $a, $b, $echo = true ) {
	$result = (string) $a === (string) $b ? " selected='selected'" : '';

	if ( $echo ) {
		echo $result;
	}

	return $result;
}

function shortcode_atts( $pairs, $atts, $shortcode = '' ) {
	$atts = (array) $atts;
	$out  = array();

	foreach ( $pairs as $name => $default ) {
		$out[ $name ] = array_key_exists( $name, $atts ) ? $atts[ $name ] : $default;
	}

	return $out;
}

// --- REST ---------------------------------------------------------------------

class WP_Error {
	public $code;
	public $message;
	public $data;

	public function __construct( $code = '', $message = '', $data = array() ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}

	public function get_error_message() { return $this->message; }
	public function get_error_code() { return $this->code; }
}

function is_wp_error( $thing ) { return $thing instanceof WP_Error; }

class WP_REST_Server {
	const CREATABLE = 'POST';
}

class WP_REST_Request {
	private $params;

	public function __construct( $params = array() ) { $this->params = $params; }

	public function get_param( $key ) { return isset( $this->params[ $key ] ) ? $this->params[ $key ] : null; }
}

function register_rest_route( $namespace, $route, $args ) {
	$GLOBALS['insurwp_routes'][ $namespace . $route ] = $args;
}

function rest_ensure_response( $data ) { return $data; }

// --- HTTP ---------------------------------------------------------------------

function add_query_arg( $args, $url ) {
	$separator = false === strpos( $url, '?' ) ? '?' : '&';

	return $url . $separator . http_build_query( $args );
}

/**
 * Настоящий HTTP-запрос через cURL вместо wp_remote_get().
 *
 * @param string $url  Адрес.
 * @param array  $args Аргументы запроса.
 * @return array|WP_Error Ответ.
 */
function wp_remote_get( $url, $args = array() ) {
	if ( ! function_exists( 'curl_init' ) ) {
		return new WP_Error( 'no_curl', 'Расширение cURL недоступно.' );
	}

	$handle = curl_init( $url );

	curl_setopt_array(
		$handle,
		array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_TIMEOUT        => isset( $args['timeout'] ) ? $args['timeout'] : 20,
			CURLOPT_USERAGENT      => 'InsurWP-smoke/1.0',
		)
	);

	// В сборках PHP без набора корневых сертификатов путь к нему можно
	// передать переменной окружения INSURWP_CAINFO. Проверку сертификата
	// не отключаем: сам WordPress её тоже выполняет.
	$cainfo = getenv( 'INSURWP_CAINFO' );

	if ( $cainfo && is_readable( $cainfo ) ) {
		curl_setopt( $handle, CURLOPT_CAINFO, $cainfo );
	}

	$body  = curl_exec( $handle );
	$code  = (int) curl_getinfo( $handle, CURLINFO_RESPONSE_CODE );
	$error = curl_error( $handle );

	curl_close( $handle );

	if ( false === $body ) {
		return new WP_Error( 'http_error', $error );
	}

	return array(
		'code' => $code,
		'body' => $body,
	);
}

function wp_remote_retrieve_response_code( $response ) { return is_array( $response ) ? $response['code'] : 0; }
function wp_remote_retrieve_body( $response ) { return is_array( $response ) ? $response['body'] : ''; }

// --- Загрузка классов плагина -------------------------------------------------

require_once $insurwp_root . 'includes/class-insurwp-catalog.php';
require_once $insurwp_root . 'includes/class-insurwp-calculator.php';
require_once $insurwp_root . 'includes/class-insurwp-order-url.php';
require_once $insurwp_root . 'includes/class-insurwp-prices.php';
require_once $insurwp_root . 'includes/class-insurwp-settings.php';
require_once $insurwp_root . 'includes/class-insurwp-shortcode.php';
require_once $insurwp_root . 'includes/class-insurwp-rest.php';
require_once $insurwp_root . 'includes/class-insurwp-api-sync.php';

$GLOBALS['insurwp_failures'] = 0;

/**
 * Проверяет условие и печатает результат.
 *
 * @param string $label Описание проверки.
 * @param bool   $ok    Результат.
 */
function insurwp_check( $label, $ok ) {
	if ( $ok ) {
		echo "  ok   $label\n";
	} else {
		echo "  FAIL $label\n";
		$GLOBALS['insurwp_failures']++;
	}
}

/**
 * Завершает смоук-тест с подходящим кодом возврата.
 */
function insurwp_finish() {
	$failures = $GLOBALS['insurwp_failures'];

	echo "\n" . ( $failures ? "ПРОВАЛЕНО ПРОВЕРОК: $failures\n" : "ВСЕ ПРОВЕРКИ ПРОЙДЕНЫ\n" );

	exit( $failures ? 1 : 0 );
}
