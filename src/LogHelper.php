<?php

namespace sharkom\automigration;

use sharkom\automigration\ColorHelper;
use Yii;
use yii\base\ErrorException;
use yii\console\Request;
use yii\helpers\Console;


/**
 * Class LogHelper
 * @package sharkom\devhelper
 */
class LogHelper{

    /**
     * @param $lower_level
     * @param $message
     * @param string $id
     * @param string $code
     */
    static function log($level, $message, $id = '', $code = '', $toFile = '') {
        /**
         * Livelli di log:
         * 1. info - colore: cyan
         * 2. warn / warning: yellow
         * 3. err / error: red
         * 4. fatal: white con BG red
         */

        $colors = new ColorHelper;
        $lower_level = strtolower($level);

        switch ($lower_level) {
            case 'info':
                $level_str = $colors->getColoredString('[info]', 'green');
                break;
            case 'warn':
            case 'warning':
                $level_str = $colors->getColoredString('[warning]', 'yellow');
                break;
            case 'err':
            case 'error':
                $level_str = $colors->getColoredString('[error]', 'red');
                break;
            case 'fatal':
                $level_str = $colors->getColoredString('[fatal]', 'white', 'red');
                break;
            default:
                $level_str = $colors->getColoredString("[$level]", 'green');
                break;
        }

        if (ctype_upper($level)) {
            $message = $colors->getColoredString(" $message ", 'blue', 'light_gray');
        }

        if ($id) {
            $message .= " - " . $colors->getColoredString("ID: $id", 'light_green');
        }

        if ($code) {
            $message .= " - " . $colors->getColoredString("$code", 'purple');
        }

        $log = '[' . date('Y-m-d H:i:s') . "] $level_str - $message\n";

        if ($toFile) {
            error_log($log, 3, \Yii::$app->basePath . '/runtime/debug/' . $toFile);
        } else {
            echo $log;
        }
    }


    /**
     * @param $prompt
     * @param bool $interactive
     * @return bool|void
     */
    static function confirm($prompt, $interactive = true) {
        if (!$interactive) {
            return true;
        }

        $module = Yii::$app->controller->module;

        if (!isset($module->params['interactive']) || !$module->params['interactive']) {
            return true;
        }

        $input = static::readChar($prompt);

        if (in_array(strtolower($input), ['y', 'yes', "\n"], true)) {
            return true;
        } else {
            die("Esecuzione script terminata");
        }
    }


    /**
     * @param $prompt
     * @return false|string
     */
    static function readchar($prompt)
    {
        readline_callback_handler_install($prompt, function() {});
        $char = stream_get_contents(STDIN, 1);
        if($char=="-") $char.=stream_get_contents(STDIN, 1);
        readline_callback_handler_remove();
        echo "\n";
        return $char;
    }
}
