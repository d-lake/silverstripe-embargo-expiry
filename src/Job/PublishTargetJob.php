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
 * @property array $options
 */
class PublishTargetJob extends AbstractQueuedJob
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
    public function getTarget(): ?DataObject
    {
        if ($this->target !== null) {
            return $this->target;
        }

        if (is_array($this->options) && array_key_exists('onBeforeGetObject', $this->options)) {
            $cb = $this->options['onBeforeGetObject'];

            // Preferred in opis v4: stored as serialized string
            if (is_string($cb)) {
                try {
                    $cb = o_unserialize($cb);
                } catch (\Throwable $e) {
                    $cb = null; // ignore bad payloads
                }
            }

            if ($cb instanceof Closure) {
                $cb();
            }
        }

        $this->target = parent::getObject();

        return $this->target;
    }

    public function getTitle(): string
    {
        $target = $this->getTarget();

        return _t(
            self::class . '.SCHEDULEPUBLISHJOBTITLE',
            'Scheduled publishing of {object}',
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

        // Use local variable when passing by reference
        $options = $this->options;
        $target->invokeWithExtensions('prePublishTargetJob', $options);
        $this->options = $options;

        $target->unlinkPublishJobAndDate();
        $target->writeWithoutVersion();
        $target->publishRecursive();

        // Use local variable when passing by reference
        $options = $this->options;
        $target->invokeWithExtensions('afterPublishTargetJob', $options);
        $this->options = $options;

        ActionProcessingState::singleton()->setActionIsProcessing(false);
        $this->completeJob();
    }

    protected function completeJob(): void
    {
        $this->currentStep = 1;
        $this->isComplete = true;
    }
}
