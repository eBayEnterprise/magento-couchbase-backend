# Composer Install

Basically `composer install`.

Actually, a bit more involved...

## Root Composer File

A Composer file for Jenkins is at [`tests/composer.jenkins.json`](/tests/composer.jenkins.json). This file is targeted at CI/CD builds but may also be used as a root Composer file to set up development environments. It will install the eBayEnterprise/magento-retail-order-management extension and its dependencies into an existing Magento instance. You will probably want to modify the Jenkins Composer file to use it on your own system. For example, you may want to include other extensions for your convenience that are not strictly required for automated testing, e.g. AOE_Scheduler.

## Magento Composer Installer

The Jenkins Composer file includes the Magento Composer Installer. This package provides a custom installer for deploying Magento modules into a Magento instance.

The installer uses sets of mappings to link files into the appropriate place in Magento. This mapping can be in one of three forms: mappings in the composer.json file, a package.xml file, a `modman` file. The eBayEnterprise/magento-retail-order-management module includes a `modman` file with the necessary mappings.

[Magento Composer Installer](https://github.com/magento-hackathon/magento-composer-installer)

## Composer File Details

### Repositories

This is a list of alternate sources for packages. In the Jenkins file, this includes the firegento package repository as well as some "git" sources. To meet specific needs of a local system, update the "url" to point to the desired source. For example, the following will use a fork of EcomDev_PHPUnit (ivanchepurnyi/ecomdev_phpunit) as the source:

```json
{
	...
	"repositories": [
		{
			"type": "git",
			"url": "git@github.com:kojiromike/EcomDev_PHPUnit.git"
		}
	],
	...
}
```

One important thing to note is that only the root Composer file may specify repositories. This means every required package (including dependencies of dependencies) must be listed either in the [default Packagist repository](https://getcomposer.org/doc/05-repositories.md#repository) or in the root composer file. Repository specifications in dependencies themselves will be ignored.

In the root Composer file, you'll likely want to adjust the repositories to point to forks or local repositories. This will likely just involve the eBayEnterprise repositories, but maybe others as well.

The Jenkins Composer file points to the local git repositories used by Jenkins.

### Magento Root Dir

In the "extra" section of the composer.jenkins.json file, there is a property for "magento-root-dir". This should point to the root directory of the Magento instance to install the module on. This will likely vary based upon the local environment.

### Magento Deploy Strategy

This option controls how the Magento Composer Installer installs modules into the Magento instance. This can be one of three options:

- `copy` - Deploys files as a copy
- `none` - Does not copy any files
- `symlink` - Deploys files as a symlink (default)

The Jenkins Composer file replaces the default `symlink` setting with `copy`. For local development, it may work better to restore the default `symlink` setting.

[Magento Composer Install Deploy Documentation](https://github.com/magento-hackathon/magento-composer-installer/blob/master/doc/Deploy.md)

### Magento Force

The "magento-force" option causes the files being deployed to replace any existing files. This mimics the behavior of `modman deploy --force`. The "magento-force" option is set to true for Jenkins builds. It may be safer to set this to false while in development to be alerted of any unexpected overwrites.

## Notes, Gotchas, FAQs

[Composer's troubleshooting page](https://getcomposer.org/doc/articles/troubleshooting.md) includes some common problems. Some common issues are also included for quick reference below.

### Github API Rate Limit

Composer makes pretty heavy use of the Github API to get details about package availability and versions. This means that when installing a large number of dependencies, Composer is likely to hit the Github API rate limit. During the install, Composer will ask for Github credentials to continue. You can provide the credentials as needed but this means you need to watch the install as it runs.

An alternative, is to authorize Composer with a Github personal access token. This allows Composer to bypass the rate limit. To do so, you need to first [generate a personal access token in Github](https://github.com/settings/applications). Then, add the token to Composer global configuration.

```
composer config -g github-oauth.github.com <oauthtoken>
```

### Broken Modman Links

The Magento Composer Installer is much less forgiving when it comes to broken links in the mappings. `modman` would complain about missing links but continue the deploy. The Magento Composer Installer will halt with an error message and fail to create further links.

For the most part, this means we will need to be more careful about keeping the `modman` links up-to-date. It will also make it much more obvious when `modman` files contain broken links.

### Stalled Installation

There appears to be a bug (feature?) in Composer related to how it calculates which version of a dependency to use. When Composer cannot determine if any version of the package is valid or invalid, it will spin away until stopped.

The best solution for this is to adjust the required package versions. Adding [version constraints](https://getcomposer.org/doc/01-basic-usage.md#package-versions) to required packages will often solved the issue. Composer is much more likely to be able to determine which versions are valid when they are well defined.
