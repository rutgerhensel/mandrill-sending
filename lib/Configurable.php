<?php namespace Mailer;

class Configurable
{
	protected $configs = array();
	
	protected static function loadConfigsFromFile($file)
	{
		$configs_path = dirname(__FILE__) . '/../config';
		
		#first we get the default configuration
		$configs = require("{$configs_path}/{$file}.php");
		
		# detect environment
		$env = static::getCurrentEnvironment();
		
		# enviromnent detected, then we load the configuration for that environment
		if($env !== false)
		{
			if(file_exists("{$configs_path}/{$env}/{$file}.php") )
			{
				$env_configs = require("{$configs_path}/{$env}/{$file}.php");
				
				$configs = array_replace_recursive($configs, $env_configs);
			}
		}
		
		return $configs;
	}
	
	private static function getCurrentEnvironment()
	{
		$envs = require(dirname(__FILE__) . '/../environments.php');
		
		$current_host = $_SERVER['HTTP_HOST'];
		
		return array_search($current_host, $envs);
	}

	public function setConfig($key, $value)
	{
		$this->configs[$key] = $value;
		
		return $this;
	}
	
	public function setConfigs(Array $configs)
	{
		foreach($configs as $key=>$value)
		{
			$this->setConfig($key, $value);
		}
		
		return $this;
	}
	
	protected function getConfig($key, $default = null)
	{
		if(isset($this->configs[$key]))
		{
			return $this->configs[$key];
		}
		
		return $default;
	}
}