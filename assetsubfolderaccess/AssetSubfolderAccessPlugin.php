<?php
namespace Craft;

class AssetSubfolderAccessPlugin extends BasePlugin
{
	/**
	 * @return mixed
	 */
	public function getName()
	{
		return Craft::t('Asset Subfolder Access');
	}

	/**
	 * @return string
	 */
	public function getVersion()
	{
		return '1.1.1';
	}

	/**
	 * @return string
	 */
	public function getSchemaVersion()
	{
		return '1.0.0';
	}

	/**
	 * @return string
	 */
	public function getDeveloper()
	{
		return 'Pixel & Tonic';
	}

	/**
	 * @return string
	 */
	public function getDeveloperUrl()
	{
		return 'http://pixelandtonic.com';
	}

	/**
	 * @return string
	 */
	public function getPluginUrl()
	{
		return 'https://github.com/pixelandtonic/AssetSubfolderAccess';
	}

	/**
	 * @return string
	 */
	public function getDocumentationUrl()
	{
		return $this->getPluginUrl().'/blob/master/README.md';
	}

	/**
	 * @return string
	 */
	public function getReleaseFeedUrl()
	{
		return 'https://raw.githubusercontent.com/pixelandtonic/AssetSubfolderAccess/master/releases.json';
	}

	/**
	 * @return string
	 */
	public function getSettingsHtml()
	{
		$html = '<p class="first">For each user group, choose which subfolders they should be allowed to access from the Assets index page.</p>';

		$accessibleFolders = $this->getSettings()->accessibleFolders;
		$assetSources = craft()->assetSources->getAllSources();
		$userGroups = craft()->userGroups->getAllGroups();

		$foldersBySource = array();
		$subfoldersBySource = array();

		foreach ($assetSources as $source)
		{
			$folder = craft()->assets->findFolder(array(
				'sourceId' => $source->id,
				'parentId' => ':empty:'
			));

			$subfolders = craft()->assets->findFolders(array(
				'parentId' => $folder->id
			));

			$foldersBySource[$source->id] = $folder;
			$subfoldersBySource[$source->id] = $subfolders;
		}

		foreach ($userGroups as $group)
		{
			$html .= '<hr><h3>User Group: '.$group->name.'</h3>';
			$canViewSources = false;

			foreach ($assetSources as $source)
			{
				if (!$group->can('viewAssetSource:'.$source->id))
				{
					continue;
				}

				$canViewSources = true;
				$folder = $foldersBySource[$source->id];
				$options = array();

				foreach ($subfoldersBySource[$source->id] as $subfolder)
				{
					$options[] = array('label' => $subfolder->name, 'value' => $subfolder->id);
				}

				if (!isset($accessibleFolders[$group->id][$folder->id]))
				{
					$accessibleFolders[$group->id][$folder->id] = array();
				}

				$html .= craft()->templates->renderMacro('_includes/forms', 'checkboxSelectField', array(
					array(
						'label' => '“'.$source->name.'” Subfolders',
						'instructions' => 'Choose which subfolders this group should be able to access',
						'name' => 'accessibleFolders['.$group->id.']['.$folder->id.']',
						'options' => $options,
						'values' => $accessibleFolders[$group->id][$folder->id],
					)
				));
			}

			if (!$canViewSources)
			{
				$html .= '<p><em>This user group doesn’t have permission to view any asset sources.</em></p>';
			}
		}

		return $html;
	}

	/**
	 * @param $sources
	 * @param $context
	 */
	public function modifyAssetSources(&$sources, $context)
	{
		// If the current user is an admin, just let them see everything
		if (craft()->userSession->isAdmin())
		{
			return;
		}

		$accessibleFolders = $this->getSettings()->accessibleFolders;

		$i = 0;
		$output = $sources;

		foreach ($sources as $key => $source)
		{
			// Is this an asset folder, and are we limiting access to its subfolders?
			$parentFolderId = $this->_getFolderIdFromSourceKey($key);

			if ($parentFolderId !== false && $this->_shouldOverrideSource($parentFolderId, $accessibleFolders))
			{
				$newSources = $this->_filterSubfolderSources($source, $parentFolderId, $accessibleFolders);

				$output = array_slice($output, 0, $i, true) +
					$newSources +
					array_slice($output, $i + 1, null, true);

				$i += count($newSources);
			}
			else
			{
				$i++;
			}

			$sources = $output;
		}
	}

	/**
	 * @return array
	 */
	protected function defineSettings()
	{
		return array(
			'accessibleFolders' => array(AttributeType::Mixed, 'default' => array()),
		);
	}

	/**
	 * @param $key
	 *
	 * @return bool|int
	 */
	private function _getFolderIdFromSourceKey($key)
	{
		if (strncmp($key, 'folder:', 7) === 0)
		{
			return (int) substr($key, 7);
		}
		else
		{
			return false;
		}
	}

	/**
	 * @param $parentFolderId
	 * @param $accessibleFolders
	 *
	 * @return bool
	 */
	private function _shouldOverrideSource($parentFolderId, $accessibleFolders)
	{
		$userGroups = craft()->userSession->getUser()->getGroups();

		if (!$userGroups)
		{
			// No groups, no overrides
			return false;
		}

		foreach ($userGroups as $group)
		{
			// Is this group allowed to see the whole source?
			if (
				!isset($accessibleFolders[$group->id][$parentFolderId]) ||
				$accessibleFolders[$group->id][$parentFolderId] == '*'
			)
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * @param $parentSource
	 * @param $parentFolderId
	 * @param $accessibleFolders
	 *
	 * @return array
	 */
	private function _filterSubfolderSources($parentSource, $parentFolderId, $accessibleFolders)
	{
		if (!isset($parentSource['nested']))
		{
			return array();
		}

		$newSources = $parentSource['nested'];

		foreach ($newSources as $key => $source)
		{
			$subfolderId = $this->_getFolderIdFromSourceKey($key);

			if ($subfolderId === false || !$this->_canAccessSubfolder($parentFolderId, $subfolderId, $accessibleFolders))
			{
				unset($newSources[$key]);
			}
		}

		return $newSources;
	}

	/**
	 * @param $parentFolderId
	 * @param $subfolderId
	 * @param $accessibleFolders
	 *
	 * @return bool
	 */
	private function _canAccessSubfolder($parentFolderId, $subfolderId, $accessibleFolders)
	{
		$userGroups = craft()->userSession->getUser()->getGroups();

		foreach ($userGroups as $group)
		{
			if (in_array($subfolderId, $accessibleFolders[$group->id][$parentFolderId]))
			{
				return true;
			}
		}

		return false;
	}
}
