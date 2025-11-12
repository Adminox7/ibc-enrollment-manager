/**
 * IBC Enrollment public scripts.
 */
(function ($) {
	'use strict';

	const $form = $('#ibc-registration-form');

	if ($form.length) {
		const siteKey = window.ibcRegister && window.ibcRegister.recaptcha ? window.ibcRegister.recaptcha : '';

		if (siteKey && !document.querySelector('script[src*="recaptcha/api.js"]')) {
			const script = document.createElement('script');
			script.src = 'https://www.google.com/recaptcha/api.js?render=' + siteKey;
			script.defer = true;
			document.head.appendChild(script);
		}

		const notice = $form.find('.ibc-form-notice');
		const submitButton = $form.find('button[type="submit"]');

		const showNotice = (message, isError = false) => {
			notice.text(message);
			notice.removeAttr('hidden');
			notice.toggleClass('ibc-error', isError);
			notice.toggleClass('ibc-success', !isError);
		};

		const clearNotice = () => {
			notice.attr('hidden', true);
			notice.removeClass('ibc-error ibc-success');
			notice.text('');
		};

		const sendRequest = (token) => {
			const formData = new FormData($form[0]);
			formData.append('nonce', window.ibcRegister.nonce);
			formData.append('recaptcha_token', token || '');

			return fetch(window.ibcRegister.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: formData,
			})
				.then((response) => response.json());
		};

		const handleSubmit = (event) => {
			event.preventDefault();
			clearNotice();
			submitButton.prop('disabled', true);

			const finalize = () => {
				submitButton.prop('disabled', false);
			};

			const process = (token) => {
				sendRequest(token)
					.then((result) => {
						if (result.success) {
							showNotice(window.ibcRegister.messages.success, false);
							if (result.data && result.data.redirect) {
								setTimeout(() => {
									window.location.href = result.data.redirect;
								}, 800);
							}
						} else {
							const message = result.data && result.data.message ? result.data.message : window.ibcRegister.messages.error;
							showNotice(message, true);
						}
					})
					.catch(() => {
						showNotice(window.ibcRegister.messages.error, true);
					})
					.finally(finalize);
			};

			if (siteKey && window.grecaptcha) {
				window.grecaptcha.ready(() => {
					window.grecaptcha.execute(siteKey, { action: 'ibc_register' }).then(process).catch(() => {
						showNotice(window.ibcRegister.messages.error, true);
						finalize();
					});
				});
			} else {
				process('');
			}
		};

		$form.on('submit', handleSubmit);

		if (window.intlTelInput) {
			const phoneInput = document.querySelector('#ibc_phone');
			if (phoneInput) {
				window.intlTelInput(phoneInput, {
					initialCountry: 'ma',
					preferredCountries: ['ma', 'fr'],
					separateDialCode: true,
					utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@18/build/js/utils.js',
				});
			}
		}
	}
}(jQuery));
