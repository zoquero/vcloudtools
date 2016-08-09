<?php
/**
 * Gets firewall rules of vShield Edges
 * <p>
 * Requires:<ul>
 *              <li> PHP version 5+
 *              <li> vCloud SDK for PHP for vCloud Suite 5.5
 *             ( https://developercenter.vmware.com/web/sdk/5.5.0/vcloud-php )
 * </ul>
 * <p>
 * Tested on Ubuntu 15.04 64b with PHP 5.6.4
 *
 * <p>
 * TO_DO Improve firewallRule2CsvRow, there are some "to dos" tagged.
 *
 * @author Angel Galindo Muñoz (zoquero at gmail dot com)
 * @version 1.0
 * @since 08/08/2016
 */

require_once dirname(__FILE__) . '/config.php';

// Get parameters from command line
$shorts  = "";
$shorts .= "s:";
$shorts .= "u:";
$shorts .= "p:";
$shorts .= "v:";
$shorts .= "c:";
$shorts .= "o:";
$shorts .= "d:";
$shorts .= "e:";
$shorts .= "f:";
$shorts .= "g::";
$shorts .= "h:";
$shorts .= "i:";
$shorts .= "j::";
$shorts .= "k:";
$shorts .= "m:";

$longs  = array(
    "server:",    //-s|--server    [required]
    "user:",      //-u|--user      [required]
    "pswd:",      //-p|--pswd      [required]
    "sdkver:",    //-v|--sdkver    [required]
    "certpath::", //-c|--certpath  [optional] local certificate path
    "org:",       //-o|--org       [optional]
    "vdc:",       //-d|--vdc       [optional]
    "vse:",       //-e|--vse       [optional]
    "fromip:",    //-f|--fromip    [required]
    "fromport:",  //-g|--fromport  [required]
    "proto:",     //-h|--proto     [required]
    "toip:",      //-i|--toip      [required]
    "toport:",    //-j|--toport     [required]
);

$orgArg = null;
$vdcArg = null;
$vseArg = null;
$fromip    = null;
$fromport  = null;
$proto     = null;
$toip      = null;
$toport    = null;

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

    case "c":
        $certPath = $opts['c'];
        break;
    case "certpath":
        $certPath = $opts['certpath'];
        break;

    case "f":
        $fromip = $opts['f'];
        break;
    case "fromip":
        $fromip = $opts['fromip'];
        break;

    case "g":
        $fromport = $opts['g'];
        break;
    case "fromport":
        $fromport = $opts['fromport'];
        break;

    case "h":
        $proto = $opts['h'];
        break;
    case "proto":
        $proto = $opts['proto'];
        break;

    case "i":
        $toip = $opts['i'];
        break;
    case "toip":
        $toip = $opts['toip'];
        break;

    case "j":
        $toport = $opts['j'];
        break;
    case "toport":
        $toport = $opts['toport'];
        break;

    case "o":
        $orgArg = $opts['o'];
        break;
    case "org":
        $orgArg = $opts['org'];
        break;

    case "d":
        $vdcArg = $opts['d'];
        break;
    case "vdc":
        $vdcArg = $opts['vdc'];
        break;

    case "e":
        $vseArg = $opts['e'];
        break;
    case "vse":
        $vseArg = $opts['vse'];
        break;

}

// parameters validation
if (!isset($server) || !isset($user) || !isset($pswd) || !isset($sdkversion)
  || !isset($fromip) || !isset($fromport) || !isset($proto) || !isset($toip) || !isset($toport)) {
    echo "Error: missing required parameters\n";
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
  if(!is_null($orgArg) && $orgArg != '') {
    $orgRefs = $service->getOrgRefs($orgArg);
    if (0 == count($orgRefs)) {
        exit("No organization $orgArg found\n");
    }
  }
  else {
    $orgRefs = $service->getOrgRefs();
    if (0 == count($orgRefs)) {
        exit("No organizations found\n");
    }
    # echo "Found " . count($orgRefs) . " organizations:\n";
  }

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

    if(!is_null($vdcArg) && $vdcArg != '') {
      $vdcRefs = $sdkOrg->getVdcRefs($vdcArg);
      if (0 == count($vdcRefs)) {
          exit("No vDC $vdcArg found in " . $sdkOrg->getOrg()->get_name() . " organization\n");
      }
      # echo "Found " . count($vdcRefs) . " vDCs:\n";
    }
    else {
      $vdcRefs = $sdkOrg->getVdcRefs();
      if (0 == count($vdcRefs)) {
          exit("No vDCs found\n");
      }
      # echo "Found " . count($vdcRefs) . " vDCs:\n";
    }

    foreach ($vdcRefs as $vdcRef) {
      ## Iterate through vDCs ##
      $sdkVdc = $service->createSDKObj($vdcRef);
      echo "-* vDC: " . $sdkVdc->getVdc()->get_name() . "\n";

      // create admin vdc object
      $adminVdcRefs = $adminOrgObj->getAdminVdcRefs($sdkVdc->getVdc()->get_name());
      if(empty($adminVdcRefs)) {
          exit("No admin vdc with name " . $sdkVdc->getVdc()->get_name() . " is found.");
      }
      $adminVdcRef=$adminVdcRefs[0];
      $adminVdcObj=$service->createSDKObj($adminVdcRef->get_href());

      if(!is_null($vseArg) && $vseArg != '') {
        $edgeGatewayRefs = $adminVdcObj->getEdgeGatewayRefs($vseArg);
        if (0 == count($edgeGatewayRefs)) {
          echo "No vShield Edges called $vseArg in this vDC\n";
          continue;
        }
      }
      else {
        $edgeGatewayRefs = $adminVdcObj->getEdgeGatewayRefs();
        if (0 == count($edgeGatewayRefs)) {
          echo "No vShield Edges found in this vDC\n";
          continue;
        }
      }

      foreach ($edgeGatewayRefs as $edgeGatewayRef) {
        echo "--* vSE: " . $edgeGatewayRef->get_name() . "\n";

        $edgeGatewayObj = $service->createSDKObj($edgeGatewayRef->get_href());
        $edgeGatewayNetworkServices = $edgeGatewayObj->getEdgeGateway()->getConfiguration()->getEdgeGatewayServiceConfiguration()->getNetworkService();

        foreach($edgeGatewayNetworkServices as $index => $edgeGatewayNetworkService) {
          if(get_class($edgeGatewayNetworkService) === "VMware_VCloud_API_FirewallServiceType") {
            # EdgeGateway getConfiguration getEdgeGatewayServiceConfiguration, VMware_VCloud_API_FirewallServiceType component:
            echo "---* Firewall rules\n";

            if($edgeGatewayNetworkService->getIsEnabled() != 1) {
              error_log("Warning: " . $edgeGatewayRef->get_name() . " is not enabled. This vSE will be ignored.\n");
              continue;
            }

            $fwSvcInfo  = $edgeGatewayNetworkService->getDefaultAction() . $colSep;
            $fwSvcInfo .= $edgeGatewayNetworkService->getLogDefaultAction() . $colSep;
            $fwSvcInfo .= $edgeGatewayNetworkService->getIsEnabled();

            $firewallRules=$edgeGatewayNetworkService->getFirewallRule();

            foreach($firewallRules as $firewallRule) {
              if(ruleMatch($firewallRule, $fromip, $fromport, $proto, $toip, $toport)) {
                print($sdkOrg->getOrg()->get_name() . $colSep . $sdkVdc->getVdc()->get_name() . $colSep . $edgeGatewayRef->get_name() . $colSep . $fwSvcInfo . $colSep . firewallRule2CsvRow($firewallRule) . "\n");
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


function usage() {
    echo "Usage:\n\n";
    echo "  [Description]\n";
    echo "     Gets matching firewall rules on the vShield Edges of your organizations.\n";
    echo "\n";
    echo "  [Usage]\n";
    echo "     # php getvsefwrules.php -s <server> -u <username> -p <password> -v <sdkversion> \\ \n";
    echo "                  -f <ip> -g <port> -h <proto> -i <ip> -j <port> \\ \n";
    echo "                  (-o <OrgName) (-d <vdcName) (-e <vShieldEdgeName) \\ \n";
    echo "\n";
    echo "     -s|--server <IP|hostname>        [req] IP or hostname of the vCloud Director.\n";
    echo "     -u|--user <username>             [req] User name in the form user@organization\n";
    echo "                                           for the vCloud Director.\n";
    echo "     -p|--pswd <password>             [req] Password for user.\n";
    echo "     -v|--sdkver <sdkversion>         [req] SDK Version e.g. 1.5, 5.1 and 5.5.\n";
    echo "     -f|--fromip                      [req] source IP addres\n";
    echo "     -g|--fromport                    [req] source port\n";
    echo "     -h|--proto                       [req] source proto ('T' for TCP, 'U' for UDP, 'I' for ICMP)\n";
    echo "     -i|--toip                        [req] destination IP addres\n";
    echo "     -j|--toport                      [req] destination Port\n";
    echo "     -o|--org                         [opt] Organization\n";
    echo "     -d|--vdc                         [opt] Virtual Data Center name\n";
    echo "     -e|--vse                         [opt] vShield Edge Gateway name\n";
    echo "\n";
    echo "  You can set the security parameters like server, user and pswd in 'config.php' file\n";
    echo "\n";
    echo "  [Examples]\n";
    echo "     # php getvsefwrules.php --server 127.0.0.1 --user admin@MyOrg --pswd mypassword --sdkver 5.5 --fromip 1.2.3.4 --fromport 8080 --proto T --toip 192.168.100.10 --toport 80 -o MyOrg -d MyVdcName -e MyVShieldName\n\n";
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
}

/**
 * Tells if an integer is contained in a rang
 *
 * @param rangStr range in format "m-n"
 * @param aInt the integer to test
 * @return true if it matches, false if doesn't match
 */
function intIsContained($rangeStr, $aInt) {
  $m=array();
  if(preg_match('/^(\d+)\-(\d+)$/',$rangeStr,$m)) {
    if(is_null($m) || count($m) != 3) {
      return false;
    }
    $min=$m[1];
    $max=$m[2];
    if(!$min > -1 || !$max > -1) {
      return false;
    }
    if($min <= $aInt && $aInt <= $max) {
      return true;
    }
  }
  return false;
}

/**
 * It shows if the rule does match.
 *
 * @return true if it matches, false if doesn't match
 */
function ruleMatch($r, $fromip, $fromport, $proto, $toip, $toport) {
# if($fromip !== $r->getSourceIp()) 
  if(!isNetContained($r->getSourceIp(), $fromip)) {
#echo "break by source ip\n";
    return false;
  }

  if(!($r->getSourcePort() == -1 && $r->getSourcePortRange() == "Any")) {
    if($fromport != $r->getSourcePort() && ! intIsContained($r->getSourcePortRange(), $fromport)) {
#echo "break by source port: fromport=[$fromport] , getSourcePort=[" . $r->getSourcePort() . "] i getSourcePortRange=[" . $r->getSourcePortRange() . "]\n";
      return false;
    }

    if($r->getSourcePort() != -1) {
      if($fromport != $r->getSourcePort()) {
        return false;
      }
    }
    else {
      if(! intIsContained($r->getSourcePortRange(), $fromport)) {
#echo "break by source port: fromport=[$fromport] , getSourcePort=[" . $r->getSourcePort() . "] i getSourcePortRange=[" . $r->getSourcePortRange() . "]\n";
        return false;
      }
    }
  }

  // A (any), I (ICMP), T (TCP), U (UDP), TU (TCP|UDP), UT (UDP|TCP)
  if($proto != "A") {
    if($proto == "I" && $r->getProtocols()->getIcmp() != 1) {
#echo "break by protocol no match icmp\n";
      return false;
    }
    else if(($proto == "T" || $proto == "TU" || $proto == "UT") && ($r->getProtocols()->getTcp() != 1)) {
#echo "break by protocol no match TCP\n";
      return false;
    }
    else if(($proto == "U" || $proto == "TU" || $proto == "UT") && ($r->getProtocols()->getUdp() != 1)) {
#echo "break by protocol no match UDP\n";
      return false;
    }
    else if(($proto == "TU" || $proto == "UT") && ($r->getProtocols()->getTcp() != 1 && $r->getProtocols()->getUdp() != 1)) {
#echo "break by protocol no match TCP|UDP\n";
      return false;
    }
  }

  if(!isNetContained($r->getDestinationIp(), $toip)) {
#echo "break by destination ip\n";
    return false;
  }



  if(!($r->getPort() == -1 && $r->getDestinationPortRange() == "Any")) {
    if($toport != $r->getPort() && ! intIsContained($r->getDestinationPortRange(), $toport)) {
#echo "break by destination port: toport=[$toport] , getPort=[" . $r->getPort() . "] i getDestinationPortRange=[" . $r->getDestinationPortRange() . "]\n";
      return false;
    }

    if($r->getPort() != -1) {
      if($toport != $r->getPort()) {
        return false;
      }
    }
    else {
      if(! intIsContained($r->getDestinationPortRange(), $toport)) {
#echo "break by destination port: toport=[$toport] , getPort=[" . $r->getPort() . "] i getDestinationPortRange=[" . $r->getDestinationPortRange() . "]\n";
        return false;
      }
    }
  }

  return true;
}

/**
 * Shows if an IP is contained in a subnet
 * <p>
 * It just supports IPv4.
 * @param $range The IPv4 subnet (CIDR) to test if contains certain IPv4
 * @param $ipAddr The IPv4 address (CIDR) to test if is contained by 
 * @return true if it is contained, false if isn't
 */
function isNetContained($range, $ipAddr) {
  if(!is_null($range) && $range != '' && $range == $ipAddr) {
    # trivial
    return true;
  }

## GENERA UN ERROR DE 
## PHP Notice:  Undefined offset: 1
## 
##   list($subnet, $mask) = explode('/', $range);
##   if((ip2long($ipAddr) & ~((1 << (32 - $mask)) - 1) ) == ip2long($subnet)) { 
##     return true;
##   }
##   return false;

  # from https://gist.github.com/tott/7684443
  if ( strpos( $range, '/' ) == false ) {
    $range .= '/32';
  }
  // $range is in IP/CIDR format eg 127.0.0.1/24
  list( $range, $netmask ) = explode( '/', $range, 2 );
  $range_decimal = ip2long( $range );
  $ip_decimal = ip2long( $ipAddr );
  $wildcard_decimal = pow( 2, ( 32 - $netmask ) ) - 1;
  $netmask_decimal = ~ $wildcard_decimal;
  return ( ( $ip_decimal & $netmask_decimal ) == ( $range_decimal & $netmask_decimal ) );
}

/**
 * Returns the CSV row representing a firewall rule
 *
 * @param The Firewall rule
 * @return The string with the line to dump
 */
function firewallRule2CsvRow($r) {
  global $colSep;

# if($r->getIsEnabled() != 1) {
# }

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
  # TO_DO : in my tests it generates a null VMware_VCloud_API_VmSelectionType ... ->get_name() ...
  $ret .= $r->getSourceVm() . $colSep;

  $ret .= $r->getDestinationIp() . $colSep;
  $ret .= $r->getPort() . $colSep;
  $ret .= $r->getDestinationPortRange() . $colSep;
  # TO_DO : in my tests it generates a null VMware_VCloud_API_VmSelectionType ... ->get_name() ...
  $ret .= $r->getDestinationVm() . $colSep;

  # TO_DO : in my tests it generates a null...
  $ret .= $r->getDirection() . $colSep;
  $ret .= $r->getEnableLogging() . $colSep;
  return $ret;
}

?>
