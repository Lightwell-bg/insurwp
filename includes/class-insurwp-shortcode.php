<?php
/**
 * Шорткод калькулятора.
 *
 * @package InsurWP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Регистрация шорткода [insurwp_calculator] и вывод формы.
 */
class InsurWP_Shortcode {

	/**
	 * Счётчик экземпляров на странице — нужен для уникальных id полей.
	 *
	 * @var int
	 */
	private static $instance = 0;

	/**
	 * Ассеты уже зарегистрированы.
	 *
	 * @var bool
	 */
	private static $registered = false;

	/**
	 * Подключает хуки.
	 */
	public static function init() {
		add_shortcode( 'insurwp_calculator', array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	/**
	 * Регистрирует стили и скрипты. Подключаются они только там,
	 * где на странице реально встретился шорткод.
	 */
	public static function register_assets() {
		if ( self::$registered ) {
			return;
		}

		wp_register_style( 'insurwp', INSURWP_URL . 'assets/css/insurwp.css', array(), INSURWP_VERSION );
		wp_register_script( 'insurwp', INSURWP_URL . 'assets/js/insurwp.js', array(), INSURWP_VERSION, true );

		self::$registered = true;
	}

	/**
	 * Рендерит калькулятор.
	 *
	 * @param array $atts Атрибуты шорткода.
	 * @return string HTML калькулятора.
	 */
	public static function render( $atts ) {
		$settings = InsurWP_Settings::all();

		$atts = shortcode_atts(
			array(
				'title'     => $settings['title'],
				'order_url' => $settings['order_url'],
				'show_bgn'  => $settings['show_bgn'] ? 'yes' : 'no',
				'accent'    => $settings['accent'],
				'territory' => 'all',
			),
			$atts,
			'insurwp_calculator'
		);

		self::register_assets();
		wp_enqueue_style( 'insurwp' );
		wp_enqueue_script( 'insurwp' );

		$prices = InsurWP_Prices::get();
		$terms  = InsurWP_Catalog::terms_from_prices( $prices );
		$limits = InsurWP_Prices::limits( $prices );

		self::$instance++;
		$uid = 'insurwp-' . self::$instance;

		$show_bgn = in_array( strtolower( (string) $atts['show_bgn'] ), array( 'yes', '1', 'true', 'on' ), true );

		// Данные общие для всех экземпляров шорткода: REST-адрес и nonce одинаковые.
		wp_localize_script(
			'insurwp',
			'insurwpConfig',
			array(
				'root'     => esc_url_raw( rest_url( 'insurwp/v1/quote' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'strings'  => array(
					'calculating' => __( 'Считаем…', 'insurwp' ),
					'error'       => __( 'Не удалось рассчитать стоимость. Попробуйте ещё раз.', 'insurwp' ),
					'empty'       => __( 'Для указанных параметров предложений нет.', 'insurwp' ),
					'emptyHint'   => __( 'Попробуйте изменить срок или проверьте дату рождения — часть страховщиков не оформляет полисы для некоторых возрастов.', 'insurwp' ),
					'order'       => __( 'Заказать', 'insurwp' ),
					'perPolicy'   => __( 'за весь срок', 'insurwp' ),
					'best'        => __( 'Выгоднее всего', 'insurwp' ),
					'limitLabel'  => __( 'Лимит', 'insurwp' ),
					'ageLabel'    => __( 'Возраст на дату начала', 'insurwp' ),
					'years'       => __( 'лет', 'insurwp' ),
					'found'       => __( 'Найдено предложений:', 'insurwp' ),
				),
			)
		);

		$today    = current_time( 'Y-m-d' );
		$max_date = gmdate( 'Y-m-d', strtotime( $today . ' +1 year' ) );
		// Ограничение по возрасту: верхняя граница тарифов — 85 лет.
		$min_birth = gmdate( 'Y-m-d', strtotime( $today . ' -85 years' ) );

		$default_term = '';

		foreach ( $terms as $term ) {
			if ( '12 месяцев' === $term['value'] ) {
				$default_term = $term['value'];
			}
		}

		if ( '' === $default_term && ! empty( $terms ) ) {
			$default_term = $terms[0]['value'];
		}

		ob_start();
		?>
		<div class="insurwp"
			id="<?php echo esc_attr( $uid ); ?>"
			data-insurwp
			data-show-bgn="<?php echo $show_bgn ? '1' : '0'; ?>"
			style="--insurwp-accent: <?php echo esc_attr( $atts['accent'] ); ?>;">

			<div class="insurwp__head">
				<h3 class="insurwp__title"><?php echo esc_html( $atts['title'] ); ?></h3>
				<button type="button" class="insurwp__recalc" data-insurwp-recalc hidden>
					<?php esc_html_e( 'Пересчитать', 'insurwp' ); ?>
				</button>
			</div>

			<form class="insurwp__form" data-insurwp-form novalidate>
				<div class="insurwp__grid">
					<div class="insurwp__field">
						<label class="insurwp__label" for="<?php echo esc_attr( $uid ); ?>-territory">
							<?php esc_html_e( 'Тип страховки', 'insurwp' ); ?>
						</label>
						<select class="insurwp__control" id="<?php echo esc_attr( $uid ); ?>-territory" name="territory">
							<option value="all"<?php selected( $atts['territory'], 'all' ); ?>><?php esc_html_e( 'Все предложения', 'insurwp' ); ?></option>
							<?php foreach ( InsurWP_Catalog::TERRITORIES as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>"<?php selected( $atts['territory'], $key ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="insurwp__field">
						<label class="insurwp__label" for="<?php echo esc_attr( $uid ); ?>-limit">
							<?php esc_html_e( 'Сумма страхования', 'insurwp' ); ?>
						</label>
						<select class="insurwp__control" id="<?php echo esc_attr( $uid ); ?>-limit" name="limit">
							<?php foreach ( $limits as $limit ) : ?>
								<option value="<?php echo esc_attr( $limit['value'] ); ?>"><?php echo esc_html( $limit['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="insurwp__field">
						<label class="insurwp__label" for="<?php echo esc_attr( $uid ); ?>-term">
							<?php esc_html_e( 'Срок страховки', 'insurwp' ); ?>
						</label>
						<select class="insurwp__control" id="<?php echo esc_attr( $uid ); ?>-term" name="term" required>
							<?php foreach ( $terms as $term ) : ?>
								<option value="<?php echo esc_attr( $term['value'] ); ?>"<?php selected( $default_term, $term['value'] ); ?>>
									<?php echo esc_html( $term['label'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="insurwp__field">
						<label class="insurwp__label" for="<?php echo esc_attr( $uid ); ?>-date-start">
							<?php esc_html_e( 'Дата начала страховки', 'insurwp' ); ?>
						</label>
						<input class="insurwp__control" type="date" id="<?php echo esc_attr( $uid ); ?>-date-start"
							name="date_start"
							value="<?php echo esc_attr( $today ); ?>"
							min="<?php echo esc_attr( $today ); ?>"
							max="<?php echo esc_attr( $max_date ); ?>" required>
					</div>

					<div class="insurwp__field">
						<label class="insurwp__label" for="<?php echo esc_attr( $uid ); ?>-birth-date">
							<?php esc_html_e( 'Дата вашего рождения', 'insurwp' ); ?>
						</label>
						<input class="insurwp__control" type="date" id="<?php echo esc_attr( $uid ); ?>-birth-date"
							name="birth_date"
							min="<?php echo esc_attr( $min_birth ); ?>"
							max="<?php echo esc_attr( $today ); ?>" required>
					</div>

					<div class="insurwp__field insurwp__field--submit">
						<button type="submit" class="insurwp__submit">
							<?php esc_html_e( 'Рассчитать стоимость', 'insurwp' ); ?>
						</button>
					</div>
				</div>

				<p class="insurwp__error" data-insurwp-error role="alert" hidden></p>
			</form>

			<div class="insurwp__summary" data-insurwp-summary hidden></div>
			<div class="insurwp__results" data-insurwp-results aria-live="polite"></div>

			<?php if ( ! empty( $settings['show_disclaimer'] ) ) : ?>
				<p class="insurwp__note">
					<?php esc_html_e( 'Расчёт носит справочный характер. Окончательная стоимость подтверждается страховщиком при оформлении полиса.', 'insurwp' ); ?>
				</p>
			<?php endif; ?>
		</div>
		<?php

		return (string) ob_get_clean();
	}
}
