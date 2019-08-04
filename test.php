<?php

function y() {
	return 'z';
}

class X {
	public $x = y();
}

$z = new X();