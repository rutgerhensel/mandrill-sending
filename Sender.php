<?php
	
class MailSender
{
	public static function sendscheduledMail()
	{
		global $db;
		global $cfg;
		
		$limit = isset($cfg['mandrill']['send_per_run']) ? $cfg['mandrill']['send_per_run'] : 5;
		
		#get some not sent entries
		$res = mysql_query("
			SELECT *
			FROM `{$cfg['db_prefix']}scheduled_emails`
			WHERE `sent_at` IS NULL
			AND `attempted_at` IS NULL
			LIMIT " . $limit . "
		") or die(mysql_error());
		
		$mails = array();
		
		while($row = mysql_fetch_assoc($res))
		{
			$mails[] = $row;
		}
		
		$failedNumToTake = (count($mails) == $limit ? 1 : ( $limit - count($mails) ) );
		$ten_min_ago = date('Y-m-d H:i:s', strtotime('-10 minute'));
		$attemptsLimit = isset($cfg['mandrill']['resend_attempts']) ? $cfg['mandrill']['resend_attempts'] : 5;
		
		$res = mysql_query("
			SELECT *
			FROM `{$cfg['db_prefix']}scheduled_emails`
			WHERE `sent_at` IS NULL
			AND `attempted_at` < '{$ten_min_ago}'
			AND `attempts` < '" . $attemptsLimit . "'
			LIMIT " . $failedNumToTake . "
		") or die(mysql_error());
		
		while($row = mysql_fetch_assoc($res))
		{
			$mails[] = $row;
		}
		
		/*
			if we want to increase number of rows to take,
			we need to make sure all of them are locked by
			setting their attempted_at to 'now'
			
		*/
		
		if( ! count($mails))
		{
			$res = static::deleteOldEntries();
			
			return array('nothing to send', $res);
		}
		
		$now = date('Y-m-d H:i:s');
		
		foreach($mails as $mail)
		{
			mysql_query("UPDATE `{$cfg['db_prefix']}scheduled_emails` SET `attempted_at` = '$now' WHERE `id` = {$mail['id']}") or die(mysql_error());
			
			$Sender = static::getMailSenderClass($mail['esp']);
			
			$updates = "`attempted_at` = '" . date('Y-m-d H:i:s') . "'";
			
			$result = $Sender::sendscheduledMail($mail);
			
			if( isset($result['sent']) && $result['sent'] === true )
			{
				$updates = "`sent_at` = '" . date('Y-m-d H:i:s') . "'";
			}
			else
			{
				$updates = "`attempts` = '" . ($mail['attempts'] + 1) . "'";
				
				if(($mail['attempts'] + 1) == $attemptsLimit)
				{
					static::sendFailedWarning($result, $mail);
				}
			}
			
			$results[] = $result;
			
			mysql_query("UPDATE `{$cfg['db_prefix']}scheduled_emails` SET $updates WHERE `id` = {$mail['id']}") or die(mysql_error());
		}
		
		return $results;
	}
	
	private static function getMailSenderClass($esp)
	{
		$class = static::getMailSenderClassName($esp);
		
		if(! is_dir(dirname(__FILE__) . "/vendor/{$esp}/"))
		{
			throw new Exception("Mailer '{$esp}' does not exist.");
		}
		
		require_once(dirname(__FILE__) . "/vendor/{$esp}/{$class}.php");
		
		if( ! class_exists($class))
		{
			throw new Exception("Class '$class' does not exist.");
		}
		
		return $class;
	}
	
	private static function getMailSenderClassName($value)
	{
		$value = ucwords(str_replace(array('-', '_'), ' ', $value));

		return str_replace(' ', '', $value) . '_Sender';
	}
	
	private static function deleteOldEntries()
	{
		global $db;
		global $cfg;
		
		$slugs = isset($cfg['mandrill']['delete']) ? $cfg['mandrill']['delete'] : array();
		
		// make sure slugs is an array
		if( ! is_array($slugs) )
		{
			$slugs = array();
		}
		
		$res = array();
		
		foreach($slugs as $slug=>$date)
		{
			$deleteDate = date('Y-m-d H:i:s', strtotime($date));
			
			if( ! $deleteDate )
			{
				$res[] = "Bad date string('{$date}') for slug {$slug}";
				continue;
			}
			
			mysql_query("
				DELETE 
				FROM `{$cfg['db_prefix']}scheduled_emails`
				WHERE `sent_at` IS NOT NULL
				AND `template_slug` = '" . $slug . "'
				AND `created_at` < '" . $deleteDate . "'
				LIMIT 5
			");
			
			$deleted = mysql_affected_rows();
			
			$res[] = "{$deleted} deleted for slug {$slug}";
		}
		
		return $res;
	}

	private static function sendFailedWarning($result, $mail)
	{
		global $cfg;
		
		$mail['payload'] = json_decode($mail['payload_json'], true);
		
		unset($mail['payload_json']);
		
		$mail = static::mailerArrayFlatten($mail);
		$result = static::mailerArrayFlatten($result);
		
		$msg = "--- Mail Info ---\r\n\r\n";
		
		foreach($mail as $title=>$row)
		{
			$msg .= "$title: $row \r\n";
		}
		
		$msg .= "\r\n--- Response Info ---\r\n\r\n";
		
		foreach($result as $title=>$row)
		{
			$msg .= "$title: $row \r\n";
		}
		
		mail($cfg['fails_to'], "[" . $_SERVER['HTTP_HOST'] . "] Mail sending failed 5 times", $msg);
	}
	
	private static function mailerArrayFlatten($array)
	{
		$return = array();
		foreach ($array as $key => $value)
		{
			if (is_array($value))
			{
				$return = array_merge($return, static::mailerArrayFlatten($value));
			}
			else
			{
				$return[$key] = $value;
			}
		}
		return $return;
	}
}