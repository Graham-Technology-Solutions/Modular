<?php
namespace Modular;

class Component {

    public $template = NULL;
    public $routeVars;
    public \Modular\Modular $ModularPHP;
    public \Modular\Modular $Modular;
    public $params;
    public $appState;
    public $passedData;
    public \coreauth_USER $currentUser;

    public function __construct($p)
    {
        /* Use of ModularPHP is deprecated, use Modular in templates now */
        $this->ModularPHP = $p;

        $this->Modular = $p;

        $ca_inst = $p->Modules['_CoreAuth'];

        if($ca_inst->checkAuth()) {
            $this->currentUser = $ca_inst->getCurrentUser();
        } else {
            $this->currentUser = new \coreauth_USER();
        }

        foreach ($p->Modules as $m=>$ref) {
            $this->{$m} = $ref;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->params = $_POST;
        } else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $this->params = $_GET;
        }
    }

    public function __GET($params) { }

    public function __POST($params) { }

    private function showComponent($cmp) {

    }


    public function __render() {
        if($this->template != null) {
            foreach ($this->getFields() as $val) {
                ${$val} = $this->{$val};
            }

            $reflection = new \ReflectionClass($this);

            if(MHelper::contains(".twig", $this->template)) {

                $loader = new \Twig\Loader\FilesystemLoader(dirname($reflection->getFileName()) );

                $twig = new \Twig\Environment($loader);

                $function = new \Twig\TwigFunction('component', function ($name, $data = array()) {
                    $this->ModularPHP->loadComponent(array("component" => $name), false, $data);
                });

                $twig->addFunction($function);

                //getComponent($name)

                $twigArray = array();

                foreach ($this as $k => $v) {
                    $twigArray[$k] = $v;
                }

                echo $twig->render($this->template, $twigArray);

            } else {
                include(dirname($reflection->getFileName()) . "\\" . $this->template);
            }
        }
    }

    public function getFields()
    {
        $fields = array();

        foreach ($this as $u => $v) {
            array_push($fields, $u);
        }

        return $fields;
    }

    public static function Load($mp, $req = "GET") {

        $class = static::class;
        $obj = new $class($mp);

        if($req == "GET") {
            $obj->__GET($_GET);
        } else if($req == "POST") {
            $obj->__POST($_POST);
        } else {
            return false;
        }
    }

    public function setPage($pageName) {
        setcookie("MPHP_RENG_CURRPAGE", $pageName, time() + 36000, "/");
        $this->pageName = $pageName;
    }

    public function getPage() {
       return "";
    }

    public function reload() {
        header('Location: .');
    }

    // Create the function, so you can use it
    function isMobile() {
        return preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $_SERVER["HTTP_USER_AGENT"]);
    }
}