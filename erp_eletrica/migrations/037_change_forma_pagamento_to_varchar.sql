-- Fix: Convert forma_pagamento from ENUM to VARCHAR to prevent dropping/blanking new payment types like 'fiado'
ALTER TABLE vendas MODIFY COLUMN forma_pagamento VARCHAR(50) NOT NULL DEFAULT 'dinheiro';

-- Update the existing blank rows that were caused by this ENUM mismatch when 'fiado' was inserted
UPDATE vendas SET forma_pagamento = 'fiado' WHERE forma_pagamento = '';
