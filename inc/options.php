<?php
namespace VideoWhisper\LiveSupport;

if ( ! defined( 'ABSPATH' ) )
{
	exit; // Exit if accessed directly
}

trait Options {
	// define and edit settings


static function settings_link( $links ) {
	$settings_link = '<a href="admin.php?page=vw-support">' . __( 'Settings', 'live-support-tickets' ) . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
}

// admin menus
function admin_bar_menu( $wp_admin_bar )
{
	if ( ! is_user_logged_in() )
	{
		return;
	}

	$options = self::getOptions();

    //admin/editor menus
	if ( current_user_can( 'editor' ) || current_user_can( 'administrator' ) )
	{

		// find VideoWhisper menu
		$nodes = $wp_admin_bar->get_nodes();
		if ( ! $nodes )
		{
			$nodes = array();
		}
		$found = 0;
		foreach ( $nodes as $node )
		{
			if ( $node->title == 'VideoWhisper' )
			{
				$found = 1;
			}
		}

		if ( ! $found )
		{
			$wp_admin_bar->add_node(
				array(
					'id'    => 'videowhisper',
					'title' => '👁 VideoWhisper',
					'href'  => admin_url( 'plugin-install.php?s=videowhisper&tab=search&type=term' ),
				)
			);

			// more VideoWhisper menus

			$wp_admin_bar->add_node(
				array(
					'parent' => 'videowhisper',
					'id'     => 'videowhisper-add',
					'title'  => __( 'Add Plugins', 'live-support-tickets' ),
					'href'   => admin_url( 'plugin-install.php?s=videowhisper&tab=search&type=term' ),
				)
			);

			$wp_admin_bar->add_node(
				array(
					'parent' => 'videowhisper',
					'id'     => 'videowhisper-contact',
					'title'  => __( 'Contact Support', 'live-support-tickets' ),
					'href'   => 'https://videowhisper.com/tickets_submit.php?topic=WordPress+Plugins+' . urlencode( $_SERVER['HTTP_HOST'] ),
				)
			);
		} //!found


		$menu_id = 'videowhisper-support';

		if ( ! self::timeTo( 'backendCount', 60, $options ) ) self::updateCounters($options);
		$tickets_admin = intval( get_option('vwSupport_new') );


		$wp_admin_bar->add_node(
			array(
				'parent' => 'videowhisper',
				'id'     => $menu_id,
				'title'  => $tickets_admin ? '💬 ' . 'Contact Support CRM' . ' <span class="ab-label update-count">+' . $tickets_admin . '</span> ' : '💬 ' . 'Contact Support CRM',
				'href'   => admin_url( 'admin.php?page=vw-support' ),
			)
		);

	$wp_admin_bar->add_node(
		array(
			'parent' => $menu_id,
			'id'     => $menu_id . '-conversations',
			'title'  => $tickets_admin ? '💬 ' . __( 'Conversations', 'live-support-tickets' ) . ' <span class="ab-label update-count">+' . $tickets_admin . '</span>' : '💬 ' . __( 'Conversations', 'live-support-tickets' ),
			'href'   => admin_url( 'admin.php?page=vw-support-conversations' ),
		)
	);

$wp_admin_bar->add_node(
		array(
			'parent' => $menu_id,
			'id'     => $menu_id . '-contacts',
			'title'  => '👤 ' . __( 'Contacts', 'live-support-tickets' ),
			'href'   => admin_url( 'admin.php?page=vw-support-contacts' ),
		)
	);

	$wp_admin_bar->add_node(
		array(
			'parent' => $menu_id,
			'id'     => $menu_id . '-conversation-new',
			'title'  => '💬 ' . __( 'New Conversation', 'live-support-tickets' ),
			'href'   => admin_url( 'admin.php?page=vw-support-conversation-new' ),
		)
	);

	$wp_admin_bar->add_node(
		array(
			'parent' => $menu_id,
			'id'     => $menu_id . '-invite',
			'title'  => '📧 ' . __( 'Invite', 'live-support-tickets' ),
			'href'   => admin_url( 'admin.php?page=vw-support-invite' ),
		)
	);

	$wp_admin_bar->add_node(
		array(
			'parent' => $menu_id,
			'id'     => $menu_id . '-form-invite',
			'title'  => '📝 ' . __( 'Form Invite', 'live-support-tickets' ),
			'href'   => admin_url( 'admin.php?page=vw-support-form-invite' ),
		)
	);

	$wp_admin_bar->add_node(
		array(
			'parent' => $menu_id,
			'id'     => $menu_id . '-pages',
			'title'  => '📄 ' . __( 'Setup Pages', 'live-support-tickets' ),
			'href'   => admin_url( 'admin.php?page=vw-support&tab=pages' ),
		)
	);


	if ($options['accounts_db'])
	{
	$wp_admin_bar->add_node(
		array(
			'parent' => $menu_id,
			'id'     => $menu_id . '-accounts',
			'title'  => '👤 ' . __( 'Accounts', 'live-support-tickets' ),
			'href'   => admin_url( 'admin.php?page=vw-support-accounts' ),
		)
	);

	$wp_admin_bar->add_node(
		array(
			'parent' => $menu_id,
			'id'     => $menu_id . '-plans',
			'title'  => '💳 ' . __( 'Account Plans', 'live-support-tickets' ),
			'href'   => admin_url( 'admin.php?page=vw-support-plans' ),
		)
	);
	}

	if ($options['accountsAPI'])
	{
	$wp_admin_bar->add_node(
		array(
			'parent' => $menu_id,
			'id'     => $menu_id . '-status',
			'title'  => '📊 ' . __( 'Account Status', 'live-support-tickets' ),
			'href'   => admin_url( 'admin.php?page=vw-support-status' ),
		)
	);
	}	

	$wp_admin_bar->add_node(
		array(
			'parent' => $menu_id,
			'id'     => $menu_id . '-settings',
			'title'  => '🔧 ' . __( 'Settings', 'live-support-tickets' ),
			'href'   => admin_url( 'admin.php?page=vw-support' ),
		)
	);


	$wp_admin_bar->add_node(
		array(
			'parent' => $menu_id,
			'id'     => $menu_id . '-documentation',
			'title'  => '📙 ' . __( 'Documentation', 'live-support-tickets' ),
			'href'   => admin_url( 'admin.php?page=vw-support-documentation' ),
		)
	);

	}

	//user menus

	$current_user = wp_get_current_user();

	// user
	if ( $options['p_videowhisper_support_conversations'] ?? false )  //if (self::rolesUser( $options['rolesCreator'], $current_user ) )
	{
		if ( current_user_can( 'editor' ) || current_user_can( 'administrator' ) )
		$wp_admin_bar->add_node(
			array(
				'parent' => 'my-account',
				'id'     => 'videowhisper_support_conversations',
				'title'  => ( $tickets_admin ?? 0 ) ? '💬 ' . __( 'Support Conversations', 'live-support-tickets' ) . ' <span class="count alert">' . ( $tickets_admin ?? 0 ) . '</span>' : '💬 ' . __( 'Support Conversations', 'live-support-tickets' ),
				'href'   => admin_url( 'admin.php?page=vw-support-conversations' ),
			)
		);
		
		if ( ! self::timeTo( 'frontendCount' . $current_user->ID, 120, $options ) ) self::updateCounters($options, $current_user->ID);
		$tickets_new = intval( get_user_meta( $current_user->ID, 'vwSupport_new', true) );

			$wp_admin_bar->add_node(
				array(
					'parent' => 'my-account',
					'id'     => 'videowhisper_user_conversations',
					'title'  => $tickets_new ? '💬 ' . __( 'My Conversations', 'live-support-tickets' ) . ' <span class="count alert">' . $tickets_new . '</span>' : '💬 ' . __( 'My Conversations', 'live-support-tickets' ),
					'href'   => get_permalink( $options['p_videowhisper_support_conversations'] ),
				)
			);
	}

	if ( $options['accounts_db'] && $options['p_videowhisper_support_accounts'] ?? false)
	{
		$wp_admin_bar->add_node(
			array(
				'parent' => 'my-account',
				'id'     => 'videowhisper_support_accounts',
				'title'  => '🔒 ' . __( 'My Accounts', 'live-support-tickets' ),
				'href'   => get_permalink( $options['p_videowhisper_support_accounts'] ),
			)
		);
	}


}


function admin_menu()
{

	$options = self::getOptions();

	if ( ! self::timeTo( 'backendCount', 60, $options ) ) self::updateCounters($options);
	$tickets_new = intval( get_option('vwSupport_new') );

	add_menu_page( 'Support CRM', $tickets_new ? 'Support CRM' . '<span class="awaiting-mod">' . $tickets_new . '</span>' : 'Support CRM', 'manage_options', 'vw-support', array( 'VWliveSupport', 'adminOptions' ), 'dashicons-editor-help', 84 );
	add_submenu_page( 'vw-support', 'Settings for Live Support', 'Settings', 'manage_options', 'vw-support', array( 'VWliveSupport', 'adminOptions' ) );
	add_submenu_page( 'vw-support', 'Conversations', $tickets_new ? 'Conversations' . '<span class="awaiting-mod">' . $tickets_new . '</span>' : 'Conversations', 'manage_options', 'vw-support-conversations', array( 'VWliveSupport', 'adminConversations' ) );
	add_submenu_page( 'vw-support', 'Contacts', 'Contacts', 'manage_options', 'vw-support-contacts', array( 'VWliveSupport', 'adminContacts' ) );
	add_submenu_page( 'vw-support', 'Cans', 'Cans', 'manage_options', 'vw-support-cans', array( 'VWliveSupport', 'adminCans' ) );
	add_submenu_page( 'vw-support', 'New Conversation', 'New Conversation', 'manage_options', 'vw-support-conversation-new', array( 'VWliveSupport', 'adminConversationNew' ) );
	add_submenu_page( 'vw-support', 'Invite', 'Invite', 'manage_options', 'vw-support-invite', array( 'VWliveSupport', 'adminInvite' ) );
	add_submenu_page( 'vw-support', 'Form Invite', 'Form Invite', 'manage_options', 'vw-support-form-invite', array( 'VWliveSupport', 'adminFormInvite' ) );
	add_submenu_page( 'vw-support', 'Properties', 'Properties', 'manage_options', 'vw-support-properties', array( 'VWliveSupport', 'adminProperties' ) );
	if ($options['accounts_db']) 
	{
		add_submenu_page( 'vw-support', 'Accounts', 'Accounts', 'manage_options', 'vw-support-accounts', array( 'VWliveSupport', 'adminAccounts' ) );
		add_submenu_page( 'vw-support', 'Account Plans', 'Account Plans', 'manage_options', 'vw-support-plans', array( 'VWliveSupport', 'adminPlans' ) );
	}
	if ($options['accountsAPI']) add_submenu_page( 'vw-support', 'Account Status', 'Account Status', 'manage_options', 'vw-support-status', array( 'VWliveSupport', 'adminStatus' ) );

	add_submenu_page( 'vw-support', 'Documentation', 'Documentation', 'manage_options', 'vw-support-documentation', array( 'VWliveSupport', 'adminDocumentation' ) );

}

static function adminConversationNew()
{
?>
<div class="wrap">
		<h3><span class="dashicons dashicons-admin-comments"></span>New Conversation /  Support & Contact Forms / VideoWhisper</h3>
		Open a new conversation with a new or existing contact. This will create a new contact if not existent and user will confirm it on access.
		</div>
		<?php

	$options = self::getOptions();

	$email = sanitize_email( $_POST['email'] ?? '' );
	$name =	 sanitize_text_field( $_POST['name'] ?? '' );
	$department_id = intval( $_POST['department'] ?? 0 );

	if (!$email || !$department_id)
	{
		$departments = self::departments(0,0,0, $options);
		
		echo '<form action="' . esc_url( wp_nonce_url( 'admin.php?page=vw-support-conversation-new', 'vwsec') ) . '" method="post">';
		echo '<table class="form-table">';
		echo '<tr><th scope="row">Email</th><td><input type="text" name="email" value="' . esc_attr( sanitize_email( $_GET['email'] ?? '' ) ) . '" /></td></tr>';
		echo '<tr><th scope="row">Name</th><td><input type="text" name="name" value="' . esc_attr( sanitize_text_field( $_GET['name'] ?? '' )  ) . '" /></td></tr>';

		echo '<tr><th scope="row">Department</th><td><select name="department">';

		foreach ($departments as $depart)
		{
			echo '<option value="' . esc_attr($depart['value']) . '">' . esc_html($depart['text']) . '</option>';	
		}
		echo '</select></td></tr>';
		echo '<tr><th scope="row" colspan="2"><input class="button" type="submit" name="submit" id="submit" value="Open New Conversation"/></th></tr>';
		echo '</table>';
		echo '</form>';
	}else
	{

	$nonce = $_REQUEST['_wpnonce'];
	if ( ! wp_verify_nonce( $nonce, 'vwsec' ) )
	{
		echo 'Invalid nonce!';
		exit;
	}

	global $wpdb;
	$table_contacts = $wpdb->prefix . 'vws_contacts';
	$table_tickets = $wpdb->prefix . 'vws_tickets';
	$table_ticket_contacts = $wpdb->prefix . 'vws_ticket_contacts';

	$contact = $wpdb->get_row( "SELECT * FROM `$table_contacts` WHERE contact = '$email'" );
	if (!$contact)
	{
		$meta = [];
		$meta['created_by'] = 'conversation_new';
		$metaS = serialize($meta);

		//if user exists, use it
		$user = get_user_by( 'email', $email );
		if ($user) 
		{
			$uid = $user->ID;
			$name = $user->display_name;
			$confirmed = time();
			echo 'User found: ' . esc_html( $name ) . ', #' . intval( $uid ) . '<br/>';
		}
		else
		{ 
			$uid = 0;
			$confirmed = 0;
		}

		if (!$name) $name = substr(strstr($email, '@'), 1);

		$type = 'email';
		$pin = self::pinGenerate();

		$sqlI = "INSERT INTO `$table_contacts` ( `uid`, `name`, `contact`, `type`, `pin`, `created`, `meta`, `confirmed` ) VALUES ( '$uid', '" . $name . "', '" . $email . "', '" . $type . "', '" . $pin . "', '" . time() . "', '$metaS', '" . $confirmed ."' )";
		$wpdb->query( $sqlI );
		$contact_id = $wpdb->insert_id;

		$args = [ 'contact' => $contact_id, 'confirm' => $pin];
	
 	}
	else
	{	
		echo 'Contact found: ' . esc_html( $contact->name ) . ', #' . intval( $contact->id ). '<br/>';

		$contact_id = $contact->id;
		$args = [ 'contact' => $contact_id, 'confirm' => $contact->pin];
	}

	//create a new ticket/conversation 
	$meta = [];
	$meta['created_contact'] = $contact_id;
	$meta['created_ip'] = self::get_ip_address();
	$meta['created_time'] = time();

	$department = self::departments(0, 0, 0, $options, $department_id);
	$type = $department['type'];

	$metaS = serialize($meta);

	$sqlI = "INSERT INTO `$table_tickets` ( `department`, `created`, `meta`, `status`, `type`, `site`, `content`, `creator`) VALUES ( '" . $department_id . "', '" . time() . "', '" . $metaS . "', '0', '$type', '0', '0', '0' )";
	$wpdb->query( $sqlI );
	$ticket_id = $wpdb->insert_id;

	if (!$ticket_id) {
    echo 'SQL error: ' . esc_html($wpdb->last_error) . ' / ' . esc_html($sqlI) . '<br/>';
	return;
	}
	else
	{
		echo 'Conversation was created: #' . intval( $ticket_id ) . '<br/>';
		$args['ticket'] = $ticket_id;
	}

	//add representative contact to ticket and generate access pin
	$repPin = self::pinGenerate();
	//retrieve current user contact 
	$repContact = $wpdb->get_row( "SELECT * FROM `$table_contacts` WHERE uid = '" . get_current_user_id() . "' LIMIT 1" );
	if ($repContact)
	{
		$repArgs = [ 'contact' => $repContact->id, 'rep' => $repContact->pin, 'ticket' => $ticket_id, 'code' => $repPin];

		$sqlI = "INSERT INTO `$table_ticket_contacts` ( `tid`, `cid`, `pin`, `rep` ) VALUES ( '" . $ticket_id . "', '" . $repContact->id . "', '" . $repPin . "', '1' )";
		$wpdb->query($sqlI);
		$ticket_contact_id = $wpdb->insert_id;
		if (!$ticket_contact_id) {
			echo 'Last SQL error: ' . esc_html( $wpdb->last_error . ' / ' . $sqlI ). '<br/>';
		}

		$repURL = add_query_arg( $repArgs , $options['appURL'] ) . '#vws';
		echo 'Representative Conversation URL: ' . esc_html( $repURL ) . '<br/>';
		echo '<a class="button primary" href="' . esc_url( $repURL ) . '" target="_blank">Open Conversation</a><br/>';
	}

	//add target contact to ticket and generate access pin
    $pin = self::pinGenerate();
    $sqlI = "INSERT INTO `$table_ticket_contacts` ( `tid`, `cid`, `pin`, `rep` ) VALUES ( '" . $ticket_id . "', '" . $contact_id . "', '" . $pin . "', '0' )";
    $wpdb->query($sqlI);
    $ticket_contact_id = $wpdb->insert_id;
    if (!$ticket_contact_id) {
        echo 'Last SQL error: ' . esc_html( $wpdb->last_error . ' / ' . $sqlI ). '<br/>';
    }
	else $args['code'] = $pin;

	$url = add_query_arg( $args , $options['appURL'] ) . '#vws';

	$subject = $options['notifyTicketSubject'];
	$message = $options['notifyTicketMessage'] . ' ' . $url;
	$replace = [ '{sender}' => $repContact->name ?? '{unknown}', '{department}' => self::departmentName($department_id, $options) ];
	$subject = strtr($subject, $replace);
	$message = strtr($message, $replace);
    //self::notifyContact($contact_id, $subject, $message . ' ' . $url, $options, false, $ticket_id); // notify on new conversations (not needed as it will be done on first message)

	//display ticket
	//$params = "ticket:'$ticket_id', contact:'$contact_id', rep:'". $repContact->pin ."', code:'$repPin', devMode:'1'";
	//echo do_shortcode( '[videowhisper_support params="'. $params . '"]' );

	echo '<p>Notification sent to ' . esc_html( $email ) . ' for ticket.<br/>';
	echo 'Contact will be notified by email and will be able to access the contact form by clicking the link in the email.<p/>';

	}

echo '<h4>Settings</h4>';
echo 'Email subject:<br/> ' . esc_html( $options['notifyTicketSubject'] ) . '<br/>';
echo 'Email message:<br/> ' . esc_html( $options['notifyTicketMessage'] ) . '<br/>'; 
}

static function adminInvite()
{
	?>
		<div class="wrap">
		<h3><span class="dashicons dashicons-email"></span>Invite /  Contact Support CRM / VideoWhisper</h3>
		Invite a contact to open a conversation, by email with contact page URL. This will create a new contact if not existent and user will confirm it on access.
		</div>
		<?php

	$options = self::getOptions();

	$email = sanitize_email( $_POST['email'] ?? '' );
	$name =	 sanitize_text_field( $_POST['name'] ?? '' );
	$department = sanitize_text_field( $_POST['department'] ?? '' );

	if (!$email || !$department)
	{
		$departments = self::departments(0,0,0, $options);
		
		echo '<form action="' . wp_nonce_url( 'admin.php?page=vw-support-invite', 'vwsec') . '" method="post">';
		echo '<table class="form-table">';
		echo '<tr><th scope="row">Email</th><td><input type="text" name="email" value="' . esc_attr( sanitize_email( $_GET['email'] ?? '' ) ) . '" /></td></tr>';
		echo '<tr><th scope="row">Name</th><td><input type="text" name="name" value="' . esc_attr( sanitize_text_field( $_GET['name'] ?? '' )  ) . '" /></td></tr>';

		echo '<tr><th scope="row">Department</th><td><select name="department">';

		foreach ($departments as $depart)
		{
			echo '<option value="' . esc_attr($depart['text']) . '">' . esc_html($depart['text']) . '</option>';	
		}
		echo '</select></td></tr>';
		echo '<tr><th scope="row" colspan="2"><input class="button" type="submit" name="submit" id="submit" value="Invite"/></th></tr>';
		echo '</table>';
		echo '</form>';
	}else
	{

	$nonce = $_REQUEST['_wpnonce'];
	if ( ! wp_verify_nonce( $nonce, 'vwsec' ) )
	{
		echo 'Invalid nonce!';
		exit;
	}

	global $wpdb;
	$table_contacts = $wpdb->prefix . 'vws_contacts';

	$contact = $wpdb->get_row( "SELECT * FROM `$table_contacts` WHERE contact = '$email'" );
	if (!$contact)
	{
		$meta = [];
		$meta['created_by'] = 'department_invite';
		$metaS = serialize($meta);

		//if user exists, use it
		$user = get_user_by( 'email', $email );
		if ($user) 
		{
			$uid = $user->ID;
			$name = $user->display_name;
			$confirmed = time();
			echo 'User found: ' . esc_html( $name ) . ', #' . intval( $uid ) . '<br/>';
		}
		else
		{ 
			$uid = 0;
			$confirmed = 0;
		}

		if (!$name) $name = substr(strstr($email, '@'), 1);

		$type = 'email';
		$pin = self::pinGenerate();

		$sqlI = "INSERT INTO `$table_contacts` ( `uid`, `name`, `contact`, `type`, `pin`, `created`, `meta`, `confirmed` ) VALUES ( '$uid', '" . $name . "', '" . $email . "', '" . $type . "', '" . $pin . "', '" . time() . "', '$metaS', '" . $confirmed ."' )";
		$wpdb->query( $sqlI );
		$contact_id = $wpdb->insert_id;

		$args = [ 'contact' => $contact_id, 'confirm' => $pin];
	
 	}
	else
	{	
		echo 'Contact found: ' . esc_html( $contact->name ) . ', #' . intval( $contact->id ). '<br/>';

		$contact_id = $contact->id;
		$args = [ 'contact' => $contact_id, 'confirm' => $contact->pin];
	}

	$args['department'] = urlencode( $department );
	$url = add_query_arg( $args , $options['appURL'] ) . '#vws';

	self::notifyContact($contact_id, $options['notifyInviteSubject'],  $options['notifyInviteMessage'] . ' ' . $url);

	echo '<p>Invitation sent to ' . esc_html( $email ) . ' from deparment ' . esc_html( $department ) . '.<br/>';
	echo 'Contact will be notified by email and will be able to access the contact form by clicking the link in the email.<p/>';

	}

echo '<h4>Settings</h4>';
echo 'Email subject:<br/> ' . esc_html( $options['notifyInviteSubject'] ) . '<br/>';
echo 'Email message:<br/> ' . esc_html( $options['notifyInviteMessage'] ) . '<br/>'; 
}

static function adminFormInvite()
{
	?>
		<div class="wrap">
		<h3><span class="dashicons dashicons-email"></span> Form Invite / Contact Support CRM & Contact Forms / VideoWhisper</h3>
		Invite a contact to fill a form, by email with form URL. This will create a new contact if not existent and user will confirm it on access.
		</div>
	<?php	

$options = self::getOptions();

$email = sanitize_email( $_POST['email'] ?? '' );
$name =	 sanitize_text_field( $_POST['name'] ?? '' );
$form  = sanitize_text_field( $_POST['form'] ?? '' );

if (!$email || !$form)
{ 
	echo '<form action="' . wp_nonce_url( 'admin.php?page=vw-support-form-invite', 'vwsec') . '" method="post">';
	echo '<table class="form-table">';
	echo '<tr><th scope="row">Email</th><td><input size="30" name="email" value="' . esc_attr( sanitize_email( $_GET['email'] ?? '' ) ) . '"/>*</td></tr>';
	echo '<tr><th scope="row">Contact</th><td><input size="30" name="name" value="' . esc_attr( sanitize_text_field( $_GET['name'] ?? '' ) ) . '"/></td></tr>';
	echo '<tr><th scope="row">Form</th><td><select name="form">';
	if ( is_array( $options['forms'] ) ) foreach ( $options['forms'] as $label => $params )
	{
		echo '<option value="' . esc_attr($label) . '">' . esc_html($label) . '</option>';	
	}
	echo '</select></td></tr>';
	echo '<tr><th scope="row" colspan="2"><input class="button" type="submit" name="submit" id="submit" value="Invite"/></th></tr>';
	echo '</table>';
	echo '</form>';
}
else
{
	$nonce = $_REQUEST['_wpnonce'];
	if ( ! wp_verify_nonce( $nonce, 'vwsec' ) )
	{
		echo 'Invalid nonce!';
		exit;
	}

	global $wpdb;
	$table_contacts = $wpdb->prefix . 'vws_contacts';

	$contact = $wpdb->get_row( "SELECT * FROM `$table_contacts` WHERE contact = '$email'" );
	if (!$contact)
	{
		$meta = [];
		$meta['created_by'] = 'form_invite';
		$metaS = serialize($meta);

		//if user exists, use it
		$user = get_user_by( 'email', $email );
		if ($user) 
		{
			$uid = $user->ID;
			$name = $user->display_name;
			$confirmed = time();
			echo 'User found: ' . esc_html( $name ) . ', #' . intval( $uid ) . '<br/>';
		}
		else
		{ 
			$uid = 0;
			$confirmed = 0;
		}

		if (!$name) $name = substr(strstr($email, '@'), 1);

		$type = 'email';
		$pin = self::pinGenerate();

		$sqlI = "INSERT INTO `$table_contacts` ( `uid`, `name`, `contact`, `type`, `pin`, `created`, `meta`, `confirmed` ) VALUES ( '$uid', '" . $name . "', '" . $email . "', '" . $type . "', '" . $pin . "', '" . time() . "', '$metaS', '" . $confirmed ."' )";
		$wpdb->query( $sqlI );
		$contact_id = $wpdb->insert_id;

		$args = [ 'contact' => $contact_id, 'confirm' => $pin];
	
 	}
	else
	{	
		echo 'Contact found: ' . esc_html( $contact->name ) . ', #' . intval( $contact->id ). '<br/>';

		$contact_id = $contact->id;
		$args = [ 'contact' => $contact_id, 'confirm' => $contact->pin];
	}

	$args['form'] = urlencode( $form );
	$url = add_query_arg( $args , $options['appURL'] ) . '#vws';

	self::notifyContact($contact_id, $form . ': ' . $options['notifyFormInviteSubject'], $form . ': ' . $options['notifyFormInviteMessage'] . ' ' . $url);

	echo '<p>Invitation sent to ' . esc_html( $email ) . ' for form ' . esc_html( $form ) . '.<br/>';
	echo 'Contact will be notified by email and will be able to access the form by clicking the link in the email.<p/>';
}
echo '<h4>Settings</h4>';
echo 'Email subject:<br/> #Form#: ' . esc_html( $options['notifyFormInviteSubject'] ) . '<br/>';
echo 'Email message:<br/> #Form#: ' . esc_html( $options['notifyFormInviteMessage'] ) . '<br/>'; 
}


static function adminProperties()
{
	$options = self::getOptions();
    global $wpdb;

	$table_contacts = $wpdb->prefix . 'vws_contacts';
	$table_properties = $wpdb->prefix . 'vws_properties'; //payments, services, etc

	/* 
	//$table_properties structure:
	CREATE TABLE `$table_properties` (
			  `id` int(11) NOT NULL AUTO_INCREMENT,
			  `type` varchar(32) NOT NULL,
			  `name` varchar(64) NOT NULL,
			  `value` mediumtext NOT NULL,
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
			  KEY `category` (`category`),
			  KEY `group` (`group`),
			  KEY `owner` (`owner`),
			  KEY `cid` (`cid`),
			  KEY `created` (`created`),
			  KEY `updated` (`updated`),
			  KEY `status` (`status`),
			  KEY `site` (`site`)
			) ENGINE=InnoDB $charset_collate COMMENT='VideoWhisper  - Support - Properties @2023';

	*/

	?>
	<div class="wrap">
	<h3><span class="dashicons dashicons-admin-comments"></span> Properties / Contact Forms, Support, CRM / VideoWhisper     
	<a href="<?php echo wp_nonce_url(admin_url('admin.php?page=vw-support-properties&action=upload-properties'), 'upload_properties'); ?>" class="button-primary">Upload Properties</a>
</h3>
	</div>
<?php

if (isset($_GET['action']) && $_GET['action'] == 'upload-properties') {
    if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'upload_properties')) {
        die('Security check failed');
    }

    if (isset($_GET['action']) && $_GET['action'] == 'upload-properties') {
		if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'upload_properties')) {
			die('Security check failed');
		}
		?>
		<h2>Upload Properties</h2>
<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( wp_nonce_url( admin_url('admin.php?page=vw-support-properties&action=process-upload'), 'process_upload' ) ); ?>">
	<table class="form-table">
		<tbody>
			<tr>
				<th scope="row"><label for="csv_file">Select CSV File:</label></th>
				<td><input type="file" name="csv_file" id="csv_file" accept=".csv" required /></td>
			</tr>
			<tr>
				<th scope="row"><label for="file_type">Select File Type:</label></th>
				<td>
					<select name="file_type" id="file_type" class="regular-text" required>
						<option value="auto">Auto</option>
						<option value="CpanelAccounts">WHM/Cpanel Accounts</option>
						<option value="StripeSubscriptions">Stripe Subscriptions</option>
					</select>
				</td>
			</tr>
		</tbody>
	</table>
	<p>
		<input type="submit" value="Upload" class="button button-primary" />
	</p>
</form>
		<?php
		
	}
	
}

if (isset($_GET['action']) && $_GET['action'] == 'process-upload') {
    if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'process_upload')) {
        die('Security check failed');
    }

	if (isset($_FILES['csv_file']))
    if ($_FILES['csv_file']['error'] == UPLOAD_ERR_OK && $_POST['file_type']) {
        $csv_file = new \SplFileObject($_FILES['csv_file']['tmp_name']);
        $csv_file->setFlags(\SplFileObject::READ_CSV);
		$file_type = esc_html($_POST['file_type']); //initially

		//detect
		$first_row = $csv_file->fgetcsv(); // get the header row
	
		$cpanel_accounts_fields = array('Domain', 'IP', 'User Name', 'Email', 'Start Date', 'Disk Partition');
		$is_cpanel_accounts = count(array_intersect($cpanel_accounts_fields, $first_row)) == count($cpanel_accounts_fields);
		if (count($first_row) != 20) $is_cpanel_accounts = false;

		$stripe_subscriptions_fields = array('id', 'Customer ID', 'Customer Description', 'Customer Email', 'Plan', 'Product', 'Quantity', 'Interval', 'Amount', 'Status');
		$is_stripe_subscriptions = count(array_intersect($stripe_subscriptions_fields, $first_row)) == count($stripe_subscriptions_fields);
		if (count($first_row) != 28) $is_stripe_subscriptions = false;

		if ($_POST['file_type'] == 'auto')
		if ($is_cpanel_accounts) {
			$_POST['file_type'] = 'CpanelAccounts';
		} elseif ($is_stripe_subscriptions) {
			$_POST['file_type'] = 'StripeSubscriptions';
		} else {
			$_POST['file_type'] = 'error';
		}
	
	    // Verify file type
		if ($_POST['file_type'] == 'CpanelAccounts' && !$is_cpanel_accounts) {
			$_POST['file_type'] = 'error';
		} 

		if ($_POST['file_type'] == 'StripeSubscriptions' && !$is_stripe_subscriptions) {
			$_POST['file_type'] = 'error';
		}
	
	if ($_POST['file_type'] == 'error') {
		echo '<p>Error: ' . esc_html( $file_type  ) . ' #' . count($first_row). ' / Unable to automatically detect or recognize the file type based on the fields in the first row, or the file_type is set incorrectly.</p>';
		//var_dump($first_row);
		return;
	}

		//detect type
		$file_type = sanitize_text_field($_POST['file_type']);

		$rowsFile = 0;
		$rowsInserted = 0;
		$rowsUpdated = 0;

        while (!$csv_file->eof()) {
            $row = $csv_file->fgetcsv();
            if ( !empty($row) && $row[0] ) {

				$rowsFile++;

                switch ($file_type) {
                    case 'CpanelAccounts':
                        $data = array(
                            'type' => 'CPanel',
                            'meta' => json_encode(array_combine($first_row, $row)),
                            'value' => $row[0], // Domain
                            'name' => $row[2], // User Name
                            'owner' => $row[3], // Email
                            'category' => $row[8], // Package
                            'group' => $row[11], // Server
                            'created' => intval($row[12]), // Unix Startdate
                            'status' => intval($row[15]), // Is Suspended
                            'updated' => time(),
                        );

						// Check if any of the emails in the Email field match the 'contact' field in $table_contacts
						$emails = explode(',', $row[3]);
						$found_contact_id = 0;

						foreach ($emails as $email) {
							$email = trim($email);
							$contact = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_contacts WHERE contact = %s", $email));

							if ($contact) {
								$found_contact_id = $contact->id;
								break;
							}
						}

						$data['cid'] = $found_contact_id;

                        // Check if the user name already exists
                        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_properties WHERE `name` = '%s' AND `group` = '%s'", $data['name'], $data['group']));

                        if ($existing) {
                            // Update the row
                            $wpdb->update($table_properties, $data, array('id' => $existing->id));
							$rowsUpdated++;
                        } else {
                            // Insert a new row
                            $wpdb->insert($table_properties, $data);
							$rowsInserted++;
                        }

						//display $wpdb error and query if any
						if ($wpdb->last_error) {
							echo '<p>Database error: ' . esc_html( $wpdb->last_error ) . ' Query: ' . esc_html( $wpdb->last_query ) . '</p>';
						}

                        break;

						case 'StripeSubscriptions':
							$data = array(
								'type' => 'StripeSubscription',
								'meta' => json_encode(array_combine($first_row, $row)),
								'name' => $row[0], // id
								'value' => $row[25], // Customer Name
								'owner' => $row[3], // Customer Email
								'category' => $row[5], // Product
								'group' => $row[7], // Interval
								'created' => strtotime($row[10]), // Created (UTC)
								'status' => $row[9] == 'active' ? 0 : ($row[21] == 'true' ? 2 : 1), // Status
								'updated' => strtotime($row[13]), // Current Period Start (UTC)
							);
						
							// Check if Customer Email or Customer Name matches a contact in the $table_contacts
							$contact = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_contacts WHERE contact = %s OR name = %s", $row[3], $row[24]));
						
							$data['cid'] = $contact ? $contact->id : 0;
						
							// Check if the id already exists
							$existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_properties WHERE name = %s", $data['name']));
						
							if ($existing) {
								// Update the row
								$wpdb->update($table_properties, $data, array('id' => $existing->id));
								$rowsUpdated++;

							} else {
								// Insert a new row
								$wpdb->insert($table_properties, $data);
								$rowsInserted++;
							}
							break;

							case 'auto':
							case 'error':
								// Do nothing
								break;
                }
            }
        }

		echo '<p>' . esc_html( $file_type ) . ' CSV file uploaded successfully. Rows in file: ' . intval( $rowsFile ) . ' Rows inserted: ' . intval( $rowsInserted ) . ' Rows updated: ' . intval( $rowsUpdated ) . '</p>';

		if ($file_type == 'auto') echo '<p>File type was not automatically detected. Count: ' . count($first_row) . ' Header: ' . json_encode($first_row). ' .</p>';

    } else {
        echo '<p>CSV file upload failed';
    }
}

// Check for delete and edit actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];

    if ($action == 'delete-row' && isset($_GET['id'])) {
        if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'delete_row')) {
            die('Security check failed');
        }

        $id = intval($_GET['id']);
        $wpdb->delete($table_properties, array('id' => $id));
    } elseif ($action == 'edit-row' && isset($_GET['id'])) {
        if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'edit_row')) {
            die('Security check failed');
        }

        $id = intval($_GET['id']);
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_properties} WHERE id = %d", $id), ARRAY_A);

        if ($row) {
            echo '<h2>Edit Row</h2>';
            echo '<form method="post" action="' . wp_nonce_url(admin_url('admin.php?page=vw-support-properties&action=update-row&id=' . $id), 'update_row') . '">';
            // Display the edit form with the row data as default values
            // Add input fields for type, name, value, category, group, owner, meta, status 
			echo '<input type="hidden" name="id" value="' . esc_attr( $id ) . '" />';
			echo '<table class="form-table">';
			echo '<tr><th scope="row"><label for="type">Type</label></th><td><input size=64 type="text" name="type" value="' . esc_attr( $row['type'] ). '" /></td></tr>';
			echo '<tr><th scope="row"><label for="name">Name</label></th><td><input size=64 type="text" name="name" value="' . esc_attr( $row['name'] ). '" /></td></tr>';
			echo '<tr><th scope="row"><label for="value">Value</label></th><td><input size=64 type="text" name="value" value="' . esc_attr( $row['value'] ). '" /></td></tr>';
			echo '<tr><th scope="row"><label for="category">Category</label></th><td><input size=64 type="text" name="category" value="' . esc_attr( $row['category'] ). '" /></td></tr>';
			echo '<tr><th scope="row"><label for="group">Group</label></th><td><input size=64 type="text" name="group" value="' . esc_attr( $row['group'] ). '" /></td></tr>';
			echo '<tr><th scope="row"><label for="owner">Owner</label></th><td><input size=64 type="text" name="owner" value="' . esc_attr( $row['owner'] ). '" /></td></tr>';
			echo '<tr><th scope="row"><label for="meta">Meta</label></th><td><textarea name="meta" rows="5" cols="64">' . esc_attr( $row['meta'] ). '</textarea></td></tr>';
			echo '<tr><th scope="row"><label for="status">Status</label></th><td><input size=3 type="text" name="status" value="' . esc_attr( $row['status'] ). '" /></td></tr>';
			echo '</table>';
			echo '<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Update"></p>';
            echo '</form>';
        }
    } elseif ($action == 'update-row' && isset($_POST['id'])) {
        if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'update_row')) {
            die('Security check failed');
        }

        $id = intval($_POST['id']);
        // Get the updated values from the form input fields
        $updated_data = array(
            'type' => sanitize_text_field($_POST['type']),
            'name' => sanitize_text_field($_POST['name']),
            'value' => sanitize_text_field($_POST['value']),
            'category' => sanitize_text_field($_POST['category']),
            'group' => sanitize_text_field($_POST['group']),
            'owner' => sanitize_text_field($_POST['owner']),
			'meta' => sanitize_textarea_field($_POST['owner']),
			'status' => sanitize_text_field($_POST['status']),
            // Add other fields as needed
        );

        // Update the row in the database
        $wpdb->update($table_properties, $updated_data, array('id' => $id));
    }
}


// Pagination
$per_page = 10;
$current_page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;

// Fetch data with search
$search_keyword = isset($_GET['s']) ? $_GET['s'] : '';
if ($search_keyword) {
    $search_keyword = '%' . $wpdb->esc_like($search_keyword) . '%';
    $properties_data = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table_properties} WHERE type LIKE %s OR name LIKE %s OR value LIKE %s OR category LIKE %s OR `group` LIKE %s OR owner LIKE %s ORDER BY id ASC LIMIT %d OFFSET %d", $search_keyword, $search_keyword, $search_keyword, $search_keyword, $search_keyword, $search_keyword, $per_page, ($current_page - 1) * $per_page), ARRAY_A);
	$total_items = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_properties} WHERE type LIKE %s OR name LIKE %s OR value LIKE %s OR category LIKE %s OR `group` LIKE %s OR owner LIKE %s", $search_keyword, $search_keyword, $search_keyword, $search_keyword, $search_keyword, $search_keyword));
} else {
    $properties_data = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table_properties} ORDER BY id ASC LIMIT %d OFFSET %d", $per_page, ($current_page - 1) * $per_page), ARRAY_A);
	$total_items = $wpdb->get_var("SELECT COUNT(*) FROM {$table_properties}");
}

$total_pages = ceil($total_items / $per_page);

// Display table
echo '<table class="wp-list-table widefat fixed striped table-view-list">';
echo '<thead>';
echo '<tr>';
echo '<th>Type / Name / Value / Category / Group </th>';
echo '<th>Meta</th>';
echo '<th>Owner / Status / Contact / Actions</th>';
echo '</tr>';
echo '</thead>';

echo '<tbody>';
foreach ($properties_data as $row) {
    echo '<tr>';
    echo '<td>' . esc_html($row['type']) . '<br>' . esc_html($row['name']) . '<br>' . esc_html($row['value']) . '<br>' . esc_html($row['category']) . '<br>' . esc_html($row['group']) . '</td>';

	echo '<td><small>' . esc_html($row['meta']) . '</small></td>';
	echo '<td>' . esc_html($row['owner']) . '<br>'. esc_html($row['status']) . '<br>';
	if ($row['cid']) {
		$contact = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_contacts WHERE id = %d", $row['cid']));
		echo '<a href="' . wp_nonce_url(admin_url('admin.php?page=vw-support-contacts&edit=' . $row['cid']), 'vwsec') . '">' . esc_html($contact->name) . '</a>';
	} else {
		echo '-';
	}
    echo '<br>';
    echo '<a href="' . wp_nonce_url(admin_url('admin.php?page=vw-support-properties&action=edit-row&id=' . $row['id']), 'edit_row') . '">Edit</a> | ';
    echo '<a href="' . wp_nonce_url(admin_url('admin.php?page=vw-support-properties&action=delete-row&id=' . $row['id']), 'delete_row') . '">Delete</a>';
    echo '</td>';
    echo '</tr>';
}
echo '</tbody>';
echo '</table>';

// Display pagination
echo '<div class="tablenav bottom">';
echo '<div class="tablenav-pages">';
echo '<span class="displaying-num">Page ' . esc_html( $current_page ) . ' / '. esc_html( $total_items ) . ' items</span>';
echo paginate_links(array(
    'base' => add_query_arg('paged', '%#%'),
    'format' => '',
    'prev_text' => __('&laquo;', 'live-support-tickets'),
    'next_text' => __('&raquo;', 'live-support-tickets'),
    'total' => $total_pages,
    'current' => $current_page,
));
echo '</div>';
echo '</div>';


// Add a search form
echo '<form method="get" action="' . admin_url('admin.php') . '">';
echo '<input type="hidden" name="page" value="vw-support-properties" />';
echo '<p class="search-box">';
echo '<label class="screen-reader-text" for="search-input">Search Properties:</label>';
echo '<input type="search" id="search-input" name="s" value="' . (isset($_GET['s']) ? esc_attr($_GET['s']) : '') . '">';
echo '<input type="submit" id="search-submit" class="button" value="Search">';
echo '</p>';
echo '</form>';

}

static function adminStatus()
{
    $options = self::getOptions();

	?>
	<div class="wrap">
	<h3>Account Status / Contact Forms, Support, CRM / VideoWhisper     
</h3>
<?php
	echo esc_html( self::statusAccountsAPI(false, $options) );
?>

	</div>
<?php	
}


static function adminPlans()
{
    $options = self::getOptions();
    global $wpdb;

    ?>
        <div class="wrap">
        <h3><span class="dashicons dashicons-admin-comments"></span> Plans / Contact Forms, Support, CRM / VideoWhisper     
		<a href="<?php echo wp_nonce_url(admin_url('admin.php?page=vw-support-plans&action=create-plan'), 'create_plan'); ?>" class="button-primary">Create Plan</a>
</h3>
        
        </div>
    <?php

    if (!$options['accounts_db'] && !$options['accounts_host'])
    {
        echo '<p>Remote accounts are disabled. To enable, set accounts_db and accounts_host in settings.</p>';
        return;
    }
        $mysqli = new \mysqli($options['accounts_host'], $options['accounts_user'], $options['accounts_pass'], $options['accounts_db'], $options['accounts_port']);

        if ($mysqli->connect_error)
        {
            echo '<BR><font color="red">Database connection failed: ' . esc_html($accountsDB->connect_error) . '</font>';
            return;
        }

    // Output the create plan form
    if (isset($_GET['action']) && $_GET['action'] == 'create-plan') {
        echo '<h2>Create Plan</h2>';
        echo '<form method="post" action="' . wp_nonce_url(admin_url('admin.php?page=vw-support-plans&action=add-plan'), 'add_plan') . '">';
        echo '<table class="form-table">';
        echo '<tr><th><label for="name">Name:</label></th><td><input size="32" type="text" name="name" value=""></td></tr>';
        echo '<tr><th><label for="properties">Properties:</label></th><td><textarea cols="100" rows="2" name="properties"></textarea></td></tr>';
        echo '</table>';
        echo '<p><input type="submit" value="Create" class="button-primary"></p>';
        echo '</form>';
    }

    // Handle plan creation
    if (isset($_GET['action']) && $_GET['action'] == 'add-plan') {
        if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'add_plan')) {
            die('Security check failed');
        }

        $name = $mysqli->real_escape_string(sanitize_text_field($_POST['name']));
        $properties = $mysqli->real_escape_string(stripslashes(sanitize_textarea_field($_POST['properties'])));

        $mysqli->query("INSERT INTO plans (name, properties) VALUES ('$name', '$properties')");
        echo '<p>Plan created.</p>';
    }

    // Output the edit form if a plan is being edited
    if (isset($_GET['action']) && $_GET['action'] == 'edit-plan' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $plan = $mysqli->query("SELECT * FROM plans WHERE id = $id")->fetch_assoc();
        if (!$plan) {
            echo '<p>Plan not found.</p>';
            return;
        }
		echo '<h2>Edit Plan</h2>';
        echo '<form method="post" action="' . wp_nonce_url(admin_url('admin.php?page=vw-support-plans&action=update-plan&id=' . $id), 'update_plan') . '">';
        echo '<table class="form-table">';
        echo '<tr><th><label for="name">Name:</label></th><td><input size="32" type="text" name="name" value="' . esc_attr(stripslashes($plan['name'])) . '"></td></tr>';
        echo '<tr><th><label for="properties">Properties:</label></th><td><textarea cols="100" rows="2" name="properties">' . esc_textarea(stripslashes($plan['properties'])) . '</textarea></td></tr>';
        echo '</table>';
        echo '<p><input type="submit" value="Save" class="button-primary"></p>';
        echo '</form>';
    }

    // Handle plan updates
    if (isset($_GET['action']) && $_GET['action'] == 'update-plan' && isset($_GET['id'])) {
        if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'update_plan')) {
            die('Security check failed');
        }
        $id = intval($_GET['id']);

        $name = $mysqli->real_escape_string(sanitize_text_field($_POST['name']));
        $properties = $mysqli->real_escape_string(stripslashes(sanitize_textarea_field($_POST['properties'])));

        $mysqli->query("UPDATE plans SET name = '$name', properties = '$properties' WHERE id = $id");
        echo '<p>Plan updated.</p>';
    }

    // Handle plan deletions
    if (isset($_GET['action']) && $_GET['action'] == 'delete-plan' && isset($_GET['id'])) {
        if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'delete_plan')) {
            die('Security check failed');
        }
        $id = (int)$_GET['id'];
        $mysqli->query("DELETE FROM plans WHERE id = $id");
        echo '<p>Plan deleted.</p>';
    }

    // Set up pagination
    $page = isset($_GET['paged']) ? (int)$_GET['paged'] : 1;
    $perPage = 10;
    $start = ($page > 1) ? ($page * $perPage) - $perPage : 0;

    // Retrieve the plans data from the remote table with pagination
    $data = $mysqli->query("SELECT * FROM plans ORDER by id ASC LIMIT $start, $perPage");
    $total = $mysqli->query("SELECT COUNT(*) as total FROM plans")->fetch_assoc()['total'];
    $pages = ceil($total / $perPage);

    // Output the plans data in a table
    echo '<table class="wp-list-table widefat fixed striped posts">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>ID</th>';
    echo '<th>Name</th>';
    echo '<th>Properties</th>';
    echo '<th>Actions</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
	while ($row = $data->fetch_assoc()) {
        echo '<tr>';
        echo '<td>' . esc_html($row['id']) . '</td>';
        echo '<td>' . esc_html($row['name']) . '</td>';
        echo '<td><small>' . esc_html($row['properties']) . '</small></td>';
        echo '<td>';
        echo '<a href="' . wp_nonce_url(admin_url('admin.php?page=vw-support-plans&action=edit-plan&id=' . $row['id']), 'edit_plan') . '" class="button">Edit</a>';
        echo ' ';
        echo '<a href="' . wp_nonce_url(admin_url('admin.php?page=vw-support-plans&action=delete-plan&id=' . $row['id']), 'delete_plan') . '" class="button" onclick="return confirm(\'Are you sure you want to delete this plan?\');">Delete</a>';
        echo '</td>';
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';

    // Output pagination
    if ($pages > 1) {
        echo '<div class="tablenav bottom">';
        echo '<div class="tablenav-pages">';
        echo '<span class="pagination-links">';
        if ($page > 1) {
            echo '<a class="first-page" href="' . admin_url('admin.php?page=vw-support-plans&paged=1') . '">&laquo;</a>';
            echo '<a class="prev-page" href="' . admin_url('admin.php?page=vw-support-plans&paged=' . ($page - 1)) . '">&lsaquo;</a>';
        }
        echo '<span class="paging-input">' . esc_html( $page ) . ' of ' . esc_html( $pages ) . '</span>';
        if ($page < $pages) {
            echo '<a class="next-page" href="' . admin_url('admin.php?page=vw-support-plans&paged=' . esc_attr( $page + 1 )) . '">&rsaquo;</a>';
            echo '<a class="last-page" href="' . admin_url('admin.php?page=vw-support-plans&paged=' . esc_attr( $pages )) . '">&raquo;</a>';
        }
        echo '</span>';
        echo '</div>';
        echo '</div>';
    }

    $mysqli->close();
}

static function adminAccounts()
{
	$options = self::getOptions();
	global $wpdb;
	?>
		<div class="wrap">
		<h3><span class="dashicons dashicons-admin-comments"></span> Remote Accounts / Contact Forms, Support, CRM / VideoWhisper
		<a href="<?php echo wp_nonce_url(admin_url('admin.php?page=vw-support-accounts&action=create-account'), 'create_account'); ?>" class="button-primary">Create Account</a>
	</h3>
		</div>
	<?php

	if (!$options['accounts_db'] && !$options['accounts_host'])
	{
		echo '<p>Remote accounts are disabled. To enable, set accounts_db and accounts_host in settings.</p>';
		return;
	}
		$mysqli = new \mysqli($options['accounts_host'], $options['accounts_user'], $options['accounts_pass'], $options['accounts_db'], $options['accounts_port']);
		
		if ($mysqli->connect_error) 
		{
			echo '<BR><font color="red">Database connection failed: ' . esc_html($accountsDB->connect_error) . '</font>';
			return;
		}

		 // Output the create account form
		 if (isset($_GET['action']) && $_GET['action'] == 'create-account') {
			echo '<h2>Create Account</h2>';
			echo '<form method="post" action="' . wp_nonce_url(admin_url('admin.php?page=vw-support-accounts&action=add-account'), 'add_account') . '">';
			echo '<table class="form-table">';
			echo '<tr><th><label for="name">Name:</label></th><td><input size="32" type="text" name="name" value=""></td></tr>';

			//get contact id based on user id as uid, from vws_contacts table
			$contacts_table = $wpdb->prefix . 'vws_contacts';
			$contact_id = $wpdb->get_var( "SELECT id FROM $contacts_table WHERE uid = ".get_current_user_id() );
			$token = 'vws-domain-' . $contact_id . '-'. self::pinGenerate();

			echo '<tr><th><label for="token">Token:</label></th><td><input size="64" type="text" name="token" value="' . esc_html($token) . '"></td></tr>';

			// Fetch plans from the plans table
			$plans = $mysqli->query("SELECT * FROM plans ORDER BY name ASC");

			echo '<tr><th><label for="planId">Plan:</label></th><td><select name="planId">';
			echo '<option value="0">None</option>';
			if ($plans) while ($plan = $plans->fetch_assoc()) {
				echo '<option value="' . esc_attr($plan['id']) . '">' . esc_html($plan['name']) . '</option>';
			}
			echo '</select></td></tr>';

			echo '<tr><th><label for="contactId">Contact ID:</label></th><td><input size="5" type="text" name="contactId" value="' . intval($contact_id) . '"></td></tr>';
			echo '<tr><th><label for="properties">Properties:</label></th><td><textarea cols="100" rows="2" name="properties"></textarea></td></tr>';
			echo '<tr><th><label for="meta">Meta:</label></th><td><textarea cols="100" rows="4" name="meta"></textarea></td></tr>';
			echo '</table>';
			echo '<p><input type="submit" value="Create" class="button-primary"></p>';
			echo '</form>';
		}

		// Handle account creation
if (isset($_GET['action']) && $_GET['action'] == 'add-account') {
    if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'add_account')) {
        die('Security check failed');
    }

    $name = $mysqli->real_escape_string(sanitize_text_field($_POST['name']));
    $token = $mysqli->real_escape_string(sanitize_text_field($_POST['token']));
    $planId = $mysqli->real_escape_string(intval($_POST['planId']));
    $contactId = $mysqli->real_escape_string(intval($_POST['contactId']));
    $properties = $mysqli->real_escape_string(stripslashes(sanitize_textarea_field($_POST['properties'])));
    $meta = $mysqli->real_escape_string(stripslashes(sanitize_textarea_field($_POST['meta'])));
    $created = time();

    $mysqli->query("INSERT INTO accounts (name, token, planId, contactId, properties, meta, created) VALUES ('$name', '$token', '$planId', '$contactId', '$properties', '$meta', '$created')");
    echo '<p>Account created.</p>';
}

		   // Output the edit form if an account is being edited
		   if (isset($_GET['action']) && $_GET['action'] == 'edit-account' && isset($_GET['id'])) {
			$id = (int)$_GET['id'];
			$account = $mysqli->query("SELECT * FROM accounts WHERE id = $id")->fetch_assoc();
			if (!$account) {
				echo '<p>Account not found.</p>';
				return;
			}
			echo '<h2>Edit Account</h2>';
			echo '<form method="post" action="' . wp_nonce_url(admin_url('admin.php?page=vw-support-accounts&action=update-account&id=' . $id), 'update_account') . '">';
			echo '<table class="form-table">';
			echo '<tr><th><label for="name">Name:</label></th><td><input size="32" type="text" name="name" value="' . esc_attr(stripslashes($account['name'])) . '"></td></tr>';
			echo '<tr><th><label for="token">Token:</label></th><td><input size="64" type="text" name="token" value="' . esc_attr(stripslashes($account['token'])) . '"></td></tr>';

			// Fetch plans from the plans table
			$plans = $mysqli->query("SELECT * FROM plans ORDER BY name ASC");

			echo '<tr><th><label for="planId">Plan:</label></th><td><select name="planId">';
			echo '<option value="0">None</option>';
			if ($plans) while ($plan = $plans->fetch_assoc()) {
				$selected = ($plan['id'] == $account['planId']) ? 'selected' : '';
				echo '<option value="' . esc_attr($plan['id']) . '" ' . esc_attr($selected) . '>' . esc_html($plan['name']) . '</option>';
			}
			echo '</select></td></tr>';

			echo '<tr><th><label for="contactId">Contact ID:</label></th><td><input size="5" type="text" name="contactId" value="' . esc_attr(stripslashes($account['contactId'])) . '"></td></tr>';
			echo '<tr><th><label for="properties">Properties:</label></th><td><textarea cols="100" rows="2" name="properties">' . esc_textarea(stripslashes($account['properties'])) . '</textarea></td></tr>';
			echo '<tr><th><label for="meta">Meta:</label></th><td><textarea cols="100" rows="4" name="meta">' . esc_textarea(stripslashes($account['meta'])) . '</textarea></td></tr>';
			echo '</table>';
			echo '<p><input type="submit" value="Save" class="button-primary"></p>';
			echo '</form>';			
		}

		// Handle account updates
		if (isset($_GET['action']) && $_GET['action'] == 'update-account' && isset($_GET['id'])) {
			if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'update_account')) {
				die('Security check failed');
			}
			$id = intval($_GET['id']);

			$name = $mysqli->real_escape_string(sanitize_text_field($_POST['name']));
			$token = $mysqli->real_escape_string(sanitize_text_field($_POST['token']));
			$planId = $mysqli->real_escape_string(intval($_POST['planId']));
			$contactId = $mysqli->real_escape_string(intval($_POST['contactId']));
			$properties = $mysqli->real_escape_string(stripslashes(sanitize_textarea_field($_POST['properties'])));
			$meta = $mysqli->real_escape_string(stripslashes(sanitize_textarea_field($_POST['meta'])));
	
			$mysqli->query("UPDATE accounts SET name = '$name', token = '$token', planId = '$planId', contactId = '$contactId', properties = '$properties', meta = '$meta' WHERE id = $id");
			echo '<p>Account updated.</p>';
		}
	
		// Handle account deletions
		if (isset($_GET['action']) && $_GET['action'] == 'delete-account' && isset($_GET['id'])) {
			if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'delete_account')) {
				die('Security check failed');
			}
			$id = (int)$_GET['id'];
			$mysqli->query("DELETE FROM accounts WHERE id = $id");
			echo '<p>Account deleted.</p>';
		}

		?>

		<?php
    // Set up pagination
    $page = isset($_GET['paged']) ? (int)$_GET['paged'] : 1;
    $perPage = 10;
    $start = ($page > 1) ? ($page * $perPage) - $perPage : 0;

	$cndSQL = ''; 
	$cndcSQL = '';
	$cid = intval($_GET['cid'] ?? 0);
	if ($cid) {
		$cndSQL = 'WHERE accounts.contactId = ' . $cid;
		$cndcSQL = 'WHERE contactId = ' . $cid;
	}
    // Retrieve the accounts data from the remote table with pagination
	$data = $mysqli->query("SELECT accounts.*, plans.name as plan_name FROM accounts LEFT JOIN plans ON accounts.planId = plans.id $cndSQL ORDER BY created DESC LIMIT $start, $perPage");
    $total = $mysqli->query("SELECT COUNT(*) as total FROM accounts $cndcSQL")->fetch_assoc()['total'];
    $pages = ceil($total / $perPage);

    // Output the accounts data in a table
    echo '<table class="wp-list-table widefat fixed striped posts">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Name</th>';
    echo '<th>Token</th>';
    echo '<th>Plan</th>';
    echo '<th>Contact</th>';
    echo '<th>Properties</th>';
    echo '<th>Meta</th>';
    echo '<th>Created</th>';
    echo '<th>Actions</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    while ($row = $data->fetch_assoc()) {
        echo '<tr>';
        echo '<td>' . esc_html($row['name']) . '</td>';
        echo '<td><small>' . esc_html($row['token']) . '</small></td>';
		
		echo '<td>' .( $row['planId'] ? esc_html($row['plan_name'] . ' #' . $row['planId']) : 'None' ) . '</td>';

		//get contact name from $wpdb->prefix . 'vws_contacts' table
		if ($row['contactId']) $contact = $wpdb->get_row("SELECT * FROM " . $wpdb->prefix . "vws_contacts WHERE id = " . $row['contactId']);
		else $contact = false;
		echo '<td>' . ( $contact? esc_html($contact->name) . ' #' . esc_html($row['contactId']) : '-' ) . '</td>';
        echo '<td><small>' . esc_html($row['properties']) . '</small></td>';
        echo '<td><small>' . esc_html($row['meta']) . '</small></td>';
        echo '<td><small>' . ( $row['created'] ? date("Y M d, H:i:s T", intval($row['created']) ) : '-' ) . '</small></td>';
        echo '<td>';
        echo '<a href="' . wp_nonce_url(admin_url('admin.php?page=vw-support-accounts&action=edit-account&id=' . $row['id']), 'edit_account') . '">Edit</a> | ';
        echo '<a href="' . wp_nonce_url(admin_url('admin.php?page=vw-support-accounts&action=delete-account&id=' . $row['id']), 'delete_account') . '">Delete</a> | ';
		echo '<a href="' . admin_url('admin-ajax.php?action=vws_settings&token=' . $row['token'] ) . '">Settings</a>';

		//frontend account page
		if ($options['p_videowhisper_support_accounts']) 
		{
		echo '<BR><a href="' . esc_url( get_permalink( $options['p_videowhisper_support_accounts'] ) ) . '?editAccount=' . esc_attr( $row['id'] ) . '">Front Edit</a>';
		}

        echo '</td>';
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';

 // Output pagination links
 echo '<div class="tablenav">';
 echo '<div class="tablenav-pages">';
 //translators: %s is the number of items
 echo '<span class="displaying-num">' . sprintf(_n('%s item', '%s items', $total, 'live-support-tickets'), esc_html($total)) . '</span>';
 echo '<span class="pagination-links">';
 echo paginate_links(array(
	 'base' => add_query_arg('paged', '%#%'),
	 'format' => '',
	 'prev_text' => __('&laquo;', 'live-support-tickets'),
	 'next_text' => __('&raquo;', 'live-support-tickets'),
	 'total' => $pages,
	 'current' => $page
 ));
 echo '</span>';
 echo '</div>';
 echo '</div>';

 // Close the database connection
 $mysqli->close();

}

static function adminCans()
{
	$options = self::getOptions();

	global $wpdb;
	$table_cans = $wpdb->prefix . 'vws_cans';
	$table_contacts = $wpdb->prefix . 'vws_contacts';

	?>
		<div class="wrap">
		<h3><span class="dashicons dashicons-admin-comments"></span> Canned Responses / Contact Forms, Support, CRM / VideoWhisper</h3>
		</div>
	<?php

	//display a warning if table does not exist
	if (!$wpdb->get_var("SHOW TABLES LIKE '$table_cans'") == $table_cans)
	{
		echo '<div class="error"><p>Database table not found: ' . esc_html($table_cans) . '</p></div>';
		return;
	} 

	$edit = intval( $_GET['edit'] ?? 0 );

	if ($edit) {
		
		$nonce = $_REQUEST['_wpnonce'];
		if ( ! wp_verify_nonce( $nonce, 'vwsec' ) )
		{
			echo 'Invalid nonce!';
			exit;
		}

		$query             = "SELECT * FROM `$table_cans` WHERE id = '$edit'";
		$r         = $wpdb->get_row( $query );

		if ($r)
		{
			echo '<form action="' . wp_nonce_url( 'admin.php?page=vw-support-cans', 'vwsec') . '" method="post">';
			echo '<table class="form-table">';
			echo '<tr><th scope="row">Title</th><td><input size="64" name="title" value="' . esc_attr($r->title) . '"/></td></tr>';
			echo '<tr><th scope="row">Message</th><td><textarea cols=100 rows=10 name="message" rows="5" cols="50">' . esc_textarea($r->message) . '</textarea></td></tr>';
			echo '<tr><th scope="row" colspan="2"><input class="button" type="submit" name="submit" id="submit" value="Update"/><input type="hidden" name="save" value="' . intval($r->id) . '"/></th></tr>';
			echo '</table>';
			echo '</form>';
		}
		else
		{
			echo 'Not found!';
		}
	}

		$save = intval( $_POST['save'] ?? 0 );
		if ($save)
		{
			$title = sanitize_text_field( $_POST['title'] ?? '' );
			$message = sanitize_textarea_field( $_POST['message'] ?? '' );

			$sqlU = "UPDATE `$table_cans` SET `title` = '$title', `message` = '$message' WHERE `id` = '$save'";
			$wpdb->query( $sqlU );

			echo '<p>Updated!</p>';
		}


		$delete = intval( $_GET['delete'] ?? 0 );
		if ($delete)
		{
			$nonce = $_REQUEST['_wpnonce'];	
			if ( ! wp_verify_nonce( $nonce, 'vwsec' ) )
			{
				echo 'Invalid nonce!';
				exit;
			}

			$sqlD = "DELETE FROM `$table_cans` WHERE `id` = '$delete'";
			$wpdb->query( $sqlD );
		}

		//list 
		$paginationCode = '';
		$query = "SELECT * FROM `$table_cans` ORDER BY `id` DESC";
		$total_query = "SELECT COUNT(1) FROM ({$query}) AS combined_table";
		$total = $wpdb->get_var( $total_query );
		$items_per_page = 20;
		$page = isset( $_GET['cpage'] ) ? abs( (int) $_GET['cpage'] ) : 1;
		$offset = ( $page * $items_per_page ) - $items_per_page;

		$results = $wpdb->get_results( "{$query} LIMIT {$offset}, {$items_per_page}" );
		$totalPages = ceil($total / $items_per_page);

		//display error if any
		if ( $wpdb->last_error ) echo '<div class="error"><p>' . esc_html($wpdb->last_error) . '</p></div>';

		//display query if no results
		if ( ! $results ) echo '<p>No results!</p>';

		//display table 
		echo '<table class="wp-list-table widefat fixed striped posts">';
		echo '<thead><tr><th>ID</th><th>Title</th><th>Message</th><th>Contact</th><th>Actions</th></tr></thead>';
		echo '<tbody id="the-list">';
		if ( $results )
		foreach ( $results as $r )
		{
			echo '<tr>';
			echo '<td>' . intval($r->id) . '</td>';
			echo '<td>' . esc_html($r->title) . '</td>';
			echo '<td><small>' . esc_html($r->message) . '</small></td>';
			$contact = $wpdb->get_var( "SELECT `name` FROM `$table_contacts` WHERE `id` = '$r->cid'" );
			echo '<td>' . esc_html($contact) . '</td>';
			echo '<td><a href="' . wp_nonce_url( 'admin.php?page=vw-support-cans&edit=' . intval($r->id), 'vwsec') . '">Edit</a> | <a href="' . wp_nonce_url( 'admin.php?page=vw-support-cans&delete=' . intval($r->id), 'vwsec') . '">Delete</a></td>';
			echo '</tr>';
		} else {
			echo '<tr><td colspan="5">No canned responses found.</td></tr>';
		};
		echo '</tbody>';
		echo '</table>';

		//pagination
		echo '<div class="tablenav"><div class="tablenav-pages"><span class="displaying-num">' . esc_html( $total ) . ' items</span>';
		echo paginate_links( array(
			'base' => add_query_arg( 'cpage', '%#%' ),
			'format' => '',
			'prev_text' => __( '&laquo;', 'live-support-tickets' ),
			'next_text' => __( '&raquo;', 'live-support-tickets' ),
			'total' => $totalPages,
			'current' => $page
		));
		echo '</div></div>';
}

static function adminContacts()
{
	$options = self::getOptions();

	global $wpdb;
	$table_contacts = $wpdb->prefix . 'vws_contacts';
	$table_ticket_contacts = $wpdb->prefix . 'vws_ticket_contacts';

	?>
		<div class="wrap">
		<h3><span class="dashicons dashicons-admin-users"></span> Contacts / Contact Forms, Support, CRM / VideoWhisper</h3>
		</div>
	<?php


		$edit = intval( $_GET['edit'] ?? 0 );
		if ($edit) {

			$nonce = $_REQUEST['_wpnonce'];
			if ( ! wp_verify_nonce( $nonce, 'vwsec' ) )
			{
				echo 'Invalid nonce!';
				exit;
			}

			$query             = "SELECT * FROM `$table_contacts` WHERE id = '$edit'";
			$r         = $wpdb->get_row( $query );

		if ($r)
		{
		echo '<form action="' . wp_nonce_url( 'admin.php?page=vw-support-contacts', 'vwsec') . '" method="post">';
		echo '<input size="30" name="name" value="' . esc_attr($r->name) . '"/>';
		echo '<input size="30" name="contact" value="' . esc_attr($r->contact) . '"/>';
		echo '<input name="save" type="hidden" value="' . intval($r->id) . '"/>';
		echo '<p><input class="button" type="submit" name="submit" id="submit" value="Save"/></p></form>';

		echo 'ID: #' . esc_html($r->id) . '</br>';
		echo 'UID: #' . esc_html($r->uid) . '</br>';
		echo 'Created: ' . esc_html(date('Y-m-d H:i:s', $r->created)) . '</br>';
		echo 'Confirmed: ' . ( $r->confirmed ? esc_html(date('Y-m-d H:i:s', $r->confirmed)) : '-' ) . '</br>';
		echo 'PIN: ' . esc_html($r->pin) . '</br>';

		echo '</br>Meta:<pre>';
		$meta = unserialize($r->meta);
	//	var_dump($meta);
		echo '</pre>';
		}
		else
		{
			echo 'Contact not found!';
		}

		return;
		}

		$save = intval( $_POST['save'] ?? 0 );
		if ($save)
		{
			$nonce = $_REQUEST['_wpnonce'];
			if ( ! wp_verify_nonce( $nonce, 'vwsec' ) )
			{
				echo 'Invalid nonce!';
				exit;
			}

			$name = sanitize_text_field( $_POST['name'] );
			$contact = sanitize_text_field( $_POST['contact'] );
			$query             = "UPDATE `$table_contacts` SET `name`='$name', `contact`='$contact' WHERE id = '$save'";
			$wpdb->query( $query );

			echo '<p>Contact was updated.</p>';
		}

		//delete contact
		$delete = intval( $_GET['delete'] ?? 0 );
		$confirm = intval( $_GET['confirm'] ?? 0 );

		if ($delete && !$confirm)
		{
			$nonce = $_REQUEST['_wpnonce'];
			if ( ! wp_verify_nonce( $nonce, 'vwsec' ) )
			{
				echo 'Invalid nonce for deletion confirmation!';
				exit;
			}

			//get contact name
			$sqlS = "SELECT * FROM `$table_contacts` WHERE id = '$delete' LIMIT 1";
			$contact = $wpdb->get_row( $sqlS );

			//count tickets with this contact
			$tickets = $wpdb->get_var( "SELECT COUNT(*) FROM $table_ticket_contacts WHERE cid = '$delete'" );
			
			//ask for confirmation before deletion and use an extra confirm parameter to complete
			echo '<p>Are you sure you want to delete contact #' . esc_html($delete) . ' (' . esc_html($contact->name) . '/' . esc_html($contact->contact) . ')?</p>';
			echo '<p>Associated tickets: ' . ( $tickets ? esc_html($tickets) : 'None' ) . '</p>'; 

			echo '<a class="button secondary" href="' . wp_nonce_url( 'admin.php?page=vw-support-contacts&delete=' . esc_attr($delete) . '&confirm=' . esc_attr($delete), 'vwsec') . '">Yes, Delete</a>';

			//no
			echo ' | <a class="button secondary" href="' . wp_nonce_url( 'admin.php?page=vw-support-contacts', 'vwsec') . '">No, Keep</a> <BR>';
		}

		if ($delete && $confirm)
		{
			$nonce = $_REQUEST['_wpnonce'];
			if ( ! wp_verify_nonce( $nonce, 'vwsec' ) )
			{
				echo 'Invalid nonce for deletion!';
				exit;
			}

			$query             = "DELETE FROM `$table_contacts` WHERE id = '$delete'";
			$wpdb->query( $query );

			//delete from ticket contacts
			$query             = "DELETE FROM `$table_ticket_contacts` WHERE cid = '$delete'";
			$wpdb->query( $query );
			
			echo '<p>Contact was deleted: #' . esc_html( $delete ) . '</p>';
		}

		//notify contact (again)
		$notify = intval( $_GET['notify'] ?? 0 );

		if ($notify)
		{
			$nonce = $_REQUEST['_wpnonce'];
			if ( ! wp_verify_nonce( $nonce, 'vwsec' ) )
			{
				echo 'Invalid nonce!';
				exit;
			}

				echo '<br>Notification for contact #' . esc_html( $notify ) . '';

				//check if contact exists 
				$sqlS = "SELECT * FROM `$table_contacts` WHERE id = '$notify' LIMIT 1";
				$contact = $wpdb->get_row( $sqlS );
	
				if ($contact)
				{
					echo '<br>Name:<br>' . esc_html( $contact->name ) . '<br>Contact:<br>' . esc_html( $contact->contact ) . '<br>';

					//display meta
					$meta = unserialize($contact->meta);
					if (is_array($meta)) {
						echo '<br>Meta:<br>';
						echo wp_json_encode($meta);
					}

					//notify existing contact
					$args = [ 'contact' => $contact->id, 'confirm' => $contact->pin ];
	
					//retrieve form from contact meta
					$meta = [];
					if ($contact->meta) $meta = unserialize($contact->meta);
					if (is_array($meta)) if (array_key_exists('forms', $meta)) $args['form'] = urlencode( array_key_first($meta['forms']) );
					
					$url = add_query_arg( $args , $options['appURL'] ) . '#vws';

					$subject = $options['notifyConfirmSubject'];
					$messageNotify = $options['notifyConfirmMessage'] . " \n" . $url;
					$replace = [ '{code}' => $args['confirm'] ];
					$subject = strtr($subject, $replace);
					$messageNotify = strtr($messageNotify, $replace);
					$notifyError = self::notifyContact($contact->id, $subject, $messageNotify, $options);
	
					echo ( $notifyError ? ' Notify Error:<br>' . esc_html($notifyError) : '<br>Notification email was re-sent.') ;

					echo '<br>Notification Message:<br>' . esc_html($messageNotify);
				} else {
					echo '<br>Contact not found to notify!';
				}
				
		}

//List

// Get the base admin URL for the form action.
$form_action_url = admin_url('admin.php');

// Start the form, specifying the base URL. The 'page' parameter and others will be included as hidden inputs.
echo '<form method="get" action="' . esc_url($form_action_url) . '">';

// Include hidden fields for retaining necessary parameters and nonce for security.
echo '<input type="hidden" name="page" value="vw-support-contacts">';
wp_nonce_field('vwsec_filter', 'vwsec_nonce');

//count total, confirmed, unconfirmed (where confirmed = 0) contacts from $table_contacts 
$total = $wpdb->get_var( "SELECT COUNT(*) FROM $table_contacts" );
$confirmed = $wpdb->get_var( "SELECT COUNT(*) FROM $table_contacts WHERE confirmed != 0" );
$unconfirmed = $wpdb->get_var( "SELECT COUNT(*) FROM $table_contacts WHERE confirmed = 0" );

// Include search and status fields as before.
echo '<input size="48" type="text" name="search" placeholder="Search by name or contact..." value="' . esc_attr($_GET['search'] ?? '') . '">
<select name="status">
    <option value="all"' . (($_GET['status'] ?? '') === 'all' ? ' selected' : '') . '>All (' . esc_html( $total ) .')</option>
    <option value="confirmed"' . (($_GET['status'] ?? '') === 'confirmed' ? ' selected' : '') . '>Confirmed (' . esc_html( $confirmed ) .')</option>
    <option value="unconfirmed"' . (($_GET['status'] ?? '') === 'unconfirmed' ? ' selected' : '') . '>Unconfirmed (' . esc_html( $unconfirmed ) .')</option>
</select>
<input name="filter" type="submit" value="Filter" class="button secondary">
</form>';


// Check if the form was submitted and verify the nonce.
if (isset($_GET['filter']) && check_admin_referer('vwsec_filter', 'vwsec_nonce')) {

    // Proceed with form processing since the nonce is valid.
    $search_term = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
    $status_filter = isset($_GET['status']) ? $_GET['status'] : '';

    // Dynamically construct query conditions based on form inputs.
    if (!empty($search_term)) {
        $search_termQ = '%' . $wpdb->esc_like($search_term) . '%';
		echo '<br>Search Term: ' . esc_html( $search_termQ );
		$query_conditions[] = "(`name` LIKE '$search_termQ' OR `contact` LIKE '$search_termQ')";
		var_dump($query_conditions);
    }

	if ($status_filter === 'confirmed' || $status_filter === 'unconfirmed') {
		if ($status_filter === 'confirmed') {
			// For confirmed status, set confirmed_value to 1 (assuming 1 indicates confirmed)
			$confirmed_value = 1;
		} else {
			// For unconfirmed status, set confirmed_value to 0
			$confirmed_value = 0;
		}
		$query_conditions[] = "`confirmed` = $confirmed_value";
	}
}


// Base query.
$query = "SELECT * FROM `$table_contacts`";

// Include conditions in the query if any.
if (!empty($query_conditions)) {
    $query .= ' WHERE ' . implode(' AND ', $query_conditions);
}


		$total_query     = "SELECT COUNT(1) FROM ({$query}) AS combined_table";
		$total             = $wpdb->get_var( $total_query );
		$items_per_page = 10;
		$page             = isset( $_GET['cpage'] ) ? abs( (int) $_GET['cpage'] ) : 1;
		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field($_GET['orderby']) : 'created';
		$offset         = ( $page * $items_per_page ) - $items_per_page;
		$results         = $wpdb->get_results( $query . " ORDER BY `$orderby` DESC LIMIT {$offset}, {$items_per_page}" );
		$totalPage         = ceil($total / $items_per_page);

if ( $wpdb->last_error ) echo '<div class="error"><p>' . esc_html($wpdb->last_error) . '</p></div>';

//display table
$paginationCode     = "";


echo '<table class="widefat fixed" cellspacing="0"><thead><tr>
<th class="manage-column" scope="col">Name</th>
<th class="manage-column" scope="col">Contact</th>
<th class="manage-column" scope="col"><a href="'. add_query_arg( ['orderby' => 'created' ] ) .'">Created</a></th>
<th class="manage-column" scope="col"><a href="'. add_query_arg( ['orderby' => 'lform' ] ) .'">Forms</a></th>
<th class="manage-column" scope="col"><a href="'. add_query_arg( ['orderby' => 'confirmed' ] ) .'">Confirmed</a></th>
<th class="manage-column" scope="col">Data</th>
</tr></thead>
<tbody>';

$table_ticket_contacts = $wpdb->prefix . 'vws_ticket_contacts';


foreach ($results as $r)
{
	$meta = [];
	$fields = '';
	$fieldsCode = '';

	$fieldWebsite = '';
	$created_location = '';


	if ($r->meta)
	{
		$meta = unserialize($r->meta);
		if (is_array($meta)) 
		{
			if (array_key_exists('accounts', $meta)) 
			{
				$accounts = $meta['accounts'];
				if (is_array($accounts)) foreach ($accounts as $key => $value) $fieldsCode .= ( $fieldsCode ?  '<br />' : '' ) . ' Account / ' . esc_html($key) . ': ' . esc_html($value) ;
			}

			if (array_key_exists('fields', $meta)) 
			{
				$fields = $meta['fields'];
				if (is_array($fields)) foreach ($fields as $key => $value) $fieldsCode .= ( $fieldsCode ?  '<br />' : '' ) .esc_html($key) . ': ' . esc_html($value) ;

				if (array_key_exists('Website', $fields)) $fieldWebsite = $fields['Website'];
			}

			if (array_key_exists('forms', $meta)) 
			{
				$forms = $meta['forms'];
				if (is_array($forms)) foreach ($forms as $key => $value) $fieldsCode .= ( $fieldsCode ?  '<br />' : '' ) . ' Form / ' . esc_html($key) . ': ' . date( 'Y M d, H:i', $value ) ; 
			}

			if (array_key_exists('created_location', $meta)) $created_location = $meta['created_location'];

		}
	}

	//get tickets count for contact
	$tickets = $wpdb->get_var( "SELECT COUNT(*) FROM $table_ticket_contacts WHERE cid = " . $r->id );

	echo '<tr class="alternate"><td><a href="' .  wp_nonce_url('admin.php?page=vw-support-contacts&edit=' . intval($r->id), 'vwsec' ) . '"><span class="dashicons dashicons-edit"></span>' . esc_html($r->name) . '</a><small><br>' . esc_html( $fieldWebsite ) .'<br>'. esc_html( $created_location ) .  '</small></td><td>' . esc_html($r->contact) . '<br><a href="admin.php?page=vw-support-conversations&filterContact=' . esc_attr( $r->id ). '&filterContactName=' . urlencode(esc_attr($r->name)) . '"><span class="dashicons dashicons-admin-comments"></span> ' . esc_html( $tickets) . '</a> <a title="New Conversation" href="' .  wp_nonce_url('admin.php?page=vw-support-conversation-new&email=' . urlencode(esc_attr($r->contact)) . '&name=' . urlencode(esc_attr($r->name)), 'vwsec' ) . '"><span class="dashicons dashicons-plus"></span></a> <a title="Accounts" href="' .  wp_nonce_url( 'admin.php?page=vw-support-accounts&cid=' . esc_attr($r->id), 'vwsec' ) . '"><span class="dashicons dashicons-lock"></span></a></td><td>' . esc_html( date( 'Y M d, H:i:s T', $r->created ) ) . '</td><td><small>' . ($r->lform ? esc_html( date( 'Y M d, H:i:s T', $r->lform ) ) : '-' ) . '</small> <a title="Form Invite" href="' .  wp_nonce_url('admin.php?page=vw-support-form-invite&email=' . urlencode(esc_attr($r->contact)) . '&name=' . urlencode(esc_attr($r->name)), 'vwsec' ) . '"><span class="dashicons dashicons-feedback"></span></a> <a title="Invite" href="' .  wp_nonce_url('admin.php?page=vw-support-invite&email=' . urlencode(esc_attr($r->contact)) . '&name=' . urlencode(esc_attr($r->name)), 'vwsec' ) . '"><span class="dashicons dashicons-email"></span></a> </td><td><small>' . ( $r->confirmed ? esc_html( date( 'Y M d, H:i:s T',$r->confirmed )) : '-' ) . '</small><a title="Delete" href="' .  wp_nonce_url('admin.php?page=vw-support-contacts&delete=' . intval($r->id), 'vwsec' ) . '"><span class="dashicons dashicons-trash"></span></a>' . ( $r->confirmed  ?  '' : '<a href="' .  wp_nonce_url('admin.php?page=vw-support-contacts&notify=' . intval($r->id), 'vwsec' ) . '"><span class="dashicons dashicons-email-alt2"></span></a>') . '</td><td><small>' . wp_kses_post( $fieldsCode ) . '</small></td></tr>';
}

echo '<tbody></table>';

//display pagination
		$removeParams = [ 'delete', 'edit', 'notify', 'invite', 'form', '_wpnonce' ];
		$currentURL = remove_query_arg( $removeParams );

		if($totalPage > 1){
			echo  '<div><span>Page '. esc_html( $page ).' of ' . esc_html( $totalPage ) . ' </span> | ';
			echo paginate_links( array(
			'base' =>  add_query_arg( ['cpage' => '%#%', 'orderby' => $orderby ], 	$currentURL ),
			'format' => '',
			'prev_text' => __('&laquo;', 'live-support-tickets'),
			'next_text' => __('&raquo;', 'live-support-tickets'),
			'total' => $totalPage,
			'current' => $page
		)).'</div>';
		}



		echo 'URL: ' . esc_html( $currentURL );
		echo '<br>Query: ' . esc_html( $query );


}



static function adminConversations()
{
		$options = self::getOptions();

		//create a local contact for current user
		$current_user = wp_get_current_user();
		$uid = intval($current_user->ID);

				global $wpdb;
				$table_contacts = $wpdb->prefix . 'vws_contacts';

				$sqlS = "SELECT * FROM `$table_contacts` WHERE uid = '$uid' LIMIT 1";
				$contact = $wpdb->get_row( $sqlS );

				if (!$contact)
				{
					$name = $current_user->display_name;
					$email = $current_user->user_email;
					$type = 'email';
					$pin = self::pinGenerate();

					$meta = [];
					$meta['created_ip'] = self::get_ip_address();
					$meta['created_by'] = 'admin';
					$meta['representative'] = 1;
					$meta['admin'] = 1;
					$metaS = serialize($meta);

					$sqlI = "INSERT INTO `$table_contacts` ( `uid`, `name`, `contact`, `type`, `pin`, `created`, `confirmed`, `meta` ) VALUES ( '$uid', '" . $name . "', '" . $email . "', '" . $type . "', '" . $pin . "', '" . time() . "', '" . time() . "', '$metaS' )";
					$wpdb->query( $sqlI );
					$contact_id = $wpdb->insert_id;

					if (!$contact_id)  echo esc_html('SQL error: ' . $wpdb->last_error . ' / ' . $sqlI);
					else $contact = $wpdb->get_row( $sqlS );


					}

					//make representative if previously created (ie by frontend support page)
					$meta = unserialize($contact->meta);
					if ( !is_array($meta) ) $meta = [];

					if (! array_key_exists('representative', $meta) || !$meta['representative'] )
					{
					$meta['representative'] = 1;
					$meta['admin'] = 1;
					$metaS = serialize($meta);

					//update meta
					$sqlU = "UPDATE `$table_contacts` SET meta='$metaS' WHERE id='" . $contact->id . "'";
					$wpdb->query( $sqlU );
					}

		?>
	<div class="wrap">
	<h3><span class="dashicons dashicons-format-chat"></span>Support Conversations / VideoWhisper Live Support Tickets</h3>
	</div>
<?php

	echo do_shortcode( '[videowhisper_support params="contact: ' . intval( $contact->id) . ', rep: \'' . esc_attr( $contact->pin ) . '\', list: 1"]');
	?>
	<style>
		.icon.arrow, .icon.arrow::after
		{
			position: inherit !important;
			bottom: inherit !important;
			left: inherit !important;
		}
	</style>
	<?php
}

// ! Feature Pages and Menus

static function setupPagesList( $options = null )
{

	if ( ! $options )
	{
		$options = self::getOptions();
	}

	$pages = array(
		'videowhisper_support'            => __( 'Support', 'live-support-tickets' ),
		'videowhisper_support_conversations'            => __( 'My Conversations', 'live-support-tickets' ),
		'videowhisper_support_terms'            => __( 'Support Terms', 'live-support-tickets' ),
	);

	if ($options['micropayments']) $pages['videowhisper_support_micropayments'] =  __( 'My Fees', 'live-support-tickets' );
	if ($options['accounts_db']) $pages['videowhisper_support_accounts'] =  __( 'My Accounts', 'live-support-tickets' );

	// shortcode pages
	return $pages;
}


static function setupPagesContent( $options = null )
{
	if ( ! $options )
	{
		$options = self::getOptions();
	}

	return array(
		'videowhisper_support_terms' => '<h2>Support Terms</h2>
		<p>Using this support system is subject to all website terms and policies, including Terms of Use, Privacy Policy.</p>',
	);
}


static function setupPages()
{
	//ini_set( 'display_errors', 1 );

	$options = self::getOptions();
	if ( $options['disableSetupPages'] )
	{
		return;
	}

	$pages = self::setupPagesList();

	$noMenu = array( 'videowhisper_support_terms' => 1);
	$parents = array(
		'videowhisper_support'     => array( 'Client', 'Client Dashboard', 'Member' ),
		'videowhisper_support_conversations'    => array( 'Support', 'Client', 'Client Dashboard', 'Member'),
		'videowhisper_support_terms'    => array( 'Support', 'Client', 'Client Dashboard', 'Member'),
		'videowhisper_support_micropayments' => array( 'Creator', 'Performer', 'Performer Dashboard', 'Support'),
		'videowhisper_support_accounts' => array( 'Client', 'Support'),
	);


	// custom content (not shortcode)
	$content = self::setupPagesContent();

	$duplicate = array();

	// create a menu and add pages
	$menu_name   = 'VideoWhisper';
	$menu_exists = wp_get_nav_menu_object( $menu_name );

	if ( ! $menu_exists )
	{
		$menu_id = wp_create_nav_menu( $menu_name );
	} else
	{
		$menu_id = $menu_exists->term_id;
	}
	$menuItems = array();

	// create pages if not created or existant
	foreach ( $pages as $key => $value )
	{
		$pid  = $options[ 'p_' . $key ] ?? 0;
		$page = get_post( $pid );
		if ( ! $page )
		{
			$pid = 0;
		}

		if ( ! $pid )
		{
			global $user_ID;
			$page                   = array();
			$page['post_type']      = 'page';
			$page['post_parent']    = 0;
			$page['post_status']    = 'publish';
			$page['post_title']     = $value;
			$page['comment_status'] = 'closed';

			if ( array_key_exists( $key, $content ) )
			{
				$page['post_content'] = $content[ $key ]; // custom content
			} else
			{
				$page['post_content'] = '[' . $key . ']';
			}

			$pid = wp_insert_post( $page );

			$options[ 'p_' . $key ] = $pid;
			$link                   = get_permalink( $pid );

			// get updated menu
			if ( $menu_id )
			{
				$menuItems = wp_get_nav_menu_items( $menu_id, array( 'output' => ARRAY_A ) );
			}

			// find if menu exists, to update
			$foundID = 0;
			foreach ( $menuItems as $menuitem )
			{
				if ( $menuitem->title == $value )
				{
					$foundID = $menuitem->ID;
					break;
				}
			}

			if ( ! in_array( $key, $noMenu ) )
			{
				if ( $menu_id )
				{
					// select menu parent
					$parentID = 0;
					if ( array_key_exists( $key, $parents ) )
					{
						foreach ( $parents[ $key ] as $parent )
						{
							foreach ( $menuItems as $menuitem )
							{
								if ( $menuitem->title == $parent )
								{
									$parentID = $menuitem->ID;
									break 2;
								}
							}
						}
					}

					// update menu for page
					$updateID = wp_update_nav_menu_item(
						$menu_id,
						$foundID,
						array(
							'menu-item-title'     => $value,
							'menu-item-url'       => $link,
							'menu-item-status'    => 'publish',
							'menu-item-object-id' => $pid,
							'menu-item-object'    => 'page',
							'menu-item-type'      => 'post_type',
							'menu-item-parent-id' => $parentID,
						)
					);

					// duplicate menu, only first time for main menu
					if ( ! $foundID )
					{
						if ( ! $parentID )
						{
							if ( intval( $updateID ) )
							{
								if ( in_array( $key, $duplicate ) )
								{
									wp_update_nav_menu_item(
										$menu_id,
										0,
										array(
											'menu-item-title'  => $value,
											'menu-item-url'    => $link,
											'menu-item-status' => 'publish',
											'menu-item-object-id' => $pid,
											'menu-item-object' => 'page',
											'menu-item-type'   => 'post_type',
											'menu-item-parent-id' => $updateID,
										)
									);
								}
							}
						}
					}
				}
			}
		}
	}

	update_option( 'VWsupportOptions', $options );
}


static function adminDocumentation()
{
	?>
	<div class="wrap">
	<h3><span class="dashicons dashicons-editor-help"></span> Documentation / VideoWhisper Live Support Tickets</h3>
	</div>

	<h3>Shortcodes</h3>

	<h4>[videowhisper_support params="" inline=""]</h4>
	Displays contact form. Pass parameters like params="creator: id_number, content: id_number" to target specific user id or content id (post id). Providing only content id targets site support (Report Content) and providing creator opens conversations for that user (Contact User, Contact Content Owner). Departments can include custom fields/questions that can be filled on each new conversation. Departments available for each scenario can be configured from <a href="admin.php?page=vw-support&tab=departments">settings</a>.
	<br>Forms can be setup with params="form: 'Form Name'". Forms and form fields can be configured from <a href="admin.php?page=vw-support&tab=forms">settings</a>. A form will associate the field values to the contact and will enable contact to edit form later.
	<br>Set inline="1" to load contact form with a toggle button on same page (without navigating to a different support page)

	<h4>[videowhisper_support_conversations]</h4> 
	Displays conversations for logged in user.

	<h4>[videowhisper_support_buttons]</h4>
	Displays floating support buttons. 

	<h4>[videowhisper_support_micropayments]</h4>
	Use shortcode to display a form that allows selected user types to set custom prices for conversations and messages, that apply for creator conversations.

	<h3>Full Page Template</h3>
	If enabled, a full page template is available with vwtemplate=support parameter, which will load the template-support.php template from plugin folder. By default this is disabled from Integration tab in plugin settings.
	
	<h3>Google Analytics</h3>
	The application will trigger gtag events (if enabled on site, in example with Google SiteKit). 
	<br>'event_category': 'VideoWhisper Support'
	<br>'event_label': 'New Contact', 'Contact Confirmed', 'New Conversation', 'New Message'
	<?php

}

	// ! Options
	static function adminOptionsDefault()
	{
		$upload_dir = wp_upload_dir();
		$root_url    = plugins_url();
		$root_ajax   = admin_url( 'admin-ajax.php?action=vmls&task=' );

		return array(

			'gtagContactView' => '',
			'gtagContactNew' => '',
			'gtagContactConfirm' => '',
			'gtagMessage' => '',
			'gtagConversation' => '',
			'gtagConversationView' => '',

			'accountsRTMP' =>'', //
			'accountsHLS' => '',
			'accountsWebRTC' => '',

			'demoMode' => 1, // by default demo mode enabled

			'registerConversation' => 0,

			'emailsUnsupported' => 'outlook.com, hotmail.com, live.com, msn.com, mailinator.com, 10minutemail.com, 33mail.com, yopmail.com, sharklasers.com, guerillamail.com, getnada.com, temp-mail.org, tempmailaddress.com, maildrop.cc, mailinator2.com, mailinator.net, mailinator.org, mailinator.us, mailinator.co.uk, mailinator.co, mailinator.info, mailinator.biz',

			'fullpageTemplate' =>0,

			'customCSS' => '
			.dropdown.ui{
				overflow: visible;
			}',
			'multilanguage' => 1,
			'deepLkey' => '',
			'translations' => 'all',
			'languageDefault' => 'en-us',

			'logPath' => WP_CONTENT_DIR . '/vwSupportDebug_' . self::pinGenerate(), //randomize path
		
			'logLevel'	=> 1, //0: none, 1: error, 2: warning, 3: notice, 4: info, 5: debug
			'logDays'	=> 7, //days to keep log files

			'accountsAPI' => '', //use accounts API for user management
			'accountsAPIkey' => '', //apikey for accounts API

			'apiSSL'	=> 1, //enforce SSL for API calls
			'accountsLimit' => 3, //max accounts per user (0: unlimited)
			'accounts_host'           => 'localhost',
			'accounts_port'           => '3306',
			'accounts_user'           => '',
			'accounts_pass'           => '',
			'accounts_db'             => '',
			'termsPage' => 0,
			'forms' => unserialize( 'a:1:{s:3:"Web";a:2:{s:6:"fields";s:17:"\Website|Twitter\";s:12:"instructions";s:54:"\You can include information about your web presence.\";}}' ) ,
			'formsConfig'             => '
			[Web]
			fields="Website|Twitter"
			instructions="You can include information about your web presence."',

			'fields'                   => unserialize( 'a:9:{s:19:"How Did You Find Us";a:4:{s:4:"type";s:6:"select";s:11:"placeholder";s:28:"\How did you hear about us?\";s:7:"options";s:52:"\Google|WordPress|Social Media|Other Customer|Other\";s:12:"instructions";s:49:"\Tell us where you are coming from, for context.\";}s:16:"Already Customer";a:2:{s:4:"type";s:6:"toggle";s:12:"instructions";s:31:"\Did you order from us before?\";}s:13:"Support Issue";a:4:{s:4:"type";s:6:"select";s:11:"placeholder";s:35:"\What do you need assistance with?\";s:7:"options";s:63:"\Using Website|Account Credentials|Paid Services|Billing|Other\";s:12:"instructions";s:43:"\Tell us what do you need assistance with.\";}s:13:"Content Issue";a:4:{s:4:"type";s:6:"select";s:7:"options";s:66:"\Incorrect Content|Offensive Content|Copyright Infringement|Other\";s:11:"placeholder";s:21:"Type of Content Issue";s:12:"instructions";s:56:"\Specify what type of content issue you want to report.\";}s:15:"Technical Issue";a:4:{s:4:"type";s:6:"select";s:7:"options";s:42:"\Error Message|Unexpected Behaviour|Other\";s:11:"placeholder";s:23:"Type of Technical Issue";s:12:"instructions";s:58:"\Specify what type of technical issue you want to report.\";}s:3:"URL";a:3:{s:4:"type";s:4:"text";s:11:"placeholder";s:28:"https://thiswebsite.com/page";s:12:"instructions";s:53:"\Specify exact or sample URL your inquiry refers to.\";}s:17:"Technical Details";a:3:{s:4:"type";s:8:"textarea";s:11:"placeholder";s:17:"Technical Details";s:12:"instructions";s:93:"\Describe the technical issue, including steps to replicate and exact error message, if any.\";}s:7:"Website";a:3:{s:4:"type";s:4:"text";s:11:"placeholder";s:21:"https://mywebsite.com";s:12:"instructions";s:30:"\Specify URL of your website.\";}s:7:"Twitter";a:3:{s:4:"type";s:4:"text";s:11:"placeholder";s:14:"\videowhisper\";s:12:"instructions";s:41:"\Specify your Twitter username (handle).\";}}' ),
			'fieldsConfig'             => '
			[How Did You Find Us]
			type=select
			placeholder="How did you hear about us?"
			options="Google|WordPress|Social Media|Other Customer|Other"
			instructions="Tell us where you are coming from, for context."

			[Already Customer]
			type=toggle
			instructions="Did you order from us before?"

			[Order Email]
			type=text
			placeholder=youraccount@emailprovider.com
			instructions="Specify order email if you already have an order."

			[Support Issue]
			type=select
			placeholder="What do you need assistance with?"
			options="Using Website|Account Credentials|Paid Services|Billing|Other"
			instructions="Tell us what do you need assistance with."

			[Content Issue]
			type=select
			options="Incorrect Content|Offensive Content|Copyright Infringement|Other"
			placeholder=Type of Content Issue
			instructions="Specify what type of content issue you want to report."

			[Technical Issue]
			type=select
			options="Error Message|Unexpected Behaviour|Other"
			placeholder=Type of Technical Issue
			instructions="Specify what type of technical issue you want to report."

			[URL]
			type=text
			placeholder=https://thiswebsite.com/page
			instructions="Specify exact or sample URL your inquiry refers to."

			[Technical Details]
			type=textarea
			placeholder=Technical Details
			instructions="Describe the technical issue, including steps to replicate and exact error message, if any."

			[Website]
			type=text
			placeholder=https://mywebsite.com
			instructions="Specify URL of your website."

			[Twitter]
			type=text
			placeholder="videowhisper"
			instructions="Specify your Twitter username (handle)."
						',

			'redirectUser' => 0,
			'registerUser' => 0,
			'micropaymentsShare' =>1, 
			'micropayments' => 0,
			'micropaymentsRoles' => 'administrator, editor, author, contributor, performer, creator, studio',
			'micropaymentsConversation' => 5,
			'micropaymentsMessage' => 0.20,
			'micropaymentsConversationMin' => 1,
			'micropaymentsMessageMin' => 0.10,
			'micropaymentsConversationMax' => 20,
			'micropaymentsMessageMax' => 1,
			'micropaymentsRatio' => 0.80,
			'interfaceClass' => '',
			'uploadsPath'                     => $upload_dir['basedir'] . '/vwSupport',
			'buttons' => 1,
			'rolesCreator' => 'administrator, editor, author, contributor, performer, creator, studio',
			'buttonsPosition' => 'after',
			'postTypesContent' => 'post, video, picture, download, webcam, channel',
			'postTypesAuthor' => 'post, video, picture, download, webcam, channel',
			'corsACLO'                        => '',
			'appURL'						  => '',
			'devMode'						  => 0,
			'disableSetupPages' => 0,
			'notifyCooldownContact' => 300,
			'notifyEmail' => 1,
			'notifyAddSubject' => 'Hi {name}, You have been invited to a conversation',
			'notifyAddMessage' => 'Hello {name}, 
			You have been invited to a conversation. Access this URL to access and participate:',
			'notifyAccountSubject' => 'Hi {name}, Your New Account is Ready:',
			'notifyAccountMessage' => 'Hello {name}, 
			Your new account token:',
			'notifyConfirmSubject' => 'Hi {name}, Confirm Contact To Continue',
			'notifyConfirmMessage' => 'Hello {name}, 
			Your confirmation code:
			{code}

			Access this URL to confirm contact and start conversation:',
			'notifyTicketSubject' => 'Hey {name}, You Received A New Message',
			'notifyTicketMessage' => 'Hello {name}, 
			Access {department} conversation and message from {sender} at this URL:',
			'notifyFormInviteSubject'=> 'Hey {name}, Provide Details To Continue',
			'notifyFormInviteMessage'=> 'Hello {name}, 
			Please fill out this form, to continue:',
			'notifyInviteSubject'=> 'Hey {name}, If You Are Still Interested',
			'notifyInviteMessage'=> 'Hello {name}, 
			If you are still interested, get a free consultation at this URL:',
			'notifyMessageFooter'=> 'Use URL above to access. This is an automated message. Direct emails and email replies are not monitored or processed by this system. Send your messages and provide information exclusively using the online forms. If you need to contact us, please use the form on the contact page.',

			'departments' => unserialize( 'a:6:{i:1;a:3:{s:4:"text";s:7:"\Sales\";s:4:"type";s:1:"0";s:6:"fields";s:38:"\How Did You Find Us|Already Customer\";}i:2;a:3:{s:4:"text";s:9:"\Support\";s:4:"type";s:1:"0";s:6:"fields";s:15:"\Support Issue\";}i:3;a:3:{s:4:"text";s:11:"\Technical\";s:4:"type";s:1:"0";s:6:"fields";s:39:"\Technical Issue|URL|Technical Details\";}i:4;a:3:{s:4:"text";s:16:"\Report Content\";s:4:"type";s:1:"1";s:6:"fields";s:15:"\Content Issue\";}i:5;a:2:{s:4:"text";s:15:"\Contact Owner\";s:4:"type";s:1:"2";}i:6;a:2:{s:4:"text";s:14:"\Contact User\";s:4:"type";s:1:"3";}}' ),
			'departmentsConfig'                => '
			[1]
			text="Sales"
			type=0 ; 0 = site
			fields="How Did You Find Us|Already Customer"
			instructions = "Contact Sales first, if you are interested in our products and services. "
			
			[2]
			text="Support"
			type=0
			fields="Order Email|Support Issue"
			instructions = "After placing an order, use this to receive support."
			
			[3]
			text="Technical"
			type=0
			fields="Order Email|Technical Issue|URL|Technical Details"
			instructions ="Contact to report technical issues, after ordering."
			
			[4]
			text="Other"
			type=0 ; 0 = site
			fields="URL|How Did You Find Us"
			instructions = "Contact for other issues, including offering your services or reporting content issues."
			
			
			[101]
			text="Report Content"
			type=1 ; 1 = content site
			instructions = "Report this content."
			
			[102]
			text="Contact Owner"
			type=2 ; 2 = content owner
			instructions = "Contact the creator of this content."
			
			[103]
			text="Contact User"
			type=3; 3 = user
			instructions = "Contact site user."
',
		);

	}

	static function getOptions()
	{
		$options = get_option( 'VWsupportOptions' );

		if ( ! $options )
		{
			$options = self::adminOptionsDefault();
		}

		return $options;
	}

static function getAdminOptions()
{

	//latest options list
	$adminOptions = self::adminOptionsDefault();

	//local options
	$options = get_option( 'VWsupportOptions' );
	if ( !empty( $options ) )
	{
		foreach ( $options as $key => $option )
		{
			$adminOptions[ $key ] = $option;
		}
	}

	//save updated list, with local options
	update_option( 'VWsupportOptions', $adminOptions );

	return $adminOptions;
}



static function adminOptions()
{
	$options = self::getAdminOptions(); //update options list

	if ( isset( $_POST ) )	if ( ! empty( $_POST ) )
		{

			$nonce = $_REQUEST['_wpnonce'];
			if ( ! wp_verify_nonce( $nonce, 'vwsec' ) )
			{
				echo 'Invalid nonce!';
				exit;
			}

			$updateOptions = false;

			foreach ( $options as $key => $value )
			{
				if ( isset( $_POST[ $key ] ) )
				{
					$options[ $key ] = trim( sanitize_textarea_field( $_POST[ $key ] ) );
					$updateOptions = true;
				}
			}

			//config options
			foreach (['departments', 'fields', 'forms' ] as $option)
			if ( isset( $_POST[ $option . 'Config' ] ) )
			{
				$options[$option] = parse_ini_string( sanitize_textarea_field( $_POST[ $option . 'Config' ] ), true );
				$updateOptions = true;
			}

			//save new options
			if ($updateOptions) update_option( 'VWsupportOptions', $options );
		}

		$optionsDefault = self::adminOptionsDefault();

		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'server';
?>
		<div class="wrap">
		<h3><span class="dashicons dashicons-admin-settings"></span> Settings / Contact Support CRM / VideoWhisper</h3>
		</div>

<nav class="nav-tab-wrapper wp-clearfix">
	<a href="admin.php?page=vw-support&tab=server" class="nav-tab <?php echo $active_tab == 'server' ? 'nav-tab-active' : ''; ?>">Server</a>
	<a href="admin.php?page=vw-support&tab=pages" class="nav-tab <?php echo $active_tab == 'pages' ? 'nav-tab-active' : ''; ?>">Pages</a>
	<a href="admin.php?page=vw-support&tab=integration" class="nav-tab <?php echo $active_tab == 'integration' ? 'nav-tab-active' : ''; ?>">Integration</a>
	<a href="admin.php?page=vw-support&tab=notifications" class="nav-tab <?php echo $active_tab == 'notifications' ? 'nav-tab-active' : ''; ?>">Notifications</a>
	<a href="admin.php?page=vw-support&tab=departments" class="nav-tab <?php echo $active_tab == 'departments' ? 'nav-tab-active' : ''; ?>">Departments</a>
	<a href="admin.php?page=vw-support&tab=forms" class="nav-tab <?php echo $active_tab == 'forms' ? 'nav-tab-active' : ''; ?>">Forms</a>
	<a href="admin.php?page=vw-support&tab=accounts" class="nav-tab <?php echo $active_tab == 'accounts' ? 'nav-tab-active' : ''; ?>">Accounts</a>
	<a href="admin.php?page=vw-support&tab=translation" class="nav-tab <?php echo $active_tab == 'translation' ? 'nav-tab-active' : ''; ?>">Translation</a>

	<a href="admin.php?page=vw-support&tab=micropayments" class="nav-tab <?php echo $active_tab == 'micropayments' ? 'nav-tab-active' : ''; ?>">MicroPayments</a>

	<a href="admin.php?page=vw-support&tab=gtag" class="nav-tab <?php echo $active_tab == 'gtag' ? 'nav-tab-active' : ''; ?>">Gtag</a>
	<a href="admin.php?page=vw-support&tab=logs" class="nav-tab <?php echo $active_tab == 'logs' ? 'nav-tab-active' : ''; ?>">Logs</a>
</nav>

	<form method="post" action="<?php echo wp_nonce_url( $_SERVER['REQUEST_URI'], 'vwsec' ); ?>">
			<?php
			switch ( $active_tab )
			{

				case 'gtag':
					?>
				<h3>Tracking</h3>
				Track goals by sending to Google Analytics (gtag), defined as AW-account/Event_Label . Gtag must be already available (setup with Site Kit or plugin).

				<h4>Contact View</h4>
				<input type="text" name="gtagContactView" id="gtagContactView" value="<?php echo esc_attr( $options['gtagContactView'] ); ?>" size="100" />

				<h4>Contact New</h4>
				<input type="text" name="gtagContactNew" id="gtagContactNew" value="<?php echo esc_attr( $options['gtagContactNew'] ); ?>" size="100" />

				<h4>Contact Confirm</h4>
				<input type="text" name="gtagContactConfirm" id="gtagContactConfirm" value="<?php echo esc_attr( $options['gtagContactConfirm'] ); ?>" size="100" />

				<h4>Message</h4>
				<input type="text" name="gtagMessage" id="gtagMessage" value="<?php echo esc_attr( $options['gtagMessage'] ); ?>" size="100" />

				<h4>Conversation</h4>
				<input type="text" name="gtagConversation" id="gtagConversation" value="<?php echo esc_attr( $options['gtagConversation'] ); ?>" size="100" />

				<h4>Conversation View</h4>
				<input type="text" name="gtagConversationView" id="gtagConversationView" value="<?php echo esc_attr( $options['gtagConversationView'] ); ?>" size="100" />
					<?php
					break;

				case 'translation':
		
					?>
					
					<h3>MultiLanguage & Translations</h3>
					This plugin integrates <a href="https://www.deepl.com/en/whydeepl" target="_vw">DeepL</a> API for live chat text translations. Static texts can be translated as rest of WP plugins.
					
					<h4>Multilanguage Chat</h4>
					<select name="multilanguage" id="multilanguage">
					  <option value="0" <?php echo $options['multilanguage'] ? '' : 'selected'; ?>>Disabled</option>
					  <option value="1" <?php echo $options['multilanguage'] ? 'selected' : ''; ?>>Enabled</option>
					</select>
					<br>Enable users to specify language they are using in text chat.
					
					<H4>DeepL API Key</H4>
					<input name="deepLkey" type="text" id="deepLkey" size="100" maxlength="256" value="<?php echo esc_attr( $options['deepLkey'] ); ?>"/>
					<br>Register a <a href="https://www.deepl.com/pro-checkout/account?productId=1200&yearly=false&trial=false">free DeepL developer account</a> to get a key. After login, you can retrieve your key from <a href="https://www.deepl.com/account/summary" target="_vw">DeepL account > Authentication Key for DeepL API</a>. For high activity sites, a paid account may be required depending on translation volume. Keep your key secret to prevent unauthorized usage. 
					
					<h4>Chat Translations</h4>
					<select name="translations" id="translations">
					  <option value="0" <?php echo $options['translations'] ? '' : 'selected'; ?>>Disabled</option>
					  <option value="registered" <?php echo $options['translations'] == 'registered' ? 'selected' : ''; ?>>Registered</option>
					  <option value="all" <?php echo $options['translations'] == 'all' ? 'selected' : ''; ?>>All Users</option>
					</select>
					<br>Enable translations for everybody or just registered users.
					
					<h4>Default Language</h4>
					<select name="languageDefault" id="languageDefault">
					<?php
					$languages = get_option( 'VWdeepLlangs' );
					//list languages as options	
					if ( !$languages ) echo '<option value="en-us" selected>English (American)</option>';
					else foreach ( $languages as $key => $value )
					{
						echo '<option value="'. esc_attr( $key ).'" '.( $options['languageDefault'] == $key ? 'selected' : '' ).'>'. esc_html( $value ).'</option>';
					}
					?>
					</select>
					<br>Default language of site users. This will be used for translations if user does not specify a language.
					
					<?php submit_button(); ?>
					
					<H4>Supported Languages</H4>
					<?php 
					if ( !$languages )
					{
							echo 'First runs. Setting default languages. ';
							update_option( 'VWdeepLlangs', unserialize( 'a:31:{s:2:"bg";s:9:"Bulgarian";s:2:"cs";s:5:"Czech";s:2:"da";s:6:"Danish";s:2:"de";s:6:"German";s:2:"el";s:5:"Greek";s:5:"en-gb";s:17:"English (British)";s:5:"en-us";s:18:"English (American)";s:2:"es";s:7:"Spanish";s:2:"et";s:8:"Estonian";s:2:"fi";s:7:"Finnish";s:2:"fr";s:6:"French";s:2:"hu";s:9:"Hungarian";s:2:"id";s:10:"Indonesian";s:2:"it";s:7:"Italian";s:2:"ja";s:8:"Japanese";s:2:"ko";s:6:"Korean";s:2:"lt";s:10:"Lithuanian";s:2:"lv";s:7:"Latvian";s:2:"nb";s:9:"Norwegian";s:2:"nl";s:5:"Dutch";s:2:"pl";s:6:"Polish";s:5:"pt-br";s:22:"Portuguese (Brazilian)";s:5:"pt-pt";s:21:"Portuguese (European)";s:2:"ro";s:8:"Romanian";s:2:"ru";s:7:"Russian";s:2:"sk";s:6:"Slovak";s:2:"sl";s:9:"Slovenian";s:2:"sv";s:7:"Swedish";s:2:"tr";s:7:"Turkish";s:2:"uk";s:9:"Ukrainian";s:2:"zh";s:20:"Chinese (simplified)";}' ) );
							
							$languages = get_option( 'VWdeepLlangs', true);
							
					}
					var_dump( $languages );
					?>
					<br><a class="button secondary" target="_vw" href="<?php echo plugins_url('live-support-tickets/server/translate.php?update_languages=videowhisper'); ?>">Update Supported Languages</a>
					<br>This will retrieve latest list of supported languages from DeepL API, if a valid key is available.
					
					<h4>Translation for Solution Features, Pages, HTML5 Videochat</h4>
					Translate solution in different language. Software is composed of applications and integration code (plugin) that shows features on WP pages.
					
					This plugin is translation ready and can be easily translated started from 'pot' file from languages folder. You can translate for own use and also <a href="https://translate.wordpress.org/projects/wp-plugins/live-support-tickets/">contributing translations</a>.
					
					<br>Sample translations for plugin are available in "languages" plugin folder and you can edit/adjust or add new languages using a translation plugin like <a href="https://wordpress.org/plugins/loco-translate/">Loco Translate</a> : From Loco Translate > Plugins > [plugin name],  you can edit existing languages or add new languages.
					<br>You can also start with an automated translator application like Poedit, translate more texts with Google Translate and at the end have a human translator make final adjustments. If you want to contribute translation files, you can contact VideoWhisper support and provide links to new translation files if you want these included in future plugin updates.
					
									<?php
								break;

				case 'logs':
				?>
				<h3>Logs</h3>

				<h4>Log Level</h4>
				<select name="logLevel" id="logLevel">
					<option value="0" <?php if ( $options['logLevel'] == 0 ) echo 'selected'; ?>>0. None</option>
					<option value="1" <?php if ( $options['logLevel'] == 1 ) echo 'selected'; ?>>1. Errors</option>
					<option value="2" <?php if ( $options['logLevel'] == 2 ) echo 'selected'; ?>>2. Warnings</option>
					<option value="3" <?php if ( $options['logLevel'] == 3 ) echo 'selected'; ?>>3. Notice</option>
					<option value="4" <?php if ( $options['logLevel'] == 4 ) echo 'selected'; ?>>4. Info</option>
					<option value="5" <?php if ( $options['logLevel'] == 5 ) echo 'selected'; ?>>5. Debug</option>
				</select>
				<br>Each level includes previous levels.

				<h4>Log Days</h4>
				<input type="text" name="logDays" id="logDays" value="<?php echo esc_attr( $options['logDays'] ); ?>" size="5" />
				<br>Number of days to keep log files.
				<?php
				submit_button();
				?>

				<h4>Log Path</h4>
				<input type="text" name="logPath" id="logPath" value="<?php echo esc_attr( $options['logPath'] ); ?>" size="100" />
				<br>Path to store log files. Make sure this is writable by PHP and not accessible from web.
				
				<h4>Today's Log</h4>
				<?php
				$dir = $options['logPath'];
				if ( !file_exists( $dir ) ) mkdir( $dir, 0777, true );
			
				$logFile = $dir . '/' . date( 'Y-m-d' ) . '.txt';
				if ( file_exists( $logFile ) )
				{
					$log = file_get_contents( $logFile );
					echo '<textarea rows="10" cols="100" readonly>' . esc_html($log) . '</textarea>';
					echo '<br/>' . esc_html($logFile);
				}
				else
				{
					echo 'Log file not found: ' . esc_html($logFile);
				}

				$logFile = $dir .  '/' . date( 'Y-m-d', strtotime( '-1 days' ) ). '.txt';
				?>
				<h4>Yesterday's Log</h4>
				<?php 
				if ( file_exists( $logFile ) )
				{
					$log = file_get_contents( $logFile );
					echo '<textarea rows="10" cols="100" readonly>' . esc_html($log) . '</textarea>';
					echo '<br/>' . esc_html( $logFile );
				}
				else
				{
					echo 'Log file not found: ' . esc_html( $logFile );
				}

				//read log cleanup time from a file logCleanup.txt
				$logCleanupFile = $options['logPath'] . '/logCleanup.txt';
				$logCleanupTime = 0;
				if ( file_exists( $logCleanupFile ) )
				{
					$logCleanupTime = file_get_contents( $logCleanupFile );
				}	

				//cleanup logs if not done in the last hour
				if ( $logCleanupTime < time() - 60 * 60 )
				{
				//delete files in the log directory older than logDays days ago
				$files = glob( $options['logsPath'] . '/*' );
				$count = 0;
				$countDeleted = 0;
				foreach ( $files as $file )
				{
					if ( is_file( $file ) )
					{
						$count ++; 
						if ( filemtime( $file ) < time() - 60 * 60 * 24 * $options['logDays'] )
						{
							unlink( $file );
							$countDeleted++;

						}
					}
				}
				echo '<p>Cleanup: Found ' . esc_html( $count ) . ' log files.</p>';
				if ($countDeleted) echo '<p>Deleted ' . esc_html( $countDeleted ) . ' log files out of ' . esc_html( $count ) . '.</p>';

				//update log cleanup time in a file logCleanup.txt
				file_put_contents( $logCleanupFile, time() );
				}

				echo '<p>Find all logs at ' . esc_html( $options['logPath'] ) . '/ </p>';
				
				?>
				<h4>Counters</h4>
				<?php
				echo wp_kses_post( self::updateCounters( $options, 0, true ) );
				break;
				case 'accounts':
				?>
				<h3>Accounts</h3>
				<p>Registration form: Form action 'account' will create an account, with name field based on domain from Website form field (max 32 chars), a generated token string, planId 1, contactId field with contact id, created field with creation time. All form fields will be included in meta.</p>

				<?php
					if ($_GET['apitest'] ?? false) {

						$nonce = $_REQUEST['_wpnonce'];
						if ( ! wp_verify_nonce( $nonce, 'vwsec' ) )
						{
							echo 'Invalid nonce!';
							exit;
						}
	
						echo '<h4>Accounts API Test</h4>';
						echo 'Calling ' . esc_html( $options['accountsAPI'] ) . '<br>';
						if ($options['accountsAPI']) echo wp_kses_post( self::updateAccountsAPI( $options ) );
						else echo 'No API URL set.';
					}
				?>

				<h4>Database Server</h4>
				<input type="text" name="accounts_host" id="accounts_host" value="<?php echo esc_attr( $options['accounts_host'] ); ?>" size="30" />
				<br> For access to a remote MySQL server, that server needs to allow incoming connections in Firewall and Remote MySQL access for this host. 
				<?php
				$host = gethostname();
				$ip = gethostbyname( $host );
				echo 'Host: ' . esc_html( $host ) . ' / ' . esc_html( $ip );
				?>

				<h4>Database Port</h4>
				<input type="text" name="accounts_port" id="accounts_port" value="<?php echo esc_attr( $options['accounts_port'] ); ?>" size="30" />

				<h4>Database User</h4>
				<input type="text" name="accounts_user" id="accounts_user" value="<?php echo esc_attr( $options['accounts_user'] ); ?>" size="30" />

				<h4>Database Password</h4>
				<input type="text" name="accounts_pass" id="accounts_pass" value="<?php echo esc_attr( $options['accounts_pass'] ); ?>" size="30" />

				<h4>Database Name</h4>
				<input type="text" name="accounts_db" id="accounts_db" value="<?php echo esc_attr( $options['accounts_db'] ); ?>" size="30" />

				<h4>Accounts Limit</h4>
				<input type="text" name="accountsLimit" id="accountsLimit" value="<?php echo esc_attr( $options['accountsLimit'] ); ?>" size="5" />
				<br>Maximum number of accounts a contact can register. Set 0 for unlimited.

				<h4>Accounts API</h4>
				<input type="text" name="accountsAPI" id="accountsAPI" value="<?php echo esc_attr( $options['accountsAPI'] ); ?>" size="128" />
				<br>URL to call after a new account is created. Ex: https://yourServer.com:3000
				<br>After saving, you can<a href="<?php echo wp_nonce_url('admin.php?page=vw-support&tab=accounts&apitest=1', 'vwsec') ?>">Test Account Update</a>.

				<h4>Accounts API Key</h4>
				<input type="text" name="accountsAPIkey" id="accountsAPIkey" value="<?php echo esc_attr( $options['accountsAPIkey'] ); ?>" size="128" />
				<br>API key to use when calling API URL (to be included as ?apikey=YOUR_API_KEY).

				<h4>Enforce API SSL</h4>
				<select name="apiSSL" id="apiSSL">
					<option value="0" <?php selected( $options['apiSSL'], 0 ); ?>>Disable</option>
					<option value="1" <?php selected( $options['apiSSL'], 1 ); ?>>Enable</option>
				</select>
				<br>If SSL is not configured for your setups, you can disable CURLOPT_SSL_VERIFYPEER to test. Could result in man in the middle attacks.

				<h4>Accounts RTMP</h4>
				<input type="text" name="accountsRTMP" id="accountsRTMP" value="<?php echo esc_attr( $options['accountsRTMP'] ); ?>" size="128" />
				<br>RTMP address for broadcasting if included. Ex: rtmp://yourServer.com:1935

				<h4>Accounts HLS</h4>
				<input type="text" name="accountsHLS" id="accountsHLS" value="<?php echo esc_attr( $options['accountsHLS'] ); ?>" size="128" />
				<br>HLS address for playback if included. Ex: https://yourServer.com:3000/hls

				<h4>Accounts WebRTC</h4>
				<input type="text" name="accountsWebRTC" id="accountsWebRTC" value="<?php echo esc_attr( $options['accountsWebRTC'] ); ?>" size="128" />
				<br>WebRTC address for WebRTC signaling server if included. Ex: wss://yourServer.com:3000

				<?php

				submit_button();

				//if database name is set, test connection and list tables with row count
				if ($options['accounts_db'] && $options['accounts_host'])
				{
					$accountsDB = new \mysqli($options['accounts_host'], $options['accounts_user'], $options['accounts_pass'], $options['accounts_db'], $options['accounts_port']);
					if ($accountsDB->connect_error) echo '<BR><font color="red">Database connection failed: ' . esc_html( $accountsDB->connect_error ) . '</font>';
					else
					{
						echo '<h4><font color="green">Database Connection Successful</font></h4>';

						//list last 5 rows added (by id) from accounts table, with fields: name, token, planId, contactId, properties, meta
						$accounts = $accountsDB->query("SELECT * FROM accounts ORDER BY id DESC LIMIT 10");
						if ($accounts)
						{
							echo "<h4>Last 10 Accounts</h4>";
							echo "<table><tr><th>id</th><th>name</th><th>token</th><th>planId</th><th>contactId</th><th>properties</th><th>meta</th></tr>";
							while ($account = $accounts->fetch_assoc())
							{
								echo "<tr>";
									echo "<td>" . esc_html($account['id']) . "</td>";
									echo "<td>" . esc_html($account['name']) . "</td>";
									echo "<td>" . esc_html($account['token']) . "</td>";
									echo "<td>" . esc_html($account['planId']) . "</td>";
									echo "<td>" . esc_html($account['contactId']) . "</td>";
									echo "<td>" . esc_html($account['properties']) . "</td>";
									echo "<td><small>" . esc_html($account['meta']) . "</small></td>";
								echo "</tr>";
							}
							echo "</table>";
						}

						
						//list tables, columns, row count
						$tables = $accountsDB->query("SHOW TABLES");
						if ($tables)
						{
							while ($table = $tables->fetch_row())
							{
								$table = $table[0];
								$columns = $accountsDB->query("SHOW COLUMNS FROM $table");
								$columns = $columns->fetch_all(MYSQLI_ASSOC);

								$rows = $accountsDB->query("SELECT COUNT(*) FROM $table");
								$rows = $rows->fetch_row();
								$rows = $rows[0];

								echo '<h4>' . esc_html($table).'('. esc_html($rows) . ' rows)</h4>';
								echo "<table><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
								foreach ($columns as $column)
								{
									echo "<tr>";
									foreach ($column as $key => $value) echo "<td>" . esc_html($value) . "</td>";
									echo "</tr>";
								}
								echo "</table>";
							}
						}			
					}
				}
					break;

				case 'forms':
					$options['fieldsConfig'] = stripslashes( $options['fieldsConfig'] );
					$options['formsConfig'] = stripslashes( $options['formsConfig'] );

				?>
				<h3>Forms</h3>
				Define custom fields that can be filled per contact with a form or per new conversation (custom fields per department). Forms can be displayed with shortcode [videowhisper_support params="form: 'Form Name'"] and will enable contacts to fill fields and update these later.

				<h4>Fields / Questions</h4>
				<textarea name="fieldsConfig" id="fieldsConfig" cols="100" rows="12"><?php echo esc_textarea( $options['fieldsConfig'] ); ?></textarea>
				<BR>Save to setup. Configure fields as sections with parameters "type" (text/textarea/select/toggle), "instructions", "options" (values separated by |).
				<br>If a value  contains any non-alphanumeric characters it needs to be enclosed in double-quotes ("").
				<br>Default Settings:<br><textarea readonly cols="100" rows="5"><?php echo esc_textarea( $optionsDefault['fieldsConfig'] ); ?></textarea>
				<BR>Parsed fields configuration (should be an array or arrays):<BR>
				<?php
							if ( $options['fieldsConfig'] && !$options['fields'] )
							{
								echo '<br><b>Warning: No values, probably syntax error. Please review & correct.</b>';
							}
							var_dump( $options['fields'] );
							?>
										<BR>Serialized:<BR>
				<?php

						echo esc_html( serialize( $options['fields'] ) );
			?>

			<h4>Contact Forms</h4>
						<textarea name="formsConfig" id="formsConfig" cols="100" rows="12"><?php echo esc_textarea( $options['formsConfig'] ); ?></textarea>
						<BR>Save to setup. Configure forms as sections with parameters "fields" (list of field names separated by |), "instructions", "unconfirmed", "terms", "done", "doneUrl", "gtag", "gtagView".
						<BR>Registration forms: Parameter "actions" can include "account" to create an account (see Accounts section</a>). Ex: actions="account"
							<br>If a value  contains any non-alphanumeric characters it needs to be enclosed in double-quotes ("").
							<br>Default Settings:<br><textarea readonly cols="100" rows="5"><?php echo esc_textarea( $optionsDefault['formsConfig'] ); ?></textarea>
							<BR>Parsed fields configuration (should be an array or arrays):<BR>
							<?php
										if ( $options['formsConfig'] && !$options['forms'] )
										{
											echo '<br><b>Warning: No values, probably syntax error. Please review & correct.</b>';
										}
										var_dump( $options['forms'] );
										?>
													<BR>Serialized:<BR>
									<?php

									echo esc_html( serialize( $options['forms'] ) );

					break;

				case 'micropayments':
				?>
				<h3>MicroPayments</h3>
				Integration with <a href="https://ppvscript.com/micropayments/">MicroPayments</a> plugin provides monetization options: clients pay to open conversations and send questions.
				<br>MicroPayments plugin:
				<?php
				if (!class_exists( 'VWpaidMembership' )) echo 'If you want to use this functionality, <a href="plugin-install.php?s=videowhisper+micropayments&tab=search&type=term">install and activate the MicroPayments plugin</a>.';
				else echo 'Detected.';
				?>

				<h4>Paid Conversations</h4>
				<select name="micropayments" id="micropayments">
				  <option value="0" <?php echo !$options['micropayments'] ? 'selected' : ''; ?>>Disabled</option>
				  <option value="users" <?php echo $options['micropayments'] == 'users' ? 'selected' : ''; ?>>Users</option>
				  <option value="all" <?php echo $options['micropayments'] == 'all' ? 'selected' : ''; ?>>All</option>
				</select>
				<br>Paid conversations can be enabled by default to conversation requests for frontend Users (creators) or for All conversations (including addressed to backend support).

				<h4>Representative Revenue Share</h4>
				<select name="micropaymentsShare" id="micropaymentsShare">
				<option value="0" <?php echo !$options['micropaymentsShare'] ? 'selected' : ''; ?>>Disabled</option>
				<option value="1" <?php echo $options['micropayments'] == '1' ? 'selected' : ''; ?>>Enabled</option>
				</select>
				<br>Revenue share can be enabled to split payments between multiple representatives in a conversation, after site takes own share. If disabled only the "creator" receives payment. 
				<br>When any representative answers, all representatives get paid. Payment is provided by client that opens conversation and sends messages. Messages can be sent (and paid) by different clients that were added as contacts to conversation.

				<h4>Conversation Request Cost</h4>
				<input name="micropaymentsConversation" type="text" id="micropaymentsConversation" size="10" maxlength="10" value="<?php echo esc_attr( $options['micropaymentsConversation'] ); ?>"/>
				<BR>Cost to request a new conversation. Includes original message/question and additional ones may be charged extra.

				<h4>Additional Message Cost</h4>
				<input name="micropaymentsMessage" type="text" id="micropaymentsMessage" size="10" maxlength="10" value="<?php echo esc_attr( $options['micropaymentsMessage'] ); ?>"/>
				<BR>Cost to send an additional message/question in an existing conversation (after first message).

				<h4>Earning Ratio</h4>
				<input name="micropaymentsRatio" type="text" id="micropaymentsRatio" size="10" maxlength="10" value="<?php echo esc_attr( $options['micropaymentsRatio'] ); ?>"/>
				<BR>How much of cost is received by conversation/message recipient. In example a ratio of 0.8 means recipient gets 80% to answer and 20% remains to website.
				<BR>Creator is paid for conversation/messages after replying.

				<h4>Custom Prices</h4>
				Use shortcode [videowhisper_support_micropayments] to display a form that allows selected user types to set custom prices for conversations and messages, that apply for creator conversations.

				<h4>Custom Cost Roles</h4>
				<input name="micropaymentsRoles" type="text" id="micropaymentsRoles" size="100" maxlength="250" value="<?php echo esc_attr( $options['micropaymentsRoles'] ); ?>"/>
				<BR>Comma separated roles allowed to set custom cost for conversations and messages. Ex: <?php echo esc_html( $optionsDefault['micropaymentsRoles'] ); ?>
				
				<h4>Minimum Conversation Cost</h4>
				<input name="micropaymentsConversationMin" type="text" id="micropaymentsConversationMin" size="10" maxlength="10" value="<?php echo esc_attr( $options['micropaymentsConversationMin'] ); ?>"/>
				<BR>Minimum cost for a conversation. Default: <?php echo esc_html( $optionsDefault['micropaymentsConversationMin'] ); ?>

				<h4>Minimum Message Cost</h4>
				<input name="micropaymentsMessageMin" type="text" id="micropaymentsMessageMin" size="10" maxlength="10" value="<?php echo esc_attr( $options['micropaymentsMessageMin'] ); ?>"/>
				<BR>Minimum cost for a message. Default: <?php echo esc_html( $optionsDefault['micropaymentsMessageMin'] ); ?>

				<h4>Maximum Conversation Cost</h4>
				<input name="micropaymentsConversationMax" type="text" id="micropaymentsConversationMax" size="10" maxlength="10" value="<?php echo esc_attr( $options['micropaymentsConversationMax'] ); ?>"/>
				<BR>Maximum cost for a conversation. Default: <?php echo esc_html( $optionsDefault['micropaymentsConversationMax'] ); ?>

				<h4>Maximum Message Cost</h4>
				<input name="micropaymentsMessageMax" type="text" id="micropaymentsMessageMax" size="10" maxlength="10" value="<?php echo esc_attr( $options['micropaymentsMessageMax'] ); ?>"/>
				<BR>Maximum cost for a message. Default: <?php echo esc_html( $optionsDefault['micropaymentsMessageMax'] ); ?>




				<?php
				break;

				case 'integration':
				?>
				<h3>Integration</h3>
				Integration with site content and features.

				<h4>Open Conversation Before Confirmation</h4>
				<select name="registerConversation" id="registerConversation">
				<option value="0" <?php echo $options['registerConversation'] ? '' : 'selected'; ?>>Disabled</option>
				<option value="1" <?php echo $options['registerConversation'] ? 'selected' : ''; ?>>Enabled</option>
				</select>
				<br>This will allow visitors to select a department and submit inquiry before confirming contact, ownership of email. By default it's disabled as it can result in spam conversations being open or users not verifying their contact email (replies will never reach them if contact email is incorrect).
				<br>Until contact is confirmed, conversation will have status Pending (5) and will show at end of list when sorted by status. A special counter for Pending conversations will show in Conversations list.

				<h4>Floating Support Buttons</h4>
				<select name="buttons" id="buttons">
				  <option value="0" <?php echo $options['buttons'] ? '' : 'selected'; ?>>Disabled</option>
				  <option value="1" <?php echo $options['buttons'] ? 'selected' : ''; ?>>Enabled</option>
				</select>
				<br>Show support buttons floating on all pages.

				<h4>Content Report (to Site Support): Content Types</h4>
				<input name="postTypesContent" type="text" id="postTypesContent" size="100" maxlength="250" value="<?php echo esc_attr( $options['postTypesContent'] ); ?>"/>
				<BR>Shows a Report button on content pages, to report that content to site support. Comma separated content/post types. Ex: page, post, video, picture, download

				<h4>Contact Author (Creator): Content Types</h4>
				<input name="postTypesAuthor" type="text" id="postTypesAuthor" size="100" maxlength="250" value="<?php echo esc_attr( $options['postTypesAuthor'] ); ?>"/>
				<BR>Shows a Contact button on content pages, to contact content author (creator). Leave empty to disable. Comma separated content/post types. Ex: page, post, video, picture, download

				<h4>Content Button Position</h4>
				<select name="buttonsPosition" id="buttonsPosition">
				  <option value="after" <?php echo $options['buttonsPosition'] ? '' : 'selected'; ?>>After (Bottom)</option>
				  <option value="before" <?php echo $options['buttonsPosition'] ? 'selected' : ''; ?>>Before (Top)</option>
				</select>
				<br>Show content buttons before (on top) or after content (bottom).

				<h4>Creator User Roles for BuddyPress Contact</h4>
				<input name="rolesCreator" type="text" id="rolesCreator" size="100" maxlength="250" value="<?php echo esc_attr( $options['rolesCreator'] ); ?>"/>
				<BR>Comma separated roles allowed to be contacted from their BuddyPress profile. Ex: administrator, editor, author, contributor, performer, creator, studio
				<br>Leave empty to allow anybody or only an inexistent role (none) to disable for all.

				<h4>Register WP User for Each Contact</h4>
				<select name="registerUser" id="registerUser">
				  <option value="0" <?php echo $options['registerUser'] ? '' : 'selected'; ?>>Disabled</option>
				  <option value="1" <?php echo $options['registerUser'] ? 'selected' : ''; ?>>Enabled</option>
				</select>
				<br>Register & login a WordPress user on contact confirmation. WP also sends an email with link to reset password.
				<br>Registration provides extra security, easier access to previous tickets and may be used for more advanced member features.
				<br>Do NOT enable on websites that already include advanced signup forms or registration options.

				<h4>Redirect to Support Page After Login</h4>
				<select name="redirectUser" id="redirectUser">
				  <option value="0" <?php echo $options['redirectUser'] ? '' : 'selected'; ?>>Disabled</option>
				  <option value="1" <?php echo $options['redirectUser'] ? 'selected' : ''; ?>>Enabled</option>
				</select>
				<br>Redirects to support page after login. Only use on sites/subsites where support is the main functionality. 

				<h4>Interface Class(es)</h4>
				<input name="interfaceClass" type="text" id="interfaceClass" size="30" maxlength="128" value="<?php echo esc_attr( $options['interfaceClass'] ); ?>"/>
				<br>Extra class to apply to interface (using Semantic UI). Use inverted when theme uses a dark mode (a dark background with white text) or for contrast. Ex: inverted
				<br>Some common Semantic UI classes: inverted = dark mode or contrast, basic = no formatting, secondary/tertiary = greys, red/orange/yellow/olive/green/teal/blue/violet/purple/pink/brown/grey/black = colors . Multiple classes can be combined, divided by space. Ex: inverted, basic pink, secondary green, secondary

				<h4>Custom CSS</h4>
				<textarea name="customCSS" id="customCSS" cols="100" rows="4"><?php echo esc_textarea( $options['customCSS'] ); ?></textarea>
				<br>Custom CSS to apply to app interface. May include fixes in case theme CSS breaks app interface.
				<br>Default:<br><textarea readonly cols="100" rows="3"><?php echo esc_textarea( $optionsDefault['customCSS'] ); ?></textarea>

				<h4>FullPage Template</h4>
				<select name="fullpageTemplate" id="fullpageTemplate">
				  <option value="0" <?php echo $options['fullpageTemplate'] ? '' : 'selected'; ?>>Disabled</option>
				  <option value="1" <?php echo $options['fullpageTemplate'] ? 'selected' : ''; ?>>Enabled</option>
				</select>
				<br>The full page template can be is available as template-support.php in plugin folder and can be used with vwtemplate=support GET parameter if enabled. You can leave disabled and configure own template per page, depending on theme capabilities.
				
				<?php
				break;

				case 'departments':

				$options['departmentsConfig'] =  stripslashes( $options['departmentsConfig'] ) ;

				//type: 0 = site, 1 = content site, 2 = content owner, 3 = user; 13 = custom user, 23 = custom user remote site
				?>
				<h3>Departments</h3>

				<h4>Configure Public Departments</h4>
				<textarea name="departmentsConfig" id="departmentsConfig" cols="100" rows="12"><?php echo esc_textarea( $options['departmentsConfig'] ); ?></textarea>
				<br>Department definition includes index number as title [] that will be assigned to tickets, text label and type number referring to usage: 0 = contact site support, 1 = report content to site support, 2 = contact content creator, 3 = contact user).
				<br>Departments are available based on context: types 1,2 show when "content" (post ID) parameter is included and type 3 when "creator" (user ID) is included.
				<br>Departments can show multiple custom fields defined in Forms settings section, divided by |.
				<br>Default Settings:<br><textarea readonly cols="100" rows="6"><?php echo esc_textarea( $optionsDefault['departmentsConfig'] ); ?></textarea>
				<BR>Parsed configuration (should be an array or arrays):<BR>
								<?php
							var_dump( $options['departments'] );

							if ( $options['departmentsConfig'] && !$options['departments'] )
							{
								echo '<br><b>Warning: Configuration syntax error! Please review & correct.</b>';
							}
				?>
				<BR>Serialized:<BR>
								<?php

							echo esc_html( serialize( $options['departments'] ) );

							echo '<br>Default:<br>' . esc_html( serialize( $options['departments'] ) );
				?>
				<?php
				break;

				case 'notifications':
				?>
				<h3>Contact Notifications</h3>
				To improve email deliverability, use a <a href="plugin-install.php?s=smtp&tab=search&type=term">WordPress SMTP plugin</a> configured with a real email account. In contact Subject and Message you can use: {name} as contact name, {contact} as contact (email address).

				<h4>Unsupported Email Providers</h4>
			   <input type="text" name="emailsUnsupported" id="emailsUnsupported" value="<?php echo esc_attr( $options['emailsUnsupported'] ); ?>" size="100" />
			   <br>Define a list of usupported email providers to prevent contact registration, if there's issues with delivery or spammers.
			   <br>CSV list of unsupported email providers. Default: <?php echo esc_html( $optionsDefault['emailsUnsupported'] ); ?> 
				
				<h4>Contact Confirm Subject</h4>
				<input name="notifyConfirmSubject" type="text" id="notifyConfirmSubject" size="100" maxlength="256" value="<?php echo esc_attr( $options['notifyConfirmSubject'] ); ?>"/>
				<br>A confirmation message with a secret code is sent to contact, to confirm validity. Default:  <I><?php echo esc_html( $optionsDefault['notifyConfirmSubject'] ); ?> </I>

				<h4>Contact Confirm Message</h4>
				<textarea name="notifyConfirmMessage" id="notifyConfirmMessage" cols="100" rows="3"><?php echo esc_textarea( $options['notifyConfirmMessage'] ); ?></textarea>
				<br>Use {code} to include the confirmation code in message where needed.
				<br>Default:  <I><?php echo esc_html( $optionsDefault['notifyConfirmMessage'] ); ?> </I>

				<h4>Conversation (Ticket) Update Subject</h4>
				<input name="notifyTicketSubject" type="text" id="notifyTicketSubject" size="100" maxlength="256" value="<?php echo esc_attr( $options['notifyTicketSubject'] ); ?>"/>
				<br>Sent when ticket is created or updated. Can also use {sender} parameter as contact name of message sender and {department} as department name. Default:  <I><?php echo esc_html( $optionsDefault['notifyTicketSubject'] ); ?></I>

				<h4>Conversation (Ticket) Update Message</h4>
				<textarea name="notifyTicketMessage" id="notifyTicketMessage" cols="100" rows="3"><?php echo esc_textarea( $options['notifyTicketMessage'] ); ?></textarea>
				<br>Can also use {sender} parameter as contact name of message sender and {department} as department name. Default: <I><?php echo esc_html( $optionsDefault['notifyTicketMessage'] ); ?></I>

				<h4>Conversation Add Contact: Subject</h4>
				<input name="notifyAddSubject" type="text" id="notifyAddSubject" size="100" maxlength="256" value="<?php echo esc_attr( $options['notifyAddSubject'] ); ?>"/>
				<br>Sent to a contact that is added (invited). Default:  <I><?php echo esc_html( $optionsDefault['notifyAddSubject'] ); ?></I>

				<h4>Conversation Add Contact: Message</h4>
				<textarea name="notifyAddMessage" id="notifyAddMessage" cols="100" rows="3"><?php echo esc_textarea( $options['notifyAddMessage'] ); ?></textarea>
				<br>Default: <I><?php echo esc_html( $optionsDefault['notifyAddMessage'] ); ?></I>

				<h4>Invite Subject</h4>
				<input name="notifyInviteSubject" type="text" id="notifyInviteSubject" size="100" maxlength="256" value="<?php echo esc_attr( $options['notifyInviteSubject'] ); ?>"/>
				<br>Default: <I><?php echo esc_html( $optionsDefault['notifyInviteSubject'] ); ?></I>

				<h4>Invite Message</h4>
				<textarea name="notifyInviteMessage" id="notifyInviteMessage" cols="100" rows="3"><?php echo esc_textarea( $options['notifyInviteMessage'] ); ?></textarea>
				<br>Default: <I><?php echo esc_html( $optionsDefault['notifyInviteMessage'] ); ?></I>

				<h4>Form Invite Subject</h4>
				<input name="notifyFormInviteSubject" type="text" id="notifyFormInviteSubject" size="100" maxlength="256" value="<?php echo esc_attr( $options['notifyFormInviteSubject'] ); ?>"/>
				<br>Default: <I><?php echo esc_html( $optionsDefault['notifyFormInviteSubject'] ); ?></I>

				<h4>Form Invite Message</h4>
				<textarea name="notifyFormInviteMessage" id="notifyFormInviteMessage" cols="100" rows="3"><?php echo esc_textarea( $options['notifyFormInviteMessage'] ); ?></textarea>
				<br>Default: <I><?php echo esc_html( $optionsDefault['notifyFormInviteMessage'] ); ?></I>

				<h4>Account Creation Subject</h4>
				<input name="notifyAccountSubject" type="text" id="notifyAccountSubject" size="100" maxlength="256" value="<?php echo esc_attr( $options['notifyAccountSubject'] ); ?>"/>
				<br>Default: <I><?php echo esc_html( $optionsDefault['notifyAccountSubject'] ); ?></I>

				<h4>Account Creation Message</h4>
				<textarea name="notifyAccountMessage" id="notifyAccountMessage" cols="100" rows="3"><?php echo esc_textarea( $options['notifyAccountMessage'] ); ?></textarea>
				<br>Default: <I><?php echo esc_html( $optionsDefault['notifyAccountMessage'] ); ?></I>

				<h4>Notify Message Footer</h4>
				<textarea name="notifyMessageFooter" id="notinotifyMessageFooterfyFooter" cols="100" rows="3"><?php echo esc_textarea( $options['notifyMessageFooter'] ); ?></textarea>
				<br>Default: <I><?php echo esc_html( $optionsDefault['notifyMessageFooter'] ); ?></I>

				<h4>Send Email Notifications</h4>
				<select name="notifyEmail" id="notifyEmail">
				  <option value="0" <?php echo $options['notifyEmail'] ? '' : 'selected'; ?>>Disabled</option>
				  <option value="1" <?php echo $options['notifyEmail'] ? 'selected' : ''; ?>>Enabled</option>
				</select>
				<br>Sending emails can be disabled during development and testing.

				<h4>Notification Cooldown per Contact</h4>
				<input name="notifyCooldownContact" type="text" id="notifyCooldownContact" size="6" maxlength="10" value="<?php echo esc_attr( $options['notifyCooldownContact'] ); ?>"/>s
				<br>As messages are sent similar to a chat, a low value can result in many notifications. Default:  <I><?php echo esc_html( $optionsDefault['notifyCooldownContact'] ); ?> </I>

				<?php
				break;

				case 'pages':

				if ( $_POST['submit'] ?? false )
				{
					self::setupPages();
					echo '<p class="notice notice-info is-dismissible">Saved pages setup.</p>';
				}
				?>
				<h3>Setup Pages</h3>
				You can manage pages anytime from backend: <a href="edit.php?post_type=page">pages</a> and <a href="nav-menus.php">menus</a>.

				<h4>Application URL</h4>
				<?php
				if (isset($options['p_videowhisper_support']) && !$options['appURL'] && $options['p_videowhisper_support'])
				{
					$options['appURL'] = get_permalink($options['p_videowhisper_support']);
					echo 'Local support page detected: Save changes to apply!<br>';
				}
				?>
				<input name="appURL" type="text" id="appURL" size="80" maxlength="256" value="<?php echo esc_attr( $options['appURL'] ); ?>"/>
				<br>Default application URL, used for access URLs (confirm contact, access ticket).
				<br>Local: <?php echo ( ($options['p_videowhisper_support'] ?? false) ? get_permalink($options['p_videowhisper_support']) : 'Not define, yet: Setup from Pages tab.' ) ?>


				<h4>Setup Pages</h4>
				<select name="disableSetupPages" id="disableSetupPages">
				  <option value="0" <?php echo $options['disableSetupPages'] ? '' : 'selected'; ?>>Yes</option>
				  <option value="1" <?php echo $options['disableSetupPages'] ? 'selected' : ''; ?>>No</option>
				</select>
				<br>Create pages for main functionality. Also creates a menu with these pages (VideoWhisper) that can be added to themes.

<h3>Feature Pages</h3>
				These pages are required for specific functionality. If you edit pages with shortcodes to add extra content, make sure shortcodes remain present.
								<?php

							$pages   = self::setupPagesList();
							$content = self::setupPagesContent();

							//site pages
							$args   = array(
								'sort_order'   => 'asc',
								'sort_column'  => 'post_title',
								'hierarchical' => 1,
								'post_type'    => 'page',
							);
							$sPages = get_pages( $args );

							foreach ( $pages as $shortcode => $title )
							{
								$pid = sanitize_text_field( $options[ 'p_' . $shortcode ] ?? 0 );
								if ( $pid != '' )
								{

									echo '<h4>' . esc_html( $title ) . '</h4>';
									echo '<select name="p_' . esc_attr( $shortcode ) . '" id="p_' . esc_attr( $shortcode ) . '">';
									echo '<option value="0">Undefined: Reset</option>';
									foreach ( $sPages as $sPage )
									{
										echo '<option value="' . esc_attr( $sPage->ID ) . '" ' . ( ( $pid == $sPage->ID ) ? 'selected' : '' ) . '>' . esc_html( $sPage->ID ) . '. ' . esc_html( $sPage->post_title ) . ' - ' . esc_html( $sPage->post_status ) . '</option>' . "\r\n";
									}
									echo '</select><br>';
									if ( $pid )
									{
										echo '<a href="' . get_permalink( $pid ) . '">view</a> | ';
									}
									if ( $pid )
									{
										echo '<a href="post.php?post=' . esc_attr( $pid ) . '&action=edit">edit</a> | ';
									}
									echo 'Default content: ' . ( array_key_exists( $shortcode, $content ) ? esc_html( $content[ $shortcode ] ) : esc_html( "[$shortcode]" ) ) . '';

								}
							}

				break;

			case 'server':
			?>
			<h4>Application URL</h4>
			<?php
			if (isset($options['appURL']) && !$options['appURL'] && isset($options['p_videowhisper_support']))
			{
				$options['appURL'] = get_permalink($options['p_videowhisper_support']);
				echo 'Local support page detected: Save changes to apply!<br>';
			}
			?>
			<input name="appURL" type="text" id="appURL" size="80" maxlength="256" value="<?php echo esc_attr( $options['appURL'] ); ?>"/>
			<br>Default application URL, for access links (confirm, view ticket).
			<br>Local: <?php echo ( $options['p_videowhisper_support'] ?? false ? get_permalink($options['p_videowhisper_support']) : 'Not defined, yet: Setup from Pages tab.' ) ?>

			<h4>Uploads Path</h4>
			<input name="uploadsPath" type="text" id="uploadsPath" size="80" maxlength="256" value="<?php echo esc_attr( $options['uploadsPath'] ); ?>"/>
							<?php
						echo '<br>Default: ' . esc_html( $optionsDefault['uploadsPath'] );
						echo '<br>WordPress Path: ' . get_home_path();
						$upload_dir = wp_upload_dir();
						echo '<br>Uploads Path: ' . esc_html( $upload_dir['basedir'] );
						if ( ! strstr( $options['uploadsPath'], get_home_path() ) )
						{
							echo '<br><b>Warning: Uploaded files may not be accessible by web.</b>';
						}
						echo '<br>WordPress URL: ' . get_site_url();
			?>
			<br>Windows sample path: C:/Inetpub/vhosts/yoursite.com/httpdocs/wp-content/uploads/vwSupport
			
			<h4>CORS Access-Control-Allow-Origin</h4>

			<input name="corsACLO" type="text" id="corsACLO" size="80" maxlength="256" value="<?php echo esc_attr( $options['corsACLO'] ); ?>"/>
			<br>Not required for using on this site, only for embedding on other sites or development environments. Enable external web access from live support deployed on these domains as CSV (comma separated values). Ex:  https://myfirstsite.com , https://mysecondsite.com , http://localhost:3000

			<h4>Demo Mode</h4>
			<select name="demoMode" id="demoMode">
			  <option value="0" <?php echo $options['demoMode'] ? '' : 'selected'; ?>>No</option>
			  <option value="1" <?php echo $options['demoMode'] == '1' ? 'selected' : ''; ?>>Yes</option>
			</select>
			<br>By default plugin is in demo mode, warning users that new conversations are not monitored. Disable for production use, when you start monitoring the conversations.

			<h4>Developer Mode</h4>
			<select name="devMode" id="devMode">
			  <option value="0" <?php echo $options['devMode'] ? '' : 'selected'; ?>>No</option>
			  <option value="1" <?php echo $options['devMode'] == '1' ? 'selected' : ''; ?>>Yes</option>
			</select>
			<br>Return various debug information including queries, confirmation links for review and testing during development. Should be disabled at runtime.

			<?php
			break;
			}

			//add button at end
			if ( ! in_array( $active_tab, array( 'setup', 'support', 'reset', 'requirements', 'billing', 'accounts', 'logs') ) )
			{
				submit_button();
			}


			echo '</form>';
}

}
