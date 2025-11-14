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
	const successText = successPopup ? successPopup.querySelector('[data-ibc-success-text]') : null;
	const successDownload = successPopup ? successPopup.querySelector('[data-ibc-download]') : null;
	const closedPopup = root.querySelector('[data-ibc-closed]');
	const schema = Array.isArray(IBCForm.fields) ? IBCForm.fields : [];

	const findFieldElement = (fieldId) => root.querySelector(`[data-ibc-field-id="${fieldId}"]`);

	const emailField = schema.find((field) => field.map === 'email' || field.id === 'email');
	const telephoneField = schema.find(
		(field) => field.map === 'telephone' || field.id === 'telephone' || field.map === 'phone' || field.id === 'phone'
	);

	const inputs = {
		email: emailField ? findFieldElement(emailField.id)?.querySelector('input, select, textarea') : null,
		telephone: telephoneField ? findFieldElement(telephoneField.id)?.querySelector('input, select, textarea') : null,
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

	let activeModal = null;
	let lastFocusedElement = null;

	const focusableSelectors = [
		'a[href]',
		'button:not([disabled])',
		'input:not([disabled])',
		'select:not([disabled])',
		'textarea:not([disabled])',
		'[tabindex]:not([tabindex="-1"])',
	].join(', ');

	const trapFocus = (event) => {
		if (!activeModal || event.key !== 'Tab') {
			return;
		}

		const focusable = Array.from(activeModal.querySelectorAll(focusableSelectors)).filter(
			(element) => element.offsetParent !== null || element === document.activeElement
		);

		if (!focusable.length) {
			event.preventDefault();
			return;
		}

		const first = focusable[0];
		const last = focusable[focusable.length - 1];

		if (event.shiftKey && document.activeElement === first) {
			event.preventDefault();
			last.focus();
		} else if (!event.shiftKey && document.activeElement === last) {
			event.preventDefault();
			first.focus();
		}
	};

	const openModal = (modal) => {
		if (!modal) {
			return;
		}
		lastFocusedElement = document.activeElement instanceof HTMLElement ? document.activeElement : null;
		modal.hidden = false;
		modal.setAttribute('aria-hidden', 'false');
		document.body.classList.add('ibc-modal-open');
		activeModal = modal;

		const focusable = modal.querySelectorAll(focusableSelectors);
		if (focusable.length > 0) {
			focusable[0].focus();
		} else {
			modal.focus();
		}
	};

	const closeModal = (modal) => {
		if (!modal) {
			return;
		}
		modal.hidden = true;
		modal.setAttribute('aria-hidden', 'true');
		if (!document.querySelector('.ibc-popup:not([hidden])')) {
			document.body.classList.remove('ibc-modal-open');
			activeModal = null;
		}
		if (lastFocusedElement) {
			lastFocusedElement.focus();
		}
	};

	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape' && activeModal) {
			event.preventDefault();
			closeModal(activeModal);
		}
	});

	document.addEventListener('keydown', trapFocus, true);

	root.querySelectorAll('[data-ibc-close]').forEach((button) => {
		button.addEventListener('click', () => {
			closeModal(button.closest('.ibc-popup'));
		});
	});

	[successPopup, closedPopup].forEach((modal) => {
		if (!modal) {
			return;
		}
		modal.addEventListener('click', (event) => {
			if (event.target === modal) {
				closeModal(modal);
			}
		});
	});

	if (successDownload) {
		successDownload.addEventListener('click', (event) => {
			const url = successDownload.getAttribute('href');
			const isDisabled = successDownload.classList.contains('is-disabled');
			if (!url || isDisabled) {
				event.preventDefault();
				return;
			}
		});
	}

	const ensureJson = async (response) => {
		const contentType = (response.headers.get('content-type') || '').toLowerCase();
		if (!contentType.includes('application/json')) {
			throw new Error(IBCForm.messages.nonJson || 'Non-JSON response received.');
		}
		return response.json();
	};

	const checkCapacity = () => {
		const email = inputs.email ? inputs.email.value.trim() : '';
		const telephone = inputs.telephone ? inputs.telephone.value.trim() : '';

		if (!email && !telephone) {
			return;
		}

		const cacheKey = `${email}|${telephone}`;
		if (lastCheck.key === cacheKey && Date.now() - lastCheck.time < 10000) {
			return;
		}

		clearTimeout(debounceTimer);
		debounceTimer = setTimeout(() => {
			const url = new URL(`${IBCForm.restUrl}/check`);
			url.searchParams.append('email', email);
			url.searchParams.append('telephone', telephone);
			url.searchParams.append('phone', telephone);

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
	if (inputs.telephone) {
		inputs.telephone.addEventListener('input', checkCapacity);
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

  				const data = payload.data || {};
  				const reference = data.reference || data.ref || '';
  				const receiptUrl = data.receipt_url || data.receiptUrl || '';
  				const pdfAvailable =
  					typeof data.pdf_available === 'boolean'
  						? data.pdf_available
  						: Boolean(receiptUrl);

  				if (successText) {
  					successText.textContent = pdfAvailable
  						? IBCForm.messages.success ||
  						  'Votre préinscription est enregistrée. Vous pouvez maintenant télécharger votre reçu de préinscription.'
  						: IBCForm.messages.successNoPdf ||
  						  'Votre préinscription est enregistrée. Le reçu vous sera envoyé par e-mail.';
  				}

  				if (successDownload) {
  					if (pdfAvailable && receiptUrl) {
  						successDownload.classList.remove('is-disabled');
  						successDownload.removeAttribute('aria-disabled');
						successDownload.setAttribute('href', receiptUrl);
						successDownload.setAttribute('download', reference ? `recu-prepa-${reference}.pdf` : '');
  					} else {
  						successDownload.classList.add('is-disabled');
  						successDownload.setAttribute('aria-disabled', 'true');
  						successDownload.removeAttribute('href');
  						successDownload.removeAttribute('download');
  					}
  				}

  				openModal(successPopup);
			})
			.catch((error) => {
				const message = error.message || IBCForm.messages.error;
				if (message === IBCForm.messages.capacity) {
  					openModal(closedPopup);
				} else {
					showFeedback(message);
				}
			})
			.finally(() => {
				disableForm(false);
			});
	});
})();
