# WorkingWithEuroOffice - Home Assistant Add-ons

[![Repository](https://img.shields.io/badge/GitHub-WorkingWithEuroOffice-blue?logo=github)](https://github.com/AlfonsoVertu/WorkingWithEuroOffice)

A collection of Home Assistant add-ons for collaborative document editing using **EuroOffice** (ONLYOFFICE-based Document Server) and **Nextcloud**, with full JWT integration out of the box.

## Add-ons available

| Add-on | Description | Version |
|--------|-------------|---------|
| [EuroOffice Document Server](#eurooffice-document-server) | Collaborative document editing server | 1.0.0 |
| [Nextcloud + ONLYOFFICE](#nextcloud--onlyoffice) | Nextcloud with ONLYOFFICE pre-installed | 33.0.2 |

---

## How to add this repository to Home Assistant

1. Go to **Settings** → **Add-ons** → **Add-on Store**
2. Click the three-dot menu (top right) → **Repositories**
3. Paste this URL and click **Add**:
   ```
   https://github.com/AlfonsoVertu/WorkingWithEuroOffice
   ```
4. The add-ons will appear in the store.

---

## EuroOffice Document Server

A Home Assistant add-on running the **EuroOffice Document Server** (based on `ghcr.io/euro-office/documentserver`), providing collaborative editing for DOCX, XLSX, PPTX and more.

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `jwt_enabled` | `bool` | `true` | Enable/disable JWT token validation |
| `jwt_secret` | `password` | `my_jwt_secret` | Secret used to sign and verify JWT tokens |
| `example_enabled` | `bool` | `true` | Enable the built-in test/example application |
| `document_server_port` | `int` | `8080` | Port exposed for the Document Server |
| `example_app_port` | `int` | `3000` | Port exposed for the Example App |

> **Note:** The `jwt_secret` must match exactly between EuroOffice and the Nextcloud ONLYOFFICE app configuration to allow document editing.

---

## Nextcloud + ONLYOFFICE

A Home Assistant add-on running **Nextcloud** (with OCR support) based on [alexbelgium/hassio-addons](https://github.com/alexbelgium/hassio-addons/tree/master/nextcloud).

The ONLYOFFICE application is **automatically installed and configured** at first startup using the values you set in the add-on options.

### Configuration Options

All original options from alexbelgium are preserved, plus:

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `onlyoffice_url` | `str` | `http://eurooffice:8080/` | URL of the EuroOffice Document Server |
| `onlyoffice_jwt` | `password` | `my_jwt_secret` | JWT secret shared with EuroOffice |
| `PUID` | `int` | `1000` | User ID for Nextcloud process |
| `PGID` | `int` | `1000` | Group ID for Nextcloud process |
| `trusted_domains` | `str` | - | Comma-separated list of trusted domains |
| `use_own_certs` | `bool` | `false` | Use custom SSL certificates |
| `certfile` | `str` | `fullchain.pem` | SSL certificate file name |
| `keyfile` | `str` | `privkey.pem` | SSL key file name |
| `enable_thumbnails` | `bool` | `true` | Enable file thumbnail generation |
| `OCR` | `bool` | `false` | Enable OCR text recognition |
| `OCRLANG` | `str` | `fra` | OCR language code |
| `Full_Text_Search` | `bool` | `false` | Enable full-text search (requires Elasticsearch) |
| `elasticsearch_server` | `str` | - | Elasticsearch host:port |
| `additional_apps` | `str` | `inotify-tools` | Extra packages to install |
| `localdisks` | `str` | - | Local disk paths to mount |
| `networkdisks` | `str` | - | Network shares to mount (SMB/NFS) |

### Connecting EuroOffice and Nextcloud

1. Install and start the **EuroOffice Document Server** add-on first.
2. Note the `jwt_secret` you configured in EuroOffice.
3. In the **Nextcloud + ONLYOFFICE** add-on, set:
   - `onlyoffice_url` → URL of your EuroOffice instance (e.g. `http://homeassistant.local:8080/`)
   - `onlyoffice_jwt` → same secret used in EuroOffice
4. Start the Nextcloud add-on. The ONLYOFFICE app will be installed and configured automatically.

---

## License

MIT License — see [LICENSE](LICENSE) for details.
