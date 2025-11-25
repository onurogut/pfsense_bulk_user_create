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
| `client_specific_32_to_16.php` | **CSO Subnet Mask Changer:** Iterates through Client Specific Overrides and changes the `tunnel_network` subnet mask (e.g., from `/32` to `/16`) for CSOs within a defined IP range. | None. |

***

## 🛠️ Configuration Required (MUST EDIT)

Before executing any script, you **must** edit the variables under the `// Configuration` or `// SETTINGS` section to match your pfSense environment.

### 1. `pfsense_add_multiple_users.php`

| Variable | Description | Example Value |
| :--- | :--- | :--- |
| `\$password_prefix` | Base string for auto-generated passwords. | `"VPN-User-"` |
| `\$start_ip` / `\$end_ip` | The **Client Tunnel IP Range** for which users will be created (and used for Common Name). | `"10.8.0.3"` - `"10.8.0.31"` |
| `\$ca_name` | The exact description/name of your Certificate Authority (CA) in pfSense. | `"My_VPN_CA"` |
| `\$openvpn_server_id` | The ID of your OpenVPN server (usually `1`). | `1` |
| `\$export_enabled` / `\$create_zip` | Control whether OVPN files are generated and zipped. | `true` |

### 2. `pfsense_export_ovpns.php`

| Variable | Description | Example Value |
| :--- | :--- | :--- |
| `\$vpn_server_host` | The WAN IP or hostname that clients will connect to. **REQUIRED.** | `"vpn.yourdomain.com"` |
| `\$vpn_server_port` | The OpenVPN server port. | `"1194"` |
| `\$vpn_protocol` | The OpenVPN server protocol (`udp` or `tcp`). | `"udp"` |
| `\$ca_name` | The exact description/name of your Certificate Authority (CA). | `"My_VPN_CA"` |
| `\$username_pattern`| Only export users whose Common Name starts with this string. | `"10.8.0."` |

### 3. `client_specific_32_to_16.php`

| Variable | Description | Example Value |
| :--- | :--- | :--- |
| `\$start_ip` / `\$end_ip` | The IP range of CSOs to apply the mask change to. | `"10.8.0.3"` - `"10.8.0.31"` |
| `\$old_mask` | The mask to search for and replace. | `"/32"` |
| `\$new_mask` | The new mask to apply. | `"/16"` |

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

# pfSense OpenVPN Otomasyon Scriptleri (Turkish)

Bu script koleksiyonu, pfSense güvenlik duvarlarında **OpenVPN kullanıcı yönetimi** ve **yapılandırması** ile ilgili tekrarlayan görevleri otomatikleştirmek için tasarlanmış PHP dosyalarından oluşmaktadır.

Bu scriptler, doğrudan pfSense yapılandırması (`config.xml`) ile etkileşime girer ve pfSense cihazının kendi komut satırı (SSH/Diagnostics > Command Prompt) üzerinden çalıştırılmalıdır.

***

## ⚠️ UYARI / Sorumluluk Reddi

**BU SCRİPTLERİ KENDİ SORUMLULUĞUNUZDA KULLANIN.** pfSense yapılandırmasını doğrudan manipüle etmek, yanlış yapıldığında sistem dengesizliğine yol açabilir. **Herhangi bir scripti çalıştırmadan önce mutlaka yapılandırmanızın yedeğini alın.**

Yapılandırmanızın yedeğini **Diagnostics > Backup & Restore** üzerinden alabilirsiniz.

***

## ⚙️ Scriptler ve İşlevleri

| Dosya Adı | Açıklama | Ön Koşullar |
| :--- | :--- | :--- |
| `pfsense_add_multiple_users.php` | **Toplu Kullanıcı Oluşturma ve Export:** Tanımlanan bir IP aralığına göre kullanıcılar oluşturur, sertifikaları üretir, Client Specific Override (CSO) ekler ve son `.ovpn` dosyalarını dışa aktarır. | `openvpn-client-export` paketinin kurulu olması gerekir. |
| `pfsense_export_ovpns.php` | **Mevcut Kullanıcılar İçin OVPN Export:** Sertifikası olan mevcut kullanıcıları (bir desene uyanları) tarar ve onlar için manuel olarak `.ovpn` yapılandırma dosyaları oluşturur. Bağımsız bir araç veya yedek olarak kullanışlıdır. | Scriptteki VPN Sunucusu ayrıntılarının doğru yapılandırılmasını gerektirir. |
| `client_specific_32_to_16.php` | **CSO Alt Ağ Maskesi Değiştirici:** Client Specific Overrides'ı (CSO) yineler ve tanımlanan bir IP aralığındaki CSO'ların `tunnel_network` alt ağ maskesini (örneğin `/32`'den `/16`'ya) toplu olarak değiştirir. | Yok. |

***

## 🛠️ Gerekli Yapılandırma (DÜZENLENMELİ)

Herhangi bir scripti çalıştırmadan önce, pfSense ortamınıza uyacak şekilde `// Configuration` veya `// SETTINGS` bölümündeki değişkenleri **mutlaka** düzenlemelisiniz.

### 1. `pfsense_add_multiple_users.php`

| Değişken | Açıklama | Örnek Değer |
| :--- | :--- | :--- |
| `\$password_prefix` | Otomatik oluşturulan parolalar için temel dize. | `"VPN-User-"` |
| `\$start_ip` / `\$end_ip` | Kullanıcıların oluşturulacağı (ve Common Name olarak kullanılacağı) **Client Tunnel IP Aralığı**. | `"10.8.0.3"` - `"10.8.0.31"` |
| `\$ca_name` | pfSense'teki Sertifika Otoritenizin (CA) tam açıklaması/adı. | `"My_VPN_CA"` |
| `\$openvpn_server_id` | OpenVPN sunucunuzun ID'si (genellikle `1`). | `1` |
| `\$export_enabled` / `\$create_zip` | OVPN dosyalarının oluşturulup ZIP'lenip ZIP'lenmeyeceğini kontrol eder. | `true` |

### 2. `pfsense_export_ovpns.php`

| Değişken | Açıklama | Örnek Değer |
| :--- | :--- | :--- |
| `\$vpn_server_host` | İstemcilerin bağlanacağı WAN IP'si veya hostname'i. **GEREKLİ.** | `"vpn.alanadiniz.com"` |
| `\$vpn_server_port` | OpenVPN sunucu portu. | `"1194"` |
| `\$vpn_protocol` | OpenVPN sunucu protokolü (`udp` veya `tcp`). | `"udp"` |
| `\$ca_name` | Sertifika Otoritenizin (CA) tam açıklaması/adı. | `"My_VPN_CA"` |
| `\$username_pattern`| Yalnızca Common Name'i bu dizeyle başlayan kullanıcıları dışa aktarır. | `"10.8.0."` |

### 3. `client_specific_32_to_16.php`

| Değişken | Açıklama | Örnek Değer |
| :--- | :--- | :--- |
| `\$start_ip` / `\$end_ip` | Maske değişikliğinin uygulanacağı CSO'ların IP aralığı. | `"10.8.0.3"` - `"10.8.0.31"` |
| `\$old_mask` | Aranacak ve değiştirilecek maske. | `"/32"` |
| `\$new_mask` | Uygulanacak yeni maske. | `"/16"` |

***

## 🚀 Kurulum ve Kullanım

1.  **Yapılandırma:** İstediğiniz scripti açın ve yukarıdaki **Gerekli Yapılandırma** bölümünde listelenen değişkenleri ayarlayın.
2.  **Yedekleme:** pfSense yapılandırmanızın tam bir yedeğini alın.
3.  **Transfer:** Yapılandırılmış PHP script(ler)ini pfSense makinenizdeki `/tmp` dizinine yükleyin.

### A. pfSense Web Arayüzü (GUI) Üzerinden Çalıştırma (Hızlı kullanım için önerilir)

1.  **Transfer:**
    * pfSense web arayüzünde **Diagnostics > Command Prompt** sayfasına gidin.
    * Sayfanın altındaki **Upload File** (Dosya Yükle) bölümünü kullanın, PHP dosyanızı seçin ve **Upload** butonuna tıklayın. Dosya `/tmp` dizinine yüklenecektir.
2.  **Çalıştırma:**
    * Aynı sayfada, **Execute Shell Command** (Kabuk Komutu Çalıştır) alanına, yüklediğiniz scriptin komutunu girin (gerekirse dosya adını değiştirin):
        ```bash
        php /tmp/pfsense_add_multiple_users.php
        ```
    * **Execute** (Çalıştır) butonuna tıklayın.
    > 
3.  **Çıktıyı İndirme:** Çalıştırmadan sonra oluşan `.zip` dosyasını, aynı Command Prompt sayfasındaki **Download File** (Dosya İndir) bölümünü kullanarak indirin.

### B. SSH / Konsol Üzerinden Çalıştırma

1.  **Transfer:** Secure Copy Protocol (`scp`) veya herhangi bir dosya transfer aracını kullanarak script(ler)i pfSense makinenizdeki `/tmp` dizinine kopyalayın.
    ```bash
    # Scripti pfSense'e kopyalama komutu:
    scp /dizin/yerel/script_adi.php root@pfsense-ip:/tmp/
    ```
2.  **Çalıştırma:**
    * SSH veya fiziksel konsol aracılığıyla pfSense cihazınıza bağlanın.
    * Scripti `php` komutu ile çalıştırın:
        ```bash
        php /tmp/pfsense_add_multiple_users.php
        ```
3.  **Çıktıyı İndirme:** Oluşan `.zip` dosyasını `/tmp` dizininden yerel makinenize geri indirmek için `scp` kullanın.

***
