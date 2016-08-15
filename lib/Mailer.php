<?php namespace Mailer;
	
use Mailer\DB\DB;
use Mailer\Configurable;

class Mailer extends Configurable
{
	protected $recipients;
	
	protected $subject;
	
	private $instance;
	
	protected $attachments = array();
	
	protected $errors = array();
	
	public static $defaults = array();
	
	private function __construct($configs = array())
	{
		$this->loadDefaultConfiguration();
		
		#override any configuration
		$this->setConfigs($configs);
	}
	
	public static function instance($configs = array())
	{
		return new static($configs);
	}
	
	private function getServiceInstance($service_name)
	{
		$services_path = dirname(__FILE__) . "/services";
		$service_namepace = $this->camelCase($service_name);
		$configuration = $this->getConfig($service_name, array());
		
		if(! is_dir("{$services_path}/{$service_name}/"))
		{
			throw new \Exception("Mailer '{$service_name}' does not exist.");
		}
		
		require_once("{$services_path}/{$service_name}/Mailer.php");
		
		$class = "Mailer\Services\\{$service_namepace}\\Mailer";
		
		if( ! class_exists($class))
		{
			throw new \Exception("Class '$class' does not exist.");
		}
		
		return new $class($configuration);
	}
	
	private function getDBConnection()
	{
		return DB::instance();
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
		
		$recipients = $this->recipients;
		
		# lets send a copy of the email to the sender
		if($this->getConfig('send_copy', false) === true)
		{
			$recipients[] = array(
				'email'		=> $this->getConfig('from_email', ''),
				'type'		=> 'to',
			);
		}
		
		return $recipients;
	}
	
	public function getErrors()
	{
		return $this->errors;
	}
	
	public function addAttachment($name, $content)
	{
		$this->attachments[] = array(
			'type' => $this->getAttachmentTypeByName($name),
			'name' => $name,
			'content' => base64_encode($content)
		);
		
		return $this;
	}
	
	public function scheduleHtml($html)
	{
		if($this->getConfig('include_unsubscribe_link', false) === true)
		{
			$html .= $this->getConfig('unsubscribe_link', '');
		}
		
		$content = array('type' => 'html', 'content' => $html);
		
		return $this->sendOrScheduleMail($content);
	}
	
	public function sendHtml($html)
	{
		if($this->getConfig('include_unsubscribe_link', false) === true)
		{
			$html .= $this->getConfig('unsubscribe_link', '');
		}
		
		$content = array('type' => 'html', 'content' => $html);
		
		return $this->sendOrScheduleMail($content, true);
	}
	
	public function scheduleTemplate($template_slug, $variables = array())
	{
		$content = array('type' => 'template', 'template_slug' => $template_slug,  'variables' => $variables);
		
		return $this->sendOrScheduleMail($content);
	}
	
	public function sendTemplate($template_slug, $variables = array())
	{
		$content = array('type' => 'template', 'template_slug' => $template_slug,  'variables' => $variables);
		
		return $this->sendOrScheduleMail($content, true);
	}
	
	private function sendOrScheduleMail($content, $send_now = false)
	{
		$service = $this->getServiceInstance($this->getConfig('service', ''));
		$db_conn = $this->getDBConnection();
		
		$mail = array_merge($content , array(
			'subject' => $this->subject,
			'from_name' => $this->getConfig('from_name', ''),
			'from_email' => $this->getConfig('from_email', ''),
			'attachments' => $this->attachments,
			'recipients' => $this->getRecipients()
		));
		
		# have email service prepare the payload they way it needs it
		$payload = $service->preparePayload($mail);
		
		$entry = array(
			'recipients'=> $this->getRecipients(),
			'subject'	=> $this->subject,
			'payload'	=> $payload,
			'esp'		=> $this->getConfig('service'),
			'template'	=> isset($mail['template_slug']) ? $mail['template_slug'] : '',
			'attempt'	=> $send_now
		);
		
		# save entry, no matter if the user wants us to send it right away, so we can re-attempt later if need it
		if( $entry = $db_conn->saveEntry($entry) )
		{
			$success = true;
			if($send_now)
			{
				$send_result = $this->attemptSendingMail(array($entry));
			}
		}
		else
		{
			$success = false;
			
			$this->errors[] = $db_conn->getLastError();
		}
		
		$errors = $this->getErrors();
		
		return compact('success','errors','send_result');
	}
	
	public function sendScheduled()
	{
		$db_conn = $this->getDBConnection();
		
		$numToTake = $this->getConfig('send_per_run', 5);
		
		$mails = $db_conn->getUnsentEntries($numToTake);
		
		$failedNumToTake = (count($mails) == $numToTake ? 1 : ( $numToTake - count($mails) ) );
		$failsLimit = $this->getConfig('resend_attempts', 5);

		//get any entries that have not been attempted in the last 10 minutes
		$fails = $db_conn->getFailedEntries($failsLimit, $failedNumToTake);
		
		foreach($fails as $failed)
		{
			array_push($mails,$failed);
		}
		
		/*
			if we want to increase number of rows to take,we need to make sure all of them are locked by
			setting their attempted_at to 'now'
		*/
		if( ! count($mails))
		{
			$criteria = $this->getConfig('delete_criteria', array());
			
			$res = $db_conn->deleteOldEntries($criteria);
			
			return array('nothing to send', $res);
		}
		
		$db_conn->lockEntries($mails);

		return $this->attemptSendingMail($mails);

	}
	
	private function attemptSendingMail(Array $mails)
	{
		$db_conn = $this->getDBConnection();
		
		$results = array();
		foreach($mails as $mail)
		{
			$service = $this->getServiceInstance($mail['esp']);
			
			$updates = array();
			$send_result = $service->send($mail);
			
			if($send_result['sent'])
			{
				$updates['sent_at'] = date('Y-m-d H:i:s');
			}
			else
			{
				$updates['attempts'] = $mail['attempts'] + 1;
				
				if($updates['attempts'] == $this->getConfig('resend_attempts', 5))
				{
					$this->sendFailedWarning($send_result, $mail);
				}
			}
			
			$db_conn->updateEntry($mail, $updates);
			
			$results[] = $send_result;
		}
		
		return $results;
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
	
	private function sendFailedWarning($result, $mail)
	{
		$mail['payload'] = json_decode($mail['payload_json'], true);
		
		unset($mail['payload_json']);
		
		$mail = static::mailerArrayFlatten($mail);
		$result = static::mailerArrayFlatten($result);
		
		$msg = "--- Mail Info ---\r\n\r\n";
		
		foreach($mail as $title=>$row)
		{
			$msg .= "$title: $row \r\n";
		}
		
		$msg .= "\r\n--- Response Info ---\r\n\r\n";
		
		foreach($result as $title=>$row)
		{
			$msg .= "$title: $row \r\n";
		}
		
		$subject = "[" . $_SERVER['HTTP_HOST'] . "] Mail sending failed " . $this->getConfig('resend_attempts', 5) . " times";
		
		mail($this->getConfig('pretend_email'), $subject, $msg);
	}
	
	private function mailerArrayFlatten($array)
	{
		$return = array();
		foreach ($array as $key => $value)
		{
			if (is_array($value))
			{
				$return = array_merge($return, static::mailerArrayFlatten($value));
			}
			else
			{
				$return[$key] = $value;
			}
		}
		return $return;
	}
	
	private function camelCase($service_name)
	{
		$service_name = ucwords(str_replace(array('-', '_'), ' ', $service_name));

		return str_replace(' ', '', $service_name);
	}
}