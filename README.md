# Agenda PHP Backend

Migración 1:1 del backend FastAPI (Python) a PHP 8.1+ con PDO puro.

## Estructura (espejo de FastAPI)

```
agenda-php/
├── public/
│   ├── index.php          ← Entry point único (equivale a uvicorn)
│   └── .htaccess          ← Rewrite rules Apache
├── app/
│   ├── main.php           ← Router (equivale a app/main.py)
│   ├── api/
│   │   ├── deps.php       ← Middleware auth (equivale a deps.py)
│   │   └── v1/
│   │       ├── AuthController.php
│   │       ├── ClienteController.php
│   │       ├── TecnicoController.php
│   │       └── TurnoController.php
│   ├── core/
│   │   ├── Security.php   ← JWT + hashing (equivale a core/security.py)
│   │   ├── Response.php   ← JSONResponse helper
│   │   └── Validator.php  ← Validación (equivale a schemas Pydantic)
│   ├── db/
│   │   └── session.php    ← PDO singleton (equivale a db/session.py)
│   ├── repositories/      ← Queries SQL (equivale a los modelos SQLAlchemy)
│   │   ├── UserRepository.php
│   │   ├── ClienteRepository.php
│   │   ├── TecnicoRepository.php
│   │   └── TurnoRepository.php
│   └── services/          ← Lógica de negocio
│       ├── TecnicoService.php
│       └── TurnoService.php
├── config/
│   └── env.php            ← Configuración centralizada
├── database/
│   └── migrations/
│       └── 001_create_tables.sql
├── .env.example
├── composer.json
└── nginx.conf
```

## Instalación

### 1. Clonar y configurar entorno

```bash
cp .env.example .env
# Editar .env con tus datos de DB y JWT_SECRET
```

### 2. Instalar dependencias (solo firebase/php-jwt)

```bash
composer install
```

### 3. Crear base de datos MySQL

En PHPMyAdmin o CLI:
```sql
CREATE DATABASE agenda CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Luego importar:
```bash
mysql -u root -p agenda < database/migrations/001_create_tables.sql
```

### 4. Configurar servidor web

**Apache** (XAMPP/WAMP): apuntar DocumentRoot a `public/` y habilitar `mod_rewrite`.

**Nginx**: usar el `nginx.conf` incluido.

**PHP Built-in** (desarrollo local):
```bash
php -S localhost:8000 -t public/
```

## Endpoints (idénticos al FastAPI original)

### Auth
| Método | Ruta | Descripción |
|--------|------|-------------|
| POST | `/api/v1/auth/login` | Login → JWT |
| POST | `/api/v1/auth/register` | Registro |
| POST | `/api/v1/auth/forgot-password` | Solicitar reset |
| POST | `/api/v1/auth/reset-password` | Resetear contraseña |
| GET  | `/api/v1/auth/me` | Usuario actual 🔒 |

### Clientes 🔒
| Método | Ruta | Descripción |
|--------|------|-------------|
| GET    | `/api/v1/clientes` | Listar |
| GET    | `/api/v1/clientes/search?q=...` | Buscar |
| GET    | `/api/v1/clientes/{id}` | Ver uno |
| POST   | `/api/v1/clientes` | Crear |
| PUT    | `/api/v1/clientes/{id}` | Actualizar |
| DELETE | `/api/v1/clientes/{id}` | Eliminar 👑 |

### Técnicos
| Método | Ruta | Descripción |
|--------|------|-------------|
| GET    | `/api/v1/tecnicos` | Listar |
| POST   | `/api/v1/tecnicos` | Crear 👑 |
| PUT    | `/api/v1/tecnicos/{id}` | Actualizar 👑 |
| DELETE | `/api/v1/tecnicos/{id}` | Eliminar 👑 |
| GET    | `/api/v1/tecnicos/{id}/disponibilidad` | Ver horarios |
| POST   | `/api/v1/tecnicos/{id}/disponibilidad` | Agregar horario 👑 |
| DELETE | `/api/v1/tecnicos/{id}/disponibilidad/{dispId}` | Eliminar horario 👑 |
| GET    | `/api/v1/tecnicos/{id}/slots?fecha=YYYY-MM-DD` | Slots libres |

### Turnos 🔒
| Método | Ruta | Descripción |
|--------|------|-------------|
| GET    | `/api/v1/turnos` | Listar (con filtros) |
| GET    | `/api/v1/turnos/cliente/{clienteId}` | Por cliente |
| GET    | `/api/v1/turnos/{id}` | Ver uno |
| POST   | `/api/v1/turnos` | Crear (valida disponibilidad) |
| PATCH  | `/api/v1/turnos/{id}/cancelar` | Cancelar |
| PATCH  | `/api/v1/turnos/{id}/confirmar` | Confirmar 👑 |
| PATCH  | `/api/v1/turnos/{id}/estado` | Cambiar estado 👑 |

🔒 = Requiere JWT | 👑 = Solo admin

## Cambiar a PostgreSQL (Neon)

Solo modificar en `.env`:
```env
DB_HOST=ep-xxx.us-east-1.aws.neon.tech
DB_PORT=5432
DB_NAME=neondb
DB_USER=tu_user
DB_PASSWORD=tu_password
```

Y en `config/env.php` cambiar `'driver' => 'pgsql'`.
El resto del código **no cambia** gracias a PDO.
