#!/usr/local/bin/php-cgi -f
<?php
/*
 * OVPN Export for Existing Users
 * Creates .ovpn files for already created users
 * 
 * Author: Onur Öğüt
 * Website: https://www.onurogut.com
 * LinkedIn: https://www.linkedin.com/in/onurogut/
 */

require_once("config.inc");
require_once("certs.inc");

// SETTINGS - Edit these
$vpn_server_host = "vpn.example.com";  // pfSense WAN IP or hostname
$vpn_server_port = "1194";
$vpn_protocol = "udp";
$ca_name = "example_ca";

// Which users to export?
$username_pattern = "10.8.0.";  // Users starting with this (10.8.0.3, 10.8.0.4, etc)

// SETTINGS - Edit these
echo "=======================================================\n";
echo "SETTINGS CHECK\n";
echo "=======================================================\n";

$vpn_server_host = "vpn.example.com";  // pfSense WAN IP or hostname
$vpn_server_port = "1194";
$vpn_protocol = "udp";
$ca_name = "example_ca";

echo "VPN Server: $vpn_server_host:$vpn_server_port\n";
echo "Protocol: $vpn_protocol\n";
echo "CA: $ca_name\n\n";

if ($vpn_server_host == "YOUR-WAN-IP-OR-HOSTNAME") {
    echo "✗ ERROR: VPN server host not configured!\n\n";
    echo "To configure:\n";
    echo "1. Status > Interfaces > WAN - Find your IP address\n";
    echo "2. Update \$vpn_server_host variable in the script\n";
    echo "   Example: \$vpn_server_host = \"85.34.12.45\";\n";
    echo "   Or:      \$vpn_server_host = \"vpn.yourcompany.com\";\n\n";
    die();
}

// Find CA
$ca_cert_content = null;
if (is_array($config['ca'])) {
    foreach ($config['ca'] as $ca) {
        if (stripos($ca['descr'], $ca_name) !== false) {
            $ca_cert_content = base64_decode($ca['crt']);
            echo "✓ CA: {$ca['descr']}\n";
            break;
        }
    }
}

if (!$ca_cert_content) {
    die("ERROR: CA not found!\n");
}

// TLS Auth Key
$tls_auth_key = "";
if (is_array($config['openvpn']['openvpn-server'])) {
    foreach ($config['openvpn']['openvpn-server'] as $server) {
        if (isset($server['tls'])) {
            $tls_auth_key = base64_decode($server['tls']);
            echo "✓ TLS Auth Key found\n";
            break;
        }
    }
}

echo "\n";

// Working directory
$work_dir = "/tmp/vpn_ovpn_export_" . date('Ymd_His');
mkdir($work_dir, 0755, true);

echo "Scanning users...\n";
echo "-------------------------------------------\n";

$exported = 0;
$skipped = 0;

// Scan all users
if (is_array($config['system']['user'])) {
    foreach ($config['system']['user'] as $user) {
        $username = $user['name'];

        // Process only users matching the pattern
        if (strpos($username, $username_pattern) !== 0) {
            continue;
        }

        echo "\n[$username]\n";

        // Does user have a certificate?
        if (!isset($user['cert']) || empty($user['cert'])) {
            echo "  ⊘ No certificate, skipping\n";
            $skipped++;
            continue;
        }

        $cert_refid = is_array($user['cert']) ? $user['cert'][0] : $user['cert'];

        // Find certificate
        $user_cert = null;
        $user_key = null;

        if (is_array($config['cert'])) {
            foreach ($config['cert'] as $cert) {
                if ($cert['refid'] == $cert_refid) {
                    $user_cert = base64_decode($cert['crt']);
                    $user_key = base64_decode($cert['prv']);
                    echo "  ✓ Certificate found\n";
                    break;
                }
            }
        }

        if (!$user_cert || !$user_key) {
            echo "  ⊘ Certificate could not be read, skipping\n";
            $skipped++;
            continue;
        }

        // Create OVPN content
        $ovpn_content = "client
dev tun
proto $vpn_protocol
remote $vpn_server_host $vpn_server_port
resolv-retry infinite
nobind
persist-key
persist-tun
remote-cert-tls server
auth SHA256
cipher AES-256-CBC
verb 3

<ca>
$ca_cert_content
</ca>

<cert>
$user_cert
</cert>

<key>
$user_key
</key>
";

        // Add TLS Auth if available
        if (!empty($tls_auth_key)) {
            $ovpn_content .= "\nkey-direction 1\n<tls-auth>\n$tls_auth_key\n</tls-auth>\n";
        }

        // Write to file
        $ovpn_file = $work_dir . "/" . $username . ".ovpn";
        file_put_contents($ovpn_file, $ovpn_content);

        echo "  ✓ OVPN created: " . basename($ovpn_file) . "\n";
        $exported++;
    }
}

echo "\n";
echo "=======================================================\n";

// Add user information to a text file
$info_file = $work_dir . "/user_information.txt";
$info = "OVPN Files Created\n";
$info .= "=========================\n\n";
$info .= "VPN Server: $vpn_server_host:$vpn_server_port\n";
$info .= "Protocol: $vpn_protocol\n";
$info .= "Total Exported: $exported files\n\n";
$info .= "Usage:\n";
$info .= "1. Import the .ovpn file into OpenVPN client\n";
$info .= "2. Username: IP address (e.g., 10.8.0.3)\n";
$info .= "3. Password: (generated when script was run)\n\n";
$info .= "NOTE: You can find passwords in /tmp/vpn_export_*/users_log.csv\n";

file_put_contents($info_file, $info);

// Create ZIP
if ($exported > 0) {
    echo "Creating ZIP...\n";

    $zip_file = "/tmp/ovpn_files_" . date('Ymd_His') . ".zip";
    exec("cd " . escapeshellarg($work_dir) . " && zip -q " . escapeshellarg($zip_file) . " *.ovpn user_information.txt 2>&1", $output, $ret);

    if ($ret == 0 && file_exists($zip_file)) {
        echo "✓ ZIP created: $zip_file\n";
        echo "  Size: " . round(filesize($zip_file) / 1024, 2) . " KB\n";
        echo "  Contents: $exported .ovpn files\n";
    } else {
        echo "⚠ Could not create ZIP, manual zip:\n";
        echo "  cd $work_dir && zip /tmp/ovpn_files.zip *.ovpn\n";
        $zip_file = null;
    }
}

echo "\n=======================================================\n";
echo "COMPLETED\n";
echo "=======================================================\n";
echo "Exported: $exported\n";
echo "Skipped: $skipped\n";

if ($zip_file && file_exists($zip_file)) {
    echo "\nZIP File: $zip_file\n";
    echo "\nTo download:\n";
    echo "scp root@pfsense-ip:$zip_file .\n";
} else {
    echo "\nOVPN Folder: $work_dir\n";
}

echo "\n=======================================================\n\n";

// Remind where passwords are located
echo "NOTE: To find user passwords:\n";
echo "cat /tmp/vpn_export_*/users_log.csv\n\n";

?>