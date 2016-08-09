# vcloudtools
**Tools for working with vCloud Director**

It **requires** [vCloud SDK for PHP for vCloud Suite 5.5](https://developercenter.vmware.com/web/sdk/5.5.0/vcloud-php). Don't forget to add it's folder to your **```include_path```** setting in your php.ini

Tested on Ubuntu 15.04 64b with PHP 5.6.4 against vCloud Director 5.5

zoquero at gmail dot com
9 August 2016

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
                  -f <ip> -g <port> -h <proto> -i <ip> -j <port> \ 
                  (-o <OrgName) (-d <vdcName) (-e <vShieldEdgeName) \ 

     -s|--server <IP|hostname>        [req] IP or hostname of the vCloud Director.
     -u|--user <username>             [req] User name in the form user@organization
                                           for the vCloud Director.
     -p|--pswd <password>             [req] Password for user.
     -v|--sdkver <sdkversion>         [req] SDK Version e.g. 1.5, 5.1 and 5.5.
     -f|--fromip                      [req] source IP addres
     -g|--fromport                    [req] source port
     -h|--proto                       [req] source proto ('T' for TCP, 'U' for UDP, 'I' for ICMP)
     -i|--toip                        [req] destination IP addres
     -j|--toport                      [req] destination Port
     -o|--org                         [opt] Organization
     -d|--vdc                         [opt] Virtual Data Center name
     -e|--vse                         [opt] vShield Edge Gateway name

  You can set the security parameters like server, user and pswd in 'config.php' file

  [Examples]
     # php getvsefwrules.php --server 127.0.0.1 --user admin@MyOrg --pswd mypassword --sdkver 5.5 --fromip 1.2.3.4 --fromport 8080 --proto T --toip 192.168.100.10 --toport 80 -o MyOrg -d MyVdcName -e MyVShieldName
```

## config.php
**Configuration file**, just to avoid using command line parameters that appear on console, history and 'ps'

