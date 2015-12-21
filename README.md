# Asset Subfolder Access plugin for Craft

This plugin makes it possible to limit user groups to only access certain subfolders of your asset sources.


## Installation

To install Asset Subfolder Access, follow these steps:

1.  Upload the assetsubfolderaccess/ folder to your craft/plugins/ folder.
2.  Go to Settings > Plugins from your Craft control panel and enable the Asset Subfolder Access plugin.
3.  Click on “Asset Subfolder Access” to go to the plugin’s settings page, and configure the plugin how you’d like.


## Setup

To limit a user group’s access to an asset source, follow these steps:

1. Go to Settings > Users > User Groups > [group name], and ensure that the group has (at least) the “View source” permission for the relevant asset source. Save the settings.
2. Go to Settings > Plugins > Asset Subfolder Access, find the user group, find its checkbox list for the relevant asset source, and choose which specific subfolders the user group should be able to access. Save the settings.

Note that if a user belongs to multiple groups, and one of the groups has access to the entire asset source from the Asset Subfolder Access settings, then the restrictions won’t be applied.

Admins will always have access to the entire asset source, regardless of what user group(s) they may belong to.


## Changelog

### 1.1

- Updated to take advantage of new Craft 2.5 plugin features.

### 1.0

- Initial release
