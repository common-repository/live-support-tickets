<?php
namespace VideoWhisper\LiveSupport;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

trait Shortcodes {

 static function rolesUser( $csvRoles, $user)
{
	// user has any of the listed roles
	// if (self::rolesUser( $option['rolesDonate'], wp_get_current_user() )
	
	if (!$csvRoles) return true; //all allowed if not defined
	if (!$user) return false;
	if (!is_array($user->roles)) return false;
	
	$roles = explode(',', $csvRoles);
	foreach ($roles as $key => $value) $roles[$key] = trim($value);
	
	if ( self::any_in_array( $roles, $user->roles ) ) return true;
	
	return false;
}

static function any_in_array( $array1 = null, $array2 = null ) {

	if (!is_array($array1)) return false;
	if (!is_array($array2)) return false;
	
	foreach ( $array1 as $value ) {
		if ( in_array( $value, $array2 ) ) {
			return true;
		}
	}
		return false;
}

function vws_settings()
{
	//admin-ajax : retrieve logged in user session info, nonce, contact, app config

	$options = self::getOptions();

	//target
	$token = sanitize_text_field( trim( $_GET['token'] ?? '' ) ); //form name

	// output clean
	ob_clean();

	echo '[VideoWhisper Streaming Server Settings]';
	echo PHP_EOL;

	if (!$token) die('error="Token not provided."');

			if ($options['accounts_db'] && $options['accounts_host'])
			{
				$accountsDB = new \mysqli($options['accounts_host'], $options['accounts_user'], $options['accounts_pass'], $options['accounts_db'], $options['accounts_port']);
				if ($accountsDB->connect_error) 
				{
					echo 'error="Database connection failed: ' . esc_html( $accountsDB->connect_error ) . '"';
				}
				else
				{
	
					//get account by $token
					$account = $accountsDB->query("SELECT * FROM accounts WHERE token='$token'")->fetch_assoc();
					if ($account)
					{
						$properties = $account['properties'] ? json_decode($account['properties'], true) : array();

											//if any pin is missing generate it and save it
											$save = 0;
											if (!($properties['broadcastPin'] ?? false)) { $properties['broadcastPin'] = self::pinGenerate(); $save = 1; }
											if (!($properties['playbackPin'] ?? false)) { $properties['playbackPin'] = self::pinGenerate(); $save = 1; }
											if ($save) $accountsDB->query("UPDATE accounts SET properties='" . json_encode($properties) . "' WHERE id='" . $account['id'] . "'");

						$settings = [ 'vwsAccount' => $account['name'], 'vwsToken' => $account['token'], 'vwsSocket' => $options['accountsWebRTC'],  'videowhisperRTMP' => $options['accountsRTMP'], 'videowhisperHLS' => $options['accountsHLS'], 'broadcastPin' => $properties['broadcastPin'], 'playbackPin' => $properties['playbackPin'], 'rtmpServer'=>'videowhisper', 'webrtcServer'=>'videowhisper' ];

						$planId = $account['planId'];
						$planData = $accountsDB->query("SELECT * FROM plans WHERE id='$planId'")->fetch_assoc();						
						$plan = $planData ? json_decode($planData['properties'], true) : array();
						if ($plan)
						{
							if ($plan['bitrate']) $settings['webrtcVideoBitrate'] = $plan['bitrate'];
							if ($plan['audioBitrate']) $settings['webrtcAudioBitrate'] = $plan['audioBitrate'];
							if ($plan['bitrate']) $settings['maxVideoBitrate'] = $plan['bitrate'];
							if ($plan['audioBitrate']) $settings['maxAudioBitrate'] = $plan['audioBitrate'];
							if ($plan['width']) $settings['maxWidth'] = $plan['width'];
							if ($plan['height']) $settings['maxHeight'] = $plan['height'];
							if ($plan['framerate']) $settings['maxFramerate'] = $plan['framerate'];
							if ($plan['connections']) $settings['totalConnections'] = $plan['connections'];
							if ($plan['totalBitrate']) $settings['totalBitrate'] = $plan['totalBitrate'];
						}

						if ( $options['p_videowhisper_support_accounts']) $settings['accountURL'] = get_permalink( $options['p_videowhisper_support_accounts'] );
						$settings['planURL'] = 'https://webrtchost.com/hosting-plans/';
						$settings['supportURL'] = 'https://consult.videowhisper.com/';

						//output settings
							foreach ($settings as $key => $value) echo esc_attr( $key ) . '="' . esc_attr( $value ) . '"' . PHP_EOL;

						if ($options['accountsAPI']) if ( self::timeTo( 'updateAccountsBySettings' . $account['id'], 180, $options ) ) self::updateAccountsAPI($options);
					}
					else echo 'error="Account not found for provided token."';

				}
			}else echo 'error="Accounts db not configured."';

			echo PHP_EOL;
			die();
}



	static function videowhisper_support_accounts ($atts)
	{

		self::enqueueUI();

		//list and manage accounts
		if ( ! is_user_logged_in() ) {				
			return '<i class="lock icon"></i>' . __( 'Login to Access!', 'live-support-tickets' ) . '<BR><a class="ui button primary qbutton" href="' . wp_login_url() . '">' . __( 'Login', 'live-support-tickets' ) . '</a>';
		}

		$options = self::getOptions();

		$current_user = wp_get_current_user();
		$uid = intval($current_user->ID);
		
		global $wpdb;
		$table_contacts = $wpdb->prefix . 'vws_contacts';

		//select row from table_contacts where uid property is current user id
		$contact = $wpdb->get_row( "SELECT * FROM `$table_contacts` WHERE uid = '$uid'" );
		if (!$contact) return 'No contact found!';

		$contactId = $contact->id;

		$htmlCode = '<h4>My Accounts</h4>';


			//if database name is set, test connection and list tables with row count
			if ($options['accounts_db'] && $options['accounts_host'])
			{
				$accountsDB = new \mysqli($options['accounts_host'], $options['accounts_user'], $options['accounts_pass'], $options['accounts_db'], $options['accounts_port']);
				if ($accountsDB->connect_error) $htmlCode .= '<BR><font color="red">Database connection failed: ' . $accountsDB->connect_error . '</font>';
				else
				{

					//get account status
					if (isset($_GET['status']) && isset($_GET['nonce']) && wp_verify_nonce( $_GET['nonce'], 'status' ) )	
					{
						$accountId = intval( $_GET['status'] );
						$account = $accountsDB->query("SELECT * FROM accounts WHERE id='$accountId'")->fetch_assoc();
						$properties = $account['properties'] ? json_decode($account['properties'], true) : array();

						if ( $account['contactId'] != $contactId && !current_user_can( 'manage_options' ) ) return 'Not permitted!';

						$htmlCode .= '<div class="ui segment small"><p>Status for account: ' . esc_html( $account['name'] ) . '</p>';

						if ($account['token']) $htmlCode .= esc_html( self::statusAccountsAPI($account['token'], $options) );
						else $htmlCode .= 'Missing account token!';

						$htmlCode .= '</div>';

					}

					//display info when GET configureApps

					if (isset($_GET['configureApps']) && isset($_GET['nonce']) && wp_verify_nonce( $_GET['nonce'], 'configureApps' ) )	
					{
						$accountId = intval( $_GET['configureApps'] );
				
						$account = $accountsDB->query("SELECT * FROM accounts WHERE id='$accountId'")->fetch_assoc();
						$properties = $account['properties'] ? json_decode($account['properties'], true) : array();

						if ( $account['contactId'] != $contactId && !current_user_can( 'manage_options' ) ) return 'Not permitted!';

						//if any pin is missing generate it and save it
						$save = 0;
						if (!($properties['broadcastPin'] ?? false)) { $properties['broadcastPin'] = self::pinGenerate(); $save = 1; }
						if (!($properties['playbackPin'] ?? false)) { $properties['playbackPin'] = self::pinGenerate(); $save = 1; }
						if ($save) $accountsDB->query("UPDATE accounts SET properties='" . json_encode($properties) . "' WHERE id='$accountId'");

						//display info


						$htmlCode .= '<div class="ui segment small"><p>Configure apps for account: ' . esc_html( $account['name'] ) . '</p>';

						$htmlCode .= '<div class="ui form">';

						$settingsURL = admin_url('admin-ajax.php') . '?action=vws_settings&token=' . urlencode($account['token']);
						$htmlCode .= '<div class="field">';
						$htmlCode .= '<label>Settings Import URL</label>';
						$htmlCode .= '<div class="ui action input">
                            <input type="text" class="copyInput" value="' . $settingsURL . '" readonly>
                            <button type="button" name="copyToken" value="copy" class="copyToken ui right icon button">
                                <i class="clipboard icon"></i>
                            </button>
                        </div>';
						$htmlCode .= '<small>Use this URL to automatically import all settings in solutions that support it.</small>';
						$htmlCode .= '</div>';

						$htmlCode .= '<div class="field">';
						$htmlCode .= '<label>Account Name</label>';
						$htmlCode .= '<div class="ui action input">
                            <input type="text" class="copyInput" value="' . $account['name']. '" readonly>
                            <button type="button" name="copyToken" value="copy" class="copyToken ui right icon button">
                                <i class="clipboard icon"></i>
                            </button>
                        </div>';
						$htmlCode .= '</div>';

						$htmlCode .= '<div class="field">';
						$htmlCode .= '<label>Account Token</label>';
						$htmlCode .= '<div class="ui action input">
                            <input type="text" class="copyInput" value="' . $account['token']. '" readonly>
                            <button type="button" name="copyToken" value="copy" class="copyToken ui right icon button">
                                <i class="clipboard icon"></i>
                            </button>
                        </div>';
						$htmlCode .= '<small>Account token is used in multiple applications/integrations for validating streams and requests.</small>';
						$htmlCode .= '</div>';

						if ($options['accountsWebRTC'])
						{
						$htmlCode .= '<div class="field">';
						$htmlCode .= '<label>WebRTC Signaling Server</label>';
						$htmlCode .= '<div class="ui action input">
                            <input type="text" class="copyInput" value="' . $options['accountsWebRTC']. '" readonly>
                            <button type="button" name="copyToken" value="copy" class="copyToken ui right icon button">
                                <i class="clipboard icon"></i>
                            </button>
                        </div>';

						$htmlCode .= '<small>VideoWhisper WebRTC Signaling Server is used for WebRTC live video streaming in HTML5 Videochat and solutions like <a href="https://paidvideochat.com">PaidVideochat</a>, <a href="https://broadcastlivevideo.com">BroadcastLiveVideo</a>.</small>';
						$htmlCode .= '</div>';
						}

						if ($options['accountsRTMP'])
						{
						$htmlCode .= '<div class="field">';
						$htmlCode .= '<label>RTMP Server</label>';
						$htmlCode .= '<div class="ui action input">
                            <input type="text" class="copyInput" value="' . $options['accountsRTMP']. '" readonly>
                            <button type="button" name="copyToken" value="copy" class="copyToken ui right icon button">
                                <i class="clipboard icon"></i>
                            </button>
                        </div>';

						$htmlCode .= '<small>RTMP Server is used for publishing streams with RTMP encoders like OBS, Larix Broadcaster mobile.</small>';
						$htmlCode .= '</div>';
						}

						if ($options['accountsHLS'])
						{
						$htmlCode .= '<div class="field">';
						$htmlCode .= '<label>HLS Server</label>';
						$htmlCode .= '<div class="ui action input">
                            <input type="text" class="copyInput" value="' . $options['accountsHLS']. '" readonly>
                            <button type="button" name="copyToken" value="copy" class="copyToken ui right icon button">
                                <i class="clipboard icon"></i>
                            </button>
                        </div>';
						$htmlCode .= '<small>HLS Server is used for playing RTMP streams.</small>';
						$htmlCode .= '</div>';
						}

						//broadcast pin
						$htmlCode .= '<div class="field">';
						$htmlCode .= '<label>Broadcast PIN</label>';
						$htmlCode .= '<div class="ui action input">
							<input type="text" class="copyInput" value="' . $properties['broadcastPin']. '" readonly>
							<button type="button" name="copyToken" value="copy" class="copyToken ui right icon button">
								<i class="clipboard icon"></i>
							</button>
						</div>';
						$htmlCode .= '<small>The master broadcast PIN enables RTMP publishing for any account stream as Account/Stream?pin=PIN.</small>';
						$htmlCode .= '</div>';

						//playback pin
						$htmlCode .= '<div class="field">';
						$htmlCode .= '<label>Playback PIN</label>';
						$htmlCode .= '<div class="ui action input">
							<input type="text" class="copyInput" value="' . $properties['playbackPin']. '" readonly>
							<button type="button" name="copyToken" value="copy" class="copyToken ui right icon button">
								<i class="clipboard icon"></i>
							</button>
						</div>';
						$htmlCode .= '<small>The master Playback PIN enables HLS playback for any account stream as Account/Stream/index.m3u8?pin=PIN.</small>';
						$htmlCode .= '</div>';

						$htmlCode .= '</div></div>';

						//user heredoc to include JS
						$htmlCode .= <<<SCRIPT
						<script>
						var popupTimer;

function delayPopup(popup) {
    popupTimer = setTimeout(function() { jQuery(popup).popup('hide') }, 4200);
}

jQuery(document).ready(function () {
    jQuery('.copyToken').click(function (){
        clearTimeout(popupTimer);

        var input = jQuery(this).closest('div').find('.copyInput');

        /* Select the text field */
        input.select();

        /* Copy the text inside the text field */
        document.execCommand("copy");

        jQuery(this)
            .popup({
                title    : 'Successfully copied to clipboard!',
                content  : 'Paste this setting making sure no extra spaces are included.',
                on: 'manual',
                exclusive: true
            })
            .popup('show')
        ;

        // Hide popup after 5 seconds
        delayPopup(this);


    });

});
</script>
SCRIPT;

					}

					if (isset($_GET['resetPin']) && isset($_GET['nonce']) && wp_verify_nonce( $_GET['nonce'], 'resetPin' ) )
					{
						//retrieve account from accounts table
						$accountId = intval($_GET['resetPin']);

						//pins
						$account = $accountsDB->query("SELECT * FROM accounts WHERE id='$accountId'")->fetch_assoc();

						if ( $account['contactId'] != $contactId && !current_user_can( 'manage_options' ) ) return 'Not permitted!';

						//get properties field, decode json if not empty, initialize array if empty
						$properties = $account['properties'] ? json_decode($account['properties'], true) : array();
						$properties['broadcastPin'] = self::pinGenerate();
						$properties['playbackPin'] = self::pinGenerate();
						//save properties back to account table
						$accountsDB->query("UPDATE accounts SET properties='" . json_encode($properties) . "' WHERE id='$accountId'"); 

						$htmlCode .= '<div class="ui message success">Master PINs reset for: ' . $account['name'] . '<br>';

						if ($options['accountsAPI']) if ( self::timeTo( 'updateAccountsByUser' . $current_user->ID, 180, $options ) ) $htmlCode .= 'Updated server: ' . self::updateAccountsAPI($options);
						else $htmlCode .= 'Server not updated (cooldown).';

						$htmlCode .= '</div>';
					}

					
					
						$nonce = isset($_POST['nonce']) ? $_POST['nonce'] : ( isset($_GET['nonce']) ? $_GET['nonce'] : '' );//get nonce from GET or POST form

						if (isset($_GET['testStream']) && $nonce && wp_verify_nonce( $nonce, 'testStream' ) )
						{
						$accountId = intval($_GET['testStream']);
						$account = $accountsDB->query("SELECT * FROM accounts WHERE id='$accountId'")->fetch_assoc();

						if ( $account['contactId'] != $contactId && !current_user_can( 'manage_options' ) ) return 'Not permitted!';


						$properties = $account['properties'] ? json_decode($account['properties'], true) : array();
						//if any pin is missing generate it and save it
						$save = 0;
						if (!$properties['broadcastPin']) { $properties['broadcastPin'] = self::pinGenerate(); $save = 1; }
						if (!$properties['playbackPin']) { $properties['playbackPin'] = self::pinGenerate(); $save = 1; }
						if ($save) $accountsDB->query("UPDATE accounts SET properties='" . json_encode($properties) . "' WHERE id='$accountId'");

						$stream = sanitize_text_field( $_POST['stream'] ?? '' );
						if (!$stream) $stream = 'test' . self::pinGenerate();

						//display form with an editable stream name and update button
						$htmlCode .= '<div class="ui segment small"><p>Testing stream for account: ' . esc_html( $account['name'] ) . '</p>';

						//include testStream in $_GET
						$actionURL = add_query_arg( 'testStream', $accountId, get_permalink() );
						$htmlCode .= '<form class="ui small form" method="post" action="' . $actionURL . '">';

						$htmlCode .= wp_nonce_field( 'testStream', 'nonce' );
						$htmlCode .= '<input type="hidden" name="accountId" value="' .  $accountId . '">';
						$htmlCode .= '<input type="hidden" name="stream" value="' . $stream . '">';

						$htmlCode .= '<div class="field">';
						$htmlCode .= '<label>Stream Name</label>';
						$htmlCode .= '<input type="text" name="stream" value="' . $stream . '">';
						$htmlCode .= '</div>';
						$htmlCode .= '<button class="ui button primary" type="submit">Update</button>';
						
						$htmlCode .= '</form>';

						//display streaming info
						$htmlCode .= '<p>Testing stream: ' . esc_html( $stream  ) . '</p>';

						//display stream info
						$htmlCode .= '<h5>RTMP Broadcast</h5>';
						$htmlCode .= '<p>Server: <div class="ui label">' . esc_html( $options['accountsRTMP'] ?? ''  ) . '</div><small><br>OBS: Settings /  Service: Custom... / Destination / Server </small></p>';
						$htmlCode .= '<p>Stream Key: <div class="ui label">' . $account['name'] . '/' . $stream . '?pin=' . esc_attr($properties['broadcastPin']) . '</div><small><br>OBS: Settings /  Service: Custom... / Destination / Stream Key</small></p>';

						$htmlCode .= '<h5>HLS Playback</h5>';
						$hlsUrl = trim($options['accountsHLS'] ?? '' ) . '/' . $account['name'] . '/' . $stream . '/index.m3u8?pin=' . $properties['playbackPin'];
						$htmlCode .= '<p>HLS URL: <a class="ui label" href="' . esc_url($hlsUrl) . '" target="_blank">' . esc_url($hlsUrl) . '</a></p>';

						//include preview video tag
						$htmlCode .= '<video controls width="480px" height="auto"><source src="' . esc_url($hlsUrl) . '" type="application/x-mpegURL"></video>';

						$htmlCode .= '</div>';

					}

					if (isset($_GET['editAccount']))
					{ //display form to edit these from properties field: streamUrl, broadcastPin, playbackPin
						$accountId = intval($_GET['editAccount']);
						$account = $accountsDB->query("SELECT * FROM accounts WHERE id='$accountId'")->fetch_assoc();

						if ( $account['contactId'] != $contactId && !current_user_can( 'manage_options' ) ) return 'Not permitted!';


						//get properties field, decode json if not empty, initialize array if empty
						$properties = $account['properties'] ? json_decode($account['properties'], true) : array();

						$htmlCode .= '<div class="ui segment"><p>Editing account: ' . $account['name'] . '</p>';
						//display form to edit these from properties field: streamUrl, broadcastPin, playbackPin
						$htmlCode .= '<form class="ui small form" method="post" action="' . get_permalink() . '">';
						$htmlCode .= wp_nonce_field( 'vwSupport_editAccount', 'vwSupport_editAccount_nonce' );
						$htmlCode .= '<input type="hidden" name="accountId" value="' . $accountId . '">';
						
						$htmlCode .= '<div class="ui form">';

						$htmlCode .= '<div class="field">';
						$htmlCode .= '<label>Broadcast PIN</label>';
						$htmlCode .= '<input type="text" name="broadcastPin" value="' . ( $properties['broadcastPin'] ?? '')  . '">';
						$htmlCode .= '<small>The master broadcast PIN enables RTMP publishing for any account stream with ?pin=PIN.</small>';
						$htmlCode .= '</div>';

						$htmlCode .= '<div class="field">';
						$htmlCode .= '<label>Playback PIN</label>';
						$htmlCode .= '<input type="text" name="playbackPin" value="' . ( $properties['playbackPin']??'') . '">';
						$htmlCode .= '<small>The master Playback PIN enables HLS playback for any account stream with ?pin=PIN.</small>';
						$htmlCode .= '</div>';
						
						//streamUrl
						$htmlCode .= '<div class="field">';
						$htmlCode .= '<label>Stream URL</label>';
						$htmlCode .= '<input type="text" name="streamUrl" value="' . ($properties['streamUrl'] ?? '') . '">';
						$htmlCode .= '<small>Stream URL is used to validate publishing/playback per stream basis by web server. It is requested with account token & stream by POST. Should provide these properties json encoded: broadcastPin, playbackPin to validate broadcast and playback per stream.</small>';
						$htmlCode .= '</div>';

						//notifyUrl
						$htmlCode .= '<div class="field">';
						$htmlCode .= '<label>Notify URL</label>';
						$htmlCode .= '<input type="text" name="notifyUrl" value="' . ($properties['notifyUrl'] ?? '') . '">';
						$htmlCode .= '<small>Notify URL is used to send updated live stream list on broadcast events like publish/done and periodic stream analysis. It is requested with account token by POST. Functionality is used by <a href="https://paidvideochat.com">PaidVideochat</a>, <a href="https://broadcastlivevideo.com">BroadcastLiveVideo</a> to list live rooms on website. </small>';
						$htmlCode .= '</div>';

						//button
						$htmlCode .= '<button class="ui button primary" type="submit">Save</button>';
						$htmlCode .= '</div>';

						$htmlCode .= '</form></div>';
					}
					else if (isset($_POST['vwSupport_editAccount_nonce']) && wp_verify_nonce( $_POST['vwSupport_editAccount_nonce'], 'vwSupport_editAccount' ) )	
					{
						//retrieve account from accounts table
						$accountId = intval($_POST['accountId']);
						$account = $accountsDB->query("SELECT * FROM accounts WHERE id='$accountId'")->fetch_assoc();
						
						if ( $account['contactId'] != $contactId && !current_user_can( 'manage_options' ) ) return 'Not permitted!';

						
						//get properties field, decode json if not empty, initialize array if empty
						$properties = $account['properties'] ? json_decode($account['properties'], true) : array();
						
						//update properties from form
						$properties['broadcastPin'] = sanitize_text_field( $_POST['broadcastPin'] );
						$properties['playbackPin'] = sanitize_text_field( $_POST['playbackPin'] );
						$properties['streamUrl'] = sanitize_text_field( $_POST['streamUrl'] );
						$properties['notifyUrl'] = sanitize_text_field( $_POST['notifyUrl'] );


						//save properties back to account table
						$accountsDB->query("UPDATE accounts SET properties='" . json_encode($properties) . "' WHERE id='$accountId'");

						$htmlCode .= '<div class="ui message success">Account updated: ' . $account['name'] . '<br>';
						$htmlCode .=  json_encode($properties) . '<br>';

						if ($options['accountsAPI']) if ( self::timeTo( 'updateAccountsByUser' . $current_user->ID, 180, $options ) ) $htmlCode .= 'Updated server: ' . self::updateAccountsAPI($options);
						else $htmlCode .= 'Server not updated (cooldown).';

						$htmlCode .= '</div>';
					}
					
						// Fetch plans from the plans table
						$plansList = $accountsDB->query("SELECT * FROM plans ORDER BY name ASC");

						//save name as $plans[id]
						$plans = array();
						if ($plansList) while ($plan = $plansList->fetch_assoc())
								$plans[$plan['id']] = $plan['name'];			

						// Fetch accounts for this contact
						$accounts = $accountsDB->query("SELECT * FROM accounts WHERE contactId='$contactId' ORDER BY id DESC LIMIT 10");
						if ($accounts)
						{	
							$htmlCode .= "<table class='ui striped table small'><tr><th>Account</th><th>Token</th><th>Plan</th><th>Properties</th><th>Actions</th></tr>";

							while ($account = $accounts->fetch_assoc())
							{
								$htmlCode .= "<tr>";
								$htmlCode .= "<td>" . $account['name'] . "</td>";
								$htmlCode .= "<td>" . $account['token'] . "</td>";
								//include plan name
								$htmlCode .= "<td><small>" . ($plans[$account['planId']] ?? '#' . $account['planId']) . "</small></td>";

								$htmlCode .= "<td><small>" . $account['properties'] . "</small></td><td>";
								//edit from same page
								$htmlCode .= "<a class='ui button small' href='" . get_permalink() . "?editAccount=" . $account['id'] . "'>Edit</a>";
								//reset PIN button, with wp nonce in url for security
								$htmlCode .= "<a class='ui button small' href='" . get_permalink() . "?resetPin=" . $account['id'] . "&nonce=" . wp_create_nonce( 'resetPin' ) . "'>Reset PINs</a>";
								
								if ($options['accountsRTMP'] && $options['accountsHLS'])
								$htmlCode .= "<a class='ui button small' href='" . get_permalink() . "?testStream=" . $account['id'] . "&nonce=" . wp_create_nonce( 'testStream' ) . "'>Test RTMP/HLS</a>";

								if ($options['accountsRTMP'] || $options['accountsHLS'] || $options['accountsWebRTC'])
								$htmlCode .= "<a class='ui button small' href='" . get_permalink() . "?configureApps=" . $account['id'] . "&nonce=" . wp_create_nonce( 'configureApps' ) . "'>Configure Apps</a>";

								if ($options['accountsAPI'])
								$htmlCode .= "<a class='ui button small' href='" . get_permalink() . "?status=" . $account['id'] . "&nonce=" . wp_create_nonce( 'status' ) . "'>Status</a>";

								$htmlCode .= "</td></tr>";
							}
							$htmlCode .= "</table>";		
						}
						else {
							//get contact meta and remove 'Register' from forms, to allow registering a new account
							$meta = false;
							if ($contact->meta) $meta = unserialize($contact->meta);
							if (is_array($meta)) if (array_key_exists('forms', $meta)) if (array_key_exists('Register', $meta['forms'])) {
								//unset form and save updated contact, escaping serialized meta array if necessary
								unset($meta['forms']['Register']);
								$metaS = esq_sql(serialize($meta));
								$wpdb->query("UPDATE `$table_contacts` SET meta='" . $metaS . "' WHERE id='$contactId'");

								self::log( 'videowhisper_support_accounts no accounts for ' . $contactId . ' - cleared Register meta: ' . $metaS, 5, $options);
							}


							return 'No accounts found for your contact, yet.';
						}			
				}
			} else return 'Accounts db not configured.';


		return $htmlCode;
		
	}

    static function videowhisper_support_micropayments( $atts ) 
	{
	
	//allow selected roles to set custom conversation/message cost as user meta
	if ( ! is_user_logged_in() ) {
		self::enqueueUI();
			
		return '<i class="lock icon"></i>' . __( 'Login to Access!', 'live-support-tickets' ) . '<BR><a class="ui button primary qbutton" href="' . wp_login_url() . '">' . __( 'Login', 'live-support-tickets' ) . '</a>';
	}

	$options = self::getOptions();

	$allowedRoles = explode(',', $options['micropaymentsRoles']);
	//check current user role and reject if no it list
	if (!self::rolesUser($options['micropaymentsRoles'], wp_get_current_user() ) ) return 'Setting cost not allowed for your role.';

	$current_user = wp_get_current_user();

	//check nonce if form was submitted and save cost as user meta
	if ( isset( $_POST['vwSupport_setCost_nonce'] ) && wp_verify_nonce( $_POST['vwSupport_setCost_nonce'], 'vwSupport_setCost' ) ) {
		$conversationCost = floatval($_POST['vwSupport_conversation_cost']);
		$messageCost = floatval($_POST['vwSupport_message_cost']);

		if (!$conversationCost) delete_user_meta( $current_user->ID, 'vwSupport_conversation_cost');
		else 
		{
			$conversationCost = max($conversationCost, floatval($options['micropaymentsConversationMin']));
			$conversationCost = min($conversationCost, floatval($options['micropaymentsConversationMax']));
			update_user_meta( $current_user->ID, 'vwSupport_conversation_cost', $conversationCost);
		}

		if (!$messageCost) delete_user_meta( $current_user->ID, 'vwSupport_message_cost');
		else 
		{
			$messageCost = max($messageCost, floatval($options['micropaymentsMessageMin']));
			$messageCost = min($messageCost, floatval($options['micropaymentsMessageMax']));
			update_user_meta( $current_user->ID, 'vwSupport_message_cost', $messageCost);
		}
	}

	$conversationCost = get_user_meta( $current_user->ID, 'vwSupport_conversation_cost', true);
	$conversationCost = floatval($conversationCost);
	if (!$conversationCost) $conversationCost = floatval($options['micropaymentsConversation']);
	$messageCost = get_user_meta( $current_user->ID, 'vwSupport_message_cost', true);
	$messageCost = floatval($messageCost);
	if (!$messageCost) $messageCost = floatval($options['micropaymentsMessage']);
	
	//post form to current page with wordpress nonce for security
	$htmlCode = '<form method="post" action="' . get_permalink() . '">';
	$htmlCode .= wp_nonce_field( 'vwSupport_setCost', 'vwSupport_setCost_nonce' );
	$htmlCode .= '<div class="ui form">';
	//conversation
	$htmlCode .= '<div class="field">';
	$htmlCode .= '<label>' . __('Conversation Price', 'live-support-tickets') . '</label>';
	$htmlCode .= '<input type="number" step="0.01" min="0" max="' . floatval($options['micropaymentsConversationMax']) .  '" name="vwSupport_conversation_cost" value="' . $conversationCost . '">';
	$htmlCode .= '</div>';
	//message
	$htmlCode .= '<div class="field">';
	$htmlCode .= '<label>' . __('Message Price', 'live-support-tickets') . '</label>';
	$htmlCode .= '<input type="number" step="0.01" min="0" max="' . floatval($options['micropaymentsMessageMax']) .  '" name="vwSupport_message_cost" value="' . $messageCost . '">';
	$htmlCode .= '</div>';
	//submit  button
	$htmlCode .= '<button class="ui button primary" type="submit">' . __('Set Prices', 'live-support-tickets') . '</button>';
	$htmlCode .= '</div>';
	$htmlCode .= '</form>'; 

	$htmlCode .= '<div class="ui basic segment ' . $options['interfaceClass'] . '">' . __('Set 0 to use defaults.', 'live-support-tickets') . '</div>';
	return $htmlCode;
	
	}
	
	static function videowhisper_support_conversations($atts)
	{
		//show conversations list in frontent for logged in users
		
		if ( ! is_user_logged_in() ) {
			self::enqueueUI();
				
			return '<i class="lock icon"></i>' . __( 'Login to Access!', 'live-support-tickets' ) . '<BR><a class="ui button primary qbutton" href="' . wp_login_url() . '">' . __( 'Login', 'live-support-tickets' ) . '</a>';
		}

		return do_shortcode('[videowhisper_support params="list:1"]');
		
	}
	
	static function videowhisper_support_buttons( $atts ) {
	//displays support buttons with link to support page and user conversations
		$options = self::getOptions();
			
		$atts = shortcode_atts(
			array(
				'params'      => '',
			),
			$atts,
			'videowhisper_support_buttons'
		);
		
		if ( !$options['p_videowhisper_support'] ) return '<!-- VideoWhisper Support: Support page not setup from backend, yet. -->';
		
		
		
		$htmlCode = '<div class="videowhisperButtons">';
		$htmlCode .= '<a class="ui button icon small" data-position="left center" data-tooltip="' . __('Contact Support', 'live-support-tickets') . '" href="' . get_permalink( $options['p_videowhisper_support'] ) . '"><i class="icon comments"></i></a>';
		
		if ( is_user_logged_in() ) if ($options['p_videowhisper_support_conversations'] ) 
		{
		
		$current_user = wp_get_current_user();
		if ( ! self::timeTo( 'frontendCount' . $current_user->ID, 120, $options ) ) self::updateCounters($options, $current_user->ID);
			
		$tickets_new = intval( get_user_meta( $current_user->ID, 'vwSupport_new', true) );
		
		if ($tickets_new) $htmlCode .= '<a class="ui right labeled button small" data-position="left center" data-tooltip="' . __('My Conversations', 'live-support-tickets') .': ' . $tickets_new . ' ' .  __('New', 'live-support-tickets') . '" href="' . get_permalink( $options['p_videowhisper_support_conversations'] ) . '"><div class="ui button icon small"><i class="icon envelope"></i></div><div class="ui basic left pointing label small">' . $tickets_new . '</div></a>';
		}
	
		$htmlCode .='</div><STYLE>
		.videowhisperButtons {
			position: fixed;
			bottom: 32px;
			right: 32px; 
		}
		</STYLE>
		';
		
		return $htmlCode;
	}

	static function videowhisper_support( $atts ) {

	$options = self::getOptions();

	self::enqueueUI(); // Ensure all necessary scripts and styles are enqueued

	$atts = shortcode_atts(
		array(
			'inline' => false,
			'params'      => '',
			'css' => $options['customCSS'] ?? '',
		),
		$atts,
		'videowhisper_support'
	);
	$cssCode =  $atts['css'];

	$inline = boolval( $atts['inline'] );

	
	//wp_enqueue_script( 'fomantic-ui', dirname( plugin_dir_url( __FILE__ ) ) . '/semantic/semantic.min.js', array( 'jquery' ) );
	wp_enqueue_style( 'fomantic-ui', dirname(plugin_dir_url(  __FILE__ )) . '/semantic/semantic.min.css');
	
	$k = 0;

		$CSSfiles = scandir( dirname( dirname( __FILE__ ) ) . '/static/css/' );
	foreach ( $CSSfiles as $filename ) {
		if ( strpos( $filename, '.css' ) && ! strpos( $filename, '.css.map' ) ) {
			wp_enqueue_style( 'vw-support-app' . ++$k, dirname( plugin_dir_url( __FILE__ ) ) . '/static/css/' . $filename );
		}
	}
	
	$countMain = 0;
	$countRuntime  = 0;
	$JSfiles       = scandir( dirname( dirname( __FILE__ ) ) . '/static/js/' );
	foreach ( $JSfiles as $filename ) {
		if ( strpos( $filename, '.js' ) && ! strpos( $filename, '.js.map' ) && ! strpos( $filename, '.txt' ) ) {
			wp_enqueue_script( 'vw-support-app' . ++$k, dirname( plugin_dir_url( __FILE__ ) ) . '/static/js/' . $filename, array(), '', true );
	
			if ( ! strstr( $filename, 'LICENSE.txt' ) ) {
				if ( substr( $filename, 0, 5 ) == 'main.' ) {
					$countMain++;
				}
			}
			if ( ! strstr( $filename, 'LICENSE.txt' ) ) {
				if ( substr( $filename, 0, 7 ) == 'runtime' ) {
					$countRuntime++;
				}
			}
		}
	}
	
	$htmlCode = '';
	
	if ( $countMain > 1 || $countRuntime > 1 ) {
		$htmlCode .= '<div class="ui segment red">Warning: Possible duplicate JS files in application folder! Only latest versions should be deployed.</div>';
	}

	$serverURL = get_site_url() . '/';
	
	$params =  $atts['params'] ;

	$htmlCode .= '<!--VideoWhisper.com - Live Support Tickets --><a id="vws"></a>';

	if ($inline) 
	{
		$params =  $params ? $params . ',inline:1' : 'inline:1';
		
		// This button will toggle the visibility of the React app
		$htmlCode = '<div id="toggleSupportApp" data-tooltip="' . __('Toggle Support Dialog', 'live-support-tickets') . '" class="ui button icon small" style="position: fixed; bottom: 32px; right: 32px; z-index: 999;">';
		$htmlCode .= '<i class="icon comments"></i> ' .  __('Support', 'live-support-tickets') . '</div>';

		$htmlCode .= <<<SCRIPT
		<script>
		jQuery(document).ready(function($) {
			$('#toggleSupportApp').click(function() {
				$('#videowhisperSupportContainer').toggle();
			});
		});
		</script>
		<style>
		@media (max-width: 768px) {
			#videowhisperSupportContainer {
				max-width: 100%;
				width: 100%;
			}
		}
		</style>
		SCRIPT;

		// Container for the ReactJS app inline floating
		$htmlCode .= '<div id="videowhisperSupportContainer" style="display: none; position: fixed; bottom: 70px; right: 32px; width: 100%; max-width: 500px; max-height: 480px; z-index: 998; overflow-y: auto; overflow-x: hidden;">';
	}
	else 
	{
		// Container for the ReactJS app block
		$htmlCode .= '<div id="videowhisperSupportContainer" style="display: block; height: auto; max-height: 100%; height: inherit; position: relative; z-index: 102 !important">';
	}
 
	$htmlCode .= '<div id="videowhisperSupport"></div>';

	$htmlCode .= '<div style="display: block; height: 20px" class="ui hidden divider"></div>';

	$isList = false;
	if (strstr($params, 'list: 1')) $isList = true;

	if (current_user_can('administrator') && !$isList) $htmlCode .= '<div class="ui message warning tiny ' . $options['interfaceClass'] . '">Clarification for site admins: This application handles conversations on your own website. Support inquiries are available in <a href="' . admin_url( 'admin.php?page=vw-support-conversations' ) . '">Support Backend</a>. If you want to contact VideoWhisper, please use contact forms on <a href="https://consult.videowhisper.com/">VideoWhisper Website</a>.</div>';

	if ($options['demoMode'] ?? true) if (!$isList) $htmlCode .= '<div class="ui message warning tiny ' . $options['interfaceClass'] . '">Demo Mode Configured: Conversations and messages are not monitored on this setup. The Contact Forms / Live Support / CRM plugin is currently configured for demo mode, in backend of this setup.  If you need to contact plugin developers, <a href="https://consult.videowhisper.com/">Consult VideoWhisper</a>.</div>';
			
	$htmlCode .= '</div>';

	$htmlCode .= "<script>window.VideoWhisperSupport = { server: '$serverURL', $params };</script>";

	$htmlCode .= <<<CSSCODE
	<style>
	#videowhisperSupportContainer
	{
		border-radius: 4px; 
		padding: 2px;
		background-color: #eee;
	}

	#videowhisperSupport
	{
	}
	
	$cssCode
	</style>
	CSSCODE;

	return $htmlCode;
	}
	
	static function enqueueUI() {
		wp_enqueue_script( 'jquery' );
	
		wp_enqueue_style( 'fomantic-ui', dirname( plugin_dir_url( __FILE__ ) ) . '/semantic/semantic.min.css' );
		wp_enqueue_script( 'fomantic-ui', dirname( plugin_dir_url( __FILE__ ) ) . '/semantic/semantic.min.js', array( 'jquery' ) );
	}
	
}