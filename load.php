<?php

if ( !defined( 'WP_ROUTINES_PLUGIN_FILE' ) ) {
	WP_Routines\Routines::get_instance();
	define( 'WP_ROUTINES_PLUGIN_DIR', ( __DIR__ ) );
	define( 'WP_ROUTINES_PLUGIN_FILE', ( __FILE__ ) );
}