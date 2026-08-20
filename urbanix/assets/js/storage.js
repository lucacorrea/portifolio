(function (root) {
  'use strict';

  const Urbanix = root.Urbanix = root.Urbanix || {};
  const KEY = 'urbanix.erp.v1';
  const REQUIRED_COLLECTIONS = [
    'users', 'enterprises', 'blocks', 'units', 'leads', 'customers', 'brokers',
    'proposals', 'reservations', 'sales', 'contracts', 'installments', 'payments',
    'accountsReceivable', 'accountsPayable', 'works', 'services', 'measurements',
    'purchaseRequests', 'quotations', 'suppliers', 'inventory', 'documents',
    'notifications', 'audits'
  ];
  let memoryState = null;

  function clone(value) {
    if (typeof structuredClone === 'function') return structuredClone(value);
    return JSON.parse(JSON.stringify(value));
  }

  function validateState(state) {
    if (!state || typeof state !== 'object') throw new Error('Estado ausente ou inválido.');
    if (!state.meta || state.meta.version !== Urbanix.Data.version) throw new Error('Versão do estado incompatível.');
    if (!state.settings || typeof state.settings !== 'object') throw new Error('Configurações inválidas.');
    REQUIRED_COLLECTIONS.forEach(collection => {
      if (!Array.isArray(state[collection])) throw new Error(`Coleção inválida: ${collection}.`);
    });
    const contractIds = new Set(state.contracts.map(item => item.id));
    const installmentIds = new Set(state.installments.map(item => item.id));
    const unitIds = new Set(state.units.map(item => item.id));
    const customerIds = new Set(state.customers.map(item => item.id));
    const proposalIds = new Set(state.proposals.map(item => item.id));
    const saleIds = new Set(state.sales.map(item => item.id));
    if (state.installments.some(item => !contractIds.has(item.contractId))) throw new Error('Parcela vinculada a contrato inexistente.');
    if (state.payments.some(item => !contractIds.has(item.contractId) || !installmentIds.has(item.installmentId))) throw new Error('Pagamento com vínculo inexistente.');
    if (state.accountsReceivable.some(item => !contractIds.has(item.contractId) || !installmentIds.has(item.installmentId))) throw new Error('Conta a receber com vínculo inexistente.');
    if (state.reservations.some(item => !unitIds.has(item.unitId) || !customerIds.has(item.customerId) || (item.proposalId && !proposalIds.has(item.proposalId)))) throw new Error('Reserva com vínculo inexistente.');
    if (state.reservations.some(item => {
      if (item.status !== 'active') return false;
      const unit = state.units.find(entry => entry.id === item.unitId);
      const proposal = item.proposalId && state.proposals.find(entry => entry.id === item.proposalId);
      return unit?.status !== 'reserved' || unit.reservationId !== item.id || (proposal && (proposal.unitId !== item.unitId || proposal.customerId !== item.customerId));
    })) throw new Error('Reserva ativa com relações inconsistentes.');
    if (state.units.some(item => item.status === 'reserved' && !state.reservations.some(reservation => reservation.id === item.reservationId && reservation.status === 'active'))) throw new Error('Unidade reservada sem reserva ativa.');
    if (state.sales.some(item => !unitIds.has(item.unitId) || !customerIds.has(item.customerId) || !contractIds.has(item.contractId))) throw new Error('Venda com vínculo inexistente.');
    if (state.contracts.some(item => !saleIds.has(item.saleId) || !unitIds.has(item.unitId) || !customerIds.has(item.customerId))) throw new Error('Contrato com vínculo inexistente.');
    if (state.sales.some(item => {
      const unit = state.units.find(entry => entry.id === item.unitId);
      const contract = state.contracts.find(entry => entry.id === item.contractId);
      return unit?.status !== 'sold' || unit.saleId !== item.id || contract?.saleId !== item.id;
    })) throw new Error('Venda com relações inconsistentes.');
    return true;
  }

  function readRaw() {
    try {
      return root.localStorage.getItem(KEY);
    } catch (error) {
      console.warn('LocalStorage indisponível; os dados durarão somente nesta aba.', error);
      return null;
    }
  }

  function writeState(state) {
    const serialized = JSON.stringify(state);
    try {
      root.localStorage.setItem(KEY, serialized);
    } catch (error) {
      console.warn('Não foi possível persistir no LocalStorage.', error);
    }
    memoryState = clone(state);
  }

  function seed() {
    const fresh = Urbanix.Data.createSeed();
    validateState(fresh);
    writeState(fresh);
    return clone(fresh);
  }

  function load() {
    if (memoryState) return clone(memoryState);
    const raw = readRaw();
    if (!raw) return seed();
    try {
      const state = JSON.parse(raw);
      validateState(state);
      memoryState = clone(state);
      return clone(state);
    } catch (error) {
      console.warn('Base demonstrativa corrompida ou incompatível; um seed seguro será aplicado.', error);
      return seed();
    }
  }

  function notify(action, state) {
    root.dispatchEvent(new CustomEvent('urbanix:store-changed', {
      detail: { action, updatedAt: state.meta.updatedAt }
    }));
  }

  function mutate(action, mutator) {
    if (typeof mutator !== 'function') throw new TypeError('A mutação deve ser uma função.');
    const next = load();
    const result = mutator(next);
    next.meta.updatedAt = new Date().toISOString();
    validateState(next);
    writeState(next);
    notify(action || 'state.updated', next);
    return { state: clone(next), result: clone(result) };
  }

  function collection(name) {
    const state = load();
    if (!Array.isArray(state[name])) throw new Error(`Coleção desconhecida: ${name}.`);
    return state[name];
  }

  function find(name, id) {
    return collection(name).find(item => item.id === id) || null;
  }

  function create(name, values) {
    return mutate(`${name}.created`, state => {
      if (!Array.isArray(state[name])) throw new Error(`Coleção desconhecida: ${name}.`);
      const record = { ...values, id: values.id || generateId(name) };
      if (state[name].some(item => item.id === record.id)) throw new Error('O identificador já está em uso.');
      state[name].push(record);
      return record;
    }).result;
  }

  function update(name, id, values) {
    return mutate(`${name}.updated`, state => {
      if (!Array.isArray(state[name])) throw new Error(`Coleção desconhecida: ${name}.`);
      const index = state[name].findIndex(item => item.id === id);
      if (index < 0) throw new Error('Registro não encontrado.');
      state[name][index] = { ...state[name][index], ...values, id };
      return state[name][index];
    }).result;
  }

  function remove(name, id) {
    return mutate(`${name}.removed`, state => {
      if (!Array.isArray(state[name])) throw new Error(`Coleção desconhecida: ${name}.`);
      const index = state[name].findIndex(item => item.id === id);
      if (index < 0) throw new Error('Registro não encontrado.');
      return state[name].splice(index, 1)[0];
    }).result;
  }

  function generateId(prefix) {
    const random = Math.random().toString(36).slice(2, 8);
    return `${String(prefix || 'record').replace(/[^a-z0-9-]/gi, '-').toLowerCase()}-${Date.now().toString(36)}-${random}`;
  }

  function reset(options) {
    const currentSession = options && options.keepSession ? load().session : null;
    const fresh = Urbanix.Data.createSeed();
    fresh.session = currentSession;
    writeState(fresh);
    notify('demo.reset', fresh);
    return clone(fresh);
  }

  Urbanix.Store = Object.freeze({
    key: KEY,
    getState: load,
    query: collection,
    find,
    create,
    update,
    remove,
    mutate,
    reset,
    generateId,
    validateState
  });

  load();
})(window);
