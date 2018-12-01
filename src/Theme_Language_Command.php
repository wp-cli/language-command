<?php

/**
 * Installs, activates, and manages theme language packs.
 *
 * ## EXAMPLES
 *
 *     # Install the Dutch theme language pack.
 *     $ wp language theme install twentyten nl_NL
 *     Success: Language installed.
 *
 *     # Uninstall the Dutch theme language pack.
 *     $ wp language theme uninstall twentyten nl_NL
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

	protected $obj_fields = array(
		'theme',
		'language',
		'english_name',
		'native_name',
		'status',
		'update',
		'updated',
	);

	/**
	 * Lists all available languages for one or more themes.
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

		if ( ! $all && empty( $args ) ) {
			WP_CLI::error( 'Please specify one or more themes, or use --all.' );
		}

		if ( $all ) {
			$args = array_map( function( $file ){
				return \WP_CLI\Utils\get_theme_name( $file );
			}, array_keys( wp_get_themes() ) );

			if ( empty( $args ) ) {
				WP_CLI::success( 'No themes installed.' );
				return;
			}
		}

		$updates        = $this->get_translation_updates();
		$current_locale = get_locale();

		$translations = array();

		foreach ( $args as $theme ) {
			$installed_translations = $this->get_installed_languages( $theme );
			$available_translations = $this->get_all_languages( $theme );

			foreach ( $available_translations as $translation ) {
				$translation['theme'] = $theme;
				$translation['status'] = in_array( $translation['language'], $installed_translations, true ) ? 'installed' : 'uninstalled';

				if ( $current_locale === $translation['language'] ) {
					$translation['status'] = 'active';
				}

				$update = wp_list_filter( $updates, array(
					'language' => $translation['language'],
					'type'     => 'theme',
					'slug'     => $theme,
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
	 * <theme>
	 * : Theme to check for.
	 *
	 * <language>...
	 * : The language code to check.
	 *
	 * ## EXAMPLES
	 *
	 *     # Check whether the German language is installed for Twenty Seventeen; exit status 0 if installed, otherwise 1.
	 *     $ wp language theme is-installed twentyseventeen de_DE
	 *     $ echo $?
	 *     1
	 *
	 * @subcommand is-installed
	 */
	public function is_installed( $args, $assoc_args = array() ) {
		$theme          = array_shift( $args );
		$language_codes = (array) $args;

		$available = $this->get_installed_languages( $theme );

		foreach ( $language_codes as $language_code ) {
			if ( ! in_array( $language_code, $available, true ) ) {
				\WP_CLI::halt( 1 );
			}
		}

		\WP_CLI::halt( 0 );
	}

	/**
	 * Installs a given language for a theme.
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
	 *     # Install the Japanese language for Twenty Seventeen.
	 *     $ wp language theme install twentyseventeen ja
	 *     Downloading translation from https://downloads.wordpress.org/translation/theme/twentyseventeen/1.3/ja.zip...
	 *     Unpacking the update...
	 *     Installing the latest version...
	 *     Translation updated successfully.
	 *     Language 'ja' installed.
	 *     Success: Installed 1 of 1 languages.
	 *
	 * @subcommand install
	 */
	public function install( $args, $assoc_args ) {
		$theme          = array_shift( $args );
		$language_codes = (array) $args;
		$count          = count( $language_codes );

		$available = $this->get_installed_languages( $theme );

		$successes = $errors = $skips = 0;
		foreach ( $language_codes as $language_code ) {

			if ( in_array( $language_code, $available, true ) ) {
				\WP_CLI::log( "Language '{$language_code}' already installed." );
				$skips++;
			} else {
				$response = $this->download_language_pack( $language_code, $theme );

				if ( is_wp_error( $response ) ) {
					\WP_CLI::warning( $response );
					\WP_CLI::log( "Language '{$language_code}' not installed." );

					// Skip if translation is not yet available.
					if ( 'not_found' === $response->get_error_code() ) {
						$skips++;
					} else {
						$errors++;
					}
				} else {
					\WP_CLI::log( "Language '{$language_code}' installed." );
					$successes++;
				}
			}
		}

		\WP_CLI\Utils\report_batch_operation_results( 'language', 'install', $count, $successes, $errors, $skips );
	}

	/**
	 * Uninstalls a given language for a theme.
	 *
	 * ## OPTIONS
	 *
	 * <theme>
	 * : Theme to uninstall language for.
	 *
	 * <language>...
	 * : Language code to uninstall.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp language theme uninstall twentyten ja
	 *     Success: Language uninstalled.
	 *
	 * @subcommand uninstall
	 */
	public function uninstall( $args, $assoc_args ) {
		/* @var WP_Filesystem_Base $wp_filesystem */
		global $wp_filesystem;

		$theme          = array_shift( $args );
		$language_codes = (array) $args;
		$current_locale = get_locale();

		$dir   = WP_LANG_DIR . "/$this->obj_type";
		$files = scandir( $dir );

		if ( ! $files ) {
			\WP_CLI::error( 'No files found in language directory.' );
		}

		// As of WP 4.0, no API for deleting a language pack
		WP_Filesystem();
		$available = $this->get_installed_languages( $theme );

		foreach ( $language_codes as $language_code ) {
			if ( ! in_array( $language_code, $available, true ) ) {
				WP_CLI::error( 'Language not installed.' );
			}

			if ( $language_code === $current_locale ) {
				WP_CLI::warning( "The '{$language_code}' language is active." );
				exit;
			}

			if ( $wp_filesystem->delete( "{$dir}/{$theme}-{$language_code}.po" ) && $wp_filesystem->delete( "{$dir}/{$theme}-{$language_code}.mo" ) ) {
				WP_CLI::success( 'Language uninstalled.' );
			} else {
				WP_CLI::error( "Couldn't uninstall language." );
			}
		}
	}

	/**
	 * Updates installed languages for one or more themes.
	 *
	 * ## OPTIONS
	 *
	 * [<theme>...]
	 * : One or more themes to update languages for.
	 *
	 * [--all]
	 * : If set, languages for all themes will be updated.
	 *
	 * [--dry-run]
	 * : Preview which translations would be updated.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp language theme update --all
	 *     Updating 'Japanese' translation for Twenty Fifteen 1.5...
	 *     Downloading translation from https://downloads.wordpress.org/translation/theme/twentyfifteen/1.5/ja.zip...
	 *     Translation updated successfully.
	 *     Success: Updated 1/1 translation.
	 *
	 * @subcommand update
	 */
	public function update( $args, $assoc_args ) {
		$all = \WP_CLI\Utils\get_flag_value( $assoc_args, 'all', false );

		if ( ! $all && empty( $args ) ) {
			WP_CLI::error( 'Please specify one or more themes, or use --all.' );
		}

		if ( $all ) {
			$args = array_map( '\WP_CLI\Utils\get_theme_name', array_keys( wp_get_themes() ) );
			if ( empty( $args ) ) {
				WP_CLI::success( 'No themes installed.' );

				return;
			}
		}

		parent::update( $args, $assoc_args );
	}
}
