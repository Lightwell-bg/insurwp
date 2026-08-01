/**
 * Автозаполнение формы на странице оформления (bginfo.eu/insur/).
 *
 * ЭТОТ ФАЙЛ ПОДКЛЮЧАЕТСЯ НЕ НА САЙТЕ С ПЛАГИНОМ, А НА СТРАНИЦЕ ОФОРМЛЕНИЯ.
 * Скопируйте его на сервер страницы оформления и подключите перед </body>:
 *
 *     <script src="/js/bginfo-prefill.js" defer></script>
 *
 * Зачем он нужен: сама страница оформления не умеет читать параметры из адресной
 * строки, а её выпадающие списки заполняются AJAX-запросами уже после загрузки.
 * Сниппет дожидается появления опций, проставляет значения из URL и при желании
 * запускает расчёт. Если параметров в адресе нет — скрипт не делает ничего,
 * и страница работает ровно как раньше.
 *
 * Ожидаемые параметры (их формирует плагин InsurWP):
 *   ins_type, foreigner_limit, foreigner_term, date_start, dfb,
 *   foreigner_country, agent_id, insurer, shengen, autocalc
 */
( function () {
	'use strict';

	var params = new URLSearchParams( window.location.search );

	// Без параметров расчёта вмешиваться не во что.
	if ( ! params.has( 'foreigner_term' ) && ! params.has( 'dfb' ) ) {
		return;
	}

	var MAX_WAIT_MS = 12000;
	var POLL_MS = 150;

	/**
	 * Проставляет значение в select, сверяя опции по числовому значению.
	 *
	 * Значения лимита приходят с разной точностью (30677.51 против длинного
	 * float из API), поэтому числа сравниваем с допуском, а не строками.
	 *
	 * @param {HTMLSelectElement} select Элемент списка.
	 * @param {string}            wanted Искомое значение.
	 * @return {boolean} Удалось ли выбрать опцию.
	 */
	function selectOption( select, wanted ) {
		if ( ! select || null === wanted || undefined === wanted || '' === wanted ) {
			return false;
		}

		var target = String( wanted );
		var targetNum = parseFloat( target );
		var i;

		for ( i = 0; i < select.options.length; i++ ) {
			if ( select.options[ i ].value === target ) {
				select.selectedIndex = i;
				fire( select );
				return true;
			}
		}

		if ( ! isNaN( targetNum ) ) {
			for ( i = 0; i < select.options.length; i++ ) {
				var optionNum = parseFloat( select.options[ i ].value );

				if ( ! isNaN( optionNum ) && Math.abs( optionNum - targetNum ) < 0.01 ) {
					select.selectedIndex = i;
					fire( select );
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Сообщает странице об изменении поля.
	 *
	 * @param {HTMLElement} element Изменённый элемент.
	 */
	function fire( element ) {
		try {
			element.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} catch ( error ) {
			// Старые браузеры: обойдёмся без события, значение уже проставлено.
		}
	}

	/**
	 * Проставляет значение в текстовое поле.
	 *
	 * @param {string} id    Идентификатор поля.
	 * @param {string} value Значение.
	 */
	function setText( id, value ) {
		var field = document.getElementById( id );

		if ( field && value ) {
			field.value = value;
			fire( field );
		}
	}

	/**
	 * Проверяет, наполнился ли список реальными опциями.
	 *
	 * У пустых списков на странице есть единственный пункт «- Выберите -»
	 * с пустым value, поэтому его не считаем.
	 *
	 * @param {string} id Идентификатор списка.
	 * @return {boolean} Есть ли пригодные опции.
	 */
	function hasOptions( id ) {
		var select = document.getElementById( id );

		if ( ! select ) {
			return false;
		}

		for ( var i = 0; i < select.options.length; i++ ) {
			if ( '' !== select.options[ i ].value ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Подсвечивает предложение выбранного страховщика в таблице результатов.
	 *
	 * Строки рисуются после ответа сервиса, поэтому ждём их появления отдельно.
	 *
	 * @param {string} insurer Часть имени файла логотипа, например unica.
	 * @param {string} shengen Признак шенгена: 1 или 0.
	 */
	function highlightOffer( insurer, shengen ) {
		if ( ! insurer ) {
			return;
		}

		var deadline = Date.now() + MAX_WAIT_MS;

		var timer = window.setInterval( function () {
			var rows = document.querySelectorAll( '#prices .presult' );

			if ( ! rows.length ) {
				if ( Date.now() > deadline ) {
					window.clearInterval( timer );
				}

				return;
			}

			window.clearInterval( timer );

			Array.prototype.forEach.call( rows, function ( row ) {
				var logo = row.querySelector( 'img' );
				var src = logo ? String( logo.getAttribute( 'src' ) || '' ).toLowerCase() : '';

				if ( -1 === src.indexOf( String( insurer ).toLowerCase() ) ) {
					return;
				}

				// Колонка «шенген» — вторая после логотипа и цены.
				var cells = row.querySelectorAll( '.col-sm-2' );
				var isSchengen = cells.length > 2 && '+' === cells[ 2 ].textContent.trim();

				if ( null !== shengen && ( '1' === shengen ) !== isSchengen ) {
					return;
				}

				row.style.outline = '2px solid #f0ad4e';
				row.style.borderRadius = '6px';
				row.scrollIntoView( { behavior: 'smooth', block: 'center' } );
			} );
		}, 250 );
	}

	/**
	 * Заполняет форму, когда списки готовы.
	 */
	function fill() {
		selectOption( document.getElementById( 'ins_type' ), params.get( 'ins_type' ) || '1' );
		selectOption( document.getElementById( 'foreigner_limit' ), params.get( 'foreigner_limit' ) );
		selectOption( document.getElementById( 'foreigner_term' ), params.get( 'foreigner_term' ) );
		selectOption( document.getElementById( 'foreigner_country' ), params.get( 'foreigner_country' ) );

		setText( 'date_start', params.get( 'date_start' ) );
		setText( 'dfb', params.get( 'dfb' ) );

		var agent = document.getElementById( 'agent_id' );

		if ( agent && params.get( 'agent_id' ) ) {
			agent.value = params.get( 'agent_id' );
		}

		if ( '1' === params.get( 'autocalc' ) ) {
			var button = document.getElementById( 'btn_step1' );

			if ( button ) {
				button.click();
				highlightOffer( params.get( 'insurer' ), params.get( 'shengen' ) );
			}
		}
	}

	/**
	 * Ждёт, пока AJAX наполнит выпадающие списки, и запускает заполнение.
	 */
	function waitAndFill() {
		var deadline = Date.now() + MAX_WAIT_MS;

		var timer = window.setInterval( function () {
			var ready = hasOptions( 'foreigner_term' ) && hasOptions( 'foreigner_limit' );

			if ( ready ) {
				window.clearInterval( timer );
				fill();
				return;
			}

			if ( Date.now() > deadline ) {
				// Списки так и не пришли — заполняем то, что можем (даты),
				// чтобы пользователю осталось меньше ручной работы.
				window.clearInterval( timer );
				setText( 'date_start', params.get( 'date_start' ) );
				setText( 'dfb', params.get( 'dfb' ) );
			}
		}, POLL_MS );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', waitAndFill );
	} else {
		waitAndFill();
	}
} )();
