ARG BUILD_FROM
FROM $BUILD_FROM

# Use the EuroOffice DocumentServer as the core
FROM ghcr.io/euro-office/documentserver:latest

# Home Assistant Add-on Labels
LABEL \
    io.hass.name="EuroOffice Document Server" \
    io.hass.description="Collabrative document editing in Home Assistant" \
    io.hass.arch="${BUILD_ARCH}" \
    io.hass.type="addon" \
    io.hass.version="1.0.0"

# Install dependencies for the startup script
RUN apt-get update && apt-get install -y jq python3 && rm -rf /var/lib/apt/lists/*

# Copy rootfs
COPY run.sh /
RUN chmod a+x /run.sh

ENTRYPOINT [ "/run.sh" ]
