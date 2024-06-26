<?php

declare(strict_types=1);

namespace JaguarJack\MigrateGenerator\Command;

use JaguarJack\MigrateGenerator\Migration\LaravelMigrationForeignKeys;
use JaguarJack\MigrateGenerator\Migration\ThinkphpMigrationForeignKeys;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use JaguarJack\MigrateGenerator\MigrateGenerator;

class ThinkPHPCommand extends Command
{

    protected function configure()
    {
        // 指令配置
        $this->setName('migration:generate')
            ->setDescription('the app\command\generator command');
    }

    protected function execute(Input $input, Output $output)
    {
        $composer = \json_decode(file_get_contents($this->app->getRootPath() . 'composer.json'), true);

        if (!isset($composer['require']['topthink/think-migration'])) {

            fwrite(STDOUT, 'it found that you have not install [topthink/think-migration]?Y/N' . PHP_EOL);

            $answer = strtolower(trim(fread(STDIN, 1024), PHP_EOL));

            if ($answer == 'y' || $answer == 'yes') {
                exec('composer require topthink/think-migration');
            }
        }

        $this->generate($output);
    }

    protected function generate(Output $output)
    {
        try {
            $migrateGenerator = (new MigrateGenerator('thinkphp'));


            $tables = $migrateGenerator->getDatabase()->getAllTables();

            $migrationsPath = $this->app->getRootPath() . '/database/migrations/';

            if (!is_dir($migrationsPath)) {
                if (!mkdir($migrationsPath, 0777, true) && !is_dir($migrationsPath)) {
                    throw new \RuntimeException(sprintf('Directory "%s" was not created', $migrationsPath));
                }
            }

            foreach ($tables as $key => $table) {
                $migrationFilePath = $this->formatMigrationFilePath($migrationsPath, $table->getName(), $key + 1);
                file_put_contents($migrationFilePath, $migrateGenerator->getMigrationContent($table));

                $output->info(sprintf('%s table migration file generated', $table->getName()));
            }

            $this->foreignKeys($tables, $migrationsPath);
        } catch (\Exception $e) {
            $output->error($e->getMessage());
        }
    }

    protected function foreignKeys($tables, $migrationsPath)
    {
        foreach ($tables as $key => $table) {
            $tableForeign = (new ThinkphpMigrationForeignKeys())->setTable($table);
            if ($tableForeign->hasForeignKeys()) {
                $migrationFilePath = $this->formatMigrationFilePath($migrationsPath, $table->getName(), $key + 1, true);
                file_put_contents($migrationFilePath, $tableForeign->output());
            }
        }
    }

    protected function formatMigrationFilePath($migrationsPath, $tableName, $key, $hasForeignKeys = false)
    {
        $connection = config('database.default');
        $config = config('database.connections.' . $connection);
        $prefix = isset($config['prefix']) ? $config['prefix'] : '';
        $formatTableName = str_replace($prefix, '', $tableName);

        $filePath = $migrationsPath . date('YmdHis') . $key . mt_rand(100, 999) . '_create_' . $formatTableName . '_table';
        $filePath = $hasForeignKeys ? "{$filePath}_foreign_keys.php" : "{$filePath}.php";

        return $filePath;
    }
}

