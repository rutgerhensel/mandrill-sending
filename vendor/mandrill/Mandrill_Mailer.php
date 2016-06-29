<?php

require_once dirname(__FILE__) . '/vendor/autoload.php';

class Mandrill_Mailer {

	private $configs;
	private $errors;
	
	private $recipients;
	
	private $subject;
	
	private $attachments;
	
	public function __construct($configs = array())
	{
		# lets set some defaults, they will be overridden if in $configs array
		$this->setConfig('pretend', false);
		
		$this->setConfigs($configs);
		$this->errors = array();
		$this->attachments = array();
	}
	
	public static function instance($configs = null)
	{
		return new static($configs);
	}
	
	public function sendHtml($body, $send_now = false)
	{
		# add the unsubscribe link
		if($this->getConfig('include_unsubscribe_link', false) === true)
		{
			$body .= $this->getConfig('unsubscribe_link', '');
		}
		
		$message = array(
			'html' => $body,
			'subject' => $this->subject,
			'from_name' => $this->getConfig('from_name', ''),
			'from_email' => $this->getConfig('from_email', ''),
			'to' => $this->getRecipients()
		);
		
		$message = $this->addAttachments($message);
		$message = $this->addSubAccount($message);
		
		if($send_now)
		{
			try
			{
				$mandrill = new Mandrill($this->getConfig('api_key'));
				
				return $mandrill->messages->send($message);
			}
			catch(Exception $e)
			{
				// Mandrill errors are thrown as exceptions
				$this->errors[] = 'A mandrill error occurred: ' . get_class($e) . ' - ' . $e->getMessage();
				
				return $this->sendBackup($body);
			}
		}
		
		return $this->scheduleSending($this->getRecipients(), $this->subject, $message);
	}
	
	public function sendTemplate($template_slug, $variables = array(), $send_now = false)
	{
		#convert $variables array into the weird format required by mandrill API
		$global_vars = array();
		foreach($variables as $name => $content)
		{
			$global_vars[] = array(
				'name' => $name,
				'content' => $content 
			);
		}
		
		$message = array(
			'merge_language' => 'handlebars',
			'subject' => $this->subject,
			'from_name' => $this->getConfig('from_name', ''),
			'from_email' => $this->getConfig('from_email', ''),
			'to' => $this->getRecipients(),
			'global_merge_vars' => $global_vars
		);
		
		$message = $this->addAttachments($message);
		$message = $this->addSubAccount($message);
		
		if($send_now)
		{
			try
			{
		
				$mandrill = new Mandrill($this->getConfig('api_key'));
				
				return $mandrill->messages->sendTemplate($template_slug, array(), $message);
			}
			catch(Exception $e)
			{
				// Mandrill errors are thrown as exceptions
				$this->errors[] = 'A mandrill error occurred: ' . get_class($e) . ' - ' . $e->getMessage();
				
				return $this->sendBackup($body);
			}
		}
		
		return $this->scheduleSending($this->getRecipients(), $this->subject, $message, $template_slug);
	}
	
	public function setAttachment($content, $name)
	{
		$this->attachments[$name] = base64_encode($content);
		
		return $this;
	}
	
	public function setConfig($key, $value)
	{
		$this->configs[$key] = $value;
		
		return $this;
	}
	
	public function getConfig($key, $default = null)
	{
		if(isset($this->configs[$key]))
		{
			return $this->configs[$key];
		}
		
		return $default;
	}
	public function setConfigs(Array $configs)
	{
		foreach($configs as $key=>$value)
		{
			$this->setConfig($key, $value);
		}
		
		return $this;
	}
	
	public function setSubject($subject)
	{
		$this->subject = $subject;
		
		return $this;
	}
	
	public function setRecipients($recipients = array())
	{
		# make sure we have an array of arrays
		if( ! count(array_filter($recipients,'is_array')) )
		{
			$recipients = array($recipients);
		}
		
		$this->recipients = $recipients;
		
		return $this;
	}
	
	public function getErrors()
	{
		return $this->errors;
	}
	
	private function getRecipients()
	{
		if($this->getConfig('pretend', false) === true)
		{
			return array(
				array(
					'name'	=> '',
					'first'	=> '',
					'last'	=> '',
					'email'	=> $this->getConfig('pretend_email', ''),
					'type'	=> 'to'
				)
			);
		}
		
		return $this->recipients;
	}
	
	private function scheduleSending($recipients, $subject, $payload, $template_slug = null)
	{
		global $db;
		global $cfg;
		
		$now = date('Y-m-d H:i:s');
		$values = array(
			'created_at'		=> $now,
			'updated_at'		=> $now,
			'esp'				=> 'mandrill',
			'template_slug'		=> $template_slug,
			'subject'			=> $subject,
			'recipients_json'	=> json_encode($recipients),
			'payload_json'		=> json_encode($payload)
		);
		
		$fields = '';
		foreach($values as $field => $value)
		{
			$fields .= " `{$field}` = '" . mysql_real_escape_string($value) . "' ,";
		}
		
		$fields = preg_replace("/,$/", '', $fields);
		
		$res = mysql_query("INSERT INTO `{$cfg['db_prefix']}scheduled_emails` SET $fields");
		
		return $res;
	}
	
	private function sendBackup($content)
	{
		$headers = array("From: " . $this->getConfig('from_name', ''),'MIME-Version: 1.0','Content-Type: text/html; charset=UTF-8');
		$mail = $this->getConfig('pretend_email', '');
		
		$recipients = array();
		foreach($this->getRecipients() as $recipient)
		{
			$recipients[] = $recipient['email'];
		}
		
		if($mail)
		{
			return mail($mail, "Mandrill Error [" . $this->subject . " to " . implode(',', $recipients) . "]", $content , implode("\r\n", $headers));
		}
	}
	
	private function addAttachments($message)
	{
		if(count($this->attachments))
		{
			foreach($this->attachments as $n=>$att)
			{
				$message['attachments'][] = array(
					'type' => $this->getAttachmentTypeByName($n),
					'name' => $n,
					'content' => $att
				);
			}
		}
		
		return $message;
	}
	
	private function addSubAccount($message)
	{
		
		if($subaccount = $this->getConfig('subaccount', false))
		{
			$message['subaccount'] = $subaccount;
		}
		
		return $message;
	}
	
	private function getAttachmentTypeByName($name)
	{
		$ext = pathinfo($name, PATHINFO_EXTENSION);
		
		$mimes = $this->getMIMEtypes();
		
		if( ! isset($mimes[$ext]))
		{
			$ext  = 'bin';
		}
		
		return $mimes[$ext];
	}
	
	private function getMIMEtypes()
	{
		return array(
			'ai'      => 'application/postscript',
			'aif'     => 'audio/x-aiff',
			'aifc'    => 'audio/x-aiff',
			'aiff'    => 'audio/x-aiff',
			'asc'     => 'text/plain',
			'atom'    => 'application/atom+xml',
			'atom'    => 'application/atom+xml',
			'au'      => 'audio/basic',
			'avi'     => 'video/x-msvideo',
			'bcpio'   => 'application/x-bcpio',
			'bin'     => 'application/octet-stream',
			'bmp'     => 'image/bmp',
			'cdf'     => 'application/x-netcdf',
			'cgm'     => 'image/cgm',
			'class'   => 'application/octet-stream',
			'cpio'    => 'application/x-cpio',
			'cpt'     => 'application/mac-compactpro',
			'csh'     => 'application/x-csh',
			'css'     => 'text/css',
			'csv'     => 'text/csv',
			'dcr'     => 'application/x-director',
			'dir'     => 'application/x-director',
			'djv'     => 'image/vnd.djvu',
			'djvu'    => 'image/vnd.djvu',
			'dll'     => 'application/octet-stream',
			'dmg'     => 'application/octet-stream',
			'dms'     => 'application/octet-stream',
			'doc'     => 'application/msword',
			'dtd'     => 'application/xml-dtd',
			'dvi'     => 'application/x-dvi',
			'dxr'     => 'application/x-director',
			'eps'     => 'application/postscript',
			'etx'     => 'text/x-setext',
			'exe'     => 'application/octet-stream',
			'ez'      => 'application/andrew-inset',
			'gif'     => 'image/gif',
			'gram'    => 'application/srgs',
			'grxml'   => 'application/srgs+xml',
			'gtar'    => 'application/x-gtar',
			'hdf'     => 'application/x-hdf',
			'hqx'     => 'application/mac-binhex40',
			'htm'     => 'text/html',
			'html'    => 'text/html',
			'ice'     => 'x-conference/x-cooltalk',
			'ico'     => 'image/x-icon',
			'ics'     => 'text/calendar',
			'ief'     => 'image/ief',
			'ifb'     => 'text/calendar',
			'iges'    => 'model/iges',
			'igs'     => 'model/iges',
			'jpe'     => 'image/jpeg',
			'jpeg'    => 'image/jpeg',
			'jpg'     => 'image/jpeg',
			'js'      => 'application/x-javascript',
			'json'    => 'application/json',
			'kar'     => 'audio/midi',
			'latex'   => 'application/x-latex',
			'lha'     => 'application/octet-stream',
			'lzh'     => 'application/octet-stream',
			'm3u'     => 'audio/x-mpegurl',
			'man'     => 'application/x-troff-man',
			'mathml'  => 'application/mathml+xml',
			'me'      => 'application/x-troff-me',
			'mesh'    => 'model/mesh',
			'mid'     => 'audio/midi',
			'midi'    => 'audio/midi',
			'mif'     => 'application/vnd.mif',
			'mov'     => 'video/quicktime',
			'movie'   => 'video/x-sgi-movie',
			'mp2'     => 'audio/mpeg',
			'mp3'     => 'audio/mpeg',
			'mpe'     => 'video/mpeg',
			'mpeg'    => 'video/mpeg',
			'mpg'     => 'video/mpeg',
			'mpga'    => 'audio/mpeg',
			'ms'      => 'application/x-troff-ms',
			'msh'     => 'model/mesh',
			'mxu'     => 'video/vnd.mpegurl',
			'nc'      => 'application/x-netcdf',
			'oda'     => 'application/oda',
			'ogg'     => 'application/ogg',
			'pbm'     => 'image/x-portable-bitmap',
			'pdb'     => 'chemical/x-pdb',
			'pdf'     => 'application/pdf',
			'pgm'     => 'image/x-portable-graymap',
			'pgn'     => 'application/x-chess-pgn',
			'png'     => 'image/png',
			'pnm'     => 'image/x-portable-anymap',
			'ppm'     => 'image/x-portable-pixmap',
			'ppt'     => 'application/vnd.ms-powerpoint',
			'ps'      => 'application/postscript',
			'qt'      => 'video/quicktime',
			'ra'      => 'audio/x-pn-realaudio',
			'ram'     => 'audio/x-pn-realaudio',
			'ras'     => 'image/x-cmu-raster',
			'rdf'     => 'application/rdf+xml',
			'rgb'     => 'image/x-rgb',
			'rm'      => 'application/vnd.rn-realmedia',
			'roff'    => 'application/x-troff',
			'rss'     => 'application/rss+xml',
			'rtf'     => 'text/rtf',
			'rtx'     => 'text/richtext',
			'sgm'     => 'text/sgml',
			'sgml'    => 'text/sgml',
			'sh'      => 'application/x-sh',
			'shar'    => 'application/x-shar',
			'silo'    => 'model/mesh',
			'sit'     => 'application/x-stuffit',
			'skd'     => 'application/x-koan',
			'skm'     => 'application/x-koan',
			'skp'     => 'application/x-koan',
			'skt'     => 'application/x-koan',
			'smi'     => 'application/smil',
			'smil'    => 'application/smil',
			'snd'     => 'audio/basic',
			'so'      => 'application/octet-stream',
			'spl'     => 'application/x-futuresplash',
			'src'     => 'application/x-wais-source',
			'sv4cpio' => 'application/x-sv4cpio',
			'sv4crc'  => 'application/x-sv4crc',
			'svg'     => 'image/svg+xml',
			'svgz'    => 'image/svg+xml',
			'swf'     => 'application/x-shockwave-flash',
			't'       => 'application/x-troff',
			'tar'     => 'application/x-tar',
			'tcl'     => 'application/x-tcl',
			'tex'     => 'application/x-tex',
			'texi'    => 'application/x-texinfo',
			'texinfo' => 'application/x-texinfo',
			'tif'     => 'image/tiff',
			'tiff'    => 'image/tiff',
			'tr'      => 'application/x-troff',
			'tsv'     => 'text/tab-separated-values',
			'txt'     => 'text/plain',
			'ustar'   => 'application/x-ustar',
			'vcd'     => 'application/x-cdlink',
			'vrml'    => 'model/vrml',
			'vxml'    => 'application/voicexml+xml',
			'wav'     => 'audio/x-wav',
			'wbmp'    => 'image/vnd.wap.wbmp',
			'wbxml'   => 'application/vnd.wap.wbxml',
			'wml'     => 'text/vnd.wap.wml',
			'wmlc'    => 'application/vnd.wap.wmlc',
			'wmls'    => 'text/vnd.wap.wmlscript',
			'wmlsc'   => 'application/vnd.wap.wmlscriptc',
			'wrl'     => 'model/vrml',
			'xbm'     => 'image/x-xbitmap',
			'xht'     => 'application/xhtml+xml',
			'xhtml'   => 'application/xhtml+xml',
			'xls'     => 'application/vnd.ms-excel',
			'xml'     => 'application/xml',
			'xpm'     => 'image/x-xpixmap',
			'xsl'     => 'application/xml',
			'xslt'    => 'application/xslt+xml',
			'xul'     => 'application/vnd.mozilla.xul+xml',
			'xwd'     => 'image/x-xwindowdump',
			'xyz'     => 'chemical/x-xyz',
			'zip'     => 'application/zip'
		);
	}
}