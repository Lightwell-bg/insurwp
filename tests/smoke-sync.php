<?php
/**
 * Смоук-тест синхронизации тарифов с сервисом bgmedins.com.
 *
 * Прогоняет тот же код, что и кнопка «Обновить цены из API» в админке,
 * и сравнивает результат с текущим датасетом.
 *
 * Запуск: php tests/smoke-sync.php
 * Требуется доступ в интернет и расширение cURL.
 *
 * @package InsurWP
 */

require_once __DIR__ . '/wp-stubs.php';

InsurWP_Prices::seed_if_empty();
InsurWP_Settings::seed_if_empty();

$before = InsurWP_Prices::get();
$state  = InsurWP_Api_Sync::start();

printf(
	"Опрашиваем сервис: %d сроков × %d возрастов = %d запросов…\n",
	$state['total'],
	count( InsurWP_Api_Sync::PROBE_AGES ),
	$state['total'] * count( InsurWP_Api_Sync::PROBE_AGES )
);

$started = microtime( true );

while ( $state['index'] < $state['total'] ) {
	$state = InsurWP_Api_Sync::process_next();

	if ( is_wp_error( $state ) ) {
		echo "\nОШИБКА: " . $state->get_error_message() . "\n";
		exit( 1 );
	}

	echo '.';
}

printf( "\nГотово за %.1f с. Записей в тарифы: %d\n\n", microtime( true ) - $started, $state['filled'] );

$changes = InsurWP_Api_Sync::diff( $before, $state['prices'] );

printf( "Расхождений с текущим датасетом: %d\n", count( $changes ) );

foreach ( array_slice( $changes, 0, 25 ) as $change ) {
	printf(
		"  %-14s %-12s %-22s %8s -> %8s\n",
		$change['insurer'],
		$change['term'],
		$change['cell'],
		$change['from'],
		$change['to']
	);
}

$filled = 0;
$empty  = 0;

foreach ( $state['prices']['insurers'] as $insurer ) {
	foreach ( $insurer['prices'] as $row ) {
		foreach ( $row as $cell => $value ) {
			if ( 'term' === $cell ) {
				continue;
			}

			if ( null === $value ) {
				$empty++;
			} else {
				$filled++;
			}
		}
	}
}

printf( "\nПосле синхронизации: с ценой %d, без предложения (null) %d\n", $filled, $empty );

// Синхронизация не должна опустошать датасет даже при частичных ответах сервиса.
insurwp_check( 'тарифы не опустели', $filled > 100 );
insurwp_check( 'расхождений нет', 0 === count( $changes ) );

insurwp_finish();
