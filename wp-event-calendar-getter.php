<?php

/*
	Plugin Name: WP Event Calendar Getter
	Author:      Michael Savchuk
	License:     MIT
	License URI: https://opensource.org/licenses/MIT
	Version:     1.0.0
	Description: Makes WP Event Calendar post types public, along with some helpful functions.
	Text Domain: wp-event-calendar-getter
*/

// --- Functions --- //
function event_getter_modify_args($args, $post_type) {
	if ($post_type === 'event') {
		$args['public'] = true;
		$args['publicly_queryable'] = true;
	}

	return $args;
}

function event_getter_get_event_range($post_id, $date_format = 'M j') {
	$datetime_start_meta = get_post_meta($post_id, 'wp_event_calendar_date_time', true);
	$datetime_end_meta   = get_post_meta($post_id, 'wp_event_calendar_end_date_time', true);

	$datetime_start = $datetime_start_meta !== ''
		? DateTime::createFromFormat('Y-m-d H:i:s', $datetime_start_meta)->format($date_format)
		: false;
	
	$datetime_end = $datetime_end_meta !== ''
		? DateTime::createFromFormat('Y-m-d H:i:s', $datetime_end_meta)->format($date_format)
		: false;
	
	if ($datetime_start === $datetime_end) $datetime_end = false;
	
	if ($datetime_start !== false) {
		if ($datetime_end !== false) {
			return $datetime_start . ' - ' . $datetime_end;
		} else return $datetime_start;
	} else return false;
}

function event_getter_get_event_info($post_id, $date_format = 'l, M j', $time_format = 'g:i A') {
	$datetime_start_meta   = get_post_meta($post_id, 'wp_event_calendar_date_time', true);
	$datetime_end_meta     = get_post_meta($post_id, 'wp_event_calendar_end_date_time', true);
	$datetime_all_day_meta = get_post_meta($post_id, 'wp_event_calendar_all_day', true);
	$location_meta         = get_post_meta($post_id, 'wp_event_calendar_location', true);
	
	$datetime_start = $datetime_start_meta !== ''
		? DateTime::createFromFormat('Y-m-d H:i:s', $datetime_start_meta)
		: false;
	
	$datetime_end = $datetime_end_meta !== ''
		? DateTime::createFromFormat('Y-m-d H:i:s', $datetime_end_meta)
		: false;

	if (
		$datetime_end === false
		|| $datetime_start->format('Y-m-d') === $datetime_end->format('Y-m-d')
	) {
		$format = $time_format;
	} else $format = $datetime_all_day_meta !== '' ? $date_format : $date_format . ', ' . $time_format;

	$date_start = $datetime_start->format($format);
	$date_end   = $datetime_end !== false ? $datetime_end->format($format) : false;
	$location   = $location_meta !== '' ? $location_meta : false;

	return array(
		'date_start' => $date_start,
		'date_end'   => $date_end,
		'location'   => $location
	);
}

function event_getter_get_month_query($month = false, $year = false) {
	$start = new DateTime();

	if ($month === false) $month = intval($start->format('m'));
	if ($year === false)  $year  = intval($start->format('Y'));

	$start->setDate($year, $month, 1);
	$start->setTime(0, 0);

	$num_days = intval($start->format('t'));

	$end = new DateTime();
	$end->setDate($year, $month, $num_days);
	$end->setTime(23, 59, 59);

	$query_args = array(
		'post_type'      => 'event',
		'meta_key'       => 'wp_event_calendar_date_time',
		'orderby'        => 'meta_value',
		'order'          => 'ASC',
		'posts_per_page' => -1,
		'meta_query'     => array(
			'relation' => 'OR',
			array(
				'key'     => 'wp_event_calendar_date_time',
				'value'   => array(
					$start->format('Y-m-d H:i:s'),
					$end->format('Y-m-d H:i:s')
				),
				'compare' => 'BETWEEN',
				'type'    => 'DATETIME'
			),
			array(
				'key'     => 'wp_event_calendar_end_date_time',
				'value'   => array(
					$start->format('Y-m-d H:i:s'),
					$end->format('Y-m-d H:i:s')
				),
				'compare' => 'BETWEEN',
				'type'    => 'DATETIME'
			)
		)
	);

	return $query = new WP_Query($query_args);
}

// --- Hooks --- //
add_filter('register_post_type_args', 'event_getter_modify_args', 10, 2);