<?php
/**
 * Обновление тарифов из внешнего API bgmedins.com.
 *
 * @package InsurWP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Импорт тарифов: опрашивает get_prices.php по всем срокам и контрольным
 * возрастам и раскладывает ответы по страховщикам, территориям и возрастным группам.
 *
 * Синхронизация идёт пачками (по одному сроку за запрос) из админского JS —
 * иначе 60 внешних запросов подряд упираются в max_execution_time.
 */
class InsurWP_Api_Sync {

	/**
	 * Адрес внешнего сервиса расчёта.
	 */
	const ENDPOINT = 'https://www.bgmedins.com/system/get_prices.php';

	/**
	 * Имя транзиента с промежуточным состоянием синхронизации.
	 */
	const TRANSIENT = 'insurwp_sync_state';

	/**
	 * Время жизни промежуточного состояния, секунд.
	 */
	const TRANSIENT_TTL = 1800;

	/**
	 * Контрольные возрасты.
	 *
	 * Подобраны так, чтобы попасть во все возрастные группы обоих страховщиков:
	 * 35 — UNIQA 0–69 и Bulstrad 0–64; 67 — UNIQA 0–69 и Bulstrad 65–69;
	 * 72 — UNIQA 70–74; 80 — UNIQA 75–85.
	 *
	 * @var int[]
	 */
	const PROBE_AGES = array( 35, 67, 72, 80 );

	/**
	 * Соответствие логотипа на стороне API ключу страховщика в тарифах.
	 *
	 * Опираемся на файл логотипа, а не на название компании: название приходит
	 * кириллицей и может меняться, имя файла стабильнее.
	 *
	 * @var array<string,string>
	 */
	const LOGO_MAP = array(
		'unica'    => 'uniqa',
		'bulstrad' => 'bulstrad_life',
	);

	/**
	 * Возвращает список сроков, которые нужно опросить.
	 *
	 * @param array $prices Текущие тарифы.
	 * @return array<int,array{value:string,api_id:int|null}> Сроки.
	 */
	public static function terms( array $prices ) {
		$terms = array();

		foreach ( InsurWP_Catalog::terms_from_prices( $prices ) as $term ) {
			if ( null !== $term['api_id'] ) {
				$terms[] = array(
					'value'  => $term['value'],
					'api_id' => $term['api_id'],
				);
			}
		}

		return $terms;
	}

	/**
	 * Начинает синхронизацию: сбрасывает опрашиваемые ячейки в null.
	 *
	 * Обнуляем только те ячейки, которые синхронизация действительно проверит.
	 * Всё остальное в JSON остаётся нетронутым.
	 *
	 * @return array Состояние синхронизации.
	 */
	public static function start() {
		$prices = InsurWP_Prices::get();
		$terms  = self::terms( $prices );

		foreach ( $prices['insurers'] as $insurer_key => $insurer ) {
			$groups = self::probed_groups( $insurer );

			foreach ( $insurer['prices'] as $row_index => $row ) {
				if ( empty( $row['term'] ) || null === InsurWP_Catalog::term_api_id( $row['term'] ) ) {
					continue;
				}

				foreach ( $groups as $group_key ) {
					foreach ( array_keys( InsurWP_Catalog::TERRITORIES ) as $territory ) {
						$cell = $territory . '_' . $group_key;

						if ( array_key_exists( $cell, $row ) ) {
							$prices['insurers'][ $insurer_key ]['prices'][ $row_index ][ $cell ] = null;
						}
					}
				}
			}
		}

		$state = array(
			'prices'  => $prices,
			'terms'   => $terms,
			'index'   => 0,
			'total'   => count( $terms ),
			'started' => time(),
			'filled'  => 0,
		);

		set_transient( self::TRANSIENT, $state, self::TRANSIENT_TTL );

		return $state;
	}

	/**
	 * Обрабатывает один срок.
	 *
	 * @return array|WP_Error Состояние синхронизации либо ошибка обращения к API.
	 */
	public static function process_next() {
		$state = get_transient( self::TRANSIENT );

		if ( ! is_array( $state ) || ! isset( $state['terms'] ) ) {
			return new WP_Error( 'insurwp_sync_expired', __( 'Сессия синхронизации истекла. Запустите обновление заново.', 'insurwp' ) );
		}

		if ( $state['index'] >= $state['total'] ) {
			return $state;
		}

		$term       = $state['terms'][ $state['index'] ];
		$limit      = self::limit_value( $state['prices'] );
		$date_start = gmdate( 'd.m.Y', strtotime( '+1 day' ) );

		foreach ( self::PROBE_AGES as $age ) {
			$offers = self::fetch( $term['api_id'], $age, $limit, $date_start );

			if ( is_wp_error( $offers ) ) {
				return $offers;
			}

			foreach ( $offers as $offer ) {
				if ( self::write_price( $state['prices'], $term['value'], $age, $offer ) ) {
					$state['filled']++;
				}
			}
		}

		$state['index']++;

		set_transient( self::TRANSIENT, $state, self::TRANSIENT_TTL );

		return $state;
	}

	/**
	 * Записывает цену из ответа API в нужную ячейку тарифов.
	 *
	 * @param array  $prices Тарифы (по ссылке).
	 * @param string $term   Срок в формулировке JSON.
	 * @param int    $age    Контрольный возраст.
	 * @param array  $offer  Одно предложение из ответа API.
	 * @return bool Записана ли цена.
	 */
	private static function write_price( array &$prices, $term, $age, array $offer ) {
		$insurer_key = self::insurer_key_from_logo( isset( $offer['logo'] ) ? $offer['logo'] : '' );

		if ( null === $insurer_key || ! isset( $prices['insurers'][ $insurer_key ] ) ) {
			return false;
		}

		$insurer = $prices['insurers'][ $insurer_key ];
		$group   = InsurWP_Calculator::age_group( $insurer, $age );

		if ( null === $group ) {
			return false;
		}

		$territory = ! empty( $offer['shengen'] ) ? 'schengen' : 'bulgaria';
		$cell      = $territory . '_' . $group['key'];

		foreach ( $insurer['prices'] as $row_index => $row ) {
			if ( empty( $row['term'] ) || trim( (string) $row['term'] ) !== $term ) {
				continue;
			}

			$price = isset( $offer['price_eur'] ) ? (float) $offer['price_eur'] : (float) $offer['price'];

			if ( $price <= 0 ) {
				return false;
			}

			$prices['insurers'][ $insurer_key ]['prices'][ $row_index ][ $cell ] = round( $price, 2 );

			return true;
		}

		return false;
	}

	/**
	 * Запрашивает предложения у внешнего API.
	 *
	 * @param int    $term_api_id Идентификатор срока.
	 * @param int    $age         Возраст застрахованного.
	 * @param string $limit       Страховая сумма.
	 * @param string $date_start  Дата начала, d.m.Y.
	 * @return array|WP_Error Список предложений либо ошибка.
	 */
	private static function fetch( $term_api_id, $age, $limit, $date_start ) {
		$settings = InsurWP_Settings::all();

		$url = add_query_arg(
			array(
				'jsoncallback'      => 'insurwp',
				'foreigner_country' => $settings['default_country'],
				'dfb'               => gmdate( 'd.m.Y', strtotime( '-' . (int) $age . ' years -1 month' ) ),
				'foreigner_term'    => (int) $term_api_id,
				'foreigner_limit'   => $limit,
				'date_start'        => $date_start,
				'agent_id'          => $settings['agent_id'],
			),
			self::ENDPOINT
		);

		$response = wp_remote_get( $url, array( 'timeout' => 20 ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			return new WP_Error(
				'insurwp_sync_http',
				sprintf(
					/* translators: %d: код ответа HTTP. */
					__( 'Сервис тарифов ответил кодом %d.', 'insurwp' ),
					$code
				)
			);
		}

		$data = self::parse_jsonp( wp_remote_retrieve_body( $response ) );

		if ( null === $data ) {
			return new WP_Error( 'insurwp_sync_parse', __( 'Не удалось разобрать ответ сервиса тарифов.', 'insurwp' ) );
		}

		// status = 1 означает отказ по параметрам, а не сетевую ошибку:
		// для части возрастов это нормальный ответ «предложений нет».
		if ( empty( $data['Result'] ) || ! is_array( $data['Result'] ) ) {
			return array();
		}

		return $data['Result'];
	}

	/**
	 * Разбирает JSONP-ответ вида callback({...}).
	 *
	 * @param string $body Тело ответа.
	 * @return array|null Разобранные данные либо null.
	 */
	public static function parse_jsonp( $body ) {
		$body = trim( (string) $body );

		if ( '' === $body ) {
			return null;
		}

		$start = strpos( $body, '(' );
		$end   = strrpos( $body, ')' );

		if ( false !== $start && false !== $end && $end > $start ) {
			$body = substr( $body, $start + 1, $end - $start - 1 );
		}

		$data = json_decode( trim( $body ), true );

		return is_array( $data ) ? $data : null;
	}

	/**
	 * Возвращает ключ страховщика по имени файла логотипа.
	 *
	 * @param string $logo Имя файла логотипа, например unica.jpg.
	 * @return string|null Ключ страховщика либо null.
	 */
	public static function insurer_key_from_logo( $logo ) {
		$logo = strtolower( (string) $logo );

		foreach ( self::LOGO_MAP as $needle => $key ) {
			if ( false !== strpos( $logo, $needle ) ) {
				return $key;
			}
		}

		return null;
	}

	/**
	 * Возвращает страховую сумму для запроса к API.
	 *
	 * @param array $prices Тарифы.
	 * @return string Сумма в формате, который принимает API.
	 */
	private static function limit_value( array $prices ) {
		$limits = InsurWP_Prices::limits( $prices );

		return empty( $limits ) ? '30677.51' : $limits[0]['value'];
	}

	/**
	 * Возрастные группы страховщика, которые покрываются контрольными возрастами.
	 *
	 * @param array $insurer Данные страховщика.
	 * @return string[] Ключи групп.
	 */
	private static function probed_groups( array $insurer ) {
		$groups = array();

		foreach ( self::PROBE_AGES as $age ) {
			$group = InsurWP_Calculator::age_group( $insurer, $age );

			if ( null !== $group ) {
				$groups[ $group['key'] ] = $group['key'];
			}
		}

		return array_values( $groups );
	}

	/**
	 * Возвращает результат синхронизации из транзиента.
	 *
	 * @return array|null Состояние либо null.
	 */
	public static function pending() {
		$state = get_transient( self::TRANSIENT );

		return is_array( $state ) ? $state : null;
	}

	/**
	 * Сбрасывает состояние синхронизации.
	 */
	public static function reset() {
		delete_transient( self::TRANSIENT );
	}

	/**
	 * Сравнивает текущие и новые тарифы.
	 *
	 * @param array $old Текущие тарифы.
	 * @param array $new Новые тарифы.
	 * @return array<int,array{insurer:string,term:string,cell:string,from:string,to:string}> Список изменений.
	 */
	public static function diff( array $old, array $new ) {
		$changes = array();

		if ( empty( $new['insurers'] ) ) {
			return $changes;
		}

		foreach ( $new['insurers'] as $insurer_key => $insurer ) {
			$name = isset( $insurer['name'] ) ? $insurer['name'] : $insurer_key;

			foreach ( $insurer['prices'] as $row_index => $row ) {
				$old_row = isset( $old['insurers'][ $insurer_key ]['prices'][ $row_index ] )
					? $old['insurers'][ $insurer_key ]['prices'][ $row_index ]
					: array();

				foreach ( $row as $cell => $value ) {
					if ( 'term' === $cell ) {
						continue;
					}

					$before = array_key_exists( $cell, $old_row ) ? $old_row[ $cell ] : null;

					if ( self::same_price( $before, $value ) ) {
						continue;
					}

					$changes[] = array(
						'insurer' => $name,
						'term'    => isset( $row['term'] ) ? $row['term'] : '',
						'cell'    => $cell,
						'from'    => null === $before ? '—' : number_format( (float) $before, 2, '.', '' ),
						'to'      => null === $value ? '—' : number_format( (float) $value, 2, '.', '' ),
					);
				}
			}
		}

		return $changes;
	}

	/**
	 * Сравнивает две цены с учётом null.
	 *
	 * @param mixed $a Первое значение.
	 * @param mixed $b Второе значение.
	 * @return bool Совпадают ли значения.
	 */
	private static function same_price( $a, $b ) {
		if ( null === $a && null === $b ) {
			return true;
		}

		if ( null === $a || null === $b ) {
			return false;
		}

		return abs( (float) $a - (float) $b ) < 0.005;
	}
}
