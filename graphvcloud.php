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
 *   </ul>
 *
 * @author Angel Galindo Muñoz (zoquero at gmail dot com)
 * @version 1.1
 * @since 20/08/2016
 * @see http://graphviz.org/
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
$shorts .= "o:";
$shorts .= "t:";

$longs  = array(
    "server:",    //-s|--server    [required]
    "user:",      //-u|--user      [required]
    "pswd:",      //-p|--pswd      [required]
    "sdkver:",    //-v|--sdkver    [required]
    "certpath:",  //-e|--certpath  [optional] local certificate path
    "output:",    //-o|--output    [required]
    "title:",     //-t|--title     [optional]
);

$opts = getopt($shorts, $longs);

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

    case "t":
        $title = $opts['t'];
        break;
    case "title":
        $title = $opts['title'];
        break;
}

// parameters validation
if(!isset($server) || !isset($user) || !isset($pswd) || !isset($sdkversion) || !isset($oFile)) {
  echo "Error: missing required parameters" . PHP_EOL;
  usage();
  exit(1);
}

if(!isset($title)) {
  $title="Graph for $server in " . date("Y/m/d h:i:s a") ;
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

  graph($orgsArray, $vdcsArray, $vsesArray, $vseNetsArray, $vappsArray, $vmsArray, $storProfsArray, $title);
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
    echo "     -s|--server <IP|hostname>        [req] IP or hostname of the vCloud Director."  . PHP_EOL;
    echo "     -u|--user <username>             [req] User name in the form user@organization" . PHP_EOL;
    echo "                                           for the vCloud Director."                 . PHP_EOL;
    echo "     -p|--pswd <password>             [req] Password for user."                      . PHP_EOL;
    echo "     -v|--sdkver <sdkversion>         [req] SDK Version e.g. 1.5, 5.1 and 5.5."      . PHP_EOL;
    echo "     -o|--output <file>               [req] Folder where CSVs will be created."      . PHP_EOL;
    echo "     -t|--title <file>                [opt] Title for the graph."                    . PHP_EOL;
    echo PHP_EOL;
    echo "  [Options]" . PHP_EOL;
    echo "     -e|--certpath <certificatepath>  [opt] Local certificate's full path." . PHP_EOL;
    echo PHP_EOL;
    echo "  You can set the security parameters like server, user and pswd in 'config.php' file" . PHP_EOL;
    echo PHP_EOL;
    echo "  [Examples]" . PHP_EOL;
    echo "     # php graphvcloud.php --server 127.0.0.1 --user admin@MyOrg --pswd mypassword --sdkver 5.5 --output /tmp/vc.dot" . PHP_EOL;
}

?>
