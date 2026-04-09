<?php
    function chkldapuser($domain,$domainUser,$domainPassword) {
        $cmgLDAPserver = "10.8.88.5"; //$cmgad = "s-thcw-infdc01.cmg.co.th";
        $centralLDAPserver = "ldapadc.central.co.th";
        $ofmLDAPserver = "10.86.202.104";
        // $cmgLDAPserver = "ldap://10.8.88.5:389";
        // $centralLDAPserver = "ldap://10.0.10.1:389";
        switch ($domain) {
            case "cmg":
                $ldapserver = $cmgLDAPserver;
                break;
            case "central":
                $ldapserver = $centralLDAPserver;
                break;
            case "ofm":
                $ldapserver = $ofmLDAPserver;
                break;
        }
        if(!serviceping($ldapserver)){
            return "Not found";
        }
        //connect to LDAP
        $ldap = ldap_connect($ldapserver,389);
        if(!$ldap){
            return "Not connect";          
        } 
        // set LDAP Option
        ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
        // Bind needs user/password
        $bindUser = $domain.'\\'.$domainUser;
        // $bindUser = 'cn='.$domainUser.',ou=user,dc=cmg,dc=co,dc=th';
        $bind = ldap_bind($ldap, $bindUser, $domainPassword);
        ldap_close($ldap);
        if(!$bind){
            return "Invalid";
        } else{
            return "Pass";
        }  
    }

    function serviceping($host, $port=389, $timeout=1){
        $op = fsockopen($host, $port, $errno, $errstr, $timeout);
        if (!$op){
            return 0;
        } else{
			fclose($op); //explicitly close open socket connection
			return 1; //DC is up & running, we can safely connect with ldap_connect
        }
    }

    function ping($host){
        // Turn off all error reporting
        // error_reporting(0);
        exec("ping -n 2 ".$host,$output,$status);
        if($status == 0){
			return 1;
        } else{
			return 0;
        }
     }
?>
