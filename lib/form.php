<?php

namespace form;

function is_submitted($form_name)
{
	return $_SERVER['REQUEST_METHOD'] == 'POST' && is_valid_nonce($form_name, $_POST['_nonce']);
}

function is_valid_nonce($action, $nonce)
{
	return hash_equals($nonce, generate_nonce($action));
}

function generate_nonce($action)
{
	if (!session_id())
		session_start();

	$session = session_id();

	$fields = [
		nonce_tick(),
		$session,
		$action
	];

	$salt = constant('NONCE_SALT');

	if ($salt === null)
		throw new \RuntimeException('No nonce_salt configured');

	return hash_hmac('sha1', implode(';', $fields), $salt);
}

function nonce_tick()
{
	$nonce_life = 12 * 3600; // 12 hours
	return ceil(time() / ($nonce_life / 2));
}
