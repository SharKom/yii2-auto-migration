<?php

namespace sharkom\automigration\commands;

use sharkom\devhelper\LogHelper;
use sharkom\automigration\MysqlDumpHelper;
use Yii;
use yii\console\Controller;
use yii\db\Exception;
use yii\helpers\Console;
use yii\console\ExitCode;


/**
 * Console controller for automatic schema migrations.
 */
class MigrateController extends Controller
{
    /** @var string The name of the Git module. */
    public $gitmodule;
    /** @var string The path to the root directory of the vendor folder. */
    public $root;
    /** @var mixed The current module instance. */
    public $module;

    /**
     * Constructor.
     *
     * @param string $id The ID of this controller.
     * @param mixed $module The current module instance.
     * @param array $config Name-value pairs that will be used to initialize the object properties.
     */
    public function __construct($id, $module, $config = [])
    {
        parent::__construct($id, $module, $config);
        $this->root = Yii::getAlias('@vendor');
        $this->module=Yii::$app->controller->module;

    }

    /**
     * Returns the options and their default values for this controller.
     *
     * @param string $actionID The ID of the current action.
     * @return array The options and their default values.
     */
    public function options($actionID)
    {
        return ['gitmodule'];
    }

    /**
     * Returns the aliases for the options of this controller.
     *
     * @return array The aliases for the options of this controller.
     */
    public function optionAliases()
    {
        return ['m' => 'gitmodule'];
    }

    /**
     * Generates migration schemas for all the tables in all the Git modules.
     */
    public function actionGenerateAllSchemas() {

        $dir = array_filter(scandir($this->root . "/{$this->module->params['pattern']}/"), function($file) {
            return is_dir($this->root . "/{$this->module->params['pattern']}/" . $file) && $file != '..' && $file != '.';
        });

        foreach ($dir as $file) {
            $this->gitmodule = "{$this->module->params['pattern']}/$file";


            $migrationsTablesFile=$this->root . "/{$this->module->params['pattern']}/" . $file ."/src/MigrationTables.php";

            if(file_exists($migrationsTablesFile)) {
                LogHelper::log("info","----------------------------------------------------");
                LogHelper::log("INFO",Yii::t('automigration', 'Start creating migration schemas for module {module}', [
                    'module' => $this->gitmodule,
                ]));
                LogHelper::log("info","----------------------------------------------------");
                $tables=require $migrationsTablesFile;
                foreach ($tables as $table) {
                    $this->actionGenerateSchemaFile($table);
                }
                LogHelper::log("info","----------------------------------------------------");
                LogHelper::log("info",Yii::t('automigration', 'Schemas for module {module} created', [
                    'module' => $this->gitmodule,
                ]));
                LogHelper::log("info","----------------------------------------------------");
            }
        }
    }

    /**
     * Generates a migration schema file for the specified table.
     *
     * @param string $table The name of the table for which to generate the schema file.
     * @param bool $backup Whether to generate the file in the backup folder.
     * @param bool $return Whether to return the schema array
     **/
    public function actionGenerateSchemaFile($table, $backup=false, $return=false)
    {
        $db = Yii::$app->getDb();
        $tableSchema = $db->getTableSchema($table);

        if ($tableSchema === null) {
            LogHelper::log("error",Yii::t('automigration', 'Table {table} does not exist in the database', [
                'table' => $table,
            ]));
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $columns = json_decode(json_encode($tableSchema->columns));


        $schema = [
            'table' => $tableSchema->name,
            'columns' => $columns,
            'keys' => $this->getAllTableKeys($table),
        ];

        if($backup) {
            $path="{$this->root}/{$this->gitmodule}/rollback_schema/";
        } else {
            $path="{$this->root}/{$this->gitmodule}/schema/";
        }

        if(!is_dir($path)) {
            LogHelper::log("info",Yii::t('automigration', 'Creating the directory: {path}', [
                'path' => $path,
            ]));
            if (!mkdir($path, 0777) && !is_dir($path)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $path));
            }
        }

        if($return) {
            return $schema;
        }

        $schemaFile =  $path. $table . '.php';
        file_put_contents($schemaFile, '<?php return ' . var_export($schema, true) . ';');

        LogHelper::log("info",Yii::t('automigration', 'Schema file generated for table {table} in {path}', [
            'path' => $path,
            'table' => $table
        ]));

        return ExitCode::OK;
    }

    /**
     * Apply all migrations for each module.
     *
     * @return void
     */
    public function actionApplyAllMigrations() {

        $dir = array_filter(scandir($this->root . "/{$this->module->params['pattern']}/"), function($file) {
            return is_dir($this->root . "/{$this->module->params['pattern']}/" . $file) && $file != '..' && $file != '.';
        });

        foreach ($dir as $file) {
            $this->gitmodule = "{$this->module->params['pattern']}/$file";


            $migrationsTablesFile=$this->root . "/{$this->module->params['pattern']}/" . $file ."/src/MigrationTables.php";

            if(file_exists($migrationsTablesFile)) {
                LogHelper::log("info","----------------------------------------------------");
                LogHelper::log("INFO",Yii::t('automigration', 'Start tables migration for module {module}', [
                    'module' => $this->gitmodule,
                ]));
                LogHelper::log("info","----------------------------------------------------");
                $tables=require $migrationsTablesFile;
                foreach ($tables as $table) {
                    $this->actionApply($table);
                }
                LogHelper::log("info","----------------------------------------------------");
                LogHelper::log("INFO",Yii::t('automigration', 'End tables migration for module {module}', [
                    'module' => $this->gitmodule,
                ]));
                LogHelper::log("info","----------------------------------------------------");
            }
        }
    }

    /**
     * Apply migration for a specified table.
     *
     * @param string  $table     Table name
     * @param boolean $rollback  Whether to rollback or not
     *
     * @return int Exit code
     */
    public function actionApplyMigration($table, $rollback=false)
    {
        $alterCommands=[];
        $keysCommands=[];

        $db = Yii::$app->getDb();
        if($rollback) {
            LogHelper::log("WARN",Yii::t('automigration', 'Start rollback procedure'));
            $schemaFile = "{$this->root}/{$this->gitmodule}/rollback_schema/" . $table . '.php';
        } else {
            $schemaFile = "{$this->root}/{$this->gitmodule}/schema/" . $table . '.php';
        }
        if (!file_exists($schemaFile)) {
            LogHelper::log("error",Yii::t('automigration', 'Schema not found for table {table}', [
                'table' => $table,
            ]));
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $schema = require $schemaFile;

        if (!isset($schema['table'], $schema['columns'], $schema['keys'])) {
            LogHelper::log("error",Yii::t('automigration', 'invalid schema file for table {table}', [
                'table' => $table,
            ]));
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $tableName = $schema['table'];
        $columns = $schema['columns'];
        $keys = $schema['keys'];

        $tableSchema = $db->getTableSchema($tableName);

        $createCommand="";

        if ($tableSchema === null) {
            $createCommand.=$this->generateCreateTable($tableName,$columns);
        } else {
            $alterCommands = $this->generateSqlEditFieldsStatements($tableSchema->getColumnNames(), $columns, $tableName, $tableSchema);
        }

        //echo $createCommand;
        $keysCommands=$this->generateSqlEditKeysStatements($this->getAllTableKeys($tableName), $keys, $tableName);

        if ($createCommand==="" && (count($alterCommands) === 0) && (count($keysCommands) === 0)) {
            LogHelper::log("info",Yii::t('automigration', 'No changes for table {table}', [
                'table' => $tableName,
            ]));
            return ExitCode::OK;
        }

        if(!$rollback && $createCommand==="") {
            $this->actionGenerateSchemaFile($table, true);
            MysqlDumpHelper::dumpTable($table, "{$this->root}/{$this->gitmodule}/dumps");
        }

        $transaction = $db->beginTransaction();
        try {

            if($createCommand!=="") {

                LogHelper::log("info",$createCommand);
                if(LogHelper::confirm(Yii::t('automigration', 'Do you want to create the table?')." [Y|n]")){
                    $db->createCommand($createCommand)->execute();

                    LogHelper::log("info",Yii::t('automigration', 'Table {table} create with command {createCommand}', [
                        'table' => $tableName,
                        'createCommand' => $createCommand,
                    ]));
                }


            }

            foreach ($alterCommands as $alterCommand) {
                LogHelper::log("info", $alterCommand);

                if(LogHelper::confirm(Yii::t('automigration', 'Do you want to launch this command?')." [Y|n]")) {
                    $db->createCommand($alterCommand)->execute();
                    LogHelper::log("info",Yii::t('automigration', 'Execute the command: {command}', [
                        'command' => $alterCommand,
                    ]));
                }


            }

            foreach ($keysCommands as $keyCommand) {
                LogHelper::log("info", $keyCommand);

                if(LogHelper::confirm(Yii::t('automigration', 'Do you want to launch this command?')." [Y|n]")) {
                    $db->createCommand($keyCommand)->execute();
                    LogHelper::log("info",Yii::t('automigration', 'Execute the command: {command}', [
                        'comman' => $keyCommand,
                    ]));
                }
            }

            $transaction->commit();
            LogHelper::log("info",Yii::t('automigration', 'Changes for table {table} applied', [
                'table' => $tableName,
            ]));
            return ExitCode::OK;

        } catch (\Exception $e) {
            if(!$rollback) {
                $transaction->rollBack();
                $this->actionApply($tableName, true);
            }
            LogHelper::log("error",$e->getMessage());
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * @param $name
     * @param $table
     * @return bool
     * @throws \yii\base\NotSupportedException
     */
    private function getTableIndex($name, $table){
        $db = Yii::$app->getDb();
        $indexes=$db->getSchema()->getTableIndexes($table);
        foreach ($indexes as $key=>$index) {
            if($name===$key) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param $tableName
     * @param $columnName
     * @param $columnSchema
     * @param bool $add
     * @return string
     */
    public function generateAlterColumnSql($tableName, $columnName, $columnSchema, $add = true)
    {
        $sql = '';

        $type = $columnSchema->dbType;
        $isPrimaryKey = $columnSchema->isPrimaryKey;
        $isNullable = $columnSchema->allowNull;
        $defaultValue = $columnSchema->defaultValue;
        $comment = $columnSchema->comment;
        $autoIncrement = $columnSchema->autoIncrement;

        if ($add) {
            $sql = 'ALTER TABLE `' . $tableName . '` ADD `' . $columnName . '` ' . $type;
        } else {
            $sql = 'ALTER TABLE `' . $tableName . '` MODIFY `' . $columnName . '` ' . $type;
        }

        return $this->columnDefsExt($isPrimaryKey, $sql, $isNullable, $defaultValue, $comment, $autoIncrement);
    }

    /**
     * @param $columnName
     * @param $columnSchema
     * @return string
     */
    public function generateColumnDefinitions($columnName, $columnSchema)
    {
        $sql = '';

        $type = $columnSchema->dbType;
        $isPrimaryKey = $columnSchema->isPrimaryKey;
        $isNullable = $columnSchema->allowNull;
        $defaultValue = $columnSchema->defaultValue;
        $comment = $columnSchema->comment;
        $autoIncrement = $columnSchema->autoIncrement;

        $sql = '`'.$columnName . '` ' . $type;

        return str_replace(";", ",", $this->columnDefsExt($isPrimaryKey, $sql, $isNullable, $defaultValue, $comment, $autoIncrement))."\n";
    }

    /**
     * @param $productionColumns
     * @param $deploymentColumns
     * @param $tableName
     * @param $productionSchema
     * @return array
     */
    public function generateSqlEditFieldsStatements($productionColumns, $deploymentColumns, $tableName, $productionSchema){

        $alterCommands = [];

        foreach ($deploymentColumns as $name=>$schema) {
            $production_column = $productionSchema->getColumn($name);
            //LogHelper::log("info", "Column name deploy cycle: $name");
            if (!$production_column) {
                $alterCommands[] = $this->generateAlterColumnSql($tableName, $name, $deploymentColumns->$name);
            } elseif ($this->hasSchemaChanges($deploymentColumns->$name, $production_column)) {
                $alterCommands[] = $this->generateAlterColumnSql($tableName, $name, $deploymentColumns->$name, false);
            }
        }

        foreach ($productionColumns as $name) {
            $exists=isset($deploymentColumns->$name);
            //LogHelper::log("info", "Column name: $name");
            if (!$exists) {
                $alterCommands[] = "ALTER TABLE `$tableName` DROP COLUMN `$name`";
            }
        }

        return $alterCommands;
    }


    /**
     * @param $productionKeys
     * @param $deploymentKeys
     * @param $tableName
     * @return array
     */
    public function generateSqlEditKeysStatements($productionKeys, $deploymentKeys, $tableName){
        $alterCommands = [];


        foreach ($deploymentKeys as $name=>$schema) {

            $exists=isset($productionKeys[$name]);

            //print_r($productionKeys);
            //LogHelper::log("info", "Index name deploy cycle: $name");
            if (!$exists) {
                //LogHelper::log("info", "$name does not exists");
                $alterCommands[] = $this->getAddKeysSql($tableName, $name, $schema);
            } elseif (!$this->compareObjects($schema, $productionKeys[$name])) {
                //LogHelper::log("info", "$name exists - modify");
                $alterCommands[] = $this->getAddKeysSql($tableName, $name, $schema, false);
            }
        }

        foreach ($productionKeys as $name=>$schema) {
            $exists=isset($deploymentKeys[$name]);
            //LogHelper::log("info", "Index name: $name");
            if (!$exists) {
                $alterCommands[] = "ALTER TABLE `$tableName` DROP INDEX `$name`";
            }
        }

        return $alterCommands;
    }


    /**
     * @param $table
     * @param $deploymentColumns
     * @return string
     */
    public function generateCreateTable($table, $deploymentColumns){
        $definitions="";

        foreach ($deploymentColumns as $name=>$schema) {
            $definitions.=$this->generateColumnDefinitions($name,$schema);
        }
        $definitions=substr(trim($definitions),0, -1);
        $sql="CREATE TABLE `$table` (\n$definitions);\n";

        return $sql;
    }


    /**
     * @param $oldColumnSchema
     * @param $newColumnSchema
     * @return bool
     */
    public function hasSchemaChanges($oldColumnSchema, $newColumnSchema)
    {
        $oldProps = get_object_vars($oldColumnSchema);
        $newProps = get_object_vars($newColumnSchema);

        foreach ($oldProps as $propName => $oldValue) {
            if ($propName === '_errors') {
                continue;
            }

            if (!array_key_exists($propName, $newProps)) {
                return true;
            }

            $newValue = $newProps[$propName];

            if ($oldValue !== $newValue) {
                return true;
            }
        }

        foreach ($newProps as $propName => $newValue) {
            if ($propName === '_errors') {
                continue;
            }

            if (!array_key_exists($propName, $oldProps)) {
                return true;
            }
        }

        return false;
    }


    /**
     * Get the difference between the given key definition and the actual key schema.
     *
     * @param string $name the name of the key
     * @param string|array $definition the key definition in the schema file
     * @param \yii\db\mysql\IndexSchema|null $keySchema the actual key schema, null if the key does not exist
     * @return string the alter command for modifying the key, empty if no changes needed
     */
    private function getKeyDiff($name, $definition, $keySchema)
    {
        if ($keySchema === null) {
            // The key does not exist, return the add command
            return 'ADD ' . $definition;
        }

        if (!is_array($definition)) {
            throw new \Exception('Cannot add key ' . $name . ' because it already exists.');
        }

        $actualColumns = $keySchema->columnNames;
        $expectedColumns = $definition;
        sort($actualColumns);
        sort($expectedColumns);

        if ($actualColumns != $expectedColumns) {
            // The columns in the key are different, return the drop and add command
            return 'DROP INDEX ' . $name . ', ADD ' . $definition;
        }

        return '';
    }

    /**
     * @param $table
     * @param $indexName
     * @param $value
     * @param bool $add
     * @return string
     */
    function getAddKeysSql($table, $indexName, $value, $add=true) {
        $query = '';
        if(!$add) {
            $query="SET foreign_key_checks = 0; ALTER TABLE $table DROP INDEX $indexName; SET foreign_key_checks = 1; ";
        }

        if (isset($value->isPrimary) && $value->isPrimary) {
            if(!$add) {
                $query="SET foreign_key_checks = 0; ALTER TABLE $table DROP INDEX `PRIMARY`; SET foreign_key_checks = 1; ";
            }
            $query .= 'SET foreign_key_checks = 0; ALTER TABLE '.$table.' ADD PRIMARY KEY ('.implode(",",$value->columnNames).'); SET foreign_key_checks = 1; ';
        } elseif (isset($value->foreignTableName)) {
            if(!$add) {
                $query="SET foreign_key_checks = 0; ALTER TABLE $table DROP FOREIGN KEY $indexName; SET foreign_key_checks = 1; ";
            }
            $query .= 'SET foreign_key_checks = 0; ALTER TABLE '.$table.' ADD CONSTRAINT '.$value->name.' FOREIGN KEY ('.implode(",",$value->columnNames).') REFERENCES '.$value->foreignTableName.'('.$value->foreignColumnNames[0].') ON UPDATE '.$value->onUpdate.' ON DELETE '.$value->onDelete.'; SET foreign_key_checks = 1;';

        } elseif (isset($value->isUnique) && $value->isUnique) {
            $query .= 'SET foreign_key_checks = 0; CREATE UNIQUE INDEX '.$value->name.' ON '.$table.'('.implode(",",$value->columnNames).'); SET foreign_key_checks = 1; ';
        } else {
            $query .= 'SET foreign_key_checks = 0; ALTER TABLE '.$table.' ADD INDEX '.$value->name.' ('.implode(",",$value->columnNames).');SET foreign_key_checks = 1; ';
        }


        return $query;
    }

    /**
     * @param $table
     * @return array
     * @throws \yii\base\NotSupportedException
     */
    public function getAllTableKeys($table){
        $db = Yii::$app->getDb();
        $foreign_keys = json_decode(json_encode($db->getSchema()->getTableForeignKeys($table)));
        $keys = json_decode(json_encode($db->getSchema()->getTableIndexes($table)));;

        $keys=(object)(array_merge((array)$foreign_keys, (array)$keys));
        $finalKeys=[];
        foreach ($keys as $key) {
            if($key->name!="") {
                $finalKeys[$key->name]=$key;
            }
        }
        return $finalKeys;
    }


    /**
     * @param $isPrimaryKey
     * @param string $sql
     * @param $isNullable
     * @param $defaultValue
     * @param $comment
     * @return string
     */
    public function columnDefsExt($isPrimaryKey, string $sql, $isNullable, $defaultValue, $comment, $autoIncrement): string
    {
        if ($isPrimaryKey) {
            $sql .= ' PRIMARY KEY';
        }

        if ($autoIncrement) {
            $sql .= ' AUTO_INCREMENT';
        }

        if (!$isNullable) {
            $sql .= ' NOT NULL';
        }

        if (!is_null($defaultValue)) {
            $defaultValue = is_string($defaultValue) ? "'" . $defaultValue . "'" : $defaultValue;
            $sql .= ' DEFAULT ' . $defaultValue;
        }

        if ($comment) {
            $sql .= " COMMENT '" . $comment . "'";
        }

        $sql .= ';';

        return $sql;
    }

    /**
     * @param $obj1
     * @param $obj2
     * @return bool
     */
    function compareObjects($obj1, $obj2) {
        if (count((array)$obj1) !== count((array)$obj2)) {
            return false;
        }

        foreach ($obj1 as $key => $value) {
            if (!property_exists($obj2, $key)) {
                return false;
            }

            if (is_object($value) && is_object($obj2->$key)) {
                $result = compareObjects($value, $obj2->$key);
                if (!$result) {
                    return false;
                }
            } else {
                if ($value !== $obj2->$key) {
                    return false;
                }
            }
        }

        //LogHelper::log("INFO", "TRUE");
        return true;

    }

}