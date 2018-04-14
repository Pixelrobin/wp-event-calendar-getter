<?php
/*
function wp_ec_getter_add_metabox() {
	add_meta_box(
		'wp_event_calendar_getter_settings',
		__('Event Getter Settings', 'wp-event-calendar-getter'),
		'wp_ec_getter_settings_metabox',
		wp_event_calendar_allowed_post_types(),
		'side',
		'default'
	);
}

function wp_ec_getter_settings_metabox($post) {
	wp_nonce_field( plugin_basename( __FILE__ ), "event_date_box_content_nonce" );

	?>
		<label>
			<input type="checkbox">
			Show in getter
	<?php
}