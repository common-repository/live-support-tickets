<?php
/*
Plugin Name: Contact Forms, Live Support, CRM, Video Messages
Plugin URI: https://videolivesupport.com
Description: <strong>Contact Forms, Live Support, CRM</strong> provides site support desk & messaging features for backend/frontend, combining ticket management with live chat interface in unified conversation interface. Custom contact forms enable collecting custom data (fields) per per contact or conversation. Supports advanced features like registration forms for registering accounts.<a href='admin.php?page=vw-support&tab=pages'>Setup</a> | <a href='admin.php?page=vw-support-conversations'>Conversations</a> | <a href='https://consult.videowhisper.com/'>Consult VideoWhisper</a>
Version: 1.11.1
Author: VideoWhisper.com
Author URI: https://videowhisper.com/
Contributors: videowhisper, VideoWhisper.com
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Text Domain: live-support-tickets
Domain Path: /languages/
*/

defined( 'ABSPATH' ) or exit;

require_once plugin_dir_path( __FILE__ ) . '/inc/options.php';
require_once plugin_dir_path( __FILE__ ) . '/inc/shortcodes.php';
require_once plugin_dir_path( __FILE__ ) . '/inc/server.php';

use VideoWhisper\LiveSupport;


if ( ! class_exists( 'VWliveSupport' ) ) {
class VWliveSupport {

	use VideoWhisper\LiveSupport\Options;
	use VideoWhisper\LiveSupport\Shortcodes;
	use VideoWhisper\LiveSupport\Server;

	public function __construct() {         }


	public function VWliveSupport() {
		// constructor
		self::__construct();

	}
	

	// ! Plugin Hooks

	function plugins_loaded() {

		add_shortcode( 'videowhisper_support_accounts', array( $this, 'videowhisper_support_accounts' ) );
		add_shortcode( 'videowhisper_support_micropayments', array( $this, 'videowhisper_support_micropayments' ) );
		add_shortcode( 'videowhisper_support', array( $this, 'videowhisper_support' ) );
		add_shortcode( 'videowhisper_support_conversations', array( $this, 'videowhisper_support_conversations' ) );
		add_shortcode( 'videowhisper_support_buttons', array( $this, 'videowhisper_support_buttons' ) );

		add_action( 'wp_ajax_vws_settings', array( $this, 'vws_settings' ) );
		add_action( 'wp_ajax_nopriv_vws_settings', array( $this, 'vws_settings' ) );

		add_action( 'wp_ajax_vws_app', array( $this, 'vws_app' ) );
		add_action( 'wp_ajax_nopriv_vws_app', array( $this, 'vws_app' ) );

		// settings link in plugins view
		$plugin = plugin_basename( __FILE__ );
		add_filter( "plugin_action_links_$plugin", array( $this, 'settings_link' ) );

		add_filter( 'the_content', array( $this, 'the_content' ), 101 ); // high priority to run at end

		//buddypress / BuddyBoss
		if ( function_exists( 'bp_is_active' ) ) {
			add_action( 'bp_setup_nav', array( $this, 'bp_setup_nav' ) );
		}

		// check db version
		$plugin_version = '2024.09.18b';
		$installed_version = get_option( 'vwSupport_db_version' );

		if ( $installed_version != $plugin_version ) {
			//update tables
			global $wpdb;
			$wpdb->flush();
			$charset_collate = $wpdb->get_charset_collate();

			$table_contacts = $wpdb->prefix . 'vws_contacts';
			$table_tickets = $wpdb->prefix . 'vws_tickets';
			$table_ticket_contacts = $wpdb->prefix . 'vws_ticket_contacts';
			$table_messages = $wpdb->prefix . 'vws_messages';
			$table_departments = $wpdb->prefix . 'vws_departments';
			$table_cans = $wpdb->prefix . 'vws_cans';

			$table_sites = $wpdb->prefix . 'vws_sites';
			$table_properties = $wpdb->prefix . 'vws_properties'; //payments, services, etc

			$table_tags = $wpdb->prefix . 'vws_tags'; //tag definitions
			$table_tagged = $wpdb->prefix . 'vws_tagged'; //tagged contacts, tickets, messages

			//department type: 0 = site, 1 = context site (report), 2 = context owner (request); site: 0 = all sites (common), ticket type matches department type

			$sql = "CREATE TABLE `$table_contacts` (
			  `id` int(11) NOT NULL AUTO_INCREMENT,
			  `uid` int(11) NOT NULL,
			  `name` varchar(64) NOT NULL,
			  `contact` varchar(64) NOT NULL,
			  `type` varchar(16) NOT NULL,
			  `created` int(11) NOT NULL,
			  `pin` char(6) NOT NULL,
			  `confirmed` int(11) NOT NULL,
			  `meta` mediumtext NOT NULL,
			  `site` int(11) NOT NULL DEFAULT '0',
			  `laccess` int(11) NOT NULL DEFAULT '0',
			  `lmessage` int(11) NOT NULL DEFAULT '0',
			  `lform` int(11) NOT NULL DEFAULT '0',
			  PRIMARY KEY (`id`),
			  KEY `uid` (`uid`),
			  KEY `name` (`name`),
			  KEY `contact` (`contact`),
			  KEY `type` (`type`),
			  KEY `confirmed` (`confirmed`),
			  KEY `created` (`created`),
			  KEY `laccess` (`laccess`),
			  KEY `lmessage` (`lmessage`),
			  KEY `lform` (`lform`),			  
			  KEY `site` (`site`)
			) ENGINE=InnoDB $charset_collate COMMENT='VideoWhisper.com - Support - Contacts @2022';

			CREATE TABLE `$table_tickets` (
			  `id` int(11) NOT NULL AUTO_INCREMENT,
			  `department` int(11) NOT NULL,
			  `type` tinyint(4) NOT NULL DEFAULT '0',
			  `site` int(11) NOT NULL DEFAULT '0',
			  `content` int(11) NOT NULL DEFAULT '0',
			  `creator` int(11) NOT NULL DEFAULT '0',
			  `meta` mediumtext NOT NULL,
			  `created` int(11) NOT NULL,
			  `updated` int(11) NOT NULL,
			  `status` tinyint(4) NOT NULL DEFAULT '0',
			  PRIMARY KEY (`id`),
			  KEY `type` (`type`),
			  KEY `site` (`site`),
			  KEY `content` (`content`),
			  KEY `creator` (`creator`),
			  KEY `updated` (`updated`),
			  KEY `status` (`status`),
			  KEY `department` (`department`)
			) ENGINE=InnoDB $charset_collate COMMENT='VideoWhisper  - Support - Tickets @2022';

			CREATE TABLE `$table_ticket_contacts` (
			  `id` int(11) NOT NULL AUTO_INCREMENT,
			  `tid` int(11) NOT NULL,
			  `cid` int(11) NOT NULL,
			  `rep` tinyint(4) NOT NULL DEFAULT '0',
			  `pin` char(6),
			  `status` TINYINT NOT NULL DEFAULT '0',
			  `meta` TEXT NOT NULL,
			  PRIMARY KEY (`id`),
			  KEY `tid` (`tid`),
			  KEY `cid` (`cid`),
			  KEY `status` (`status`)
			) ENGINE=InnoDB $charset_collate COMMENT='VideoWhisper.com - Support - Ticket Contacts @2022';

			CREATE TABLE `$table_messages` (
			  `id` int(11) NOT NULL AUTO_INCREMENT,
			  `tid` int(11) NOT NULL,
			  `cid` int(11) NOT NULL,
			  `content` mediumtext NOT NULL,
			  `meta` mediumtext NOT NULL,
			  `created` int(11) NOT NULL,
			  PRIMARY KEY (`id`),
			  KEY `cid` (`cid`),
			  KEY `tid` (`tid`),
			  KEY `created` (`created`)
			) ENGINE=InnoDB $charset_collate COMMENT='VideoWhisper.com - Support - Messages @2022';

			CREATE TABLE `$table_departments` (
			  `id` int(11) NOT NULL AUTO_INCREMENT,
			  `name` varchar(64) NOT NULL,
			  `type` tinyint(4) NOT NULL,
			  `site` int(11) NOT NULL DEFAULT '0',
			  `meta` mediumtext NOT NULL,
			  PRIMARY KEY (`id`),
			  KEY `type` (`type`),
			  KEY `site` (`site`)
			) ENGINE=InnoDB $charset_collate COMMENT='VideoWhisper  - Support - Departments @2022';

			CREATE TABLE `$table_sites` (
		  	`id` int(11) NOT NULL AUTO_INCREMENT,
		  	`name` varchar(64) NOT NULL,
		  	`meta` mediumtext NOT NULL,
		  	PRIMARY KEY (`id`)
			) ENGINE=InnoDB $charset_collate COMMENT='VideoWhisper  - Support - Sites @2022';

			CREATE TABLE `$table_properties` (
			  `id` int(11) NOT NULL AUTO_INCREMENT,
			  `type` varchar(32) NOT NULL,
			  `name` varchar(64) NOT NULL,
			  `value` varchar(64) NOT NULL,
			  `category` varchar(64) NOT NULL, 
			  `group` varchar(64) NOT NULL,
			  `owner` varchar(64) NOT NULL,
			  `meta` mediumtext NOT NULL,
			  `cid` int(11) NOT NULL DEFAULT '0',
			  `created` int(11) NOT NULL,
			  `updated` int(11) NOT NULL,
			  `status` tinyint(4) NOT NULL DEFAULT '0',
			  `site` int(11) NOT NULL DEFAULT '0',
			  PRIMARY KEY (`id`),
			  KEY `type` (`type`),
			  KEY `name` (`name`),
			  KEY `value` (`value`),
			  KEY `category` (`category`),
			  KEY `group` (`group`),
			  KEY `owner` (`owner`),
			  KEY `cid` (`cid`),
			  KEY `created` (`created`),
			  KEY `updated` (`updated`),
			  KEY `status` (`status`),
			  KEY `site` (`site`)
			) ENGINE=InnoDB $charset_collate COMMENT='VideoWhisper  - Support - Properties @2023';

			CREATE TABLE `$table_cans` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`title` varchar(64) NOT NULL,
				`message` mediumtext NOT NULL,
				`cid` int(11) NOT NULL,
			PRIMARY KEY (`id`),
			KEY `cid` (`cid`)
			) ENGINE=InnoDB $charset_collate COMMENT='VideoWhisper  - Support - Cans @2023';

				CREATE TABLE `$table_tags` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`tag` varchar(64) NOT NULL,
				`type` varchar(16) NOT NULL DEFAULT '',
				`visibility` varchar(16) NOT NULL DEFAULT '',				
				`duration` int(11) NOT NULL,
			PRIMARY KEY (`id`),
			KEY `tag` (`tag`),
			KEY `type` (`type`),
			KEY `visibility` (`visibility`)
			) ENGINE=InnoDB $charset_collate COMMENT='VideoWhisper  - Support - Tags @2024';
	
			CREATE TABLE `$table_tagged` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`tag` int(11) NOT NULL,
				`class` varchar(16) NOT NULL DEFAULT '',
				`item` int(11) NOT NULL,
				`created` int(11) NOT NULL,
				`trigger` int(11) NOT NULL,
			PRIMARY KEY (`id`),
			KEY `tag` (`tag`),
			KEY `class` (`class`),
			KEY `item` (`item`),
			KEY `created` (`created`),
			KEY `trigger` (`trigger`)
			) ENGINE=InnoDB $charset_collate COMMENT='VideoWhisper  - Support - Tagged @2024';
			";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );

			if ( ! $installed_version ) {
				add_option( 'vwSupport_db_version', $plugin_version );
			} else {
				update_option( 'vwSupport_db_version', $plugin_version );
			}
			$wpdb->flush();

		//if
		}

	//plugins_loaded
	}

	static function tagSet($tag, $item, $class = 'conversation',  $type ='', $visibility = '', $duration = 0)
	{
		if (!$tag) return; //no tag

		//tag item: create tag if not exists, add tag if not already tagged
		global $wpdb;
		$table_tags = $wpdb->prefix . 'vws_tags';
		$table_tagged = $wpdb->prefix . 'vws_tagged';

		$tagID = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_tags WHERE tag = %s AND type = %s AND visibility = %s", $tag, $type, $visibility ) );

		if (!$tagID)
		{
			$wpdb->insert( $table_tags, array( 'tag' => $tag, 'type' => $type, 'visibility' => $visibility, 'duration' => $duration ) );
			$tagID = $wpdb->insert_id;
		}

		$taggedID = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_tagged WHERE tag = %d AND class = %s AND item = %d", $tagID, $class, $item ) );

		if (!$taggedID)
		{
			$wpdb->insert( $table_tagged, array( 'tag' => $tagID, 'class' => $class, 'item' => $item, 'created' => time() ) );
		}
		
	}

	static function tagRemove($tag, $item, $class = 'conversation')
	{
		//remove from tagged list 
		global $wpdb;
		$table_tags = $wpdb->prefix . 'vws_tags';
		$table_tagged = $wpdb->prefix . 'vws_tagged';

		$tagID = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_tags WHERE tag = %s", $tag ) );
		if (!$tagID) return;

		$wpdb->delete( $table_tagged, array( 'tag' => $tagID, 'class' => $class, 'item' => $item ) );

	}

	static function tagGet($item, $class = 'conversation', $visibility = '')
	{
		//get array of all tags for item as $tag => $created, last added first
		global $wpdb;
		$table_tags = $wpdb->prefix . 'vws_tags';
		$table_tagged = $wpdb->prefix . 'vws_tagged';

		$tags = $wpdb->get_results( $wpdb->prepare( "SELECT t.tag, tg.created FROM $table_tags t, $table_tagged tg WHERE tg.tag = t.id AND tg.class = %s AND tg.item = %d AND t.visibility = %s ORDER BY tg.created DESC", $class, $item, $visibility ) );

		$tagList = array();
		foreach ($tags as $tag)
		{
			$tagList[$tag->tag] = $tag->created;
		}

		return $tagList;
	}

	function the_content( $content ) {

	$options = self::getOptions();
	$afterContent = '';

	if ($options['buttons'])
	{
		self::enqueueUI();
		$afterContent = do_shortcode( '[videowhisper_support_buttons]' );
	}

	// ! content page
	if ( ! is_single() && ! is_page() ) {
		return $content . $afterContent; // listings
	}

	$postID = get_the_ID();
		$preContent = '';

		// content report
		if ( $options['postTypesContent'] ) {
				$type = get_post_type( $postID );
				$postTypes = explode( ',', $options['postTypesContent'] );
				foreach ( $postTypes as $postType )
					if ( $type == trim( $postType ) ) {
						$preContent .= '<a class="ui ' . ( $options['interfaceClass'] ?? '' ) . ' tiny compact button" href="' . add_query_arg( 'content', $postID, get_permalink( $options['p_videowhisper_support'] ) ) . '"><i class="flag icon"></i> ' . __( 'Report', 'live-support-tickets' ) . ' ' . ucwords( $type ) . ' </a>';
					}
				}

		// contact author
		if ( $options['postTypesAuthor'] ) {
		$type = get_post_type( $postID );
		$postTypes = explode( ',', $options['postTypesAuthor'] );
		foreach ( $postTypes as $postType )
			if ( $type == trim( $postType ) ) {
				$author = get_post_field( 'post_author', $postID );
				$user = get_user_by( 'id', $author );
				if ($user) $userName = $user->display_name;

				$preContent .= '<a class="ui ' . ( $options['interfaceClass'] ?? '' ) . ' tiny compact button" href="' . add_query_arg( ['creator'=> $author, 'content' => $postID ], get_permalink( $options['p_videowhisper_support'] ) ) . '"><i class="question circle icon"></i> ' . __( 'Contact', 'live-support-tickets' ) . ' ' . $user->display_name . ' </a>';
			}
		}

		//nothing to add
		if (!$preContent) return $content . $afterContent;

		//before or after
		if ($options['buttonsPosition'] != 'before' )
		{
			$afterContent = $preContent . $afterContent;
			$preContent = '';
		}

				self::enqueueUI();
				return $preContent. $content . $afterContent ;

	}

	function init() {
		$options = get_option( 'VWsupportOptions' );

add_action( 'rest_api_init', [ $this, 'restRoutes'] );


if ( $options['corsACLO'] ?? false ) {
	$http_origin = get_http_origin();

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
		header( 'Access-Control-Allow-Headers: X-WP-Nonce, Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With' ); // Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With

		if ( 'OPTIONS' == $_SERVER['REQUEST_METHOD'] ) {
			status_header( 200 );
			exit();
			}
		}
	//if
	}

		//init()
		}


		static function path2url($file, $Protocol='https://')
		{
			if (is_ssl() && $Protocol=='http://') $Protocol='https://';

			$url = $Protocol . $_SERVER['HTTP_HOST'];

			//on godaddy hosting uploads is in different folder like /var/www/clients/ ..
			$upload_dir = wp_upload_dir();
			if (strstr($file, $upload_dir['basedir']))
				return  $upload_dir['baseurl'] . str_replace($upload_dir['basedir'], '', $file);

			//folder under WP path
			require_once(ABSPATH . 'wp-admin/includes/file.php');
			if (strstr($file, get_home_path()))
				return site_url() . str_replace(get_home_path(), '', $file);

			//under document root
			if (strstr($file, $_SERVER['DOCUMENT_ROOT']))
				return  $url . str_replace($_SERVER['DOCUMENT_ROOT'], '', $file);

			return $url . $file;
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

			if (!$timersPath) return 1;

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
		//bp
		 function bp_setup_nav() {
			 global $bp;

			 if (!$bp) return;
	 		 if (! $bp->displayed_user ) return;
			 if ( empty($bp->displayed_user->id) ) return;

			 $userID = $bp->displayed_user->id;

			 $user = get_userdata( $userID );
			 if (!$user) return;

			 $options = self::getOptions();

			if ( self::rolesUser( $options['rolesCreator'], $user ) )
			{

			bp_core_new_nav_item(
				array(
					'name'                    => 'Contact',
					'slug'                    => 'support',
					'screen_function'         => array( $this, 'bp_support_screen' ),
					'position'                => 41,
					'show_for_displayed_user' => true,
					'parent_url'              => $bp->displayed_user->domain,
					'parent_slug'             => $bp->profile->slug,
					'default_subnav_slug'     => 'support',
				)
			);

			}

		}

		function bp_support_screen() {
			// Add title and content here - last is to call the members plugin.php template.
			add_action( 'bp_template_title', array( $this, 'bp_support_title' ) );
			add_action( 'bp_template_content', array( $this, 'bp_support_content' ) );
			bp_core_load_template( 'buddypress/members/single/plugins' );
		}
		
		function bp_support_title() {
			echo esc_html(__( 'Contact Creator', 'live-support-tickets' ));
		}

		function bp_support_content() {
			global $bp;
			$userID = $bp->displayed_user->id;

			echo do_shortcode( '[videowhisper_support params="creator:' . intval( $userID ) . '"]' );
		}
		//bp

		static function set_logged_in_cookie( $logged_in_cookie ){
			$_COOKIE[LOGGED_IN_COOKIE] = $logged_in_cookie;
		}

		static function login_redirect( $redirect_to, $request, $user ) {

			$options = self::getOptions();
			if ( ! isset( $options['redirectUser']) ) return $redirect_to;

			if ( ! $options['redirectUser'] ) return $redirect_to;

			//redirect to support page
			$pid = $options['p_videowhisper_support'];
			if ($pid) return get_permalink( $pid );
			
			//default
			return $redirect_to;
			}

			function single_template( $single_template ) {

				if ( ! is_singular() ) {
					return $single_template; // not single page/post
				}
	
				// forced template
				if  ( isset($_GET['vwtemplate']) && $_GET['vwtemplate'] == 'support' ) {
					$options = self::getOptions(); //only if enabled
					if ( $options['fullpageTemplate'] ?? false)
					{
						$single_template_new = dirname( __FILE__ ) . '/template-support.php';
						if ( file_exists( $single_template_new ) ) {
							return $single_template_new;
						}
					}

				}
	
				return $single_template;
			}
	
		

		//class
		}
//if
}
// instantiate
if ( class_exists( 'VWliveSupport' ) ) 	$liveSupport = new VWliveSupport();

// Actions and Filters
if ( isset( $liveSupport ) ) {
	add_action( 'init', array( &$liveSupport, 'init' ) );
	add_action( 'plugins_loaded', array( &$liveSupport, 'plugins_loaded' ) );

	// admin
	add_action( 'admin_menu', array( &$liveSupport, 'admin_menu' ) );
	add_action( 'admin_bar_menu', array( &$liveSupport, 'admin_bar_menu' ), 91 );

	//REST login nonce fix
	add_action( 'set_logged_in_cookie', array( &$liveSupport, 'set_logged_in_cookie' ) );

	//redirect
	add_filter( 'login_redirect', array( &$liveSupport, 'login_redirect' ), 20, 3 ); //priority 20 in case there's some other redirect plugin

	//  template
	add_filter( 'single_template', array( &$liveSupport, 'single_template' ) );
	add_filter( 'page_template', array( &$liveSupport, 'single_template' ) );
}
?>