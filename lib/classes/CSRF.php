<?php

/**
 * Sistema di protezione CSRF, basato sulla libreria Slim CSRF.
 *
 * @since 2.3
 */
class CSRF extends Util\Singleton
{
    /**
     * Prefix for CSRF parameters (omit trailing "_" underscore).
     *
     * @var string
     */
    protected $prefix;

    /**
     * CSRF storage.
     *
     * Should be either an array or an object. If an object is used, then it must
     * implement ArrayAccess and should implement Countable and Iterator (or
     * IteratorAggregate) if storage limit enforcement is required.
     *
     * @var array|ArrayAccess
     */
    protected $storage;

    /**
     * Number of elements to store in the storage array.
     *
     * @var int
     */
    protected $storageLimit;

     /**
      * CSRF Strength.
      *
      * @var int
      */
     protected $strength = 16;

    /**
     * Stores the latest key-pair generated by the class.
     *
     * @var array
     */
    protected $keyPair = null;

    /**
     * Create new CSRF guard.
     *
     * @param string                 $prefix
     * @param null|array|ArrayAccess $storage
     * @param int                    $storageLimit
     */
    protected function __construct($prefix = 'csrf', &$storage = null, $storageLimit = -1)
    {
        $this->prefix = rtrim($prefix, '_');

        $this->storage = &$storage;
        $this->validateStorage();

        $this->storageLimit = $storageLimit;
    }

    /**
     * @return string
     */
    public function getToken()
    {
        if (!$this->keyPair && (!$this->isPersistent() || !$this->loadLastToken())) {
            $this->generateToken();
        }

        return $this->keyPair;
    }

    /**
     * Generates a new CSRF token.
     *
     * @return array
     */
    protected function generateToken()
    {
        // Generate new CSRF token
        $name = uniqid($this->prefix);
        $value = $this->createToken();
        $this->saveToStorage($name, $value);

        $this->keyPair = [
            $this->prefix.'_name' => $name,
            $this->prefix.'_value' => $value,
        ];

        return $this->keyPair;
    }

    public function validate()
    {
        $result = true;

        $this->validateStorage();

        if (in_array($_SERVER['REQUEST_METHOD'], ['POST'])) {
            $name = isset($_POST[$this->prefix.'_name']) ? $_POST[$this->prefix.'_name'] : false;
            $value = isset($_POST[$this->prefix.'_value']) ? $_POST[$this->prefix.'_value'] : false;

            if (!$name || !$value || !$this->validateToken($name, $value)) {
                // Need to regenerate a new token, as the validateToken removed the current one.
                $this->generateToken();

                $result = false;
            }
        }

        // Enforce the storage limit
        $this->enforceStorageLimit();

        return $result;
    }

    /**
     * @param $prefix
     * @param $storage
     *
     * @return mixed
     */
    protected function validateStorage()
    {
        if (is_array($this->storage)) {
            return $this->storage;
        }

        if ($this->storage instanceof ArrayAccess) {
            return $this->storage;
        }

        if (!array_key_exists($this->prefix, $_SESSION)) {
            $_SESSION[$this->prefix] = [];
        }

        $this->storage = &$_SESSION[$this->prefix];

        return $this->storage;
    }

    /**
     * Validate CSRF token from current request against token value stored in $_SESSION.
     *
     * @param string $name  CSRF name
     * @param string $value CSRF token value
     *
     * @return bool
     */
    protected function validateToken($name, $value)
    {
        $token = $this->getFromStorage($name);
        if (function_exists('hash_equals')) {
            $result = ($token !== false && hash_equals($token, $value));
        } else {
            $result = ($token !== false && $token === $value);
        }

        // If we're not in persistent token mode, delete the token.
        if (!$this->isPersistent() || !$result) {
            $this->removeFromStorage($name);
        }

        return $result;
    }

    /**
     * Create CSRF token value.
     *
     * @return string
     */
    protected function createToken()
    {
        return bin2hex(random_bytes($this->strength));
    }

    /**
     * Save token to storage.
     *
     * @param string $name  CSRF token name
     * @param string $value CSRF token value
     */
    protected function saveToStorage($name, $value)
    {
        $this->storage[$name] = $value;
    }

    /**
     * Get token from storage.
     *
     * @param string $name CSRF token name
     *
     * @return string|bool CSRF token value or `false` if not present
     */
    protected function getFromStorage($name)
    {
        return isset($this->storage[$name]) ? $this->storage[$name] : false;
    }

    /**
     * Get the most recent key pair from storage.
     *
     * @return string[]|null Array containing name and value if found, null otherwise
     */
    protected function loadLastToken()
    {
        // Use count, since empty ArrayAccess objects can still return false for `empty`
        if (count($this->storage) < 1) {
            return null;
        }

        foreach ($this->storage as $name => $value) {
            continue;
        }

        $keyPair = [
            $this->prefix.'_name' => $name,
            $this->prefix.'_value' => $value,
        ];

        if ($keyPair) {
            $this->keyPair = $keyPair;

            return true;
        }

        return false;
    }

    /**
     * Remove token from storage.
     *
     * @param string $name CSRF token name
     */
    protected function removeFromStorage($name)
    {
        $this->storage[$name] = ' ';
        unset($this->storage[$name]);
    }

    /**
     * Remove the oldest tokens from the storage array so that there
     * are never more than storageLimit tokens in the array.
     *
     * This is required as a token is generated every request and so
     * most will never be used.
     */
    protected function enforceStorageLimit()
    {
        if ($this->storageLimit < 1) {
            return;
        }

        // $storage must be an array or implement Countable and Traversable
        if (!is_array($this->storage)
            && !($this->storage instanceof Countable && $this->storage instanceof Traversable)
        ) {
            return;
        }

        if (is_array($this->storage)) {
            while (count($this->storage) > $this->storageLimit) {
                array_shift($this->storage);
            }
        } else {
            // array_shift() doesn't work for ArrayAccess, so we need an iterator in order to use rewind()
            // and key(), so that we can then unset
            $iterator = $this->storage;
            if ($this->storage instanceof \IteratorAggregate) {
                $iterator = $this->storage->getIterator();
            }
            while (count($this->storage) > $this->storageLimit) {
                $iterator->rewind();
                unset($this->storage[$iterator->key()]);
            }
        }
    }

    /**
     * Setter for storageLimit.
     *
     * @param int $storageLimit Value to set
     *
     * @return $this
     */
    public function setStorageLimit($storageLimit)
    {
        $this->storageLimit = (int) $storageLimit;
    }

    /**
     * Getter for persistentTokenMode.
     *
     * @return bool
     */
    public function isPersistent()
    {
        return $this->storageLimit < 0;
    }
}