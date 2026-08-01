<?php
/**
 * Юнит-тесты сборки ссылки «Заказать» и справочников.
 *
 * @package InsurWP
 */

use PHPUnit\Framework\TestCase;

/**
 * Проверяет параметры ссылки на страницу оформления и маппинг сроков.
 */
class Test_InsurWP_Order_Url extends TestCase {

	/**
	 * Разбирает query-строку собранной ссылки.
	 *
	 * @param string $url Ссылка.
	 * @return array Параметры.
	 */
	private function query_of( $url ) {
		$parsed = wp_parse_url_stub( $url );

		parse_str( $parsed, $params );

		return $params;
	}

	/**
	 * Полный набор параметров совпадает с ожиданиями страницы оформления.
	 */
	public function test_builds_full_query() {
		$url = InsurWP_Order_Url::build(
			'https://bginfo.eu/insur/',
			array(
				'term_api_id' => 12,
				'limit_eur'   => 30677.51,
				'date_start'  => '2026-08-06',
				'birth_date'  => '1990-07-01',
				'country'     => 'ru',
				'agent_id'    => '49',
				'insurer_key' => 'uniqa',
				'territory'   => 'bulgaria',
				'autocalc'    => true,
			)
		);

		$this->assertStringStartsWith( 'https://bginfo.eu/insur/?', $url );

		$params = $this->query_of( $url );

		$this->assertSame( '1', $params['ins_type'] );
		$this->assertSame( '30677.51', $params['foreigner_limit'] );
		$this->assertSame( '12', $params['foreigner_term'] );
		// Целевые поля используют формат d.m.Y, а не ISO.
		$this->assertSame( '06.08.2026', $params['date_start'] );
		$this->assertSame( '01.07.1990', $params['dfb'] );
		$this->assertSame( 'RU', $params['foreigner_country'] );
		$this->assertSame( '49', $params['agent_id'] );
		$this->assertSame( 'unica', $params['insurer'] );
		$this->assertSame( '0', $params['shengen'] );
		$this->assertSame( '1', $params['autocalc'] );
	}

	/**
	 * Для шенгена выставляется признак shengen=1.
	 */
	public function test_schengen_flag() {
		$url = InsurWP_Order_Url::build(
			'https://bginfo.eu/insur/',
			array(
				'insurer_key' => 'bulstrad_life',
				'territory'   => 'schengen',
			)
		);

		$params = $this->query_of( $url );

		$this->assertSame( '1', $params['shengen'] );
		$this->assertSame( 'bulstrad', $params['insurer'] );
	}

	/**
	 * Если в адресе уже есть параметры, добавляем через амперсанд.
	 */
	public function test_appends_to_existing_query() {
		$url = InsurWP_Order_Url::build( 'https://bginfo.eu/insur/?lang=ru', array( 'term_api_id' => 3 ) );

		$this->assertStringContainsString( 'lang=ru&', $url );
		$this->assertStringContainsString( 'foreigner_term=3', $url );
	}

	/**
	 * Пустой базовый адрес даёт пустую ссылку, а не битый URL.
	 */
	public function test_empty_base_returns_empty_string() {
		$this->assertSame( '', InsurWP_Order_Url::build( '', array( 'term_api_id' => 12 ) ) );
	}

	/**
	 * Некорректная дата не попадает в ссылку.
	 */
	public function test_invalid_date_is_omitted() {
		$url = InsurWP_Order_Url::build(
			'https://bginfo.eu/insur/',
			array(
				'date_start' => '06.08.2026',
				'birth_date' => '',
			)
		);

		$params = $this->query_of( $url );

		$this->assertArrayNotHasKey( 'date_start', $params );
		$this->assertArrayNotHasKey( 'dfb', $params );
	}

	/**
	 * Все сроки из тарифов имеют идентификатор во внешнем API:
	 * иначе кнопка «Заказать» открыла бы форму с несовпадающим сроком.
	 */
	public function test_every_term_maps_to_api_id() {
		$terms = InsurWP_Catalog::terms_from_prices( insurwp_test_prices() );

		$this->assertCount( 15, $terms );

		foreach ( $terms as $term ) {
			$this->assertIsInt( $term['api_id'], 'Нет id API для срока: ' . $term['value'] );
			$this->assertSame( 'на ' . $term['value'], $term['label'] );
		}
	}

	/**
	 * Идентификаторы соответствуют справочнику getterms.php.
	 */
	public function test_known_term_ids() {
		$this->assertSame( 12, InsurWP_Catalog::term_api_id( '12 месяцев' ) );
		$this->assertSame( 1, InsurWP_Catalog::term_api_id( '1 месяц' ) );
		$this->assertSame( 13, InsurWP_Catalog::term_api_id( '3 дня' ) );
		$this->assertSame( 15, InsurWP_Catalog::term_api_id( '15 дней' ) );
		$this->assertNull( InsurWP_Catalog::term_api_id( 'нет такого срока' ) );
	}

	/**
	 * Подпись страховой суммы совпадает с той, что показывает сервис.
	 */
	public function test_limit_label() {
		$this->assertSame( '60 000Лв / 30677.51 €', InsurWP_Catalog::limit_label( 30677.51 ) );
	}
}

/**
 * Возвращает query-часть адреса.
 *
 * Отдельная функция, чтобы не тянуть в тесты ядро WordPress ради wp_parse_url().
 *
 * @param string $url Адрес.
 * @return string Query-строка.
 */
function wp_parse_url_stub( $url ) {
	$query = parse_url( $url, PHP_URL_QUERY );

	return is_string( $query ) ? $query : '';
}
