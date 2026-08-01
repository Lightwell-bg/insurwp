/**
 * Скрипт админ-страницы InsurWP: пошаговое обновление тарифов и проверка JSON.
 */
( function () {
	'use strict';

	var config = window.insurwpAdmin || {};
	var strings = config.strings || {};

	/**
	 * Подставляет значения в строку с плейсхолдерами %1$d и %2$d.
	 *
	 * @param {string} template Шаблон строки.
	 * @param {number} first    Первое значение.
	 * @param {number} second   Второе значение.
	 * @return {string} Готовая строка.
	 */
	function sprintf( template, first, second ) {
		return String( template ).replace( '%1$d', first ).replace( '%2$d', second );
	}

	/**
	 * Один шаг синхронизации.
	 *
	 * @param {string} step Значение шага: start или next.
	 * @return {Promise<Object>} Состояние синхронизации.
	 */
	function syncStep( step ) {
		var body = new URLSearchParams();

		body.append( 'action', 'insurwp_sync_step' );
		body.append( 'nonce', config.nonce || '' );
		body.append( 'step', step );

		return fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( payload ) {
				if ( ! payload || ! payload.success ) {
					throw new Error( ( payload && payload.data && payload.data.message ) || 'error' );
				}

				return payload.data;
			} );
	}

	/**
	 * Настраивает кнопку обновления тарифов.
	 */
	function initSync() {
		var button = document.getElementById( 'insurwp-sync-start' );
		var status = document.getElementById( 'insurwp-sync-status' );
		var progress = document.getElementById( 'insurwp-sync-progress' );
		var bar = document.getElementById( 'insurwp-sync-bar' );

		if ( ! button ) {
			return;
		}

		/**
		 * Обновляет текст статуса.
		 *
		 * @param {string}  message Текст.
		 * @param {boolean} isError Является ли сообщение ошибкой.
		 */
		function setStatus( message, isError ) {
			if ( ! status ) {
				return;
			}

			status.textContent = message;
			status.classList.toggle( 'is-error', !! isError );
		}

		button.addEventListener( 'click', function () {
			if ( ! window.confirm( strings.confirm || '' ) ) {
				return;
			}

			button.disabled = true;
			setStatus( strings.starting || '', false );

			if ( progress ) {
				progress.hidden = false;
			}

			/**
			 * Рекурсивно выполняет шаги, пока сервер не сообщит о завершении.
			 *
			 * @param {Object} state Состояние после предыдущего шага.
			 * @return {Promise} Промис завершения.
			 */
			function next( state ) {
				if ( state.done ) {
					setStatus( strings.done || '', false );
					window.location.reload();
					return Promise.resolve();
				}

				return syncStep( 'next' ).then( function ( updated ) {
					if ( bar && updated.total ) {
						bar.style.width = Math.round( ( updated.index / updated.total ) * 100 ) + '%';
					}

					setStatus( sprintf( strings.progress || '', updated.index, updated.total ), false );

					return next( updated );
				} );
			}

			syncStep( 'start' )
				.then( next )
				.catch( function ( error ) {
					button.disabled = false;

					if ( progress ) {
						progress.hidden = true;
					}

					setStatus( ( strings.failed || '' ) + ' ' + ( error.message || '' ), true );
				} );
		} );
	}

	/**
	 * Живая проверка JSON в редакторе тарифов.
	 */
	function initJsonValidation() {
		var editor = document.getElementById( 'insurwp-prices-json' );
		var status = document.getElementById( 'insurwp-json-status' );

		if ( ! editor || ! status ) {
			return;
		}

		/**
		 * Проверяет содержимое редактора.
		 */
		function validate() {
			try {
				JSON.parse( editor.value );
				status.textContent = strings.goodJson || '';
				status.className = 'insurwp-admin__json-status is-ok';
			} catch ( error ) {
				status.textContent = ( strings.badJson || '' ) + ' ' + error.message;
				status.className = 'insurwp-admin__json-status is-error';
			}
		}

		var timer = null;

		editor.addEventListener( 'input', function () {
			window.clearTimeout( timer );
			timer = window.setTimeout( validate, 400 );
		} );

		validate();
	}

	/**
	 * Точка входа.
	 */
	function boot() {
		initSync();
		initJsonValidation();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
