<?php
/**
 * Статистика расчётов калькулятора.
 *
 * @package InsurWP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Считает успешные расчёты — одинаково с сайта и из Telegram-мини-аппа,
 * поскольку оба ходят в один и тот же REST-эндпоинт.
 */
class InsurWP_Stats {

	/**
	 * Опция с общим счётчиком расчётов.
	 */
	const OPTION_TOTAL = 'insurwp_stats_total';

	/**
	 * Опция с разбивкой по дням: { 'Y-m-d' => count }.
	 */
	const OPTION_DAILY = 'insurwp_stats_daily';

	/**
	 * Сколько дней разбивки хранить — старше просто не нужны для статистики.
	 */
	const DAILY_RETENTION_DAYS = 90;

	/**
	 * Отмечает один успешный расчёт.
	 */
	public static function record() {
		self::increment_total();
		self::increment_today();
	}

	/**
	 * Общее число расчётов за всё время.
	 *
	 * @return int Счётчик.
	 */
	public static function total() {
		return (int) get_option( self::OPTION_TOTAL, 0 );
	}

	/**
	 * Разбивка по дням — от сегодня к прошлому, включая дни без расчётов.
	 *
	 * @param int $days Сколько последних дней вернуть.
	 * @return array<string,int> Ключ — дата Y-m-d, значение — число расчётов.
	 */
	public static function daily( $days ) {
		$stored = get_option( self::OPTION_DAILY, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$today  = self::today();
		$result = array();

		for ( $i = 0; $i < (int) $days; $i++ ) {
			$date = $today->modify( '-' . $i . ' days' )->format( 'Y-m-d' );

			$result[ $date ] = isset( $stored[ $date ] ) ? (int) $stored[ $date ] : 0;
		}

		return $result;
	}

	/**
	 * Сегодняшняя дата сайта как объект для арифметики по дням.
	 *
	 * Через DateTimeImmutable, а не gmdate(strtotime()): если часовой пояс
	 * сервера не UTC, разбор строки даты через strtotime() и возврат через
	 * gmdate() съезжают на день — тот же приём, что и в InsurWP_Calculator.
	 *
	 * @return DateTimeImmutable Полночь сегодняшнего дня.
	 */
	private static function today() {
		return DateTimeImmutable::createFromFormat( '!Y-m-d', current_time( 'Y-m-d' ) );
	}

	/**
	 * Сумма расчётов за последние N дней, включая сегодня.
	 *
	 * @param int $days Сколько последних дней просуммировать.
	 * @return int Сумма.
	 */
	public static function range_total( $days ) {
		return array_sum( self::daily( $days ) );
	}

	/**
	 * Полностью обнуляет статистику.
	 */
	public static function reset() {
		delete_option( self::OPTION_TOTAL );
		delete_option( self::OPTION_DAILY );
	}

	/**
	 * Атомарно увеличивает общий счётчик через прямой SQL.
	 *
	 * update_option() сначала читает значение, потом пишет — при одновременных
	 * запросах это теряет часть инкрементов. UPDATE ... SET x = x + 1 атомарен
	 * на уровне СУБД независимо от нагрузки.
	 */
	private static function increment_total() {
		global $wpdb;

		$updated = false;

		if ( isset( $wpdb ) ) {
			$updated = (bool) $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->options} SET option_value = option_value + 1 WHERE option_name = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- имя таблицы, не пользовательский ввод.
					self::OPTION_TOTAL
				)
			);

			if ( $updated ) {
				// Прямой SQL мимо Options API не инвалидирует объектный кэш сам.
				wp_cache_delete( self::OPTION_TOTAL, 'options' );
			}
		}

		// Опции ещё нет (первый в жизни плагина расчёт) — завести её.
		if ( ! $updated ) {
			add_option( self::OPTION_TOTAL, 1, '', 'no' );
		}
	}

	/**
	 * Увеличивает счётчик сегодняшнего дня и подчищает старые записи.
	 */
	private static function increment_today() {
		$today_date = self::today();
		$today      = $today_date->format( 'Y-m-d' );
		$daily      = get_option( self::OPTION_DAILY, array() );

		if ( ! is_array( $daily ) ) {
			$daily = array();
		}

		$daily[ $today ] = isset( $daily[ $today ] ) ? (int) $daily[ $today ] + 1 : 1;

		$cutoff = $today_date->modify( '-' . self::DAILY_RETENTION_DAYS . ' days' )->format( 'Y-m-d' );

		foreach ( $daily as $date => $count ) {
			if ( $date < $cutoff ) {
				unset( $daily[ $date ] );
			}
		}

		update_option( self::OPTION_DAILY, $daily );
	}
}
