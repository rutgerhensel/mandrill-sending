<?php namespace Mailer\DB;
	
/*
	All DB drivers should implement this functions in their own way
*/

Interface DriverContract
{
	public function saveEntry(Array $mail);
	
	public function updateEntry(Array $email, Array $updates);
	
	public function getUnsentEntries($take);
	
	public function getFailedEntries($max_attempts, $take);
	
	public function lockEntries(Array $mails);
	
	public function deleteOldEntries($criteria);
	
	public function syncRejectslist(Array $list);
	
	public function getRejectsList($from, $to);
	
	public function getTableFields($table);
	
	public function getLastError();
}