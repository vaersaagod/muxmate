<?php

namespace vaersaagod\muxmate\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\base\PreviewableFieldInterface;
use craft\elements\Asset;
use craft\elements\db\ElementQuery;
use craft\elements\db\ElementQueryInterface;
use craft\fieldlayoutelements\Tip;
use craft\helpers\Db;
use craft\helpers\ElementHelper;
use craft\helpers\Html;
use craft\helpers\StringHelper;
use craft\web\View;

use vaersaagod\muxmate\helpers\MuxMateHelper;
use vaersaagod\muxmate\models\MuxMateFieldAttributes;

use yii\base\InvalidConfigException;
use yii\db\Schema;

/**
 * MuxMate field type
 */
class MuxMateField extends Field implements PreviewableFieldInterface
{
    public static function displayName(): string
    {
        return Craft::t('_muxmate', 'MuxMate');
    }

    public static function valueType(): string
    {
        return 'mixed';
    }

    /**
     * @param mixed $value
     * @param ElementInterface $element
     * @return string
     */
    public function getPreviewHtml(mixed $value, ElementInterface $element): string
    {
        if (!$element instanceof Asset || $element->kind !== Asset::KIND_VIDEO) {
            return '';
        }
        if (!$value instanceof MuxMateFieldAttributes || !$value->muxAssetId) {
            $label = \Craft::t('_muxmate', 'Video does not have a Mux asset');
            $content = '❌';
        } else {
            $muxData = $value->muxMetaData ?? [];
            $muxStatus = $muxData['status'] ?? null;
            if ($muxStatus !== 'ready') {
                $label = \Craft::t('_muxmate', 'Mux video is being processed. Stay tuned!');
                $content = '⏳';
            } else {
                $label = \Craft::t('_muxmate', 'Mux video is ready to play!');
                $content = '👍';
                try {
                    if (MuxMateHelper::getMuxPlaybackId($element, MuxMateHelper::PLAYBACK_POLICY_SIGNED)) {
                        $content .= '🔒';
                    }
                } catch (\Throwable $e) {
                    Craft::error($e, __METHOD__);
                }
            }
        }
        return Html::tag('span', $content, [
            'role' => 'img',
            'title' => $label,
            'aria' => [
                'label' => $label,
            ],
        ]);
    }

    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            // ...
        ]);
    }

    public function getSettingsHtml(): ?string
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public static function dbType(): array|string
    {
        return [
            'muxAssetId' => Schema::TYPE_STRING,
            'muxMetaData' => Schema::TYPE_TEXT,
        ];
    }

    public function useFieldset(): bool
    {
        return true;
    }

    /**
     * @throws InvalidConfigException
     */
    public function normalizeValue(mixed $value, ElementInterface $element = null): mixed
    {
        if ($value instanceof MuxMateFieldAttributes) {
            return $value;
        }
        return Craft::createObject([
            'class' => MuxMateFieldAttributes::class,
            ...($value ?? []),
        ]);
    }

    protected function inputHtml(mixed $value, ElementInterface $element = null, bool $inline): string
    {
        if (!$element instanceof Asset || $element->kind !== Asset::KIND_VIDEO) {
            $warningTip = new Tip([
                'style' => Tip::STYLE_WARNING,
                'tip' => Craft::t('_muxmate', 'The MuxMate field is only designed to work on video assets.'),
            ]);
            return $warningTip->formHtml();
        }
        $id = Html::id($this->handle);
        $namespacedId = Craft::$app->getView()->namespaceInputId($id);
        $css = <<< CSS
            #$namespacedId-field > .heading {
                margin-bottom: 15px;
            }
            #$namespacedId-field legend {
                font-size: 18px;
            }
            CSS;
        Craft::$app->getView()->registerCss($css);
        return \Craft::$app->getView()->renderTemplate(
            '_muxmate/_components/muxmate-field-input.twig',
            ['asset' => $element],
            View::TEMPLATE_MODE_CP
        );
    }

    public function getElementValidationRules(): array
    {
        return [];
    }

    protected function searchKeywords(mixed $value, ElementInterface $element): string
    {
        if ($value instanceof MuxMateFieldAttributes) {
            return $value->muxAssetId;
        }
        return '';
    }

    /**
     * @inheritdoc
     */
    public function modifyElementsQuery(ElementQueryInterface $query, mixed $value): void
    {
        if (!$value) {
            return;
        }
        /** @var ElementQuery $query */
        $column = ElementHelper::fieldColumnFromField($this);
        $metaDataColumn = StringHelper::replace($column, $this->handle, "{$this->handle}_muxMetaData");
        if (is_array($value)) {
            $keys = array_keys($value);
            foreach ($keys as $key) {
                $query
                    ->subQuery
                    ->andWhere(Db::parseParam("JSON_EXTRACT(content.$metaDataColumn, '$.$key')", $value[$key]));
            }
        } else {
            $query
                ->subQuery
                ->andWhere(Db::parseParam("content.$column", $value));
        }
    }

}
