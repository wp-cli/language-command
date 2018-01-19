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
	 * Installs a given language.
	 *
	 * Downloads the language pack from WordPress.org.
	 *
	 * ## OPTIONS
	 *
	 * <language>...
	 * : Language code to install.
	 *
	 * [--activate]
	 * : If set, the language will be activated immediately after install.
	 *
	 * ## EXAMPLES
	 *
	 *     # Install the Japanese language.
	 *     $ wp language core install ja
	 *     Success: Language installed.
	 *
	 * @subcommand install
	 */
	public function install( $args, $assoc_args ) {
		$language_codes = $args;

		if(  1 < count( $language_codes )  &&  in_array( true , $assoc_args , true ) ){
			\WP_CLI::error( 'Only a single language can be active.' );
		}

		$available = $this->get_installed_languages();

		foreach ($language_codes as $language_code) {

			if ( in_array( $language_code, $available, true ) ) {
				\WP_CLI::warning( "Language '{$language_code}' already installed." );
			} else {
				$response = $this->download_language_pack( $language_code );

				if ( is_wp_error( $response ) ) {
					\WP_CLI::error( $response );
				} else {
					\WP_CLI::success( "Language installed." );
				}
			}

			if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'activate' ) ) {
				$this->activate( array( $language_code ), array() );
			}
		}
	}

	/**
	 * Uninstalls a given language.
	 *
	 * ## OPTIONS
	 *
	 * <language>...
	 * : Language code to uninstall.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp language core uninstall ja
	 *     Success: Language uninstalled.
	 *
	 * @subcommand uninstall
	 */
	public function uninstall( $args, $assoc_args ) {
		global $wp_filesystem;

		$language_codes = $args;

		$available = $this->get_installed_languages();

		foreach ($language_codes as $language_code) {

			if ( ! in_array( $language_code, $available ) ) {
				\WP_CLI::error( "Language not installed." );
			}

			$dir = 'core' === $this->obj_type ? '' : "/$this->obj_type";
			$files = scandir( WP_LANG_DIR . $dir );
			if ( ! $files ) {
				\WP_CLI::error( "No files found in language directory." );
			}

			$current_locale = get_locale();
			if ( $language_code === $current_locale ) {
				\WP_CLI::warning( "The '{$language_code}' language is active." );
				exit;
			}

			// As of WP 4.0, no API for deleting a language pack
			WP_Filesystem();
			$deleted = false;
			foreach ( $files as $file ) {
				if ( '.' === $file[0] || is_dir( $file ) ) {
					continue;
				}
				$extension_length = strlen( $language_code ) + 4;
				$ending = substr( $file, -$extension_length );
				if ( ! in_array( $file, array( $language_code . '.po', $language_code . '.mo' ) ) && ! in_array( $ending, array( '-' . $language_code . '.po', '-' . $language_code . '.mo' ) ) ) {
					continue;
				}
				$deleted = $wp_filesystem->delete( WP_LANG_DIR . $dir . '/' . $file );
			}

			if ( $deleted ) {
				\WP_CLI::success( "Language uninstalled." );
			} else {
				\WP_CLI::error( "Couldn't uninstall language." );
			}
		}
	}

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
