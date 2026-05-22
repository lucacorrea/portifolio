async function gerarEnviarOrcamento(id = 301) {
  try {
    App.toast('Gerando PDF do orçamento...');
    const pdf = await App.fetchJson(`api/gerar_orcamento_pdf.php?id=${encodeURIComponent(id)}`);
    if (!pdf.success) throw new Error(pdf.message || 'Falha ao gerar PDF.');
    const formData = new FormData();
    formData.append('id', id);
    formData.append('pdf_url', pdf.pdf_url);
    const send = await App.fetchJson('api/enviar_orcamento_whatsapp.php', { method: 'POST', body: formData });
    if (!send.success) throw new Error(send.message || 'Falha ao preparar WhatsApp.');
    if (send.mode === 'business_api') {
      App.toast('PDF enviado pela WhatsApp Business API configurada.');
    } else {
      App.toast('PDF gerado. Abrindo WhatsApp com mensagem e link seguro...');
      window.open(send.whatsapp_url, '_blank', 'noopener');
    }
  } catch (error) {
    App.toast(error.message);
  }
}

document.addEventListener('click', (event) => {
  const sendButton = event.target.closest('[data-send-budget]');
  if (sendButton) gerarEnviarOrcamento(sendButton.dataset.sendBudget);
  if (event.target.closest('#btnDemoWhatsapp')) gerarEnviarOrcamento(301);
});
