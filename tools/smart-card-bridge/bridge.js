const http = require('http');
const net = require('net');

const HTTP_HOST = process.env.BRIDGE_HOST || '127.0.0.1';
const HTTP_PORT = Number(process.env.BRIDGE_PORT || 8189);
const MQTT_HOST = process.env.MQTT_HOST || '127.0.0.1';
const MQTT_PORT = Number(process.env.MQTT_PORT || 10883);
const MQTT_TOPIC = process.env.MQTT_TOPIC || 'moph/ict/mqtt';
const CARD_TTL_MS = Number(process.env.CARD_TTL_MS || 5 * 60 * 1000);

let mqttSocket = null;
let mqttBuffer = Buffer.alloc(0);
let mqttConnected = false;
let reconnectTimer = null;

let lastCard = null;
let lastRaw = null;
let lastReceivedAt = null;
let lastError = null;
let messageCount = 0;
let ignoredMessageCount = 0;
let cardPresent = false;
let cardStatus = null;
let pendingPhoto = '';

function nowIso() {
  return new Date().toISOString();
}

function json(res, statusCode, payload) {
  const body = JSON.stringify(payload);
  res.writeHead(statusCode, {
    'Content-Type': 'application/json; charset=utf-8',
    'Access-Control-Allow-Origin': '*',
    'Access-Control-Allow-Methods': 'GET, OPTIONS',
    'Access-Control-Allow-Headers': 'Content-Type',
    'Cache-Control': 'no-store',
  });
  res.end(body);
}

function normalizeCard(payload) {
  const data = payload && typeof payload === 'object'
    ? (payload.data || payload.card || payload.result || payload)
    : {};

  const get = (...keys) => {
    for (const key of keys) {
      if (data[key] !== undefined && data[key] !== null && String(data[key]).trim() !== '') {
        return String(data[key]).replace(/#/g, ' ').replace(/\s+/g, ' ').trim();
      }
    }
    return '';
  };

  const citizenId = get('citizen_id', 'citizenId', 'cid', 'pid', 'PID', 'CitizenNo', 'card_id', 'id_card').replace(/\D/g, '');
  const birthDate = normalizeDate(get('birth_date', 'birthDate', 'birthdate', 'BirthDate', 'dob'));

  const getRaw = (...keys) => {
    for (const key of keys) {
      if (data[key] !== undefined && data[key] !== null && String(data[key]).trim() !== '') {
        return String(data[key]).trim();
      }
    }
    return '';
  };

  const thaiFullName = getRaw('th_fullname', 'ThaiName', 'thai_name', 'fullname_th', 'fullNameTh');
  const splitThaiName = splitThaiFullName(thaiFullName);

  return {
    citizen_id: citizenId,
    title_name: get('title_name', 'titleName', 'titleTH', 'prefixTH', 'PrefixTH', 'ThaiTitle') || splitThaiName.title_name,
    first_name: get('first_name', 'firstName', 'firstname', 'firstnameTH', 'firstNameTh', 'FirstNameTh', 'fname', 'ThaiFirstName') || splitThaiName.first_name,
    last_name: get('last_name', 'lastName', 'lastname', 'lastnameTH', 'lastNameTh', 'LastNameTh', 'lname', 'ThaiLastName') || splitThaiName.last_name,
    gender: normalizeGender(get('gender', 'sex', 'Sex', 'Gender')),
    birth_date: birthDate,
    address: get('address', 'addressTH', 'Address', 'addr', 'ThaiAddress'),
    photo: normalizePhoto(findPhotoValue(payload)),
  };
}

function findPhotoValue(value, depth = 0) {
  if (!value || depth > 5) return '';

  if (typeof value === 'string') {
    const trimmed = value.trim();
    if (looksLikeImageValue(trimmed)) return trimmed;
    return '';
  }

  if (Array.isArray(value)) {
    for (const item of value) {
      const found = findPhotoValue(item, depth + 1);
      if (found) return found;
    }
    return '';
  }

  if (typeof value !== 'object') return '';

  const directKeys = [
    'photo', 'photo_base64', 'photoBase64', 'image', 'image_base64', 'imageBase64',
    'portrait', 'portrait_base64', 'picture', 'picture_base64', 'card_photo',
    'cardPhoto', 'face', 'faceImage', 'jpeg', 'jpg',
  ];

  for (const key of directKeys) {
    if (Object.prototype.hasOwnProperty.call(value, key)) {
      const found = findPhotoValue(value[key], depth + 1);
      if (found) return found;
    }
  }

  for (const nested of Object.values(value)) {
    const found = findPhotoValue(nested, depth + 1);
    if (found) return found;
  }

  return '';
}

function looksLikeImageValue(value) {
  if (!value) return false;
  if (/^data:image\/(jpeg|jpg|png|webp);base64,/i.test(value)) return true;
  const compact = value.replace(/\s+/g, '');
  if (compact.length < 500) return false;
  if (!/^[A-Za-z0-9+/=]+$/.test(compact)) return false;
  return compact.startsWith('/9j/') || compact.startsWith('iVBOR') || compact.startsWith('UklGR');
}

function normalizePhoto(value) {
  if (!value) return '';
  const compact = String(value).trim().replace(/\s+/g, '');
  if (/^data:image\//i.test(compact)) return compact;
  if (compact.startsWith('iVBOR')) return `data:image/png;base64,${compact}`;
  if (compact.startsWith('UklGR')) return `data:image/webp;base64,${compact}`;
  return `data:image/jpeg;base64,${compact}`;
}

function splitThaiFullName(value) {
  const parts = String(value || '')
    .split('#')
    .map((part) => part.trim())
    .filter(Boolean);

  if (parts.length >= 3) {
    return {
      title_name: parts[0],
      first_name: parts[1],
      last_name: parts.slice(2).join(' '),
    };
  }

  if (parts.length === 2) {
    return {
      title_name: '',
      first_name: parts[0],
      last_name: parts[1],
    };
  }

  return {
    title_name: '',
    first_name: '',
    last_name: '',
  };
}

function normalizeGender(value) {
  if (['1', 'M', 'm', 'ชาย'].includes(value)) return 'M';
  if (['2', 'F', 'f', 'หญิง'].includes(value)) return 'F';
  return '';
}

function normalizeDate(value) {
  const raw = String(value || '').replace(/\D/g, '');
  if (raw.length !== 8) return '';
  let year = Number(raw.slice(0, 4));
  const month = raw.slice(4, 6);
  const day = raw.slice(6, 8);
  if (year > 2400) year -= 543;
  const date = new Date(`${year.toString().padStart(4, '0')}-${month}-${day}T00:00:00Z`);
  if (Number.isNaN(date.getTime())) return '';
  return `${year.toString().padStart(4, '0')}-${month}-${day}`;
}

function cachePayload(rawMessage) {
  try {
    const payload = JSON.parse(rawMessage);
    if (payload && typeof payload === 'object' && typeof payload.status === 'string') {
      cardStatus = payload.status;
      if (payload.status === 'CARD_ENTERED') {
        cardPresent = true;
      }
      if (payload.status === 'CARD_EXITED') {
        cardPresent = false;
        lastCard = null;
        lastRaw = null;
        lastReceivedAt = null;
        pendingPhoto = '';
      }
    }

    const card = normalizeCard(payload);

    if (!card.citizen_id && !card.first_name && !card.last_name) {
      if (card.photo && lastCard) {
        lastCard = { ...lastCard, photo: card.photo };
        lastRaw = { ...(lastRaw || {}), image_payload: payload };
        lastReceivedAt = nowIso();
        lastError = null;
        console.log(`[${lastReceivedAt}] card photo payload received messages=${messageCount}`);
        return;
      }

      if (card.photo) {
        pendingPhoto = card.photo;
        lastError = null;
        console.log(`[${nowIso()}] card photo payload waiting for card data`);
        return;
      }

      ignoredMessageCount += 1;
      lastError = `ignored_non_card_payload: ${payload.status || payload.from || 'unknown'}`;
      console.log(`[${nowIso()}] ignored non-card payload status=${payload.status || '-'} ignored=${ignoredMessageCount}`);
      return;
    }

    if (!card.photo) {
      if (lastCard && lastCard.photo && lastCard.citizen_id === card.citizen_id) {
        card.photo = lastCard.photo;
      } else if (pendingPhoto) {
        card.photo = pendingPhoto;
      }
    }

    lastRaw = payload;
    lastCard = card;
    cardPresent = true;
    lastReceivedAt = nowIso();
    lastError = null;
    messageCount += 1;
    console.log(`[${lastReceivedAt}] card payload received citizen=${card.citizen_id || '-'} messages=${messageCount}`);
  } catch (error) {
    lastError = `invalid_json: ${error.message}`;
    console.warn(`[${nowIso()}] invalid payload: ${error.message}`);
  }
}

function mqttRemainingLength(length) {
  const bytes = [];
  do {
    let byte = length % 128;
    length = Math.floor(length / 128);
    if (length > 0) byte |= 128;
    bytes.push(byte);
  } while (length > 0);
  return Buffer.from(bytes);
}

function mqttString(value) {
  const body = Buffer.from(value);
  const header = Buffer.alloc(2);
  header.writeUInt16BE(body.length, 0);
  return Buffer.concat([header, body]);
}

function mqttConnectPacket(clientId) {
  const variableHeader = Buffer.concat([
    mqttString('MQTT'),
    Buffer.from([0x04, 0x02, 0x00, 0x1e]),
  ]);
  const payload = mqttString(clientId);
  return Buffer.concat([Buffer.from([0x10]), mqttRemainingLength(variableHeader.length + payload.length), variableHeader, payload]);
}

function mqttSubscribePacket(topic) {
  const variableHeader = Buffer.from([0x00, 0x01]);
  const payload = Buffer.concat([mqttString(topic), Buffer.from([0x00])]);
  return Buffer.concat([Buffer.from([0x82]), mqttRemainingLength(variableHeader.length + payload.length), variableHeader, payload]);
}

function parseMqttPackets() {
  while (mqttBuffer.length >= 2) {
    const type = mqttBuffer[0] >> 4;
    let multiplier = 1;
    let remainingLength = 0;
    let offset = 1;
    let encodedByte = 0;

    do {
      if (offset >= mqttBuffer.length) return;
      encodedByte = mqttBuffer[offset++];
      remainingLength += (encodedByte & 127) * multiplier;
      multiplier *= 128;
    } while ((encodedByte & 128) !== 0);

    if (mqttBuffer.length < offset + remainingLength) return;

    const payload = mqttBuffer.slice(offset, offset + remainingLength);
    mqttBuffer = mqttBuffer.slice(offset + remainingLength);

    if (type === 2) {
      mqttConnected = true;
      mqttSocket.write(mqttSubscribePacket(MQTT_TOPIC));
      console.log(`[${nowIso()}] MQTT connected and subscribed ${MQTT_TOPIC}`);
      continue;
    }

    if (type === 3) {
      const topicLength = payload.readUInt16BE(0);
      const message = payload.slice(2 + topicLength).toString('utf8').trim();
      if (message) cachePayload(message);
    }
  }
}

function scheduleReconnect() {
  mqttConnected = false;
  if (reconnectTimer) return;
  reconnectTimer = setTimeout(() => {
    reconnectTimer = null;
    connectMqtt();
  }, 2500);
}

function connectMqtt() {
  if (mqttSocket) {
    mqttSocket.destroy();
    mqttSocket = null;
  }

  mqttSocket = net.createConnection({ host: MQTT_HOST, port: MQTT_PORT }, () => {
    lastError = null;
    mqttBuffer = Buffer.alloc(0);
    mqttSocket.write(mqttConnectPacket(`dongmahawan-bridge-${Date.now()}`));
  });

  mqttSocket.on('data', (chunk) => {
    mqttBuffer = Buffer.concat([mqttBuffer, chunk]);
    parseMqttPackets();
  });

  mqttSocket.on('error', (error) => {
    lastError = `mqtt_error: ${error.message}`;
  });

  mqttSocket.on('close', () => {
    scheduleReconnect();
  });
}

function hasFreshCard(since = null) {
  if (!lastCard || !lastReceivedAt) return false;
  if (!cardPresent) return false;
  const receivedTime = new Date(lastReceivedAt).getTime();
  if (Date.now() - receivedTime > CARD_TTL_MS) return false;
  if (since) {
    const sinceTime = new Date(since).getTime();
    if (!Number.isNaN(sinceTime) && receivedTime < sinceTime) return false;
  }
  return true;
}

function bridgeStatus() {
  return {
    ok: true,
    service: 'Dongmahawan Smart Card Bridge',
    mqtt: {
      host: MQTT_HOST,
      port: MQTT_PORT,
      topic: MQTT_TOPIC,
      connected: mqttConnected,
    },
    cache: {
      has_card: hasFreshCard(),
      card_present: cardPresent,
      last_status: cardStatus,
      received_at: lastReceivedAt,
      ttl_ms: CARD_TTL_MS,
      message_count: messageCount,
      ignored_message_count: ignoredMessageCount,
      has_photo: Boolean(lastCard && lastCard.photo),
    },
    last_error: lastError,
  };
}

const server = http.createServer((req, res) => {
  if (req.method === 'OPTIONS') {
    json(res, 200, { ok: true });
    return;
  }

  const url = new URL(req.url, `http://${HTTP_HOST}:${HTTP_PORT}`);

  if (url.pathname === '/health' || url.pathname === '/status') {
    json(res, 200, bridgeStatus());
    return;
  }

  if (url.pathname === '/read' || url.pathname === '/last' || url.pathname === '/json') {
    const since = url.searchParams.get('since');
    if (!hasFreshCard(since)) {
      json(res, 404, {
        success: false,
        message: since
          ? 'ยังไม่พบบัตรที่เสียบใหม่ กรุณากดอ่านบัตร แล้วเสียบบัตรประชาชนตอนที่ระบบกำลังรออ่าน'
          : 'ยังไม่มีข้อมูลบัตรล่าสุด กรุณาเสียบบัตรประชาชนใหม่อีกครั้ง',
        status: bridgeStatus(),
      });
      return;
    }

    json(res, 200, {
      success: true,
      data: lastCard,
      raw: url.searchParams.get('raw') === '1' ? lastRaw : undefined,
      received_at: lastReceivedAt,
      source: `mqtt://${MQTT_HOST}:${MQTT_PORT}/${MQTT_TOPIC}`,
    });
    return;
  }

  json(res, 404, {
    success: false,
    message: 'Not found',
    endpoints: ['/health', '/read', '/last', '/json'],
  });
});

server.listen(HTTP_PORT, HTTP_HOST, () => {
  console.log(`[${nowIso()}] Dongmahawan Smart Card Bridge http://${HTTP_HOST}:${HTTP_PORT}`);
  connectMqtt();
});

process.on('SIGINT', () => {
  server.close();
  if (mqttSocket) mqttSocket.destroy();
  process.exit(0);
});
