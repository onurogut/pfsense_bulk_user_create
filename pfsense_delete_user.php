#!/usr/local/bin/php-cgi -f
<?php
/*
 * Delete VPN Users, Certificates and CSOs
 * Range: 10.8.0.3 - 10.8.0.31
 */

require_once("config.inc");

$start_ip = "10.8.0.3";
$end_ip = "10.8.0.31";

echo "\n=======================================================\n";
echo "Delete VPN Users\n";
echo "=======================================================\n";
echo "Range: $start_ip - $end_ip\n\n";

// Build list of usernames to delete
$usernames_to_delete = array();
$current_ip = $start_ip;
$end_ip_long = ip2long($end_ip);

while (ip2long($current_ip) <= $end_ip_long) {
    $usernames_to_delete[] = $current_ip;
    $current_ip = long2ip(ip2long($current_ip) + 1);
}

echo "Will delete " . count($usernames_to_delete) . " users\n\n";

echo "⚠ WARNING: This will permanently delete:\n";
echo "  - Users\n";
echo "  - Certificates\n";
echo "  - Client Specific Overrides\n\n";

echo "Press ENTER to continue (CTRL+C to cancel): ";
$handle = fopen("php://stdin", "r");
fgets($handle);
fclose($handle);
echo "\n";

$deleted_users = 0;
$deleted_certs = 0;
$deleted_csos = 0;

// Delete users and their certificates
if (is_array($config['system']['user'])) {
    foreach ($config['system']['user'] as $idx => $user) {
        if (in_array($user['name'], $usernames_to_delete)) {
            echo "Deleting user: {$user['name']}\n";

            // Get user's certificate refid
            $cert_refid = null;
            if (isset($user['cert'])) {
                $cert_refid = is_array($user['cert']) ? $user['cert'][0] : $user['cert'];
            }

            // Delete user's certificate
            if ($cert_refid && is_array($config['cert'])) {
                foreach ($config['cert'] as $cert_idx => $cert) {
                    if ($cert['refid'] == $cert_refid) {
                        echo "  - Certificate: {$cert['descr']}\n";
                        unset($config['cert'][$cert_idx]);
                        $deleted_certs++;
                        break;
                    }
                }
            }

            // Delete user
            unset($config['system']['user'][$idx]);
            $deleted_users++;
        }
    }

    // Re-index arrays
    $config['system']['user'] = array_values($config['system']['user']);
    if (isset($config['cert'])) {
        $config['cert'] = array_values($config['cert']);
    }
}

// Delete CSOs
if (is_array($config['openvpn']['openvpn-csc'])) {
    foreach ($config['openvpn']['openvpn-csc'] as $idx => $csc) {
        if (in_array($csc['common_name'], $usernames_to_delete)) {
            echo "Deleting CSO: {$csc['common_name']}\n";
            unset($config['openvpn']['openvpn-csc'][$idx]);
            $deleted_csos++;
        }
    }

    // Re-index array
    $config['openvpn']['openvpn-csc'] = array_values($config['openvpn']['openvpn-csc']);
}

echo "\n";

// Save config
if ($deleted_users > 0 || $deleted_certs > 0 || $deleted_csos > 0) {
    echo "Saving configuration...\n";
    write_config("Deleted $deleted_users users, $deleted_certs certs, $deleted_csos CSOs");
    echo "✓ Configuration saved\n\n";
}

echo "=======================================================\n";
echo "COMPLETED\n";
echo "=======================================================\n";
echo "Deleted users: $deleted_users\n";
echo "Deleted certificates: $deleted_certs\n";
echo "Deleted CSOs: $deleted_csos\n";
echo "=======================================================\n\n";

?>