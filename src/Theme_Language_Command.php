<?php

/**
 * Installs, activates, and manages theme language packs.
 *
 * ## EXAMPLES
 *
 *     # Install the Dutch theme language pack.
 *     $ wp language theme install nl_NL
 *     Success: Language installed.
 *
 *     # Activate the Dutch theme language pack.
 *     $ wp language theme activate nl_NL
 *     Success: Language activated.
 *
 *     # Uninstall the Dutch theme language pack.
 *     $ wp language theme uninstall nl_NL
 *     Success: Language uninstalled.
 *
 *     # List installed theme language packages.
 *     $ wp language theme list --status=installed
 *     +----------+--------------+-------------+-----------+-----------+---------------------+
 *     | language | english_name | native_name | status    | update    | updated             |
 *     +----------+--------------+-------------+-----------+-----------+---------------------+
 *     | nl_NL    | Dutch        | Nederlands  | installed | available | 2016-05-13 08:12:50 |
 *     +----------+--------------+-------------+-----------+-----------+---------------------+
 */
class Theme_Language_Command extends WP_CLI\CommandWithTranslation {

	protected $obj_type = 'themes';

	/**
	 * Lists all available languages.
	 *
	 * ## OPTIONS
	 *
	 * [<theme>...]
	 * : One or more themes to list languages for.
	 *
	 * [--all]
	 * : If set, available languages for all themes will be listed.
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
	 * * theme
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
	 *     $ wp language theme list --fields=language,english_name,status
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
				return \WP_CLI\Utils\get_theme_name( $file );
			}, array_keys( wp_get_themes() ) );
		}

		$available      = $this->get_installed_languages();
		$updates        = $this->get_translation_updates();
		$current_locale = get_locale();

		$translations = array();

		foreach ( $args as $theme ) {
			$theme_translations = $this->get_all_languages( $theme );

			foreach ( $theme_translations as $key => $translation ) {
				$translation['theme'] = $theme;

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
						unset( $theme_translations[ $key ] );
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
	 * <theme>
	 * : Theme to install language for.
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
		$theme         = array_shift( $args );
		$language_codes = $args;

		$available = $this->get_installed_languages();

		foreach ( $language_codes as $language_code ) {

			if ( in_array( $language_code, $available, true ) ) {
				\WP_CLI::warning( "Language '{$language_code}' already installed." );
			} else {
				$response = $this->download_language_pack( $language_code, $theme );

				if ( is_wp_error( $response ) ) {
					\WP_CLI::error( $response );
				} else {
					\WP_CLI::success( 'Language installed.' );
				}
			}
		}
	}

}
