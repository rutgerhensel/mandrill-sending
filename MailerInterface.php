<?php
	
Interface MailerInterface
{
	public function sendHtml($body);
	
	public function sendTemplate($template_slug, $variables = array());
	
	public function setRecipients($recipients = array());
	
	private function getRecipients();
}