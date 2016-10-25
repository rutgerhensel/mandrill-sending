<?php namespace Mailer\Services;
	
/*
	All services should implement this functions in their own way
*/

Interface ServiceContract
{
	public function preparePayload(Array $mail);
	
	public function send(Array $mail);
	
	public function fetchRejectslist();
}