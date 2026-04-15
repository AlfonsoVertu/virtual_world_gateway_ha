# EuroOffice Document Server

Collaborative ONLYOFFICE-based document server for Home Assistant.

## Features
- Fully compatible with ONLYOFFICE Document Server formats.
- Pre-installed collaborative editing server.
- Automatic JWT security token synchronization with Home Assistant.

## What we added to the standard container:
- **Smart Entrypoint**: A custom `run.sh` script that reads your Home Assistant add-on options and automatically configures JWT for both the Document Server and the Example App.
- **Port Mapping**: Simplified port exposure for Document Server (8080) and Example App (3000).
- **Automated Build**: CI/CD pipeline on GitHub for fast, optimized deployment on amd64 architectures.

## Credits
This project is maintained and optimized by **Alfonso Vertucci** of **Working With Web**.
Website: [workingwithweb.it/webagency](https://workingwithweb.it/webagency)
