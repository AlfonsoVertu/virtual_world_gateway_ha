#!/usr/bin/with-contenv bashio
set -e

CONFIG_PATH=/data/options.json

JWT_ENABLED=$(jq --raw-output '.jwt_enabled' $CONFIG_PATH)
JWT_SECRET=$(jq --raw-output '.jwt_secret' $CONFIG_PATH)
EXAMPLE_ENABLED=$(jq --raw-output '.example_enabled' $CONFIG_PATH)

echo "Configuring EuroOffice Document Server..."

# Update Document Server config (local.json)
PYTHON_SCRIPT="
import json
import os

local_json = '/etc/onlyoffice/documentserver/local.json'
with open(local_json, 'r') as f:
    data = json.load(f)

# Configure JWT
data['services']['CoAuthoring']['token']['enable']['request']['inbox'] = $JWT_ENABLED.lower() == 'true'
data['services']['CoAuthoring']['token']['enable']['request']['outbox'] = $JWT_ENABLED.lower() == 'true'
data['services']['CoAuthoring']['token']['enable']['browser'] = $JWT_ENABLED.lower() == 'true'

secret = '$JWT_SECRET'
data['services']['CoAuthoring']['secret']['inbox']['string'] = secret
data['services']['CoAuthoring']['secret']['outbox']['string'] = secret
data['services']['CoAuthoring']['secret']['browser']['string'] = secret
data['services']['CoAuthoring']['secret']['session']['string'] = secret

with open(local_json, 'w') as f:
    json.dump(data, f, indent=2)
"

python3 -c "$PYTHON_SCRIPT"

# Update Example App config (default.json)
if [ "$EXAMPLE_ENABLED" == "true" ]; then
    echo "Enabling Example Application..."
    EXAMPLE_SCRIPT="
import json
example_json = '/etc/onlyoffice/documentserver-example/default.json'
with open(example_json, 'r') as f:
    data = json.load(f)

data['server']['token']['enable'] = $JWT_ENABLED.lower() == 'true'
data['server']['token']['secret'] = '$JWT_SECRET'

with open(example_json, 'w') as f:
    json.dump(data, f, indent=2)
"
    python3 -c "$EXAMPLE_SCRIPT"
fi

echo "Starting services..."
# The EuroOffice image uses supervisor to manage services.
# We start supervisord in the background or foreground as needed.
/usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf
