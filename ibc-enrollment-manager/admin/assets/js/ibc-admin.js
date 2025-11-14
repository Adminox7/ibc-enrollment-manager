/* global IBCDashboard */
(function () {
	'use strict';

	const root = document.querySelector('[data-ibc-dashboard]');
	if (!root || typeof IBCDashboard === 'undefined') {
		return;
	}

	const state = {
		token: sessionStorage.getItem('ibcToken') || '',
		page: 1,
		perPage: 10,
		total: 0,
		loading: false,
		lastQuery: {},
	};
	let statusTimer = null;
	let searchTimer = null;

	const getText = (key, fallback) => {
		if (typeof IBCDashboard.texts !== 'object' || IBCDashboard.texts === null) {
			return fallback;
		}
		return typeof IBCDashboard.texts[key] !== 'undefined' ? IBCDashboard.texts[key] : fallback;
	};

	const escapeHtml = (value = '') =>
		value
			.toString()
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');

	const displayOrDash = (value) => (value ? escapeHtml(value) : '—');

	const parseDateValue = (value) => {
		if (!value) {
			return null;
		}
		if (/^\d{2}\/\d{2}\/\d{4}$/.test(value)) {
			const [day, month, year] = value.split('/');
			const parsed = new Date(`${year}-${month}-${day}T00:00:00`);
			return Number.isNaN(parsed.getTime()) ? null : parsed;
		}
		const normalized = value.includes(' ') ? value.replace(' ', 'T') : value;
		const date = new Date(normalized);
		return Number.isNaN(date.getTime()) ? null : date;
	};

	const formatDate = (value) => {
		if (!value) {
			return '—';
		}
		if (/^\d{2}\/\d{2}\/\d{4}$/.test(value)) {
			return value;
		}
		if (/^\d{4}-\d{2}-\d{2}$/.test(value)) {
			const [year, month, day] = value.split('-');
			return `${day}/${month}/${year}`;
		}
		const date = parseDateValue(value);
		if (!date) {
			return value;
		}
		try {
			return new Intl.DateTimeFormat('fr-FR', { dateStyle: 'short' }).format(date);
		} catch (error) {
			return value;
		}
	};

	const formatDateTime = (value) => {
		if (!value) {
			return '—';
		}
		const date = parseDateValue(value);
		if (!date) {
			return value;
		}
		try {
			return new Intl.DateTimeFormat('fr-FR', {
				dateStyle: 'short',
				timeStyle: 'short',
			}).format(date);
		} catch (error) {
			return value;
		}
	};

	const ensureJson = async (response) => {
		const contentType = (response.headers.get('content-type') || '').toLowerCase();
		if (!contentType.includes('application/json')) {
			throw new Error(IBCDashboard.texts.nonJson || 'Réponse invalide.');
		}
		return response.json();
	};

	const selectors = {
		search: '#ibc_filter_search',
		level: '#ibc_filter_level',
		status: '#ibc_filter_status',
		perPage: '#ibc_filter_perpage',
		tableBody: '[data-ibc-table-body]',
		pagination: '[data-ibc-pagination]',
		pageIndicator: '[data-ibc-page-indicator]',
		statusBar: '[data-ibc-status]',
		statusText: '[data-ibc-status-text]',
		refresh: '[data-ibc-refresh]',
		reset: '[data-ibc-reset]',
		export: '[data-ibc-export]',
		logout: '[data-ibc-logout]',
		prev: '[data-ibc-prev]',
		next: '[data-ibc-next]',
		loginModal: '[data-ibc-login]',
		loginPassword: '#ibc_admin_password',
		loginButton: '[data-ibc-login-submit]',
		loginFeedback: '[data-ibc-login] .ibc-modal-feedback',
		editModal: '[data-ibc-edit]',
		editForm: '[data-ibc-edit-form]',
		editFeedback: '[data-ibc-edit] .ibc-modal-feedback',
		editCancel: '[data-ibc-edit-cancel]',
		extraPreview: '[data-ibc-edit-extra]',
		docsPreview: '[data-ibc-edit-docs]',
		docRecto: '[data-ibc-doc="recto"]',
		docVerso: '[data-ibc-doc="verso"]',
		docEmpty: '[data-ibc-doc-empty]',
	};

	const elements = {};
	Object.keys(selectors).forEach((key) => {
		elements[key] = root.querySelector(selectors[key]) || document.querySelector(selectors[key]);
	});

	const tableWrap = root.querySelector('.ibc-table-wrap');
	const scrollShadows = {
		left: root.querySelector('.ibc-scroll-shadow.ibc-left'),
		right: root.querySelector('.ibc-scroll-shadow.ibc-right'),
	};
	const isRTL = document.dir === 'rtl' || document.documentElement.dir === 'rtl' || document.body.dir === 'rtl';
	let rtlScrollBehavior;

	const detectRtlScrollBehavior = () => {
		if (!isRTL) {
			return 'ltr';
		}
		if (rtlScrollBehavior) {
			return rtlScrollBehavior;
		}
		const container = document.createElement('div');
		const content = document.createElement('div');
		container.dir = 'rtl';
		container.style.width = '100px';
		container.style.height = '100px';
		container.style.overflow = 'scroll';
		container.style.visibility = 'hidden';
		content.style.width = '200px';
		content.style.height = '1px';
		container.appendChild(content);
		document.body.appendChild(container);
		container.scrollLeft = 0;
		if (container.scrollLeft > 0) {
			rtlScrollBehavior = 'positive';
		} else {
			container.scrollLeft = 1;
			rtlScrollBehavior = container.scrollLeft === 0 ? 'negative' : 'reverse';
		}
		document.body.removeChild(container);
		return rtlScrollBehavior;
	};

	const toggleShadow = (shadow, visible) => {
		if (!shadow) {
			return;
		}
		shadow.classList.toggle('is-visible', visible);
	};

	const updateScrollShadows = () => {
		if (!tableWrap) {
			return;
		}
		const maxScroll = Math.max(0, tableWrap.scrollWidth - tableWrap.clientWidth);
		if (maxScroll <= 1) {
			toggleShadow(scrollShadows.left, false);
			toggleShadow(scrollShadows.right, false);
			return;
		}

		let startOffset = 0;
		let endOffset = 0;
		const scrollLeft = tableWrap.scrollLeft;

		if (!isRTL) {
			startOffset = scrollLeft;
			endOffset = maxScroll - scrollLeft;
		} else {
			const behavior = detectRtlScrollBehavior();
			if (behavior === 'negative') {
				const abs = Math.abs(scrollLeft);
				startOffset = abs;
				endOffset = maxScroll - abs;
			} else if (behavior === 'reverse') {
				startOffset = maxScroll - scrollLeft;
				endOffset = scrollLeft;
			} else {
				startOffset = scrollLeft;
				endOffset = maxScroll - scrollLeft;
			}
			startOffset = Math.max(0, startOffset);
			endOffset = Math.max(0, endOffset);
		}

		toggleShadow(scrollShadows.left, startOffset > 1);
		toggleShadow(scrollShadows.right, endOffset > 1);
	};

	const scheduleShadowUpdate = () => {
		window.requestAnimationFrame(updateScrollShadows);
	};

	const setDashboardStatus = (message, variant = 'ready', timeout = 0) => {
		if (!elements.statusBar || !elements.statusText) {
			return;
		}

		elements.statusBar.classList.remove('is-loading', 'is-error', 'is-success');
		if (variant === 'loading') {
			elements.statusBar.classList.add('is-loading');
		} else if (variant === 'error') {
			elements.statusBar.classList.add('is-error');
		} else if (variant === 'success') {
			elements.statusBar.classList.add('is-success');
		}

		elements.statusText.textContent = message;

		if (statusTimer) {
			clearTimeout(statusTimer);
		}

		if (timeout > 0 && variant !== 'loading') {
			statusTimer = setTimeout(() => {
				if (elements.statusBar && elements.statusText) {
					elements.statusBar.classList.remove('is-loading', 'is-error', 'is-success');
					elements.statusText.textContent = getText('ready', 'Prêt');
				}
			}, timeout);
		}
	};

	const sanitizeCsvValue = (value) =>
		`"${(value || '')
			.toString()
			.replace(/\r?\n/g, ' ')
			.replace(/"/g, '""')
			.trim()}"`;

	const cleanPhoneHref = (value) => {
		if (!value) {
			return '';
		}
		return value.replace(/\s+/g, '').replace(/[^0-9+]/g, '');
	};

	const columnLabels = {
		timestamp: "Date d’inscription",
		prenom: 'Prénom',
		nom: 'Nom',
		birthDate: 'Date de naissance',
		birthPlace: 'Lieu de naissance',
		email: 'Email',
		phone: 'Téléphone',
		level: 'Niveau',
		cinRecto: 'CIN Recto',
		cinVerso: 'CIN Verso',
		message: 'Message',
		reference: 'Référence',
		status: 'Statut',
		actions: 'Éditer',
	};

	const getStatusDisplay = (statut) => {
		const normalized = (statut || '').toLowerCase();
		if (normalized === 'confirme' || normalized === 'confirmé' || normalized === 'confirmed') {
			return {
				label: getText('statusConfirm', 'Confirmée'),
				className: 'ibc-badge ibc-badge--confirmed',
			};
		}
		if (normalized === 'annule' || normalized === 'annulé' || normalized === 'cancelled' || normalized === 'canceled') {
			return {
				label: getText('statusCancel', 'Annulée'),
				className: 'ibc-badge ibc-badge--canceled',
			};
		}
		if (normalized === 'paye' || normalized === 'payé' || normalized === 'paid') {
			return {
				label: getText('statusPaid', 'Payée'),
				className: 'ibc-badge ibc-badge--paid',
			};
		}

		return {
			label: statut || getText('statusPending', 'En attente'),
			className: 'ibc-badge ibc-badge--pending',
		};
	};

	const updateStatusBadge = (row, statut) => {
		if (!row) {
			return;
		}
		const badge = row.querySelector('[data-ibc-status-pill]');
		if (!badge) {
			return;
		}
		const statusDisplay = getStatusDisplay(statut);
		badge.className = statusDisplay.className;
		badge.textContent = statusDisplay.label;
	};

	if (elements.perPage) {
		const initial = parseInt(elements.perPage.value, 10);
		if (!Number.isNaN(initial) && initial > 0) {
			state.perPage = initial;
		}
	}

	const restFetch = (endpoint, options = {}) => {
		const url = `${IBCDashboard.restUrl}${endpoint}`;
		const headers = options.headers || {};

		if (state.token) {
			headers['X-IBC-Token'] = state.token;
		}

		return fetch(url, {
			credentials: 'same-origin',
			...options,
			headers,
		}).then(async (response) => {
			const payload = await ensureJson(response);
			if (!payload.success) {
				const message = payload.message || IBCDashboard.texts.loginError;
				throw new Error(message);
			}
			return payload.data;
		});
	};

	const toggleLoginModal = (visible) => {
		if (elements.loginModal) {
			elements.loginModal.hidden = !visible;
		}
	};

	const toggleEditModal = (visible) => {
		if (elements.editModal) {
			elements.editModal.hidden = !visible;
		}
	};

	const renderTable = (items) => {
		const tbody = elements.tableBody;
		if (!tbody) {
			return;
		}

		tbody.innerHTML = '';

		if (!items.length) {
			const row = document.createElement('tr');
			row.className = 'ibc-table-empty';
			row.innerHTML = `<td class="ibc-col-sticky" colspan="14">${getText('empty', 'Aucun résultat.')}</td>`;
			tbody.appendChild(row);
			updateScrollShadows();
			return;
		}

		items.forEach((item) => {
			const row = document.createElement('tr');
			row.dataset.id = item.row;
			row.dataset.ref = item.ref;
			row.dataset.notes = item.message || '';
			row.dataset.extra = JSON.stringify(item.extraFields || []);
			row.dataset.prenom = item.prenom || '';
			row.dataset.nom = item.nom || '';
			row.dataset.email = item.email || '';
			row.dataset.phone = item.phone || '';
			row.dataset.telephone = item.telephone || item.phone || '';
			row.dataset.level = item.level || '';
			row.dataset.status = item.statut || '';
			row.dataset.cinRecto = item.cinRectoUrl || '';
			row.dataset.cinVerso = item.cinVersoUrl || '';
			row.dataset.timestamp = item.timestamp || '';
			row.dataset.birthdate = item.dateNaissance || '';
			row.dataset.birthplace = item.lieuNaissance || '';

			const safeRecto = item.cinRectoUrl ? encodeURI(item.cinRectoUrl) : '';
			const safeVerso = item.cinVersoUrl ? encodeURI(item.cinVersoUrl) : '';
			const docLabels = {
				recto: getText('docRecto', 'Recto'),
				verso: getText('docVerso', 'Verso'),
				empty: getText('docMissing', 'Non fourni'),
			};

			const rectoChip = safeRecto
				? `<a href="${safeRecto}" class="ibc-doc-chip" target="_blank" rel="noopener noreferrer">${docLabels.recto}</a>`
				: `<span class="ibc-doc-chip is-muted">${docLabels.empty}</span>`;
			const versoChip = safeVerso
				? `<a href="${safeVerso}" class="ibc-doc-chip" target="_blank" rel="noopener noreferrer">${docLabels.verso}</a>`
				: `<span class="ibc-doc-chip is-muted">${docLabels.empty}</span>`;

			const messageContent = item.message
				? `<span class="ibc-message-chip" title="${escapeHtml(item.message)}">${escapeHtml(item.message).replace(/\n/g, '<br>')}</span>`
				: `<span class="ibc-message-chip is-empty">—</span>`;

			const statusConfirm = getText('statusConfirm', 'Confirmée');
			const statusCancel = getText('statusCancel', 'Annulée');
			const statusDisplay = getStatusDisplay(item.statut);
			const saveLabel = getText('save', 'Sauver');
			const deleteLabel = getText('delete', 'Supprimer');

			row.innerHTML = `
				<td class="ibc-col-sticky" data-label="${columnLabels.timestamp}">${formatDateTime(item.timestamp)}</td>
				<td data-label="${columnLabels.prenom}">${displayOrDash(item.prenom)}</td>
				<td data-label="${columnLabels.nom}">${displayOrDash(item.nom)}</td>
				<td data-label="${columnLabels.birthDate}">${formatDate(item.dateNaissance)}</td>
				<td data-label="${columnLabels.birthPlace}">${displayOrDash(item.lieuNaissance)}</td>
				<td data-label="${columnLabels.email}">${item.email ? `<a href="mailto:${encodeURIComponent(item.email)}">${escapeHtml(item.email)}</a>` : '—'}</td>
				<td data-label="${columnLabels.phone}">${
					item.telephone || item.phone
						? `<a href="tel:${cleanPhoneHref(item.telephone || item.phone)}">${escapeHtml(item.telephone || item.phone)}</a>`
						: '—'
				}</td>
				<td data-label="${columnLabels.level}">${displayOrDash(item.level)}</td>
				<td data-label="${columnLabels.cinRecto}"><div class="ibc-doc-chip-group">${rectoChip}</div></td>
				<td data-label="${columnLabels.cinVerso}"><div class="ibc-doc-chip-group">${versoChip}</div></td>
				<td data-label="${columnLabels.message}">${messageContent}</td>
				<td data-label="${columnLabels.reference}">
					<button type="button" class="ibc-ref-link" data-ibc-action="details">${escapeHtml(item.ref)}</button>
				</td>
				<td data-label="${columnLabels.status}">
					<div class="ibc-status-cell">
						<span class="${statusDisplay.className}" data-ibc-status-pill>${statusDisplay.label}</span>
						<select class="ibc-status-select" data-ibc-status>
							<option value="Confirme"${item.statut === 'Confirme' ? ' selected' : ''}>${statusConfirm}</option>
							<option value="Annule"${item.statut === 'Annule' ? ' selected' : ''}>${statusCancel}</option>
						</select>
					</div>
				</td>
				<td data-label="${columnLabels.actions}">
					<div class="ibc-row-actions">
						<button type="button" class="ibc-btn ibc-btn-primary" data-ibc-action="save">${saveLabel}</button>
						<button type="button" class="ibc-btn ibc-btn-danger" data-ibc-action="delete">${deleteLabel}</button>
					</div>
				</td>
			`;
			tbody.appendChild(row);
			updateStatusBadge(row, item.statut);
		});
		updateScrollShadows();
	};

	const updatePagination = (itemsCount, totalCount) => {
		if (!elements.pagination || !elements.pageIndicator) {
			return;
		}

		const total = typeof totalCount === 'number' ? totalCount : state.total;
		const totalPages = total > 0 ? Math.ceil(total / state.perPage) : null;
		const hasItems = total > 0 || itemsCount > 0;
		const isFirst = state.page === 1;
		const hasMore = totalPages ? state.page < totalPages : itemsCount === state.perPage;

		elements.pagination.hidden = !hasItems;

		if (elements.prev) {
			elements.prev.disabled = isFirst;
		}
		if (elements.next) {
			elements.next.disabled = !hasMore;
		}

		const pageLabel = getText('page', 'Page');
		elements.pageIndicator.textContent = totalPages
			? `${pageLabel} ${state.page} / ${totalPages}`
			: `${pageLabel} ${state.page}`;
	};

	const collectFilters = () => ({
		search: elements.search ? elements.search.value.trim() : '',
		niveau: elements.level ? elements.level.value.trim() : '',
		statut: elements.status ? elements.status.value.trim() : '',
		per_page: state.perPage,
		page: state.page,
	});

	const resetFilters = () => {
		if (searchTimer) {
			clearTimeout(searchTimer);
			searchTimer = null;
		}
		if (elements.search) {
			elements.search.value = '';
		}
		if (elements.level) {
			elements.level.value = '';
		}
		if (elements.status) {
			elements.status.value = '';
		}
		if (elements.perPage) {
			elements.perPage.value = '10';
		}
		state.perPage = 10;
		state.page = 1;
		state.total = 0;
		loadData();
	};

	const loadData = () => {
		if (state.loading) {
			return;
		}

		state.loading = true;
		setDashboardStatus(getText('loading', 'Chargement…'), 'loading');
		const filters = collectFilters();
		state.lastQuery = filters;

		restFetch(`/regs?${new URLSearchParams(filters).toString()}`)
			.then((data) => {
				const items = data.items || [];
				state.total = typeof data.total === 'number' ? data.total : 0;

				if (typeof data.limit === 'number' && data.limit !== state.perPage) {
					state.perPage = data.limit;
					if (elements.perPage) {
						elements.perPage.value = String(data.limit);
					}
				}

				if (typeof data.page === 'number') {
					state.page = data.page;
				}

				renderTable(items);
				updatePagination(items.length, state.total);
				const message =
					items.length === 0 && state.total === 0
						? getText('empty', 'Aucun résultat.')
						: getText('refreshed', 'Données mises à jour.');
				setDashboardStatus(message, 'success', 2500);
			})
			.catch((error) => {
				renderTable([]);
				updatePagination(0, 0);
				const message = error.message || getText('loginError', 'Mot de passe incorrect.');
				setDashboardStatus(message, 'error');
				showLoginError(message);
				sessionStorage.removeItem('ibcToken');
				state.token = '';
				state.total = 0;
				toggleLoginModal(true);
			})
			.finally(() => {
				state.loading = false;
			});
	};

	const showLoginError = (message) => {
		if (!elements.loginFeedback) {
			return;
		}
		elements.loginFeedback.hidden = false;
		elements.loginFeedback.textContent = message;
	};

	const showEditFeedback = (message, type = 'error') => {
		if (!elements.editFeedback) {
			return;
		}
		elements.editFeedback.hidden = false;
		elements.editFeedback.textContent = message;
		elements.editFeedback.classList.toggle('ibc-modal-feedback-error', type === 'error');
		elements.editFeedback.classList.toggle('ibc-modal-feedback-success', type === 'success');
	};

	const clearEditFeedback = () => {
		if (elements.editFeedback) {
			elements.editFeedback.hidden = true;
			elements.editFeedback.textContent = '';
			elements.editFeedback.className = 'ibc-modal-feedback';
		}
	};

	const openEditModal = (row) => {
		if (!elements.editForm) {
			return;
		}

		elements.editForm.reset();
		clearEditFeedback();

		elements.editForm.querySelector('[name="id"]').value = row.dataset.id || '';
		elements.editForm.querySelector('#ibc_edit_prenom').value = row.dataset.prenom || '';
		elements.editForm.querySelector('#ibc_edit_nom').value = row.dataset.nom || '';

		elements.editForm.querySelector('#ibc_edit_email').value = row.dataset.email || '';
		elements.editForm.querySelector('#ibc_edit_phone').value = row.dataset.telephone || row.dataset.phone || '';
		elements.editForm.querySelector('#ibc_edit_status').value = row.dataset.status || 'Confirme';
		const notesField = elements.editForm.querySelector('#ibc_edit_notes');
		if (notesField) {
			notesField.value = row.dataset.notes || '';
		}

		const extraContainer = elements.extraPreview;
		if (extraContainer) {
			let extras = [];
			try {
				extras = row.dataset.extra ? JSON.parse(row.dataset.extra) : [];
			} catch (error) {
				extras = [];
			}

			if (extras && extras.length) {
				extraContainer.hidden = false;
				const list = extras
					.filter((entry) => entry && entry.value)
					.map((entry) => {
						const label = (entry.label || entry.id || '').toString().replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
						let value = entry.display || entry.value;
						if (entry.type === 'file' && entry.value) {
							const safeUrl = encodeURI(entry.value);
							value = `<a href="${safeUrl}" target="_blank" rel="noopener noreferrer">${IBCDashboard.texts.download || 'Télécharger'}</a>`;
						} else {
							value = (value || '')
								.toString()
								.replace(/&/g, '&amp;')
								.replace(/</g, '&lt;')
								.replace(/>/g, '&gt;');
						}
						return `<li><strong>${label}</strong> : ${value}</li>`;
					})
					.join('');

				extraContainer.innerHTML = `
					<h4>${IBCDashboard.texts.extraTitle || 'Informations complémentaires'}</h4>
					<ul>${list}</ul>
				`;
			} else {
				extraContainer.hidden = true;
				extraContainer.innerHTML = '';
			}
		}

		const docsContainer = elements.docsPreview;
		if (docsContainer) {
			const rectoLink = elements.docRecto || docsContainer.querySelector('[data-ibc-doc="recto"]');
			const versoLink = elements.docVerso || docsContainer.querySelector('[data-ibc-doc="verso"]');
			const emptyBadge = elements.docEmpty || docsContainer.querySelector('[data-ibc-doc-empty]');

			const hasRecto = Boolean(row.dataset.cinRecto);
			const hasVerso = Boolean(row.dataset.cinVerso);
			const hasDocs = hasRecto || hasVerso;

			const rectoUrl = hasRecto ? encodeURI(row.dataset.cinRecto) : '#';
			const versoUrl = hasVerso ? encodeURI(row.dataset.cinVerso) : '#';

			docsContainer.hidden = !hasDocs && !emptyBadge;

			if (rectoLink) {
				rectoLink.href = rectoUrl;
				rectoLink.hidden = !hasRecto;
			}

			if (versoLink) {
				versoLink.href = versoUrl;
				versoLink.hidden = !hasVerso;
			}

			if (emptyBadge) {
				emptyBadge.hidden = hasDocs;
			}

			docsContainer.classList.toggle('is-empty', !hasDocs);
		}

		toggleEditModal(true);
	};

	const submitLogin = () => {
		const password = elements.loginPassword.value;
		if (!password) {
			showLoginError(IBCDashboard.texts.loginError);
			return;
		}

		elements.loginFeedback.hidden = true;

		restFetch('/login', {
			method: 'POST',
			body: new URLSearchParams({ password }),
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
			},
		})
			.then((data) => {
				state.token = data.token;
				sessionStorage.setItem('ibcToken', data.token);
				toggleLoginModal(false);
				loadData();
			})
			.catch((error) => {
				const message = error.message || getText('loginError', 'Mot de passe incorrect.');
				showLoginError(message);
				setDashboardStatus(message, 'error', 4000);
			});
	};

	const submitEdit = (event) => {
		event.preventDefault();
		clearEditFeedback();

		const formData = new FormData(elements.editForm);
		const id = formData.get('id');
		if (!id) {
			showEditFeedback('ID manquant.');
			return;
		}

		const fields = {};
		formData.forEach((value, key) => {
			if (key !== 'id' && value !== '') {
				fields[key] = value;
			}
		});

		restFetch('/reg/update', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
			},
			body: JSON.stringify({
				id,
				fields,
			}),
		})
			.then(() => {
				const feedbackMessage = getText('saveSuccess', 'Inscription mise à jour.');
				showEditFeedback(feedbackMessage, 'success');
				setDashboardStatus(feedbackMessage, 'success', 2500);
				loadData();
				setTimeout(() => toggleEditModal(false), 800);
			})
			.catch((error) => {
				showEditFeedback(error.message || IBCDashboard.texts.loginError);
			});
	};

	const quickSave = (row) => {
		const id = row.dataset.id;
		const statusSelect = row.querySelector('[data-ibc-status]');

		if (!id || !statusSelect) {
			setDashboardStatus(getText('saveError', 'Impossible d’enregistrer les modifications.'), 'error', 3000);
			return;
		}

		const statut = statusSelect.value || 'Confirme';
		setDashboardStatus(getText('saving', 'Enregistrement…'), 'loading');

		restFetch('/reg/update', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
			},
			body: JSON.stringify({
				id,
				fields: { statut },
			}),
		})
			.then(() => {
				setDashboardStatus(getText('saveStatus', 'Statut mis à jour.'), 'success', 2500);
				row.dataset.status = statut;
				updateStatusBadge(row, statut);
				loadData();
			})
			.catch((error) => {
				setDashboardStatus(error.message || getText('saveError', 'Impossible d’enregistrer les modifications.'), 'error', 4000);
			});
	};

	const deleteRow = (row) => {
		const reference = row.dataset.ref;
		if (!reference) {
			return;
		}
		if (!window.confirm(getText('deleteConfirm', 'Confirmer l’annulation de cette inscription ?'))) {
			return;
		}

		setDashboardStatus(getText('deleting', 'Suppression en cours…'), 'loading');

		restFetch('/reg/delete', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
			},
			body: JSON.stringify({ ref: reference }),
		})
			.then(() => {
				setDashboardStatus(getText('deleteDone', 'Inscription annulée.'), 'success', 2500);
				loadData();
			})
			.catch((error) => {
				setDashboardStatus(error.message || getText('deleteError', 'Impossible de supprimer l’inscription.'), 'error', 4000);
			});
	};

	const exportCsv = () => {
		const rows = elements.tableBody ? elements.tableBody.querySelectorAll('tr') : [];
		if (!rows.length || rows[0].classList.contains('ibc-table-empty')) {
			return;
		}

		const headers = [
			'Date d\'inscription',
			'Prénom',
			'Nom',
			'Date de naissance',
			'Lieu de naissance',
			'Email',
			'Téléphone',
			'Niveau',
			'CIN Recto',
			'CIN Verso',
			'Message',
			'Référence',
			'Statut',
		];
		const lines = [headers.map((header) => sanitizeCsvValue(header)).join(',')];

		rows.forEach((row) => {
			if (row.classList.contains('ibc-table-empty')) {
				return;
			}

			const ref = row.dataset.ref || '';
			const prenom = row.dataset.prenom || '';
			const nom = row.dataset.nom || '';
			const birthdate = row.dataset.birthdate || '';
			const birthPlace = row.dataset.birthplace || '';
			const email = row.dataset.email || '';
			const phone = row.dataset.telephone || row.dataset.phone || '';
			const level = row.dataset.level || '';
			const recto = row.dataset.cinRecto || '';
			const verso = row.dataset.cinVerso || '';
			const message = (row.dataset.notes || '').replace(/\s+/g, ' ').trim();
			const statusSelect = row.querySelector('[data-ibc-status]');
			const statut = statusSelect ? statusSelect.value : row.dataset.status || '';
			const timestamp = formatDateTime(row.dataset.timestamp || '');
			const birthFormatted = formatDate(birthdate);

			lines.push(
				[
					timestamp,
					prenom,
					nom,
					birthFormatted,
					birthPlace,
					email,
					phone,
					level,
					recto,
					verso,
					message,
					ref,
					statut,
				]
					.map((value) => sanitizeCsvValue(value))
					.join(',')
			);
		});

		const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
		const url = URL.createObjectURL(blob);
		const link = document.createElement('a');
		link.href = url;
		link.download = `ibc-registrations-${new Date().toISOString().slice(0, 10)}.csv`;
		document.body.appendChild(link);
		link.click();
		document.body.removeChild(link);
		URL.revokeObjectURL(url);
		setDashboardStatus(getText('exported', 'Export CSV généré.'), 'success', 2500);
	};

	const logout = () => {
		sessionStorage.removeItem('ibcToken');
		state.token = '';
		state.page = 1;
		state.perPage = 10;
		state.total = 0;
		state.lastQuery = {};
		if (elements.loginPassword) {
			elements.loginPassword.value = '';
		}
		if (elements.search) {
			elements.search.value = '';
		}
		if (elements.level) {
			elements.level.value = '';
		}
		if (elements.status) {
			elements.status.value = '';
		}
		if (elements.loginFeedback) {
			elements.loginFeedback.hidden = true;
			elements.loginFeedback.textContent = '';
			elements.loginFeedback.className = 'ibc-modal-feedback';
		}
		if (elements.perPage) {
			elements.perPage.value = '10';
		}
		renderTable([]);
		updatePagination(0, 0);
		setDashboardStatus(getText('logoutDone', 'Déconnexion effectuée.'), 'success', 3000);
		toggleLoginModal(true);
	};

	/* Event bindings */
	if (tableWrap) {
		tableWrap.addEventListener(
			'scroll',
			() => {
				scheduleShadowUpdate();
			},
			{ passive: true }
		);
	}

	window.addEventListener('resize', scheduleShadowUpdate);
	if (elements.loginButton) {
		elements.loginButton.addEventListener('click', submitLogin);
	}

	if (elements.loginPassword) {
		elements.loginPassword.addEventListener('keydown', (event) => {
			if (event.key === 'Enter') {
				event.preventDefault();
				submitLogin();
			}
		});
	}

	if (elements.refresh) {
		elements.refresh.addEventListener('click', () => {
			state.page = 1;
			loadData();
		});
	}

	if (elements.reset) {
		elements.reset.addEventListener('click', () => {
			resetFilters();
		});
	}

	if (elements.export) {
		elements.export.addEventListener('click', exportCsv);
	}

	if (elements.logout) {
		elements.logout.addEventListener('click', (event) => {
			event.preventDefault();
			logout();
		});
	}

	if (elements.prev) {
		elements.prev.addEventListener('click', () => {
			if (state.page > 1) {
				state.page -= 1;
				loadData();
			}
		});
	}

	if (elements.next) {
		elements.next.addEventListener('click', () => {
			state.page += 1;
			loadData();
		});
	}

	if (elements.search) {
		elements.search.addEventListener('input', () => {
			if (searchTimer) {
				clearTimeout(searchTimer);
			}
			searchTimer = setTimeout(() => {
				state.page = 1;
				const value = elements.search.value.trim();
				if (value.length > 0 && value.length < 2) {
					return;
				}
				loadData();
			}, 300);
		});

		elements.search.addEventListener('keydown', (event) => {
			if (event.key === 'Enter') {
				event.preventDefault();
				if (searchTimer) {
					clearTimeout(searchTimer);
				}
				state.page = 1;
				loadData();
			}
		});
	}

	if (elements.level) {
		elements.level.addEventListener('change', () => {
			state.page = 1;
			loadData();
		});
	}

	if (elements.status) {
		elements.status.addEventListener('change', () => {
			state.page = 1;
			loadData();
		});
	}

	if (elements.perPage) {
		elements.perPage.addEventListener('change', () => {
			state.perPage = parseInt(elements.perPage.value, 10) || 10;
			state.page = 1;
			loadData();
		});
	}

	if (elements.tableBody) {
		elements.tableBody.addEventListener('click', (event) => {
			const action = event.target.closest('[data-ibc-action]');
			if (!action) {
				return;
			}
			const row = event.target.closest('tr');
			if (!row) {
				return;
			}

			const actionType = action.dataset.ibcAction;
			if (actionType === 'details') {
				openEditModal(row);
				return;
			}
			if (actionType === 'save') {
				quickSave(row);
				return;
			}
			if (actionType === 'delete') {
				deleteRow(row);
			}
		});
	}

	if (elements.editForm) {
		elements.editForm.addEventListener('submit', submitEdit);
	}

	if (elements.editCancel) {
		elements.editCancel.addEventListener('click', () => toggleEditModal(false));
	}

	if (elements.editModal) {
		elements.editModal.addEventListener('click', (event) => {
			if (event.target === elements.editModal) {
				toggleEditModal(false);
			}
		});
	}

	setDashboardStatus(state.token ? getText('ready', 'Prêt') : getText('loginRequired', 'Connexion requise.'), 'ready');
	updateScrollShadows();

	if (state.token) {
		loadData();
	} else {
		toggleLoginModal(true);
	}
})();
