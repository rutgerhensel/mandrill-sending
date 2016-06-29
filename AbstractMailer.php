<?php
	
abstract class AbstractMailer implements MailerInterface
{
	private $configs;
	private $errors;
	private $recipients;
	private $subject;
	
	public function __construct($configs = array())
	{
		# lets set some defaults, they will be overridden if in $configs array
		$this->setConfig('pretend', false);
		
		$this->setConfigs($configs);
		$this->errors = array();
	}
	
	public function setConfig($key, $value)
	{
		$this->configs[$key] = $value;
		
		return $this;
	}
	
	public function getConfig($key, $default = null)
	{
		if(isset($this->configs[$key]))
		{
			return $this->configs[$key];
		}
		
		return $default;
	}
	public function setConfigs(Array $configs)
	{
		foreach($configs as $key=>$value)
		{
			$this->setConfig($key, $value);
		}
		
		return $this;
	}
	
	public function setSubject($subject)
	{
		$this->subject = $subject;
		
		return $this;
	}
	
	public function getErrors()
	{
		return $this->errors;
	}
	
	private function sendMailNative($mail, $content)
	{
			$headers = array("From: {$fromEmail}",'MIME-Version: 1.0','Content-Type: text/html; charset=UTF-8');
			$mail = env('DEV_EMAIL', 'development@kneadle.com');
			
			mail($mail, "Mandrill Error [{$subject} to {$toemail}]", $html , implode("\r\n", $headers));
	}
	
	public function sendHtml($body);
	
	public function sendTemplate($template_slug, $variables = array());
	
	public function setRecipients($recipients = array());
	
	private function getRecipients();
}