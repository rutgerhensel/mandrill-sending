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
		$keys = explode('.', $key);
		
		$configs = &$this->configs;
		
		while (count($keys) > 1)
		{
			$key = array_shift($keys);
		
			// If the key doesn't exist at this depth, we will just create an empty array
			// to hold the next value, allowing us to create the arrays to hold final
			// values at the correct depth. Then we'll keep digging into the array.
			if (! isset($configs[$key]) || ! is_array($configs[$key]))
			{
				$configs[$key] = [];
			}
		
			$configs = &$configs[$key];
		}
		
		$configs[array_shift($keys)] = $value;
		
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