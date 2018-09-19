<?php

namespace Rexlabs\Enum;

use Rexlabs\Enum\Exceptions\InvalidEnumException;
use Rexlabs\Enum\Exceptions\InvalidKeyException;
use Rexlabs\Enum\Exceptions\InvalidValueException;

/**
 * Enum implementation.
 *
 * @author    Jodie Dunlop <jodie.dunlop@rexsoftware.com.au>
 * @copyright 2018 Rex Software Pty Ltd
 */
abstract class Enum
{
    /** @var array Cache of constant name => key per class */
    public static $namesToKeysMap = [];

    /** @var array Cache of key => value per class (santized version of what map() returns) */
    public static $keysToValuesMap = [];

    /** @var string */
    protected $name;

    /** @var mixed */
    protected $key;

    /** @var mixed */
    protected $value;

    /**
     * Enum constructor.
     *
     * @param string $name
     * @param mixed  $key
     * @param mixed  $value
     */
    public function __construct(string $name, $key, $value)
    {
        $this->name = $name;
        $this->key = $key;
        $this->value = $value;
    }

    /**
     * Return the keys for the map
     *
     * @return array
     */
    public static function keys(): array
    {
        return array_keys(static::cachedMap());
    }

    /**
     * Returns a cached version of the map
     * It not only ensures the array supplied by map() is indexed by key,
     * it allows map() to do any intensive one-off operations.
     *
     * @return array
     */
    public static function cachedMap(): array
    {
        // Ensure the map is indexed by key
        $class = static::class;
        if (!isset(static::$keysToValuesMap[$class])) {
            static::$keysToValuesMap[$class] = static::map();
        }

        return static::$keysToValuesMap[$class];
    }

    /**
     * Returns an array of $key => $value.
     * If an empty array is returned, the declared const keys will be used.
     *
     * @return array
     */
    public static function map(): array
    {
        return array_fill_keys(array_values(static::constantMap()), null);
    }

    /**
     * Return the values
     *
     * @return array
     */
    public static function values(): array
    {
        return array_values(static::cachedMap());
    }

    /**
     * Return the constant names
     * Each constant declared in the class will be returned.
     *
     * @return array
     */
    public static function names(): array
    {
        return array_keys(static::constantMap());
    }

    /**
     * Return a map of constant names and their associated key.
     *
     * @return array
     */
    public static function constantMap(): array
    {
        $class = static::class;
        if (!array_key_exists($class, static::$namesToKeysMap)) {
            static::$namesToKeysMap[$class] = (new \ReflectionClass($class))->getConstants();
        }

        return static::$namesToKeysMap[$class];
    }

    /**
     * Handle calls to class::SOME_CONSTANT() and returns a new instance of the class.
     *
     * @param string $name
     * @param array  $arguments
     *
     * @return static
     * @throws InvalidKeyException
     * @throws InvalidEnumException
     */
    public static function __callStatic($name, $arguments)
    {
        $key = static::keyForName($name);
        if ($key === null) {
            throw new InvalidEnumException("Invalid constant name '{$name}' in " . static::class);
        }

        return new static($name, $key, static::valueForKey($key));
    }

    /**
     * Get the key for the given constant name.
     *
     * @param string $name
     *
     * @return null|mixed|string
     */
    public static function keyForName(string $name)
    {
        return static::constantMap()[$name] ?? null;
    }

    /**
     * Get the value for a given key.
     *
     * @param mixed|int|string $key
     *
     * @return mixed|null
     * @throws InvalidKeyException
     */
    public static function valueForKey($key)
    {
        static::requireValidKey($key);

        return static::cachedMap()[$key];
    }

    /**
     * Get the constant name for a given key.
     *
     * @param mixed|int|string $key
     *
     * @return string
     * @throws InvalidKeyException
     */
    public static function nameForKey($key): string
    {
        $name = array_search($key, static::constantMap(), true);
        if ($name === false) {
            throw new InvalidKeyException("Invalid key: $key in " . static::class);
        }

        return $name;
    }

    /**
     * Returns the key for a given value (an inverted search).
     * Since keys can be assigned the same value, only the first match will be
     * returned.
     *
     * @param mixed $value
     * @return mixed
     */
    public static function keyForValue($value)
    {
        $key = array_search($value, static::cachedMap(), true);
        if ($key === false) {
            throw new InvalidValueException("Value '{$value}' not found in map for " . static::class);
        }

        return $key;
    }

    /**
     * Create instance of this Enum from the constant name.
     * This method is case-sensitive, meaning if you declare your constant
     * as const MY_CONST = '...', then you will need to provide 'MY_CONST' as
     * the argument.
     *
     * @param string $name
     *
     * @return static
     * @throws InvalidEnumException
     */
    public static function instanceFromName($name): self
    {
        if (!array_key_exists($name, static::constantMap())) {
            throw new InvalidEnumException(sprintf('Invalid constant name: %s in %s', $name, static::class));
        }
        return static::{$name}();
    }

    /**
     * Create instance of this Enum from the key.
     *
     * @param string|int $key
     *
     * @return static
     * @throws InvalidKeyException
     */
    public static function instanceFromKey($key): self
    {
        $name = array_search($key, static::constantMap(), true);
        if ($name === false) {
            throw new InvalidKeyException(sprintf('Invalid key: %s in %s', $key, static::class));
        }
        return static::{$name}();
    }

    /**
     * Determine if a key exists within the constant map
     *
     * @param mixed|string $key
     *
     * @return boolean
     */
    public static function isValidKey($key): bool
    {
        return array_key_exists($key, static::cachedMap());
    }

    /**
     * Check if the key exists or throw an exception
     *
     * @param mixed|string|int $key
     *
     * @throws InvalidKeyException
     */
    public static function requireValidKey($key)
    {
        if (!static::isValidKey($key)) {
            throw new InvalidKeyException("Invalid key: $key in " . static::class);
        }
    }

    /**
     * Check if the given constant name is valid.
     * @param string $name
     *
     * @return bool
     */
    public static function isValidName(string $name): bool
    {
        return static::keyForName($name) !== null;
    }

    /**
     * Overloads the string behavior, to return the constant name.
     * Same as $instance->name().
     *
     * @return string
     */
    public function __toString()
    {
        return $this->name();
    }

    /**
     * Returns the constant name.
     *
     * @return string
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Returns the value assigned to the constant declaration.
     *
     * @return mixed|string|int
     */
    public function key()
    {
        return $this->key;
    }

    /**
     * Returns the mapped value (null if no value is assigned)
     *
     * @return mixed|null
     */
    public function value()
    {
        return $this->value;
    }

    /**
     * Returns true if this instance is equal to the given key or Enum instance.
     *
     * @param static|mixed $compare
     *
     * @return bool
     * @throws InvalidEnumException
     */
    public function is($compare): bool
    {
        if ($compare instanceof self) {
            return $compare->name() === $this->name();
        }

        if (\is_scalar($compare)) {
            return $compare === $this->key();
        }

        throw new InvalidEnumException('Enum instance or key (scalar) expected but ' . \gettype($compare) . ' given.');
    }
}
