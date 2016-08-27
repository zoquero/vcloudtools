<?php

/**
 * A vCloud Organization
 */
class Org {
  public static $classDisplayName  = 'Org';
  public $parent  = ''; # Usefull for graph
  public $name    = '';
  public $id      = ''; # Can't find it, we will use "name"
  public $enabled = false;

  public function __construct($_name, $_enabled) {
    $this->parent = null;
    $this->name   = $_name;
    $this->id     = $_name; # Can't find id on API
    if($_enabled === true || $_enabled === 1) {
      $this->enabled = true;
    }
    else {
      $this->enabled = false;
    }
  }

  public function __toString() {
    $e = ($this->enabled) ? 'enabled' : 'disabled';
    return "Organization '" . $this->name . "' " . $e;
  }

}

/**
 * A vCloud Virtual DataCenter
 */
class Vdc {
  public static $classDisplayName  = 'vDC';
  public $parent  = ''; # Usefull for graph
  public $name = '';
  public $id   = '';
  public $org  = null;

  public function __construct($_name, $_id, &$_org) {
    $this->parent = $_org;
    $this->name   = $_name;
    $this->id     = $_id;
    $this->org    = $_org;
  }

  public function __toString() {
    $org = $this->org;
    return "vDC with name='" . $this->name . "', id='" . $this->id . "' from org '" . $org->name . "'";
  }
}

/**
 * A vShield Edge
 */
class Vse {
  public static $classDisplayName  = 'vSE';
  public $parent  = ''; # Usefull for graph
  public $name   = '';
  public $id     = '';
  public $status = 0;
  public $org    = null;
  public $vdc    = null;

  public function __construct($_name, $_id, $_status, &$_org, &$_vdc) {
    $this->parent = $_vdc;
    $this->name   = $_name;
    $this->id     = $_id;
    $this->status = $_status;
    $this->org    = $_org;
    $this->vdc    = $_vdc;
  }

  public function __toString() {
    $org = $this->org;
    $vdc = $this->vdc;
    return "vShield Edge with name='" . $this->name . "', id='" . $this->id . "', status='" . $this->status . "' from org '" . $org->name . "' and vdc '" . $vdc->name . "'";
  }
}

/**
 * A vShield Edge Network
 */
class VseNetwork {
  public static $classDisplayName  = 'Network';
  public $parent = ''; # Usefull for graph
  public $name   = '';
  public $id     = ''; # Can't find it, we will use "name"
/*
  TO_DO: Must understand what's each "Subnet Participation" on each GatewayInterface
  public $gw     = '';
  public $mask   = '';
*/
  public $org    = null;
  public $vdc    = null;
  public $vse    = null;

  public function __construct($_name, /* $_gw, $_mask, */ /* &$_org, &$_vdc, */ &$_vse) {
    $this->parent = $_vse;
    $this->name   = $_name;
    $this->id     = $_name; # Can't find id on API
    $this->vse    = $_vse;
    $this->vdc    = $_vse->vdc;
    $this->org    = $_vse->vdc->org;

  }

  public function __toString() {
    $org = $this->org;
    $vdc = $this->vdc;
    $vse = $this->vse;
    return "vShield Edge Network with name='" . $this->name . /* "', gateway='" . $this->gw . "', mask='" . $this->mask . */ "' from org '" . $org->name . "', vdc '" . $vdc->name . "' and vShield Edge '" . $vse->name . "'";
  }
}

/**
 * An Isolated Network
 */
class IsolatedNetwork {
  public static $classDisplayName  = 'IsolatedNetwork';
  public $parent = ''; # Usefull for graph
  public $name   = '';
  public $id     = ''; # Can't find it, we will use "name"
  public $org    = null;
  public $vdc    = null;

  public function __construct($_name, /* $_gw, $_mask, */ /* &$_org, &$_vdc, */ &$_vmOrVapp) {
    $this->parent = $_vmOrVapp->parent;
    $this->name   = $_name;
    $this->id     = $_name; # Can't find id on API
    $this->vdc    = $_vmOrVapp->vdc;
    $this->org    = $_vmOrVapp->vdc->org;
  }

  public function __toString() {
    $org = $this->org;
    $vdc = $this->vdc;
    return "Isolated Network with name='" . $this->name . /* "', gateway='" . $this->gw . "', mask='" . $this->mask . */ "' from org '" . $org->name . "' and vdc '" . $vdc->name . "'";
  }
}

/**
 * A vApp
 */
class Vapp {
  public static $classDisplayName  = 'vApp';
  public $parent  = ''; # Usefull for graph
  public $name     = '';
  public $id       = '';
  public $status   = '';
  public $networks = array( /* String */ );
  public $org      = null;
  public $vdc      = null;

  public function __construct($_name, $_id, $_status, $_networks, &$_vdc) {
    $this->parent   = $_vdc;
    $this->name     = $_name;
    $this->id       = $_id;
    $this->status   = $_status;
    $this->networks = $_networks;
    $this->vdc      = $_vdc;
    $this->org      = $_vdc->org;
  }

  public function __toString() {
    $org = $this->org;
    $vdc = $this->vdc;
    $networksStr = "[" . join (", ", $this->networks) . "]";
    return "vApp with name='" . $this->name . "', id='" . $this->id . "', status='" . $this->status . "', connected to networks='" . $networksStr . "' from org '" . $org->name . "' and vdc '" . $vdc->name . "'";
  }
}

/**
 * A VM
 */
class VM {
  public static $classDisplayName  = 'VM';
  public $parent   = ''; # Usefull for graph
  public $name     = '';
  public $id       = '';
  public $status   = '';
  public $networks = array( /* String */ );
  public $storProf = '';
  public $vapp     = null;
  public $org      = null;
  public $vdc      = null;

  public function __construct($_name, $_id, $_status, $_networks, $_storProf, &$_vapp) {
    $this->parent   = $_vapp;
    $this->name     = $_name;
    $this->id       = $_id;
    $this->status   = $_status;
    $this->networks = $_networks;
    $this->storProf = $_storProf;
    $this->vapp     = $_vapp;
    $this->vdc      = $_vapp->vdc;
    $this->org      = $_vapp->vdc->org;
  }

  public function __toString() {
    $org = $this->org;
    $vdc = $this->vdc;
    $networksStr = "[" . join (", ", $this->networks) . "]";
    return "VM with name='" . $this->name . "', id='" . $this->id . "', status='" . $this->status . "', connected to networks='" . $networksStr . "', from org '" . $org->name . "', vdc '" . $vdc->name . "', on Storage Profile '" . $this->storProf . "' and vApp '" . $this->vapp->name . "'" ;
  }
}

/**
 * A Storage Profile
 */
class StorageProfile {
  public static $classDisplayName  = 'StorProf';
  public $parent   = ''; # Usefull for graph
  public $name     = '';
  public $id       = '';
  public $enabled  = ''; /* 1 === true */
  public $limitMB  = '';
  /* Where's the capacity/usage ?? TO_DO */

  public function __construct($_name, $_id, $_enabled, $_limit, $_units, &$_vdc) {
    $limMB = StorageProfile::sizeToMB($_limit, $_units);
    if($limMB == null) {
      trigger_error("Can't convert this limit to MB (limit=$_limit, units=$_units)", E_USER_WARNING);
      return null;
    }

    $this->parent   = $_vdc;
    $this->name     = $_name;
    $this->id       = $_id;
    $this->enabled  = $_enabled;
    $this->limitMB  = $limMB;
    $this->vdc      = $_vdc;
    $this->org      = $_vdc->org;
  }

/**
 * A Storage Profile.
 * <p> As array constants are just available from PHP7,
 *     and on 5.6 constants may only evaluate to scalar values
 *     ... let's do it backward compatible instead.
 *  "MB"=>"1", "GB"=>"1024", "TB"=>"1048576", "PB"=>"1073741824"
 * @param $size
 * @param $units "MB", "GB", "TB", "PB"
 * @return int size in MegaBytes
 */
  public static function sizeToMB($size, $units) {
    if($units      == "MB") {
      return $size * 1;
    }
    else if($units == "GB") {
      return $size * 1024;
    }
    else if($units == "TB") {
      return $size * 1048576;
    }
    else if($units == "PB") {
      return $size * 1073741824;
    }
    return null;
  }

  public function __toString() {
    $org = $this->org;
    $vdc = $this->vdc;
    return "Storage Profile with name='" . $this->name . "', id='" . $this->id . "', enabled='" . $this->enabled . "', with limit='" . $this->limitMB . "' MB, from org '" . $org->name . "' and vdc '" . $vdc->name . "'" ;
  }
}

?>
