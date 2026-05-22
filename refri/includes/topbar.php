<!-- topbar.php -->
<header class="topbar">
  <div class="topbar-left">
    <button id="menu-toggle" class="menu-btn">
      <span class="bar"></span><span class="bar"></span><span class="bar"></span>
    </button>
    <div class="search-box">
      <input type="text" placeholder="Buscar OS, cliente..." class="search-input" />
    </div>
  </div>
  <div class="topbar-right">
    <div class="user-profile" id="profile-btn">
      <div class="avatar">KY</div>
      <span class="user-name">Admin</span>
    </div>
    <div id="profile-dropdown" class="dropdown hidden">
      <a href="#">Configurações</a>
      <a href="#">Sair</a>
    </div>
  </div>
</header>

<style>
.topbar {
  height: 70px;
  background: #FFFFFF;
  border-bottom: 1px solid #DDE3EA;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 24px;
  position: sticky;
  top: 0;
  z-index: 30;
}

.topbar-left {
  display: flex;
  align-items: center;
  gap: 16px;
}

.menu-btn {
  display: none;
  background: none;
  border: none;
  width: 24px;
  height: 18px;
  flex-direction: column;
  justify-content: space-between;
  cursor: pointer;
}

.menu-btn .bar {
  height: 2px;
  background: #374151;
  border-radius: 1px;
}

@media (max-width: 768px) {
  .menu-btn {
    display: flex;
  }
}

.search-input {
  padding: 8px 14px;
  border: 1px solid #DDE3EA;
  border-radius: 6px;
  width: 280px;
  font-size: 0.9rem;
  outline: none;
  background: #F9FAFB;
}

.search-input:focus {
  border-color: #0F766E;
  background: #fff;
}

.topbar-right {
  position: relative;
}

.user-profile {
  display: flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
}

.avatar {
  width: 36px;
  height: 36px;
  background: #EAF5F3;
  color: #0F766E;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 0.85rem;
}

.user-name {
  font-weight: 500;
  color: #111827;
}

.dropdown {
  position: absolute;
  right: 0;
  top: 110%;
  background: #fff;
  border: 1px solid #E5E7EB;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.04);
  min-width: 160px;
  padding: 8px 0;
  z-index: 50;
}

.dropdown a {
  display: block;
  padding: 10px 20px;
  font-size: 0.9rem;
  color: #374151;
  transition: background 0.15s;
}

.dropdown a:hover {
  background: #F3F6F8;
}

.hidden { display: none; }
</style>