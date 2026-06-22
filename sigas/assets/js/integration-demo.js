'use strict';

/**
 * Adaptador demonstrativo de comparação entre o SIGAS Coari e o sistema SEMTH/SEMAS.
 *
 * Regras de governança simuladas:
 * - SIGAS grava somente na própria base.
 * - SEMTH é consultado em modo somente leitura.
 * - nenhum retorno deste adaptador executa INSERT, UPDATE ou DELETE no SEMTH.
 * - registros encontrados no SEMTH podem ser vinculados por referência externa,
 *   sem copiar silenciosamente o cadastro inteiro.
 */
window.SIGASIntegration = (() => {
    const scenarios = {
        'sigas-only': {
            key: 'sigas-only',
            name: 'Maria de Lourdes Silva',
            initials: 'ML',
            cpf: '12345678909',
            birthDate: '1986-03-14',
            sigas: { found: true, id: 'PS-018452', status: 'Regular', updatedAt: '18/06/2026' },
            semth: { found: false },
            decision: 'open-existing',
            severity: 'danger',
            title: 'Cadastro já existe no SIGAS',
            message: 'A criação de uma nova pessoa foi bloqueada. Abra o registro existente para evitar duplicidade.'
        },
        'semth-only': {
            key: 'semth-only',
            name: 'Joana Lima de Oliveira',
            initials: 'JL',
            cpf: '98765432100',
            birthDate: '1992-08-22',
            sigas: { found: false },
            semth: { found: true, id: 'SEMTH-009842', status: 'Cadastro ativo', updatedAt: '12/06/2026', unit: 'ANEXO SEMAS' },
            decision: 'create-reference',
            severity: 'warning',
            title: 'Pessoa localizada somente no SEMTH',
            message: 'O SIGAS pode criar uma referência local vinculada ao código legado. O registro do SEMTH permanecerá somente leitura.'
        },
        linked: {
            key: 'linked',
            name: 'Antônia Pereira da Costa',
            initials: 'AP',
            cpf: '11122233344',
            birthDate: '1958-02-11',
            sigas: { found: true, id: 'PS-014208', status: 'Em revisão', updatedAt: '17/06/2026' },
            semth: { found: true, id: 'SEMTH-004107', status: 'Cadastro ativo', updatedAt: '15/06/2026', unit: 'ANEXO SEMAS' },
            decision: 'open-linked',
            severity: 'info',
            title: 'Cadastros já vinculados',
            message: 'O SIGAS possui registro próprio e mantém apenas a referência ao SEMTH. Alterações continuam isoladas em cada sistema.'
        },
        conflict: {
            key: 'conflict',
            name: 'Francisca Costa dos Santos',
            initials: 'FC',
            cpf: '55566677788',
            birthDate: '1979-09-06',
            sigas: { found: true, id: 'PS-018448', status: 'Regular', updatedAt: '18/06/2026', name: 'Francisca Costa dos Santos' },
            semth: { found: true, id: 'SEMTH-008203', status: 'Cadastro ativo', updatedAt: '10/06/2026', unit: 'ANEXO SEMAS', name: 'Francisca C. dos Santos' },
            decision: 'review-conflict',
            severity: 'danger',
            title: 'Possível divergência entre as bases',
            message: 'Há correspondência nas duas bases, mas os dados não estão plenamente consistentes. Novo cadastro bloqueado até revisão.'
        },
        none: {
            key: 'none',
            name: 'Pessoa não localizada',
            initials: '—',
            cpf: '',
            birthDate: '',
            sigas: { found: false },
            semth: { found: false },
            decision: 'create-new',
            severity: 'success',
            title: 'Nenhum cadastro localizado',
            message: 'O CPF não foi encontrado no SIGAS nem no SEMTH. O novo cadastro pode prosseguir após conferência documental.'
        }
    };

    const cpfScenarioMap = {
        '12345678909': 'sigas-only',
        '98765432100': 'semth-only',
        '11122233344': 'linked',
        '55566677788': 'conflict'
    };

    const digits = value => String(value || '').replace(/\D/g, '').slice(0, 11);
    const formatCpf = value => {
        const cpf = digits(value);
        return cpf.replace(/^(\d{3})(\d)/, '$1.$2').replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3').replace(/\.(\d{3})(\d)/, '.$1-$2');
    };
    const maskCpf = value => {
        const cpf = digits(value);
        return cpf.length === 11 ? `***.${cpf.slice(3, 6)}.${cpf.slice(6, 9)}-**` : 'CPF não informado';
    };

    function cloneScenario(key, cpf = '') {
        const base = scenarios[key] || scenarios.none;
        const result = JSON.parse(JSON.stringify(base));
        if (cpf) result.cpf = digits(cpf);
        return result;
    }

    async function lookupByCpf(cpf, forcedScenario = '') {
        const normalized = digits(cpf);
        const key = forcedScenario && scenarios[forcedScenario]
            ? forcedScenario
            : (cpfScenarioMap[normalized] || 'none');
        await new Promise(resolve => window.setTimeout(resolve, 520));
        return cloneScenario(key, normalized);
    }

    function getScenario(key) {
        return cloneScenario(key);
    }

    return Object.freeze({
        lookupByCpf,
        getScenario,
        formatCpf,
        maskCpf,
        digits,
        scenarios: Object.freeze(Object.keys(scenarios))
    });
})();
