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
		parent::list_( $args, $assoc_args );
	}

}
