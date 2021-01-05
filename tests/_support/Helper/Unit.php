<?php
namespace Helper;

// here you can define custom actions
// all public methods declared in helper class will be available in $I

class Unit extends \Codeception\Module
{
    private $currentEnv = '';

    public function _before(\Codeception\TestInterface $test)
    {
        parent::_before($test);
        $this->currentEnv =  $test->getMetadata()->getCurrent('env');
    }

    public function getCustomParams(): array
    {
        $config = \Codeception\Configuration::suiteSettings('unit', \Codeception\Configuration::config());
        return $config['env'][$this->currentEnv]['params'];
    }
}
