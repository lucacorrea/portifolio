async function sendWebhook({ logger, payload }) {
  const url = process.env.WHATSAPP_WEBHOOK_URL || '';
  const secret = process.env.WHATSAPP_WEBHOOK_SECRET || '';
  if (!url) {
    return;
  }

  try {
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-SEMAS-Webhook-Secret': secret
      },
      body: JSON.stringify(payload)
    });

    if (!response.ok) {
      logger.warn('webhook_response_error', { status: response.status });
    }
  } catch (error) {
    logger.warn('webhook_send_failed', { message: error.message });
  }
}

module.exports = { sendWebhook };
