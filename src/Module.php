<?php

namespace sharkom\automigration;

use Yii;
use yii\base\BootstrapInterface;
use yii\base\Module as BaseModule;
/**
 * core module definition class
 */
class Module extends BaseModule implements BootstrapInterface
{
    /**
     * @var string The controller Namespace
     */
    public $controllerNamespace = 'sharkom\automigration\controllers';

    /**
     * Initialize the module
     */
    public function init()
    {
        parent::init();
        $this->registerTranslations();
    }

    /**
     * Register translations
     */
    public function registerTranslations()
    {
        \Yii::$app->i18n->translations['automigration'] = [
            'class' => '\yii\i18n\PhpMessageSource',
            'sourceLanguage' => 'en-US',
            'basePath' => '@sharkom/automigration/messages',
        ];
    }

    public function bootstrap($app)
    {
        if ($app instanceof \yii\console\Application) {
            $this->controllerNamespace = 'sharkom\automigration\commands';
        }
    }
}
