<?php

session_name('PHPSESSID_CMAPPLYSTAFF');
session_start();
require_once dirname(__FILE__).'/../lib/common.php';
require_once dirname(__FILE__).'/../lib/staffers.php';
require_once dirname(__FILE__).'/../lib/cart.php';
require_once theme_file_path('public.php');

function render_application_head($title) {
	render_head($title);
}

function render_application_body($title) {
	render_body($title, null);
}

function render_application_tail() {
	render_tail();
}

// Trigger a Slack webhook to notify about new applications.
function staff_application_hook($staffer_id, $first_name, $last_name, $fandom_name) {
	$url = '';
	$payload = '{"text": "<https://example.com/admin/review_staffer.php?id='.$staffer_id.'|New staff application> from '.$first_name.' '.$last_name.' ('.$fandom_name.')"}';
	$params = array('payload' => $payload);
	$query = http_build_query($params);
	$contextData = array(
		'method' => 'POST',
		'header' => "Connection: close\r\n".
					"Content-Length: ".strlen($query)."\r\n",
		'content' => $query);
	$context = stream_context_create(array('http' => $contextData));
	$result = file_get_contents($url, false, $context);
}
