<?php

return array(

	/*
	|--------------------------------------------------------------------------
	| Mailer service
	|--------------------------------------------------------------------------
	|
	|can be 'madrill' or 'phpmailer'
	|
	*/
	
	'service' => 'phpmailer',

	/*
	|--------------------------------------------------------------------------
	| Pretend Mode
	|--------------------------------------------------------------------------
	|
	| when set to true, it will remove all email recipents and only include 'pretend_email'
	|
	*/	
	
	'pretend' => true,
	
	'pretend_email' => 'email@example.com',
	
	'send_copy_to_sender' => false,
	
	'include_unsubscribe_link' => false,
	
	'unsubscribe_link' => "
		<br />
		<br />
		<p style=\"font-family:'Tahoma','sans-serif';font-size:10px\">
			To unsubscribe <a href=\"*|UNSUB:http://localhost/unsub|*\">click here</a>. 
		</p>
	",
	
	'from_email' => 'email@example.com',
	
	'from_name' => 'Mailer',
	
	'send_per_run' => 5,
	
	'resend_attempts' => 5,
	
	'delete' => array(
		
		//'slug' => 'date string'
		'slugs-to-delete' => '-1 month',
	),
		
	/*
	|--------------------------------------------------------------------------
	| Mandrill Service Configuration
	|--------------------------------------------------------------------------
	|
	*/	

	'mandrill' => array(
	
		'api_key' => 'xxxxxx',
		
		'subaccount' => 'SubAccount',
	
	),
	
	'phpmailer' => array(
		
		'templates_path' => '/path/to/templates/'
	)
);