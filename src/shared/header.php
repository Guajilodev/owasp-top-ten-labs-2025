<?php
/**
 * OWASP Top 10 Labs 2025 - Nexo
 * Header compartido
 * 
 * Variables esperadas:
 * - $pageTitle: string - título de la página
 * - $labInfo: array (opcional) - información del lab para el panel lateral
 */

$pageTitle = $pageTitle ?? 'Nexo';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    
    <!-- Estilos custom de Nexo -->
    <style>
        :root {
            --nexo-primary: #0d6efd;
            --nexo-dark: #1a1d23;
            --nexo-light: #f8f9fa;
        }
        
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background-color: var(--nexo-light);
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
        }
        
        main {
            flex: 1;
        }
        
        /* Panel lateral del lab */
        .lab-panel {
            position: fixed;
            right: 0;
            top: 56px;
            width: 420px;
            height: calc(100vh - 56px - 110px); /* Resta navbar (56px) + footer (~110px) */
            background: white;
            border-left: 3px solid #dc3545;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: -4px 0 10px rgba(0,0,0,0.1);
        }
        
        .lab-panel-toggle {
            position: fixed;
            right: 420px;
            top: 70px;
            z-index: 1001;
            background: #dc3545;
            color: white;
            border: none;
            padding: 0.5rem 0.75rem;
            border-radius: 4px 0 0 4px;
            cursor: pointer;
        }
        
        .lab-panel.collapsed {
            transform: translateX(100%);
        }
        
        .lab-panel.collapsed + .lab-panel-toggle {
            right: 0;
        }
        
        /* Ajuste del contenido principal cuando hay panel */
        body.has-lab-panel main {
            margin-right: 420px;
        }
        
        @media (max-width: 991.98px) {
            .lab-panel {
                width: 100%;
                height: 50vh;
                top: auto;
                bottom: 0;
                border-left: none;
                border-top: 3px solid #dc3545;
            }
            
            .lab-panel-toggle {
                right: 10px;
                top: auto;
                bottom: 50vh;
                border-radius: 4px 4px 0 0;
            }
            
            .lab-panel.collapsed {
                transform: translateY(100%);
            }
            
            .lab-panel.collapsed + .lab-panel-toggle {
                bottom: 0;
            }
            
            body.has-lab-panel main {
                margin-right: 0;
                margin-bottom: 50vh;
            }
        }
        
        /* Code blocks */
        pre.vulnerable {
            background: #fff3f3;
            border-left: 4px solid #dc3545;
        }
        
        pre.secure {
            background: #f0fff0;
            border-left: 4px solid #198754;
        }
        
        /* Code blocks en el panel - scroll horizontal */
        .lab-panel pre {
            overflow-x: auto;
            white-space: pre;
            font-size: 0.75rem;
        }
        
        .lab-panel pre code {
            white-space: pre;
        }
    </style>
</head>
<body<?= isset($labInfo) ? ' class="has-lab-panel"' : '' ?>>
    
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="/">
                <span class="me-1">⚡</span>Nexo
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/">Inicio</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            Módulos
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="/a01_facturas/">📄 Mis Facturas</a></li>
                            <li><a class="dropdown-item" href="/a02_admin/">⚙️ Panel Admin</a></li>
                            <li><a class="dropdown-item" href="/a03_plugins/">🔌 Tienda Plugins</a></li>
                            <li><a class="dropdown-item" href="/a04_registro/">👤 Registro</a></li>
                            <li><a class="dropdown-item" href="/a05_clientes/">🔍 Buscador</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/a06_checkout/">🛒 Checkout</a></li>
                            <li><a class="dropdown-item" href="/a07_login/">🔐 Login</a></li>
                            <li><a class="dropdown-item" href="/a08_preferencias/">🧑‍💼 Preferencias</a></li>
                            <li><a class="dropdown-item" href="/a09_actividad/">📊 Actividad</a></li>
                            <li><a class="dropdown-item" href="/a10_pagos/">💳 Transferencias</a></li>
                        </ul>
                    </li>
                </ul>
                
                <div class="navbar-text">
                    <span class="badge bg-danger">OWASP 2025 Labs</span>
                </div>
            </div>
        </div>
    </nav>
