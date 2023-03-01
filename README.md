yii2-auto-migration
===================

Why a new database migration software?

In agile software development, database migration often requires additional efforts that, if not followed step by step during development, can significantly slow down the deployment phase. Each table variation requires creating a migration and applying it, which takes time and requires a high level of discipline to manage all changes correctly.

Lack of these things, when development needs to proceed quickly, risks compromising the software deployment process, slowing down the development team.

This migration software was born from the idea that this activity is not valuable and should be automated. Based on this idea, we created a system that automatically saves the database schema structure and, during migration, automatically identifies variations and executes the necessary SQL code to apply them without having to write all the migrations or generate them manually table by table.

In addition, the system also implements a rollback procedure that uses the same mechanism and allows you to revert changes.

We do not think that this first version is perfect, but we are already using it, and we are reasonably confident that it handles most possible cases.

What is missing?

1. Table migration procedure, which also requires moving data.
2. Restore dump procedure (currently requires manual import).
3. Verify that the SQL code covers all possible field types.
4. Properly manage the collation set to maintain the same character set (currently uses the DB's default collation).
5. Make it usable even with modules that cannot be installed with composer (in vendor) - currently, the system is set to our habit of having a private composer repository for all the modules we develop, but we are aware that not everyone has this habit and would like to make this system more universal.

Certainly, in addition to this, it will be necessary to perform unittests, debugging, and understand if other changes are necessary to make it a complete product.

Any help from the community to implement these features or any other contribution to improve this system will be welcome.

Thank you!

------ 

This Yii2 module provides a console controller to automate the creation of migration schemas and the application of tables migrations for modules that follow the pattern specified in the configuration.

Features:

1. Generate all modules migration schemas
2. Generate single table migration schemas
3. Apply all modules migration schemas
4. Apply single table migration schema
5. Rollback single table migration
6. Dump table before apply migration


Installation
------------

The preferred way to install this module is through composer.

Either run

```
php composer.phar require sharkom/yii2-auto-migration "@dev"
```

or add

```
"sharkom/yii2-auto-migration": "@dev"
```


to the require section of your composer.json file.


Usage
-----

To use this module, you need to add it to the modules section of your Yii2 configuration file:

```
'modules' => [
    'automigration' => [
        'class' => 'sharkom\automigration\Module',
        'interactive'=>1 //if 1 ask configmation before to apply migrations
        'pattern'=>"" //if 1 ask configmation before to apply migrations
    ],
],
```

In the pattern param you have to specify the vendor directory for your modules

In order to work each module that needs migrations has to have a MigrationTables.php file that specify the DB table names in its directory (ex. vendor/sharkom/yii2-cron/MigrationTables.php)

```
<?php
return [
    "cron_job",
    "cron_job_run",
];
```


Once the module is added, you can use the provided console controller to generate the migration schemas and apply the tables migrations for all the modules that follow the specified pattern in the vendor directory.

To generate all the migration schemas, run the following command:

```
./yii automigration/migrate/generate-all-schemas
```


To generate the migration schema for a single table, run the following command:

```
./yii automigration/migrate/generate-schema-file <table-name> -m=<pattern>/<module>
```


To apply all the tables migrations, run the following command:

```
./yii automigration/migrate/apply-all-migrations
```


To apply the tables migration for a single table, run the following command:

```
./yii automigration/migrate/apply-migration <table-name> -m=<pattern>/<module>
```

To rollback a migration for a single table , run the following command:

```
./yii automigration/migrate/apply-migration <table-name> 1 -m=<pattern>/<module>
```

Paths
-----
* The schemas generated are stored in each module in the "schema" directory (ex. sharkom/yii2-cron/schema)
* Rollback schemas are stored in the "rollback_schema" directory (ex. sharkom/yii2-cron/rollback_schema)
* Backup dumps are stored in the "dumps directory" (ex. sharkom/yii2-cron/dumps)