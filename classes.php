<?php

/**
 * A vCloud Organization
 */
class Org {
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
 * A vShield Edge
 */
class VseNetwork {
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
/*
    $this->gw     = $_gw;
    $this->mask   = $_mask;
*/

/*
$___org=$_vdc->org;
#   $this->org    = $_org;
    $this->org    = $___org;
#   $this->vdc    = $_vdc;
    $this->vdc    = $_vse->vdc;
    $this->vse    = $_vse;
*/
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
 * A vApp
 */
class Vapp {
  public $parent  = ''; # Usefull for graph
  public $name     = '';
  public $id       = '';
  public $status   = '';
  public $networks = array( /* String */ );
  public $org      = null;
  public $vdc      = null;

  public function __construct($_name, $_id, $_status, $_networks, /* &$_org, */ &$_vdc) {
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
  public $parent   = ''; # Usefull for graph
  public $name     = '';
  public $id       = '';
  public $status   = '';
  public $networks = array( /* String */ );
  public $vapp     = null;
  public $org      = null;
  public $vdc      = null;

  public function __construct($_name, $_id, $_status, $_networks, /* &$_org, */ &$_vapp) {
    $this->parent   = $_vapp;
    $this->name     = $_name;
    $this->id       = $_id;
    $this->status   = $_status;
    $this->networks = $_networks;
    $this->vapp     = $_vapp;
    $this->vdc      = $_vapp->vdc;
    $this->org      = $_vapp->vdc->org;
  }

  public function __toString() {
    $org = $this->org;
    $vdc = $this->vdc;
    $networksStr = "[" . join (", ", $this->networks) . "]";
    return "VM with name='" . $this->name . "', id='" . $this->id . "', status='" . $this->status . "', connected to networks='" . $networksStr . "' from org '" . $org->name . "', vdc '" . $vdc->name . "' and vApp '" . $vapp->name . "'" ;
  }
}

?>
