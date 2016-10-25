<?php namespace Mailer\Services\Mandrill;

use Mailer\Services\ServiceContract;
use Mailer\Configurable;

class Mailer extends Configurable implements ServiceContract
{

	public function __construct($configs = array())
	{
		$this->setConfigs($configs);
		
		$rejects_table_mapping = array(
			'email' => 'email',
			'reason' => 'reason',
			'detail' => 'detail',
			'expired' => 'expired',
			'added_at' => 'created_at',
			'last_event_at' => 'last_event_at',
			'expires_at' => 'expires_at',
		);
		
		$this->setConfig('rejects_table_mapping', $rejects_table_mapping);
	}

	public function preparePayload(Array $mail)
	{
		$payload = array(
			'subject' => $mail['subject'],
			'from_name' => $mail['from_name'],
			'from_email' => $mail['from_email'],
			'to' => $mail['recipients'],
			'attachments' => $mail['attachments'],
		);
		
		if($mail['type'] == 'template')
		{
			$global_vars = array();
			foreach($mail['variables'] as $name => $content)
			{
				$global_vars[] = array(
					'name' => $name,
					'content' => $content 
				);
			}
		
			$payload = array_merge($payload, array(
				'merge_language' => 'handlebars',
				'global_merge_vars' => $global_vars
			));
		}
		
		if($mail['type'] == 'html')
		{
			$payload = array_merge($payload, array(
				'html' => $mail['content'],
			));
		}		
		return $this->addSubAccount($payload);;
	}
	
	
	public function send(Array $mail)
	{
		$result = array('recipients' => $this->extractRecipients($mail), 'sent' => false);
		
		try
		{
			$mandrill = new \Mandrill($this->getConfig('api_key'));
			
			if($mail['template_slug'])
			{
				$response = $mandrill->messages->sendTemplate($mail['template_slug'], array(), json_decode($mail['payload_json'], true));
			}
			else
			{
				$response = $mandrill->messages->send(json_decode($mail['payload_json'], true));
			}
			
			$result['response'] = $response;
			
			# if we did not get an array something went wrong
			if( is_array($response))
			{
				/*
					for each email recepient we get an attempt entry,
					as long as one of the attempt was successful, we
					can mark the email as sent
				*/
				foreach($response as $attempt)
				{
					$status = isset($attempt['status']) ? $attempt['status'] : 'failed';
					
					if( in_array($status , array("sent", "queued", "scheduled")) )
					{
						$result['sent'] = true;
						break;
					}
				}
			}
		}
		catch(\Exception $e)
		{
			$result['response'] = $e->getMessage();
		}
		
		return $result;
	}
	
	public function fetchRejectslist()
	{
		try
		{
			$mandrill = new \Mandrill($this->getConfig('api_key'));
			
			$raw = $mandrill->rejects->getList(null, true, $this->getConfig('subaccount', null));
		}
		catch(\Exception $e)
		{
			return false;
		}
		
		$fields = $this->getConfig('rejects_table_mapping', array());
		$rejects = array();
		
		# we need to make sure indexes match table fields
		foreach($raw as $row)
		{
			$reject = array();
			foreach($fields as $table=>$list)
			{
				$reject[$table] = $row[$list];
			}
			
			$rejects[] = $reject;
		}
		
		return $rejects;
	}
	
	private static function extractRecipients($mail)
	{
		$recipients = json_decode($mail['recipients_json'], true);
		
		$emails = array();
		foreach($recipients as $recipient)
		{
			$emails[] = $recipient['email'];
		}
		
		return $emails;
	}
	
	private function addSubAccount($message)
	{
		
		if($subaccount = $this->getConfig('subaccount', false))
		{
			$message['subaccount'] = $subaccount;
		}
		
		return $message;
	}

}