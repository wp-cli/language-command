<?php

/**
 * Installs, activates, and manages theme language packs.
 *
 * ## EXAMPLES
 *
 *     # Install the Dutch theme language pack for Twenty Ten.
 *     $ wp language theme install twentyten nl_NL
 *     Downloading translation from https://downloads.wordpress.org/translation/theme/twentyten/4.0/nl_NL.zip...
 *     Unpacking the update...
 *     Installing the latest version...
 *     Removing the old version of the translation...
 *     Translation updated successfully.
 *     Language 'nl_NL' installed.
 *     Success: Installed 1 of 1 languages.
 *
 *     # Uninstall the Dutch theme language pack for Twenty Ten.
 *     $ wp language theme uninstall twentyten nl_NL
 *     Language 'nl_NL' for 'twentyten' uninstalled.
 *     +-----------+--------+-------------+
 *     | name      | locale | status      |
 *     +-----------+--------+-------------+
 *     | twentyten | nl_NL  | uninstalled |
 *     +-----------+--------+-------------+
 *     Success: Uninstalled 1 of 1 languages.
 *
 *     # List installed theme language packs for Twenty Ten.
 *     $ wp language theme list twentyten --status=installed
 *     +-----------+----------+--------------+-------------+-----------+--------+---------------------+
 *     | theme     | language | english_name | native_name | status    | update | updated             |
 *     +-----------+----------+--------------+-------------+-----------+--------+---------------------+
 *     | twentyten | nl_NL    | Dutch        | Nederlands  | installed | none   | 2023-12-29 21:21:39 |
 *     +-----------+----------+--------------+-------------+-----------+--------+---------------------+
 *
 * @package wp-cli
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
	 *   - count
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
	 *     # List available language packs for the theme.
	 *     $ wp language theme list twentyten --fields=language,english_name,status
	 *     +----------------+-------------------------+-------------+
	 *     | language       | english_name            | status      |
	 *     +----------------+-------------------------+-------------+
	 *     | ar             | Arabic                  | uninstalled |
	 *     | ary            | Moroccan Arabic         | uninstalled |
	 *     | az             | Azerbaijani             | uninstalled |
	 *
	 * @subcommand list
	 *
	 * @param string[] $args Positional arguments.
	 * @param array{all?: bool, field?: string, format: string, theme?: string, language?: string, english_name?: string, native_name?: string, status?: string, update?: string, updated?: string} $assoc_args Associative arguments.
	 */
	public function list_( $args, $assoc_args ) {
		$all = \WP_CLI\Utils\get_flag_value( $assoc_args, 'all', false );

		if ( ! $all && empty( $args ) ) {
			WP_CLI::error( 'Please specify one or more themes, or use --all.' );
		}

		if ( $all ) {
			$args = array_map(
				function ( $file ) {
					return \WP_CLI\Utils\get_theme_name( $file );
				},
				array_keys( wp_get_themes() )
			);

			if ( empty( $args ) ) {
				WP_CLI::success( 'No themes installed.' );
				return;
			}
		}

		$updates        = $this->get_translation_updates();
		$current_locale = get_locale();

		$translations = array();
		$themes       = new \WP_CLI\Fetchers\Theme();

		foreach ( $args as $theme ) {

			if ( ! $themes->get( $theme ) ) {
				WP_CLI::warning( "Theme '{$theme}' not found." );
				continue;
			}

			$installed_translations = $this->get_installed_languages( $theme );
			$available_translations = $this->get_all_languages( $theme );

			foreach ( $available_translations as $translation ) {
				$translation['theme']  = $theme;
				$translation['status'] = in_array( $translation['language'], $installed_translations, true ) ? 'installed' : 'uninstalled';

				if ( $current_locale === $translation['language'] ) {
					$translation['status'] = 'active';
				}

				$filter_args = array(
					'language' => $translation['language'],
					'type'     => 'theme',
					'slug'     => $theme,
				);
				$update      = wp_list_filter( $updates, $filter_args );

				$translation['update'] = $update ? 'available' : 'none';

				// Support features like --status=active.
				foreach ( array_keys( $translation ) as $field ) {
					if ( isset( $assoc_args[ $field ] ) && ! in_array( $translation[ $field ], array_map( 'trim', explode( ',', $assoc_args[ $field ] ) ), true ) ) {
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
	 *
	 * @param non-empty-array<string> $args Positional arguments.
	 */
	public function is_installed( $args ) {
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
	 * [<theme>]
	 * : Theme to install language for.
	 *
	 * [--all]
	 * : If set, languages for all themes will be installed.
	 *
	 * <language>...
	 * : Language code to install.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format. Used when installing languages for all themes.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - summary
	 * ---
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
	 *
	 * @param string[] $args Positional arguments.
	 * @param array{all?: bool, format: string} $assoc_args Associative arguments.
	 */
	public function install( $args, $assoc_args ) {
		$all = \WP_CLI\Utils\get_flag_value( $assoc_args, 'all', false );

		if ( ! $all && count( $args ) < 2 ) {
			\WP_CLI::error( 'Please specify a theme, or use --all.' );
		}

		if ( $all ) {
			$this->install_many( $args, $assoc_args );
		} else {
			$this->install_one( $args, $assoc_args );
		}
	}

	/**
	 * Installs translations for a theme.
	 *
	 * @param array $args       Runtime arguments.
	 * @param array $assoc_args Runtime arguments.
	 */
	private function install_one( $args, $assoc_args ) {
		$theme          = array_shift( $args );
		$language_codes = $args;
		$count          = count( $language_codes );

		$available = $this->get_installed_languages( $theme );

		$successes = 0;
		$errors    = 0;
		$skips     = 0;
		foreach ( $language_codes as $language_code ) {

			if ( in_array( $language_code, $available, true ) ) {
				\WP_CLI::log( "Language '{$language_code}' already installed." );
				++$skips;
			} else {
				$response = $this->download_language_pack( $language_code, $theme );

				if ( is_wp_error( $response ) ) {
					\WP_CLI::warning( $response );
					\WP_CLI::log( "Language '{$language_code}' not installed." );

					// Skip if translation is not yet available.
					if ( 'not_found' === $response->get_error_code() ) {
						++$skips;
					} else {
						++$errors;
					}
				} else {
					\WP_CLI::log( "Language '{$language_code}' installed." );
					++$successes;
				}
			}
		}
		\WP_CLI\Utils\report_batch_operation_results( 'language', 'install', $count, $successes, $errors, $skips );
	}

	/**
	 * Installs translations for all installed themes.
	 *
	 * @param array $args       Runtime arguments.
	 * @param array $assoc_args Runtime arguments.
	 */
	private function install_many( $args, $assoc_args ) {
		$language_codes = (array) $args;

		/**
		 * @var \WP_Theme[] $themes
		 */
		$themes = wp_get_themes();

		if ( empty( $assoc_args['format'] ) ) {
			$assoc_args['format'] = 'table';
		}

		if ( in_array( $assoc_args['format'], array( 'json', 'csv' ), true ) ) {
			$logger = new \WP_CLI\Loggers\Quiet();
			\WP_CLI::set_logger( $logger );
		}

		if ( empty( $themes ) ) {
			\WP_CLI::success( 'No themes installed.' );
			return;
		}

		$count = count( $themes ) * count( $language_codes );

		$results = array();

		$successes = 0;
		$errors    = 0;
		$skips     = 0;
		foreach ( $themes as $theme_path => $theme_details ) {
			$theme_name = \WP_CLI\Utils\get_theme_name( $theme_path );

			$available = $this->get_installed_languages( $theme_name );

			/**
			 * @var string $display_name
			 */
			$display_name = $theme_details['Name'];

			foreach ( $language_codes as $language_code ) {
				$result = [
					'name'   => $theme_name,
					'locale' => $language_code,
				];

				if ( in_array( $language_code, $available, true ) ) {
					\WP_CLI::log( "Language '{$language_code}' for '{$display_name}' already installed." );
					$result['status'] = 'already installed';
					++$skips;
				} else {
					$response = $this->download_language_pack( $language_code, $theme_name );

					if ( is_wp_error( $response ) ) {
						\WP_CLI::warning( $response );
						\WP_CLI::log( "Language '{$language_code}' for '{$display_name}' not installed." );

						if ( 'not_found' === $response->get_error_code() ) {
							$result['status'] = 'not available';
							++$skips;
						} else {
							$result['status'] = 'not installed';
							++$errors;
						}
					} else {
						\WP_CLI::log( "Language '{$language_code}' for '{$display_name}' installed." );
						$result['status'] = 'installed';
						++$successes;
					}
				}

				$results[] = (object) $result;
			}
		}

		if ( 'summary' !== $assoc_args['format'] ) {
			\WP_CLI\Utils\format_items( $assoc_args['format'], $results, array( 'name', 'locale', 'status' ) );
		}

		\WP_CLI\Utils\report_batch_operation_results( 'language', 'install', $count, $successes, $errors, $skips );
	}

	/**
	 * Uninstalls a given language for a theme.
	 *
	 * ## OPTIONS
	 *
	 * [<theme>]
	 * : Theme to uninstall language for.
	 *
	 * [--all]
	 * : If set, languages for all themes will be uninstalled.
	 *
	 * <language>...
	 * : Language code to uninstall.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format. Used when installing languages for all themes.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - summary
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Uninstall the Japanese theme language pack for Twenty Ten.
	 *     $ wp language theme uninstall twentyten ja
	 *     Language 'ja' for 'twentyten' uninstalled.
	 *     +-----------+--------+-------------+
	 *     | name      | locale | status      |
	 *     +-----------+--------+-------------+
	 *     | twentyten | ja     | uninstalled |
	 *     +-----------+--------+-------------+
	 *     Success: Uninstalled 1 of 1 languages.
	 *
	 * @subcommand uninstall
	 *
	 * @param string[] $args Positional arguments.
	 * @param array{all?: bool, format: string} $assoc_args Associative arguments.
	 */
	public function uninstall( $args, $assoc_args ) {
		/** @var WP_Filesystem_Base $wp_filesystem */
		global $wp_filesystem;

		if ( empty( $assoc_args['format'] ) ) {
			$assoc_args['format'] = 'table';
		}

		if ( in_array( $assoc_args['format'], array( 'json', 'csv' ), true ) ) {
			$logger = new \WP_CLI\Loggers\Quiet();
			\WP_CLI::set_logger( $logger );
		}

		$all = \WP_CLI\Utils\get_flag_value( $assoc_args, 'all', false );

		if ( ! $all && count( $args ) < 2 ) {
			\WP_CLI::error( 'Please specify one or more themes, or use --all.' );
		}

		if ( $all ) {
			$themes = wp_get_themes();

			if ( empty( $themes ) ) {
				\WP_CLI::success( 'No themes installed.' );
				return;
			}

			$process_themes = array();
			foreach ( $themes as $theme_path => $theme_details ) {
				$theme_name = \WP_CLI\Utils\get_theme_name( $theme_path );
				array_push( $process_themes, $theme_name );
			}
		} else {
			$process_themes = array( array_shift( $args ) );
		}

		$language_codes = (array) $args;
		$current_locale = get_locale();

		$dir   = WP_LANG_DIR . "/$this->obj_type";
		$files = scandir( $dir );

		if ( ! $files ) {
			\WP_CLI::error( 'No files found in language directory.' );
		}

		$count = count( $process_themes ) * count( $language_codes );

		$results = array();

		$successes = 0;
		$errors    = 0;
		$skips     = 0;

		// As of WP 4.0, no API for deleting a language pack
		WP_Filesystem();

		/**
		 * @var string $theme
		 */
		foreach ( $process_themes as $theme ) {
			$available_languages = $this->get_installed_languages( $theme );

			foreach ( $language_codes as $language_code ) {
				$result = [
					'name'   => $theme,
					'locale' => $language_code,
					'status' => 'not available',
				];

				if ( ! in_array( $language_code, $available_languages, true ) ) {
					$result['status'] = 'not installed';
					\WP_CLI::warning( "Language '{$language_code}' not installed." );
					if ( $all ) {
						++$skips;
					} else {
						++$errors;
					}
					$results[] = (object) $result;
					continue;
				}

				if ( $language_code === $current_locale ) {
					\WP_CLI::warning( "The '{$language_code}' language is active." );
					exit;
				}

				$files_to_remove = array(
					"$theme-$language_code.po",
					"$theme-$language_code.mo",
					"$theme-$language_code.l10n.php",
				);

				$count_files_to_remove = 0;
				$count_files_removed   = 0;
				$had_one_file          = false;

				foreach ( $files as $file ) {
					if ( '.' === $file[0] || is_dir( $file ) ) {
						continue;
					}

					if (
						! in_array( $file, $files_to_remove, true ) &&
						! preg_match( "/$theme-$language_code-\w{32}\.json/", $file )
					) {
						continue;
					}

					$had_one_file = true;

					++$count_files_to_remove;

					if ( $wp_filesystem->delete( $dir . '/' . $file ) ) {
						++$count_files_removed;
					} else {
						\WP_CLI::error( "Couldn't uninstall language: $language_code from theme $theme." );
					}
				}

				if ( $count_files_to_remove === $count_files_removed ) {
					$result['status'] = 'uninstalled';
					++$successes;
					\WP_CLI::log( "Language '{$language_code}' for '{$theme}' uninstalled." );
				} elseif ( $count_files_removed ) {
					\WP_CLI::log( "Language '{$language_code}' for '{$theme}' partially uninstalled." );
					$result['status'] = 'partial uninstall';
					++$errors;
				} elseif ( $had_one_file ) { /* $count_files_removed == 0 */
						\WP_CLI::log( "Couldn't uninstall language '{$language_code}' from theme {$theme}." );
						$result['status'] = 'failed to uninstall';
						++$errors;
				} else {
					\WP_CLI::log( "Language '{$language_code}' for '{$theme}' already uninstalled." );
					$result['status'] = 'already uninstalled';
					++$skips;
				}

				$results[] = (object) $result;
			}
		}

		if ( 'summary' !== $assoc_args['format'] ) {
			\WP_CLI\Utils\format_items( $assoc_args['format'], $results, array( 'name', 'locale', 'status' ) );
		}

		\WP_CLI\Utils\report_batch_operation_results( 'language', 'uninstall', $count, $successes, $errors, $skips );
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
	 *     # Update all installed language packs for all themes.
	 *     $ wp language theme update --all
	 *     Updating 'Japanese' translation for Twenty Fifteen 1.5...
	 *     Downloading translation from https://downloads.wordpress.org/translation/theme/twentyfifteen/1.5/ja.zip...
	 *     Translation updated successfully.
	 *     Success: Updated 1/1 translation.
	 *
	 * @subcommand update
	 *
	 *  @param string[] $args Positional arguments.
	 * @param array{'dry-run'?: bool, all?: bool} $assoc_args Associative arguments.
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
