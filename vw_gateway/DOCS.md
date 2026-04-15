# Virtual World Gateway (WordPress)

WordPress-based middleware gateway designed to bridge AI (GPTs) and Home Assistant.

## Features
- Dedicated WordPress instance inside Home Assistant.
- Acting as a secure REST Gateway.
- Pre-installed **Virtual World Gate Key** plugin.

## What we added to the standard container:
- **Automated Bootstrap**: A custom `run.sh` entrypoint that uses `wp-cli` to handle installation, hardening, and plugin activation automatically.
- **Zero-Touch Config**: Automatically reads database and HA settings from your add-on options.
- **Security Hardening**: Pre-configured with login-only mode and anti-indexing defaults.
- **AI-Ready**: Designed specifically to work as a control plane for GPT Actions.

## Credits
This project is maintained and optimized by **Alfonso Vertucci** of **Working With Web**.
Website: [workingwithweb.it/webagency](https://workingwithweb.it/webagency)
