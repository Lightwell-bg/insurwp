<?php
/**
 * Хранение и валидация тарифов.
 *
 * @package InsurWP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Чтение, запись и проверка JSON тарифов.
 */
class InsurWP_Prices {

	/**
	 * Имя опции с тарифами.
	 */
	const OPTION = 'insurwp_prices';

	/**
	 * Имя опции с датой последнего обновления тарифов.
	 */
	const OPTION_UPDATED = 'insurwp_prices_updated';

	/**
	 * Возвращает текущие тарифы.
	 *
	 * @return array Тарифы; при отсутствии опции — значения из файла-сида.
	 */
	public static function get() {
		$prices = get_option( self::OPTION );

		if ( ! is_array( $prices ) || empty( $prices['insurers'] ) ) {
			return self::defaults();
		}

		return $prices;
	}

	/**
	 * Сохраняет тарифы.
	 *
	 * @param array $prices Проверенные тарифы.
	 * @return bool Результат сохранения.
	 */
	public static function save( array $prices ) {
		update_option( self::OPTION_UPDATED, current_time( 'mysql' ) );

		return update_option( self::OPTION, $prices );
	}

	/**
	 * Дата последнего обновления тарифов.
	 *
	 * @return string Дата в формате MySQL либо пустая строка.
	 */
	public static function updated_at() {
		return (string) get_option( self::OPTION_UPDATED, '' );
	}

	/**
	 * Тарифы по умолчанию из data/prices.default.json.
	 *
	 * @return array Тарифы либо пустая структура, если файл недоступен.
	 */
	public static function defaults() {
		$file = INSURWP_DIR . 'data/prices.default.json';

		if ( ! is_readable( $file ) ) {
			return array( 'insurers' => array() );
		}

		$raw    = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- локальный файл плагина.
		$parsed = json_decode( (string) $raw, true );

		return is_array( $parsed ) ? $parsed : array( 'insurers' => array() );
	}

	/**
	 * Записывает тарифы по умолчанию, если опции ещё нет.
	 *
	 * Существующие тарифы не трогаем — иначе правки администратора
	 * терялись бы при повторной активации плагина.
	 */
	public static function seed_if_empty() {
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, self::defaults() );
			add_option( self::OPTION_UPDATED, current_time( 'mysql' ) );
		}
	}

	/**
	 * Разбирает и проверяет JSON тарифов.
	 *
	 * @param string $json Сырой JSON из редактора в админке.
	 * @return array|WP_Error Разобранные тарифы либо ошибка с описанием.
	 */
	public static function parse_and_validate( $json ) {
		$parsed = json_decode( (string) $json, true );

		if ( null === $parsed && JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error(
				'insurwp_invalid_json',
				sprintf(
					/* translators: %s: текст ошибки разбора JSON. */
					__( 'Ошибка в JSON: %s', 'insurwp' ),
					json_last_error_msg()
				)
			);
		}

		if ( ! is_array( $parsed ) ) {
			return new WP_Error( 'insurwp_invalid_json', __( 'JSON должен быть объектом.', 'insurwp' ) );
		}

		if ( empty( $parsed['insurers'] ) || ! is_array( $parsed['insurers'] ) ) {
			return new WP_Error( 'insurwp_no_insurers', __( 'В JSON отсутствует непустой раздел «insurers».', 'insurwp' ) );
		}

		foreach ( $parsed['insurers'] as $key => $insurer ) {
			if ( ! is_array( $insurer ) ) {
				return new WP_Error(
					'insurwp_bad_insurer',
					sprintf(
						/* translators: %s: ключ страховщика. */
						__( 'Страховщик «%s» описан некорректно.', 'insurwp' ),
						$key
					)
				);
			}

			if ( empty( $insurer['prices'] ) || ! is_array( $insurer['prices'] ) ) {
				return new WP_Error(
					'insurwp_bad_prices',
					sprintf(
						/* translators: %s: ключ страховщика. */
						__( 'У страховщика «%s» отсутствует непустой массив «prices».', 'insurwp' ),
						$key
					)
				);
			}

			$has_groups = ( ! empty( $insurer['age_groups'] ) && is_array( $insurer['age_groups'] ) )
				|| ( ! empty( $insurer['age_rules'] ) && is_array( $insurer['age_rules'] ) );

			if ( ! $has_groups ) {
				return new WP_Error(
					'insurwp_bad_age_groups',
					sprintf(
						/* translators: %s: ключ страховщика. */
						__( 'У страховщика «%s» нет ни «age_groups», ни «age_rules».', 'insurwp' ),
						$key
					)
				);
			}

			foreach ( $insurer['prices'] as $row ) {
				if ( ! is_array( $row ) || empty( $row['term'] ) ) {
					return new WP_Error(
						'insurwp_bad_price_row',
						sprintf(
							/* translators: %s: ключ страховщика. */
							__( 'У страховщика «%s» есть строка тарифов без поля «term».', 'insurwp' ),
							$key
						)
					);
				}
			}
		}

		return $parsed;
	}

	/**
	 * Возвращает тарифы в виде отформатированного JSON для редактора.
	 *
	 * @param array|null $prices Тарифы; по умолчанию текущие.
	 * @return string JSON с отступами и читаемой кириллицей.
	 */
	public static function to_json( $prices = null ) {
		$prices = null === $prices ? self::get() : $prices;

		return (string) wp_json_encode( $prices, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Список доступных страховых сумм, собранный из тарифов.
	 *
	 * @param array|null $prices Тарифы; по умолчанию текущие.
	 * @return array<int,array{value:string,label:string}> Лимиты для селекта.
	 */
	public static function limits( $prices = null ) {
		$prices = null === $prices ? self::get() : $prices;
		$limits = array();

		if ( ! empty( $prices['insurance_limit_eur'] ) ) {
			$limits[] = (float) $prices['insurance_limit_eur'];
		}

		if ( ! empty( $prices['insurers'] ) && is_array( $prices['insurers'] ) ) {
			foreach ( $prices['insurers'] as $insurer ) {
				if ( ! empty( $insurer['insurance_limit_eur'] ) ) {
					$limits[] = (float) $insurer['insurance_limit_eur'];
				}
			}
		}

		// Страховщики могут декларировать чуть разные лимиты (30677.51 и 30000).
		// В селекте показываем один — максимальный, он же общий лимит калькулятора.
		if ( empty( $limits ) ) {
			return array();
		}

		$limit = max( $limits );

		return array(
			array(
				'value' => number_format( $limit, 2, '.', '' ),
				'label' => InsurWP_Catalog::limit_label( $limit ),
			),
		);
	}
}
