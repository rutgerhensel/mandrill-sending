<?php

return array(
	/*
	|--------------------------------------------------------------------------
	| Database driver
	|--------------------------------------------------------------------------
	|
	|can be 'mysql-driver' or 'pdo-driver' (had to add '-driver' because of colisions with PDO and mysql libraries
	|
	*/
	
	'driver'    => 'mysql-driver', 
	
	/*
	|--------------------------------------------------------------------------
	| Database credentials
	|--------------------------------------------------------------------------
	|
	*/
	'credentials' => array(
		'host'      => 'localhost',
		'database'  => 'tamsbu',
		'username'  => 'root',
		'password'  => 'root',
		'prefix'    => 'basejump_'
	),
);