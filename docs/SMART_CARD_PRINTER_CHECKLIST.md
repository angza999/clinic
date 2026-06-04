# Smart Card And Printer Checklist

## Smart Card

- Start Mosquitto.
- Start MOPH Smartcard Reader MQTT service.
- Start Dongmahawan Smart Card Bridge.
- Open `http://127.0.0.1:8189/health`.
- Expected:
  - `ok = true`
  - `mqtt.connected = true`
- In the clinic app, open Patient Registration and click "อ่านบัตรประชาชน".
- If no data appears:
  - Remove and reinsert the card.
  - Restart the bridge.
  - Restart the MOPH smartcard service.
  - Confirm the reader driver is installed.

## Printer

### Receipt

- Open a paid receipt.
- Click print.
- Confirm patient name, receipt number, totals, and after-care note if available.

### Medication Sticker

- Open Smart Exam with medicine lines.
- Click "พิมพ์สติ๊กเกอร์ยา".
- Preview labels before printing.
- Use 58x40 mm first.
- Set scale to 100%.
- Turn off browser header/footer.

## Deployment Note

Each new workstation must install the smart-card middleware and bridge locally. The web app alone cannot read a physical card without the local bridge/service layer.
