# Dongmahawan Smart Card Bridge

Local bridge for Thai ID smart card workflows in Dongmahawan Clinic.

## What it does

- Subscribes to MOPH Smartcard Reader MQTT on `127.0.0.1:10883`
- Listens to topic `moph/ict/mqtt`
- Keeps the latest card payload in memory
- Exposes simple HTTP endpoints for the PHP app

## Run

```bat
tools\smart-card-bridge\start-bridge.bat
```

or:

```bat
node tools\smart-card-bridge\bridge.js
```

## Endpoints

- `GET http://127.0.0.1:8189/health`
- `GET http://127.0.0.1:8189/read`
- `GET http://127.0.0.1:8189/last`
- `GET http://127.0.0.1:8189/json`

## Environment

```bat
set BRIDGE_PORT=8189
set MQTT_HOST=127.0.0.1
set MQTT_PORT=10883
set MQTT_TOPIC=moph/ict/mqtt
set CARD_TTL_MS=300000
```

## New Workstation Setup

Install on every workstation that has a card reader:

1. Smart card reader driver
2. MOPH Smartcard Reader MQTT
3. Mosquitto broker
4. Node.js runtime
5. Dongmahawan Smart Card Bridge

The browser must run on the same workstation as the bridge because the app calls `localhost`.
