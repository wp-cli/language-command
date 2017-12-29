<?php

/**
 * Installs, activates, and manages plugin language packs.
 *
 * ## EXAMPLES
 *
 *     # Install the Dutch theme language pack.
 *     $ wp language plugin install nl_NL
 *     Success: Language installed.
 *
 *     # Activate the Dutch theme language pack.
 *     $ wp language plugin activate nl_NL
 *     Success: Language activated.
 *
 *     # Uninstall the Dutch theme language pack.
 *     $ wp language plugin uninstall nl_NL
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

	/**
	 * Lists all available languages.
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

		if ( $all ) {
			$args = array_map( function( $file ){
				return \WP_CLI\Utils\get_plugin_name( $file );
			}, array_keys( $this->get_all_plugins() ) );
		}

		$available      = $this->get_installed_languages();
		$updates        = $this->get_translation_updates();
		$current_locale = get_locale();

		$translations = array();

		foreach ( $args as $plugin ) {
			$plugin_translations = $this->get_all_languages( $plugin );

			foreach ( $plugin_translations as $key => $translation ) {
				$translation['plugin'] = $plugin;

				$translation['status'] = in_array( $translation['language'], $available, true ) ? 'installed' : 'uninstalled';

				if ( $current_locale === $translation['language'] ) {
					$translation['status'] = 'active';
				}

				$update = wp_list_filter( $updates, array(
					'language' => $translation['language']
				) );

				$translation['update'] = $update ? 'available' : 'none';

				$fields = array_keys( $translation );
				foreach ( $fields as $field ) {
					if ( isset( $assoc_args[ $field ] ) && $assoc_args[ $field ] !== $translation[ $field ] ) {
						unset( $plugin_translations[ $key ] );
					}
				}

				$translations[] = $translation;
			}
		}

		$formatter = $this->get_formatter( $assoc_args );
		$formatter->display_items( $translations );
	}

	/**
	 * Installs a given language.
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
	 *     # Install the Japanese language.
	 *     $ wp language core install ja
	 *     Success: Language installed.
	 *
	 * @subcommand install
	 */
	public function install( $args, $assoc_args ) {
		$plugin         = array_shift( $args );
		$language_codes = $args;

		$available = $this->get_installed_languages();

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
