<?php namespace Mailer\Services\Phpmailer;

use Mailer\Services\ServiceContract;
use Mailer\Configurable;

use PHPMailer\PHPMailer\PHPMailer;

class Mailer extends Configurable implements ServiceContract
{
	private $last_error;

	public function __construct($configs = array())
	{
		$this->setConfigs($configs);
	}

	public function preparePayload(Array $mail)
	{
		# nothing to proccess
		return $mail;
	}
	
	
	public function send(Array $mail)
	{
		$result = array(
			'recipients'=> $this->extractRecipients($mail),
			'sent'      => false,
			'attempted' => true
		);
		
		$mail = unserialize($mail['payload_json']);
		
		//Create a new PHPMailer instance
		$mailer = new PHPMailer;
		
		//Set the subject line
		$mailer->Subject = $mail['subject'];
		
		//Set who the message is to be sent from
		$mailer->setFrom($mail['from_email'], $mail['from_name']);

		foreach($mail['recipients'] as $recipient)
		{
			$type = isset($recipient['type']) ? $recipient['type'] :  'to';
			
			if($type == 'bcc')
			{
				$mailer->addBCC($recipient['email'], $recipient['name']);
			}
			if($type == 'cc')
			{
				$mailer->addCC($recipient['email'], $recipient['name']);
			}
			else
			{
				$mailer->addAddress($recipient['email'], $recipient['name']);
			}
		}
		
		#will always send HTML
		$mailer->isHTML(true);
		
		$mailer->Body = $mail['type'] == 'template' ? $this->getTemplateContents($mail) : $mail['content'];
		
		if(isset($mail['attachments']))
		{
			foreach($mail['attachments'] as $attachment)
			{
				$mailer->addStringAttachment(
					$attachment['content'],
					$attachment['name'],
					PHPMailer::ENCODING_BASE64,
					$attachment['type']
				);
			}
		}
		
		if ( $mailer->send() )
		{
			$result['sent'] = true;
			
			return $result;
		}
		else
		{
			$result['response'] = $mailer->ErrorInfo;
		}
		
		return $result;
	}
	
	public function fetchRejectslist()
	{
		return array();
	}
	
	private static function extractRecipients($mail)
	{
		$recipients = unserialize($mail['recipients_json']);
		
		$emails = array();
		foreach($recipients as $recipient)
		{
			$emails[] = $recipient['email'];
		}
		
		return $emails;
	}
	
	private function getTemplateContents($mail)
	{
		$mustache = new \Mustache_Engine;
		
		$path = rtrim($this->getConfig('templates_path'),'/');
		
		$template = @file_get_contents("{$path}/{$mail['template_slug']}.php");
		
		return $mustache->render($template, $mail['variables']);
	}
	
	public function getLastError()
	{
		return $this->last_error;
	}

}