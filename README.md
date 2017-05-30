wp-cli/language-command
=======================

Manage language packs.

[![Build Status](https://travis-ci.org/wp-cli/language-command.svg?branch=master)](https://travis-ci.org/wp-cli/language-command)

Quick links: [Using](#using) | [Installing](#installing) | [Contributing](#contributing)

## Using

This package implements the following commands:

### wp language core activate

Activate a given language.

~~~
wp language core activate <language>
~~~

	<language>
		Language code to activate.

**EXAMPLES**

    $ wp core language activate ja
    Success: Language activated.



### wp language core install

Install a given language.

~~~
wp language core install <language>... [--activate]
~~~

Downloads the language pack from WordPress.org.

	<language>...
		Language code to install.

	[--activate]
		If set, the language will be activated immediately after install.

**EXAMPLES**

    # Install the Japanese language.
    $ wp core language install ja
    Success: Language installed.



### wp language core list

List all available languages.

~~~
wp language core list [--field=<field>] [--<field>=<value>] [--fields=<fields>] [--format=<format>]
~~~

	[--field=<field>]
		Display the value of a single field

	[--<field>=<value>]
		Filter results by key=value pairs.

	[--fields=<fields>]
		Limit the output to specific fields.

	[--format=<format>]
		Accepted values: table, csv, json. Default: table

**AVAILABLE FIELDS**

These fields will be displayed by default for each translation:

* language
* english_name
* native_name
* status
* update
* updated

These fields are optionally available:

* version
* package

**EXAMPLES**

    # List language,english_name,status fields of available languages.
    $ wp core language list --fields=language,english_name,status
    +----------------+-------------------------+-------------+
    | language       | english_name            | status      |
    +----------------+-------------------------+-------------+
    | ar             | Arabic                  | uninstalled |
    | ary            | Moroccan Arabic         | uninstalled |
    | az             | Azerbaijani             | uninstalled |



### wp language core uninstall

Uninstall a given language.

~~~
wp language core uninstall <language>...
~~~

	<language>...
		Language code to uninstall.

**EXAMPLES**

    $ wp core language uninstall ja
    Success: Language uninstalled.



### wp language core update

Update installed languages.

~~~
wp language core update [--dry-run]
~~~

Updates installed languages for core, plugins and themes.

	[--dry-run]
		Preview which translations would be updated.

**EXAMPLES**

    $ wp core language update
    Updating 'Japanese' translation for Akismet 3.1.11...
    Downloading translation from https://downloads.wordpress.org/translation/plugin/akismet/3.1.11/ja.zip...
    Translation updated successfully.
    Updating 'Japanese' translation for Twenty Fifteen 1.5...
    Downloading translation from https://downloads.wordpress.org/translation/theme/twentyfifteen/1.5/ja.zip...
    Translation updated successfully.
    Success: Updated 2/2 translations.

## Installing

This package is included with WP-CLI itself, no additional installation necessary.

To install the latest version of this package over what's included in WP-CLI, run:

    wp package install git@github.com:wp-cli/language-command.git

## Contributing

We appreciate you taking the initiative to contribute to this project.

Contributing isn’t limited to just code. We encourage you to contribute in the way that best fits your abilities, by writing tutorials, giving a demo at your local meetup, helping other users with their support questions, or revising our documentation.

### Reporting a bug

Think you’ve found a bug? We’d love for you to help us get it fixed.

Before you create a new issue, you should [search existing issues](https://github.com/wp-cli/language-command/issues?q=label%3Abug%20) to see if there’s an existing resolution to it, or if it’s already been fixed in a newer version.

Once you’ve done a bit of searching and discovered there isn’t an open or fixed issue for your bug, please [create a new issue](https://github.com/wp-cli/language-command/issues/new) with the following:

1. What you were doing (e.g. "When I run `wp post list`").
2. What you saw (e.g. "I see a fatal about a class being undefined.").
3. What you expected to see (e.g. "I expected to see the list of posts.")

Include as much detail as you can, and clear steps to reproduce if possible.

### Creating a pull request

Want to contribute a new feature? Please first [open a new issue](https://github.com/wp-cli/language-command/issues/new) to discuss whether the feature is a good fit for the project.

Once you've decided to commit the time to seeing your pull request through, please follow our guidelines for creating a pull request to make sure it's a pleasant experience:

1. Create a feature branch for each contribution.
2. Submit your pull request early for feedback.
3. Include functional tests with your changes. [Read the WP-CLI documentation](https://wp-cli.org/docs/pull-requests/#functional-tests) for an introduction.
4. Follow the [WordPress Coding Standards](http://make.wordpress.org/core/handbook/coding-standards/).


*This README.md is generated dynamically from the project's codebase using `wp scaffold package-readme` ([doc](https://github.com/wp-cli/scaffold-package-command#wp-scaffold-package-readme)). To suggest changes, please submit a pull request against the corresponding part of the codebase.*
