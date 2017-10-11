<?php

namespace craft\assetsubfolderaccess;

use Craft;
use craft\assetsubfolderaccess\models\Settings;
use craft\base\Element;
use craft\base\Volume;
use craft\elements\Asset;
use craft\events\RegisterElementSourcesEvent;
use yii\base\Event;

/**
 * Asset Subfolder Access plugin.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class Plugin extends \craft\base\Plugin
{

    // Public Methods
    // =============================================================================

    /**
     * @inheritdoc
     */
    public $hasCpSettings = true;

    public function init()
    {
        parent::init();
        Event::on(Asset::class, Element::EVENT_REGISTER_SOURCES, function(RegisterElementSourcesEvent $event) {
            // If the current user is an admin, just let them see everything
            if (Craft::$app->getUser()->getIsAdmin()) {
                return;
            }
            $accessibleFolders = $this->getSettings()->accessibleFolders;
            // Get a non-by-reference copy of the array.
            $incomingSources = $event->sources;
            $i = 0;
            foreach ($incomingSources as $key => $source) {
                // Is this an asset folder, and are we limiting access to its subfolders?
                $parentFolderId = $this->_getFolderIdFromSourceKey($source['key']);
                if ($parentFolderId !== false && $this->_shouldOverrideSource($parentFolderId, $accessibleFolders)) {
                    $newSources = $this->_filterSubfolderSources($source, $parentFolderId, $accessibleFolders);
                    // Modify the original passed-by-reference array
                    $event->sources = array_slice($event->sources, 0, $i, true) +
                        $newSources +
                        array_slice($event->sources, $i + 1, null, true);
                    $i += count($newSources);
                } else {
                    $i++;
                }
            }
        });
    }

    /**
     * @inheritdoc
     */
    public function settingsHtml(): string
    {
        $html = '<p class="first">For each user group, choose which subfolders they should be allowed to access from the Assets index page.</p>';
        $accessibleFolders = $this->getSettings()->accessibleFolders;
        /** @var Volume[] $assetVolumes */
        $assetVolumes = Craft::$app->getVolumes()->getAllVolumes();
        $userGroups = Craft::$app->getUserGroups()->getAllGroups();
        $foldersByVolume = [];
        $subfoldersByVolume = [];
        foreach ($assetVolumes as $volume) {
            $folder = Craft::$app->getAssets()->findFolder([
                'volumeId' => $volume->id,
                'parentId' => ':empty:'
            ]);
            $subfolders = Craft::$app->getAssets()->findFolders([
                'parentId' => $folder->id
            ]);
            $foldersByVolume[$volume->id] = $folder;
            $subfoldersByVolume[$volume->id] = $subfolders;
        }
        foreach ($userGroups as $group) {
            $html .= '<hr><h3>User Group: '.$group->name.'</h3>';
            $canViewVolumes = false;
            foreach ($assetVolumes as $volume) {
                if (!$group->can('viewVolume:'.$volume->id)) {
                    continue;
                }
                $canViewVolumes = true;
                $folder = $foldersByVolume[$volume->id];
                $options = [];
                foreach ($subfoldersByVolume[$volume->id] as $subfolder) {
                    $options[] = ['label' => $subfolder->name, 'value' => $subfolder->id];
                }
                if (!isset($accessibleFolders[$group->id][$folder->id])) {
                    $accessibleFolders[$group->id][$folder->id] = [];
                }
                $html .= Craft::$app->getView()->renderTemplateMacro('_includes/forms', 'checkboxSelectField', [
                    [
                        'label' => '“'.$volume->name.'” Subfolders',
                        'instructions' => 'Choose which subfolders this group should be able to access',
                        'name' => 'accessibleFolders['.$group->id.']['.$folder->id.']',
                        'options' => $options,
                        'showAllOption' => true,
                        'values' => $accessibleFolders[$group->id][$folder->id],
                    ]
                ]);
            }
            if (!$canViewVolumes) {
                $html .= '<p><em>This user group doesn’t have permission to view any asset volume.</em></p>';
            }
        }

        return $html;
    }

    // Private Methods
    // =============================================================================

    /**
     * @param $key
     *
     * @return bool|int
     */
    private function _getFolderIdFromSourceKey($key)
    {
        if (strncmp($key, 'folder:', 7) === 0) {
            return (int)substr($key, 7);
        } else {
            return false;
        }
    }

    /**
     * @param $parentFolderId
     * @param $accessibleFolders
     *
     * @return bool
     */
    private function _shouldOverrideSource($parentFolderId, $accessibleFolders): bool
    {
        $userGroups = Craft::$app->getUserGroups()->getAllGroups();
        if (!$userGroups) {
            // No groups, no overrides
            return false;
        }
        foreach ($userGroups as $group) {
            // Is this group allowed to see the whole source?
            if (
                !isset($accessibleFolders[$group->id][$parentFolderId]) ||
                $accessibleFolders[$group->id][$parentFolderId] == '*'
            ) {
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
    private function _filterSubfolderSources($parentSource, $parentFolderId, $accessibleFolders): array
    {
        if (!isset($parentSource['nested'])) {
            return [];
        }
        $newSources = $parentSource['nested'];
        foreach ($newSources as $key => $source) {
            $subfolderId = $this->_getFolderIdFromSourceKey($source['key']);
            if ($subfolderId === false || !$this->_canAccessSubfolder($parentFolderId, $subfolderId, $accessibleFolders)) {
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
    private function _canAccessSubfolder($parentFolderId, $subfolderId, $accessibleFolders): bool
    {
        $userGroups = Craft::$app->getUserGroups()->getAllGroups();
        foreach ($userGroups as $group) {
            $folders = $accessibleFolders[$group->id][$parentFolderId];
            if ($folders == '*' || (is_array($folders) && in_array($subfolderId, $folders, false))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    protected function createSettingsModel()
    {
        return new Settings();
    }
}