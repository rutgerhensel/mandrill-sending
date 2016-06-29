<?php

require_once dirname(__FILE__) . '/vendor/autoload.php';
	
class Mandrill_Sender
{
	public static function sendscheduledMail(Array $mail)
	{
		global $cfg;
		$mandrill = $cfg['mandrill'];
		
		$result = array('recipients' => static::extractRecipients($mail), 'sent' => false);
		
		try
		{
			$mandrill = new Mandrill($mandrill['api_key']);
			
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
		catch(Exception $e)
		{
			$result['response'] = $e->getMessage();
		}
		
		return $result;
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
}