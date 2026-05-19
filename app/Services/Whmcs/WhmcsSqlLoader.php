<?php

namespace App\Services\Whmcs;

use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class WhmcsSqlLoader
{
    public function __construct(
        private readonly string $connectionName,
    ) {}

    public static function fromConfig(): self
    {
        return new self(config('whmcs-import.connection'));
    }

    /**
     * Import a .sql dump into the WHMCS database using the mysql CLI.
     */
    public function loadFile(string $path): void
    {
        if (! is_file($path)) {
            throw new \InvalidArgumentException("SQL file not found: {$path}");
        }

        $config = config("database.connections.{$this->connectionName}");

        if (($config['driver'] ?? '') !== 'mysql' && ($config['driver'] ?? '') !== 'mariadb') {
            throw new \RuntimeException('WHMCS SQL file import requires a mysql/mariadb connection.');
        }

        $host = $config['host'] ?? '127.0.0.1';
        $port = (string) ($config['port'] ?? '3306');
        $database = $config['database'] ?? '';
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';

        if ($database === '') {
            throw new \RuntimeException('WHMCS database name is not configured.');
        }

        $command = [
            'mysql',
            '-h', $host,
            '-P', $port,
            '-u', $username,
            $database,
        ];

        $env = null;
        if ($password !== '') {
            $env = ['MYSQL_PWD' => $password];
        }

        $process = new Process(
            command: $command,
            env: $env,
        );
        $process->setInput(file_get_contents($path) ?: '');
        $process->setTimeout(null);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException(
                'Failed to import SQL file: '.trim($process->getErrorOutput() ?: $process->getOutput())
            );
        }
    }

    public function assertClientsTableExists(): void
    {
        $this->assertTableExists(config('whmcs-import.clients_table'));
    }

    public function assertContactsTableExists(): void
    {
        $this->assertTableExists(config('whmcs-import.contacts_table'));
    }

    public function assertHostingTableExists(): void
    {
        $this->assertTableExists(config('whmcs-import.hosting_table'));
    }

    public function assertProductsTableExists(): void
    {
        $this->assertTableExists(config('whmcs-import.products_table'));
    }

    public function assertDomainsTableExists(): void
    {
        $this->assertTableExists(config('whmcs-import.domains_table'));
    }

    public function assertInvoicesTableExists(): void
    {
        $this->assertTableExists(config('whmcs-import.invoices_table'));
    }

    private function assertTableExists(string $table): void
    {
        if (! DB::connection($this->connectionName)->getSchemaBuilder()->hasTable($table)) {
            throw new \RuntimeException(
                "Table [{$table}] not found on connection [{$this->connectionName}]. ".
                'Restore your WHMCS dump first (use --prepare --file=path/to/dump.sql).'
            );
        }
    }
}
