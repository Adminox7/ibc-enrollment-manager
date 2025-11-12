/* global IBCBuilder */
(function () {
	'use strict';

	const root = document.querySelector('[data-ibc-builder]');
	if (!root || typeof IBCBuilder === 'undefined') {
		return;
	}

	const listEl = root.querySelector('[data-builder-list]');
	const editorEl = root.querySelector('[data-builder-editor]');
	const previewEl = root.querySelector('[data-builder-preview]');
	const addButton = root.querySelector('[data-builder-add]');
	const schemaInput = document.getElementById('ibc_form_schema');

	const types = IBCBuilder.types || {};
	const colors = IBCBuilder.colors || {};
	const strings = IBCBuilder.i18n || {};

	let fields = (IBCBuilder.schema || []).map((field, index) => ({
		...field,
		order: field.order || (index + 1) * 10,
	}));

	let activeId = fields.length ? fields[0].id : null;
	let dragSourceId = null;

	function getFieldById(id) {
		return fields.find((field) => field.id === id);
	}

	function normalizeId(value) {
		const cleaned = (value || '')
			.toString()
			.toLowerCase()
			.replace(/[^a-z0-9_\-]/g, '_')
			.replace(/_{2,}/g, '_')
			.replace(/^-+|-+$/g, '');
		return cleaned || `champ_${Date.now()}`;
	}

	function generateId(base) {
		let id = normalizeId(base);
		let suffix = 2;

		while (fields.some((field) => field.id === id)) {
			id = `${normalizeId(base)}_${suffix}`;
			suffix += 1;
		}
		return id;
	}

	function sortFields() {
		fields.sort((a, b) => (a.order || 0) - (b.order || 0));
		fields = fields.map((field, index) => ({
			...field,
			order: (index + 1) * 10,
		}));
	}

	function persist() {
		if (schemaInput) {
			schemaInput.value = JSON.stringify(fields);
		}
	}

	function setActive(id) {
		const exists = fields.some((field) => field.id === id);
		activeId = exists ? id : (fields[0] && fields[0].id) || null;
		render();
	}

	function applyUpdates(id, updates) {
		fields = fields.map((field) => {
			if (field.id !== id) {
				return field;
			}
			return {
				...field,
				...updates,
			};
		});
		persist();
		renderPreview();
	}

	function fieldToChoicesText(field) {
		if (!field.choices || !field.choices.length) {
			return '';
		}

		return field.choices
			.map((choice) => {
				const value = choice.value || '';
				const label = choice.label || value;
				return value === label ? value : `${value}|${label}`;
			})
			.join('\n');
	}

	function parseChoices(text) {
		if (!text.trim()) {
			return [];
		}

		return text
			.split('\n')
			.map((line) => line.trim())
			.filter(Boolean)
			.map((line) => {
				const [value, label] = line.split('|').map((part) => part.trim());
				return {
					value,
					label: label || value,
				};
			});
	}

	function handleDelete(id) {
		const field = getFieldById(id);
		if (!field) {
			return;
		}
		if (field.locked) {
			window.alert(strings.lockedField || 'Ce champ est protégé.');
			return;
		}
		if (!window.confirm(strings.confirmDelete || 'Supprimer ce champ du formulaire ?')) {
			return;
		}
		fields = fields.filter((item) => item.id !== id);
		sortFields();
		persist();
		setActive(fields[0] ? fields[0].id : null);
	}

	function handleDuplicate(id) {
		const field = getFieldById(id);
		if (!field) {
			return;
		}
		const clone = JSON.parse(JSON.stringify(field));
		clone.id = generateId(`${field.id}_copie`);
		clone.label = `${field.label || ''} (${strings.copy || 'Copie'})`;
		clone.locked = false;
		clone.order = (fields.length + 1) * 10;

		fields.push(clone);
		sortFields();
		persist();
		setActive(clone.id);
	}

	function changeType(field, newType) {
		if (!types[newType]) {
			return;
		}

		const updates = { type: newType };

		if (newType === 'select' && (!field.choices || !field.choices.length)) {
			updates.choices = [
				{ value: 'Option 1', label: 'Option 1' },
				{ value: 'Option 2', label: 'Option 2' },
			];
		}

		if (newType !== 'select') {
			updates.choices = [];
		}

		if (newType === 'file' && !field.accept) {
			updates.accept = '.jpg,.jpeg,.png,.pdf';
		}

		if (newType !== 'file') {
			updates.accept = '';
		}

		applyUpdates(field.id, updates);
	}

	function renderList() {
		if (!listEl) {
			return;
		}
		listEl.innerHTML = '';

		fields.forEach((field) => {
			const item = document.createElement('li');
			item.className = 'ibc-builder__list-item';
			item.draggable = true;
			item.dataset.fieldId = field.id;

			if (field.id === activeId) {
				item.classList.add('is-active');
			}
			if (!field.active) {
				item.classList.add('is-muted');
			}

			const label = document.createElement('div');
			label.className = 'ibc-builder__list-label';
			label.textContent = field.label || field.id;

			const meta = document.createElement('div');
			meta.className = 'ibc-builder__list-meta';
			const typeLabel = types[field.type] ? types[field.type].label : field.type;
			meta.textContent = `${typeLabel || ''} · ${field.width === 'half' ? (strings.widthHalf || 'Demi-largeur') : (strings.widthFull || 'Largeur complète')}`;

			const actions = document.createElement('div');
			actions.className = 'ibc-builder__list-actions';

			const duplicateBtn = document.createElement('button');
			duplicateBtn.type = 'button';
			duplicateBtn.className = 'button button-small';
			duplicateBtn.textContent = strings.duplicateField || 'Dupliquer';
			duplicateBtn.addEventListener('click', (event) => {
				event.stopPropagation();
				handleDuplicate(field.id);
			});

			const deleteBtn = document.createElement('button');
			deleteBtn.type = 'button';
			deleteBtn.className = 'button button-small button-link-delete';
			deleteBtn.textContent = strings.deleteField || 'Supprimer';
			deleteBtn.disabled = !!field.locked;
			deleteBtn.addEventListener('click', (event) => {
				event.stopPropagation();
				handleDelete(field.id);
			});

			actions.appendChild(duplicateBtn);
			actions.appendChild(deleteBtn);

			item.appendChild(label);
			item.appendChild(meta);
			item.appendChild(actions);

			item.addEventListener('click', () => setActive(field.id));

			item.addEventListener('dragstart', (event) => {
				dragSourceId = field.id;
				event.dataTransfer.effectAllowed = 'move';
				event.dataTransfer.setData('text/plain', field.id);
				item.classList.add('is-dragging');
			});

			item.addEventListener('dragend', () => {
				item.classList.remove('is-dragging');
				dragSourceId = null;
			});

			item.addEventListener('dragover', (event) => {
				event.preventDefault();
				item.classList.add('is-drag-over');
			});

			item.addEventListener('dragleave', () => {
				item.classList.remove('is-drag-over');
			});

			item.addEventListener('drop', (event) => {
				event.preventDefault();
				item.classList.remove('is-drag-over');
				const sourceId = dragSourceId || event.dataTransfer.getData('text/plain');
				if (!sourceId || sourceId === field.id) {
					return;
				}
				const sourceIndex = fields.findIndex((f) => f.id === sourceId);
				const targetIndex = fields.findIndex((f) => f.id === field.id);
				if (sourceIndex === -1 || targetIndex === -1) {
					return;
				}
				const [moved] = fields.splice(sourceIndex, 1);
				fields.splice(targetIndex, 0, moved);
				sortFields();
				persist();
				renderList();
				renderPreview();
			});

			listEl.appendChild(item);
		});
	}

	function renderEditor() {
		if (!editorEl) {
			return;
		}
		const field = activeId ? getFieldById(activeId) : null;
		if (!field) {
			editorEl.innerHTML = `<p class="ibc-builder__placeholder">${strings.selectField || 'Sélectionnez un champ.'}</p>`;
			return;
		}

		const supportsPlaceholder = types[field.type] && types[field.type].supports_placeholder;
		const supportsChoices = types[field.type] && types[field.type].supports_choices;
		const supportsAccept = types[field.type] && types[field.type].supports_accept;

		editorEl.innerHTML = '';

		const form = document.createElement('div');
		form.className = 'ibc-builder-editor-form';

		const labelField = document.createElement('label');
		labelField.className = 'ibc-builder-editor-field';
		labelField.innerHTML = `<span>${strings.label || 'Libellé'}</span>`;
		const labelInput = document.createElement('input');
		labelInput.type = 'text';
		labelInput.value = field.label || '';
		labelInput.addEventListener('input', () => {
			applyUpdates(field.id, { label: labelInput.value });
			renderList();
		});
		labelField.appendChild(labelInput);
		form.appendChild(labelField);

		if (supportsPlaceholder) {
			const placeholderField = document.createElement('label');
			placeholderField.className = 'ibc-builder-editor-field';
			placeholderField.innerHTML = `<span>${strings.placeholder || 'Placeholder'}</span>`;
			const placeholderInput = document.createElement('input');
			placeholderInput.type = 'text';
			placeholderInput.value = field.placeholder || '';
			placeholderInput.addEventListener('input', () => {
				applyUpdates(field.id, { placeholder: placeholderInput.value });
			});
			placeholderField.appendChild(placeholderInput);
			form.appendChild(placeholderField);
		}

		const typeField = document.createElement('label');
		typeField.className = 'ibc-builder-editor-field';
		typeField.innerHTML = `<span>${strings.type || 'Type'}</span>`;
		const typeSelect = document.createElement('select');
		Object.entries(types).forEach(([key, meta]) => {
			const option = document.createElement('option');
			option.value = key;
			option.textContent = meta.label || key;
			if (key === field.type) {
				option.selected = true;
			}
			typeSelect.appendChild(option);
		});
		if (field.locked) {
			typeSelect.disabled = true;
		}
		typeSelect.addEventListener('change', () => {
			changeType(field, typeSelect.value);
			renderEditor();
		});
		typeField.appendChild(typeSelect);
		if (field.locked) {
			const lockNote = document.createElement('p');
			lockNote.className = 'description';
			lockNote.textContent = strings.lockedField || 'Ce champ est protégé.';
			typeField.appendChild(lockNote);
		}
		form.appendChild(typeField);

		const widthField = document.createElement('label');
		widthField.className = 'ibc-builder-editor-field';
		widthField.innerHTML = `<span>${strings.width || 'Largeur'}</span>`;
		const widthSelect = document.createElement('select');
		const widthFull = document.createElement('option');
		widthFull.value = 'full';
		widthFull.textContent = strings.widthFull || 'Largeur complète';
		const widthHalf = document.createElement('option');
		widthHalf.value = 'half';
		widthHalf.textContent = strings.widthHalf || 'Demi-largeur';
		if (field.width === 'half') {
			widthHalf.selected = true;
		} else {
			widthFull.selected = true;
		}
		widthSelect.appendChild(widthFull);
		widthSelect.appendChild(widthHalf);
		widthSelect.addEventListener('change', () => {
			applyUpdates(field.id, { width: widthSelect.value });
			renderList();
		});
		widthField.appendChild(widthSelect);
		form.appendChild(widthField);

		const requiredField = document.createElement('label');
		requiredField.className = 'ibc-builder-editor-inline';
		const requiredInput = document.createElement('input');
		requiredInput.type = 'checkbox';
		requiredInput.checked = !!field.required;
		requiredInput.disabled = false;
		requiredInput.addEventListener('change', () => {
			applyUpdates(field.id, { required: requiredInput.checked });
		});
		requiredField.appendChild(requiredInput);
		requiredField.appendChild(document.createTextNode(strings.fieldRequired || 'Champ obligatoire'));
		form.appendChild(requiredField);

		const activeField = document.createElement('label');
		activeField.className = 'ibc-builder-editor-inline';
		const activeInput = document.createElement('input');
		activeInput.type = 'checkbox';
		activeInput.checked = field.active !== false;
		activeInput.addEventListener('change', () => {
			applyUpdates(field.id, { active: activeInput.checked });
			renderList();
		});
		activeField.appendChild(activeInput);
		activeField.appendChild(document.createTextNode(strings.fieldOptional || 'Champ actif'));
		form.appendChild(activeField);

		const helpField = document.createElement('label');
		helpField.className = 'ibc-builder-editor-field';
		helpField.innerHTML = `<span>${strings.helpText || 'Texte d’aide'}</span>`;
		const helpInput = document.createElement('input');
		helpInput.type = 'text';
		helpInput.value = field.help || '';
		helpInput.addEventListener('input', () => {
			applyUpdates(field.id, { help: helpInput.value });
		});
		helpField.appendChild(helpInput);
		form.appendChild(helpField);

		if (supportsChoices) {
			const choicesField = document.createElement('label');
			choicesField.className = 'ibc-builder-editor-field';
			choicesField.innerHTML = `<span>${strings.choices || 'Options'}</span>`;
			const choicesTextarea = document.createElement('textarea');
			choicesTextarea.rows = 5;
			choicesTextarea.value = fieldToChoicesText(field);
			choicesTextarea.placeholder = strings.choicesPlaceholder || 'Option 1\nOption 2\n';
			choicesTextarea.addEventListener('input', () => {
				applyUpdates(field.id, { choices: parseChoices(choicesTextarea.value) });
			});
			choicesField.appendChild(choicesTextarea);
			form.appendChild(choicesField);
		}

		if (supportsAccept) {
			const acceptField = document.createElement('label');
			acceptField.className = 'ibc-builder-editor-field';
			acceptField.innerHTML = `<span>${strings.fileFormats || 'Formats acceptés'}</span>`;
			const acceptInput = document.createElement('input');
			acceptInput.type = 'text';
			acceptInput.value = field.accept || '.jpg,.jpeg,.png,.pdf';
			acceptInput.addEventListener('input', () => {
				applyUpdates(field.id, { accept: acceptInput.value });
			});
			acceptField.appendChild(acceptInput);
			form.appendChild(acceptField);
		}

		editorEl.appendChild(form);
	}

	function createPreviewField(field) {
		if (!field.active) {
			return null;
		}
		const wrapper = document.createElement('div');
		wrapper.className = 'ibc-preview-field';
		if (field.width === 'half') {
			wrapper.classList.add('is-half');
		}

		const label = document.createElement('label');
		label.textContent = field.label || field.id;
		if (field.required) {
			const requiredSpan = document.createElement('span');
			requiredSpan.className = 'ibc-preview-required';
			requiredSpan.textContent = '*';
			label.appendChild(requiredSpan);
		}

		let input;
		switch (field.type) {
			case 'textarea':
				input = document.createElement('textarea');
				input.rows = 3;
				break;
			case 'select':
				input = document.createElement('select');
				const blank = document.createElement('option');
				blank.value = '';
				blank.textContent = field.placeholder || strings.selectPlaceholder || 'Sélectionnez...';
				input.appendChild(blank);
				(field.choices || []).forEach((choice) => {
					const option = document.createElement('option');
					option.value = choice.value || '';
					option.textContent = choice.label || choice.value || '';
					input.appendChild(option);
				});
				break;
			default:
				input = document.createElement('input');
				input.type = field.type === 'file' ? 'file' : (field.type || 'text');
		}

		input.disabled = true;
		input.placeholder = field.placeholder || '';

		wrapper.appendChild(label);
		wrapper.appendChild(input);

		if (field.help) {
			const help = document.createElement('p');
			help.className = 'ibc-preview-help';
			help.textContent = field.help;
			wrapper.appendChild(help);
		}

		return wrapper;
	}

	function renderPreview() {
		if (!previewEl) {
			return;
		}
		previewEl.innerHTML = '';

		const container = document.createElement('div');
		container.className = 'ibc-preview-form';
		container.style.setProperty('--ibc-preview-border', colors.border || '#e2e8f0');
		container.style.setProperty('--ibc-preview-button-bg', colors.button || '#e94162');
		container.style.setProperty('--ibc-preview-button-text', colors.button_text || '#ffffff');
		container.style.setProperty('--ibc-preview-success-bg', colors.success_bg || '#dcfce7');
		container.style.setProperty('--ibc-preview-error-bg', colors.error_bg || '#fee2e2');

		const grid = document.createElement('div');
		grid.className = 'ibc-preview-grid';

		fields.forEach((field) => {
			const previewField = createPreviewField(field);
			if (previewField) {
				grid.appendChild(previewField);
			}
		});

		const submit = document.createElement('button');
		submit.type = 'button';
		submit.className = 'button button-primary';
		submit.textContent = strings.previewSubmit || 'Envoyer';

		container.appendChild(grid);
		container.appendChild(submit);

		previewEl.appendChild(container);
	}

	function render() {
		sortFields();
		renderList();
		renderEditor();
		renderPreview();
		persist();
	}

	function createField(type = 'text') {
		return {
			id: generateId('champ'),
			label: strings.newFieldLabel || 'Nouveau champ',
			placeholder: '',
			type,
			width: 'full',
			required: false,
			active: true,
			help: '',
			choices: type === 'select' ? [
				{ value: 'Option 1', label: 'Option 1' },
			] : [],
			accept: type === 'file' ? '.jpg,.jpeg,.png,.pdf' : '',
			order: (fields.length + 1) * 10,
			locked: false,
			map: '',
			default: '',
		};
	}

	if (addButton) {
		addButton.addEventListener('click', () => {
			const newField = createField('text');
			fields.push(newField);
			sortFields();
			persist();
			setActive(newField.id);
		});
	}

	render();
})();
