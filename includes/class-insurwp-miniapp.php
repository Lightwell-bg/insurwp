<?php
/**
 * Поддержка Telegram Mini App.
 *
 * @package InsurWP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Справочники для мини-аппа и CORS для маршрутов плагина.
 *
 * Мини-апп живёт на отдельном домене и обращается к тем же REST-маршрутам,
 * что и калькулятор на сайте. Расчёт здесь не дублируется: только справочники
 * для формы и разрешение кросс-доменных запросов с известных адресов.
 */
class InsurWP_Miniapp {

	/**
	 * Подключает хуки.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );

		// Приоритет 11: ядро на 10 (rest_send_cors_headers) уже отразило любой
		// Origin, наша задача — сузить это до белого списка после него.
		add_filter( 'rest_pre_serve_request', array( __CLASS__, 'send_cors_headers' ), 11, 4 );
	}

	/**
	 * Регистрирует маршруты.
	 */
	public static function register_routes() {
		register_rest_route(
			InsurWP_Rest::NAMESPACE_V1,
			'/options',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'options' ),
				// Справочники публичны ровно в той же мере, что и калькулятор на сайте.
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Справочники для формы мини-аппа.
	 *
	 * Отдаём только то, что администратор меняет в WordPress: сроки и лимит
	 * зависят от тарифов, флаги — от настроек, границы дат считаются во времени
	 * сайта. Строки интерфейса и оформление у мини-аппа свои.
	 *
	 * @return WP_REST_Response Справочники.
	 */
	public static function options() {
		$settings = InsurWP_Settings::all();
		$prices   = InsurWP_Prices::get();
		$terms    = InsurWP_Catalog::terms_from_prices( $prices );

		$territories = array(
			array(
				'value' => 'all',
				'label' => __( 'Все предложения', 'insurwp' ),
			),
		);

		foreach ( InsurWP_Catalog::TERRITORIES as $value => $label ) {
			$territories[] = array(
				'value' => $value,
				'label' => $label,
			);
		}

		return rest_ensure_response(
			array(
				'terms'           => $terms,
				'default_term'    => InsurWP_Catalog::default_term( $terms ),
				'territories'     => $territories,
				'limits'          => InsurWP_Prices::limits( $prices ),
				'show_bgn'        => ! empty( $settings['show_bgn'] ),
				'show_disclaimer' => ! empty( $settings['show_disclaimer'] ),
				'dates'           => InsurWP_Shortcode::date_bounds(),
			)
		);
	}

	/**
	 * Сужает CORS-заголовки ядра до белого списка origin.
	 *
	 * WordPress на этом же хуке отражает любой Origin и добавляет
	 * Access-Control-Allow-Credentials. Для публичного калькулятора это лишнее:
	 * cookie мини-апп не шлёт, а список его адресов известен заранее.
	 *
	 * @param bool   $served  Отправлен ли ответ.
	 * @param mixed  $result  Ответ REST.
	 * @param mixed  $request Запрос REST.
	 * @param mixed  $server  Сервер REST.
	 * @return bool Значение $served без изменений.
	 */
	public static function send_cors_headers( $served, $result = null, $request = null, $server = null ) {
		if ( ! $request instanceof WP_REST_Request ) {
			return $served;
		}

		// Чужие маршруты не трогаем — там продолжает действовать политика ядра.
		if ( 0 !== strpos( (string) $request->get_route(), '/' . InsurWP_Rest::NAMESPACE_V1 ) ) {
			return $served;
		}

		$origin = get_http_origin();

		if ( $origin && self::is_allowed_origin( $origin, self::allowed_origins() ) ) {
			header( 'Access-Control-Allow-Origin: ' . self::normalize_origin( $origin ) );
			header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
			header( 'Access-Control-Allow-Headers: Content-Type' );
			header( 'Access-Control-Max-Age: 600' );
			header( 'Vary: Origin', false );

			// Ответ одинаков для всех, аутентификации нет — разрешать отправку
			// cookie с чужого домена незачем.
			header_remove( 'Access-Control-Allow-Credentials' );

			return $served;
		}

		// Origin неизвестен: снимаем разрешение, выданное ядром.
		header_remove( 'Access-Control-Allow-Origin' );
		header_remove( 'Access-Control-Allow-Credentials' );

		return $served;
	}

	/**
	 * Разрешённые origin мини-аппа.
	 *
	 * @return string[] Нормализованные origin.
	 */
	public static function allowed_origins() {
		$list = array();

		foreach ( preg_split( '/[\r\n]+/', (string) InsurWP_Settings::get( 'miniapp_origins', '' ) ) as $line ) {
			$origin = self::normalize_origin( $line );

			if ( '' !== $origin ) {
				$list[ $origin ] = $origin;
			}
		}

		/**
		 * Позволяет разрешить origin, не заводя его в настройках, —
		 * например адрес локального dev-сервера.
		 *
		 * @param string[] $list Разрешённые origin.
		 */
		$list = apply_filters( 'insurwp_miniapp_allowed_origins', array_values( $list ) );

		return is_array( $list ) ? $list : array();
	}

	/**
	 * Разрешён ли origin.
	 *
	 * Сравнение идёт по нормализованному значению целиком: подстрока совпадением
	 * не считается, иначе адрес вида miniapp.bginfo.eu.example.com прошёл бы проверку.
	 *
	 * @param string   $origin  Значение заголовка Origin.
	 * @param string[] $allowed Разрешённые origin.
	 * @return bool Разрешён ли запрос.
	 */
	public static function is_allowed_origin( $origin, array $allowed ) {
		$origin = self::normalize_origin( $origin );

		if ( '' === $origin ) {
			return false;
		}

		foreach ( $allowed as $candidate ) {
			if ( $origin === self::normalize_origin( $candidate ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Приводит адрес к каноничному origin: схема, хост и порт в нижнем регистре.
	 *
	 * @param string $value Адрес из настроек или из заголовка Origin.
	 * @return string Нормализованный origin либо пустая строка.
	 */
	public static function normalize_origin( $value ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		$parts = wp_parse_url( $value );

		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}

		$origin = strtolower( $parts['scheme'] ) . '://' . strtolower( $parts['host'] );

		if ( ! empty( $parts['port'] ) ) {
			$origin .= ':' . (int) $parts['port'];
		}

		return $origin;
	}
}
