<?php

namespace Tests\Unit;

use App\Services\Catalog\DoorCatalogSnapshotException;
use App\Services\Catalog\DoorCatalogSnapshotSource;
use PHPUnit\Framework\TestCase;

/**
 * The export may only carry the catalog of this machine, so the decision "local or not" is the guard
 * that keeps a remote database out of a bundle. The test exercises the pure value object only: it
 * never opens a connection, reads a file or boots the application, so it is safe to run on a machine
 * whose .env points at a real database.
 *
 * The exporter itself is not covered here, because it needs catalog tables and media disks, and the
 * current phpunit.xml does not define an isolated connection.
 */
class DoorCatalogSnapshotSourceTest extends TestCase
{
    public function test_loopback_hosts_are_local(): void
    {
        foreach (['localhost', 'LOCALHOST', '127.0.0.1', '::1'] as $host) {
            $source = $this->source($host);

            $this->assertTrue($source->isLocal(), sprintf('Host %s musí být považován za lokální.', $host));
        }
    }

    public function test_a_unix_socket_path_is_local(): void
    {
        $this->assertTrue($this->source('/var/run/postgresql')->isLocal());
    }

    public function test_a_sqlite_connection_is_local_without_a_host(): void
    {
        $source = DoorCatalogSnapshotSource::fromConnectionConfig('sqlite', [
            'driver' => 'sqlite',
            'database' => 'E:/b_git/2026-stolarstvi-jun/database/database.sqlite',
        ]);

        $this->assertNull($source->host);
        $this->assertTrue($source->isLocal());
    }

    public function test_a_network_driver_without_a_host_is_not_treated_as_remote(): void
    {
        // Without a host the driver reaches its server through a local socket or pipe.
        $this->assertTrue($this->source(null)->isLocal());
    }

    public function test_a_remote_host_is_not_local(): void
    {
        foreach (['db.example.com', '10.0.0.5', '192.168.1.10', 'mysql'] as $host) {
            $source = $this->source($host);

            $this->assertFalse($source->isLocal(), sprintf('Host %s nesmí být považován za lokální.', $host));
        }
    }

    public function test_a_remote_host_refuses_the_export_by_default(): void
    {
        $source = $this->source('db.example.com');

        try {
            $source->assertExportable(false);
            $this->fail('Export ze vzdálené databáze měl být odmítnut.');
        } catch (DoorCatalogSnapshotException $exception) {
            $this->assertStringContainsString('Export byl odmítnut', $exception->getMessage());
            $this->assertStringContainsString('--allow-nonlocal', implode(' ', $exception->details));
            $this->assertStringContainsString('db.example.com', implode(' ', $exception->details));
        }
    }

    public function test_a_remote_host_may_only_be_exported_after_an_explicit_opt_in(): void
    {
        $this->expectNotToPerformAssertions();

        $this->source('db.example.com')->assertExportable(true);
    }

    public function test_a_local_host_never_needs_the_opt_in(): void
    {
        $this->expectNotToPerformAssertions();

        $this->source('127.0.0.1')->assertExportable(false);
    }

    public function test_a_blank_host_is_read_as_no_host(): void
    {
        $source = $this->source('   ');

        $this->assertNull($source->host);
        $this->assertSame('mysql', $source->driver);
    }

    public function test_the_manifest_values_describe_the_connection(): void
    {
        $source = $this->source('  db.example.com  ');

        $this->assertSame([
            'connection' => 'mysql',
            'driver' => 'mysql',
            'database' => 'stolarstvi',
            'host' => 'db.example.com',
        ], $source->toArray());

        $this->assertSame(
            'spojení "mysql" (mysql), databáze "stolarstvi", host db.example.com',
            $source->describe(),
        );
    }

    public function test_a_connection_without_a_host_describes_no_host(): void
    {
        $source = DoorCatalogSnapshotSource::fromConnectionConfig('sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $this->assertSame('spojení "sqlite" (sqlite), databáze ":memory:"', $source->describe());
    }

    private function source(?string $host): DoorCatalogSnapshotSource
    {
        return DoorCatalogSnapshotSource::fromConnectionConfig('mysql', [
            'driver' => 'mysql',
            'database' => 'stolarstvi',
            'host' => $host,
        ]);
    }
}
