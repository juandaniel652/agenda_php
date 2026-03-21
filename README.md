# agenda-php

Backend REST API en PHP puro — espejo funcional del proyecto Python/FastAPI original.  
Misma lógica de negocio, mismos endpoints, misma estructura de respuestas. Motor: PHP 8.2 + MySQL 8 + Nginx.

---

## Stack

| Capa | Tecnología |
|---|---|
| Lenguaje | PHP 8.2 |
| Base de datos | MySQL 8 (compatible con el dump `agenda.sql`) |
| Servidor | Nginx (config incluida en `nginx.conf`) |
| Hash de passwords | Argon2ID (`PASSWORD_ARGON2ID`) |
| JWT | `firebase/php-jwt` |
| Email | PHPMailer (SMTP Gmail, puerto 587 STARTTLS) |
| Imágenes | Cloudinary |

---

## Estructura del proyecto

```
agenda_php/
├── app/
│   ├── api/
│   │   ├── deps.php               # Auth middleware (Bearer token → usuario)
│   │   └── v1/
│   │       ├── AuthController.php
│   │       ├── ClienteController.php
│   │       ├── HealthController.php
│   │       ├── TecnicoController.php
│   │       └── TurnoController.php
│   ├── core/
│   │   ├── Email.php              # Envío de emails (reset password)
│   │   ├── Response.php           # Helper JSON responses
│   │   ├── Security.php           # JWT + Argon2ID
│   │   └── Validator.php          # Validación de inputs (equivalente a Pydantic)
│   ├── db/
│   │   └── session.php            # Singleton PDO (MySQL / PostgreSQL)
│   ├── main.php                   # Router principal
│   ├── models/                    # (estructuras manejadas via repositories)
│   ├── repositories/
│   │   ├── ClienteRepository.php
│   │   ├── TecnicoRepository.php
│   │   ├── TurnoRepository.php
│   │   └── UserRepository.php
│   ├── services/
│   │   ├── TecnicoService.php
│   │   └── TurnoService.php
│   └── utils/
│       └── CloudinaryUpload.php
├── config/
│   └── env.php                    # Configuración centralizada (lee variables de entorno)
├── database/
│   └── migrations/
│       └── 001_create_tables.sql  # Schema MySQL
├── public/
│   └── index.php                  # Punto de entrada
├── composer.json
└── nginx.conf
```

---

## Instalación local

### 1. Clonar e instalar dependencias

```bash
git clone <repo>
cd agenda_php
composer install
```

### 2. Variables de entorno

Crear un archivo `.env` en la raíz (o configurar directamente en el servidor):

```env
# Base de datos
DB_DRIVER=mysql
DB_HOST=localhost
DB_PORT=3306
DB_NAME=agenda
DB_USER=root
DB_PASSWORD=tu_password

# JWT
JWT_SECRET=cambia_este_secret_en_produccion
JWT_EXPIRE_MINUTES=60

# App
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost

# CORS (separados por coma)
CORS_ORIGINS=http://localhost:5173,https://tu-frontend.netlify.app

# Email (Gmail)
MAIL_USERNAME=tu@gmail.com
MAIL_PASSWORD=tu_app_password_de_gmail
MAIL_FROM=tu@gmail.com
FRONTEND_URL=https://tu-frontend.com

# Cloudinary
CLOUDINARY_CLOUD_NAME=...
CLOUDINARY_API_KEY=...
CLOUDINARY_API_SECRET=...
```

### 3. Crear la base de datos

```bash
mysql -u root -p agenda < database/migrations/001_create_tables.sql
```

O importar `agenda.sql` directamente desde MySQL Workbench.

### 4. Servidor de desarrollo

Con PHP built-in server:

```bash
php -S localhost:8000 -t public/
```

Con Nginx, usar la configuración provista en `nginx.conf`.

---

## Endpoints

**Base URL:** `http://localhost:8000/api/v1`

### Auth

| Método | Endpoint | Auth | Descripción |
|---|---|---|---|
| POST | `/auth/register` | ❌ | Registrar usuario nuevo |
| POST | `/auth/login` | ❌ | Login → devuelve JWT |
| GET | `/auth/me` | ✅ | Datos del usuario autenticado |
| POST | `/auth/forgot-password` | ❌ | Enviar email de reset |
| POST | `/auth/reset-password` | ❌ | Cambiar password con token |

**Login — body:**
```json
{ "email": "user@example.com", "password": "123456" }
```

**Login — respuesta:**
```json
{
  "access_token": "eyJ...",
  "token_type": "bearer",
  "user": { "id": "uuid", "email": "...", "role": "admin", "is_verified": false }
}
```

---

### Clientes

| Método | Endpoint | Auth | Descripción |
|---|---|---|---|
| GET | `/clientes` | ✅ | Listar clientes (paginado) |
| GET | `/clientes/{id}` | ✅ | Obtener cliente por ID |
| GET | `/clientes/search?q=...` | ✅ | Buscar por nombre, apellido, email, teléfono |
| POST | `/clientes` | ✅ | Crear cliente |
| PUT | `/clientes/{id}` | ✅ | Actualizar cliente (partial update) |
| DELETE | `/clientes/{id}` | ✅ admin | Eliminar cliente |

**Crear cliente — body:**
```json
{
  "numero_cliente": "CLI-001",
  "nombre": "Juan",
  "apellido": "Pérez",
  "telefono": "1122334455",
  "domicilio": "Av. Corrientes",
  "numero_domicilio": 1234,
  "email": "juan@example.com"
}
```

---

### Técnicos

| Método | Endpoint | Auth | Descripción |
|---|---|---|---|
| GET | `/tecnicos` | ✅ admin/user | Listar técnicos activos con horarios |
| POST | `/tecnicos` | ✅ admin | Crear técnico (multipart/form-data) |
| PUT | `/tecnicos/{id}` | ✅ admin | Actualizar técnico (multipart/form-data) |
| DELETE | `/tecnicos/{id}` | ✅ admin | Soft delete (activo = false) |

**Crear/actualizar técnico — form-data:**

| Campo | Tipo | Requerido |
|---|---|---|
| `nombre` | string | ✅ |
| `apellido` | string | ✅ |
| `duracion_turno_min` | integer | ✅ |
| `telefono` | string | ❌ |
| `email` | string | ❌ |
| `imagen` | file | ❌ |
| `horarios` | JSON string | ❌ |

**Ejemplo `horarios` (JSON string en el form):**
```json
[
  { "dia_semana": 1, "hora_inicio": "09:00", "hora_fin": "13:00" },
  { "dia_semana": 3, "hora_inicio": "14:00", "hora_fin": "18:00" }
]
```

> `dia_semana` usa convención JS: 0=Dom, 1=Lun, 2=Mar, 3=Mié, 4=Jue, 5=Vie, 6=Sáb

---

### Turnos

| Método | Endpoint | Auth | Descripción |
|---|---|---|---|
| GET | `/turnos/menu` | ❌ | Menú público con técnicos y próximos slots |
| GET | `/turnos/disponibilidad` | ❌ | Slots disponibles para un técnico en una fecha |
| GET | `/turnos/sugerencias` | ❌ | Próximas fechas disponibles automáticamente |
| GET | `/turnos` | ❌ | Listar turnos (filtrable por fecha) |
| GET | `/turnos/{id}` | ❌ | Obtener turno por ID |
| POST | `/turnos` | ✅ admin | Crear turno |
| PATCH | `/turnos/{id}/estado` | ✅ admin | Cambiar estado del turno |
| PATCH | `/turnos/{id}/cancelar` | ✅ admin | Cancelar turno (soft delete) |

**GET `/turnos/disponibilidad`:**
```
GET /api/v1/turnos/disponibilidad?tecnico_id=UUID&fecha=2026-04-01
```

**GET `/turnos/sugerencias`:**
```
GET /api/v1/turnos/sugerencias?tecnico_id=UUID
```

**GET `/turnos?fecha=2026-04-01`** — filtro opcional por fecha.

**Crear turno — body:**
```json
{
  "numero_ticket": "TK-00001",
  "cliente_id": "uuid-del-cliente",
  "tecnico_id": "uuid-del-tecnico",
  "tipo_turno": 1,
  "rango_horario": "M",
  "fecha": "2026-04-01",
  "hora_inicio": "09:00",
  "hora_fin": "09:30",
  "estado": "Abierto"
}
```

**Estados válidos:** `Abierto` | `Cerrado` | `Reprogramación` | `Cancelado`

---

### Health

| Método | Endpoint | Auth | Descripción |
|---|---|---|---|
| GET | `/health` | ❌ | Estado del servicio |

**Respuesta:** `{ "status": "ok" }`

---

## Autenticación

Todos los endpoints marcados con ✅ requieren header:

```
Authorization: Bearer <token>
```

Los roles disponibles son `admin` y `user`. Los endpoints marcados como **admin** requieren rol admin específicamente.

---

## Lógica de disponibilidad

El sistema calcula slots libres de la siguiente manera:

1. Obtiene los horarios del técnico para el día de la semana de la fecha pedida.
2. Genera slots del tamaño `duracion_turno_min` del técnico dentro de cada franja.
3. Filtra los slots que se solapan con turnos existentes (excluyendo Cancelados).
4. Para `tipo_turno > 1`, devuelve solo los slots que tienen suficientes slots consecutivos disponibles.

---

## Diferencias con la versión Python

Este proyecto es un espejo funcional de `agenda` (Python/FastAPI). Las diferencias intencionales son:

- `GET /clientes` incluye paginación (`skip` / `limit`) — mejora sobre Python.
- `GET /clientes/search` — endpoint adicional no presente en Python.
- `PUT /clientes/{id}` hace partial update — Python reemplazaba todos los campos.
- La sesión de DB usa un singleton PDO en vez de generadores SQLAlchemy.
- El hash de passwords usa `PASSWORD_ARGON2ID` nativo de PHP en lugar de `passlib`.

---

## Notas de producción

- Asegurarse de que `APP_DEBUG=false` en producción para no exponer errores de DB.
- `CORS_ORIGINS` debe listar explícitamente los orígenes permitidos (no usar `*`).
- El campo `JWT_SECRET` debe ser una cadena larga y aleatoria — nunca el valor por defecto.
- Las imágenes se suben a Cloudinary; el directorio `uploads/` local no se usa.