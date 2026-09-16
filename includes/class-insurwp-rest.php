<?php
/**
 * REST-эндпоинт расчёта.
 *
 * @package InsurWP
 */

defined( 'ABSPATH' ) || exit;

/**
 * POST /wp-json/insurwp/v1/quote — возвращает предложения по параметрам расчёта.
 */
class InsurWP_Rest {

	/**
	 * Пространство имён REST API плагина.
	 */
	const NAMESPACE_V1 = 'insurwp/v1';

	/**
	 * Подключает хуки.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Регистрирует маршруты.
	 */
	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/quote',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'quote' ),
				// Калькулятор публичный: тарифы и так открыто опубликованы страховщиками.
				// Защита сводится к строгой валидации входных полей ниже.
				'permission_callback' => '__return_true',
				'args'                => array(
					'term'       => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'date_start' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'birth_date' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'territory'  => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => 'all',
						'enum'              => array( 'all', 'bulgaria', 'schengen' ),
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Обработчик расчёта.
	 *
	 * @param WP_REST_Request $request Запрос.
	 * @return WP_REST_Response|WP_Error Результат расчёта либо ошибка валидации.
	 */
	public static function quote( WP_REST_Request $request ) {
		$settings = InsurWP_Settings::all();
		$prices   = InsurWP_Prices::get();

		$calculator = new InsurWP_Calculator( $prices, (float) $settings['bgn_rate'] );

		try {
			$result = $calculator->calculate(
				array(
					'term'       => $request->get_param( 'term' ),
					'date_start' => $request->get_param( 'date_start' ),
					'birth_date' => $request->get_param( 'birth_date' ),
					'territory'  => $request->get_param( 'territory' ),
				)
			);
		} catch ( InvalidArgumentException $e ) {
			return new WP_Error(
				'insurwp_invalid_input',
				$e->getMessage(),
				array( 'status' => 400 )
			);
		}

		$result['show_bgn'] = ! empty( $settings['show_bgn'] );

		// Для ссылки на оформление берём ЕДИНЫЙ лимит, реально доступный в селекте
		// на целевой странице (InsurWP_Prices::limits()), а не декларируемый лимит
		// конкретного страховщика (offer['limit_eur']): у Bulstrad Life он в JSON
		// указан как 30000, тогда как на bginfo.eu выбрать можно только 30677.51 —
		// подстановка недоступного значения оставляла селект пустым и страница
		// требовала выбрать сумму страхования вручную.
		$order_limits = InsurWP_Prices::limits( $prices );
		$order_limit  = ! empty( $order_limits ) ? (float) $order_limits[0]['value'] : null;

		// Ссылку на оформление собираем на сервере: так параметры целевой площадки
		// (agent_id, гражданство по умолчанию) не попадают в разметку страницы заранее.
		foreach ( $result['offers'] as $index => $offer ) {
			$result['offers'][ $index ]['order_url'] = InsurWP_Order_Url::build(
				$settings['order_url'],
				array(
					'term_api_id' => $result['term_api_id'],
					'limit_eur'   => $order_limit,
					'date_start'  => $result['date_start'],
					'birth_date'  => $result['birth_date'],
					'country'     => $settings['default_country'],
					'agent_id'    => $settings['agent_id'],
					'insurer_key' => $offer['insurer_key'],
					'territory'   => $offer['territory'],
					'autocalc'    => ! empty( $settings['autocalc'] ),
				)
			);
		}

		// Считаем только состоявшиеся расчёты — не пустые запросы с ошибкой
		// валидации, они выше уже вернулись до этой строки.
		InsurWP_Stats::record();

		return rest_ensure_response( $result );
	}
}
