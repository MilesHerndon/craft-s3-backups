# S3 Backups plugin for Craft CMS 3.x

Plugin to backup Craft files and database to AWS S3

## Requirements

This plugin requires Craft CMS 3.1 or later.

## Installation

To install the plugin, follow these instructions.

1. Open your terminal and go to your Craft project:

        cd /path/to/project

2. Then tell Composer to load the plugin:

        composer require milesherndon/s3-backups

3. In the Control Panel, go to Settings → Plugins and click the “Install” button for S3 Backups.

## S3 Backups Overview

S3 Backups offers a simple way of setting up a backup process to Craft projects. It is easy to setup and run regularly. Before using, you will need a
AWS account, an AWS Key and Secret, and a bucket to store the backup files.

## Configuring S3 Backups

For AWS settings, those can be saved in the settings section directly, or they can be saved in the .env.

## Using S3 Backups

-Insert text here-

## S3 Backups Roadmap

Some things to do, and ideas for potential features:

* Add more error handling and error emails
* Set logging of error messages
* Add cleanup on queue second attempt
* Add feature to exclude additonal files from being backed up
* Add ability to download backup directly from Craft CP

Brought to you by [MilesHerndon](https://milesherndon.com)
