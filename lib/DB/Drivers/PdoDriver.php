<?php namespace Mailer\DB\Drivers;

use Mailer\DB\DriverContract;
use Mailer\Configurable;
use PDO;

class PdoDriver extends Configurable implements DriverContract
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
		
		try
		{
			$conn = new PDO("mysql:host={$host};dbname={$dbname}", $user, $pass);
		}
		catch (\PDOException $e)
		{
			$this->last_error = $e->getMessage();
			return false;
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
		
		$sql = "
			INSERT INTO `" . $this->getConfig('prefix', '') . "scheduled_emails`
			SET
				`created_at` = ?,
				`updated_at` = ?,
				`attempted_at` = ?,
				`esp` = ?,
				`template_slug` = ?,
				`subject` = ?,
				`recipients_json` = ?,
				`payload_json`  = ?,
				`attempts` = ?
		";
		
		$sth = static::$connection->prepare($sql);
	
		if( ! $success =  $sth->execute(array_values($entry) ) )
		{
			$err = $sth->errorInfo();
			$this->last_error = isset($err[2]) ? $err[2] : 'Error Inserting Entry.';
			
			return false;
		}
		
		$entry['id'] = static::$connection->lastInsertId();
		
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
			WHERE `id` = ?
		";
		
		$values[] = $mail['id'];
		
		$sth = static::$connection->prepare($sql);
	
		if( ! $success =  $sth->execute( $values ) )
		{
			$err = $sth->errorInfo();
			$this->last_error = isset($err[2]) ? $err[2] : 'Error Inserting Entry.';
			
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
		
		$stmt = static::$connection->query($sql);
		
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
		
		$stmt = static::$connection->query($sql);
		
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
			
			$stmt = static::$connection->query($sql);
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
			
			$sth = static::$connection->prepare($sql);
			
			if( ! $success =  $sth->execute( array($slug ) ) )
			{
				$err = $sth->errorInfo();
				$result[] = isset($err[2]) ? $err[2] : 'Error Deleting Entry.';
				
			}
			else
			{
				$deleted = $sth->rowCount();
				
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
		
		$stmt = static::$connection->query($sql);
		
		$raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
		
		$entries = array();
		foreach($raw as $row)
		{
			unset($row['id']);
			$entries[] = $row;
		}
		
		return $entries;
	}
	
	public function syncRejectslist(Array $list)
	{
		$response = array('list_total' => count($list));
		
		# if we do not have anything to work with we bail
		if(empty($list))
		{
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
		
		$stmt = static::$connection->query($sql);
		
		$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
		
		$reject_reasons = array();
		$existing_emails = array();
		foreach($res as $row)
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
			
			$row = array_map(function($value)
			{
				if(is_null($value))
				{
					$value = '';
				}
				
				return $value;
			}
			, $row);
			
			$row['updated_at'] = $now;
			
			if( in_array($row['email'], $existing_emails) )
			{
				# we only update if row already exist and the reason has been updated
				if($reject_reasons[$row['email']] != $row['reason'])
				{
					$updates[] = $row['email'];
					
					foreach($fields as $index => $field)
					{
						$sub_sql[] = "`{$field}` = ? ";
					}
					
					$row['compare_email'] = $row['email'];
					
					$sql = "UPDATE `" . $this->getConfig('prefix', '') . "mailer_rejects` SET " . implode(',', $sub_sql);
					$sql .= " WHERE `email` = ?";
				}
			}
			else
			{
				$adds[] = $row['email'];
				
				$row['created_at'] = $now;
				
				$sql = "INSERT INTO `" . $this->getConfig('prefix', '') . "mailer_rejects` (" . implode(',', $fields) . ", created_at) VALUES ";
				$sql .= "(" . implode(",", array_fill(0, count($row), '?')) . ") ";
			}
			
			if($sql)
			{
				$sth = static::$connection->prepare($sql);
			
				if( ! $success =  $sth->execute( array_values($row) ) )
				{
					var_dump($row);
					$err = $sth->errorInfo();
					$errors[$row['email']] = isset($err[2]) ? $err[2] : 'Error Inserting Entry.';
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
		if( ! in_array($table, $this->getConfig( 'tables', array() ) ) )
		{
			$this->last_error = "Invalid table name '{$table}'";
			
			print_r($this->last_error);
			
			return false;
		}
		
		$sql = "SHOW COLUMNS FROM `" . $this->getConfig('prefix', '') . "$table`";
		
		
		if (! $stmt = static::$connection->query($sql) )
		{
			$this->last_error = 'Error Fetching table fields.';
			
			return false;
		}
		
		return $stmt->fetchAll(PDO::FETCH_COLUMN);
	}
	
	public function getLastError()
	{
		return $this->last_error;
	}
}
