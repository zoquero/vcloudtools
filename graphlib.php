<?php

/**
 * Library of functions to generate a graphviz diagram representing your vCloud entities.
 *
 * @author Angel Galindo Muñoz (zoquero at gmail dot com)
 * @since 20/08/2016
 * @see http://graphviz.org/
 */

require_once dirname(__FILE__) . '/config.php';
require_once dirname(__FILE__) . '/classes.php';


##
## Configuration
##
$doPrintVappNetLinks    = true;
$doPrintVmNetLinks      = true;
$doPrintVdc2VappLinks   = false;
$doPrintVdc2VmLinks     = false;
$doPrintVmStorProfLinks = true;
$rankDir="BT";                   # LR RL BT TB"
# Colors taken from http://www.color-hex.com/color-palettes/popular.php
define("DEFAULT_TITLE_SIZE", "40");
define("COLOR4ORG",  "#f5f5f5");
define("COLOR4VDC",  "#ffb3ba");
define("COLOR4VSE",  "#ffdfba");
define("COLOR4NET1", "#ffffba"); /* vShield Edge Network */
define("COLOR4NET2", "#e5e5a0"); /* Isolated Network */
define("COLOR4STO",  "#baffc9");
define("COLOR4VAP",  "#bae1ff");
define("COLOR4VM",   "#4dffb8");
# Shapes for nodes
define("SHAPE4ORG",  "house");
define("SHAPE4VDC",  "invhouse");
define("SHAPE4VSE",  "doublecircle");
define("SHAPE4NET1", "parallelogram"); /* vShield Edge Network */
define("SHAPE4NET2", "parallelogram"); /* Isolated Network */
define("SHAPE4STO",  "circle");
define("SHAPE4VAP",  "Msquare");
define("SHAPE4VM",   "box");

/**
 * Prints to output the classname and public methods of an object
 *
 * @param The object
 */
function showObject($o) {
  if(is_null($o)) {
    echo "  It's a NULL object" . PHP_EOL;
    return;
  }
  else if(is_array($o)) {
    echo "  It's not an object, it's an array of " . count($o) . " elements" . PHP_EOL;
    return;
  }
  else if(is_string($o)) {
    echo "  It's not an object, it's a string: " . $o . PHP_EOL;
    return;
  }
  else if(is_numeric($o)) {
    echo "  It's not an object, it's a number:"  . $o . PHP_EOL;
    return;
  }
  else if(!is_object($o)) {
    echo "  It's not an object, nor a number, nor a string, nor an array" . PHP_EOL;
    return;
  }
  else {
    echo "  This is an object of class " . get_class($o) . "" . PHP_EOL;
  }
  echo "  It's public methods:" . PHP_EOL;
  $egMethods=get_class_methods($o);
  foreach ($egMethods as $aMethod) {
    echo "   * $aMethod" . PHP_EOL;
  }

  var_dump(get_object_vars($o));
}


/**
 * Returns a new organization object
 *
 * <p> You can get the SDK organization parameter this way:
 *   <pre>
       $sdkOrg = $service->createSDKObj($orgRef);
 *     $org    = $sdkOrg->getOrg()
 *   </pre>
 * </p>
 *
 * @param $org The organization taken from the SDK, passed by reference
 * @return a new Org object representing that organization, passed by reference
 */
function org2obj(&$org) {
  return new Org($org->get_name(), $org->getIsEnabled());
}

/**
 * Returns a new Vdc object
 *
 * <p> You can get the SDK vDC parameter this way:
 *   <pre>
 *     $sdkVdc = $service->createSDKObj($vdcRef);
 *     $vdc=vdc2obj($sdkVdc->getVdc(),
 *   </pre>
 * </p>
 *
 * @param $vdc The vDC taken from this lib
 * @param $org The organization to which the vDC belongs, from this lib, passed by reference
 * @param $href It's href
 * @return a new Vdc object representing that vDC, passed by reference
 */
function vdc2obj(&$org, &$vdc, $href) {
  return new Vdc($vdc->get_name(), $vdc->get_id(), $org, $href);
}

/**
 * Returns a new vShield Edge object
 *
 * <p> You can get the SDK vShield Edge parameter this way:
 *   <pre>
       $edgeGatewayObj = $service->createSDKObj($edgeGatewayRef->get_href());
 *   </pre>
 * </p>
 *
 * @return a new Vse object representing that vShield Edge
 * @param $org The Organization object from this lib, passed by reference
 * @param $vdc The Virtual DataCenter object from this lib, passed by reference
 * @param $vse The vShield Edge taken from the SDK, passed by reference
 */
function vse2obj(&$org, &$vdc, &$vse) {
  $__vse = $vse->getEdgeGateway();
  return new vSE($__vse->get_name(), $__vse->get_id(),  $__vse->get_status(), $org, $vdc);
}

/**
 * Returns a new vShield Edge Network object
 *
 * <p> You can get the SDK vShield Edge Network Interface parameter this way:
 *   <pre>
       ...
 *   </pre>
 * </p>
 *
 * @return a new VseNetwork object representing that Network
 * @param $org The Organization object from this lib, passed by reference
 * @param $vdc The Virtual DataCenter object from this lib, passed by reference
 * @param $vse The vShield Edge object from this lib, passed by reference
 * @param $vseNetwork The vShield Edge Network Interface taken from the SDK, passed by reference
 */
function vseNetwork2obj(&$org, &$vdc, &$vse, &$gatewayInterface) {
# $gatewayInterface->getName() vs $gatewayInterface->getNetwork()->get_name() ????
  return new VseNetwork($gatewayInterface->getName() /*, $vse->vdc->org, $vse->vdc */, $vse);
}

/**
 * Returns a new vApp object
 *
 * @return a new Vapp object representing that vApp
 * @param $vdc The Virtual DataCenter object from this lib, passed by reference
 * @param $sdkVApp The vApp taken from the SDK, passed by reference
 */
function vApp2obj(&$vdc, &$sdkVApp) {
  $networks=array();
  foreach($sdkVApp->getNetworkSettings()->getNetwork() as $aNetwork) {
    array_push($networks, $aNetwork->get_name());
  }
  return new vApp($sdkVApp->getVapp()->get_name(), $sdkVApp->getVapp()->get_id(), $sdkVApp->getStatus(), $networks, $vdc);
}

/**
 * Returns a new VM object
 *
 * <p>
 * About the "<i>status</i>" of the VMs: Empirically:
 * <ul>
 *   <li> status=3 == Suspended  </li>
 *   <li> status=4 == PoweredOn  </li>
 *   <li> status=8 == PoweredOff </li>
 * </ul>
 *
 * @return a new VM object representing that VM
 * @param $vapp The vApp object from this lib, passed by reference
 * @param $aVM The VM taken from the SDK, passed by reference
 */
function vm2obj(&$vapp, &$sdkVM) {
  $vmNetworks = array();
  foreach ($sdkVM->getSection() as $aSection) {
    if(get_class($aSection) == "VMware_VCloud_API_NetworkConnectionSectionType") {
     foreach($aSection->getNetworkConnection() as $aNetConn) {
       # $aNetConn :: VMware_VCloud_API_NetworkConnectionType
       array_push($vmNetworks, $aNetConn->get_network());
     }
    }
  }
  return new VM($sdkVM->get_name(), $sdkVM->get_id(), $sdkVM->get_status(), $vmNetworks, $sdkVM->getStorageProfile()->get_name(), $vapp);
}

/**
 * Returns a new Storage Profile object
 *
 * @param $vdc The vDC object from this lib, passed by reference
 * @param $sp The storageProfile taken from the SDK, passed by reference
 * @return StorageProfile A new StorageProfile object representing that Storage Profile
 */
function storProf2obj(&$vdc, &$sp) {
#
# $sp->getEnabled()  # 1 enabled
# $sp->getUnits()    # MB
# $sp->getLimit()    # 112640
# $sp->getDefault()  # 1
# $sp->get_name()    # CLOUD-SAS
# $sp->get_id()      # id
#

# return new StorageProfile($sp->get_name(), $sp->get_id(), $sp->getEnabled(), $sp->getLimit(), $sp->getUnits(), $vdc);
  $id = $vdc->org->name . "___" . $sp->get_vdcName() . "___" . $sp->get_name();
  return new StorageProfile($sp->get_name(), $id, $sp->get_isEnabled(), $sp->get_storageLimitMB(), $sp->get_storageUsedMB(), $vdc);
}

function simplifyString($str) {
  $r = str_replace('-', '_', $str);
  $r = str_replace(':', '_', $r);
  return $r;
}

/**
 * Generates a GraphViz diagram and prints it to output file
 *
 * See also:
 * * Docs        : http://www.graphviz.org/Documentation.php
 * * Node, Edge and Graph Attributes:
 *                 http://www.graphviz.org/content/attrs
 * * Node  shapes: http://www.graphviz.org/doc/info/shapes.html
 * * Arrow shapes: http://www.graphviz.org/doc/info/arrows.html
 *
 * @param $orgs Array of organizations
 * @param $vdcs Array of Virtual Datacenters
 * @param $vses Array of vShield Edges
 * @param $vseNets Array of Networks
 * @param $vapps Array of vApps
 * @param $vms Array of VMs
 * @param $storProf Array of Storage Profiles
 * @param $title Title for graph
 */
function graph($orgs, $vdcs, $vses, $vseNets, $vapps, $vms, $storProfs, $title) {
  global $oFile;
  global $rankDir;

  $isolatedNets = getIsolatedNets($vseNets, array_merge($vapps, $vms));

  ## Open output file
  ($fp = fopen($oFile, 'w')) || die ("Can't open output file $oFile");

  ## Headers
  fwrite($fp, "#"                                               . PHP_EOL);
  fwrite($fp, "# Graph genated on " . date("Y/m/d h:i:s a")      . PHP_EOL);
  fwrite($fp, "# by vcloudtools:"                               . PHP_EOL);
  fwrite($fp, "# https://github.com/zoquero/vcloudtools"        . PHP_EOL);
  fwrite($fp, "#"                                               . PHP_EOL);
  fwrite($fp, ""                                                . PHP_EOL);
  fwrite($fp, "digraph vCloud {"                                . PHP_EOL);
  fwrite($fp, "  rankdir=$rankDir;    # LR RL BT TB"            . PHP_EOL);
  fwrite($fp, "  splines=false; # avoid curve lines"            . PHP_EOL);
  fwrite($fp, "  edge [arrowhead=none,arrowtail=none];"         . PHP_EOL);
  fwrite($fp, "  graph [label=\"$title\", fontsize=\"" . DEFAULT_TITLE_SIZE . "\"];" . PHP_EOL);
  fwrite($fp, "  {"                                             . PHP_EOL);
  fwrite($fp, "    " . Org::$classDisplayName            . " -> " . Vdc::$classDisplayName                               . PHP_EOL);
  fwrite($fp, "    " . Vdc::$classDisplayName            . " -> " . Vse::$classDisplayName                               . PHP_EOL);
  fwrite($fp, "    " . Vse::$classDisplayName            . " -> " . VseNetwork::$classDisplayName                        . PHP_EOL);
  fwrite($fp, "    " . VseNetwork::$classDisplayName     . " -> " . StorageProfile::$classDisplayName . " [style=invis]" . PHP_EOL);
  fwrite($fp, "    " . StorageProfile::$classDisplayName . " -> " . Vapp::$classDisplayName           . " [style=invis]" . PHP_EOL);
  fwrite($fp, "    " . Vapp::$classDisplayName           . " -> " . VM::$classDisplayName                                . PHP_EOL);
  fwrite($fp, ""                                               . PHP_EOL);
  fwrite($fp, getNodeLegend(Org::$classDisplayName)            . PHP_EOL);
  fwrite($fp, getNodeLegend(Vdc::$classDisplayName)            . PHP_EOL);
  fwrite($fp, getNodeLegend(Vse::$classDisplayName)            . PHP_EOL);
  fwrite($fp, getNodeLegend(VseNetwork::$classDisplayName)     . PHP_EOL);
  fwrite($fp, getNodeLegend(StorageProfile::$classDisplayName) . PHP_EOL);
  fwrite($fp, getNodeLegend(Vapp::$classDisplayName)           . PHP_EOL);
  fwrite($fp, getNodeLegend(VM::$classDisplayName)             . PHP_EOL);
  fwrite($fp, "  }"                                             . PHP_EOL);

  ###################
  ## Node definitions
  ###################

  fwrite($fp, "  # Orgs"                                     . PHP_EOL);
  fwrite($fp, "  {"                                          . PHP_EOL);
  fwrite($fp, getNodeGroupPreamble(Org::$classDisplayName) . PHP_EOL);
  foreach($orgs as $aNode) {
    printNode($fp, $aNode);
  }
  fwrite($fp, "  }"                                          . PHP_EOL);

  fwrite($fp, "  # vDCs"                                     . PHP_EOL);
  fwrite($fp, "  {"                                          . PHP_EOL);
  fwrite($fp, getNodeGroupPreamble(Vdc::$classDisplayName) . PHP_EOL);
  foreach($vdcs as $aNode) {
    printNode($fp, $aNode);
  }
  fwrite($fp, "  }"                                          . PHP_EOL);

  fwrite($fp, "  # vSEs"                                     . PHP_EOL);
  fwrite($fp, "  {"                                          . PHP_EOL);
  fwrite($fp, getNodeGroupPreamble(Vse::$classDisplayName) . PHP_EOL);
  foreach($vses as $aNode) {
    printNode($fp, $aNode);
  }
  fwrite($fp, "  }"                                          . PHP_EOL);

  fwrite($fp, "  # vSE Networks"                             . PHP_EOL);
  fwrite($fp, "  {"                                          . PHP_EOL);
  fwrite($fp, getNodeGroupPreamble(VseNetwork::$classDisplayName) . PHP_EOL);
  foreach($vseNets as $aNode) {
    printNode($fp, $aNode);
  }
  fwrite($fp, "  }"                                          . PHP_EOL);

  # $isolatedNets
  fwrite($fp, "  # Isolated Networks"                        . PHP_EOL);

  if(count($isolatedNets > 0)) {
    fwrite($fp, "  {"                                          . PHP_EOL);
    fwrite($fp, getNodeGroupPreamble(IsolatedNetwork::$classDisplayName) . PHP_EOL);
    foreach($isolatedNets as $aNode) {
      printNode($fp, $aNode);
    }
    fwrite($fp, "  }"                                          . PHP_EOL);
  }

  fwrite($fp, "  # Storage Profiles"                         . PHP_EOL);
  fwrite($fp, "  {"                                          . PHP_EOL);
  fwrite($fp, getNodeGroupPreamble(StorageProfile::$classDisplayName) . PHP_EOL);
  foreach($storProfs as $aNode) {
    printNode($fp, $aNode);
  }
  fwrite($fp, "  }"                                          . PHP_EOL);

  fwrite($fp, "  # vApps"                                    . PHP_EOL);
  fwrite($fp, "  {"                                          . PHP_EOL);
  fwrite($fp, getNodeGroupPreamble(Vapp::$classDisplayName) . PHP_EOL);
  foreach($vapps as $aNode) {
    printNode($fp, $aNode);
  }
  fwrite($fp, "  }"                                          . PHP_EOL);

  fwrite($fp, "  # VMs"                                      . PHP_EOL);
  fwrite($fp, "  {"                                          . PHP_EOL);
  fwrite($fp, getNodeGroupPreamble(VM::$classDisplayName) . PHP_EOL);
  foreach($vms as $aNode) {
    printNode($fp, $aNode);
  }
  fwrite($fp, "  }"                                          . PHP_EOL);

  ###################
  ## Edge definitions
  ###################

  fwrite($fp, "  #"                    . PHP_EOL);
  fwrite($fp, "  # Edges"              . PHP_EOL);
  fwrite($fp, "  #"                    . PHP_EOL);
  fwrite($fp, ""                       . PHP_EOL);

  fwrite($fp, "  # Org edges:"         . PHP_EOL);
  foreach($orgs as $aNode) {
    printLinks($fp, $aNode, $storProfs);
  }

  fwrite($fp, "  # vDC edges:"         . PHP_EOL);
  foreach($vdcs as $aNode) {
    printLinks($fp, $aNode, $storProfs);
  }

  fwrite($fp, "  # vSE edges:"         . PHP_EOL);
  foreach($vses as $aNode) {
    printLinks($fp, $aNode, $storProfs);
  }

  fwrite($fp, "  # vSE Network edges:" . PHP_EOL);
  foreach($vseNets as $aNode) {
    printLinks($fp, $aNode, $storProfs);
  }

  fwrite($fp, "  # Isolated Network edges:" . PHP_EOL);
  foreach($isolatedNets as $aNode) {
    printLinks($fp, $aNode, $storProfs);
  }

  fwrite($fp, "  # Storage Profiles:"  . PHP_EOL);
  foreach($storProfs as $aNode) {
    printLinks($fp, $aNode, $storProfs);
  }

  fwrite($fp, "  # vApp edges:"        . PHP_EOL);
  foreach($vapps as $aNode) {
    printLinks($fp, $aNode, $storProfs);
  }

  fwrite($fp, "  # VM edges:"          . PHP_EOL);
  foreach($vms as $aNode) {
    printLinks($fp, $aNode, $storProfs);
  }

  fwrite($fp, "}" . PHP_EOL);
  fclose($fp) || die ("Can't close output file");

}

function getNodeLegend($nodeType) {
  return "    " . $nodeType . " [shape=". getNodeShape($nodeType) . ",style=filled,fillcolor=\"" . getNodeColor($nodeType) . "\"];";
}

function getNodeGroupPreamble($nodeType) {
  return "    node [shape=". getNodeShape($nodeType) . ",style=filled,fillcolor=\"". getNodeColor($nodeType) . "\"];";
}

function getNodeShape($nodeType) {
  if($nodeType      == Org::$classDisplayName) {
    return SHAPE4ORG;
  }
  else if($nodeType == Vdc::$classDisplayName) {
    return SHAPE4VDC;
  }
  else if($nodeType == Vse::$classDisplayName) {
    return SHAPE4VSE;
  }
  else if($nodeType == VseNetwork::$classDisplayName) {
    return SHAPE4NET1;
  }
  else if($nodeType == IsolatedNetwork::$classDisplayName) {
    return SHAPE4NET2;
  }
  else if($nodeType == Vapp::$classDisplayName) {
    return SHAPE4VAP;
  }
  else if($nodeType == VM::$classDisplayName) {
    return SHAPE4VM;
  }
  else if($nodeType == StorageProfile::$classDisplayName) {
    return SHAPE4STO;
  }
  else {
    die("Missing shape for " . $nodeType);
  }
}

/**
 * Generates Edges pointing to an object when generating a GraphViz diagram
 *
 *
 * @param $fp File Handler to write to
 * @param $obj The object that has a non-null "parent" field which is an object that has an "id" field.
 * @param $storProfs To be able to look for the id of the storage profile it needs to access to the array of StorageProfile $storProfs, ... quick fix 
 */
function printLinks($fp, $obj, $storProfs) {
  global $doPrintVappNetLinks;
  global $doPrintVmNetLinks;
  global $doPrintVdc2VmLinks;
  global $doPrintVdc2VappLinks;
  global $doPrintVmStorProfLinks;

  if($obj == null || ! isset($obj->parent) || ! is_object($obj->parent) || ! isset($obj->parent->id) ) {
    return;
  }
  $id=simplifyString($obj->id);
  $pId=simplifyString($obj->parent->id);

  $shouldPrintLink=true;
  if(get_class($obj) == "Vapp" && ! $doPrintVdc2VappLinks) {
    $shouldPrintLink=false;
  }

  if($shouldPrintLink) {
    $attrs='';
    if(get_class($obj) == "IsolatedNetwork") {
      $attrs=' [style="dotted"]';
    }
    fwrite($fp, "    \"$pId\":n->\"$id\":s$attrs;" . PHP_EOL);

    if(get_class($obj) == "VM" && $doPrintVdc2VmLinks) {
      $vdcId=simplifyString($obj->vdc->id);
      fwrite($fp, "    \"$vdcId\":n->\"$id\":s$attrs;" . PHP_EOL);
    }
  }

  ## Network Links for VMs and vApps
  if((get_class($obj) == "Vapp" && $doPrintVappNetLinks) || (get_class($obj) === "VM" && $doPrintVmNetLinks)) {
    foreach($obj->networks as $aNetwork) {
      ## On networks id == name, so this string should be network's id.
      fwrite($fp, "    \"" . $aNetwork . "\":n->\"$id\":s;" . PHP_EOL);
    }
  }

  ## Storage Profile Links for VMs
  if(get_class($obj) == "VM" && $doPrintVmStorProfLinks) {
    fwrite($fp, "    \"" . simplifyString(getStorProfIdFromName($obj->storProf, $storProfs)) . "\":n->\"$id\":s;" . PHP_EOL);
  }
}

function getStorProfIdFromName($spName, &$storProfArray) {
  foreach($storProfArray as $aStorProf) {
    if($aStorProf->name == $spName) {
      return $aStorProf->id;
    }
  }
  return null;
}

/**
 * Generates node entry of an object when generating a GraphViz diagram
 *
 * @param $fp File Handler to write to
 * @param $obj The String that equals to the static field "classDisplayName" of object's class.
 */
function printNode($fp, $obj) {
    $id=simplifyString($obj->id);
    fwrite($fp, "    \"$id\" [label=\"" . $obj->name . "\"]" . PHP_EOL);
    fwrite($fp, "    rank = same; " . getNodeAlign($obj::$classDisplayName) . "; \"$id\";" . PHP_EOL);
}

/**
 * Returns the color to print an object when generating a GraphViz diagram
 *
 * @param $obj The String that equals to the static field "classDisplayName" of object's class.
 */
function getNodeColor($nodeType) {
  if($nodeType      == Org::$classDisplayName) {
    return COLOR4ORG;
  }
  else if($nodeType == Vdc::$classDisplayName) {
    return COLOR4VDC;
  }
  else if($nodeType == Vse::$classDisplayName) {
    return COLOR4VSE;
  }
  else if($nodeType == VseNetwork::$classDisplayName) {
    return COLOR4NET1;
  }
  else if($nodeType == IsolatedNetwork::$classDisplayName) {
    return COLOR4NET2;
  }
  else if($nodeType == Vapp::$classDisplayName) {
    return COLOR4VAP;
  }
  else if($nodeType == VM::$classDisplayName) {
    return COLOR4VM;
  }
  else if($nodeType == StorageProfile::$classDisplayName) {
    return COLOR4STO;
  }
  else {
    die("Missing color for " . $nodeType);
  }
}

/**
 * Returns the name of the legend object to which align this object when generating a GraphViz diagram
 *
 * @param $obj The String that equals to the static field "classDisplayName" of object's class.
 */
function getNodeAlign($nodeType) {
  if($nodeType      == Org::$classDisplayName) {
    return Org::$classDisplayName;
  }
  else if($nodeType == Vdc::$classDisplayName) {
    return Vdc::$classDisplayName;
  }
  else if($nodeType == Vse::$classDisplayName) {
    return Vse::$classDisplayName;
  }
  else if($nodeType == VseNetwork::$classDisplayName) {
    return VseNetwork::$classDisplayName;
  }
  else if($nodeType == IsolatedNetwork::$classDisplayName) {
    ## VseNetwork and IsolatedNetwork must be aligned together
    return VseNetwork::$classDisplayName;
  }
  else if($nodeType == Vapp::$classDisplayName) {
    return Vapp::$classDisplayName;
  }
  else if($nodeType == VM::$classDisplayName) {
    return VM::$classDisplayName;
  }
  else if($nodeType == StorageProfile::$classDisplayName) {
    return StorageProfile::$classDisplayName;
  }
  else {
    die("Missing align for " . $nodeType);
  }
}

/**
 * Returns the network object representing isolated networks
 *
 * @param $vseNets Array of VseNetwork objects from this lib
 * @param $vms Array of VM objects from this lib
 * @return $array of network objects
 */
function getIsolatedNets($vseNets, $vms) {
  $ret = array();

  foreach($vms as $aObj) {
    foreach($aObj->networks as $aNetwork) {
      if(! netNameIsInVseNetworkArrays($aNetwork, $ret, $vseNets)) {
        $aIsolatedNetwork = new IsolatedNetwork($aNetwork, $aObj);
        array_push($ret, $aIsolatedNetwork );
      }
    }
  }
  return $ret;
}

/**
 * Tells if a network name matches with one of the VseNetwork names of an array or one of the IsolatedNetwork names of an array.
 *
 * @param $networkName Name of a network
 * @param $isolatedNets Array of IsolatedNetwork objects from this lib
 * @param $vseNets Array of VseNetwork objects from this lib
 * @return boolean True if network name matches with a VseNetwork or a IsolatedNetwork
 */
function netNameIsInVseNetworkArrays($networkName, $isolatedNets /* IsolatedNetwork */ , $vseNets /* VseNetwork */) {
  foreach($isolatedNets as $aNet) {
    if($networkName == $aNet->name) {
      return true;
    }
  }
  foreach($vseNets as $aNet) {
    if($networkName == $aNet->name) {
      return true;
    }
  }
  return false;
}

?>
