<?php
/**
 * Сборка ссылки «Заказать» на внешнюю страницу оформления.
 *
 * Класс не использует функции WordPress — покрывается юнит-тестами напрямую.
 *
 * @package InsurWP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Формирует URL страницы оформления с параметрами расчёта.
 *
 * Имена параметров совпадают с id полей на странице bginfo.eu/insur/
 * (ins_type, foreigner_limit, foreigner_term, date_start, dfb, foreigner_country),
 * чтобы сниппет assets/js/bginfo-prefill.js мог заполнить форму без маппинга.
 */
class InsurWP_Order_Url {

	/**
	 * Единственное значение селекта «Тип страховки» на странице оформления.
	 */
	const INS_TYPE_BULGARIA = 1;

	/**
	 * Соответствие ключа страховщика имени файла логотипа на целевой странице.
	 *
	 * Нужно, чтобы сниппет подсветил именно выбранное предложение в списке.
	 *
	 * @var array<string,string>
	 */
	const INSURER_SLUGS = array(
		'uniqa'         => 'unica',
		'bulstrad_life' => 'bulstrad',
	);

	/**
	 * Собирает ссылку на оформление конкретного предложения.
	 *
	 * @param string $base_url Базовый URL страницы оформления.
	 * @param array  $args {
	 *     Параметры расчёта.
	 *
	 *     @type int|null    $term_api_id Идентификатор срока во внешнем API.
	 *     @type float|null  $limit_eur   Страховая сумма в евро.
	 *     @type string      $date_start  Дата начала, Y-m-d.
	 *     @type string      $birth_date  Дата рождения, Y-m-d.
	 *     @type string      $country     ISO-код гражданства, например RU.
	 *     @type int|string  $agent_id    Идентификатор агента на целевой площадке.
	 *     @type string      $insurer_key Ключ страховщика из тарифов.
	 *     @type string      $territory   bulgaria или schengen.
	 *     @type bool        $autocalc    Запускать ли расчёт на целевой странице автоматически.
	 * }
	 * @return string Готовый URL.
	 */
	public static function build( $base_url, array $args ) {
		$base_url = trim( (string) $base_url );

		if ( '' === $base_url ) {
			return '';
		}

		$params = array( 'ins_type' => self::INS_TYPE_BULGARIA );

		if ( ! empty( $args['limit_eur'] ) ) {
			// Целевой API ожидает лимит числом, как в getlimits.php (30677.51).
			$params['foreigner_limit'] = number_format( (float) $args['limit_eur'], 2, '.', '' );
		}

		if ( ! empty( $args['term_api_id'] ) ) {
			$params['foreigner_term'] = (int) $args['term_api_id'];
		}

		$date_start = self::to_display_date( isset( $args['date_start'] ) ? $args['date_start'] : '' );

		if ( '' !== $date_start ) {
			$params['date_start'] = $date_start;
		}

		$birth_date = self::to_display_date( isset( $args['birth_date'] ) ? $args['birth_date'] : '' );

		if ( '' !== $birth_date ) {
			$params['dfb'] = $birth_date;
		}

		if ( ! empty( $args['country'] ) ) {
			$params['foreigner_country'] = strtoupper( substr( (string) $args['country'], 0, 2 ) );
		}

		if ( ! empty( $args['agent_id'] ) ) {
			$params['agent_id'] = (string) $args['agent_id'];
		}

		if ( ! empty( $args['insurer_key'] ) ) {
			$key = (string) $args['insurer_key'];

			$params['insurer'] = isset( self::INSURER_SLUGS[ $key ] ) ? self::INSURER_SLUGS[ $key ] : $key;
		}

		if ( isset( $args['territory'] ) && in_array( $args['territory'], array( 'bulgaria', 'schengen' ), true ) ) {
			$params['shengen'] = 'schengen' === $args['territory'] ? 1 : 0;
		}

		if ( ! empty( $args['autocalc'] ) ) {
			$params['autocalc'] = 1;
		}

		$separator = ( false === strpos( $base_url, '?' ) ) ? '?' : '&';

		return $base_url . $separator . http_build_query( $params );
	}

	/**
	 * Переводит дату из Y-m-d в d.m.Y — формат, который ждут поля на целевой странице.
	 *
	 * @param string $value Дата в формате Y-m-d.
	 * @return string Дата в формате d.m.Y либо пустая строка.
	 */
	public static function to_display_date( $value ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value );

		if ( false === $date || $date->format( 'Y-m-d' ) !== $value ) {
			return '';
		}

		return $date->format( 'd.m.Y' );
	}
}
