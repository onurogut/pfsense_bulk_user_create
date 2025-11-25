# pfSense OpenVPN Automation Scripts

A collection of PHP scripts designed to automate repetitive tasks related to **OpenVPN user management** and **configuration** on pfSense firewalls.

These scripts interact directly with the pfSense configuration (`config.xml`) and must be run via the command line (SSH/Diagnostics > Command Prompt) on the pfSense appliance itself.

***

## ⚠️ WARNING / Disclaimer

**USE THESE SCRIPTS AT YOUR OWN RISK.** Directly manipulating the pfSense configuration can lead to system instability if done incorrectly. **Always take a backup of your configuration before running any script.**

You can back up your configuration via **Diagnostics > Backup & Restore**.

***

## ⚙️ Scripts and Functions

| Filename | Description | Prerequisites |
| :--- | :--- | :--- |
| `pfsense_add_multiple_users.php` | **Bulk User Creation & Export:** Creates users, generates certificates, adds Client Specific Overrides (CSO) based on a defined IP range, and exports the final `.ovpn` files. | The `openvpn-client-export` package must be installed. |
| `pfsense_export_ovpns.php` | **Export OVPN for Existing Users:** Scans existing users (matching a pattern) with certificates and manually creates `.ovpn` configuration files for them. Useful as a standalone tool or a fallback. | Requires correct configuration of VPN Server details in the script. |
| `pfsense_fix_cso_serverlist.php` | **CSO Server List Fixer:** Adds or updates the `server_list` parameter for Client Specific Overrides matching a defined pattern. | None. |
| `pfsense_delete_user.php` | **Bulk User Deletion:** Deletes users, their certificates, and Client Specific Overrides within a defined IP range. | None. |

***

## 🛠️ Configuration Required (MUST EDIT)

Before executing any script, you **must** edit the variables under the `// Configuration` or `// SETTINGS` section to match your pfSense environment.

### 1. `pfsense_add_multiple_users.php`

| Variable | Description | Example Value |
| :--- | :--- | :--- |
| `\$password_prefix` | Base string for auto-generated passwords. | `"VPN-User-"` |
| `\$start_ip` / `\$end_ip` | The **Client Tunnel IP Range** for which users will be created (and used for Common Name). | `"10.8.0.3"` - `"10.8.0.31"` |
| `\$ca_name` | The exact description/name of your Certificate Authority (CA) in pfSense. | `"example_ca"` |

### 2. `pfsense_export_ovpns.php`

| Variable | Description | Example Value |
| :--- | :--- | :--- |
| `\$vpn_server_host` | The WAN IP or hostname that clients will connect to. **REQUIRED.** | `"vpn.example.com"` |
| `\$vpn_server_port` | The OpenVPN server port. | `"1194"` |
| `\$vpn_protocol` | The OpenVPN server protocol (`udp` or `tcp`). | `"udp"` |
| `\$ca_name` | The exact description/name of your Certificate Authority (CA). | `"example_ca"` |
| `\$username_pattern`| Only export users whose Common Name starts with this string. | `"10.8.0."` |

### 3. `pfsense_fix_cso_serverlist.php`

| Variable | Description | Example Value |
| :--- | :--- | :--- |
| `\$username_pattern` | Pattern to match CSO common names. | `"10.8.0."` |
| `\$selected_server_id` | The OpenVPN server ID to assign to matched CSOs (null = auto-detect). | `null` or `1` |

### 4. `pfsense_delete_user.php`

| Variable | Description | Example Value |
| :--- | :--- | :--- |
| `\$start_ip` / `\$end_ip` | The IP range of users to delete. | `"10.8.0.3"` - `"10.8.0.31"` |

***

## 🚀 Installation & Usage

1.  **Configure:** Open the desired script and adjust the variables listed above in the **Configuration Required** section.
2.  **Backup:** Create a full backup of your pfSense configuration.
3.  **Transfer:** Upload the configured PHP script(s) to `/tmp` on your pfSense machine.

### A. Run via pfSense Web GUI (Recommended for one-time/quick use)

1.  **Transfer:**
    * Navigate to **Diagnostics > Command Prompt** in the pfSense web interface.
    * Scroll down to the **Upload File** section, choose your PHP file, and click **Upload**. The file will be placed in `/tmp`.
2.  **Execute:**
    * Still on the **Diagnostics > Command Prompt** page, locate the **Execute Shell Command** field.
    * Enter the command to run the script (replace the filename if necessary):
        ```bash
        php /tmp/pfsense_add_multiple_users.php
        ```
    * Click the **Execute** button.
    
3.  **Download Output:** After execution, you can download the generated `.zip` file using the **Download File** section on the same Command Prompt page.

### B. Run via SSH / Console

1.  **Transfer:** Use the Secure Copy Protocol (`scp`) or any file transfer tool to copy the script(s) to `/tmp` on your pfSense machine.
    ```bash
    # Command to copy a script to pfSense:
    scp /path/to/local/script_name.php root@pfsense-ip:/tmp/
    ```
2.  **Execute:**
    * Connect to your pfSense box using SSH or the physical console.
    * Execute the script using the `php` command:
        ```bash
        php /tmp/pfsense_add_multiple_users.php
        ```
3.  **Download Output:** Use `scp` to download the resulting `.zip` file from `/tmp` back to your local machine.

***

## 📝 Notes

- All IP addresses, server names, and CA names in the scripts are anonymized examples. You must replace them with your actual values.
- The `write_config()` function is a pfSense-specific function that is only available when running on a pfSense system.
- Always test scripts in a non-production environment first.
- Keep backups of your configuration before making bulk changes.

***