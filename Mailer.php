<?php
	
class Mailer 
{
	private static $mailer;
	private static $configs = array();
	
	public static function instance($mailer = null, $configs = null)
	{
		$Mailer = static::getMailerClass($mailer);
		
		if(is_null($configs))
		{
			$configs = static::$configs;
		}
		
		return static::$mailer = new $Mailer($configs);
	}
	
	public static function setconfigs(Array $configs)
	{
		static::$configs = $configs;
	}
	
	private static function getMailerClass($mailer)
	{
		$class = static::getMailerClassName($mailer);
		
		if(! is_dir(dirname(__FILE__) . "/vendor/{$mailer}/"))
		{
			throw new Exception("Mailer '{$mailer}' does not exist.");
		}
		
		require_once(dirname(__FILE__) . "/vendor/{$mailer}/{$class}.php");
		
		if( ! class_exists($class))
		{
			throw new Exception("Class '$class' does not exist.");
		}
		
		return $class;
	}
	
	private static function getMailerClassName($value)
	{
		$value = ucwords(str_replace(array('-', '_'), ' ', $value));

		return str_replace(' ', '', $value) . '_Mailer';
	}
}