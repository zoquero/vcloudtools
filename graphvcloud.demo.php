<?php
/**
 * Test script to generate a graphviz diagram representing arbitrary vCloud entities.
 * <p>
 * Test script to generate a graphviz diagram representing arbitrary vCloud entities
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
 * </ul>
 * <p>
 * Tested on
 * <ul>
 *   <li>Ubuntu 15.04 64b with PHP 5.6.4
 *   <li>Ubuntu 16.04 64b with PHP 7.0.8
 * </ul>
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
$shorts .= "o:";
$shorts .= "t:";
$shorts .= "r:";

$longs  = array(
    "output:",    //-o|--output       [required]
    "title:",     //-t|--title        [optional]
    "part:",      //-r|--part         [optional]
);

$opts = getopt($shorts, $longs);
$parts = array();

// loop through command arguments
foreach (array_keys($opts) as $opt) switch ($opt) {
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

  case "r":
    $param = $opts['r'];
    if(is_array($param)) {
      foreach($param as $comp) {
        array_push($parts, $comp);
      }
    }
    else {
      array_push($parts, $param);
    }
    break;
  case "part":
    $param = $opts['part'];
    if(is_array($param)) {
      foreach($param as $comp) {
        array_push($parts, $comp);
      }
    }
    else {
      array_push($parts, $param);
    }
    break;
}

// parameters validation
if (!isset($oFile)) {
  echo "Error: missing required parameters" . PHP_EOL;
  usage();
  exit(1);
}

if (file_exists($oFile)) {
  echo "Error: $oFile already exists" . PHP_EOL;
  usage();
  exit(1);
}

if (!isset($title)) {
  $title = "Sample graph of a vCloud Infrastructure";
}

$zParts = /* Filter */ array();
foreach($parts as $part) {
  if(! preg_match("/(.+)=(.+)/", $part, $z)) {
    echo "Parts must be in 'partType=partName' format\n";
    usage();
    exit(1);
  }
  if($z[1] != Org::$classDisplayName             &&
     $z[1] != Vdc::$classDisplayName             &&
     $z[1] != Vse::$classDisplayName             &&
     $z[1] != VseNetwork::$classDisplayName      &&
     $z[1] != IsolatedNetwork::$classDisplayName &&
     $z[1] != Vapp::$classDisplayName            &&
     $z[1] != VM::$classDisplayName              &&
     $z[1] != StorageProfile ::$classDisplayName) {
    echo "Parts must be in 'partType=partName' format. " . $z[1] . " is not a valid partType.\n";
    $cdps = array( Org::$classDisplayName, Vdc::$classDisplayName, Vse::$classDisplayName, VseNetwork::$classDisplayName, IsolatedNetwork::$classDisplayName, Vapp::$classDisplayName, VM::$classDisplayName, StorageProfile ::$classDisplayName);
    echo "Supported partTypes: " . join (", ", $cdps) . PHP_EOL;

    usage();
    exit(1);
  }

  $aFilter = new Filter($z[1], $z[2]);
  array_push($zParts, $aFilter);
}

# Initialization of arrays of objects:
$orgsArray      = array();
$vdcsArray      = array();
$vsesArray      = array();
$vseNetsArray   = array();
$vappsArray     = array();
$vmsArray       = array();
$storProfsArray = array();

$org1    = new Org("Org1", 1);
$org2    = new Org("Org2", 1);
array_push($orgsArray,      $org1);
array_push($orgsArray,      $org2);

$vdc1    = new Vdc("Vdc1", "Vdc1", $org1, 'https://does.not.matter/vcloud/vdc1');
$vdc2    = new Vdc("Vdc2", "Vdc2", $org1, 'https://does.not.matter/vcloud/vdc2');
$vdc3    = new Vdc("Calculus", "Calculus", $org2, 'https://does.not.matter/vcloud/vdc3');
array_push($vdcsArray,      $vdc1);
array_push($vdcsArray,      $vdc2);
array_push($vdcsArray,      $vdc3);

$vse1 = new Vse("Vse1", "Vse1", 1, $org1, $vdc1);
$vse2 = new Vse("Vse2", "Vse2", 1, $org1, $vdc2);
$vse3 = new Vse("Vse3", "Vse3", 1, $org2, $vdc3);
$vse4 = new Vse("Vse4", "Vse4", 1, $org2, $vdc3);
array_push($vsesArray,      $vse1);
array_push($vsesArray,      $vse2);
array_push($vsesArray,      $vse3);
array_push($vsesArray,      $vse4);

$vseNet1 = new VseNetwork("Net1", $vse1);
$vseNet2 = new VseNetwork("Net2", $vse1);
$vseNet3 = new VseNetwork("Net3", $vse1);
$vseNet4 = new VseNetwork("Mail Net"   , $vse2);
$vseNet5 = new VseNetwork("CalcNet"    , $vse3);
$vseNet6 = new VseNetwork("CalcManNet" , $vse4);
array_push($vseNetsArray,   $vseNet1);
array_push($vseNetsArray,   $vseNet2);
array_push($vseNetsArray,   $vseNet3);
array_push($vseNetsArray,   $vseNet4);
array_push($vseNetsArray,   $vseNet5);
array_push($vseNetsArray,   $vseNet6);

$storProf1 = new StorageProfile("storProf1", "storProf1", true, 1, "TB", $vdc1);
$storProf2 = new StorageProfile("storProf2", "storProf2", true, 1, "TB", $vdc1);
$storProf3 = new StorageProfile("storProf3", "storProf3", true, 1, "TB", $vdc2);
$storProf4 = new StorageProfile("calcStorProf", "calcStorProf", true, 1, "TB", $vdc3);
array_push($storProfsArray, $storProf1);
array_push($storProfsArray, $storProf2);
array_push($storProfsArray, $storProf3);
array_push($storProfsArray, $storProf4);

$vApp1   = new Vapp("vApp1_FE", "vApp1_FE", 1, array($vseNet1->name), $vdc1);
$vApp2   = new Vapp("vApp2_BE", "vApp2_BE", 1, array($vseNet1->name, $vseNet2->name), $vdc1);
$vApp3   = new Vapp("Monitoring", "monitoring", 1, array($vseNet3->name), $vdc1);
$vApp4   = new Vapp("Mail", "Mail", 1, array($vseNet4->name), $vdc2);
$vApp5   = new Vapp("Calc vApp", "Calc vApp", 1, array($vseNet5->name), $vdc3);
$vApp6   = new Vapp("Calc Manag vApp", "Calc Manag vApp", 1, array($vseNet6->name), $vdc3);
array_push($vappsArray,     $vApp1);
array_push($vappsArray,     $vApp2);
array_push($vappsArray,     $vApp3);
array_push($vappsArray,     $vApp4);
array_push($vappsArray,     $vApp5);
array_push($vappsArray,     $vApp6);

$VM1   = new VM("vm1", "vm1", 1, array($vseNet1->name), $storProf1->name, $vApp1);
$VM2   = new VM("vm2", "vm2", 1, array($vseNet1->name), $storProf1->name, $vApp1);
$VM3   = new VM("vm3", "vm3", 1, array($vseNet1->name), $storProf1->name, $vApp1);
$VM4   = new VM("vm4", "vm4", 1, array($vseNet1->name,$vseNet2->name), $storProf1->name, $vApp2);
$VM5   = new VM("vm5", "vm5", 1, array($vseNet1->name,$vseNet2->name), $storProf1->name, $vApp2);
$VM6   = new VM("monitor1", "monitor1", 1, array($vseNet3->name), $storProf2->name, $vApp3);
$VM7   = new VM("monitor2", "monitor2", 1, array($vseNet3->name), $storProf2->name, $vApp3);
$VM8   = new VM("Webmail",  "Webmail",  1, array($vseNet4->name), $storProf3->name, $vApp4);
$VM9   = new VM("MTA",      "MTA",      1, array($vseNet4->name), $storProf3->name, $vApp4);
$VM10  = new VM("Mailbox Server", "Mailbox Server", 1, array($vseNet4->name), $storProf3->name, $vApp4);
$VM11  = new VM("Grid 01", "Grid 01", 1, array($vseNet5->name), $storProf4->name, $vApp5);
$VM12  = new VM("Grid 02", "Grid 02", 1, array($vseNet5->name), $storProf4->name, $vApp5);
$VM13  = new VM("Grid 03", "Grid 03", 1, array($vseNet5->name), $storProf4->name, $vApp5);
$VM14  = new VM("Grid 04", "Grid 04", 1, array($vseNet5->name), $storProf4->name, $vApp5);
$VM15  = new VM("Calc Manager", "Calc Manager", 1, array($vseNet5->name, $vseNet6->name), $storProf4->name, $vApp6);
array_push($vmsArray,       $VM1);
array_push($vmsArray,       $VM2);
array_push($vmsArray,       $VM3);
array_push($vmsArray,       $VM4);
array_push($vmsArray,       $VM5);
array_push($vmsArray,       $VM6);
array_push($vmsArray,       $VM7);
array_push($vmsArray,       $VM8);
array_push($vmsArray,       $VM9);
array_push($vmsArray,       $VM10);
array_push($vmsArray,       $VM11);
array_push($vmsArray,       $VM12);
array_push($vmsArray,       $VM13);
array_push($vmsArray,       $VM14);
array_push($vmsArray,       $VM15);

if(count($parts) > 0) {
  filterParts($zParts, $orgsArray, $vdcsArray, $vsesArray, $vseNetsArray, $vappsArray, $vmsArray, $storProfsArray);
}
graph($orgsArray, $vdcsArray, $vsesArray, $vseNetsArray, $vappsArray, $vmsArray, $storProfsArray, $title);
echo PHP_EOL;
echo "Graph '$oFile' Generated successfully." . PHP_EOL;
echo "Now you can render it with graphviz ( http://www.graphviz.org/ ) this way:" . PHP_EOL;
echo "as a PNG:" . PHP_EOL;
echo "# dot -Tpng '$oFile' -o ./vcloud.png" . PHP_EOL;
echo "as a SVG:" . PHP_EOL;
echo "# dot -Tsvg '$oFile' -o ./vcloud.svg" . PHP_EOL;
exit(0);

function usage() {
    echo "Usage:" . PHP_EOL;
    echo "  [Description]" . PHP_EOL;
    echo "     Generates a GraphViz diagram representing a demo of a vCloud Infraestructure." . PHP_EOL;
    echo PHP_EOL;
    echo "  [Usage]" . PHP_EOL;
    echo "     # php graphvcloud.demo.php --output <file> (--title \"<title>\")"              . PHP_EOL;
    echo "     # php graphvcloud.demo.php -o <file> (-t \"<title>\")"                         . PHP_EOL;
    echo PHP_EOL;
    echo "     -o|--output <file>               [req] Folder where CSVs will be craeted."     . PHP_EOL;
    echo "     -t|--title <file>                [opt] Title for the graph."                   . PHP_EOL;
    echo PHP_EOL;
    echo "  [Examples]" . PHP_EOL;
    echo "     # php graphvcloud.demo.php --output /tmp/vc.dot" . PHP_EOL;

}

?>
