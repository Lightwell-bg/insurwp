/**
 * Сверка тарифов из data/prices.default.json с живым сервисом bgmedins.com.
 *
 * Скрипт повторяет ту же раскладку ответов по страховщикам, территориям и
 * возрастным группам, что и PHP-класс InsurWP_Api_Sync, поэтому проверяет
 * сразу две вещи: актуальность датасета и корректность алгоритма раскладки.
 *
 * Запуск: node tests/verify-prices.mjs
 * Ключи:  --json путь   использовать другой файл тарифов
 *         --quiet       печатать только итог
 */

import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const ENDPOINT = 'https://www.bgmedins.com/system/get_prices.php';
const AGENT_ID = '49';
const COUNTRY = 'RU';

/** Контрольные возрасты — те же, что в InsurWP_Api_Sync::PROBE_AGES. */
const PROBE_AGES = [ 35, 67, 72, 80 ];

/** Соответствие логотипа ключу страховщика — как в InsurWP_Api_Sync::LOGO_MAP. */
const LOGO_MAP = { unica: 'uniqa', bulstrad: 'bulstrad_life' };

/** Соответствие срока идентификатору API — как в InsurWP_Catalog::TERM_API_IDS. */
const TERM_API_IDS = {
	'3 дня': 13,
	'7 дней': 14,
	'15 дней': 15,
	'1 месяц': 1,
	'2 месяца': 2,
	'3 месяца': 3,
	'4 месяца': 4,
	'5 месяцев': 5,
	'6 месяцев': 6,
	'7 месяцев': 7,
	'8 месяцев': 8,
	'9 месяцев': 9,
	'10 месяцев': 10,
	'11 месяцев': 11,
	'12 месяцев': 12,
};

const args = process.argv.slice( 2 );
const quiet = args.includes( '--quiet' );
const jsonFlag = args.indexOf( '--json' );

const here = path.dirname( fileURLToPath( import.meta.url ) );
const pricesPath =
	jsonFlag !== -1 && args[ jsonFlag + 1 ]
		? path.resolve( args[ jsonFlag + 1 ] )
		: path.join( here, '..', 'data', 'prices.default.json' );

/**
 * Возвращает возрастную группу страховщика — портированный
 * InsurWP_Calculator::age_group.
 *
 * @param {Object} insurer Данные страховщика.
 * @param {number} age     Возраст.
 * @return {{key: string, rules: Object}|null} Группа либо null.
 */
function ageGroup( insurer, age ) {
	const groups = insurer.age_groups || insurer.age_rules || {};

	for ( const [ key, rules ] of Object.entries( groups ) ) {
		if ( ! rules || typeof rules !== 'object' ) {
			continue;
		}

		const min = Number( rules.min_age ?? 0 );

		if ( age < min ) {
			continue;
		}

		if ( rules.max_age !== undefined && age > Number( rules.max_age ) ) {
			continue;
		}

		return { key, rules };
	}

	return null;
}

/**
 * Форматирует дату в d.m.Y.
 *
 * @param {Date} date Дата.
 * @return {string} Строка вида 06.08.2026.
 */
function formatDate( date ) {
	const pad = ( n ) => String( n ).padStart( 2, '0' );

	return `${ pad( date.getDate() ) }.${ pad( date.getMonth() + 1 ) }.${ date.getFullYear() }`;
}

/**
 * Разбирает JSONP-ответ — портированный InsurWP_Api_Sync::parse_jsonp.
 *
 * @param {string} body Тело ответа.
 * @return {Object|null} Данные либо null.
 */
function parseJsonp( body ) {
	const text = String( body ).trim();
	const start = text.indexOf( '(' );
	const end = text.lastIndexOf( ')' );
	const payload = start !== -1 && end > start ? text.slice( start + 1, end ) : text;

	try {
		return JSON.parse( payload.trim() );
	} catch {
		return null;
	}
}

/**
 * Запрашивает предложения у внешнего сервиса.
 *
 * @param {number} termApiId Идентификатор срока.
 * @param {number} age       Возраст.
 * @param {string} limit     Страховая сумма.
 * @param {string} dateStart Дата начала, d.m.Y.
 * @return {Promise<Array>} Список предложений.
 */
async function fetchOffers( termApiId, age, limit, dateStart ) {
	const birth = new Date();
	birth.setFullYear( birth.getFullYear() - age );
	birth.setMonth( birth.getMonth() - 1 );

	const url = new URL( ENDPOINT );

	url.searchParams.set( 'jsoncallback', 'insurwp' );
	url.searchParams.set( 'foreigner_country', COUNTRY );
	url.searchParams.set( 'dfb', formatDate( birth ) );
	url.searchParams.set( 'foreigner_term', String( termApiId ) );
	url.searchParams.set( 'foreigner_limit', limit );
	url.searchParams.set( 'date_start', dateStart );
	url.searchParams.set( 'agent_id', AGENT_ID );

	const response = await fetch( url, { headers: { 'User-Agent': 'InsurWP-verify/1.0' } } );

	if ( ! response.ok ) {
		throw new Error( `HTTP ${ response.status } для срока ${ termApiId }, возраст ${ age }` );
	}

	const data = parseJsonp( await response.text() );

	if ( ! data ) {
		throw new Error( `Не разобран ответ для срока ${ termApiId }, возраст ${ age }` );
	}

	return Array.isArray( data.Result ) ? data.Result : [];
}

/**
 * Точка входа.
 */
async function main() {
	const prices = JSON.parse( await readFile( pricesPath, 'utf8' ) );
	const insurers = prices.insurers || {};

	// Страховая сумма — как в InsurWP_Prices::limits: максимум из объявленных.
	const limits = Object.values( insurers )
		.map( ( insurer ) => Number( insurer.insurance_limit_eur ) )
		.filter( ( value ) => Number.isFinite( value ) && value > 0 );
	const limit = ( limits.length ? Math.max( ...limits ) : 30677.51 ).toFixed( 2 );

	const start = new Date();
	start.setDate( start.getDate() + 1 );
	const dateStart = formatDate( start );

	const terms = Object.keys( TERM_API_IDS ).filter( ( term ) =>
		Object.values( insurers ).some( ( insurer ) =>
			( insurer.prices || [] ).some( ( row ) => row.term === term )
		)
	);

	if ( ! quiet ) {
		console.log( `Файл тарифов: ${ pricesPath }` );
		console.log( `Лимит: ${ limit } € · дата начала: ${ dateStart }` );
		console.log( `Проверяем ${ terms.length } сроков × ${ PROBE_AGES.length } возрастов…\n` );
	}

	/** Ожидаемые значения, собранные из ответов API: term -> insurer -> cell -> price. */
	const expected = new Map();
	const errors = [];

	for ( const term of terms ) {
		for ( const age of PROBE_AGES ) {
			let offers;

			try {
				offers = await fetchOffers( TERM_API_IDS[ term ], age, limit, dateStart );
			} catch ( error ) {
				errors.push( error.message );
				continue;
			}

			for ( const offer of offers ) {
				const logo = String( offer.logo || '' ).toLowerCase();
				const insurerKey = Object.entries( LOGO_MAP ).find( ( [ needle ] ) =>
					logo.includes( needle )
				)?.[ 1 ];

				if ( ! insurerKey || ! insurers[ insurerKey ] ) {
					continue;
				}

				const group = ageGroup( insurers[ insurerKey ], age );

				if ( ! group ) {
					continue;
				}

				const territory = Number( offer.shengen ) ? 'schengen' : 'bulgaria';
				const cell = `${ territory }_${ group.key }`;
				const price = Number( offer.price_eur ?? offer.price );

				if ( ! Number.isFinite( price ) || price <= 0 ) {
					continue;
				}

				if ( ! expected.has( term ) ) {
					expected.set( term, {} );
				}

				const perTerm = expected.get( term );

				perTerm[ insurerKey ] = perTerm[ insurerKey ] || {};
				perTerm[ insurerKey ][ cell ] = Number( price.toFixed( 2 ) );
			}
		}

		if ( ! quiet ) {
			process.stdout.write( '.' );
		}
	}

	if ( ! quiet ) {
		console.log( '\n' );
	}

	// Сравниваем только те ячейки, которые контрольные возрасты действительно покрывают.
	const mismatches = [];
	let compared = 0;

	for ( const [ insurerKey, insurer ] of Object.entries( insurers ) ) {
		const probedGroups = [
			...new Set( PROBE_AGES.map( ( age ) => ageGroup( insurer, age )?.key ).filter( Boolean ) ),
		];

		for ( const row of insurer.prices || [] ) {
			const fromApi = expected.get( row.term )?.[ insurerKey ] || {};

			for ( const groupKey of probedGroups ) {
				for ( const territory of [ 'bulgaria', 'schengen' ] ) {
					const cell = `${ territory }_${ groupKey }`;

					if ( ! ( cell in row ) ) {
						continue;
					}

					compared++;

					const actual = row[ cell ] === null ? null : Number( row[ cell ] );
					const live = cell in fromApi ? fromApi[ cell ] : null;

					const same =
						actual === null && live === null
							? true
							: actual !== null && live !== null && Math.abs( actual - live ) < 0.005;

					if ( ! same ) {
						mismatches.push( {
							insurer: insurer.name || insurerKey,
							term: row.term,
							cell,
							json: actual === null ? '—' : actual.toFixed( 2 ),
							api: live === null ? '—' : live.toFixed( 2 ),
						} );
					}
				}
			}
		}
	}

	console.log( `Сверено ячеек: ${ compared }` );
	console.log( `Расхождений:   ${ mismatches.length }` );

	if ( errors.length ) {
		console.log( `\nОшибки запросов (${ errors.length }):` );
		errors.slice( 0, 10 ).forEach( ( message ) => console.log( `  ! ${ message }` ) );
	}

	if ( mismatches.length ) {
		console.log( '\nРасхождения JSON → API:' );
		console.table( mismatches );
	} else if ( ! errors.length ) {
		console.log( '\nДатасет совпадает с живым сервисом.' );
	}

	process.exitCode = mismatches.length || errors.length ? 1 : 0;
}

main().catch( ( error ) => {
	console.error( 'Сверка не выполнена:', error.message );
	process.exitCode = 1;
} );
