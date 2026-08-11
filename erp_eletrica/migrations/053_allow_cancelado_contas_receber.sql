ALTER TABLE contas_receber
MODIFY status ENUM('pendente','pago','atrasado','cancelado') DEFAULT 'pendente';
