<?php

class UnauthorizedException extends RuntimeException
{
		
}

function assert_admin($current_user)
{
	if ($current_user->role() !== Roles::Administrator)
		throw new UnauthorizedException();
}