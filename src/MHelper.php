<?php
namespace Modular;
class MHelper {
    // returns true if $needle is a substring of $haystack
    public static function contains($needle, $haystack)
    {
        return strpos($haystack, $needle) !== false;
    }

    public static function getError($type)
    {
        return __DIR__ . "/templates/$type.tpl.php";
    }
}
