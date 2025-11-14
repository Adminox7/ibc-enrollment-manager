/* global IBCEnrollmentForm */

(function () {
	'use strict';

	if (!window.IBCEnrollmentForm) {
		return;
	}

	const settings = window.IBCEnrollmentForm;
	const roots = document.querySelectorAll('[data-ibc-form]');
	if (!roots.length) {
		return;
	}

	const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'application/pdf'];
	const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB safety net.

	const apiFetch = (endpoint, options = {}) => {
		const base = settings.restUrl?.replace(/\/$/, '') || '';
		return fetch(`${base}${endpoint}`, {
			credentials: 'same-origin',
			headers: {
				'X-WP-Nonce': settings.nonce || '',
				...(options.headers || {}),
			},
			...options,
		}).then(async (response) => {
			const contentType = response.headers.get('content-type') || '';
			if (!contentType.includes('application/json')) {
				throw new Error(settings.messages?.serverError || 'Unexpected response.');
			}
			const data = await response.json();
			if (!response.ok || data.success === false) {
				throw new Error(data.message || settings.messages?.serverError || 'Request failed.');
			}
			return data;
		});
	};

	const normalizeEmail = (value) => (value || '').trim().toLowerCase();
	const normalizePhone = (value) => (value || '').replace(/[^\d+]/g, '').trim();

	const formatBytes = (bytes) => `${(bytes / (1024 * 1024)).toFixed(1)} MB`;

	const showFeedback = (box, message, isSuccess = false) => {
		if (!box) {
			return;
		}
		box.textContent = message;
		box.hidden = false;
		box.classList.toggle('ibc-form-feedback-success', isSuccess);
	};

	const hideFeedback = (box) => {
		if (!box) {
			return;
		}
		box.hidden = true;
		box.textContent = '';
		box.classList.remove('ibc-form-feedback-success');
	};

	const toggleModal = (modal, open) => {
		if (!modal) {
			return;
		}
		modal.hidden = !open;
		modal.setAttribute('aria-hidden', String(!open));
		document.body.classList.toggle('ibc-modal-open', open);
		if (open) {
			const primaryAction = modal.querySelector('a, button, input, select, textarea');
			if (primaryAction instanceof HTMLElement) {
				primaryAction.focus();
			}
		}
	};

	const resetDownloadLink = (link) => {
		if (!link) {
			return;
		}
		link.classList.add('is-disabled');
		link.setAttribute('aria-disabled', 'true');
		link.removeAttribute('href');
		link.removeAttribute('download');
	};

	const enableDownloadLink = (link, url, reference) => {
		if (!link || !url) {
			return;
		}
		link.classList.remove('is-disabled');
		link.removeAttribute('aria-disabled');
		link.href = url;
		if (reference) {
			link.download = `recu-prepa-${reference}.pdf`;
		}
	};

	const markFieldError = (fieldWrapper, message) => {
		fieldWrapper?.classList.add('is-error');
		const hint = fieldWrapper?.querySelector('.ibc-field-error');
		if (hint) {
			hint.textContent = message;
			return;
		}
		const small = document.createElement('p');
		small.className = 'ibc-field-error';
		small.textContent = message;
		fieldWrapper?.appendChild(small);
	};

	const clearFieldError = (fieldWrapper) => {
		fieldWrapper?.classList.remove('is-error');
		const hint = fieldWrapper?.querySelector('.ibc-field-error');
		if (hint) {
			hint.remove();
		}
	};

	const validateFileInput = (input, fieldWrapper) => {
		clearFieldError(fieldWrapper);
		const file = input?.files?.[0];
		if (!file) {
			if (input?.required) {
				markFieldError(fieldWrapper, settings.messages?.validation || 'Veuillez fournir ce document.');
				return false;
			}
			return true;
		}

		if (!ALLOWED_TYPES.includes(file.type)) {
			markFieldError(fieldWrapper, settings.messages?.uploadError || 'Format non autorisé.');
			return false;
		}

		if (file.size > MAX_FILE_SIZE) {
			markFieldError(
				fieldWrapper,
				`${settings.messages?.uploadError || 'Fichier trop volumineux.'} (${formatBytes(file.size)} > ${formatBytes(
					MAX_FILE_SIZE
				)})`
			);
			return false;
		}

		return true;
	};

	const serializeForm = (form) => new FormData(form);

	const initForm = (root) => {
		const form = root.querySelector('form');
		const feedback = root.querySelector('.ibc-form-feedback');
		const successModal = root.querySelector('[data-ibc-success]');
		const successText = successModal?.querySelector('[data-ibc-success-text]');
		const successDownload = successModal?.querySelector('[data-ibc-download]');
		const closedModal = root.querySelector('[data-ibc-closed]');

		if (!form) {
			return;
		}

		let capacitySnapshot = null;
		let debounceTimer = null;

		const emailInput = root.querySelector('[data-ibc-field-map="email"] input, [data-ibc-field-map="email"] textarea');
		const phoneInput = root.querySelector(
			'[data-ibc-field-map="telephone"] input, [data-ibc-field-map="telephone"] textarea, [data-ibc-field-map="phone"] input'
		);

		const runDupCheck = () => {
			const email = normalizeEmail(emailInput?.value || '');
			const phone = normalizePhone(phoneInput?.value || '');

			if (!email && !phone) {
				return;
			}

			if (capacitySnapshot && capacitySnapshot.email === email && capacitySnapshot.phone === phone) {
				return;
			}

			clearTimeout(debounceTimer);
			debounceTimer = setTimeout(() => {
				const params = new URLSearchParams();
				params.set('email', email);
				params.set('telephone', phone);
				params.set('phone', phone);

				apiFetch(`/check?${params.toString()}`, { method: 'GET' })
					.then((response) => {
						capacitySnapshot = {
							email,
							phone,
							data: response.data || {},
						};

						const info = capacitySnapshot.data || {};
						if (info.existsEmail || info.existsPhone) {
							showFeedback(feedback, settings.messages?.duplicate || 'Inscription déjà enregistrée.');
						} else if (info.capacity > 0 && info.total >= info.capacity) {
							showFeedback(feedback, settings.messages?.capacity || 'Les inscriptions sont closes.');
						} else {
							hideFeedback(feedback);
						}
					})
					.catch(() => {
						// silent fail
					});
			}, 400);
		};

		emailInput?.addEventListener('input', runDupCheck);
		phoneInput?.addEventListener('input', runDupCheck);

		root.querySelectorAll('[data-ibc-close]').forEach((btn) => {
			btn.addEventListener('click', () => toggleModal(btn.closest('.ibc-popup'), false));
		});

		successDownload?.addEventListener('click', (event) => {
			if (successDownload.classList.contains('is-disabled')) {
				event.preventDefault();
			}
		});

		const validateForm = () => {
			let valid = form.checkValidity();

			form.querySelectorAll('[data-ibc-field-id]').forEach((wrapper) => {
				clearFieldError(wrapper);
				const control = wrapper.querySelector('input, textarea, select');
				if (!control) {
					return;
				}

				if (control.type === 'file') {
					valid = validateFileInput(control, wrapper) && valid;
					return;
				}

				if (!control.checkValidity()) {
					markFieldError(wrapper, settings.messages?.validation || 'Champ requis.');
					valid = false;
				}
			});

			if (!valid && feedback) {
				showFeedback(feedback, settings.messages?.validation || 'Merci de vérifier les champs en surbrillance.');
			}

			return valid;
		};

		const disableForm = (state) => {
			const submit = form.querySelector('[type="submit"]');
			if (submit) {
				submit.disabled = state;
			}
		};

		form.addEventListener('submit', (event) => {
			event.preventDefault();
			hideFeedback(feedback);

			if (!validateForm()) {
				return;
			}

			const info = capacitySnapshot?.data;
			if (info) {
				if (info.existsEmail || info.existsPhone) {
					showFeedback(feedback, settings.messages?.duplicate || 'Inscription déjà enregistrée.');
					return;
				}
				if (info.capacity > 0 && info.total >= info.capacity) {
					toggleModal(closedModal, true);
					return;
				}
			}

			const formData = serializeForm(form);
			disableForm(true);

			apiFetch('/register', {
				method: 'POST',
				body: formData,
			})
				.then((payload) => {
					const data = payload.data || {};
					form.reset();
					capacitySnapshot = null;

					const pdfUrl = data.receipt_url || data.receiptUrl || '';
					const reference = data.reference || data.ref || '';
					const pdfAvailable =
						typeof data.pdf_available === 'boolean'
							? data.pdf_available
							: Boolean(pdfUrl);

					if (successText) {
						successText.textContent = pdfAvailable
							? settings.messages?.success ||
							  'Votre préinscription est enregistrée. Téléchargez votre reçu.'
							: settings.messages?.successNoPdf ||
							  'Votre préinscription est enregistrée. Le reçu vous sera envoyé par e-mail.';
					}

					resetDownloadLink(successDownload);
					if (pdfAvailable && pdfUrl) {
						enableDownloadLink(successDownload, pdfUrl, reference);
					}

					toggleModal(successModal, true);
				})
				.catch((error) => {
					showFeedback(feedback, error.message || settings.messages?.serverError || 'Erreur inattendue.');
				})
				.finally(() => {
					disableForm(false);
				});
		});
	};

	roots.forEach(initForm);
})();
