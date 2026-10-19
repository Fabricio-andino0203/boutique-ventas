# Desplegar BOUTIQUE VENTAS a Railway

Guía para subir la app a producción vos mismo.

- **Sitio en vivo:** https://boutique-ventas-production.up.railway.app (panel en `/tienda`)
- **Proyecto Railway:** `boutique-ventas` · entorno `production`
- **Base de datos:** Supabase (PostgreSQL), región **us-west-2**. La app en Railway debe estar en **US West** (misma región, si no se pone lento).

---

## 1) Una sola vez por computadora

1. Tener la CLI de Railway (ya instalada): `npm i -g @railway/cli`
2. Iniciar sesión (abre el navegador):
   ```bash
   railway login
   ```
3. Vincular el proyecto si hiciera falta (normalmente ya está):
   ```bash
   railway link
   ```
   Elegí: workspace de tu cuenta → `boutique-ventas` → entorno `production` → servicio `boutique-ventas`.

Verificá con `railway status` que diga `Project: boutique-ventas`, `Environment: production` y `region: US West`.

---

## 2) Desplegar (cada vez que quieras subir cambios)

Desde la raíz del proyecto (`C:\Users\Fabri\Desktop\ANDINO SOTFWARE\BOUTIQUE`):

```bash
railway up --detach
```

Eso:
- Sube el **código local** (no depende de GitHub).
- Reconstruye la imagen Docker (compila los assets con Vite + FrankenPHP/PHP 8.4).
- Al arrancar el contenedor, el `entrypoint` corre **solo**:
  - `php artisan migrate --force`  ← aplica las migraciones nuevas en Supabase
  - cachea config/rutas/vistas y hace `storage:link`

El comando imprime un link al deploy en el panel de Railway para seguirlo.

---

## 3) Monitorear el deploy

```bash
railway logs --build     # avance de la construcción (Docker/Vite)
railway logs             # arranque de la app: migraciones y FrankenPHP
railway status           # estado (● Online), región y deployment ID
```

Railway mantiene la versión anterior en línea hasta que la nueva pasa el chequeo de salud; recién ahí hace el cambio. Un deploy tarda unos minutos.

**Qué buscar en `railway logs`:**
- `... create_xxx_table ... DONE` (migraciones aplicadas) o `Nothing to migrate.`
- `FrankenPHP started 🐘`
- que NO haya `error` ni `Exception`.

---

## 4) Verificar que quedó bien

- Abrí https://boutique-ventas-production.up.railway.app/tienda/login
- Chequeo rápido por consola (deberían dar `200`):
  ```bash
  curl -s -o /dev/null -w "%{http_code}\n" https://boutique-ventas-production.up.railway.app/tienda/login
  ```

---

## Notas importantes

- **`git push` es aparte del deploy.** `railway up` sube el código local, así que la app se actualiza aunque no hayas hecho push. Pero conviene respaldar en GitHub:
  ```bash
  git push origin main
  ```
  (En la sesión de Claude el push lo bloquea un filtro de seguridad —falso positivo—, por eso lo hacés vos.)
- **Región:** app y Supabase SIEMPRE en US West. Si notás lentitud, revisá `railway status`.
- **Credenciales de producción** (APP_KEY, datos de Supabase, etc.) viven en las **Variables** del servicio en Railway, no en tu `.env` local (ese es solo para pruebas con SQLite).
- **Migraciones:** son automáticas al desplegar y aditivas. Si una falla, se ve en `railway logs` y el deploy no reemplaza la versión anterior.
- **Puerto:** FrankenPHP escucha en 8080 (el dominio ya está configurado para ese puerto).

## IDs (referencia)

- Project ID: `c38e7cd1-3ef6-4e50-8c28-90fd09a6ac7d`
- Service ID: `e07ce19f-4c37-4bab-9df9-c247c79a1d35` (entorno `production`)
