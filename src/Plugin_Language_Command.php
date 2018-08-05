<?php

/**
 * Installs, activates, and manages plugin language packs.
 *
 * ## EXAMPLES
 *
 *     # Install the Dutch theme language pack.
 *     $ wp language plugin install hello-dolly nl_NL
 *     Success: Language installed.
 *
 *     # Uninstall the Dutch theme language pack.
 *     $ wp language plugin uninstall hello-dolly nl_NL
 *     Success: Language uninstalled.
 *
 *     # List installed theme language packages.
 *     $ wp language plugin list --status=installed
 *     +----------+--------------+-------------+-----------+-----------+---------------------+
 *     | language | english_name | native_name | status    | update    | updated             |
 *     +----------+--------------+-------------+-----------+-----------+---------------------+
 *     | nl_NL    | Dutch        | Nederlands  | installed | available | 2016-05-13 08:12:50 |
 *     +----------+--------------+-------------+-----------+-----------+---------------------+
 */
class Plugin_Language_Command extends WP_CLI\CommandWithTranslation {
	protected $obj_type = 'plugins';

	protected $obj_fields = array(
		'plugin',
		'language',
		'english_name',
		'native_name',
		'status',
		'update',
		'updated',
	);

	/**
	 * Lists all available languages for one or more plugins.
	 *
	 * ## OPTIONS
	 *
	 * [<plugin>...]
	 * : One or more plugins to list languages for.
	 *
	 * [--all]
	 * : If set, available languages for all plugins will be listed.
	 *
	 * [--field=<field>]
	 * : Display the value of a single field.
	 *
	 * [--<field>=<value>]
	 * : Filter results by key=value pairs.
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific fields.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 * ---
	 *
	 * ## AVAILABLE FIELDS
	 *
	 * These fields will be displayed by default for each translation:
	 *
	 * * plugin
	 * * language
	 * * english_name
	 * * native_name
	 * * status
	 * * update
	 * * updated
	 *
	 * These fields are optionally available:
	 *
	 * * version
	 * * package
	 *
	 * ## EXAMPLES
	 *
	 *     # List language,english_name,status fields of available languages.
	 *     $ wp language plugin list --fields=language,english_name,status
	 *     +----------------+-------------------------+-------------+
	 *     | language       | english_name            | status      |
	 *     +----------------+-------------------------+-------------+
	 *     | ar             | Arabic                  | uninstalled |
	 *     | ary            | Moroccan Arabic         | uninstalled |
	 *     | az             | Azerbaijani             | uninstalled |
	 *
	 * @subcommand list
	 */
	public function list_( $args, $assoc_args ) {
		$all = \WP_CLI\Utils\get_flag_value( $assoc_args, 'all', false );

		if ( ! $all && empty( $args ) ) {
			WP_CLI::error( 'Please specify one or more plugins, or use --all.' );
		}

		if ( $all ) {
			$args = array_map( function( $file ){
				return \WP_CLI\Utils\get_plugin_name( $file );
			}, array_keys( $this->get_all_plugins() ) );

			if ( empty( $args ) ) {
				WP_CLI::success( 'No plugins installed.' );
				return;
			}
		}

		$updates        = $this->get_translation_updates();
		$current_locale = get_locale();

		$translations = array();

		foreach ( $args as $plugin ) {
			$installed_translations = $this->get_installed_languages( $plugin );
			$available_translations = $this->get_all_languages( $plugin );

			foreach ( $available_translations as $translation ) {
				$translation['plugin'] = $plugin;
				$translation['status'] = in_array( $translation['language'], $installed_translations, true ) ? 'installed' : 'uninstalled';

				if ( $current_locale === $translation['language'] ) {
					$translation['status'] = 'active';
				}

				$update = wp_list_filter( $updates, array(
					'language' => $translation['language']
				) );

				$translation['update'] = $update ? 'available' : 'none';

				// Support features like --status=active.
				foreach( array_keys( $translation ) as $field ) {
					if ( isset( $assoc_args[ $field ] ) && $assoc_args[ $field ] !== $translation[ $field ] ) {
						continue 2;
					}
				}

				$translations[] = $translation;
			}
		}

		$formatter = $this->get_formatter( $assoc_args );
		$formatter->display_items( $translations );
	}

	/**
	 * Checks if a given language is installed.
	 *
	 * Returns exit code 0 when installed, 1 when uninstalled.
	 *
	 * ## OPTIONS
	 *
	 * <plugin>
	 * : Plugin to check for.
	 *
	 * <language>...
	 * : The language code to check.
	 *
	 * ## EXAMPLES
	 *
	 *     # Check whether the German language is installed for Akismet; exit status 0 if installed, otherwise 1.
	 *     $ wp language plugin is-installed akismet de_DE
	 *     $ echo $?
	 *     1
	 *
	 * @subcommand is-installed
	 */
	public function is_installed( $args, $assoc_args = array() ) {
		$plugin         = array_shift( $args );
		$language_codes = (array) $args;

		$available = $this->get_installed_languages( $plugin );

		foreach ( $language_codes as $language_code ) {
			if ( ! in_array( $language_code, $available, true ) ) {
				\WP_CLI::halt( 1 );
			}
		}

		\WP_CLI::halt( 0 );
	}

	/**
	 * Installs a given language for a plugin.
	 *
	 * Downloads the language pack from WordPress.org.
	 *
	 * ## OPTIONS
	 *
	 * <plugin>
	 * : Plugin to install language for.
	 *
	 * <language>...
	 * : Language code to install.
	 *
	 * ## EXAMPLES
	 *
	 *     # Install the Japanese language for Akismet.
	 *     $ wp language plugin install akismet ja
	 *     Success: Language installed.
	 *
	 * @subcommand install
	 */
	public function install( $args, $assoc_args ) {
		$plugin         = array_shift( $args );
		$language_codes = (array) $args;

		$available = $this->get_installed_languages( $plugin );

		foreach ( $language_codes as $language_code ) {

			if ( in_array( $language_code, $available, true ) ) {
				\WP_CLI::warning( "Language '{$language_code}' already installed." );
			} else {
				$response = $this->download_language_pack( $language_code, $plugin );

				if ( is_wp_error( $response ) ) {
					\WP_CLI::error( $response );
				} else {
					\WP_CLI::success( 'Language installed.' );
				}
			}
		}
	}

	/**
	 * Uninstalls a given language for a plugin.
	 *
	 * ## OPTIONS
	 *
	 * <plugin>
	 * : Plugin to uninstall language for.
	 *
	 * <language>...
	 * : Language code to uninstall.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp language plugin uninstall hello-dolly ja
	 *     Success: Language uninstalled.
	 *
	 * @subcommand uninstall
	 */
	public function uninstall( $args, $assoc_args ) {
		/* @var WP_Filesystem_Base $wp_filesystem */
		global $wp_filesystem;

		$plugin         = array_shift( $args );
		$language_codes = (array) $args;
		$current_locale = get_locale();

		$dir   = WP_LANG_DIR . "/$this->obj_type";
		$files = scandir( $dir );

		if ( ! $files ) {
			\WP_CLI::error( 'No files found in language directory.' );
		}

		// As of WP 4.0, no API for deleting a language pack
		WP_Filesystem();
		$available = $this->get_installed_languages( $plugin );

		foreach ( $language_codes as $language_code ) {
			if ( ! in_array( $language_code, $available, true ) ) {
				\WP_CLI::error( 'Language not installed.' );
			}

			if ( $language_code === $current_locale ) {
				\WP_CLI::warning( "The '{$language_code}' language is active." );
				exit;
			}

			if ( $wp_filesystem->delete( "{$dir}/{$plugin}-{$language_code}.po" ) && $wp_filesystem->delete( "{$dir}/{$plugin}-{$language_code}.mo" ) ) {
				\WP_CLI::success( 'Language uninstalled.' );
			} else {
				\WP_CLI::error( "Couldn't uninstall language." );
			}
		}
	}

	/**
	 * Updates installed languages for one or more plugins.
	 *
	 * ## OPTIONS
	 *
	 * [<plugin>...]
	 * : One or more plugins to update languages for.
	 *
	 * [--all]
	 * : If set, languages for all plugins will be updated.
	 *
	 * [--dry-run]
	 * : Preview which translations would be updated.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp language plugin update --all
	 *     Updating 'Japanese' translation for Akismet 3.1.11...
	 *     Downloading translation from https://downloads.wordpress.org/translation/plugin/akismet/3.1.11/ja.zip...
	 *     Translation updated successfully.
	 *     Success: Updated 1/1 translation.
	 *
	 * @subcommand update
	 */
	public function update( $args, $assoc_args ) {
		$all = \WP_CLI\Utils\get_flag_value( $assoc_args, 'all', false );

		if ( ! $all && empty( $args ) ) {
			WP_CLI::error( 'Please specify one or more plugins, or use --all.' );
		}

		if ( $all ) {
			$args = array_map( function ( $file ) {
				return \WP_CLI\Utils\get_plugin_name( $file );
			}, array_keys( $this->get_all_plugins() ) );
			if ( empty( $args ) ) {
				WP_CLI::success( 'No plugins installed.' );

				return;
			}
		}

		parent::update( $args, $assoc_args );
	}

	/**
	 * Gets all available plugins.
	 *
	 * Uses the same filter core uses in plugins.php to determine which plugins
	 * should be available to manage through the WP_Plugins_List_Table class.
	 *
	 * @return array
	 */
	private function get_all_plugins() {
		return apply_filters( 'all_plugins', get_plugins() );
	}
}
