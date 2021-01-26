<?php
namespace biglotteryfund;

use Craft;
use craft\elements\Entry;
use craft\events\RegisterElementSortOptionsEvent;
use yii\base\Event;

class Module extends \yii\base\Module
{
    public function init()
    {
        // Define a custom alias named after the namespace
        Craft::setAlias('@biglotteryfund', __DIR__);
        parent::init();

        // https://github.com/craftcms/cms/issues/2818
        // https://docs.craftcms.com/v3/extend/updating-plugins.html#plugin-hooks
        Event::on(Entry::class, Entry::EVENT_REGISTER_SORT_OPTIONS, function (RegisterElementSortOptionsEvent $event) {
            $event->sortOptions[] = [
                'orderBy' => 'typeId',
                'label' => \Craft::t('app', 'Entry Type'),
                'attribute' => 'type',
            ];
        });
    }
}
