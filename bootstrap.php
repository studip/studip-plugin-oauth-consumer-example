<?
if (!function_exists('array_map_recursive')) {
    function array_map_recursive($func, $arr){
      $a = array(); 
      if(is_array($arr))
        foreach($arr as $k => $v)
          $a[$k] = is_array($v) ? array_map_recursive($func, $v) : $func($v);
      return $a;
    }
}

require_once 'vendor/trails/trails.php';
require_once 'app/controllers/studip_controller.php';
require_once 'classes/buttons/Interactable.class.php';
require_once 'classes/buttons/Button.class.php';
require_once 'classes/buttons/LinkButton.class.php';

// Populate $_DELETE, $_HEAD, $_OPTIONS and $_PUT
foreach (words('DELETE HEAD OPTIONS PUT') as $method) {
    $var = '_' . $method;
    $$var = array();  
    if ($_SERVER['REQUEST_METHOD'] == $method) {  
        parse_str(file_get_contents('php://input'), $$var);  
    }
}
