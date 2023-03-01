<?php
namespace sharkom\automigration;

use Yii;
use yii\base\InvalidConfigException;

/**
 * Class to Dump SQL Tables
 */
class MysqlDumpHelper
{
    /**
     * @param $tableName
     * @param $dumpPath
     * @param string $db
     * @return bool
     */
    public static function dumpTable($tableName, $dumpPath, $db = 'db')
    {
        // Verifica e crea la cartella di destinazione
        if (!is_dir($dumpPath)) {
            if (!mkdir($dumpPath, 0755, true) && !is_dir($dumpPath)) {
                throw new \RuntimeException(sprintf('Directory "%s" could not be created', $dumpPath));
            }
        }

        // Genera il nome del file di dump includendo la data e l'ora corrente
        $fileName = $tableName . '_' . date('Y-m-d_H-i-s') . '.sql';

        // Esegue il dump della tabella
        $command = sprintf(
            'mysqldump --user=%s --password=%s --host=%s %s %s > %s',
            escapeshellarg(Yii::$app->{$db}->username),
            escapeshellarg(Yii::$app->{$db}->password),
            escapeshellarg(self::getDsnAttribute("host")),
            escapeshellarg(self::getDsnAttribute("dbname")),
            escapeshellarg($tableName),
            escapeshellarg($dumpPath . DIRECTORY_SEPARATOR . $fileName)
        );

        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            throw new \RuntimeException("mysqldump failed with error code: " . $returnVar);
        }

        return true;
    }

    /**
     * @param $name
     * @param string $db
     * @return mixed
     */
    private static function getDsnAttribute($name, $db = "db")
    {
        $dsn = Yii::$app->{$db}->dsn;
        if (preg_match('/' . $name . '=([^;]*)/', $dsn, $match)) {
            return $match[1];
        }
        throw new \RuntimeException(sprintf('"%s" not found in DSN: %s', $name, $dsn));
    }
}
