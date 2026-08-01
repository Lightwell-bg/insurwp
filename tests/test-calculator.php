<?php
/**
 * Юнит-тесты ядра расчёта.
 *
 * @package InsurWP
 */

use PHPUnit\Framework\TestCase;

/**
 * Проверяет подбор предложений, возрастные группы и обработку null-тарифов.
 */
class Test_InsurWP_Calculator extends TestCase {

	/**
	 * Создаёт калькулятор на тарифах по умолчанию.
	 *
	 * @param float|null $rate Курс EUR→BGN.
	 * @return InsurWP_Calculator Калькулятор.
	 */
	private function calculator( $rate = null ) {
		return new InsurWP_Calculator( insurwp_test_prices(), $rate );
	}

	/**
	 * Дата рождения, дающая нужный возраст на указанную дату начала.
	 *
	 * @param int    $age        Требуемый возраст.
	 * @param string $date_start Дата начала, Y-m-d.
	 * @return string Дата рождения, Y-m-d.
	 */
	private function birth_for_age( $age, $date_start ) {
		$start = new DateTimeImmutable( $date_start );

		return $start->modify( '-' . $age . ' years -1 month' )->format( 'Y-m-d' );
	}

	/**
	 * Контрольный пример: 36 лет, 12 месяцев — все четыре предложения
	 * с ценами, которые подтверждены живым сервисом.
	 */
	public function test_returns_four_offers_for_adult_annual_policy() {
		$result = $this->calculator()->calculate(
			array(
				'term'       => '12 месяцев',
				'date_start' => '2026-08-06',
				'birth_date' => '1990-07-01',
			)
		);

		$this->assertSame( 36, $result['age'] );
		$this->assertCount( 4, $result['offers'] );

		$actual = array();

		foreach ( $result['offers'] as $offer ) {
			$actual[ $offer['insurer_key'] . '_' . $offer['territory'] ] = $offer['price_eur'];
		}

		$this->assertSame(
			array(
				'uniqa_bulgaria'         => 89.70,
				'uniqa_schengen'         => 98.67,
				'bulstrad_life_schengen' => 193.60,
				'bulstrad_life_bulgaria' => 211.96,
			),
			$actual
		);
	}

	/**
	 * Предложения отсортированы по возрастанию цены.
	 */
	public function test_offers_are_sorted_by_price() {
		$result = $this->calculator()->calculate(
			array(
				'term'       => '12 месяцев',
				'date_start' => '2026-08-06',
				'birth_date' => '1990-07-01',
			)
		);

		$prices = array_column( $result['offers'], 'price_eur' );
		$sorted = $prices;
		sort( $sorted );

		$this->assertSame( $sorted, $prices );
		$this->assertSame( 89.70, $prices[0] );
	}

	/**
	 * Возраст считается на дату начала страховки, а не на сегодня.
	 *
	 * День рождения между «сегодня» и датой начала должен переводить
	 * человека в следующую возрастную группу.
	 */
	public function test_age_is_evaluated_at_policy_start_date() {
		$before = $this->calculator()->calculate(
			array(
				'term'       => '12 месяцев',
				'date_start' => '2026-06-30',
				'birth_date' => '1956-07-01',
			)
		);

		$after = $this->calculator()->calculate(
			array(
				'term'       => '12 месяцев',
				'date_start' => '2026-07-02',
				'birth_date' => '1956-07-01',
			)
		);

		$this->assertSame( 69, $before['age'] );
		$this->assertSame( 70, $after['age'] );
	}

	/**
	 * В 70 лет UNIQA переходит в группу 70–74 с удвоенным тарифом,
	 * а Bulstrad перестаёт предлагать полис вовсе.
	 */
	public function test_seventy_years_old_gets_only_uniqa_at_higher_rate() {
		$result = $this->calculator()->calculate(
			array(
				'term'       => '12 месяцев',
				'date_start' => '2026-08-06',
				'birth_date' => $this->birth_for_age( 70, '2026-08-06' ),
			)
		);

		$this->assertCount( 2, $result['offers'] );

		foreach ( $result['offers'] as $offer ) {
			$this->assertSame( 'uniqa', $offer['insurer_key'] );
			$this->assertSame( '70_74', $offer['age_group'] );
		}

		$this->assertSame( 179.40, $result['offers'][0]['price_eur'] );
		$this->assertSame( 197.35, $result['offers'][1]['price_eur'] );
	}

	/**
	 * Bulstrad не даёт Шенген в группе 65–69: должно остаться только
	 * предложение по Болгарии.
	 */
	public function test_bulstrad_has_no_schengen_for_65_69_group() {
		$result = $this->calculator()->calculate(
			array(
				'term'       => '12 месяцев',
				'date_start' => '2026-08-06',
				'birth_date' => $this->birth_for_age( 67, '2026-08-06' ),
			)
		);

		$bulstrad = array_values(
			array_filter(
				$result['offers'],
				static function ( $offer ) {
					return 'bulstrad_life' === $offer['insurer_key'];
				}
			)
		);

		$this->assertCount( 1, $bulstrad );
		$this->assertSame( 'bulgaria', $bulstrad[0]['territory'] );
		$this->assertSame( 346.80, $bulstrad[0]['price_eur'] );
	}

	/**
	 * Значение null в тарифах означает «полис не предлагается»,
	 * а не нулевую цену.
	 */
	public function test_null_tariff_is_skipped_not_treated_as_free() {
		$result = $this->calculator()->calculate(
			array(
				'term'       => '7 дней',
				'date_start' => '2026-08-06',
				'birth_date' => '1990-07-01',
			)
		);

		foreach ( $result['offers'] as $offer ) {
			$this->assertGreaterThan( 0, $offer['price_eur'] );
			// У Bulstrad на 7 дней в Болгарии тариф null — такой строки быть не должно.
			$this->assertFalse( 'bulstrad_life' === $offer['insurer_key'] && 'bulgaria' === $offer['territory'] );
		}
	}

	/**
	 * Возраст старше 85 лет не покрывается ни одним страховщиком.
	 */
	public function test_no_offers_above_maximum_age() {
		$result = $this->calculator()->calculate(
			array(
				'term'       => '12 месяцев',
				'date_start' => '2026-08-06',
				'birth_date' => $this->birth_for_age( 90, '2026-08-06' ),
			)
		);

		$this->assertSame( array(), $result['offers'] );
	}

	/**
	 * Фильтр по территории оставляет только нужные предложения.
	 */
	public function test_territory_filter() {
		$result = $this->calculator()->calculate(
			array(
				'term'       => '12 месяцев',
				'date_start' => '2026-08-06',
				'birth_date' => '1990-07-01',
				'territory'  => 'schengen',
			)
		);

		$this->assertCount( 2, $result['offers'] );

		foreach ( $result['offers'] as $offer ) {
			$this->assertSame( 'schengen', $offer['territory'] );
		}
	}

	/**
	 * Цена в левах считается по переданному курсу.
	 */
	public function test_bgn_conversion_uses_configured_rate() {
		$result = $this->calculator( 1.95583 )->calculate(
			array(
				'term'       => '12 месяцев',
				'date_start' => '2026-08-06',
				'birth_date' => '1990-07-01',
				'territory'  => 'bulgaria',
			)
		);

		$this->assertSame( 89.70, $result['offers'][0]['price_eur'] );
		$this->assertSame( 175.44, $result['offers'][0]['price_bgn'] );
	}

	/**
	 * Срок без тарифов не должен ломать расчёт.
	 */
	public function test_unknown_term_returns_no_offers() {
		$result = $this->calculator()->calculate(
			array(
				'term'       => '13 месяцев',
				'date_start' => '2026-08-06',
				'birth_date' => '1990-07-01',
			)
		);

		$this->assertSame( array(), $result['offers'] );
		$this->assertNull( $result['term_api_id'] );
	}

	/**
	 * Пустой срок — ошибка валидации.
	 */
	public function test_empty_term_throws() {
		$this->expectException( InvalidArgumentException::class );

		$this->calculator()->calculate(
			array(
				'term'       => '',
				'date_start' => '2026-08-06',
				'birth_date' => '1990-07-01',
			)
		);
	}

	/**
	 * Некорректная дата — ошибка валидации.
	 */
	public function test_invalid_date_throws() {
		$this->expectException( InvalidArgumentException::class );

		$this->calculator()->calculate(
			array(
				'term'       => '12 месяцев',
				'date_start' => '06.08.2026',
				'birth_date' => '1990-07-01',
			)
		);
	}

	/**
	 * Дата рождения позже начала страховки — ошибка валидации.
	 */
	public function test_birth_after_start_throws() {
		$this->expectException( InvalidArgumentException::class );

		$this->calculator()->calculate(
			array(
				'term'       => '12 месяцев',
				'date_start' => '2026-08-06',
				'birth_date' => '2027-01-01',
			)
		);
	}
}
