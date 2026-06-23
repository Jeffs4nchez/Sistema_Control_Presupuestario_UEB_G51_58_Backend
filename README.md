<div align="center">

<h1>Sistema de Control Presupuestario UEB</h1>
<h3>API REST &mdash; Backend Laravel 12</h3>

<br/>

<img src="https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat-square&logo=php&logoColor=white"/>
<img src="https://img.shields.io/badge/Laravel-12.x-FF2D20?style=flat-square&logo=laravel&logoColor=white"/>
<img src="https://img.shields.io/badge/PostgreSQL-15+-4169E1?style=flat-square&logo=postgresql&logoColor=white"/>
<img src="https://img.shields.io/badge/Deploy-Railway-0B0D0E?style=flat-square&logo=railway&logoColor=white"/>

<br/><br/>

<p><em>Proyecto de Titulación G51-58 &bull; Universidad Estatal de Bolívar</em></p>

</div>

---

## Descripción

API REST que gestiona el ciclo presupuestario institucional de la UEB: desde la carga de estructura y cédula presupuestaria hasta la certificación, liquidación y auditoría de operaciones financieras. Desarrollada con Laravel 12 e integrada con un frontend React 19.

---

## Stack tecnológico

<table>
  <tr>
    <td align="center" width="120">
      <img src="https://cdn.jsdelivr.net/gh/devicons/devicon@latest/icons/php/php-plain.svg" width="48" height="48"/>
      <br/><strong>PHP 8.2+</strong>
      <br/><sub>Lenguaje backend</sub>
    </td>
    <td align="center" width="120">
      <img src="https://cdn.jsdelivr.net/gh/devicons/devicon@latest/icons/laravel/laravel-original.svg" width="48" height="48"/>
      <br/><strong>Laravel 12</strong>
      <br/><sub>Framework REST</sub>
    </td>
    <td align="center" width="120">
      <img src="https://cdn.jsdelivr.net/gh/devicons/devicon@latest/icons/postgresql/postgresql-plain.svg" width="48" height="48"/>
      <br/><strong>PostgreSQL 15</strong>
      <br/><sub>Base de datos</sub>
    </td>
    <td align="center" width="120">
      <img src="https://cdn.jsdelivr.net/gh/devicons/devicon@latest/icons/composer/composer-original.svg" width="48" height="48"/>
      <br/><strong>Composer</strong>
      <br/><sub>Gestión de dependencias</sub>
    </td>
  </tr>
</table>

La autenticación utiliza un **token personalizado** (`api_token`, `bin2hex + random_bytes`) en lugar de Sanctum o Passport. Las notificaciones se envían por correo vía **SMTP Gmail** con `Laravel Mail`.

---

## Módulos

| Módulo | Descripción |
|:---|:---|
| Autenticación | Login, logout, recuperación y restablecimiento de contraseña |
| Usuarios | CRUD de usuarios, roles y bloqueo por intentos fallidos |
| Estructura Presupuestaria | Importación masiva desde CSV (18+ columnas, 11 entidades encadenadas) |
| Cédula Presupuestaria | Importación y actualización de valores financieros por fuente |
| Certificaciones | Ciclo completo de estados con notificaciones por correo electrónico |
| Liquidaciones | Registro de pagos vinculados, transición automática de estado |
| Presupuesto Disponible | Cálculo de saldo: codificado − reservado − devengado |
| Entidad Requirente | Gestión de entidades solicitantes |
| Reportes | Generación de archivos CSV con patrón Template Method |
| Auditoría | Bitácora automática de operaciones sobre certificaciones |

---

## Ciclo de vida de certificaciones

```
REGISTRADO ──► APROBADO ──► LIQUIDADO  (automático al cubrir 100%)
     │              │
     ▼              ▼
 RECHAZADO       ERRADO
     │
     ▼
REGISTRADO  (reenvío del analista)
```

Los estados RECHAZADO, ERRADO y REGISTRADO no afectan el presupuesto disponible.

---

## Instalación local

<details>
<summary><strong>Ver instrucciones</strong></summary>
<br/>

**Requisitos:** PHP 8.2+, Composer, PostgreSQL 15+

```bash
# 1. Clonar el repositorio
git clone https://github.com/Jeffs4nchez/Sistema_Control_Presupuestario_UEB_G51_58_Backend.git
cd Sistema_Control_Presupuestario_UEB_G51_58_Backend

# 2. Instalar dependencias
composer install

# 3. Configurar entorno
cp .env.example .env
# Editar .env con credenciales de BD y SMTP

# 4. Generar clave de aplicación
php artisan key:generate

# 5. Ejecutar migraciones y seeders
php artisan migrate --seed

# 6. Iniciar servidor
php artisan serve
```

La API queda disponible en `http://localhost:8000`.

</details>

---

## Variables de entorno principales

```env
APP_NAME="Control Presupuestario UEB"
APP_ENV=local
APP_KEY=                          # generada con php artisan key:generate
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=sistema_control_presupuestario_ueb
DB_USERNAME=postgres
DB_PASSWORD=

FRONTEND_URL=http://localhost:5173
CORS_ALLOWED_ORIGINS=http://localhost:5173

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=correo@gmail.com
MAIL_PASSWORD=app_password
```

---

## Endpoints principales

<details>
<summary><strong>Ver lista de endpoints</strong></summary>
<br/>

```
# Autenticación
POST   /api/auth/login
POST   /api/auth/logout
GET    /api/auth/me
POST   /api/auth/forgot-password
POST   /api/auth/reset-password

# Usuarios
GET    /api/usuarios
POST   /api/usuarios
PUT    /api/usuarios/{id}
DELETE /api/usuarios/{id}

# Certificaciones
GET    /api/certificaciones
POST   /api/certificaciones
PUT    /api/certificaciones/{id}
PATCH  /api/certificaciones/{id}/aprobar
PATCH  /api/certificaciones/{id}/rechazar
PATCH  /api/certificaciones/{id}/reenviar
PATCH  /api/certificaciones/{id}/errar

# Presupuesto
POST   /api/estructura-presupuestaria/upload
POST   /api/cedula-presupuestaria/upload
GET    /api/liquidaciones
POST   /api/liquidaciones
GET    /api/presupuesto-disponible
GET    /api/reportes
GET    /api/auditoria
```

Todos los endpoints (salvo login y recuperación) requieren el header:

```
Authorization: Bearer {api_token}
```

</details>

---

## Roles y permisos

| Rol | Permisos |
|:---|:---|
| Administrador del sistema | Acceso total: usuarios, configuración, reportes y aprobaciones |
| Director(a) Financiero/a | Aprobar, rechazar y marcar como errado certificaciones |
| Analista de Presupuesto | Crear y reenviar sus propias certificaciones |
| Director(a) Talento Humano | Solo lectura: Inicio y Reportes |
| Rector | Solo lectura: Inicio y Reportes |

---

## Despliegue en Railway

El proyecto incluye `railway.toml` y `nixpacks.toml` preconfigurados para despliegue automático.

Variables requeridas en Railway:

```
APP_KEY             APP_URL             APP_ENV=production
APP_DEBUG=false     DB_CONNECTION=pgsql
DB_HOST             DB_PORT             DB_DATABASE
DB_USERNAME         DB_PASSWORD
FRONTEND_URL        CORS_ALLOWED_ORIGINS
```

---

## Estructura del proyecto

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/     # 11 controladores REST
│   │   └── Middleware/      # ValidateCustomToken
│   ├── Models/              # 17 modelos Eloquent
│   └── Observers/           # CertificacionObserver, CertificacionItemObserver
├── database/
│   ├── migrations/          # Historial de cambios de BD
│   └── seeders/             # 7 seeders de datos iniciales
└── routes/
    └── api.php              # Definición de rutas
```

---

## Equipo

<div align="center">
<br/>

| Integrante | Rol |
|:---|:---|
| Jefferson Sanchez | Ingeniero de Software |

<br/>

**Universidad Estatal de Bolívar**  
Facultad de Ciencias Administrativas, Gestión Empresarial e Informática  
Proyecto de Titulación · Grupo G51-58 · 2026

<br/>
</div>
