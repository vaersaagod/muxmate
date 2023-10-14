<?php

namespace vaersaagod\muxmate\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\services\ProjectConfig;
use vaersaagod\muxmate\fields\MuxMateField;

/**
 * m231014_142449_remove_playbackid_content_columns migration.
 */
class m231014_142449_remove_playbackid_content_columns extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {

        // Remove playbackId content table columns
        // We only need to worry about the primary content table, because adding MuxMate fields to anything other than an asset isn't possible
        $muxMateFieldsQuery = (new Query())
            ->from('{{%fields}}')
            ->where(['type' => MuxMateField::class])
            ->andWhere(['context' => 'global']);

        $contentTableColumns = $this->db->schema->getTableSchema('{{%content}}')->getColumnNames();

        foreach ($muxMateFieldsQuery->each() as $muxMateField) {
            $handle = $muxMateField['handle'] ?? null;
            $columnSuffix = $muxMateField['columnSuffix'] ?? null;
            $contentColumnName = "field_{$handle}_muxPlaybackId" . ($columnSuffix ? "_$columnSuffix" : '');
            if (!isset($contentTableColumns[$contentColumnName])) {
                continue;
            }
            $this->dropColumn('{{%content}}', $contentColumnName);
        }

        // Maybe update the project config as well
        $projectConfig = Craft::$app->getProjectConfig();
        $schemaVersion = $projectConfig->get('plugins._muxmate.schemaVersion', true);

        if (version_compare($schemaVersion, '1.1.0', '>=')) {
            return true;
        }

        $fields = $projectConfig->get('fields') ?? [];

        foreach ($fields as $fieldUid => $field) {
            $type = $field['type'] ?? null;
            if ($type !== MuxMateField::class) {
                continue;
            }
            $field = Craft::$app->getFields()->getFieldByUid($fieldUid);
            $configData = Craft::$app->getFields()->createFieldConfig($field);
            $configPath = ProjectConfig::PATH_FIELDS . '.' . $fieldUid;
            Craft::$app->getProjectConfig()->set($configPath, $configData);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m231014_142449_remove_playbackid_content_columns cannot be reverted.\n";
        return false;
    }
}
