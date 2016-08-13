<?php namespace Mailer\DB\Drivers;

use Mailer\DB\DriverContract;
use Mailer\Configurable;

class MysqlDriver extends Configurable implements DriverContract
{
	# recycle db connection
	private static $connection;
	
	private $last_error;
	
	public function __construct(Array $credentials)
	{
		$this->setConfigs($credentials);
		
		if(is_null(static::$connection))
		{
			static::$connection = $this->createConnection();
		}
	}
	
	private function createConnection()
	{
		$host	= $this->getConfig('host', '');
		$dbname	= $this->getConfig('database', '');
		$user	= $this->getConfig('username', '');
		$pass	= $this->getConfig('password', '');
		
		$conn = mysql_connect($host, $user, $pass);
		
		if (!$conn)
		{
			$this->last_error = mysql_error();
		}
		else
		{
			if( ! mysql_select_db($dbname) )
			{
				$this->last_error = mysql_error();
			}
		}
		
		return $conn;
	}
	
	public function saveEntry(Array $mail)
	{
		$now = date('Y-m-d H:i:s');
		
		$attempted_at = ($mail['attempt'] ? $now : null);
		
		$entry = array(
			'created_at'		=> $now,
			'updated_at'		=> $now,
			'attempted_at'		=> $attempted_at,
			'esp'				=> $mail['esp'],
			'template_slug' 	=> $mail['template'],
			'subject'			=> $mail['subject'],
			'recipients_json'	=> json_encode($mail['recipients']),
			'payload_json'		=> json_encode($mail['payload']),
			'attempts'		=> '0',
		);
		
		$fields = array();
		foreach($updates as $field => $value)
		{
			$fields[] = "`{$field}` = '" . mysql_real_escape_string($value) . "'";
		}
		
		$sql = "
			INSERT INTO `" . $this->getConfig('prefix', '') . "scheduled_emails`
			SET
				" . implode(',', $fields) . "
		";
	
		if( ! $success = mysql_query($sql) )
		{
			$this->last_error = mysql_error();
			
			return false;
		}
		
		$entry['id'] = mysql_insert_id();
		
		return $entry;
	}
	
	public function updateEntry(Array $mail, Array $updates)
	{
		$fields = array();
		$values = array();
		foreach($updates as $field => $value)
		{
			$fields[] = "`{$field}` = ? ";
			$values[] = $value;
		}

		$sql = "
			UPDATE `" . $this->getConfig('prefix', '') . "scheduled_emails`
			SET
				" . implode(',', $fields) . "
			WHERE `id` = '" . $mail['id'] . "'
		";
		
		if( ! $success = mysql_query($sql) )
		{
			$this->last_error = mysql_error();
			
			return false;
		}
		
		return $mail;
	}
	
	public function getUnsentEntries($take)
	{
		$sql = "
			SELECT * 
			FROM `" . $this->getConfig('prefix', '') . "scheduled_emails`
			WHERE `sent_at` IS NULL
			AND `attempted_at` IS NULL
			LIMIT 0, $take
		";
		
		if( ! $res = mysql_query($sql) )
		{
			$this->last_error = mysql_error();
			
			return false;
		}
		
		$mails = array();
		while($mail = mysql_fetch_assoc($res))
		{
			$mails[] = $mail;
		}
		
		return $mails;
	}
	
	public function getFailedEntries($max_attempts, $take)
	{
		$ten_min_ago = date('Y-m-d H:i:s', strtotime('-10 minute'));
		
		$sql = "
			SELECT * 
			FROM `" . $this->getConfig('prefix', '') . "scheduled_emails`
			WHERE `sent_at` IS NULL
			AND `attempted_at` < '{$ten_min_ago}'
			AND `attempts` < '{$max_attempts}'
			LIMIT 0, $take
		";
		
		if( ! $res = mysql_query($sql) )
		{
			$this->last_error = mysql_error();
			
			return false;
		}
		
		$mails = array();
		while($mail = mysql_fetch_assoc($res))
		{
			$mails[] = $mail;
		}
		
		return $mails;
	}
	
	public function lockEntries(Array $mails)
	{
		$now = date('Y-m-d H:i:s');
		$ids = array();
		
		foreach($mails as $mail)
		{
			$ids[] = $mail['id'];
		}
		
		if(count($ids))
		{
			$sql = "
				UPDATE `" . $this->getConfig('prefix', '') . "scheduled_emails`
				SET `attempted_at` = '{$now}'
				WHERE `id` IN('" . implode("','", $ids) . "')
			";
			
			if( ! $res = mysql_query($sql) )
			{
				$this->last_error = mysql_error();
				
				return false;
			}
		}
	}
	
	public function deleteOldEntries($criteria)
	{
		// make sure slugs is an array
		if( ! is_array($criteria) )
		{
			$criteria = array();
		}
		
		$result = array();
		
		foreach($criteria as $slug=>$date)
		{
			
			if( ! $date = date('Y-m-d H:i:s', strtotime($date) ) )
			{
				$result[] = "Bad date string('{$date}') for slug {$slug}";
				continue;
			}
			
			$sql = "
				DELETE FROM `" . $this->getConfig('prefix', '') . "scheduled_emails`
				WHERE `sent_at` IS NOT NULL
				AND `template_slug` =  '$slug'
				AND `created_at` < '{$date}'
				LIMIT 5
			";
			
			if( ! $res = mysql_query($sql) )
			{
				$result[] = mysql_error();
				
			}
			else
			{
				$deleted = mysql_affected_rows();
				
				$result[] = "{$deleted} deleted for slug '{$slug}'";
			}
		}
		
		return $result;
	}
	
	public function getLastError()
	{
		return $this->last_error;
	}
}
