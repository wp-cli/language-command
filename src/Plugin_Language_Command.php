<?php

/**
 * Installs, activates, and manages plugin language packs.
 *
 * ## EXAMPLES
 *
 *     # Install the Dutch plugin language pack for Hello Dolly.
 *     $ wp language plugin install hello-dolly nl_NL
 *     Downloading translation from https://downloads.wordpress.org/translation/plugin/hello-dolly/1.7.2/nl_NL.zip...
 *     Unpacking the update...
 *     Installing the latest version...
 *     Removing the old version of the translation...
 *     Translation updated successfully.
 *     Language 'nl_NL' installed.
 *     Success: Installed 1 of 1 languages.
 *
 *     # Uninstall the Dutch plugin language pack for Hello Dolly.
 *     $ wp language plugin uninstall hello-dolly nl_NL
 *     Language 'nl_NL' for 'hello-dolly' uninstalled.
 *     +-------------+--------+-------------+
 *     | name        | locale | status      |
 *     +-------------+--------+-------------+
 *     | hello-dolly | nl_NL  | uninstalled |
 *     +-------------+--------+-------------+
 *     Success: Uninstalled 1 of 1 languages.
 *
 *     # List installed plugin language packs for Hello Dolly.
 *     $ wp language plugin list hello-dolly --status=installed
 *     +-------------+----------+--------------+-------------+-----------+--------+---------------------+
 *     | plugin      | language | english_name | native_name | status    | update | updated             |
 *     +-------------+----------+--------------+-------------+-----------+--------+---------------------+
 *     | hello-dolly | nl_NL    | Dutch        | Nederlands  | installed | none   | 2023-11-13 12:34:15 |
 *     +-------------+----------+--------------+-------------+-----------+--------+---------------------+
 *
 * @package wp-cli
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
	 *   - count
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
	 * ## EXAMPLES
	 *
	 *     # List available language packs for the plugin.
	 *     $ wp language plugin list hello-dolly --fields=language,english_name,status
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
	 * @param array{all?: bool, field?: string, format: string, plugin?: string, language?: string, english_name?: string, native_name?: string, status?: string, update?: string, updated?: string} $assoc_args Associative arguments.
	 */
	public function list_( $args, $assoc_args ) {
		$all = \WP_CLI\Utils\get_flag_value( $assoc_args, 'all', false );

		if ( ! $all && empty( $args ) ) {
			WP_CLI::error( 'Please specify one or more plugins, or use --all.' );
		}

		if ( $all ) {
			$args = array_map( '\WP_CLI\Utils\get_plugin_name', array_keys( $this->get_all_plugins() ) );

			if ( empty( $args ) ) {
				WP_CLI::success( 'No plugins installed.' );
				return;
			}
		}

		$updates        = $this->get_translation_updates();
		$current_locale = get_locale();

		$translations = array();
		$plugins      = new \WP_CLI\Fetchers\Plugin();

		foreach ( $args as $plugin ) {
			if ( ! $plugins->get( $plugin ) ) {
				WP_CLI::warning( "Plugin '{$plugin}' not found." );
				continue;
			}

			$installed_translations = $this->get_installed_languages( $plugin );
			$available_translations = $this->get_all_languages( $plugin );

			foreach ( $available_translations as $translation ) {
				$translation['plugin'] = $plugin;
				$translation['status'] = in_array( $translation['language'], $installed_translations, true ) ? 'installed' : 'uninstalled';

				if ( $current_locale === $translation['language'] ) {
					$translation['status'] = 'active';
				}

				$filter_args = array(
					'language' => $translation['language'],
					'type'     => 'plugin',
					'slug'     => $plugin,
				);
				$update      = wp_list_filter( $updates, $filter_args );

				$translation['update'] = $update ? 'available' : 'none';

				// Support features like --status=active.
				foreach ( array_keys( $translation ) as $field ) {
					if ( isset( $assoc_args[ $field ] ) && ! in_array( $translation[ $field ], array_map( 'trim', explode( ',', (string) $assoc_args[ $field ] ) ), true ) ) {
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
	 *
	 * @param non-empty-array<string> $args Positional arguments.
	 */
	public function is_installed( $args ) {
		$plugin         = array_shift( $args );
		$language_codes = $args;

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
	 * [<plugin>]
	 * : Plugin to install language for.
	 *
	 * [--all]
	 * : If set, languages for all plugins will be installed.
	 *
	 * <language>...
	 * : Language code to install.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format. Used when installing languages for all plugins.
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
	 *     # Install the Japanese language for Akismet.
	 *     $ wp language plugin install akismet ja
	 *     Downloading translation from https://downloads.wordpress.org/translation/plugin/akismet/4.0.3/ja.zip...
	 *     Unpacking the update...
	 *     Installing the latest version...
	 *     Removing the old version of the translation...
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
			\WP_CLI::error( 'Please specify a plugin, or use --all.' );
		}

		if ( $all ) {
			$this->install_many( $args, $assoc_args );
		} else {
			$this->install_one( $args, $assoc_args );
		}
	}

	/**
	 * Installs translations for a plugin.
	 *
	 * @param array $args       Runtime arguments.
	 * @param array $assoc_args Runtime arguments.
	 */
	private function install_one( $args, $assoc_args ) {
		$plugin         = array_shift( $args );
		$language_codes = $args;
		$count          = count( $language_codes );

		$available = $this->get_installed_languages( $plugin );

		$successes = 0;
		$errors    = 0;
		$skips     = 0;
		foreach ( $language_codes as $language_code ) {

			if ( in_array( $language_code, $available, true ) ) {
				\WP_CLI::log( "Language '{$language_code}' already installed." );
				++$skips;
			} else {
				$response = $this->download_language_pack( $language_code, $plugin );

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
	 * Installs translations for all installed plugins.
	 *
	 * @param array $args       Runtime arguments.
	 * @param array $assoc_args Runtime arguments.
	 */
	private function install_many( $args, $assoc_args ) {
		$language_codes = (array) $args;
		$plugins        = $this->get_all_plugins();

		if ( empty( $assoc_args['format'] ) ) {
			$assoc_args['format'] = 'table';
		}

		if ( in_array( $assoc_args['format'], array( 'json', 'csv' ), true ) ) {
			$logger = new \WP_CLI\Loggers\Quiet();
			\WP_CLI::set_logger( $logger );
		}

		if ( empty( $plugins ) ) {
			\WP_CLI::success( 'No plugins installed.' );
			return;
		}

		$count = count( $plugins ) * count( $language_codes );

		$results = array();

		$successes = 0;
		$errors    = 0;
		$skips     = 0;
		foreach ( $plugins as $plugin_path => $plugin_details ) {
			$plugin_name = \WP_CLI\Utils\get_plugin_name( $plugin_path );

			$available = $this->get_installed_languages( $plugin_name );

			foreach ( $language_codes as $language_code ) {
				$result = [
					'name'   => $plugin_name,
					'locale' => $language_code,
				];

				if ( in_array( $language_code, $available, true ) ) {
					\WP_CLI::log( "Language '{$language_code}' for '{$plugin_details['Name']}' already installed." );
					$result['status'] = 'already installed';
					++$skips;
				} else {
					$response = $this->download_language_pack( $language_code, $plugin_name );

					if ( is_wp_error( $response ) ) {
						\WP_CLI::warning( $response );
						\WP_CLI::log( "Language '{$language_code}' for '{$plugin_details['Name']}' not installed." );

						if ( 'not_found' === $response->get_error_code() ) {
							$result['status'] = 'not available';
							++$skips;
						} else {
							$result['status'] = 'not installed';
							++$errors;
						}
					} else {
						\WP_CLI::log( "Language '{$language_code}' for '{$plugin_details['Name']}' installed." );
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
	 * Uninstalls a given language for a plugin.
	 *
	 * ## OPTIONS
	 *
	 * [<plugin>]
	 * : Plugin to uninstall language for.
	 *
	 * [--all]
	 * : If set, languages for all plugins will be uninstalled.
	 *
	 * <language>...
	 * : Language code to uninstall.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format. Used when installing languages for all plugins.
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
	 *     # Uninstall the Japanese plugin language pack for Hello Dolly.
	 *     $ wp language plugin uninstall hello-dolly ja
	 *     Language 'ja' for 'hello-dolly' uninstalled.
	 *     +-------------+--------+-------------+
	 *     | name        | locale | status      |
	 *     +-------------+--------+-------------+
	 *     | hello-dolly | ja     | uninstalled |
	 *     +-------------+--------+-------------+
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
			\WP_CLI::error( 'Please specify one or more plugins, or use --all.' );
		}

		if ( $all ) {
			$plugins = array_map( '\WP_CLI\Utils\get_plugin_name', array_keys( $this->get_all_plugins() ) );

			if ( empty( $plugins ) ) {
				WP_CLI::success( 'No plugins installed.' );
				return;
			}
		} else {
				$plugins = array( array_shift( $args ) );
		}

		$language_codes = (array) $args;
		$current_locale = get_locale();

		$dir   = WP_LANG_DIR . "/$this->obj_type";
		$files = scandir( $dir );

		if ( ! $files ) {
			\WP_CLI::error( 'No files found in language directory.' );
		}

		// As of WP 4.0, no API for deleting a language pack
		WP_Filesystem();

		$count = count( $plugins ) * count( $language_codes );

		$results = array();

		$successes = 0;
		$errors    = 0;
		$skips     = 0;

		/**
		 * @var string $plugin
		 */
		foreach ( $plugins as $plugin ) {
			$available = $this->get_installed_languages( $plugin );

			foreach ( $language_codes as $language_code ) {
				$result = [
					'name'   => $plugin,
					'locale' => $language_code,
					'status' => 'not available',
				];

				if ( ! in_array( $language_code, $available, true ) ) {
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
					"$plugin-$language_code.po",
					"$plugin-$language_code.mo",
					"$plugin-$language_code.l10n.php",
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
						! preg_match( "/$plugin-$language_code-\w{32}\.json/", $file )
					) {
						continue;
					}

					$had_one_file = true;

					++$count_files_to_remove;

					if ( $wp_filesystem->delete( $dir . '/' . $file ) ) {
						++$count_files_removed;
					} else {
						\WP_CLI::error( "Couldn't uninstall language: $language_code from plugin $plugin." );
					}
				}

				if ( $count_files_to_remove === $count_files_removed ) {
					$result['status'] = 'uninstalled';
					++$successes;
					\WP_CLI::log( "Language '{$language_code}' for '{$plugin}' uninstalled." );
				} elseif ( $count_files_removed ) {
					\WP_CLI::log( "Language '{$language_code}' for '{$plugin}' partially uninstalled." );
					$result['status'] = 'partial uninstall';
					++$errors;
				} elseif ( $had_one_file ) { /* $count_files_removed == 0 */
						\WP_CLI::log( "Couldn't uninstall language '{$language_code}' from plugin {$plugin}." );
						$result['status'] = 'failed to uninstall';
						++$errors;
				} else {
					\WP_CLI::log( "Language '{$language_code}' for '{$plugin}' already uninstalled." );
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
	 *     # Update all installed language packs for all plugins.
	 *     $ wp language plugin update --all
	 *     Updating 'Japanese' translation for Akismet 3.1.11...
	 *     Downloading translation from https://downloads.wordpress.org/translation/plugin/akismet/3.1.11/ja.zip...
	 *     Translation updated successfully.
	 *     Success: Updated 1/1 translation.
	 *
	 * @subcommand update
	 *
	 * @param string[] $args Positional arguments.
	 * @param array{'dry-run'?: bool, all?: bool} $assoc_args Associative arguments.
	 */
	public function update( $args, $assoc_args ) {
		$all = \WP_CLI\Utils\get_flag_value( $assoc_args, 'all', false );

		if ( ! $all && empty( $args ) ) {
			WP_CLI::error( 'Please specify one or more plugins, or use --all.' );
		}

		if ( $all ) {
			$args = array_map( '\WP_CLI\Utils\get_plugin_name', array_keys( $this->get_all_plugins() ) );
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
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Using WP native hook.
		return apply_filters( 'all_plugins', get_plugins() );
	}
}
