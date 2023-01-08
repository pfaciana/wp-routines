(function ($, window, document) {
	$(function () {
		function getRoutineEl(el) {
			if (el instanceof jQuery) {
				el = el[0];
			}
			return el.hasAttribute('data-routine-id') ? $(el) : $(el).closest('[data-routine-id]');
		}

		function routineData(el, key, value, returnEl = true) {
			let $el = getRoutineEl(el), menu_slug = $el.data('routine-id');

			if (typeof key === 'undefined') {
				return $el.data();
			}

			if (typeof value === 'undefined') {
				return $el.data(key);
			}

			const prevValue = $el.data(key);

			if (value === 'toggle') {
				value = !prevValue;
			}

			$el.data(key, value);

			if (typeof jQuery === 'function' && 'publish' in jQuery) {
				jQuery.publish('rdb/routine/update', menu_slug, key, value, prevValue, $el);
				jQuery.publish('rdb/routine/update/' + menu_slug, key, value, prevValue, $el);
				jQuery.publish('rdb/routine/update/' + menu_slug + '/' + key, value, prevValue, $el);
			}

			return returnEl ? $el : value;
		}

		function scrollToBottom($routine, $output) {
			if (!($routine instanceof jQuery)) {
				$routine = getRoutineEl($output);
			}
			if (!$routine.data('autoScroll')) {
				return;
			}
			if (!($output instanceof jQuery)) {
				$output = $routine.find('.routine-output-buffer');
			}
			$output.scrollTop($output.prop('scrollHeight'));
		}

		$('.routine-output-buffer, .routine-pause-scroll').on('wheel mouseup', function () {
			routineData($(this), 'autoScroll', false);
		});

		$('.routine-auto-scroll').on('click', function () {
			scrollToBottom(routineData($(this), 'autoScroll', true));
		});

		$('.routine-abort-xhr').on('click', function () {
			var $routine = getRoutineEl($(this));
			$routine.data('currentRequest') && $routine.data('currentRequest').abort();
			routineData($routine, 'currentRequest', null);
		});

		$('[data-routine-id]').find('[data-action]').on('click', function () {
			var $this = $(this),
				$routine = getRoutineEl($this),
				$output = $routine.find('.routine-output-buffer'),
				data = {action: $this.data('action')};

			var urlParams = new URLSearchParams(window.location.search);
			var dataArgs = urlParams.get(data.action);
			(![undefined, null, false, ''].includes(dataArgs)) && (data.args = dataArgs);

			routineData($routine, 'currentRequest', $.ajax({
				type: 'GET',
				url: $routine.data('ajax-url'),
				dataType: 'html',
				data: data,
				xhr: function () {
					var xhr = $.ajaxSettings.xhr();
					$output.html('');
					var prevResponseLength = 0;
					xhr.onprogress = function (e) {
						var currentResponseLength = this.responseText.length;
						if (prevResponseLength != currentResponseLength) {
							var newResponse = this.responseText.substring(prevResponseLength, currentResponseLength);
							if (newResponse.includes('\\a')) {
								newResponse = newResponse.replaceAll('\\a', "\\b\\b\n");
							}
							if (newResponse.includes('\\b')) {
								var currentOutputText = $output.get(0).textContent;
								var newTextSections = newResponse.split('\\b');
								$.each(newTextSections, function (i, newText) {
									if (i !== 0) {
										var pos = currentOutputText.lastIndexOf("\n");
										currentOutputText = currentOutputText.substring(0, pos);
									}
									currentOutputText += newText;
									$output.html(currentOutputText);
								});
							} else {
								$output.append(newResponse);
							}
							scrollToBottom($routine, $output);
							prevResponseLength = currentResponseLength;
						}
					};
					return xhr;
				},
				complete: function (response) {
					$output.append('\nDone!\n\n');
					scrollToBottom($routine, $output);
					routineData($routine, 'currentRequest', null);
				}
			}));
		});

		$('[data-routine-id]').each(function () {
			scrollToBottom(routineData($(this), 'autoScroll', true));
		});
	});
}(jQuery, window, document));