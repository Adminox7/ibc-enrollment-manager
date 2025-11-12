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
		perPage: 50,
		loading: false,
		lastQuery: {},
	};

	const selectors = {
		search: '#ibc_filter_search',
		level: '#ibc_filter_level',
		status: '#ibc_filter_status',
		perPage: '#ibc_filter_perpage',
		tableBody: '[data-ibc-table-body]',
		pagination: '[data-ibc-pagination]',
		pageIndicator: '[data-ibc-page-indicator]',
		loginModal: '[data-ibc-login]',
		loginPassword: '#ibc_admin_password',
		loginButton: '[data-ibc-login-submit]',
		loginFeedback: '[data-ibc-login] .ibc-modal-feedback',
		editModal: '[data-ibc-edit]',
		editForm: '[data-ibc-edit-form]',
		editFeedback: '[data-ibc-edit] .ibc-modal-feedback',
		export: '[data-ibc-export]',
		prev: '[data-ibc-prev]',
		next: '[data-ibc-next]',
	};

	const elements = {};
	Object.keys(selectors).forEach((key) => {
		elements[key] = root.querySelector(selectors[key]) || document.querySelector(selectors[key]);
	});

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
			const payload = await response.json();
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
			row.innerHTML = `<td colspan="6">${IBCDashboard.texts.empty || 'Aucun résultat.'}</td>`;
			tbody.appendChild(row);
			return;
		}

		items.forEach((item) => {
			const row = document.createElement('tr');
			row.dataset.id = item.row;
			row.dataset.ref = item.ref;
			row.dataset.notes = item.message || '';
			row.dataset.prenom = item.prenom || '';
			row.dataset.nom = item.nom || '';
			row.dataset.email = item.email || '';
			row.dataset.phone = item.phone || '';
			row.dataset.level = item.level || '';
			row.dataset.status = item.statut || '';
			row.innerHTML = `
				<td>
					<strong>${item.ref}</strong><br>
					<small>${item.timestamp}</small>
				</td>
				<td>
					${item.fullName || `${item.prenom} ${item.nom}`}<br>
					<small>${item.dateNaissance || ''}</small>
				</td>
				<td>
					<a href="mailto:${item.email}" class="ibc-link">${item.email}</a><br>
					<a href="tel:${item.phone}" class="ibc-link">${item.phone}</a>
				</td>
				<td>${item.level || '-'}</td>
				<td>
					<span class="ibc-badge ${item.statut === 'Confirme' ? 'ibc-badge-success' : 'ibc-badge-warning'}">${item.statut}</span>
				</td>
				<td>
					<div class="ibc-row-actions">
						<button type="button" class="ibc-link" data-ibc-action="edit">${IBCDashboard.texts.edit || 'Modifier'}</button>
						<button type="button" class="ibc-link" data-ibc-action="delete">${IBCDashboard.texts.delete || 'Annuler'}</button>
					</div>
				</td>
			`;
			tbody.appendChild(row);
		});
	};

	const updatePagination = (itemsCount) => {
		if (!elements.pagination || !elements.pageIndicator) {
			return;
		}

		const hasMore = itemsCount === state.perPage;
		const isFirst = state.page === 1;

		elements.pagination.hidden = itemsCount === 0 && state.page === 1;

		if (elements.prev) {
			elements.prev.disabled = isFirst;
		}
		if (elements.next) {
			elements.next.disabled = !hasMore;
		}

		elements.pageIndicator.textContent = `${IBCDashboard.texts.page || 'Page'} ${state.page}`;
	};

	const collectFilters = () => ({
		search: elements.search ? elements.search.value.trim() : '',
		niveau: elements.level ? elements.level.value.trim() : '',
		statut: elements.status ? elements.status.value.trim() : '',
		per_page: state.perPage,
		page: state.page,
	});

	const loadData = () => {
		if (state.loading) {
			return;
		}

		state.loading = true;
		const filters = collectFilters();
		state.lastQuery = filters;

		restFetch(`/regs?${new URLSearchParams(filters).toString()}`)
			.then((data) => {
				renderTable(data.items || []);
				updatePagination((data.items || []).length);
			})
			.catch((error) => {
				renderTable([]);
				updatePagination(0);
				showLoginError(error.message);
				sessionStorage.removeItem('ibcToken');
				state.token = '';
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
		elements.editForm.querySelector('#ibc_edit_phone').value = row.dataset.phone || '';
		elements.editForm.querySelector('#ibc_edit_status').value = row.dataset.status || 'Confirme';
		const notesField = elements.editForm.querySelector('#ibc_edit_notes');
		if (notesField) {
			notesField.value = row.dataset.notes || '';
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
				showLoginError(error.message || IBCDashboard.texts.loginError);
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
				showEditFeedback(IBCDashboard.texts.saveSuccess, 'success');
				loadData();
				setTimeout(() => toggleEditModal(false), 800);
			})
			.catch((error) => {
				showEditFeedback(error.message || IBCDashboard.texts.loginError);
			});
	};

	const deleteRow = (row) => {
		const reference = row.dataset.ref;
		if (!reference) {
			return;
		}
		if (!window.confirm(IBCDashboard.texts.deleteConfirm)) {
			return;
		}

		restFetch('/reg/delete', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
			},
			body: JSON.stringify({ ref: reference }),
		})
			.then(() => {
				loadData();
				alert(IBCDashboard.texts.deleteDone);
			})
			.catch((error) => {
				alert(error.message);
			});
	};

	const exportCsv = () => {
		const rows = root.querySelectorAll('[data-ibc-table-body] tr');
		if (!rows.length || rows[0].classList.contains('ibc-table-empty')) {
			return;
		}

		const headers = ['Référence', 'Nom', 'Email', 'Téléphone', 'Niveau', 'Statut', 'Date'];
		const lines = [headers.join(',')];

		rows.forEach((row) => {
			const ref = row.dataset.ref || '';
			const fullName = row.querySelector('td:nth-child(2)').textContent.replace(/\s+/g, ' ').trim();
			const email = (row.querySelector('a[href^="mailto:"]') || {}).textContent || '';
			const phone = (row.querySelector('a[href^="tel:"]') || {}).textContent || '';
			const level = row.querySelector('td:nth-child(4)').textContent.trim();
			const status = row.querySelector('.ibc-badge').textContent.trim();
			const date = row.querySelector('td:first-child small').textContent.trim();

			lines.push(
				[
					ref,
					fullName,
					email,
					phone,
					level,
					status,
					date,
				]
					.map((value) => `"${value.replace(/"/g, '""')}"`)
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
	};

	/* Event bindings */
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

	if (elements.export) {
		elements.export.addEventListener('click', exportCsv);
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

	['search', 'level', 'status'].forEach((key) => {
		const el = elements[key];
		if (!el) {
			return;
		}
		el.addEventListener('input', () => {
			state.page = 1;
			if (key === 'search') {
				if (el.value.length < 2 && el.value.length !== 0) {
					return;
				}
			}
			loadData();
		});
	});

	if (elements.perPage) {
		elements.perPage.addEventListener('change', () => {
			state.perPage = parseInt(elements.perPage.value, 10) || 50;
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

			if (action.dataset.ibcAction === 'edit') {
				openEditModal(row);
			}
			if (action.dataset.ibcAction === 'delete') {
				deleteRow(row);
			}
		});
	}

	if (elements.editForm) {
		elements.editForm.addEventListener('submit', submitEdit);
	}

	if (elements.editModal) {
		elements.editModal.addEventListener('click', (event) => {
			if (event.target === elements.editModal) {
				toggleEditModal(false);
			}
		});
	}

	if (state.token) {
		loadData();
	} else {
		toggleLoginModal(true);
	}
})();
