#!/usr/local/bin/php-cgi -f
<?php
/*
 * Fix CSO Server List
 * Adds server_list to Client Specific Overrides
 * CSOs don't work without this!
 */

require_once("config.inc");

// Configuration
$username_pattern = "10.8.0.";  // Match users starting with this
$selected_server_id = null;       // null = auto-detect, or set manually (1, 2, 3...)

echo "\n=======================================================\n";
echo "Fix CSO Server List\n";
echo "=======================================================\n\n";

// Detect OpenVPN servers
echo "OpenVPN Servers:\n";
$openvpn_servers = array();
if (is_array($config['openvpn']['openvpn-server'])) {
    foreach ($config['openvpn']['openvpn-server'] as $idx => $server) {
        $vpnid = $idx + 1;
        $desc = isset($server['description']) ? $server['description'] : "Server $vpnid";
        $openvpn_servers[$vpnid] = $desc;
        echo "  [$vpnid] $desc\n";
    }
}

if (empty($openvpn_servers)) {
    die("\nERROR: No OpenVPN servers found!\n");
}

// Select server
if ($selected_server_id !== null && isset($openvpn_servers[$selected_server_id])) {
    echo "\n✓ Manual selection: {$openvpn_servers[$selected_server_id]}\n";
} else if (count($openvpn_servers) == 1) {
    $selected_server_id = key($openvpn_servers);
    echo "\n✓ Auto-selected: {$openvpn_servers[$selected_server_id]}\n";
} else {
    echo "\n⚠ Multiple OpenVPN servers found!\n";
    echo "Edit this script and set \$selected_server_id:\n\n";
    foreach ($openvpn_servers as $id => $desc) {
        echo "  \$selected_server_id = $id;  // $desc\n";
    }
    die("\nERROR: Server ID not specified!\n");
}

echo "\n";

// Show current CSOs
echo "Client Specific Overrides:\n";
echo "-------------------------------------------\n";

$cso_count = 0;
$needs_fix = 0;

if (is_array($config['openvpn']['openvpn-csc'])) {
    foreach ($config['openvpn']['openvpn-csc'] as $idx => $csc) {
        $common_name = $csc['common_name'];

        // Match pattern?
        if (strpos($common_name, $username_pattern) !== 0) {
            continue;
        }

        $cso_count++;
        $has_server = isset($csc['server_list']) && !empty($csc['server_list']);

        echo "  [$cso_count] $common_name - {$csc['tunnel_network']}";

        if ($has_server) {
            echo " ✓ Has server\n";
        } else {
            echo " ✗ MISSING server!\n";
            $needs_fix++;
        }
    }
}

echo "\n";
echo "Total CSOs: $cso_count\n";
echo "Need fixing: $needs_fix\n\n";

if ($needs_fix == 0) {
    echo "All CSOs already have server_list. Nothing to do.\n\n";
    exit(0);
}

// Check if running non-interactively (called by another script)
$auto_mode = !posix_isatty(STDIN);

if (!$auto_mode) {
    echo "Press ENTER to fix (CTRL+C to cancel): ";
    $handle = fopen("php://stdin", "r");
    fgets($handle);
    fclose($handle);
    echo "\n";
} else {
    echo "Running in auto mode...\n\n";
}

// Fix CSOs
$fixed_count = 0;

echo "Processing...\n";
echo "-------------------------------------------\n";

if (is_array($config['openvpn']['openvpn-csc'])) {
    foreach ($config['openvpn']['openvpn-csc'] as $idx => &$csc) {
        $common_name = $csc['common_name'];

        // Match pattern?
        if (strpos($common_name, $username_pattern) !== 0) {
            continue;
        }

        // Already has server_list?
        $has_server = isset($csc['server_list']) && !empty($csc['server_list']);

        if (!$has_server) {
            // Add server_list and missing fields
            $csc['server_list'] = "1"; // Assuming server ID 1, adjust if needed

            // Add other fields pfSense GUI adds automatically
            if (!isset($csc['disable'])) {
                $csc['disable'] = false;  // Ensure CSO is enabled
            }
            if (!isset($csc['custom_options'])) {
                $csc['custom_options'] = '';
            }

            // Update description to include IP if it doesn't already
            if (isset($csc['description']) && strpos($csc['description'], 'Auto') !== false) {
                $csc['description'] = "Auto created for " . $common_name;
            }

            echo "[$common_name] ✓ Server list added\n";
            $fixed_count++;
        }
    }
    unset($csc); // Clear reference
}

echo "\n";

// Save config
if ($fixed_count > 0) {
    echo "Saving configuration...\n";
    write_config("CSO server list fixed: $fixed_count entries");
    echo "✓ Configuration saved\n\n";

    // Apply CSC changes - this syncs CSOs to OpenVPN
    echo "Applying CSO changes to OpenVPN...\n";
    require_once("openvpn.inc");

    // Resync all CSCs (Client Specific Overrides)
    if (function_exists('openvpn_resync_csc_all')) {
        openvpn_resync_csc_all();
        echo "✓ CSOs synced (openvpn_resync_csc_all)\n\n";
    } else if (function_exists('openvpn_resync_csc')) {
        // Try individual sync for each server
        if (is_array($config['openvpn']['openvpn-server'])) {
            foreach ($config['openvpn']['openvpn-server'] as $idx => $server) {
                $vpnid = $idx + 1;
                openvpn_resync_csc($vpnid);
            }
        }
        echo "✓ CSOs synced (openvpn_resync_csc)\n\n";
    } else {
        echo "⚠ CSO sync function not found, restarting OpenVPN...\n";
        // Fallback: restart OpenVPN
        require_once("service-utils.inc");
        if (is_array($config['openvpn']['openvpn-server'])) {
            foreach ($config['openvpn']['openvpn-server'] as $idx => $server) {
                openvpn_restart('server', $server);
                sleep(1);
            }
        }
        echo "✓ OpenVPN restarted\n\n";
    }

    echo "=======================================================\n";
    echo "COMPLETED\n";
    echo "=======================================================\n";
    echo "Fixed CSOs: $fixed_count\n\n";

    echo "VERIFY:\n";
    echo "1. VPN > OpenVPN > Client Specific Overrides\n";
    echo "   All CSOs should show 'Server List: OpenVPN Server 1'\n";
    echo "2. Try connecting with a VPN client - should work now\n";
    echo "=======================================================\n\n";
} else {
    echo "✗ No CSOs were fixed!\n\n";
}

?>