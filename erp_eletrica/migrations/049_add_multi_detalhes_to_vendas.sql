-- Migration 049: Add multi_detalhes to vendas table
ALTER TABLE vendas ADD COLUMN IF NOT EXISTS multi_detalhes VARCHAR(255) NULL AFTER forma_pagamento;
