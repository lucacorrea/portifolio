(function (root) {
  'use strict';

  const Urbanix = root.Urbanix = root.Urbanix || {};
  const nowIso = () => new Date().toISOString();

  function createUnits() {
    const statuses = [
      'sold', 'reserved', 'available', 'sold', 'available', 'blocked', 'available', 'available',
      'available', 'available', 'sold', 'available', 'blocked', 'available', 'available', 'sold',
      'available', 'sold', 'available', 'blocked', 'available', 'available', 'sold', 'reserved',
      'sold', 'available', 'blocked', 'available', 'available', 'sold', 'available', 'available'
    ];

    const mappedUnits = ['A', 'B', 'C', 'D'].flatMap((blockCode, blockIndex) =>
      Array.from({ length: 8 }, (_, index) => {
        const number = index + 1;
        const sequence = (blockIndex * 8) + index;
        return {
          id: `unit-amz-${blockCode.toLowerCase()}${String(number).padStart(2, '0')}`,
          enterpriseId: 'ent-amazonas',
          blockId: `block-amz-${blockCode.toLowerCase()}`,
          code: `${blockCode}-${String(number).padStart(2, '0')}`,
          area: 250,
          frontage: 10,
          depth: 25,
          listPrice: 86250 + (sequence * 1250),
          status: statuses[sequence],
          ownerCustomerId: null,
          reservationId: null,
          saleId: null
        };
      })
    );
    return mappedUnits.concat([
      { id: 'unit-aguas-t204', enterpriseId: 'ent-aguas', blockId: null, code: 'T-204', area: 74, frontage: null, depth: null, listPrice: 240000, status: 'sold', ownerCustomerId: null, reservationId: null, saleId: null },
      { id: 'unit-sol-c12', enterpriseId: 'ent-sol', blockId: null, code: 'C-12', area: 68, frontage: 8, depth: 20, listPrice: 156000, status: 'sold', ownerCustomerId: null, reservationId: null, saleId: null }
    ]);
  }

  function createCustomers() {
    const names = [
      'Maria Oliveira', 'João Pereira', 'Fernanda Melo', 'Eduardo Ramos', 'Luciana Braga',
      'Carlos Nunes', 'Rafael Costa', 'Ana Martins', 'Paulo Lima', 'Carla Souza',
      'Lívia Reis', 'Marcos Alves', 'Daniela Luz', 'Renato Gomes', 'Beatriz Santos'
    ];
    return names.map((name, index) => ({
      id: `customer-${index + 1}`,
      name,
      cpf: `${String(index + 100).padStart(3, '0')}.111.222-${String(30 + index).padStart(2, '0')}`,
      phone: `(97) 99${String(810000 + index * 137).padStart(6, '0')}`,
      email: `${name.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/ /g, '.')}@exemplo.com`,
      brokerId: index % 2 ? 'broker-ana' : 'broker-joao',
      status: index < 7 ? 'active' : 'prospect',
      createdAt: `2026-0${(index % 7) + 1}-${String((index % 24) + 1).padStart(2, '0')}T12:00:00.000Z`
    }));
  }

  function createLeads() {
    const names = [
      'Ana Martins', 'Paulo Lima', 'Carla Souza', 'Rafael Costa', 'Lívia Reis',
      'Marcos Alves', 'Daniela Luz', 'Maria Oliveira', 'Carlos Nunes', 'Fernanda Melo',
      'João Pereira', 'Eduardo Ramos', 'Sônia Ribeiro', 'André Batista', 'Camila Rocha',
      'Bruno Tavares', 'Patrícia Lopes', 'Mateus Freire', 'Isabela Moura', 'Diego Campos'
    ];
    const stages = ['new', 'new', 'contact', 'contact', 'visit', 'visit', 'proposal', 'proposal', 'reservation', 'sale'];
    const origins = ['Instagram', 'Site', 'Indicação', 'WhatsApp', 'Facebook'];
    return names.map((name, index) => ({
      id: `lead-${index + 1}`,
      name,
      phone: `(97) 99${String(910000 + index * 173).padStart(6, '0')}`,
      email: `lead${index + 1}@exemplo.com`,
      cpf: null,
      origin: origins[index % origins.length],
      brokerId: index % 2 ? 'broker-ana' : 'broker-joao',
      enterpriseId: index % 4 === 0 ? 'ent-aguas' : 'ent-amazonas',
      unitId: null,
      stage: stages[index % stages.length],
      expectedValue: 85000 + (index * 4500),
      customerId: index >= 7 && index <= 11 ? `customer-${index - 6}` : null,
      lastInteractionAt: `2026-08-${String((index % 18) + 2).padStart(2, '0')}T14:00:00.000Z`
    }));
  }

  function createInstallments(contractCount) {
    return Array.from({ length: contractCount }, (_, contractIndex) =>
      Array.from({ length: 6 }, (_, installmentIndex) => ({
        id: `installment-${contractIndex + 1}-${installmentIndex + 1}`,
        contractId: `contract-${contractIndex + 1}`,
        number: installmentIndex + 1,
        dueDate: `2026-${String(4 + installmentIndex).padStart(2, '0')}-10`,
        amount: 1000 + (contractIndex * 75),
        status: installmentIndex < 4 ? 'paid' : (installmentIndex === 4 && contractIndex % 3 === 0 ? 'overdue' : 'pending'),
        paymentId: installmentIndex < 4 ? `payment-${contractIndex + 1}-${installmentIndex + 1}` : null
      }))
    ).flat();
  }

  function createSeed() {
    const createdAt = nowIso();
    const units = createUnits();
    const soldUnits = units.filter(unit => unit.status === 'sold').slice(0, 10);
    const customers = createCustomers();
    const sales = soldUnits.map((unit, index) => ({
      id: `sale-${index + 1}`,
      number: `V-2026-${String(403 + index).padStart(4, '0')}`,
      customerId: `customer-${index + 2}`,
      enterpriseId: unit.enterpriseId,
      unitId: unit.id,
      brokerId: index % 2 ? 'broker-ana' : 'broker-joao',
      proposalId: index < 3 ? `proposal-${index + 3}` : null,
      amount: unit.listPrice,
      soldAt: `2026-08-${String(10 + index).padStart(2, '0')}T15:00:00.000Z`,
      contractId: `contract-${index + 1}`,
      status: 'active'
    }));
    soldUnits.forEach((unit, index) => {
      unit.saleId = sales[index].id;
      unit.ownerCustomerId = sales[index].customerId;
    });
    const contracts = sales.map((sale, index) => ({
      id: `contract-${index + 1}`,
      number: `CTR-${String(115 + index).padStart(6, '0')}`,
      saleId: sale.id,
      customerId: sale.customerId,
      enterpriseId: sale.enterpriseId,
      unitId: sale.unitId,
      totalAmount: sale.amount,
      downPayment: 10000 + (index * 1000),
      paidAmount: (1000 + (index * 75)) * 4,
      installmentCount: 100 - (index * 2),
      signedAt: sale.soldAt.slice(0, 10),
      status: 'active'
    }));
    const installments = createInstallments(contracts.length);
    const payments = installments.filter(item => item.paymentId).map(item => ({
      id: item.paymentId,
      installmentId: item.id,
      contractId: item.contractId,
      amount: item.amount,
      paidAt: `${item.dueDate}T13:00:00.000Z`,
      method: 'pix'
    }));
    const reservations = [
      { id: 'reservation-1', customerId: 'customer-1', enterpriseId: 'ent-amazonas', unitId: 'unit-amz-a02', brokerId: 'broker-joao', proposalId: 'proposal-1', status: 'active', createdAt: '2026-08-19T10:40:00.000Z', expiresAt: '2026-08-21T18:00:00.000Z' },
      { id: 'reservation-2', customerId: 'customer-3', enterpriseId: 'ent-amazonas', unitId: 'unit-amz-c08', brokerId: 'broker-ana', proposalId: null, status: 'active', createdAt: '2026-08-18T15:20:00.000Z', expiresAt: '2026-08-22T18:00:00.000Z' }
    ];
    reservations.forEach(reservation => {
      const unit = units.find(item => item.id === reservation.unitId);
      if (unit) unit.reservationId = reservation.id;
    });

    return {
      meta: { version: 1, seededAt: createdAt, updatedAt: createdAt },
      session: null,
      settings: {
        theme: 'light', currency: 'BRL', timezone: 'America/Manaus',
        companyName: 'Urbanix Empreendimentos Ltda.', reservationHours: 48,
        proposalDays: 5, defaultCommissionPercent: 4
      },
      users: [
        { id: 'user-admin', name: 'Lucas Souza', email: 'admin@empresa.com', password: '123456', roleCode: 'admin', accountType: 'internal', role: 'Administrador', phone: '(97) 99999-0001', initials: 'LS', active: true },
        { id: 'user-broker', name: 'João Silva', email: 'corretor@empresa.com', password: '123456', roleCode: 'broker', accountType: 'internal', role: 'Corretor', phone: '(97) 99999-0002', initials: 'JS', brokerId: 'broker-joao', active: true },
        { id: 'user-engineer', name: 'Mariana Lima', email: 'engenheiro@empresa.com', password: '123456', roleCode: 'engineering', accountType: 'internal', role: 'Engenharia', phone: '(97) 99999-0003', initials: 'ML', active: true },
        { id: 'user-client', name: 'João Pereira', email: 'cliente@empresa.com', password: '123456', roleCode: 'client', accountType: 'customer', role: 'Cliente', customerId: 'customer-2', phone: '(97) 99810-0137', initials: 'JP', active: true }
      ],
      enterprises: [
        { id: 'ent-amazonas', name: 'Residencial Amazonas', city: 'Tefé', state: 'AM', type: 'loteamento', status: 'selling', unitCount: 420, progress: 82, estimatedVgv: 32400000, imageUrl: 'https://images.unsplash.com/photo-1489510789366-8556c6f91614?auto=format&fit=crop&w=1200&q=80', imageAlt: 'Vista aérea de um loteamento residencial em construção' },
        { id: 'ent-aguas', name: 'Parque das Águas', city: 'Tefé', state: 'AM', type: 'condomínio', status: 'launch', unitCount: 96, progress: 28, estimatedVgv: 18700000 },
        { id: 'ent-sol', name: 'Vila Sol Nascente', city: 'Coari', state: 'AM', type: 'casas', status: 'building', unitCount: 64, progress: 61, estimatedVgv: 9200000 }
      ],
      blocks: ['A', 'B', 'C', 'D'].map(code => ({ id: `block-amz-${code.toLowerCase()}`, enterpriseId: 'ent-amazonas', code })),
      units,
      brokers: [
        { id: 'broker-joao', name: 'João Silva', creci: 'AM-10422', commissionPercent: 4 },
        { id: 'broker-ana', name: 'Ana Costa', creci: 'AM-11308', commissionPercent: 4 }
      ],
      leads: createLeads(),
      customers,
      proposals: [
        { id: 'proposal-1', number: 'P-2026-0142', customerId: 'customer-1', enterpriseId: 'ent-amazonas', unitId: 'unit-amz-a02', brokerId: 'broker-joao', listPrice: 87500, discount: 1500, negotiatedPrice: 86000, downPayment: 10000, installmentCount: 120, validUntil: '2026-08-22', status: 'approved', saleId: null },
        { id: 'proposal-2', number: 'P-2026-0141', customerId: 'customer-7', enterpriseId: 'ent-amazonas', unitId: 'unit-amz-d04', brokerId: 'broker-ana', listPrice: 145000, discount: 0, negotiatedPrice: 145000, downPayment: 29000, installmentCount: 96, validUntil: '2026-08-21', status: 'sent', saleId: null },
        { id: 'proposal-3', number: 'P-2026-0137', customerId: 'customer-2', enterpriseId: soldUnits[0].enterpriseId, unitId: soldUnits[0].id, brokerId: 'broker-joao', listPrice: soldUnits[0].listPrice, discount: 0, negotiatedPrice: soldUnits[0].listPrice, downPayment: 15000, installmentCount: 100, validUntil: '2026-08-20', status: 'converted', saleId: 'sale-1' },
        { id: 'proposal-4', number: 'P-2026-0134', customerId: 'customer-3', enterpriseId: soldUnits[1].enterpriseId, unitId: soldUnits[1].id, brokerId: 'broker-ana', listPrice: soldUnits[1].listPrice, discount: 1500, negotiatedPrice: soldUnits[1].listPrice - 1500, downPayment: 12000, installmentCount: 96, validUntil: '2026-08-18', status: 'converted', saleId: 'sale-2' },
        { id: 'proposal-5', number: 'P-2026-0129', customerId: 'customer-4', enterpriseId: soldUnits[2].enterpriseId, unitId: soldUnits[2].id, brokerId: 'broker-joao', listPrice: soldUnits[2].listPrice, discount: 0, negotiatedPrice: soldUnits[2].listPrice, downPayment: 14000, installmentCount: 84, validUntil: '2026-08-17', status: 'converted', saleId: 'sale-3' }
      ],
      reservations,
      sales,
      contracts,
      installments,
      payments,
      accountsReceivable: installments.map(item => ({ id: `receivable-${item.id}`, installmentId: item.id, contractId: item.contractId, dueDate: item.dueDate, amount: item.amount, status: item.status })),
      accountsPayable: [
        { id: 'payable-1', supplierId: 'supplier-concremax', workId: 'work-amazonas', description: 'Concreto usinado', dueDate: '2026-08-22', amount: 42780, status: 'pending' },
        { id: 'payable-2', supplierId: 'supplier-eletro', workId: 'work-aguas', description: 'Cabos elétricos', dueDate: '2026-08-28', amount: 27840, status: 'pending' }
      ],
      works: [
        { id: 'work-amazonas', enterpriseId: 'ent-amazonas', name: 'Infraestrutura Residencial Amazonas', budget: 5400000, executedCost: 3620000, progress: 82, status: 'active', updatedAt: '2026-08-19T16:30:00.000Z' },
        { id: 'work-aguas', enterpriseId: 'ent-aguas', name: 'Implantação Parque das Águas', budget: 7800000, executedCost: 2480000, progress: 28, status: 'attention', updatedAt: '2026-08-18T15:00:00.000Z' }
      ],
      services: [
        { id: 'service-earthwork', workId: 'work-amazonas', name: 'Terraplanagem', budget: 680000, progress: 100, customerVisible: true, updatedAt: '2026-04-28T12:00:00.000Z' },
        { id: 'service-drainage', workId: 'work-amazonas', name: 'Drenagem', budget: 740000, progress: 100, customerVisible: true, updatedAt: '2026-05-30T12:00:00.000Z' },
        { id: 'service-water', workId: 'work-amazonas', name: 'Rede de água', budget: 520000, progress: 92, customerVisible: true, updatedAt: '2026-07-18T12:00:00.000Z' },
        { id: 'service-electric', workId: 'work-amazonas', name: 'Rede elétrica', budget: 610000, progress: 85, customerVisible: true, updatedAt: '2026-08-10T12:00:00.000Z' },
        { id: 'service-paving', workId: 'work-amazonas', name: 'Pavimentação', budget: 920000, progress: 64, customerVisible: true, updatedAt: '2026-08-19T12:00:00.000Z' },
        { id: 'service-landscape', workId: 'work-amazonas', name: 'Paisagismo', budget: 310000, progress: 28, customerVisible: true, updatedAt: '2026-08-15T12:00:00.000Z' }
      ],
      workPhotos: [
        { id: 'work-photo-1', workId: 'work-amazonas', imageUrl: 'https://images.unsplash.com/photo-1489510761922-2a8bc10a168a?auto=format&fit=crop&w=900&q=80', caption: 'Execução da pavimentação da via principal', alt: 'Vista aérea de uma via pavimentada em um loteamento em construção', createdAt: '2026-08-19T12:00:00.000Z' },
        { id: 'work-photo-2', workId: 'work-amazonas', imageUrl: 'https://images.unsplash.com/photo-1503387762-592deb58ef4e?auto=format&fit=crop&w=900&q=80', caption: 'Instalação da rede elétrica', alt: 'Profissionais trabalhando na infraestrutura de um canteiro de obras', createdAt: '2026-08-12T12:00:00.000Z' },
        { id: 'work-photo-3', workId: 'work-amazonas', imageUrl: 'https://images.unsplash.com/photo-1541888946425-d81bb19240f5?auto=format&fit=crop&w=900&q=80', caption: 'Infraestrutura de drenagem concluída', alt: 'Vista ampla de uma obra de infraestrutura em andamento', createdAt: '2026-07-30T12:00:00.000Z' }
      ],
      measurements: [
        { id: 'measurement-81', workId: 'work-amazonas', serviceId: 'service-paving', number: 'MED-0081', amount: 118420, progress: 8, status: 'submitted', accountedAt: null }
      ],
      purchaseRequests: [
        { id: 'request-319', workId: 'work-amazonas', number: 'SC-00319', material: 'Cimento CP II', quantity: 320, unit: 'saco', estimatedAmount: 13760, status: 'quotation' }
      ],
      quotations: [
        { id: 'quotation-1', purchaseRequestId: 'request-319', supplierId: 'supplier-concremax', amount: 13760, selected: true },
        { id: 'quotation-2', purchaseRequestId: 'request-319', supplierId: 'supplier-norte', amount: 14560, selected: false }
      ],
      suppliers: [
        { id: 'supplier-concremax', name: 'Concremax Materiais', cnpj: '11.222.333/0001-44', phone: '(97) 3333-1900', category: 'Materiais' },
        { id: 'supplier-eletro', name: 'Eletro Norte', cnpj: '22.333.444/0001-55', phone: '(92) 3333-2400', category: 'Elétrica' },
        { id: 'supplier-norte', name: 'Depósito Norte', cnpj: '33.444.555/0001-66', phone: '(97) 3333-2700', category: 'Materiais' }
      ],
      inventory: [
        { id: 'stock-cement', workId: 'work-amazonas', material: 'Cimento CP II', unit: 'saco', balance: 86, minimum: 120 },
        { id: 'stock-cable', workId: 'work-amazonas', material: 'Cabo 35 mm²', unit: 'metro', balance: 1450, minimum: 500 }
      ],
      documents: [
        { id: 'document-1', contractId: 'contract-1', customerId: 'customer-2', name: 'Contrato de compra e venda', category: 'Contrato', fileName: 'contrato-ctr-000115.html', mimeType: 'text/html', createdAt: '2026-02-14T12:00:00.000Z' },
        { id: 'document-2', contractId: 'contract-1', customerId: 'customer-2', name: 'Extrato financeiro demonstrativo', category: 'Financeiro', fileName: 'extrato-ctr-000115.csv', mimeType: 'text/csv', createdAt: '2026-08-20T12:00:00.000Z' },
        { id: 'document-3', contractId: 'contract-2', customerId: 'customer-3', name: 'Contrato de compra e venda', category: 'Contrato', fileName: 'contrato-ctr-000116.html', mimeType: 'text/html', createdAt: '2026-03-06T12:00:00.000Z' }
      ],
      supportRequests: [
        { id: 'support-1', customerId: 'customer-2', contractId: 'contract-1', createdByUserId: 'user-client', subject: 'Atualização da obra', message: 'Gostaria de confirmar a previsão da próxima atualização da pavimentação.', status: 'answered', createdAt: '2026-08-12T14:30:00.000Z', updatedAt: '2026-08-13T11:10:00.000Z' }
      ],
      notifications: [
        { id: 'notification-1', title: 'Nova proposta aguardando aprovação', detail: 'Proposta P-2026-0142', href: 'propostas.html', read: false, createdAt: '2026-08-20T10:45:00.000Z' },
        { id: 'notification-2', title: 'Reserva expira em breve', detail: 'Unidade A-02', href: 'reservas.html', read: false, createdAt: '2026-08-20T09:20:00.000Z' },
        { id: 'notification-3', title: 'Parcela vencida', detail: 'Contrato CTR-000115', href: 'financeiro.html', read: false, createdAt: '2026-08-19T16:10:00.000Z' },
        { id: 'notification-4', title: 'Medição aguardando aprovação', detail: 'Medição MED-0081', href: 'engenharia.html', read: true, createdAt: '2026-08-19T11:00:00.000Z' },
        { id: 'notification-5', title: 'Estoque abaixo do mínimo', detail: 'Cimento CP II', href: 'compras.html', read: false, createdAt: '2026-08-18T14:30:00.000Z' }
      ],
      audits: [
        { id: 'audit-1', userId: 'user-admin', action: 'seed.created', entity: 'system', entityId: 'urbanix.erp.v1', detail: 'Base demonstrativa inicial criada', createdAt }
      ]
    };
  }

  Urbanix.Data = Object.freeze({ version: 1, createSeed });
})(window);
