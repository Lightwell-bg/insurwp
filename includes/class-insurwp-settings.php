<?php
/**
 * Настройки плагина.
 *
 * @package InsurWP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Чтение, значения по умолчанию и санитизация настроек.
 */
class InsurWP_Settings {

	/**
	 * Имя опции с настройками.
	 */
	const OPTION = 'insurwp_settings';

	/**
	 * Значения по умолчанию.
	 *
	 * @return array Настройки по умолчанию.
	 */
	public static function defaults() {
		return array(
			'order_url'       => 'https://bginfo.eu/insur/',
			'show_bgn'        => 1,
			'bgn_rate'        => InsurWP_Catalog::DEFAULT_BGN_RATE,
			'default_country' => 'RU',
			'agent_id'        => '49',
			'accent'          => '#f0ad4e',
			'title'           => 'Расчёт стоимости страховки',
			'autocalc'        => 1,
			'show_disclaimer' => 1,
		);
	}

	/**
	 * Возвращает все настройки, дополненные значениями по умолчанию.
	 *
	 * @return array Настройки.
	 */
	public static function all() {
		$saved = get_option( self::OPTION, array() );

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return array_merge( self::defaults(), $saved );
	}

	/**
	 * Возвращает одну настройку.
	 *
	 * @param string $key     Ключ настройки.
	 * @param mixed  $default Значение, если ключа нет.
	 * @return mixed Значение настройки.
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();

		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Записывает настройки по умолчанию при активации, если их ещё нет.
	 */
	public static function seed_if_empty() {
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, self::defaults() );
		}
	}

	/**
	 * Санитизация значений из формы настроек.
	 *
	 * @param array $input Сырые значения из $_POST.
	 * @return array Очищенные настройки.
	 */
	public static function sanitize( $input ) {
		$defaults = self::defaults();
		$input    = is_array( $input ) ? $input : array();
		$clean    = array();

		$order_url            = isset( $input['order_url'] ) ? esc_url_raw( trim( (string) $input['order_url'] ) ) : '';
		$clean['order_url']   = '' !== $order_url ? $order_url : $defaults['order_url'];
		$clean['show_bgn']    = empty( $input['show_bgn'] ) ? 0 : 1;
		$clean['autocalc']    = empty( $input['autocalc'] ) ? 0 : 1;
		$clean['show_disclaimer'] = empty( $input['show_disclaimer'] ) ? 0 : 1;

		$rate               = isset( $input['bgn_rate'] ) ? (float) str_replace( ',', '.', (string) $input['bgn_rate'] ) : 0.0;
		$clean['bgn_rate']  = $rate > 0 ? round( $rate, 5 ) : $defaults['bgn_rate'];

		$country                  = isset( $input['default_country'] ) ? strtoupper( sanitize_text_field( $input['default_country'] ) ) : '';
		$clean['default_country'] = preg_match( '/^[A-Z]{2}$/', $country ) ? $country : $defaults['default_country'];

		$agent_id           = isset( $input['agent_id'] ) ? sanitize_text_field( $input['agent_id'] ) : '';
		$clean['agent_id']  = preg_match( '/^\d+$/', $agent_id ) ? $agent_id : $defaults['agent_id'];

		$accent          = isset( $input['accent'] ) ? sanitize_hex_color( $input['accent'] ) : '';
		$clean['accent'] = $accent ? $accent : $defaults['accent'];

		$title          = isset( $input['title'] ) ? sanitize_text_field( $input['title'] ) : '';
		$clean['title'] = '' !== $title ? $title : $defaults['title'];

		return $clean;
	}
}
