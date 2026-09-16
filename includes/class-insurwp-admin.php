<?php
/**
 * Админ-панель плагина.
 *
 * @package InsurWP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Страница настроек, редактор тарифов и синхронизация с внешним API.
 */
class InsurWP_Admin {

	/**
	 * Слаг страницы в админке.
	 */
	const PAGE = 'insurwp';

	/**
	 * Подключает хуки.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_insurwp_save_prices', array( __CLASS__, 'handle_save_prices' ) );
		add_action( 'admin_post_insurwp_apply_sync', array( __CLASS__, 'handle_apply_sync' ) );
		add_action( 'admin_post_insurwp_discard_sync', array( __CLASS__, 'handle_discard_sync' ) );
		add_action( 'admin_post_insurwp_reset_stats', array( __CLASS__, 'handle_reset_stats' ) );
		add_action( 'wp_ajax_insurwp_sync_step', array( __CLASS__, 'ajax_sync_step' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( INSURWP_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Добавляет ссылку «Настройки» в списке плагинов.
	 *
	 * @param array $links Существующие ссылки.
	 * @return array Дополненные ссылки.
	 */
	public static function action_links( $links ) {
		$url = admin_url( 'admin.php?page=' . self::PAGE );

		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Настройки', 'insurwp' ) . '</a>' );

		return $links;
	}

	/**
	 * Регистрирует пункт меню.
	 */
	public static function add_menu() {
		add_menu_page(
			__( 'Калькулятор страховки', 'insurwp' ),
			__( 'Страховка', 'insurwp' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render_page' ),
			'dashicons-shield-alt',
			58
		);
	}

	/**
	 * Регистрирует настройки через Settings API.
	 */
	public static function register_settings() {
		register_setting(
			'insurwp_settings_group',
			InsurWP_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'InsurWP_Settings', 'sanitize' ),
				'default'           => InsurWP_Settings::defaults(),
			)
		);
	}

	/**
	 * Подключает стили и скрипты админки.
	 *
	 * @param string $hook Идентификатор текущей страницы админки.
	 */
	public static function enqueue( $hook ) {
		if ( false === strpos( (string) $hook, self::PAGE ) ) {
			return;
		}

		wp_enqueue_style( 'insurwp-admin', INSURWP_URL . 'assets/css/insurwp-admin.css', array(), INSURWP_VERSION );
		wp_enqueue_script( 'insurwp-admin', INSURWP_URL . 'assets/js/insurwp-admin.js', array(), INSURWP_VERSION, true );

		wp_localize_script(
			'insurwp-admin',
			'insurwpAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'insurwp_sync' ),
				'strings' => array(
					'starting'     => __( 'Запускаем обновление…', 'insurwp' ),
					'progress'     => __( 'Обработано сроков: %1$d из %2$d', 'insurwp' ),
					'done'         => __( 'Готово. Обновляем страницу, чтобы показать изменения…', 'insurwp' ),
					'failed'       => __( 'Не удалось обновить тарифы:', 'insurwp' ),
					'badJson'      => __( 'JSON содержит ошибку:', 'insurwp' ),
					'goodJson'     => __( 'JSON корректен.', 'insurwp' ),
					'confirm'      => __( 'Запросить актуальные тарифы у bgmedins.com? Текущие тарифы не изменятся, пока вы не подтвердите результат.', 'insurwp' ),
					'resetConfirm' => __( 'Сбросить всю статистику расчётов? Это нельзя отменить.', 'insurwp' ),
				),
			)
		);
	}

	/**
	 * Проверяет права доступа и nonce.
	 *
	 * @param string $action Имя действия для nonce.
	 */
	private static function guard( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'insurwp' ) );
		}

		check_admin_referer( $action );
	}

	/**
	 * Возвращает пользователя на страницу плагина с сообщением.
	 *
	 * @param string $notice Код уведомления.
	 * @param string $tab    Вкладка, на которую вернуться.
	 */
	private static function redirect_back( $notice, $tab = 'prices' ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'           => self::PAGE,
					'tab'            => $tab,
					'insurwp_notice' => $notice,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Сохраняет тарифы из редактора.
	 */
	public static function handle_save_prices() {
		self::guard( 'insurwp_save_prices' );

		$raw = isset( $_POST['insurwp_prices_json'] ) ? wp_unslash( $_POST['insurwp_prices_json'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON проверяется целиком в parse_and_validate().

		$parsed = InsurWP_Prices::parse_and_validate( $raw );

		if ( is_wp_error( $parsed ) ) {
			// При ошибке старые тарифы не трогаем: сохраняем текст во временное
			// хранилище, чтобы администратор не потерял правки.
			set_transient( 'insurwp_prices_draft', $raw, 600 );
			set_transient( 'insurwp_prices_error', $parsed->get_error_message(), 600 );

			self::redirect_back( 'prices_error' );
		}

		delete_transient( 'insurwp_prices_draft' );
		delete_transient( 'insurwp_prices_error' );

		InsurWP_Prices::save( $parsed );

		self::redirect_back( 'prices_saved' );
	}

	/**
	 * Применяет результат синхронизации.
	 */
	public static function handle_apply_sync() {
		self::guard( 'insurwp_apply_sync' );

		$state = InsurWP_Api_Sync::pending();

		if ( ! $state || empty( $state['prices'] ) ) {
			self::redirect_back( 'sync_expired' );
		}

		InsurWP_Prices::save( $state['prices'] );
		InsurWP_Api_Sync::reset();

		self::redirect_back( 'sync_applied' );
	}

	/**
	 * Отменяет результат синхронизации.
	 */
	public static function handle_discard_sync() {
		self::guard( 'insurwp_discard_sync' );

		InsurWP_Api_Sync::reset();

		self::redirect_back( 'sync_discarded' );
	}

	/**
	 * Сбрасывает накопленную статистику расчётов.
	 */
	public static function handle_reset_stats() {
		self::guard( 'insurwp_reset_stats' );

		InsurWP_Stats::reset();

		self::redirect_back( 'stats_reset', 'stats' );
	}

	/**
	 * AJAX-шаг синхронизации: один срок за запрос.
	 */
	public static function ajax_sync_step() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Недостаточно прав.', 'insurwp' ) ), 403 );
		}

		check_ajax_referer( 'insurwp_sync', 'nonce' );

		$step = isset( $_POST['step'] ) ? sanitize_text_field( wp_unslash( $_POST['step'] ) ) : '';

		if ( 'start' === $step ) {
			$state = InsurWP_Api_Sync::start();
		} else {
			$state = InsurWP_Api_Sync::process_next();
		}

		if ( is_wp_error( $state ) ) {
			InsurWP_Api_Sync::reset();

			wp_send_json_error( array( 'message' => $state->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'index'  => (int) $state['index'],
				'total'  => (int) $state['total'],
				'filled' => (int) $state['filled'],
				'done'   => $state['index'] >= $state['total'],
			)
		);
	}

	/**
	 * Рисует страницу плагина.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- только выбор вкладки.
		$tab = in_array( $tab, array( 'settings', 'stats', 'prices', 'help' ), true ) ? $tab : 'settings';
		?>
		<div class="wrap insurwp-admin">
			<h1><?php esc_html_e( 'Калькулятор страховки', 'insurwp' ); ?></h1>

			<?php self::render_notices(); ?>

			<h2 class="nav-tab-wrapper">
				<?php
				$tabs = array(
					'settings' => __( 'Настройки', 'insurwp' ),
					'stats'    => __( 'Статистика', 'insurwp' ),
					'prices'   => __( 'Тарифы', 'insurwp' ),
					'help'     => __( 'Подключение', 'insurwp' ),
				);

				foreach ( $tabs as $key => $label ) {
					printf(
						'<a href="%s" class="nav-tab %s">%s</a>',
						esc_url( admin_url( 'admin.php?page=' . self::PAGE . '&tab=' . $key ) ),
						$key === $tab ? 'nav-tab-active' : '',
						esc_html( $label )
					);
				}
				?>
			</h2>

			<?php
			if ( 'prices' === $tab ) {
				self::render_prices_tab();
			} elseif ( 'stats' === $tab ) {
				self::render_stats_tab();
			} elseif ( 'help' === $tab ) {
				self::render_help_tab();
			} else {
				self::render_settings_tab();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Выводит уведомления после редиректов.
	 */
	private static function render_notices() {
		$notice = isset( $_GET['insurwp_notice'] ) ? sanitize_key( wp_unslash( $_GET['insurwp_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- только текст уведомления.

		if ( '' === $notice ) {
			return;
		}

		$map = array(
			'prices_saved'   => array( 'success', __( 'Тарифы сохранены.', 'insurwp' ) ),
			'sync_applied'   => array( 'success', __( 'Тарифы обновлены из внешнего сервиса.', 'insurwp' ) ),
			'sync_discarded' => array( 'info', __( 'Результат обновления отменён, тарифы не изменились.', 'insurwp' ) ),
			'sync_expired'   => array( 'error', __( 'Сессия обновления истекла. Запустите обновление заново.', 'insurwp' ) ),
			'prices_error'   => array( 'error', get_transient( 'insurwp_prices_error' ) ),
			'stats_reset'    => array( 'success', __( 'Статистика сброшена.', 'insurwp' ) ),
		);

		if ( ! isset( $map[ $notice ] ) ) {
			return;
		}

		$message = $map[ $notice ][1];

		if ( empty( $message ) ) {
			return;
		}

		// Текст ошибки одноразовый: иначе он всплывал бы снова при обновлении страницы.
		if ( 'prices_error' === $notice ) {
			delete_transient( 'insurwp_prices_error' );
		}

		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( $map[ $notice ][0] ),
			esc_html( $message )
		);
	}

	/**
	 * Вкладка настроек.
	 */
	private static function render_settings_tab() {
		$settings = InsurWP_Settings::all();
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'insurwp_settings_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="insurwp-order-url"><?php esc_html_e( 'Страница оформления', 'insurwp' ); ?></label></th>
					<td>
						<input type="url" class="regular-text" id="insurwp-order-url"
							name="<?php echo esc_attr( InsurWP_Settings::OPTION ); ?>[order_url]"
							value="<?php echo esc_attr( $settings['order_url'] ); ?>">
						<p class="description"><?php esc_html_e( 'Куда ведёт кнопка «Заказать». Параметры расчёта добавляются к адресу автоматически.', 'insurwp' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="insurwp-miniapp-origins"><?php esc_html_e( 'Адреса мини-аппа', 'insurwp' ); ?></label></th>
					<td>
						<textarea class="large-text code" id="insurwp-miniapp-origins" rows="3"
							name="<?php echo esc_attr( InsurWP_Settings::OPTION ); ?>[miniapp_origins]"><?php echo esc_textarea( $settings['miniapp_origins'] ); ?></textarea>
						<p class="description">
							<?php esc_html_e( 'Каким сайтам разрешено обращаться к калькулятору с другого домена — по одному адресу в строке, например https://miniapp.bginfo.eu. Для локальной разработки добавьте http://localhost:5173.', 'insurwp' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Отображение цен', 'insurwp' ); ?></th>
					<td>
						<label>
							<input type="checkbox" value="1"
								name="<?php echo esc_attr( InsurWP_Settings::OPTION ); ?>[show_bgn]"
								<?php checked( ! empty( $settings['show_bgn'] ) ); ?>>
							<?php esc_html_e( 'Показывать цену также в левах', 'insurwp' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Основная валюта расчёта — евро. Левы считаются по курсу ниже.', 'insurwp' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="insurwp-rate"><?php esc_html_e( 'Курс EUR → BGN', 'insurwp' ); ?></label></th>
					<td>
						<input type="text" class="small-text" id="insurwp-rate"
							name="<?php echo esc_attr( InsurWP_Settings::OPTION ); ?>[bgn_rate]"
							value="<?php echo esc_attr( $settings['bgn_rate'] ); ?>">
						<p class="description"><?php esc_html_e( 'По умолчанию 1.95583 — фиксированная привязка лева к евро.', 'insurwp' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="insurwp-country"><?php esc_html_e( 'Гражданство по умолчанию', 'insurwp' ); ?></label></th>
					<td>
						<input type="text" class="small-text" id="insurwp-country" maxlength="2"
							name="<?php echo esc_attr( InsurWP_Settings::OPTION ); ?>[default_country]"
							value="<?php echo esc_attr( $settings['default_country'] ); ?>">
						<p class="description"><?php esc_html_e( 'Двухбуквенный код ISO, например RU. На форме не спрашивается и на цену не влияет — передаётся на страницу оформления.', 'insurwp' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="insurwp-agent"><?php esc_html_e( 'Идентификатор агента', 'insurwp' ); ?></label></th>
					<td>
						<input type="text" class="small-text" id="insurwp-agent"
							name="<?php echo esc_attr( InsurWP_Settings::OPTION ); ?>[agent_id]"
							value="<?php echo esc_attr( $settings['agent_id'] ); ?>">
						<p class="description"><?php esc_html_e( 'Поле agent_id на стороне сервиса оформления. По умолчанию 49.', 'insurwp' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="insurwp-title"><?php esc_html_e( 'Заголовок калькулятора', 'insurwp' ); ?></label></th>
					<td>
						<input type="text" class="regular-text" id="insurwp-title"
							name="<?php echo esc_attr( InsurWP_Settings::OPTION ); ?>[title]"
							value="<?php echo esc_attr( $settings['title'] ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="insurwp-accent"><?php esc_html_e( 'Акцентный цвет', 'insurwp' ); ?></label></th>
					<td>
						<input type="color" id="insurwp-accent"
							name="<?php echo esc_attr( InsurWP_Settings::OPTION ); ?>[accent]"
							value="<?php echo esc_attr( $settings['accent'] ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Прочее', 'insurwp' ); ?></th>
					<td>
						<label>
							<input type="checkbox" value="1"
								name="<?php echo esc_attr( InsurWP_Settings::OPTION ); ?>[autocalc]"
								<?php checked( ! empty( $settings['autocalc'] ) ); ?>>
							<?php esc_html_e( 'Запускать расчёт на странице оформления автоматически', 'insurwp' ); ?>
						</label><br>
						<label>
							<input type="checkbox" value="1"
								name="<?php echo esc_attr( InsurWP_Settings::OPTION ); ?>[show_disclaimer]"
								<?php checked( ! empty( $settings['show_disclaimer'] ) ); ?>>
							<?php esc_html_e( 'Показывать примечание о справочном характере расчёта', 'insurwp' ); ?>
						</label>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Вкладка статистики.
	 */
	private static function render_stats_tab() {
		$total = InsurWP_Stats::total();
		$today = InsurWP_Stats::range_total( 1 );
		$week  = InsurWP_Stats::range_total( 7 );
		$month = InsurWP_Stats::range_total( 30 );
		$daily = InsurWP_Stats::daily( 30 );
		?>
		<h2><?php esc_html_e( 'Статистика расчётов', 'insurwp' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Считается каждый успешный расчёт через калькулятор — на сайте и в Telegram-мини-аппе одинаково.', 'insurwp' ); ?>
		</p>

		<div class="insurwp-admin__stats-cards">
			<div class="insurwp-admin__stats-card">
				<span class="insurwp-admin__stats-value"><?php echo esc_html( number_format_i18n( $total ) ); ?></span>
				<span class="insurwp-admin__stats-label"><?php esc_html_e( 'Всего расчётов', 'insurwp' ); ?></span>
			</div>
			<div class="insurwp-admin__stats-card">
				<span class="insurwp-admin__stats-value"><?php echo esc_html( number_format_i18n( $today ) ); ?></span>
				<span class="insurwp-admin__stats-label"><?php esc_html_e( 'Сегодня', 'insurwp' ); ?></span>
			</div>
			<div class="insurwp-admin__stats-card">
				<span class="insurwp-admin__stats-value"><?php echo esc_html( number_format_i18n( $week ) ); ?></span>
				<span class="insurwp-admin__stats-label"><?php esc_html_e( 'За 7 дней', 'insurwp' ); ?></span>
			</div>
			<div class="insurwp-admin__stats-card">
				<span class="insurwp-admin__stats-value"><?php echo esc_html( number_format_i18n( $month ) ); ?></span>
				<span class="insurwp-admin__stats-label"><?php esc_html_e( 'За 30 дней', 'insurwp' ); ?></span>
			</div>
		</div>

		<h3><?php esc_html_e( 'По дням', 'insurwp' ); ?></h3>
		<?php self::render_stats_chart( $daily ); ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="insurwp-stats-reset-form">
			<?php wp_nonce_field( 'insurwp_reset_stats' ); ?>
			<input type="hidden" name="action" value="insurwp_reset_stats">
			<button type="submit" class="button" id="insurwp-stats-reset">
				<?php esc_html_e( 'Сбросить статистику', 'insurwp' ); ?>
			</button>
		</form>
		<?php
	}

	/**
	 * Рисует столбчатый график расчётов по дням — простым inline SVG,
	 * без графических библиотек: тот же принцип, что и у фронтенда плагина.
	 *
	 * @param array<string,int> $daily Разбивка от сегодня к прошлому (см. InsurWP_Stats::daily()).
	 */
	private static function render_stats_chart( array $daily ) {
		// Для графика нужен порядок слева направо по времени — наоборот тому,
		// что удобно для карточек и текстовых списков.
		$days  = array_reverse( $daily, true );
		$count = count( $days );

		if ( 0 === $count ) {
			return;
		}

		$max = max( 1, max( $days ) );

		$width        = 640;
		$height       = 200;
		$top          = 10;
		$bottom       = 22;
		$chart_height = $height - $top - $bottom;
		$gap          = 3;
		$bar_width    = ( $width - ( $count - 1 ) * $gap ) / $count;
		$label_step   = (int) max( 1, round( $count / 6 ) );
		?>
		<svg class="insurwp-admin__stats-chart" viewBox="0 0 <?php echo esc_attr( $width ); ?> <?php echo esc_attr( $height ); ?>" role="img" aria-label="<?php esc_attr_e( 'Расчёты по дням', 'insurwp' ); ?>">
			<?php
			$index = 0;

			foreach ( $days as $date => $value ) {
				// Минимум 2px даже для нулевых дней — иначе у пустого дня
				// не остаётся области для наведения курсора.
				$bar_height = max( 2, $chart_height * ( $value / $max ) );
				$x          = $index * ( $bar_width + $gap );
				$y          = $top + ( $chart_height - $bar_height );

				printf(
					'<rect class="insurwp-admin__stats-bar" x="%s" y="%s" width="%s" height="%s"><title>%s: %s</title></rect>',
					esc_attr( round( $x, 1 ) ),
					esc_attr( round( $y, 1 ) ),
					esc_attr( round( $bar_width, 1 ) ),
					esc_attr( round( $bar_height, 1 ) ),
					esc_html( $date ),
					esc_html( number_format_i18n( $value ) )
				);

				if ( 0 === $index % $label_step || $index === $count - 1 ) {
					printf(
						'<text class="insurwp-admin__stats-axis" x="%s" y="%s" text-anchor="middle">%s</text>',
						esc_attr( round( $x + $bar_width / 2, 1 ) ),
						esc_attr( $height - 6 ),
						esc_html( substr( $date, 5 ) )
					);
				}

				++$index;
			}
			?>
		</svg>
		<?php
	}

	/**
	 * Вкладка тарифов.
	 */
	private static function render_prices_tab() {
		$draft   = get_transient( 'insurwp_prices_draft' );
		$json    = $draft ? $draft : InsurWP_Prices::to_json();
		$updated = InsurWP_Prices::updated_at();
		$pending = InsurWP_Api_Sync::pending();
		$ready   = $pending && isset( $pending['index'], $pending['total'] ) && $pending['index'] >= $pending['total'];

		delete_transient( 'insurwp_prices_draft' );
		?>
		<div class="insurwp-admin__sync">
			<h2><?php esc_html_e( 'Обновление тарифов из сервиса bgmedins.com', 'insurwp' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Плагин опросит внешний сервис по всем срокам и контрольным возрастам и покажет, что изменилось. Тарифы будут перезаписаны только после вашего подтверждения.', 'insurwp' ); ?>
			</p>

			<?php if ( $ready ) : ?>
				<?php
				$changes = InsurWP_Api_Sync::diff( InsurWP_Prices::get(), $pending['prices'] );
				?>
				<div class="notice notice-info inline">
					<p>
						<?php
						printf(
							/* translators: %d: количество изменений. */
							esc_html__( 'Обновление завершено. Изменений: %d.', 'insurwp' ),
							count( $changes )
						);
						?>
					</p>
				</div>

				<?php if ( ! empty( $changes ) ) : ?>
					<table class="widefat striped insurwp-admin__diff">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Страховщик', 'insurwp' ); ?></th>
								<th><?php esc_html_e( 'Срок', 'insurwp' ); ?></th>
								<th><?php esc_html_e( 'Ячейка', 'insurwp' ); ?></th>
								<th><?php esc_html_e( 'Было', 'insurwp' ); ?></th>
								<th><?php esc_html_e( 'Стало', 'insurwp' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $changes as $change ) : ?>
								<tr>
									<td><?php echo esc_html( $change['insurer'] ); ?></td>
									<td><?php echo esc_html( $change['term'] ); ?></td>
									<td><code><?php echo esc_html( $change['cell'] ); ?></code></td>
									<td><?php echo esc_html( $change['from'] ); ?></td>
									<td><strong><?php echo esc_html( $change['to'] ); ?></strong></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>

				<div class="insurwp-admin__sync-actions">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
						<?php wp_nonce_field( 'insurwp_apply_sync' ); ?>
						<input type="hidden" name="action" value="insurwp_apply_sync">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Применить новые тарифы', 'insurwp' ); ?></button>
					</form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
						<?php wp_nonce_field( 'insurwp_discard_sync' ); ?>
						<input type="hidden" name="action" value="insurwp_discard_sync">
						<button type="submit" class="button"><?php esc_html_e( 'Отменить', 'insurwp' ); ?></button>
					</form>
				</div>
			<?php else : ?>
				<p>
					<button type="button" class="button button-secondary" id="insurwp-sync-start">
						<?php esc_html_e( 'Обновить цены из API', 'insurwp' ); ?>
					</button>
					<span class="insurwp-admin__sync-status" id="insurwp-sync-status" role="status"></span>
				</p>
				<div class="insurwp-admin__progress" id="insurwp-sync-progress" hidden>
					<div class="insurwp-admin__progress-bar" id="insurwp-sync-bar"></div>
				</div>
			<?php endif; ?>
		</div>

		<hr>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'insurwp_save_prices' ); ?>
			<input type="hidden" name="action" value="insurwp_save_prices">

			<h2><?php esc_html_e( 'JSON тарифов', 'insurwp' ); ?></h2>
			<?php if ( $updated ) : ?>
				<p class="description">
					<?php
					printf(
						/* translators: %s: дата последнего обновления. */
						esc_html__( 'Последнее обновление: %s', 'insurwp' ),
						esc_html( $updated )
					);
					?>
				</p>
			<?php endif; ?>

			<textarea id="insurwp-prices-json" name="insurwp_prices_json" rows="24" spellcheck="false"
				class="large-text code insurwp-admin__editor"><?php echo esc_textarea( $json ); ?></textarea>

			<p class="insurwp-admin__json-status" id="insurwp-json-status" role="status"></p>

			<?php submit_button( __( 'Сохранить тарифы', 'insurwp' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Вкладка с инструкцией по подключению.
	 */
	private static function render_help_tab() {
		$snippet_url = INSURWP_URL . 'assets/js/bginfo-prefill.js';
		$order_url   = InsurWP_Settings::get( 'order_url' );
		?>
		<h2><?php esc_html_e( 'Как вставить калькулятор на страницу', 'insurwp' ); ?></h2>
		<p><?php esc_html_e( 'Добавьте шорткод в любую запись, страницу или текстовый виджет:', 'insurwp' ); ?></p>
		<p><code>[insurwp_calculator]</code></p>

		<p><?php esc_html_e( 'Необязательные атрибуты:', 'insurwp' ); ?></p>
		<ul class="ul-disc">
			<li><code>title</code> — <?php esc_html_e( 'заголовок над формой.', 'insurwp' ); ?></li>
			<li><code>order_url</code> — <?php esc_html_e( 'своя страница оформления для этого экземпляра.', 'insurwp' ); ?></li>
			<li><code>show_bgn</code> — <code>yes</code> / <code>no</code>, <?php esc_html_e( 'показывать ли цену в левах.', 'insurwp' ); ?></li>
			<li><code>accent</code> — <?php esc_html_e( 'акцентный цвет, например', 'insurwp' ); ?> <code>#f0ad4e</code>.</li>
			<li><code>territory</code> — <code>all</code> / <code>bulgaria</code> / <code>schengen</code>, <?php esc_html_e( 'территория по умолчанию.', 'insurwp' ); ?></li>
		</ul>

		<p><code>[insurwp_calculator title="Страховка для ВНЖ" show_bgn="yes" territory="bulgaria"]</code></p>

		<hr>

		<h2><?php esc_html_e( 'Автозаполнение на странице оформления', 'insurwp' ); ?></h2>
		<p>
			<?php
			printf(
				/* translators: %s: адрес страницы оформления. */
				esc_html__( 'Кнопка «Заказать» открывает %s и передаёт параметры расчёта в адресной строке. Сама эта страница параметры из URL не читает, поэтому на неё нужно один раз добавить сниппет — после этого поля будут заполняться автоматически.', 'insurwp' ),
				esc_html( $order_url )
			);
			?>
		</p>
		<p>
			<a class="button" href="<?php echo esc_url( $snippet_url ); ?>" download>
				<?php esc_html_e( 'Скачать сниппет bginfo-prefill.js', 'insurwp' ); ?>
			</a>
		</p>
		<p><?php esc_html_e( 'Подключите его на странице оформления перед закрывающим тегом body:', 'insurwp' ); ?></p>
		<p><code>&lt;script src="/js/bginfo-prefill.js" defer&gt;&lt;/script&gt;</code></p>
		<p class="description">
			<?php esc_html_e( 'Сниппет ничего не меняет, если параметров в адресе нет, — обычное открытие страницы работает как раньше.', 'insurwp' ); ?>
		</p>
		<?php
	}
}
