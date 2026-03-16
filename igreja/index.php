<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Igreja Vida Nova - Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body {
            background-color: #0a1a2b;
            color: #e0e7ff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        /* Container principal com efeito glass moderno */
        .dashboard {
            max-width: 1400px;
            width: 100%;
            background: rgba(18, 30, 46, 0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(64, 128, 255, 0.2);
            border-radius: 32px;
            padding: 28px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        /* Header com navegação */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 32px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .logo-area {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(145deg, #2a4b7c, #1a3650);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #7aa9ff;
            font-size: 24px;
            box-shadow: 0 8px 16px -4px rgba(0, 30, 80, 0.5);
        }

        .logo-text h1 {
            font-size: 1.8rem;
            font-weight: 600;
            background: linear-gradient(135deg, #ffffff, #b8d1ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -0.5px;
        }

        .logo-text p {
            font-size: 0.85rem;
            color: #8a9fc9;
            margin-top: 4px;
        }

        .nav-menu {
            display: flex;
            gap: 8px;
            background: rgba(10, 25, 45, 0.6);
            padding: 6px;
            border-radius: 40px;
            border: 1px solid rgba(80, 140, 255, 0.2);
        }

        .nav-item {
            padding: 10px 20px;
            border-radius: 30px;
            font-size: 0.95rem;
            font-weight: 500;
            color: #b0c7ed;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-item i {
            font-size: 1rem;
        }

        .nav-item.active {
            background: linear-gradient(145deg, #1e3b5c, #142b42);
            color: white;
            box-shadow: 0 4px 12px rgba(30, 100, 200, 0.3);
            border: 1px solid rgba(100, 160, 255, 0.3);
        }

        .nav-item:not(.active):hover {
            background: rgba(30, 60, 100, 0.4);
            color: #d9e6ff;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .notification-badge {
            position: relative;
            color: #9bb9ff;
            font-size: 1.3rem;
            cursor: pointer;
        }

        .notification-badge::after {
            content: '';
            position: absolute;
            top: 2px;
            right: 2px;
            width: 8px;
            height: 8px;
            background: #4c9aff;
            border-radius: 50%;
            border: 2px solid #1a2b3e;
        }

        .avatar {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(20, 45, 75, 0.6);
            padding: 8px 16px 8px 12px;
            border-radius: 40px;
            border: 1px solid rgba(80, 150, 255, 0.2);
        }

        .avatar-img {
            width: 38px;
            height: 38px;
            background: linear-gradient(145deg, #2a5f8a, #1b3f5e);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.1rem;
            color: white;
            border: 2px solid #4b7ec9;
        }

        .avatar-name {
            font-weight: 500;
            color: #ebf3ff;
        }

        /* Cards de estatísticas */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(16, 34, 52, 0.7);
            backdrop-filter: blur(5px);
            border: 1px solid rgba(70, 130, 230, 0.25);
            border-radius: 24px;
            padding: 22px 20px;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 10px 20px -10px rgba(0, 0, 0, 0.5);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            border-color: rgba(100, 170, 255, 0.5);
            box-shadow: 0 20px 30px -10px rgba(0, 80, 200, 0.3);
        }

        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
        }

        .stat-title {
            color: #9ab3dd;
            font-size: 0.95rem;
            font-weight: 500;
            letter-spacing: 0.3px;
        }

        .stat-icon {
            width: 42px;
            height: 42px;
            background: linear-gradient(145deg, #1f3b5c, #14273e);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #5f9eff;
            font-size: 1.3rem;
        }

        .stat-value {
            font-size: 2.2rem;
            font-weight: 700;
            color: white;
            line-height: 1.2;
            margin-bottom: 6px;
        }

        .stat-trend {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            color: #86b0e6;
        }

        .trend-up { color: #4cd964; }
        .trend-down { color: #ff6b6b; }

        /* Seção de gráficos e atividades */
        .charts-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 22px;
            margin-bottom: 30px;
        }

        .chart-card {
            background: rgba(16, 34, 52, 0.7);
            backdrop-filter: blur(5px);
            border: 1px solid rgba(70, 130, 230, 0.25);
            border-radius: 28px;
            padding: 22px;
        }

        .chart-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .chart-header h3 {
            font-size: 1.3rem;
            font-weight: 600;
            color: white;
        }

        .chart-legend {
            display: flex;
            gap: 18px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.85rem;
            color: #b0c6e5;
        }

        .legend-color {
            width: 10px;
            height: 10px;
            border-radius: 4px;
        }

        .color-blue { background: #3b82f6; }
        .color-cyan { background: #2dd4bf; }

        /* Gráfico de barras simplificado */
        .bar-chart {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            height: 200px;
            margin-top: 30px;
            gap: 8px;
        }

        .bar-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            flex: 1;
        }

        .bar {
            width: 100%;
            background: linear-gradient(to top, #1e4b8f, #3b82f6);
            border-radius: 12px 12px 6px 6px;
            min-height: 4px;
            transition: height 0.3s;
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.4);
        }

        .bar.secondary {
            background: linear-gradient(to top, #0f5f5a, #2dd4bf);
            box-shadow: 0 0 15px rgba(45, 212, 191, 0.3);
        }

        .bar-label {
            font-size: 0.8rem;
            color: #95b0da;
        }

        /* Lista de atividades */
        .activity-list {
            margin-top: 10px;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid rgba(100, 150, 240, 0.15);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 44px;
            height: 44px;
            background: rgba(30, 70, 130, 0.4);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6ea4ff;
            font-size: 1.2rem;
        }

        .activity-details {
            flex: 1;
        }

        .activity-title {
            font-weight: 600;
            color: white;
            margin-bottom: 5px;
        }

        .activity-time {
            font-size: 0.8rem;
            color: #7c96c0;
        }

        .activity-badge {
            background: rgba(59, 130, 246, 0.2);
            padding: 5px 12px;
            border-radius: 40px;
            font-size: 0.75rem;
            font-weight: 600;
            color: #9ac0ff;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        /* Cards inferiores (eventos, membros) */
        .bottom-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px;
        }

        .info-card {
            background: rgba(16, 34, 52, 0.7);
            backdrop-filter: blur(5px);
            border: 1px solid rgba(70, 130, 230, 0.25);
            border-radius: 28px;
            padding: 22px;
        }

        .info-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
        }

        .info-header h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: white;
        }

        .info-link {
            color: #7aaaff;
            font-size: 0.9rem;
            cursor: pointer;
            text-decoration: none;
            border-bottom: 1px dashed #3d6eb0;
        }

        .event-item, .member-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 0;
            border-bottom: 1px solid rgba(100, 150, 240, 0.1);
        }

        .event-date {
            background: rgba(26, 54, 93, 0.6);
            border-radius: 18px;
            padding: 10px 14px;
            text-align: center;
            min-width: 60px;
            border: 1px solid rgba(80, 150, 255, 0.2);
        }

        .event-day {
            font-size: 1.3rem;
            font-weight: 700;
            color: white;
            line-height: 1.2;
        }

        .event-month {
            font-size: 0.7rem;
            color: #98b5e9;
            text-transform: uppercase;
        }

        .event-info h4 {
            font-weight: 600;
            color: white;
            margin-bottom: 4px;
        }

        .event-info p {
            font-size: 0.8rem;
            color: #92acd4;
        }

        .member-avatar {
            width: 42px;
            height: 42px;
            background: linear-gradient(145deg, #1f4570, #143052);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: white;
            border: 2px solid #3f74b3;
        }

        .member-info h4 {
            font-weight: 600;
            color: white;
            margin-bottom: 4px;
        }

        .member-info p {
            font-size: 0.75rem;
            color: #82a1cf;
        }

        .member-status {
            margin-left: auto;
            background: rgba(45, 212, 191, 0.15);
            color: #5fd9cf;
            padding: 5px 12px;
            border-radius: 40px;
            font-size: 0.7rem;
            font-weight: 600;
            border: 1px solid rgba(45, 212, 191, 0.3);
        }

        /* Footer simples */
        .footer {
            margin-top: 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: #59779e;
            font-size: 0.85rem;
            border-top: 1px solid rgba(70, 130, 230, 0.15);
            padding-top: 20px;
        }

        .footer-links {
            display: flex;
            gap: 30px;
        }

        .footer-links span {
            cursor: pointer;
        }

        .footer-links span:hover {
            color: #b3ceff;
        }

        /* Responsividade */
        @media (max-width: 1000px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .charts-section {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 700px) {
            .dashboard {
                padding: 18px;
            }
            .header {
                flex-direction: column;
                align-items: flex-start;
            }
            .nav-menu {
                flex-wrap: wrap;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .bottom-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Header -->
        <div class="header">
            <div class="logo-area">
                <div class="logo-icon">
                    <i class="fas fa-church"></i>
                </div>
                <div class="logo-text">
                    <h1>Igreja Vida Nova</h1>
                    <p>Administração eclesiástica</p>
                </div>
            </div>

            <div class="nav-menu">
                <div class="nav-item active">
                    <i class="fas fa-home"></i> Início
                </div>
                <div class="nav-item">
                    <i class="fas fa-calendar-alt"></i> Eventos
                </div>
                <div class="nav-item">
                    <i class="fas fa-users"></i> Membros
                </div>
                <div class="nav-item">
                    <i class="fas fa-chart-line"></i> Relatórios
                </div>
                <div class="nav-item">
                    <i class="fas fa-cog"></i> Config.
                </div>
            </div>

            <div class="user-profile">
                <div class="notification-badge">
                    <i class="far fa-bell"></i>
                </div>
                <div class="avatar">
                    <div class="avatar-img">PS</div>
                    <span class="avatar-name">Pastor Samuel</span>
                    <i class="fas fa-chevron-down" style="font-size: 0.8rem; color: #7c9bd4;"></i>
                </div>
            </div>
        </div>

        <!-- Cards de estatísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">Membros ativos</span>
                    <div class="stat-icon"><i class="fas fa-user-friends"></i></div>
                </div>
                <div class="stat-value">1.248</div>
                <div class="stat-trend">
                    <span class="trend-up"><i class="fas fa-arrow-up"></i> +12%</span>
                    <span>último mês</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">Visitantes</span>
                    <div class="stat-icon"><i class="fas fa-user-plus"></i></div>
                </div>
                <div class="stat-value">89</div>
                <div class="stat-trend">
                    <span class="trend-up"><i class="fas fa-arrow-up"></i> +5%</span>
                    <span>esta semana</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">Dízimos/OF</span>
                    <div class="stat-icon"><i class="fas fa-hand-holding-heart"></i></div>
                </div>
                <div class="stat-value">R$ 24,8k</div>
                <div class="stat-trend">
                    <span class="trend-up"><i class="fas fa-arrow-up"></i> +18%</span>
                    <span>vs mês anterior</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">Escola Bíblica</span>
                    <div class="stat-icon"><i class="fas fa-bible"></i></div>
                </div>
                <div class="stat-value">342</div>
                <div class="stat-trend">
                    <span class="trend-down"><i class="fas fa-arrow-down"></i> -3%</span>
                    <span>esta semana</span>
                </div>
            </div>
        </div>

        <!-- Gráficos e atividades recentes -->
        <div class="charts-section">
            <div class="chart-card">
                <div class="chart-header">
                    <h3>Frequência nos cultos</h3>
                    <div class="chart-legend">
                        <div class="legend-item">
                            <span class="legend-color color-blue"></span>
                            <span>2024</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color color-cyan"></span>
                            <span>2023</span>
                        </div>
                    </div>
                </div>
                <div class="bar-chart">
                    <div class="bar-container">
                        <div class="bar" style="height: 140px;"></div>
                        <div class="bar secondary" style="height: 110px;"></div>
                        <span class="bar-label">Jan</span>
                    </div>
                    <div class="bar-container">
                        <div class="bar" style="height: 160px;"></div>
                        <div class="bar secondary" style="height: 125px;"></div>
                        <span class="bar-label">Fev</span>
                    </div>
                    <div class="bar-container">
                        <div class="bar" style="height: 190px;"></div>
                        <div class="bar secondary" style="height: 150px;"></div>
                        <span class="bar-label">Mar</span>
                    </div>
                    <div class="bar-container">
                        <div class="bar" style="height: 170px;"></div>
                        <div class="bar secondary" style="height: 140px;"></div>
                        <span class="bar-label">Abr</span>
                    </div>
                    <div class="bar-container">
                        <div class="bar" style="height: 200px;"></div>
                        <div class="bar secondary" style="height: 160px;"></div>
                        <span class="bar-label">Mai</span>
                    </div>
                    <div class="bar-container">
                        <div class="bar" style="height: 185px;"></div>
                        <div class="bar secondary" style="height: 155px;"></div>
                        <span class="bar-label">Jun</span>
                    </div>
                    <div class="bar-container">
                        <div class="bar" style="height: 130px;"></div>
                        <div class="bar secondary" style="height: 105px;"></div>
                        <span class="bar-label">Jul</span>
                    </div>
                </div>
            </div>

            <div class="chart-card">
                <div class="chart-header">
                    <h3>Atividades recentes</h3>
                    <i class="fas fa-ellipsis-h" style="color: #7b99cc; cursor: pointer;"></i>
                </div>
                <div class="activity-list">
                    <div class="activity-item">
                        <div class="activity-icon"><i class="fas fa-user-baptism"></i></div>
                        <div class="activity-details">
                            <div class="activity-title">Batismo - 5 novos membros</div>
                            <div class="activity-time">Hoje, 10:30</div>
                        </div>
                        <div class="activity-badge">Novo</div>
                    </div>
                    <div class="activity-item">
                        <div class="activity-icon"><i class="fas fa-pray"></i></div>
                        <div class="activity-details">
                            <div class="activity-title">Reunião de oração</div>
                            <div class="activity-time">Ontem, 19:00</div>
                        </div>
                        <div class="activity-badge">38 presentes</div>
                    </div>
                    <div class="activity-item">
                        <div class="activity-icon"><i class="fas fa-hand-holding-heart"></i></div>
                        <div class="activity-details">
                            <div class="activity-title">Ação social - arrecadação</div>
                            <div class="activity-time">12 jun, 14:15</div>
                        </div>
                        <div class="activity-badge">Concluído</div>
                    </div>
                    <div class="activity-item">
                        <div class="activity-icon"><i class="fas fa-music"></i></div>
                        <div class="activity-details">
                            <div class="activity-title">Ensaio do ministério</div>
                            <div class="activity-time">11 jun, 20:00</div>
                        </div>
                        <div class="activity-badge">28 músicos</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Próximos eventos e novos membros -->
        <div class="bottom-grid">
            <div class="info-card">
                <div class="info-header">
                    <h3>📅 Próximos eventos</h3>
                    <span class="info-link">Ver todos</span>
                </div>
                <div class="event-item">
                    <div class="event-date">
                        <div class="event-day">18</div>
                        <div class="event-month">Jun</div>
                    </div>
                    <div class="event-info">
                        <h4>Culto de jovens</h4>
                        <p><i class="far fa-clock" style="margin-right: 5px;"></i>19:30 - Auditório</p>
                    </div>
                </div>
                <div class="event-item">
                    <div class="event-date">
                        <div class="event-day">22</div>
                        <div class="event-month">Jun</div>
                    </div>
                    <div class="event-info">
                        <h4>Escola de Líderes</h4>
                        <p><i class="far fa-clock" style="margin-right: 5px;"></i>09:00 - Sala 3</p>
                    </div>
                </div>
                <div class="event-item">
                    <div class="event-date">
                        <div class="event-day">25</div>
                        <div class="event-month">Jun</div>
                    </div>
                    <div class="event-info">
                        <h4>Confraternização</h4>
                        <p><i class="far fa-clock" style="margin-right: 5px;"></i>18:00 - Área externa</p>
                    </div>
                </div>
            </div>

            <div class="info-card">
                <div class="info-header">
                    <h3>👥 Novos membros</h3>
                    <span class="info-link">Ver todos</span>
                </div>
                <div class="member-item">
                    <div class="member-avatar">AM</div>
                    <div class="member-info">
                        <h4>Ana Maria</h4>
                        <p>Entrou em 10 jun 2024</p>
                    </div>
                    <div class="member-status">Novo</div>
                </div>
                <div class="member-item">
                    <div class="member-avatar">CL</div>
                    <div class="member-info">
                        <h4>Carlos Lima</h4>
                        <p>Entrou em 8 jun 2024</p>
                    </div>
                    <div class="member-status">Batizado</div>
                </div>
                <div class="member-item">
                    <div class="member-avatar">JS</div>
                    <div class="member-info">
                        <h4>Juliana Santos</h4>
                        <p>Entrou em 5 jun 2024</p>
                    </div>
                    <div class="member-status">Transferência</div>
                </div>
                <div class="member-item">
                    <div class="member-avatar">PF</div>
                    <div class="member-info">
                        <h4>Paulo Felipe</h4>
                        <p>Entrou em 3 jun 2024</p>
                    </div>
                    <div class="member-status">Visitante</div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <span>© 2024 Igreja Vida Nova - Sistema de Gestão Eclesiástica</span>
            <div class="footer-links">
                <span>Suporte</span>
                <span>Privacidade</span>
                <span>Termos</span>
            </div>
        </div>
    </div>
</body>
</html>