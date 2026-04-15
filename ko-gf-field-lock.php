<?php
/**
 * Plugin Name: KO GF Field Lock
 * Description: Lock Gravity Forms radio fields so selecting one field disables and fades another.
 * Author: Kevin O'Neill
 * Version: 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class KO_Field_Lock {

	/**
	 * Gravity Form ID this behavior applies to.
	 * Update if needed.
	 */
	const FORM_ID = 2;

	/**
	 * Field pairs for two-way locking.
	 * Each pair is [trigger_field_id, target_field_id].
	 */
	protected $field_pairs = array(
		array( 63, 115 ),
		array( 115, 63 ),
	);

	public function __construct() {
		add_action( 'gform_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10, 2 );
	}

	/**
	 * Enqueue JS and CSS only when the target form is present.
	 *
	 * @param array $form
	 * @param bool  $is_ajax
	 */
	public function enqueue_scripts( $form, $is_ajax ) {

		if ( (int) rgar( $form, 'id' ) !== self::FORM_ID ) {
			return;
		}

		// Register a dummy style so we can safely attach inline CSS.
		wp_register_style( 'ko-field-lock', false, array(), '1.1.0' );
		wp_enqueue_style( 'ko-field-lock' );
		wp_add_inline_style( 'ko-field-lock', $this->get_inline_css() );

		// Attach inline JS to Gravity Forms main frontend script.
		wp_add_inline_script( 'gform_gravityforms', $this->get_inline_js() );
	}

	/**
	 * CSS to fade and visually lock the entire field.
	 *
	 * @return string
	 */
	protected function get_inline_css() {
		$css = <<<CSS
/* KO Field Lock - fade & lock entire field wrapper */
.gfield.ko-locked-field {
	opacity: 0.45;
	pointer-events: none;
	transition: opacity 0.2s ease-in-out;
}

/* Optional: cursor style if something sneaks through */
.gfield.ko-locked-field input[type="radio"],
.gfield.ko-locked-field label {
	cursor: not-allowed !important;
}
CSS;
		return $css;
	}

	/**
	 * JS to handle two-way locking between radio fields
	 * AND allow deselecting a radio by clicking it again.
	 *
	 * @return string
	 */
	protected function get_inline_js() {

		$form_id     = (int) self::FORM_ID;
		$field_pairs = wp_json_encode( $this->field_pairs );

		// Build a unique list of all field IDs involved.
		$all_fields = array();
		foreach ( $this->field_pairs as $pair ) {
			foreach ( $pair as $field_id ) {
				if ( ! in_array( $field_id, $all_fields, true ) ) {
					$all_fields[] = $field_id;
				}
			}
		}
		$all_fields_json = wp_json_encode( $all_fields );

		$js = <<<JS
document.addEventListener('DOMContentLoaded', function() {

	var formId    = {$form_id};
	var fieldPairs = {$field_pairs} || [];
	var allFields  = {$all_fields_json} || [];

	/**
	 * Set up a one-way lock:
	 * Selecting in triggerField locks targetField.
	 */
	function setupLock(triggerField, targetField) {

		var triggerSelector     = '#field_' + formId + '_' + triggerField + ' input[type="radio"]';
		var targetFieldSelector = '#field_' + formId + '_' + targetField;
		var targetInputSelector = targetFieldSelector + ' input[type="radio"]';

		function updateLockState() {
			var triggerRadios = document.querySelectorAll(triggerSelector);
			if (!triggerRadios.length) {
				return;
			}

			var triggerSelected = Array.prototype.some.call(triggerRadios, function(radio) {
				return radio.checked;
			});

			var targetFieldEl = document.querySelector(targetFieldSelector);
			if (!targetFieldEl) {
				return;
			}

			var targetRadios = targetFieldEl.querySelectorAll('input[type="radio"]');

			if (triggerSelected) {
				// Disable and fade the whole field
				targetFieldEl.classList.add('ko-locked-field');
				targetRadios.forEach(function(r) {
					r.disabled = true;
				});
			} else {
				// Re-enable field and restore appearance
				targetFieldEl.classList.remove('ko-locked-field');
				targetRadios.forEach(function(r) {
					r.disabled = false;
				});
			}
		}

		// Watch for changes on trigger or target (in case logic toggles visibility)
		document.addEventListener('change', function(e) {
			if (e.target.matches(triggerSelector) || e.target.closest(targetFieldSelector)) {
				updateLockState();
			}
		});

		// Initial run
		updateLockState();
	}

	/**
	 * Allow deselecting a radio option by clicking it again.
	 * When a radio that was already checked is clicked, we uncheck it
	 * and fire a change event so the lock logic can update.
	 */
	function enableRadioDeselect(fieldId) {
		var selector = '#field_' + formId + '_' + fieldId + ' input[type="radio"]';
		var radios   = document.querySelectorAll(selector);
		if (!radios.length) {
			return;
		}

		radios.forEach(function(radio) {

			// Store state before the click toggles it.
			radio.addEventListener('mousedown', function() {
				radio.dataset.koWasChecked = radio.checked ? 'true' : 'false';
			});

			radio.addEventListener('click', function() {
				if (radio.dataset.koWasChecked === 'true') {
					// It was checked before click, so treat this as a "deselect"
					radio.checked = false;
					var changeEvent;
					try {
						changeEvent = new Event('change', { bubbles: true });
					} catch (e) {
						// For older browsers
						changeEvent = document.createEvent('Event');
						changeEvent.initEvent('change', true, true);
					}
					radio.dispatchEvent(changeEvent);
				}
			});
		});
	}

	// Initialize locking for all defined pairs.
	fieldPairs.forEach(function(pair) {
		if (Array.isArray(pair) && pair.length === 2) {
			setupLock(pair[0], pair[1]);
		}
	});

	// Enable deselect behavior on all fields involved.
	allFields.forEach(function(fieldId) {
		enableRadioDeselect(fieldId);
	});

});
JS;

		return $js;
	}

}

new KO_Field_Lock();
