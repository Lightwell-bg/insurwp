<?php
/**
 * Смоук-тест серверного пути: активация, шорткод, REST, валидация, настройки.
 *
 * Запуск: php tests/smoke-server.php
 * Внешняя сеть не требуется.
 *
 * @package InsurWP
 */

require_once __DIR__ . '/wp-stubs.php';

echo "== Активация ==\n";
InsurWP_Prices::seed_if_empty();
InsurWP_Settings::seed_if_empty();
insurwp_check( 'тарифы засеяны', 2 === count( InsurWP_Prices::get()['insurers'] ) );
insurwp_check( 'настройки засеяны', 'https://bginfo.eu/insur/' === InsurWP_Settings::get( 'order_url' ) );

echo "\n== Шорткод ==\n";
InsurWP_Shortcode::init();
$html = InsurWP_Shortcode::render( array() );

insurwp_check( 'разметка не пустая', strlen( $html ) > 1000 );
insurwp_check( 'есть контейнер', str_contains( $html, 'data-insurwp' ) );
insurwp_check( 'поле срока', str_contains( $html, 'name="term"' ) );
insurwp_check( 'поле даты начала', str_contains( $html, 'name="date_start"' ) );
insurwp_check( 'поле даты рождения', str_contains( $html, 'name="birth_date"' ) );
insurwp_check( 'поле территории', str_contains( $html, 'name="territory"' ) );
insurwp_check( 'поля гражданства нет', ! str_contains( $html, 'foreigner_country' ) );
insurwp_check( 'кнопка «Пересчитать»', str_contains( $html, 'data-insurwp-recalc' ) );
insurwp_check( 'лимит подписан', str_contains( $html, '60 000Лв / 30677.51 €' ) );
insurwp_check( 'ассеты подключены', in_array( 'script:insurwp', $GLOBALS['insurwp_enqueued'], true ) );
insurwp_check( 'nonce передан в JS', isset( $GLOBALS['insurwp_localized']['insurwpConfig']['nonce'] ) );
insurwp_check( 'div-теги сбалансированы', substr_count( $html, '<div' ) === substr_count( $html, '</div>' ) );

preg_match( '/<select[^>]*name="term"[^>]*>(.*?)<\/select>/s', $html, $matches );
$term_options = isset( $matches[1] ) ? substr_count( $matches[1], '<option' ) : 0;
insurwp_check( '15 сроков в списке (получено ' . $term_options . ')', 15 === $term_options );
insurwp_check( 'дни идут перед месяцами', isset( $matches[1] ) && strpos( $matches[1], 'на 3 дня' ) < strpos( $matches[1], 'на 1 месяц' ) );
insurwp_check( 'по умолчанию 12 месяцев', str_contains( $html, "value=\"12 месяцев\" selected='selected'" ) );

echo "\n== Атрибуты шорткода ==\n";
$custom = InsurWP_Shortcode::render(
	array(
		'title'     => 'Моя страховка',
		'territory' => 'schengen',
		'accent'    => '#ff0000',
	)
);
insurwp_check( 'заголовок применён', str_contains( $custom, 'Моя страховка' ) );
insurwp_check( 'акцентный цвет применён', str_contains( $custom, '--insurwp-accent: #ff0000' ) );
insurwp_check( 'территория выбрана', str_contains( $custom, "value=\"schengen\" selected='selected'" ) );

echo "\n== REST ==\n";
InsurWP_Rest::register_routes();
insurwp_check( 'маршрут зарегистрирован', isset( $GLOBALS['insurwp_routes']['insurwp/v1/quote'] ) );

$response = InsurWP_Rest::quote(
	new WP_REST_Request(
		array(
			'term'       => '12 месяцев',
			'date_start' => '2026-08-06',
			'birth_date' => '1990-07-01',
			'territory'  => 'all',
		)
	)
);

insurwp_check( 'ответ не ошибка', ! is_wp_error( $response ) );
insurwp_check( '4 предложения', 4 === count( $response['offers'] ) );
insurwp_check( 'возраст 36', 36 === $response['age'] );
insurwp_check( 'самое дешёвое 89.70 €', 89.70 === $response['offers'][0]['price_eur'] );
insurwp_check( 'левы 175.44', 175.44 === $response['offers'][0]['price_bgn'] );

$order = $response['offers'][0]['order_url'];
echo "  ссылка: $order\n";
parse_str( (string) parse_url( $order, PHP_URL_QUERY ), $query );

insurwp_check( 'ведёт на страницу оформления', str_starts_with( $order, 'https://bginfo.eu/insur/?' ) );
insurwp_check( 'foreigner_term = 12', '12' === $query['foreigner_term'] );
insurwp_check( 'date_start = 06.08.2026', '06.08.2026' === $query['date_start'] );
insurwp_check( 'dfb = 01.07.1990', '01.07.1990' === $query['dfb'] );
insurwp_check( 'foreigner_country = RU', 'RU' === $query['foreigner_country'] );
insurwp_check( 'agent_id = 49', '49' === $query['agent_id'] );
insurwp_check( 'insurer = unica', 'unica' === $query['insurer'] );
insurwp_check( 'autocalc = 1', '1' === $query['autocalc'] );

$error = InsurWP_Rest::quote(
	new WP_REST_Request(
		array(
			'term'       => '12 месяцев',
			'date_start' => 'не дата',
			'birth_date' => '1990-07-01',
		)
	)
);
insurwp_check( 'битая дата возвращает WP_Error', is_wp_error( $error ) );

echo "\n== Валидация тарифов ==\n";
insurwp_check( 'битый JSON отклонён', is_wp_error( InsurWP_Prices::parse_and_validate( '{oops' ) ) );
insurwp_check( 'пустые insurers отклонены', is_wp_error( InsurWP_Prices::parse_and_validate( '{"insurers":{}}' ) ) );
insurwp_check( 'строка без term отклонена', is_wp_error( InsurWP_Prices::parse_and_validate( '{"insurers":{"x":{"age_groups":{"a":{}},"prices":[{"bulgaria_a":1}]}}}' ) ) );
insurwp_check( 'корректный JSON принят', is_array( InsurWP_Prices::parse_and_validate( InsurWP_Prices::to_json() ) ) );

$roundtrip = InsurWP_Prices::parse_and_validate( InsurWP_Prices::to_json() );
insurwp_check( 'round-trip не теряет данные', 89.70 === (float) $roundtrip['insurers']['uniqa']['prices'][14]['bulgaria_0_69'] );

echo "\n== Санитизация настроек ==\n";
$clean = InsurWP_Settings::sanitize(
	array(
		'bgn_rate'        => '1,95583',
		'default_country' => 'ru',
		'agent_id'        => 'abc',
		'accent'          => 'не цвет',
		'order_url'       => '',
	)
);
insurwp_check( 'запятая в курсе принята', 1.95583 === $clean['bgn_rate'] );
insurwp_check( 'страна в верхнем регистре', 'RU' === $clean['default_country'] );
insurwp_check( 'битый agent_id заменён', '49' === $clean['agent_id'] );
insurwp_check( 'битый цвет заменён', '#f0ad4e' === $clean['accent'] );
insurwp_check( 'пустой URL заменён', 'https://bginfo.eu/insur/' === $clean['order_url'] );
insurwp_check( 'снятый чекбокс = 0', 0 === $clean['show_bgn'] );

echo "\n== Разбор ответов внешнего сервиса ==\n";
$parsed = InsurWP_Api_Sync::parse_jsonp( 'cb({"status":0,"Result":[{"price_eur":"89.70","logo":"unica.jpg","shengen":0}]})' );
insurwp_check( 'JSONP разобран', isset( $parsed['Result'][0]['price_eur'] ) );
insurwp_check( 'чистый JSON тоже разобран', null !== InsurWP_Api_Sync::parse_jsonp( '{"status":0}' ) );
insurwp_check( 'мусор даёт null', null === InsurWP_Api_Sync::parse_jsonp( 'ерунда' ) );
insurwp_check( 'логотип unica → uniqa', 'uniqa' === InsurWP_Api_Sync::insurer_key_from_logo( 'unica.jpg' ) );
insurwp_check( 'логотип bulstrad → bulstrad_life', 'bulstrad_life' === InsurWP_Api_Sync::insurer_key_from_logo( 'bulstrad.jpg' ) );
insurwp_check( 'неизвестный логотип → null', null === InsurWP_Api_Sync::insurer_key_from_logo( 'other.jpg' ) );

$diff = InsurWP_Api_Sync::diff(
	array( 'insurers' => array( 'uniqa' => array( 'name' => 'UNIQA', 'prices' => array( array( 'term' => '3 дня', 'bulgaria_0_69' => 3.97 ) ) ) ) ),
	array( 'insurers' => array( 'uniqa' => array( 'name' => 'UNIQA', 'prices' => array( array( 'term' => '3 дня', 'bulgaria_0_69' => 4.50 ) ) ) ) )
);
insurwp_check( 'diff видит изменение цены', 1 === count( $diff ) && '3.97' === $diff[0]['from'] && '4.50' === $diff[0]['to'] );

$no_diff = InsurWP_Api_Sync::diff(
	array( 'insurers' => array( 'uniqa' => array( 'prices' => array( array( 'term' => '3 дня', 'bulgaria_0_69' => 3.97 ) ) ) ) ),
	array( 'insurers' => array( 'uniqa' => array( 'prices' => array( array( 'term' => '3 дня', 'bulgaria_0_69' => 3.97 ) ) ) ) )
);
insurwp_check( 'diff молчит без изменений', 0 === count( $no_diff ) );

echo "\n== Мини-апп: справочники ==\n";
InsurWP_Miniapp::register_routes();
insurwp_check( 'маршрут зарегистрирован', isset( $GLOBALS['insurwp_routes']['insurwp/v1/options'] ) );

$options = InsurWP_Miniapp::options();
$dates   = $options['dates'];

insurwp_check( '15 сроков (получено ' . count( $options['terms'] ) . ')', 15 === count( $options['terms'] ) );
insurwp_check( 'срок по умолчанию — год', '12 месяцев' === $options['default_term'] );
insurwp_check( 'территории: все + 2', 3 === count( $options['territories'] ) && 'all' === $options['territories'][0]['value'] );
insurwp_check( 'лимит подписан', isset( $options['limits'][0]['label'] ) && str_contains( $options['limits'][0]['label'], '30677.51' ) );
insurwp_check( 'show_bgn булев', is_bool( $options['show_bgn'] ) );
insurwp_check( 'начало не раньше сегодня', $dates['start_min'] === $dates['today'] );
insurwp_check( 'горизонт начала — год', (int) substr( $dates['start_max'], 0, 4 ) === (int) substr( $dates['today'], 0, 4 ) + 1 );
insurwp_check( 'нижняя граница рождения — 85 лет', (int) substr( $dates['birth_min'], 0, 4 ) === (int) substr( $dates['today'], 0, 4 ) - 85 );
insurwp_check( 'рождение не позже сегодня', $dates['birth_max'] === $dates['today'] );
// Форма на сайте и мини-апп должны ограничивать даты одинаково.
insurwp_check( 'границы совпадают с формой на сайте', str_contains( $html, 'max="' . $dates['start_max'] . '"' ) );

echo "\n== Мини-апп: доступ с другого домена ==\n";
$allowed = array( 'https://miniapp.bginfo.eu' );

insurwp_check( 'свой адрес разрешён', InsurWP_Miniapp::is_allowed_origin( 'https://miniapp.bginfo.eu', $allowed ) );
insurwp_check( 'хвостовой слэш не мешает', InsurWP_Miniapp::is_allowed_origin( 'https://miniapp.bginfo.eu/', $allowed ) );
insurwp_check( 'регистр хоста не мешает', InsurWP_Miniapp::is_allowed_origin( 'https://MiniApp.BgInfo.eu', $allowed ) );
insurwp_check( 'похожий домен отклонён', ! InsurWP_Miniapp::is_allowed_origin( 'https://miniapp.bginfo.eu.example.com', $allowed ) );
insurwp_check( 'другая схема отклонена', ! InsurWP_Miniapp::is_allowed_origin( 'http://miniapp.bginfo.eu', $allowed ) );
insurwp_check( 'пустой Origin отклонён', ! InsurWP_Miniapp::is_allowed_origin( '', $allowed ) );
insurwp_check( 'порт сохраняется', 'http://localhost:5173' === InsurWP_Miniapp::normalize_origin( 'http://localhost:5173/' ) );

$origins = InsurWP_Settings::sanitize( array( 'miniapp_origins' => "https://miniapp.bginfo.eu/\nhttp://localhost:5173\nне адрес" ) );
insurwp_check( 'список нормализован', "https://miniapp.bginfo.eu\nhttp://localhost:5173" === $origins['miniapp_origins'] );

$broken = InsurWP_Settings::sanitize( array( 'miniapp_origins' => 'ерунда' ) );
insurwp_check( 'мусор заменён адресом по умолчанию', 'https://miniapp.bginfo.eu' === $broken['miniapp_origins'] );

insurwp_finish();
