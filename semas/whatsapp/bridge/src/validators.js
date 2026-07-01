function normalizePhone(phone) {
  let digits = String(phone || '').replace(/\D+/g, '');
  if (digits.length === 10 || digits.length === 11) {
    digits = `55${digits}`;
  }
  if (!digits.startsWith('55')) {
    return '';
  }
  const local = digits.slice(2);
  if (local.length !== 10 && local.length !== 11) {
    return '';
  }
  if (/^(\d)\1+$/.test(local)) {
    return '';
  }
  return digits;
}

function validateSendPayload(input) {
  const to = normalizePhone(input.to || input.number);
  const type = String(input.type || 'text');
  const text = String(input.text || '').trim();
  const idempotencyKey = String(input.idempotencyKey || '').trim();

  if (!to) {
    return { success: false, message: 'Telefone invalido.' };
  }
  if (type !== 'text') {
    return { success: false, message: 'Tipo de mensagem nao suportado.' };
  }
  if (!text || text.length > 3000) {
    return { success: false, message: 'Texto obrigatorio ou acima do limite.' };
  }
  if (!idempotencyKey || idempotencyKey.length > 120) {
    return { success: false, message: 'Idempotency key obrigatoria.' };
  }

  return { success: true, data: { to, type, text, idempotencyKey } };
}

function validatePairingPayload(input) {
  const phone = normalizePhone(input.phone || `${input.ddi || ''}${input.ddd || ''}${input.numero || ''}`);
  if (!phone) {
    return { success: false, message: 'Telefone invalido para pareamento.' };
  }
  return { success: true, data: { phone } };
}

function maskPhone(phone) {
  const digits = normalizePhone(phone);
  if (!digits) {
    return null;
  }
  return `${digits.slice(0, 2)}******${digits.slice(-4)}`;
}

module.exports = { normalizePhone, validateSendPayload, validatePairingPayload, maskPhone };
