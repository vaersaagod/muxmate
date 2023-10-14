<?php

namespace vaersaagod\muxmate\console\controllers;

use Craft;
use craft\console\Controller;
use craft\elements\Asset;

use vaersaagod\muxmate\helpers\MuxApiHelper;
use vaersaagod\muxmate\helpers\MuxMateHelper;

use yii\console\ExitCode;
use yii\helpers\BaseConsole;

/**
 * Create controller
 */
class CreateController extends Controller
{

    public $defaultAction = 'index';

    public ?string $volume = null;

    public ?int $assetId = null;

    public bool $update = false;

    public function options($actionID): array
    {
        return [
            ...parent::options($actionID),
            'volume',
            'update',
        ];
    }

    /**
     * Create or update Mux assets. Optionally pass a volume handle via the --volume parameter to limit videos to a single volume, or an --assetId to limit it to a single video asset. If you want the operation to update existing Mux assets as well, pass --update=1
     * @return int
     */
    public function actionIndex(): int
    {
        $query = Asset::find()
            ->kind(Asset::KIND_VIDEO);
        if ($this->interactive && empty($this->volume) && empty($this->assetId) && !$this->confirm("Are you sure you want to create or update Mux assets for *all* volumes? You can pass a parameter --volume to limit the operation to single volume, or --assetId to limit the operation to a single asset.\n")) {
            return ExitCode::OK;
        }
        if ($this->volume) {
            // Make sure the volume exists
            $volume = Craft::$app->getVolumes()->getVolumeByHandle($this->volume);
            if (!$volume) {
                $this->stderr("The volume \"{$this->volume}\" does not exist.\n", BaseConsole::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }
            $query->volumeId((int)$volume->id);
        } else if ($this->assetId) {
            $query->id($this->assetId);
        }
        $count = $query->count();
        if (!$count) {
            $this->stdout("No videos found.\n", BaseConsole::FG_RED);
            return ExitCode::OK;
        }
        if ($this->interactive && !$this->confirm("Create or update Mux assets for $count videos?")) {
            return ExitCode::OK;
        }
        $numCreated = 0;
        $numUpdated = 0;
        $numErrors = 0;
        /** @var Asset $video */
        foreach ($query->each() as $video) {
            $isNewMuxAsset = empty(MuxMateHelper::getMuxAssetId($video));
            if (!$isNewMuxAsset && !$this->update) {
                $this->stdout("Skipping \"$video->filename\" because it already has a Mux asset.\n", BaseConsole::FG_CYAN);
                continue;
            }
            if ($isNewMuxAsset) {
                $this->stdout("Create Mux asset for video \"$video->filename\"...\n", BaseConsole::FG_YELLOW);
            } else {
                $this->stdout("Updating Mux asset data for video \"$video->filename\"...\n", BaseConsole::FG_PURPLE);
            }
            if (MuxMateHelper::updateOrCreateMuxAsset($video)) {
                if ($isNewMuxAsset) {
                    $this->stdout("Mux asset for \"$video->filename\" was successfully created!\n", BaseConsole::FG_GREEN);
                    $numCreated++;
                } else {
                    $this->stdout("Mux asset data for \"$video->filename\" was successfully updated!\n", BaseConsole::FG_GREEN);
                    $numUpdated++;
                }
            } else {
                if ($isNewMuxAsset) {
                    $this->stdout("Failed to create Mux asset for \"$video->filename\".\n", BaseConsole::FG_RED);
                } else {
                    $this->stdout("Failed to update Mux asset data for \"$video->filename\".\n", BaseConsole::FG_RED);
                }
                $numErrors++;
            }
        }
        $this->stdout("Done! $numCreated Mux assets were created and $numUpdated were updated. There were $numErrors failures.\n", BaseConsole::FG_CYAN);
        return ExitCode::OK;
    }

    /**
     * Create or update Mux playback IDs. Optionally pass a volume handle via the --volume parameter to limit videos to a single volume, or an --assetId to limit it to a single video asset. If you want the operation to update existing Mux assets as well, pass --update=1
     * @return int
     * @throws \yii\base\InvalidConfigException
     */
    public function actionPlaybackIds(): int
    {
        if ($this->interactive && empty($this->volume) && empty($this->assetId) && !$this->confirm("Are you sure you want to create playback IDs for *all* volumes? You can pass a parameter --volume to limit the operation to single volume, or --assetId to limit the operation to a single asset.\n")) {
            return ExitCode::OK;
        }
        $query = Asset::find()
            ->kind(Asset::KIND_VIDEO);
        if ($this->volume) {
            // Make sure the volume exists
            $volume = Craft::$app->getVolumes()->getVolumeByHandle($this->volume);
            if (!$volume) {
                $this->stderr("The volume \"{$this->volume}\" does not exist.\n", BaseConsole::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }
            $query->volumeId((int)$volume->id);
        } else if ($this->assetId) {
            $query->id($this->assetId);
        }
        $count = $query->count();
        if (!$count) {
            $this->stdout("No videos found.\n", BaseConsole::FG_RED);
            return ExitCode::OK;
        }
        if ($this->interactive && !$this->confirm("Create or update Mux playback IDs for $count videos?")) {
            return ExitCode::OK;
        }
        $numAssetsUpdated = 0;
        $numCreated = 0;
        $numUpdated = 0;
        $numErrors = 0;
        /** @var Asset $video */
        foreach ($query->each() as $video) {
            $muxAssetId = MuxMateHelper::getMuxAssetId($video);
            $assetUpdated = false;
            if (empty($muxAssetId)) {
                $this->stderr("Asset \"{$video->filename}\" doesn't have a Mux asset ID. Skipping...\n", BaseConsole::FG_RED);
                $numErrors++;
                continue;
            }
            foreach ([MuxMateHelper::PLAYBACK_POLICY_PUBLIC, MuxMateHelper::PLAYBACK_POLICY_SIGNED] as $policy) {
                $playbackId = MuxMateHelper::getMuxPlaybackId($video, $policy);
                $hasExistingPlaybackId = !empty($playbackId);
                if ($hasExistingPlaybackId) {
                    if ($this->update) {
                        $this->stdout("Deleting existing $policy playback ID for \"$video->filename\"...\n", BaseConsole::FG_CYAN);
                        try {
                            MuxApiHelper::deletePlaybackId($muxAssetId, $playbackId);
                        } catch (\Throwable $e) {
                            Craft::error($e, __METHOD__);
                            $this->stderr("Failed to delete existing $policy playback ID: \"{$e->getMessage()}\" – skipping this video.\n", BaseConsole::FG_RED);
                            $numErrors++;
                            continue;
                        }
                    } else {
                        $this->stdout("Not creating a $policy playback ID for \"$video->filename\" because it already exists.\n", BaseConsole::FG_CYAN);
                        continue;
                    }
                }
                $this->stdout("Creating new $policy playback ID for \"$video->filename\"...\n", BaseConsole::FG_PURPLE);
                try {
                    $playbackId = MuxApiHelper::createPlaybackId($muxAssetId, $policy);
                } catch (\Throwable $e) {
                    Craft::error($e, __METHOD__);
                    $this->stderr("Failed to create new $policy playback ID: \"{$e->getMessage()}\" – skipping this video.\n", BaseConsole::FG_RED);
                    $numErrors++;
                    continue;
                }
                if ($hasExistingPlaybackId) {
                    $numUpdated++;
                } else {
                    $numCreated++;
                }
                $this->stdout("Playback ID $playbackId created for asset \"$video->filename\" – updating Mux meta data...\n", BaseConsole::FG_PURPLE);
                if (MuxMateHelper::updateOrCreateMuxAsset($video)) {
                    $this->stdout("Asset \"$video->filename\" updated with a new $policy playback ID!\n", BaseConsole::FG_GREEN);
                    $assetUpdated = true;
                } else {
                    $this->stderr("Failed to update \"$video->filename\" metadata with new $policy playback ID!\n", BaseConsole::FG_RED);
                }
            }
            if ($assetUpdated) {
                $numAssetsUpdated++;
            }
        }
        $this->stdout("Done! $numCreated Mux playback IDs were created and $numUpdated were updated, for a total of $numAssetsUpdated assets. There were $numErrors failures.\n", BaseConsole::FG_CYAN);
        return ExitCode::OK;
    }

}
