<?php
namespace App\Service;

use Cake\Datasource\ModelAwareTrait;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\Log\LogTrait;

class AppService {

    use LocatorAwareTrait;
    use LogTrait;
    use ModelAwareTrait;

    public $name = null;
    public $plugin = null;

    public function __construct($name = null)
    {
        if ($name !== null) {
            $this->name = $name;
        }

        if ($this->name === null) {
            list(, $name) = namespaceSplit(get_class($this));
            $this->name = substr($name, 0, -10);
        }
        $this->initialize();
    }

    public function initialize() {

    }

    /**
     * Magic accessor for model autoloading.
     *
     * @param string $name Property name
     * @return bool|object The model instance or false
     */
    public function __get($name)
    {
        return $this->loadModel($name);
    }
}
