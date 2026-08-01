/**
 * Фронтенд калькулятора InsurWP.
 *
 * Расчёт выполняет PHP (REST /wp-json/insurwp/v1/quote) — здесь только форма,
 * запрос и отрисовка результата. Формулы намеренно не дублируются в JS.
 */
( function () {
	'use strict';

	var config = window.insurwpConfig || {};
	var strings = config.strings || {};

	/**
	 * Форматирует сумму в виде «89.70 €» / «175,44 лв».
	 *
	 * @param {number} value    Сумма.
	 * @param {string} currency Обозначение валюты.
	 * @return {string} Отформатированная строка.
	 */
	function formatMoney( value, currency ) {
		var num = Number( value );

		if ( ! isFinite( num ) ) {
			return '';
		}

		return num.toFixed( 2 ).replace( '.', ',' ) + ' ' + currency;
	}

	/**
	 * Переводит дату из Y-m-d в d.m.Y для показа пользователю.
	 *
	 * @param {string} iso Дата в формате Y-m-d.
	 * @return {string} Дата в формате d.m.Y.
	 */
	function formatDate( iso ) {
		var parts = String( iso || '' ).split( '-' );

		if ( 3 !== parts.length ) {
			return String( iso || '' );
		}

		return parts[ 2 ] + '.' + parts[ 1 ] + '.' + parts[ 0 ];
	}

	/**
	 * Экранирует текст перед вставкой в разметку.
	 *
	 * @param {string} value Исходное значение.
	 * @return {string} Безопасная строка.
	 */
	function escapeHtml( value ) {
		return String( value === null || value === undefined ? '' : value ).replace(
			/[&<>"']/g,
			function ( char ) {
				return {
					'&': '&amp;',
					'<': '&lt;',
					'>': '&gt;',
					'"': '&quot;',
					"'": '&#39;'
				}[ char ];
			}
		);
	}

	/**
	 * Инициализирует один экземпляр калькулятора.
	 *
	 * @param {HTMLElement} root Корневой элемент калькулятора.
	 */
	function initCalculator( root ) {
		var form = root.querySelector( '[data-insurwp-form]' );
		var results = root.querySelector( '[data-insurwp-results]' );
		var summary = root.querySelector( '[data-insurwp-summary]' );
		var errorBox = root.querySelector( '[data-insurwp-error]' );
		var recalcBtn = root.querySelector( '[data-insurwp-recalc]' );
		var submitBtn = form ? form.querySelector( '.insurwp__submit' ) : null;

		if ( ! form || ! results ) {
			return;
		}

		var showBgn = '1' === root.getAttribute( 'data-show-bgn' );

		/**
		 * Показывает сообщение об ошибке над формой.
		 *
		 * @param {string} message Текст ошибки; пустая строка прячет блок.
		 */
		function showError( message ) {
			if ( ! errorBox ) {
				return;
			}

			if ( ! message ) {
				errorBox.hidden = true;
				errorBox.textContent = '';
				return;
			}

			errorBox.hidden = false;
			errorBox.textContent = message;
		}

		/**
		 * Переключает режим: форма либо результаты.
		 *
		 * @param {boolean} showForm Показывать ли форму.
		 */
		function toggleForm( showForm ) {
			form.hidden = ! showForm;

			if ( recalcBtn ) {
				recalcBtn.hidden = showForm;
			}

			if ( summary ) {
				summary.hidden = showForm;
			}

			if ( showForm ) {
				results.innerHTML = '';
			}
		}

		/**
		 * Рисует скелетон на время запроса.
		 */
		function renderSkeleton() {
			results.innerHTML =
				'<div class="insurwp__skeleton">' +
				'<div class="insurwp__skeleton-row"></div>' +
				'<div class="insurwp__skeleton-row"></div>' +
				'<div class="insurwp__skeleton-row"></div>' +
				'</div>';
		}

		/**
		 * Рисует сводку выбранных параметров рядом с результатами.
		 *
		 * @param {Object} data Ответ REST-эндпоинта.
		 */
		function renderSummary( data ) {
			if ( ! summary ) {
				return;
			}

			var chips = [
				{ label: strings.ageLabel || 'Возраст', value: data.age + ' ' + ( strings.years || 'лет' ) },
				{ label: 'Срок', value: data.term_label || data.term },
				{ label: 'Начало', value: formatDate( data.date_start ) }
			];

			summary.innerHTML = chips
				.map( function ( chip ) {
					return (
						'<span class="insurwp__chip">' +
						escapeHtml( chip.label ) +
						': <b>' +
						escapeHtml( chip.value ) +
						'</b></span>'
					);
				} )
				.join( '' );
		}

		/**
		 * Рисует список предложений.
		 *
		 * @param {Object} data Ответ REST-эндпоинта.
		 */
		function renderOffers( data ) {
			var offers = data.offers || [];

			if ( ! offers.length ) {
				results.innerHTML =
					'<div class="insurwp__empty">' +
					'<strong>' +
					escapeHtml( strings.empty || 'Предложений нет.' ) +
					'</strong>' +
					'<span>' +
					escapeHtml( strings.emptyHint || '' ) +
					'</span>' +
					'</div>';
				return;
			}

			// Предложения приходят отсортированными по цене, поэтому «выгоднее всего» — первое.
			var html =
				'<p class="insurwp__count">' +
				escapeHtml( strings.found || 'Найдено предложений:' ) +
				' ' +
				offers.length +
				'</p><ul class="insurwp__offers">';

			offers.forEach( function ( offer, index ) {
				var isBest = 0 === index && offers.length > 1;
				var meta = escapeHtml( offer.territory_label );

				if ( offer.limit_eur ) {
					meta +=
						' · ' +
						escapeHtml( strings.limitLabel || 'Лимит' ) +
						' ' +
						formatMoney( offer.limit_eur, '€' );
				}

				html +=
					'<li class="insurwp__offer' +
					( isBest ? ' insurwp__offer--best' : '' ) +
					'" style="animation-delay:' +
					index * 45 +
					'ms">' +
					'<div class="insurwp__offer-main">' +
					'<div class="insurwp__offer-name">' +
					escapeHtml( offer.insurer_name ) +
					( isBest
						? '<span class="insurwp__badge">' + escapeHtml( strings.best || '' ) + '</span>'
						: '' ) +
					'</div>' +
					'<div class="insurwp__offer-meta">' +
					meta +
					'</div>' +
					'</div>' +
					'<div class="insurwp__offer-price">' +
					'<div class="insurwp__price-eur">' +
					formatMoney( offer.price_eur, '€' ) +
					'</div>' +
					// Атрибут шорткода уже учитывает общую настройку и может её переопределить,
					// поэтому опираемся только на него.
					( showBgn
						? '<div class="insurwp__price-bgn">' + formatMoney( offer.price_bgn, 'лв' ) + '</div>'
						: '' ) +
					'<div class="insurwp__price-note">' +
					escapeHtml( strings.perPolicy || '' ) +
					'</div>' +
					'</div>' +
					'<div class="insurwp__offer-action">' +
					'<a class="insurwp__order" href="' +
					escapeHtml( offer.order_url ) +
					'" target="_blank" rel="noopener noreferrer">' +
					escapeHtml( strings.order || 'Заказать' ) +
					'</a>' +
					'</div>' +
					'</li>';
			} );

			results.innerHTML = html + '</ul>';
		}

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			showError( '' );

			if ( ! form.checkValidity() ) {
				form.reportValidity();
				return;
			}

			var payload = {
				term: form.elements.term ? form.elements.term.value : '',
				date_start: form.elements.date_start ? form.elements.date_start.value : '',
				birth_date: form.elements.birth_date ? form.elements.birth_date.value : '',
				territory: form.elements.territory ? form.elements.territory.value : 'all'
			};

			if ( submitBtn ) {
				submitBtn.disabled = true;
				submitBtn.dataset.label = submitBtn.textContent;
				submitBtn.textContent = strings.calculating || '…';
			}

			renderSkeleton();

			fetch( config.root, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': config.nonce || ''
				},
				body: JSON.stringify( payload )
			} )
				.then( function ( response ) {
					return response.json().then( function ( body ) {
						if ( ! response.ok ) {
							throw new Error( ( body && body.message ) || strings.error );
						}

						return body;
					} );
				} )
				.then( function ( data ) {
					renderSummary( data );
					renderOffers( data );
					toggleForm( false );
				} )
				.catch( function ( error ) {
					results.innerHTML = '';
					showError( error.message || strings.error );
				} )
				.finally( function () {
					if ( submitBtn ) {
						submitBtn.disabled = false;
						submitBtn.textContent = submitBtn.dataset.label || submitBtn.textContent;
					}
				} );
		} );

		if ( recalcBtn ) {
			recalcBtn.addEventListener( 'click', function () {
				toggleForm( true );
				showError( '' );
			} );
		}
	}

	/**
	 * Находит и инициализирует все калькуляторы на странице.
	 */
	function boot() {
		var nodes = document.querySelectorAll( '[data-insurwp]' );

		Array.prototype.forEach.call( nodes, initCalculator );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
