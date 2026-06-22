<?php
/**
 * Plugin Name: KO GF Field Lock
 * Description: Lock Gravity Forms radio fields so selecting one field disables and fades another.
 * Author: Kevin O'Neill
 * Version: 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class KO_Field_Lock {

	const VERSION = '1.2.0';

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

		$field_pairs = $this->get_field_pairs( $form );
		$all_fields  = $this->get_field_ids( $field_pairs );

		wp_enqueue_style(
			'ko-gf-field-lock',
			plugins_url( 'assets/css/ko-gf-field-lock.css', __FILE__ ),
			array(),
			self::VERSION
		);

		wp_enqueue_script(
			'ko-gf-field-lock',
			plugins_url( 'assets/js/ko-gf-field-lock.js', __FILE__ ),
			array( 'jquery' ),
			self::VERSION,
			true
		);

		wp_add_inline_script(
			'ko-gf-field-lock',
			'window.KOFieldLockConfig = window.KOFieldLockConfig || {}; window.KOFieldLockConfig[' . (int) self::FORM_ID . '] = ' . wp_json_encode(
				array(
					'formId'     => (int) self::FORM_ID,
					'fieldPairs' => $field_pairs,
					'allFields'  => $all_fields,
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Get configured field pairs.
	 *
	 * @param array $form Gravity Forms form object.
	 *
	 * @return array
	 */
	protected function get_field_pairs( $form ) {
		$field_pairs = apply_filters( 'ko_gf_field_lock_field_pairs', $this->field_pairs, self::FORM_ID, $form );

		if ( ! is_array( $field_pairs ) ) {
			return array();
		}

		$sanitized_pairs = array();

		foreach ( $field_pairs as $pair ) {
			if ( ! is_array( $pair ) || count( $pair ) < 2 ) {
				continue;
			}

			$trigger_field_id = absint( $pair[0] );
			$target_field_id  = absint( $pair[1] );

			if ( ! $trigger_field_id || ! $target_field_id ) {
				continue;
			}

			$sanitized_pairs[] = array( $trigger_field_id, $target_field_id );
		}

		return $sanitized_pairs;
	}

	/**
	 * Build a unique list of configured field IDs.
	 *
	 * @param array $field_pairs Field pairs.
	 *
	 * @return array
	 */
	protected function get_field_ids( $field_pairs ) {
		$field_ids = array();

		foreach ( $field_pairs as $pair ) {
			foreach ( $pair as $field_id ) {
				$field_id = absint( $field_id );

				if ( $field_id && ! in_array( $field_id, $field_ids, true ) ) {
					$field_ids[] = $field_id;
				}
			}
		}

		return $field_ids;
	}

}

new KO_Field_Lock();
