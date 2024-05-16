<?php

namespace vaersaagod\muxmate\gql;

use craft\gql\base\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use vaersaagod\muxmate\helpers\MuxMateHelper;

use yii\base\InvalidConfigException;

class MuxMateFieldResolver extends ObjectType
{
    /**
     * @inheritdoc
     */
    protected function resolve($source, $arguments, $context, ResolveInfo $resolveInfo): mixed
    {
        $fieldName = $resolveInfo->fieldName;

        switch ($fieldName) {
            case 'error':
                return $source->muxMetaData['errors']['type'] ?? null;
            case 'playback_id':
                $policy = $arguments['policy'] ?? null;
                if (!$policy) {
                    return $source->muxMetaData['playback_ids'][0]['id'];
                }
                if (isset($policy) && !in_array($policy, [MuxMateHelper::PLAYBACK_POLICY_SIGNED, MuxMateHelper::PLAYBACK_POLICY_PUBLIC], true)) {
                    throw new InvalidConfigException("Invalid playback policy \"$policy\"");
                }
                $index = array_search($policy, array_column($source->muxMetaData['playback_ids'], 'policy'));
                return $source->muxMetaData['playback_ids'][$index]['id'] ?? null;
            default:
                return $source->muxMetaData[$fieldName] ?? $source->$fieldName ?? null;
        }
    }
}
