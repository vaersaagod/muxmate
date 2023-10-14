<?php

namespace vaersaagod\muxmate\console\controllers;

use Craft;
use craft\console\Controller;
use craft\elements\Asset;

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
     * Create or update Mux assets. Optionally pass a volume handle via the --volume parameter to limit videos to a single volume. If you want the operation to update existing Mux assets as well, pass --update=1
     * @return int
     */
    public function actionIndex(): int
    {
        $query = Asset::find()
            ->kind(Asset::KIND_VIDEO);
        if ($this->interactive && empty($this->volume) && !$this->confirm("Are you sure you want to create or update Mux assets for *all* volumes? You can pass a parameter --volume to limit the operation to single volume.\n")) {
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

}
