<?php namespace Mailer;

class Configurable
{
	protected $configs = array();
	
	public static function setDefaults(Array $configs)
	{
		if( isset(static::$defaults) )
		{
			static::$defaults = $configs;
		}
	}
	
	protected function loadDefaultConfiguration()
	{
		if(isset(static::$defaults) && is_array(static::$defaults))
		{
			$this->setConfigs(static::$defaults);
		}
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