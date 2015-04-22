# VPSie-WHMCS-Module
WHMCS module to control and provision VPSie VPS

This is a VPSie VPS provisioning module made for WHMCS. Made using api provided by VPSie for reseller to sell without any complications.

# Features :
* Create, Terminate, Resize, Reset Password, Force Reboot, Shutdown.
* Uploads / Downloads stats, CPU usage status, RAM usage stats, DISK R/W ( read/write ) stats in form of charts.
* Display same stats to clients.
* Clients can Boot, Reboot, Rebuild, Shutdown, Reset Password of their containers.
* Coming next -> Admins (backup, snapshot, assign additional ips) , UI improvements to dynamically load stats.

# Installation
* Copy module folder to whmcs_installation/modules/servers/vpsie
* $params['grand_type'] = ''; // bearer | refresh_token
* $params['client_id'] = ''; // get from https://my.vpsie.com/profile/settings
* $params['client_secret'] = ''; // get from https://my.vpsie.com/profile/settings
* Fill the above in vpsie/vpsie.php
* Custom fields { Admin only => { text => { vpsid , cpu , ram , ssd } , dropdowns => { OS|Operating System , DC|Location } } }
* Create product / service -> Module Settings , select Vpsie. You should see option for Offer Id fill it with appropriate offer id and its done.
* OS | DC | Offer fields can be filled with data from this url http://apps.servertalks.com/vpsie/
* Resize can be done using Change Package option , edit cpu , ram , disk fields and save them , then click Change Package on admin side to get it done.
* You're good to go !

Please report bugs if you find any , UI isn't perfect yet so bear with it.
