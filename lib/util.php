<?php

/**
 * Shortcut to add and remove query parameters from urls. First all parameters
 * named in $remove are removed, then parameters from $add are recursively
 * merged with the existing parameters in the url.
 * 
 * @param string $url the url to edit
 * @param string[] $add key-value pairs of query parameters to add to the url
 * @param string[] $remove keys of query parameters to remove.
 * @return string
 */
function edit_url($url, array $add = [], array $remove = [])
{
	$query_start = strpos($url, '?');

	$fragment_start = strpos($url, '#');

	$query_end = $fragment_start !== false
		? $fragment_start
		: strlen($url);

	if ($query_start !== false)
		parse_str(substr($url, $query_start + 1, $query_end - $query_start), $query);
	else
		$query = array();

	foreach ($remove as $key)
		if (isset($query[$key]))
			unset($query[$key]);

	$query = array_merge_recursive($query, $add);

	$query_str = http_build_query($query);

	$out = $query_start !== false
		? substr($url, 0, $query_start)
		: $url;

	if ($query_str != '')
		$out .= '?' . $query_str;

	if ($fragment_start !== false)
		$out .= substr($url, $fragment_start);

	return $out;
}

function get_enum($class_name, $value)
{
	$refl = new ReflectionClass($class_name);
	return array_search($value, $refl->getConstants());
}