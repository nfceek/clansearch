<?php require_once __DIR__ . '/db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>Battle Council</title>

  <link rel="stylesheet" href="/css/styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>⚔️</text></svg>">
</head>
<body>
  <div class="header-image">
    <img src="/images/site-header-2.png" alt="Battle Council">
  </div>
  <nav class="navbar">
    <div class="nav-inner">
      <a href="/index.php" class="nav-home">
        <i class="fa-solid fa-house"></i>
      </a>

      <button class="menu-toggle" id="menuToggle">
        <i class="fa-solid fa-bars"></i>
      </button>
    </div>

    <div class="mobile-menu" id="mobileMenu">
      <a href="/index.php"><i class="fa-solid fa-house"></i> Home</a>
      <a href="/monster_hunt.php">⚔️ Monster Hunt</a>
      <a href="/monster_editor.php">👹 Monster Editor</a>
      <a href="/squad_editor.php">🪖 Squad Editor</a>
      <a href="/matrix_data.php">🐲 Matrix Data</a>
      <a href="/member_dashboard.php">👥 Members</a>
    </div>
  </nav>

  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('menuToggle');
    const menu = document.getElementById('mobileMenu');

    if (toggle && menu) {
      toggle.addEventListener('click', () => {
        menu.classList.toggle('active');
      });
    }
  });
  </script>