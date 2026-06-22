(function () {
	'use strict';

	var state = {
		initialized: false,
		formState: {},
	};

	function getConfigs() {
		return window.KOFieldLockConfig || {};
	}

	function getConfig(formId) {
		return getConfigs()[String(formId)] || getConfigs()[formId] || null;
	}

	function getFieldSelector(formId, fieldId) {
		return '#field_' + formId + '_' + fieldId;
	}

	function getFieldElement(formId, fieldId) {
		return document.querySelector(getFieldSelector(formId, fieldId));
	}

	function getRadioSelector(formId, fieldId) {
		return getFieldSelector(formId, fieldId) + ' input[type="radio"]';
	}

	function getFieldIdFromRadio(radio) {
		var field = radio ? radio.closest('.gfield') : null;
		var match = field && field.id ? field.id.match(/^field_(\d+)_(\d+)$/) : null;

		return match ? parseInt(match[2], 10) : null;
	}

	function isConfiguredField(formId, fieldId) {
		var config = getConfig(formId);

		if (!config || !config.allFields) {
			return false;
		}

		return config.allFields.indexOf(fieldId) !== -1;
	}

	function fieldIsHiddenByLogic(field) {
		if (!field || field.hidden || field.getAttribute('aria-hidden') === 'true') {
			return true;
		}

		return field.style && field.style.display === 'none';
	}

	function ensureFormState(formId) {
		if (!state.formState[formId]) {
			state.formState[formId] = {};
		}

		return state.formState[formId];
	}

	function syncFieldState(formId, fieldId) {
		var formState = ensureFormState(formId);
		var field = getFieldElement(formId, fieldId);
		var radios = document.querySelectorAll(getRadioSelector(formId, fieldId));
		var selected = false;

		if (!field || !radios.length || fieldIsHiddenByLogic(field)) {
			formState[fieldId] = false;
			return false;
		}

		Array.prototype.forEach.call(radios, function (radio) {
			if (radio.checked) {
				selected = true;
			}
		});

		formState[fieldId] = selected;
		return selected;
	}

	function syncVisibleFieldStates(formId) {
		var config = getConfig(formId);

		if (!config || !config.allFields) {
			return;
		}

		config.allFields.forEach(function (fieldId) {
			if (getFieldElement(formId, fieldId)) {
				syncFieldState(formId, fieldId);
			}
		});
	}

	function fieldHasSelection(formId, fieldId) {
		var field = getFieldElement(formId, fieldId);

		if (field) {
			return syncFieldState(formId, fieldId);
		}

		return !!ensureFormState(formId)[fieldId];
	}

	function setLocked(formId, fieldId, isLocked) {
		var field = getFieldElement(formId, fieldId);

		if (!field) {
			return;
		}

		field.classList.toggle('ko-locked-field', isLocked);

		if (isLocked) {
			field.setAttribute('aria-disabled', 'true');
		} else {
			field.removeAttribute('aria-disabled');
		}

		Array.prototype.forEach.call(field.querySelectorAll('input[type="radio"]'), function (radio) {
			if (isLocked) {
				if (!radio.disabled) {
					radio.dataset.koFieldLockDisabled = 'true';
				}
				radio.disabled = true;
				return;
			}

			if (radio.dataset.koFieldLockDisabled === 'true') {
				radio.disabled = false;
				delete radio.dataset.koFieldLockDisabled;
			}
		});
	}

	function updateLocks(formId) {
		var config = getConfig(formId);

		if (!config || !config.fieldPairs) {
			return;
		}

		syncVisibleFieldStates(formId);

		config.fieldPairs.forEach(function (pair) {
			if (!Array.isArray(pair) || pair.length < 2) {
				return;
			}

			setLocked(formId, pair[1], fieldHasSelection(formId, pair[0]));
		});
	}

	function updateAllLocks() {
		Object.keys(getConfigs()).forEach(function (formId) {
			updateLocks(parseInt(formId, 10));
		});
	}

	function getRadioFromPointerEvent(event) {
		var target = event.target;
		var radio = target && target.matches ? target : null;
		var label;

		if (radio && radio.matches('input[type="radio"]')) {
			return radio;
		}

		label = target && target.closest ? target.closest('label') : null;

		if (!label) {
			return null;
		}

		if (label.control && label.control.matches('input[type="radio"]')) {
			return label.control;
		}

		if (label.getAttribute('for')) {
			radio = document.getElementById(label.getAttribute('for'));
			return radio && radio.matches('input[type="radio"]') ? radio : null;
		}

		return label.querySelector('input[type="radio"]');
	}

	function getRadioFormId(radio) {
		var form = radio ? radio.closest('form[id^="gform_"]') : null;
		var match = form && form.id ? form.id.match(/^gform_(\d+)$/) : null;

		return match ? parseInt(match[1], 10) : null;
	}

	function dispatchChange(radio) {
		var changeEvent;

		try {
			changeEvent = new Event('change', { bubbles: true });
		} catch (error) {
			changeEvent = document.createEvent('Event');
			changeEvent.initEvent('change', true, true);
		}

		radio.dispatchEvent(changeEvent);
	}

	function handlePointerDown(event) {
		var radio = getRadioFromPointerEvent(event);
		var formId = getRadioFormId(radio);
		var fieldId = getFieldIdFromRadio(radio);

		if (!formId || !fieldId || !isConfiguredField(formId, fieldId) || radio.disabled) {
			return;
		}

		radio.dataset.koWasChecked = radio.checked ? 'true' : 'false';
	}

	function handleClick(event) {
		var radio = event.target && event.target.matches && event.target.matches('input[type="radio"]') ? event.target : null;
		var formId = getRadioFormId(radio);
		var fieldId = getFieldIdFromRadio(radio);

		if (!radio || !formId || !fieldId || !isConfiguredField(formId, fieldId) || radio.disabled) {
			return;
		}

		if (radio.dataset.koWasChecked === 'true') {
			radio.checked = false;
			dispatchChange(radio);
		}

		delete radio.dataset.koWasChecked;
	}

	function handleChange(event) {
		var radio = event.target && event.target.matches && event.target.matches('input[type="radio"]') ? event.target : null;
		var formId = getRadioFormId(radio);
		var fieldId = getFieldIdFromRadio(radio);

		if (!radio || !formId || !fieldId || !isConfiguredField(formId, fieldId)) {
			return;
		}

		syncFieldState(formId, fieldId);
		updateLocks(formId);
	}

	function handlePostRender(event) {
		var formId = event && event.detail && event.detail.formId ? event.detail.formId : null;

		if (formId && getConfig(formId)) {
			updateLocks(formId);
		}
	}

	function handleLegacyPostRender(event, formId) {
		if (formId && getConfig(formId)) {
			updateLocks(formId);
		}
	}

	function handlePageLoaded(event) {
		var formId = event && event.detail && event.detail.form_id ? event.detail.form_id : null;

		if (formId && getConfig(formId)) {
			updateLocks(formId);
		}
	}

	function handleConditionalLogic(event) {
		var formId = event && event.detail && event.detail.formId ? event.detail.formId : null;

		window.setTimeout(function () {
			if (formId && getConfig(formId)) {
				updateLocks(formId);
				return;
			}

			updateAllLocks();
		}, 0);
	}

	function init() {
		if (state.initialized) {
			updateAllLocks();
			return;
		}

		state.initialized = true;

		document.addEventListener('pointerdown', handlePointerDown, true);
		document.addEventListener('mousedown', handlePointerDown, true);
		document.addEventListener('click', handleClick, true);
		document.addEventListener('change', handleChange, true);
		document.addEventListener('gform/post_render', handlePostRender);
		document.addEventListener('gform_page_loaded', handlePageLoaded);
		document.addEventListener('gform/conditionalLogic/applyRules/end', handleConditionalLogic);

		if (window.jQuery) {
			window.jQuery(document).on('gform_post_render', handleLegacyPostRender);
		}

		updateAllLocks();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	window.KOFieldLock = window.KOFieldLock || {};
	window.KOFieldLock.refresh = updateAllLocks;
})();
