<?php

return array(
	/*
	|--------------------------------------------------------------------------
	| Database driver
	|--------------------------------------------------------------------------
	|
	|can be 'mysql' or 'pdo'
	|
	*/
	
	'driver'    => 'pdo-driver', //had to add '-driver' because of colisions with PDO library
	
	/*
	|--------------------------------------------------------------------------
	| Database credentials
	|--------------------------------------------------------------------------
	|
	*/
	'credentials' => array(
		'host'      => 'host',
		'database'  => 'dbname',
		'username'  => 'root',
		'password'  => 'root',
		'prefix'    => 'prefix_'
	),
);