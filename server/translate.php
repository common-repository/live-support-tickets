<?php
// This integrates DeepL translation calls with minimal WP overhead.
// chat server for quick ajax requests

define( 'VW_DEVMODE', 0 );

if ( VW_DEVMODE ) {
	ini_set( 'display_errors', 1 ); // debug only
	error_reporting( E_ALL & ~E_NOTICE & ~E_STRICT );
}

$time0 = microtime(true);

define( 'SHORTINIT', true );
include_once '../../../../wp-load.php';

$response = [];

//global $wpdb;
$options = get_option( 'VWsupportOptions' );


			if ( $options['corsACLO'] ) {
				$http_origin = ( isset($_SERVER['HTTP_ORIGIN']) &&$_SERVER['HTTP_ORIGIN'] )  ? $_SERVER['HTTP_ORIGIN'] : $_SERVER['HTTP_REFERER'];
				$response['HTTP_ORIGIN'] = $http_origin;

				$found   = 0;
				$domains = explode( ',', $options['corsACLO'] );
				foreach ( $domains as $domain ) {
					if ( $http_origin == trim( $domain ) ) {
						$found = 1;
					}
				}

				if ( $found ) {
					header( 'Access-Control-Allow-Origin: ' . $http_origin );
					header( 'Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE, HEAD' ); // POST, GET, OPTIONS, PUT, DELETE, HEAD
					header( 'Access-Control-Allow-Credentials: true' );
					header( 'Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With' ); // Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With

					if ( 'OPTIONS' == $_SERVER['REQUEST_METHOD'] ) {
						status_header( 200 );
						exit();
					}
				}
			}
			
//https://github.com/DeepLcom/deepl-php 
//https://www.deepl.com/account/summary

$authKey =  $options['deepLkey']; 


if (!$authKey) 
{
	$response['error'] = 'Missing DeepL key from plugin settings.';
	
	echo json_encode( $response );
	die();	
}


    //identification
    $contact_id     = intval( $_POST['contact_id'] ?? 0 );
	$ticket_id 		= intval( $_POST['ticket_id'] ?? 0 );
	$rep = sanitize_text_field($_POST['rep'] ?? '');

$message = isset($_POST['message']) && is_array($_POST['message']) ? $_POST['message'] : []; // array elements sanitized individually
if ($message){
    $m_text = sanitize_textarea_field($message['text']);
    $m_language = sanitize_text_field($message['language']);
    $m_flag = sanitize_text_field($message['flag']);
}
    
// target language
$language = sanitize_text_field($_POST['language'] ?? '');
$flag = sanitize_text_field($_POST['flag'] ?? '');

	
if ( (!$message || !$contact_id || !$language) && !$_GET['update_languages']) 
{
	$response['error'] = 'Missing required parameters.';
	echo json_encode( $response );
	die();	
}


if ( $_GET['update_languages'] )
{
	//administrative request	
}
else
{

	//check if session is valid
			global $wpdb;
			$table_ticket_contacts = $wpdb->prefix . 'vws_ticket_contacts';
			$table_contacts 	  = $wpdb->prefix . 'vws_contacts';

			if ($rep)
			{
				$sqlS    = "SELECT * FROM $table_contacts WHERE id = '$contact_id' AND pin='$rep' LIMIT 1";
				$session = $wpdb->get_row( $sqlS );

				if ( !$session ) 	
				{
					$response['error'] = 'Representativ code invalid for ' . $contact_id;
					echo json_encode( $response );
					die();			
				}
			}
			else
			{

			$sqlS    = "SELECT * FROM $table_ticket_contacts WHERE cid='$contact_id' AND tid='$ticket_id' LIMIT 1";
			$session = $wpdb->get_row( $sqlS );

			if ( !$session ) 	
			{
				$response['error'] = 'Contact not associated with ticket: ' . $contact_id . ' / ' . $ticket_id;
				echo json_encode( $response );
				die();			
			}

		   }
			
}

//DeepL API
include_once 'vendor/autoload.php';
$translator = new \DeepL\Translator($authKey);


//update supported langues
if ($_GET['update_languages'] == 'videowhisper' )
{
	$sources = [];
	$sourceLanguages = $translator->getTargetLanguages();;
foreach ($sourceLanguages as $sourceLanguage) 
    $sources [ strtolower($sourceLanguage->code) ]= $sourceLanguage->name ; // Example: 'English (en)'
    
    update_option('VWdeepLlangs', $sources );
    
	echo 'Languages Updated: ' . esc_html( serialize($sources) ) . '<br>';
	//var_dump($sources);
	
	die();	
}

//translate
if ($message && $language ) 
{

try {	
 
 $translation = $translator->translateText( $m_text, substr($m_language, 0, 2) , $language );
 $response['translation'] = $translation->text;
 $response['detectedSourceLang'] = $translation->detectedSourceLang;
 
} catch (\DeepL\DocumentTranslationException $error) {
	    $response['error'] = ( $error->getMessage() ?? 'unknown error' );
    }

$response['language'] = $language;
$response['flag'] = $flag;

$time1 = microtime(true);

$response['duration'] = $time1 - $time0;

echo json_encode( $response );
die();
}

?>