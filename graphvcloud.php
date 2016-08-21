<?php
/**
 * Generates a graphviz diagram representing your vCloud entities.
 * <p>
 * Generates a graphviz diagram representing your vCloud entities (orgs, vDCs, vShield Edges, ...). <br/>
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
 * VM:      hash 'name', 'id', networkReference[], vAppReference
 * vApp:    hash 'name', 'id', networkReference[]
 * network: hash 'name', 'id', addressing (net/mask string)
 * vSE:     hash 'name', 'id', vDCReference
 * vDC:     hash 'name', 'id', orgReference
 * org:     hash 'name', isEnabled
 *
 * <p>
 * Tested on Ubuntu 15.04 64b with PHP 5.6.4
 *
 * @author Angel Galindo Muñoz (zoquero at gmail dot com)
 * @version 1.0
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
    "dir:",       //-o|--dir       [required]
);

$opts = getopt($shorts, $longs);

// minimum conf
$colSep  = ";";

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
        $oDir = $opts['o'];
        break;
    case "dir":
        $oDir = $opts['dir'];
        break;
}

// parameters validation
if (!isset($server) || !isset($user) || !isset($pswd) || !isset($sdkversion) || !isset($oDir)) {
    echo "Error: missing required parameters\n";
    usage();
    exit(1);
}

if (!is_dir($oDir)) {
  mkdir($oDir, 0700);
  echo "Error: $oDir is not a directory and cannot be created\n";
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
        echo "\n\nValidation of certificates is successful.\n\n";
        $flag=true;
    }
    else {
        echo "\n\nCertification Failed.\n";
        $flag=false;
    }
}

if ($flag==true) {
  if (!isset($certPath)) {
      echo "Ignoring the Certificate Validation --Fake certificate - DO NOT DO THIS IN PRODUCTION.\n\n";
  }
  // vCloud login
  $service = VMware_VCloud_SDK_Service::getService();
  $service->login($server, array('username'=>$user, 'password'=>$pswd), $httpConfig, $sdkversion);

  // create sdk admin object
  $sdkAdminObj = $service->createSDKAdminObj();

  // create an SDK Org object
  $orgRefs = $service->getOrgRefs();
  if (0 == count($orgRefs)) {
      exit("No organizations found\n");
  }
  # echo "Found " . count($orgRefs) . " organizations:\n";
  foreach ($orgRefs as $orgRef) {
    ## Iterate through organizations ##
    $sdkOrg = $service->createSDKObj($orgRef);
    echo "* org: " . $sdkOrg->getOrg()->get_name() . "\n";

    // create admin org object
    $adminOrgRefs = $sdkAdminObj->getAdminOrgRefs($sdkOrg->getOrg()->get_name());
    if(empty($adminOrgRefs)) {
        exit("No admin org with name " . $sdkOrg->getOrg()->get_name() . " is found.");
    }
    $adminOrgRef = $adminOrgRefs[0];
    $adminOrgObj = $service->createSDKObj($adminOrgRef->get_href());

    $org=org2obj($sdkOrg->getOrg());
print "DEBUG: " . $org . "\n";

    $vdcRefs = $sdkOrg->getVdcRefs();
    if (0 == count($vdcRefs)) {
        exit("No vDCs found\n");
    }

    # echo "Found " . count($vdcRefs) . " vDCs:\n";
    foreach ($vdcRefs as $vdcRef) {
      ## Iterate through vDCs ##
      $sdkVdc = $service->createSDKObj($vdcRef);
      echo "-* vDC: " . $sdkVdc->getVdc()->get_name() . "\n";

      $vdc=vdc2obj($org, $sdkVdc->getVdc());
print "DEBUG: " . $vdc . "\n";

      // create admin vdc object
      $adminVdcRefs = $adminOrgObj->getAdminVdcRefs($sdkVdc->getVdc()->get_name());
      if(empty($adminVdcRefs)) {
          exit("No admin vdc with name " . $sdkVdc->getVdc()->get_name() . " is found.");
      }
      $adminVdcRef=$adminVdcRefs[0];
      $adminVdcObj=$service->createSDKObj($adminVdcRef->get_href());

      $edgeGatewayRefs = $adminVdcObj->getEdgeGatewayRefs();
      if (0 == count($edgeGatewayRefs)) {
        echo "No vShield Edges found in this vDC\n";
        continue;
      }
      foreach ($edgeGatewayRefs as $edgeGatewayRef) {
        echo "--* vSE: " . $edgeGatewayRef->get_name() . "\n";
        $edgeGatewayObj = $service->createSDKObj($edgeGatewayRef->get_href());
        $vse=vse2obj($org, $vdc, $edgeGatewayObj);
print "DEBUG: " . $vse . "\n";

        $__vse   = $edgeGatewayObj->getEdgeGateway();
        $vseConf = $__vse->getConfiguration();
        foreach($vseConf->getGatewayInterfaces()->getGatewayInterface() as $iface) {
          $vseNet=vseNetwork2obj($org, $vdc, $vse, $iface);
print "DEBUG: " . $vseNet . "\n";
#exit(1);
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
print "DEBUG: a vApp: " . $vApp . "\n";

          foreach ($aSdkVApp->getVapp()->getChildren()->getVM() as $aVM) {
            # Empirically:
            # * status=3 == Suspended
            # * status=4 == PoweredOn
            # * status=8 == PoweredOff
print "DEBUG: VM: name=" .  $aVM->get_name() . ", status=" . $aVM->get_status() . "\n";
#           $aVM->get_name()
#           $aVM->get_status()


print "\nDEBUG:\n";
showObject($aVM);
exit(1);
  This is an object of class VMware_VCloud_API_VmType
  Public methods:
   * __construct
   * getVAppScopedLocalId
   * setVAppScopedLocalId
   * getEnvironment
   * setEnvironment
   * getVmCapabilities
   * setVmCapabilities
   * getStorageProfile
   * setStorageProfile
   * get_needsCustomization
   * set_needsCustomization
   * get_nestedHypervisorEnabled
   * set_nestedHypervisorEnabled
   * get_tagName
   * set_tagName
   * export
   * build
   * getVAppParent
   * setVAppParent
   * getSection
   * setSection
   * addSection
   * getDateCreated
   * setDateCreated
   * get_deployed
   * set_deployed
   * getFiles
   * setFiles
   * get_status
   * set_status
   * getDescription
   * setDescription
   * getTasks
   * setTasks
   * get_name
   * set_name
   * get_operationKey
   * set_operationKey
   * get_id
   * set_id
   * getLink
   * setLink
   * addLink
   * get_href
   * set_href
   * get_type
   * set_type
   * getVCloudExtension
   * setVCloudExtension
   * addVCloudExtension
   * get_anyAttributes
   * set_anyAttributes
array(1) {
  ["anyAttributes"]=>
  array(0) {
  }
}




          }


        }
      }


    }
  }

}
else {
    echo "\nLogin Failed due to certification mismatch.";
    exit(1);
}
exit(0);



function usage() {
    echo "Usage:\n\n";
    echo "  [Description]\n";
    echo "     Dumps to CSV or XML all the entities (vApps, VMs,  vShields, vDCs and organizations) that you have access to.\n";
    echo "\n";
    echo "  [Usage]\n";
    echo "     # php vc2csv.php --server <server> --user <username> --pswd <password> --sdkver <sdkversion> --dir <dir> --format <format>\n";
    echo "     # php vc2csv.php -s <server> -u <username> -p <password> -v <sdkversion> -o <dir> -f <format>\n";
    echo "\n";
    echo "     -s|--server <IP|hostname>        [req] IP or hostname of the vCloud Director.\n";
    echo "     -u|--user <username>             [req] User name in the form user@organization\n";
    echo "                                           for the vCloud Director.\n";
    echo "     -p|--pswd <password>             [req] Password for user.\n";
    echo "     -v|--sdkver <sdkversion>         [req] SDK Version e.g. 1.5, 5.1 and 5.5.\n";
    echo "     -o|--dir <directory>             [req] Folder where CSVs will be craeted.\n";
    echo "     -f|--format (csv|xml)            [req] Format for output.\n";
    echo "\n";
    echo "  [Options]\n";
    echo "     -e|--certpath <certificatepath>  [opt] Local certificate's full path.\n";
    echo "\n";
    echo "  You can set the security parameters like server, user and pswd in 'config.php' file\n";
    echo "\n";
    echo "  [Examples]\n";
    echo "     # php exportvcloud.php --server 127.0.0.1 --user admin@MyOrg --pswd mypassword --sdkver 5.5 --dir /tmp/vc --format xml\n\n";

}

/**
 * Prints to output the classname and public methods of an object
 *
 * @param The object
 */
function showObject($o) {
  if(is_null($o)) {
    echo "  Is a NULL object\n";
    return;
  }
  else if(is_array($o)) {
    echo "  It's not an object, it's an array\n";
    return;
  }
  else {
    echo "  This is an object of class " . get_class($o) . "\n";
  }
  echo "  Public methods:\n";
  $egMethods=get_class_methods($o);
  foreach ($egMethods as $aMethod) {
    echo "   * $aMethod\n";
  }

  var_dump(get_object_vars($o));
}


/**
 * Just for debugging, outputs a Firewall
 *
 * @param The Firewall rule
 */
function showFirewallRule($r) {
  print "Firewall rule: \n";
  print "  getId : " . $r->getId() . "\n";
  print "  getIsEnabled : " . $r->getIsEnabled() . "\n";
  print "  getMatchOnTranslate : " . $r->getMatchOnTranslate() . "\n";
  print "  getDescription : " . $r->getDescription() . "\n";
  print "  getPolicy : " . $r->getPolicy() . "\n";
# print "  getProtocols : " . $r->getProtocols() . "\n";
  print "  getProtocols : \n";
#   showObject($r->getProtocols());
  print "               : tcp   :" . $r->getProtocols()->getTcp() . "\n";
  print "               : udp   :" . $r->getProtocols()->getUdp() . "\n";
  print "               : icmp  :" . $r->getProtocols()->getIcmp() . "\n";
  print "               : any   :" . $r->getProtocols()->getAny() . "\n";
  print "               : other :" . $r->getProtocols()->getOther() . "\n";
  print "  getIcmpSubType : " . $r->getIcmpSubType() . "\n";
  print "  getPort : " . $r->getPort() . "\n";
  print "  getDestinationPortRange : " . $r->getDestinationPortRange() . "\n";
  print "  getDestinationIp : " . $r->getDestinationIp() . "\n";
  print "  getDestinationVm : " . $r->getDestinationVm() . "\n";
  print "  getSourcePort : " . $r->getSourcePort () . "\n";
  print "  getSourcePortRange : " . $r->getSourcePortRange () . "\n";
  print "  getSourceIp : " . $r->getSourceIp () . "\n";
  print "  getSourceVm : " . $r->getSourceVm () . "\n";
  print "  getDirection : " . $r->getDirection () . "\n";
  print "  getEnableLogging : " . $r->getEnableLogging () . "\n";
  print "  get_tagName : " . $r->get_tagName () . "\n";
## Són Arrays, els del meu exemple estan buits i no sé quina mena d'objectes poden contenir
# print "  getVCloudExtension : " . $r->getVCloudExtension () . "\n";
# print "  get_anyAttributes : " . $r->get_anyAttributes () . "\n";
}


/**
 * Returns the headers for the CSV that should contain firewall rules
 *
 * @return The string with the line to dump
 */
function firewallRuleCsvHeader() {
  global $colSep;

  return "FwDefaultAction" . $colSep .
         "FwLogDefaultAction" . $colSep .
         "FwIsEnabled" . $colSep .
         "Id" . $colSep .
         "IsEnabled" . $colSep .
         "MatchOnTranslate" . $colSep .
         "Description" . $colSep .
         "Policy" . $colSep .
         "Protocols" . $colSep .
         "IcmpSubType" . $colSep .

         "SourceIp" . $colSep . 
         "SourcePort" . $colSep . 
         "SourcePortRange" . $colSep . 
         "SourceVm" . $colSep . 
         
         "DestinationIp" . $colSep . 
         "Port" . $colSep . 
         "DestinationPortRange" . $colSep . 
         "DestinationVm" . $colSep . 
         
         "Direction" . $colSep . 
         "EnableLogging";
}

/**
 * Returns the CSV row representing a firewall rule
 *
 * @param The Firewall rule
 * @return The string with the line to dump
 */
function firewallRule2CsvRow($r) {
  global $colSep;
  $ret = $r->getId() . $colSep;
  $ret .= $r->getIsEnabled() . $colSep;
  $ret .= $r->getMatchOnTranslate() . $colSep;
  $ret .= $r->getDescription() . $colSep;
  $ret .= $r->getPolicy() . $colSep;
  
  if($r->getProtocols()->getTcp() == 1) {
    $ret .= "T";
  }
  if($r->getProtocols()->getUdp() == 1) {
    $ret .= "U";
  }
  if($r->getProtocols()->getIcmp() == 1) {
    $ret .= "I";
  }
  if($r->getProtocols()->getAny() == 1) {
    $ret .= "A";
  }
  if($r->getProtocols()->getOther() == 1) {
    $ret .= "O";
  }
  $ret .= $colSep;
  
  $ret .= $r->getIcmpSubType() . $colSep;

  $ret .= $r->getSourceIp() . $colSep;
  $ret .= $r->getSourcePort() . $colSep;
  $ret .= $r->getSourcePortRange() . $colSep;
  $ret .= $r->getSourceVm() . $colSep;

  $ret .= $r->getDestinationIp() . $colSep;
  $ret .= $r->getPort() . $colSep;
  $ret .= $r->getDestinationPortRange() . $colSep;
  $ret .= $r->getDestinationVm() . $colSep;

  $ret .= $r->getDirection() . $colSep;
  $ret .= $r->getEnableLogging() . $colSep;
  return $ret;
}

function natRule2CsvRow($r) {
  global $colSep;
# showObject($r);

# Description can have new lines
# $ret  = $r->getDescription() . $colSep;
  $ret  = trim(preg_replace('/\s+/', ' ', $r->getDescription())) . $colSep;
  $ret .= $r->getRuleType() . $colSep;
  $ret .= $r->getIsEnabled() . $colSep;
  $ret .= $r->getId() . $colSep;

  $ret .= $r->getOneToOneBasicRule() . $colSep;
  $ret .= $r->getOneToOneVmRule() . $colSep;
  $ret .= $r->getPortForwardingRule() . $colSep;
  $ret .= $r->getVmRule() . $colSep;
  $ret .= $r->get_tagName() . $colSep;
# $ret .= $r->getVCloudExtension() . $colSep;
# $ret .= $r->get_anyAttributes() . $colSep;

  $gr=$r->getGatewayNatRule();
# showObject($gr->getInterface());
  $ret .= $gr->getInterface()->get_name() . $colSep;
  $ret .= $gr->getOriginalIp() . $colSep;
  $ret .= $gr->getOriginalPort() . $colSep;
  $ret .= $gr->getTranslatedIp() . $colSep;
  $ret .= $gr->getTranslatedPort() . $colSep;
  $ret .= $gr->getProtocol() . $colSep;
  $ret .= $gr->getIcmpSubType() . $colSep;
  $ret .= $gr->get_tagName();
# $ret .= $gr->getVCloudExtension() . $colSep;
# $ret .= $gr->get_anyAttributes() . $colSep;

  return $ret;
}

function natRuleCsvHeader() {
  global $colSep;

  $ret  = "SvcNatType" . $colSep;
  $ret .= "SvcPolicy" . $colSep;
  $ret .= "SvcExternalIp" . $colSep;
  $ret .= "SvcTagName" . $colSep;
  $ret .= "SvcIsEnabled" . $colSep;
  $ret .= "Description" . $colSep;
  $ret .= "RuleType" . $colSep;
  $ret .= "IsEnabled" . $colSep;
  $ret .= "Id" . $colSep;
  $ret .= "OneToOneBasicRule" . $colSep;
  $ret .= "OneToOneVmRule" . $colSep;
  $ret .= "PortForwardingRule" . $colSep;
  $ret .= "VmRule" . $colSep;
  $ret .= "tagName" . $colSep;
  $ret .= "Interface" . $colSep;
  $ret .= "OriginalIp" . $colSep;
  $ret .= "OriginalPort" . $colSep;
  $ret .= "TranslatedIp" . $colSep;
  $ret .= "TranslatedPort" . $colSep;
  $ret .= "Protocol" . $colSep;
  $ret .= "IcmpSubType" . $colSep;
  $ret .= "tagName2";
  return $ret;
}

function vDC2CsvRow($r, $orgName, $service) {
  global $colSep;
  global $vApFp;
  $ret  = $r->getAllocationModel() . $colSep;
#
# $ret .= $r->getStorageCapacity() . $colSep; ## TO_DO 
# TO_DO: on function vDC2CsvRow: get storage capacity usage: $sdkVdc->getStorageCapacity() should return an object of class VMware_VCloud_API_CapacityWithUsageType but now gets a null object.
  $ret .= $r->getComputeCapacity()->getCpu()->getReserved() . $colSep;
  $ret .= $r->getComputeCapacity()->getCpu()->getUsed() . $colSep;
  $ret .= $r->getComputeCapacity()->getCpu()->getOverhead() . $colSep;
  $ret .= $r->getComputeCapacity()->getCpu()->getUnits() . $colSep;
  $ret .= $r->getComputeCapacity()->getCpu()->getAllocated() . $colSep;
  $ret .= $r->getComputeCapacity()->getCpu()->getLimit() . $colSep;

  $ret .= $r->getComputeCapacity()->getMemory()->getReserved() . $colSep;
  $ret .= $r->getComputeCapacity()->getMemory()->getUsed() . $colSep;
  $ret .= $r->getComputeCapacity()->getMemory()->getOverhead() . $colSep;
  $ret .= $r->getComputeCapacity()->getMemory()->getUnits() . $colSep;
  $ret .= $r->getComputeCapacity()->getMemory()->getAllocated() . $colSep;
  $ret .= $r->getComputeCapacity()->getMemory()->getLimit() . $colSep;

  $aREArray=array();
  foreach ($r->getResourceEntities()->getResourceEntity() as $aRE) {
    $aType=preg_replace('/^application\/vnd\.vmware\.vcloud\./', '', $aRE->get_type());
    $aType=preg_replace('/\+xml$/', '', $aType);
    if($aType === "vApp") {
      $aVApp = $service->createSDKObj($aRE->get_href());
      fwrite($vApFp, $orgName . $colSep . $r->get_name() . $colSep . vApp2CsvRow($aVApp, $orgName, $r->get_name()) . "\n");
    }
    array_push($aREArray, "[" . $aType . ": " . $aRE->get_name() . "]");
  }
  $aResArrayStr="[" . join(", ", $aREArray) . "]";
  $ret .= $aResArrayStr . $colSep;

  $networks=array();
  foreach ($r->getAvailableNetworks()->getNetwork() as $aNetwork) {
    array_push($networks, $aNetwork->get_name());
  }
  $ret .= "[" . join(", ", $networks) . "]" . $colSep;

  $ret .= "[" . join(", ", $r->getCapabilities()->getSupportedHardwareVersions()->getSupportedHardwareVersion()) . "]" . $colSep;

  $ret .= $r->getNicQuota() . $colSep;
  $ret .= $r->getNetworkQuota() . $colSep;
  $ret .= $r->getUsedNetworkCount() . $colSep;
  $ret .= $r->getVmQuota() . $colSep;
  $ret .= $r->getIsEnabled() . $colSep;

  $storProfs=array();
  foreach ($r->getVdcStorageProfiles()->getVdcStorageProfile() as $aSP) {
    array_push($storProfs, $aSP->get_name());
  }
  $ret .= "[" . join (", ", $storProfs) . "]" . $colSep;

  $ret .= $r->get_status() . $colSep;
  $ret .= $r->getDescription() . $colSep;
  ## Let's omit the tasks, an array of VMware_VCloud_API_TaskType objects
  # $ret .= $r->getTasks() . $colSep;
  $ret .= $r->get_name() . $colSep;
  $ret .= $r->get_operationKey() . $colSep;
  $ret .= $r->get_id() . $colSep;
  return $ret;
}

function vDCCsvHeader() {
  global $colSep;
  $ret  = "AllocationModel". $colSep;
# $ret .= "StorageCapacity". $colSep; ## Bug pending
  $ret .= "CpuReserved". $colSep;
  $ret .= "CpuUsed". $colSep;
  $ret .= "CpuOverhead". $colSep;
  $ret .= "CpuUnits". $colSep;
  $ret .= "CpuAllocated". $colSep;
  $ret .= "CpuLimit". $colSep;
  $ret .= "MemReserved". $colSep;
  $ret .= "MemUsed". $colSep;
  $ret .= "MemOverhead". $colSep;
  $ret .= "MemUnits". $colSep;
  $ret .= "MemAllocated". $colSep;
  $ret .= "MemLimit". $colSep;
  $ret .= "ResourceEntities". $colSep;
  $ret .= "Networks". $colSep;
  $ret .= "SupportedHardwareVersions". $colSep;
  $ret .= "NicQuota". $colSep;
  $ret .= "NetworkQuota". $colSep;
  $ret .= "UsedNetworkCount". $colSep;
  $ret .= "VmQuota". $colSep;
  $ret .= "IsEnabled". $colSep;
  $ret .= "StorageProfiles". $colSep;
  $ret .= "Status". $colSep;
  $ret .= "Description". $colSep;
## Let's omit the tasks, an array of VMware_VCloud_API_TaskType objects
# $ret .= "Tasks". $colSep;
  $ret .= "Name". $colSep;
  $ret .= "OperationKey". $colSep;
  $ret .= "Id". $colSep;
  return $ret;
}

function vSE2CsvRow($vSEObj) {
  global $colSep;
  $ret = "";
  $vSE                = $vSEObj->getEdgeGateway();
  $vSEConf            = $vSE->getConfiguration();

# print "vSEObj:\n";
  $ret .= $vSEObj->getEntityId() . $colSep;

# print "vSE:\n";
  $ret .= $vSE->get_status() . $colSep;
  $ret .= $vSE->getDescription() . $colSep;
## Let's omit the tasks, an array of VMware_VCloud_API_TaskType objects
# $ret .= "task=". $vSE->getTasks() . $colSep;
  $ret .= $vSE->get_name() . $colSep;
  $ret .= $vSE->get_operationKey() . $colSep;
  $ret .= $vSE->get_id() . $colSep;

# print "vSEConf:\n";
  $ret .= $vSEConf->getBackwardCompatibilityMode() . $colSep;
  $ret .= $vSEConf->getGatewayBackingConfig() . $colSep;
  $ifaces = array();

  # TO_DO: Dump gateway interfaces to it's own CSV file.
  foreach($vSEConf->getGatewayInterfaces()->getGatewayInterface() as $iface) {
    $t  = "name=" . $iface->getName() . ", ";
    $t .= "disp=" . $iface->getDisplayName() . ", ";
    $t .= "netw=" . $iface->getNetwork()->get_name() . ", ";
    $t .= "iftype=" . $iface->getInterfaceType() . ", ";
#   $t .= "snpart=" . $iface->getSubnetParticipation() . ", ";
    $subnetPart = array();
    foreach($iface->getSubnetParticipation() as $sp) {
      $tt  = "gw=" . $sp->getGateway() . ", ";
      $tt .= "mask=" . $sp->getNetmask() . ", ";
      $tt .= "ipaddr=" . $sp->getIpAddress() . ", ";
      $ipranges    = array();
      $iprangesStr = "";
      if(! is_null($sp->getIpRanges())) {
        foreach($sp->getIpRanges()->getIpRange() as $anIpRange) {
          array_push($ipranges, "[" . $anIpRange->getStartAddress() . "-" . $anIpRange->getEndAddress() . "]");
        }
        $iprangesStr="[" . join(", ", $ipranges) . "]";
      }
      $tt .= "iprang=$iprangesStr";
      array_push($subnetPart, "[" . $tt . "]");
    }
    $subnetPartStr="[" . join(", ", $subnetPart) . "]";
    $t .= "snpart=" . $subnetPartStr . ", ";
    $t .= "ratlim=" . $iface->getApplyRateLimit() . ", ";
    $t .= "inrate=" . $iface->getInRateLimit() . ", ";
    $t .= "ourate=" . $iface->getOutRateLimit() . ", ";
    $t .= "defrou=" . $iface->getUseForDefaultRoute();
    array_push($ifaces, "[" . $t . "]");
  }
  $ifacesStr="[" . join(", ", $ifaces) . "]";
  $ret .= $ifacesStr . $colSep;
  $ret .= $vSEConf->getHaEnabled() . $colSep;
  $ret .= $vSEConf->getUseDefaultRouteForDnsRelay() . $colSep;

  return $ret;
}

function vSECsvHeader() {
  global $colSep;
  $ret  = "EntityId" . $colSep;
  $ret .= "Status" . $colSep;
  $ret .= "Description" . $colSep;
  $ret .= "Name" . $colSep;
  $ret .= "OperationKey" . $colSep;
  $ret .= "Id" . $colSep;
  $ret .= "BackwardCompatibilityMode" . $colSep;
  $ret .= "GatewayBackingConfig" . $colSep;
  $ret .= "Interfaces" . $colSep;
  $ret .= "HaEnabled" . $colSep;
  $ret .= "UseDefaultRouteForDnsRelay" . $colSep;
  return $ret;
}

function vApp2CsvRow($vApp, $orgName, $vdcName) {
  global $colSep;
  global $vMFp;
  $ret = "";
  $ret .= $vApp->getVapp()->getOwner()->getUser()->get_name() . $colSep;
  $ret .= $vApp->getVapp()->getInMaintenanceMode() . $colSep;
  foreach ($vApp->getVapp()->getChildren()->getVM() as $aVM) {
    fwrite($vMFp, $orgName . $colSep . $vdcName . $colSep . $vApp->getVapp()->get_name() . $colSep . vM2CsvRow($aVM) . "\n");
  }
  # $ret .= $vApp->getVapp()->get_ovfDescriptorUploaded() . $colSep;

  $lsst="";
  foreach($vApp->getVapp()->getSection() as $aSection) {
    if(get_class($aSection) === "VMware_VCloud_API_LeaseSettingsSectionType") {
      $lsst .= $aSection->getDeploymentLeaseInSeconds() . ", ";
      $lsst .= $aSection->getStorageLeaseInSeconds() . ", ";
      $lsst .= $aSection->getDeploymentLeaseExpiration() . ", ";
      $lsst .= $aSection->getStorageLeaseExpiration();
#     $lsst .= $aSection->getInfo() . $colSep; ## TO_DO class VMware_VCloud_API_OVF_Msg_Type
    }
    else if(get_class($aSection) === "VMware_VCloud_API_OVF_StartupSection_Type") {
      ## TO_DO
    }
    else if(get_class($aSection) === "VMware_VCloud_API_OVF_NetworkSection_Type") {
      ## TO_DO
    }
    else if(get_class($aSection) === "VMware_VCloud_API_NetworkConfigSectionType") {
      ## TO_DO
    }
    else if(get_class($aSection) === "VMware_VCloud_API_SnapshotSectionType") {
      ## TO_DO
    }
  }
  $ret .= $lsst . $colSep;

##
##  TO_DO Storage Profile
##  $storProfs=array();
##  foreach ($vm->getStorageProfile() as $aSP) {
##    foreach ($aSP as $oSP) {
##      array_push($storProfs, $oSP->get_name() ??? );
##    }
##  }
##  $ret .= join (", ", $storProfs) . $colSep;
##

  $ret .= $vApp->getVapp()->getDateCreated() . $colSep;
  $ret .= $vApp->getVapp()->get_deployed() . $colSep;
# $ret .= $vApp->getVapp()->getFiles() . $colSep;
  $ret .= $vApp->getVapp()->get_status() . $colSep;
  $ret .= $vApp->getVapp()->getDescription() . $colSep;
# $ret .= $vApp->getVapp()->getTasks() . $colSep;
  $ret .= $vApp->getVapp()->get_name() . $colSep;
  $ret .= $vApp->getVapp()->get_operationKey() . $colSep;
  $ret .= $vApp->getVapp()->get_id() . $colSep;

  $ret .= $vApp->getId() . $colSep;
  $vms=array();
  foreach($vApp->getContainedVms() as $aVM) {
    array_push($vms, $aVM->get_name());
  }
  $ret .= "[" . join (", ", $vms) . "]" . $colSep;

  $networkConfigArray=array();
  foreach($vApp->getNetworkConfigSettings()->getNetworkConfig() as $aNetworkConfig) {
    $t  = "[";
    $t .= "Desc="    . $aNetworkConfig->getDescription() . ", ";
#   TO_DO VMware_VCloud_API_NetworkConfigurationType , for $aNetworkConfig->getConfiguration
    $t .= "IsDepld=" . $aNetworkConfig->getIsDeployed() . ", ";
    $t .= "NetName=" . $aNetworkConfig->get_networkName();
    $t .= "]";
    array_push($networkConfigArray, $t);
  }
  $ret .= "[" . join (", ", $networkConfigArray) . "]" . $colSep;

  $ret .= $vApp->getLeaseSettings()->getDeploymentLeaseInSeconds() . $colSep;
  $ret .= $vApp->getLeaseSettings()->getStorageLeaseInSeconds() . $colSep;
  $ret .= $vApp->getLeaseSettings()->getDeploymentLeaseExpiration() . $colSep;
  $ret .= $vApp->getLeaseSettings()->getStorageLeaseExpiration() . $colSep;
  # $ret .= $vApp->getLeaseSettings()->getInfo() . $colSep;

  # TO_DO StartupSettings
  # $ret .= $vApp->getStartupSettings() . $colSep;
  $networks=array();
  foreach($vApp->getNetworkSettings()->getNetwork() as $aNetwork) {
    array_push($networks, $aNetwork->get_name());
  }
  $ret .= "[" . join (", ", $networks) . "]" . $colSep;
  ## TO_DO class VMware_VCloud_API_ControlAccessParamsType for $vApp->getControlAccess()
  # $ret .= $vApp->getControlAccess() . $colSep;
  $ret .= $vApp->getStatus() . $colSep;
  $ret .= $vApp->getOwner()->getUser()->get_name() . $colSep;

  # TO_DO array of uuids from $vApp->getVmUUIDs()
  # $ret .= $vApp->getVmUUIDs() . $colSep;
  # TO_DO class VMware_VCloud_API_MetadataType for $vApp->getMetadata() 
  # $ret .= $vApp->getMetadata() . $colSep;
  $ret .= $vApp->getEntityId();
  return $ret;
}

function vM2CsvRow($vm) {
  global $colSep;
  $ret  = $vm->getVAppScopedLocalId() . $colSep;
# $ret .= $vm->getEnvironment()->...  . $colSep; ## TO_DO
  $ret .= $vm->getVmCapabilities()->getMemoryHotAddEnabled() . $colSep;
  $ret .= $vm->getVmCapabilities()->getCpuHotAddEnabled() . $colSep;

##
## TO_DO : VM Storage Profile
##
##  $storProfs=array();
##  foreach ($vm->getStorageProfile() as $aSP) {
##    foreach ($aSP as $oSP) {
##      array_push($storProfs, $oSP->get_name() ??? );
##    }
##  }
##  $ret .= join (", ", $storProfs) . $colSep;

  $ret .= $vm->get_needsCustomization() . $colSep;
  $ret .= $vm->get_nestedHypervisorEnabled() . $colSep;
# $ret .= $vm->getVAppParent() . $colSep;

# TO_DO
# foreach($vm->getSection() as $aSection) {
#   ## TO_DO: class VMware_VCloud_API_OVF_VirtualHardwareSection_Type for $aSection
#   ## TO_DO: objects of class VMware_VCloud_API_OVF_VSSD_Type , for $aSection->getSystem()
#   $ret .= $aSection->getDescription() . $colSep;
#   $ret .= join(", ", $aSection->getSystem()->getNotes()) . $colSep;
# }

  $ret .= $vm->getDateCreated() . $colSep;
  $ret .= $vm->get_deployed() . $colSep;
# $ret .= $vm->getFiles() . $colSep;
  $ret .= $vm->get_status() . $colSep;
  $ret .= $vm->getDescription() . $colSep;
# $ret .= $vm->getTasks() . $colSep;
  $ret .= $vm->get_name() . $colSep;
  $ret .= $vm->get_operationKey();
# $ret .= $vm->get_id() . $colSep;
  return $ret;
}


function vAppCsvHeader() {
  global $colSep;
  $ret  = "Owner" . $colSep;
  $ret .= "InMaintenanceMode" . $colSep;
  $ret .= "LeaseSettings" . $colSep;
  $ret .= "DateCreated" . $colSep;
  $ret .= "Deployed" . $colSep;
  $ret .= "Status" . $colSep;
  $ret .= "Description" . $colSep;
  $ret .= "Name" . $colSep;
  $ret .= "OperationKey" . $colSep;
  $ret .= "Id" . $colSep;
  $ret .= "Id2" . $colSep;
  $ret .= "VMs" . $colSep;
  $ret .= "NetConfSettings" . $colSep;
  $ret .= "DeploymentLeaseInSeconds" . $colSep;
  $ret .= "StorageLeaseInSeconds" . $colSep;
  $ret .= "DeploymentLeaseExpiration" . $colSep;
  $ret .= "StorageLeaseExpiration" . $colSep;
  $ret .= "NetSettings" . $colSep;
  $ret .= "Status" . $colSep;
  $ret .= "Owner" . $colSep;
  $ret .= "EntityId";
  return $ret;
}

function vMCsvHeader() {
  global $colSep;
  $ret  = "VAppScopedLocalId" . $colSep;
  $ret .= "MemoryHotAddEnabled" . $colSep;
  $ret .= "CpuHotAddEnabled" . $colSep;
  $ret .= "NeedsCustomization" . $colSep;
  $ret .= "NestedHypervisorEnabled" . $colSep;
  $ret .= "DateCreated" . $colSep;
  $ret .= "Deployed" . $colSep;
  $ret .= "Status" . $colSep;
  $ret .= "Description" . $colSep;
  $ret .= "Name" . $colSep;
  $ret .= "OperationKey";
  return $ret;
}



###

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



?>
