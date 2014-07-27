<?php namespace RainLab\Translate;

use App;
use Lang;
use Event;
use Backend;
use Cms\Classes\Content;
use System\Classes\PluginBase;
use RainLab\Translate\Models\Message;
use RainLab\Translate\Classes\Translate;

/**
 * Translate Plugin Information File
 */
class Plugin extends PluginBase
{

    /**
     * Returns information about this plugin.
     *
     * @return array
     */
    public function pluginDetails()
    {
        return [
            'name'        => 'Translate',
            'description' => 'Enables multi-lingual sites.',
            'author'      => 'RainLab',
            'icon'        => 'icon-language'
        ];
    }

    public function boot()
    {
        /*
         * Set the page context for translation caching.
         */
        Event::listen('cms.page.beforeDisplay', function($controller, $url, $page) {
            if (!$page) return;
            $translate = Translate::instance();
            $translate->loadLocaleFromSession();
            Message::setContext($translate->getLocale(), $page->url);
        });

        /*
         * Adds language suffixes to content files.
         */
        Event::listen('cms.page.beforeRenderContent', function($controller, $name) {
            $locale = Translate::instance()->getLocale();
            $newName = substr_replace($name, '.'.$locale, strrpos($name, '.'), 0);
            if (($content = Content::loadCached($controller->getTheme(), $newName)) !== null)
                return $content;
        });

        /*
         * Automatically replace form fields for multi lingual equivalents
         */
        Event::listen('backend.form.extendFieldsBefore', function($widget) {

            if (!$model = $widget->model)
                return;

            if (!method_exists($model, 'isClassExtendedWith'))
                return;

            if (!$model->isClassExtendedWith('RainLab.Translate.Behaviors.TranslatableModel'))
                return;

            if (!is_array($model->translatable))
                return;

            if (!$fields = $widget->config->fields)
                return;

            foreach ($fields as $name => $config) {
                if (!in_array($name, $model->translatable))
                    continue;

                $type = array_get($config, 'type', 'text');
                if ($type == 'text')
                    $widget->config->fields[$name]['type'] = 'mltext';
            }

        });
    }

    public function registerSettings()
    {
        return [
            'locales' => [
                'label'       => 'Languages',
                'description' => 'Set up languages that can be used on the front-end.',
                'icon'        => 'icon-language',
                'url'         => Backend::url('rainlab/translate/locales'),
                'order'       => 550,
                'category'    => 'Translation',
            ],
            'messages' => [
                'label'       => 'Messages',
                'description' => 'Translate strings used throughout the front-end.',
                'icon'        => 'icon-list-alt',
                'url'         => Backend::url('rainlab/translate/messages'),
                'order'       => 551,
                'category'    => 'Translation',
            ],
        ];
    }

    /**
     * Register new Twig variables
     * @return array
     */
    public function registerMarkupTags()
    {
        return [
            'filters' => [
                '_' => [$this, 'translateString'],
                '__' => [$this, 'translatePlural'],
            ]
        ];
    }

    public function registerFormWidgets()
    {
        return [
            'RainLab\Translate\FormWidgets\MLText' => [
                'label' => 'Text (ML)',
                'alias' => 'mltext'
            ]
        ];
    }

    public function translateString($string, $params = [])
    {
        return Message::trans($string, $params);
    }

    public function translatePlural($string, $count = 0, $params = [])
    {
        return Lang::choice($string, $count, $params);
    }

}
