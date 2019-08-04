<?php

function array_map_assoc(Callable $callback, array $arr)
{
	$remapped = array();

	foreach($arr as $k => $v)
		$remapped += $callback($k, $v);

	return $remapped;
}