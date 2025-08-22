<?php

namespace Terraformers\EmbargoExpiry\Extension;

use Exception;
use SilverStripe\Core\Extension;
use TractorCow\Fluent\State\FluentState;
use function Opis\Closure\serialize as o_serialize;

class EmbargoExpiryFluentExtension extends Extension
{
    /**
     * Fluent specific configuration
     */
    private static array $field_include = [
        'DesiredPublishDate',
        'DesiredUnPublishDate',
        'PublishOnDate',
        'UnPublishOnDate',
        'PublishJobID',
        'UnPublishJobID',
    ];

    /**
     * @throws Exception
     */
    public function setLocaleOptions(array &$options): void
    {
        if (!class_exists(FluentState::class)) {
            throw new Exception('Fluent extension not available. Please add it to your composer requirements');
        }

        $locale = FluentState::singleton()->getLocale();

        // There's nothing to be done here if there is no active Locale.
        if (!$locale) {
            return;
        }

        // Locale isn't currently used in our Job, but if you subclass, you might find it useful for something.
        $options['locale'] = $locale;

        // For opis/closure v4: store a serialized Closure string that restores the desired Fluent locale
        $options['onBeforeGetObject'] = o_serialize(static function () use ($locale): void {
            FluentState::singleton()->setLocale($locale);
        });
    }

    /**
     * @throws Exception
     */
    public function updatePublishTargetJobOptions(array &$options): void
    {
        $this->setLocaleOptions($options);
    }

    /**
     * @throws Exception
     */
    public function updateUnPublishTargetJobOptions(array &$options): void
    {
        $this->setLocaleOptions($options);
    }
}
