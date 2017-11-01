<?php

use WP_CLI\Dispatcher\CommandNamespace;

/**
 * Installs, activates, and manages language packs.
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
 */
class Language_Namespace extends CommandNamespace {

}
