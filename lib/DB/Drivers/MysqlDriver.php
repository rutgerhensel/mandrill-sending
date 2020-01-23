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
			'recipients_json'	=> serialize($mail['recipients']),
			'payload_json'		=> serialize($mail['payload']),
			'attempts'		=> '0',
		);
		
		$fields = array();
		foreach($entry as $field => $value)
		{
			if( ! is_null($value))
			{
				$fields[] = "`{$field}` = '" . mysql_real_escape_string($value) . "'";
			}
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
		foreach($updates as $field => $value)
		{
			$fields[] = "`{$field}` = '" . mysql_real_escape_string($value) . "' ";
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
	
	public function getRejectsList($from, $to)
	{
		$sql = "
			SELECT * 
			FROM `" . $this->getConfig('prefix', '') . "mailer_rejects`
			WHERE DATE(`last_event_at`) >= '{$from}'
			AND DATE(`last_event_at`) <= '{$to}'
			ORDER BY last_event_at DESC
		";//die($sql);
		
		if( ! $res = mysql_query($sql) )
		{
			$this->last_error = mysql_error();
			
			return false;
		}
		
		$entries = array();
		while($entry = mysql_fetch_assoc($res))
		{
			unset($entry['id']);
			$entries[] = $entry;
		}
		
		return $entries;
	}
	
	public function syncRejectslist(Array $list)
	{
		$response = array('list_total' => count($list));
		
		$errors = array();
		$adds = array();
		$updates = array();
		
		# if we do not have anything to work with we bail
		if(empty($list))
		{
			$response['errors'] = $errors;
			$response['adds'] = $adds;
			$response['updates'] = $updates;
			return $response;
		}
		
		$emails_from_list = array_map(function($row)
		{
			return $row['email'];
		},
		$list);
		
		#get existing rows
		$sql = "SELECT
					`email`, `reason`
				FROM `" . $this->getConfig('prefix', '') . "mailer_rejects`
				WHERE `email` IN('" . implode("','", $emails_from_list) . "')
		";
		
		$res = mysql_query($sql);
		
		$reject_reasons = array();
		$existing_emails = array();
		while($row = mysql_fetch_assoc($res))
		{
			$reject_reasons[$row['email']] = $row['reason'];
			$existing_emails[] = $row['email'];
		}
		
		$response['existing'] = count($existing_emails);
		
		$fields = $this->getTableFields('mailer_rejects');
		
		#remove id and created at
		foreach($fields as $index=>$field)
		{
			if(in_array($field, array('id','created_at')))
			{
				unset($fields[$index]);
			}
		}
		
		$errors = array();
		$adds = array();
		$updates = array();
		
		$now = date('Y-m-d H:i:s');

		foreach($list as $row)
		{
			$sql = false;
			
			$row = array_map('mysql_real_escape_string', $row);
			
			$row['updated_at'] = $now;
			
			if( in_array($row['email'], $existing_emails) )
			{
				# we only update if row already exist and the reason has been updated
				if($reject_reasons[$row['email']] != $row['reason'])
				{
					$updates[] = $row['email'];
					
					foreach($fields as $index => $field)
					{
						$sub_sql[] = "`{$field}` = '{$row[$field]}' ";
					}
					
					$sql = "UPDATE `" . $this->getConfig('prefix', '') . "mailer_rejects` SET " . implode(',', $sub_sql);
					$sql .= " WHERE `email` = '{$row['email']}'";
				}
			}
			else
			{
				$adds[] = $row['email'];
				
				$sql = "INSERT INTO `" . $this->getConfig('prefix', '') . "mailer_rejects` (" . implode(',', $fields) . ", created_at) VALUES ";
				$sql .= "('" . implode("','", $row) . "', '" . $now . "') ";
			}
			
			if($sql)
			{
				if( !mysql_query($sql) )
				{
					$errors[$row['email']] = mysql_error();
				}
			}
		}
		
		$response['errors'] = $errors;
		$response['adds'] = $adds;
		$response['updates'] = $updates;
		
		return $response;
	}
	
	public function getTableFields($table)
	{
		$table = mysql_real_escape_string($table);
		$sql = "SHOW COLUMNS FROM `" . $this->getConfig('prefix', '') . "{$table}`";
		
		if( ! $res = mysql_query($sql) )
		{
			$this->last_error = mysql_error();
			
			return false;
		}
		
		$fields = array();
		
		while($row = mysql_fetch_assoc($res))
		{
			$fields[] = $row['Field'];
		}
		
		return $fields;
	}
	
	public function getLastError()
	{
		return $this->last_error;
	}
}
