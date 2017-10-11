Asset Subfolder Access for Craft CMS
===================

This plugin makes it possible to limit user groups to only access certain subfolders of your asset sources.

## Requirements

This plugin requires Craft CMS 3.0.0-beta.26 or later.


## Installation

To install the plugin, follow these instructions.

1. Open your terminal and go to your Craft project:

        cd /path/to/project

2. Then tell Composer to load the plugin:

        composer require craftcms/asset-subfolder-access

3. In the Control Panel, go to Settings → Plugins and click the “Install” button for Asset Subfolder Access.

## Setup

To limit a user group’s access to an asset source, follow these steps:

1. Go to Settings > Users > User Groups > [group name], and ensure that the group has (at least) the “View source” permission for the relevant asset source. Save the settings.

2. Go to Settings > Plugins > Asset Subfolder Access, find the user group, find its checkbox list for the relevant asset source, and choose which specific subfolders the user group should be able to access. Save the settings.
Note that if a user belongs to multiple groups, and one of the groups has access to the entire asset source from the Asset Subfolder Access settings, then the restrictions won’t be applied.

Admins will always have access to the entire asset source, regardless of what user group(s) they may belong to.

