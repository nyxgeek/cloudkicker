<?php

# Disable check for basic auth, if you do this, please restrict access by IP
$disable_safety_checks= False;

# path to config file, optional
$configFilePath = 'config.json';

$adfsFound = False;

if (isset($_GET['chuck'])){
        $backgroundImage = 'images/cloudkicker-chuck.png';


// Check if the configuration file exists
}elseif (file_exists($configFilePath)) {

// Read and decode the configuration file
$config = json_decode(file_get_contents($configFilePath), true);

// Verify that the configuration was successfully decoded
if (json_last_error() !== JSON_ERROR_NONE) {
    die('Error decoding JSON configuration file: ' . json_last_error_msg());
}

// Get the background image path
$backgroundImage = $config['background_image'] ?? 'images/chucknorris_safe_cloudkicker.png';

}


function check_basic_auth() {
    $url = "http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
    $httpCode = get_http_code($url);
    // Check if the response code is 401
    return $httpCode == 401;
}



// Function to get HTTP response code
function get_http_code($url) {
    $handle = curl_init($url);
    curl_setopt($handle, CURLOPT_TIMEOUT, 5);
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, TRUE);
    $response = curl_exec($handle);
    $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
    curl_close($handle);
    return $httpCode;
}

// Function to get tenant name
function get_tenant_name($domainname, $environment) {
    $host = $environment === 'gcc' ? 'login.microsoftonline.us' : 'login.microsoftonline.com';
    $domainlookup = json_decode(file_get_contents("https://$host/getuserrealm.srf?login=nyxgeek@$domainname"));
    return $domainlookup;
}

// Function to check tenant resolution
function check_tenant_resolution($tenant_name, &$onedrive_list, &$sharepoint_list, &$modern_auth_array, &$modern_auth_status, $environment) {
    $testmatch = strtolower($tenant_name);
    $suffix = $environment === 'gcc' ? '.us' : '.com';

    $domains = [
        "-my.sharepoint$suffix" => "OneDrive",
        ".sharepoint$suffix" => "SharePoint"
    ];

    foreach ($domains as $suffix => $type) {
        $testdomain = $testmatch . $suffix;
        $resolvedip = gethostbyname($testdomain);

        if ($resolvedip != $testdomain) {
            if ($type === "OneDrive") {
                $onedrive_list[] = $testdomain;
            } elseif ($type === "SharePoint") {
                $sharepoint_list[] = $testdomain;
            }

            try {
                $statuscode = get_http_code("https://$testdomain");
                $modern_auth_status = $statuscode == 401 ? "Enabled" : "Not Enforced";
                $modern_auth_array[] = "$type: $modern_auth_status &emsp;&nbsp;&emsp;($testdomain)";
            } catch (Exception $e) {
                echo "Error trying to get status code: {$e->getMessage()}";
            }
        }
    }
}

// Function to check ADFS endpoint
function check_adfs_endpoint($domains, $environment) {
    global $adfsFound;
    $adfs_endpoints = [];
    $hostnames = [];
    $host = $environment === 'gcc' ? 'login.microsoftonline.us' : 'login.microsoftonline.com';

    foreach ($domains as $domain) {
        if  ( $adfsFound == False ){
        $adfs_url = "https://$host/getuserrealm.srf?login=user@$domain";
        $tenant_info = json_decode(file_get_contents($adfs_url));

        if (isset($tenant_info->AuthURL)) {
            $url_parts = parse_url($tenant_info->AuthURL);
            $hostname = $url_parts['host'];

            if (!in_array($hostname, $hostnames)) {
                $hostnames[] = $hostname;
                $adfs_endpoints[] = $tenant_info->AuthURL;
                $adfsFound = True;
            }
        }
        }

    }

    return $adfs_endpoints;
}

// Determine the environment based on GET parameter
$environment = isset($_GET['environment']) && $_GET['environment'] === 'gcc' ? 'gcc' : 'commercial';


// Set the appropriate host based on the environment
$host_suffix = $environment === 'gcc' ? 'us' : 'com';

echo "<html><head><link rel='stylesheet' href='css.css'></head>";
echo "<body>";
echo "<div class='mainBody' style='font-family:sans-serif;'>";
echo "<b>Tenant Lookup</b>";
echo "<form action='index.php' method='GET'>";
echo "<input type='text' id='searchtext' name='searchtext' autofocus='autofocus' placeholder='notafakedomain.com'>";
echo "<br>";
echo "<label><input type='radio' name='environment' value='commercial' " . ($environment === 'commercial' ? 'checked' : '') . "> Commercial</label>";
echo "<label><input type='radio' name='environment' value='gcc' " . ($environment === 'gcc' ? 'checked' : '') . "> GCC</label>";
echo "<br>";
echo "<input type='submit' value='Submit'><br>";
echo "</form>";
echo "</div>";
echo '<div id="maincontent"  style="width:auto;min-width:1300px;min-height:900px;background-image:url(' . $backgroundImage . ');background-repeat: no-repeat;border: 2px solid black;position:absolute;font-family:sans-serif;background-color:#000;">';
echo "<div class='float_div' style='width:700px;overflow:auto;margin-left:500px;margin-right:25px;margin-top:225px;margin-bottom:20px;padding:10px;border: 3px solid rgba(40,206,40,1);background:rgba(5,5,5,0.95);position:relative;color:rgba(40,206,40,1);border-radius: 15px;'>";


// check to make sure page is protected by basic auth OR that they have disabled
if ( $disable_safety_checks || check_basic_auth() ) {

if (isset($_GET['searchtext']) && !empty($_GET['searchtext'])) {
    $searchTerm = htmlspecialchars($_GET['searchtext']);
    $searchTerm = preg_replace("/[^A-Za-z0-9@\. -]/", '', $searchTerm);
    echo "<script>document.getElementById('searchtext').value = '{$searchTerm}'</script>";

    $headers = [
        'Content-Type: text/xml; charset=utf-8',
        'SOAPAction: "http://schemas.microsoft.com/exchange/2010/Autodiscover/Autodiscover/GetFederationInformation"',
        'User-Agent: AutodiscoverClient',
        'Accept-Encoding: identity'
    ];
    $autodiscover_host = $environment === 'gcc' ? 'autodiscover-s.office365.us' : 'autodiscover-s.outlook.com';
    $url = "https://$autodiscover_host/autodiscover/autodiscover.svc";
    echo "The domain is <b>{$searchTerm}</b><p>";

    $xml = '<?xml version="1.0" encoding="utf-8"?>
    <soap:Envelope xmlns:exm="http://schemas.microsoft.com/exchange/services/2006/messages"
    xmlns:ext="http://schemas.microsoft.com/exchange/services/2006/types"
    xmlns:a="http://www.w3.org/2005/08/addressing"
    xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xmlns:xsd="http://www.w3.org/2001/XMLSchema">
    <soap:Header>
        <a:Action soap:mustUnderstand="1">http://schemas.microsoft.com/exchange/2010/Autodiscover/Autodiscover/GetFederationInformation</a:Action>
        <a:To soap:mustUnderstand="1">https://$autodiscover_host/autodiscover/autodiscover.svc</a:To>
        <a:ReplyTo>
            <a:Address>http://www.w3.org/2005/08/addressing/anonymous</a:Address>
        </a:ReplyTo>
    </soap:Header>
    <soap:Body>
        <GetFederationInformationRequestMessage xmlns="http://schemas.microsoft.com/exchange/2010/Autodiscover">
            <Request>
                <Domain>' . $searchTerm . '</Domain>
            </Request>
        </GetFederationInformationRequestMessage>
    </soap:Body>
    </soap:Envelope>';

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $xml);
    curl_setopt($curl, CURLOPT_TIMEOUT, 10);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    $result = curl_exec($curl);

    if (curl_errno($curl)) {
        throw new Exception(curl_error($curl));
    }

    curl_close($curl);

    $sharepoint_list = [];
    $onedrive_list = [];
    $mail_list = [];
    $domain_list = [];
    $modern_auth_status = '';
    $modern_auth_array = [];
    $adfs_endpoints = [];

    $teststring = "/<Domain>(.*?)<\/Domain>/";
    $matches = [];






if (preg_match_all($teststring, $result, $matches)) {

    foreach ($matches[0] as $match) {
        $cleaned = strtolower(str_replace(["<Domain>", "</Domain>"], "", $match));
        $domain_list[] = $cleaned;
    }

    $primarymatches = [];

    foreach ($matches[0] as $match) {
        $testmatch = strtolower(str_replace(["<Domain>", "</Domain>"], "", $match));

        if (preg_match('/\.mail\.onmicrosoft\.' . $host_suffix . '/i', $testmatch)) {
            $mail_list[] = str_replace(".mail.onmicrosoft.$host_suffix", "", $testmatch);
        } elseif (preg_match('/\.onmicrosoft\.' . $host_suffix . '/i', $testmatch)) {
            $primarymatches[] = str_replace(".onmicrosoft.$host_suffix", "", $testmatch);
        }
    }

    $uniquematches = array_values(array_unique($primarymatches));
    sort($domain_list, SORT_NATURAL);
    sort($uniquematches, SORT_NATURAL);

    foreach ($uniquematches as $match) {
        check_tenant_resolution($match, $onedrive_list, $sharepoint_list, $modern_auth_array, $modern_auth_status, $environment);
    }

    $unique_modern_auth = array_values(array_filter(array_unique($modern_auth_array)));


    echo "Here are your results:<p>";


    if (!empty($uniquematches)) {

       // Use array_map to apply the callback function to each element of the original array
       //$tenant_list = array_map('appendString', $uniquematches);
       $tenant_list = array_map(function($string) use ($host_suffix) {
           return $string . '.onmicrosoft.' . $host_suffix;
       }, $uniquematches);

       $file_content = implode("\n", $tenant_list);
       //$file_content = implode("\n", $uniquematches);
       $base64_content = base64_encode($file_content);
       $file_name = "{$searchTerm}.tenants.txt";
       echo "<P><a href='data:text/plain;base64,{$base64_content}' download='{$file_name}'>Download tenant list</a>&nbsp;&nbsp;-&nbsp;&nbsp;";
    }

    if (!empty($domain_list)) {
       $file_content = implode("\n", $domain_list);
       $base64_content = base64_encode($file_content);
       $file_name = "{$searchTerm}.domains.txt";
       echo "<a href='data:text/plain;base64,{$base64_content}' download='{$file_name}'>Download domain list</a><br>";
    }




    echo "<div style='color:rgba(40,206,40,1);'>";
    echo "<table border=1 width=100%>";

    $infolookup = get_tenant_name($searchTerm, $environment);
    echo "<tr><td>FederationBrandName</td><td>{$infolookup->FederationBrandName}</td></tr>";

    $setup_type = $infolookup->NameSpaceType == "Managed" ? "Managed (Standalone Online or AD Synced)" : $infolookup->NameSpaceType;
    echo "<tr><td>Azure Configuration</td><td>{$setup_type}</td></tr>";


    if ($setup_type == "Federated") {
        $adfs_url = $infolookup->AuthURL;
        if ( $adfs_url != "" ){
            $adfs_endpoints[] = $adfs_url;
            $adfsFound = True;
            //echo "<tr><td>ADFS Endpoint</td><td>{$infolookup->AuthURL}</td></tr>";
        }else{
    $adfs_endpoints = check_adfs_endpoint($domain_list, $environment);
       }
    }


    echo "<tr><td>&nbsp;</td><td></td></tr>";

    foreach ($onedrive_list as $onedrive) {
        echo "<tr><td>ONEDRIVE FOUND</td><td>{$onedrive}</td></tr>";
    }

    foreach ($sharepoint_list as $sharepoint) {
        echo "<tr><td>SHAREPOINT FOUND</td><td>{$sharepoint}</td></tr>";
    }

    foreach ($mail_list as $mail) {
        echo "<tr><td>MAIL RECORD FOUND<br>(indicates AD Sync Enabled)</td><td>{$mail}.mail.onmicrosoft.$host_suffix</td></tr>";
    }

    if (count($unique_modern_auth) > 0) {
        echo "<tr><td>MODERN AUTH STATUS</td><td>";
        foreach ($unique_modern_auth as $auth_status) {
            echo "{$auth_status}<br>";
        }
        echo "</td></tr>";
    }

    if (count($adfs_endpoints) > 0) {
        echo "<tr><td>ADFS ENDPOINTS</td><td>";
        foreach ($adfs_endpoints as $endpoint) {
            echo "{$endpoint}<br>";
        }
        echo "</td></tr>";
    }

    echo "</table>";
    echo "</div>";

    echo "<p><hr><p>Total Tenants: " . count($uniquematches) . " :<br>";
    foreach ($uniquematches as $unique_match) {
        echo "<br>{$unique_match}";
    }

    echo "<p><hr><p>Total Domains: " . count($domain_list) . " :<br>";
    foreach ($domain_list as $domain) {
        echo "<br>{$domain}";
    }

} else {
    echo "Match NOT found";
}










} else {
    echo "<div style='height:500px;margin-top:50px;font-family:sans-serif;font-weight:800;'>Please enter a domain to look up<p>(note: large organizations may take some time)</div>";
}

} else {
    echo "Basic Authentication not enabled. This service could be abused if exposed externally. If this is intentional, please change \$disable_safety_checks to 'true'";
}

echo "</div>";
?>
