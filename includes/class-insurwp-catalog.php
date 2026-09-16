<?php
/**
 * Справочники: сроки, территории, возрастные группы.
 *
 * Класс намеренно не использует функции WordPress — это позволяет покрывать его
 * юнит-тестами без загрузки ядра WP.
 *
 * @package InsurWP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Статические справочники и маппинги между JSON тарифов и внешним API bgmedins.com.
 */
class InsurWP_Catalog {

	/**
	 * Соответствие срока из JSON тарифов идентификатору срока во внешнем API.
	 *
	 * Значения получены из https://www.bgmedins.com/webservices/getterms.php:
	 * id 1..12 — месяцы, 13..15 — дни. Без этого маппинга кнопка «Заказать»
	 * не сможет выбрать правильный срок на странице оформления.
	 *
	 * @var array<string,int>
	 */
	const TERM_API_IDS = array(
		'3 дня'       => 13,
		'7 дней'      => 14,
		'15 дней'     => 15,
		'1 месяц'     => 1,
		'2 месяца'    => 2,
		'3 месяца'    => 3,
		'4 месяца'    => 4,
		'5 месяцев'   => 5,
		'6 месяцев'   => 6,
		'7 месяцев'   => 7,
		'8 месяцев'   => 8,
		'9 месяцев'   => 9,
		'10 месяцев'  => 10,
		'11 месяцев'  => 11,
		'12 месяцев'  => 12,
	);

	/**
	 * Территории действия полиса: ключ в JSON => подпись для интерфейса.
	 *
	 * @var array<string,string>
	 */
	const TERRITORIES = array(
		'bulgaria' => 'Болгария',
		'schengen' => 'Шенген',
	);

	/**
	 * Курс привязки лева к евро (фиксированный).
	 */
	const DEFAULT_BGN_RATE = 1.95583;

	/**
	 * Возвращает id срока для внешнего API.
	 *
	 * @param string $term Срок в формулировке JSON, например «12 месяцев».
	 * @return int|null Идентификатор срока либо null, если срок неизвестен.
	 */
	public static function term_api_id( $term ) {
		$term = trim( (string) $term );

		return isset( self::TERM_API_IDS[ $term ] ) ? self::TERM_API_IDS[ $term ] : null;
	}

	/**
	 * Подпись срока для выпадающего списка: «12 месяцев» => «на 12 месяцев».
	 *
	 * @param string $term Срок в формулировке JSON.
	 * @return string Подпись для интерфейса.
	 */
	public static function term_label( $term ) {
		return 'на ' . trim( (string) $term );
	}

	/**
	 * Список сроков в том порядке, в каком они заданы в тарифах.
	 *
	 * Порядок берётся из тарифов первого страховщика: у всех страховщиков
	 * набор сроков одинаковый, а порядок в JSON осмысленный (дни, затем месяцы).
	 *
	 * @param array $prices Массив тарифов целиком.
	 * @return array<int,array{value:string,label:string,api_id:int|null}> Сроки для селекта.
	 */
	public static function terms_from_prices( array $prices ) {
		$terms = array();

		if ( empty( $prices['insurers'] ) || ! is_array( $prices['insurers'] ) ) {
			return $terms;
		}

		foreach ( $prices['insurers'] as $insurer ) {
			if ( empty( $insurer['prices'] ) || ! is_array( $insurer['prices'] ) ) {
				continue;
			}

			foreach ( $insurer['prices'] as $row ) {
				if ( empty( $row['term'] ) || isset( $terms[ $row['term'] ] ) ) {
					continue;
				}

				$terms[ $row['term'] ] = array(
					'value'  => $row['term'],
					'label'  => self::term_label( $row['term'] ),
					'api_id' => self::term_api_id( $row['term'] ),
				);
			}
		}

		return array_values( $terms );
	}

	/**
	 * Срок, выбранный в форме по умолчанию.
	 *
	 * @param array $terms Сроки из terms_from_prices().
	 * @return string Годовой полис, если он есть в тарифах, иначе первый срок.
	 */
	public static function default_term( array $terms ) {
		foreach ( $terms as $term ) {
			if ( '12 месяцев' === $term['value'] ) {
				return $term['value'];
			}
		}

		return empty( $terms ) ? '' : (string) $terms[0]['value'];
	}

	/**
	 * Подпись страховой суммы для выпадающего списка.
	 *
	 * @param float $limit_eur Лимит ответственности в евро.
	 * @return string Подпись вида «60 000Лв / 30677.51 €».
	 */
	public static function limit_label( $limit_eur ) {
		$limit_eur = (float) $limit_eur;
		$bgn       = round( $limit_eur * self::DEFAULT_BGN_RATE );

		return number_format( $bgn, 0, ',', ' ' ) . 'Лв / ' . number_format( $limit_eur, 2, '.', '' ) . ' €';
	}
}
