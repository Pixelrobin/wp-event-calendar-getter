<?php

function wp_event_calendar_getter_allowed_post_types() {
	if function_exists('wp_event_calendar_allowed_post_types') {
		return wp_event_calendar_allowed_post_types()
	} else {
		return array('events');
	}
}