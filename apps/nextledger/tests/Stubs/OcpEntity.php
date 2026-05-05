<?php

declare(strict_types=1);

namespace OCP\AppFramework\Db;

/**
 * Minimal stand-in for OCP\AppFramework\Db\Entity so that NextLedger entity
 * classes work in unit tests without booting the full Nextcloud server.
 * Replicates only the surface NextLedger uses: id, addType(), magic getters
 * and setters via property names.
 */
class Entity {
    public $id;

    /** @var array<string, string> */
    protected array $_fieldTypes = [];

    public function addType(string $field, string $type): void {
        $this->_fieldTypes[$field] = $type;
    }

    public function __call(string $name, array $arguments) {
        if (str_starts_with($name, 'get') && strlen($name) > 3) {
            $prop = lcfirst(substr($name, 3));
            return property_exists($this, $prop) ? $this->{$prop} : null;
        }
        if (str_starts_with($name, 'set') && strlen($name) > 3) {
            $prop = lcfirst(substr($name, 3));
            if (property_exists($this, $prop)) {
                $this->{$prop} = $arguments[0] ?? null;
            }
            return $this;
        }
        throw new \BadMethodCallException(static::class . '::' . $name);
    }
}
