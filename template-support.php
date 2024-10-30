<?php
/*
Template Name: VideoWhisper Support - Full Page, without Site Template
*/

// as this full page app template does not include site header/footer, all scripts and css need to be loaded manually

defined( 'ABSPATH' ) or exit;

?><!doctype html>
<html lang="en">
	<head>
		<meta charset="utf-8"/>
		<meta name="viewport" content="width=device-width,initial-scale=1,shrink-to-fit=no"/>
		<link rel="manifest" href="./manifest.json"/>
		<link rel="stylesheet" href="<?php echo esc_attr( plugin_dir_url( __FILE__ ) ) . '/semantic/semantic.min.css'; ?>">
<?php
		$CSSfiles = scandir( dirname( __FILE__ ) . '/static/css/' );
foreach ( $CSSfiles as $filename ) {
	if ( strpos( $filename, '.css' ) && ! strpos( $filename, '.css.map' ) ) {
		echo '<link rel="stylesheet" href="' . esc_attr( plugin_dir_url( __FILE__ ) ) . '/static/css/' . esc_attr( $filename ) . '">';
	}
}
?>
		  <title><?php esc_attr_e( 'Support', 'live-support-tickets' ); ?></title>
		  <script src="<?php echo includes_url() . 'js/jquery/jquery.js'; ?>" type="text/javascript"></script>
		  <script src="<?php echo esc_attr( plugin_dir_url( __FILE__ ) ) . '/semantic/semantic.min.js'; ?>"></script>
	</head>
		<body>
<?php
if ( have_posts() ) :
	while ( have_posts() ) :
		the_post();
		the_content();
endwhile;
else :
	echo 'No content: ' . get_the_ID();
endif;

		$JSfiles = scandir( dirname( __FILE__ ) . '/static/js/' );
foreach ( $JSfiles as $filename ) {
	if ( strpos( $filename, '.js' ) && ! strpos( $filename, '.js.map' ) ) { // && !strstr($filename,'runtime~')
		echo '<script type="text/javascript" src="' . esc_attr( plugin_dir_url( __FILE__ ) ) . '/static/js/' . esc_attr( $filename ) . '"></script>';
	}
}
?>