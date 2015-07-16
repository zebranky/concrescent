<?php

require_once dirname(__FILE__).'/common.php';

function trigger_hook($url, $payload) {
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

// Trigger a Slack webhook to notify about new applications.
function app_submitted_hook($staffer_id, $first_name, $last_name, $fandom_name) {
	global $slack_notification_domain, $slack_staff_hook_url;
	$url = $slack_staff_hook_url;
	$payload = '{"text": "<https://'.$slack_notification_domain.'/admin/review_staffer.php?id='.$staffer_id.'|New staff application> from '.$first_name.' '.$last_name.' ('.$fandom_name.')"}';
	trigger_hook($url, $payload);
}

// Trigger a Slack webhook on application approval.
function app_approved_hook($staffer_id, $first_name, $last_name, $fandom_name, $approver, $assigned_position) {
	global $slack_notification_domain, $slack_staff_hook_url;
	$url = $slack_staff_hook_url;
	$payload = '{"text": "Application for <https://'.$slack_notification_domain.'/admin/review_staffer.php?id='.$staffer_id.'|'.$first_name.' '.$last_name.' ('.$fandom_name.')> ACCEPTED for '.$assigned_position.' by '.$approver.'"}';
	trigger_hook($url, $payload);
}
