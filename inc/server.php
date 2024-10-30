<?php
namespace VideoWhisper\LiveSupport;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

trait Server {

	static function pinGenerate($pinLength = 6)
	{
		$charSet = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charLen = strlen($charSet);
		$pin = '';
		for($a = 0; $a < $pinLength; $a++) $pin .= ($charSet[ rand(0, $charLen-1) ] ?? '');
		return $pin;
	}

	function restRoutes() {

	 register_rest_route( 'video-live-support/v1', '/login', array(
		 'methods' => 'GET',
		 'callback' =>  array(  $this, 'rest_login' ),
		 'permission_callback' => function ( \WP_REST_Request $request ) { return true; },
	   ) );

	 register_rest_route( 'video-live-support/v1', '/contact_new', array(
			'methods' => 'POST',
			'callback' =>  array(  $this, 'rest_contact_new' ),
			'permission_callback' => function ( \WP_REST_Request $request ) { return true; },
		 ) );

	register_rest_route( 'video-live-support/v1', '/contact_confirm', array(
		'methods' => 'POST',
		'callback' =>  array(  $this, 'rest_contact_confirm' ),
		'permission_callback' => function ( \WP_REST_Request $request ) { return true; },
	 ) );

	register_rest_route( 'video-live-support/v1', '/ticket', array(
		 'methods' => 'POST',
		 'callback' =>  array(  $this, 'rest_ticket' ),
		 'permission_callback' => function ( \WP_REST_Request $request ) { return true; },
	  ) );

	register_rest_route( 'video-live-support/v1', '/list', array(
		   'methods' => 'POST',
		   'callback' =>  array(  $this, 'rest_list' ),
		   'permission_callback' => function ( \WP_REST_Request $request ) { return true; },
		) );

	register_rest_route( 'video-live-support/v1', '/form', array(
			 'methods' => 'POST',
			 'callback' =>  array(  $this, 'rest_form' ),
			 'permission_callback' => function ( \WP_REST_Request $request ) { return true; },
		  ) );

	}

	static function log($message, $level = 5, $options = null)
	{
		if (!$options) $options = self::getOptions();
		
		//levels: 0 = none, 1 = error, 2 = warning, 3 = notice, 4 = info (success), 5 = debug (dev info)
		if ($options['logLevel'] >= $level) 
		{
			$logFile = $options['logPath'];

			//create folder if not exists
			if (!file_exists($logFile)) mkdir($logFile, 0777, true);
			$logFile .= '/'.date('Y-m-d').'.txt';
		
			//include date and level in message and end of line
			$message = date('Y-m-d H:i:s') . ' [' . $level . '] ' . $message . PHP_EOL;
			@file_put_contents($logFile, $message, FILE_APPEND);

			//disable others from accessing log file for extra security
			@chmod($logFile, 0600);		
		}
	}

	static function statusLabel($status)
	{
		$statuses = [
			0 => __('New', 'live-support-tickets'),
			1 => __('Answered', 'live-support-tickets'),
			2 => __('Closed', 'live-support-tickets'), //no updates
			3 => __('Public', 'live-support-tickets'), //no updates + public access
			4 => __('Locked', 'live-support-tickets'), //no updates + no frontend access
			5 => __('Pending', 'live-support-tickets'), //waiting for contact confirmation
			6 => __('Pending Answered', 'live-support-tickets'), //waiting for contact confirmation	
		];

		if (array_key_exists($status, $statuses)) return $statuses[$status];
		return '#' . $status;
	}

	static function statusIcon($status)
	{
		$statuses = [
			0 => 'star',
			1 => 'check',
			2 => 'lock', //can't be updated
			3 => 'announcement', //can't be updated + can be accessed without code
			4 => 'ban', //can't be updated or accessed
			5 => 'hourglass start', //waiting for contact confirmation
			6 => 'hourglass half', //waiting for contact confirmation
		];

		if (array_key_exists($status, $statuses)) return $statuses[$status];

		return 'question';
	}

	static function contactName($contact_id, $contacts)
	{
		if (array_key_exists($contact_id, $contacts)) return $contacts[$contact_id]['name'];
		return '#' . $contact_id;
	}


	function timeLabel($date)
	{

		//return date(DATE_RFC2822, $date); //Thu, 21 Dec 2000 16:01:07 +0200

		if (!$date) return __('Never', 'live-support-tickets');

		//age
	  $diff = time() - $date;
	  $periods[] = [60, 1, '%s seconds ago', 'a second ago'];
	  $periods[] = [60*100, 60, '%s minutes ago', 'one minute ago'];
	  $periods[] = [3600*70, 3600, '%s hours ago', 'an hour ago'];
	  $periods[] = [3600*24*10, 3600*24, '%s days ago', 'yesterday'];
	  $periods[] = [3600*24*30, 3600*24*7, '%s weeks ago', 'one week ago'];
	  $periods[] = [3600*24*30*30, 3600*24*30, '%s months ago', 'last month'];
	  $periods[] = [INF, 3600*24*265, '%s years ago', 'last year'];

	  foreach ($periods as $period) {
		if ($diff > $period[0]) continue;
		$diff = floor($diff / $period[1]);
		return sprintf($diff > 1 ? $period[2] : $period[3], $diff);
	  }

	}

	static function arrayValue($array, $keys, $defaultValue)
	{
		$value = $array;

		if (!is_array($keys))
		{
			if ( isset( $value[$keys] ) ) return $value[$keys];
			else return $defaultValue;
		}
		else foreach ($keys as $key) {
			if (isset($value[$key])) {
				$value = $value[$key];
			} else {
				$value = $defaultValue;
				break;
			}
		}

		return $value;
	}

	static function updateCounters($options = null, $userID = 0, $verbose = false)
	{
		//count new tickets (frontend if $userID, otherwise backend)

		if (!$options) $options = self::getOptions();

		$htmlCode = '';

		global $wpdb;
		$table_contacts = $wpdb->prefix . 'vws_contacts';
		$table_tickets = $wpdb->prefix . 'vws_tickets';
		$table_ticket_contacts = $wpdb->prefix . 'vws_ticket_contacts';

		//frontend user tickets
		if ($userID) {
			$sqlS = "SELECT `id` FROM `$table_contacts` WHERE uid = '$userID' LIMIT 1";
			$contactID = $wpdb->get_var( $sqlS );

			$sqlS = "SELECT COUNT(DISTINCT(t.id)) FROM `$table_tickets` t, $table_ticket_contacts tc WHERE t.status = '0' AND (t.id = tc.tid AND tc.cid='$contactID') OR (tc.id = NULL AND (t.type='2' OR t.type='3') AND t.creator = '$userID' )";
			$counter = $wpdb->get_var( $sqlS );

			update_user_meta( $userID, 'vwSupport_new', $counter );
		}
		else
		{
			//backend new tickets
			$sqlS = "SELECT COUNT(DISTINCT(`id`)) FROM `$table_tickets` WHERE status = '0'";
			$counter = $wpdb->get_var( $sqlS );

			$tickets_new = get_option( 'vwSupport_new' );
			if ($tickets_new != $counter)  update_option( 'vwSupport_new', $counter );
		}


		if ( $verbose ) $htmlCode .= "updateCounters: $counter SQL: $sqlS";
	//	else self::log( 'updateCounters( ' . $userID . ', '  . $verbose . ') = '. $counter , 5, $options);

		return $htmlCode;
	}

	static function timeTo( $action, $expire = 60, $options = '' ) {
		// if $action was already done in last $expire, return false

		if ( ! $options ) {
			$options = self::getOptions();
		}

		$cleanNow = false;

		$ztime = time();

		$lastClean = 0;

		// saves in specific folder
		$timersPath = $options['uploadsPath'];
		if ( ! file_exists( $timersPath ) ) {
			mkdir( $timersPath );
		}

		$timersPath .= '/_timers/';
		if ( ! file_exists( $timersPath ) ) {
			mkdir( $timersPath );
		}

		$lastCleanFile = $timersPath . $action . '.txt';

		if ( ! file_exists( $dir = dirname( $lastCleanFile ) ) ) {
			mkdir( $dir );
		} elseif ( file_exists( $lastCleanFile ) ) {
			$lastClean = file_get_contents( $lastCleanFile );
		}

		if ( ! $lastClean ) {
			$cleanNow = true;
		} elseif ( $ztime - $lastClean > $expire ) {
			$cleanNow = true;
		}

		if ( $cleanNow ) {
			file_put_contents( $lastCleanFile, $ztime );
		}

			return $cleanNow;

	}


	static function fields( $fieldList = '', $options = null, $contact_id = 0 )
	{
		if ( !$fieldList ) return [];

		if (!$options) $options = self::getOptions();

		$fields = [];
		if (!is_array($options['fields'])) return [];

		$contactFields = false;
		if ($contact_id) //load values
		{
			global $wpdb;
			$table_contacts = $wpdb->prefix . 'vws_contacts';

			$sqlS = "SELECT * FROM `$table_contacts` WHERE id = '$contact_id' LIMIT 1";
			$contact = $wpdb->get_row( $sqlS );

			if ($contact)
			{
			if ($contact->meta) $meta = unserialize($contact->meta);
			if (is_array($meta)) if (array_key_exists('fields', $meta)) $contactFields = $meta['fields'];
			if (!is_array($contactFields)) $contactFields = false;
			}

		}

		$fieldNames = explode('|', trim($fieldList) );
		if (!is_array($fieldNames)) return [];
		foreach ($fieldNames as $key => $fieldName ) $fieldNames[ $key ] = trim($fieldName); //trim

		foreach ($options['fields'] as $fieldName => $field )
		if ( in_array( $fieldName, $fieldNames ) )
		{
			$field['name'] = trim($fieldName);
			if (isset($field['options']))
			{
				$fieldOptions = explode('|', stripslashes($field['options']) );
				if (!is_array($fieldOptions)) unset($field['options']);
				else
				{
					$sOptions = [];

					foreach ($fieldOptions as $option)
					{
						$sOptions[] = ['key'=> trim($option), 'value' => trim($option), 'text' => trim($option) ];
					}
					$field['options'] = $sOptions;
				}
			}

			if (isset($field['instructions'])) $field['instructions'] = trim( stripslashes( $field['instructions'] ) );
			if (isset($field['placeholder'])) $field['placeholder'] = trim( stripslashes( $field['placeholder'] ) );

			//value
			$field['value'] = '';
			if ($contactFields && array_key_exists($field['name'], $contactFields) ) $field['value'] = $contactFields[ $field['name'] ];

			$fields[ trim($fieldName) ] = $field;
		}

		return $fields;
	}

	static function form( $formName, $options = null, $contact_id = 0, $confirmed = false )
	{
		if (!$options) $options = self::getOptions();
		//self::log( 'form( ' . $formName . ',' . $contact_id . ',' . $confirmed, 5, $options)

		$form = false;
		if ( is_array( $options['forms'] ) )
		{
			if ( array_key_exists( $formName, $options['forms'] ) )
			{
			$form = $options['forms'][ $formName ];

			$form['fields'] = self::fields( stripslashes($form['fields']), $options, $contact_id ); //expand fields
			$form['instructions'] = stripslashes( $form['instructions'] ?? '' );
			$form['unconfirmed'] = stripslashes( $form['unconfirmed'] ?? '' );

			if (!$confirmed) $form['instructions'] .= ( $form['unconfirmed'] ?? false ) ? ' ' . stripslashes( $form['unconfirmed'] ) : '';

			$form['terms'] = stripslashes( $form['terms'] ?? '' );
			$form['termsUrl'] = stripslashes( $form['termsUrl'] ?? '' );

			$form['gtag'] = stripslashes( $form['gtag'] ?? '' );
			$form['gtagView'] = stripslashes( $form['gtagView'] ?? '' );

			$form['done'] = stripslashes( $form['done'] ?? '' );
			$form['doneUrl'] = stripslashes( $form['doneUrl'] ?? '' );

			$form['filled'] = 0;
			if ($contact_id) {
				//check if form was filled previously from contact meta, forms
				global $wpdb;
				$table_contacts = $wpdb->prefix . 'vws_contacts';
				$sqlS = "SELECT * FROM `$table_contacts` WHERE id = '$contact_id' LIMIT 1";
				$contact = $wpdb->get_row( $sqlS );
				if ($contact->meta) $meta = unserialize($contact->meta);
				if (is_array($meta)) if (array_key_exists('forms', $meta)) if (array_key_exists($formName, $meta['forms'])) $form['filled'] = 1;
			}

			}
		}

		return $form;
	}

	static function formActions($formName, $contact_id, $options)
	{
		//actions to perform on contact confirmation or form submission

		if (!$options) $options = self::getOptions();
		self::log( 'formActions( ' . $formName . ',' . $contact_id . ')', 5, $options);

		$output = '';

		$form = false;
		if ( is_array( $options['forms'] ) )
		{
			if ( array_key_exists( $formName, $options['forms'] ) )
			$form = $options['forms'][ $formName ];
			$actionsParam = stripslashes($form['actions'] ?? '');
			if ($actionsParam)
			{
				$actions = explode('|', $actionsParam);
				if (is_array($actions))
				{
				
				$form['fields'] = self::fields( stripslashes($form['fields']), $options, $contact_id ); //expand fields

					foreach ($actions as $action)
					{
						$action = trim($action);
						if ($action == 'account') $output .= self::formAccount($contact_id, $form, $options);
					}

				}

			}
		}

		return $output;
	}

	static function updateAccountsAPI($options = null)
	{
		if (!$options) $options = self::getOptions();

		if (!$options['accountsAPI']) return 'Accounts API URL not configured';

		$url = trim($options['accountsAPI']) . '/update-accounts?apikey=' . urlencode(trim($options['accountsAPIkey']));

		$output = '';

		$result = @file_get_contents( $url ); //remove @ to debug i.e. SSL error
		if ($result) return esc_html( $result );
		if ($result === false) {
			$error = error_get_last();
			$output .= esc_html( $error['message'] ) . '<br>Trying with CURL:';
		} 

		//else try curl
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url  );
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		if (!$options['apiSSL']) curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //disable
		curl_setopt($ch, CURLOPT_TIMEOUT, 5);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
		$result = curl_exec($ch);
		$error = curl_error($ch); // moved this line up
		curl_close($ch);

		if ($result === false) {
			return $output . "Also error opening URL with CURL:<br>" . esc_html( $error );
		}

		return $output . '<br>' . esc_html( $result );
	}

	static function statusAccountsAPI($token, $options = null)
	{
		if (!$options) $options = self::getOptions();

		if (!$options['accountsAPI']) return 'Accounts API URL not configured';

		$url = trim($options['accountsAPI']) . '/status?';
		if ($token) $url .= 'token=' . urlencode(trim($token)) ;
		else $url .= 'apikey=' . urlencode(trim($options['accountsAPIkey']));

		$output = '';

		$result = @file_get_contents( $url ); //remove @ to debug i.e. SSL error
		if ($result) return esc_html( $result );
		if ($result === false) {
			$error = error_get_last();
			$output .= esc_html( $error['message'] ) . '<br>Trying with CURL:';
		} 

		//else try curl
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url  );
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		if (!$options['apiSSL']) curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //disable
		curl_setopt($ch, CURLOPT_TIMEOUT, 5);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
		$result = curl_exec($ch);
		$error = curl_error($ch); // moved this line up
		curl_close($ch);

		if ($result === false) {
			return $output . "Also error opening URL with CURL:<br>" . esc_html( $error );
		}

		return $output . '<br>' . esc_html( $result );
	}

	static function formAccount($contact_id, $form, $options)
	{
		if (!$options) $options = self::getOptions();
		self::log( 'formAccount( ' . $contact_id . ')', 5, $options);

		$website = '';
		$domain = '';

		if (array_key_exists('Website', $form['fields'])) {
			$website = trim($form['fields']['Website']['value']);
		
			// Ensure URL starts with http:// or https:// so parse_url works
			if (substr($website, 0, 7) !== "http://" && substr($website, 0, 8) !== "https://") {
				$website = "https://" . $website;
			}
		
			if ($website) {
				$domain = parse_url($website, PHP_URL_HOST);
				if ($domain) $domain = str_replace('www.', '', $domain);
			}
		
			// Trim domain if longer than 32 chars
			if (strlen($domain) > 32) $domain = substr($domain, 0, 32);
		}

		if (!$domain) 
		{
			self::log( 'formAccount ' . $contact_id . '- No domain: ' . $website, 3, $options);
		
			//generate a domain name to use as account name 
			$domain = 'A' . $contact_id . '-' . self::pinGenerate();
		}

		if (!$domain) 	return 'No domain provided for seting up a new account.';
		

		if ($options['accounts_host'] && $options['accounts_db'])
		{
			//get contact info
			global $wpdb;
			$table_contacts = $wpdb->prefix . 'vws_contacts';
			$sqlS = "SELECT * FROM `$table_contacts` WHERE id = '$contact_id' LIMIT 1";
			$contact = $wpdb->get_row( $sqlS );

			//get contact meta
			if ($contact->meta) $metaC = unserialize($contact->meta);
			if (!is_array($metaC)) $metaC = [];
	
			//connect to remote accounts_host mysql
			$mysqli = new \mysqli($options['accounts_host'], $options['accounts_user'], $options['accounts_pass'], $options['accounts_db'], $options['accounts_port']);
			if ($mysqli->connect_errno) return false;

			//count accounts for this contact id by contactId
			$sqlS = "SELECT COUNT(*) AS `count` FROM `accounts` WHERE `contactId` = '$contact_id'";
			$count = $mysqli->query( $sqlS )->fetch_assoc();
			if ($options['accountsLimit']) if ($count['count'] >= $options['accountsLimit'] ) return 'Accounts limit reached: ' . $count['count'] . '/' . $options['accountsLimit'] . '. Please contact support for more information.';

			//check if domain exists as account name in accounts table
			$sqlS = "SELECT * FROM `accounts` WHERE `name` = '$domain' LIMIT 1";
			$account = $mysqli->query( $sqlS )->fetch_assoc();

			//if account does not exist insert a new row with fields name, token, properties, planId, meta, contactId 
			if (!$account)
			{
				$token = 'vws-' . preg_replace("/[^a-zA-Z0-9\-_.]/", '', $domain) . '-' . $contact_id . '-'. self::pinGenerate();
				$properties = '{}';
				$planId = 1;

				if (array_key_exists('Solution', $form['fields'])) $solution = trim( $form['fields']['Solution']['value'] );
				if (array_key_exists('How Did You Find Us', $form['fields'])) $from = trim( $form['fields']['How Did You Find Us']['value'] );

				$meta = [];
				$meta['domain'] = $domain;
				$meta['contactName'] = $contact->name;
				$meta['contact'] = $contact->contact;
				$meta['contactType'] = $contact->type;
				$meta['IP'] = self::get_ip_address();

				foreach ($form['fields'] as $field)
				{
					$meta[ $field['name'] ] = trim($field['value'] ?? '');
				}

				$metaJ = esc_sql(json_encode($meta));
				$sqlI = "INSERT INTO `accounts` (`name`, `token`, `properties`, `planId`, `meta`, `contactId`, `created`) VALUES ('$domain', '$token', '$properties', '$planId', '$metaJ', '$contact_id', '".time()."')";
				$mysqli->query( $sqlI );

				//log error if insert failed
				if ($mysqli->error) self::log( 'formAccount insert failed: ' . $mysqli->error, 1, $options);
				else self::log( 'formAccount insert success: ' . $sqlI, 4, $options);

				//always sent notification about new account & token (accountsLimit is checked above)
				self::notifyContact($contact_id, $options['notifyAccountSubject'] . ' ' . $domain , $options['notifyAccountMessage'] . ' ' . $token, $options, true );
				
				//get updated list of accounts for this contact_id from sql
				$sqlS = "SELECT * FROM `accounts` WHERE `contactId` = '$contact_id'";
				$accounts = $mysqli->query( $sqlS );
				$mAccounts = [];
				while ($account = $accounts->fetch_assoc())
				{
					$mAccounts[$account['name']] = $account['token'];
				}
				$metaC['accounts'] = $mAccounts;
				$metaS = esc_sql(serialize($metaC));
				$sqlU = "UPDATE `$table_contacts` SET `meta` = '$metaS' WHERE `id` = " . $contact_id;
				$wpdb->query( $sqlU );

				//call API to reload/update accounts
				self::updateAccountsAPI($options);

				return 'Account created (' . $domain . ') with token: ' . $token . '.';
			}
			else
			{
				//if account exists update contactId field if not set or 0
				if (!$account['contactId'])
				{
					$sqlU = "UPDATE `accounts` SET `contactId` = '$contact_id' WHERE `id` = " . $account['id'];
					$mysqli->query( $sqlU );

					//log error if update failed
					if ($mysqli->error) self::log( 'formAccount update failed: ' . $mysqli->error, 1, $options);
				}
				
				self::log( 'formAccount account already exists for domain: ' . $domain, 3, $options);

				return 'Account already exists for domain: ' . $domain . ' Contact support if you lost your token.';
			}
			
		} else 
		{
			self::log( 'formAccount - host not configured ', 3, $options);
			return 'Accounts host not configured, yet.';
		}
	}

	static function departments($content_id = 0, $creator_id = 0, $site_id = 0, $options = null, $department_id = 0)
	{
		if (!$options) $options = self::getOptions();

		$departments = [];

		if ( is_array( $options['departments'] ) )
		foreach ($options['departments'] as $id => $department)
		{
			if (
				( !$content_id && !$creator_id && $department['type'] == 0 ) || //site support
				( $content_id && !$creator_id && $department['type'] == 1 ) ||  //report to site
				( $content_id && $creator_id && $department['type'] == 2 ) ||  //content owner
				( !$content_id && $creator_id && $department['type'] == 3 ) //contact user
			 )
			 {
				 $department['value'] = $id;
				 $department['text'] = stripslashes( $department['text'] );
				 $department['key'] = stripslashes( $department['text'] );
				
				 if ( isset($department['fields']) ) $department['fields'] = self::fields( stripslashes($department['fields']) );

				 if ( isset($department['instructions']) ) $department['instructions'] = stripslashes( $department['instructions'] );
				
				 $departments[] = $department;

				 if ($department_id == $id) return $department;
			 }
		}

		if ($department_id) return [ 'value' => $department_id, 'text' => 'Any', 'key' => 'None', 'type' => 0 ];
		if (!count($departments)) return [ [ 'value' => 1, 'text' => 'General', 'key' => 'General', 'type' => 0 ] ];

		return $departments;
	}

	static function departmentName($department_id, $options = null)
	{
		if (!$options) $options = self::getOptions();

		$department = self::departments(0, 0, 0, $options, $department_id);
		return $department['text'];
	}

	static function translations()
	{

		$text = [
			'Open Conversation' => __('Open Conversation', 'live-support-tickets'),
			'Add Contact'	=> __('Add Contact', 'live-support-tickets'),
			'Remove Contact'	=> __('Remove Contact', 'live-support-tickets'),
			'Contact Name'	=> __('Contact Name', 'live-support-tickets'),
			'Contact'	=> __('Contact', 'live-support-tickets'),
			'Contact Type'	=> __('Contact Type', 'live-support-tickets'),
			'Email'	=> __('Email', 'live-support-tickets'),
			'Contact Role'	=> __('Contact Role', 'live-support-tickets'),
			'Upload'	=> __('Upload', 'live-support-tickets'),
			'Insert Recording' => __('Insert Recording', 'live-support-tickets'),
		];

		$text['Send'] =  __('Send', 'live-support-tickets');
		$text['Send Message'] =  __('Send Message', 'live-support-tickets');
		$text['Write your message here'] =  __('Write your message here', 'live-support-tickets');
		$text['Emoticons'] =  __('Emoticons', 'live-support-tickets');
		$text['Nevermind'] =  __('Nevermind', 'live-support-tickets');
		$text['All'] =  __('All', 'live-support-tickets');
		$text['New'] =  __('New', 'live-support-tickets');
		$text['Pending'] =  __('Pending', 'live-support-tickets');
		$text['Answered'] =  __('Answered', 'live-support-tickets');
		$text['Pending Answered'] =  __('Pending Answered', 'live-support-tickets');
		$text['Closed'] =  __('Closed', 'live-support-tickets');
		$text['Public'] =  __('Public', 'live-support-tickets');
		$text['Locked'] =  __('Locked', 'live-support-tickets');
		$text['Status'] =  __('Status', 'live-support-tickets');
		$text['Created'] =  __('Created', 'live-support-tickets');
		$text['Updated'] =  __('Updated', 'live-support-tickets');

		return $text;

	}

	static function language2flag($lang)
			 {
				 $flags = [ 'en-us'=>'us', 'en-gb'=>'gb', 'pt-br'=> 'br', 'pt-pt' => 'pt','zh'=>'cn', 'ja' => 'jp', 'el' => 'gr', 'da' => 'dk', 'en' => 'us',  'ko'=>'kr', 'nb'=>'no'];
				 if ( array_key_exists($lang, $flags ) ) return $flags[ $lang ];
				 return $lang;
			 }
	
	static function languageField($userID, $options)
	{
		
		$langs = [];

		$languages = get_option( 'VWdeepLlangs' );
		if ($languages)
		{
			
			 foreach ($languages as $lang => $label) $langs []= ['value' => $lang, 'flag' => self::language2flag( $lang ), 'key' => $lang, 'text' => $label];
		}
		else $langs []= ['value' => 'en-us', 'flag' => 'us', 'key' => 'en-us', 'text' => 'English US'];
		
				$h5v_language = '';
			 	if ($userID) $h5v_language = get_user_meta( $userID, 'h5v_language', true );
				if (!$h5v_language) $h5v_language = $options['languageDefault'];
			 	if (!$h5v_language) $h5v_language = 'en-us';

	 			return [
	 			'name'        => 'h5v_language',
				'description' => __( 'My Language', 'live-support-tickets' ),
				'details'     => __( 'Language you will be using in conversations and target for translations.', 'live-support-tickets' ),
				'type'        => 'dropdown',
				'value'       => $h5v_language,
				'flag'		  => self::language2flag($h5v_language),
				'options'     => $langs,	 			
	 			];


	}


	function vws_app()
			{
				//admin-ajax : retrieve logged in user session info, nonce, contact, app config

				$options = self::getOptions();

				//niche
				$site_id = intval( $_POST['site_id'] ?? 0 );
				$content_id = intval( $_POST['content_id'] ?? 0 );
				$creator_id = intval( $_POST['creator_id'] ?? 0 );

				//target
				$form = sanitize_text_field( trim( $_POST['form'] ?? '' ) ); //form name
				$department_name = sanitize_text_field( trim( $_POST['department'] ?? '' ) ); //department name

				//ticket
				$ticket_id = intval( $_POST['ticket_id'] ?? 0 );

				$contact_id = 0;
				$contact_confirmed = false;

				// output clean
				ob_clean();

				$result = [];

				//current user language
				$h5v_language = '';
				if ( is_user_logged_in() ) $h5v_language = get_user_meta( get_current_user_id(), 'h5v_language', true );
			    if (!$h5v_language) $h5v_language = $options['languageDefault'];
				if (!$h5v_language) $h5v_language = 'en-us';
				$h5v_flag = self::language2flag($h5v_language);

				$userID = get_current_user_id();

				$emailsUnsupported = explode(',', $options['emailsUnsupported'] ?? '' );
				//trim elements if array or set emtpy array if not
				$emailsUnsupported = array_filter( array_map('trim', $emailsUnsupported) );


				$config = [
					'ticketUpdateInterval' => 15000,
					'text' => self::translations(),
					'darkMode' => false,
					'className' => '', //inverted
					'backgroundColor' => '#EEEEEE', //#333333
					'site_id' => $site_id,
					'content_id' => $content_id,
					'creator_id' => $creator_id,
					'termsURL' => ( $options['p_videowhisper_support_terms'] ?? false ) ? get_permalink($options['p_videowhisper_support_terms']) : get_site_url(), 
					'multilanguage'    =>  $options['multilanguage'] ? true : false,
					'translations' 	   => ( $options['translations'] == 'all' ? true : ( $options['translations'] == 'registered' ? is_user_logged_in() : false )  ),
					'languages'    	   => self::languageField($userID, $options),
					'language' => $h5v_language,
					'flag' => $h5v_flag,
					'serverURL2'	   => plugins_url('live-support-tickets/server/'), //fast server
					'emailsUnsupported' => $emailsUnsupported,
					'registerConversation' => ($options['registerConversation'] ?? false) ? true : false,
					'siteAdmin' =>   ( $userID && user_can( $userID, 'manage_options' ) ? true : false ),
				];

				$config['departments'] = self::departments($content_id, $creator_id, $site_id, $options);

				if (count($config['departments']) == 1)
				{
					$department = reset($config['departments']);
					$config['departmentDefault'] = $department['value'];
				}

				foreach ($config['departments'] as $department)
				{
					if ($department['text'] == $department_name)
					{
						$config['departmentDefault'] = $department['value'];
						break;
					}
				}

				//context
				$context = [];
				if ($creator_id)
				{
					$creator = get_userdata($creator_id);
					if ($creator) $context['creator'] = $creator->display_name;
				}

				if ($content_id)
				{
					$content = get_post($content_id);
					if ($content)
					{
						$context['content'] = $content->post_title;
						$context['url'] = get_permalink( $content_id );
					}
				}

				$config['context'] = $context;

				if (is_user_logged_in())
				{
				$current_user = wp_get_current_user();
				$uid = intval($current_user->ID);
				$email = sanitize_email($current_user->user_email);

				global $wpdb;
				$table_contacts = $wpdb->prefix . 'vws_contacts';

					$session = ['login' => 1, 'nonce' => wp_create_nonce( 'wp_rest' ) ];

						$sqlS = "SELECT * FROM `$table_contacts` WHERE `uid` = '$uid' OR `contact` = '$email' LIMIT 1";
						$contact = $wpdb->get_row( $sqlS );

						if ($contact)
						{
							$result['contact'] = [ 'id' => intval($contact->id), 'uid'=>$uid,  'name' => $contact->name, 'contact' => $contact->contact, 'type' => $contact->type, 'confirmed' => intval($contact->confirmed), 'pin' => $contact->pin ];

							if (!$contact->uid) //update contact if not assigned to current user but email matches current loggend in user
							{
								$sqlU = "UPDATE `$table_contacts` SET `uid` = '$uid' WHERE `id` = " . intval($contact->id);
								$wpdb->query( $sqlU );
							}
							
							$contact_id = intval($contact->id);
							if ($contact->confirmed) $contact_confirmed = true;
						}
						else //create contact for current logged in user
						{

							$name = $current_user->display_name;
							$email = $current_user->user_email;
							$type = 'email';
							$pin = self::pinGenerate();

								$meta = [];
								$meta['created_ip'] = self::get_ip_address();
								$meta['created_location'] = self::ip2location($meta['created_ip']);
								$meta['created_by'] = 'login';
								$metaS = esc_sql(serialize($meta));

							$sqlI = "INSERT INTO `$table_contacts` ( `uid`, `name`, `contact`, `type`, `pin`, `created`, `meta`, `confirmed` ) VALUES ( '$uid', '" . $name . "', '" . $email . "', '" . $type . "', '" . $pin . "', '" . time() . "', '$metaS', '" . time() . "' )";
							$wpdb->query( $sqlI );
							$contact_id = $wpdb->insert_id;

							if ($contact_id)
							{
								$result['contact'] = [ 'id' => intval($contact_id), 'uid'=> $uid,  'name' => $name, 'contact' => $email, 'type' => 'email', 'confirmed' => time(), 'pin' => $pin ];
								$url = add_query_arg( [ 'contact' => $contact_id, 'confirm' => $pin ], $options['appURL'] ) . '#vws';

								$sqlS = "SELECT * FROM `$table_contacts` WHERE id = '$contact_id' LIMIT 1";
								$contact = $wpdb->get_row( $sqlS );

								// maybe option to also confirm/notify members
								// self::notifyContact($contact_id, $options['notifyConfirmSubject'], $options['notifyConfirmMessage'] . ' ' . $url);

								$contact_confirmed = true;

							}else $result['error'] = 'SQL error: ' . $wpdb->last_error . ' / ' . ( $options['devMode'] ? $sqlI : '' );

						//end create contact
						}

						if ($options['micropayments'] && class_exists( 'VWpaidMembership' ) )
						{
							if ($ticket_id) {
								$table_tickets = $wpdb->prefix . 'vws_tickets';
								$sqlS = "SELECT * FROM `$table_tickets` WHERE id='$ticket_id' LIMIT 1";
								$ticket =  $wpdb->get_row( $sqlS );
								$isRep = self::isRepresentative( $contact, $ticket );
								if (!$creator_id) $creator_id = $ticket->creator; //creator for existing ticket
							}else $isRep = self::isRepresentative( $contact, null );

							if (!$isRep) //only non represenatives get costs
							if ( ($creator_id && $options['micropayments'] == 'users') || ($options['micropayments'] == 'all') ) //paid mode
							{
						
								//get custom creator cost from user meta, if set
								$conversationCost = get_user_meta( $creator_id, 'vwSupport_conversation_cost', true);
								if ($conversationCost) $costCoversation = floatval($conversationCost);
								else $costCoversation = floatval($options['micropaymentsConversation']);
								$config['costConversation'] = $costCoversation;

								//get custom creator cost from user meta, if set
								$messageCost = get_user_meta( $creator_id, 'vwSupport_message_cost', true);
								if ($messageCost) $costMessage = floatval($messageCost);
								else $costMessage = floatval($options['micropaymentsMessage']);
								$config['costMessage'] = $costMessage;

								$config['costCurrency'] = \VWpaidMembership::option( 'currency' );
								$config['costBalance'] = \VWpaidMembership::balance();
								$result['balance'] = $config['costBalance'];
							}

					    }

						}else
						{
							$session = ['login' => 0, 'nonce' => wp_create_nonce( 'wp_rest' )];

							//microPayments integration
							if ($options['micropayments'] && class_exists( 'VWpaidMembership' ) )
							if ( ($creator_id && $options['micropayments'] == 'users') || ($options['micropayments'] == 'all') ) //paid mode
							self::errorExit('Login is required to open paid conversations.');
					 }

				$result['session'] = $session;

				$config[ 'form' ] = $form ? self::form( $form, $options, $contact_id, $contact_confimed ) : false ;

				$config['gtag'] = [
					'contactView' => $options['gtagContactView'] ?? '',
					'contactNew' => $options['gtagContactNew'] ?? '',
					'contactConfirm' => $options['gtagContactConfirm'] ?? '',
					'message' => $options['gtagMessage'] ?? '',
					'conversation' => $options['gtagConversation'] ?? '',
					'conversationView' => $options['gtagConversationView'] ?? '',
				];

				$result['config'] = $config;

				echo json_encode( $result );
				die();
				//check_ajax_referer( 'wp_rest', '_nonce', false )
		}

	static function notifyContact($contact_id, $subject, $content, $options = null, $always = false, $item_id = 0)
	{
		//notifies a contact
		if (!$options) $options = self::getOptions();

		global $wpdb;
		$table_contacts = $wpdb->prefix . 'vws_contacts';

		$sqlS = "SELECT * FROM `$table_contacts` WHERE id = '$contact_id' LIMIT 1";
		$contact = $wpdb->get_row( $sqlS );

		if ($contact)
		{
			$meta = unserialize($contact->meta);
			if (!is_array($meta)) $meta = [];

			$lastKey = 'lastNotification';
			if ($item_id) $lastKey .= $item_id;

			//contact cooldown
			if (!$always) if ($options['notifyCooldownContact']) if ( array_key_exists($lastKey, $meta) ) if ( time() - $meta[$lastKey] < $options['notifyCooldownContact'] ) 
			{
				self::log( 'notifyContact Cooldown #' . $contact_id . ' item='. $item_id . ' / ' . $subject, 3, $options );
				return 'Cooldown. Try again in ' . ( $options['notifyCooldownContact'] - ( time() - $meta['lastNotification'] ) ) . ' seconds.';
			}

			$cooldown = -1;
			if ( array_key_exists($lastKey, $meta) ) $cooldown =  time() - $meta[$lastKey];

			if ($contact->type == 'email') if (!is_email($contact->contact))  
			{
				self::log( 'notifyContact Invalid Email for #' . $contact_id , 1, $options );
				return 'Invalid email address.';
			}

			//replace placeholders
			$replace = [ '{name}' => $contact->name, '{contact}' => $contact->contact, '{type}'=> $contact->type, '{item}' => $item_id ];
			$subject = strtr($subject, $replace);
			$content = strtr($content, $replace);

			if ($contact->type == 'email') if ($options['notifyEmail'])
			{
				$footer = strtr($options['notifyMessageFooter'], $replace);
				if ($footer) $content .= "\n\n" . $footer;

				$sent = wp_mail( $contact->contact, $subject, $content );

				if ($sent)
				{

				if (array_key_exists('notifications', $meta)) $meta['notifications']++; else $meta['notifications'] = 1;
				$meta[$lastKey] = time();

				self::log( 'notifyContact #' . $contact_id . ' / ' . $subject . ' / notifications=' . $meta['notifications'] . ' / cooldown=' .$cooldown , 4, $options );

				$metaS = esc_sql(serialize($meta));

				//update meta
				$sqlU = "UPDATE `$table_contacts` SET meta='$metaS' WHERE id='$contact_id'";
				$wpdb->query( $sqlU );

				//log any sql error
				if ($wpdb->last_error) self::log( 'notifyContact Failed update: ' . $wpdb->last_error . ' SQL: ' . $sqlU , 1, $options);

				return;
				} 
				else  
				{
					self::log( 'notifyContact Failed wp_mail to #' . $contact_id . ' / ' . $subject , 1, $options );
					return 'Failed wp_mail';
				}
			}
		}

		return 'Contact not found #' . $contact_id;
	}

	static function get_ip_address() {
		$ip_keys = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR' );
		foreach ( $ip_keys as $key ) {
			if ( array_key_exists( $key, $_SERVER ) === true ) {
				foreach ( explode( ',', $_SERVER[ $key ] ) as $ip ) {
					// trim for safety measures
					$ip = trim( $ip );
					// attempt to validate IP
					if ( self::validate_ip( $ip ) ) {
						return $ip;
					}
				}
			}
		}

		return isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : false;
	}


	/**
	 * Ensures an ip address is both a valid IP and does not fall within
	 * a private network range.
	 */
	static function validate_ip( $ip ) {
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) === false ) {
			return false;
		}
		return true;
	}


	static function humanSize( $value ) {
		if ( $value > 1000000000000 ) {
			return number_format( $value / 1000000000000, 2, '.', ''  ) . 't';
		}
		if ( $value > 1000000000 ) {
			return number_format( $value / 1000000000, 2, '.', '' ) . 'g';
		}
		if ( $value > 1000000 ) {
			return number_format( $value / 1000000, 2, '.', ''  ) . 'm';
		}
		if ( $value > 1000 ) {
			return number_format( $value / 1000, 2, '.', ''  ) . 'k';
		}
		return $value;
	}

	static function handle_upload( $file, $destination ) {
		// ex $_FILE['myfile']

		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
		}

		$movefile = wp_handle_upload( $file, array( 'test_form' => false ) );

		if ( $movefile && ! isset( $movefile['error'] ) ) {
			if ( ! $destination ) {
				return 0;
			}
			rename( $movefile['file'], $destination ); // $movefile[file, url, type]
			return 0;
		} else {
			/*
			 * Error generated by _wp_handle_upload()
			 * @see _wp_handle_upload() in wp-admin/includes/file.php
			 */
			return $movefile['error']; // return error
		}

	}

	static function isRepresentative( $contact, $ticket = null )
	{
		//is contact representative for this ticket?
		if (!$contact) return 0;

		//ticket for creator
		if ($ticket) if ($ticket->creator) if ($contact->uid == $ticket->creator) return 1;

		//site representative
		$meta = unserialize($contact->meta);
		if ( !is_array($meta) ) return 0;
		if ( array_key_exists('representative', $meta) ) if ( $meta['representative'] ) return 2;

		return 0;
	}

	static function toCsvString(...$args) {
		// Filter out empty arguments
		$nonEmptyArgs = array_filter($args, function($value) {
			// Consider a '0' as non-empty
			return ($value === '0' || !empty($value));
		});
	
		// Convert the non-empty arguments to a CSV string
		$csvString = implode(', ', $nonEmptyArgs);
	
		return $csvString;
	}

	static function ip2location($ip)
	{
		//if geoip is available use to get location
		if ( function_exists('geoip_record_by_name') )
		{
			$record = geoip_record_by_name($ip);
			if ($record) return self::toCsvString( $record['country_name'], $record['region'], $record['city'] );
		}

		//get location from geoip env variables
		if ( array_key_exists('GEOIP_COUNTRY_NAME', $_SERVER) ) return self::toCsvString( $_SERVER['GEOIP_COUNTRY_NAME'], $_SERVER['GEOIP_REGION'], $_SERVER['GEOIP_CITY'] );
		
		//otherwise from geoplugin
		$ipdat = @json_decode( file_get_contents( "http://www.geoplugin.net/json.gp?ip=" . $ip) ); 
		if ($ipdat && $ipdat->geoplugin_countryName) return self::toCsvString( $ipdat->geoplugin_countryName, $ipdat->geoplugin_region, $ipdat->geoplugin_city );

		return '-';
	}

	static function contactInfo($forContact, $contact, $options)
	{
		$info = [];
		$siteRepresentative = false;

		$meta = unserialize($forContact->meta);
		if ( !is_array($meta) ) return 0;
		if ( array_key_exists('representative', $meta) ) if ( $meta['representative'] ) $siteRepresentative = true;

		if (!$siteRepresentative) return false;

		$contact_id = $contact->id;

		global $wpdb;

		//retrieve properties from table vws_properties for $contact->id
		if ($siteRepresentative )
		{
			$info['representative'] = true;
			$table_properties = $wpdb->prefix . 'vws_properties';
			$sqlP = "SELECT * FROM `$table_properties` WHERE cid='" . $contact->id . "'";
			$properties = $wpdb->get_results( $sqlP );
		
			$properties_array = [];
			$info['dbError'] = $wpdb->last_error;
			foreach ($properties as $property)
			{
				$properties_array[] = [ 'name' => $property->name, 'value' => $property->value, 'type' => $property->type, 'category' => $property->category, 'group' => $property->group, 'status' => $property->status, 'created' => intval($property->created), 'updated' => intval($property->updated) ];
			}

			//retrieve accounts add add to $properties_array
			if ($options['accounts_host'] && $options['accounts_db'])
			{
				//connect to remote accounts_host mysql
				$mysqli = new \mysqli($options['accounts_host'], $options['accounts_user'], $options['accounts_pass'], $options['accounts_db'], $options['accounts_port']);
				if ($mysqli->connect_errno) return false;
	
				//count accounts for this contact id by contactId
				$sqlS = "SELECT accounts.*, plans.name as plan_name FROM accounts LEFT JOIN plans ON accounts.planId = plans.id WHERE `contactId` = '$contact_id'";
				$accounts = $mysqli->query( $sqlS );
				
				while ($account = $accounts->fetch_assoc())
				{
					$status = 0;
					$properties = json_decode($account['properties'], true);
					if (is_array($properties)) if (array_key_exists('suspended', $properties)) if ($properties['suspended']) $status = 1;

					$properties_array[] = [ 'name' => $account['name'], 'value' => $account['token'], 'type' => 'account', 'category' => $account['plan_name'], 'group' => $options['accounts_host'], 'status' => $status, 'created' => intval($account['created']), 'updated' => 0 ];				
				}

			}

			//retrieve contact info
			$table_contacts = $wpdb->prefix . 'vws_contacts';
			$sqlS = "SELECT * FROM `$table_contacts` WHERE id = '$contact_id' LIMIT 1";
			$contactR = $wpdb->get_row( $sqlS );

			$properties_array[] = [ 'name' => $contactR->name, 'value' => $contactR->contact, 'type' => 'Contact', 'category' => $contactR->type, 'group' => $contactR->id, 'status' => ( $contactR->confirmed ? 0 : 1 ) , 'created' => intval($contactR->created), 'updated' => intval($contactR->laccess) ];

			$info['properties'] = $properties_array;

			//retrieve fields from $contact->meta
			$meta = unserialize($contactR->meta);
			if (is_array($meta)) 
			{
				if (array_key_exists('fields', $meta)) $info['fields'] = $meta['fields'];

				//add meta fields to properties
				foreach ($meta as $key => $value)
				{
					if (!is_array($value)) $info['fields']['_' . $key] = esc_html($value);

					//if (strstr($key, '_ip')) $info['fields']['_' . $key] .= ' ' . self::ip2location( $value );
				}

				if (array_key_exists('accounts', $meta)) $info['accounts'] = $meta['accounts'];
				if (array_key_exists('forms', $meta)) $info['forms'] = $meta['forms'];
			}

			//count tickets this contact is part of
			$table_ticket_contacts = $wpdb->prefix . 'vws_ticket_contacts';
			$sqlSC = "SELECT COUNT(*) AS `count` FROM `$table_ticket_contacts` WHERE cid='$contact_id'";
			$count = $wpdb->get_row( $sqlSC );
			$info['ticketCount'] = $count->count;
		}

		return $info;
	}

	static function messageInfo($forContact, $message, $msg, $options)
	{

		$info = [];
		$siteRepresentative = false;

		$meta = unserialize($forContact->meta);
		if ( !is_array($meta) ) return 0;
		if ( array_key_exists('representative', $meta) ) if ( $meta['representative'] ) $siteRepresentative = true;

		if (!$siteRepresentative) return false;

		$meta = unserialize($message->meta);
		if (is_array($meta)) 
			foreach ($meta as $key => $value)
			{
				if (!is_array($value)) $info['_' . $key] = esc_html($value);
			}

			if (is_array($msg)) foreach ($msg as $key => $value) if (!is_array($value)) $info[$key] = esc_html($value);

		return $info;
	}

	static function errorExit( $error )
	{
	   self::log( 'errorExit ' . self::get_ip_address() . ' : ' .  $error  , 2 );
   	   echo json_encode(  [ 'error' => true, 'info' => $error, 'created' => false ] );
	   die();
	}

	static function micropaymentsEarn($ticket, $options)
	{
		//pay creator (request target) when answering

		$ticket_id = $ticket->id;

		$ratio = floatval( $options['micropaymentsRatio'] );
		if (!$ratio) return; //no earnings

		if ($ticket->meta) $meta = unserialize($ticket->meta);
		if (!is_array($meta)) $meta = [];

		$conversationURL = add_query_arg( [ 'ticket' => $ticket_id ], $options['appURL'] ) . '#vws'; //generic - no contact access

		global $wpdb;
		$table_tickets = $wpdb->prefix . 'vws_tickets';
		$table_messages = $wpdb->prefix . 'vws_messages';
		$table_ticket_contacts = $wpdb->prefix . 'vws_ticket_contacts';

		if ($options['micropaymentsShare'] ?? false)
		{
			//get all representative contacts
			$sqlSC = "SELECT * FROM `$table_ticket_contacts` WHERE tid='$ticket_id' AND rep<>'0'"; //add AND status='0' if confirmation is implemented
			$resultsC = $wpdb->get_results($sqlSC);
			$repCount = count($resultsC);
		}
	
		if ( isset( $meta['micropayments_cost'] ) && !isset($meta['micropayments_earned']) ) //earn
		{
			//creator gets paid for answered conversation
			$earning = floatval( $meta['micropayments_cost']) * $ratio;

			if ($options['micropaymentsShare'] ?? false)
			{

			$splitEarning = floor( $earning / $repCount, 2);
			$meta['micropayments_earning_split'] = $splitEarning;
			$meta['micropayments_earning_repcount'] = $repCount;
			$meta['micropayments_earning_reps'] = [];

			if ($repCount) foreach ($resultsC as $rC)
			{
				\VWpaidMembership::transaction( 'support_conversation_answered', $rC->cid, $splitEarning  , 'Conversation answered <a href="' . $conversationURL . '">#' . $ticket_id . '</a>' );
				$meta['micropayments_earning_reps'][] = $rC->cid;
			}

			}
			else 
			{
			//only pay creator
			\VWpaidMembership::transaction( 'support_conversation_answered', $ticket->creator, $earning  , 'Conversation answered <a href="' . $conversationURL . '">#' . $ticket_id . '</a>' );
			}

			$meta['micropayments_earning'] = $earning;
			$meta['micropayments_earned'] = time();

			//update meta
			$metaS = esc_sql(serialize($meta));
			$sqlU = "UPDATE `$table_tickets` SET meta='$metaS' WHERE id='$ticket_id'";
			$wpdb->query( $sqlU );
		}

		//earning for all answered messages (if not earned already)
		$sqlS = "SELECT * FROM `$table_messages` WHERE tid='$ticket_id' ORDER BY `created` ASC";
		$results = $wpdb->get_results($sqlS);
		if ($results) foreach ($results as $r)
		{
			$message_id = $r->id;
			if ($r->meta) $meta = unserialize($r->meta);
			if (!is_array($meta)) $meta = [];

			if ( isset( $meta['micropayments_cost'] ) && !isset($meta['micropayments_earned']) ) //earn
			{
				$conversationURL = add_query_arg( [ 'ticket' => $ticket_id ], $options['appURL'] ) . '#message' . $message_id; //generic - no contact access

				//creator gets paid for answered message
				$earning = floatval( $meta['micropayments_cost']) * $ratio;

				if ($options['micropaymentsShare'] ?? false)
				{
				$splitEarning = floor( $earning / $repCount, 2);
				$meta['micropayments_earning_split'] = $splitEarning;
				$meta['micropayments_earning_repcount'] = $repCount;
				$meta['micropayments_earning_reps'] = [];

				if ($repCount) foreach ($resultsC as $rC)
				{
					\VWpaidMembership::transaction( 'support_message_answered', $rC->cid, $splitEarning  , 'Message answered <a href="' . $conversationURL . '">#' . $ticket_id . '/' . $message_id . '</a>' );
					$meta['micropayments_earning_reps'][] = $rC->cid;
				}

				}
				else 
				{
					//only pay creator for message
					\VWpaidMembership::transaction( 'support_message_answered', $ticket->creator, $earning  , 'Message answered <a href="' . $conversationURL . '">#' . $ticket_id . '/' . $message_id . '</a>' );
				}

				$meta['micropayments_earning'] = $earning;
				$meta['micropayments_earned'] = time();

				//update meta
				$metaS = esc_sql(serialize($meta));
				$sqlU = "UPDATE `$table_messages` SET meta='$metaS' WHERE id='$message_id'";
				$wpdb->query( $sqlU );

			}
		}
	}

	function rest_form(\WP_REST_Request $request)
	{
		//update form

		$params = $request->get_params();

		//access params
		$contact_id = intval( self::arrayValue( $params, 'contact_id', 0 ) );
		$confirm = sanitize_text_field( self::arrayValue( $params, 'confirm', '' ) ); //contact code (for new ticket)
		$site_id = intval( self::arrayValue( $params, 'site_id', 0 ) );
		$form = sanitize_text_field( self::arrayValue( $params, 'form', '' ) ); 
		$fields = self::arrayValue( $params, 'fields', null );

	if ($contact_id && $confirm && $form )
		{
			$options = self::getOptions();

				global $wpdb;
				$table_contacts = $wpdb->prefix . 'vws_contacts';

				$info = '';
				
					$sqlS = "SELECT * FROM `$table_contacts` WHERE id = '$contact_id' AND pin = '$confirm' LIMIT 1";
					$contact = $wpdb->get_row( $sqlS );

					if ($contact)
					{

					//update contact fields
					if ( is_array($fields) )
					{
						//previous fields
						if ($contact->meta) $meta = unserialize($contact->meta);
						if (!is_array($meta)) $meta = [];
						$mFields = [];
						if (array_key_exists('fields', $meta)) $mFields = $meta['fields'];

						//updated fields
						foreach ($fields as $key => $value)
						{
							$mFields[ sanitize_text_field( $key ) ] = sanitize_textarea_field( $value );
						}
						$meta['fields'] = $mFields;

						//form update time
						if (array_key_exists('forms', $meta)) $mForms = $meta['forms'];
						else $mForms = [];
						$mForms[$form] = time();
						$meta['forms'] = $mForms;
							
						$metaS = esc_sql(serialize($meta));

						$ztime = time();

						$sqlU = "UPDATE `$table_contacts` SET meta='$metaS', lform='$ztime' WHERE id = '$contact_id' LIMIT 1";
						$wpdb->query( $sqlU);

						$info =  __('Contact fields updated.', 'live-support-tickets');

						//execut form actions
						$info .= self::formActions($form, $contact_id, $options);

						self::log( 'rest_form updated ' . $form . ' contact ' . $contact->name , 4, $options );
					}

					$result = [ 'form' => self::form( $form, $options, $contact_id, true ), 'info' => $info ];
					}
					else
					{
						$info = 'Invalid contact info.';
					}

		} else $info = 'Missing parameters.';

		if (!$result) $result = [ 'error' => true, 'info' => $info  ];

		echo json_encode( $result );
		die();

	}


	function rest_list(\WP_REST_Request $request)
	{
		//retrieves a list of tickets
		//ini_set( 'display_errors', 1 );

		//test http://localhost:3000/?contact=8&rep=BBQ8RU&list=1

		$options = self::getOptions();

		//input
		$params = $request->get_params();
		$contact_id = intval( self::arrayValue( $params, 'contact_id', 0 ) );
		$pin = sanitize_text_field( self::arrayValue( $params, 'confirm', '' ) ); //user contact access code
		$rep = sanitize_text_field( self::arrayValue( $params, 'rep', '' ) ); //representative contact access code
		$list = sanitize_text_field( self::arrayValue( $params, 'list', 0 ));

		//controls
		$page = intval( self::arrayValue( $params, 'page', 0 ) );
		$search = sanitize_text_field( self::arrayValue( $params, 'search', '' ) );
		$perPage = 10;

		//sort and filters
		$sort = sanitize_text_field( self::arrayValue( $params, 'sort', 'status' ) );
		$filter_contact = intval( self::arrayValue( $params, 'filter_contact', 0 ) );
		$filter_status = intval( self::arrayValue( $params, 'filter_status', -1 ) );
		$filter_department = intval( self::arrayValue( $params, 'filter_department', 0 ) );
		$filter_older = intval( self::arrayValue( $params, 'filter_older', 0 ) );

		//action
		$action = sanitize_text_field( self::arrayValue( $params, 'action', '' ));
		$selection = self::arrayValue( $params, 'selection', [] );  //array

		if (!$pin) $pin = $rep; //contact pin

		$debug = '';

		//db
		global $wpdb;
		$table_contacts = $wpdb->prefix . 'vws_contacts';
		$table_tickets = $wpdb->prefix . 'vws_tickets';
		$table_ticket_contacts = $wpdb->prefix . 'vws_ticket_contacts';
		$table_messages = $wpdb->prefix . 'vws_messages';


		$result = [];

		$sqlS = "SELECT * FROM `$table_contacts` WHERE id = '$contact_id' AND pin = '$pin' LIMIT 1";
		$contact = $wpdb->get_row( $sqlS );

		if ($contact)
		{
			$meta = unserialize($contact->meta);
			if ( !is_array($meta) ) $meta = [];
			$uid = $contact->uid; //user ID

			$representativeAny = false;


			//pagination
			$sqlPage = "LIMIT $perPage";
			if ($page)
			{	$offset = $perPage * $page;
				$sqlPage = "LIMIT $offset, $perPage";
			}

			$sortQ = "ORDER BY `status` ASC, `updated` DESC";

			switch ($sort)
			{
				case 'status':
					$sortQ = "ORDER BY `status` ASC, `updated` DESC";
				break;

				case 'updated':
					$sortQ = "ORDER BY `updated` DESC, `status` ASC";
				break;

				case 'created':
					$sortQ = "ORDER BY `created` DESC";
				break;		
			}

			$filterQ = '';


			if ($rep)
			{
			//support
			if (! array_key_exists('representative', $meta) || !$meta['representative'] )
			{
			$debug .= $sqlS . ' / ' . $contact->meta;
			$result = [ 'error' => true, 'info' => 'Only representatives can access support ticket list.' . ( $options['devMode'] ? " $debug" : '' ) ];
				echo json_encode( $result );
				die();
			}

			$sql = "FROM `$table_tickets` t,  $table_ticket_contacts tc WHERE t.id = tc.tid";
			$representativeAny = true;
		   }
		   else //regular
		   {
			   //own tickets: is ticket contact or ticket is for this owner or target creator and contact not added, yet

			   if ($uid) $sqlU = "OR (tc.id = NULL AND (t.type='2' OR t.type='3') AND t.creator = '$uid' )";
			   else $sqlU = '';
			   $sql = "FROM `$table_tickets` t,  $table_ticket_contacts tc WHERE ( (t.id = tc.tid AND tc.cid='$contact_id') $sqlU )";
		   }

		   if ($filter_contact) $filterQ .= " AND tc.`cid` = '$filter_contact'";
		   if ($filter_status >= 0) $filterQ .= " AND t.`status` = '$filter_status'";
		   if ($filter_department) $filterQ .= " AND t.`department` = '$filter_department'";
		   if ($filter_older) 
		   {
			$filter_older_time = time() - $filter_older * 3600 * 24;
			$filterQ .= " AND t.`updated` < $filter_older_time";
		   }

		   $sql1 = "SELECT DISTINCT(t.id), t.* $sql";
		   $sqlS = "SELECT DISTINCT(t.id), t.* $sql $filterQ $sortQ $sqlPage";
		   $sqlT = "SELECT COUNT(DISTINCT(t.id)) $sql $filterQ";
		   $sqlN = "SELECT COUNT(DISTINCT(t.id)) $sql AND t.status='0'";
		   $sqlP = "SELECT COUNT(DISTINCT(t.id)) $sql AND t.status='5'";

			//action process
			$actionInfo = '';
			if ($action) if (is_array($selection) )
			{
				$actionInfo = 'Action: ' . count($selection) . ' '. $action;

				foreach ($selection as $value)
				{
					$id = intval($value);

					$aTicket = $wpdb->get_row( $sql1 . " AND t.id='$id'" );


					if (!$aTicket) $actionInfo .= 'Conversation not found or you do NOT have permissions to manage it: ' . $id ;
					else
					if ($action == 'closed' || $rep || $aTicket->creator == $uid ) switch ($action)
					{
						case 'open':
							$wpdb->query("UPDATE `$table_tickets` SET status='0' WHERE id = '$id'");
						break;
						case 'closed':
							$wpdb->query("UPDATE `$table_tickets` SET status='2' WHERE id = '$id'");
						break;
						case 'public':
							$wpdb->query("UPDATE `$table_tickets` SET status='3' WHERE id = '$id'");
						break;
						case 'locked':
							$wpdb->query("UPDATE `$table_tickets` SET status='4' WHERE id = '$id'");
						break;
						case 'delete':
								$wpdb->query("DELETE FROM `$table_messages` WHERE tid = '$id'");
								$wpdb->query("DELETE FROM `$table_ticket_contacts` WHERE tid = '$id'");
								$wpdb->query("DELETE FROM `$table_tickets` WHERE id = '$id'");
						break;
					} else $actionInfo .= 'Action not permitted for regular contacts: ' . $id;
				}

			}

			//count totals
			$total = $wpdb->get_var($sqlT);
			$new = $wpdb->get_var($sqlN);		
			$pending = $wpdb->get_var($sqlP);

			//get tickets
			$tickets = [];

			$results = $wpdb->get_results($sqlS);
			if ($results)
			{
				foreach ($results as $r)
				{

					$ticket = [];

					$tkt['id'] = intval($r->id);
					$tkt['department'] = intval($r->department);
					$tkt['department_name'] = self::departmentName($tkt['department'], $options);
					$tkt['created'] = intval($r->created);
					$tkt['created_label'] = self::timeLabel(intval($r->created));
					$tkt['updated'] = intval($r->updated);
					$tkt['updated_label'] = self::timeLabel(intval($r->updated));
					$tkt['status'] = intval($r->status);
					$tkt['status_label'] = self::statusLabel($r->status);
					$tkt['status_icon'] = self::statusIcon($r->status);

					if ( in_array( $r->type, [2, 3] ) && $r->creator == $uid ) $representativeAny = true; //target creator

					$meta = unserialize($r->meta);
					if (!is_array($meta)) $meta = [];

					//ticket contacts
					$contacts = [];

					$sqlSC = "SELECT tc.*, ttc.pin as code, ttc.rep FROM `$table_contacts` tc, $table_ticket_contacts ttc  WHERE ttc.tid = '" . $r->id . "' AND ttc.cid = tc.id";
					$resultsC = $wpdb->get_results($sqlSC);

					$url = '';
					$contactList = '';
					foreach ($resultsC as $rC)
					{
						$contacts[ $rC->id ] = [ 'id' => intval($rC->id), 'name' => $rC->name, 'rep' => intval($rC->rep) ];
						$contactList .= $contactList ? ', ' . $rC->name : $rC->name;

						//if contact, use contact access code
						if ($contact_id ==  $rC->id)
						{
						$url = add_query_arg( [ 'ticket' => $tkt['id'], 'contact' => $contact_id, 'code' => $rC->code ], $options['appURL'] ) . '#vws';
						$tkt['code'] = $rC->code;
						}

					}

					$tkt['contacts'] = $contacts;
					$tkt['contact_list'] = $contactList;

					$tkt['created_contact'] = self::contactName($meta['created_contact'], $contacts);

					if (!$url) //access as representative
					{
						$url = add_query_arg( [ 'ticket' => $tkt['id'], 'contact' => $contact_id, 'rep' => $pin ], $options['appURL'] ) . '#vws';
						$tkt['rep'] = $pin;
					}
					$tkt['url'] = $url;

					//count messages
					$tkt['message_count'] = intval( $wpdb->get_var( "SELECT COUNT(DISTINCT(id)) FROM `$table_messages` WHERE tid = '" . $r->id . "'" ) );

					//count messages from rep table_ticket_contacts WHERE tid = '" . $r->id . "' AND rep='1'
					$tkt['message_count_rep'] = intval( $wpdb->get_var( "SELECT COUNT(DISTINCT(m.id)) FROM `$table_messages` m, $table_ticket_contacts tc WHERE m.tid = '" . $r->id . "' AND m.cid = tc.cid AND m.tid = tc.tid AND tc.rep='1'" ) );

					$tkt['message_count_client'] = intval( $wpdb->get_var( "SELECT COUNT(DISTINCT(m.id))
					FROM `$table_messages` m
					INNER JOIN `$table_ticket_contacts` tc ON ( m.cid = tc.cid AND m.tid = tc.tid )
					WHERE m.tid = '" . $r->id . "' AND tc.rep = '0'") );

					//first message (topic)
					$sqlM = "SELECT * FROM `$table_messages` WHERE tid='" . $r->id . "' ORDER BY `created` ASC LIMIT 1";
					$message = $wpdb->get_row( $sqlM );
					if ($message)
					{
						$tkt['first_message'] = wp_trim_words($message->content, 15, '..');
						$tkt['first_contact'] = self::contactName(intval($message->cid), $contacts) ;
						$tkt['first_id'] = $message->id;
						//determine if first contact is rep
						$tkt['first_rep'] = intval( $wpdb->get_var( "SELECT rep FROM $table_ticket_contacts WHERE tid='" . $r->id . "' AND cid='" . $message->cid . "'" ) );
					} else
					{
						$tkt['first_message'] = 'Warning: Missing message for ticket #' . $r->id;
						$tkt['first_contact'] = 0;
						$tkt['first_id'] = 0;
						$tkt['first_rep'] = 0;
					}

					//last message
					$sqlM = "SELECT * FROM `$table_messages` WHERE tid='" . $r->id . "' ORDER BY `created` DESC LIMIT 1";
					$message = $wpdb->get_row( $sqlM );
					if ($message)
					{
						$tkt['last_message'] = wp_trim_words($message->content, 15, '..');
						$tkt['last_contact'] = self::contactName(intval($message->cid), $contacts);
						$tkt['last_id'] = $message->id;
						//determine if last contact is rep 
						$tkt['last_rep'] = intval( $wpdb->get_var( "SELECT rep FROM $table_ticket_contacts WHERE tid='" . $r->id . "' AND cid='" . $message->cid . "'" ) );
					} else
					{
						$tkt['last_message'] = 'Warning: Missing message for ticket #' . $r->id;
						$tkt['last_contact'] = 0;
						$tkt['last_id'] = 0;
						$tkt['last_rep'] = 0;
					}

					//get conversation tags
					$tkt['tags'] = self::tagGet($r->id, 'conversation');

					$tickets[] = $tkt;
				}
					$result = [ 'total' => $total, 'pending' => $pending, 'new'=> $new, 'page' =>$page, 'perPage'=> $perPage, 'tickets' => $tickets, 'info' => $actionInfo, 'representative' => $representativeAny ];
					//if ($options['devMode'] ) $result['info'] = $sqlS;


			} else
			{
				$debug .= 'Last DB error: ' . $wpdb->last_error . ' / ' . $sqlS;
				$result = [  'total' => $total, 'pending' => $pending, 'new'=> $new, 'page' =>$page, 'perPage'=> $perPage, 'tickets' => [], 'representative' => false, 'info' => 'No conversations found.' . $actionInfo . ( $options['devMode'] ? " $debug" : '' ) ];
			}

	}else
	{
		$debug .= '-SQL error: ' . $wpdb->last_error . ' / ' . $sqlS;
		$result = [ 'error' => true, 'info' => 'Contact not found for provided identifiers.' . ( $options['devMode'] ? " $debug" : '' ) ];
	}
		echo json_encode( $result );
		die();

	}

	function rest_ticket(\WP_REST_Request $request)
	{
		//create, update, access ticket

		//ini_set( 'display_errors', 1 );

		$options = self::getOptions();
		$params = $request->get_params();

		//access params
		$contact_id = intval( self::arrayValue( $params, 'contact_id', 0 ) );
		$confirm = sanitize_text_field( self::arrayValue( $params, 'confirm', '' ) ); //contact code (for new ticket)
		$pin = sanitize_text_field( self::arrayValue( $params, 'pin', '' ) ); //access code
		$rep = sanitize_text_field( self::arrayValue( $params, 'rep', '' ) ); //representative access code

		if ($rep == 'undefined') $rep = '';

		//niche
		$site_id = intval( self::arrayValue( $params, 'site_id', 0 ) );
		$content_id = intval( self::arrayValue( $params, 'content_id', 0 ) );
		$creator_id = intval( self::arrayValue( $params, 'creator_id', 0 ) );

		//submit
		$message = sanitize_textarea_field( self::arrayValue( $params, 'message', '' ) );
		$language = sanitize_text_field( self::arrayValue( $params, 'language', $options['languageDefault'] ) );
		$flag = sanitize_text_field( self::arrayValue( $params, 'flag', self::language2flag($language) ) );
		$mentionUser =  sanitize_text_field( self::arrayValue( $params, 'mentionUser', '' ) );
		$mentionMessage =  intval( self::arrayValue( $params, 'mentionMessage', '' ) );

		$ticket_id = intval( self::arrayValue( $params, 'ticket_id', 0 ) );
		$department_id = intval( self::arrayValue( $params, 'department_id', 0 ) );

		$fields = (array) json_decode( sanitize_textarea_field( self::arrayValue( $params, 'fields', null ) ) ); // encoded as JSON for rest_ticket (FormData for files)

		$recordMode = sanitize_text_field( $_POST['recordMode'] ?? false );
		$recordDuration = sanitize_text_field( $_POST['recordDuration'] ?? 0 );

		$uploadMode = sanitize_text_field( $_POST['uploadMode'] ?? false );
		$filesCount = intval( $_POST['filesCount'] ?? 0 );

		$cansLoaded = ( $_POST['cansLoaded'] != 'false' ? true : false );

		//serverAction : can, user_option
		$action = sanitize_text_field( $_POST['action'] ?? false );
		$canMessage = sanitize_textarea_field( $_POST['canMessage'] ?? false );
		$canTitle = sanitize_textarea_field( $_POST['canTitle'] ?? false );

		$debug = '';

		$debugData= [];
		$result = [];


	if ($contact_id && ($pin || $confirm || $rep))
		{

			global $wpdb;
			$table_contacts = $wpdb->prefix . 'vws_contacts';
			$table_tickets = $wpdb->prefix . 'vws_tickets';
			$table_ticket_contacts = $wpdb->prefix . 'vws_ticket_contacts';
			$table_messages = $wpdb->prefix . 'vws_messages';
			$table_cans = $wpdb->prefix . 'vws_cans';

			$info = '';

			if (!$ticket_id && $confirm) //new after contact confirmation
			{
				//create new ticket with contact confirmation code
				$sqlS = "SELECT * FROM `$table_contacts` WHERE id = '$contact_id' AND pin = '$confirm' LIMIT 1";
				$contact = $wpdb->get_row( $sqlS );

				if ($contact)
				{
					//create a new conversation (ticket)
					$meta = [];
					$meta['created_contact'] = $contact_id;
					$meta['created_ip'] = self::get_ip_address();
					$meta['created_location'] = self::ip2location($meta['created_ip']);
					$meta['created_time'] = time();

					$department = self::departments($content_id, $creator_id, $site_id, $options, $department_id);
					$type = $department['type'];

					//fields 
					if ( is_array($fields) )
					{
						$mFields = [];
						foreach ($fields as $key => $value)
						{
							$mFields[ sanitize_text_field( $key ) ] = sanitize_textarea_field( $value );
						}
						$meta['fields'] = $mFields;
					} else $meta['no_fields'] = 1;

					//microPayments integration
					$microPayments = 0;
					if ($options['micropayments'] && class_exists( '\VWpaidMembership' ) )
					if ( ($creator_id && $options['micropayments'] == 'users') || ($options['micropayments'] == 'all') ) //paid mode
					{
						$userID = get_current_user_id();
						if (!$userID) self::errorExit( 'Login is required to request paid conversations.' );

						$balance = \VWpaidMembership::balance( $userID );
						$costCoversation = floatval($options['micropaymentsConversation']);

						//get custom creator cost from user meta, if set
						$conversationCost = get_user_meta( $creator_id, 'vwSupport_conversation_cost', true);
						if ($conversationCost) $costCoversation = floatval($conversationCost);

						$currency = \VWpaidMembership::option( 'currency' );
						if ( $balance < $costCoversation ) self::errorExit( 'Your balance is not sufficient to request a paid conversation: ' . $balance . $currency . ' < ' . $costCoversation . $currency );

						$meta['micropayments_cost']	= $costCoversation;
						$meta['micropayments_userID'] = $userID ;
						$microPayments = 1; //do transactions
					}

					$metaS = esc_sql(serialize($meta));

					$sqlI = "INSERT INTO `$table_tickets` ( `department`, `created`, `meta`, `status`, `type`, `site`, `content`, `creator`) VALUES ( '" . $department_id . "', '" . time() . "', '" . $metaS . "', '0', '$type', '$site_id', '$content_id', '$creator_id' )";
					$wpdb->query( $sqlI );
					$ticket_id = $wpdb->insert_id;
					if (!$ticket_id) $debug .= 'Last SQL error: ' . $wpdb->last_error . ' / ' . $sqlI;
					else
					{
						$info .= ' Conversation was created.';
						$result = array_merge( $result, ['conversationOpen' => 	$ticket_id ] );

					}

					//add contact to ticket
					if ($ticket_id)
					{

						$pin = self::pinGenerate();
						$sqlI = "INSERT INTO `$table_ticket_contacts` ( `tid`, `cid`, `pin`, `rep` ) VALUES ( '" . $ticket_id . "', '" . $contact_id . "', '" . $pin . "', '" . ( $rep ? 1 : 0 ) . "' )";
						$wpdb->query( $sqlI );
						$ticket_contact_id = $wpdb->insert_id;
						if (!$ticket_contact_id) $debug .= 'Last SQL error: ' . $wpdb->last_error . ' / ' . $sqlI;

					    $url = add_query_arg( [ 'ticket' => $ticket_id, 'contact' => $contact_id, 'code' => $pin ], $options['appURL'] ) . '#vws';

						$subject = $options['notifyTicketSubject'];
						$messageNotify = $options['notifyTicketMessage'] . ' ' . $url;
						$replace = [ '{sender}' => $contact->name ?? '{unknown}', '{department}' => self::departmentName($department_id, $options) ];
						$subject = strtr($subject, $replace);
						$messageNotify = strtr($messageNotify, $replace);
						self::notifyContact($contact_id, $subject, $messageNotify, $options, true, $ticket_id); //always notify new contact

						if ($options['devMode']) $info = "Access URL: " . $url;
						//Dev test: http://localhost:3000?ticket=4&contact=4&code=wRVeNp

						self::updateCounters($options);

						if ($microPayments) //transaction
						{
							$conversationURL = add_query_arg( [ 'ticket' => $ticket_id ], $options['appURL'] ) . '#vws'; //generic - no contact access

							//pay
							\VWpaidMembership::transaction( 'support_conversation', $userID, - $costCoversation, 'Conversation request <a href="' . $conversationURL . '">#' . $ticket_id . '</a>'  );

							$balance = \VWpaidMembership::balance();
						}

					}else $result = [ 'error' => true, 'info' => 'Error creating new ticket!' . ( $options['devMode'] ? " $debug" : '' ) ];

					//add creator to ticket, if ticket is for owner or creator
					if ($creator_id && ($type == 2 || $type == 3) )
					{
						$creator = get_userdata($creator_id);
						if ($creator)
						{
							$sqlS = "SELECT * FROM `$table_contacts` WHERE uid = '$creator_id' LIMIT 1";
							$creatorContact = $wpdb->get_row( $sqlS );

							if (!$creatorContact)
							{
								//maybe setup contact for creator - not required for listing
							}

							if ($creatorContact)
							{
							//add content owner/creator contact to ticket (if exists)
							$pinCreator = self::pinGenerate();
							$sqlI = "INSERT INTO `$table_ticket_contacts` ( `tid`, `cid`, `pin`, `rep` ) VALUES ( '" . $ticket_id . "', '" . $creatorContact->id . "', '" . $pinCreator . "', '0' )";
							$wpdb->query( $sqlI );

							// notify owner/creator
							$url = add_query_arg( [ 'ticket' => $ticket_id, 'contact' =>  $creatorContact->id , 'code' => $pinCreator ], $options['appURL'] ) . '#vws';

							$subject = $options['notifyTicketSubject'];
							$messageNotify = $options['notifyTicketMessage'] . ' ' . $url;
							$replace = [ '{sender}' => $contact->name ?? '{unknown}', '{department}' => self::departmentName($department_id, $options) ];
							$subject = strtr($subject, $replace);
							$messageNotify = strtr($messageNotify, $replace);
							self::notifyContact($creatorContact->id, $subject, $messageNotify, $options, false, $ticket_id . '-' . $creatorContact->id ); //notify creator of own message to have access link
							}

							self::updateCounters($options, $creator_id);
						}

					};


				}else $result = [ 'error' => true, 'info' => 'Contact not found!' . ( $options['devMode'] ? " $debug" : '' ) ];
			}

			if ($ticket_id && $pin && !$rep ) //access based on access code
			{
				$sqlS = "SELECT * FROM `$table_ticket_contacts` WHERE tid='$ticket_id' AND cid = '$contact_id' AND pin = '$pin' LIMIT 1";
				$ticket_contact = $wpdb->get_row( $sqlS );

				if (!$ticket_contact)
				{
					$result = [ 'error' => true, 'info' => 'Fail: Incorrect access code!' . ( $options['devMode'] ? " $debug" : '' ) ];
					echo json_encode( $result );
					die();
				}

				$sqlS = "SELECT * FROM `$table_contacts` WHERE id='$contact_id' LIMIT 1";
				$contact = $wpdb->get_row( $sqlS );
				if (!$contact)
				{
					$result = [ 'error' => true, 'info' => 'Fail: Missing contact!' . ( $options['devMode'] ? " $debug" : '' ) ];
					echo json_encode( $result );
					die();
				}

				if ( $confirm && !$contact->confirmed && !$rep)
				{
					//confirm contact (except for representative)
					$meta = unserialize($contact->meta);
					if (!is_array($meta)) $meta = [];
					$meta['confirmed_by'] = 'rest_ticket';
					$meta['confirmed_ip'] = self::get_ip_address();
					$meta['confirmed_location']	= self::ip2location($meta['confirmed_ip']);

					$metaS = esc_sql(serialize($meta));

					$sqlU = "UPDATE `$table_contacts` SET confirmed='1', meta='$metaS' WHERE id='$contact_id' AND pin = '$confirm' LIMIT 1";
					$wpdb->query( $sqlU );

					//reload contact
					$sqlS = "SELECT * FROM `$table_contacts` WHERE id='$contact_id' LIMIT 1";
					$contact = $wpdb->get_row( $sqlS );
				}

				if (!$contact->confirmed)
				{
					$debug = "Confirm: $confirm Pin: $pin Contact: $contact_id";

					$result = [ 'error' => true, 'info' => 'Fail: Contact not confirmed!' . $debug ];
					echo json_encode( $result );
					die();
				}

				//update last access time
				$ztime = time();
				$sqlU = "UPDATE `$table_contacts` SET laccess='$ztime' WHERE id='$contact_id'";
				$wpdb->query( $sqlU );
			}

			if ($ticket_id && $rep ) //access based on rep code (no in contact list, yet)
			{
				$sqlS = "SELECT * FROM `$table_contacts` WHERE id='$contact_id' AND pin = '$rep' LIMIT 1";
				$contact = $wpdb->get_row( $sqlS );
				if (!$contact)
				{
					$debug = ' -SQL error: ' . $wpdb->last_error . ' / ' . $sqlS;
					$result = [ 'error' => true, 'info' => 'Fail: Missing representative contact!' . ( $options['devMode'] ? " $debug" : '' ) ];
					echo json_encode( $result );
					die();
				}
			}

			if ($ticket_id)
			{
				//find ticket
				$sqlS = "SELECT * FROM `$table_tickets` WHERE id='$ticket_id'  LIMIT 1";
				$ticket =  $wpdb->get_row( $sqlS );
				if (!$ticket)
				{
					$debug = 'Last SQL error: ' . $wpdb->last_error . ' / ' . $sqlS;

					$result = [ 'error' => true, 'info' => 'Fail: Missing ticket!' . ( $options['devMode'] ? " $debug" : '' ) ];
					echo json_encode( $result );
					die();
				}

				//confirm conversation if contact confirmed and ticket is pending or pending answered
				if ( !$rep && $contact->confirmed && in_array( $ticket->status, [ 5, 6 ] ) ) //non representative contact confirmed but ticket is pending or pending answered
				{
					//update ticket status
					if ($ticket->status == 5) $wpdb->query("UPDATE `$table_tickets` SET status='0' WHERE id = '$ticket_id' LIMIT 1"); //pending to new
					if ($ticket->status == 6) $wpdb->query("UPDATE `$table_tickets` SET status='1' WHERE id = '$ticket_id' LIMIT 1"); //pending answered to answered

					//reload ticket
					$sqlS = "SELECT * FROM `$table_tickets` WHERE id='$ticket_id'  LIMIT 1";
					$ticket = $wpdb->get_row( $sqlS );
				}

				$departmentMeta = self::departments($ticket->content, $ticket->creator, $ticket->site, $options, $ticket->department);

				if ($ticket->creator)
				{
					$creator = get_userdata($ticket->creator);
					if ($creator) $departmentMeta['creator'] = $creator->display_name;
				}

				if ($ticket->content)
				{
					$content = get_post($ticket->content);
					if ($content)
					{
						$departmentMeta['content'] = $content->post_title;
						$departmentMeta['url'] = get_permalink( $ticket->content );
					}
				}

				if ($recordMode && !$message) 
				{
					$message = ucwords($recordMode) . ' ' . $recordDuration;
				}

				if ($uploadMode && !$message) 
				{
					$message =  '#' . $filesCount;
				}

				//is this contact representative for this ticket (admin or target creator)
				$isRep = self::isRepresentative($contact, $ticket);




				//serverAction in ticket.jsx
				if ($action) switch ($action)
				{
					case 'contact_remove':
					$contactRemoveID = intval( $_POST['contactRemoveID'] ?? 0 );

					if ($contactRemoveID)
					{
						//first check if contact has any messages in ticket
						$sqlS = "SELECT * FROM `$table_messages` WHERE tid='$ticket_id' AND cid = '$contactRemoveID' LIMIT 1";
						$contactRemove = $wpdb->get_row( $sqlS );

						if ($contactRemove)
						{
							$result = array_merge( $result, ['error'=>true, 'info'=> __('Contact participated with messages in this conversation. Cannot remove.', 'live-support-tickets'), 'contactRemoveComplete' => true ] );
						} else
						{
						//remove contact from ticket
						$sqlD = "DELETE FROM `$table_ticket_contacts` WHERE tid='$ticket_id' AND cid = '$contactRemoveID' LIMIT 1";
						$wpdb->query( $sqlD );

						$result = array_merge( $result, ['info'=> __('Contact was removed from conversation.', 'live-support-tickets') , 'contactRemoveComplete' => true ] );
						}
					}
					break;

					case 'tag_add':
					$tagAdd = trim(sanitize_text_field( $_POST['tag'] ?? '' ));
					$tagAdd = substr($tagAdd, 0, 64);

					if ($tagAdd)
					{

						$tags = self::tagGet($ticket_id, 'conversation');
						if ( array_key_exists($tagAdd, $tags) ) 
						{
							$result = array_merge( $result, ['error'=>true, 'info'=> __('Tag already exists.', 'live-support-tickets') .' '. $tagAdd, 'tagAddComplete' => true ] );
						} else
						{
							self::tagSet($tagAdd, $ticket_id, 'conversation');
							$result = array_merge( $result, ['info'=> __('Conversation was tagged.', 'live-support-tickets') . ' (' . $tagAdd . ')', 'tagAddComplete' => true ] );
						}
					} else $result = array_merge( $result, ['error'=>true, 'info'=> __('Empty tag.', 'live-support-tickets'), 'tagAddComplete' => true ] );
					break;

					case 'tag_remove':
						$tagAdd = trim(sanitize_text_field( $_POST['tag'] ?? '' ));
						$tagAdd = substr($tagAdd, 0, 64);

						$tags = self::tagGet($ticket_id, 'conversation');

						if ( !array_key_exists($tagAdd, $tags) ) 
						{
							$result = array_merge( $result, ['error'=>true, 'info'=> __('Tag not found.', 'live-support-tickets') . ' '. $tagAdd, 'tagAddComplete' => true ] );
						} else
						{
							self::tagRemove($tagAdd, $ticket_id, 'conversation');
							$result = array_merge( $result, ['info'=> __('Tag removed.', 'live-support-tickets')  . ' (' . $tagAdd . ')', 'tagAddComplete' => true ] );
						}
	
						break;

					case 'contact_add':
					//add contact to ticket

					$contactAddName = sanitize_text_field( $_POST['contactName'] ?? '' );
					$contactAddContact = sanitize_text_field( $_POST['contactContact'] ?? '' );
					$contactAddType = sanitize_text_field( $_POST['contactType'] ?? 'email' );
					$contactAddRole = sanitize_text_field( $_POST['contactRole'] ?? 'client' );
					$contactAddRep = 0;

					$contactAdd_id = 0;

				
					if ($isRep && $contactAddRole != 'client')

					$invalid = self::invalidContactName($name);
					if ( $invalid ) 
					{
						$result = array_merge( $result, ['error'=>true, 'info'=> $invalid, 'contactAddComplete' => true ] );
						break;
					}	

					//if email is invalid - do not proceed
					if ($contactAddType == 'email') if (!is_email($contactAddContact)) 
					{
						$result = array_merge( $result, ['error'=>true, 'info'=> __('Invalid email address.', 'live-support-tickets'), 'contactAddComplete' => true ] );
						break;
					}

					//unsupported email providers (domains), lower case
					$emailsUnsupported = explode(',', $options['emailsUnsupported'] ?? '' );
					$emailsUnsupported = array_filter( array_map('trim', $emailsUnsupported) );
					$emailDomain = strtolower(substr(strrchr($contactAddContact, "@"), 1)); 
					if ( in_array( $emailDomain , $emailsUnsupported ) )
					{
						$result = array_merge( $result, ['error'=>true, 'info'=> __('Unsupported email provider.', 'live-support-tickets') . ' ' . $emailDomain, 'contactAddComplete' => true ] );
						break;
					}
	

					if ($contactAddContact)
					{
						//first check if contact exists
						$sqlS = "SELECT * FROM `$table_contacts` WHERE contact='$contactAddContact' AND type='$contactAddType' LIMIT 1";
						$contactAdd = $wpdb->get_row( $sqlS );

						$args[] = '';
						if (!$contactAdd)
						{
							//create new contact to add
							if (!$contactAddName) $contactAddName = $contactAddContact;

							$meta = [];
							$meta['created_ip'] = self::get_ip_address();
							//$meta['created_location'] = self::ip2location($meta['created_ip']);
							$meta['created_by'] = 'rest_ticket/contact_add';
							$meta['added_by'] = $contact->name;
							$meta['added_by_id'] = $contact->id;
							$metaS = esc_sql(serialize($meta));

							$pin = self::pinGenerate();

							$sqlI = "INSERT INTO `$table_contacts` ( `uid`, `name`, `contact`, `type`, `pin`, `created`, `meta` ) VALUES ( '0', '" . $contactAddName . "', '" . $contactAddContact . "', '" . $contactAddType . "', '" . $pin . "', '" . time() . "', '$metaS' )";
							$wpdb->query( $sqlI );
							$contactAdd_id = $wpdb->insert_id;  //$debug .= 'Last SQL error: ' . $wpdb->last_error . ' / ' . $sqlI;
							if (!$contactAdd_id) self::log('rest_ticket/contact_add Error creating contact: ' . $wpdb->last_error . ' / ' . $sqlI, 1, $options);
							else
							{
								$args = [ 'contact' => $contactAdd_id, 'confirm' => $pin];

							}
						} else 
						{
							$contactAdd_id = $contactAdd->id;
							$args[ 'contact'] = $contactAdd_id;
						}

						//add contact to ticket
						if ($contactAdd_id)
						{
							$meta = [];
							$meta['added_ip'] = self::get_ip_address();
							$meta['added_by'] = $contact->name;
							$meta['added_time'] = time();
							$metaS = esc_sql(serialize($meta));

							$pin = self::pinGenerate();
							$sqlI = "INSERT INTO `$table_ticket_contacts` ( `tid`, `cid`, `pin`, `rep`, `status`, `meta` ) VALUES ( '" . $ticket_id . "', '" . $contactAdd_id . "', '" . $pin . "', '". $contactAddRep ."', '1', '" . $metaS . "' )";
							$wpdb->query( $sqlI );
							$ticket_contact_id = $wpdb->insert_id; //$debug .= 'Last SQL error: ' . $wpdb->last_error . ' / ' . $sqlI;
							if (!$ticket_contact_id) self::log('rest_ticket/contact_add Error adding contact: ' . $wpdb->last_error . ' / ' . $sqlI, 1, $options);

							// notify contact
							$args['ticket'] = $ticket_id;
							$args['code'] = $pin;
							$args['invitation'] = 1;
							$url = add_query_arg( $args, $options['appURL'] ) . '#vws';
							self::notifyContact($contactAdd_id, $options['notifyAddSubject'], $options['notifyAddMessage'] . ' ' . $url, $options, true, $ticket_id);

							$result = array_merge( $result, ['info'=> __('Contact was added to conversation.', 'live-support-tickets'), 'contactAddComplete' => true ] );
						}
						else 
						{
							$result = array_merge( $result, ['error'=>true, 'info'=>'Could not add contact!', 'contactAddComplete' => true ] );
						}


					}
					break;

					case 'can':
						//canMessage for later ussage
						if ($canMessage)
						{
							//insert canMessage in table cans (contact_id, title, message)
							$sqlI = "INSERT INTO `$table_cans` ( `cid`, `title`, `message` ) VALUES ( '" . $contact_id . "', '" . $canTitle . "', '" . $canMessage . "' )";
							$wpdb->query( $sqlI );
		
						}
					break;

					case 'user_option':
						$name = sanitize_text_field( $_POST['name'] ?? false );
						$value = sanitize_textarea_field( $_POST['value'] ?? false );
						if ($name && $contact->uid) if ( in_array($name, ['h5v_language'] ) ) 
						{
							update_user_meta( $contact->uid, $name, $value);
						}
					break;

				}
			
				//new message (post)
				if ($message)
				{

					$meta = [];

					//microPayments integration
					$microPayments = 0;
					if ( !$isRep && !$rep && $options['micropayments'] && class_exists( 'VWpaidMembership' ) )
					if ( ( ($creator_id || $ticket->creator) && $options['micropayments'] == 'users') || ($options['micropayments'] == 'all') ) //paid mode
					{
						$costMessage = floatval($options['micropaymentsMessage']);

						//get custom creator cost from user meta, if set
						$messageCost = get_user_meta( $creator_id, 'vwSupport_message_cost', true);
						if ($messageCost) $costMessage = floatval($messageCost);

						$userID = get_current_user_id();
						if (!$userID) self::errorExit( 'Login is required to post a new paid message.' );

						$balance = \VWpaidMembership::balance( $userID );
						$currency = \VWpaidMembership::option( 'currency' );
						if ( $balance < $costMessage ) self::errorExit( 'Your balance is not sufficient to post a new paid message: ' . $balance . $currency . ' < ' . $costMessage . $currency);

						$meta['micropayments_cost']	= $costMessage;
						$meta['micropayments_userID'] = $userID ;
						$microPayments = 1; //do transactions
					}

					//uploads
					if ($uploadMode)
					{
						$destination = $options['uploadsPath'];
						if (!file_exists($destination)) mkdir($destination);
					
						$destination.="/$ticket_id";
						if (!file_exists($destination)) mkdir($destination);	
						
						$destination.="/$contact_id";
						if (!file_exists($destination)) mkdir($destination);	


				$allowed = array( 'swf', 'jpg', 'jpeg', 'png', 'gif', 'txt', 'doc', 'docx', 'pdf', 'mp4', 'mp3', 'flv', 'avi', 'mpg', 'mpeg', 'webm', 'ppt', 'pptx', 'pps', 'ppsx', 'doc', 'docx', 'odt', 'odf', 'rtf', 'xls', 'xlsx' );

				$uploads = 0;

				if ( $_FILES ) if ( is_array( $_FILES ) ) {
						$meta['files'] = [];
						foreach ( $_FILES as $ix => $file ) {
							$filename = sanitize_file_name( $file['name'] );
							$filepath = $destination . $filename;

							$ext                       = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
							$debugData['uploadLastExt'] = $ext;
							$debugData['uploadLastF']   = $filename;

							if ( in_array( $ext, $allowed ) ) {
								if ( file_exists( $file['tmp_name'] ) ) {
									$errorUp = self::handle_upload( $file, $filepath ); // handle trough wp_handle_upload()
									
									if ( $errorUp ) {
										$response['warning'] = ( $response['warning'] ? $response['warning'] . '; ' : '' ) . 'Error uploading ' . esc_html( $filename . ':' . $errorUp );
										$debugData['lastError'] = $response['warning'];
									} else {
														//add file in chat
														$metaF = [ 'file_name' => $filename , 'file_url' => self::path2url( $filepath ), 'file_size' => self::humanSize( filesize( $filepath ) ) ];
														
														//display
														if ( in_array($ext, ['jpg', 'jpeg', 'png', 'gif'] ) ) $metaF['picture'] = $metaF['file_url'];
														if ( in_array($ext, ['mp4', 'webm'] ) ) $metaF['video'] = $metaF['file_url'];																					
														if ( in_array($ext, ['mp3'] ) ) $metaF['audio'] = $metaF['file_url'];	

														$meta['files'][] = $metaF;											
									}

									$debugData['metaFiles'] = $meta['files'];
									$debugData['uploadLast'] = $filepath;

									$uploads++;
								}
							}
						}
					}
				
				//$result = [ ...$result,  'uploadComplete' => true ];
				$result = array_merge( $result, ['uploadComplete' => true ] );
				
				$debugData['uploadCount'] = $uploads;

					}
					
					//recording
					if ($recordMode)
					{
	$destination = $options['uploadsPath'];
	if (!file_exists($destination)) mkdir($destination);

	$destination.="/$ticket_id";
	if (!file_exists($destination)) mkdir($destination);	
	
	$destination.="/$contact_id";
	if (!file_exists($destination)) mkdir($destination);	

	$debugData['_FILES'] = $_FILES;

	$allowed = array('mp3', 'ogg', 'opus', 'mp4', 'webm');

	$uploads = 0;
	$filename = '';

	if ($_FILES) if (is_array($_FILES))
			foreach ($_FILES as $ix => $file)
			{

				$filename = sanitize_file_name( $file['name'] );

				$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
				$debugData['uploadRecLastExt'] = $ext;
				$debugData['uploadRecLastF'] = $filename;

				$filepath = $destination . '/' . $filename;

				if (in_array($ext, $allowed))
					if (file_exists($file['tmp_name']))
					{
						//move_uploaded_file($file['tmp_name'], $filepath);
						self::handle_upload($file, $filepath); // handle trough wp_handle_upload(

						$debugData['uploadRecLast'] = $filepath;
						$uploads++;
						
						$filetype = wp_check_filetype($filepath);

						$url = self::path2url( $filepath );

						if ( $recordMode == 'audio' ) {
							$meta['audio'] = $url;
						} else {
							$meta['video'] = $url;
						}					
					
					}
			}

		$debugData['uploadCount'] = $uploads;


	if (!file_exists($filepath))
	{
		$debugData['warning'] = 'Recording upload failed!';
	} else 	
	{
		//$result = [ ...$result,  'recorderComplete' => true ];
		$result = array_merge( $result, ['recorderComplete' => true ] );

	}


//end recording
					}

					if ($options['multilanguage'])
					{
					$meta['language'] = $language;
					$meta['flag'] = $flag;
					}

					if ($mentionUser || $mentionMessage)
					{
						$meta['mentionUser'] = $mentionUser;
						$meta['mentionMessage'] = $mentionMessage;
					}

					$meta['ip'] = self::get_ip_address();
					$meta['location'] = self::ip2location($meta['ip']);

					$metaS = esc_sql(serialize($meta));

					$ztime = time();

					$sqlI = "INSERT INTO `$table_messages` ( `tid`, `cid`, `content`,  `meta`, `created` ) VALUES ( '" . $ticket_id . "', '" . $contact_id . "', '" . esc_sql($message) . "', '" . $metaS . "', '" . $ztime . "' )";
					$wpdb->query( $sqlI );
					$ticket_message_id = $wpdb->insert_id;
					if (!$ticket_message_id)
					{
						$debug .= '-SQL error: ' . $wpdb->last_error . ' / ' . $sqlI;
						$info .= ' Error: Message could not be sent.';
						if ($options['devMode']) $info .= $debug;
					}
					else
					{
						$info .= ' Message was sent.';

						$result = array_merge( $result, ['messageSent' => 	$ticket_message_id ] );

						//ticket update time
						$sqlU = "UPDATE `$table_tickets` SET updated='$ztime' WHERE id='$ticket_id'";
						$wpdb->query( $sqlU );

						//contact last message time
						$sqlU = "UPDATE `$table_contacts` SET lmessage='$ztime' WHERE id='$contact_id'";
						$wpdb->query( $sqlU );

						if ($microPayments) //transactions
						{
							$conversationURL = add_query_arg( [ 'ticket' => $ticket_id ], $options['appURL'] ) . '#message' . $ticket_message_id ; //generic - no contact access

							//pay
							\VWpaidMembership::transaction( 'support_message', $userID, - $costMessage, 'New inquiry in conversation <a href="' . $conversationURL . '">#' . $ticket_id . '/' . $ticket_message_id . '</a>'  );

							$balance = \VWpaidMembership::balance();
						}

						//add representative contact if missing, only when participated in ticket
						if ($rep)
						{
							$sqlS = "SELECT * FROM `$table_ticket_contacts` WHERE tid='$ticket_id' AND cid = '$contact_id' AND rep = '1' LIMIT 1";
							$ticket_contact = $wpdb->get_row( $sqlS );
							if (!$ticket_contact)
							{
								$pin = self::pinGenerate();
								$sqlI = "INSERT INTO `$table_ticket_contacts` ( `tid`, `cid`, `pin`, `rep` ) VALUES ( '" . $ticket_id . "', '" . $contact_id . "', '" . $pin . "', '1' )";
								$wpdb->query( $sqlI );
								$ticket_contact_id = $wpdb->insert_id;
								if (!$ticket_contact_id) $debug .= '-SQL error: ' . $wpdb->last_error . ' / ' . $sqlI;
								else $ticket_contact = $wpdb->get_row( $sqlS );

							}
						}

							    //update ticket status after answer
							    $sqlU = '';
								$tMeta = unserialize($ticket->meta);
								if (!is_array($tMeta)) $tMeta = [];
								
								//set answered if new and, accessed as rep or representative for ticket
								if ( $ticket->status == 0 && ( $rep || $isRep ) )
								{

									$tMeta['answered'] = time();
									$tMeta['answeredContact'] = $contact_id;
									$tMeta['answeredRep'] = $rep;
									$tMeta['answeredIsRep'] = $isRep;
									$metaS = esc_sql(serialize($tMeta));


									$sqlU = "UPDATE `$table_tickets` SET status='1', meta='$metaS' WHERE id='$ticket_id'";
									if ( $options['micropayments'] && $ticket->creator && class_exists( 'VWpaidMembership' ) )
									{
										self::micropaymentsEarn( $ticket, $options ); //pay creator
										$balance = \VWpaidMembership::balance();
									}

									//new reply by rep
									self::tagSet('new', $ticket_id, 'conversation');

								}

								//set pending answered
								if ( $ticket->status == 5 && ( $rep || $isRep ) )
								{

									$tMeta['answered'] = time();
									$tMeta['answeredContact'] = $contact_id;
									$tMeta['answeredRep'] = $rep;
									$tMeta['answeredIsRep'] = $isRep;
									$metaS = esc_sql(serialize($tMeta));
									$sqlU = "UPDATE `$table_tickets` SET status='6', meta='$metaS' WHERE id='$ticket_id'";

									//no payment for unconfimed tickets
	
								}

								//set back to new if other contact messaged again
								if ( $ticket->status == 1 && !$rep && !$isRep ) 
								{
									$tMeta['opened'] = time();
									$tMeta['openedContact'] = $contact_id;		
									$metaS = esc_sql(serialize($tMeta));
	
									$sqlU = "UPDATE `$table_tickets` SET status='0', meta='$metaS'  WHERE id='$ticket_id'";

								}

								if ($sqlU)
								{
									$wpdb->query( $sqlU );
									self::updateCounters($options);
								}

						//notify other contacts
						$sqlS = "SELECT * FROM `$table_ticket_contacts` WHERE tid='$ticket_id' AND cid != '$contact_id'";
						$ticket_contacts = $wpdb->get_results( $sqlS );
						if ($ticket_contacts) foreach ($ticket_contacts as $tc)
						{
							$contactID = $tc->cid;
							$pin = $tc->pin;

							$url = add_query_arg( [ 'ticket' => $ticket_id, 'contact' => $contactID, 'code' => $pin ], $options['appURL'] ) . '#vws';

							$subject = $options['notifyTicketSubject'];
							$messageNotify = $options['notifyTicketMessage'] . ' ' . $url;
							$replace = [ '{sender}' => $contact->name ?? '{unknown}', '{department}' => self::departmentName($ticket->department, $options) ];
							$subject = strtr($subject, $replace);
							$messageNotify = strtr($messageNotify, $replace);
							self::notifyContact($contactID, $subject, $messageNotify, $options, false, $ticket_id . '-' . $contact->id ); //notify other contacts of new message from $contact
						}

						//reload ticket after posting new message
						$sqlS = "SELECT * FROM `$table_tickets` WHERE id='$ticket_id' LIMIT 1";
						$ticket =  $wpdb->get_row( $sqlS );
					}
				}

				//ticket data

				//ticket contact list
				$sqlSC = "SELECT tc.id, tc.name, ttc.rep FROM `$table_contacts` tc, `$table_ticket_contacts` ttc  WHERE ttc.tid = '" . $ticket_id . "' AND ttc.cid = tc.id";
				$resultsC = $wpdb->get_results($sqlSC);

				$contacts = [];
				foreach ($resultsC as $rC)
				{
					$contacts[ $rC->id ] = [ 'id' => intval($rC->id), 'name' => $rC->name, 'rep' => intval($rC->rep), 'info' => self::contactInfo( $contact, $rC, $options )];
				}

				//if current contact is not representative or admin, tag ticket & contact opened
				if (!$isRep && !current_user_can('administrator'))
				{
					//get ticket tags ($tag=>$time array) and add opened tag if not already present
					$tags = self::tagGet($ticket_id, 'conversation');
					if (!array_key_exists('opened', $tags)) 
					{
						self::tagSet('opened', $ticket_id, 'conversation');
					}

					//remove new reply tag
					if ( array_key_exists('new', $tags) ) self::tagRemove('new', $ticket_id, 'conversation'); //remove new tag

					//also tag contact for opening a conversation page
					$tags = self::tagGet($contact_id, 'contact');
					if (!array_key_exists('opened', $tags)) 
					{
						self::tagSet('opened', $contact_id, 'contact');
					}

				}

				//ticket messages list
				$messages = [];

				$sqlS = "SELECT * FROM `$table_messages` WHERE tid='$ticket_id' ORDER BY `created` ASC";
				$results = $wpdb->get_results($sqlS);
				if ($results) foreach ($results as $r)
				{
					$msg = [];
					$msg['ID'] = intval($r->id);
					$msg['contactID'] = intval($r->cid);
					$msg['contactName'] = self::contactName( $msg['contactID'], $contacts);
					$msg['time'] = intval($r->created);
					$msg['text'] = html_entity_decode($r->content);

					$meta = unserialize($r->meta);
					if (!is_array($meta)) $meta = [];

					if ($options['multilanguage'])
					{
					if ($meta['flag'] ?? false) $msg['flag'] = $meta['flag'];
					if ($meta['language'] ?? false) $msg['language'] = $meta['language'];
					}

					if ($meta['video'] ?? false) $msg['video'] = $meta['video'];
					if ($meta['audio'] ?? false) $msg['audio'] = $meta['audio'];
					if ($meta['picture'] ?? false) $msg['picture'] = $meta['picture'];
					if ($meta['files'] ?? false) $msg['files'] = $meta['files'];

					if ($meta['mentionUser'] ?? false) $msg['mentionUser'] = $meta['mentionUser'];
					if ($meta['mentionMessage'] ?? false) 
					{
						$msg['mentionMessage'] = $meta['mentionMessage'];
						//retrieve mentioned message and include first 50 characters
						$sqlS = "SELECT * FROM `$table_messages` WHERE id='" . $meta['mentionMessage'] . "' LIMIT 1";
						$mentionMessage = $wpdb->get_row( $sqlS );
						if ($mentionMessage) $msg['mentionMessageText'] = substr($mentionMessage->content, 0, 50);
					}
					
					$msg['info'] = self::messageInfo( $contact, $r, $msg, $options );

					$messages[] = $msg;

					//also update message stats (read) for other contacts, except for WP admin 					
					if ( isset($contact) && $contact->id != $r->cid && !current_user_can('administrator'))
					{
						//create a $contactKey containing contact id and name with any special non alphanumeric characters removed
						$contactKey = 'c' . $contact->id . '-'. preg_replace("/[^a-zA-Z0-9]+/", "", $contact->name);
						$updateMeta = false;
						
						//read
						if (! array_key_exists($contactKey. '_read', $meta)) { 
							$meta[$contactKey. '_read'] = date('Y-m-d H:i:s'); 
							$meta[$contactKey. '_read_ip'] = self::get_ip_address(); 
							$meta[$contactKey. '_read_location'] = self::ip2location($meta[$contactKey. '_read_ip']);			
							$updateMeta = true; 

							//get message tags ($tag=>$time array) and add read tag if not already present
							if (!$isRep)
							{
								$tags = self::tagGet($r->id, 'message');
								if (!array_key_exists('read', $tags)) 
								{
									self::tagSet('read', $r->id, 'message');
								}
							}			

						}

						if ($updateMeta)	
						{
							$metaS = esc_sql(serialize($meta));
							$sqlU = "UPDATE `$table_messages` SET meta='$metaS' WHERE id='" . $r->id . "'";
							$wpdb->query( $sqlU );
						}
					}

				} else $debug .= '-SQL error: ' . $wpdb->last_error . ' / ' . $sqlS;

				//canned messages
				if ($canMessage || !$cansLoaded)
				{
					//load cans
					$sqlS = "SELECT * FROM `$table_cans` WHERE cid='$contact_id' ORDER BY id DESC";
					$cans = $wpdb->get_results( $sqlS );
					$canMessages = [];
					$canMessages[] = [ 'key' => 'Nevermind',  'text' => 'Nevermind', 'value' => '' ];

					foreach ($cans as $can)
					{
						$canMessages[] = [ 'key' => $can->title,  'text' => $can->title, 'value' => $can->message ];
					}

					//add to $result 
					//$result = [ ...$result,  'cans' => $canMessages, 'canComplete' => true  ];
					$result = array_merge($result, ['cans' => $canMessages, 'canComplete' => true ] );
				}

				//ticket access info
				$accessContact = ['id' => intval($contact->id), 'name' => $contact->name, 'contact' => $contact->contact, 'type' => $contact->type ];

				if ($rep) $accessURL = add_query_arg( [ 'ticket' => $ticket_id, 'contact' => $contact_id, 'rep' => $rep ], $options['appURL'] ) . '#vws';
				else $accessURL = add_query_arg( [ 'ticket' => $ticket_id, 'contact' => $contact_id, 'code' => $pin ], $options['appURL'] ) . '#vws';

				if (isset($ticket) && $ticket->meta) $tmeta = unserialize($ticket->meta);
				if (!is_array($tmeta)) $tmeta = [];
				$tmeta['access_time'] = time();

				$representative = self::isRepresentative($contact, $ticket);

				//valid ticket data
				//$result =[ ...$result,  'ticket_id' => intval($ticket_id), 'ticket_status' => self::statusLabel($ticket->status), 'contact_id' => intval($contact_id), 'pin' => $pin, 'representative' => $representative, 'contact' => $accessContact, 'url' => $accessURL, 'departmentMeta'=> $departmentMeta, 'meta' => $tmeta, 'contacts' => $contacts, 'cansLoaded' => $cansLoaded,  'messages' => $messages ] ;
				$result = array_merge($result, [ 'ticket_id' => intval($ticket_id), 'ticket_status' => self::statusLabel($ticket->status), 'contact_id' => intval($contact_id), 'pin' => $pin, 'representative' => $representative, 'contact' => $accessContact, 'url' => $accessURL, 'departmentMeta'=> $departmentMeta, 'meta' => $tmeta, 'contacts' => $contacts, 'cansLoaded' => $cansLoaded,  'messages' => $messages ] );


				if (isset($balance)) $result['balance'] = $balance; //update balance if defined

				//ticket tags
				$result['tags'] = self::tagGet($ticket_id, 'conversation');


				if ($options['devMode']) 
				{
					$result['debug'] = $debug;
					$result['debugData'] = $debugData;
				}

				if ($info) $result['info'] = $info;


			}//end if ticket

		}else $result = [ 'error' => true, 'info' => 'Error: Ticket access parameters missing!', 'debugData'=> $options['devMode'] ? $debugData : 'disabled' ];

		if (!$result) $result = [ 'error' => true, 'info' => $info . ' Error:' . ( $options['devMode'] ? " $debug" : '' ), 'debugData'=> $options['devMode'] ? $debugData : 'disabled'  ];

		echo json_encode( $result );
		die();
	}

	function rest_contact_confirm(\WP_REST_Request $request)
	{
		$params = $request->get_params();

		$contact_id = intval( $params['contact_id'] ?? 0 );
		$pin = trim( sanitize_text_field( $params['confirm'] ?? '' ) );
		$ticket = intval( $params['ticket'] ?? 0 );

		if ($contact_id && $pin)
		{
			$options = self::getOptions();

			$ip = self::get_ip_address();

			//cooldown to prevent abuse from IP
			if (!self::timeTo('confirm' . str_replace('.','_', $ip), 10, $options)) 
			{
				$result = [ 'error' => true, 'confirmed' => false, 'found' => true, 'info' => 'Cooldown: Please wait a few seconds before trying confirmation again.' ];
				echo json_encode( $result );
				die();
			}

			global $wpdb;
			$table_contacts = $wpdb->prefix . 'vws_contacts';
			$table_ticket_contacts = $wpdb->prefix . 'vws_ticket_contacts';
			$table_tickets = $wpdb->prefix . 'vws_tickets';

			$sqlS = "SELECT * FROM `$table_contacts` WHERE id = '$contact_id' AND pin = '$pin' LIMIT 1";
		    $contact = $wpdb->get_row( $sqlS );

			if ($contact)
			{	//found: set confirmed & return

				 $registerInfo = '';
				 $mForms = [];

				if ( !$contact->confirmed )
				{
					$meta = unserialize($contact->meta);
					if (!is_array($meta)) $meta = [];
					$meta['confirmed_ip'] = self::get_ip_address();
					$meta['confirmed_location']	= self::ip2location($meta['confirmed_ip']);

					if (array_key_exists('forms', $meta)) $mForms = $meta['forms'];
					else $mForms = [];

					$metaS = esc_sql(serialize($meta));

				$sqlU = "UPDATE `$table_contacts` SET confirmed = '" . time() . "', meta ='$metaS' WHERE id = '$contact_id'  AND pin = '$pin' LIMIT 1";
				$wpdb->query( $sqlU );
			
				//form actions on confirm, for all forms
				$formOutput = '';
				foreach ($mForms as $formName => $time) $formOutput .= self::formActions($formName, $contact_id, $options);
				$registerInfo .= $formOutput;

				 if ($options['registerUser'] && $contact->type == 'email')
				 {
					 $username = sanitize_user( $contact->name );
					 $random_password = wp_generate_password( 10, false );
					 $user_id = wp_create_user( $username, $random_password, $contact->contact );

					 if ($user_id)
					 {
						 update_user_meta( $user_id, 'videowhisper_ip_register', self::get_ip_address() ); // registration ip
						 update_user_meta( $user_id, 'created_by', 'live-support-tickets/contact_confirm' ); // for troubleshooting registration origin

						 wp_new_user_notification($user_id, null, 'user');
						 $creds = array(
							 'user_login'    => $username,
							 'user_password' => $random_password,
							 'remember'      => true
						 );
						 $user = wp_signon( $creds, is_ssl() ); //causes incorrect nonce?
						 wp_set_current_user($user_id);

						 $registerInfo = ' An user account was created. Check your email for login details.';
					 } else 
					 {
						$registerInfo = ' Failed creating new user: ' . ( $user_id->get_error_message() ?? 'Error' );
						self::log('rest_contact_confirm' . ' Failed creating new user: ' . ( $user_id->get_error_message() ?? 'Error' ), 1, $options );
					 }
				 }

				 //
				}

				$session = ['login' => 1, 'nonce' => wp_create_nonce( 'wp_rest' ) ]; //update nonce

				$result =[ 'confirmed' => true, 'contact' => ['id' => intval($contact->id), 'name' => $contact->name, 'contact' => $contact->contact , 'type' => $contact->type, 'confirmed' => true ], 'info' => 'Contact was confirmed.' . $registerInfo, 'session' => $session ];

				if ($ticket) 
				{
					//check if contact is in ticket
					$sqlS = "SELECT * FROM `$table_ticket_contacts` WHERE tid = '$ticket' AND cid = '$contact_id' LIMIT 1";
					$ticketContact = $wpdb->get_row( $sqlS );
					
					//if found and ticket status is 5 set to 0
					if ($ticketContact)
					{
						$sqlU = "UPDATE `$table_tickets` SET status = '0' WHERE id = '$ticket' AND status = '5' LIMIT 1";
						$wpdb->query( $sqlU );

						$result['ticket'] = $ticket;
						$result['pin'] = $ticketContact->pin;
					}

				}

				if (count($mForms)) {
					$result['forms'] = $mForms;

					foreach ($mForms as $formName => $time) {
						if ( array_key_exists( $formName, $options['forms'] ) )
						{
						$form = $options['forms'][ $formName ];
						if ($form['gtag']) $result['gtag'][$formName] = stripslashes($form['gtag']);
						}
					}
				}


				if ($options['devMode'] && isset($sqlU) ) $result['debugSQL'] = 'SQL: ' . ( $sqlU ?? '' ) . ' | ' . $wpdb->last_error;
				
				if ($contact->confirmed) $result[ 'info' ] =  __('This contact was already confirmed.', 'live-support-tickets');

			} 
			else
			{
				$debug = 'SQL error: ' . $wpdb->last_error . ' / ' . $sqlS;

				//check if contact exists
				$sqlS = "SELECT * FROM `$table_contacts` WHERE id = '$contact_id' LIMIT 1";
				$contactE = $wpdb->get_row( $sqlS );

				//special results if contact exists or not
				if ($contactE) $result = [ 'confirmed' => false, 'found' => true, 'info' => 'Contact could not be confirmed because confirmation code is incorrect. Review and fill code or try using the link from email notification!' . ( $options['devMode'] ? " $debug" : '' ) ];
				else $result = [ 'confirmed' => false, 'found' => false, 'info' => 'Contact is not available. Retry using the link from email notification or try registering with a different email provider!' . ( $options['devMode'] ? " $debug" : '' ) ];

				self::log('rest_contact_confirm ' . $debug, 1, $options);
			}
		} else	$result = [ 'confirmed' => false, 'info' => 'Confirmation parameters missing!' ];

		echo json_encode( $result );
		die();
	}

	static function invalidContactName($name)
	{

		$forbiddenText = array('admin', 'administrator');

		$forbiddenNames = array('admin', 'administrator', 'root', 'webmaster', 'hostmaster', 'postmaster', 'info', 'support', 'contact', 'help', 'service', 'sales', 'billing', 'abuse', 'noc', 'security', 'web', 'www', 'ftp', 'mail', 'smtp', 'pop', 'imap', 'admincp', 'cpanel', 'whm', 'webmail', 'ssl', 'dns', 'api', 'dev', 'demo', 'test', 'stage', 'staging', 'local', 'localhost', 'example', 'invalid', 'unknown', 'anonymous', 'guest', 'user', 'customer', 'client', 'member', 'subscriber', 'register', 'login', 'logout', 'password', 'forgot', 'reset', 'change', 'profile', 'account', 'edit', 'update', 'delete', 'remove', 'block', 'ban', 'spam', 'abuse', 'violation', 'fraud', 'scam', 'phish', 'malware', 'virus', 'trojan', 'worm', 'spyware', 'adware', 'ransomware', 'hacker', 'cracker', 'attacker', 'intruder', 'exploit', 'vulnerability', 'security', 'secure', 'safe', 'danger', 'warning', 'alert', 'emergency', 'urgent', 'important', 'critical', 'fatal', 'error', 'problem', 'issue', 'bug', 'defect', 'fault', 'failure', 'down', 'offline', 'unavailable', 'maintenance', 'upgrade', 'update', 'new', 'fresh', 'latest', 'current', 'modern', 'future', 'next', 'upcoming', 'coming', 'soon', 'later', 'tomorrow', 'today', 'now', 'immediate', 'urgent', 'quick', 'fast', 'speedy', 'rapid', 'swift', 'express', 'instant', 'sudden', 'abrupt', 'unexpected', 'surprise', 'shock', 'jolt');

		if ( in_array( strtolower( $name ), $forbiddenNames ) ) return 'Invalid Name - Forbidden name: ' . $name;

		if ( str_replace( $forbiddenText, '', strtolower( $name ) ) != strtolower( $name ) )
		{
			//find $forbiddenText in $name and return error
			foreach ($forbiddenText as $text)
			{
				if ( strpos( strtolower( $name ), $text ) !== false ) return 'Invalid Name - Forbidden text: ' . $text;
			}
			return 'Unknown Forbidden text.';
		} 

		if ( !validate_username( $name )) return 'Invalid Name: Invalid WP username.';

		//check if already in use for a WP user, either as login or display name or nickname
		$user = get_user_by( 'login', $name);
		if ($user) return 'Username is already in use: ' . $name;
		$user = get_user_by( 'slug', $name);
		if ($user) return 'Username is already in use as slug: ' . $name;
		$user = get_user_by( 'nicename', $name);
		if ($user) return 'Username is already in use as nickname: ' . $name;

		return false;
	}

	function rest_contact_new(\WP_REST_Request $request)
		{
			$params = $request->get_params();

			$name = sanitize_text_field( $params['name'] ?? '' );
			$email = sanitize_email( $params['email'] ?? '' );
			$type = 'email';

			//niche: new ticket target
			$content_id = intval( $params['content_id'] ?? 0 );
			$creator_id = intval( $params['creator_id'] ?? 0 );
			$site_id = intval( $params['site_id'] ?? 0);

			//target
			$form = sanitize_text_field( $params['form'] ?? '');
			$department = sanitize_text_field( $params['department'] ?? '');

			//form fields
			$fields = self::arrayValue( $params, 'fields', null );

			//message
			$department_id = sanitize_text_field( $params['department_id'] ?? 0 );
			$message = sanitize_textarea_field( $params['message'] ?? '' );
			$language = sanitize_text_field( $params['language'] ?? 'en-us');
			$flag = sanitize_text_field( $params['flag'] ?? 'us');

			$options = self::getOptions();

			global $wpdb;
			$table_contacts = $wpdb->prefix . 'vws_contacts';
			$table_tickets = $wpdb->prefix . 'vws_tickets';
			$table_ticket_contacts = $wpdb->prefix . 'vws_ticket_contacts';
			$table_messages = $wpdb->prefix . 'vws_messages';

			if ($name && $email)
			{
			
			$invalid = self::invalidContactName($name);
			if ( $invalid ) self::errorExit( $invalid );

			if ($type == 'email')  //check if email belongs to existing user
			{
			$user = get_user_by( 'email', $email);
			if ($user) 
			{
				//check if user has contact and send a notification
				$sqlS = "SELECT * FROM `$table_contacts` WHERE contact = '$email' AND type = 'email' LIMIT 1";
				$contact = $wpdb->get_row( $sqlS );
				if ($contact)
				{
				//notify existing contact
				$args = [ 'contact' => $contact->id, 'confirm' => $contact->pin ];

				if ($form) $args['form'] = urlencode( $form );
				if ($department) $args['department'] = urlencode( $department );

				$url = add_query_arg( $args , $options['appURL'] ) . '#vws';
		 
				$subject = $options['notifyConfirmSubject'];
				$messageNotify = $options['notifyConfirmMessage'] . " \n" . $url;
				$replace = [ '{code}' => $args['confirm'] ];
				$subject = strtr($subject, $replace);
				$messageNotify = strtr($messageNotify, $replace);

				$notifyError = self::notifyContact($contact->id, $subject, $messageNotify, $options);

				self::errorExit('This email is already registered. Login or check your email for access link and use it to proceed. Check Spam folder if not found. Register with a different email if your current mail server does not receive notification for any reason.' . ( $notifyError ? ' Notify Error: ' . $notifyError : ' Notification email was re-sent.') );
				}
				else self::errorExit( $email . ' Email address belongs to registered user without contact details. Please login to get a contact or try with a different email.');
			}

			//if invalid email return error
			if (!is_email($email)) self::errorExit('Invalid email address.');
			}

			//check if contact exists 
			$sqlS = "SELECT * FROM `$table_contacts` WHERE contact = '$email' AND type = '$type' LIMIT 1";
		    $contact = $wpdb->get_row( $sqlS );

			$args = [];

			if ($contact)
			{
				//notify existing contact
				$args = [ 'contact' => $contact->id, 'confirm' => $contact->pin ];

				if ($form) $args['form'] = urlencode( $form );
				if ($department) $args['department'] = urlencode( $department );

				$url = add_query_arg( $args , $options['appURL'] ) . '#vws';


				$subject = $options['notifyConfirmSubject'];
				$messageNotify = $options['notifyConfirmMessage'] . " \n" . $url;
				$replace = [ '{code}' => $args['confirm'] ];
				$subject = strtr($subject, $replace);
				$messageNotify = strtr($messageNotify, $replace);

				$notifyError = self::notifyContact($contact->id, $subject, $messageNotify, $options);

				self::errorExit('This email is already registered. Check your email for access link and use it to proceed. Check Spam folder if not found. Register with a different email if your current mail server does not receive notification for any reason.' . ( $notifyError ? ' Notify Error: ' . $notifyError : ' Notification email was re-sent.') );

				self::log('rest_contact_new: already registered ' . $contact->id . ' ' . $contact->name . ' ' . $contact->contact, 2, $options);
			}


			$meta = [];
			$meta['created_ip'] = self::get_ip_address();
			$meta['created_location'] = self::ip2location($meta['created_ip']);
			$meta['created_by'] = 'rest_contact_new';

			if ($department) $meta['created_department'] = $department;

			if ($department_id)
			{
				$meta['created_depId'] = $department_id;
				$departmentA = self::departments(0, 0, 0, $options, $department_id);
				$meta['created_depName'] = $departmentA['text'];
			}

			//fields
			if ( is_array($fields) )
			{
				$mFields = [];
				foreach ($fields as $key => $value)
				{
					$mFields[ sanitize_text_field( $key ) ] = sanitize_textarea_field( $value );
				}
				$meta['fields'] = $mFields;
			}

			if ($form) $meta['forms'] = [ $form => time() ];

			$metaS = esc_sql(serialize($meta));

			$uid = get_current_user_id();
			$pin = self::pinGenerate();

			$sqlI = "INSERT INTO `$table_contacts` ( `uid`, `name`, `contact`, `type`, `pin`, `created`, `meta` ) VALUES ( '$uid', '" . $name . "', '" . $email . "', '" . $type . "', '" . $pin . "', '" . time() . "', '$metaS' )";
			$wpdb->query( $sqlI );
			$contact_id = $wpdb->insert_id;
			$error = 'SQL error: ' . $wpdb->last_error . ' / ' . $sqlI;
			}
			else
			{
				$contact_id = 0;
				$error = 'Invalid name and/or email.';
			}

			if ($contact_id)
			{

				$args = [ 'contact' => $contact_id, 'confirm' => $pin];
				if ($content_id) $args['content'] = $content_id;
				if ($creator_id) $args['creator'] = $creator_id;
				if ($site_id) $args['site'] = $site_id;

				if ($form) $args['form'] = urlencode( $form );
				if ($department) $args['department'] = urlencode( $department );
				
				$result = ['created' => true, 'info' =>  __('Please check your Inbox for confirmation email with link to proceed further. Check Spam folder if not found. Register with a different email if your current mail server does not receive notification for any reason.', 'live-support-tickets') . ( $options['devMode'] ? " $url" : '' ) , 'contact' => intval($contact_id)];

				//Dev test: http://localhost:3000/?contact=4&confirm=AsbOZT&content=1

				if (is_array($fields)) //form update if includes fields
				{
					$ztime = time();
					$sqlU = "UPDATE `$table_contacts` SET lform='$ztime' WHERE id = '$contact_id' LIMIT 1";
					$wpdb->query( $sqlU);
				}
				
			}
			else 
			{
				$result = ['created' => false, 'info' => $error ];
				self::log('rest_contact_new: ' . $error, 2, $options);
			}

			if ($options['registerConversation']) 
			if ( $department_id && $message)
			{

				//create a new ticket/conversation 
				$meta = [];
				$meta['created_contact'] = $contact_id;
				$meta['created_ip'] = self::get_ip_address();
				$meta['created_location'] = self::ip2location($meta['created_ip']);
				$meta['created_time'] = time();
				$meta['created_by'] = 'rest_contact_new';

				$department = self::departments(0, 0, 0, $options, $department_id);
				$type = $department['type'];

				//also include fields in conversation meta
				if ( is_array($fields) )
				{
					$mFields = [];
					foreach ($fields as $key => $value)
					{
						$mFields[ sanitize_text_field( $key ) ] = sanitize_textarea_field( $value );
					}
					$meta['fields'] = $mFields;
				}

				$metaS = esc_sql(serialize($meta));

				//type: 0 open 1 answered 2 closed 3 public 4 locked 5 pending
				$sqlI = "INSERT INTO `$table_tickets` ( `department`, `created`, `meta`, `status`, `type`, `site`, `content`, `creator`) VALUES ( '" . $department_id . "', '" . time() . "', '" . $metaS . "', '5', '$type', $site_id, $content_id, $creator_id )";
				$wpdb->query( $sqlI );
				$ticket_id = $wpdb->insert_id;

				if (!$ticket_id) {
				self::log('rest_contact_new: ' . $wpdb->last_error . ' / ' . $sqlI, 1, $options);
				return;
				}
				else
				{
					
					$args['ticket'] = $ticket_id;
					$result = array_merge( $result, ['ticket' => $ticket_id ] );
				}

				//add target contact to ticket and generate access pin
				$pin = self::pinGenerate();
				$sqlI = "INSERT INTO `$table_ticket_contacts` ( `tid`, `cid`, `pin`, `rep` ) VALUES ( '" . $ticket_id . "', '" . $contact_id . "', '" . $pin . "', '0' )";
				$wpdb->query($sqlI);
				$ticket_contact_id = $wpdb->insert_id;

				if (!$ticket_contact_id) {
					self::log( 'rest_contact_new: ' . $wpdb->last_error . ' / ' . $sqlI, 1, $options);
				}
				else $args['code'] = $pin;
				// code only in email (not confirmed, yet)

				//add message
				$meta = [];
				if ($options['multilanguage'])
				{
				$meta['language'] = $language;
				$meta['flag'] = $flag;
				}
				$metaS = esc_sql(serialize($meta));
				$ztime = time();

				$sqlI = "INSERT INTO `$table_messages` ( `tid`, `cid`, `content`,  `meta`, `created` ) VALUES ( '" . $ticket_id . "', '" . $contact_id . "', '" . esc_sql($message) . "', '" . $metaS . "', '" . $ztime . "' )";
				$wpdb->query( $sqlI );

				self::log('rest_contact_new & new conversation: ' . "contact=$contact_id name=$name department=$department_id ticket=$ticket_id", 4, $options);

			} else self::log('rest_contact_new - no message: ' . "contact=$contact_id name=$name department=$department_id message=" . $message, 4, $options);
			

			//notify at end
			if ($contact_id)
			{
						$url = add_query_arg( $args , $options['appURL'] ) . '#vws';

						$subject = $options['notifyConfirmSubject'];
						$messageNotify = $options['notifyConfirmMessage'] . " \n" . $url;
						$replace = [ '{code}' =>  $args['confirm'] ];
						$subject = strtr($subject, $replace);
						$messageNotify = strtr($messageNotify, $replace);

						self::notifyContact($contact_id, $subject, $messageNotify, $options, true);
			}

			echo json_encode( $result );
			die();
		}


//trait
}