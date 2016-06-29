install Mailer package

include mailer/Mailer.php so it can be use globally,
you can also just include it just before using it.

# available configuration
$config = array(
	'api_key' => '',
	'from_email' => '',
	'from_name' => '',
	
	'pretend' = false,
	'pretend_email' => '',
	
	'include_unsubscribe_link'=> true,
	'unsubscribe_link' => "
		<br />
		<br />
		<p style=\"font-family:'Tahoma','sans-serif';font-size:10px\">
			To unsubscribe <a href=\"*|UNSUB:http://you.site.here.com/unsub|*\">click here</a>. 
		</p>
	";

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

# single recipients
$recipients = array(
	'name'	=> 'full name',
	'first'	=> 'first name',
	'last'	=> 'last name',
	'email'	=> 'valid@mail.mail',
	'type'	=> 'to' // can be 'to','cc','bcc'
);

# array of vars to use on mandril template
$array_of_vars = array('var_1' => 'value', 'var_2' => 'value' );

Mailer::instance('mandril', $config) //'mandrill' or other email service
	->setSubject('subject')
	->setRecipients($recipients)
	->sendTemplate('mandrill-template-slug', $array_of_vars);
	
Mailer::instance('mandrill',$config)//'mandrill' or other email service
	->setSubject('subject')
	->setRecipients($recipients)
	->sendHtml($html);
	
# You can also set configuration after instantiating class
$mailer = Mailer::instance('mandrill');

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

for Mandrill templates, we use 'handlebars' for dynamic content:

https://mandrill.zendesk.com/hc/en-us/articles/205582537-Using-Handlebars-for-Dynamic-Content

database


CREATE TABLE `basejump_scheduled_emails` (
  `id` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `attempted_at` datetime DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `esp` varchar(64) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'mandrill',
  `template_slug` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `subject` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  `recipients_json` text COLLATE utf8_unicode_ci NOT NULL,
  `payload_json` mediumtext COLLATE utf8_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

ALTER TABLE `basejump_scheduled_emails`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `basejump_scheduled_emails`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

