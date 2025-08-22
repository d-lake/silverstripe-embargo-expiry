<?php

namespace Terraformers\EmbargoExpiry\Job;

use Closure;
use function Opis\Closure\unserialize as o_unserialize;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Terraformers\EmbargoExpiry\Extension\EmbargoExpiryExtension;
use Terraformers\EmbargoExpiry\Job\State\ActionProcessingState;

/**
 * @property array|null $options
 */
class UnPublishTargetJob extends AbstractQueuedJob
{
    /**
     * @var DataObject|null
     */
    private $target; // phpcs:ignore SlevomatCodingStandard.TypeHints

    public function __construct(?DataObject $obj = null, ?array $options = null)
    {
        $this->totalSteps = 1;

        if ($obj !== null) {
            $this->setObject($obj);
        }

        if ($options !== null) {
            $this->options = $options;
        }
    }

    /**
     * @return DataObject|Versioned|EmbargoExpiryExtension|null
     */
    public function getTarget() // phpcs:ignore SlevomatCodingStandard.TypeHints
    {
        if ($this->target !== null) {
            return $this->target;
        }

        if (is_array($this->options) && array_key_exists('onBeforeGetObject', $this->options)) {
            $cb = $this->options['onBeforeGetObject'];

            // In opis v4 this should be a serialized string; convert to a Closure
            if (is_string($cb)) {
                try {
                    $cb = o_unserialize($cb);
                } catch (\Throwable $e) {
                    $cb = null;
                }
            }

            if ($cb instanceof Closure) {
                $cb();
            }
        }

        $this->target = parent::getObject();

        return $this->target;
    }

    /**
     * @return string
     */
    public function getTitle() // phpcs:ignore SlevomatCodingStandard.TypeHints
    {
        $target = $this->getTarget();

        return _t(
            self::class . '.SCHEDULEUNPUBLISHJOBTITLE',
            'Scheduled un-publishing of {object}',
            '',
            [
                'object' => $target ? $target->Title : '',
            ]
        );
    }

    public function process(): void
    {
        $target = $this->getTarget();

        if ($target === null) {
            $this->completeJob();

            return;
        }

        ActionProcessingState::singleton()->setActionIsProcessing(true);

        // Make sure to use local variables for passing by reference as these are job properties
        // which are manipulated via magic methods and these do not work with passing by reference directly
        $options = $this->options;
        $target->invokeWithExtensions('preUnPublishTargetJob', $options);
        $this->options = $options;

        $target->unlinkUnPublishJobAndDate();
        $target->writeWithoutVersion();
        $target->doUnpublish();

        // Make sure to use local variables for passing by reference as these are job properties
        // which are manipulated via magic methods and these do not work with passing by reference directly
        $options = $this->options;
        // This allows actions to occur after the unpublish job has run such as creating snapshots
        $target->invokeWithExtensions('afterUnPublishTargetJob', $options);
        $this->options = $options;

        ActionProcessingState::singleton()->setActionIsProcessing(false);
        $this->completeJob();
    }

    protected function completeJob() // phpcs:ignore SlevomatCodingStandard.TypeHints
    {
        $this->currentStep = 1;
        $this->isComplete = true;
    }
}
