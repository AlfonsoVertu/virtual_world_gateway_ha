![Odoo Icon](./icon.png)

![Working With Web](https://raw.githubusercontent.com/AlfonsoVertu/WorkingWithEuroOffice/master/www_logo.png)

# Odoo 16 for Home Assistant

Enterprise Resource Planning (ERP) and business applications suite inside Home Assistant.

## Features
- Fully functional Odoo 16.0 instance.
- Dedicated configuration for PostgreSQL connection.
- Persistent data storage.

## What we added to the standard container:
- **HA Integration Script**: A `run.sh` entrypoint that automatically maps your Home Assistant add-on options to the `odoo.conf` file.
- **Security Integration**: Master Password and Proxy Mode are pre-exposed in the HA UI.
- **Multi-Arch Support**: Automated builds for both `amd64` and `aarch64`.

## Configuration & Integration
1. **First Boot**: Use the **Master Password** to create your primary database.
2. **PostgreSQL**: Odoo requires a Postgres database. Use your `Postgres 17` add-on credentials.
3. **Proxy**: If using Nginx Proxy Manager, ensure `proxy_mode` is set to `true` in the configuration.

## Troubleshooting
- **Database Connection Failed**: Odoo 16 expects a specific Postgres schema. Ensure the user has full permissions: `ALTER SCHEMA public OWNER TO your_user;`.
- **Slowness**: Odoo is resource-intensive. Ensure your host has at least 4GB of RAM.
- **Internal Error 500**: Check the add-on logs. Usually related to missing database permissions or mismatched `admin_passwd`.

## Official Documentation
- [Odoo 16.0 Documentation](https://www.odoo.com/documentation/16.0/)
- [Odoo Community Forums](https://www.odoo.com/forum)

## Credits
This project is maintained and optimized by **Alfonso Vertucci** of **Working With Web**.
Website: [workingwithweb.it/webagency](https://workingwithweb.it/webagency)

