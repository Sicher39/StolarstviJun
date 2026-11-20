<?php

namespace App\Services\Catalog;

use RuntimeException;

/**
 * Every validation failure of a snapshot bundle is reported through this exception, so the export
 * and import commands can abort with one readable message before any write happens.
 *
 * The message is a short Czech sentence that is safe to show to the operator. The details carry the
 * individual problems (missing files, key conflicts) and never contain file contents.
 */
class DoorCatalogSnapshotException extends RuntimeException
{
    /**
     * @param  list<string>  $details
     */
    public function __construct(string $message, public readonly array $details = [])
    {
        parent::__construct($message);
    }

    public static function bundleNotFound(string $path): self
    {
        return new self(sprintf('Adresář se snapshotem neexistuje: %s', $path));
    }

    /**
     * @param  list<string>  $details
     */
    public static function invalid(string $reason, array $details = []): self
    {
        return new self($reason, $details);
    }

    /**
     * @param  list<string>  $conflicts
     */
    public static function conflicts(array $conflicts): self
    {
        return new self(
            'V cílové databázi už existují záznamy se stejnými stabilními klíči. Nic nebylo zapsáno.',
            $conflicts,
        );
    }

    public static function unsafePath(string $path): self
    {
        return new self(sprintf('Snapshot obsahuje nepovolenou cestu: %s', $path));
    }
}
