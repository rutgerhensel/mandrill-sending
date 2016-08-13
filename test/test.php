<?php

require_once dirname(__FILE__) . '/../vendor/autoload.php';

use Mailer\Mailer as Mailer;


$recipients = array(
	'name'	=> 'full name',
	'first'	=> 'first name',
	'last'	=> 'last name',
	'email'	=> 'development@kneadle.com',
	'type'	=> 'to' // can be 'to','cc','bcc'
);

$array_of_vars = array();

$html = '<h2>Hello There</2>';

$configs = array('from_email' => 'noreply@example.com');
	
/*
$result = Mailer::instance()
	->setSubject('Testing')
	->setRecipients($recipients)
	->addAttachment('attachment_name.txt', 'This is an attachment')
	->scheduleTemplate('template', $array_of_vars);
*/



$result = Mailer::instance()->sendScheduled();


var_dump($result);