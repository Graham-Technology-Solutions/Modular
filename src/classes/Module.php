<?php
namespace Modular;
class Module {

    public $Parent;
    public $Config;
    public $rts;

    public function __construct($p, $c) {
        //error_reporting(0);
        $this->Parent = $p;
        $this->Config = $c;
        $this->loadComponents();
        $this->autoloadRoutes();
        $this->init($p->Modules);
    }

    protected function init($modules) { }


    private function loadComponents () {
        $reflection = new \ReflectionClass($this);

        $cmpDir = dirname($reflection->getFileName()) . "/".$this->Config->MOD_COMPONENT_DIR;

        if(is_dir($cmpDir)){
            $components = array_diff(scandir($cmpDir), array('.', '..'));

            foreach ($components as $cmp) {
                if (file_exists("$cmpDir/$cmp/{$cmp}Controller.php")) {
                    require("$cmpDir/$cmp/{$cmp}Controller.php");
                }
            }
        }
    }

    public function getComponent($name) {
        $name = $name."Controller";

        $cmp = new $name($this->Parent);

        $cmp->__GET($_GET);

    }

    /* Load routes from file if routes file exists */

    private function autoloadRoutes() {
        $reflection = new \ReflectionClass($this);
        if(file_exists( dirname($reflection->getFileName()) . "/autoroute.json")) {
            $routeFile = dirname($reflection->getFileName()) . "/autoroute.json";
            $this->rts = json_decode(file_get_contents($routeFile), true);
        }
    }



}