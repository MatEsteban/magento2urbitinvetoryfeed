<?php

namespace Urbit\InventoryFeed\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Exception;

/**
 * Class Config
 * @package Urbit\InventoryFeed\Model\Config
 *
 * Config properties (stored in $_config static property)
 * @property array $cron
 * @property array $filter
 * @property array $fields
 * @property array $units
 * @property array $inventory
 * @property array $attributes
 */
final class Config
{
    /**
     * Current configuration
     * @var array
     */
    private static $_config = array();

    /**
     * @var ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * Config constructor.
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->_scopeConfig = $scopeConfig;
        $this->load();
    }

    /**
     * Load configuration from magento scope
     */
    public function load()
    {
        if (empty(static::$_config)) {
            static::$_config = $this->_scopeConfig->getValue(
                'inventoryfeed_config',
                ScopeInterface::SCOPE_STORE
            );
        }
    }

    /**
     * @param string $name
     * @return mixed
     * @throws Exception
     */
    public function get($name)
    {
        $param = false;

        if (stripos($name, '/')) {
            list($name, $param) = explode('/', $name);
        }

        if (!isset(self::$_config[$name])) {
            throw new Exception("Try to get unknown configuration field - `{$name}`");
        }

        if ($param !== false) {
            if (!isset(self::$_config[$name][$param])) {
                throw new Exception("Try to get unknown parameter of configuration field - `{$name}/{$param}`");
            }

            return self::$_config[$name][$param];
        }

        return self::$_config[$name];
    }

    /**
     * Get parameter of multiselect config field
     * @param string $name
     * @return array
     * @throws Exception
     */
    public function getSelect($name)
    {
        $val = $this->get($name);

        if (is_array($val)) {
            return $val;
        }

        return explode(",", $val);
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return mixed
     * @throws Exception
     */
    public function set($name, $value)
    {
        $param = false;

        if (stripos($name, '/')) {
            list($name, $param) = explode('/', $name);
        }

        if (!isset(self::$_config[$name])) {
            throw new Exception("Try to set unknown configuration field - `{$name}`");
        }

        if ($param !== false) {
            if (!isset(self::$_config[$name][$param])) {
                throw new Exception("Try to set unknown parameter of configuration field - `{$name}/{$param}`");
            }

            self::$_config[$name][$param] = $value;

        } else {
            self::$_config[$name] = $value;
        }

        return $this;
    }

    /**
     * @param string $name
     * @return mixed
     * @throws Exception
     */
    public function __get($name)
    {
        return $this->get($name);
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return mixed
     * @throws Exception
     */
    public function __set($name, $value)
    {
        return $this->set($name, $value);
    }
}
