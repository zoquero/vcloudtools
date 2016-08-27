<?php
/**
 * Generates a graphviz diagram representing your vCloud entities.
 * <p>
 * Generates a graphviz diagram representing your vCloud entities
 * (orgs, vDCs, vShield Edges, networks, Storage Profiles, vApps and VMs).
 * <p>
 * Then you can render the diagram like this: <br/>
 * <ul>
 *   <li>PNG: <PRE>dot -Tpng ./mycloud.dot -o ./myvcloud.png</PRE>
 *   <li>SVG: <PRE>dot -Tpng ./mycloud.dot -o ./myvcloud.svg</PRE>
 * </ul>
 * <p>
 * Requires:<ul>
 *              <li> PHP version 5+
 *              <li> vCloud SDK for PHP for vCloud Suite 5.5
 *             ( https://developercenter.vmware.com/web/sdk/5.5.0/vcloud-php )
 * </ul>
 * <p>
 * Tested on Ubuntu 15.04 64b with PHP 5.6.4
 * <p>
 *   TO_DO:
 *   <ul>
 *     <li> To be able to graph just a subset of your infraestructure
 *            (just one organization, vDC, vShield Edge, vApp or VM
 *             and all of it's objects downwards).
 *     <li> Set different color to each type of entity
 *   </ul>
 *
 * @author Angel Galindo Muñoz (zoquero at gmail dot com)
 * @version 1.1
 * @since 20/08/2016
 * @see http://graphviz.org/
 */

require_once dirname(__FILE__) . '/config.php';
require_once dirname(__FILE__) . '/classes.php';


// Get parameters from command line
$shorts  = "";
$shorts .= "s:";
$shorts .= "u:";
$shorts .= "p:";
$shorts .= "v:";
$shorts .= "e:";
$shorts .= "o:";

$longs  = array(
    "server:",    //-s|--server    [required]
    "user:",      //-u|--user      [required]
    "pswd:",      //-p|--pswd      [required]
    "sdkver:",    //-v|--sdkver    [required]
    "certpath:",  //-e|--certpath  [optional] local certificate path
    "output:",    //-o|--output       [required]
);

$opts = getopt($shorts, $longs);

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


// Initialize parameters
# $httpConfig = array('ssl_verify_peer'=>false, 'ssl_verify_host'=>false); ## From config.php
$certPath = null;

// loop through command arguments
foreach (array_keys($opts) as $opt) switch ($opt) {
    case "s":
        $server = $opts['s'];
        break;
    case "server":
        $server = $opts['server'];
        break;

    case "u":
        $user = $opts['u'];
        break;
    case "user":
        $user = $opts['user'];
        break;

    case "p":
        $pswd = $opts['p'];
        break;
    case "pswd":
        $pswd = $opts['pswd'];
        break;

    case "v":
        $sdkversion = $opts['v'];
        break;
    case "sdkver":
        $sdkversion = $opts['sdkver'];
        break;

    case "e":
        $certPath = $opts['e'];
        break;
    case "certpath":
        $certPath = $opts['certpath'];
        break;

    case "o":
        $oFile = $opts['o'];
        break;
    case "output":
        $oFile = $opts['output'];
        break;
}

// parameters validation
if (!isset($server) || !isset($user) || !isset($pswd) || !isset($sdkversion) || !isset($oFile)) {
    echo "Error: missing required parameters" . PHP_EOL;
    usage();
    exit(1);
}

if (file_exists($oFile)) {
  echo "Error: $oFile already exists" . PHP_EOL;
  usage();
  exit(1);
}

$flag = true;
if (isset($certPath)) {
    $cert = file_get_contents($certPath);
    $data = openssl_x509_parse($cert);
    $encodeddata1 = base64_encode(serialize($data));

    // Split a server url by forward back slash
    $url = explode('/', $server);
    $url = end($url);

    // Creates and returns a stream context with below options supplied in options preset
    $context = stream_context_create();
    stream_context_set_option($context, 'ssl', 'capture_peer_cert', true);
    stream_context_set_option($context, 'ssl', 'verify_host', true);

    $encodeddata2 = null;
    if ($socket = stream_socket_client("ssl://$url:443/", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context)) {
        if ($options = stream_context_get_options($context)) {
            if (isset($options['ssl']) && isset($options['ssl']['peer_certificate'])) {
                $x509_resource = $options['ssl']['peer_certificate'];
                $cert_arr = openssl_x509_parse($x509_resource);
                $encodeddata2 = base64_encode(serialize($cert_arr));
            }
        }
    }

    // compare two certificate as string
    if (strcmp($encodeddata1, $encodeddata2)==0) {
        echo PHP_EOL . "Validation of certificates is successful." . PHP_EOL;
        $flag=true;
    }
    else {
        echo PHP_EOL . "Certification Failed." . PHP_EOL;
        $flag=false;
    }
}

if ($flag==true) {
  if (!isset($certPath)) {
      echo "Ignoring the Certificate Validation --Fake certificate - DO NOT DO THIS IN PRODUCTION." . PHP_EOL;
  }
  // vCloud login
  $service = VMware_VCloud_SDK_Service::getService();
  $service->login($server, array('username'=>$user, 'password'=>$pswd), $httpConfig, $sdkversion);

  # Initialization of arrays of objects:
  $orgsArray      = array();
  $vdcsArray      = array();
  $vsesArray      = array();
  $vseNetsArray   = array();
  $vappsArray     = array();
  $vmsArray       = array();
  $storProfsArray = array();

  // create sdk admin object
  $sdkAdminObj = $service->createSDKAdminObj();

  // create an SDK Org object
  $orgRefs = $service->getOrgRefs();
  if (0 == count($orgRefs)) {
      exit("No organizations found" . PHP_EOL);
  }
  # echo "Found " . count($orgRefs) . " organizations:" . PHP_EOL;
  foreach ($orgRefs as $orgRef) {
    ## Iterate through organizations ##
    $sdkOrg = $service->createSDKObj($orgRef);
    echo "* org: " . $sdkOrg->getOrg()->get_name() . "" . PHP_EOL;

    // create admin org object
    $adminOrgRefs = $sdkAdminObj->getAdminOrgRefs($sdkOrg->getOrg()->get_name());
    if(empty($adminOrgRefs)) {
        exit("No admin org with name " . $sdkOrg->getOrg()->get_name() . " is found.");
    }
    $adminOrgRef = $adminOrgRefs[0];
    $adminOrgObj = $service->createSDKObj($adminOrgRef->get_href());

    $org=org2obj($sdkOrg->getOrg());
    array_push($orgsArray, $org);

    $vdcRefs = $sdkOrg->getVdcRefs();
    if (0 == count($vdcRefs)) {
        exit("No vDCs found" . PHP_EOL);
    }

    # echo "Found " . count($vdcRefs) . " vDCs:" . PHP_EOL;
    foreach ($vdcRefs as $vdcRef) {
      ## Iterate through vDCs ##
      $sdkVdc = $service->createSDKObj($vdcRef);
      echo "-* vDC: " . $sdkVdc->getVdc()->get_name() . "" . PHP_EOL;

      $vdc=vdc2obj($org, $sdkVdc->getVdc());
      array_push($vdcsArray, $vdc);

      # Storage Profiles
      foreach($sdkVdc->getVdcStorageProfiles() as $storageProfile) {
        $storProf=storProf2obj($vdc, $storageProfile);
        array_push($storProfsArray, $storProf);
        echo "-* storProf: " . $storProf->name . "" . PHP_EOL;
      }

      # vShield Edge Gateways

      // create admin vdc object
      $adminVdcRefs = $adminOrgObj->getAdminVdcRefs($sdkVdc->getVdc()->get_name());
      if(empty($adminVdcRefs)) {
          exit("No admin vdc with name " . $sdkVdc->getVdc()->get_name() . " is found.");
      }
      $adminVdcRef=$adminVdcRefs[0];
      $adminVdcObj=$service->createSDKObj($adminVdcRef->get_href());

      $edgeGatewayRefs = $adminVdcObj->getEdgeGatewayRefs();
      if (0 == count($edgeGatewayRefs)) {
        echo "No vShield Edges found in this vDC" . PHP_EOL;
        continue;
      }
      foreach ($edgeGatewayRefs as $edgeGatewayRef) {
        echo "--* vSE: " . $edgeGatewayRef->get_name() . "" . PHP_EOL;
        $edgeGatewayObj = $service->createSDKObj($edgeGatewayRef->get_href());
        $vse=vse2obj($org, $vdc, $edgeGatewayObj);
        array_push($vsesArray, $vse);

        $__vse   = $edgeGatewayObj->getEdgeGateway();
        $vseConf = $__vse->getConfiguration();
        foreach($vseConf->getGatewayInterfaces()->getGatewayInterface() as $iface) {
          $vseNet=vseNetwork2obj($org, $vdc, $vse, $iface);
          array_push($vseNetsArray, $vseNet);
          echo "---* vSE network: " . $vseNet->name . "" . PHP_EOL;
        }
      }

      ## vApps
      $aREArray=array();
      foreach ($sdkVdc->getVdc()->getResourceEntities()->getResourceEntity() as $aRE) {
        $aType=preg_replace('/^application\/vnd\.vmware\.vcloud\./', '', $aRE->get_type());
        $aType=preg_replace('/\+xml$/', '', $aType);
        if($aType === "vApp") {
          $aSdkVApp = $service->createSDKObj($aRE->get_href());
          $vApp=vApp2obj($vdc, $aSdkVApp);
          array_push($vappsArray, $vApp);
          echo "---* vApp : " . $vApp->name . "" . PHP_EOL;

          foreach ($aSdkVApp->getVapp()->getChildren()->getVM() as $aVM) {
            $vm=vm2obj($vApp, $aVM);
            array_push($vmsArray, $vm);
            echo "---* VM : " . $vm->name . "" . PHP_EOL;
          }
        }
      }
    }
  }

  graph($orgsArray, $vdcsArray, $vsesArray, $vseNetsArray, $vappsArray, $vmsArray, $storProfsArray);
  echo PHP_EOL;
  echo "Graph '$oFile' Generated successfully." . PHP_EOL;
  echo "Now you can render it with graphviz ( http://www.graphviz.org/ ) this way:" . PHP_EOL;
  echo "as a PNG:" . PHP_EOL;
  echo "# dot -Tpng '$oFile' -o ./vcloud.png" . PHP_EOL;
  echo "as a SVG:" . PHP_EOL;
  echo "# dot -Tsvg '$oFile' -o ./vcloud.svg" . PHP_EOL;
}
else {
    echo PHP_EOL . "Login Failed due to certification mismatch.";
    exit(1);
}
exit(0);



function usage() {
    echo "Usage:" . PHP_EOL;
    echo "  [Description]" . PHP_EOL;
    echo "     Generates a GraphViz diagram representing your vCloud Infraestructure." . PHP_EOL;
    echo PHP_EOL;
    echo "  [Usage]" . PHP_EOL;
    echo "     # php graphvcloud.php --server <server> --user <username> --pswd <password> --sdkver <sdkversion> --output <file>" . PHP_EOL;
    echo "     # php graphvcloud.php -s <server> -u <username> -p <password> -v <sdkversion> -o <file>" . PHP_EOL;
    echo PHP_EOL;
    echo "     -s|--server <IP|hostname>        [req] IP or hostname of the vCloud Director." . PHP_EOL;
    echo "     -u|--user <username>             [req] User name in the form user@organization" . PHP_EOL;
    echo "                                           for the vCloud Director." . PHP_EOL;
    echo "     -p|--pswd <password>             [req] Password for user." . PHP_EOL;
    echo "     -v|--sdkver <sdkversion>         [req] SDK Version e.g. 1.5, 5.1 and 5.5." . PHP_EOL;
    echo "     -o|--output <file>               [req] Folder where CSVs will be craeted." . PHP_EOL;
    echo PHP_EOL;
    echo "  [Options]" . PHP_EOL;
    echo "     -e|--certpath <certificatepath>  [opt] Local certificate's full path." . PHP_EOL;
    echo PHP_EOL;
    echo "  You can set the security parameters like server, user and pswd in 'config.php' file" . PHP_EOL;
    echo PHP_EOL;
    echo "  [Examples]" . PHP_EOL;
    echo "     # php graphvcloud.php --server 127.0.0.1 --user admin@MyOrg --pswd mypassword --sdkver 5.5 --output /tmp/vc.dot" . PHP_EOL;

}

/**
 * Prints to output the classname and public methods of an object
 *
 * @param The object
 */
function showObject($o) {
  if(is_null($o)) {
    echo "  Is a NULL object" . PHP_EOL;
    return;
  }
  else if(is_array($o)) {
    echo "  It's not an object, it's an array" . PHP_EOL;
    return;
  }
  else {
    echo "  This is an object of class " . get_class($o) . "" . PHP_EOL;
  }
  echo "  Public methods:" . PHP_EOL;
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
 * @return a new Vdc object representing that vDC, passed by reference
 */
function vdc2obj(&$org, &$vdc) {
  return new Vdc($vdc->get_name(), $vdc->get_id() , $org);
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
#  .... where's the current disk usage?? ( TO_DO )
#

  return new StorageProfile($sp->get_name(), $sp->get_id(), $sp->getEnabled(), $sp->getLimit(), $sp->getUnits(), $vdc);
}

function simplifyString($str) {
  $r = str_replace('-', '_', $str);
  $r = str_replace(':', '_', $r);
  return $r;
}

/**
 * Generates a GraphViz diagram
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
 */
function graph($orgs, $vdcs, $vses, $vseNets, $vapps, $vms, $storProfs) {
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

  fwrite($fp, "  {"                                             . PHP_EOL);
  fwrite($fp, "    node [style=filled,fillcolor=\"#C0C0C0\"];" . PHP_EOL);
/*
  fwrite($fp, "    org -> vDC"                                  . PHP_EOL);
  fwrite($fp, "    vDC -> vSE"                                  . PHP_EOL);
  fwrite($fp, "    vSE -> network"                              . PHP_EOL);
  fwrite($fp, "    network -> StorProf [style=invis]"           . PHP_EOL);
  fwrite($fp, "    StorProf -> vApp [style=invis]"              . PHP_EOL);
  fwrite($fp, "    vApp -> VM"                                  . PHP_EOL);
*/

  fwrite($fp, "    " . Org::$classDisplayName            . " -> " . Vdc::$classDisplayName                               . PHP_EOL);
  fwrite($fp, "    " . Vdc::$classDisplayName            . " -> " . Vse::$classDisplayName                               . PHP_EOL);
  fwrite($fp, "    " . Vse::$classDisplayName            . " -> " . VseNetwork::$classDisplayName                        . PHP_EOL);
  fwrite($fp, "    " . VseNetwork::$classDisplayName     . " -> " . StorageProfile::$classDisplayName . " [style=invis]" . PHP_EOL);
  fwrite($fp, "    " . StorageProfile::$classDisplayName . " -> " . Vapp::$classDisplayName           . " [style=invis]" . PHP_EOL);
  fwrite($fp, "    " . Vapp::$classDisplayName           . " -> " . VM::$classDisplayName                                . PHP_EOL);


  fwrite($fp, ""                                                . PHP_EOL);


/*
TO_DO: Convert it to : ...
  fwrite($fp, getNodeLegend("Org") . PHP_EOL);
  fwrite($fp, getNodeLegend("Org") . PHP_EOL);
  fwrite($fp, getNodeLegend("Vdc") . PHP_EOL);
  fwrite($fp, getNodeLegend("Vse") . PHP_EOL);
  fwrite($fp, getNodeLegend("VseNetwork") . PHP_EOL);
  fwrite($fp, getNodeLegend("StorageProfile") . PHP_EOL);
  fwrite($fp, getNodeLegend("Vapp") . PHP_EOL);
  fwrite($fp, getNodeLegend("VM") . PHP_EOL);
*/

  fwrite($fp, "    Org      [shape=". getNodeShape("Org")            . ",style=filled,fillcolor=\"" . getNodeColor("Org") . "\"];" . PHP_EOL);
  fwrite($fp, "    vDC      [shape=". getNodeShape("Vdc")            . ",style=filled,fillcolor=\"" . getNodeColor("Vdc")  . "\"];" . PHP_EOL);
  fwrite($fp, "    vSE      [shape=". getNodeShape("Vse")            . ",style=filled,fillcolor=\"" . getNodeColor("Vse")  . "\"];" . PHP_EOL);
  fwrite($fp, "    Network  [shape=". getNodeShape("VseNetwork")     . ",style=filled,fillcolor=\"" . getNodeColor("VseNetwork") . "\"];" . PHP_EOL);
  fwrite($fp, "    StorProf [shape=". getNodeShape("StorageProfile") . ",style=filled,fillcolor=\"" . getNodeColor("StorageProfile")  . "\"];" . PHP_EOL);
  fwrite($fp, "    vApp     [shape=". getNodeShape("Vapp")           . ",style=filled,fillcolor=\"" . getNodeColor("Vapp")  . "\"];" . PHP_EOL);
  fwrite($fp, "    VM       [shape=". getNodeShape("VM")             . ",style=filled,fillcolor=\"" . getNodeColor("VM")   . "\"];" . PHP_EOL);
  fwrite($fp, "  }"                                             . PHP_EOL);

  ###################
  ## Node definitions
  ###################

  fwrite($fp, "  # Orgs"                                     . PHP_EOL);
  fwrite($fp, "  {"                                          . PHP_EOL);
  fwrite($fp, "    node [shape=". getNodeShape("Org") . ",style=filled,fillcolor=\"". getNodeColor("Org") . "\"];" . PHP_EOL);
  foreach($orgs as $aNode) {
    printNode($fp, $aNode);
  }
  fwrite($fp, "  }"                                          . PHP_EOL);

  fwrite($fp, "  # vDCs"                                     . PHP_EOL);
  fwrite($fp, "  {"                                          . PHP_EOL);
  fwrite($fp, "    node [shape=". getNodeShape("Vdc") . ",style=filled,fillcolor=\"". getNodeColor("Vdc") . "\"];" . PHP_EOL);
  foreach($vdcs as $aNode) {
    printNode($fp, $aNode);
  }
  fwrite($fp, "  }"                                          . PHP_EOL);

  fwrite($fp, "  # vSEs"                                     . PHP_EOL);
  fwrite($fp, "  {"                                          . PHP_EOL);
  fwrite($fp, "    node [shape=". getNodeShape("Vse") . ",style=filled,fillcolor=\"". getNodeColor("Vse") . "\"];" . PHP_EOL);
  foreach($vses as $aNode) {
    printNode($fp, $aNode);
  }
  fwrite($fp, "  }"                                          . PHP_EOL);

  fwrite($fp, "  # vSE Networks"                             . PHP_EOL);
  fwrite($fp, "  {"                                          . PHP_EOL);
  fwrite($fp, "    node [shape=". getNodeShape("VseNetwork") . ",style=filled,fillcolor=\"". getNodeColor("VseNetwork") . "\"];" . PHP_EOL);
  foreach($vseNets as $aNode) {
    printNode($fp, $aNode);
  }
  fwrite($fp, "  }"                                          . PHP_EOL);

  # $isolatedNets
  fwrite($fp, "  # Isolated Networks"                        . PHP_EOL);
  fwrite($fp, "  {"                                          . PHP_EOL);
  fwrite($fp, "    node [shape=". getNodeShape("IsolatedNetwork") . ",style=filled,fillcolor=\"". getNodeColor("IsolatedNetwork") . "\"];" . PHP_EOL);
  foreach($isolatedNets as $aNode) {
    printNode($fp, $aNode);
  }
  fwrite($fp, "  }"                                          . PHP_EOL);

  fwrite($fp, "  # Storage Profiles"                         . PHP_EOL);
  fwrite($fp, "  {"                                          . PHP_EOL);
  fwrite($fp, "    node [shape=". getNodeShape("StorageProfile") . ",style=filled,fillcolor=\"". getNodeColor("StorageProfile") . "\"];" . PHP_EOL);
  foreach($storProfs as $aNode) {
    printNode($fp, $aNode);
  }
  fwrite($fp, "  }"                                          . PHP_EOL);

  fwrite($fp, "  # vApps"                                    . PHP_EOL);
  fwrite($fp, "  {"                                          . PHP_EOL);
  fwrite($fp, "    node [shape=". getNodeShape("Vapp") . ",style=filled,fillcolor=\"". getNodeColor("Vapp") . "\"];" . PHP_EOL);
  foreach($vapps as $aNode) {
    printNode($fp, $aNode);
  }
  fwrite($fp, "  }"                                          . PHP_EOL);

  fwrite($fp, "  # VMs"                                      . PHP_EOL);
  fwrite($fp, "  {"                                          . PHP_EOL);
  fwrite($fp, "    node [shape=". getNodeShape("VM") . ",style=filled,fillcolor=\"". getNodeColor("VM") . "\"];" . PHP_EOL);
  foreach($vms as $aNode) {
    printNode($fp, $aNode);
  }
  fwrite($fp, "  }"                                          . PHP_EOL);

  ###################
  ## Edge definitions
  ###################

# print "Orgs:" . PHP_EOL;
# print "========" . PHP_EOL;

  fwrite($fp, "  #"                    . PHP_EOL);
  fwrite($fp, "  # Edges"              . PHP_EOL);
  fwrite($fp, "  #"                    . PHP_EOL);
  fwrite($fp, ""                       . PHP_EOL);
  fwrite($fp, "  # Org edges:"         . PHP_EOL);

  #              # Org edges:"
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

function getNodeShape($nodeType) {
  if($nodeType      == "Org") {
    return SHAPE4ORG;
  }
  else if($nodeType == "Vdc") {
    return SHAPE4VDC;
  }
  else if($nodeType == "Vse") {
    return SHAPE4VSE;
  }
  else if($nodeType == "VseNetwork") {
    return SHAPE4NET1;
  }
  else if($nodeType == "IsolatedNetwork") {
    return SHAPE4NET2;
  }
  else if($nodeType == "Vapp") {
    return SHAPE4VAP;
  }
  else if($nodeType == "VM") {
    return SHAPE4VM;
  }
  else if($nodeType == "StorageProfile") {
    return SHAPE4STO;
  }
  else {
    die("Missing legend for " . $nodeType);
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
  if(get_class($obj) === "Vapp" && ! $doPrintVdc2VappLinks) {
    $shouldPrintLink=false;
  }

  if($shouldPrintLink) {
    $attrs='';
    if(get_class($obj) === "IsolatedNetwork") {
      $attrs=' [style="dotted"]';
    }
    fwrite($fp, "    \"$pId\":n->\"$id\":s$attrs;" . PHP_EOL);

    if(get_class($obj) === "VM" && $doPrintVdc2VmLinks) {
      $vdcId=simplifyString($obj->vdc->id);
      fwrite($fp, "    \"$vdcId\":n->\"$id\":s$attrs;" . PHP_EOL);
    }
  }

  ## Network Links for VMs and vApps
  if((get_class($obj) === "Vapp" && $doPrintVappNetLinks) || (get_class($obj) === "VM" && $doPrintVmNetLinks)) {
    foreach($obj->networks as $aNetwork) {
      ## On networks id == name, so this string should be network's id.
      fwrite($fp, "    \"" . $aNetwork . "\":n->\"$id\":s;" . PHP_EOL);
    }
  }

  ## Storage Profile Links for VMs
  if(get_class($obj) === "VM" && $doPrintVmStorProfLinks) {
    fwrite($fp, "    \"" . simplifyString(getStorProfIdFromName($obj->storProf, $storProfs)) . "\":n->\"$id\":s;" . PHP_EOL);
  }

}

function getStorProfIdFromName($storProf, &$storProfs) {
  foreach($storProfs as $aStorProf) {
    if($aStorProf->name == $storProf) {
      return $aStorProf->id;
    }
    return null;
  }
}

/**
 * Generates node entry of an object when generating a GraphViz diagram
 *
 * @param $fp File Handler to write to
 * @param $obj The object that has non-null "id" and "name" fields and a static field "classDisplayName".
 */
function printNode($fp, $obj) {
    $id=simplifyString($obj->id);
#   fwrite($fp, "    \"$id\" [label=\"" . $obj->name . "\",style=filled,fillcolor=\"" . getNodeColor($obj) . "\"]" . PHP_EOL);
    fwrite($fp, "    \"$id\" [label=\"" . $obj->name . "\"]" . PHP_EOL);
    fwrite($fp, "    rank = same; " . $obj::$classDisplayName . "; \"$id\";" . PHP_EOL);
}

function getNodeColor($nodeType) {
  if($nodeType == "Org") {
    return COLOR4ORG;
  }
  else if($nodeType == "Vdc") {
    return COLOR4VDC;
  }
  else if($nodeType == "Vse") {
    return COLOR4VSE;
  }
  else if($nodeType == "VseNetwork") {
    return COLOR4NET1;
  }
  else if($nodeType == "IsolatedNetwork") {
    return COLOR4NET2;
  }
  else if($nodeType == "Vapp") {
    return COLOR4VAP;
  }
  else if($nodeType == "VM") {
    return COLOR4VM;
  }
  else if($nodeType == "StorageProfile") {
    return COLOR4STO;
  }
  else {
    die("Missing color for " . $nodeType);
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
 * Returns the network object representing isolated networks
 *
 * @param $networkName Name of a network
 * @param $vseNets Array of VseNetwork objects from this lib
 * @param $vms Array of IsolatedNetwork objects from this lib
 * @return boolean True if network name matches with a VseNetwork or a IsolatedNetwork
 */
function netNameIsInVseNetworkArrays($networkName, $isolatedNets /* IsolatedNetwork */ , $vseNets /* VseNetwork */) {
  foreach($isolatedNets as $aNet) {
    if($networkName === $aNet->name) {
      return true;
    }
  }
  foreach($vseNets as $aNet) {
    if($networkName === $aNet->name) {
      return true;
    }
  }
  return false;
}

?>
