Installing Mailer

By default, this mailer uses the Mandrill Mail service to send email. We'll add support for other mail services in the future.

An array of all configuration used on this package along with the mysql code need it is located in the install directory

require_once dirname(__FILE__) . '/../vendor/autoload.php';

# make alias of Mailer (if need and desired)
use Mailer\Mailer as Mailer;

# make alias of Mailer (if need and desired)
use Mailer\DB\DB as MailerDB;

# add default functionality
$configs = Array(...);
Mailer::setDefaults($configs);

# add db configuration;
$db_config = Array(...);
MailerDB::setDefaults($db_config);

# single recipients
$recipients = array(
	'name'	=> 'full name',
	'first'	=> 'first name',
	'last'	=> 'last name',
	'email'	=> 'valid@mail.mail',
	'type'	=> 'to' // can be 'to','cc','bcc'
);

# multiple recipients
$recipients = array(
	array(
		'name'	=> 'full name',
		'first'	=> 'first name',
		'last'	=> 'last name',
		'email'	=> 'valid@email.email',
		'type'	=> 'to' // can be 'to','cc','bcc'
	),
	array(
		'name'	=> 'full name',
		'first'	=> 'first name',
		'last'	=> 'last name',
		'email'	=> 'valid@mail.mail',
		'type'	=> 'to' // can be 'to','cc','bcc'
	),
);

# array of vars to use on mandril template
$array_of_vars = array('var_1' => 'value', 'var_2' => 'value' );


scheduleHtml() and scheduleTemplate() will add to mail to the sending queue by adding an entry to the db, but leaves the sending up to the sending scheduled mail job;

sendHtml() and sendTemplate() will add to mail to the sending queue AND attempt to send right away.

# simplest use, using default configuration, which is set on config/config.php

$html = '<h2>Hello There</2>';

Mailer::instance()
	->setSubject('subject')
	->setRecipients($recipients)
	->scheduleHtml($html);
	
Mailer::instance()
	->setSubject('subject')
	->setRecipients($recipients)
	->scheduleTemplate('template-slug', $array_of_vars);


# Passing array of configuration to override defaults

$configs = array('from_email' => 'noreply@example.com');
	
Mailer::instance($configs)//'mandrill' or other email service
	->setSubject('subject')
	->setRecipients($recipients)
	->scheduleHtml($html);


# You can also set configuration after instantiating class

$mailer = Mailer::instance();

# with array

$mailer->setConfigs($config);// with array

# individually

$mailer->setConfig('api_key', $config['api_key']);
$mailer->setConfig('pretend', true);
$mailer->setConfig('pretend_email', 'development@mail.mail');
$mailer->setConfig('from_email', 'development@mail.mail');

$result = $mailer->setSubject('subject')
->setRecipients($recipients)
->sendHtml($html);

print_r($result);

# adding attachments

Mailer::instance()
	->setSubject('subject')
	->setRecipients($recipients)
	->addAttachment('attachment_name.txt', 'This is an attachment')
	->scheduleTemplate('template-slug', $array_of_vars);

# send mail right away (attempt to send the mail right after saving the entry in db )

Mailer::instance()
	->setSubject('subject')
	->setRecipients($recipients)
	->sendHtml($html);
	
# To run sending scheduled mail job
Mailer::instance()->sendScheduled();

for Mandrill templates, we use 'handlebars' for dynamic content:

https://mandrill.zendesk.com/hc/en-us/articles/205582537-Using-Handlebars-for-Dynamic-Content

