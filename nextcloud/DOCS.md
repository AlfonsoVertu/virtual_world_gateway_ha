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

## Credits
This project is maintained and optimized by **Alfonso Vertucci** of **Working With Web**.
Website: [workingwithweb.it/webagency](https://workingwithweb.it/webagency)
