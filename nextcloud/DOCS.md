![Nextcloud Icon](./icon.png)

![Working With Web](https://raw.githubusercontent.com/AlfonsoVertu/WorkingWithEuroOffice/master/www_logo.png)

# Nextcloud + ONLYOFFICE Integration

A powerful Nextcloud instance with OCR capabilities and automatic ONLYOFFICE integration.

## Features
- Nextcloud with full administrative control.
- Integrated OCR support.
- Pre-configured ONLYOFFICE connector.

## What we added to the standard container:
- **Auto-Installation Script**: A custom initialization script (`99-onlyoffice-setup.sh`) that automatically downloads, installs, and configures the ONLYOFFICE connector app on the first boot.
- **JWT Sync**: Seamlessly links with the EuroOffice add-on using shared JWT secrets defined in Home Assistant.
- **Multi-Arch Support**: Automated builds for both `amd64` and `aarch64`.

## Configuration & Integration
1. **Initial Setup**: On the first run, create your admin account. Use SQLite for a quick start or MariaDB for production.
2. **Connecting ONLYOFFICE**: The `onlyoffice_url` and `jwt_secret` options in the add-on configuration should point to your EuroOffice instance.
3. **Internal Networking**: If both are in HA, use `http://172.30.33.x:8080` (the EuroOffice IP) or the public IP/Domain if available.

## Troubleshooting
- **Permission Denied (Postgres)**: If using an external Postgres add-on, ensure you run `GRANT ALL ON SCHEMA public TO your_user;` in the database.
- **Connector Not Working**: Go to Nextcloud Settings -> Administration -> ONLYOFFICE and verify the server address and secret manually.
- **Memory**: Nextcloud requires at least 2GB of RAM to run smoothly with OCR.

## Official Documentation
- [Nextcloud Admin Manual](https://docs.nextcloud.com/server/latest/admin_manual/)
- [Nextcloud Community](https://help.nextcloud.com/)

## Credits
This project is maintained and optimized by **Alfonso Vertucci** of **Working With Web**.
Website: [workingwithweb.it/webagency](https://workingwithweb.it/webagency)

