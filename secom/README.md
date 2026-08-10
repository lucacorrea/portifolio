# SECOM Coari — UI v3

Versão aprimorada do protótipo em HTML/CSS com JavaScript progressivo.

## Princípios desta versão
- Cada página continua sendo HTML completo: não fica em branco se o JavaScript falhar.
- CSS centralizado e baseado em variáveis.
- Sidebar recolhível no desktop e drawer no mobile.
- Layout fluido até 1680px.
- Tabelas com busca local progressiva.
- Pauta detalhada virou a central completa do evento.
- Acervo separa Original, Master e Proxy.
- Design responsivo para desktop, notebook, tablet e celular.

## Páginas principais
- login.html
- index.html
- pages/agenda.html
- pages/pautas.html
- pages/pauta-detalhe.html
- pages/coberturas.html
- pages/demandas.html
- pages/producoes.html
- pages/materias.html
- pages/aprovacoes.html
- pages/publicacoes.html
- pages/acervo.html
- pages/originais.html
- pages/masters.html
- pages/preservacao.html
- pages/equipe.html
- pages/secretarias.html
- pages/relatorios.html
- pages/auditoria.html
- pages/usuarios.html
- pages/configuracoes.html
- pages/perfil.html

## Backend futuro
Ao converter para PHP OOP, a UI pode ser mantida:
- layout/sidebar/topbar viram partials;
- páginas viram views;
- controllers chamam services;
- services usam repositories;
- MySQL guarda metadados e relacionamentos;
- storage dedicado guarda bytes dos arquivos;
- original é imutável;
- preview/proxy é derivado;
- auditoria registra ações sensíveis.
