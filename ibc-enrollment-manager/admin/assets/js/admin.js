/* global IBCEnrollmentDashboard */

(function () {
	'use strict';

	const config = window.IBCEnrollmentDashboard;
	const root = document.querySelector('[data-ibc-dashboard]');
	if (!config || !root) {
		return;
	}

	const TOKEN_KEY = 'IBC_ENROLLMENT_TOKEN';
	const restBase = (config.restUrl || '').replace(/\/$/, '');
	const getText = (key, fallback) => (config.texts && config.texts[key]) || fallback;

	const $ = (selector, context = root) => context.querySelector(selector);
	const $$ = (selector, context = root) => Array.prototype.slice.call(context.querySelectorAll(selector));

	const elements = {
		status: $('[data-ibc-status]'),
		statusText: $('[data-ibc-status-text]'),
		tableBody: $('[data-ibc-table-body]'),
		search: $('#ibc_filter_search'),
		level: $('#ibc_filter_level'),
		statut: $('#ibc_filter_status'),
		perPage: $('#ibc_filter_perpage'),
		prev: $('#ibc-prev'),
		next: $('#ibc-next'),
		pageIndicator: $('[data-ibc-page-indicator]'),
		refresh: $('[data-ibc-refresh]'),
		reset: $('[data-ibc-reset]'),
		exportBtn: $('[data-ibc-export]'),
		logout: $('[data-ibc-logout]'),
		loginModal: $('[data-ibc-login]'),
		loginPassword: $('#ibc_admin_password'),
		loginSubmit: $('[data-ibc-login-submit]'),
		loginFeedback: $('[data-ibc-login] .ibc-modal-feedback'),
		editModal: $('[data-ibc-edit]'),
		editForm: $('[data-ibc-edit-form]'),
		editFeedback: $('[data-ibc-edit] .ibc-modal-feedback'),
		editCancel: $('[data-ibc-edit-cancel]'),
		docRecto: $('[data-ibc-doc="recto"]'),
		docVerso: $('[data-ibc-doc="verso"]'),
		docEmpty: $('[data-ibc-doc-empty]'),
		extraList: $('[data-ibc-edit-extra] ul'),
		extraWrapper: $('[data-ibc-edit-extra]'),
		docsWrapper: $('[data-ibc-edit-docs]'),
	};

	const state = {
		token: sessionStorage.getItem(TOKEN_KEY) || '',
		page: 1,
		perPage: parseInt(elements.perPage?.value, 10) || 10,
		total: 0,
		filters: {
			search: '',
			niveau: '',
			statut: '',
		},
		loading: false,
		lastItems: [],
	};

	let searchTimer = null;
	let statusTimer = null;

	const setStatus = (message, variant = 'idle', duration = 0) => {
		if (!elements.status || !elements.statusText) {
			return;
		}
		elements.status.classList.remove('is-loading', 'is-success', 'is-error');
		if (variant === 'loading') {
			elements.status.classList.add('is-loading');
		} else if (variant === 'success') {
			elements.status.classList.add('is-success');
		} else if (variant === 'error') {
			elements.status.classList.add('is-error');
		}
		elements.statusText.textContent = message;
		if (statusTimer) {
			clearTimeout(statusTimer);
		}
		if (duration > 0 && variant !== 'loading') {
			statusTimer = setTimeout(() => {
				elements.status.classList.remove('is-loading', 'is-success', 'is-error');
				elements.statusText.textContent = getText('ready', 'Prêt');
			}, duration);
		}
	};

	const apiFetch = (path, options = {}) => {
		const headers = new Headers(options.headers || {});
		if (state.token) {
			headers.set('X-IBC-Token', state.token);
		}
		if (!headers.has('Content-Type') && options.body && !(options.body instanceof FormData)) {
			headers.set('Content-Type', 'application/json');
		}
		return fetch(`${restBase}${path}`, {
			credentials: 'same-origin',
			...options,
			headers,
		}).then(async (response) => {
			const contentType = response.headers.get('content-type') || '';
			const payload = contentType.includes('application/json') ? await response.json() : {};
			if (!response.ok || payload.success === false) {
				const message = payload.message || getText('serverError', 'Erreur serveur.');
				throw new Error(message);
			}
			return payload.data;
		});
	};

	const toggleModal = (modal, show) => {
		if (!modal) {
			return;
		}
		modal.hidden = !show;
		modal.setAttribute('aria-hidden', String(!show));
		document.body.classList.toggle('ibc-modal-open', show);
		if (show) {
			const focusable = modal.querySelector('input, button, select, textarea, [tabindex]');
			focusable?.focus();
		}
	};

	const showLogin = (message) => {
		if (elements.loginFeedback && message) {
			elements.loginFeedback.hidden = false;
			elements.loginFeedback.textContent = message;
		}
		toggleModal(elements.loginModal, true);
	};

	const hideLogin = () => {
		if (elements.loginFeedback) {
			elements.loginFeedback.hidden = true;
			elements.loginFeedback.textContent = '';
		}
		toggleModal(elements.loginModal, false);
	};

	const sanitize = (value) =>
		(value || '')
			.toString()
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');

	const renderDocs = (row) => {
		if (!elements.docsWrapper) {
			return;
		}
		const recto = row.dataset.cinRecto || '';
		const verso = row.dataset.cinVerso || '';

		if (elements.docRecto) {
			elements.docRecto.hidden = !recto;
			if (recto) {
				elements.docRecto.href = recto;
			}
		}
		if (elements.docVerso) {
			elements.docVerso.hidden = !verso;
			if (verso) {
				elements.docVerso.href = verso;
			}
		}
		if (elements.docEmpty) {
			elements.docEmpty.hidden = Boolean(recto || verso);
		}
		elements.docsWrapper.classList.toggle('is-empty', !recto && !verso);
		elements.docsWrapper.hidden = !recto && !verso && elements.docEmpty?.hidden;
	};

	const renderExtras = (row) => {
		if (!elements.extraWrapper || !elements.extraList) {
			return;
		}
		let extras = [];
		try {
			extras = row.dataset.extra ? JSON.parse(row.dataset.extra) : [];
		} catch {
			extras = [];
		}
		elements.extraList.innerHTML = '';
		if (!extras || !extras.length) {
			elements.extraWrapper.hidden = true;
			return;
		}
		extras.forEach((extra) => {
			if (!extra?.value) {
				return;
			}
			const li = document.createElement('li');
			li.innerHTML = `<strong>${sanitize(extra.label || extra.id || '')}</strong> : ${sanitize(extra.display || extra.value)}`;
			elements.extraList.appendChild(li);
		});
		elements.extraWrapper.hidden = false;
	};

	const openEditModal = (row) => {
		if (!elements.editForm) {
			return;
		}
		elements.editForm.reset();
		elements.editForm.querySelector('[name="id"]').value = row.dataset.id || '';
		elements.editForm.querySelector('[name="prenom"]').value = row.dataset.prenom || '';
		elements.editForm.querySelector('[name="nom"]').value = row.dataset.nom || '';
		elements.editForm.querySelector('[name="email"]').value = row.dataset.email || '';
		elements.editForm.querySelector('[name="telephone"]').value = row.dataset.phone || '';
		elements.editForm.querySelector('[name="statut"]').value = row.dataset.status || 'Confirme';
		const notesField = elements.editForm.querySelector('[name="message"]');
		if (notesField) {
			notesField.value = row.dataset.notes || '';
		}
		renderDocs(row);
		renderExtras(row);
		if (elements.editFeedback) {
			elements.editFeedback.hidden = true;
			elements.editFeedback.textContent = '';
		}
		toggleModal(elements.editModal, true);
	};

	const renderTable = (items) => {
		state.lastItems = items || [];
		if (!elements.tableBody) {
			return;
		}
		elements.tableBody.innerHTML = '';
		if (!items || !items.length) {
			const emptyRow = document.createElement('tr');
			emptyRow.className = 'ibc-table-empty';
			emptyRow.innerHTML = `<td colspan="14">${getText('empty', 'Aucune inscription trouvée.')}</td>`;
			elements.tableBody.appendChild(emptyRow);
			return;
		}

		items.forEach((item) => {
			const row = document.createElement('tr');
			row.dataset.id = item.row || '';
			row.dataset.ref = item.ref || '';
			row.dataset.prenom = item.prenom || '';
			row.dataset.nom = item.nom || '';
			row.dataset.email = item.email || '';
			row.dataset.phone = item.telephone || item.phone || '';
			row.dataset.level = item.niveau || item.level || '';
			row.dataset.status = item.statut || 'Confirme';
			row.dataset.notes = item.message || '';
			row.dataset.extra = JSON.stringify(item.extraFields || []);
			row.dataset.cinRecto = item.cinRectoUrl || '';
			row.dataset.cinVerso = item.cinVersoUrl || '';
			row.dataset.timestamp = item.timestamp || item.createdAt || '';
			row.dataset.birthdate = item.dateNaissance || '';
			row.dataset.birthplace = item.lieuNaissance || '';

			const docChip = (url, label) =>
				url
					? `<a class="ibc-doc-chip" href="${encodeURI(url)}" target="_blank" rel="noopener noreferrer">${label}</a>`
					: `<span class="ibc-doc-chip is-muted">${getText('docMissing', 'Aucun doc')}</span>`;

			row.innerHTML = `
				<td>${sanitize(item.timestamp || '')}</td>
				<td>${sanitize(item.prenom || '')}</td>
				<td>${sanitize(item.nom || '')}</td>
				<td>${sanitize(item.dateNaissance || '')}</td>
				<td>${sanitize(item.lieuNaissance || '')}</td>
				<td>${item.email ? `<a href="mailto:${sanitize(item.email)}">${sanitize(item.email)}</a>` : '—'}</td>
				<td>${item.telephone ? `<a href="tel:${sanitize(item.telephone.replace(/\s+/g, ''))}">${sanitize(item.telephone)}</a>` : '—'}</td>
				<td>${sanitize(item.niveau || '')}</td>
				<td><div class="ibc-doc-chip-group">${docChip(item.cinRectoUrl, getText('docRecto', 'Recto'))}</div></td>
				<td><div class="ibc-doc-chip-group">${docChip(item.cinVersoUrl, getText('docVerso', 'Verso'))}</div></td>
				<td>${item.message ? `<span class="ibc-message-chip">${sanitize(item.message)}</span>` : '<span class="ibc-message-chip is-empty">—</span>'}</td>
				<td><button type="button" class="ibc-ref-link" data-action="details">${sanitize(item.ref || '')}</button></td>
				<td>
					<select class="ibc-status-select" data-action="status">
						<option value="Confirme"${item.statut === 'Confirme' ? ' selected' : ''}>${getText('statusConfirm', 'Confirmé')}</option>
						<option value="Annule"${item.statut === 'Annule' ? ' selected' : ''}>${getText('statusCancel', 'Annulé')}</option>
					</select>
				</td>
				<td>
					<div class="ibc-row-actions">
						<button type="button" class="ibc-button-primary" data-action="save">${getText('save', 'Sauver')}</button>
						<button type="button" class="ibc-button-danger" data-action="delete">${getText('delete', 'Supprimer')}</button>
					</div>
				</td>
			`;
			elements.tableBody.appendChild(row);
		});
	};

	const updatePagination = () => {
		const pageLabel = getText('page', 'Page');
		const totalPages = state.total > 0 ? Math.ceil(state.total / state.perPage) : state.page;
		if (elements.pageIndicator) {
			elements.pageIndicator.textContent = `${pageLabel} ${state.page} / ${totalPages || state.page}`;
		}
		if (elements.prev) {
			elements.prev.disabled = state.page <= 1;
		}
		if (elements.next) {
			const hasMore = totalPages ? state.page >= totalPages : state.lastItems.length < state.perPage;
			elements.next.disabled = hasMore;
		}
	};

	const buildQuery = () => {
		const params = new URLSearchParams();
		if (state.filters.search) {
			params.set('search', state.filters.search);
		}
		if (state.filters.niveau) {
			params.set('niveau', state.filters.niveau);
		}
		if (state.filters.statut) {
			params.set('statut', state.filters.statut);
		}
		params.set('page', String(state.page));
		params.set('per_page', String(state.perPage));
		return params.toString();
	};

	const loadData = () => {
		if (state.loading) {
			return;
		}
		state.loading = true;
		setStatus(getText('loading', 'Chargement…'), 'loading');
		apiFetch(`/registrations?${buildQuery()}`, { method: 'GET' })
			.then((data) => {
				state.total = data.total || 0;
				state.page = data.page || state.page;
				state.perPage = data.limit || state.perPage;
				renderTable(data.items || []);
				updatePagination();
				setStatus(getText('refreshed', 'Données mises à jour.'), 'success', 2000);
			})
			.catch((error) => {
				renderTable([]);
				updatePagination();
				setStatus(error.message || getText('loginError', 'Mot de passe invalide.'), 'error', 4000);
				state.token = '';
				sessionStorage.removeItem(TOKEN_KEY);
				showLogin(error.message);
			})
			.finally(() => {
				state.loading = false;
			});
	};

	const submitLogin = () => {
		const password = elements.loginPassword?.value.trim();
		if (!password) {
			showLogin(getText('loginError', 'Mot de passe requis.'));
			return;
		}
		setStatus(getText('loading', 'Chargement…'), 'loading');
		apiFetch('/login', {
			method: 'POST',
			body: JSON.stringify({ password }),
		})
			.then((data) => {
				state.token = data.token;
				sessionStorage.setItem(TOKEN_KEY, data.token);
				hideLogin();
				setStatus(getText('ready', 'Prêt'), 'success', 1500);
				loadData();
			})
			.catch((error) => {
				showLogin(error.message || getText('loginError', 'Accès refusé.'));
				setStatus(error.message || getText('loginError', 'Accès refusé.'), 'error', 3000);
			});
	};

	const saveRowStatus = (row) => {
		const id = row.dataset.id;
		const select = row.querySelector('[data-action="status"]');
		if (!id || !select) {
			return;
		}
		setStatus(getText('saving', 'Enregistrement…'), 'loading');
		apiFetch(`/registrations/${encodeURIComponent(id)}`, {
			method: 'POST',
			body: JSON.stringify({
				fields: {
					statut: select.value,
				},
			}),
		})
			.then(() => {
				setStatus(getText('saveSuccess', 'Inscription mise à jour.'), 'success', 2000);
				loadData();
			})
			.catch((error) => {
				setStatus(error.message || getText('saveError', 'Impossible d’enregistrer.'), 'error', 3000);
			});
	};

	const deleteRow = (row) => {
		const reference = row.dataset.ref;
		if (!reference) {
			return;
		}
		const confirmText = getText('deleteConfirm', 'Confirmer l’annulation ?');
		if (!window.confirm(confirmText)) {
			return;
		}
		setStatus(getText('deleting', 'Suppression…'), 'loading');
		apiFetch(`/registrations/${encodeURIComponent(reference)}/cancel`, {
			method: 'POST',
		})
			.then(() => {
				setStatus(getText('deleteDone', 'Inscription annulée.'), 'success', 2000);
				loadData();
			})
			.catch((error) => {
				setStatus(error.message || getText('deleteError', 'Impossible de supprimer.'), 'error', 3000);
			});
	};

	const submitEdit = (event) => {
		event.preventDefault();
		if (!elements.editForm) {
			return;
		}
		const id = elements.editForm.querySelector('[name="id"]').value;
		if (!id) {
			return;
		}
		const formData = new FormData(elements.editForm);
		const fields = {};
		formData.forEach((value, key) => {
			if (key !== 'id' && value !== '') {
				fields[key] = value;
			}
		});
		apiFetch(`/registrations/${encodeURIComponent(id)}`, {
			method: 'POST',
			body: JSON.stringify({ fields }),
		})
			.then(() => {
				elements.editFeedback.hidden = false;
				elements.editFeedback.textContent = getText('saveSuccess', 'Inscription mise à jour.');
				elements.editFeedback.classList.add('ibc-modal-feedback-success');
				setTimeout(() => {
					toggleModal(elements.editModal, false);
					loadData();
				}, 800);
			})
			.catch((error) => {
				elements.editFeedback.hidden = false;
				elements.editFeedback.textContent = error.message || getText('saveError', 'Impossible d’enregistrer.');
				elements.editFeedback.classList.remove('ibc-modal-feedback-success');
			});
	};

	const exportCsv = () => {
		if (!state.lastItems.length) {
			return;
		}
		const headers = [
			'Timestamp',
			'Prenom',
			'Nom',
			'DateNaissance',
			'LieuNaissance',
			'Email',
			'Telephone',
			'Niveau',
			'CIN Recto',
			'CIN Verso',
			'Message',
			'Reference',
			'Statut',
		];
		const lines = [headers.join(',')];
		state.lastItems.forEach((item) => {
			const row = [
				item.timestamp || '',
				item.prenom || '',
				item.nom || '',
				item.dateNaissance || '',
				item.lieuNaissance || '',
				item.email || '',
				item.telephone || '',
				item.niveau || '',
				item.cinRectoUrl || '',
				item.cinVersoUrl || '',
				(item.message || '').replace(/\s+/g, ' '),
				item.ref || '',
				item.statut || '',
			].map((value) => `"${(value || '').replace(/"/g, '""')}"`);
			lines.push(row.join(','));
		});
		const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
		const url = URL.createObjectURL(blob);
		const a = document.createElement('a');
		a.href = url;
		a.download = `ibc-enrollments-${new Date().toISOString().slice(0, 10)}.csv`;
		document.body.appendChild(a);
		a.click();
		document.body.removeChild(a);
		URL.revokeObjectURL(url);
		setStatus(getText('exported', 'Export CSV généré.'), 'success', 2000);
	};

	/* Event bindings */
	root.addEventListener('click', (event) => {
		const action = event.target.closest('[data-action]');
		if (!action) {
			return;
		}
		const row = event.target.closest('tr');
		if (!row) {
			return;
		}
		switch (action.dataset.action) {
			case 'details':
				openEditModal(row);
				break;
			case 'save':
				saveRowStatus(row);
				break;
			case 'delete':
				deleteRow(row);
				break;
			default:
		}
	});

	elements.prev?.addEventListener('click', () => {
		if (state.page > 1) {
			state.page -= 1;
			loadData();
		}
	});

	elements.next?.addEventListener('click', () => {
		state.page += 1;
		loadData();
	});

	elements.perPage?.addEventListener('change', () => {
		state.perPage = parseInt(elements.perPage.value, 10) || 10;
		state.page = 1;
		loadData();
	});

	const handleSearchChange = () => {
		if (searchTimer) {
			clearTimeout(searchTimer);
		}
		searchTimer = setTimeout(() => {
			state.filters.search = elements.search?.value.trim() || '';
			state.page = 1;
			loadData();
		}, 300);
	};

	elements.search?.addEventListener('input', handleSearchChange);
	elements.level?.addEventListener('change', () => {
		state.filters.niveau = elements.level.value;
		state.page = 1;
		loadData();
	});

	elements.statut?.addEventListener('change', () => {
		state.filters.statut = elements.statut.value;
		state.page = 1;
		loadData();
	});

	elements.refresh?.addEventListener('click', () => loadData());

	elements.reset?.addEventListener('click', () => {
		state.filters = { search: '', niveau: '', statut: '' };
		state.page = 1;
		elements.search.value = '';
		elements.level.value = '';
		elements.statut.value = '';
		loadData();
	});

	elements.exportBtn?.addEventListener('click', exportCsv);

	elements.logout?.addEventListener('click', () => {
		state.token = '';
		sessionStorage.removeItem(TOKEN_KEY);
		setStatus(getText('logoutDone', 'Déconnexion effectuée.'), 'success', 2000);
		showLogin();
	});

	elements.loginSubmit?.addEventListener('click', submitLogin);
	elements.loginPassword?.addEventListener('keydown', (event) => {
		if (event.key === 'Enter') {
			event.preventDefault();
			submitLogin();
		}
	});

	elements.loginModal?.addEventListener('click', (event) => {
		if (event.target === elements.loginModal) {
			toggleModal(elements.loginModal, false);
		}
	});

	elements.editCancel?.addEventListener('click', () => toggleModal(elements.editModal, false));
	elements.editForm?.addEventListener('submit', submitEdit);
	elements.editModal?.addEventListener('click', (event) => {
		if (event.target === elements.editModal) {
			toggleModal(elements.editModal, false);
		}
	});

	if (state.token) {
		setStatus(getText('ready', 'Prêt'), 'success', 1500);
		loadData();
	} else {
		showLogin();
	}
})();
