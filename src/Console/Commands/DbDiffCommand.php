<?php

namespace Xnoire666\DbDiff\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Support\Facades\Storage;

class DbDiffCommand extends Command
{
    protected $signature = 'db:diff';

    protected $description = 'Diff two MySQL databases and write SQL files for missing tables, views, columns, and column property mismatches.';

    private $create_column_last_table = null;
    private $create_column_sql_buffer = '';

    private $alter_column_last_table = null;
    private $alter_column_sql_buffer = '';

    public function handle()
    {
        $mysql1 = config('db-diff.connections.mysql1');
        $mysql2 = config('db-diff.connections.mysql2');

        // Create temporary connections on the fly
        $factory = app(ConnectionFactory::class);

        $conn1 = $factory->make($mysql1, 'mysql1');
        $conn2 = $factory->make($mysql2, 'mysql2');

        // Run queries
        $db1 = $conn1->select("SHOW TABLES");
        $db2 = $conn2->select("SHOW TABLES");

        $loopIndex = 0;

        foreach ($db1 as $d1) {

            $loopIndex++;


            if ($loopIndex % 50 === 0)  {

                $this->warn("Refreshing connections at loop #$loopIndex...");

                // re-make connections using the factory
                $conn1 = $factory->make($mysql1, 'mysql1');
                $conn2 = $factory->make($mysql2, 'mysql2');
            }


            $row = (array) $d1;
            $tableName = reset($row);


            // check if table exists in DB2
            $tableNameEscaped = str_replace(['%', '_'], ['\\%', '\\_'], $tableName); // escape LIKE wildcards
            $tableCheck = $conn2->select("SHOW TABLES LIKE '{$tableNameEscaped}'");

            if (empty($tableCheck)) {
                $this->warn("Table `$tableName` does NOT exist in DB2");

                $this->createTableOrViewSql($tableName, $conn1);

                continue;
            }

            $this->info("Table `$tableName` exists in both DB1 & DB2");


            // get columns from DB1
            $columns1 = $conn1->select("SHOW FULL COLUMNS FROM `$tableName`");
            $columns2 = $conn2->select("SHOW FULL COLUMNS FROM `$tableName`");
            $columns2Map = collect($columns2)->keyBy('Field');


            foreach ($columns1 as $col1) {

                $colName = $col1->Field;

                // check if column exists in DB2
                $colNameEscaped = str_replace(['%', '_'], ['\\%', '\\_'], $colName);
                $columnCheck = $conn2->select("SHOW FULL COLUMNS FROM `$tableName` LIKE '{$colNameEscaped}'");

                if (empty($columnCheck)) {

                    $this->createColumnSql($tableName, $col1);

                    $this->warn("   Column `$colName` missing in DB2.`$tableName`");


                } else {

                    $createTableSql = $conn1->select("SHOW CREATE TABLE `$tableName`");
                    $createStatement = (array) $createTableSql[0];

                    //skip column prop check if table is a view
                    if(isset($createStatement['View'])) {

                        $this->info("   Column `$colName` in DB2.`$tableName` is from VIEW. skipping.....");

                        continue;
                    }

                    $this->compareColumnProps($tableName, $col1, $colName, $columns2Map);

                    $this->info("   Column `$colName` exists in DB2.`$tableName`");

                }
            }

        }


        return 0;
    }


    private function createTableOrViewSql($tableName, $conn1)
    {

        $createTableSql = $conn1->select("SHOW CREATE TABLE `$tableName`");
        $createStatement = (array) $createTableSql[0];

        if(isset($createStatement['Create Table'])) {

             $createStatement = $createStatement['Create Table'];

            // Wrap it in IF NOT EXISTS
            $createStatement = preg_replace(
                '/^CREATE TABLE `/i',
                'CREATE TABLE IF NOT EXISTS `',
                $createStatement
            );


            // Step 2: remove AUTO_INCREMENT=number
            $createStatement = preg_replace('/AUTO_INCREMENT=\d+\s*/i', '', $createStatement);


            // Step 3: normalize semicolon
            $createStatement = rtrim($createStatement, ";") . ";\n\n";


            $fileName = 'missing_tables.sql';
            $this->disk()->append($fileName, $createStatement);

        } else if(isset($createStatement['Create View'])) {


            $viewName = $createStatement['View'];

            // Get CREATE VIEW statement
            $createViewSql = $conn1->select("SHOW CREATE VIEW `$viewName`");


            if (!empty($createViewSql)) {
                $createStatement = $createViewSql[0]->{'Create View'};

                // Replace CREATE VIEW with CREATE OR REPLACE VIEW
                $createStatement = preg_replace(
                    '/^CREATE(.+)VIEW/i',
                    'CREATE OR REPLACE VIEW',
                    $createStatement
                );

                // Normalize semicolon
                $createStatement = rtrim($createStatement, ";") . ";\n\n";

                // Save to file
                $this->disk()->append('missing_views.sql', $createStatement);
            }



        }else {

            $this->info(array_key_first($createStatement). ' skipping.');

        }
    }



    private function createColumnSql($tableName, $col1)
    {

        // Build column definition from DB1 info
        $colDef = $col1->Type;
        if ($col1->Null === 'NO') {
            $colDef .= " NOT NULL";
        }
        if ($col1->Default !== null) {
            $colDef .= " DEFAULT " . (is_numeric($col1->Default) ? $col1->Default : "'{$col1->Default}'");
        }
        if ($col1->Extra) {
            $colDef .= " " . strtoupper($col1->Extra);
        }

         // Check if same table as last iteration
        if($this->create_column_last_table === $tableName) {

            // Same table → append ADD COLUMN
            $this->create_column_sql_buffer .= ", ADD COLUMN `{$col1->Field}` $colDef";

        }else {

            // Different table → flush the previous SQL if any
            if (!empty($this->create_column_sql_buffer)) {

                $this->create_column_sql_buffer .= ";\n";
                $fileName = 'missing_columns.sql';
                $this->disk()->append($fileName, $this->create_column_sql_buffer);
            }

            // Start new ALTER TABLE for this table
            $this->create_column_sql_buffer = "ALTER TABLE `$tableName` ADD COLUMN `{$col1->Field}` $colDef";

        }

         // Update tracker
        $this->create_column_last_table = $tableName;

    }



    private function compareColumnProps($tableName, $col1, $colName, $columns2Map)
    {

        $col2 = $columns2Map[$colName] ?? null;


        if ($col2)
        {
            $differences = [];

            // Compare Type
            if (strtolower($col1->Type) !== strtolower($col2->Type)) {
                $differences[] = "TYPE {$col2->Type} → {$col1->Type}";
            }

            // Compare Nullability
            if ($col1->Null !== $col2->Null) {
                $differences[] = "NULLABLE {$col2->Null} → {$col1->Null}";
            }

            // Compare Default
            if ($col1->Default != $col2->Default) {
                $differences[] = "DEFAULT {$col2->Default} → {$col1->Default}";
            }

            // Compare Extra (auto_increment, etc.)
            if ($col1->Extra !== $col2->Extra) {
                $differences[] = "EXTRA {$col2->Extra} → {$col1->Extra}";
            }

            if (!empty($differences)) {

                $this->warn("Column `$colName` differs in `$tableName`: " . implode(', ', $differences));

                // Generate ALTER SQL

                $colDef = $col1->Type; // e.g. int(11), varchar(255)

                if ($col1->Collation && stripos($col1->Type, 'char') !== false) {
                    $colDef .= " COLLATE {$col1->Collation}";
                }
                if ($col1->Null === 'NO') {
                    $colDef .= " NOT NULL";
                } else {
                    $colDef .= " NULL";
                }
                if ($col1->Default !== null) {
                    $colDef .= " DEFAULT " . (is_numeric($col1->Default) ? $col1->Default : "'{$col1->Default}'");
                }
                if ($col1->Extra) {
                    $colDef .= " " . strtoupper($col1->Extra);
                }


                 // Check if same table as last iteration
                if($this->alter_column_last_table === $tableName) {

                    // Same table → append MODIFY COLUMN
                    $this->alter_column_sql_buffer .= ", MODIFY COLUMN `{$colName}` $colDef";

                }else {

                    // Different table → flush the previous SQL if any
                    if (!empty($this->alter_column_sql_buffer)) {

                        $this->alter_column_sql_buffer .= ";\n";
                        $fileName = 'mismatch_column_props.sql';
                        $this->disk()->append($fileName, $this->alter_column_sql_buffer);
                    }

                    $this->alter_column_sql_buffer = "ALTER TABLE `$tableName` MODIFY COLUMN `{$colName}` $colDef";

                }


                $this->alter_column_last_table = $tableName;

            }
        }

    }

    private function disk()
    {
        return Storage::disk(config('db-diff.output_disk', 'local'));
    }
}
