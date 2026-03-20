<?php
// Lade Free Download Handler wenn nicht im Admin
if ( ! is_admin() ) {
	require_once mp_plugin_dir( 'includes/public/class-mp-free-download.php' );
}
