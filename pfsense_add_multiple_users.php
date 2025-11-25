#!/usr/local/bin/php-cgi -f
<?php
/*
 * pfSense Bulk User Creation - Working Version
 * Creates users with proper EKU extensions
 * Does NOT modify CSO - that's done separately
 */

require_once("config.inc");
require_once("auth.inc");
require_once("certs.inc");

// Configuration
$password_prefix = "Pass";
$start_ip = "10.8.0.3";
$end_ip = "10.8.0.31";
$ca_name = "example_ca";

echo "\n=======================================================\n";
echo "pfSense Bulk User Creation\n";
echo "=======================================================\n\n";

// Find CA
$ca_ref = null;
$ca_index = null;
if (is_array($config['ca'])) {
    foreach ($config['ca'] as $idx => $ca) {
        if (stripos($ca['descr'], $ca_name) !== false) {
            $ca_ref = $ca['refid'];
            $ca_index = $idx;
            echo "✓ CA: {$ca['descr']}\n\n";
            break;
        }
    }
}

if (!$ca_ref) {
    die("ERROR: CA not found!\n");
}

$total = ip2long($end_ip) - ip2long($start_ip) + 1;
echo "Total users to create: $total\n";
echo "IP Range: $start_ip - $end_ip\n\n";

echo "Press ENTER to continue (CTRL+C to cancel): ";
$handle = fopen("php://stdin", "r");
fgets($handle);
fclose($handle);
echo "\n";

$work_dir = "/tmp/vpn_bulk_" . date('Ymd_His');
mkdir($work_dir, 0755, true);
$log_file = $work_dir . '/users_log.csv';
file_put_contents($log_file, "Username,Password,IP,Status\n");

$current_ip = $start_ip;
$end_ip_long = ip2long($end_ip);
$created = 0;
$num = 1;

// Create OpenSSL config for EKU extensions
$ssl_config = "/tmp/openssl_client_" . uniqid() . ".cnf";
$ssl_config_content = "
[req]
distinguished_name = req_distinguished_name

[req_distinguished_name]

[v3_client]
basicConstraints = CA:FALSE
keyUsage = critical, digitalSignature, keyEncipherment
extendedKeyUsage = clientAuth
subjectKeyIdentifier = hash
authorityKeyIdentifier = keyid,issuer
";
file_put_contents($ssl_config, $ssl_config_content);

while (ip2long($current_ip) <= $end_ip_long) {
    $username = $current_ip;
    $password = $password_prefix . rand(100000, 999999);

    echo "[$num/$total] $username\n";

    // Check if user exists
    $exists = false;
    if (is_array($config['system']['user'])) {
        foreach ($config['system']['user'] as $u) {
            if ($u['name'] == $username) {
                $exists = true;
                break;
            }
        }
    }

    if ($exists) {
        echo "  ⊘ Already exists\n\n";
        $current_ip = long2ip(ip2long($current_ip) + 1);
        $num++;
        continue;
    }

    try {
        // Create user
        $user = array();
        $user['name'] = $username;
        $user['descr'] = "VPN User";
        $user['scope'] = "user";
        $user['uid'] = $config['system']['nextuid']++;
        $user['bcrypt-hash'] = password_hash($password, PASSWORD_BCRYPT);

        if (!is_array($config['system']['user'])) {
            $config['system']['user'] = array();
        }
        $config['system']['user'][] = $user;

        echo "  ✓ User created\n";

        // Create certificate with proper EKU
        $cert = array();
        $cert['refid'] = uniqid();
        $cert['descr'] = $username . " Certificate";
        $cert['caref'] = $ca_ref;

        // Get CA cert and key
        $ca_crt = base64_decode($config['ca'][$ca_index]['crt']);
        $ca_key = base64_decode($config['ca'][$ca_index]['prv']);

        // Generate private key
        $pkey = openssl_pkey_new(array(
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA
        ));

        // Create CSR
        $dn = array(
            'countryName' => 'US',
            'commonName' => $username
        );

        $csr = openssl_csr_new($dn, $pkey, array(
            'digest_alg' => 'sha256',
            'config' => $ssl_config
        ));

        // Load CA cert and key
        $ca_res = openssl_x509_read($ca_crt);
        $ca_key_res = openssl_pkey_get_private($ca_key);

        // Sign with v3_client extensions (includes clientAuth EKU)
        $x509 = openssl_csr_sign(
            $csr,
            $ca_res,
            $ca_key_res,
            3650,
            array(
                'digest_alg' => 'sha256',
                'config' => $ssl_config,
                'x509_extensions' => 'v3_client'
            ),
            time()
        );

        if (!$x509) {
            throw new Exception("Certificate signing failed: " . openssl_error_string());
        }

        // Export cert and key
        openssl_x509_export($x509, $cert_str);
        openssl_pkey_export($pkey, $key_str);

        $cert['crt'] = base64_encode($cert_str);
        $cert['prv'] = base64_encode($key_str);

        if (!is_array($config['cert'])) {
            $config['cert'] = array();
        }
        $config['cert'][] = $cert;

        // Link cert to user
        $last_idx = count($config['system']['user']) - 1;
        $config['system']['user'][$last_idx]['cert'] = array($cert['refid']);

        echo "  ✓ Certificate created (EKU: clientAuth)\n";

        // Create CSO (server_list will be added by fix script)
        $csc = array();
        $csc['common_name'] = $username;
        $csc['tunnel_network'] = $current_ip . "/16";
        $csc['description'] = "Auto created for " . $current_ip;

        if (!is_array($config['openvpn']['openvpn-csc'])) {
            $config['openvpn']['openvpn-csc'] = array();
        }
        $config['openvpn']['openvpn-csc'][] = $csc;

        echo "  ✓ CSO created (/16)\n";
        echo "  ✓ Password: $password\n\n";

        file_put_contents($log_file, "$username,$password,$current_ip,SUCCESS\n", FILE_APPEND);
        $created++;

    } catch (Exception $e) {
        echo "  ✗ ERROR: " . $e->getMessage() . "\n\n";
        file_put_contents($log_file, "$username,ERROR,$current_ip,FAILED\n", FILE_APPEND);
    }

    $current_ip = long2ip(ip2long($current_ip) + 1);
    $num++;
}

// Cleanup
@unlink($ssl_config);

// Save config
if ($created > 0) {
    echo "\nSaving configuration...\n";
    write_config("Bulk VPN: $created users created");
    echo "✓ Configuration saved\n\n";
}

echo "=======================================================\n";
echo "STEP 1 COMPLETED\n";
echo "=======================================================\n";
echo "Users created: $created\n";
echo "Log file: $log_file\n\n";

// Run CSO server list fix script
echo "=======================================================\n";
echo "STEP 2: FIX CSO SERVER LIST\n";
echo "=======================================================\n\n";

$cso_fix_script = "/tmp/pfsense_fix_cso_serverlist.php";

if (file_exists($cso_fix_script)) {
    echo "Running CSO fix script...\n\n";
    passthru("php " . escapeshellarg($cso_fix_script) . " 2>&1", $cso_ret);

    if ($cso_ret == 0) {
        echo "\n✓ CSO server list fixed!\n\n";
    } else {
        echo "\n⚠ CSO fix script error (exit code: $cso_ret)\n";
        echo "Run manually: php $cso_fix_script\n\n";
    }
} else {
    echo "⚠ CSO fix script not found: $cso_fix_script\n";
    echo "Upload pfsense_fix_cso_serverlist.php to /root/\n";
    echo "Then run manually: php /root/pfsense_fix_cso_serverlist.php\n\n";
}

// Run OVPN export script
echo "=======================================================\n";
echo "STEP 3: EXPORT OVPN FILES\n";
echo "=======================================================\n\n";

$export_script = "/tmp/pfsense_export_ovpns.php";

if (file_exists($export_script)) {
    echo "Running export script...\n\n";
    passthru("php " . escapeshellarg($export_script) . " 2>&1", $export_ret);

    if ($export_ret == 0) {
        echo "\n✓ OVPN export completed!\n\n";
    } else {
        echo "\n⚠ Export script error (exit code: $export_ret)\n";
        echo "Run manually: php $export_script\n\n";
    }
} else {
    echo "⚠ Export script not found: $export_script\n\n";
    echo "To export OVPN files:\n";
    echo "1. Upload pfsense_export_ovpns.php\n";
    echo "2. Configure VPN server host\n";
    echo "3. Run: php /tmp/pfsense_export_ovpns.php\n\n";
    echo "OR use GUI:\n";
    echo "VPN > OpenVPN > Client Export\n\n";
}

echo "=======================================================\n";
echo "ALL DONE!\n";
echo "=======================================================\n\n";

?>