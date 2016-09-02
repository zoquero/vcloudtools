<?php
/**
 * Nagios plugin to check Storage Profiles usage on vCloud Director
 * <p>
 * Requires:<ul>
 *              <li> PHP version 5+
 *              <li> vCloud SDK for PHP for vCloud Suite 5.5
 *             ( https://developercenter.vmware.com/web/sdk/5.5.0/vcloud-php )
 * </ul>
 * <p>
 * Tested on Ubuntu 15.04 64b with PHP 5.6.4
 * Tested on Ubuntu 16.04 64b with PHP 7.0.8
 *
 * <p>
 * Warning: vcloudPHP-5.5.0/library/VMware/VCloud/VCloud.php adds two new lines
 *          at it's end, after ending the php tag, you should remove them
 *          to avoid those anoying blank lines.
 *
 * @author Angel Galindo Muñoz (zoquero at gmail dot com)
 * @version 1.0
 * @since 02/09/2016
 */

require_once dirname(__FILE__) . '/config.php';
require_once dirname(__FILE__) . '/classes.php';
require_once dirname(__FILE__) . '/graphlib.php';

// Get parameters from command line
$shorts  = "";
$shorts .= "s:";
$shorts .= "u:";
$shorts .= "p:";
$shorts .= "v:";
$shorts .= "e:";
$shorts .= "w:";
$shorts .= "c:";
$shorts .= "t:";

$longs  = array(
    "server:",    //-s|--server    [required]
    "user:",      //-u|--user      [required]
    "pswd:",      //-p|--pswd      [required]
    "sdkver:",    //-v|--sdkver    [required]
    "certpath:",  //-e|--certpath  [optional] local certificate path
    "warning:",   //-w|--warning   [required]
    "critical:",  //-c|--critical  [required]
    "org:",            //-o|--org            [optional]
    "vdc:",            //-d|--vdc            [optional]
    "storprof:",       //-t|--storprof       [optional]
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

    case "w":
        $warning = $opts['w'];
        break;
    case "warning":
        $warning = $opts['warning'];
        break;

    case "c":
        $critical = $opts['c'];
        break;
    case "critical":
        $critical = $opts['critical'];
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

    case "t":
        $storprofArg = $opts['t'];
        break;
    case "storprof":
        $storprofArg = $opts['storprof'];
        break;
}

// parameters validation
if (!isset($server) || !isset($user) || !isset($pswd) || !isset($sdkversion) || !isset($warning) || !isset($critical)) {
    echo "Error: missing required parameters\n";
    usage();
    exit(3);
}

if (isset($storprofArg) && (!( isset($orgArg) || isset($vdcArg) ))) {
    echo "Error: missing required parameters (storprof requieres org and vdc)\n";
    usage();
    exit(3);
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
#       echo "\n\nValidation of certificates is successful.\n\n";
        $flag=true;
    }
    else {
#       echo "\n\nCertification Failed.\n";
        $flag=false;
    }
}

if ($flag==true) {
  $storProfsArray=array();
  $criticalRaised = false;
  $warningRaised  = false;

#  if (!isset($certPath)) {
#    echo "Ignoring the Certificate Validation --Fake certificate - DO NOT DO THIS IN PRODUCTION.\n\n";
#  }
  // vCloud login
  $service = VMware_VCloud_SDK_Service::getService();
  $service->login($server, array('username'=>$user, 'password'=>$pswd), $httpConfig, $sdkversion);

  // create sdk admin object
  $sdkAdminObj = $service->createSDKAdminObj();

  // create an SDK Org object
  $orgRefs = $service->getOrgRefs();
  if (0 == count($orgRefs)) {
      unknown("No organizations found");
  }
  # echo "Found " . count($orgRefs) . " organizations:\n";
  foreach ($orgRefs as $orgRef) {
    ## Iterate through organizations ##
    $sdkOrg = $service->createSDKObj($orgRef);
    # echo "* org: " . $sdkOrg->getOrg()->get_name() . "\n";

    if(isset($orgArg) && $sdkOrg->getOrg()->get_name() != $orgArg) {
      continue;
    }

    $sdkOrgOrg=$sdkOrg->getOrg();
    $org=org2obj($sdkOrgOrg);

    $vdcRefs = $sdkOrg->getVdcRefs();
    if (0 == count($vdcRefs)) {
        unknown("No vDCs found on org " . $sdkOrg->getOrg()->get_name());
    }

    foreach ($vdcRefs as $vdcRef) {
      ## Iterate through vDCs ##
      $sdkVdc = $service->createSDKObj($vdcRef);
      # echo "-* vDC: " . $sdkVdc->getVdc()->get_name() . "\n";

      if(isset($vdcArg) && $sdkVdc->getVdc()->get_name() != $vdcArg) {
        continue;
      }

      $sdkVdcVdc=$sdkVdc->getVdc();
      $vdc=vdc2obj($org, $sdkVdcVdc, $sdkVdc->getVdc()->get_href());

      # Storage Profiles
      $param = new VMware_VCloud_SDK_Query_Params();
      $param->setPageSize(128);
      $param->setFilter('vdc==' . $sdkVdc->getVdc()->get_href());
      $query = $service->getQueryService();
      $storProfsQueryResults = $query->queryRecords(VMware_VCloud_SDK_Query_Types::ORG_VDC_STORAGE_PROFILE, $param);
      if (!empty($storProfsQueryResults)) {
        $storProfsQueryResults = $storProfsQueryResults->getRecord();
        // iterate through the org vdc resource pool relation result.
        foreach ($storProfsQueryResults as $aStorProfQueryResult) {
          # echo "--* storProf: " . $aStorProfQueryResult->get_name() . "" . "\n";
          if(isset($storprofArg) && $aStorProfQueryResult->get_name() != $storprofArg) {
            continue;
          }
          $storProf=storProf2obj($vdc, $aStorProfQueryResult);
          array_push($storProfsArray, $storProf);
        }
      }
    }
  }


  $perfDataArray       = array();
  $spWithWarningArray  = array();
  $spWithCriticalArray = array();
  foreach($storProfsArray as $storProf) {
    if(! $criticalRaised and $storProf->usedPercent > $critical) {
      $criticalRaised = true;
      array_push($spWithCriticalArray, $storProf->name . " (org=" . $storProf->org->name . ", vdc=" . $storProf->vdc->name . ")");
    }
    else if(! $warningRaised and $storProf->usedPercent > $warning) {
      $warningRaised  = true;
      array_push($spWithWarningArray,  $storProf->name . " (org=" . $storProf->org->name . ", vdc=" . $storProf->vdc->name . ")");
    }
    $freePercent=100-$storProf->usedPercent;
    $freePercentRounded = number_format((float)$freePercent, 1, '.', '');
    array_push($perfDataArray, $storProf->name . "_freepc=" . $freePercentRounded);
    array_push($perfDataArray, $storProf->name . "_usedmb=" . $storProf->usedMB);
  }

  $perfData = join(" ", $perfDataArray);
  $spWithCriticalStr = join(", ", $spWithCriticalArray);
  $spWithWarningStr  = join(", ", $spWithWarningArray);

  $critStrAppend = '';
  $warnStrAppend = '';
  if($criticalRaised) {
    $retVal=2;
    $retStrTag="Error";
    $critStrAppend=". StorProfs crit = $spWithCriticalStr";
  }
  else if($warningRaised) {
    $retVal=1;
    $retStrTag="Warning";
    $warnStrAppend=". StorProfs warn = $spWithWarningStr";
  }
  else {
    $retVal=0;
    $retStrTag="OK";
  }

  $retStr = $retStrTag . $critStrAppend . $warnStrAppend . "|" . $perfData;
  echo "$retStr\n";
  exit($retVal);
}
else {
  unknown("Login Failed due to certification mismatch");
}
unknown("Bug");


function unknown($msg) {
  echo "Unknown: $msg\n";
  exit(3);
}

function usage() {
    echo "Usage:\n\n";
    echo "  [Description]\n";
    echo "     * Nagios plugin to check Storage Profiles usage on vCloud Director" . PHP_EOL;
    echo PHP_EOL;
    echo "  [Usage]" . PHP_EOL;
    echo "     # php check_vcloudstorprof.php --server <server> --user <username> --pswd <password> --sdkver <sdkversion> --warning warnthreshold --critical critthreshold (--org <orgname> --vdc <vdcname> --storprof <storprofname>)" . PHP_EOL;
    echo "     # php check_vcloudstorprof.php -s <server> -u <username> -p <password> -v <sdkversion> -w warnthreshold -c critthreshold (-o <orgname> -d <vdcname> -t <storprofname>)" . PHP_EOL;
    echo PHP_EOL;
    echo "     -s|--server <IP|hostname>        [req] IP or hostname of the vCloud Director."    . PHP_EOL;
    echo "     -u|--user <username>             [req] User name in the form user@organization"   . PHP_EOL;
    echo "                                           for the vCloud Director."                   . PHP_EOL;
    echo "     -p|--pswd <password>             [req] Password for user."                        . PHP_EOL;
    echo "     -v|--sdkver <sdkversion>         [req] SDK Version e.g. 1.5, 5.1 and 5.5."        . PHP_EOL;
    echo "     -w|--warning <warnthreshold>     [req] Warning % threshold for stor prof usage"   . PHP_EOL;
    echo "     -c|--critical <critthreshold>    [req] Critical % threshold for stor prof usage"  . PHP_EOL;
    echo PHP_EOL;
    echo "  [Options]" . PHP_EOL;
    echo "     -e|--certpath <certificatepath>  [opt] Local certificate's full path."            . PHP_EOL;
    echo "     -o|--org <orgname>               [opt] Organization name"                         . PHP_EOL;
    echo "     -d|--vdc <vdcname>               [opt] vDC name."                                 . PHP_EOL;
    echo "     -t|--storprof <storprofname>     [opt] Storage Profile name."                     . PHP_EOL;
    echo PHP_EOL;
    echo "  You can set the security parameters like server, user and pswd in 'config.php' file" . PHP_EOL;
    echo PHP_EOL;
    echo "  [Examples]" . PHP_EOL;
    echo "     # php check_vcloudstorpro.php --server 127.0.0.1 --user admin@MyOrg --pswd mypassword --sdkver 5.5 --warning 90 --critical 95" . PHP_EOL;
    echo "     # php check_vcloudstorpro.php --server 127.0.0.1 --user admin@MyOrg --pswd mypassword --sdkver 5.5 --warning 90 --critical 95 -o MyOrg -d MyVdc -t MyStorProf" . PHP_EOL;

}

function storProf2CsvRow($r, $orgName) {
  global $colSep;
  global $spFp;
  $id = $orgName . "___"           . $r->get_vdcName() . "___" . $r->get_name();

  $ret  = $orgName                 . $colSep;
  $ret .= $r->get_vdcName()        . $colSep;
  $ret .= $r->get_name()           . $colSep;
  $ret .= $id                      . $colSep;
  $ret .= $r->get_isEnabled()      . $colSep;
  $ret .= $r->get_storageUsedMB()  . $colSep;
  $ret .= $r->get_storageLimitMB() . $colSep;
  $ret .= $r->get_isDefaultStorageProfile() . $colSep;
  $ret .= $r->get_isVdcBusy()      . $colSep;

  return $ret;
}

?>
