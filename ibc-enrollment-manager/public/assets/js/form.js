/* global IBCForm */
(function () {
	'use strict';

	const root = document.querySelector('[data-ibc-form]');
	if (!root || typeof IBCForm === 'undefined') {
		return;
	}

	const form = root.querySelector('form');
	const feedback = root.querySelector('.ibc-form-feedback');
	const successPopup = root.querySelector('[data-ibc-success]');
	const closedPopup = root.querySelector('[data-ibc-closed]');
	const closeButtons = root.querySelectorAll('[data-ibc-close]');
	const schema = Array.isArray(IBCForm.fields) ? IBCForm.fields : [];

	const findFieldElement = (fieldId) => root.querySelector(`[data-ibc-field-id="${fieldId}"]`);

	const emailField = schema.find((field) => field.map === 'email' || field.id === 'email');
	const phoneField = schema.find((field) => field.map === 'phone' || field.id === 'phone');

	const inputs = {
		email: emailField ? findFieldElement(emailField.id)?.querySelector('input, select, textarea') : null,
		phone: phoneField ? findFieldElement(phoneField.id)?.querySelector('input, select, textarea') : null,
	};

	let debounceTimer;
	let lastCheck = {};

	const showFeedback = (message, type = 'error') => {
		if (!feedback) {
			return;
		}
		feedback.textContent = message;
		feedback.hidden = false;
		feedback.classList.toggle('ibc-form-feedback-error', type === 'error');
		feedback.classList.toggle('ibc-form-feedback-success', type === 'success');
	};

	const clearFeedback = () => {
		if (feedback) {
			feedback.hidden = true;
			feedback.textContent = '';
			feedback.classList.remove('ibc-form-feedback-error', 'ibc-form-feedback-success');
		}
	};

	const togglePopup = (popup, visible) => {
		if (popup) {
			popup.hidden = !visible;
		}
	};

	closeButtons.forEach((button) => {
		button.addEventListener('click', () => {
			togglePopup(successPopup, false);
			togglePopup(closedPopup, false);
		});
	});

	const ensureJson = async (response) => {
		const contentType = (response.headers.get('content-type') || '').toLowerCase();
		if (!contentType.includes('application/json')) {
			throw new Error(IBCForm.messages.nonJson || 'Non-JSON response received.');
		}
		return response.json();
	};

	const checkCapacity = () => {
		const email = inputs.email ? inputs.email.value.trim() : '';
		const phone = inputs.phone ? inputs.phone.value.trim() : '';

		if (!email && !phone) {
			return;
		}

		const cacheKey = `${email}|${phone}`;
		if (lastCheck.key === cacheKey && Date.now() - lastCheck.time < 10000) {
			return;
		}

		clearTimeout(debounceTimer);
		debounceTimer = setTimeout(() => {
			const url = new URL(`${IBCForm.restUrl}/check`);
			url.searchParams.append('email', email);
			url.searchParams.append('phone', phone);

			fetch(url.toString(), {
				method: 'GET',
				credentials: 'same-origin',
			})
				.then(ensureJson)
				.then((result) => {
					if (!result.success) {
						return;
					}

					lastCheck = { key: cacheKey, time: Date.now(), data: result.data };

					if (result.data.existsEmail || result.data.existsPhone) {
						showFeedback(IBCForm.messages.duplicate);
					} else if (result.data.capacity > 0 && result.data.total >= result.data.capacity) {
						showFeedback(IBCForm.messages.capacity);
					} else {
						clearFeedback();
					}
				})
				.catch(() => {
					// Quietly ignore network errors for preview checks.
				});
		}, 400);
	};

	if (inputs.email) {
		inputs.email.addEventListener('input', checkCapacity);
	}
	if (inputs.phone) {
		inputs.phone.addEventListener('input', checkCapacity);
	}

	const disableForm = (disabled) => {
		const button = form.querySelector('button[type="submit"]');
		if (button) {
			button.disabled = disabled;
		}
	};

	const buildFormData = () => {
		const data = new FormData(form);
		return data;
	};

	const resetCheckState = () => {
		lastCheck = {};
	};

	form.addEventListener('submit', (event) => {
		event.preventDefault();
		clearFeedback();

		const last = lastCheck.data;
		if (last) {
			if (last.existsEmail || last.existsPhone) {
				showFeedback(IBCForm.messages.duplicate);
				return;
			}
			if (last.capacity > 0 && last.total >= last.capacity) {
				togglePopup(closedPopup, true);
				return;
			}
		}

		const formData = buildFormData();
		formData.append('_locale', 'fr');

		disableForm(true);

		fetch(`${IBCForm.restUrl}/register`, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData,
		})
			.then(ensureJson)
			.then((payload) => {
				if (!payload.success) {
					const message = payload.message || IBCForm.messages.error;
					throw new Error(message);
				}

				form.reset();
				resetCheckState();
				togglePopup(successPopup, true);
			})
			.catch((error) => {
				const message = error.message || IBCForm.messages.error;
				if (message === IBCForm.messages.capacity) {
					togglePopup(closedPopup, true);
				} else {
					showFeedback(message);
				}
			})
			.finally(() => {
				disableForm(false);
			});
	});
})();
