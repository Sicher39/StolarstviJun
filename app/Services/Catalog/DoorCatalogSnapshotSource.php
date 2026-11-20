<?php

namespace App\Services\Catalog;

/**
 * The database connection an export reads from, as resolved from the configuration of this
 * installation.
 *
 * The catalog snapshot is meant to be taken from the local database and carried to the target server
 * by hand. A connection pointing anywhere else would silently put foreign data into the bundle, so
 * this value object answers the single question the export has to ask before it reads a row: is the
 * configured server provably the machine the command runs on?
 *
 * The class touches nothing: no database, no filesystem, no container. The rule can therefore be
 * verified without a connection.
 */
class DoorCatalogSnapshotSource
{
    /**
     * Drivers that keep the whole database in a file, so they have no host at all.
     */
    private const FILE_DRIVERS = ['sqlite'];

    /**
     * Host names that always resolve to the machine the command runs on.
     */
    private const LOOPBACK_HOSTS = ['localhost', '127.0.0.1', '::1'];

    public function __construct(
        public readonly string $connection,
        public readonly string $driver,
        public readonly string $database,
        public readonly ?string $host,
    ) {}

    /**
     * @param  array<string, mixed>  $config  the connection configuration, as resolved by Laravel
     */
    public static function fromConnectionConfig(string $connection, array $config): self
    {
        $host = $config['host'] ?? null;

        return new self(
            connection: $connection,
            driver: (string) ($config['driver'] ?? ''),
            database: (string) ($config['database'] ?? ''),
            host: is_string($host) && trim($host) !== '' ? trim($host) : null,
        );
    }

    /**
     * Only a loopback host counts as local. A container service name, a LAN address or a public host
     * all reach a server that is not this machine, and the export must not assume otherwise.
     */
    public function isLocal(): bool
    {
        if (in_array(strtolower($this->driver), self::FILE_DRIVERS, true)) {
            return true;
        }

        if ($this->host === null) {
            // Without a host the driver reaches its server through a local socket or pipe.
            return true;
        }

        // A unix socket path (for example /var/run/postgresql) always points at a local server.
        if (str_starts_with($this->host, '/')) {
            return true;
        }

        return in_array(strtolower($this->host), self::LOOPBACK_HOSTS, true);
    }

    /**
     * Refuses an export that would carry the data of another machine. The operator can still export
     * such a database, but only by naming the opt in option explicitly, never by accident.
     */
    public function assertExportable(bool $allowNonLocal): void
    {
        if ($allowNonLocal || $this->isLocal()) {
            return;
        }

        throw DoorCatalogSnapshotException::invalid(
            'Databáze neběží na tomto počítači, snapshot by neobsahoval lokální data. Export byl odmítnut.',
            [
                'Zdroj: '.$this->describe().'.',
                'Export z této databáze povolíte jen vědomě, parametrem --allow-nonlocal.',
            ],
        );
    }

    /**
     * One line naming the connection, the driver, the database and the host, so the operator can
     * confirm what the bundle really contains.
     */
    public function describe(): string
    {
        return sprintf(
            'spojení "%s" (%s), databáze "%s"%s',
            $this->connection,
            $this->driver,
            $this->database,
            $this->host === null ? '' : ', host '.$this->host,
        );
    }

    /**
     * The values written to the manifest, so the target installation can tell what it receives.
     *
     * @return array{connection: string, driver: string, database: string, host: string|null}
     */
    public function toArray(): array
    {
        return [
            'connection' => $this->connection,
            'driver' => $this->driver,
            'database' => $this->database,
            'host' => $this->host,
        ];
    }
}
