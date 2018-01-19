<?php

namespace WP_CLI;
use WP_CLI\Formatter;

/**
 * Base class for WP-CLI commands that deal with translations
 *
 * @package wp-cli
 */
abstract class CommandWithTranslation extends \WP_CLI_Command {
	protected $obj_type;

	protected $obj_fields = array(
		'language',
		'english_name',
		'native_name',
		'status',
		'update',
		'updated',
		);

	/**
	 * Lists all available languages.
	 *
	 * ## OPTIONS
	 *
	 * [--field=<field>]
	 * : Display the value of a single field
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
	 *     $ wp language core list --fields=language,english_name,status
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

		$translations = $this->get_all_languages();
		$available = $this->get_installed_languages();

		$updates = $this->get_translation_updates();

		$current_locale = get_locale();
		$translations = array_map( function( $translation ) use ( $available, $current_locale, $updates ) {
			$translation['status'] = ( in_array( $translation['language'], $available ) ) ? 'installed' : 'uninstalled';
			if ( $current_locale == $translation['language'] ) {
				$translation['status'] = 'active';
			}

			$update = wp_list_filter( $updates, array(
				'language' => $translation['language']
			) );
			if ( $update ) {
				$translation['update'] = 'available';
			} else {
				$translation['update'] = 'none';
			}

			return $translation;
		}, $translations );

		foreach( $translations as $key => $translation ) {

			$fields = array_keys( $translation );
			foreach( $fields as $field ) {
				if ( isset( $assoc_args[ $field ] ) && $assoc_args[ $field ] != $translation[ $field ] ) {
					unset( $translations[ $key ] );
				}
			}
		}

		$formatter = $this->get_formatter( $assoc_args );
		$formatter->display_items( $translations );

	}

	/**
	 * Callback to sort array by a 'language' key.
	 */
	protected function sort_translations_callback( $a, $b ) {
		return strnatcasecmp( $a['language'], $b['language'] );
	}

	/**
	 * Updates installed languages for the current object type.
	 */
	public function update( $args, $assoc_args ) {
		$updates = $this->get_translation_updates();

		if ( empty( $updates ) ) {
			\WP_CLI::success( 'Translations are up to date.' );

			return;
		}

		// Gets a list of all languages.
		$all_languages = $this->get_all_languages();

		$updates_per_type = array();

		// Formats the updates list.
		foreach ( $updates as $update ) {
			if ( 'plugin' === $update->type ) {
				$plugins	 = get_plugins( '/' . $update->slug );
				$plugin_data = array_shift( $plugins );
				$name		 = $plugin_data['Name'];
			} elseif ( 'theme' === $update->type ) {
				$theme_data	 = wp_get_theme( $update->slug );
				$name		 = $theme_data['Name'];
			} else { // Core
				$name = 'WordPress';
			}

			// Gets the translation data.
			$translation = wp_list_filter( $all_languages, array(
				'language' => $update->language
			) );
			$translation = (object) reset( $translation );

			$update->Type		 = ucfirst( $update->type );
			$update->Name		 = $name;
			$update->Version	 = $update->version;
			$update->Language	 = $translation->english_name;

			$updates_per_type[ $update->type ] = $update;
		}

		$obj_type = rtrim( $this->obj_type, 's' );
		$available_updates = $updates_per_type[ $obj_type ];

		$num_to_update	 = count( $available_updates );

		// Only preview which translations would be updated.
		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run' ) ) {
			\WP_CLI::line( sprintf( 'Available %d translations updates:', $num_to_update ) );
			\WP_CLI\Utils\format_items( 'table', $available_updates, array( 'Type', 'Name', 'Version', 'Language' ) );

			return;
		}

		$upgrader = 'WP_CLI\\LanguagePackUpgrader';
		$results = array();

		// Update translations.
		foreach ( $available_updates as $update ) {
			\WP_CLI::line( "Updating '{$update->Language}' translation for {$update->Name} {$update->Version}..." );

			$result = Utils\get_upgrader( $upgrader )->upgrade( $update );

			$results[] = $result;
		}

		$num_updated = count( array_filter( $results ) );

		$line = "Updated $num_updated/$num_to_update translations.";

		if ( $num_to_update === $num_updated ) {
			\WP_CLI::success( $line );
		} else if ( $num_updated > 0 ) {
			\WP_CLI::warning( $line );
		} else {
			\WP_CLI::error( $line );
		}

	}

	/**
	 * Get all updates available for all translations.
	 *
	 * @see wp_get_translation_updates()
	 *
	 * @return array
	 */
	protected function get_translation_updates() {
		$available = $this->get_installed_languages();

		$func = function() use ( $available ) {
			return $available;
		};

		switch( $this->obj_type ) {
			case 'plugins':
				add_filter( 'plugins_update_check_locales', $func );

				wp_clean_plugins_cache();
				// Check for Plugin translation updates.
				wp_update_plugins();

				remove_filter( 'plugins_update_check_locales', $func );

				$transient = 'update_plugins';
				break;
			case 'themes':
				add_filter( 'themes_update_check_locales', $func );

				wp_clean_themes_cache();
				// Check for Theme translation updates.
				wp_update_themes();

				remove_filter( 'themes_update_check_locales', $func );

				$transient = 'update_themes';
				break;
			default:
				delete_site_transient( 'update_core' );

				// Check for Core translation updates.
				wp_version_check();

				$transient = 'update_core';
				break;
		}

		$updates   = array();
		$transient = get_site_transient( $transient );

		foreach ( $transient->translations as $translation ) {
			$updates[] = (object) $translation;
		}

		return $updates;
	}

	/**
	 * Download a language pack.
	 *
	 * @see wp_download_language_pack()
	 *
	 * @param string $download Language code to download.
	 * @param string $slug Plugin or theme slug. Not used for core.
	 * @return string|\WP_Error Returns the language code if successfully downloaded, or a WP_Error object on failure.
	 */
	protected function download_language_pack( $download, $slug = null ) {

		$translations = $this->get_all_languages( $slug );

		foreach ( $translations as $translation ) {
			if ( $translation['language'] === $download ) {
				$translation_to_load = true;
				break;
			}
		}

		if ( empty( $translation_to_load ) ) {
			return new \WP_Error( 'not_found', "Language '{$download}' not found." );
		}
		$translation = (object) $translation;

		$translation->type = rtrim( $this->obj_type, 's' );

		// Make sure caching in LanguagePackUpgrader works.
		if ( ! isset( $translation->slug ) ) {
			$translation->slug = $slug;
		}

		$upgrader = 'WP_CLI\\LanguagePackUpgrader';
		$result = Utils\get_upgrader( $upgrader )->upgrade( $translation, array( 'clear_update_cache' => false ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		} else if ( ! $result ) {
			return new \WP_Error( 'not_installed', "Could not install language '{$download}'." );
		}

		return $translation->language;
	}

	/**
	 * Return a list of installed languages.
	 *
	 * @param string $slug Optional. Plugin or theme slug. Defaults to 'default' for core.
	 *
	 * @return array
	 */
	protected function get_installed_languages( $slug = 'default' ) {
		$available = wp_get_installed_translations( $this->obj_type );
		$available = ! empty( $available[ $slug ] ) ? array_keys( $available[ $slug ] ) : array();
		$available[] = 'en_US';

		return $available;
	}

	/**
	 * Return a list of all languages
	 *
	 * @param string $slug Optional. Plugin or theme slug. Not used fore core.
	 *
	 * @return array
	 */
	protected function get_all_languages( $slug = null ) {
		require_once ABSPATH . '/wp-admin/includes/translation-install.php';
		require ABSPATH . WPINC . '/version.php';

		$response = translations_api( $this->obj_type, array(
			'version' => $wp_version,
			'slug'    => $slug
		) );
		if ( is_wp_error( $response ) ) {
			\WP_CLI::error( $response );
		}
		$translations = ! empty( $response['translations'] ) ? $response['translations'] : array();

		$en_us = array(
			'language' => 'en_US',
			'english_name' => 'English (United States)',
			'native_name' => 'English (United States)',
			'updated' => '',
		);

		array_push( $translations, $en_us );
		uasort( $translations, array( $this, 'sort_translations_callback' ) );

		return $translations;
	}

	/**
	 * Get Formatter object based on supplied parameters.
	 *
	 * @param array $assoc_args Parameters passed to command. Determines formatting.
	 * @return Formatter
	 */
	protected function get_formatter( &$assoc_args ) {
		return new Formatter( $assoc_args, $this->obj_fields, $this->obj_type );
	}
}
