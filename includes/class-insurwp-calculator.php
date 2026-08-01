<?php
/**
 * Ядро расчёта стоимости страховки.
 *
 * Класс намеренно не использует функции WordPress: он принимает массив тарифов
 * и параметры, возвращает готовые предложения. Благодаря этому его можно
 * покрывать юнит-тестами без загрузки ядра WP.
 *
 * @package InsurWP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Подбор предложений по тарифам, сроку и возрасту застрахованного.
 */
class InsurWP_Calculator {

	/**
	 * Тарифы целиком (содержимое prices.json).
	 *
	 * @var array
	 */
	private $prices;

	/**
	 * Курс пересчёта EUR в BGN.
	 *
	 * @var float
	 */
	private $bgn_rate;

	/**
	 * Конструктор.
	 *
	 * @param array      $prices   Тарифы целиком.
	 * @param float|null $bgn_rate Курс EUR→BGN; по умолчанию фиксированная привязка.
	 */
	public function __construct( array $prices, $bgn_rate = null ) {
		$this->prices   = $prices;
		$this->bgn_rate = null === $bgn_rate ? InsurWP_Catalog::DEFAULT_BGN_RATE : (float) $bgn_rate;
	}

	/**
	 * Рассчитывает предложения.
	 *
	 * @param array $args {
	 *     Параметры расчёта.
	 *
	 *     @type string $term       Срок в формулировке JSON, например «12 месяцев».
	 *     @type string $date_start Дата начала страховки, Y-m-d.
	 *     @type string $birth_date Дата рождения, Y-m-d.
	 *     @type string $territory  Фильтр территории: bulgaria, schengen или all.
	 * }
	 * @return array Результат расчёта с ключами offers, age, term, dates.
	 * @throws InvalidArgumentException Если параметры не проходят валидацию.
	 */
	public function calculate( array $args ) {
		$term       = isset( $args['term'] ) ? trim( (string) $args['term'] ) : '';
		$territory  = isset( $args['territory'] ) ? (string) $args['territory'] : 'all';
		$date_start = $this->parse_date( isset( $args['date_start'] ) ? $args['date_start'] : '' , 'дата начала страховки' );
		$birth_date = $this->parse_date( isset( $args['birth_date'] ) ? $args['birth_date'] : '', 'дата рождения' );

		if ( '' === $term ) {
			throw new InvalidArgumentException( 'Не выбран срок страховки.' );
		}

		if ( $birth_date > $date_start ) {
			throw new InvalidArgumentException( 'Дата рождения не может быть позже даты начала страховки.' );
		}

		// Возраст считается на дату начала действия полиса, а не на сегодня:
		// именно так тарифицируют страховщики.
		$age = (int) $birth_date->diff( $date_start )->y;

		$offers = array();

		if ( ! empty( $this->prices['insurers'] ) && is_array( $this->prices['insurers'] ) ) {
			foreach ( $this->prices['insurers'] as $insurer_key => $insurer ) {
				foreach ( $this->offers_for_insurer( (string) $insurer_key, (array) $insurer, $term, $age ) as $offer ) {
					if ( 'all' !== $territory && $offer['territory'] !== $territory ) {
						continue;
					}

					$offers[] = $offer;
				}
			}
		}

		usort(
			$offers,
			static function ( $a, $b ) {
				return $a['price_eur'] <=> $b['price_eur'];
			}
		);

		return array(
			'offers'      => $offers,
			'age'         => $age,
			'term'        => $term,
			'term_label'  => InsurWP_Catalog::term_label( $term ),
			'term_api_id' => InsurWP_Catalog::term_api_id( $term ),
			'territory'   => $territory,
			'date_start'  => $date_start->format( 'Y-m-d' ),
			'birth_date'  => $birth_date->format( 'Y-m-d' ),
			'bgn_rate'    => $this->bgn_rate,
		);
	}

	/**
	 * Собирает предложения одного страховщика по всем территориям.
	 *
	 * @param string $insurer_key Ключ страховщика в JSON.
	 * @param array  $insurer     Данные страховщика.
	 * @param string $term        Выбранный срок.
	 * @param int    $age         Возраст на дату начала страховки.
	 * @return array Список предложений.
	 */
	private function offers_for_insurer( $insurer_key, array $insurer, $term, $age ) {
		$group = self::age_group( $insurer, $age );

		if ( null === $group ) {
			return array();
		}

		$row = $this->find_price_row( $insurer, $term );

		if ( null === $row ) {
			return array();
		}

		$offers = array();

		foreach ( InsurWP_Catalog::TERRITORIES as $territory => $territory_label ) {
			// В age_rules страховщик может явно запрещать территорию для группы
			// (например, Bulstrad не даёт Шенген для 65–69 лет).
			$availability_key = $territory . '_available';

			if ( isset( $group['rules'][ $availability_key ] ) && ! $group['rules'][ $availability_key ] ) {
				continue;
			}

			$price_key = $territory . '_' . $group['key'];

			// Отсутствие ключа или null означает, что полис не предлагается.
			// Это НЕ бесплатная страховка — строка просто не попадает в выдачу.
			if ( ! array_key_exists( $price_key, $row ) || null === $row[ $price_key ] ) {
				continue;
			}

			$price_eur = round( (float) $row[ $price_key ], 2 );

			$offers[] = array(
				'insurer_key'     => $insurer_key,
				'insurer_name'    => isset( $insurer['name'] ) ? (string) $insurer['name'] : $insurer_key,
				'territory'       => $territory,
				'territory_label' => $territory_label,
				'age_group'       => $group['key'],
				'price_eur'       => $price_eur,
				'price_bgn'       => round( $price_eur * $this->bgn_rate, 2 ),
				'limit_eur'       => isset( $insurer['insurance_limit_eur'] ) ? (float) $insurer['insurance_limit_eur'] : null,
				'official_url'    => isset( $insurer['official_url'] ) ? (string) $insurer['official_url'] : '',
			);
		}

		return $offers;
	}

	/**
	 * Определяет возрастную группу страховщика.
	 *
	 * Поддерживаются оба варианта записи из JSON: age_groups (UNIQA)
	 * и age_rules (Bulstrad Life) — структура ключей у них одинаковая.
	 *
	 * Поле coefficient у групп UNIQA справочное и в расчёте НЕ участвует:
	 * в prices уже лежат готовые цены по каждой группе. Умножать на него
	 * второй раз нельзя.
	 *
	 * Метод публичный и статический: той же логикой пользуется синхронизация
	 * тарифов, когда раскладывает ответы внешнего API по возрастным группам.
	 *
	 * @param array $insurer Данные страховщика.
	 * @param int   $age     Возраст застрахованного.
	 * @return array|null Ключ группы и её правила либо null, если группы нет.
	 */
	public static function age_group( array $insurer, $age ) {
		$groups = array();

		if ( ! empty( $insurer['age_groups'] ) && is_array( $insurer['age_groups'] ) ) {
			$groups = $insurer['age_groups'];
		} elseif ( ! empty( $insurer['age_rules'] ) && is_array( $insurer['age_rules'] ) ) {
			$groups = $insurer['age_rules'];
		}

		foreach ( $groups as $key => $rules ) {
			if ( ! is_array( $rules ) ) {
				continue;
			}

			$min = isset( $rules['min_age'] ) ? (int) $rules['min_age'] : 0;

			if ( $age < $min ) {
				continue;
			}

			// Верхняя граница может отсутствовать — например, группа 70_plus.
			if ( isset( $rules['max_age'] ) && $age > (int) $rules['max_age'] ) {
				continue;
			}

			return array(
				'key'   => (string) $key,
				'rules' => $rules,
			);
		}

		return null;
	}

	/**
	 * Находит строку тарифов по сроку.
	 *
	 * @param array  $insurer Данные страховщика.
	 * @param string $term    Срок.
	 * @return array|null Строка тарифов либо null.
	 */
	private function find_price_row( array $insurer, $term ) {
		if ( empty( $insurer['prices'] ) || ! is_array( $insurer['prices'] ) ) {
			return null;
		}

		foreach ( $insurer['prices'] as $row ) {
			if ( is_array( $row ) && isset( $row['term'] ) && trim( (string) $row['term'] ) === $term ) {
				return $row;
			}
		}

		return null;
	}

	/**
	 * Разбирает дату в формате Y-m-d.
	 *
	 * @param string $value Значение даты.
	 * @param string $label Название поля для текста ошибки.
	 * @return DateTimeImmutable Разобранная дата.
	 * @throws InvalidArgumentException Если дата пустая или некорректная.
	 */
	private function parse_date( $value, $label ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			throw new InvalidArgumentException( sprintf( 'Не заполнено поле: %s.', $label ) );
		}

		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value );

		if ( false === $date || $date->format( 'Y-m-d' ) !== $value ) {
			throw new InvalidArgumentException( sprintf( 'Некорректное значение поля: %s.', $label ) );
		}

		return $date;
	}
}
