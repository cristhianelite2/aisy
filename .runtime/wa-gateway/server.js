const express = require('express');
const fs = require('fs');
const path = require('path');
const pino = require('pino');
const QRCode = require('qrcode');
const {
  default: makeWASocket,
  useMultiFileAuthState,
  DisconnectReason,
  fetchLatestBaileysVersion,
  downloadMediaMessage,
} = require('@whiskeysockets/baileys');

const PORT = parseInt(process.env.WA_GATEWAY_PORT || '8085', 10);
const SESSIONS_DIR = path.join(__dirname, 'sessions');
const META_FILE = path.join(SESSIONS_DIR, '_instances.json');

if (!fs.existsSync(SESSIONS_DIR)) {
  fs.mkdirSync(SESSIONS_DIR, { recursive: true });
}

const app = express();
app.use(express.json({ limit: '25mb' }));

const logger = pino({ level: 'silent' });
const instances = new Map();

function loadMeta() {
  try {
    if (fs.existsSync(META_FILE)) {
      return JSON.parse(fs.readFileSync(META_FILE, 'utf8'));
    }
  } catch (_) {}
  return {};
}

function saveMeta(meta) {
  try {
    fs.writeFileSync(META_FILE, JSON.stringify(meta, null, 2));
  } catch (_) {}
}

function normalizePhone(phone) {
  const digits = String(phone || '').replace(/\D/g, '');
  if (!digits) return '';
  return digits.includes('@') ? digits : `${digits}@s.whatsapp.net`;
}

async function startInstance(instanceName, webhookUrl = null) {
  if (!instanceName) throw new Error('instanceName required');

  const existing = instances.get(instanceName);
  if (existing && existing.sock && (existing.state === 'open' || existing.state === 'connecting')) {
    if (webhookUrl) {
      existing.webhookUrl = webhookUrl;
      const meta = loadMeta();
      meta[instanceName] = { webhookUrl };
      saveMeta(meta);
    }
    return existing;
  }

  const sessionPath = path.join(SESSIONS_DIR, instanceName);
  fs.mkdirSync(sessionPath, { recursive: true });

  const meta = loadMeta();
  const resolvedWebhook = webhookUrl || (meta[instanceName] && meta[instanceName].webhookUrl) || null;
  if (resolvedWebhook) {
    meta[instanceName] = { webhookUrl: resolvedWebhook };
    saveMeta(meta);
  }

  const entry = {
    instanceName,
    webhookUrl: resolvedWebhook,
    state: 'connecting',
    qrText: null,
    qrDataUrl: null,
    connectedPhone: null,
    sock: null,
  };
  instances.set(instanceName, entry);

  try {
    const { state, saveCreds } = await useMultiFileAuthState(sessionPath);
    let version;
    try {
      const latest = await fetchLatestBaileysVersion();
      version = latest.version;
    } catch (_) {
      version = [2, 3000, 1015901307];
    }

    const sock = makeWASocket({
      version,
      logger,
      auth: state,
      printQRInTerminal: false,
      browser: ['Aisy Assistant', 'Chrome', '1.0.0'],
      syncFullHistory: false,
    });
    entry.sock = sock;

    sock.ev.on('creds.update', saveCreds);

    sock.ev.on('connection.update', async (update) => {
      const { connection, lastDisconnect, qr } = update;
      if (qr) {
        entry.qrText = qr;
        entry.state = 'qr';
        try {
          entry.qrDataUrl = await QRCode.toDataURL(qr, { width: 320, margin: 2 });
        } catch (_) {}
      }
      if (connection === 'open') {
        entry.state = 'open';
        entry.qrText = null;
        entry.qrDataUrl = null;
        entry.connectedPhone = sock.user && sock.user.id ? sock.user.id.split(':')[0] : null;
      } else if (connection === 'close') {
        const statusCode = lastDisconnect?.error?.output?.statusCode;
        const shouldReconnect = statusCode !== DisconnectReason.loggedOut;
        entry.state = 'close';
        if (shouldReconnect) {
          setTimeout(() => startInstance(instanceName, entry.webhookUrl).catch(() => {}), 4000);
        } else {
          try {
            fs.rmSync(sessionPath, { recursive: true, force: true });
          } catch (_) {}
        }
      }
    });

    sock.ev.on('messages.upsert', async ({ messages, type }) => {
      if (type !== 'notify' || !entry.webhookUrl) return;
      for (const msg of messages) {
        if (!msg.message || msg.key.fromMe) continue;
        const remoteJid = msg.key.remoteJid || '';
        if (remoteJid.endsWith('@g.us') || remoteJid === 'status@broadcast') continue;

        const senderPhone = remoteJid.split('@')[0];
        let text =
          msg.message.conversation ||
          msg.message.extendedTextMessage?.text ||
          msg.message.imageMessage?.caption ||
          '';

        let audioBase64 = null;
        let audioMime = null;
        if (msg.message.audioMessage) {
          try {
            const buffer = await downloadMediaMessage(msg, 'buffer', {});
            audioBase64 = buffer.toString('base64');
            audioMime = msg.message.audioMessage.mimetype || 'audio/ogg';
          } catch (_) {}
        }

        let location = null;
        if (msg.message.locationMessage) {
          location = {
            lat: msg.message.locationMessage.degreesLatitude,
            lng: msg.message.locationMessage.degreesLongitude,
          };
        }

        try {
          await fetch(entry.webhookUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              instance: instanceName,
              sender_phone: senderPhone,
              text,
              audio_base64: audioBase64,
              audio_mime: audioMime,
              location,
              message_id: msg.key.id,
            }),
          });
        } catch (_) {}
      }
    });
  } catch (err) {
    entry.state = 'error';
    entry.error = err.message;
  }

  return entry;
}

app.get('/health', (_req, res) => {
  res.json({ ok: true, service: 'aisy-wa-gateway', instances: instances.size });
});

app.post('/instance/create', async (req, res) => {
  try {
    const { instanceName, webhookUrl } = req.body || {};
    if (!instanceName) return res.status(400).json({ ok: false, error: 'instanceName is required' });
    const entry = await startInstance(instanceName, webhookUrl || null);
    res.json({
      ok: true,
      instanceName: entry.instanceName,
      state: entry.state,
      qrcode: entry.qrDataUrl,
    });
  } catch (err) {
    res.status(500).json({ ok: false, error: err.message });
  }
});

app.get('/instance/connect/:instanceName', async (req, res) => {
  try {
    const { instanceName } = req.params;
    const entry = await startInstance(instanceName);
    // Wait briefly if QR is still generating
    for (let i = 0; i < 10 && !entry.qrDataUrl && entry.state !== 'open'; i++) {
      await new Promise((r) => setTimeout(r, 300));
    }
    res.json({
      ok: true,
      instanceName,
      state: entry.state,
      qrcode: entry.qrDataUrl,
      connectedPhone: entry.connectedPhone,
    });
  } catch (err) {
    res.status(500).json({ ok: false, error: err.message });
  }
});

app.get('/instance/status/:instanceName', (req, res) => {
  const entry = instances.get(req.params.instanceName);
  if (!entry) {
    return res.json({
      ok: true,
      instanceName: req.params.instanceName,
      state: 'disconnected',
      connectedPhone: null,
    });
  }
  res.json({
    ok: true,
    instanceName: entry.instanceName,
    state: entry.state,
    connectedPhone: entry.connectedPhone,
    hasQr: Boolean(entry.qrDataUrl),
  });
});

app.post('/message/sendText/:instanceName', async (req, res) => {
  try {
    const entry = instances.get(req.params.instanceName);
    const { number, text } = req.body || {};
    if (!entry || !entry.sock || entry.state !== 'open') {
      return res.status(409).json({ ok: false, error: 'Instance not connected' });
    }
    const jid = normalizePhone(number);
    const sent = await entry.sock.sendMessage(jid, { text: String(text || '') });
    res.json({ ok: true, id: sent?.key?.id || null });
  } catch (err) {
    res.status(500).json({ ok: false, error: err.message });
  }
});

app.post('/message/sendMedia/:instanceName', async (req, res) => {
  try {
    const entry = instances.get(req.params.instanceName);
    const { number, mediaUrl, caption } = req.body || {};
    if (!entry || !entry.sock || entry.state !== 'open') {
      return res.status(409).json({ ok: false, error: 'Instance not connected' });
    }
    const jid = normalizePhone(number);
    const sent = await entry.sock.sendMessage(jid, {
      image: { url: mediaUrl },
      caption: String(caption || ''),
    });
    res.json({ ok: true, id: sent?.key?.id || null });
  } catch (err) {
    res.status(500).json({ ok: false, error: err.message });
  }
});

// Restore previously saved instances on startup
const savedMeta = loadMeta();
for (const [name, cfg] of Object.entries(savedMeta)) {
  startInstance(name, cfg.webhookUrl).catch(() => {});
}

app.listen(PORT, '127.0.0.1', () => {
  console.log(`Aisy WA Gateway listening on http://127.0.0.1:${PORT}`);
});
