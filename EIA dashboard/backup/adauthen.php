<?php
    function chkldapuser($domain,$domainUser,$domainPassword) {
        $cmgLDAPserver = "10.8.88.5";
        $centralLDAPserver = "ldapadc.central.co.th";
        $ofmLDAPserver = "10.86.202.104";
        
        switch ($domain) {
            case "cmg": $ldapserver = $cmgLDAPserver; $base_dn = "DC=cmg,DC=co,DC=th"; break;
            case "central": $ldapserver = $centralLDAPserver; $base_dn = "DC=central,DC=co,DC=th"; break;
            case "ofm": $ldapserver = $ofmLDAPserver; $base_dn = "DC=officemate,DC=co,DC=th"; break;
            default: return "Invalid Domain";
        }

        if(!serviceping($ldapserver)){
            return "Not found";
        }

        $ldap = ldap_connect($ldapserver, 389);
        if(!$ldap) return "Not connect";

        ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);

        $bindUser = $domain.'\\'.$domainUser;
        $bind = @ldap_bind($ldap, $bindUser, $domainPassword);

        if(!$bind){
            ldap_close($ldap);
            return "Invalid";
        } else {
            // SUCCESS: Fetch Full Name (DisplayName)
            $filter = "(sAMAccountName=$domainUser)";
            $attributes = array("displayname", "name");
            $search = ldap_search($ldap, $base_dn, $filter, $attributes);
            $info = ldap_get_entries($ldap, $search);
            
            $fullName = $domainUser; // Default to username if not found
            if ($info["count"] > 0) {
                $fullName = $info[0]["displayname"][0] ?? $info[0]["name"][0] ?? $domainUser;
            }
            
            ldap_close($ldap);
            // Return Array instead of just string
            return array("status" => "Pass", "full_name" => $fullName);
        }  
    }

    function serviceping($host, $port=389, $timeout=10){
        $op = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if (!$op){
            $log_file = __DIR__ . '/worker_log.txt';
            $msg = "[" . date('Y-m-d H:i:s') . "] AD Connection Failed to $host. Error ($errno): $errstr\n";
            file_put_contents($log_file, $msg, FILE_APPEND);
            return 0;
        } else{
			fclose($op);
			return 1;
        }
    }

    function ping($host){
        exec("ping -n 2 ".$host,$output,$status);
        return ($status == 0) ? 1 : 0;
     }
?>
