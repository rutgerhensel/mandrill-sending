<?php namespace Mailer\DB;

use Mailer\Configurable;

class DB extends Configurable
{
	public static $defaults = array();

	public static function instance()
	{
		$configuration = static::$defaults;
		
		$driver_name = isset($configuration['driver']) ? $configuration['driver'] : '';
		$driver_name = static::camelCase($driver_name);
		$drivers_path = dirname(__FILE__) . "/Drivers";
		
		$driver_name = ucfirst($driver_name);
		
		require_once("{$drivers_path}/{$driver_name}.php");
		
		$class = "Mailer\DB\Drivers\\{$driver_name}";
		
		if( ! class_exists($class))
		{
			throw new \Exception("Class '$class' does not exist.");
		}
		
		$settings = isset($configuration['credentials']) ? $configuration['credentials'] : array();
		
		$settings['tables'] = array('mailer_rejects', 'scheduled_emails');
		$settings['rejects_updatable'] = array('reason','detail','expired','added_at','last_event_at','expires_at');
		
		return new $class($settings);
	}
	
	private static function camelCase($service_name)
	{
		$service_name = ucwords(str_replace(array('-', '_'), ' ', $service_name));

		return str_replace(' ', '', $service_name);
	}
}