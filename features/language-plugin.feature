Feature: Manage translation files for a WordPress install

  @require-wp-4.0
  Scenario: Plugin translation CRUD
    Given a WP install
    And an empty cache

    When I run `wp plugin install hello-dolly`
    Then STDOUT should contain:
      """
      Plugin installed successfully.
      """
    And STDERR should be empty

    When I run `wp language plugin list hello-dolly --fields=language,english_name,status`
    Then STDOUT should be a table containing rows:
      | language  | english_name            | status        |
      | cs_CZ     | Czech                   | uninstalled   |
      | de_DE     | German                  | uninstalled   |
      | en_US     | English (United States) | active        |
      | en_GB     | English (UK)            | uninstalled   |

    When I run `wp language plugin install hello-dolly en_GB`
    Then the wp-content/languages/plugins/hello-dolly-en_GB.po file should exist
    And STDOUT should contain:
      """
      Success: Language installed.
      """
    And STDERR should be empty

    When I run `wp language plugin install hello-dolly cs_CZ de_DE`
    Then the wp-content/languages/plugins/hello-dolly-cs_CZ.po file should exist
    And the wp-content/languages/plugins/hello-dolly-es_ES.po file should exist
    And STDOUT should contain:
      """
      Success: Language installed.
      """
    And STDERR should be empty

    When I run `ls {SUITE_CACHE_DIR}/translation | grep plugin-hello-dolly-`
    Then STDOUT should contain:
      """
      de_DE
      """
    And STDOUT should contain:
      """
      en_GB
      """

    When I try `wp language plugin install hello-dolly en_GB`
    Then STDERR should be:
      """
      Warning: Language 'en_GB' already installed.
      """
    And STDOUT should be empty
    And the return code should be 0

    When I run `wp language plugin list hello-dolly --fields=language,english_name,status`
    Then STDOUT should be a table containing rows:
      | language  | english_name            | status      |
      | cs_CZ     | Czech                   | installed   |
      | de_DE     | German                  | installed   |
      | en_US     | English (United States) | active      |
      | en_GB     | English (UK)            | installed   |

    When I run `wp language plugin list hello-dolly --fields=language,english_name,update`
    Then STDOUT should be a table containing rows:
      | language  | english_name            | update        |
      | ar        | Arabic                  | none          |
      | az        | Azerbaijani             | none          |
      | en_AU     | English (Australia)     | available     |
      | en_US     | English (United States) | none          |
      | en_GB     | English (UK)            | available     |

    When I run `wp language plugin update --dry-run`
    Then save STDOUT 'Available (\d+) translations updates' as {UPDATES}

    When I run `wp language plugin update`
    Then STDOUT should contain:
      """
      Success: Updated {UPDATES}/{UPDATES} translations.
      """
    And the wp-content/languages/plugins directory should exist
    And the wp-content/languages/themes directory should exist

    When I run `wp language plugin list --field=language --status=active`
    Then STDOUT should be:
      """
      en_GB
      """

    When I run `wp language plugin list --fields=language,english_name,status`
    Then STDOUT should be a table containing rows:
      | language  | english_name     | status        |
      | ar        | Arabic           | uninstalled   |
      | az        | Azerbaijani      | uninstalled   |
      | en_GB     | English (UK)     | active        |

    When I try `wp language plugin install hello-dolly en_AU --activate`
    Then STDERR should contain:
      """
      Warning: Language 'en_AU' already installed.
      """
    And STDOUT should be:
      """
      Success: Language activated.
      """
    And the return code should be 0

    When I try `wp language plugin install hello-dolly en_AU --activate`
    Then STDERR should contain:
      """
      Warning: Language 'en_AU' already installed.
      Warning: Language 'en_AU' already active.
      """
    And STDOUT should be empty
    And the return code should be 0

    When I try `wp language plugin install hello-dolly en_CA en_NZ --activate`
    Then STDERR should be:
      """
      Error: Only a single language can be active.
      """
    And STDOUT should be empty
    And the return code should be 1

    When I run `wp language plugin activate en_US`
    Then STDOUT should be:
      """
      Success: Language activated.
      """

    When I run `wp language plugin list hello-dolly --fields=language,english_name,status`
    Then STDOUT should be a table containing rows:
      | language  | english_name            | status        |
      | ar        | Arabic                  | uninstalled   |
      | en_US     | English (United States) | active        |
      | en_GB     | English (UK)            | installed     |

    When I try `wp language plugin activate invalid_lang`
    Then STDERR should be:
      """
      Error: Language not installed.
      """
    And STDOUT should be empty
    And the return code should be 1

    When I run `wp language plugin uninstall hello-dolly en_GB`
    Then the wp-content/languages/admin-en_GB.po file should not exist
    And the wp-content/languages/en_GB.po file should not exist
    And STDOUT should be:
      """
      Success: Language uninstalled.
      """

    When I run `wp language plugin uninstall hello-dolly en_CA en_NZ`
     Then the wp-content/languages/admin-en_CA.po file should not exist
     And the wp-content/languages/en_CA.po file should not exist
     And STDOUT should be:
       """
      Success: Language uninstalled.
      Success: Language uninstalled.
      """

    When I try `wp language plugin uninstall en_GB`
    Then STDERR should be:
      """
      Error: Language not installed.
      """
    And STDOUT should be empty
    And the return code should be 1

    When I run `wp language plugin install hello-dolly en_GB --activate`
    Then the wp-content/languages/admin-en_GB.po file should exist
    And the wp-content/languages/en_GB.po file should exist
    And STDOUT should contain:
      """
      Success: Language installed.
      Success: Language activated.
      """
    And STDERR should be empty

    When I try `wp language plugin install hello-dolly invalid_lang`
    Then STDERR should be:
      """
      Error: Language 'invalid_lang' not found.
      """
    And STDOUT should be empty
    And the return code should be 1

  @require-wp-4.0
  Scenario: Don't allow active language to be uninstalled
    Given a WP install

    When I run `wp language core install en_GB --activate`
    Then STDOUT should not be empty

    When I try `wp language plugin uninstall en_GB`
    Then STDERR should be:
      """
      Warning: The 'en_GB' language is active.
      """
    And STDOUT should be empty
    And the return code should be 0
