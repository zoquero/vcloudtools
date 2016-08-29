# vcloudtools

**Tools for working with vCloud Director**

These tools give you a **better visibility and manageability of your vCloud Infraestructure**. They fill some gaps, some functionalities that can't be found on the vCloud Director web UI or that are not agile enough:

* to **generate a graphviz diagram** representing your organizations, Virtual Datacenters, vShield Edges, networks, Storage Profiles, vApps and VMs)
* to look for vShield Edge firewall rules when **troubleshooting communications** on your vDCs
* to **dump your infraestructure to CSV or XML** (it can help you to backup your configuration, to plug your cloud to your CMDB (manual integration, if your CMDB doesn't support vCloud API), to account our usage, a step towards monitoring), ...

![Sample of a generated diagram](https://github.com/zoquero/vcloudtools/raw/master/diagramsamples/vcloud.thumbnail.png "Sample of a generated diagram")

It **requires** [vCloud SDK for PHP for vCloud Suite 5.5](https://developercenter.vmware.com/web/sdk/5.5.0/vcloud-php). Don't forget to add it's folder to your **```include_path```** setting in your php.ini

Tested on Ubuntu 15.04 64b with PHP 5.6.4 against vCloud Director 5.5

zoquero at gmail dot com
9 August 2016

.

## graphcloud.php
**Generates a GraphViz diagram** representing your vCloud Infraestructure.

### Usage
```
  [Description]
     Generates a GraphViz diagram representing your vCloud Infraestructure.

  [Usage]
     # php graphvcloud.php --server <server> --user <username> --pswd <password> --sdkver <sdkversion> --dir <dir>
     # php graphvcloud.php -s <server> -u <username> -p <password> -v <sdkversion> -o <dir>

     -s|--server <IP|hostname>        [req] IP or hostname of the vCloud Director.
     -u|--user <username>             [req] User name in the form user@organization
                                           for the vCloud Director.
     -p|--pswd <password>             [req] Password for user.
     -v|--sdkver <sdkversion>         [req] SDK Version e.g. 1.5, 5.1 and 5.5.
     -o|--dir <directory>             [req] Folder where CSVs will be craeted.

  [Options]
     -e|--certpath <certificatepath>  [opt] Local certificate's full path.

  You can set the security parameters like server, user and pswd in 'config.php' file

  [Examples]
     # php graphvcloud.php --server 127.0.0.1 --user admin@MyOrg --pswd mypassword --sdkver 5.5 --dir /tmp/vc
```

## graphcloud.demo.php
Generates a GraphViz diagram representing a demo of a vCloud Infraestructure. Usefull for generating arbitrary diagrams, for design.

### Usage
```
  [Description]
     Generates a GraphViz diagram representing a demo of a vCloud Infraestructure.

  [Usage]
     # php graphvcloud.demo.php --output <file>
     # php graphvcloud.demo.php -o <file>

     -o|--output <file>               [req] Folder where CSVs will be craeted.

  [Examples]
     # php graphvcloud.demo.php --output /tmp/vc.dot
```

## exportvcloud.php
**Dumps** to CSV or XML all the entities (vApps, VMs,  vShields, vDCs and organizations) that you have access to.

### Usage
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

