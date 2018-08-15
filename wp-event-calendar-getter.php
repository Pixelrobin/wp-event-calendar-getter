<?php

/*
	Plugin Name: WP Event Calendar Getter
	Author:      Michael Savchuk
	License:     MIT
	License URI: https://opensource.org/licenses/MIT
	Version:     1.0.0
	Description: Adds REST API support for WP Event Calendar, along with some helpful functions for getting events
	Text Domain: wp-event-calendar-getter
*/

require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

add_filter('register_post_type_args', 'wp_ec_getter_modify_args', 10, 2);
add_action('rest_api_init', 'wp_ec_getter_register_fields');
add_action('rest_api_init', 'wp_ec_getter_register_routes');

function wp_ec_getter_modify_args($args, $post_type) {
	// https://developer.wordpress.org/reference/hooks/register_post_type_args/

	if ($post_type == 'event') {
		$args['show_in_rest'] = true;
		$args['rest_base'] = 'events';
	}

	return $args;
}

function wp_ec_getter_get_meta_cb($meta_key) {
	return function($obj) use ($meta_key) {
		$post_id = $obj['id'];
		return get_post_meta($post_id, $meta_key, true);
	};
}

function wp_ec_getter_register_meta_field($field, $meta_key) {
	register_rest_field('event', $field, array(
		'get_callback'    => wp_ec_getter_get_meta_cb($meta_key),
		'update_callback' => null,
		'schema'          => null
	));
}

function wp_ec_getter_get_events_by_month($data) {
	$year  = (int)$data['year'];
	$month = (int)$data['month'];

	$days  = str_pad(
		cal_days_in_month(CAL_GREGORIAN, $month, $year),
		2, '0', STR_PAD_LEFT
	);

	$year_pad  = str_pad($year, 4, '0', STR_PAD_LEFT);
	$month_pad = str_pad($month, 2, '0', STR_PAD_LEFT);

	$posts = get_posts(array(
		'post_type' => 'event',
			'meta_query' => array(
				array(
					'type'    => 'DATETIME',
					'key'     => 'wp_event_calendar_date_time',
					'value'   => array(
						$year_pad . '-' . $month_pad . '-00 00:00:00',
						$year_pad . '-' . $month_pad . '-' . $days . ' 23:59:59'
					),
					'compare' => 'BETWEEN'
				)
			)
	));

	return $posts;
}

function wp_ec_getter_validate_datetime($value, $request, $param) {
	// https://stackoverflow.com/questions/15858685/fast-way-in-php-to-check-if-a-value-is-in-mysql-datetime-format
	// https://stackoverflow.com/questions/14504913/verify-valid-date-using-phps-datetime-class
	
	$date = DateTime::createFromFormat('Y-m-d', $value);

	if ($date !== false) {
		$errors = DateTime::getLastErrors();

		if (!empty($errors['warning_count'])) {
			return new WP_ERROR(
				'rest_invalid_param',
				esc_html__('Parameter is an impossible date.', 'wp-event-calendar-getter'),
				array('status' => 400)
			);
		} else return true;
	} else return new WP_ERROR(
		'rest_invalid_param',
		esc_html__('Parameter is not in the correct format. Use YYYY-MM-DD.', 'wp-event-calendar-getter'),
		array('status' => 400)
	);
}

function wp_ec_getter_validate_at($value, $request, $param) {
	if (!in_array($value, array('start', 'end', 'overlap'), true)) {
		return new WP_ERROR(
			'rest_invalid_param',
			esc_html__('Parameter is not an accepted value'),
			array('status' => 400)
		);
	}
}

function wp_ec_getter_register_fields() {
	wp_ec_getter_register_meta_field('event_start',    'wp_event_calendar_date_time');
	wp_ec_getter_register_meta_field('event_end',      'wp_event_calendar_end_date_time');
	wp_ec_getter_register_meta_field('event_location', 'wp_event_calendar_location');
	wp_ec_getter_register_meta_field('event_all_day',  'wp_event_calendar_all_day');
	wp_ec_getter_register_meta_field('event_repeat',   'wp_event_calendar_repeat');
	wp_ec_getter_register_meta_field('event_expire',   'wp_event_calendar_expire');
}

function wp_ec_getter_terms_to_arrays($terms) {
	$result = array();

	foreach ($terms as $term) {
		array_push($result, 'hello');
	};

	return $result;
}

function wp_ec_getter_date_string_to_datetime($date, $endof = false) {
	$datetime = DateTime::createFromFormat('Y-m-d', $date);

	if ($datetime !== false) {
		$errors = DateTime::getLastErrors();

		if (empty($errors['warning_count'])) {
			if ($endof) {
				date_time_set($datetime, 23, 59, 59);
			} else date_time_set($datetime, 0, 0, 0);

			return $datetime;
		} else return false;
	} else return false;
}

/*
	wp-event-calendar Repeat Modes:
	0    = never
	10   = weekly
	100  = monthly
	1000 = yearly
*/

/*
NOTES

Arguments:
from  - optional - default is today
to    - optional
at    - "start", "end", or "both" - optional - default is "end"
limit - optional

     | start | end | both 
-----+-------+-----+------
from | A     | A   | A    
-----+-------+-----+------
+ to | B     | B   | C    

A:
if (at == "both") at = "end";

SELECT wp_posts.*, start.meta_value AS "start_date", end.meta_value AS "end_date" FROM wp_posts
	LEFT JOIN wp_postmeta start ON wp_posts.ID=start.post_id AND start.meta_key="wp_event_calendar_date_time"
	LEFT JOIN wp_postmeta end ON wp_posts.ID=end.post_id AND end.meta_key="wp_event_calendar_end_date_time"
	WHERE wp_posts.post_type="event" AND wp_posts.post_status="publish" AND CAST([at].meta_value AS DATETIME) >= [from]
	ORDER BY CASE
		WHEN CAST([start].meta_value AS DATETIME) >= "2018-05-08 00:00:00" THEN start.meta_value
		ELSE end.meta_value
	END ASC
	;

SELECT wp_posts.post_name, start.meta_value AS "start_date", end.meta_value AS "end_date" FROM wp_posts
	LEFT JOIN wp_postmeta start ON wp_posts.ID=start.post_id AND start.meta_key="wp_event_calendar_date_time"
	LEFT JOIN wp_postmeta end ON wp_posts.ID=end.post_id AND end.meta_key="wp_event_calendar_end_date_time"
	WHERE wp_posts.post_type="event" AND wp_posts.post_status="publish" AND CAST(end.meta_value AS DATETIME) >= "2018-05-08 00:00:00"
	ORDER BY CASE
		WHEN CAST(start.meta_value AS DATETIME) >= "2018-05-08 00:00:00" THEN start.meta_value
		ELSE end.meta_value
	END ASC
	LIMIT 3
	;

B:
SELECT wp_posts.*, start.meta_value AS "start_date", end.meta_value AS "end_date" FROM wp_posts
	LEFT JOIN wp_postmeta start ON wp_posts.ID=start.post_id AND start.meta_key="wp_event_calendar_date_time"
	LEFT JOIN wp_postmeta end ON wp_posts.ID=end.post_id AND end.meta_key="wp_event_calendar_end_date_time"
	WHERE wp_posts.post_type="event" AND wp_posts.post_status="publish"
*/



function wp_ec_getter_get_results($args) {
	global $wpdb;

	$defaults = array (
		'from'  => date('Y-m-d H:i:s'),
		'to'    => NULL,
		'at'    => 'end',
		'limit' => NULL,
		'order' => 'first'
	);

	$args = wp_parse_args($args, $defaults);

	$query = "
		SELECT posts.*, start.meta_value AS 'start_date', end.meta_value AS 'end_date', all_day.meta_value AS 'all_day' FROM $wpdb->posts posts
		LEFT JOIN $wpdb->postmeta start ON posts.ID=start.post_id AND start.meta_key='wp_event_calendar_date_time'
		LEFT JOIN $wpdb->postmeta end ON posts.ID=end.post_id AND end.meta_key='wp_event_calendar_end_date_time'
		LEFT JOIN $wpdb->postmeta all_day ON posts.ID=all_day.post_id AND all_day.meta_key='wp_event_calendar_all_day'
		WHERE posts.post_type='event' AND (posts.post_status='publish' OR posts.post_status='passed')
	";

	if (is_null($args['to'])) {
		$query .= " AND CAST({$args['at']}.meta_value AS DATETIME) >= '{$args['from']}'";
	} else {
		switch ($args['at']) {
			case 'start':
			case 'end':
				$query .= " AND CAST({$args['at']}.meta_value AS DATETIME) BETWEEN '{$args['from']}' AND '{$args['to']}'";
			break;

			default:
				$query .= " AND CAST(end.meta_value AS DATETIME) >= '{$args['from']}' AND CAST(start.meta_value AS DATETIME) <= '{$args['to']}'";
			break;
		}
	}

	switch ($args['order']) {
		case 'start':
			$query .= ' ORDER BY start.meta_value ASC';
		break;

		case 'end':
			$query .= ' ORDER BY end.value_value ASC';
		break;

		default:
			$query .= "
				ORDER BY CASE
					WHEN CAST(start.meta_value AS DATETIME) >= '{$args['from']}' THEN start.meta_value
					ELSE end.meta_value
				END ASC
			";
		break;
	}

	if (!is_null($args['limit'])) $query .= " LIMIT {$args['limit']}";

	$query .= ';';

	$results = $wpdb->get_results($query, OBJECT);

	return $results;
}

function wp_ec_getter_get_events_from_rest($request) {
	$from  = null;
	$to    = null;
	$after = false;

	if (!is_null($request['from'])) {
		$from = wp_ec_getter_date_string_to_datetime($request['from']);
	}
	
	if (!is_null($request['to'])) {
		$to = wp_ec_getter_date_string_to_datetime($request['to'], true);
	}

	$range = array();
	$check_at = $request['at'] === 'start'
		? 'wp_event_calendar_date_time'
		: 'wp_event_calendar_end_date_time';

	if ($from && $to) {
		if ($request['at'] == 'overlap') {
			$range[] = array(
				'relation' => 'AND',
				array(
					'key'     => 'wp_event_calendar_end_date_time',
					'value'   => $from->format('Y-m-d H:i:s'),
					'compare' => '>=',
					'type'    => 'DATETIME'
				),
				array(
					'key'     => 'wp_event_calendar_date_time',
					'value'   => $to->format('Y-m-d H:i:s'),
					'compare' => '<=',
					'type'    => 'DATETIME'
				)
			);
		} else {
			$range[] = array(
				'key'     => $check_at,
				'value'   => array(
					$from -> format('Y-m-d H:i:s'),
					$to   -> format('Y-m-d H:i:s')),
				'compare' => 'BETWEEN',
				'type'    => 'DATETIME'
			);
		}
	} else {
		if ($from) {
			$range[] = array(
				'key'   => $check_at,
				'value' => $from->format('Y-m-d H:i:s'),
				'compare' => '>',
				'type' => 'DATETIME'
			);
		} else if ($to) {
			$range[] = array(
				'key' => $check_at,
				'value' => $to->format('Y-m-d H:i:s'),
				'compare' => '<',
				'type' => 'DATETIME'
			);
		}
	}

	$mq = array(
		'relation' => 'OR',
		array(
			'key'     => 'wp_event_calendar_repeat',
			'value'   => '0',
			'compare' => '!=',
			'type'    => 'NUMERIC'
		),
		$range
	);

	$args = array(
		'post_type'  => 'event',
		'meta_query' => $mq
	);

	$query = new WP_QUERY($args);
	$posts = $query->posts;
	$return_data = array();

	$terms_args = array('fields' => 'names');

	foreach ($posts as $post) {
		$return_data[] = array(
			'created'      => $post->post_date,
			'createdGmt'   => $post->post_date_gmt,
			'id'           => $post->ID,
			'url'          => get_permalink($post),
			'modified'     => $post->post_modified,
			'modifiedGmt'  => $post->post_modified_gmt,
			'title'        => $post->post_title,
			'content'      => $post->post_content,
			'author'       => $post->post_author,
			'types'        => wp_get_post_terms($post->ID, 'event-type', $terms_args),
			'categories'   => wp_get_post_terms($post->ID, 'event-category', $terms_args),
			'tags'         => wp_get_post_terms($post->ID, 'event-tag', $terms_args),
			'start'        => get_post_meta($post->ID, 'wp_event_calendar_date_time', true),
			'end'          => get_post_meta($post->ID, 'wp_event_calendar_end_date_time', true),
			'allDay'       => get_post_meta($post->ID, 'wp_event_calendar_all_day', true),
			'repeat'       => (int) get_post_meta($post->ID, 'wp_event_calendar_repeat', true),
			'location'     => get_post_meta($post->ID, 'wp_event_calendar_location', true)
		);
	}

	$return_data[] = $request['at'];

	return $return_data;
}

function wp_ec_getter_register_routes() {
	register_rest_route('wp-event-calendar-getter/v1', '/events', array(
		'methods'  => 'GET',
		'callback' => 'wp_ec_getter_get_events_from_rest',
		'args'     => array(

			'from' => array(
				'description'       => __('Used to return relevant events after a date', 'wp-event-calendar-getter'),
				'type'              => 'string',
				'validate_callback' => 'wp_ec_getter_validate_datetime'
			),

			'to' => array(
				'description'       => __('Used to return relevant events before a date', 'wp-event-calendar-getter'),
				'type'              => 'string',
				'validate_callback' => 'wp_ec_getter_validate_datetime'
			),
			
			'at' => array(
				'description'       => __('Choose to select dates that start or end in the range, or for overlaps.', 'wp-event-calendar-getter'),
				'type'              => 'string',
				'validate_callback' => 'wp_ec_getter_validate_at',
				'enum'              => array('start', 'end', 'overlap'),
				'default'           => 'overlap'
			)
		)
	));
}

// https://github.com/stuttter/wp-event-calendar/issues/11
add_filter( 'register_post_type_args', function( $args, $name ) {
    if ( 'event' === $name ) {
        $args['publicly_queryable'] = true;
    }

   return $args;
}, 10, 2 );