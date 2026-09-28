# Hábitos

App personal (un solo usuario) para registrar hábitos, planear el día siguiente
y ver el progreso. Laravel 11 · PHP 8.2+ · MySQL/MariaDB · Blade + Alpine + Tailwind.

- [Qué hace](#qué-hace)
- [Desarrollo local](#desarrollo-local)
- [Despliegue en InfinityFree](#despliegue-en-infinityfree)
- [Instalar en el móvil (PWA)](#instalar-en-el-móvil-pwa)
- [Informe para IA y respaldo](#informe-para-ia-y-respaldo)
- [Actualizar una instalación existente](#actualizar-una-instalación-existente)
- [Otro hosting o VPS](#otro-hosting-o-vps)
- [Problemas frecuentes](#problemas-frecuentes)

---

## Qué hace

| Pantalla | Para qué |
|---|---|
| **Hoy** (`/`) | Marcar hábitos con un toque, nota opcional por registro, prioridades del día (cumplida / no cumplida). Con ‹ se puede corregir hasta 6 días atrás. |
| **Mañana** | Hasta 3 prioridades para el día siguiente; al otro día aparecen en Hoy. |
| **Progreso** | % por hábito (7 y 30 días), rachas actual y mejor, calendario de 30 días, cumplimiento por día de la semana y retroalimentación automática por reglas. |
| **Generar informe** (desde Progreso) | Markdown de 7, 14 o 30 días listo para copiar y pegar en un chat con una IA; termina con la pregunta de análisis. También se puede descargar como `.md`. |
| **Hábitos** | Crear, editar, archivar, restaurar, eliminar. Diario o N veces por semana. |
| **Cuenta** | Perfil, contraseña, **exportar todos los datos en JSON**, cerrar sesión. |

Reglas de cálculo (en `app/Services/HabitStatsService.php`):
- Diario: un día pasado sin marcar = no cumplido; hoy solo cuenta si ya está marcado.
- Semanal: se evalúa por semana ISO (lunes a domingo); la semana en curso y la
  semana parcial en que empezó el hábito solo cuentan si ya se cumplieron.
- Nada anterior a la fecha "Cuenta desde" del hábito se evalúa.

---

## Desarrollo local

Requisitos: PHP 8.2+, Composer, Node 18+, MySQL 5.7+ o MariaDB 10.3+.

```bash
cd habit-tracker
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Crea las bases de datos (la de tests es aparte porque se vacía en cada corrida):

```sql
CREATE DATABASE habitos      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE habitos_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Ajusta `DB_USERNAME` / `DB_PASSWORD` en `.env` y luego:

```bash
php artisan migrate
npm run dev          # en otra terminal: php artisan serve
```

Abre http://localhost:8000/register y crea tu usuario. Después de eso el
registro queda cerrado (404): la app es de un solo usuario.

Para probar las pantallas con historial (60 días de registros con patrones,
prioridades y notas) en tu BD **local**:

```bash
php artisan db:seed --class=DemoSeeder   # borra y regenera los hábitos demo del primer usuario
```

### Tests

```bash
php artisan test
```

Usan MySQL (`habitos_test`, ver `phpunit.xml`) con las credenciales de tu `.env`.

### Zona horaria

Los timestamps se guardan en UTC (`APP_TIMEZONE=UTC`). Las fechas de los
registros (qué es "hoy", rachas, semanas) se calculan en
`HABITS_TIMEZONE=America/Caracas`. El día cambia a medianoche local; la semana
va de lunes a domingo.

---

## Despliegue en InfinityFree

InfinityFree tiene tres restricciones que obligan a cambiar la estructura
estándar de Laravel:

| Restricción | Solución |
|---|---|
| `open_basedir`: PHP no puede leer nada fuera de `htdocs/` | Todo el proyecto va **dentro** de `htdocs/`: el contenido de `public/` en la raíz y el resto en `htdocs/laravel/`, bloqueado por `.htaccess` |
| Sin symlinks (`storage:link`) | La app no sube archivos de usuario; no hace falta |
| Sin SSH ni artisan en el servidor | `vendor/`, la caché de paquetes y el `APP_KEY` se generan en local; las migraciones se importan como SQL en phpMyAdmin |

La estructura estándar se mantiene en el repo (artisan funciona en local).
El comando `php artisan deploy:build` arma la carpeta adaptada:

```
build/infinityfree/htdocs/
├── .htaccess          ← reescritura a index.php + bloquea /laravel y archivos .*
├── index.php          ← front controller que carga laravel/ y fija public_path
├── build/             ← CSS/JS compilados por Vite
├── pwa/, manifest.webmanifest, sw.js, offline.html   ← PWA
├── favicon.ico, robots.txt
└── laravel/
    ├── .htaccess      ← "Require all denied" (segunda capa)
    ├── .env           ← copia de tu .env.production
    ├── app/ bootstrap/ config/ database/ resources/views/ routes/
    ├── storage/       ← solo la estructura de carpetas
    └── vendor/        ← composer install --no-dev
```

Son ~7.000 archivos y ~45 MB; el límite del plan gratis es 30.000 inodes.

### 1. Preparar la cuenta en InfinityFree

1. Crea la cuenta de hosting y anota del panel (**Account Details**):
   - FTP hostname (normalmente `ftpupload.net`), usuario (`if0_XXXXXXXX`) y contraseña.
2. **PHP version**: en el panel (Account → Settings / PHP Version) elige
   **8.2 u 8.3**. El `composer.lock` está resuelto para PHP 8.2, así que
   funciona en ambas.
3. **MySQL Databases** → crea una base, por ejemplo `habitos`. Anota:
   - MySQL hostname (`sqlXXX.infinityfree.com`, **no** es `localhost`)
   - Nombre completo de la base (`if0_XXXXXXXX_habitos`)
   - Usuario (`if0_XXXXXXXX`) y contraseña (la del panel)
4. **SSL/TLS**: solicita e instala el certificado gratuito para tu dominio.
   Hasta que esté activo usa `http://` en `APP_URL` y `APP_FORCE_HTTPS=false`.

### 2. Crear las tablas (phpMyAdmin, sin artisan)

El SQL ya está generado en [`deploy/sql/schema.sql`](deploy/sql/schema.sql).
Incluye las tablas y las filas de `migrations`, para que el servidor quede
en el mismo estado que un `php artisan migrate`.

1. Panel → MySQL Databases → **phpMyAdmin** junto a tu base.
2. Selecciona la base `if0_XXXXXXXX_habitos` en la columna izquierda.
3. Pestaña **Importar** → elige `deploy/sql/schema.sql` → formato SQL → **Continuar**.
4. Verifica que aparezcan: `users`, `habits`, `habit_logs`, `daily_priorities`,
   `migrations`, `sessions`, `cache`, `jobs`, etc.

Si cambiaste migraciones, regenera el archivo antes de importarlo:

```bash
php artisan deploy:sql --all --output=deploy/sql/schema.sql
```

(`deploy:sql --all` no se conecta a la BD; solo traduce las migraciones a SQL
de MySQL. Un test verifica que `schema.sql` esté al día.)

### 3. Configurar el `.env` de producción (en local)

```bash
cp deploy/infinityfree/env.example .env.production
php artisan key:generate --show      # copia la salida en APP_KEY=
```

Edita `.env.production`:

```dotenv
APP_KEY=base64:...                     # salida del comando anterior
APP_DEBUG=false
APP_URL=https://tu-subdominio.infinityfreeapp.com
APP_FORCE_HTTPS=true                   # false si aún no tienes SSL
DB_HOST=sqlXXX.infinityfree.com
DB_DATABASE=if0_XXXXXXXX_habitos
DB_USERNAME=if0_XXXXXXXX
DB_PASSWORD=tu_contraseña_del_panel
SESSION_SECURE_COOKIE=true             # false si aún no tienes SSL
```

`.env.production` está en `.gitignore`. **Nunca lo subas al repo.**

> Guarda el `APP_KEY`. Si lo cambias después, se invalidan las sesiones y
> cookies "recordarme" (no se pierden datos: la app no cifra columnas).

### 4. Generar la carpeta de despliegue (en local)

```bash
npm ci
npm run build                 # genera public/build
php artisan deploy:build      # genera build/infinityfree/htdocs
```

`deploy:build` verifica que exista `public/build`, que no esté corriendo
`npm run dev` (`public/hot`) y que el `APP_KEY` no esté vacío. Además:

- copia el código y ejecuta `composer install --no-dev --prefer-dist --optimize-autoloader`
  dentro de la carpeta de salida (tu `vendor/` local, con paquetes de
  desarrollo, no se toca);
- regenera `bootstrap/cache/packages.php` sin paquetes de desarrollo
  (subir el de tu máquina rompe la app: referencia Breeze, Pail, Collision…);
- **no** genera `config:cache` ni `route:cache`, porque guardan rutas
  absolutas de tu máquina que no existen en el servidor.

### 5. Subir por FTP

Con FileZilla (u otro cliente):

1. **Servidor → Forzar mostrar archivos ocultos**. Sin esto, `.htaccess` y
   `.env` pueden no subirse.
2. **Edición → Opciones → Transferencias → máximo de transferencias
   simultáneas: 2**. InfinityFree corta con `421 Too many connections` si
   abres más.
3. Conéctate: host `ftpupload.net`, puerto `21`, usuario y contraseña del panel.
4. En el servidor, entra a `htdocs/` y **borra** los archivos de ejemplo
   (`index2.html` o similares).
5. En local, abre `build/infinityfree/htdocs/`, selecciona **todo su
   contenido** (incluidos `.htaccess` y `laravel/`) y arrástralo a `htdocs/`
   del servidor. No subas la carpeta `htdocs` en sí, porque quedaría
   `htdocs/htdocs/`.
6. La primera subida tarda (unos 7.000 archivos, la mayoría de `vendor/`). Al
   terminar, revisa la cola de **Transferencias fallidas** de FileZilla y
   reintenta esas; un archivo faltante en `vendor/` provoca un error 500.

Resultado esperado en el servidor:

```
htdocs/.htaccess
htdocs/index.php
htdocs/build/manifest.json
htdocs/laravel/.env
htdocs/laravel/.htaccess
htdocs/laravel/vendor/autoload.php
...
```

### 6. Verificar

1. `https://tu-dominio/` → pantalla de inicio de sesión.
2. `https://tu-dominio/laravel/.env` → **403**. Si ves el contenido, el
   `.htaccess` no se subió: revisa el paso 5.1 y cambia `DB_PASSWORD` y
   `APP_KEY` de inmediato.
3. `https://tu-dominio/register` → crea tu usuario. Después de eso devuelve 404.

---

## Instalar en el móvil (PWA)

Requiere HTTPS (paso 1.4 del despliegue); sin HTTPS el navegador no registra
el service worker ni ofrece instalar.

- **Android (Chrome):** abre la app, menú ⋮ → **Instalar app** (o "Agregar a
  pantalla de inicio"). Mantener presionado el icono muestra accesos directos
  a *Planear mañana* y *Progreso*.
- **iPhone (Safari):** botón Compartir → **Agregar a pantalla de inicio**.
  (En iOS solo Safari puede instalar PWAs.)

Se abre a pantalla completa, sin barra del navegador, directo en **Hoy**.
Marca "Mantener sesión iniciada" al entrar para no tener que loguearte cada vez.

Qué hace el service worker (`public/sw.js`):
- **No cachea páginas HTML**: siempre pide la versión fresca, porque llevan tus
  datos del día y el token CSRF (una copia vieja causaría errores 419).
- Cachea CSS/JS de `build/assets` (llevan hash, son inmutables) e iconos, para
  que la app abra rápido aunque el hosting sea lento.
- Sin conexión muestra `offline.html`. Los registros **no** se guardan offline:
  si tocas un hábito sin red, la app avisa y revierte el cambio.
- Solo guarda respuestas con el tipo de contenido esperado. Así no cachea por
  error la página del "security system" de InfinityFree (desafío JS/cookie),
  que responde HTML en lugar del archivo pedido.

---

## Informe para IA y respaldo

- **Progreso → Generar informe** → elige 7, 14 o 30 días → **Copiar al
  portapapeles** → pega en tu chat con la IA. Incluye: rango, cada hábito con %,
  rachas, detalle semanal, patrón por día de la semana, prioridades cumplidas /
  no cumplidas / sin marcar, tus notas y la pregunta final.
- **Cuenta → Exportar datos (JSON)** descarga `habitos-respaldo-AAAA-MM-DD.json`
  con hábitos (incluidos archivados), todos los registros con notas y las
  prioridades. Hazlo antes de cambios grandes. El formato lleva
  `format_version: 1`; la importación desde la app aún no existe (ver
  ROADMAP). Mientras tanto el respaldo completo "restaurable" es exportar la BD
  desde phpMyAdmin (pestaña **Exportar** → SQL).

---

## Actualizar una instalación existente

1. **Si hay migraciones nuevas**, genera solo el SQL pendiente. Tu BD local
   debe estar en el mismo punto que producción: no corras `migrate` en local
   antes de exportar.

   ```bash
   php artisan deploy:sql --output=deploy/sql/update.sql   # solo las pendientes
   php artisan migrate                                      # ahora sí, en local
   ```

   Importa `update.sql` en phpMyAdmin **antes** de subir el código nuevo.
   El archivo también registra las migraciones en la tabla `migrations`,
   con el siguiente número de batch.

2. Regenera y sube:

   ```bash
   npm run build && php artisan deploy:build
   ```

   Qué subir según lo que cambió:

   | Cambio | Subir |
   |---|---|
   | PHP / vistas / rutas / config | `laravel/app`, `laravel/resources`, `laravel/routes`, `laravel/config` |
   | CSS / JS | `build/` (borra primero la vieja en el servidor) |
   | PWA (iconos, manifest, service worker) | `pwa/`, `manifest.webmanifest`, `sw.js`, `offline.html` |
   | `composer.lock` | `laravel/vendor` completo y `laravel/bootstrap/cache/packages.php` |
   | `.env.production` | `laravel/.env` |

   No subas `laravel/storage/` completo en actualizaciones, porque pisarías
   las sesiones y los logs. Solo súbelo si agregaste carpetas nuevas.

3. **Modo mantenimiento sin artisan** (opcional, para cambios grandes):
   sube por FTP un archivo `htdocs/laravel/storage/framework/down` cuyo
   contenido sea exactamente `{}` y bórralo al terminar. Laravel responde 503
   mientras exista. **Vacío no sirve: da error 500.**

---

## Otro hosting o VPS

Con SSH y PHP 8.2+ no hace falta `deploy:build` ni el SQL manual; se usa la
estructura estándar de Laravel:

```bash
git clone <repo> && cd habit-tracker
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env          # APP_ENV=production, APP_DEBUG=false, APP_URL, DB_*
php artisan key:generate
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

- El *document root* del sitio (Nginx/Apache) debe ser `habit-tracker/public`.
- Permisos de escritura para el usuario del servidor web en `storage/` y
  `bootstrap/cache/`.
- HTTPS con Let's Encrypt (`certbot`). Si hay un proxy/balanceador delante,
  usa `APP_FORCE_HTTPS=true` o configura *trusted proxies*.
- No necesita cron ni colas.

---

## Problemas frecuentes

**Error 500 sin detalles.** Mira `htdocs/laravel/storage/logs/laravel-AAAA-MM-DD.log`
(descárgalo por FTP). Solo mientras depuras, pon `APP_DEBUG=true` en
`htdocs/laravel/.env`, y vuelve a `false` en cuanto termines.

**La página carga sin estilos / "Mixed content" en la consola.** El SSL de
InfinityFree termina antes de PHP, así que Laravel cree que la petición es
`http`. Usa `APP_FORCE_HTTPS=true` y `APP_URL` con `https://`.

**`Vite manifest not found`.** Falta `htdocs/build/manifest.json`: corre
`npm run build`, vuelve a ejecutar `deploy:build` y sube `build/`.

**`Class "Laravel\Pail\PailServiceProvider" not found`** (o Breeze, Collision…).
Se subió el `bootstrap/cache/packages.php` de desarrollo. Sube el que genera
`deploy:build` y borra `services.php` del servidor.

**`SQLSTATE[HY000] [2002]`.** `DB_HOST` debe ser el hostname del panel
(`sqlXXX.infinityfree.com`), no `localhost` ni `127.0.0.1`.

**`Specified key was too long`** al importar. Tu MySQL es anterior a 5.7. El
proyecto ya limita los `varchar` indexados a 191 caracteres
(`Schema::defaultStringLength(191)`). Si el error persiste, regenera
`schema.sql` con la versión actual del proyecto.

**Olvidé la contraseña.** El plan gratis no envía correos. Genera un hash
en local:

```bash
php -r "echo password_hash('nueva-clave', PASSWORD_BCRYPT), PHP_EOL;"
```

y pégalo en la columna `password` de `users` desde phpMyAdmin.

**La app instalada sigue mostrando la versión vieja.** El service worker se
revisa en cada visita (`sw.js` va con `Cache-Control: no-cache`). Cierra y
abre la app. Si cambiaste `sw.js`, sube `VERSION` en ese archivo para
invalidar la caché.

**`/icons/...` da 404.** Muchos Apache traen `Alias /icons/` para el listado
de directorios. Por eso los iconos de la PWA están en `pwa/`. No los muevas a
`icons/`.

**Permisos.** En InfinityFree PHP corre con tu usuario FTP, así que
`storage/` y `bootstrap/cache/` ya son escribibles. En otro hosting, dales
permisos 755 (carpetas) y 644 (archivos).

**Límites del plan gratis.** Hay un tope diario de hits y de CPU. La app usa
sesiones y caché en archivos (`SESSION_DRIVER=file`, `CACHE_STORE=file`)
para no gastar consultas MySQL en cada petición.
