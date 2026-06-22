# KO GF Field Lock

## Overview
Locks mutually exclusive Gravity Forms radio fields. When a user selects an option in one configured field, the paired field is disabled and faded. Clicking the selected radio option again clears it and unlocks the paired field.

## Key Features
- Targets Gravity Form ID `2`
- Locks Field `63` when Field `115` has a selection
- Locks Field `115` when Field `63` has a selection
- Supports click-again radio deselection for configured fields
- Reinitializes after Gravity Forms AJAX renders, AJAX page changes, validation refreshes, and conditional-logic updates
- Adds `aria-disabled="true"` and the `.ko-locked-field` class while a field is locked

## Requirements
- WordPress
- Gravity Forms

## Installation
1. Copy the `ko-gf-field-lock` plugin folder into `/wp-content/plugins/`
2. Activate it from the WordPress admin
3. Test Form ID `2` in a staging environment before production rollout

## Usage
The current configuration is code-based and intentionally narrow:

```php
array( 63, 115 ),
array( 115, 63 ),
```

Each pair is one-way. The two entries above create the current two-way mutual exclusion behavior.

## Configuration
Additional field pairs can be added in code or by filtering `ko_gf_field_lock_field_pairs`:

```php
add_filter( 'ko_gf_field_lock_field_pairs', function ( $pairs, $form_id ) {
	if ( 2 !== (int) $form_id ) {
		return $pairs;
	}

	$pairs[] = array( 80, 114 );
	$pairs[] = array( 114, 80 );

	return $pairs;
}, 10, 2 );
```

## Extensibility
Future enhancements could add an admin settings screen, reusable lock groups, visual lock messages, explicit clear links, or import/export of configuration.

## Development Notes
- JavaScript is loaded with the plugin's own `ko-gf-field-lock` handle instead of attaching inline code to a Gravity Forms internal script handle.
- Lifecycle handling listens for `gform/post_render`, legacy `gform_post_render`, `gform_page_loaded`, and `gform/conditionalLogic/applyRules/end`.
- Disabled radio inputs are only re-enabled when this plugin disabled them.

## License
GPL-2.0-or-later
