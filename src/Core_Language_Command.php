<?php

/**
 * Installs, activates, and manages core language packs.
 *
 * ## EXAMPLES
 *
 *     # Install the Dutch core language pack.
 *     $ wp language core install nl_NL
 *     Success: Language installed.
 *
 *     # Activate the Dutch core language pack.
 *     $ wp language core activate nl_NL
 *     Success: Language activated.
 *
 *     # Uninstall the Dutch core language pack.
 *     $ wp language core uninstall nl_NL
 *     Success: Language uninstalled.
 *
 *     # List installed core language packages.
 *     $ wp language core list --status=installed
 *     +----------+--------------+-------------+-----------+-----------+---------------------+
 *     | language | english_name | native_name | status    | update    | updated             |
 *     +----------+--------------+-------------+-----------+-----------+---------------------+
 *     | nl_NL    | Dutch        | Nederlands  | installed | available | 2016-05-13 08:12:50 |
 *     +----------+--------------+-------------+-----------+-----------+---------------------+
 */
class Core_Language_Command extends WP_CLI\CommandWithTranslation {
	protected $obj_type = 'core';

	/**
	 * Activates a given language.
	 *
	 * ## OPTIONS
	 *
	 * <language>
	 * : Language code to activate.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp language core activate ja
	 *     Success: Language activated.
	 *
	 * @subcommand activate
	 */
	public function activate( $args, $assoc_args ) {

		list( $language_code ) = $args;

		$available = $this->get_installed_languages();

		if ( ! in_array( $language_code, $available ) ) {
			\WP_CLI::error( "Language not installed." );
		}

		if ( $language_code == 'en_US' ) {
			$language_code = '';
		}

		if ( $language_code === get_locale() ) {
			\WP_CLI::warning( "Language '{$language_code}' already active." );

			return;
		}

		update_option( 'WPLANG', $language_code );
		\WP_CLI::success( "Language activated." );
	}
}
