# vcloudtools

**Tools for working with vCloud Director**

These tools give you a **better visibility and manageability of your vCloud Infraestructure**. They fill some gaps, some functionalities that can't be found on the vCloud Director web UI or that are not agile enough:

* to **generate a graphviz diagram** representing your organizations, Virtual Datacenters, vShield Edges, networks, Storage Profiles, vApps and VMs)
* to look for vShield Edge firewall rules when **troubleshooting communications** on your vDCs
* to **dump your infraestructure to CSV or XML** (it can help you to backup your configuration, to plug your cloud to your CMDB (manual integration, if your CMDB doesn't support vCloud API), to account our usage, a step towards monitoring), ...

![Sample of a generated diagram](https://github.com/zoquero/vcloudtools/raw/master/diagramsamples/vcloud.thumbnail.png "Sample of a generated diagram")

Tested on:

* Ubuntu 15.04 64b with PHP 5.6.4 connecting to vCloud Director 5.5
* Ubuntu 16.04 64b with PHP 7.0   connecting to vCloud Director 5.5

zoquero at gmail dot com

9 August 2016

.

## Requirements

It **requires**:

* [VMware vCloud SDK for PHP for vCloud Suite 5.5](https://developercenter.vmware.com/web/sdk/5.5.0/vcloud-php).
* PHP 5 or PHP 7

## Hints for VMware vCloud SDK for PHP

Just download it an unzip it somewhere like "/opt/lib/' and don't forget to add it's folder to your **```include_path```** setting in your php.ini, like this:
```
include_path = ".:/usr/share/php:/opt/lib/vcloudPHP-5.5.0/library/"
```

You'll also need some libs:

* On Ubuntu 15.04 you'll need: 
```
sudo apt-get install php-http-request2 php-net-url2
```

* On Ubuntu 16.04 LTS you'll need: 
```
sudo apt-get install php-http-request2 php-net-url2 php-mbstring
```

## Scripts

### graphcloud.php
**Generates a GraphViz diagram** representing your vCloud Infraestructure.

#### Usage
```
  [Description]
     Generates a GraphViz diagram representing your vCloud Infraestructure.

  [Usage]
     # php graphvcloud.php --server <server> --user <username> --pswd <password> --sdkver <sdkversion> --output <file> (--title "<title>")
     # php graphvcloud.php -s <server> -u <username> -p <password> -v <sdkversion> -o <file> (-t "<title>")

     -s|--server <IP|hostname>        [req] IP or hostname of the vCloud Director.
     -u|--user <username>             [req] User name in the form user@organization
                                           for the vCloud Director.
     -p|--pswd <password>             [req] Password for user.
     -v|--sdkver <sdkversion>         [req] SDK Version e.g. 1.5, 5.1 and 5.5.
     -o|--output <file>               [req] Folder where CSVs will be created.
     -t|--title <file>                [opt] Title for the graph.

  [Options]
     -e|--certpath <certificatepath>  [opt] Local certificate's full path.

  You can set the security parameters like server, user and pswd in 'config.php' file

  [Examples]
     # php graphvcloud.php --server 127.0.0.1 --user admin@MyOrg --pswd mypassword --sdkver 5.5 --output /tmp/vc.dot
```

### graphcloud.demo.php
Generates a GraphViz diagram representing a demo of a vCloud Infraestructure. Usefull for generating arbitrary diagrams, for design.

#### Usage
```
  [Description]
     Generates a GraphViz diagram representing a demo of a vCloud Infraestructure.

  [Usage]
     # php graphvcloud.demo.php --output <file> (--title "<title>")
     # php graphvcloud.demo.php -o <file> (-t "<title>")

     -o|--output <file>               [req] Folder where CSVs will be craeted.
     -t|--title <file>                [opt] Title for the graph.

  [Examples]
     # php graphvcloud.demo.php --output /tmp/vc.dot
```

### check_vcloudstorprof.php

**Nagios plugin** to check **Storage Profiles usage** on vCloud Director

#### Usage
```
  [Description]
     * Nagios plugin to check Storage Profiles usage on vCloud Director

  [Usage]
     # php check_vcloudstorprof.php --server <server> --user <username> --pswd <password> --sdkver <sdkversion> --warning warnthreshold --critical critthreshold (--org <orgname> --vdc <vdcname> --storprof <storprofname>)
     # php check_vcloudstorprof.php -s <server> -u <username> -p <password> -v <sdkversion> -w warnthreshold -c critthreshold (-o <orgname> -d <vdcname> -t <storprofname>)

     -s|--server <IP|hostname>        [req] IP or hostname of the vCloud Director.
     -u|--user <username>             [req] User name in the form user@organization
                                           for the vCloud Director.
     -p|--pswd <password>             [req] Password for user.
     -v|--sdkver <sdkversion>         [req] SDK Version e.g. 1.5, 5.1 and 5.5.
     -w|--warning <warnthreshold>     [req] Warning % threshold for stor prof usage
     -c|--critical <critthreshold>    [req] Critical % threshold for stor prof usage

  [Options]
     -e|--certpath <certificatepath>  [opt] Local certificate's full path.
     -o|--org <orgname>               [opt] Organization name
     -d|--vdc <vdcname>               [opt] vDC name.
     -t|--storprof <storprofname>     [opt] Storage Profile name.

  You can set the security parameters like server, user and pswd in 'config.php' file

  [Examples]
     # php check_vcloudstorpro.php --server 127.0.0.1 --user admin@MyOrg --pswd mypassword --sdkver 5.5 --warning 90 --critical 95
     # php check_vcloudstorpro.php --server 127.0.0.1 --user admin@MyOrg --pswd mypassword --sdkver 5.5 --warning 90 --critical 95 -o MyOrg -d MyVdc -t MyStorProf
```

### exportvcloud.php
**Dumps** to CSV or XML all the entities (vApps, VMs,  vShields, vDCs and organizations) that you have access to.

#### Usage
```
  [Description]
     Dumps to CSV or XML all the entities (vApps, VMs,  vShields, vDCs and organizations) that you have access to.

  [Usage]
     # php vc2csv.php --server <server> --user <username> --pswd <password> --sdkver <sdkversion> --dir <dir> --format <format>
     # php vc2csv.php -s <server> -u <username> -p <password> -v <sdkversion> -o <dir> -f <format>

     -s|--server <IP|hostname>        [req] IP or hostname of the vCloud Director.
     -u|--user <username>             [req] User name in the form user@organization
                                           for the vCloud Director.
     -p|--pswd <password>             [req] Password for user.
     -v|--sdkver <sdkversion>         [req] SDK Version e.g. 1.5, 5.1 and 5.5.
     -o|--dir <directory>             [req] Folder where CSVs will be craeted.
     -f|--format (csv|xml)            [req] Format for output.

  [Options]
     -e|--certpath <certificatepath>  [opt] Local certificate's full path.

  You can set the security parameters like server, user and pswd in 'config.php' file

  [Examples]
     # php exportvcloud.php --server 127.0.0.1 --user admin@MyOrg --pswd mypassword --sdkver 5.5 --dir /tmp/vc --format xml
```


## getvsefwrules.php
**Gets matching firewall rules** on the vShield Edges of your organizations.

### Usage
```
  [Description]
     Gets matching firewall rules on the vShield Edges of your organizations.

  [Usage]
     # php getvsefwrules.php -s <server> -u <username> -p <password> -v <sdkversion> \ 
                  (-f <ip>) (-g <port>) (-h <proto>) (-i <ip>) (-j <port>) \ 
                  (-o <OrgName) (-d <vdcName) (-e <vShieldEdgeName) \ 

     -s|--server <IP|hostname>        [opt] IP or hostname of the vCloud Director.
     -u|--user <username>             [opt] User name in the form user@organization
                                           for the vCloud Director.
     -p|--pswd <password>             [opt] Password for user.
     -v|--sdkver <sdkversion>         [opt] SDK Version e.g. 1.5, 5.1 and 5.5.
     -f|--fromip                      [opt] source IP addres (defaults to 'any')
     -g|--fromport                    [opt] source port (defaults to 'any')
     -h|--proto                       [opt] source proto ('T' for TCP, 'U' for UDP, 'I' for ICMP, 'A' or '*' for any) (defaults to 'any')
     -i|--toip                        [opt] destination IP addres (defaults to 'any')
     -j|--toport                      [opt] destination Port (defaults to 'any')
     -o|--org                         [opt] Organization
     -d|--vdc                         [opt] Virtual Data Center name
     -e|--vse                         [opt] vShield Edge Gateway name

  You can set the security parameters like server, user and pswd in 'config.php' file, this is why they're optional parameters.

  [Examples]
     # php getvsefwrules.php --server 127.0.0.1 --user admin@MyOrg --pswd mypassword --sdkver 5.5 --fromip 1.2.3.4 --fromport 8080 --proto T --toip 192.168.100.10 --toport 80 -o MyOrg -d MyVdcName -e MyVShieldName
```

## config.php
**Configuration file**, just to avoid using command line parameters that appear on console, history and 'ps'

