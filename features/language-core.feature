Feature: Manage translation files for a WordPress install

  @require-wp-4.0
  Scenario: Core translation CRUD
    Given a WP install
    And an empty cache

    When I run `wp language core list --fields=language,english_name,status`
    Then STDOUT should be a table containing rows:
      | language  | english_name            | status        |
      | ar        | Arabic                  | uninstalled   |
      | en_AU     | English (Australia)     | uninstalled   |
      | en_CA     | English (Canada)        | uninstalled   |
      | en_GB     | English (UK)            | uninstalled   |
      | en_US     | English (United States) | active        |
      | ja        | Japanese                | uninstalled   |

    When I try `wp language core is-installed en_GB`
    Then the return code should be 1
    And STDERR should be empty

    When I try `wp language core is-installed en_AU`
    Then the return code should be 1
    And STDERR should be empty

    When I run `wp language core install en_GB`
    And I run `wp language core install en_AU`
    Then the wp-content/languages/admin-en_GB.po file should exist
    And the wp-content/languages/en_GB.po file should exist
    And the wp-content/languages/admin-en_AU.po file should exist
    And the wp-content/languages/en_AU.po file should exist
    And STDOUT should contain:
      """
      Success: Language installed.
      """
    And STDERR should be empty

    When I try `wp language core is-installed en_GB`
    Then the return code should be 0

    When I try `wp language core is-installed en_AU`
    Then the return code should be 0

    When I run `wp language core install en_CA ja`
    Then the wp-content/languages/admin-en_CA.po file should exist
    And the wp-content/languages/en_CA.po file should exist
    And the wp-content/languages/admin-ja.po file should exist
    And the wp-content/languages/ja.po file should exist
    And STDOUT should contain:
      """
      Success: Language installed.
      """
    And STDERR should be empty

    When I run `ls {SUITE_CACHE_DIR}/translation | grep core-default-`
    Then STDOUT should contain:
      """
      en_AU
      """
    And STDOUT should contain:
      """
      en_GB
      """

    When I try `wp language core install en_AU`
    Then STDERR should be:
      """
      Warning: Language 'en_AU' already installed.
      """
    And STDOUT should be empty
    And the return code should be 0

    When I run `wp language core list --fields=language,english_name,status`
    Then STDOUT should be a table containing rows:
      | language  | english_name     | status        |
      | ar        | Arabic           | uninstalled   |
      | en_GB     | English (UK)     | installed     |

    When I run `wp language core activate en_GB`
    Then STDOUT should be:
      """
      Success: Language activated.
      """

    When I run `wp language core list --fields=language,english_name,update`
    Then STDOUT should be a table containing rows:
      | language  | english_name            | update   |
      | ar        | Arabic                  | none     |
      | en_AU     | English (Australia)     | none     |
      | en_CA     | English (Canada)        | none     |
      | en_US     | English (United States) | none     |
      | en_GB     | English (UK)            | none     |
      | ja        | Japanese                | none     |

    When I run `wp language core update --dry-run`
    Then save STDOUT 'Found (\d+) translation updates that would be processed' as {UPDATES}

    When I run `wp language core update`
    Then STDOUT should contain:
      """
      Success: Translations are up to date.
      """

    When I run `wp language core list --field=language --status=active`
    Then STDOUT should be:
      """
      en_GB
      """

    When I run `wp language core list --fields=language,english_name,status`
    Then STDOUT should be a table containing rows:
      | language  | english_name     | status        |
      | ar        | Arabic           | uninstalled   |
      | en_GB     | English (UK)     | active        |

    When I try `wp language core install en_AU --activate`
    Then STDERR should contain:
      """
      Warning: Language 'en_AU' already installed.
      """
    And STDOUT should be:
      """
      Success: Language activated.
      """
    And the return code should be 0

    When I try `wp language core install en_AU --activate`
    Then STDERR should contain:
      """
      Warning: Language 'en_AU' already installed.
      Warning: Language 'en_AU' already active.
      """
    And STDOUT should be empty
    And the return code should be 0

    When I try `wp language core install en_CA ja --activate`
    Then STDERR should be:
      """
      Error: Only a single language can be active.
      """
    And STDOUT should be empty
    And the return code should be 1

    When I run `wp language core activate en_US`
    Then STDOUT should be:
      """
      Success: Language activated.
      """

    When I run `wp language core list --fields=language,english_name,status`
    Then STDOUT should be a table containing rows:
      | language  | english_name            | status        |
      | ar        | Arabic                  | uninstalled   |
      | en_US     | English (United States) | active        |
      | en_GB     | English (UK)            | installed     |

    When I try `wp language core activate invalid_lang`
    Then STDERR should be:
      """
      Error: Language not installed.
      """
    And STDOUT should be empty
    And the return code should be 1

    When I run `wp language core uninstall en_GB`
    Then the wp-content/languages/admin-en_GB.po file should not exist
    And the wp-content/languages/en_GB.po file should not exist
    And STDOUT should be:
      """
      Success: Language uninstalled.
      """

    When I run `wp language core uninstall en_CA ja`
     Then the wp-content/languages/admin-en_CA.po file should not exist
     And the wp-content/languages/en_CA.po file should not exist
     And the wp-content/languages/admin-ja.po file should not exist
     And the wp-content/languages/ja.po file should not exist
     And STDOUT should be:
       """
      Success: Language uninstalled.
      Success: Language uninstalled.
      """

    When I try `wp language core uninstall en_GB`
    Then STDERR should be:
      """
      Error: Language not installed.
      """
    And STDOUT should be empty
    And the return code should be 1

    When I run `wp language core install en_GB --activate`
    Then the wp-content/languages/admin-en_GB.po file should exist
    And the wp-content/languages/en_GB.po file should exist
    And STDOUT should contain:
      """
      Success: Language installed.
      Success: Language activated.
      """
    And STDERR should be empty

    When I try `wp language core install invalid_lang`
    Then STDERR should be:
      """
      Error: Language 'invalid_lang' not found.
      """
    And STDOUT should be empty
    And the return code should be 1

  @require-wp-latest @require-php-5.6 @less-than-php-7.0
  Scenario Outline: Core translation update
    Given an empty directory
    And WP files
    And a database
    And I run `wp core download --version=<original> --force`
    And wp-config.php
    And I run `wp core install --url='localhost:8001' --title='Test' --admin_user=wpcli --admin_email=admin@example.com --admin_password=1`

    When I run `wp language core list --fields=language,status,update`
    Then STDOUT should be a table containing rows:
      | language | status      | update    |
      | ar       | uninstalled | none      |
      | en_CA    | uninstalled | none      |
      | en_US    | active      | none      |
      | ja       | uninstalled | none      |

    When I run `wp language core install en_CA ja`
    Then the wp-content/languages/admin-en_CA.po file should exist
    And the wp-content/languages/en_CA.po file should exist
    And the wp-content/languages/admin-ja.po file should exist
    And the wp-content/languages/ja.po file should exist
    And STDOUT should contain:
      """
      Success: Language installed.
      """
    And STDERR should be empty

    Given I try `wp core download --version=<update> --force`
    Then the return code should be 0
    And I run `wp core update-db`

    When I run `wp language core list --fields=language,status,update`
    Then STDOUT should be a table containing rows:
      | language | status      | update    |
      | ar       | uninstalled | none      |
      | en_CA    | installed   | available |
      | en_US    | active      | none      |
      | ja       | installed   | available |

    When I run `wp language core update --dry-run`
    Then STDOUT should contain:
      """
      Available 2 translations updates:
      """
    And STDOUT should contain:
      """
      Core
      """
    And STDOUT should contain:
      """
      WordPress
      """
    And STDOUT should contain:
      """
      <update>
      """
    And STDOUT should contain:
      """
      English (Canada)
      """
    And STDOUT should contain:
      """
      Japanese
      """

    Examples:
      | original | update |
      | 4.8      | 4.9    |
      | 4.0.1    | 4.2    |

  @require-wp-4.0
  Scenario: Don't allow active language to be uninstalled
    Given a WP install

    When I run `wp language core install en_GB --activate`
    Then STDOUT should not be empty

    When I try `wp language core uninstall en_GB`
    Then STDERR should be:
      """
      Warning: The 'en_GB' language is active.
      """
    And STDOUT should be empty
    And the return code should be 0

  @require-wp-4.0
  Scenario: Ensure correct language is installed for WP version
    Given a WP install
    And an empty cache
    And I run `wp core download --version=4.5.3 --force`

    When I run `wp language core install nl_NL`
    Then STDOUT should contain:
      """
      Downloading translation from https://downloads.wordpress.org/translation/core/4.5.3
      """
