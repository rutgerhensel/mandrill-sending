<?php

require_once dirname(__FILE__) . '/../vendor/autoload.php';

use Mailer\Mailer as Mailer;
use Mailer\DB\DB as MailerDB;

$configs = require(dirname(__FILE__) . '/config/mailer.php');
$db      = require(dirname(__FILE__) . '/config/db.php');

Mailer::setDefaults($configs);
MailerDB::setDefaults($db);

$recipients = array(
	'name'	=> 'full name',
	'first'	=> 'first name',
	'last'	=> 'last name',
	'email'	=> 'test@example.com',
	'type'	=> 'to' // can be 'to','cc','bcc'
);


### HTML EXAMPLE ###
$html = '<h2>Hello World!</h2>';

Mailer::instance()
	->setSubject('Testing')
	->setRecipients($recipients)
	->addAttachment('attachment_name.txt', 'This is an attachment')
	->scheduleHtml($html);


### TEMPLATE EXAMPLE ###

$array_of_vars = array('var_1' => 'Hello World!');

$template = 'email-template-slug';

Mailer::instance()
	->setSubject('Testing')
	->setRecipients($recipients)
	->addAttachment('attachment_name.txt', 'This is an attachment')
	->scheduleTemplate($template, $array_of_vars);


$result = Mailer::instance()->sendScheduled();


echo '<pre>' . print_r($result, true) . '</pre>';