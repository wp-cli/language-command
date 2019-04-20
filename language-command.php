<?php

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

$autoload = dirname( __FILE__ ) . '/vendor/autoload.php';

if ( file_exists( $autoload ) ) {
	require_once $autoload;
}

$wpcli_language_check_requirements = function() {
	if ( \WP_CLI\Utils\wp_version_compare( '4.0', '<' ) ) {
		WP_CLI::error( 'Requires WordPress 4.0 or greater.' );
	}
};

WP_CLI::add_command( 'language core', 'Core_Language_Command', array(
	'before_invoke' => $wpcli_language_check_requirements )
);

WP_CLI::add_command( 'language plugin', 'Plugin_Language_Command', array(
		'before_invoke' => $wpcli_language_check_requirements )
);

WP_CLI::add_command( 'language theme', 'Theme_Language_Command', array(
		'before_invoke' => $wpcli_language_check_requirements )
);

WP_CLI::add_hook( 'after_add_command:site', function () {
	WP_CLI::add_command( 'site switch-language', 'Site_Switch_Language_Command' );
} );

if ( class_exists( 'WP_CLI\Dispatcher\CommandNamespace' ) ) {
	WP_CLI::add_command( 'language', 'Language_Namespace' );
}
